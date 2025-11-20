<?php
require_once '../config/config.php';
validarTipoUsuario(['admin_supremo']);

$titulo = 'Gestión de Estudiantes';
// El include de header se mueve más abajo para permitir redirecciones antes de emitir salida

$pdo = getDBConnection();

$error = '';
$mensaje = '';

// Procesar carga masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv']) && isset($_POST['accion']) && $_POST['accion'] === 'carga_masiva') {
    $archivo = $_FILES['archivo_csv'];
    $carrera_id = $_POST['carrera_id_carga'] ?? null;
    
    if ($archivo['error'] === UPLOAD_ERR_OK && $carrera_id) {
        $tmp_name = $archivo['tmp_name'];
        $handle = fopen($tmp_name, 'r');
        
        if ($handle !== false) {
            try {
                $pdo->beginTransaction();
                $linea = 0;
                $exitosos = 0;
                $errores = 0;
                $errores_detalle = [];
                
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $linea++;
                    if ($linea === 1) continue; // Saltar encabezados
                    
                    if (count($data) >= 4) {
                        $email = trim($data[0]);
                        $nombre = trim($data[1]);
                        $apellido = trim($data[2]);
                        $rut = trim($data[3] ?? '');
                        
                        // Validar correo institucional
                        if (strpos($email, DOMINIO_ESTUDIANTE) !== false) {
                            // Verificar si el usuario existe
                            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                            $stmt->execute([$email]);
                            $usuario = $stmt->fetch();
                            
                            if (!$usuario) {
                                // Crear usuario
                                $password_hash = password_hash('TecUct2024', PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("
                                    INSERT INTO usuarios (email, password, nombre, apellido, rut, tipo, carrera_id, primera_vez) 
                                    VALUES (?, ?, ?, ?, ?, 'estudiante', ?, 1)
                                ");
                                $stmt->execute([$email, $password_hash, $nombre, $apellido, $rut, $carrera_id]);
                                $exitosos++;
                            } else {
                                // Actualizar carrera si es necesario
                                $stmt = $pdo->prepare("UPDATE usuarios SET carrera_id = ? WHERE id = ?");
                                $stmt->execute([$carrera_id, $usuario['id']]);
                                $exitosos++;
                            }
                        } else {
                            $errores++;
                            $errores_detalle[] = "Línea $linea: Email inválido ($email)";
                        }
                    } else {
                        $errores++;
                        $errores_detalle[] = "Línea $linea: Formato incorrecto";
                    }
                }
                
                $pdo->commit();
                $mensaje = "Carga completada: $exitosos estudiantes procesados, $errores errores.";
                if (!empty($errores_detalle) && $errores <= 10) {
                    $mensaje .= "<br><small>Errores: " . implode(', ', $errores_detalle) . "</small>";
                }
                fclose($handle);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error en carga masiva: " . $e->getMessage());
                $error = 'Error al procesar el archivo. Por favor, verifique el formato.';
                if ($handle) fclose($handle);
            }
        } else {
            $error = 'Error al leer el archivo';
        }
    } else {
        $error = 'Error al subir el archivo o carrera no seleccionada';
    }
}

// Crear nuevo estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $email = sanitizar($_POST['email'] ?? '');
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    $rut = sanitizar($_POST['rut'] ?? '');
    $carrera_id = $_POST['carrera_id'] ?? null;
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($nombre) || empty($apellido) || empty($rut) || empty($carrera_id)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } elseif (!validarCorreoInstitucional($email, 'estudiante')) {
        $error = 'El correo debe ser del dominio @alu.uct.cl';
    } elseif (!validarRUT($rut)) {
        $error = 'El RUT ingresado no es válido. Debe tener el formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)';
    } elseif (empty($password) || strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } else {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (email, password, nombre, apellido, rut, tipo, carrera_id, primera_vez) 
                VALUES (?, ?, ?, ?, ?, 'estudiante', ?, 1)
            ");
                $stmt->execute([$email, $password_hash, $nombre, $apellido, $rut, $carrera_id]);
                $estudiante_id_creado = $pdo->lastInsertId();
                header('Location: ' . BASE_URL . 'admin_supremo/estudiantes.php?asignar_asignaturas=' . $estudiante_id_creado);
                exit();
            } catch (PDOException $e) {
            error_log("Error al crear estudiante: " . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'El correo electrónico ya está registrado';
            } else {
                $error = 'Error al crear el estudiante';
            }
        }
    }
}

