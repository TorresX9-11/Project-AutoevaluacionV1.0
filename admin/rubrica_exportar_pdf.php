<?php
// Iniciar output buffering desde el inicio para capturar cualquier output
ob_start();

// Desactivar visualización de errores para evitar output antes del PDF
ini_set('display_errors', 0);
error_reporting(0);

require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

// Limpiar cualquier output previo
ob_clean();

// Intentar cargar TCPDF
$tcpdf_available = false;
$tcpdf_path = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

if (file_exists($tcpdf_path)) {
    require_once $tcpdf_path;
    $tcpdf_available = true;
}

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
    $puntaje_4_0 += $max_puntaje * 0.6;
}

// Obtener escala de notas personalizada si existe
$escala_personalizada = false;
$escala_notas = [];
try {
    $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
    $columnas_existen = $stmt_check->rowCount() > 0;
} catch (PDOException $e) {
    $columnas_existen = false;
}

if ($columnas_existen && isset($rubrica['escala_personalizada']) && $rubrica['escala_personalizada']) {
    $escala_personalizada = true;
    if (!empty($rubrica['escala_notas'])) {
        $escala_notas = json_decode($rubrica['escala_notas'], true);
        if (!is_array($escala_notas)) {
            $escala_notas = [];
            $escala_personalizada = false;
        }
    } else {
        $escala_personalizada = false;
    }
}

// Obtener niveles únicos para los encabezados
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
usort($todos_niveles, function($a, $b) {
    return $b['puntaje'] <=> $a['puntaje'];
});
$niveles_unicos = [];
foreach ($todos_niveles as $nivel) {
    if (!in_array($nivel['nombre'], array_column($niveles_unicos, 'nombre'))) {
        $niveles_unicos[] = $nivel;
    }
}
$niveles_unicos = array_slice($niveles_unicos, 0, 4);

