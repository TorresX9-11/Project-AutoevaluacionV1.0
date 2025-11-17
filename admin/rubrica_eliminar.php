<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$rubrica_id = $_GET['id'] ?? null;

if (!$rubrica_id) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Verificar que la rúbrica pertenece al docente
$stmt = $pdo->prepare("
    SELECT r.* FROM rubricas r
    JOIN asignaturas a ON r.asignatura_id = a.id
    WHERE r.id = ? AND a.docente_id = ?
");
$stmt->execute([$rubrica_id, $docente_id]);
$rubrica = $stmt->fetch();

if (!$rubrica) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Eliminar rúbrica (las relaciones se eliminan en cascada)
try {
    $stmt = $pdo->prepare("DELETE FROM rubricas WHERE id = ?");
    $stmt->execute([$rubrica_id]);
    
    header('Location: ' . BASE_URL . 'admin/rubricas.php?asignatura_id=' . $rubrica['asignatura_id'] . '&mensaje=eliminada');
    exit();
} catch (PDOException $e) {
    error_log("Error al eliminar rúbrica: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'admin/rubricas.php?asignatura_id=' . $rubrica['asignatura_id'] . '&error=eliminar');
    exit();
}
?>

