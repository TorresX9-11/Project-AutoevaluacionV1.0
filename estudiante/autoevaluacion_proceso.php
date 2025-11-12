<?php
require_once '../config/config.php';
validarTipoUsuario(['estudiante']);

$pdo = getDBConnection();
$estudiante_id = $_SESSION['usuario_id'];
$autoeval_id = $_GET['id'] ?? null;
$error = '';

// Validaciones y redirects (antes de incluir header)
if (!$autoeval_id) {
    header('Location: ' . BASE_URL . 'estudiante/autoevaluacion.php');
    exit();
}

// Obtener autoevaluación
$stmt = $pdo->prepare("
    SELECT a.*, asig.nombre as asignatura_nombre, r.nombre as rubrica_nombre,
           u.nombre as estudiante_nombre, u.apellido as estudiante_apellido
    FROM autoevaluaciones a
    JOIN asignaturas asig ON a.asignatura_id = asig.id
    JOIN rubricas r ON a.rubrica_id = r.id
    JOIN usuarios u ON a.estudiante_id = u.id
    WHERE a.id = ? AND a.estudiante_id = ?
");
$stmt->execute([$autoeval_id, $estudiante_id]);
$autoeval = $stmt->fetch();

if (!$autoeval) {
    header('Location: ' . BASE_URL . 'estudiante/autoevaluacion.php');
    exit();
}

// Si está completada, redirigir a ver resultado
if ($autoeval['estado'] === 'completada') {
    header('Location: ' . BASE_URL . 'estudiante/autoevaluacion_ver.php?id=' . $autoeval_id);
    exit();
}

// Obtener rúbrica con criterios y niveles
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
$stmt->execute([$autoeval['rubrica_id']]);
$criterios = $stmt->fetchAll();

// Obtener respuestas existentes
$stmt = $pdo->prepare("
    SELECT * FROM respuestas_autoevaluacion 
    WHERE autoevaluacion_id = ?
");
$stmt->execute([$autoeval_id]);
$respuestas = $stmt->fetchAll();
$respuestas_map = [];
foreach ($respuestas as $resp) {
    $respuestas_map[$resp['criterio_id']] = $resp;
}

// Guardar respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_respuesta') {
        $criterio_id = $_POST['criterio_id'] ?? null;
        $nivel_id = $_POST['nivel_id'] ?? null;
        $comentario = sanitizar($_POST['comentario'] ?? '');
        
        if ($criterio_id && $nivel_id) {
            // Obtener puntaje del nivel
            $stmt = $pdo->prepare("SELECT puntaje FROM niveles WHERE id = ?");
            $stmt->execute([$nivel_id]);
            $nivel = $stmt->fetch();
            
            if ($nivel) {
                try {
                    // Verificar si ya existe respuesta
                    $stmt = $pdo->prepare("SELECT id FROM respuestas_autoevaluacion WHERE autoevaluacion_id = ? AND criterio_id = ?");
                    $stmt->execute([$autoeval_id, $criterio_id]);
                    $existente = $stmt->fetch();
                    
                    if ($existente) {
                        $stmt = $pdo->prepare("
                            UPDATE respuestas_autoevaluacion 
                            SET nivel_id = ?, puntaje = ?, comentario = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$nivel_id, $nivel['puntaje'], $comentario, $existente['id']]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO respuestas_autoevaluacion 
                            (autoevaluacion_id, criterio_id, nivel_id, puntaje, comentario) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$autoeval_id, $criterio_id, $nivel_id, $nivel['puntaje'], $comentario]);
                    }
                    
                    echo json_encode(['success' => true]);
                    exit();
                } catch (PDOException $e) {
                    error_log("Error al guardar respuesta: " . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'Error al guardar']);
                    exit();
                }
            }
        }
    } elseif ($_POST['accion'] === 'finalizar') {
        // Finalizar autoevaluación
        try {
            // Calcular nota
            $stmt = $pdo->prepare("
                SELECT SUM(ra.puntaje * c.peso / 100) as nota
                FROM respuestas_autoevaluacion ra
                JOIN criterios c ON ra.criterio_id = c.id
                WHERE ra.autoevaluacion_id = ?
            ");
            $stmt->execute([$autoeval_id]);
            $resultado = $stmt->fetch();
            $nota = $resultado['nota'] ?? 0;
            
            $tiempo_fin = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                UPDATE autoevaluaciones 
                SET estado = 'completada', nota_autoevaluada = ?, nota_final = ?, tiempo_fin = ? 
                WHERE id = ?
            ");
            $stmt->execute([$nota, $nota, $tiempo_fin, $autoeval_id]);
            
            header('Location: ' . BASE_URL . 'estudiante/autoevaluacion_ver.php?id=' . $autoeval_id);
            exit();
        } catch (PDOException $e) {
            error_log("Error al finalizar autoevaluación: " . $e->getMessage());
            $error = 'Error al finalizar la autoevaluación.';
        }
    }
}

