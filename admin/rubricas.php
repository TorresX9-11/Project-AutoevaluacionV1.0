<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Gestión de Rúbricas';
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

// Obtener rúbricas con filtros
$asignatura_id = $_GET['asignatura_id'] ?? null;
$busqueda = sanitizar($_GET['busqueda'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$rubricas = [];

if ($asignatura_id) {
    // Verificar si las columnas de escala existen
    $columnas_escala_existen = false;
    try {
        $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
        $columnas_escala_existen = $stmt_check->rowCount() > 0;
    } catch (PDOException $e) {
        $columnas_escala_existen = false;
    }
    
    $campos_escala = '';
    if ($columnas_escala_existen) {
        $campos_escala = ', r.escala_personalizada, r.escala_notas';
    }
    
    $sql = "
        SELECT r.*, a.nombre as asignatura_nombre, c.nombre as carrera_nombre
        $campos_escala
        FROM rubricas r
        JOIN asignaturas a ON r.asignatura_id = a.id
        JOIN carreras c ON a.carrera_id = c.id
        WHERE r.asignatura_id = ? AND a.docente_id = ?
    ";
    $params = [$asignatura_id, $docente_id];
    
    if (!empty($busqueda)) {
        $sql .= " AND (r.nombre LIKE ? OR r.descripcion LIKE ?)";
        $busqueda_param = "%$busqueda%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    
    if ($filtro_estado !== '') {
        $sql .= " AND r.activa = ?";
        $params[] = ($filtro_estado === 'activa') ? 1 : 0;
    }
    
    if (!empty($filtro_fecha_desde)) {
        $sql .= " AND DATE(r.created_at) >= ?";
        $params[] = $filtro_fecha_desde;
    }
    
    if (!empty($filtro_fecha_hasta)) {
        $sql .= " AND DATE(r.created_at) <= ?";
        $params[] = $filtro_fecha_hasta;
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rubricas = $stmt->fetchAll();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Gestión de Rúbricas</h2>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Seleccionar Asignatura</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="asignatura_id" class="form-label">Asignatura</label>
                        <select class="form-select" id="asignatura_id" name="asignatura_id" required>
                            <option value="">-- Seleccione una asignatura --</option>
                            <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id']; ?>" 
                                        <?php echo ($asignatura_id == $asig['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Ver Rúbricas</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php if ($asignatura_id): ?>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body">
                <a href="<?php echo BASE_URL; ?>admin/rubrica_nueva.php?asignatura_id=<?php echo $asignatura_id; ?>" 
                   class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-plus-circle"></i> Nueva Rúbrica
                </a>
                <a href="<?php echo BASE_URL; ?>admin/rubrica_importar.php?asignatura_id=<?php echo $asignatura_id; ?>" 
                   class="btn btn-secondary w-100 mb-2">
                    <i class="bi bi-upload"></i> Importar (CSV/PDF)
                </a>
                <a href="<?php echo BASE_URL; ?>admin/rubrica_exportar.php?asignatura_id=<?php echo $asignatura_id; ?>" 
                   class="btn btn-info w-100">
                    <i class="bi bi-download"></i> Exportar a CSV
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($asignatura_id && !empty($rubricas)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Rúbricas de la Asignatura (<?php echo count($rubricas); ?>)</h5>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <form method="GET" action="" class="mb-3">
                    <input type="hidden" name="asignatura_id" value="<?php echo $asignatura_id; ?>">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="busqueda" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                                   placeholder="Nombre o descripción">
                        </div>
                        <div class="col-md-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Todos</option>
                                <option value="activa" <?php echo ($filtro_estado === 'activa') ? 'selected' : ''; ?>>Activas</option>
                                <option value="inactiva" <?php echo ($filtro_estado === 'inactiva') ? 'selected' : ''; ?>>Inactivas</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="fecha_desde" class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                   value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                   value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <?php if ($busqueda || $filtro_estado || $filtro_fecha_desde || $filtro_fecha_hasta): ?>
                        <div class="col-12">
                            <a href="<?php echo BASE_URL; ?>admin/rubricas.php?asignatura_id=<?php echo $asignatura_id; ?>" class="btn btn-sm btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="min-width: 180px;">Nombre</th>
                                <th class="text-long" style="min-width: 250px;">Descripción</th>
                                <th style="min-width: 100px;" class="text-center">Criterios</th>
                                <th style="min-width: 100px;" class="text-center">Estado</th>
                                <th style="min-width: 120px;" class="text-center">Escala</th>
                                <th style="min-width: 120px;">Fecha Creación</th>
                                <th class="actions" style="min-width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rubricas as $rubrica): ?>
                                <?php
                                // Contar criterios
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM criterios WHERE rubrica_id = ?");
                                $stmt->execute([$rubrica['id']]);
                                $criterios_count = $stmt->fetch()['total'];
                                
                                // Verificar si tiene escala configurada
                                $tiene_escala = false;
                                if ($columnas_escala_existen && isset($rubrica['escala_personalizada']) && $rubrica['escala_personalizada']) {
                                    if (!empty($rubrica['escala_notas'])) {
                                        $escala_data = json_decode($rubrica['escala_notas'], true);
                                        $tiene_escala = (is_array($escala_data) && isset($escala_data['puntaje_maximo']));
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rubrica['nombre']); ?></td>
                                    <td class="text-long"><?php echo htmlspecialchars($rubrica['descripcion'] ?? 'Sin descripción'); ?></td>
                                    <td class="text-center"><span class="badge bg-info"><?php echo $criterios_count; ?></span></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $rubrica['activa'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $rubrica['activa'] ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($tiene_escala): ?>
                                            <span class="badge bg-success" title="Escala de notas configurada">
                                                <i class="bi bi-check-circle"></i> Configurada
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" title="Escala de notas no configurada">
                                                <i class="bi bi-exclamation-triangle"></i> Pendiente
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($rubrica['created_at'])); ?></td>
                                    <td class="actions">
                                        <a href="<?php echo BASE_URL; ?>admin/rubrica_editar.php?id=<?php echo $rubrica['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>admin/rubrica_ver.php?id=<?php echo $rubrica['id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>admin/rubrica_eliminar.php?id=<?php echo $rubrica['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('¿Está seguro de eliminar esta rúbrica?');">
                                            <i class="bi bi-trash"></i>
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
<?php elseif ($asignatura_id && empty($rubricas)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <?php if ($busqueda || $filtro_estado || $filtro_fecha_desde || $filtro_fecha_hasta): ?>
                No se encontraron rúbricas con los filtros seleccionados.
            <?php else: ?>
                No hay rúbricas para esta asignatura. Cree una nueva rúbrica o impórtela desde un archivo CSV.
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

