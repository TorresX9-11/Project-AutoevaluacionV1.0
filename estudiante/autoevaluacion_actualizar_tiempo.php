<?php
require_once '../config/config.php';
validarTipoUsuario(['estudiante']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $autoeval_id = $_POST['id'] ?? null;
    $tiempo = intval($_POST['tiempo'] ?? AUTOEVAL_TIME_LIMIT);
    
    if ($autoeval_id) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                UPDATE autoevaluaciones 
                SET tiempo_restante = ? 
                WHERE id = ? AND estudiante_id = ? AND estado IN ('en_proceso', 'pausada')
            ");
            $stmt->execute([$tiempo, $autoeval_id, $_SESSION['usuario_id']]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error al actualizar tiempo: " . $e->getMessage());
            echo json_encode(['success' => false]);
        }
    }
}
exit();
?>

