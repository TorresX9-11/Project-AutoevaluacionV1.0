<?php
require_once '../config/config.php';
validarTipoUsuario(['admin_supremo']);

$titulo = 'Gestión de Asignaturas';
include '../includes/header.php';

$pdo = getDBConnection();

$error = '';
$mensaje = '';

// Crear nueva asignatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $carrera_id = $_POST['carrera_id'] ?? null;
    $docente_id = $_POST['docente_id'] ?? null;
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $codigo = sanitizar($_POST['codigo'] ?? '');
    $semestre = sanitizar($_POST['semestre'] ?? '');
    $anio = $_POST['anio'] ?? date('Y');
    
    if (empty($carrera_id) || empty($docente_id) || empty($nombre) || empty($codigo)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } else {
        // Verificar que el docente esté conectado a la carrera (usando docentes_carreras)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM docentes_carreras 
            WHERE docente_id = ? AND carrera_id = ? AND activo = 1
        ");
        $stmt->execute([$docente_id, $carrera_id]);
        $result = $stmt->fetch();
        
        if ($result['total'] == 0) {
            $error = 'El docente seleccionado no está asociado a la carrera seleccionada. Por favor, asigne la carrera al docente primero.';
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
                $error = 'Error al crear la asignatura. Puede que el código ya exista para este docente.';
            }
        }
    }
}

// Actualizar asignatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $id = $_POST['id'] ?? null;
    $carrera_id = $_POST['carrera_id'] ?? null;
    $docente_id = $_POST['docente_id'] ?? null;
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $codigo = sanitizar($_POST['codigo'] ?? '');
    $semestre = sanitizar($_POST['semestre'] ?? '');
    $anio = $_POST['anio'] ?? date('Y');
    $activa = isset($_POST['activa']) ? 1 : 0;
    
    if (empty($id) || empty($carrera_id) || empty($docente_id) || empty($nombre) || empty($codigo)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } else {
        // Verificar que el docente esté conectado a la carrera
        $stmt = $pdo->prepare("
            SELECT dc.carrera_id 
            FROM docentes_carreras dc
            WHERE dc.docente_id = ? AND dc.carrera_id = ? AND dc.activo = 1
        ");
        $stmt->execute([$docente_id, $carrera_id]);
        $docente_carrera = $stmt->fetch();
        
        if (!$docente_carrera) {
            $error = 'El docente seleccionado no está asociado a la carrera seleccionada. Por favor, asigne la carrera al docente primero.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE asignaturas 
                    SET carrera_id = ?, nombre = ?, codigo = ?, docente_id = ?, semestre = ?, anio = ?, activa = ?
                    WHERE id = ?
                ");
                $stmt->execute([$carrera_id, $nombre, $codigo, $docente_id, $semestre, $anio, $activa, $id]);
                $mensaje = 'Asignatura actualizada exitosamente';
            } catch (PDOException $e) {
                error_log("Error al actualizar asignatura: " . $e->getMessage());
                $error = 'Error al actualizar la asignatura. Puede que el código ya exista para este docente.';
            }
        }
    }
}

// Eliminar asignatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = $_POST['id'] ?? null;
    
    if ($id) {
        try {
            // Verificar si hay rúbricas o autoevaluaciones asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rubricas WHERE asignatura_id = ?");
            $stmt->execute([$id]);
            $rubricas = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM autoevaluaciones WHERE asignatura_id = ?");
            $stmt->execute([$id]);
            $autoeval = $stmt->fetch();
            
            if ($rubricas['total'] > 0 || $autoeval['total'] > 0) {
                $error = 'No se puede eliminar la asignatura porque tiene rúbricas o autoevaluaciones asociadas';
            } else {
                $stmt = $pdo->prepare("DELETE FROM asignaturas WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = 'Asignatura eliminada exitosamente';
            }
        } catch (PDOException $e) {
            error_log("Error al eliminar asignatura: " . $e->getMessage());
            $error = 'Error al eliminar la asignatura';
        }
    }
}

// Obtener asignatura para editar
$asignatura_editar = null;
if (isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ?");
    $stmt->execute([$id]);
    $asignatura_editar = $stmt->fetch();
}

// Obtener carreras
$stmt = $pdo->query("SELECT * FROM carreras WHERE activa = 1 ORDER BY nombre");
$carreras = $stmt->fetchAll();

