<?php
require_once '../config/config.php';
validarSesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/notificaciones.php';
    $usuario_id = $_SESSION['usuario_id'];
    
    if (marcarTodasLeidas($usuario_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>