// Incluir header después de procesar POST y validaciones (para que los redirects funcionen)
$titulo = 'Autoevaluación en Proceso';
include '../includes/header.php';

// Calcular progreso
$total_criterios = count($criterios);
$criterios_respondidos = count($respuestas_map);
$progreso = $total_criterios > 0 ? ($criterios_respondidos / $total_criterios) * 100 : 0;

// Tiempo restante
$tiempo_restante = $autoeval['tiempo_restante'] ?? AUTOEVAL_TIME_LIMIT;
?>

<!-- Contador de tiempo -->
<div class="timer-container" id="timerContainer">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-clock"></i> 
            <strong>Tiempo restante:</strong> 
            <span id="timerDisplay"><?php echo gmdate('i:s', $tiempo_restante); ?></span>
        </div>
        <div>
            <span class="badge bg-light text-dark" id="progresoBadge">
                <?php echo $criterios_respondidos; ?>/<?php echo $total_criterios; ?> criterios
            </span>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <h2>Autoevaluación: <?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></h2>
        <p class="text-muted">
            Asignatura: <strong><?php echo htmlspecialchars($autoeval['asignatura_nombre']); ?></strong>
        </p>
    </div>
</div>

<!-- Barra de progreso -->
<div class="row mb-4">
    <div class="col-12">
        <div class="progress" style="height: 30px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: <?php echo $progreso; ?>%"
                 aria-valuenow="<?php echo $progreso; ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                <?php echo number_format($progreso, 1); ?>%
            </div>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<form id="formAutoevaluacion" method="POST" action="">
    <input type="hidden" name="accion" value="guardar_respuesta" id="accionInput">
    
    <?php foreach ($criterios as $index => $criterio): ?>
        <?php
        $nivel_ids = explode('||', $criterio['nivel_ids']);
        $nivel_nombres = explode('||', $criterio['nivel_nombres']);
        $nivel_descripciones = explode('||', $criterio['nivel_descripciones']);
        $nivel_puntajes = explode('||', $criterio['nivel_puntajes']);
        $respuesta_actual = $respuestas_map[$criterio['id']] ?? null;
        ?>
        
        <div class="card mb-4 criterio-card" data-criterio-id="<?php echo $criterio['id']; ?>">
            <div class="card-header">
                <h5 class="mb-0">
                    Criterio <?php echo $index + 1; ?>: <?php echo htmlspecialchars($criterio['nombre']); ?>
                    <?php if ($criterio['peso'] > 0): ?>
                        <span class="badge bg-info">Peso: <?php echo number_format($criterio['peso'], 1); ?>%</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($criterio['descripcion']): ?>
                    <p class="text-muted"><?php echo htmlspecialchars($criterio['descripcion']); ?></p>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label">Seleccione el nivel que mejor describe su desempeño:</label>
                    <div class="row g-3" id="niveles-<?php echo $criterio['id']; ?>">
                        <?php for ($i = 0; $i < count($nivel_ids); $i++): ?>
                            <?php if (!empty($nivel_ids[$i])): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card nivel-option h-100 
                                        <?php echo ($respuesta_actual && $respuesta_actual['nivel_id'] == $nivel_ids[$i]) ? 'selected' : ''; ?>"
                                         onclick="seleccionarNivel(<?php echo $criterio['id']; ?>, <?php echo $nivel_ids[$i]; ?>, <?php echo $nivel_puntajes[$i]; ?>)">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($nivel_nombres[$i]); ?></h6>
                                            <?php if (!empty($nivel_descripciones[$i])): ?>
                                                <p class="card-text small"><?php echo htmlspecialchars($nivel_descripciones[$i]); ?></p>
                                            <?php endif; ?>
                                            <p class="card-text">
                                                <strong class="text-primary">Puntaje: <?php echo number_format($nivel_puntajes[$i], 1); ?></strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="comentario-<?php echo $criterio['id']; ?>" class="form-label">Comentario (opcional)</label>
                    <textarea class="form-control" 
                              id="comentario-<?php echo $criterio['id']; ?>" 
                              name="comentario" 
                              rows="2"
                              onchange="guardarComentario(<?php echo $criterio['id']; ?>)"><?php echo $respuesta_actual ? htmlspecialchars($respuesta_actual['comentario']) : ''; ?></textarea>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="row mb-4">
        <div class="col-12">
            <button type="button" class="btn btn-success btn-lg w-100" onclick="finalizarAutoevaluacion()">
                <i class="bi bi-check-circle"></i> Finalizar Autoevaluación
            </button>
        </div>
    </div>
