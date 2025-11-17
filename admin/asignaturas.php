<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Gestión de Asignaturas';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];

$mensaje = '';
$error = '';

// Crear nueva asignatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $carrera_id = $_POST['carrera_id'] ?? null;
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $codigo = sanitizar($_POST['codigo'] ?? '');
    $semestre = sanitizar($_POST['semestre'] ?? '');
    $anio = $_POST['anio'] ?? date('Y');
    
    if (empty($carrera_id) || empty($nombre) || empty($codigo)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } else {
        // Validar que el docente pertenezca a la carrera seleccionada
        $stmt = $pdo->prepare("SELECT carrera_id FROM usuarios WHERE id = ? AND tipo IN ('docente', 'admin')");
        $stmt->execute([$docente_id]);
        $docente = $stmt->fetch();
        
        if (!$docente || !$docente['carrera_id']) {
            $error = 'Usted no está asociado a ninguna carrera. Contacte al administrador.';
        } elseif ($docente['carrera_id'] != $carrera_id) {
            $error = 'Solo puede crear asignaturas para su propia carrera.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO asignaturas (carrera_id, nombre, codigo, docente_id, semestre, anio) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$carrera_id, $nombre, $codigo, $docente_id, $semestre, $anio]);
                $mensaje = 'Asignatura creada exitosamente';
            } catch (PDOException $e) {
                error_log("Error al crear asignatura: " . $e->getMessage());
                $error = 'Error al crear la asignatura. Puede que el código ya exista.';
            }
        }
    }
}

// Obtener carrera del docente
$stmt = $pdo->prepare("SELECT carrera_id FROM usuarios WHERE id = ? AND tipo IN ('docente', 'admin')");
$stmt->execute([$docente_id]);
$docente = $stmt->fetch();
$carrera_docente_id = $docente['carrera_id'] ?? null;

// Obtener carreras (solo la del docente si no es admin)
if ($_SESSION['usuario_tipo'] === 'admin') {
    $stmt = $pdo->query("SELECT * FROM carreras WHERE activa = 1 ORDER BY nombre");
    $carreras = $stmt->fetchAll();
} else {
    // Docente solo ve su propia carrera
    $stmt = $pdo->prepare("SELECT * FROM carreras WHERE id = ? AND activa = 1 ORDER BY nombre");
    $stmt->execute([$carrera_docente_id]);
    $carreras = $stmt->fetchAll();
}

// Obtener asignaturas del docente
$stmt = $pdo->prepare("
    SELECT a.*, c.nombre as carrera_nombre 
    FROM asignaturas a
    JOIN carreras c ON a.carrera_id = c.id
    WHERE a.docente_id = ?
    ORDER BY c.nombre, a.nombre
");
$stmt->execute([$docente_id]);
$asignaturas = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Gestión de Asignaturas</h2>
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
                <h5 class="mb-0">Nueva Asignatura</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label for="carrera_id" class="form-label">Carrera <span class="text-danger">*</span></label>
                        <select class="form-select" id="carrera_id" name="carrera_id" required>
                            <option value="">-- Seleccione una carrera --</option>
                            <?php foreach ($carreras as $carrera): ?>
                                <option value="<?php echo $carrera['id']; ?>">
                                    <?php echo htmlspecialchars($carrera['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="semestre" class="form-label">Semestre</label>
                            <input type="text" class="form-control" id="semestre" name="semestre" 
                                   placeholder="Ej: 2024-1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="anio" class="form-label">Año</label>
                            <input type="number" class="form-control" id="anio" name="anio" 
                                   value="<?php echo date('Y'); ?>" min="2020" max="2030">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Crear Asignatura
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Mis Asignaturas</h5>
            </div>
            <div class="card-body">
                <?php if (empty($asignaturas)): ?>
                    <p class="text-muted">No tiene asignaturas creadas aún.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($asignaturas as $asig): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($asig['nombre']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($asig['carrera_nombre']); ?> - 
                                            Código: <?php echo htmlspecialchars($asig['codigo']); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php echo $asig['activa'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $asig['activa'] ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