// Obtener docentes (con sus carreras asociadas)
$stmt = $pdo->query("
    SELECT DISTINCT u.*, 
           GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') as carreras_nombres
    FROM usuarios u
    LEFT JOIN docentes_carreras dc ON u.id = dc.docente_id AND dc.activo = 1
    LEFT JOIN carreras c ON dc.carrera_id = c.id
    WHERE u.tipo = 'docente' AND u.activo = 1
    GROUP BY u.id
    ORDER BY u.apellido, u.nombre
");
$docentes = $stmt->fetchAll();

// Filtros y búsqueda
$docente_filtro = $_GET['docente_id'] ?? null;
$carrera_filtro = $_GET['carrera_id'] ?? null;
$busqueda = sanitizar($_GET['busqueda'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';

// Obtener todas las asignaturas con información relacionada
$sql = "
    SELECT a.*, c.nombre as carrera_nombre, c.codigo as carrera_codigo,
           u.nombre as docente_nombre, u.apellido as docente_apellido, u.email as docente_email
    FROM asignaturas a
    JOIN carreras c ON a.carrera_id = c.id
    JOIN usuarios u ON a.docente_id = u.id
    WHERE 1=1
";
$params = [];

if ($docente_filtro) {
    $sql .= " AND a.docente_id = ?";
    $params[] = $docente_filtro;
}

if ($carrera_filtro) {
    $sql .= " AND a.carrera_id = ?";
    $params[] = $carrera_filtro;
}

if (!empty($busqueda)) {
    $sql .= " AND (a.nombre LIKE ? OR a.codigo LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

if ($filtro_estado !== '') {
    $sql .= " AND a.activa = ?";
    $params[] = ($filtro_estado === 'activa') ? 1 : 0;
}

$sql .= " ORDER BY c.nombre, a.nombre";

if (!empty($params)) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} else {
    $stmt = $pdo->query($sql);
}
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
                <h5 class="mb-0"><?php echo $asignatura_editar ? 'Editar Asignatura' : 'Nueva Asignatura'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="formAsignatura">
                    <input type="hidden" name="accion" value="<?php echo $asignatura_editar ? 'actualizar' : 'crear'; ?>">
                    <?php if ($asignatura_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $asignatura_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="carrera_id" class="form-label">Carrera <span class="text-danger">*</span></label>
                        <select class="form-select" id="carrera_id" name="carrera_id" required>
                            <option value="">-- Seleccione una carrera --</option>
                            <?php foreach ($carreras as $carrera): ?>
                                <option value="<?php echo $carrera['id']; ?>"
                                        <?php echo ($asignatura_editar && $asignatura_editar['carrera_id'] == $carrera['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($carrera['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="docente_id" class="form-label">Docente <span class="text-danger">*</span></label>
                        <select class="form-select" id="docente_id" name="docente_id" required>
                            <option value="">-- Seleccione un docente --</option>
                            <?php foreach ($docentes as $docente): ?>
                                <option value="<?php echo $docente['id']; ?>"
                                        data-carrera-id="<?php echo $docente['carrera_id']; ?>"
                                        <?php 
                                        $selected = false;
                                        if ($asignatura_editar && $asignatura_editar['docente_id'] == $docente['id']) {
                                            $selected = true;
                                        } elseif ($docente_filtro && $docente_filtro == $docente['id']) {
                                            $selected = true;
                                        }
                                        echo $selected ? 'selected' : ''; 
                                        ?>>
                                    <?php echo htmlspecialchars($docente['apellido'] . ', ' . $docente['nombre'] . ' (' . $docente['email'] . ')'); ?>
                                    <?php if ($docente['carreras_nombres']): ?>
                                        - Carreras: <?php echo htmlspecialchars($docente['carreras_nombres']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">El docente debe estar asociado a la carrera seleccionada</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="codigo" name="codigo" 
                               value="<?php echo htmlspecialchars($asignatura_editar['codigo'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($asignatura_editar['nombre'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="semestre" class="form-label">Semestre</label>
                            <input type="text" class="form-control" id="semestre" name="semestre" 
                                   value="<?php echo htmlspecialchars($asignatura_editar['semestre'] ?? ''); ?>"
                                   placeholder="Ej: 2024-1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="anio" class="form-label">Año</label>
                            <input type="number" class="form-control" id="anio" name="anio" 
                                   value="<?php echo $asignatura_editar['anio'] ?? date('Y'); ?>" 
                                   min="2020" max="2030">
                        </div>
                    </div>
                    
                    <?php if ($asignatura_editar): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activa" name="activa" 
                                       <?php echo $asignatura_editar['activa'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activa">
                                    Asignatura Activa
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $asignatura_editar ? 'check' : 'plus'; ?>-circle"></i> 
                        <?php echo $asignatura_editar ? 'Actualizar' : 'Crear'; ?> Asignatura
                    </button>
                    <?php if ($asignatura_editar): ?>
                        <a href="<?php echo BASE_URL; ?>admin_supremo/asignaturas.php" class="btn btn-secondary">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Asignaturas (<?php echo count($asignaturas); ?>)</h5>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <form method="GET" action="" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="carrera_id" class="form-label">Carrera</label>
                            <select class="form-select" id="carrera_id" name="carrera_id">
                                <option value="">Todas las carreras</option>
                                <?php 
                                $stmt = $pdo->query("SELECT * FROM carreras WHERE activa = 1 ORDER BY nombre");
                                $todas_carreras = $stmt->fetchAll();
                                foreach ($todas_carreras as $carr): ?>
                                    <option value="<?php echo $carr['id']; ?>" 
                                            <?php echo ($carrera_filtro == $carr['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($carr['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="docente_id" class="form-label">Docente</label>
                            <select class="form-select" id="docente_id" name="docente_id">
                                <option value="">Todos los docentes</option>
                                <?php foreach ($docentes as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>" 
                                            <?php echo ($docente_filtro == $doc['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doc['apellido'] . ', ' . $doc['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="busqueda" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                                   placeholder="Nombre, código o docente">
                        </div>
                        <div class="col-md-4">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Todos</option>
                                <option value="activa" <?php echo ($filtro_estado === 'activa') ? 'selected' : ''; ?>>Activas</option>
                                <option value="inactiva" <?php echo ($filtro_estado === 'inactiva') ? 'selected' : ''; ?>>Inactivas</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <?php if ($docente_filtro || $carrera_filtro || $busqueda || $filtro_estado): ?>
                        <div class="col-12">
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-sm btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                
                <?php if (empty($asignaturas)): ?>
                    <p class="text-muted">No hay asignaturas registradas<?php echo ($docente_filtro || $carrera_filtro || $busqueda || $filtro_estado) ? ' con los filtros aplicados' : ''; ?>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="min-width: 120px;">Código</th>
                                    <th class="text-long" style="min-width: 200px;">Nombre</th>
                                    <th class="text-long" style="min-width: 250px;">Carrera</th>
                                    <th style="min-width: 180px;">Docente</th>
                                    <th style="min-width: 100px;" class="text-center">Estado</th>
                                    <th class="actions" style="min-width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asignaturas as $asig): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asig['codigo']); ?></td>
                                        <td class="text-long"><?php echo htmlspecialchars($asig['nombre']); ?></td>
                                        <td class="text-long"><?php echo htmlspecialchars($asig['carrera_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($asig['docente_apellido'] . ', ' . $asig['docente_nombre']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $asig['activa'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $asig['activa'] ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <a href="<?php echo BASE_URL; ?>admin_supremo/asignaturas.php?editar=<?php echo $asig['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="" style="display: inline;" 
                                                  onsubmit="return confirm('¿Está seguro de eliminar esta asignatura?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $asig['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
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
                        <?php foreach ($asignaturas as $asig): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-2">
                                        <i class="bi bi-book"></i> <?php echo htmlspecialchars($asig['nombre']); ?>
                                    </h6>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-tag"></i> <strong>Código:</strong><br>
                                            <?php echo htmlspecialchars($asig['codigo']); ?>
                                        </small>
                                    </p>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-mortarboard"></i> <strong>Carrera:</strong><br>
                                            <?php echo htmlspecialchars($asig['carrera_nombre']); ?>
                                        </small>
                                    </p>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-person"></i> <strong>Docente:</strong><br>
                                            <?php echo htmlspecialchars($asig['docente_apellido'] . ', ' . $asig['docente_nombre']); ?>
                                        </small>
                                    </p>
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Estado:</small>
                                        <span class="badge bg-<?php echo $asig['activa'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $asig['activa'] ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </div>
                                    <div class="mt-3 d-grid gap-2">
                                        <a href="<?php echo BASE_URL; ?>admin_supremo/asignaturas.php?editar=<?php echo $asig['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('¿Está seguro de eliminar esta asignatura?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $asig['id']; ?>">
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
// Validar que el docente seleccionado pertenezca a la carrera seleccionada
document.getElementById('carrera_id')?.addEventListener('change', function() {
    const carreraId = this.value;
    const docenteSelect = document.getElementById('docente_id');
    
    if (docenteSelect) {
        Array.from(docenteSelect.options).forEach(option => {
            if (option.value && option.dataset.carreraId) {
                if (carreraId && option.dataset.carreraId != carreraId) {
                    option.style.display = 'none';
                } else {
                    option.style.display = 'block';
                }
            }
        });
        
        // Si el docente seleccionado no pertenece a la carrera, limpiar selección
        const selectedOption = docenteSelect.options[docenteSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.carreraId && selectedOption.dataset.carreraId != carreraId) {
            docenteSelect.value = '';
        }
    }
});

// Validar al enviar el formulario
document.getElementById('formAsignatura')?.addEventListener('submit', function(e) {
    const carreraId = document.getElementById('carrera_id').value;
    const docenteId = document.getElementById('docente_id').value;
    const selectedOption = document.getElementById('docente_id').options[document.getElementById('docente_id').selectedIndex];
    
    if (carreraId && docenteId && selectedOption && selectedOption.dataset.carreraId) {
        if (selectedOption.dataset.carreraId != carreraId) {
            e.preventDefault();
            alert('El docente seleccionado no está asociado a la carrera seleccionada');
            return false;
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>

