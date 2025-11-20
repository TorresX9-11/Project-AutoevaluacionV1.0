<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);


$titulo = 'Nueva Rúbrica';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$asignatura_id = $_GET['asignatura_id'] ?? null;

$error = '';
$mensaje = '';

// Validar asignatura
if ($asignatura_id) {
    $stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ? AND docente_id = ?");
    $stmt->execute([$asignatura_id, $docente_id]);
    $asignatura = $stmt->fetch();
    
    if (!$asignatura) {
        header('Location: ' . BASE_URL . 'admin/rubricas.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $descripcion = sanitizar($_POST['descripcion'] ?? '');
    $asignatura_id = $_POST['asignatura_id'] ?? null;
    $criterios = $_POST['criterios'] ?? [];
    
    if (empty($nombre) || empty($asignatura_id) || empty($criterios)) {
        $error = 'Por favor, complete todos los campos requeridos';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insertar rúbrica
            $stmt = $pdo->prepare("INSERT INTO rubricas (asignatura_id, nombre, descripcion) VALUES (?, ?, ?)");
            $stmt->execute([$asignatura_id, $nombre, $descripcion]);
            $rubrica_id = $pdo->lastInsertId();
            
            // Insertar criterios y niveles
            foreach ($criterios as $index => $criterio) {
                if (!empty($criterio['nombre'])) {
                    $stmt = $pdo->prepare("INSERT INTO criterios (rubrica_id, nombre, descripcion, peso, orden) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $rubrica_id,
                        sanitizar($criterio['nombre']),
                        sanitizar($criterio['descripcion'] ?? ''),
                        floatval($criterio['peso'] ?? 0),
                        $index + 1
                    ]);
                    $criterio_id = $pdo->lastInsertId();
                    
                    // Insertar niveles
                    if (!empty($criterio['niveles'])) {
                        foreach ($criterio['niveles'] as $nivel_index => $nivel) {
                            if (!empty($nivel['nombre'])) {
                                $stmt = $pdo->prepare("INSERT INTO niveles (criterio_id, nombre, descripcion, puntaje, orden) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $criterio_id,
                                    sanitizar($nivel['nombre']),
                                    sanitizar($nivel['descripcion'] ?? ''),
                                    floatval($nivel['puntaje'] ?? 0),
                                    $nivel_index + 1
                                ]);
                            }
                        }
                    }
                }
            }
            
            $pdo->commit();
            $mensaje = 'Rúbrica creada exitosamente';
            header('refresh:2;url=' . BASE_URL . 'admin/rubrica_editar.php?id=' . $rubrica_id);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al crear rúbrica: " . $e->getMessage());
            $error = 'Error al crear la rúbrica. Por favor, intente nuevamente.';
        }
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
?>

<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Nueva Rúbrica</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<form method="POST" action="" id="formRubrica">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Información General</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="asignatura_id" class="form-label">Asignatura <span class="text-danger">*</span></label>
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
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Rúbrica <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Instrucciones</h5>
                </div>
                <div class="card-body">
                    <p>Complete la información de la rúbrica y luego agregue los criterios de evaluación con sus respectivos niveles.</p>
                    <p><strong>Pasos:</strong></p>
                    <ol>
                        <li>Seleccione la asignatura</li>
                        <li>Ingrese el nombre y descripción de la rúbrica</li>
                        <li>Agregue criterios de evaluación</li>
                        <li>Para cada criterio, defina los niveles con sus puntajes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Criterios de Evaluación</h5>
            <button type="button" class="btn btn-sm btn-primary" onclick="agregarCriterio()">
                <i class="bi bi-plus-circle"></i> Agregar Criterio
            </button>
        </div>
        <div class="card-body">
            <div id="criterios-container">
                <!-- Los criterios se agregarán dinámicamente aquí -->
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save"></i> Guardar Rúbrica
            </button>
            <a href="<?php echo BASE_URL; ?>admin/rubricas.php" class="btn btn-secondary btn-lg">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
        </div>
    </div>
</form>

<script>
let criterioIndex = 0;

function agregarCriterio() {
    const container = document.getElementById('criterios-container');
    const criterioHtml = `
        <div class="card mb-3 criterio-item" data-index="${criterioIndex}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Criterio ${criterioIndex + 1}</h6>
                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarCriterio(${criterioIndex})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre del Criterio <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="criterios[${criterioIndex}][nombre]" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Peso (%)</label>
                        <input type="number" class="form-control" name="criterios[${criterioIndex}][peso]" 
                               min="0" max="100" step="0.01" value="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="criterios[${criterioIndex}][descripcion]" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Niveles de Evaluación</label>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="agregarNivel(${criterioIndex})">
                            <i class="bi bi-plus"></i> Agregar Nivel
                        </button>
                    </div>
                    <div class="niveles-container" data-criterio="${criterioIndex}">
                        <!-- Los niveles se agregarán aquí -->
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', criterioHtml);
    criterioIndex++;
}

function agregarNivel(criterioIndex) {
    const container = document.querySelector(`.niveles-container[data-criterio="${criterioIndex}"]`);
    const nivelIndex = container.children.length;
    const nivelHtml = `
        <div class="card mb-2 nivel-item">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Nombre del Nivel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="criterios[${criterioIndex}][niveles][${nivelIndex}][nombre]" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Puntaje <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="criterios[${criterioIndex}][niveles][${nivelIndex}][puntaje]" 
                               min="0" step="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="criterios[${criterioIndex}][niveles][${nivelIndex}][descripcion]">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-danger w-100" onclick="eliminarNivel(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', nivelHtml);
}

function eliminarCriterio(index) {
    const item = document.querySelector(`.criterio-item[data-index="${index}"]`);
    if (item) {
        item.remove();
    }
}

function eliminarNivel(button) {
    button.closest('.nivel-item').remove();
}

// Agregar un criterio inicial
document.addEventListener('DOMContentLoaded', function() {
    agregarCriterio();
});
</script>

<?php include '../includes/footer.php'; ?>

