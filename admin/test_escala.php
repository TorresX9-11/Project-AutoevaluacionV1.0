<?php
// Script de prueba para verificar el guardado de escala
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$pdo = getDBConnection();
$rubrica_id = $_GET['id'] ?? 1;

echo "<h2>Prueba de Escala de Notas</h2>";

// 1. Verificar si las columnas existen
echo "<h3>1. Verificar columnas:</h3>";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM rubricas");
    $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columnas encontradas: " . implode(", ", $columnas) . "<br>";
    
    $tiene_escala_personalizada = in_array('escala_personalizada', $columnas);
    $tiene_escala_notas = in_array('escala_notas', $columnas);
    
    echo "escala_personalizada existe: " . ($tiene_escala_personalizada ? "SÍ" : "NO") . "<br>";
    echo "escala_notas existe: " . ($tiene_escala_notas ? "SÍ" : "NO") . "<br>";
    
    if (!$tiene_escala_personalizada || !$tiene_escala_notas) {
        echo "<strong style='color:red;'>ERROR: Las columnas no existen. Ejecute el script de migración.</strong><br>";
    }
} catch (PDOException $e) {
    echo "<strong style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
}

// 2. Verificar datos actuales
echo "<h3>2. Datos actuales de la rúbrica ID $rubrica_id:</h3>";
try {
    $stmt = $pdo->prepare("SELECT id, nombre, escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
    $stmt->execute([$rubrica_id]);
    $rubrica = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rubrica) {
        echo "ID: " . $rubrica['id'] . "<br>";
        echo "Nombre: " . htmlspecialchars($rubrica['nombre']) . "<br>";
        echo "escala_personalizada: " . var_export($rubrica['escala_personalizada'] ?? 'NULL', true) . "<br>";
        echo "escala_notas: " . htmlspecialchars(substr($rubrica['escala_notas'] ?? 'NULL', 0, 200)) . "<br>";
    } else {
        echo "<strong style='color:red;'>La rúbrica no existe</strong><br>";
    }
} catch (PDOException $e) {
    echo "<strong style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
}

// 3. Intentar guardar datos de prueba
if (isset($_GET['test_save'])) {
    echo "<h3>3. Intentar guardar datos de prueba:</h3>";
    
    $test_data = [
        'puntaje_maximo' => 35.0,
        'exigencia' => 60.0,
        'nota_minima' => 1.0,
        'nota_maxima' => 7.0,
        'nota_aprobacion' => 4.0
    ];
    
    $test_json = json_encode($test_data, JSON_UNESCAPED_UNICODE);
    echo "JSON a guardar: " . htmlspecialchars($test_json) . "<br>";
    
    try {
        $stmt = $pdo->prepare("UPDATE rubricas SET escala_personalizada = 1, escala_notas = ? WHERE id = ?");
        $resultado = $stmt->execute([$test_json, $rubrica_id]);
        
        echo "Resultado execute: " . ($resultado ? "TRUE" : "FALSE") . "<br>";
        echo "Filas afectadas: " . $stmt->rowCount() . "<br>";
        
        if ($resultado) {
            // Verificar
            $stmt_check = $pdo->prepare("SELECT escala_personalizada, escala_notas FROM rubricas WHERE id = ?");
            $stmt_check->execute([$rubrica_id]);
            $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            echo "<br>Después de guardar:<br>";
            echo "escala_personalizada: " . var_export($check['escala_personalizada'], true) . "<br>";
            echo "escala_notas: " . htmlspecialchars($check['escala_notas'] ?? 'NULL') . "<br>";
            
            if ($check['escala_personalizada'] == 1 && !empty($check['escala_notas'])) {
                echo "<strong style='color:green;'>✓ Guardado exitoso</strong><br>";
            } else {
                echo "<strong style='color:red;'>✗ No se guardó correctamente</strong><br>";
            }
        }
    } catch (PDOException $e) {
        echo "<strong style='color:red;'>Error al guardar: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
        echo "Código de error: " . $e->getCode() . "<br>";
    }
}

echo "<hr>";
echo "<a href='?id=$rubrica_id&test_save=1'>Probar guardado</a> | ";
echo "<a href='rubrica_ver.php?id=$rubrica_id'>Ver rúbrica</a> | ";
echo "<a href='rubrica_configurar_escala.php?id=$rubrica_id'>Configurar escala</a>";
?>


