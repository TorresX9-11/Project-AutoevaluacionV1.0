<?php
/**
 * Script de configuración inicial para crear/actualizar usuario administrador
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

echo "=== Configuración de Usuario Administrador ===\n\n";

try {
    $pdo = getDBConnection();
    
    // Generar hash de contraseña para "admin123"
    $password = 'admin123';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "Contraseña: admin123\n";
    echo "Hash generado: " . $password_hash . "\n\n";
    
    // Verificar si el usuario admin existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = 'admin@uct.cl'");
    $stmt->execute();
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        // Actualizar contraseña del usuario existente
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, primera_vez = 0 WHERE email = 'admin@uct.cl'");
        $stmt->execute([$password_hash]);
        echo "✓ Usuario administrador actualizado correctamente.\n";
        echo "  Email: admin@uct.cl\n";
        echo "  Contraseña: admin123\n\n";
    } else {
        // Crear nuevo usuario admin
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (email, password, nombre, apellido, tipo, primera_vez) 
            VALUES ('admin@uct.cl', ?, 'Admin', 'Sistema', 'admin', 0)
        ");
        $stmt->execute([$password_hash]);
        echo "✓ Usuario administrador creado correctamente.\n";
        echo "  Email: admin@uct.cl\n";
        echo "  Contraseña: admin123\n\n";
    }
    
    // Verificar que funciona
    echo "Verificando credenciales...\n";
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = 'admin@uct.cl'");
    $stmt->execute();
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        echo "✓ Verificación exitosa. Las credenciales funcionan correctamente.\n\n";
    } else {
        echo "✗ Error: La verificación falló. Por favor, revise la configuración.\n\n";
    }
    
    echo "=== Configuración completada ===\n";
    echo "Ahora puede iniciar sesión con:\n";
    echo "  Email: admin@uct.cl\n";
    echo "  Contraseña: admin123\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Asegúrese de que la base de datos esté creada y configurada correctamente.\n";
}
?>