</form>

<script>
const autoevalId = <?php echo $autoeval_id; ?>;
const tiempoRestante = <?php echo $tiempo_restante; ?>;
let tiempoActual = tiempoRestante;
let timerInterval = null;

// Iniciar contador
function iniciarContador() {
    timerInterval = setInterval(() => {
        tiempoActual--;
        
        if (tiempoActual <= 0) {
            clearInterval(timerInterval);
            tiempoAgotado();
            return;
        }
        
        actualizarDisplay();
        
        // Alertas
        if (tiempoActual === 120) {
            mostrarAlerta('Quedan 2 minutos', 'warning');
        } else if (tiempoActual === 60) {
            mostrarAlerta('Queda 1 minuto', 'danger');
        } else if (tiempoActual === 30) {
            mostrarAlerta('Quedan 30 segundos', 'danger');
        }
    }, 1000);
}

function actualizarDisplay() {
    const minutos = Math.floor(tiempoActual / 60);
    const segundos = tiempoActual % 60;
    document.getElementById('timerDisplay').textContent = 
        `${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
    
    const container = document.getElementById('timerContainer');
    if (tiempoActual <= 60) {
        container.className = 'timer-container danger';
    } else if (tiempoActual <= 120) {
        container.className = 'timer-container warning';
    }
}

function tiempoAgotado() {
    mostrarAlerta('Tiempo agotado. La autoevaluación se ha pausado como incompleta.', 'danger');
    
    // Actualizar estado en servidor
    fetch('<?php echo BASE_URL; ?>estudiante/autoevaluacion_pausar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${autoevalId}&accion=pausar`
    }).then(() => {
        setTimeout(() => {
            window.location.href = '<?php echo BASE_URL; ?>estudiante/autoevaluacion.php';
        }, 2000);
    });
}

function mostrarAlerta(mensaje, tipo) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${tipo} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

function seleccionarNivel(criterioId, nivelId, puntaje) {
    // Deseleccionar otros niveles
    const container = document.querySelector(`#niveles-${criterioId}`);
    container.querySelectorAll('.nivel-option').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Seleccionar nivel
    event.currentTarget.classList.add('selected');
    
    // Guardar respuesta
    guardarRespuesta(criterioId, nivelId);
}

function guardarRespuesta(criterioId, nivelId) {
    const formData = new FormData();
    formData.append('accion', 'guardar_respuesta');
    formData.append('criterio_id', criterioId);
    formData.append('nivel_id', nivelId);
    
    fetch('', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              actualizarProgreso();
          }
      });
}

function guardarComentario(criterioId) {
    const comentario = document.getElementById(`comentario-${criterioId}`).value;
    const respuesta = document.querySelector(`[data-criterio-id="${criterioId}"] .nivel-option.selected`);
    
    if (respuesta) {
        const nivelId = respuesta.getAttribute('onclick').match(/\d+/)[0];
        const formData = new FormData();
        formData.append('accion', 'guardar_respuesta');
        formData.append('criterio_id', criterioId);
        formData.append('nivel_id', nivelId);
        formData.append('comentario', comentario);
        
        fetch('', {
            method: 'POST',
            body: formData
        });
    }
}

function actualizarProgreso() {
    const criterios = document.querySelectorAll('.criterio-card');
    const respondidos = document.querySelectorAll('.criterio-card .nivel-option.selected').length;
    const total = criterios.length;
    const progreso = (respondidos / total) * 100;
    
    document.querySelector('.progress-bar').style.width = `${progreso}%`;
    document.querySelector('.progress-bar').textContent = `${progreso.toFixed(1)}%`;
    document.getElementById('progresoBadge').textContent = `${respondidos}/${total} criterios`;
}

function finalizarAutoevaluacion() {
    if (confirm('¿Está seguro de finalizar la autoevaluación? No podrá modificarla después.')) {
        const form = document.getElementById('formAutoevaluacion');
        const input = document.getElementById('accionInput');
        input.value = 'finalizar';
        form.submit();
    }
}

// Iniciar contador al cargar
document.addEventListener('DOMContentLoaded', function() {
    iniciarContador();
    actualizarProgreso();
});

// Guardar tiempo restante al salir
window.addEventListener('beforeunload', function() {
    if (timerInterval) {
        fetch('<?php echo BASE_URL; ?>estudiante/autoevaluacion_actualizar_tiempo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${autoevalId}&tiempo=${tiempoActual}`
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>

