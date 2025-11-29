<?php
require_once '../config/config.php';
validarTipoUsuario(['estudiante']);

$pdo = getDBConnection();
$estudiante_id = $_SESSION['usuario_id'];

$error = '';
$mensaje = '';

// Iniciar autoevaluación (procesar antes de incluir header para evitar problemas con redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'iniciar') {
    $asignatura_id = $_POST['asignatura_id'] ?? null;
    $rubrica_id = $_POST['rubrica_id'] ?? null;
    
    if ($asignatura_id && $rubrica_id) {
        // Verificar si ya existe una autoevaluación completada
        $stmt = $pdo->prepare("
            SELECT * FROM autoevaluaciones 
            WHERE estudiante_id = ? AND asignatura_id = ? AND rubrica_id = ? 
            AND estado = 'completada'
        ");
        $stmt->execute([$estudiante_id, $asignatura_id, $rubrica_id]);
        $completada = $stmt->fetch();
        
        if ($completada) {
            $error = 'Ya ha completado esta autoevaluación. No puede volver a realizarla.';
        } else {
            // Verificar si ya existe una autoevaluación en proceso o pausada
            $stmt = $pdo->prepare("
                SELECT * FROM autoevaluaciones 
                WHERE estudiante_id = ? AND asignatura_id = ? AND rubrica_id = ? 
                AND estado IN ('en_proceso', 'pausada')
            ");
            $stmt->execute([$estudiante_id, $asignatura_id, $rubrica_id]);
            $existente = $stmt->fetch();
            
            if ($existente) {
                // Reanudar autoevaluación existente
                header('Location: ' . BASE_URL . 'estudiante/autoevaluacion_proceso.php?id=' . $existente['id']);
                exit();
            } else {
                // Verificar si hay una autoevaluación incompleta (pendiente)
                $stmt = $pdo->prepare("
                    SELECT * FROM autoevaluaciones 
                    WHERE estudiante_id = ? AND asignatura_id = ? AND rubrica_id = ? 
                    AND estado = 'incompleta'
                ");
                $stmt->execute([$estudiante_id, $asignatura_id, $rubrica_id]);
                $incompleta = $stmt->fetch();
                
                if ($incompleta) {
                    $error = 'Tiene una autoevaluación pendiente para esta asignatura. Contacte a su docente para reiniciar el proceso.';
                } else {
                    // Crear nueva autoevaluación
                    try {
                        $tiempo_inicio = date('Y-m-d H:i:s');
                        $stmt = $pdo->prepare("
                            INSERT INTO autoevaluaciones 
                            (estudiante_id, asignatura_id, rubrica_id, estado, tiempo_inicio, tiempo_restante) 
                            VALUES (?, ?, ?, 'en_proceso', ?, ?)
                        ");
                        $stmt->execute([$estudiante_id, $asignatura_id, $rubrica_id, $tiempo_inicio, AUTOEVAL_TIME_LIMIT]);
                        $autoeval_id = $pdo->lastInsertId();
                        
                        header('Location: ' . BASE_URL . 'estudiante/autoevaluacion_proceso.php?id=' . $autoeval_id);
                        exit();
                    } catch (PDOException $e) {
                        error_log("Error al iniciar autoevaluación: " . $e->getMessage());
                        $error = 'Error al iniciar la autoevaluación. Por favor, intente nuevamente.';
                    }
                }
            }
        }
    } else {
        $error = 'Por favor, seleccione una asignatura y rúbrica';
    }
}

// Obtener asignaturas del estudiante (después de procesar POST para evitar problemas con redirects)
$stmt = $pdo->prepare("
    SELECT a.*, c.nombre as carrera_nombre, r.id as rubrica_id, r.nombre as rubrica_nombre
    FROM estudiantes_asignaturas ea
    JOIN asignaturas a ON ea.asignatura_id = a.id
    JOIN carreras c ON a.carrera_id = c.id
    LEFT JOIN rubricas r ON r.asignatura_id = a.id AND r.activa = 1
    WHERE ea.estudiante_id = ? AND ea.activo = 1 AND a.activa = 1
    ORDER BY c.nombre, a.nombre
");
$stmt->execute([$estudiante_id]);
$asignaturas = $stmt->fetchAll();

// Incluir header después de procesar POST (para que los redirects funcionen)
$titulo = 'Autoevaluación';
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Iniciar Autoevaluación</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<?php if (empty($asignaturas)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> No tiene asignaturas asignadas o no hay rúbricas disponibles.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Seleccionar Asignatura y Rúbrica</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="accion" value="iniciar">
                
                <div class="mb-3">
                    <label for="asignatura_id" class="form-label">Asignatura <span class="text-danger">*</span></label>
                    <select class="form-select" id="asignatura_id" name="asignatura_id" required 
                            onchange="cargarRubricas(this.value)">
                        <option value="">-- Seleccione una asignatura --</option>
                        <?php 
                        $asignaturas_agrupadas = [];
                        foreach ($asignaturas as $asig) {
                            if (!isset($asignaturas_agrupadas[$asig['id']])) {
                                $asignaturas_agrupadas[$asig['id']] = $asig;
                            }
                        }
                        foreach ($asignaturas_agrupadas as $asig): 
                        ?>
                            <option value="<?php echo $asig['id']; ?>" 
                                    data-carrera="<?php echo htmlspecialchars($asig['carrera_nombre']); ?>">
                                <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="rubrica_id" class="form-label">Rúbrica <span class="text-danger">*</span></label>
                    <select class="form-select" id="rubrica_id" name="rubrica_id" required disabled>
                        <option value="">-- Primero seleccione una asignatura --</option>
                    </select>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Importante:</strong> Tendrá 5 minutos para completar la autoevaluación. 
                    El tiempo comenzará al iniciar el proceso.
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-play-circle"></i> Iniciar Autoevaluación
                </button>
            </form>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Autoevaluaciones en Proceso</h5>
        </div>
        <div class="card-body">
            <?php
            $stmt = $pdo->prepare("
                SELECT a.*, asig.nombre as asignatura_nombre, r.nombre as rubrica_nombre
                FROM autoevaluaciones a
                JOIN asignaturas asig ON a.asignatura_id = asig.id
                JOIN rubricas r ON a.rubrica_id = r.id
                WHERE a.estudiante_id = ? AND a.estado IN ('en_proceso', 'pausada')
                ORDER BY a.tiempo_inicio DESC
            ");
            $stmt->execute([$estudiante_id]);
            $en_proceso = $stmt->fetchAll();
            
            if (empty($en_proceso)):
            ?>
                <p class="text-muted">No hay autoevaluaciones en proceso.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Asignatura</th>
                                <th>Rúbrica</th>
                                <th>Estado</th>
                                <th>Iniciada</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($en_proceso as $autoeval): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($autoeval['asignatura_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $autoeval['estado'] === 'pausada' ? 'warning' : 'info'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $autoeval['estado'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($autoeval['tiempo_inicio'])); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion_proceso.php?id=<?php echo $autoeval['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-play"></i> Continuar
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
<?php endif; ?>

<script>
const asignaturas = <?php echo json_encode($asignaturas); ?>;

function cargarRubricas(asignaturaId) {
    const rubricaSelect = document.getElementById('rubrica_id');
    rubricaSelect.innerHTML = '<option value="">-- Cargando rúbricas --</option>';
    rubricaSelect.disabled = true;
    
    if (!asignaturaId) {
        rubricaSelect.innerHTML = '<option value="">-- Primero seleccione una asignatura --</option>';
        return;
    }
    
    const rubricas = asignaturas.filter(a => a.id == asignaturaId && a.rubrica_id);
    
    if (rubricas.length === 0) {
        rubricaSelect.innerHTML = '<option value="">-- No hay rúbricas disponibles --</option>';
        rubricaSelect.disabled = true;
        return;
    }
    
    rubricaSelect.innerHTML = '<option value="">-- Seleccione una rúbrica --</option>';
    rubricas.forEach(rubrica => {
        const option = document.createElement('option');
        option.value = rubrica.rubrica_id;
        option.textContent = rubrica.rubrica_nombre;
        rubricaSelect.appendChild(option);
    });
    
    rubricaSelect.disabled = false;
}
</script>

<?php include '../includes/footer.php'; ?>

