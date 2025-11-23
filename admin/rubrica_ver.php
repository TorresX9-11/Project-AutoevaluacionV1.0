<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Ver Rúbrica';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$rubrica_id = $_GET['id'] ?? null;

if (!$rubrica_id) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Obtener rúbrica con información del docente
$stmt = $pdo->prepare("
    SELECT r.*, a.nombre as asignatura_nombre, c.nombre as carrera_nombre,
           u.nombre as docente_nombre, u.apellido as docente_apellido
    FROM rubricas r
    JOIN asignaturas a ON r.asignatura_id = a.id
    JOIN carreras c ON a.carrera_id = c.id
    JOIN usuarios u ON a.docente_id = u.id
    WHERE r.id = ? AND a.docente_id = ?
");
$stmt->execute([$rubrica_id, $docente_id]);
$rubrica = $stmt->fetch();

// Verificar si las columnas de escala existen y obtener datos de escala si están disponibles
$columnas_escala_existen = false;
try {
    $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
    $columnas_escala_existen = $stmt_check->rowCount() > 0;
    
    // Si las columnas existen pero no están en el resultado, obtenerlas por separado
    if ($columnas_escala_existen && !isset($rubrica['escala_personalizada'])) {
        try {
            $stmt_escala = $pdo->prepare("SELECT escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
            $stmt_escala->execute([$rubrica_id]);
            $escala_data = $stmt_escala->fetch();
            if ($escala_data) {
                $rubrica['escala_personalizada'] = $escala_data['escala_personalizada'] ?? null;
                $rubrica['escala_notas'] = $escala_data['escala_notas'] ?? null;
            }
        } catch (PDOException $e) {
            // Ignorar error
        }
    }
} catch (PDOException $e) {
    $columnas_escala_existen = false;
}

// Obtener escala de notas personalizada si existe
$escala_personalizada = false;
$escala_notas = [];

// Siempre intentar obtener los datos de escala directamente
if ($columnas_escala_existen) {
    // Obtener datos de escala directamente de la base de datos
    try {
        $stmt_escala = $pdo->prepare("SELECT escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
        $stmt_escala->execute([$rubrica_id]);
        $escala_data = $stmt_escala->fetch(PDO::FETCH_ASSOC);
        
        if ($escala_data) {
            // Verificar escala_personalizada (puede venir como 1, '1', true, etc.)
            $escala_activa = false;
            if (isset($escala_data['escala_personalizada'])) {
                $valor = $escala_data['escala_personalizada'];
                $escala_activa = ($valor === 1 || $valor === '1' || $valor === true || $valor === 'true');
            }
            
            if ($escala_activa && !empty($escala_data['escala_notas'])) {
                $escala_notas = json_decode($escala_data['escala_notas'], true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($escala_notas) && !empty($escala_notas)) {
                    // Verificar que tenga la estructura correcta (nuevo formato)
                    if (isset($escala_notas['puntaje_maximo'])) {
                        $escala_personalizada = true;
                    } else {
                        // Formato antiguo, desactivar
                        $escala_personalizada = false;
                        $escala_notas = [];
                    }
                } else {
                    $escala_personalizada = false;
                    $escala_notas = [];
                }
            } else {
                $escala_personalizada = false;
                $escala_notas = [];
            }
        }
    } catch (PDOException $e) {
        error_log("Error al leer escala: " . $e->getMessage());
        // Si falla, usar datos del array $rubrica si están disponibles
        if (isset($rubrica['escala_personalizada'])) {
            $valor = $rubrica['escala_personalizada'];
            $escala_activa = ($valor === 1 || $valor === '1' || $valor === true || $valor === 'true');
            
            if ($escala_activa && !empty($rubrica['escala_notas'])) {
                $escala_notas = json_decode($rubrica['escala_notas'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($escala_notas) && !empty($escala_notas) && isset($escala_notas['puntaje_maximo'])) {
                    $escala_personalizada = true;
                } else {
                    $escala_personalizada = false;
                    $escala_notas = [];
                }
            } else {
                $escala_personalizada = false;
                $escala_notas = [];
            }
        }
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
?>

<?php
// Calcular puntaje total
$puntaje_total = 0;
$puntaje_4_0 = 0;
foreach ($criterios as $criterio) {
    $nivel_puntajes = explode('||', $criterio['nivel_puntajes'] ?? '');
    $max_puntaje = 0;
    foreach ($nivel_puntajes as $puntaje) {
        if (!empty($puntaje) && floatval($puntaje) > $max_puntaje) {
            $max_puntaje = floatval($puntaje);
        }
    }
    $puntaje_total += $max_puntaje;
    // Puntaje para nota 4.0 (60% del total)
    $puntaje_4_0 += $max_puntaje * 0.6;
}
?>

<style>
.rubrica-evaluacion {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
}
.rubrica-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #003366;
}
.rubrica-logo {
    font-size: 18px;
    font-weight: bold;
    color: #003366;
}
.rubrica-title {
    font-size: 20px;
    font-weight: bold;
    color: #003366;
}
.rubrica-info-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
}
.rubrica-info-table td {
    padding: 8px;
    border: 1px solid #ddd;
    vertical-align: top;
}
.rubrica-info-table .label {
    font-weight: bold;
    background-color: #f8f9fa;
    width: 30%;
}
.rubrica-main-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: fixed;
    border: 2px solid #003366;
}
.rubrica-main-table th,
.rubrica-main-table td {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
    vertical-align: top;
    word-wrap: break-word;
}
.rubrica-main-table th {
    background-color: #003366;
    color: white;
    font-weight: bold;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 10;
    border: 1px solid #002244;
}
.rubrica-main-table .criterio-row {
    background-color: #f8f9fa;
    font-weight: bold;
}
.rubrica-main-table .criterio-row td:first-child {
    vertical-align: middle;
    text-align: center;
    background-color: #e9ecef;
    font-weight: bold;
    border-right: 2px solid #003366;
}
.rubrica-main-table .subcriterio-row {
    background-color: #ffffff;
}
.rubrica-main-table .subcriterio-row td:nth-child(2) {
    padding-left: 20px;
    font-style: italic;
}
.rubrica-main-table .nivel-col {
    width: 20%;
    font-size: 11px;
    min-width: 100px;
    text-align: left;
}
.rubrica-main-table .puntos-col {
    width: 8%;
    text-align: center;
    font-weight: bold;
    min-width: 60px;
    background-color: #f0f0f0;
}
.rubrica-main-table td[rowspan] {
    vertical-align: middle;
    text-align: center;
    background-color: #e9ecef;
    font-weight: bold;
    border-right: 2px solid #003366;
}
.rubrica-main-table tbody tr:hover {
    background-color: #f5f5f5;
}
.rubrica-main-table tbody tr:hover td[rowspan] {
    background-color: #e9ecef;
}
.rubrica-observaciones {
    margin-top: 20px;
    margin-bottom: 20px;
}
.rubrica-observaciones textarea {
    width: 100%;
    min-height: 100px;
    padding: 10px;
    border: 1px solid #ddd;
}
.rubrica-conversion-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.rubrica-conversion-table th,
.rubrica-conversion-table td {
    padding: 5px;
    border: 1px solid #ddd;
    text-align: center;
    font-size: 11px;
}
.rubrica-conversion-table th {
    background-color: #003366;
    color: white;
    font-weight: bold;
}
</style>

<div class="rubrica-evaluacion">
    <!-- Encabezado -->
    <div class="rubrica-header">
        <div class="rubrica-logo">TEC-UCT INSTITUTO TECNOLÓGICO</div>
        <div class="rubrica-title"><?php echo htmlspecialchars($rubrica['nombre']); ?></div>
    </div>
    
    <!-- Información General -->
    <table class="rubrica-info-table">
        <tr>
            <td class="label">Estudiante:</td>
            <td></td>
            <td class="label">Puntaje Total:</td>
            <td><?php echo number_format($puntaje_total, 0); ?></td>
        </tr>
        <tr>
            <td class="label">Carrera:</td>
            <td><?php echo htmlspecialchars($rubrica['carrera_nombre']); ?></td>
            <td class="label">Puntaje 4.0:</td>
            <td><?php echo number_format($puntaje_4_0, 0); ?></td>
        </tr>
        <tr>
            <td class="label">Módulo:</td>
            <td><?php echo htmlspecialchars($rubrica['asignatura_nombre']); ?></td>
            <td class="label">Puntaje Obtenido:</td>
            <td></td>
        </tr>
        <tr>
            <td class="label">Docente:</td>
            <td><?php echo htmlspecialchars($rubrica['docente_nombre'] . ' ' . $rubrica['docente_apellido']); ?></td>
            <td class="label">Nota Final:</td>
            <td></td>
        </tr>
    </table>
    
    <!-- Tabla Principal de Niveles de Desempeño -->
    <table class="rubrica-main-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 15%;">Criterios</th>
                <th rowspan="2" style="width: 15%;">Sub Criterios</th>
                <th colspan="<?php 
                    $max_niveles = 0;
                    foreach ($criterios as $criterio) {
                        $nivel_ids = explode('||', $criterio['nivel_ids'] ?? '');
                        $count = count(array_filter($nivel_ids));
                        if ($count > $max_niveles) {
                            $max_niveles = $count;
                        }
                    }
                    echo $max_niveles;
                ?>">Niveles de desempeño</th>
                <th rowspan="2" class="puntos-col">Puntos</th>
            </tr>
            <tr>
                <?php
                // Obtener todos los niveles únicos ordenados por puntaje descendente
                $todos_niveles = [];
                foreach ($criterios as $criterio) {
                    $nivel_ids = explode('||', $criterio['nivel_ids'] ?? '');
                    $nivel_nombres = explode('||', $criterio['nivel_nombres'] ?? '');
                    $nivel_puntajes = explode('||', $criterio['nivel_puntajes'] ?? '');
                    for ($i = 0; $i < count($nivel_ids); $i++) {
                        if (!empty($nivel_ids[$i])) {
                            $todos_niveles[] = [
                                'nombre' => $nivel_nombres[$i] ?? '',
                                'puntaje' => floatval($nivel_puntajes[$i] ?? 0)
                            ];
                        }
                    }
                }
                // Ordenar por puntaje descendente y obtener únicos
                usort($todos_niveles, function($a, $b) {
                    return $b['puntaje'] <=> $a['puntaje'];
                });
                $niveles_unicos = [];
                foreach ($todos_niveles as $nivel) {
                    if (!in_array($nivel['nombre'], array_column($niveles_unicos, 'nombre'))) {
                        $niveles_unicos[] = $nivel;
                    }
                }
                // Limitar a 4 niveles máximo
                $niveles_unicos = array_slice($niveles_unicos, 0, 4);
                foreach ($niveles_unicos as $nivel): 
                ?>
                    <th class="nivel-col"><?php echo htmlspecialchars($nivel['nombre']); ?> (<?php echo number_format($nivel['puntaje'], 0); ?>)</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($criterios as $index => $criterio): ?>
                <?php
                $nivel_ids = explode('||', $criterio['nivel_ids'] ?? '');
                $nivel_nombres = explode('||', $criterio['nivel_nombres'] ?? '');
                $nivel_descripciones = explode('||', $criterio['nivel_descripciones'] ?? '');
                $nivel_puntajes = explode('||', $criterio['nivel_puntajes'] ?? '');
                
                // Filtrar niveles vacíos
                $niveles_validos = [];
                for ($i = 0; $i < count($nivel_ids); $i++) {
                    if (!empty($nivel_ids[$i])) {
                        $niveles_validos[] = [
                            'id' => $nivel_ids[$i],
                            'nombre' => $nivel_nombres[$i] ?? '',
                            'descripcion' => $nivel_descripciones[$i] ?? '',
                            'puntaje' => floatval($nivel_puntajes[$i] ?? 0)
                        ];
                    }
                }
                
                // Obtener máximo puntaje del criterio
                $max_puntaje_criterio = 0;
                foreach ($niveles_validos as $nivel) {
                    if ($nivel['puntaje'] > $max_puntaje_criterio) {
                        $max_puntaje_criterio = $nivel['puntaje'];
                    }
                }
                
                $num_filas = max(1, count($niveles_validos));
                ?>
                <?php if (count($niveles_validos) > 0): ?>
                    <?php foreach ($niveles_validos as $idx => $nivel_valido): ?>
                        <tr class="<?php echo $idx === 0 ? 'criterio-row' : 'subcriterio-row'; ?>">
                            <?php if ($idx === 0): ?>
                                <td rowspan="<?php echo $num_filas; ?>" style="vertical-align: middle; text-align: center;">
                                    <?php echo htmlspecialchars($criterio['nombre']); ?>
                                </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($nivel_valido['nombre'] ?: ($criterio['descripcion'] ?? $criterio['nombre'])); ?></td>
                            <?php foreach ($niveles_unicos as $nivel_unico): ?>
                                <td class="nivel-col">
                                    <?php
                                    if ($nivel_valido['nombre'] === $nivel_unico['nombre']) {
                                        echo htmlspecialchars($nivel_valido['descripcion'] ?: '-');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="puntos-col"><?php echo number_format($nivel_valido['puntaje'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="criterio-row">
                        <td><?php echo htmlspecialchars($criterio['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($criterio['descripcion'] ?? $criterio['nombre']); ?></td>
                        <?php foreach ($niveles_unicos as $nivel_unico): ?>
                            <td class="nivel-col">-</td>
                        <?php endforeach; ?>
                        <td class="puntos-col">0</td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="table-primary" style="background-color: #cfe2ff;">
                <th colspan="<?php echo 2 + count($niveles_unicos); ?>">Total</th>
                <th class="puntos-col"><?php echo number_format($puntaje_total, 0); ?></th>
            </tr>
        </tbody>
    </table>
    
    <!-- Observaciones -->
    <div class="rubrica-observaciones">
        <strong>OBSERVACIONES:</strong>
        <textarea readonly><?php echo htmlspecialchars($rubrica['descripcion'] ?? ''); ?></textarea>
    </div>
    
    <!-- Tabla de Conversión Puntaje/Nota -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info mb-3">
            <strong>Debug Escala:</strong><br>
            escala_personalizada: <?php echo var_export($escala_personalizada, true); ?><br>
            escala_notas vacío: <?php echo empty($escala_notas) ? 'Sí' : 'No'; ?><br>
            tiene puntaje_maximo: <?php echo isset($escala_notas['puntaje_maximo']) ? 'Sí' : 'No'; ?><br>
            <?php if (!empty($escala_notas)): ?>
                Contenido: <?php echo htmlspecialchars(json_encode($escala_notas)); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <table class="rubrica-conversion-table">
        <thead>
            <tr>
                <th>Puntaje</th>
                <th>Nota</th>
                <th>Puntaje</th>
                <th>Nota</th>
                <th>Puntaje</th>
                <th>Nota</th>
                <th>Puntaje</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Verificar si tiene escala configurada, si no, mostrar mensaje
            if (!$escala_personalizada || empty($escala_notas) || !isset($escala_notas['puntaje_maximo'])):
            ?>
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="alert alert-warning">
                            <strong>Escala de notas no configurada</strong><br>
                            Esta rúbrica requiere que se configure la escala de notas antes de poder utilizarla.<br>
                            <a href="<?php echo BASE_URL; ?>admin/rubrica_configurar_escala.php?id=<?php echo $rubrica_id; ?>" 
                               class="btn btn-primary mt-2">
                                <i class="bi bi-gear"></i> Configurar Escala de Notas
                            </a>
                        </div>
                    </td>
                </tr>
            <?php
            else:
                // Usar escala personalizada con los nuevos parámetros
                $puntaje_maximo = floatval($escala_notas['puntaje_maximo'] ?? $puntaje_total);
                $exigencia = floatval($escala_notas['exigencia'] ?? 60);
                $nota_minima = intval($escala_notas['nota_minima'] ?? 1);
                $nota_maxima = intval($escala_notas['nota_maxima'] ?? 7);
                $nota_aprobacion = intval($escala_notas['nota_aprobacion'] ?? 4);
                
                // Calcular puntos de referencia incrementando de 1 en 1
                $puntos_referencia = [];
                $punto_aprobacion = $puntaje_maximo * $exigencia / 100;
                $puntaje_maximo_int = ceil($puntaje_maximo);
                
                for ($punto = 0; $punto <= $puntaje_maximo_int; $punto++) {
                    if ($punto <= $punto_aprobacion) {
                        // Interpolación lineal entre nota mínima y nota de aprobación
                        $ratio = $punto / $punto_aprobacion;
                        $nota = $nota_minima + ($nota_aprobacion - $nota_minima) * $ratio;
                    } else {
                        // Interpolación lineal entre nota de aprobación y nota máxima
                        $ratio = ($punto - $punto_aprobacion) / ($puntaje_maximo - $punto_aprobacion);
                        $nota = $nota_aprobacion + ($nota_maxima - $nota_aprobacion) * $ratio;
                    }
                    $puntos_referencia[$punto] = $nota;
                }
                
                // Organizar en filas de 4 columnas (cada columna tiene Puntaje y Nota)
                $filas = [];
                $puntos_anteriores = array_keys($puntos_referencia);
                for ($i = 0; $i < count($puntos_anteriores); $i++) {
                    $punto_actual = intval($puntos_anteriores[$i]);
                    $nota_actual = $puntos_referencia[$puntos_anteriores[$i]];
                    
                    $filas[] = [
                        'puntaje' => number_format($punto_actual, 1),
                        'nota' => number_format($nota_actual, 1),
                        'es_rojo' => $nota_actual < 4.0
                    ];
                }
                
                // Dividir en grupos de 4 para mostrar en la tabla (4 columnas dobles = 8 celdas)
                $grupos = array_chunk($filas, 4);
                foreach ($grupos as $grupo):
            ?>
                <tr>
                    <?php 
                    for ($i = 0; $i < 4; $i++): 
                        if (isset($grupo[$i])):
                    ?>
                        <td><?php echo htmlspecialchars($grupo[$i]['puntaje']); ?></td>
                        <td style="<?php echo $grupo[$i]['es_rojo'] ? 'color: red;' : ''; ?>"><?php echo htmlspecialchars($grupo[$i]['nota']); ?></td>
                    <?php 
                        else:
                    ?>
                        <td></td>
                        <td></td>
                    <?php 
                        endif;
                    endfor; 
                    ?>
                </tr>
            <?php 
                endforeach;
            endif; 
            ?>
        </tbody>
    </table>
</div>

<div class="row mt-4">
    <div class="col-12">
        <a href="<?php echo BASE_URL; ?>admin/rubrica_editar.php?id=<?php echo $rubrica_id; ?>" 
           class="btn btn-primary">
            <i class="bi bi-pencil"></i> Editar Rúbrica
        </a>
        <a href="<?php echo BASE_URL; ?>admin/rubrica_exportar_pdf.php?id=<?php echo $rubrica_id; ?>" 
           class="btn btn-success" target="_blank">
            <i class="bi bi-file-pdf"></i> Exportar PDF
        </a>
        <a href="<?php echo BASE_URL; ?>admin/rubrica_exportar.php?rubrica_id=<?php echo $rubrica_id; ?>&asignatura_id=<?php echo $rubrica['asignatura_id']; ?>" 
           class="btn btn-info">
            <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
        </a>
        <a href="<?php echo BASE_URL; ?>admin/rubricas.php?asignatura_id=<?php echo $rubrica['asignatura_id']; ?>" 
           class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