// Actualizar estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $id = $_POST['id'] ?? null;
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    $rut = sanitizar($_POST['rut'] ?? '');
    $carrera_id = $_POST['carrera_id'] ?? null;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    
    if (empty($id) || empty($nombre) || empty($apellido) || empty($rut) || empty($carrera_id)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } elseif (!validarRUT($rut)) {
        $error = 'El RUT ingresado no es válido. Debe tener el formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)';
    } else {
        try {
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $error = 'La contraseña debe tener al menos 8 caracteres';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, apellido = ?, rut = ?, carrera_id = ?, activo = ?, password = ?, primera_vez = 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $apellido, $rut, $carrera_id, $activo, $password_hash, $id]);
                    $mensaje = 'Estudiante actualizado exitosamente. Deberá cambiar su contraseña al iniciar sesión.';
                }
            } else {
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nombre = ?, apellido = ?, rut = ?, carrera_id = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $apellido, $rut, $carrera_id, $activo, $id]);
                $mensaje = 'Estudiante actualizado exitosamente';
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar estudiante: " . $e->getMessage());
            $error = 'Error al actualizar el estudiante';
        }
    }
}

// Eliminar estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = $_POST['id'] ?? null;
    
    if ($id) {
        try {
            // Verificar si hay autoevaluaciones asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM autoevaluaciones WHERE estudiante_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                $error = 'No se puede eliminar el estudiante porque tiene autoevaluaciones asociadas';
            } else {
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND tipo = 'estudiante'");
                $stmt->execute([$id]);
                $mensaje = 'Estudiante eliminado exitosamente';
            }
        } catch (PDOException $e) {
            error_log("Error al eliminar estudiante: " . $e->getMessage());
            $error = 'Error al eliminar el estudiante';
        }
    }
}

// Asignar asignaturas a estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar_asignaturas') {
    $estudiante_id = $_POST['estudiante_id'] ?? null;
    $asignaturas_ids = $_POST['asignaturas_ids'] ?? [];
    
    if ($estudiante_id && !empty($asignaturas_ids)) {
        try {
            $pdo->beginTransaction();
            // Eliminar asignaciones existentes
            $stmt = $pdo->prepare("DELETE FROM estudiantes_asignaturas WHERE estudiante_id = ?");
            $stmt->execute([$estudiante_id]);
            
            // Crear nuevas asignaciones
            $stmt = $pdo->prepare("
                INSERT INTO estudiantes_asignaturas (estudiante_id, asignatura_id, activo) 
                VALUES (?, ?, 1)
            ");
            foreach ($asignaturas_ids as $asignatura_id) {
                $stmt->execute([$estudiante_id, $asignatura_id]);
            }
            
            $pdo->commit();
            $mensaje = 'Asignaturas asignadas exitosamente al estudiante.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al asignar asignaturas: " . $e->getMessage());
            $error = 'Error al asignar las asignaturas.';
        }
    } else {
        $error = 'Por favor, seleccione al menos una asignatura.';
    }
}

// Obtener estudiante para editar
$estudiante_editar = null;
$estudiante_asignar = null;
$asignaturas_estudiante = [];

