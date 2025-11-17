<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Gestión de Estudiantes';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];

$mensaje = '';
$error = '';

// Procesar carga masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
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
                                    $password_hash = password_hash('TecUct2024', PASSWORD_DEFAULT);
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
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Carga Masiva de Estudiantes</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
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
                                           class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
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

<?php include '../includes/footer.php'; ?>

