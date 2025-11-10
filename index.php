<?php
require_once 'config/config.php';
validarSesion();

$titulo = 'Inicio';
$usuario_tipo = $_SESSION['usuario_tipo'];

include 'includes/header.php';

$pdo = getDBConnection();

// Obtener estadísticas según tipo de usuario
if ($usuario_tipo === 'estudiante') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
               SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso
        FROM autoevaluaciones 
        WHERE estudiante_id = ?
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $stats = $stmt->fetch();
    
    // Obtener última autoevaluación
    $stmt = $pdo->prepare("
        SELECT a.*, asig.nombre as asignatura_nombre, r.nombre as rubrica_nombre
        FROM autoevaluaciones a
        JOIN asignaturas asig ON a.asignatura_id = asig.id
        JOIN rubricas r ON a.rubrica_id = r.id
        WHERE a.estudiante_id = ?
        ORDER BY a.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $ultima_autoeval = $stmt->fetch();
} elseif (in_array($usuario_tipo, ['docente', 'admin'])) {
    // Estadísticas para docente/admin
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id) as total_autoevaluaciones,
               COUNT(DISTINCT a.estudiante_id) as total_estudiantes,
               COUNT(DISTINCT a.asignatura_id) as total_asignaturas,
               SUM(CASE WHEN a.estado = 'completada' THEN 1 ELSE 0 END) as completadas
        FROM autoevaluaciones a
        JOIN asignaturas asig ON a.asignatura_id = asig.id
        WHERE asig.docente_id = ?
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $stats = $stmt->fetch();
}
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4">
            <?php 
            if ($usuario_tipo === 'docente' || $usuario_tipo === 'admin') {
                echo 'Bienvenido Profesor ' . htmlspecialchars($_SESSION['usuario_nombre']);
            } elseif ($usuario_tipo === 'admin_supremo') {
                echo 'Bienvenido ' . htmlspecialchars($_SESSION['usuario_nombre']);
            } else {
                echo 'Bienvenido, ' . htmlspecialchars($_SESSION['usuario_nombre']);
            }
            ?>
        </h2>
    </div>
</div>

