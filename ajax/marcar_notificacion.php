<?php
require_once '../config/config.php';
validarSesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    require_once '../includes/notificaciones.php';
    $notificacion_id = intval($_POST['id']);
    $usuario_id = $_SESSION['usuario_id'];
    
    if (marcarNotificacionLeida($notificacion_id, $usuario_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>

