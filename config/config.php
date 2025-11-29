<?php
// Configuración general de la aplicación
session_start();

// Configuración de errores
// Cambiar 'desarrollo' a 'produccion' cuando se despliegue en producción
$entorno = 'produccion'; // Opciones: 'desarrollo' | 'produccion'

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
define('BASE_URL', 'https://teclab.uct.cl/~emanuel.torres/Project-AutoevaluacionV1.0/');
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

// Función para validar RUT chileno (formato con o sin puntos y guion)
function validarRUT($rut) {
    if (empty($rut)) return false;
    // Normalizar: eliminar puntos, espacios y convertir a mayúsculas
    $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));

    if (strlen($rut) < 2) return false;

    $dv = substr($rut, -1);
    $numero = substr($rut, 0, -1);

    if (!ctype_digit($numero)) return false;

    $suma = 0;
    $multiplo = 2;
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += intval($numero[$i]) * $multiplo;
        $multiplo = ($multiplo == 7) ? 2 : $multiplo + 1;
    }

    $resto = 11 - ($suma % 11);
    if ($resto == 11) {
        $dvCalc = '0';
    } elseif ($resto == 10) {
        $dvCalc = 'K';
    } else {
        $dvCalc = (string)$resto;
    }

    return $dvCalc === strtoupper($dv);
}

/**
 * Convierte un puntaje a nota usando la escala de notas personalizada
 * @param float $puntaje_obtenido El puntaje obtenido por el estudiante
 * @param array $escala_notas Array con los parámetros de la escala: puntaje_maximo, exigencia, nota_minima, nota_maxima, nota_aprobacion
 * @return float La nota calculada (con 1 decimal)
 */
function convertirPuntajeANota($puntaje_obtenido, $escala_notas) {
    if (empty($escala_notas) || !isset($escala_notas['puntaje_maximo'])) {
        // Si no hay escala configurada, retornar el puntaje como está
        return round($puntaje_obtenido, 1);
    }
    
    $puntaje_maximo = floatval($escala_notas['puntaje_maximo'] ?? 0);
    $exigencia = floatval($escala_notas['exigencia'] ?? 60);
    $nota_minima = floatval($escala_notas['nota_minima'] ?? 1);
    $nota_maxima = floatval($escala_notas['nota_maxima'] ?? 7);
    $nota_aprobacion = floatval($escala_notas['nota_aprobacion'] ?? 4);
    
    if ($puntaje_maximo <= 0) {
        return round($puntaje_obtenido, 1);
    }
    
    // Asegurar que el puntaje no exceda el máximo
    $puntaje_obtenido = min($puntaje_obtenido, $puntaje_maximo);
    $puntaje_obtenido = max(0, $puntaje_obtenido); // No puede ser negativo
    
    // Calcular puntaje para nota de aprobación
    $punto_aprobacion = $puntaje_maximo * $exigencia / 100;
    
    // Calcular nota usando interpolación lineal
    if ($puntaje_obtenido <= $punto_aprobacion) {
        // Interpolación lineal entre nota mínima y nota de aprobación
        if ($punto_aprobacion > 0) {
            $ratio = $puntaje_obtenido / $punto_aprobacion;
        } else {
            $ratio = 0;
        }
        $nota = $nota_minima + ($nota_aprobacion - $nota_minima) * $ratio;
    } else {
        // Interpolación lineal entre nota de aprobación y nota máxima
        $rango_superior = $puntaje_maximo - $punto_aprobacion;
        if ($rango_superior > 0) {
            $ratio = ($puntaje_obtenido - $punto_aprobacion) / $rango_superior;
        } else {
            $ratio = 1;
        }
        $nota = $nota_aprobacion + ($nota_maxima - $nota_aprobacion) * $ratio;
    }
    
    // Redondear a 1 decimal
    return round($nota, 1);
}
?>