<?php if ($usuario_tipo === 'estudiante'): ?>
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Autoevaluaciones</h5>
                    <h2 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Completadas</h5>
                    <h2 class="text-success"><?php echo $stats['completadas'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">En Proceso</h5>
                    <h2 class="text-warning"><?php echo $stats['en_proceso'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($ultima_autoeval): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Última Autoevaluación</h5>
            </div>
            <div class="card-body">
                <p><strong>Asignatura:</strong> <?php echo htmlspecialchars($ultima_autoeval['asignatura_nombre']); ?></p>
                <p><strong>Rúbrica:</strong> <?php echo htmlspecialchars($ultima_autoeval['rubrica_nombre']); ?></p>
                <p><strong>Estado:</strong> 
                    <span class="badge bg-<?php 
                        echo $ultima_autoeval['estado'] === 'completada' ? 'success' : 
                            ($ultima_autoeval['estado'] === 'en_proceso' ? 'warning' : 'secondary'); 
                    ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $ultima_autoeval['estado'])); ?>
                    </span>
                </p>
                <?php if ($ultima_autoeval['nota_final']): ?>
                    <p><strong>Nota Final:</strong> <?php echo number_format($ultima_autoeval['nota_final'], 1); ?></p>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion.php?id=<?php echo $ultima_autoeval['id']; ?>" 
                   class="btn btn-primary">
                    Ver Detalles
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php
    // Vista previa de asignaturas disponibles
    $estudiante_id = $_SESSION['usuario_id']; // Definir variable para uso en consulta
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.id, a.nombre as asignatura_nombre, c.nombre as carrera_nombre,
               r.id as rubrica_id, r.nombre as rubrica_nombre,
               (SELECT estado FROM autoevaluaciones 
                WHERE estudiante_id = ? AND asignatura_id = a.id AND rubrica_id = r.id 
                ORDER BY created_at DESC LIMIT 1) as estado_autoeval
        FROM estudiantes_asignaturas ea
        JOIN asignaturas a ON ea.asignatura_id = a.id
        JOIN carreras c ON a.carrera_id = c.id
        LEFT JOIN rubricas r ON r.asignatura_id = a.id AND r.activa = 1
        WHERE ea.estudiante_id = ? AND ea.activo = 1 AND a.activa = 1
        ORDER BY c.nombre, a.nombre
    ");
    $stmt->execute([$estudiante_id, $estudiante_id]);
    $asignaturas_disponibles = $stmt->fetchAll();
    
    // Agrupar por asignatura
    $asignaturas_agrupadas = [];
    foreach ($asignaturas_disponibles as $item) {
        if (!isset($asignaturas_agrupadas[$item['id']])) {
            $asignaturas_agrupadas[$item['id']] = [
                'id' => $item['id'],
                'nombre' => $item['asignatura_nombre'],
                'carrera' => $item['carrera_nombre'],
                'rubricas' => []
            ];
        }
        if ($item['rubrica_id']) {
            $asignaturas_agrupadas[$item['id']]['rubricas'][] = [
                'id' => $item['rubrica_id'],
                'nombre' => $item['rubrica_nombre'],
                'estado' => $item['estado_autoeval']
            ];
        }
    }
    ?>
    
    <?php if (!empty($asignaturas_agrupadas)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Asignaturas Disponibles</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Asignaturas a las que está inscrito y sus rúbricas disponibles:</p>
                
                <!-- Vista de tabla para desktop -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="min-width: 150px;">Carrera</th>
                                <th style="min-width: 150px;">Asignatura</th>
                                <th style="min-width: 150px;">Rúbrica</th>
                                <th style="min-width: 100px;">Estado</th>
                                <th style="min-width: 120px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($asignaturas_agrupadas as $asig): ?>
                                <?php if (!empty($asig['rubricas'])): ?>
                                    <?php foreach ($asig['rubricas'] as $rubrica): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($asig['carrera']); ?></td>
                                            <td><?php echo htmlspecialchars($asig['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($rubrica['nombre']); ?></td>
                                            <td>
                                                <?php if ($rubrica['estado'] === 'completada'): ?>
                                                    <span class="badge bg-success">Completada</span>
                                                <?php elseif ($rubrica['estado'] === 'incompleta'): ?>
                                                    <span class="badge bg-warning">Pendiente</span>
                                                <?php elseif ($rubrica['estado'] === 'en_proceso' || $rubrica['estado'] === 'pausada'): ?>
                                                    <span class="badge bg-info">En Proceso</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Disponible</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rubrica['estado'] === 'completada'): ?>
                                                    <span class="text-muted small">Ya completada</span>
                                                <?php elseif ($rubrica['estado'] === 'incompleta'): ?>
                                                    <span class="text-warning small">Contacte docente</span>
                                                <?php else: ?>
                                                    <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion.php" 
                                                       class="btn btn-sm btn-primary">
                                                        Iniciar
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($asig['carrera']); ?></td>
                                        <td><?php echo htmlspecialchars($asig['nombre']); ?></td>
                                        <td colspan="3" class="text-muted">Sin rúbricas disponibles</td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Vista de cards para móvil -->
                <div class="d-md-none">
                    <?php foreach ($asignaturas_agrupadas as $asig): ?>
                        <?php if (!empty($asig['rubricas'])): ?>
                            <?php foreach ($asig['rubricas'] as $rubrica): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary mb-2">
                                            <i class="bi bi-book"></i> <?php echo htmlspecialchars($asig['nombre']); ?>
                                        </h6>
                                        <p class="card-text mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-mortarboard"></i> <strong>Carrera:</strong><br>
                                                <?php echo htmlspecialchars($asig['carrera']); ?>
                                            </small>
                                        </p>
                                        <p class="card-text mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-clipboard-check"></i> <strong>Rúbrica:</strong><br>
                                                <?php echo htmlspecialchars($rubrica['nombre']); ?>
                                            </small>
                                        </p>
                                        <p class="card-text mb-2">
                                            <strong>Estado:</strong><br>
                                            <?php if ($rubrica['estado'] === 'completada'): ?>
                                                <span class="badge bg-success">Completada</span>
                                            <?php elseif ($rubrica['estado'] === 'incompleta'): ?>
                                                <span class="badge bg-warning">Pendiente</span>
                                            <?php elseif ($rubrica['estado'] === 'en_proceso' || $rubrica['estado'] === 'pausada'): ?>
                                                <span class="badge bg-info">En Proceso</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disponible</span>
                                            <?php endif; ?>
                                        </p>
                                        <div class="mt-2">
                                            <?php if ($rubrica['estado'] === 'completada'): ?>
                                                <span class="text-muted small">Ya completada</span>
                                            <?php elseif ($rubrica['estado'] === 'incompleta'): ?>
                                                <span class="text-warning small">Contacte a su docente</span>
                                            <?php else: ?>
                                                <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion.php" 
                                                   class="btn btn-sm btn-primary w-100">
                                                    <i class="bi bi-play-circle"></i> Iniciar Autoevaluación
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-2">
                                        <i class="bi bi-book"></i> <?php echo htmlspecialchars($asig['nombre']); ?>
                                    </h6>
                                    <p class="card-text mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-mortarboard"></i> <strong>Carrera:</strong><br>
                                            <?php echo htmlspecialchars($asig['carrera']); ?>
                                        </small>
                                    </p>
                                    <p class="text-muted small">Sin rúbricas disponibles</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row mt-4">
        <div class="col-12">
            <a href="<?php echo BASE_URL; ?>estudiante/autoevaluacion.php" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-clipboard-check"></i> Iniciar Nueva Autoevaluación
            </a>
        </div>
    </div>

<?php elseif (in_array($usuario_tipo, ['docente', 'admin'])): ?>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Autoevaluaciones</h5>
                    <h2 class="text-primary"><?php echo $stats['total_autoevaluaciones'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Estudiantes</h5>
                    <h2 class="text-info"><?php echo $stats['total_estudiantes'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Asignaturas</h5>
                    <h2 class="text-secondary"><?php echo $stats['total_asignaturas'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Completadas</h5>
                    <h2 class="text-success"><?php echo $stats['completadas'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Accesos Rápidos</h5>
                </div>
                <div class="card-body">
                    <a href="<?php echo BASE_URL; ?>admin/rubricas.php" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-list-check"></i> Gestionar Rúbricas
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/estudiantes.php" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-people"></i> Gestionar Estudiantes
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/validaciones.php" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-circle"></i> Validar Autoevaluaciones
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/reportes.php" class="btn btn-primary w-100">
                        <i class="bi bi-file-earmark-bar-graph"></i> Generar Reportes
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Información</h5>
                </div>
                <div class="card-body">
                    <p>Bienvenido al panel de administración de la plataforma de autoevaluación TEC-UCT.</p>
                    <p>Desde aquí puede gestionar rúbricas, estudiantes, asignaturas y validar las autoevaluaciones realizadas por los estudiantes.</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($usuario_tipo === 'admin_supremo'): ?>
    <?php
    // Estadísticas para admin_supremo
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM carreras");
    $total_carreras = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM asignaturas");
    $total_asignaturas = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'docente'");
    $total_docentes = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'estudiante'");
    $total_estudiantes = $stmt->fetch()['total'];
    
    // Estadísticas de uso (sin datos sensibles)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM autoevaluaciones");
    $total_autoevaluaciones = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM autoevaluaciones WHERE estado = 'completada'");
    $autoevaluaciones_completadas = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rubricas");
    $total_rubricas = $stmt->fetch()['total'];
    
    // Docentes sin asignaturas
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as total 
        FROM usuarios u
        LEFT JOIN asignaturas a ON u.id = a.docente_id
        WHERE u.tipo = 'docente' AND a.id IS NULL
    ");
    $docentes_sin_asignaturas = $stmt->fetch()['total'];
    
    // Estudiantes sin asignaturas
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as total 
        FROM usuarios u
        LEFT JOIN estudiantes_asignaturas ea ON u.id = ea.estudiante_id
        WHERE u.tipo = 'estudiante' AND ea.id IS NULL
    ");
    $estudiantes_sin_asignaturas = $stmt->fetch()['total'];
    ?>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Carreras</h5>
                    <h2 class="text-primary"><?php echo $total_carreras; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Asignaturas</h5>
                    <h2 class="text-info"><?php echo $total_asignaturas; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Docentes</h5>
                    <h2 class="text-secondary"><?php echo $total_docentes; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Estudiantes</h5>
                    <h2 class="text-success"><?php echo $total_estudiantes; ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Rúbricas</h5>
                    <h2 class="text-primary"><?php echo $total_rubricas; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Autoevaluaciones</h5>
                    <h2 class="text-info"><?php echo $total_autoevaluaciones; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Completadas</h5>
                    <h2 class="text-success"><?php echo $autoevaluaciones_completadas; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Tasa de Completación</h5>
                    <h2 class="text-<?php echo ($total_autoevaluaciones > 0 && ($autoevaluaciones_completadas / $total_autoevaluaciones) >= 0.7) ? 'success' : 'warning'; ?>">
                        <?php echo $total_autoevaluaciones > 0 ? number_format(($autoevaluaciones_completadas / $total_autoevaluaciones) * 100, 1) : 0; ?>%
                    </h2>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($docentes_sin_asignaturas > 0 || $estudiantes_sin_asignaturas > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> Alertas del Sistema</h5>
                    <ul class="mb-0">
                        <?php if ($docentes_sin_asignaturas > 0): ?>
                            <li><strong><?php echo $docentes_sin_asignaturas; ?></strong> docente(s) sin asignaturas asignadas</li>
                        <?php endif; ?>
                        <?php if ($estudiantes_sin_asignaturas > 0): ?>
                            <li><strong><?php echo $estudiantes_sin_asignaturas; ?></strong> estudiante(s) sin asignaturas asignadas</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Accesos Rápidos</h5>
                </div>
                <div class="card-body">
                    <a href="<?php echo BASE_URL; ?>admin_supremo/carreras.php" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-building"></i> Gestionar Carreras
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin_supremo/asignaturas.php" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-book"></i> Gestionar Asignaturas
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin_supremo/docentes.php" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-person-badge"></i> Gestionar Docentes
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php" class="btn btn-primary w-100">
                        <i class="bi bi-people"></i> Gestionar Estudiantes
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Información</h5>
                </div>
                <div class="card-body">
                    <p>Bienvenido al panel de administración suprema de la plataforma de autoevaluación TEC-UCT.</p>
                    <p>Desde aquí puede gestionar carreras, asignaturas, docentes y estudiantes. No tiene acceso a rúbricas ni autoevaluaciones.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

