<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Ver Autoevaluación';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$autoeval_id = $_GET['id'] ?? null;

if (!$autoeval_id) {
    header('Location: ' . BASE_URL . 'admin/validaciones.php');
    exit();
}

// Obtener autoevaluación
$stmt = $pdo->prepare("
    SELECT a.*, asig.nombre as asignatura_nombre, r.nombre as rubrica_nombre,
           u.nombre as estudiante_nombre, u.apellido as estudiante_apellido, u.email as estudiante_email
    FROM autoevaluaciones a
    JOIN asignaturas asig ON a.asignatura_id = asig.id
    JOIN rubricas r ON a.rubrica_id = r.id
    JOIN usuarios u ON a.estudiante_id = u.id
    WHERE a.id = ? AND asig.docente_id = ?
");
$stmt->execute([$autoeval_id, $docente_id]);
$autoeval = $stmt->fetch();

if (!$autoeval) {
    header('Location: ' . BASE_URL . 'admin/validaciones.php');
    exit();
}

// Obtener respuestas
$stmt = $pdo->prepare("
    SELECT ra.*, c.nombre as criterio_nombre, c.peso, n.nombre as nivel_nombre, n.puntaje as nivel_puntaje
    FROM respuestas_autoevaluacion ra
    JOIN criterios c ON ra.criterio_id = c.id
    JOIN niveles n ON ra.nivel_id = n.id
    WHERE ra.autoevaluacion_id = ?
    ORDER BY c.orden
");
$stmt->execute([$autoeval_id]);
$respuestas = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Autoevaluación del Estudiante</h2>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Información General</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Estudiante:</strong> <?php echo htmlspecialchars($autoeval['estudiante_nombre'] . ' ' . $autoeval['estudiante_apellido']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($autoeval['estudiante_email']); ?></p>
                <p><strong>Asignatura:</strong> <?php echo htmlspecialchars($autoeval['asignatura_nombre']); ?></p>
                <p><strong>Rúbrica:</strong> <?php echo htmlspecialchars($autoeval['rubrica_nombre']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Estado:</strong> 
                    <span class="badge bg-<?php echo $autoeval['estado'] === 'completada' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $autoeval['estado'])); ?>
                    </span>
                </p>
                <p><strong>Fecha de Inicio:</strong> <?php echo date('d/m/Y H:i', strtotime($autoeval['tiempo_inicio'])); ?></p>
                <?php if ($autoeval['tiempo_fin']): ?>
                    <p><strong>Fecha de Finalización:</strong> <?php echo date('d/m/Y H:i', strtotime($autoeval['tiempo_fin'])); ?></p>
                <?php endif; ?>
                <p><strong>Nota Autoevaluada:</strong> 
                    <span class="badge bg-primary fs-6">
                        <?php echo $autoeval['nota_autoevaluada'] ? number_format($autoeval['nota_autoevaluada'], 1) : 'N/A'; ?>
                    </span>
                </p>
                <?php if ($autoeval['nota_ajustada']): ?>
                    <p><strong>Nota Ajustada:</strong> 
                        <span class="badge bg-warning fs-6">
                            <?php echo number_format($autoeval['nota_ajustada'], 1); ?>
                        </span>
                    </p>
                <?php endif; ?>
                <p><strong>Nota Final:</strong> 
                    <span class="badge bg-success fs-5">
                        <?php echo $autoeval['nota_final'] ? number_format($autoeval['nota_final'], 1) : 'Pendiente'; ?>
                    </span>
                </p>
            </div>
        </div>
        
        <?php if ($autoeval['comentario_estudiante']): ?>
            <div class="mt-3">
                <strong>Comentario del Estudiante:</strong>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($autoeval['comentario_estudiante'])); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($autoeval['comentario_docente']): ?>
            <div class="mt-3">
                <strong>Comentario del Docente:</strong>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($autoeval['comentario_docente'])); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Detalle de Respuestas</h5>
    </div>
    <div class="card-body">
        <?php 
        $total_puntaje = 0;
        foreach ($respuestas as $resp): 
            $total_puntaje += floatval($resp['puntaje']);
        endforeach;
        ?>
        
        <!-- Vista de tabla para desktop -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th style="min-width: 150px;">Criterio</th>
                    <th style="min-width: 150px;">Nivel Seleccionado</th>
                    <th style="min-width: 100px;">Puntaje</th>
                    <th style="min-width: 150px;">Comentario</th>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($respuestas as $resp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($resp['criterio_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($resp['nivel_nombre']); ?></td>
                            <td><?php echo number_format($resp['nivel_puntaje'], 1); ?></td>
                            <td><?php echo $resp['comentario'] ? htmlspecialchars($resp['comentario']) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="2">Total</th>
                        <th><?php echo number_format($total_puntaje, 1); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Vista de cards para móvil -->
        <div class="d-md-none">
            <?php foreach ($respuestas as $resp): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-primary mb-2">
                            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($resp['criterio_nombre']); ?>
                        </h6>
                        <div class="row mb-2">
                            <div class="col-6">
                                <small class="text-muted d-block">Nivel Seleccionado:</small>
                                <span class="badge bg-info"><?php echo htmlspecialchars($resp['nivel_nombre']); ?></span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Puntaje:</small>
                                <strong><?php echo number_format($resp['nivel_puntaje'], 1); ?></strong>
                            </div>
                        </div>
                        <?php if ($resp['comentario']): ?>
                            <div class="mt-2">
                                <small class="text-muted d-block">Comentario:</small>
                                <p class="mb-0 small"><?php echo htmlspecialchars($resp['comentario']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Total en móvil -->
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calculator"></i> Total: 
                        <strong><?php echo number_format($total_puntaje, 1); ?></strong>
                    </h5>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <a href="<?php echo BASE_URL; ?>admin/validaciones.php?asignatura_id=<?php echo $autoeval['asignatura_id']; ?>" 
           class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <button type="button" class="btn btn-primary" 
                onclick="abrirModalAjustar(<?php echo htmlspecialchars(json_encode($autoeval)); ?>)">
            <i class="bi bi-pencil"></i> Ajustar Nota
        </button>
    </div>
</div>

<!-- Modal para ajustar nota (similar al de validaciones.php) -->
<div class="modal fade" id="modalAjustar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajustar Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>admin/validaciones.php">
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
</script>

<?php include '../includes/footer.php'; ?>

