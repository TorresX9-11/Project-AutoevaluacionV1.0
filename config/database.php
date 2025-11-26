<?php
// Configuración de base de datos
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'autoeval_tecuct');
define('DB_CHARSET', 'utf8mb4');
*/

// Conexión remota (completa con tus credenciales reales)
define('DB_HOST', 'teclab.uct.cl');
define('DB_USER', 'emanuel_torres');
define('DB_PASS', 'Agil2025CIet');
define('DB_NAME', 'emanuel_torres_db3');
define('DB_CHARSET', 'utf8mb4');

// Conexión a la base de datos
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
    }
}
?>

