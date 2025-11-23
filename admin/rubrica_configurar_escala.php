<?php
// Activar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar en pantalla, solo en logs
ini_set('log_errors', 1);

require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

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

if (!$rubrica) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Obtener criterios para calcular puntaje total
$stmt = $pdo->prepare("
    SELECT c.*, 
           GROUP_CONCAT(n.puntaje ORDER BY n.orden SEPARATOR '||') as nivel_puntajes
    FROM criterios c
    LEFT JOIN niveles n ON c.id = n.criterio_id
    WHERE c.rubrica_id = ?
    GROUP BY c.id
    ORDER BY c.orden
");
$stmt->execute([$rubrica_id]);
$criterios = $stmt->fetchAll();

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

$error = '';
$mensaje = '';

// Procesar formulario ANTES de incluir header para evitar problemas con redirects
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puntaje_maximo = floatval($_POST['puntaje_maximo'] ?? 0);
    $exigencia = floatval($_POST['exigencia'] ?? 60);
    $nota_minima = intval($_POST['nota_minima'] ?? 1);
    $nota_maxima = intval($_POST['nota_maxima'] ?? 7);
    $nota_aprobacion = intval($_POST['nota_aprobacion'] ?? 4);
    
    // Validaciones
    if ($puntaje_maximo <= 0) {
        $error = 'El puntaje máximo debe ser mayor a 0';
    } elseif ($exigencia < 0 || $exigencia > 100) {
        $error = 'La exigencia debe estar entre 0 y 100%';
    } elseif ($nota_minima < 1 || $nota_minima > 7) {
        $error = 'La nota mínima debe estar entre 1 y 7';
    } elseif ($nota_maxima < 1 || $nota_maxima > 7 || $nota_maxima <= $nota_minima) {
        $error = 'La nota máxima debe estar entre 1 y 7 y ser mayor que la nota mínima';
    } elseif ($nota_aprobacion < $nota_minima || $nota_aprobacion > $nota_maxima) {
        $error = 'La nota de aprobación debe estar entre la nota mínima y máxima';
    } else {
        try {
            // Verificar si las columnas existen
            $columnas_existen = false;
            try {
                $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
                $columnas_existen = $stmt_check->rowCount() > 0;
            } catch (PDOException $e) {
                // Intentar crear las columnas si no existen
                try {
                    // Verificar si MySQL soporta JSON (MySQL 5.7+), si no usar TEXT
                    $mysql_version = $pdo->query("SELECT VERSION()")->fetchColumn();
                    $version_num = floatval(substr($mysql_version, 0, 3));
                    
                    if ($version_num >= 5.7) {
                        $pdo->exec("ALTER TABLE rubricas ADD COLUMN escala_personalizada BOOLEAN DEFAULT FALSE");
                        $pdo->exec("ALTER TABLE rubricas ADD COLUMN escala_notas JSON NULL");
                    } else {
                        $pdo->exec("ALTER TABLE rubricas ADD COLUMN escala_personalizada BOOLEAN DEFAULT FALSE");
                        $pdo->exec("ALTER TABLE rubricas ADD COLUMN escala_notas TEXT NULL");
                    }
                    $columnas_existen = true;
                } catch (PDOException $e2) {
                    error_log("Error al crear columnas: " . $e2->getMessage());
                    $error = 'Error: Las columnas de escala no existen. Por favor, ejecute el script de migración: ' . $e2->getMessage();
                }
            }
            
            if ($columnas_existen) {
                $escala_notas_array = [
                    'puntaje_maximo' => $puntaje_maximo,
                    'exigencia' => $exigencia,
                    'nota_minima' => $nota_minima,
                    'nota_maxima' => $nota_maxima,
                    'nota_aprobacion' => $nota_aprobacion
                ];
                
                $escala_notas_json = json_encode($escala_notas_array, JSON_UNESCAPED_UNICODE);
                
                // Guardar la escala
                try {
                    // Primero verificar que la rúbrica existe
                    $stmt_check_rubrica = $pdo->prepare("SELECT id FROM rubricas WHERE id = ?");
                    $stmt_check_rubrica->execute([$rubrica_id]);
                    if (!$stmt_check_rubrica->fetch()) {
                        $error = 'Error: La rúbrica no existe.';
                    } else {
                        // Intentar actualizar
                        $pdo->beginTransaction();
                        
                        try {
                            // Usar 1 en lugar de true para MySQL
                            $stmt = $pdo->prepare("UPDATE rubricas SET escala_personalizada = 1, escala_notas = ? WHERE id = ?");
                            $resultado = $stmt->execute([$escala_notas_json, $rubrica_id]);
                            
                            if (!$resultado) {
                                throw new Exception("Error al ejecutar UPDATE");
                            }
                            
                            $filas_afectadas = $stmt->rowCount();
                            
                            if ($filas_afectadas == 0) {
                                // Intentar con UPDATE directo sin WHERE para verificar
                                throw new Exception("No se actualizó ninguna fila. Rúbrica ID: $rubrica_id");
                            }
                            
                            $pdo->commit();
                            
                            // Verificar que se guardó correctamente
                            $stmt_verificar = $pdo->prepare("SELECT escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
                            $stmt_verificar->execute([$rubrica_id]);
                            $verificacion = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
                            
                            if ($verificacion) {
                                $valor_guardado = $verificacion['escala_personalizada'];
                                $escala_guardada = ($valor_guardado == 1 || $valor_guardado === '1' || $valor_guardado === true || $valor_guardado === 'true');
                                
                                if ($escala_guardada && !empty($verificacion['escala_notas'])) {
                                    // Redirigir antes de incluir header
                                    header('Location: ' . BASE_URL . 'admin/rubrica_ver.php?id=' . $rubrica_id);
                                    exit();
                                } else {
                                    error_log("Escala no guardada correctamente. Valor escala_personalizada: " . var_export($valor_guardado, true) . ", escala_notas: " . substr($verificacion['escala_notas'] ?? 'NULL', 0, 100));
                                    $error = 'Error: La escala no se guardó correctamente. Verifique los logs del servidor.';
                                }
                            } else {
                                $error = 'Error: No se pudo verificar la escala guardada.';
                            }
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    }
                } catch (PDOException $e) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Error al guardar escala: " . $e->getMessage());
                    $error = 'Error al guardar la escala de notas: ' . htmlspecialchars($e->getMessage()) . ' (Código: ' . $e->getCode() . ')';
                } catch (Exception $e) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Error al guardar escala: " . $e->getMessage());
                    $error = 'Error: ' . htmlspecialchars($e->getMessage());
                }
            } else {
                $error = 'Error: Las columnas de escala no existen en la base de datos. Por favor, ejecute el script de migración.';
            }
        } catch (PDOException $e) {
            error_log("Error al guardar escala: " . $e->getMessage());
            $error = 'Error al guardar la escala de notas. Por favor, intente nuevamente.';
        }
    }
}

