<?php
require_once '../config/config.php';
validarTipoUsuario(['admin_supremo']);

$titulo = 'Gestión de Docentes';
include '../includes/header.php';

$pdo = getDBConnection();

$error = '';
$mensaje = '';

// Crear nuevo docente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $email = sanitizar($_POST['email'] ?? '');
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    $rut = sanitizar($_POST['rut'] ?? '');
    $carreras_ids = $_POST['carreras_ids'] ?? [];
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($nombre) || empty($apellido) || empty($rut) || empty($carreras_ids)) {
        $error = 'Por favor, complete todos los campos requeridos y seleccione al menos una carrera';
    } elseif (!validarCorreoInstitucional($email, 'docente')) {
        $error = 'El correo debe ser del dominio @uct.cl';
    } elseif (!validarRUT($rut)) {
        $error = 'El RUT ingresado no es válido. Debe tener el formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)';
    } elseif (empty($password) || strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } else {
        try {
            $pdo->beginTransaction();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            // Crear docente sin carrera_id (usaremos la tabla de relación)
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (email, password, nombre, apellido, rut, tipo, primera_vez) 
                VALUES (?, ?, ?, ?, ?, 'docente', 1)
            ");
            $stmt->execute([$email, $password_hash, $nombre, $apellido, $rut]);
            $docente_id = $pdo->lastInsertId();
            
            // Asociar docente a carreras
            $stmt = $pdo->prepare("
                INSERT INTO docentes_carreras (docente_id, carrera_id, activo) 
                VALUES (?, ?, 1)
            ");
            foreach ($carreras_ids as $carrera_id) {
                $stmt->execute([$docente_id, $carrera_id]);
            }
            
            $pdo->commit();
            $mensaje = 'Docente creado exitosamente. Deberá cambiar su contraseña al iniciar sesión por primera vez.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al crear docente: " . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'El correo electrónico ya está registrado';
            } else {
                $error = 'Error al crear el docente';
            }
        }
    }
}

// Actualizar docente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $id = $_POST['id'] ?? null;
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    $rut = sanitizar($_POST['rut'] ?? '');
    $carreras_ids = $_POST['carreras_ids'] ?? [];
    $activo = isset($_POST['activo']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    
    if (empty($id) || empty($nombre) || empty($apellido) || empty($rut) || empty($carreras_ids)) {
        $error = 'Por favor, complete todos los campos requeridos y seleccione al menos una carrera';
    } elseif (!validarRUT($rut)) {
        $error = 'El RUT ingresado no es válido. Debe tener el formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)';
    } else {
        try {
            $pdo->beginTransaction();
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $error = 'La contraseña debe tener al menos 8 caracteres';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, apellido = ?, rut = ?, activo = ?, password = ?, primera_vez = 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $apellido, $rut, $activo, $password_hash, $id]);
                    $mensaje = 'Docente actualizado exitosamente. Deberá cambiar su contraseña al iniciar sesión.';
                }
            } else {
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nombre = ?, apellido = ?, rut = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $apellido, $rut, $activo, $id]);
                $mensaje = 'Docente actualizado exitosamente';
            }
            
            // Actualizar relaciones con carreras
            // Eliminar relaciones existentes
            $stmt = $pdo->prepare("DELETE FROM docentes_carreras WHERE docente_id = ?");
            $stmt->execute([$id]);
            
            // Crear nuevas relaciones
            $stmt = $pdo->prepare("
                INSERT INTO docentes_carreras (docente_id, carrera_id, activo) 
                VALUES (?, ?, 1)
            ");
            foreach ($carreras_ids as $carrera_id) {
                $stmt->execute([$id, $carrera_id]);
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al actualizar docente: " . $e->getMessage());
            $error = 'Error al actualizar el docente';
        }
    }
}

// Eliminar docente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = $_POST['id'] ?? null;
    
    if ($id) {
        try {
            // Verificar si hay asignaturas asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM asignaturas WHERE docente_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                $error = 'No se puede eliminar el docente porque tiene asignaturas asociadas';
            } else {
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND tipo = 'docente'");
                $stmt->execute([$id]);
                $mensaje = 'Docente eliminado exitosamente';
            }
        } catch (PDOException $e) {
            error_log("Error al eliminar docente: " . $e->getMessage());
            $error = 'Error al eliminar el docente';
        }
    }
}

