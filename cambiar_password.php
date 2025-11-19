<?php
require_once 'config/config.php';

$error = '';
$mensaje = '';
$token = $_GET['token'] ?? '';
$primera_vez = isset($_GET['primera_vez']);

// Si hay sesión activa y no es primera vez, usar sesión
if (isset($_SESSION['usuario_id']) && !$primera_vez && empty($token)) {
    $usuario_id = $_SESSION['usuario_id'];
    $usar_token = false;
} elseif (!empty($token)) {
    // Validar token
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE token_recuperacion = ? AND token_expiracion > NOW() AND activo = 1");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            $usuario_id = $usuario['id'];
            $usar_token = true;
        } else {
            $error = 'Token inválido o expirado. Por favor, solicite un nuevo enlace de recuperación.';
        }
    } catch (PDOException $e) {
        error_log("Error en cambiar_password: " . $e->getMessage());
        $error = 'Error al validar el token.';
    }
} elseif ($primera_vez && isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $usar_token = false;
} else {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($usuario_id)) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password) || empty($password_confirm)) {
        $error = 'Por favor, complete todos los campos';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $pdo = getDBConnection();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            if ($usar_token) {
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, token_recuperacion = NULL, token_expiracion = NULL, primera_vez = 0 WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, primera_vez = 0 WHERE id = ?");
            }
            
            $stmt->execute([$password_hash, $usuario_id]);
            
            $mensaje = 'Contraseña actualizada correctamente. Será redirigido al inicio de sesión.';
            header('refresh:3;url=' . BASE_URL . 'login.php');
        } catch (PDOException $e) {
            error_log("Error al cambiar contraseña: " . $e->getMessage());
            $error = 'Error al actualizar la contraseña. Por favor, intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - TEC-UCT Autoevaluación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow">
                    <div class="card-header text-center">
                        <img src="<?php echo BASE_URL; ?>assets/img/logo-uct.png" alt="TEC-UCT" height="60" class="mb-3">
                        <h4 class="mb-0"><?php echo $primera_vez ? 'Cambiar Contraseña' : 'Restablecer Contraseña'; ?></h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($mensaje): ?>
                            <div class="alert alert-success"><?php echo $mensaje; ?></div>
                        <?php endif; ?>
                        
                        <?php if (empty($error) || isset($usuario_id)): ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="8" required autofocus>
                                    <small class="text-muted">Mínimo 8 caracteres</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                           minlength="8" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="bi bi-key"></i> Cambiar Contraseña
                                </button>
                                
                                <div class="text-center">
                                    <a href="<?php echo BASE_URL; ?>login.php" class="text-decoration-none">
                                        Volver al inicio de sesión
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