// Verificar si ya tiene escala configurada
$tiene_escala = false;
try {
    $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
    if ($stmt_check->rowCount() > 0) {
        $stmt_escala = $pdo->prepare("SELECT escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
        $stmt_escala->execute([$rubrica_id]);
        $escala_data = $stmt_escala->fetch();
        if ($escala_data && $escala_data['escala_personalizada'] && !empty($escala_data['escala_notas'])) {
            $tiene_escala = true;
        }
    }
} catch (PDOException $e) {
    // Ignorar
}

// Si ya tiene escala, redirigir a ver rúbrica (ANTES de incluir header)
if ($tiene_escala && !isset($_GET['forzar'])) {
    header('Location: ' . BASE_URL . 'admin/rubrica_ver.php?id=' . $rubrica_id);
    exit();
}

// Ahora incluir header después de todos los posibles redirects
$titulo = 'Configurar Escala de Notas';
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Configurar Escala de Notas</h2>
        <p class="text-muted">Rúbrica: <strong><?php echo htmlspecialchars($rubrica['nombre']); ?></strong></p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <strong>Error:</strong> <?php echo $error; ?>
        <?php if (isset($_GET['debug'])): ?>
            <br><small>Debug: Verifique los logs del servidor para más detalles.</small>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<?php if (isset($_GET['debug'])): ?>
    <div class="alert alert-info">
        <strong>Información de depuración:</strong><br>
        <?php
        try {
            $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
            $columnas_existen = $stmt_check->rowCount() > 0;
            echo "Columnas de escala existen: " . ($columnas_existen ? "Sí" : "No") . "<br>";
            
            if ($columnas_existen) {
                $stmt_debug = $pdo->prepare("SELECT escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
                $stmt_debug->execute([$rubrica_id]);
                $debug_data = $stmt_debug->fetch(PDO::FETCH_ASSOC);
                echo "Datos actuales en BD:<br>";
                echo "escala_personalizada: " . var_export($debug_data['escala_personalizada'] ?? 'NULL', true) . "<br>";
                echo "escala_notas: " . htmlspecialchars(substr($debug_data['escala_notas'] ?? 'NULL', 0, 100)) . "<br>";
            }
        } catch (PDOException $e) {
            echo "Error: " . htmlspecialchars($e->getMessage());
        }
        ?>
    </div>
<?php endif; ?>

<div class="alert alert-warning">
    <strong><i class="bi bi-exclamation-triangle"></i> Importante:</strong> 
    Debe configurar la escala de notas para esta rúbrica antes de poder utilizarla. 
    Esta configuración es obligatoria.
</div>

<form method="POST" action="" id="formEscala">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Parámetros de la Escala de Notas</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="puntaje_maximo" class="form-label">Puntaje Máximo <span class="text-danger">*</span></label>
                    <input type="number" step="0.1" class="form-control" id="puntaje_maximo" name="puntaje_maximo" 
                           value="<?php echo number_format($puntaje_total_rubrica, 1); ?>" required readonly>
                    <small class="text-muted">Basado en el puntaje total de la rúbrica: <?php echo number_format($puntaje_total_rubrica, 1); ?> puntos</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="exigencia" class="form-label">Exigencia (%) <span class="text-danger">*</span></label>
                    <input type="number" step="0.1" min="0" max="100" class="form-control" id="exigencia" name="exigencia" 
                           value="60" required>
                    <small class="text-muted">Porcentaje del puntaje máximo necesario para aprobar (nota de aprobación)</small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="nota_minima" class="form-label">Nota Mínima <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="1" max="7" class="form-control" id="nota_minima" name="nota_minima" 
                           value="1" required>
                    <small class="text-muted">Nota mínima de la escala (generalmente 1)</small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="nota_maxima" class="form-label">Nota Máxima <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="1" max="7" class="form-control" id="nota_maxima" name="nota_maxima" 
                           value="7" required>
                    <small class="text-muted">Nota máxima de la escala (generalmente 7)</small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="nota_aprobacion" class="form-label">Nota Aprobación <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="1" max="7" class="form-control" id="nota_aprobacion" name="nota_aprobacion" 
                           value="4" required>
                    <small class="text-muted">Nota mínima para aprobar (generalmente 4)</small>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong>Vista previa de la escala:</strong><br>
                <small id="escala-preview">Complete los campos para ver la vista previa</small>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> Guardar Escala de Notas
            </button>
            <a href="<?php echo BASE_URL; ?>admin/rubricas.php?asignatura_id=<?php echo $rubrica['asignatura_id']; ?>" 
               class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
        </div>
    </div>
</form>

<script>
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
    
    let preview = '<table class="table table-sm table-bordered">';
    preview += '<thead><tr><th>Puntaje</th><th>Nota</th></tr></thead><tbody>';
    puntos.forEach((punto, i) => {
        const nota = notas[i];
        const colorRojo = nota < 4.0 ? ' style="color: red;"' : '';
        preview += `<tr><td>${punto.toFixed(1)}</td><td${colorRojo}>${nota.toFixed(1)}</td></tr>`;
    });
    preview += '</tbody></table>';
    preview += `<strong>Puntaje para aprobar (${notaAprobacion}):</strong> ${puntajeAprobacion.toFixed(1)} puntos (${exigencia}% del total)`;
    
    document.getElementById('escala-preview').innerHTML = preview;
}

// Agregar event listeners
document.addEventListener('DOMContentLoaded', function() {
    const campos = ['puntaje_maximo', 'exigencia', 'nota_minima', 'nota_maxima', 'nota_aprobacion'];
    campos.forEach(campo => {
        const input = document.getElementById(campo);
        if (input) {
            input.addEventListener('input', calcularEscala);
        }
    });
    
    // Calcular escala inicial
    calcularEscala();
});
</script>

<?php include '../includes/footer.php'; ?>

