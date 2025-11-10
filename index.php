<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Plataforma de Evaluación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <!-- Logo en la esquina superior derecha -->
    <div class="logo-container">
        <img src="logo_uct.png" alt="Logo UCT">
    </div>

    <div class="container login-container">
        <div class="row justify-content-center w-100">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg login-card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Plataforma de Evaluación</h3>
                        <p class="text-center text-muted">Inicia sesión con tu correo institucional</p>
                        
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                        <?php endif; ?>

                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Institucional</label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="usuario@alu.uct.cl">
                            </div>
                            
                            <div class="mb-3" id="password-field">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Ingresar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script removido - campo de contraseña siempre visible
    </script>
</body>
</html>