// Generar HTML para PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rúbrica: <?php echo htmlspecialchars($rubrica['nombre']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 10px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #003366; padding-bottom: 10px; }
        .header-title { font-size: 18px; font-weight: bold; color: #003366; margin-bottom: 5px; }
        .header-subtitle { font-size: 14px; color: #003366; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 8px; }
        .info-table td { padding: 5px; border: 1px solid #333; }
        .info-table .label { font-weight: bold; background-color: #f0f0f0; width: 20%; }
        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 7px; }
        .main-table th, .main-table td { padding: 4px; border: 1px solid #333; text-align: left; vertical-align: top; }
        .main-table th { background-color: #003366; color: white; font-weight: bold; text-align: center; }
        .main-table .criterio-cell { background-color: #f8f9fa; font-weight: bold; text-align: center; vertical-align: middle; }
        .main-table .puntos-cell { text-align: center; font-weight: bold; background-color: #f0f0f0; }
        .conversion-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 8px; }
        .conversion-table th, .conversion-table td { padding: 4px; border: 1px solid #333; text-align: center; }
        .conversion-table th { background-color: #003366; color: white; font-weight: bold; }
        .observaciones { margin-top: 15px; }
        .observaciones textarea { width: 100%; min-height: 60px; border: 1px solid #333; padding: 5px; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">TEC-UCT INSTITUTO TECNOLÓGICO</div>
        <div class="header-subtitle"><?php echo htmlspecialchars($rubrica['nombre']); ?></div>
    </div>
    
    <table class="info-table">
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
    
    <table class="main-table">
        <thead>
            <tr>
                <th style="width: 12%;">Criterios</th>
                <th style="width: 15%;">Sub Criterios</th>
                <?php foreach ($niveles_unicos as $nivel): ?>
                    <th style="width: 18%;"><?php echo htmlspecialchars($nivel['nombre']); ?> (<?php echo number_format($nivel['puntaje'], 0); ?>)</th>
                <?php endforeach; ?>
                <th style="width: 8%;">Puntos</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($criterios as $criterio): ?>
                <?php
                $nivel_ids = explode('||', $criterio['nivel_ids'] ?? '');
                $nivel_nombres = explode('||', $criterio['nivel_nombres'] ?? '');
                $nivel_descripciones = explode('||', $criterio['nivel_descripciones'] ?? '');
                $nivel_puntajes = explode('||', $criterio['nivel_puntajes'] ?? '');
                
                $niveles_validos = [];
                for ($i = 0; $i < count($nivel_ids); $i++) {
                    if (!empty($nivel_ids[$i])) {
                        $niveles_validos[] = [
                            'nombre' => $nivel_nombres[$i] ?? '',
                            'descripcion' => $nivel_descripciones[$i] ?? '',
                            'puntaje' => floatval($nivel_puntajes[$i] ?? 0)
                        ];
                    }
                }
                
                $max_puntaje = 0;
                foreach ($niveles_validos as $nivel) {
                    if ($nivel['puntaje'] > $max_puntaje) {
                        $max_puntaje = $nivel['puntaje'];
                    }
                }
                
                $num_filas = max(1, count($niveles_validos));
                ?>
                <?php if (count($niveles_validos) > 0): ?>
                    <?php foreach ($niveles_validos as $idx => $nivel_valido): ?>
                        <tr>
                            <?php if ($idx === 0): ?>
                                <td rowspan="<?php echo $num_filas; ?>" class="criterio-cell">
                                    <?php echo htmlspecialchars($criterio['nombre']); ?>
                                </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($nivel_valido['nombre'] ?: ($criterio['descripcion'] ?? $criterio['nombre'])); ?></td>
                            <?php foreach ($niveles_unicos as $nivel_unico): ?>
                                <td>
                                    <?php
                                    if ($nivel_valido['nombre'] === $nivel_unico['nombre']) {
                                        echo htmlspecialchars($nivel_valido['descripcion'] ?: '-');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="puntos-cell"><?php echo number_format($nivel_valido['puntaje'], 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="criterio-cell"><?php echo htmlspecialchars($criterio['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($criterio['descripcion'] ?? $criterio['nombre']); ?></td>
                        <?php foreach ($niveles_unicos as $nivel_unico): ?>
                            <td>-</td>
                        <?php endforeach; ?>
                        <td class="puntos-cell">0</td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr style="background-color: #cfe2ff;">
                <th colspan="<?php echo 2 + count($niveles_unicos); ?>">Total</th>
                <th class="puntos-cell"><?php echo number_format($puntaje_total, 0); ?></th>
            </tr>
        </tbody>
    </table>
    
    <?php if (!empty($rubrica['descripcion'])): ?>
    <div class="observaciones">
        <strong>OBSERVACIONES:</strong><br>
        <textarea readonly><?php echo htmlspecialchars($rubrica['descripcion']); ?></textarea>
    </div>
    <?php endif; ?>
    
    <table class="conversion-table">
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
            if (!$escala_personalizada || empty($escala_notas) || !isset($escala_notas['puntaje_maximo'])):
            ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px;">
                        <strong>Escala de notas no configurada</strong><br>
                        Esta rúbrica requiere que se configure la escala de notas.
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
                
                $grupos = array_chunk($filas, 4);
                foreach ($grupos as $grupo):
            ?>
                <tr>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <?php if (isset($grupo[$i])): ?>
                            <td><?php echo htmlspecialchars($grupo[$i]['puntaje']); ?></td>
                            <td style="<?php echo $grupo[$i]['es_rojo'] ? 'color: red;' : ''; ?>"><?php echo htmlspecialchars($grupo[$i]['nota']); ?></td>
                        <?php else: ?>
                            <td></td>
                            <td></td>
                        <?php endif; ?>
                    <?php endfor; ?>
                </tr>
            <?php 
                endforeach;
            endif; 
            ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

// Generar PDF si TCPDF está disponible
if ($tcpdf_available) {
    try {
        ob_clean();
        
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('TEC-UCT');
        $pdf->SetAuthor('TEC-UCT');
        $pdf->SetTitle('Rúbrica: ' . $rubrica['nombre']);
        $pdf->SetSubject('Rúbrica de Evaluación');
        
        $pdf->SetMargins(10, 15, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(5);
        
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->setCellHeightRatio(1.3);
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'rubrica_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $rubrica['nombre']) . '_' . date('Y-m-d_His') . '.pdf';
        
        ob_end_clean();
        
        $pdf->Output($filename, 'D');
        exit();
    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><body><h1>Error al generar PDF</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>Por favor, verifique que TCPDF esté instalado correctamente.</p>';
        echo '<p><a href="' . BASE_URL . 'admin/rubrica_ver.php?id=' . $rubrica_id . '">Volver</a></p></body></html>';
        exit();
    }
} else {
    ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body><h1>Error</h1><p>TCPDF no está disponible. Por favor, instale las dependencias con: composer install</p>';
    echo '<p><a href="' . BASE_URL . 'admin/rubrica_ver.php?id=' . $rubrica_id . '">Volver</a></p></body></html>';
    exit();
}
?>

