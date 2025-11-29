<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$asignatura_id = $_GET['asignatura_id'] ?? null;

if (!$asignatura_id) {
    header('Location: ' . BASE_URL . 'admin/reportes.php');
    exit();
}

// Verificar que la asignatura pertenece al docente
$stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ? AND docente_id = ?");
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

// Configurar headers para descarga CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_autoevaluaciones_' . date('Y-m-d') . '.csv"');

// Crear output
$output = fopen('php://output', 'w');

// BOM para UTF-8 (Excel)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
fputcsv($output, [
    'RUT',
    'Nombre',
    'Apellido',
    'Email',
    'Rúbrica',
    'Estado',
    'Nota Autoevaluada',
    'Nota Ajustada',
    'Nota Final',
    'Fecha Inicio',
    'Fecha Fin',
    'Comentario Estudiante',
    'Comentario Docente'
]);

// Datos
foreach ($autoevaluaciones as $autoeval) {
    fputcsv($output, [
        $autoeval['rut'] ?? '',
        $autoeval['estudiante_nombre'],
        $autoeval['estudiante_apellido'],
        $autoeval['estudiante_email'],
        $autoeval['rubrica_nombre'],
        ucfirst(str_replace('_', ' ', $autoeval['estado'])),
        $autoeval['nota_autoevaluada'] ? number_format($autoeval['nota_autoevaluada'], 2) : '',
        $autoeval['nota_ajustada'] ? number_format($autoeval['nota_ajustada'], 2) : '',
        $autoeval['nota_final'] ? number_format($autoeval['nota_final'], 2) : '',
        $autoeval['tiempo_inicio'] ? date('d/m/Y H:i', strtotime($autoeval['tiempo_inicio'])) : '',
        $autoeval['tiempo_fin'] ? date('d/m/Y H:i', strtotime($autoeval['tiempo_fin'])) : '',
        $autoeval['comentario_estudiante'] ?? '',
        $autoeval['comentario_docente'] ?? ''
    ]);
}

fclose($output);
exit();
?>

