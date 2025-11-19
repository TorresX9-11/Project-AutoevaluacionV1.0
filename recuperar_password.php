<?php
require_once 'config/config.php';

$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizar($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor, ingrese su correo electrónico';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // Generar token de recuperación
                $token = generarToken();
                $expiracion = date('Y-m-d H:i:s', time() + TOKEN_LIFETIME);
                
                $stmt = $pdo->prepare("UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id = ?");
                $stmt->execute([$token, $expiracion, $usuario['id']]);
                
                // Enviar correo
                require_once 'config/email.php';
                if (enviarCorreoRecuperacion($email, $usuario['nombre'] . ' ' . $usuario['apellido'], $token)) {
                    $mensaje = 'Se ha enviado un correo con las instrucciones para recuperar su contraseña.';
                } else {
                    $error = 'Error al enviar el correo. Por favor, intente nuevamente más tarde.';
                }
            } else {
                // Por seguridad, mostrar el mismo mensaje aunque el correo no exista
                $mensaje = 'Si el correo existe en nuestro sistema, se enviarán las instrucciones.';
            }
        } catch (PDOException $e) {
            error_log("Error en recuperar_password: " . $e->getMessage());
            $error = 'Error al procesar la solicitud. Por favor, intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - TEC-UCT Autoevaluación</title>
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
                        <h4 class="mb-0">Recuperar Contraseña</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($mensaje): ?>
                            <div class="alert alert-success"><?php echo $mensaje; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Institucional</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="usuario@alu.uct.cl o usuario@uct.cl" required autofocus>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-envelope"></i> Enviar Instrucciones
                            </button>
                            
                            <div class="text-center">
                                <a href="<?php echo BASE_URL; ?>login.php" class="text-decoration-none">
                                    Volver al inicio de sesión
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

