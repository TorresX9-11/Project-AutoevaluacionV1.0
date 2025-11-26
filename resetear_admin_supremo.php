<?php
/**
 * Script para resetear la contraseña del admin_supremo
 * Ejecutar desde el navegador: https://teclab.uct.cl/~emanuel.torres/Project-AutoevaluacionV1.0/resetear_admin_supremo.php
 */

require_once 'config/config.php';

// // Solo permitir ejecución desde localhost
// if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
//     die('Este script solo puede ejecutarse desde localhost.');
// }

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo = getDBConnection();
        
        // Generar nuevo hash para "admin123"
        $password = 'admin123';
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Verificar si el usuario existe
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute(['admin_supremo@uct.cl']);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            // Actualizar contraseña y activar usuario
            $stmt = $pdo->prepare("
                UPDATE usuarios 
                SET password = ?, 
                    activo = 1, 
                    primera_vez = 0,
                    tipo = 'admin_supremo'
                WHERE email = 'admin_supremo@uct.cl'
            ");
            $stmt->execute([$password_hash]);
            
            // Verificar que funciona
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute(['admin_supremo@uct.cl']);
            $usuario_actualizado = $stmt->fetch();
            
            if ($usuario_actualizado && password_verify($password, $usuario_actualizado['password'])) {
                $mensaje = "✅ Contraseña reseteada exitosamente. El usuario está activo y listo para usar.";
            } else {
                $error = "❌ Error: La verificación falló después del reset.";
            }
        } else {
            // Crear el usuario si no existe
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (email, password, nombre, apellido, tipo, activo, primera_vez) 
                VALUES ('admin_supremo@uct.cl', ?, 'Admin', 'Supremo', 'admin_supremo', 1, 0)
            ");
            $stmt->execute([$password_hash]);
            $mensaje = "✅ Usuario admin_supremo creado exitosamente.";
        }
        
    } catch (PDOException $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resetear Admin Supremo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">🔄 Resetear Contraseña Admin Supremo</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($mensaje): ?>
                            <div class="alert alert-success">
                                <?php echo $mensaje; ?>
                                <hr>
                                <strong>Credenciales:</strong><br>
                                Email: <code>admin_supremo@uct.cl</code><br>
                                Contraseña: <code>admin123</code>
                                <hr>
                                <a href="login.php" class="btn btn-primary">Ir al Login</a>
                            </div>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong>⚠️ Advertencia:</strong> Este script reseteará la contraseña del usuario admin_supremo a "admin123" y lo activará.
                            </div>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Email:</label>
                                    <input type="text" class="form-control" value="admin_supremo@uct.cl" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nueva Contraseña:</label>
                                    <input type="text" class="form-control" value="admin123" readonly>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="confirmar" name="confirmar" required>
                                    <label class="form-check-label" for="confirmar">
                                        Confirmo que quiero resetear la contraseña
                                    </label>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-warning" name="confirmar" value="1">
                                        🔄 Resetear Contraseña
                                    </button>
                                    <a href="diagnostico_login.php" class="btn btn-secondary">
                                        ← Volver al Diagnóstico
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

