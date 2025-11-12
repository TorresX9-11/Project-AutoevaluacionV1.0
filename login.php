<?php
require_once 'config/config.php';

$error = '';
$mensaje = '';

// Si ya está logueado, redirigir
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizar($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($password, $usuario['password'])) {
                // Validar correo institucional
                $tipoEsperado = '';
                if (strpos($email, DOMINIO_ESTUDIANTE) !== false) {
                    $tipoEsperado = 'estudiante';
                } elseif (strpos($email, DOMINIO_DOCENTE) !== false) {
                    $tipoEsperado = in_array($usuario['tipo'], ['docente', 'admin', 'admin_supremo']) ? $usuario['tipo'] : 'docente';
                }
                
                if ($tipoEsperado && $usuario['tipo'] === $tipoEsperado) {
                    // Crear sesión
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    $_SESSION['usuario_apellido'] = $usuario['apellido'];
                    $_SESSION['usuario_tipo'] = $usuario['tipo'];
                    $_SESSION['usuario_carrera_id'] = $usuario['carrera_id'];
                    $_SESSION['ultima_actividad'] = time();
                    
                    // Si es primera vez, redirigir a cambio de contraseña
                    if ($usuario['primera_vez']) {
                        header('Location: ' . BASE_URL . 'cambiar_password.php?primera_vez=1');
                        exit();
                    }
                    
                    header('Location: ' . BASE_URL . 'index.php');
                    exit();
                } else {
                    $error = 'Correo no autorizado para este tipo de usuario';
                }
            } else {
                $error = 'Credenciales incorrectas';
            }
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error al iniciar sesión. Por favor, intente nuevamente.';
        }
    }
}

if (isset($_GET['expired'])) {
    $error = 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.';
}

if (isset($_GET['recuperado'])) {
    $mensaje = 'Se ha enviado un correo con las instrucciones para recuperar su contraseña.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - TEC-UCT Autoevaluación</title>
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
                        <h4 class="mb-0">TEC-UCT Autoevaluación</h4>
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
                                <small class="text-muted">Estudiantes: @alu.uct.cl | Docentes: @uct.cl</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3 text-end">
                                <a href="<?php echo BASE_URL; ?>recuperar_password.php" class="text-decoration-none">
                                    ¿Olvidaste tu contraseña?
                                </a>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

