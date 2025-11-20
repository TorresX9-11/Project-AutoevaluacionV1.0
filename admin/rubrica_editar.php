<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Editar Rúbrica';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$rubrica_id = $_GET['id'] ?? null;

if (!$rubrica_id) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Obtener rúbrica
$stmt = $pdo->prepare("
    SELECT r.*, a.id as asignatura_id, a.nombre as asignatura_nombre
    FROM rubricas r
    JOIN asignaturas a ON r.asignatura_id = a.id
    WHERE r.id = ? AND a.docente_id = ?
");
$stmt->execute([$rubrica_id, $docente_id]);
$rubrica = $stmt->fetch();

if (!$rubrica) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Obtener criterios con niveles
$stmt = $pdo->prepare("
    SELECT c.*, 
           GROUP_CONCAT(n.id ORDER BY n.orden SEPARATOR '||') as nivel_ids,
           GROUP_CONCAT(n.nombre ORDER BY n.orden SEPARATOR '||') as nivel_nombres,
           GROUP_CONCAT(n.descripcion ORDER BY n.orden SEPARATOR '||') as nivel_descripciones,
           GROUP_CONCAT(n.puntaje ORDER BY n.orden SEPARATOR '||') as nivel_puntajes
    FROM criterios c
    LEFT JOIN niveles n ON c.id = n.criterio_id
    WHERE c.rubrica_id = ?
    GROUP BY c.id
    ORDER BY c.orden
");
$stmt->execute([$rubrica_id]);
$criterios = $stmt->fetchAll();

$error = '';
$mensaje = '';

// Actualizar rúbrica
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $descripcion = sanitizar($_POST['descripcion'] ?? '');
    $activa = isset($_POST['activa']) ? 1 : 0;
    
    if (empty($nombre)) {
        $error = 'El nombre es requerido';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE rubricas SET nombre = ?, descripcion = ?, activa = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion, $activa, $rubrica_id]);
            $mensaje = 'Rúbrica actualizada exitosamente';
            $rubrica['nombre'] = $nombre;
            $rubrica['descripcion'] = $descripcion;
            $rubrica['activa'] = $activa;
        } catch (PDOException $e) {
            error_log("Error al actualizar rúbrica: " . $e->getMessage());
            $error = 'Error al actualizar la rúbrica';
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Editar Rúbrica</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Información General</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Asignatura</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($rubrica['asignatura_nombre']); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($rubrica['nombre']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($rubrica['descripcion']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="activa" name="activa" 
                                   <?php echo $rubrica['activa'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="activa">
                                Rúbrica activa
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                    <a href="<?php echo BASE_URL; ?>admin/rubricas.php?asignatura_id=<?php echo $rubrica['asignatura_id']; ?>" 
                       class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Criterios de Evaluación</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($criterios)): ?>
                        <p class="text-muted">No hay criterios definidos.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($criterios as $index => $criterio): ?>
                                <div class="list-group-item">
                                    <h6><?php echo ($index + 1) . '. ' . htmlspecialchars($criterio['nombre']); ?></h6>
                                    <small class="text-muted">Peso: <?php echo number_format($criterio['peso'], 1); ?>%</small>
                                    <?php
                                    // GROUP_CONCAT puede devolver NULL si no hay niveles; manejarlo para evitar deprecated warnings
                                    $nivel_ids = $criterio['nivel_ids'] !== null ? explode('||', $criterio['nivel_ids']) : [];
                                    $nivel_nombres = $criterio['nivel_nombres'] !== null ? explode('||', $criterio['nivel_nombres']) : [];
                                    $nivel_puntajes = $criterio['nivel_puntajes'] !== null ? explode('||', $criterio['nivel_puntajes']) : [];
                                    ?>
                                    <ul class="mt-2">
                                        <?php for ($i = 0; $i < count($nivel_ids); $i++): ?>
                                            <?php if (!empty($nivel_ids[$i])): ?>
                                                <?php $nombreNivel = $nivel_nombres[$i] ?? ''; ?>
                                                <?php $puntajeNivel = isset($nivel_puntajes[$i]) ? floatval($nivel_puntajes[$i]) : 0; ?>
                                                <li>
                                                    <?php echo htmlspecialchars($nombreNivel); ?> 
                                                    (<?php echo number_format($puntajeNivel, 1); ?> pts)
                                                </li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../includes/footer.php'; ?>

