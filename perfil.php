<?php
require_once 'config/config.php';
validarSesion();

$titulo = 'Mi Perfil';
include 'includes/header.php';

$pdo = getDBConnection();
$usuario_id = $_SESSION['usuario_id'];

$error = '';
$mensaje = '';

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

// Actualizar perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    $rut = sanitizar($_POST['rut'] ?? '');
    
    if (empty($nombre) || empty($apellido) || empty($rut)) {
        $error = 'El nombre, apellido y RUT son requeridos';
    } elseif (!validarRUT($rut)) {
        $error = 'El RUT ingresado no es válido. Debe tener el formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, rut = ? WHERE id = ?");
            $stmt->execute([$nombre, $apellido, $rut, $usuario_id]);
            
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['usuario_apellido'] = $apellido;
            
            $mensaje = 'Perfil actualizado exitosamente';
            $usuario['nombre'] = $nombre;
            $usuario['apellido'] = $apellido;
            $usuario['rut'] = $rut;
        } catch (PDOException $e) {
            error_log("Error al actualizar perfil: " . $e->getMessage());
            $error = 'Error al actualizar el perfil';
        }
    }
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_password') {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirm)) {
        $error = 'Complete todos los campos de contraseña';
    } elseif ($password_nueva !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password_nueva) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif (!password_verify($password_actual, $usuario['password'])) {
        $error = 'La contraseña actual es incorrecta';
    } else {
        try {
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $usuario_id]);
            $mensaje = 'Contraseña actualizada exitosamente';
        } catch (PDOException $e) {
            error_log("Error al cambiar contraseña: " . $e->getMessage());
            $error = 'Error al cambiar la contraseña';
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Mi Perfil</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información Personal</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="actualizar">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" 
                               value="<?php echo htmlspecialchars($usuario['email']); ?>" readonly>
                        <small class="text-muted">El email no se puede cambiar</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" 
                               value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rut" name="rut" 
                               value="<?php echo htmlspecialchars($usuario['rut'] ?? ''); ?>"
                               placeholder="12345678-9" 
                               required
                               onblur="formatearRUT(this)"
                               oninput="this.setCustomValidity('')">
                        <small class="text-muted">Formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Usuario</label>
                        <input type="text" class="form-control" 
                               value="<?php echo ucfirst($usuario['tipo']); ?>" readonly>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Cambiar Contraseña</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="cambiar_password">
                    
                    <div class="mb-3">
                        <label for="password_actual" class="form-label">Contraseña Actual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_nueva" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_nueva" name="password_nueva" 
                               minlength="8" required>
                        <small class="text-muted">Mínimo 8 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                               minlength="8" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key"></i> Cambiar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Función para formatear RUT chileno
function formatearRUT(input) {
    if (!input || !input.value) return;
    
    let rut = input.value.toUpperCase().trim();
    // Eliminar puntos y espacios
    rut = rut.replace(/[\.\s]/g, '');
    
    // Validar formato básico antes de formatear
    if (!rut.match(/^\d{7,8}-?[0-9K]$/)) {
        // Si no tiene el formato correcto, intentar validar
        input.setCustomValidity('El RUT debe tener el formato: 12345678-9 o 12345678-K');
        return;
    }
    
    // Si tiene guion, separar número y dígito verificador
    if (rut.includes('-')) {
        const partes = rut.split('-');
        if (partes.length === 2) {
            let numero = partes[0].replace(/\D/g, '');
            const digito = partes[1].replace(/[^0-9K]/g, '');
            
            // Validar que tenga 7-8 dígitos
            if (numero.length < 7 || numero.length > 8) {
                input.setCustomValidity('El RUT debe tener 7 u 8 dígitos antes del guion');
                return;
            }
            
            // Agregar puntos cada 3 dígitos desde la derecha
            if (numero.length > 0) {
                let numeroFormateado = '';
                for (let i = 0; i < numero.length; i++) {
                    if (i > 0 && (numero.length - i) % 3 === 0) {
                        numeroFormateado += '.';
                    }
                    numeroFormateado += numero[i];
                }
                input.value = numeroFormateado + '-' + digito;
                input.setCustomValidity('');
            }
        }
    } else {
        // Si no tiene guion, intentar detectar el último carácter como dígito verificador
        const match = rut.match(/^(\d{7,8})([0-9K])$/);
        if (match) {
            let numero = match[1];
            const digito = match[2];
            
            let numeroFormateado = '';
            for (let i = 0; i < numero.length; i++) {
                if (i > 0 && (numero.length - i) % 3 === 0) {
                    numeroFormateado += '.';
                }
                numeroFormateado += numero[i];
            }
            input.value = numeroFormateado + '-' + digito;
            input.setCustomValidity('');
        } else {
            input.setCustomValidity('El RUT debe tener el formato: 12345678-9 o 12345678-K');
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>

