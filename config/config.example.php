<?php
// Archivo de ejemplo de configuración
// Copiar este archivo a config.php y ajustar los valores según su entorno

// Configuración general de la aplicación
session_start();

// Configuración de errores
// En desarrollo: mostrar errores
// En producción: ocultar errores
$entorno = 'desarrollo'; // Cambiar a 'produccion' en producción

if ($entorno === 'desarrollo') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Zona horaria
date_default_timezone_set('America/Santiago');

// Rutas base
// IMPORTANTE: Cambiar según su entorno
define('BASE_URL', 'http://localhost/project-autoevaluacionv1.0/'); // Cambiar en producción
define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Configuración de correo
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu_correo@gmail.com'); // Configurar con correo real
define('SMTP_PASS', 'tu_contraseña'); // Configurar con contraseña real
define('SMTP_FROM_EMAIL', 'noreply@tec.uct.cl');
define('SMTP_FROM_NAME', 'TEC-UCT Autoevaluación');

// Configuración de seguridad
define('SESSION_LIFETIME', 3600); // 1 hora
define('TOKEN_LIFETIME', 3600); // 1 hora para tokens de recuperación
define('AUTOEVAL_TIME_LIMIT', 300); // 5 minutos en segundos

// Dominios permitidos para correos
define('DOMINIO_ESTUDIANTE', '@alu.uct.cl');
define('DOMINIO_DOCENTE', '@uct.cl');

// Incluir conexión a base de datos
require_once BASE_PATH . 'config/database.php';

// Función para validar sesión
function validarSesion() {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
    
    // Verificar si la sesión expiró
    if (isset($_SESSION['ultima_actividad']) && 
        (time() - $_SESSION['ultima_actividad'] > SESSION_LIFETIME)) {
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?expired=1');
        exit();
    }
    
    $_SESSION['ultima_actividad'] = time();
}

// Función para validar tipo de usuario
function validarTipoUsuario($tiposPermitidos) {
    validarSesion();
    if (!in_array($_SESSION['usuario_tipo'], $tiposPermitidos)) {
        header('Location: ' . BASE_URL . 'index.php?error=acceso_denegado');
        exit();
    }
}

// Función para sanitizar entrada
function sanitizar($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Función para generar token seguro
function generarToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Función para validar correo institucional
function validarCorreoInstitucional($email, $tipo) {
    if ($tipo === 'estudiante') {
        return strpos($email, DOMINIO_ESTUDIANTE) !== false;
    } elseif (in_array($tipo, ['docente', 'admin', 'admin_supremo'])) {
        return strpos($email, DOMINIO_DOCENTE) !== false;
    }
    return false;
}
?>

