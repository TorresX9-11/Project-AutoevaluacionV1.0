<?php
require_once '../config/config.php';
validarTipoUsuario(['admin_supremo']);

$titulo = 'Gestión de Carreras';
include '../includes/header.php';

$pdo = getDBConnection();

$error = '';
$mensaje = '';

// Crear nueva carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $codigo = sanitizar($_POST['codigo'] ?? '');
    
    if (empty($nombre) || empty($codigo)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO carreras (nombre, codigo) VALUES (?, ?)");
            $stmt->execute([$nombre, $codigo]);
            $mensaje = 'Carrera creada exitosamente';
        } catch (PDOException $e) {
            error_log("Error al crear carrera: " . $e->getMessage());
            $error = 'Error al crear la carrera. Puede que el código ya exista.';
        }
    }
}

// Actualizar carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $id = $_POST['id'] ?? null;
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $codigo = sanitizar($_POST['codigo'] ?? '');
    $activa = isset($_POST['activa']) ? 1 : 0;
    
    if (empty($id) || empty($nombre) || empty($codigo)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE carreras SET nombre = ?, codigo = ?, activa = ? WHERE id = ?");
            $stmt->execute([$nombre, $codigo, $activa, $id]);
            $mensaje = 'Carrera actualizada exitosamente';
        } catch (PDOException $e) {
            error_log("Error al actualizar carrera: " . $e->getMessage());
            $error = 'Error al actualizar la carrera. Puede que el código ya exista.';
        }
    }
}

// Eliminar carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = $_POST['id'] ?? null;
    
    if ($id) {
        try {
            // Verificar si hay asignaturas asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM asignaturas WHERE carrera_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                $error = 'No se puede eliminar la carrera porque tiene asignaturas asociadas';
            } else {
                $stmt = $pdo->prepare("DELETE FROM carreras WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = 'Carrera eliminada exitosamente';
            }
        } catch (PDOException $e) {
            error_log("Error al eliminar carrera: " . $e->getMessage());
            $error = 'Error al eliminar la carrera';
        }
    }
}

// Obtener carrera para editar
$carrera_editar = null;
if (isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM carreras WHERE id = ?");
    $stmt->execute([$id]);
    $carrera_editar = $stmt->fetch();
}

// Filtros y búsqueda
$busqueda = sanitizar($_GET['busqueda'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';

// Construir consulta con filtros
$sql = "SELECT * FROM carreras WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR codigo LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

if ($filtro_estado !== '') {
    $sql .= " AND activa = ?";
    $params[] = ($filtro_estado === 'activa') ? 1 : 0;
}

$sql .= " ORDER BY nombre";

if (!empty($params)) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} else {
    $stmt = $pdo->query($sql);
}
$carreras = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Gestión de Carreras</h2>
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
                <h5 class="mb-0"><?php echo $carrera_editar ? 'Editar Carrera' : 'Nueva Carrera'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="<?php echo $carrera_editar ? 'actualizar' : 'crear'; ?>">
                    <?php if ($carrera_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $carrera_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="codigo" name="codigo" 
                               value="<?php echo htmlspecialchars($carrera_editar['codigo'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($carrera_editar['nombre'] ?? ''); ?>" required>
                    </div>
                    
                    <?php if ($carrera_editar): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activa" name="activa" 
                                       <?php echo $carrera_editar['activa'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activa">
                                    Carrera Activa
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $carrera_editar ? 'check' : 'plus'; ?>-circle"></i> 
                        <?php echo $carrera_editar ? 'Actualizar' : 'Crear'; ?> Carrera
                    </button>
                    <?php if ($carrera_editar): ?>
                        <a href="<?php echo BASE_URL; ?>admin_supremo/carreras.php" class="btn btn-secondary">
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
                <h5 class="mb-0">Lista de Carreras (<?php echo count($carreras); ?>)</h5>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <form method="GET" action="" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="busqueda" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                                   placeholder="Nombre o código">
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
                        <?php if ($busqueda || $filtro_estado): ?>
                        <div class="col-12">
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-sm btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                
                <?php if (empty($carreras)): ?>
                    <p class="text-muted">No hay carreras registradas<?php echo ($busqueda || $filtro_estado) ? ' con los filtros aplicados' : ''; ?>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="min-width: 120px;">Código</th>
                                    <th class="text-long" style="min-width: 300px;">Nombre</th>
                                    <th style="min-width: 100px;" class="text-center">Estado</th>
                                    <th class="actions" style="min-width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carreras as $carrera): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($carrera['codigo']); ?></td>
                                        <td class="text-long"><?php echo htmlspecialchars($carrera['nombre']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $carrera['activa'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $carrera['activa'] ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <a href="<?php echo BASE_URL; ?>admin_supremo/carreras.php?editar=<?php echo $carrera['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="" style="display: inline;" 
                                                  onsubmit="return confirm('¿Está seguro de eliminar esta carrera?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $carrera['id']; ?>">
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
                        <?php foreach ($carreras as $carrera): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-2">
                                        <i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($carrera['nombre']); ?>
                                    </h6>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-tag"></i> <strong>Código:</strong><br>
                                            <?php echo htmlspecialchars($carrera['codigo']); ?>
                                        </small>
                                    </p>
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Estado:</small>
                                        <span class="badge bg-<?php echo $carrera['activa'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $carrera['activa'] ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </div>
                                    <div class="mt-3 d-grid gap-2">
                                        <a href="<?php echo BASE_URL; ?>admin_supremo/carreras.php?editar=<?php echo $carrera['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('¿Está seguro de eliminar esta carrera?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $carrera['id']; ?>">
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

<?php include '../includes/footer.php'; ?>

