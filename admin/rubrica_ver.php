<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Ver Rúbrica';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$rubrica_id = $_GET['id'] ?? null;

if (!$rubrica_id) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Obtener rúbrica con información del docente
$stmt = $pdo->prepare("
    SELECT r.*, a.nombre as asignatura_nombre, c.nombre as carrera_nombre,
           u.nombre as docente_nombre, u.apellido as docente_apellido
    FROM rubricas r
    JOIN asignaturas a ON r.asignatura_id = a.id
    JOIN carreras c ON a.carrera_id = c.id
    JOIN usuarios u ON a.docente_id = u.id
    WHERE r.id = ? AND a.docente_id = ?
");
$stmt->execute([$rubrica_id, $docente_id]);
$rubrica = $stmt->fetch();

if (!$rubrica) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Obtener criterios con niveles
$stmt = $pdo->prepare("
    SELECT c.*, 
           GROUP_CONCAT(n.id ORDER BY n.orden SEPARATOR '||') as nivel_ids,
           GROUP_CONCAT(n.nombre ORDER BY n.orden SEPARATOR '||') as nivel_nombres,
           GROUP_CONCAT(n.descripcion ORDER BY n.orden SEPARATOR '||') as nivel_descripciones,
           GROUP_CONCAT(n.puntaje ORDER BY n.orden SEPARATOR '||') as nivel_puntajes
    FROM criterios c
    LEFT JOIN niveles n ON c.id = n.criterio_id
    WHERE c.rubrica_id = ?
    GROUP BY c.id
    ORDER BY c.orden
");
$stmt->execute([$rubrica_id]);
$criterios = $stmt->fetchAll();
?>

<?php
// Calcular puntaje total
$puntaje_total = 0;
$puntaje_4_0 = 0;
foreach ($criterios as $criterio) {
    $nivel_puntajes = explode('||', $criterio['nivel_puntajes']);
    $max_puntaje = 0;
    foreach ($nivel_puntajes as $puntaje) {
        if (!empty($puntaje) && floatval($puntaje) > $max_puntaje) {
            $max_puntaje = floatval($puntaje);
        }
    }
    $puntaje_total += $max_puntaje;
    // Puntaje para nota 4.0 (60% del total)
    $puntaje_4_0 += $max_puntaje * 0.6;
}
?>

<style>
.rubrica-evaluacion {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
}
.rubrica-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #003366;
}
.rubrica-logo {
    font-size: 18px;
    font-weight: bold;
    color: #003366;
}
.rubrica-title {
    font-size: 20px;
    font-weight: bold;
    color: #003366;
}
.rubrica-info-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
}
.rubrica-info-table td {
    padding: 8px;
    border: 1px solid #ddd;
    vertical-align: top;
}
.rubrica-info-table .label {
    font-weight: bold;
    background-color: #f8f9fa;
    width: 30%;
}
.rubrica-main-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
    font-size: 12px;
}
.rubrica-main-table th,
.rubrica-main-table td {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
    vertical-align: top;
}
.rubrica-main-table th {
    background-color: #003366;
    color: white;
    font-weight: bold;
    text-align: center;
}
.rubrica-main-table .criterio-row {
    background-color: #f8f9fa;
    font-weight: bold;
}
.rubrica-main-table .subcriterio-row {
    background-color: #ffffff;
}
.rubrica-main-table .nivel-col {
    width: 20%;
    font-size: 11px;
}
.rubrica-main-table .puntos-col {
    width: 8%;
    text-align: center;
    font-weight: bold;
}
.rubrica-observaciones {
    margin-top: 20px;
    margin-bottom: 20px;
}
.rubrica-observaciones textarea {
    width: 100%;
    min-height: 100px;
    padding: 10px;
    border: 1px solid #ddd;
}
.rubrica-conversion-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.rubrica-conversion-table th,
.rubrica-conversion-table td {
    padding: 5px;
    border: 1px solid #ddd;
    text-align: center;
    font-size: 11px;
}
.rubrica-conversion-table th {
    background-color: #003366;
    color: white;
    font-weight: bold;
}
</style>

