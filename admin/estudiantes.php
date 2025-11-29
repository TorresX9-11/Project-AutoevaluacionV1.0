<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Gestión de Estudiantes';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];

$mensaje = '';
$error = '';

// Procesar creación individual de estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_individual') {
    $email = sanitizar($_POST['email'] ?? '');
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    $rut = sanitizar($_POST['rut'] ?? '');
    $password = $_POST['password'] ?? '';
    $asignatura_id_crear = $_POST['asignatura_id_crear'] ?? null;

    if (empty($email) || empty($nombre) || empty($apellido) || empty($rut) || empty($password) || empty($asignatura_id_crear)) {
        $error = 'Por favor, complete todos los campos requeridos para crear el estudiante.';
    } elseif (!validarCorreoInstitucional($email, 'estudiante')) {
        $error = 'El correo debe ser del dominio @alu.uct.cl';
    } elseif (!validarRUT($rut)) {
        $error = 'El RUT ingresado no es válido. Debe tener el formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } else {
        try {
            // Validar que la asignatura pertenece al docente/admin actual
            $stmt = $pdo->prepare("
                SELECT id, carrera_id 
                FROM asignaturas 
                WHERE id = ? AND docente_id = ? AND activa = 1
            ");
            $stmt->execute([$asignatura_id_crear, $docente_id]);
            $asignatura_creacion = $stmt->fetch();

            if (!$asignatura_creacion) {
                $error = 'La asignatura seleccionada no es válida para este usuario.';
            } else {
                // Verificar si ya existe un usuario con ese email
                $stmt = $pdo->prepare("SELECT id, tipo, carrera_id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                $usuario_existente = $stmt->fetch();

                if ($usuario_existente) {
                    if ($usuario_existente['tipo'] !== 'estudiante') {
                        $error = 'Ya existe un usuario con este correo y no es de tipo estudiante.';
                    } elseif ($usuario_existente['carrera_id'] && $usuario_existente['carrera_id'] != $asignatura_creacion['carrera_id']) {
                        $error = 'El estudiante ya está asociado a otra carrera distinta a la de la asignatura seleccionada.';
                    } else {
                        // Usuario estudiante existente: solo asignar a la asignatura
                        $estudiante_id = $usuario_existente['id'];
                    }
                } else {
                    // Crear nuevo estudiante
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios (email, password, nombre, apellido, rut, tipo, carrera_id, primera_vez, activo) 
                        VALUES (?, ?, ?, ?, ?, 'estudiante', ?, 1, 1)
                    ");
                    $stmt->execute([
                        $email,
                        $password_hash,
                        $nombre,
                        $apellido,
                        $rut,
                        $asignatura_creacion['carrera_id']
                    ]);
                    $estudiante_id = $pdo->lastInsertId();
                }

                if (!isset($error) || $error === '') {
                    // Asignar estudiante a la asignatura (si no estaba ya asignado)
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO estudiantes_asignaturas (estudiante_id, asignatura_id, activo) 
                        VALUES (?, ?, 1)
                    ");
                    $stmt->execute([$estudiante_id, $asignatura_id_crear]);

                    $mensaje = 'Estudiante creado y asignado a la asignatura exitosamente.';
                }
            }
        } catch (PDOException $e) {
            error_log("Error al crear estudiante individual: " . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'El correo electrónico ya está registrado.';
            } else {
                $error = 'Error al crear el estudiante.';
            }
        }
    }
}

// Procesar carga masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'carga_masiva' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];
    $asignatura_id = $_POST['asignatura_id'] ?? null;
    
    if ($archivo['error'] === UPLOAD_ERR_OK && $asignatura_id) {
        $tmp_name = $archivo['tmp_name'];
        $handle = fopen($tmp_name, 'r');
        
        if ($handle !== false) {
            try {
                $pdo->beginTransaction();
                $linea = 0;
                $exitosos = 0;
                $errores = 0;
                $errores_detalle = [];
                
                // Validar asignatura
                $stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ? AND docente_id = ?");
                $stmt->execute([$asignatura_id, $docente_id]);
                $asignatura = $stmt->fetch();
                
                if ($asignatura) {
                    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                        $linea++;
                        if ($linea === 1) continue; // Saltar encabezados
                        
                        if (count($data) >= 4) {
                            $email = trim($data[0]);
                            $nombre = trim($data[1]);
                            $apellido = trim($data[2]);
                            $rut = trim($data[3]);
                            
                            // Validar correo institucional
                            if (strpos($email, DOMINIO_ESTUDIANTE) !== false) {
                                // Verificar si el usuario existe
                                $stmt = $pdo->prepare("SELECT id, carrera_id FROM usuarios WHERE email = ?");
                                $stmt->execute([$email]);
                                $usuario = $stmt->fetch();
                                
                                if (!$usuario) {
                                    // Crear usuario con la carrera de la asignatura
                                    $password_base = explode('@', $email)[0];
                                    if (empty($password_base)) {
                                        $password_base = 'TecUct2024';
                                    }
                                    $password_hash = password_hash($password_base, PASSWORD_DEFAULT);
                                    $stmt = $pdo->prepare("
                                        INSERT INTO usuarios (email, password, nombre, apellido, rut, tipo, carrera_id, primera_vez) 
                                        VALUES (?, ?, ?, ?, ?, 'estudiante', ?, 1)
                                    ");
                                    $stmt->execute([$email, $password_hash, $nombre, $apellido, $rut, $asignatura['carrera_id']]);
                                    $usuario_id = $pdo->lastInsertId();
                                } else {
                                    $usuario_id = $usuario['id'];
                                    
                                    // Validar que el estudiante pertenezca a la carrera de la asignatura
                                    if ($usuario['carrera_id'] != $asignatura['carrera_id']) {
                                        $errores++;
                                        $errores_detalle[] = "Línea $linea: Estudiante $email pertenece a otra carrera";
                                        continue;
                                    }
                                }
                                
                                // Asignar a asignatura
                                $stmt = $pdo->prepare("
                                    INSERT IGNORE INTO estudiantes_asignaturas (estudiante_id, asignatura_id) 
                                    VALUES (?, ?)
                                ");
                                $stmt->execute([$usuario_id, $asignatura_id]);
                                $exitosos++;
                            } else {
                                $errores++;
                                $errores_detalle[] = "Línea $linea: Email inválido ($email)";
                            }
                        } else {
                            $errores++;
                            $errores_detalle[] = "Línea $linea: Formato incorrecto (se requieren al menos 4 columnas)";
                        }
                    }
                    
                    $pdo->commit();
                    $mensaje = "Carga completada: $exitosos estudiantes agregados, $errores errores.";
                    if (!empty($errores_detalle) && $errores <= 10) {
                        $mensaje .= "<br><small>Errores: " . implode(', ', $errores_detalle) . "</small>";
                    }
                } else {
                    $error = 'Asignatura no válida';
                }
                
                fclose($handle);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error en carga masiva: " . $e->getMessage());
                $error = 'Error al procesar el archivo. Por favor, verifique el formato.';
            }
        } else {
            $error = 'Error al leer el archivo';
        }
    } else {
        $error = 'Error al subir el archivo o asignatura no seleccionada';
    }
}

// Obtener asignaturas del docente
$stmt = $pdo->prepare("
    SELECT a.*, c.nombre as carrera_nombre 
    FROM asignaturas a
    JOIN carreras c ON a.carrera_id = c.id
    WHERE a.docente_id = ? AND a.activa = 1
    ORDER BY c.nombre, a.nombre
");
$stmt->execute([$docente_id]);
$asignaturas = $stmt->fetchAll();
$asignaturas_ids = array_map('intval', array_column($asignaturas, 'id'));

// Actualizar estudiante (solo dentro de las asignaturas del docente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $id = intval($_POST['id'] ?? 0);
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    $rut = sanitizar($_POST['rut'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $asignatura_contexto = isset($_POST['asignatura_contexto']) ? intval($_POST['asignatura_contexto']) : null;

    if ($id <= 0 || empty($nombre) || empty($apellido) || empty($rut) || empty($asignatura_contexto)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } elseif (!validarRUT($rut)) {
        $error = 'El RUT ingresado no es válido. Debe tener el formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)';
    } elseif (!in_array($asignatura_contexto, $asignaturas_ids, true)) {
        $error = 'No tiene permisos para editar este estudiante';
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM estudiantes_asignaturas 
            WHERE estudiante_id = ? AND asignatura_id = ?
        ");
        $stmt->execute([$id, $asignatura_contexto]);
        if ($stmt->fetchColumn() == 0) {
            $error = 'El estudiante no pertenece a la asignatura seleccionada';
        } else {
            $actualizado = false;
            try {
                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $error = 'La contraseña debe tener al menos 8 caracteres';
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE usuarios 
                            SET nombre = ?, apellido = ?, rut = ?, activo = ?, password = ?, primera_vez = 1
                            WHERE id = ? AND tipo = 'estudiante'
                        ");
                        $stmt->execute([$nombre, $apellido, $rut, $activo, $password_hash, $id]);
                        $actualizado = true;
                        $mensaje = 'Estudiante actualizado exitosamente. Deberá cambiar su contraseña al iniciar sesión.';
                    }
                } elseif (empty($error)) {
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, apellido = ?, rut = ?, activo = ?
                        WHERE id = ? AND tipo = 'estudiante'
                    ");
                    $stmt->execute([$nombre, $apellido, $rut, $activo, $id]);
                    $actualizado = true;
                    $mensaje = 'Estudiante actualizado exitosamente';
                }

                if ($actualizado) {
                    $stmt = $pdo->prepare("
                        UPDATE estudiantes_asignaturas 
                        SET activo = ?
                        WHERE estudiante_id = ? AND asignatura_id = ?
                    ");
                    $stmt->execute([$activo, $id, $asignatura_contexto]);
                }
            } catch (PDOException $e) {
                error_log("Error al actualizar estudiante: " . $e->getMessage());
                $error = 'Error al actualizar el estudiante';
            }

            if ($actualizado && empty($error)) {
                header('Location: ' . BASE_URL . 'admin/estudiantes.php?asignatura_id=' . $asignatura_contexto);
                exit();
            }
        }
    }
}

// Eliminar estudiante (solo si pertenece a la asignatura del docente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = intval($_POST['id'] ?? 0);
    $asignatura_contexto = isset($_POST['asignatura_contexto']) ? intval($_POST['asignatura_contexto']) : null;

    if ($id <= 0 || empty($asignatura_contexto)) {
        $error = 'Estudiante no válido';
    } elseif (!in_array($asignatura_contexto, $asignaturas_ids, true)) {
        $error = 'No tiene permisos para eliminar este estudiante';
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM estudiantes_asignaturas 
            WHERE estudiante_id = ? AND asignatura_id = ?
        ");
        $stmt->execute([$id, $asignatura_contexto]);
        if ($stmt->fetchColumn() == 0) {
            $error = 'El estudiante no pertenece a la asignatura seleccionada';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM autoevaluaciones WHERE estudiante_id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch();

                if ($result['total'] > 0) {
                    $error = 'No se puede eliminar el estudiante porque tiene autoevaluaciones asociadas';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND tipo = 'estudiante'");
                    $stmt->execute([$id]);
                    $mensaje = 'Estudiante eliminado exitosamente';
                    header('Location: ' . BASE_URL . 'admin/estudiantes.php?asignatura_id=' . $asignatura_contexto);
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Error al eliminar estudiante: " . $e->getMessage());
                $error = 'Error al eliminar el estudiante';
            }
        }
    }
}

// Obtener estudiantes si hay asignatura seleccionada con filtros
$asignatura_id = $_GET['asignatura_id'] ?? null;
$busqueda = sanitizar($_GET['busqueda'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';
$estudiantes = [];

if ($asignatura_id) {
    $sql = "
        SELECT u.*, ea.activo as asignatura_activo
        FROM usuarios u
        JOIN estudiantes_asignaturas ea ON u.id = ea.estudiante_id
        WHERE ea.asignatura_id = ? AND u.tipo = 'estudiante'
    ";
    $params = [$asignatura_id];
    
    if (!empty($busqueda)) {
        $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR u.rut LIKE ?)";
        $busqueda_param = "%$busqueda%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    
    if ($filtro_estado !== '') {
        if ($filtro_estado === 'activo') {
            $sql .= " AND u.activo = 1 AND ea.activo = 1";
        } elseif ($filtro_estado === 'inactivo') {
            $sql .= " AND (u.activo = 0 OR ea.activo = 0)";
        }
    }
    
    $sql .= " ORDER BY u.apellido, u.nombre";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $estudiantes = $stmt->fetchAll();
}

$estudiante_editar = null;
$asignatura_contexto_editar = null;

if (isset($_GET['editar']) && !empty($asignaturas_ids)) {
    $estudiante_id_editar = intval($_GET['editar']);
    if ($asignatura_id && in_array((int)$asignatura_id, $asignaturas_ids, true)) {
        $stmt = $pdo->prepare("
            SELECT u.*, ea.asignatura_id 
            FROM usuarios u
            JOIN estudiantes_asignaturas ea ON u.id = ea.estudiante_id
            WHERE u.id = ? AND ea.asignatura_id = ? AND u.tipo = 'estudiante'
            LIMIT 1
        ");
        $stmt->execute([$estudiante_id_editar, $asignatura_id]);
        $estudiante_editar = $stmt->fetch();
        if ($estudiante_editar) {
            $asignatura_contexto_editar = (int)$asignatura_id;
        }
    }

    if (!$estudiante_editar && !empty($asignaturas_ids)) {
        $placeholders = implode(',', array_fill(0, count($asignaturas_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT u.*, ea.asignatura_id 
            FROM usuarios u
            JOIN estudiantes_asignaturas ea ON u.id = ea.estudiante_id
            WHERE u.id = ? AND ea.asignatura_id IN ($placeholders) AND u.tipo = 'estudiante'
            LIMIT 1
        ");
        $stmt->execute(array_merge([$estudiante_id_editar], $asignaturas_ids));
        $estudiante_editar = $stmt->fetch();
        if ($estudiante_editar) {
            $asignatura_contexto_editar = (int)$estudiante_editar['asignatura_id'];
        }
    }
}

$asignatura_editar_nombre = '';
if ($estudiante_editar && $asignatura_contexto_editar) {
    foreach ($asignaturas as $asig) {
        if ($asig['id'] == $asignatura_contexto_editar) {
            $asignatura_editar_nombre = $asig['carrera_nombre'] . ' - ' . $asig['nombre'];
            break;
        }
    }
}

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
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <?php echo $estudiante_editar ? 'Editar Estudiante' : 'Crear Estudiante en Asignatura'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($estudiante_editar): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="id" value="<?php echo $estudiante_editar['id']; ?>">
                        <input type="hidden" name="asignatura_contexto" value="<?php echo $asignatura_contexto_editar; ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($estudiante_editar['email']); ?>" readonly>
                            <small class="text-muted">El correo no se puede cambiar.</small>
                        </div>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre"
                                   value="<?php echo htmlspecialchars($_POST['nombre'] ?? $estudiante_editar['nombre']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellido" name="apellido"
                                   value="<?php echo htmlspecialchars($_POST['apellido'] ?? $estudiante_editar['apellido']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="rut" name="rut"
                                   value="<?php echo htmlspecialchars($_POST['rut'] ?? $estudiante_editar['rut']); ?>"
                                   placeholder="12345678-9" required
                                   onblur="formatearRUT(this)"
                                   oninput="this.setCustomValidity('')">
                            <small class="text-muted">Formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Asignatura asociada</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($asignatura_editar_nombre); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva Contraseña (opcional)</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   minlength="8">
                            <small class="text-muted">Dejar vacío para mantener la contraseña actual. Si se cambia, el estudiante deberá actualizarla al iniciar sesión.</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo"
                                       <?php echo $estudiante_editar['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activo">
                                    Estudiante activo
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Actualizar Estudiante
                            </button>
                            <a href="<?php echo BASE_URL; ?>admin/estudiantes.php<?php echo $asignatura_id ? '?asignatura_id=' . $asignatura_id : ''; ?>" 
                               class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="accion" value="crear_individual">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="usuario@alu.uct.cl" required>
                            <small class="text-muted">Debe ser del dominio @alu.uct.cl</small>
                        </div>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre"
                                   value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellido" name="apellido"
                                   value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="rut" name="rut"
                                   value="<?php echo htmlspecialchars($_POST['rut'] ?? ''); ?>"
                                   placeholder="12345678-9" required
                                   onblur="formatearRUT(this)"
                                   oninput="this.setCustomValidity('')">
                            <small class="text-muted">Formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)</small>
                        </div>

                        <div class="mb-3">
                            <label for="asignatura_id_crear" class="form-label">Asignatura <span class="text-danger">*</span></label>
                            <select class="form-select" id="asignatura_id_crear" name="asignatura_id_crear" required>
                                <option value="">-- Seleccione una asignatura --</option>
                                <?php foreach ($asignaturas as $asig): ?>
                                    <option value="<?php echo $asig['id']; ?>"
                                            <?php echo (($_POST['asignatura_id_crear'] ?? '') == $asig['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña Temporal <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password"
                                   minlength="8" required>
                            <small class="text-muted">Mínimo 8 caracteres. El estudiante deberá cambiarla al iniciar sesión.</small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Crear y Asignar Estudiante
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Carga Masiva de Estudiantes</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="carga_masiva">
                    <div class="mb-3">
                        <label for="asignatura_id" class="form-label">Asignatura <span class="text-danger">*</span></label>
                        <select class="form-select" id="asignatura_id" name="asignatura_id" required>
                            <option value="">-- Seleccione una asignatura --</option>
                            <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id']; ?>">
                                    <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="archivo_csv" class="form-label">Archivo CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" 
                               accept=".csv,.xlsx,.xls" required>
                        <small class="text-muted">
                            Formato: email, nombre, apellido, rut<br>
                            Los estudiantes deben tener correo @alu.uct.cl
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Cargar Estudiantes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Ver Estudiantes por Asignatura</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filtrosEstudiantes">
                    <div class="mb-3">
                        <label for="asignatura_id_view" class="form-label">Asignatura</label>
                        <select class="form-select" id="asignatura_id_view" name="asignatura_id">
                            <option value="">-- Seleccione una asignatura --</option>
                            <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id']; ?>" 
                                        <?php echo ($asignatura_id == $asig['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($asignatura_id): ?>
                    <div class="mb-3">
                        <label for="busqueda" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" 
                               value="<?php echo htmlspecialchars($busqueda); ?>" 
                               placeholder="Nombre, apellido, email o RUT">
                    </div>
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="activo" <?php echo ($filtro_estado === 'activo') ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactivo" <?php echo ($filtro_estado === 'inactivo') ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <?php if ($busqueda || $filtro_estado): ?>
                        <a href="<?php echo BASE_URL; ?>admin/estudiantes.php?asignatura_id=<?php echo $asignatura_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar filtros
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Ver Estudiantes
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($asignatura_id && !empty($estudiantes)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Estudiantes (<?php echo count($estudiantes); ?>)</h5>
                <a href="<?php echo BASE_URL; ?>admin/estudiantes_exportar.php?asignatura_id=<?php echo $asignatura_id; ?>" 
                   class="btn btn-sm btn-info">
                    <i class="bi bi-download"></i> Exportar CSV
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="min-width: 120px;">RUT</th>
                                <th style="min-width: 150px;">Nombre</th>
                                <th style="min-width: 150px;">Apellido</th>
                                <th style="min-width: 200px;">Email</th>
                                <th style="min-width: 100px;" class="text-center">Estado</th>
                                <th class="actions" style="min-width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $estudiante): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($estudiante['rut'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['apellido']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['email']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo ($estudiante['activo'] && $estudiante['asignatura_activo']) ? 'success' : 'secondary'; ?>">
                                            <?php echo ($estudiante['activo'] && $estudiante['asignatura_activo']) ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="<?php echo BASE_URL; ?>admin/estudiante_ver.php?id=<?php echo $estudiante['id']; ?>&asignatura_id=<?php echo $asignatura_id; ?>" 
                                           class="btn btn-sm btn-info" title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>admin/estudiantes.php?asignatura_id=<?php echo $asignatura_id; ?>&editar=<?php echo $estudiante['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="" class="d-inline" 
                                              onsubmit="return confirm('¿Está seguro de eliminar este estudiante?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $estudiante['id']; ?>">
                                            <input type="hidden" name="asignatura_contexto" value="<?php echo $asignatura_id; ?>">
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
            </div>
        </div>
    </div>
</div>
<?php elseif ($asignatura_id && empty($estudiantes)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <?php if ($busqueda || $filtro_estado): ?>
                No se encontraron estudiantes con los filtros seleccionados.
            <?php else: ?>
                No hay estudiantes asignados a esta asignatura.
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Función para formatear RUT chileno (igual que en admin_supremo)
function formatearRUT(input) {
    if (!input || !input.value) return;
    
    let rut = input.value.toUpperCase().trim();
    rut = rut.replace(/[\.\s]/g, '');
    
    if (!rut.match(/^\d{7,8}-?[0-9K]$/)) {
        input.setCustomValidity('El RUT debe tener el formato: 12345678-9 o 12345678-K');
        return;
    }
    
    if (rut.includes('-')) {
        const partes = rut.split('-');
        if (partes.length === 2) {
            let numero = partes[0].replace(/\D/g, '');
            const digito = partes[1].replace(/[^0-9K]/g, '');
            
            if (numero.length < 7 || numero.length > 8) {
                input.setCustomValidity('El RUT debe tener 7 u 8 dígitos antes del guion');
                return;
            }
            
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

