<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Reportes';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];

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

// Obtener estadísticas con filtros
$asignatura_id = $_GET['asignatura_id'] ?? null;
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_nota_min = $_GET['nota_min'] ?? '';
$filtro_nota_max = $_GET['nota_max'] ?? '';
$busqueda = sanitizar($_GET['busqueda'] ?? '');
$estadisticas = null;
$autoevaluaciones = [];

if ($asignatura_id) {
    // Construir WHERE para estadísticas y autoevaluaciones
    $where_conditions = "a.asignatura_id = ?";
    $params = [$asignatura_id];
    
    if (!empty($filtro_estado)) {
        $where_conditions .= " AND a.estado = ?";
        $params[] = $filtro_estado;
    }
    
    if (!empty($filtro_fecha_desde)) {
        $where_conditions .= " AND DATE(a.created_at) >= ?";
        $params[] = $filtro_fecha_desde;
    }
    
    if (!empty($filtro_fecha_hasta)) {
        $where_conditions .= " AND DATE(a.created_at) <= ?";
        $params[] = $filtro_fecha_hasta;
    }
    
    if (!empty($filtro_nota_min)) {
        $where_conditions .= " AND (a.nota_final >= ? OR a.nota_autoevaluada >= ?)";
        $params[] = $filtro_nota_min;
        $params[] = $filtro_nota_min;
    }
    
    if (!empty($filtro_nota_max)) {
        $where_conditions .= " AND (a.nota_final <= ? OR a.nota_autoevaluada <= ?)";
        $params[] = $filtro_nota_max;
        $params[] = $filtro_nota_max;
    }
    
    // Estadísticas generales
    $sql_stats = "
        SELECT 
            COUNT(*) as total_autoevaluaciones,
            SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'pausada' THEN 1 ELSE 0 END) as pausadas,
            SUM(CASE WHEN estado = 'incompleta' THEN 1 ELSE 0 END) as incompletas,
            AVG(nota_final) as promedio_notas,
            MIN(nota_final) as nota_minima,
            MAX(nota_final) as nota_maxima
        FROM autoevaluaciones a
        WHERE $where_conditions
    ";
    $stmt = $pdo->prepare($sql_stats);
    $stmt->execute($params);
    $estadisticas = $stmt->fetch();
    
    // Obtener autoevaluaciones para el reporte
    $sql_autoeval = "
        SELECT a.*, u.nombre as estudiante_nombre, u.apellido as estudiante_apellido, 
               u.email as estudiante_email, r.nombre as rubrica_nombre
        FROM autoevaluaciones a
        JOIN usuarios u ON a.estudiante_id = u.id
        JOIN rubricas r ON a.rubrica_id = r.id
        WHERE $where_conditions
    ";
    
    $params_autoeval = $params;
    
    if (!empty($busqueda)) {
        $sql_autoeval .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR r.nombre LIKE ?)";
        $busqueda_param = "%$busqueda%";
        $params_autoeval[] = $busqueda_param;
        $params_autoeval[] = $busqueda_param;
        $params_autoeval[] = $busqueda_param;
        $params_autoeval[] = $busqueda_param;
    }
    
    $sql_autoeval .= " ORDER BY u.apellido, u.nombre";
    
    $stmt = $pdo->prepare($sql_autoeval);
    $stmt->execute($params_autoeval);
    $autoevaluaciones = $stmt->fetchAll();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Reportes y Estadísticas</h2>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-funnel"></i> Filtros de Reporte
            <button class="btn btn-sm btn-link text-white float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </h5>
    </div>
    <div class="collapse show" id="filtrosCollapse">
        <div class="card-body">
            <form method="GET" action="" id="filtrosForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="asignatura_id" class="form-label">Asignatura</label>
                        <select class="form-select" id="asignatura_id" name="asignatura_id">
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
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="completada" <?php echo ($filtro_estado === 'completada') ? 'selected' : ''; ?>>Completadas</option>
                            <option value="en_proceso" <?php echo ($filtro_estado === 'en_proceso') ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="pausada" <?php echo ($filtro_estado === 'pausada') ? 'selected' : ''; ?>>Pausadas</option>
                            <option value="incompleta" <?php echo ($filtro_estado === 'incompleta') ? 'selected' : ''; ?>>Incompletas</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="busqueda" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" 
                               value="<?php echo htmlspecialchars($busqueda); ?>" 
                               placeholder="Estudiante o rúbrica">
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
                    
                    <div class="col-md-3">
                        <label for="nota_min" class="form-label">Nota Mínima</label>
                        <input type="number" class="form-control" id="nota_min" name="nota_min" 
                               value="<?php echo htmlspecialchars($filtro_nota_min); ?>" 
                               min="1" max="7" step="0.1" placeholder="1.0">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="nota_max" class="form-label">Nota Máxima</label>
                        <input type="number" class="form-control" id="nota_max" name="nota_max" 
                               value="<?php echo htmlspecialchars($filtro_nota_max); ?>" 
                               min="1" max="7" step="0.1" placeholder="7.0">
                    </div>
                    
                    <div class="col-md-12 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <?php if ($filtro_estado || $filtro_fecha_desde || $filtro_fecha_hasta || $filtro_nota_min || $filtro_nota_max || $busqueda): ?>
                        <a href="<?php echo BASE_URL; ?>admin/reportes.php?asignatura_id=<?php echo $asignatura_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar filtros
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Ver Reporte
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row mb-4">
    <?php if ($asignatura_id && $estadisticas): ?>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Estadísticas Generales</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-3">
                        <strong>Total:</strong> <?php echo $estadisticas['total_autoevaluaciones']; ?>
                    </div>
                    <div class="col-6 mb-3">
                        <strong>Completadas:</strong> 
                        <span class="badge bg-success"><?php echo $estadisticas['completadas']; ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <strong>En Proceso:</strong> 
                        <span class="badge bg-info"><?php echo $estadisticas['en_proceso']; ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <strong>Pausadas:</strong> 
                        <span class="badge bg-warning"><?php echo $estadisticas['pausadas']; ?></span>
                    </div>
                    <div class="col-6 mb-3">
                        <strong>Promedio:</strong> 
                        <span class="badge bg-primary">
                            <?php echo $estadisticas['promedio_notas'] ? number_format($estadisticas['promedio_notas'], 2) : 'N/A'; ?>
                        </span>
                    </div>
                    <div class="col-6 mb-3">
                        <strong>Rango:</strong> 
                        <?php if ($estadisticas['nota_minima'] && $estadisticas['nota_maxima']): ?>
                            <?php echo number_format($estadisticas['nota_minima'], 1); ?> - 
                            <?php echo number_format($estadisticas['nota_maxima'], 1); ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($asignatura_id && $estadisticas): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Exportar Reportes</h5>
            </div>
            <div class="card-body">
                <a href="<?php echo BASE_URL; ?>admin/reportes_exportar_csv.php?asignatura_id=<?php echo $asignatura_id; ?>" 
                   class="btn btn-primary me-2">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
                </a>
                <a href="<?php echo BASE_URL; ?>admin/reportes_exportar_pdf.php?asignatura_id=<?php echo $asignatura_id; ?>" 
                   class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Detalle de Autoevaluaciones (<?php echo count($autoevaluaciones); ?>)</h5>
                <?php if ($filtro_estado || $filtro_fecha_desde || $filtro_fecha_hasta || $filtro_nota_min || $filtro_nota_max || $busqueda): ?>
                    <span class="badge bg-info">Filtros activos</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="min-width: 180px;">Estudiante</th>
                                <th style="min-width: 200px;">Email</th>
                                <th class="text-long" style="min-width: 200px;">Rúbrica</th>
                                <th style="min-width: 100px;" class="text-center">Estado</th>
                                <th style="min-width: 120px;" class="text-center">Nota Autoevaluada</th>
                                <th style="min-width: 120px;" class="text-center">Nota Ajustada</th>
                                <th style="min-width: 100px;" class="text-center">Nota Final</th>
                                <th style="min-width: 130px;">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($autoevaluaciones as $autoeval): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($autoeval['estudiante_nombre'] . ' ' . $autoeval['estudiante_apellido']); ?></td>
                                    <td><?php echo htmlspecialchars($autoeval['estudiante_email']); ?></td>
                                    <td class="text-long"><?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></td>
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
                                            <?php echo number_format($autoeval['nota_autoevaluada'], 1); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($autoeval['nota_ajustada']): ?>
                                            <?php echo number_format($autoeval['nota_ajustada'], 1); ?>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

