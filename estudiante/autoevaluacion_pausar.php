<?php
require_once '../config/config.php';
validarTipoUsuario(['estudiante']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $autoeval_id = $_POST['id'] ?? null;
    $accion = $_POST['accion'] ?? '';
    
    if ($autoeval_id && $accion === 'pausar') {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                UPDATE autoevaluaciones 
                SET estado = 'incompleta', tiempo_pausa = NOW() 
                WHERE id = ? AND estudiante_id = ?
            ");
            $stmt->execute([$autoeval_id, $_SESSION['usuario_id']]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error al pausar autoevaluación: " . $e->getMessage());
            echo json_encode(['success' => false]);
        }
    }
}
exit();
?>

