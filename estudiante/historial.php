<?php
require_once '../config/config.php';
validarTipoUsuario(['estudiante']);

$titulo = 'Historial de Autoevaluaciones';
include '../includes/header.php';

$pdo = getDBConnection();
$estudiante_id = $_SESSION['usuario_id'];

// Obtener parámetros de filtro
$filtro_asignatura = $_GET['asignatura_id'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_rubrica = $_GET['rubrica_id'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta con filtros
$sql = "
    SELECT a.*, asig.nombre as asignatura_nombre, asig.id as asignatura_id,
           r.nombre as rubrica_nombre, r.id as rubrica_id
    FROM autoevaluaciones a
    JOIN asignaturas asig ON a.asignatura_id = asig.id
    JOIN rubricas r ON a.rubrica_id = r.id
    WHERE a.estudiante_id = ?
";

$params = [$estudiante_id];

// Aplicar filtros
if (!empty($filtro_asignatura)) {
    $sql .= " AND a.asignatura_id = ?";
    $params[] = $filtro_asignatura;
}

if (!empty($filtro_estado)) {
    $sql .= " AND a.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_rubrica)) {
    $sql .= " AND a.rubrica_id = ?";
    $params[] = $filtro_rubrica;
}

if (!empty($filtro_fecha_desde)) {
    $sql .= " AND DATE(a.created_at) >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $sql .= " AND DATE(a.created_at) <= ?";
    $params[] = $filtro_fecha_hasta;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$autoevaluaciones = $stmt->fetchAll();

// Obtener listas para los filtros
// Asignaturas únicas
$stmt = $pdo->prepare("
    SELECT DISTINCT asig.id, asig.nombre
    FROM autoevaluaciones a
    JOIN asignaturas asig ON a.asignatura_id = asig.id
    WHERE a.estudiante_id = ?
    ORDER BY asig.nombre
");
$stmt->execute([$estudiante_id]);
$asignaturas = $stmt->fetchAll();

// Rúbricas únicas
$stmt = $pdo->prepare("
    SELECT DISTINCT r.id, r.nombre
    FROM autoevaluaciones a
    JOIN rubricas r ON a.rubrica_id = r.id
    WHERE a.estudiante_id = ?
    ORDER BY r.nombre
");
$stmt->execute([$estudiante_id]);
$rubricas = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Historial de Autoevaluaciones</h2>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-funnel"></i> Filtros
            <button class="btn btn-sm btn-link text-white float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </h5>
    </div>
    <div class="collapse show" id="filtrosCollapse">
        <div class="card-body">
            <form method="GET" action="" id="filtrosForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="asignatura_id" class="form-label">Asignatura</label>
                        <select class="form-select" id="asignatura_id" name="asignatura_id">
                            <option value="">Todas las asignaturas</option>
                            <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id']; ?>" 
                                        <?php echo ($filtro_asignatura == $asig['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asig['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="completada" <?php echo ($filtro_estado == 'completada') ? 'selected' : ''; ?>>Completada</option>
                            <option value="en_proceso" <?php echo ($filtro_estado == 'en_proceso') ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="pausada" <?php echo ($filtro_estado == 'pausada') ? 'selected' : ''; ?>>Pausada</option>
                            <option value="incompleta" <?php echo ($filtro_estado == 'incompleta') ? 'selected' : ''; ?>>Incompleta</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="rubrica_id" class="form-label">Rúbrica</label>
                        <select class="form-select" id="rubrica_id" name="rubrica_id">
                            <option value="">Todas las rúbricas</option>
                            <?php foreach ($rubricas as $rub): ?>
                                <option value="<?php echo $rub['id']; ?>" 
                                        <?php echo ($filtro_rubrica == $rub['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rub['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                               value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                               value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="<?php echo BASE_URL; ?>estudiante/historial.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (empty($autoevaluaciones)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> 
        <?php if (!empty($filtro_asignatura) || !empty($filtro_estado) || !empty($filtro_rubrica) || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)): ?>
            No se encontraron autoevaluaciones con los filtros seleccionados.
        <?php else: ?>
            No tiene autoevaluaciones registradas aún.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Mis Autoevaluaciones (<?php echo count($autoevaluaciones); ?>)</h5>
            <?php if (!empty($filtro_asignatura) || !empty($filtro_estado) || !empty($filtro_rubrica) || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)): ?>
                <span class="badge bg-info">Filtros activos</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Vista de tabla para desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 150px;">Asignatura</th>
                            <th style="min-width: 150px;">Rúbrica</th>
                            <th style="min-width: 100px;">Estado</th>
                            <th style="min-width: 120px;">Nota Autoevaluada</th>
                            <th style="min-width: 100px;">Nota Final</th>
                            <th style="min-width: 130px;">Fecha</th>
                            <th style="min-width: 150px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($autoevaluaciones as $autoeval): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($autoeval['asignatura_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $autoeval['estado'] === 'completada' ? 'success' : 
                                            ($autoeval['estado'] === 'en_proceso' ? 'info' : 
                                            ($autoeval['estado'] === 'pausada' ? 'warning' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $autoeval['estado'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($autoeval['nota_autoevaluada']): ?>
                                        <span class="badge bg-primary">
                                            <?php echo number_format($autoeval['nota_autoevaluada'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($autoeval['nota_final']): ?>
                                        <span class="badge bg-success fs-6">
                                            <?php echo number_format($autoeval['nota_final'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($autoeval['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion_ver.php?id=<?php echo $autoeval['id']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <?php if ($autoeval['estado'] === 'en_proceso' || $autoeval['estado'] === 'pausada'): ?>
                                        <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion_proceso.php?id=<?php echo $autoeval['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-play"></i> Continuar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Vista de cards para móvil -->
            <div class="d-md-none">
                <?php foreach ($autoevaluaciones as $autoeval): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-primary mb-2">
                                <i class="bi bi-book"></i> <?php echo htmlspecialchars($autoeval['asignatura_nombre']); ?>
                            </h6>
                            <p class="card-text mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-clipboard-check"></i> <strong>Rúbrica:</strong><br>
                                    <?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?>
                                </small>
                            </p>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Estado:</small>
                                    <span class="badge bg-<?php 
                                        echo $autoeval['estado'] === 'completada' ? 'success' : 
                                            ($autoeval['estado'] === 'en_proceso' ? 'info' : 
                                            ($autoeval['estado'] === 'pausada' ? 'warning' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $autoeval['estado'])); ?>
                                    </span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Fecha:</small>
                                    <small><?php echo date('d/m/Y H:i', strtotime($autoeval['created_at'])); ?></small>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Nota Autoevaluada:</small>
                                    <?php if ($autoeval['nota_autoevaluada']): ?>
                                        <span class="badge bg-primary">
                                            <?php echo number_format($autoeval['nota_autoevaluada'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Nota Final:</small>
                                    <?php if ($autoeval['nota_final']): ?>
                                        <span class="badge bg-success">
                                            <?php echo number_format($autoeval['nota_final'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Pendiente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-3 d-grid gap-2">
                                <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion_ver.php?id=<?php echo $autoeval['id']; ?>" 
                                   class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i> Ver Detalles
                                </a>
                                <?php if ($autoeval['estado'] === 'en_proceso' || $autoeval['estado'] === 'pausada'): ?>
                                    <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion_proceso.php?id=<?php echo $autoeval['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-play-circle"></i> Continuar Autoevaluación
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row mt-4">
    <div class="col-12">
        <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Autoevaluación
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

