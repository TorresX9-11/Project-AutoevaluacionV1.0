<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$asignatura_id = $_GET['asignatura_id'] ?? null;

if (!$asignatura_id) {
    header('Location: ' . BASE_URL . 'admin/estudiantes.php');
    exit();
}

// Verificar asignatura
$stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ? AND docente_id = ?");
$stmt->execute([$asignatura_id, $docente_id]);
$asignatura = $stmt->fetch();

if (!$asignatura) {
    header('Location: ' . BASE_URL . 'admin/estudiantes.php');
    exit();
}

// Obtener estudiantes
$stmt = $pdo->prepare("
    SELECT u.*, ea.activo as asignatura_activo
    FROM usuarios u
    JOIN estudiantes_asignaturas ea ON u.id = ea.estudiante_id
    WHERE ea.asignatura_id = ? AND u.tipo = 'estudiante'
    ORDER BY u.apellido, u.nombre
");
$stmt->execute([$asignatura_id]);
$estudiantes = $stmt->fetchAll();

// Configurar headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="estudiantes_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
fputcsv($output, ['Email', 'Nombre', 'Apellido', 'RUT', 'Estado']);

// Datos
foreach ($estudiantes as $estudiante) {
    fputcsv($output, [
        $estudiante['email'],
        $estudiante['nombre'],
        $estudiante['apellido'],
        $estudiante['rut'] ?? '',
        ($estudiante['activo'] && $estudiante['asignatura_activo']) ? 'Activo' : 'Inactivo'
    ]);
}

fclose($output);
exit();
?>

