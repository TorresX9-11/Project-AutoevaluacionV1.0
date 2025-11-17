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
$asignatura_id = $_GET['asignatura_id'] ?? null;

if (!$asignatura_id) {
    header('Location: ' . BASE_URL . 'admin/reportes.php');
    exit();
}

// Verificar que la asignatura pertenece al docente
$stmt = $pdo->prepare("
    SELECT a.*, c.nombre as carrera_nombre 
    FROM asignaturas a
    JOIN carreras c ON a.carrera_id = c.id
    WHERE a.id = ? AND a.docente_id = ?
");
$stmt->execute([$asignatura_id, $docente_id]);
$asignatura = $stmt->fetch();

if (!$asignatura) {
    header('Location: ' . BASE_URL . 'admin/reportes.php');
    exit();
}

// Obtener datos
$stmt = $pdo->prepare("
    SELECT a.*, u.nombre as estudiante_nombre, u.apellido as estudiante_apellido, 
           u.email as estudiante_email, u.rut, r.nombre as rubrica_nombre
    FROM autoevaluaciones a
    JOIN usuarios u ON a.estudiante_id = u.id
    JOIN rubricas r ON a.rubrica_id = r.id
    WHERE a.asignatura_id = ?
    ORDER BY u.apellido, u.nombre
");
$stmt->execute([$asignatura_id]);
$autoevaluaciones = $stmt->fetchAll();

// Estadísticas
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        AVG(nota_final) as promedio,
        MIN(nota_final) as minima,
        MAX(nota_final) as maxima
    FROM autoevaluaciones
    WHERE asignatura_id = ? AND nota_final IS NOT NULL
");
$stmt->execute([$asignatura_id]);
$estadisticas = $stmt->fetch();

// Generar HTML para PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Autoevaluaciones</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 8px; margin: 0; padding: 0; }
        h1 { color: #003366; font-size: 16px; margin-bottom: 3px; margin-top: 0; }
        h2 { color: #0066CC; font-size: 12px; margin-top: 3px; margin-bottom: 8px; }
        h3 { color: #003366; font-size: 10px; margin-top: 10px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 7px; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 3px; text-align: left; word-wrap: break-word; overflow: hidden; vertical-align: top; }
        th { background-color: #003366; color: white; font-weight: bold; text-align: center; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 15px; }
        .stats { margin: 10px 0; }
        .stats p { margin: 3px 0; font-size: 8px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .col-rut { width: 10%; }
        .col-estudiante { width: 15%; }
        .col-email { width: 18%; font-size: 6px; }
        .col-rubrica { width: 12%; }
        .col-estado { width: 10%; }
        .col-nota { width: 8%; text-align: center; }
        .col-fecha { width: 10%; font-size: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TEC-UCT INSTITUTO TECNOLÓGICO</h1>
        <h2>Reporte de Autoevaluaciones</h2>
        <p><strong>Asignatura:</strong> <?php echo htmlspecialchars($asignatura['carrera_nombre'] . ' - ' . $asignatura['nombre']); ?></p>
        <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
    </div>
    
    <?php if ($estadisticas): ?>
    <div class="stats">
        <h3>Estadísticas Generales</h3>
        <p><strong>Total de Autoevaluaciones:</strong> <?php echo $estadisticas['total']; ?></p>
        <p><strong>Promedio:</strong> <?php echo $estadisticas['promedio'] ? number_format($estadisticas['promedio'], 2) : 'N/A'; ?></p>
        <p><strong>Nota Mínima:</strong> <?php echo $estadisticas['minima'] ? number_format($estadisticas['minima'], 1) : 'N/A'; ?></p>
        <p><strong>Nota Máxima:</strong> <?php echo $estadisticas['maxima'] ? number_format($estadisticas['maxima'], 1) : 'N/A'; ?></p>
    </div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th class="col-rut">RUT</th>
                <th class="col-estudiante">Estudiante</th>
                <th class="col-email">Email</th>
                <th class="col-rubrica">Rúbrica</th>
                <th class="col-estado">Estado</th>
                <th class="col-nota">Nota Autoevaluada</th>
                <th class="col-nota">Nota Ajustada</th>
                <th class="col-nota">Nota Final</th>
                <th class="col-fecha">Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($autoevaluaciones as $autoeval): ?>
                <tr>
                    <td class="col-rut"><?php echo htmlspecialchars($autoeval['rut'] ?? '-'); ?></td>
                    <td class="col-estudiante"><?php echo htmlspecialchars($autoeval['estudiante_nombre'] . ' ' . $autoeval['estudiante_apellido']); ?></td>
                    <td class="col-email"><?php echo htmlspecialchars($autoeval['estudiante_email']); ?></td>
                    <td class="col-rubrica"><?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></td>
                    <td class="col-estado"><?php echo ucfirst(str_replace('_', ' ', $autoeval['estado'])); ?></td>
                    <td class="col-nota text-center"><?php echo $autoeval['nota_autoevaluada'] ? number_format($autoeval['nota_autoevaluada'], 1) : '-'; ?></td>
                    <td class="col-nota text-center"><strong><?php echo $autoeval['nota_ajustada'] ? number_format($autoeval['nota_ajustada'], 1) : '-'; ?></strong></td>
                    <td class="col-nota text-center"><strong><?php echo $autoeval['nota_final'] ? number_format($autoeval['nota_final'], 1) : 'Pendiente'; ?></strong></td>
                    <td class="col-fecha"><?php echo date('d/m/Y H:i', strtotime($autoeval['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

// Generar PDF si TCPDF está disponible
if ($tcpdf_available) {
    try {
        // Limpiar cualquier output antes de generar el PDF
        ob_clean();
        
        // Crear instancia de TCPDF en formato Landscape (horizontal)
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configurar información del documento
        $pdf->SetCreator('TEC-UCT');
        $pdf->SetAuthor('TEC-UCT');
        $pdf->SetTitle('Reporte de Autoevaluaciones');
        $pdf->SetSubject('Reporte de Autoevaluaciones');
        
        // Configurar márgenes
        $pdf->SetMargins(10, 15, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(5);
        
        // Configurar auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Agregar página
        $pdf->AddPage();
        
        // Configurar fuente
        $pdf->SetFont('helvetica', '', 7);
        
        // Configurar para mejor manejo de tablas
        $pdf->setCellHeightRatio(1.5);
        
        // Escribir HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Generar nombre de archivo
        $filename = 'reporte_autoevaluaciones_' . date('Y-m-d_His') . '.pdf';
        
        // Limpiar output buffer antes de enviar PDF
        ob_end_clean();
        
        // Descargar PDF
        $pdf->Output($filename, 'D');
        exit();
    } catch (Exception $e) {
        // Limpiar output buffer en caso de error
        ob_end_clean();
        error_log("Error al generar PDF: " . $e->getMessage());
        // Si falla, mostrar HTML como fallback
        header('Content-Type: text/html; charset=utf-8');
        echo '<div style="color: red; padding: 20px;">';
        echo '<h2>Error al generar PDF</h2>';
        echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>Mostrando contenido en HTML:</p>';
        echo '</div>';
        echo $html;
    }
} else {
    // Limpiar output buffer
    ob_end_clean();
    // Si TCPDF no está disponible, mostrar HTML con mensaje
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px; border-radius: 5px;">';
    echo '<h3 style="color: #856404; margin-top: 0;">⚠️ Librería PDF no instalada</h3>';
    echo '<p>Para exportar a PDF, instale la librería TCPDF ejecutando:</p>';
    echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 3px;">composer require tecnickcom/tcpdf</pre>';
    echo '<p>Por ahora, se muestra el contenido en HTML. Puede usar la función "Imprimir" de su navegador y guardar como PDF.</p>';
    echo '</div>';
    echo $html;
}
?>

