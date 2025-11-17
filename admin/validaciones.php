<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Validación de Autoevaluaciones';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];

$mensaje = '';
$error = '';

// Ajustar nota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ajustar') {
    $autoeval_id = $_POST['autoeval_id'] ?? null;
    $nota_ajustada = floatval($_POST['nota_ajustada'] ?? 0);
    $comentario = sanitizar($_POST['comentario'] ?? '');
    
    if ($autoeval_id && $nota_ajustada >= 0) {
        try {
            // Verificar que la autoevaluación pertenece a una asignatura del docente
            $stmt = $pdo->prepare("
                SELECT a.* FROM autoevaluaciones a
                JOIN asignaturas asig ON a.asignatura_id = asig.id
                WHERE a.id = ? AND asig.docente_id = ?
            ");
            $stmt->execute([$autoeval_id, $docente_id]);
            $autoeval = $stmt->fetch();
            
            if ($autoeval) {
                $nota_final = $nota_ajustada > 0 ? $nota_ajustada : $autoeval['nota_autoevaluada'];
                
                $stmt = $pdo->prepare("
                    UPDATE autoevaluaciones 
                    SET nota_ajustada = ?, nota_final = ?, comentario_docente = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nota_ajustada, $nota_final, $comentario, $autoeval_id]);
                
                $mensaje = 'Nota ajustada exitosamente';
            } else {
                $error = 'Autoevaluación no encontrada o no autorizada';
            }
        } catch (PDOException $e) {
            error_log("Error al ajustar nota: " . $e->getMessage());
            $error = 'Error al ajustar la nota. Por favor, intente nuevamente.';
        }
    } else {
        $error = 'Datos inválidos';
    }
}