<div class="rubrica-evaluacion">
    <!-- Encabezado -->
    <div class="rubrica-header">
        <div class="rubrica-logo">TEC-UCT INSTITUTO TECNOLÓGICO</div>
        <div class="rubrica-title"><?php echo htmlspecialchars($rubrica['nombre']); ?></div>
    </div>
    
    <!-- Información General -->
    <table class="rubrica-info-table">
        <tr>
            <td class="label">Estudiante:</td>
            <td></td>
            <td class="label">Puntaje Total:</td>
            <td><?php echo number_format($puntaje_total, 0); ?></td>
        </tr>
        <tr>
            <td class="label">Carrera:</td>
            <td><?php echo htmlspecialchars($rubrica['carrera_nombre']); ?></td>
            <td class="label">Puntaje 4.0:</td>
            <td><?php echo number_format($puntaje_4_0, 0); ?></td>
        </tr>
        <tr>
            <td class="label">Módulo:</td>
            <td><?php echo htmlspecialchars($rubrica['asignatura_nombre']); ?></td>
            <td class="label">Puntaje Obtenido:</td>
            <td></td>
        </tr>
        <tr>
            <td class="label">Docente:</td>
            <td><?php echo htmlspecialchars($rubrica['docente_nombre'] . ' ' . $rubrica['docente_apellido']); ?></td>
            <td class="label">Nota Final:</td>
            <td></td>
        </tr>
    </table>
    
    <!-- Tabla Principal de Niveles de Desempeño -->
    <table class="rubrica-main-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 15%;">Criterios</th>
                <th rowspan="2" style="width: 15%;">Sub Criterios</th>
                <th colspan="<?php 
                    $max_niveles = 0;
                    foreach ($criterios as $criterio) {
                        $nivel_ids = explode('||', $criterio['nivel_ids']);
                        $count = count(array_filter($nivel_ids));
                        if ($count > $max_niveles) {
                            $max_niveles = $count;
                        }
                    }
                    echo $max_niveles;
                ?>">Niveles de desempeño</th>
                <th rowspan="2" class="puntos-col">Puntos</th>
            </tr>
            <tr>
                <?php
                // Obtener todos los niveles únicos ordenados por puntaje descendente
                $todos_niveles = [];
                foreach ($criterios as $criterio) {
                    $nivel_ids = explode('||', $criterio['nivel_ids']);
                    $nivel_nombres = explode('||', $criterio['nivel_nombres']);
                    $nivel_puntajes = explode('||', $criterio['nivel_puntajes']);
                    for ($i = 0; $i < count($nivel_ids); $i++) {
                        if (!empty($nivel_ids[$i])) {
                            $todos_niveles[] = [
                                'nombre' => $nivel_nombres[$i],
                                'puntaje' => floatval($nivel_puntajes[$i])
                            ];
                        }
                    }
                }
                // Ordenar por puntaje descendente y obtener únicos
                usort($todos_niveles, function($a, $b) {
                    return $b['puntaje'] <=> $a['puntaje'];
                });
                $niveles_unicos = [];
                foreach ($todos_niveles as $nivel) {
                    if (!in_array($nivel['nombre'], array_column($niveles_unicos, 'nombre'))) {
                        $niveles_unicos[] = $nivel;
                    }
                }
                // Limitar a 4 niveles máximo
                $niveles_unicos = array_slice($niveles_unicos, 0, 4);
                foreach ($niveles_unicos as $nivel): 
                ?>
                    <th class="nivel-col"><?php echo htmlspecialchars($nivel['nombre']); ?> (<?php echo number_format($nivel['puntaje'], 0); ?>)</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($criterios as $index => $criterio): ?>
                <?php
                $nivel_ids = explode('||', $criterio['nivel_ids']);
                $nivel_nombres = explode('||', $criterio['nivel_nombres']);
                $nivel_descripciones = explode('||', $criterio['nivel_descripciones']);
                $nivel_puntajes = explode('||', $criterio['nivel_puntajes']);
                
                // Obtener máximo puntaje del criterio
                $max_puntaje_criterio = 0;
                foreach ($nivel_puntajes as $puntaje) {
                    if (!empty($puntaje) && floatval($puntaje) > $max_puntaje_criterio) {
                        $max_puntaje_criterio = floatval($puntaje);
                    }
                }
                ?>
                <tr class="criterio-row">
                    <td rowspan="<?php echo count(array_filter($nivel_ids)) + 1; ?>">
                        <?php echo htmlspecialchars($criterio['nombre']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($criterio['descripcion'] ?? $criterio['nombre']); ?></td>
                    <?php foreach ($niveles_unicos as $nivel_unico): ?>
                        <td class="nivel-col">
                            <?php
                            // Buscar si este nivel existe para este criterio
                            $encontrado = false;
                            for ($i = 0; $i < count($nivel_ids); $i++) {
                                if (!empty($nivel_ids[$i]) && $nivel_nombres[$i] === $nivel_unico['nombre']) {
                                    echo htmlspecialchars($nivel_descripciones[$i] ?? '-');
                                    $encontrado = true;
                                    break;
                                }
                            }
                            if (!$encontrado) {
                                echo '-';
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="puntos-col"><?php echo number_format($max_puntaje_criterio, 0); ?></td>
                </tr>
                <?php
                // Mostrar niveles adicionales si existen
                $niveles_mostrados = 0;
                for ($i = 0; $i < count($nivel_ids); $i++):
                    if (!empty($nivel_ids[$i])):
                        $nivel_nombre = $nivel_nombres[$i];
                        // Solo mostrar si no es el primer nivel (ya mostrado en la fila del criterio)
                        if ($niveles_mostrados > 0):
                ?>
                    <tr class="subcriterio-row">
                        <td><?php echo htmlspecialchars($nivel_nombre); ?></td>
                        <?php foreach ($niveles_unicos as $nivel_unico): ?>
                            <td class="nivel-col">
                                <?php
                                if ($nivel_nombre === $nivel_unico['nombre']) {
                                    echo htmlspecialchars($nivel_descripciones[$i] ?? '-');
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="puntos-col"><?php echo number_format($nivel_puntajes[$i], 0); ?></td>
                    </tr>
                <?php
                        endif;
                        $niveles_mostrados++;
                    endif;
                endfor;
                ?>
            <?php endforeach; ?>
            <tr class="table-primary" style="background-color: #cfe2ff;">
                <th colspan="<?php echo 2 + count($niveles_unicos); ?>">Total</th>
                <th class="puntos-col"><?php echo number_format($puntaje_total, 0); ?></th>
            </tr>
        </tbody>
    </table>
    
    <!-- Observaciones -->
    <div class="rubrica-observaciones">
        <strong>OBSERVACIONES:</strong>
        <textarea readonly><?php echo htmlspecialchars($rubrica['descripcion'] ?? ''); ?></textarea>
    </div>
    
    <!-- Tabla de Conversión Puntaje/Nota -->
    <table class="rubrica-conversion-table">
        <thead>
            <tr>
                <th>Puntaje</th>
                <th>Nota</th>
                <th>Puntaje</th>
                <th>Nota</th>
                <th>Puntaje</th>
                <th>Nota</th>
                <th>Puntaje</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $puntaje_max = $puntaje_total;
            $incremento = $puntaje_max / 7;
            for ($i = 0; $i < 7; $i++):
                $puntaje = $i * $incremento;
                $nota = 1.0 + ($i * 1.0);
            ?>
                <tr>
                    <td><?php echo number_format($puntaje, 1); ?></td>
                    <td><?php echo number_format($nota, 1); ?></td>
                    <?php if ($i < 6): ?>
                        <td><?php echo number_format($puntaje + ($incremento / 2), 1); ?></td>
                        <td><?php echo number_format($nota + 0.5, 1); ?></td>
                    <?php else: ?>
                        <td></td>
                        <td></td>
                    <?php endif; ?>
                    <?php if ($i < 5): ?>
                        <td><?php echo number_format($puntaje + ($incremento * 0.75), 1); ?></td>
                        <td><?php echo number_format($nota + 0.75, 1); ?></td>
                    <?php else: ?>
                        <td></td>
                        <td></td>
                    <?php endif; ?>
                    <?php if ($i < 4): ?>
                        <td><?php echo number_format($puntaje + ($incremento * 0.25), 1); ?></td>
                        <td><?php echo number_format($nota + 0.25, 1); ?></td>
                    <?php else: ?>
                        <td></td>
                        <td></td>
                    <?php endif; ?>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>

<div class="row mt-4">
    <div class="col-12">
        <a href="<?php echo BASE_URL; ?>admin/rubrica_editar.php?id=<?php echo $rubrica_id; ?>" 
           class="btn btn-primary">
            <i class="bi bi-pencil"></i> Editar Rúbrica
        </a>
        <a href="<?php echo BASE_URL; ?>admin/rubricas.php?asignatura_id=<?php echo $rubrica['asignatura_id']; ?>" 
           class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

