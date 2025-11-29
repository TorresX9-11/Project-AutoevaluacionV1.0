<?php
/**
 * Script de configuración inicial para crear/actualizar usuario admin_supremo
 * Ejecutar una sola vez después de crear la base de datos
 */

require_once '../config/config.php';

// Solo permitir ejecución desde línea de comandos o localhost
$allowed = false;
if (php_sapi_name() === 'cli') {
    $allowed = true;
} elseif ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    $allowed = true;
}

if (!$allowed) {
    die('Este script solo puede ejecutarse desde localhost o línea de comandos.');
}

echo "=== Configuración de Usuario Admin Supremo ===\n\n";

try {
    $pdo = getDBConnection();
    
    // Verificar que el tipo admin_supremo existe en la base de datos
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios WHERE Field = 'tipo'");
    $column = $stmt->fetch();
    
    if ($column && strpos($column['Type'], 'admin_supremo') === false) {
        echo "⚠ ADVERTENCIA: El tipo 'admin_supremo' no existe en la base de datos.\n";
        echo "Por favor, ejecute primero el script de migración:\n";
        echo "  database/migrate_admin_supremo.sql\n\n";
        echo "O ejecute este comando SQL en phpMyAdmin:\n";
        echo "  ALTER TABLE usuarios MODIFY COLUMN tipo ENUM('estudiante', 'docente', 'admin', 'admin_supremo') NOT NULL;\n\n";
        exit(1);
    }
    
    // Generar hash de contraseña para "admin123"
    $password = 'admin123';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "Contraseña: admin123\n";
    echo "Hash generado: " . $password_hash . "\n\n";
    
    // Verificar si el usuario admin_supremo existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = 'admin_supremo@uct.cl'");
    $stmt->execute();
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        // Actualizar contraseña del usuario existente
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, primera_vez = 0, tipo = 'admin_supremo' WHERE email = 'admin_supremo@uct.cl'");
        $stmt->execute([$password_hash]);
        echo "✓ Usuario admin_supremo actualizado correctamente.\n";
        echo "  Email: admin_supremo@uct.cl\n";
        echo "  Contraseña: admin123\n";
        echo "  Tipo: admin_supremo\n\n";
    } else {
        // Crear nuevo usuario admin_supremo
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (email, password, nombre, apellido, tipo, primera_vez) 
            VALUES ('admin_supremo@uct.cl', ?, 'Admin', 'Supremo', 'admin_supremo', 0)
        ");
        $stmt->execute([$password_hash]);
        echo "✓ Usuario admin_supremo creado correctamente.\n";
        echo "  Email: admin_supremo@uct.cl\n";
        echo "  Contraseña: admin123\n";
        echo "  Tipo: admin_supremo\n\n";
    }
    
    // Verificar que funciona
    echo "Verificando credenciales...\n";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = 'admin_supremo@uct.cl'");
    $stmt->execute();
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        echo "✓ Verificación exitosa. Las credenciales funcionan correctamente.\n\n";
    } else {
        echo "✗ Error: La verificación falló. Por favor, revise la configuración.\n\n";
    }
    
    echo "=== Configuración completada ===\n";
    echo "Ahora puede iniciar sesión con:\n";
    echo "  Email: admin_supremo@uct.cl\n";
    echo "  Contraseña: admin123\n";
    echo "\nIMPORTANTE: Cambiar la contraseña después del primer acceso por seguridad.\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Asegúrese de que la base de datos esté creada y configurada correctamente.\n";
}
?>