// Obtener docente para editar
$docente_editar = null;
$docente_carreras = [];
if (isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND tipo = 'docente'");
    $stmt->execute([$id]);
    $docente_editar = $stmt->fetch();
    
    // Obtener carreras asociadas al docente
    if ($docente_editar) {
        $stmt = $pdo->prepare("SELECT carrera_id FROM docentes_carreras WHERE docente_id = ? AND activo = 1");
        $stmt->execute([$id]);
        $docente_carreras = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Obtener carreras
$stmt = $pdo->query("SELECT * FROM carreras WHERE activa = 1 ORDER BY nombre");
$carreras = $stmt->fetchAll();

// Filtros y búsqueda
$carrera_filtro = $_GET['carrera_filtro'] ?? null;
$busqueda = sanitizar($_GET['busqueda'] ?? '');

// Obtener todos los docentes con información relacionada
$sql = "
    SELECT u.*, 
           GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') as carreras_nombres,
           COUNT(DISTINCT a.id) as total_asignaturas
    FROM usuarios u
    LEFT JOIN docentes_carreras dc ON u.id = dc.docente_id AND dc.activo = 1
    LEFT JOIN carreras c ON dc.carrera_id = c.id
    LEFT JOIN asignaturas a ON u.id = a.docente_id
    WHERE u.tipo = 'docente'
";
$params = [];

if ($carrera_filtro) {
    $sql .= " AND dc.carrera_id = ?";
    $params[] = $carrera_filtro;
}

if ($busqueda) {
    $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR u.rut LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

$sql .= " GROUP BY u.id ORDER BY u.apellido, u.nombre";

if (!empty($params)) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} else {
    $stmt = $pdo->query($sql);
}
$docentes = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Gestión de Docentes</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $docente_editar ? 'Editar Docente' : 'Nuevo Docente'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="<?php echo $docente_editar ? 'actualizar' : 'crear'; ?>">
                    <?php if ($docente_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $docente_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <?php if (!$docente_editar): ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($docente_editar['email'] ?? ''); ?>" 
                                   placeholder="usuario@uct.cl" required>
                            <small class="text-muted">Debe ser del dominio @uct.cl</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña Temporal <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="8" required>
                            <small class="text-muted">Mínimo 8 caracteres. El docente deberá cambiarla al iniciar sesión.</small>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($docente_editar['email']); ?>" readonly>
                            <small class="text-muted">El email no se puede cambiar</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva Contraseña (opcional)</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="8">
                            <small class="text-muted">Dejar vacío para mantener la contraseña actual. Si se cambia, el docente deberá cambiarla al iniciar sesión.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($docente_editar['nombre'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apellido" name="apellido" 
                               value="<?php echo htmlspecialchars($docente_editar['apellido'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rut" name="rut" 
                               value="<?php echo htmlspecialchars($docente_editar['rut'] ?? ''); ?>"
                               placeholder="12345678-9" 
                               required
                               onblur="formatearRUT(this)"
                               oninput="this.setCustomValidity('')">
                        <small class="text-muted">Formato: 12345678-9 o 12345678-K (7-8 dígitos, guion y dígito verificador)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Carreras <span class="text-danger">*</span></label>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <?php if (empty($carreras)): ?>
                                <p class="text-muted mb-0">No hay carreras disponibles. Cree carreras primero.</p>
                            <?php else: ?>
                                <?php foreach ($carreras as $carrera): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="carreras_ids[]" 
                                               id="carrera_<?php echo $carrera['id']; ?>" 
                                               value="<?php echo $carrera['id']; ?>"
                                               <?php echo ($docente_editar && in_array($carrera['id'], $docente_carreras)) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="carrera_<?php echo $carrera['id']; ?>">
                                            <?php echo htmlspecialchars($carrera['nombre']); ?>
                                            <?php if ($carrera['codigo']): ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($carrera['codigo']); ?>)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">Seleccione una o más carreras. El docente puede estar asociado a múltiples carreras.</small>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="seleccionarTodasCarreras()">
                                <i class="bi bi-check-all"></i> Seleccionar todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deseleccionarTodasCarreras()">
                                <i class="bi bi-x-circle"></i> Deseleccionar todas
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($docente_editar): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                                       <?php echo $docente_editar['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activo">
                                    Docente Activo
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $docente_editar ? 'check' : 'plus'; ?>-circle"></i> 
                        <?php echo $docente_editar ? 'Actualizar' : 'Crear'; ?> Docente
                    </button>
                    <?php if ($docente_editar): ?>
                        <a href="<?php echo BASE_URL; ?>admin_supremo/docentes.php" class="btn btn-secondary">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Docentes (<?php echo count($docentes); ?>)</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="carrera_filtro" class="form-label">Filtrar por Carrera</label>
                            <select class="form-select" id="carrera_filtro" name="carrera_filtro" onchange="this.form.submit()">
                                <option value="">Todas las carreras</option>
                                <?php foreach ($carreras as $carrera): ?>
                                    <option value="<?php echo $carrera['id']; ?>" 
                                            <?php echo ($carrera_filtro == $carrera['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($carrera['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="busqueda" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="busqueda" name="busqueda" 
                                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                                   placeholder="Buscar por nombre, apellido, email o RUT">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                        <?php if ($carrera_filtro || $busqueda): ?>
                        <div class="col-12">
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-sm btn-secondary">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if (empty($docentes)): ?>
                    <p class="text-muted">No hay docentes registrados<?php echo ($carrera_filtro || $busqueda) ? ' con los filtros aplicados' : ''; ?>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="min-width: 180px;">Nombre</th>
                                    <th style="min-width: 200px;">Email</th>
                                    <th class="text-long" style="min-width: 250px;">Carrera</th>
                                    <th style="min-width: 100px;" class="text-center">Asignaturas</th>
                                    <th style="min-width: 100px;" class="text-center">Estado</th>
                                    <th class="actions" style="min-width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($docentes as $docente): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($docente['apellido'] . ', ' . $docente['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($docente['email']); ?></td>
                                        <td class="text-long"><?php echo htmlspecialchars($docente['carreras_nombres'] ?? 'Sin carreras'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?php echo $docente['total_asignaturas']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $docente['activo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $docente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <a href="<?php echo BASE_URL; ?>admin_supremo/asignaturas.php?docente_id=<?php echo $docente['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Gestionar asignaturas">
                                                <i class="bi bi-plus-circle"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>admin_supremo/docentes.php?editar=<?php echo $docente['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="" style="display: inline;" 
                                                  onsubmit="return confirm('¿Está seguro de eliminar este docente?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $docente['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Vista de cards para móvil (opcional, se puede ocultar si la tabla funciona bien) -->
                    <div class="d-none">
                        <?php foreach ($docentes as $docente): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-2">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($docente['apellido'] . ', ' . $docente['nombre']); ?>
                                    </h6>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-envelope"></i> <strong>Email:</strong><br>
                                            <?php echo htmlspecialchars($docente['email']); ?>
                                        </small>
                                    </p>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-book"></i> <strong>Carrera:</strong><br>
                                            <?php echo htmlspecialchars($docente['carreras_nombres'] ?? 'Sin carreras'); ?>
                                        </small>
                                    </p>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Asignaturas:</small>
                                            <span class="badge bg-info"><?php echo $docente['total_asignaturas']; ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Estado:</small>
                                            <span class="badge bg-<?php echo $docente['activo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $docente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-3 d-grid gap-2">
                                        <a href="<?php echo BASE_URL; ?>admin_supremo/asignaturas.php?docente_id=<?php echo $docente['id']; ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-plus-circle"></i> Gestionar Asignaturas
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>admin_supremo/docentes.php?editar=<?php echo $docente['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('¿Está seguro de eliminar este docente?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $docente['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger w-100">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function seleccionarTodasCarreras() {
    document.querySelectorAll('input[name="carreras_ids[]"]').forEach(function(checkbox) {
        checkbox.checked = true;
    });
}

function deseleccionarTodasCarreras() {
    document.querySelectorAll('input[name="carreras_ids[]"]').forEach(function(checkbox) {
        checkbox.checked = false;
    });
}

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

// Validar que al menos una carrera esté seleccionada
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="carreras_ids[]"]:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Por favor, seleccione al menos una carrera.');
                return false;
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>

