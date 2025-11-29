<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$asignatura_id = $_GET['asignatura_id'] ?? null;

if (!$asignatura_id) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Verificar asignatura
$stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ? AND docente_id = ?");
$stmt->execute([$asignatura_id, $docente_id]);
$asignatura = $stmt->fetch();

if (!$asignatura) {
    header('Location: ' . BASE_URL . 'admin/rubricas.php');
    exit();
}

// Obtener rúbricas
$rubrica_id = $_GET['rubrica_id'] ?? null;

if ($rubrica_id) {
    // Exportar rúbrica específica
    $stmt = $pdo->prepare("
        SELECT * FROM rubricas 
        WHERE id = ? AND asignatura_id = ?
    ");
    $stmt->execute([$rubrica_id, $asignatura_id]);
    $rubrica = $stmt->fetch();
    
    if ($rubrica) {
        // Obtener criterios
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   GROUP_CONCAT(n.id ORDER BY n.orden SEPARATOR '||') as nivel_ids,
                   GROUP_CONCAT(n.nombre ORDER BY n.orden SEPARATOR '||') as nivel_nombres,
                   GROUP_CONCAT(n.descripcion ORDER BY n.orden SEPARATOR '||') as nivel_descripciones,
                   GROUP_CONCAT(n.puntaje ORDER BY n.orden SEPARATOR '||') as nivel_puntajes,
                   GROUP_CONCAT(n.orden ORDER BY n.orden SEPARATOR '||') as nivel_ordenes
            FROM criterios c
            LEFT JOIN niveles n ON c.id = n.criterio_id
            WHERE c.rubrica_id = ?
            GROUP BY c.id
            ORDER BY c.orden
        ");
        $stmt->execute([$rubrica_id]);
        $criterios = $stmt->fetchAll();
        
        // Configurar headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rubrica_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Obtener escala de notas si existe
        $escala_personalizada = false;
        $escala_notas = [];
        try {
            $stmt_check = $pdo->query("SHOW COLUMNS FROM rubricas LIKE 'escala_personalizada'");
            $columnas_existen = $stmt_check->rowCount() > 0;
        } catch (PDOException $e) {
            $columnas_existen = false;
        }
        
        if ($columnas_existen && isset($rubrica['escala_personalizada']) && $rubrica['escala_personalizada']) {
            $escala_personalizada = true;
            if (!empty($rubrica['escala_notas'])) {
                $escala_notas = json_decode($rubrica['escala_notas'], true);
                if (!is_array($escala_notas)) {
                    $escala_notas = [];
                    $escala_personalizada = false;
                }
            }
        }
        
        // Nombre de rúbrica
        fputcsv($output, [$rubrica['nombre'], $rubrica['descripcion']]);
        
        // Encabezados
        fputcsv($output, ['Tipo', 'Nombre', 'Descripción', 'Valor', 'Peso/Orden']);
        
        // Datos
        foreach ($criterios as $criterio) {
            // Criterio
            fputcsv($output, [
                'Criterio',
                $criterio['nombre'],
                $criterio['descripcion'] ?? '',
                '0',
                $criterio['peso']
            ]);
            
            // Niveles
            $nivel_ids = explode('||', $criterio['nivel_ids']);
            $nivel_nombres = explode('||', $criterio['nivel_nombres']);
            $nivel_descripciones = explode('||', $criterio['nivel_descripciones']);
            $nivel_puntajes = explode('||', $criterio['nivel_puntajes']);
            $nivel_ordenes = explode('||', $criterio['nivel_ordenes']);
            
            for ($i = 0; $i < count($nivel_ids); $i++) {
                if (!empty($nivel_ids[$i])) {
                    fputcsv($output, [
                        'Nivel',
                        $nivel_nombres[$i],
                        $nivel_descripciones[$i] ?? '',
                        $nivel_puntajes[$i],
                        $nivel_ordenes[$i] ?? ($i + 1)
                    ]);
                }
            }
        }
        
        // Agregar escala de notas si existe
        if ($escala_personalizada && !empty($escala_notas)) {
            fputcsv($output, []); // Línea en blanco
            fputcsv($output, ['ESCALA DE NOTAS PERSONALIZADA']);
            fputcsv($output, ['Puntaje Máximo', 'Exigencia (%)', 'Nota Mínima', 'Nota Máxima', 'Nota Aprobación']);
            if (isset($escala_notas['puntaje_maximo'])) {
                fputcsv($output, [
                    $escala_notas['puntaje_maximo'],
                    $escala_notas['exigencia'],
                    $escala_notas['nota_minima'],
                    $escala_notas['nota_maxima'],
                    $escala_notas['nota_aprobacion']
                ]);
            } else {
                // Formato antiguo (compatibilidad)
                fputcsv($output, ['Puntaje Mínimo', 'Puntaje Máximo', 'Nota']);
                foreach ($escala_notas as $escala) {
                    if (isset($escala['puntaje_min'])) {
                        fputcsv($output, [
                            $escala['puntaje_min'],
                            $escala['puntaje_max'],
                            $escala['nota']
                        ]);
                    }
                }
            }
        }
        
        fclose($output);
        exit();
    }
}

// Si no hay rúbrica específica, redirigir
header('Location: ' . BASE_URL . 'admin/rubricas.php?asignatura_id=' . $asignatura_id);
exit();
?>

