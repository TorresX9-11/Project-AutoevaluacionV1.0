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

// Decodificar escala de notas si existe
$escala_notas = [];
$escala_personalizada = false;
try {
    $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
    $columnas_existen = $stmt_check->rowCount() > 0;
} catch (PDOException $e) {
    $columnas_existen = false;
}

if ($columnas_existen) {
    // Obtener datos de escala directamente
    try {
        $stmt_escala = $pdo->prepare("SELECT escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
        $stmt_escala->execute([$rubrica_id]);
        $escala_data = $stmt_escala->fetch();
        
        if ($escala_data && $escala_data['escala_personalizada'] && !empty($escala_data['escala_notas'])) {
            $escala_personalizada = true;
            $escala_notas = json_decode($escala_data['escala_notas'], true);
            if (!is_array($escala_notas) || !isset($escala_notas['puntaje_maximo'])) {
                $escala_notas = [];
                $escala_personalizada = false;
            }
        }
    } catch (PDOException $e) {
        // Ignorar
    }
}

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
    $escala_personalizada = isset($_POST['escala_personalizada']) ? 1 : 0;
    
    // Procesar escala de notas personalizada
    $escala_notas_json = null;
    if ($escala_personalizada) {
        $puntaje_maximo = floatval($_POST['puntaje_maximo'] ?? 0);
        $exigencia = floatval($_POST['exigencia'] ?? 60);
        $nota_minima = intval($_POST['nota_minima'] ?? 1);
        $nota_maxima = intval($_POST['nota_maxima'] ?? 7);
        $nota_aprobacion = intval($_POST['nota_aprobacion'] ?? 4);
        
        $escala_notas_array = [
            'puntaje_maximo' => $puntaje_maximo,
            'exigencia' => $exigencia,
            'nota_minima' => $nota_minima,
            'nota_maxima' => $nota_maxima,
            'nota_aprobacion' => $nota_aprobacion
        ];
        
        $escala_notas_json = json_encode($escala_notas_array);
    }
    
    if (empty($nombre)) {
        $error = 'El nombre es requerido';
    } else {
        try {
            // Verificar si la columna existe, si no, usar consulta sin ella
            $columnas_escala = '';
            $valores_escala = [];
            
            // Intentar verificar si las columnas existen
            try {
                $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
                if ($stmt_check->rowCount() > 0) {
                    $columnas_escala = ', escala_personalizada = ?, escala_notas = ?';
                    $valores_escala = [$escala_personalizada, $escala_notas_json];
                }
            } catch (PDOException $e) {
                // Las columnas no existen, continuar sin ellas
            }
            
            $stmt = $pdo->prepare("UPDATE rubricas SET nombre = ?, descripcion = ?, activa = ?" . $columnas_escala . " WHERE id = ?");
            $params = array_merge([$nombre, $descripcion, $activa], $valores_escala, [$rubrica_id]);
            $stmt->execute($params);
            
            $mensaje = 'Rúbrica actualizada exitosamente';
            $rubrica['nombre'] = $nombre;
            $rubrica['descripcion'] = $descripcion;
            $rubrica['activa'] = $activa;
            if (!empty($valores_escala)) {
                $rubrica['escala_personalizada'] = $escala_personalizada;
                $rubrica['escala_notas'] = $escala_notas_json;
                $escala_personalizada = (bool)$escala_personalizada;
                if ($escala_notas_json) {
                    $escala_notas = json_decode($escala_notas_json, true);
                }
            }
            
            // Recalcular valores de escala para mostrar
            if ($escala_personalizada && !empty($escala_notas_json)) {
                $escala_data = json_decode($escala_notas_json, true);
                $puntaje_maximo = floatval($escala_data['puntaje_maximo'] ?? 0);
                $exigencia = floatval($escala_data['exigencia'] ?? 60);
                $nota_minima = intval($escala_data['nota_minima'] ?? 1);
                $nota_maxima = intval($escala_data['nota_maxima'] ?? 7);
                $nota_aprobacion = intval($escala_data['nota_aprobacion'] ?? 4);
            }
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

<?php if (!$escala_personalizada): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle"></i> Escala de notas no configurada:</strong> 
        Esta rúbrica requiere que se configure la escala de notas antes de poder utilizarla.
        <a href="<?php echo BASE_URL; ?>admin/rubrica_configurar_escala.php?id=<?php echo $rubrica_id; ?>" 
           class="btn btn-sm btn-primary ms-2">
            <i class="bi bi-gear"></i> Configurar Escala de Notas Ahora
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
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
            
            <!-- Sección de Escala de Notas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Configuración de Escala de Notas</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Calcular puntaje total (suma de puntajes máximos sin ponderar)
                    $puntaje_total_rubrica = 0;
                    foreach ($criterios as $criterio) {
                        $nivel_puntajes = $criterio['nivel_puntajes'] !== null ? explode('||', $criterio['nivel_puntajes']) : [];
                        $max_puntaje = 0;
                        foreach ($nivel_puntajes as $puntaje) {
                            if (!empty($puntaje) && floatval($puntaje) > $max_puntaje) {
                                $max_puntaje = floatval($puntaje);
                            }
                        }
                        $puntaje_total_rubrica += $max_puntaje;
                    }
                    
                    // Obtener valores de escala si existen
                    $puntaje_maximo = $puntaje_total_rubrica;
                    $exigencia = 60;
                    $nota_minima = 1;
                    $nota_maxima = 7;
                    $nota_aprobacion = 4;
                    
                    if ($escala_personalizada && !empty($escala_notas) && isset($escala_notas['puntaje_maximo'])) {
                        $puntaje_maximo = floatval($escala_notas['puntaje_maximo'] ?? $puntaje_total_rubrica);
                        $exigencia = floatval($escala_notas['exigencia'] ?? 60);
                        $nota_minima = intval($escala_notas['nota_minima'] ?? 1);
                        $nota_maxima = intval($escala_notas['nota_maxima'] ?? 7);
                        $nota_aprobacion = intval($escala_notas['nota_aprobacion'] ?? 4);
                    }
                    ?>
                    
                    <?php if ($escala_personalizada): ?>
                        <div class="alert alert-success mb-3">
                            <strong><i class="bi bi-check-circle"></i> Escala de notas configurada</strong><br>
                            <small>La escala de notas está configurada para esta rúbrica.</small>
                            <div class="mt-2">
                                <a href="<?php echo BASE_URL; ?>admin/rubrica_configurar_escala.php?id=<?php echo $rubrica_id; ?>&forzar=1" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Reconfigurar Escala
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-3">
                            <strong><i class="bi bi-exclamation-triangle"></i> Escala de notas no configurada</strong><br>
                            <small>Debe configurar la escala de notas antes de poder utilizar esta rúbrica.</small>
                            <div class="mt-2">
                                <a href="<?php echo BASE_URL; ?>admin/rubrica_configurar_escala.php?id=<?php echo $rubrica_id; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="bi bi-gear"></i> Configurar Escala Ahora
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="escala_personalizada" name="escala_personalizada" 
                                   <?php echo $escala_personalizada ? 'checked' : ''; ?> onchange="toggleEscalaPersonalizada()">
                            <label class="form-check-label" for="escala_personalizada">
                                Configurar escala de notas personalizada
                            </label>
                        </div>
                    </div>
                    
                    <div id="escala-container" style="display: <?php echo $escala_personalizada ? 'block' : 'none'; ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="puntaje_maximo" class="form-label">Puntaje Máximo <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" class="form-control" id="puntaje_maximo" name="puntaje_maximo" 
                                       value="<?php echo number_format($puntaje_maximo, 1); ?>" required readonly>
                                <small class="text-muted">Basado en el puntaje total de la rúbrica: <?php echo number_format($puntaje_total_rubrica, 1); ?></small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="exigencia" class="form-label">Exigencia (%) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" min="0" max="100" class="form-control" id="exigencia" name="exigencia" 
                                       value="<?php echo number_format($exigencia, 1); ?>" required>
                                <small class="text-muted">Porcentaje necesario para aprobar (nota 4.0)</small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="nota_minima" class="form-label">Nota Mínima <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" max="7" class="form-control" id="nota_minima" name="nota_minima" 
                                       value="<?php echo number_format($nota_minima, 0); ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="nota_maxima" class="form-label">Nota Máxima <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" max="7" class="form-control" id="nota_maxima" name="nota_maxima" 
                                       value="<?php echo number_format($nota_maxima, 0); ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="nota_aprobacion" class="form-label">Nota Aprobación <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" max="7" class="form-control" id="nota_aprobacion" name="nota_aprobacion" 
                                       value="<?php echo number_format($nota_aprobacion, 0); ?>" required>
                                <small class="text-muted">Nota mínima para aprobar</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Vista previa de la escala:</strong><br>
                            <small id="escala-preview">Calculando...</small>
                        </div>
                    </div>
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

<script>
function toggleEscalaPersonalizada() {
    const checkbox = document.getElementById('escala_personalizada');
    const container = document.getElementById('escala-container');
    container.style.display = checkbox.checked ? 'block' : 'none';
    if (checkbox.checked) {
        calcularEscala();
    }
}

function calcularEscala() {
    const puntajeMaximo = parseFloat(document.getElementById('puntaje_maximo').value) || 0;
    const exigencia = parseFloat(document.getElementById('exigencia').value) || 60;
    const notaMinima = parseInt(document.getElementById('nota_minima').value) || 1;
    const notaMaxima = parseInt(document.getElementById('nota_maxima').value) || 7;
    const notaAprobacion = parseInt(document.getElementById('nota_aprobacion').value) || 4;
    
    if (puntajeMaximo <= 0) {
        document.getElementById('escala-preview').textContent = 'Ingrese un puntaje máximo válido';
        return;
    }
    
    // Calcular puntaje para nota de aprobación
    const puntajeAprobacion = (puntajeMaximo * exigencia) / 100;
    
    // Calcular puntos de referencia incrementando de 1 en 1
    const puntos = [];
    const notas = [];
    const puntajeMaximoInt = Math.ceil(puntajeMaximo);
    
    for (let punto = 0; punto <= puntajeMaximoInt; punto++) {
        puntos.push(punto);
        
        if (punto <= puntajeAprobacion) {
            // Interpolación lineal entre nota mínima y nota de aprobación
            const ratio = punto / puntajeAprobacion;
            const nota = notaMinima + (notaAprobacion - notaMinima) * ratio;
            notas.push(nota);
        } else {
            // Interpolación lineal entre nota de aprobación y nota máxima
            const ratio = (punto - puntajeAprobacion) / (puntajeMaximo - puntajeAprobacion);
            const nota = notaAprobacion + (notaMaxima - notaAprobacion) * ratio;
            notas.push(nota);
        }
    }
    
    let preview = 'Puntaje → Nota: ';
    preview += puntos.map((p, i) => `${p.toFixed(1)} → ${notas[i].toFixed(1)}`).join(' | ');
    preview += `<br><strong>Puntaje para aprobar (${notaAprobacion}):</strong> ${puntajeAprobacion.toFixed(1)} puntos (${exigencia}% del total)`;
    
    document.getElementById('escala-preview').innerHTML = preview;
}

// Agregar event listeners a los campos
document.addEventListener('DOMContentLoaded', function() {
    const campos = ['puntaje_maximo', 'exigencia', 'nota_minima', 'nota_maxima', 'nota_aprobacion'];
    campos.forEach(campo => {
        const input = document.getElementById(campo);
        if (input) {
            input.addEventListener('input', calcularEscala);
        }
    });
    
    // Si viene de crear/importar, activar y mostrar la configuración de escala
    <?php if (isset($_GET['configurar_escala'])): ?>
    document.getElementById('escala_personalizada').checked = true;
    toggleEscalaPersonalizada();
    // Scroll suave a la sección de escala
    setTimeout(() => {
        document.getElementById('escala-container').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 300);
    <?php else: ?>
    // Calcular escala inicial si está activada
    if (document.getElementById('escala_personalizada').checked) {
        calcularEscala();
    }
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>