if (isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND tipo = 'estudiante'");
    $stmt->execute([$id]);
    $estudiante_editar = $stmt->fetch();
} elseif (isset($_GET['asignar_asignaturas'])) {
    $id = $_GET['asignar_asignaturas'];
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND tipo = 'estudiante'");
    $stmt->execute([$id]);
    $estudiante_asignar = $stmt->fetch();
    
    // Obtener asignaturas ya asignadas al estudiante
    if ($estudiante_asignar) {
        $stmt = $pdo->prepare("SELECT asignatura_id FROM estudiantes_asignaturas WHERE estudiante_id = ? AND activo = 1");
        $stmt->execute([$id]);
        $asignaturas_estudiante = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Obtener carreras
$stmt = $pdo->query("SELECT * FROM carreras WHERE activa = 1 ORDER BY nombre");
$carreras = $stmt->fetchAll();

// Filtros y búsqueda
$carrera_filtro = $_GET['carrera_filtro'] ?? null;
$busqueda = sanitizar($_GET['busqueda'] ?? '');

// Obtener todos los estudiantes con información relacionada
$sql = "
    SELECT u.*, c.nombre as carrera_nombre, c.codigo as carrera_codigo,
           COUNT(DISTINCT ea.asignatura_id) as total_asignaturas
    FROM usuarios u
    LEFT JOIN carreras c ON u.carrera_id = c.id
    LEFT JOIN estudiantes_asignaturas ea ON u.id = ea.estudiante_id
    WHERE u.tipo = 'estudiante'
";
$params = [];

if ($carrera_filtro) {
    $sql .= " AND u.carrera_id = ?";
    $params[] = $carrera_filtro;
}

if ($busqueda) {
    $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR u.rut LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

$sql .= " GROUP BY u.id ORDER BY u.apellido, u.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();
// Incluir header después de procesar peticiones POST para evitar 'headers already sent'
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Gestión de Estudiantes</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $estudiante_editar ? 'Editar Estudiante' : 'Nuevo Estudiante'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="<?php echo $estudiante_editar ? 'actualizar' : 'crear'; ?>">
                    <?php if ($estudiante_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $estudiante_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <?php if (!$estudiante_editar): ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($estudiante_editar['email'] ?? ''); ?>" 
                                   placeholder="usuario@alu.uct.cl" required>
                            <small class="text-muted">Debe ser del dominio @alu.uct.cl</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña Temporal <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="8" required>
                            <small class="text-muted">Mínimo 8 caracteres. El estudiante deberá cambiarla al iniciar sesión.</small>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($estudiante_editar['email']); ?>" readonly>
                            <small class="text-muted">El email no se puede cambiar</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva Contraseña (opcional)</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="8">
                            <small class="text-muted">Dejar vacío para mantener la contraseña actual. Si se cambia, el estudiante deberá cambiarla al iniciar sesión.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($estudiante_editar['nombre'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" 
                               value="<?php echo htmlspecialchars($estudiante_editar['apellido'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rut" name="rut" 
                               value="<?php echo htmlspecialchars($estudiante_editar['rut'] ?? ''); ?>"
                               placeholder="12345678-9" 
                               required
                               onblur="formatearRUT(this)"
                               oninput="this.setCustomValidity('')">
                        <small class="text-muted">Formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="carrera_id" class="form-label">Carrera <span class="text-danger">*</span></label>
                        <select class="form-select" id="carrera_id" name="carrera_id" required>
                            <option value="">-- Seleccione una carrera --</option>
                            <?php foreach ($carreras as $carrera): ?>
                                <option value="<?php echo $carrera['id']; ?>"
                                        <?php echo ($estudiante_editar && $estudiante_editar['carrera_id'] == $carrera['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($carrera['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($estudiante_editar): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                                       <?php echo $estudiante_editar['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activo">
                                    Estudiante Activo
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $estudiante_editar ? 'check' : 'plus'; ?>-circle"></i> 
                        <?php echo $estudiante_editar ? 'Actualizar' : 'Crear'; ?> Estudiante
                    </button>
                    <?php if ($estudiante_editar): ?>
                        <a href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php" class="btn btn-secondary">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if ($estudiante_asignar): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Asignar Asignaturas a <?php echo htmlspecialchars($estudiante_asignar['nombre'] . ' ' . $estudiante_asignar['apellido']); ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="accion" value="asignar_asignaturas">
                        <input type="hidden" name="estudiante_id" value="<?php echo $estudiante_asignar['id']; ?>">
                        
                        <?php
                        // Obtener asignaturas disponibles para la carrera del estudiante
                        $stmt = $pdo->prepare("
                            SELECT a.*, c.nombre as carrera_nombre, u.nombre as docente_nombre, u.apellido as docente_apellido
                            FROM asignaturas a
                            JOIN carreras c ON a.carrera_id = c.id
                            JOIN usuarios u ON a.docente_id = u.id
                            WHERE a.carrera_id = ? AND a.activa = 1
                            ORDER BY c.nombre, a.nombre
                        ");
                        $stmt->execute([$estudiante_asignar['carrera_id']]);
                        $asignaturas_disponibles = $stmt->fetchAll();
                        ?>
                        
                        <div class="mb-3">
                            <label for="asignaturas_ids" class="form-label">Asignaturas <span class="text-danger">*</span></label>
                            <select class="form-select" id="asignaturas_ids" name="asignaturas_ids[]" multiple size="10" required>
                                <?php foreach ($asignaturas_disponibles as $asig): ?>
                                    <option value="<?php echo $asig['id']; ?>"
                                            <?php echo in_array($asig['id'], $asignaturas_estudiante) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre'] . ' (' . $asig['docente_nombre'] . ' ' . $asig['docente_apellido'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Mantenga presionado Ctrl (o Cmd en Mac) para seleccionar múltiples asignaturas.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Asignar Asignaturas
                        </button>
                        <a href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Carga Masiva de Estudiantes</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="carga_masiva">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="carrera_id_carga" class="form-label">Carrera <span class="text-danger">*</span></label>
                            <select class="form-select" id="carrera_id_carga" name="carrera_id_carga" required>
                                <option value="">-- Seleccione una carrera --</option>
                                <?php foreach ($carreras as $carrera): ?>
                                    <option value="<?php echo $carrera['id']; ?>">
                                        <?php echo htmlspecialchars($carrera['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="archivo_csv" class="form-label">Archivo CSV <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" 
                                   accept=".csv,.xlsx,.xls" required>
                            <small class="text-muted">
                                Formato: email, nombre, apellido, rut<br>
                                Los estudiantes deben tener correo @alu.uct.cl
                            </small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Cargar Estudiantes
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Estudiantes (<?php echo count($estudiantes); ?>)</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="carrera_filtro" class="form-label">Filtrar por Carrera</label>
                            <select class="form-select" id="carrera_filtro" name="carrera_filtro" onchange="this.form.submit()">
                                <option value="">Todas las carreras</option>
                                <?php foreach ($carreras as $carrera): ?>
                                    <option value="<?php echo $carrera['id']; ?>" 
                                            <?php echo ($carrera_filtro == $carrera['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($carrera['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="busqueda" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                                   placeholder="Buscar por nombre, apellido, email o RUT">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                        <?php if ($carrera_filtro || $busqueda): ?>
                        <div class="col-12">
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-sm btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if (empty($estudiantes)): ?>
                    <p class="text-muted">No hay estudiantes registrados<?php echo ($carrera_filtro || $busqueda) ? ' con los filtros aplicados' : ''; ?>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 180px;">Nombre</th>
                                    <th style="min-width: 200px;">Email</th>
                                    <th class="text-long" style="min-width: 250px;">Carrera</th>
                                    <th style="min-width: 100px;" class="text-center">Asignaturas</th>
                                    <th style="min-width: 100px;" class="text-center">Estado</th>
                                    <th class="actions" style="min-width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $estudiante): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($estudiante['apellido'] . ', ' . $estudiante['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['email']); ?></td>
                                        <td class="text-long"><?php echo htmlspecialchars($estudiante['carrera_nombre'] ?? 'Sin carrera'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?php echo $estudiante['total_asignaturas']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $estudiante['activo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $estudiante['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <a href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php?asignar_asignaturas=<?php echo $estudiante['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Asignar asignaturas">
                                                <i class="bi bi-plus-circle"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php?editar=<?php echo $estudiante['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="" style="display: inline;" 
                                                  onsubmit="return confirm('¿Está seguro de eliminar este estudiante?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $estudiante['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Vista de cards para móvil (opcional) -->
                    <div class="d-none">
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-2">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($estudiante['apellido'] . ', ' . $estudiante['nombre']); ?>
                                    </h6>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-envelope"></i> <strong>Email:</strong><br>
                                            <?php echo htmlspecialchars($estudiante['email']); ?>
                                        </small>
                                    </p>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-mortarboard"></i> <strong>Carrera:</strong><br>
                                            <?php echo htmlspecialchars($estudiante['carrera_nombre'] ?? 'Sin carrera'); ?>
                                        </small>
                                    </p>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Asignaturas:</small>
                                            <span class="badge bg-info"><?php echo $estudiante['total_asignaturas']; ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Estado:</small>
                                            <span class="badge bg-<?php echo $estudiante['activo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $estudiante['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-3 d-grid gap-2">
                                        <a href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php?asignar_asignaturas=<?php echo $estudiante['id']; ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-plus-circle"></i> Asignar Asignaturas
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php?editar=<?php echo $estudiante['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('¿Está seguro de eliminar este estudiante?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $estudiante['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger w-100">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Función para formatear RUT chileno
function formatearRUT(input) {
    if (!input || !input.value) return;
    
    let rut = input.value.toUpperCase().trim();
    // Eliminar puntos y espacios
    rut = rut.replace(/[\.\s]/g, '');
    
    // Validar formato básico antes de formatear
    if (!rut.match(/^\d{7,8}-?[0-9K]$/)) {
        // Si no tiene el formato correcto, intentar validar
        input.setCustomValidity('El RUT debe tener el formato: 12345678-9 o 12345678-K');
        return;
    }
    
    // Si tiene guion, separar número y dígito verificador
    if (rut.includes('-')) {
        const partes = rut.split('-');
        if (partes.length === 2) {
            let numero = partes[0].replace(/\D/g, '');
            const digito = partes[1].replace(/[^0-9K]/g, '');
            
            // Validar que tenga 7-8 dígitos
            if (numero.length < 7 || numero.length > 8) {
                input.setCustomValidity('El RUT debe tener 7 u 8 dígitos antes del guion');
                return;
            }
            
            // Agregar puntos cada 3 dígitos desde la derecha
            if (numero.length > 0) {
                let numeroFormateado = '';
                for (let i = 0; i < numero.length; i++) {
                    if (i > 0 && (numero.length - i) % 3 === 0) {
                        numeroFormateado += '.';
                    }
                    numeroFormateado += numero[i];
                }
                input.value = numeroFormateado + '-' + digito;
                input.setCustomValidity('');
            }
        }
    } else {
        // Si no tiene guion, intentar detectar el último carácter como dígito verificador
        const match = rut.match(/^(\d{7,8})([0-9K])$/);
        if (match) {
            let numero = match[1];
            const digito = match[2];
            
            let numeroFormateado = '';
            for (let i = 0; i < numero.length; i++) {
                if (i > 0 && (numero.length - i) % 3 === 0) {
                    numeroFormateado += '.';
                }
                numeroFormateado += numero[i];
            }
            input.value = numeroFormateado + '-' + digito;
            input.setCustomValidity('');
        } else {
            input.setCustomValidity('El RUT debe tener el formato: 12345678-9 o 12345678-K');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>