// Pausar/reiniciar tiempo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'pausar' || $_POST['accion'] === 'reiniciar') {
        $autoeval_id = $_POST['autoeval_id'] ?? null;
        
        if ($autoeval_id) {
            try {
                $stmt = $pdo->prepare("
                    SELECT a.* FROM autoevaluaciones a
                    JOIN asignaturas asig ON a.asignatura_id = asig.id
                    WHERE a.id = ? AND asig.docente_id = ?
                ");
                $stmt->execute([$autoeval_id, $docente_id]);
                $autoeval = $stmt->fetch();
                
                if ($autoeval) {
                    if ($_POST['accion'] === 'pausar') {
                        $stmt = $pdo->prepare("
                            UPDATE autoevaluaciones 
                            SET estado = 'pausada', tiempo_pausa = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$autoeval_id]);
                        $mensaje = 'Autoevaluación pausada';
                    } elseif ($_POST['accion'] === 'reiniciar') {
                        // Reiniciar autoevaluación: cambiar estado a en_proceso, resetear tiempo y limpiar respuestas
                        $pdo->beginTransaction();
                        try {
                            // Eliminar respuestas anteriores
                            $stmt = $pdo->prepare("DELETE FROM respuestas_autoevaluacion WHERE autoevaluacion_id = ?");
                            $stmt->execute([$autoeval_id]);
                            
                            // Reiniciar autoevaluación
                            $stmt = $pdo->prepare("
                                UPDATE autoevaluaciones 
                                SET estado = 'en_proceso', 
                                    tiempo_restante = ?, 
                                    tiempo_inicio = NOW(),
                                    tiempo_pausa = NULL,
                                    tiempo_fin = NULL,
                                    nota_autoevaluada = NULL,
                                    nota_ajustada = NULL,
                                    nota_final = NULL,
                                    comentario_estudiante = NULL,
                                    comentario_docente = NULL
                                WHERE id = ?
                            ");
                            $stmt->execute([AUTOEVAL_TIME_LIMIT, $autoeval_id]);
                            
                            // Obtener información del estudiante para notificación
                            $stmt = $pdo->prepare("
                                SELECT u.id as estudiante_id, u.nombre, u.apellido, 
                                       asig.nombre as asignatura_nombre, r.nombre as rubrica_nombre
                                FROM autoevaluaciones a
                                JOIN usuarios u ON a.estudiante_id = u.id
                                JOIN asignaturas asig ON a.asignatura_id = asig.id
                                JOIN rubricas r ON a.rubrica_id = r.id
                                WHERE a.id = ?
                            ");
                            $stmt->execute([$autoeval_id]);
                            $info_autoeval = $stmt->fetch();
                            
                            if ($info_autoeval) {
                                // Crear notificación para el estudiante
                                require_once '../includes/notificaciones.php';
                                crearNotificacion(
                                    $info_autoeval['estudiante_id'],
                                    'Autoevaluación Reiniciada',
                                    "Su autoevaluación de {$info_autoeval['asignatura_nombre']} ({$info_autoeval['rubrica_nombre']}) ha sido reiniciada. Puede volver a iniciar el proceso desde su perfil.",
                                    'success',
                                    'estudiante/autoevaluacion.php'
                                );
                            }
                            
                            $pdo->commit();
                            $mensaje = 'Autoevaluación reiniciada exitosamente. El estudiante ha sido notificado y puede volver a iniciar el proceso desde su perfil.';
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            error_log("Error al reiniciar autoevaluación: " . $e->getMessage());
                            $error = 'Error al reiniciar la autoevaluación.';
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Error al pausar/reiniciar: " . $e->getMessage());
                $error = 'Error al procesar la solicitud.';
            }
        }
    }
}

// Obtener asignaturas del docente
$stmt = $pdo->prepare("
    SELECT a.*, c.nombre as carrera_nombre 
    FROM asignaturas a
    JOIN carreras c ON a.carrera_id = c.id
    WHERE a.docente_id = ? AND a.activa = 1
    ORDER BY c.nombre, a.nombre
");
$stmt->execute([$docente_id]);
$asignaturas = $stmt->fetchAll();

// Obtener autoevaluaciones con filtros
$asignatura_id = $_GET['asignatura_id'] ?? null;
$estado = $_GET['estado'] ?? 'completada';
$busqueda = sanitizar($_GET['busqueda'] ?? '');
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_nota_min = $_GET['nota_min'] ?? '';
$filtro_nota_max = $_GET['nota_max'] ?? '';
$autoevaluaciones = [];

if ($asignatura_id) {
    $sql = "
        SELECT a.*, asig.nombre as asignatura_nombre, r.nombre as rubrica_nombre,
               u.nombre as estudiante_nombre, u.apellido as estudiante_apellido, u.email as estudiante_email
        FROM autoevaluaciones a
        JOIN asignaturas asig ON a.asignatura_id = asig.id
        JOIN rubricas r ON a.rubrica_id = r.id
        JOIN usuarios u ON a.estudiante_id = u.id
        WHERE a.asignatura_id = ? AND asig.docente_id = ? AND a.estado = ?
    ";
    $params = [$asignatura_id, $docente_id, $estado];
    
    if (!empty($busqueda)) {
        $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR r.nombre LIKE ?)";
        $busqueda_param = "%$busqueda%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    
    if (!empty($filtro_fecha_desde)) {
        $sql .= " AND DATE(a.created_at) >= ?";
        $params[] = $filtro_fecha_desde;
    }
    
    if (!empty($filtro_fecha_hasta)) {
        $sql .= " AND DATE(a.created_at) <= ?";
        $params[] = $filtro_fecha_hasta;
    }
    
    if (!empty($filtro_nota_min)) {
        $sql .= " AND (a.nota_final >= ? OR a.nota_autoevaluada >= ?)";
        $params[] = $filtro_nota_min;
        $params[] = $filtro_nota_min;
    }
    
    if (!empty($filtro_nota_max)) {
        $sql .= " AND (a.nota_final <= ? OR a.nota_autoevaluada <= ?)";
        $params[] = $filtro_nota_max;
        $params[] = $filtro_nota_max;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $autoevaluaciones = $stmt->fetchAll();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Validación de Autoevaluaciones</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo $mensaje; ?></div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-funnel"></i> Filtros
            <button class="btn btn-sm btn-link text-white float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </h5>
    </div>
    <div class="collapse show" id="filtrosCollapse">
        <div class="card-body">
            <form method="GET" action="" id="filtrosForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="asignatura_id" class="form-label">Asignatura</label>
                        <select class="form-select" id="asignatura_id" name="asignatura_id">
                            <option value="">-- Seleccione una asignatura --</option>
                            <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id']; ?>" 
                                        <?php echo ($asignatura_id == $asig['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($asignatura_id): ?>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="completada" <?php echo ($estado === 'completada') ? 'selected' : ''; ?>>Completadas</option>
                            <option value="en_proceso" <?php echo ($estado === 'en_proceso') ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="pausada" <?php echo ($estado === 'pausada') ? 'selected' : ''; ?>>Pausadas</option>
                            <option value="incompleta" <?php echo ($estado === 'incompleta') ? 'selected' : ''; ?>>Incompletas (Pendientes)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="busqueda" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" 
                               value="<?php echo htmlspecialchars($busqueda); ?>" 
                               placeholder="Estudiante o rúbrica">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                               value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                               value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="nota_min" class="form-label">Nota Mínima</label>
                        <input type="number" class="form-control" id="nota_min" name="nota_min" 
                               value="<?php echo htmlspecialchars($filtro_nota_min); ?>" 
                               min="1" max="7" step="0.1" placeholder="1.0">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="nota_max" class="form-label">Nota Máxima</label>
                        <input type="number" class="form-control" id="nota_max" name="nota_max" 
                               value="<?php echo htmlspecialchars($filtro_nota_max); ?>" 
                               min="1" max="7" step="0.1" placeholder="7.0">
                    </div>
                    
                    <div class="col-md-12 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <?php if ($busqueda || $filtro_fecha_desde || $filtro_fecha_hasta || $filtro_nota_min || $filtro_nota_max): ?>
                        <a href="<?php echo BASE_URL; ?>admin/validaciones.php?asignatura_id=<?php echo $asignatura_id; ?>&estado=<?php echo $estado; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar filtros
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Ver Autoevaluaciones
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($asignatura_id && !empty($autoevaluaciones)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Autoevaluaciones (<?php echo count($autoevaluaciones); ?>)</h5>
                <?php if ($busqueda || $filtro_fecha_desde || $filtro_fecha_hasta || $filtro_nota_min || $filtro_nota_max): ?>
                    <span class="badge bg-info">Filtros activos</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="min-width: 180px;">Estudiante</th>
                                <th style="min-width: 200px;">Email</th>
                                <th class="text-long" style="min-width: 200px;">Rúbrica</th>
                                <th style="min-width: 120px;" class="text-center">Nota Autoevaluada</th>
                                <th style="min-width: 120px;" class="text-center">Nota Ajustada</th>
                                <th style="min-width: 100px;" class="text-center">Nota Final</th>
                                <th style="min-width: 130px;">Fecha</th>
                                <th class="actions" style="min-width: 200px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($autoevaluaciones as $autoeval): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($autoeval['estudiante_nombre'] . ' ' . $autoeval['estudiante_apellido']); ?></td>
                                    <td><?php echo htmlspecialchars($autoeval['estudiante_email']); ?></td>
                                    <td class="text-long"><?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></td>
                                    <td>
                                        <?php if ($autoeval['nota_autoevaluada']): ?>
                                            <span class="badge bg-primary">
                                                <?php echo number_format($autoeval['nota_autoevaluada'], 1); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($autoeval['nota_ajustada']): ?>
                                            <span class="badge bg-warning">
                                                <?php echo number_format($autoeval['nota_ajustada'], 1); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($autoeval['nota_final']): ?>
                                            <span class="badge bg-success fs-6">
                                                <?php echo number_format($autoeval['nota_final'], 1); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($autoeval['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="abrirModalAjustar(<?php echo htmlspecialchars(json_encode($autoeval)); ?>)">
                                            <i class="bi bi-pencil"></i> Ajustar
                                        </button>
                                        <a href="<?php echo BASE_URL; ?>admin/autoevaluacion_ver.php?id=<?php echo $autoeval['id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                        <?php if ($autoeval['estado'] === 'en_proceso' || $autoeval['estado'] === 'pausada'): ?>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="pausarReiniciar(<?php echo $autoeval['id']; ?>, '<?php echo $autoeval['estado'] === 'pausada' ? 'reiniciar' : 'pausar'; ?>')">
                                                <i class="bi bi-<?php echo $autoeval['estado'] === 'pausada' ? 'play' : 'pause'; ?>"></i>
                                                <?php echo $autoeval['estado'] === 'pausada' ? 'Reiniciar' : 'Pausar'; ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($autoeval['estado'] === 'incompleta'): ?>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="reiniciarAutoevaluacion(<?php echo $autoeval['id']; ?>)">
                                                <i class="bi bi-arrow-clockwise"></i> Reiniciar Proceso
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($asignatura_id && empty($autoevaluaciones)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No hay autoevaluaciones con el estado seleccionado.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal para ajustar nota -->
<div class="modal fade" id="modalAjustar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajustar Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="formAjustar">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="ajustar">
                    <input type="hidden" name="autoeval_id" id="autoeval_id_ajustar">
                    
                    <div class="mb-3">
                        <label class="form-label">Estudiante</label>
                        <input type="text" class="form-control" id="estudiante_nombre_ajustar" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nota Autoevaluada</label>
                        <input type="text" class="form-control" id="nota_autoevaluada_ajustar" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nota_ajustada" class="form-label">Nota Ajustada <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="nota_ajustada" name="nota_ajustada" 
                               min="0" max="7" step="0.1" required>
                        <small class="text-muted">Ingrese 0 para mantener la nota autoevaluada</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Comentario</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalAjustar(autoeval) {
    document.getElementById('autoeval_id_ajustar').value = autoeval.id;
    document.getElementById('estudiante_nombre_ajustar').value = 
        autoeval.estudiante_nombre + ' ' + autoeval.estudiante_apellido;
    document.getElementById('nota_autoevaluada_ajustar').value = 
        autoeval.nota_autoevaluada ? parseFloat(autoeval.nota_autoevaluada).toFixed(1) : '-';
    document.getElementById('nota_ajustada').value = 
        autoeval.nota_ajustada ? parseFloat(autoeval.nota_ajustada).toFixed(1) : '';
    document.getElementById('comentario').value = autoeval.comentario_docente || '';
    
    const modal = new bootstrap.Modal(document.getElementById('modalAjustar'));
    modal.show();
}

function pausarReiniciar(autoevalId, accion) {
    if (confirm(`¿Está seguro de ${accion === 'pausar' ? 'pausar' : 'reiniciar el tiempo de'} esta autoevaluación?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="${accion}">
            <input type="hidden" name="autoeval_id" value="${autoevalId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function reiniciarAutoevaluacion(autoevalId) {
    if (confirm('¿Está seguro de reiniciar esta autoevaluación? Se eliminarán todas las respuestas y el estudiante podrá volver a iniciar el proceso desde su perfil.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="reiniciar">
            <input type="hidden" name="autoeval_id" value="${autoevalId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>

