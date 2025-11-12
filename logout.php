<?php
require_once 'config/config.php';

// Destruir sesión
session_destroy();

// Redirigir al login
header('Location: ' . BASE_URL . 'login.php');
exit();
?>

