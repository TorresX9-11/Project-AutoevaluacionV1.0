<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Ver Estudiante';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$estudiante_id = $_GET['id'] ?? null;
$asignatura_id = $_GET['asignatura_id'] ?? null;

if (!$estudiante_id || !$asignatura_id) {
    header('Location: ' . BASE_URL . 'admin/estudiantes.php');
    exit();
}

// Verificar asignatura
$stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ? AND docente_id = ?");
$stmt->execute([$asignatura_id, $docente_id]);
$asignatura = $stmt->fetch();

if (!$asignatura) {
    header('Location: ' . BASE_URL . 'admin/estudiantes.php');
    exit();
}

// Obtener estudiante
$stmt = $pdo->prepare("
    SELECT u.*, ea.activo as asignatura_activo
    FROM usuarios u
    JOIN estudiantes_asignaturas ea ON u.id = ea.estudiante_id
    WHERE u.id = ? AND ea.asignatura_id = ?
");
$stmt->execute([$estudiante_id, $asignatura_id]);
$estudiante = $stmt->fetch();

if (!$estudiante) {
    header('Location: ' . BASE_URL . 'admin/estudiantes.php');
    exit();
}

// Obtener autoevaluaciones del estudiante
$stmt = $pdo->prepare("
    SELECT a.*, r.nombre as rubrica_nombre
    FROM autoevaluaciones a
    JOIN rubricas r ON a.rubrica_id = r.id
    WHERE a.estudiante_id = ? AND a.asignatura_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$estudiante_id, $asignatura_id]);
$autoevaluaciones = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Información del Estudiante</h2>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Datos Personales</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($estudiante['nombre']); ?></p>
                <p><strong>Apellido:</strong> <?php echo htmlspecialchars($estudiante['apellido']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($estudiante['email']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>RUT:</strong> <?php echo htmlspecialchars($estudiante['rut'] ?? '-'); ?></p>
                <p><strong>Estado:</strong> 
                    <span class="badge bg-<?php echo ($estudiante['activo'] && $estudiante['asignatura_activo']) ? 'success' : 'secondary'; ?>">
                        <?php echo ($estudiante['activo'] && $estudiante['asignatura_activo']) ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Autoevaluaciones (<?php echo count($autoevaluaciones); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($autoevaluaciones)): ?>
            <p class="text-muted">El estudiante no tiene autoevaluaciones registradas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rúbrica</th>
                            <th>Estado</th>
                            <th>Nota Autoevaluada</th>
                            <th>Nota Final</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($autoevaluaciones as $autoeval): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $autoeval['estado'] === 'completada' ? 'success' : 
                                            ($autoeval['estado'] === 'en_proceso' ? 'info' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $autoeval['estado'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($autoeval['nota_autoevaluada']): ?>
                                        <?php echo number_format($autoeval['nota_autoevaluada'], 1); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($autoeval['nota_final']): ?>
                                        <strong><?php echo number_format($autoeval['nota_final'], 1); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($autoeval['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>admin/autoevaluacion_ver.php?id=<?php echo $autoeval['id']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <a href="<?php echo BASE_URL; ?>admin/estudiantes.php?asignatura_id=<?php echo $asignatura_id; ?>" 
           class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

