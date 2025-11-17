<?php
require_once '../config/config.php';
validarTipoUsuario(['docente', 'admin']);

$titulo = 'Importar Rúbrica';
include '../includes/header.php';

$pdo = getDBConnection();
$docente_id = $_SESSION['usuario_id'];
$asignatura_id = $_GET['asignatura_id'] ?? null;

$error = '';
$mensaje = '';

// Validar asignatura
if ($asignatura_id) {
    $stmt = $pdo->prepare("SELECT * FROM asignaturas WHERE id = ? AND docente_id = ?");
    $stmt->execute([$asignatura_id, $docente_id]);
    $asignatura = $stmt->fetch();
    
    if (!$asignatura) {
        header('Location: ' . BASE_URL . 'admin/rubricas.php');
        exit();
    }
}

// Procesar importación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo'];
    $asignatura_id = $_POST['asignatura_id'] ?? null;
    $tipo_archivo = $_POST['tipo_archivo'] ?? 'csv';
    
    if ($archivo['error'] === UPLOAD_ERR_OK && $asignatura_id) {
        $tmp_name = $archivo['tmp_name'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        try {
            $pdo->beginTransaction();
            
            if ($tipo_archivo === 'pdf' || $extension === 'pdf') {
                // Importar desde PDF
                // Nota: Requiere instalar la librería smalot/pdfparser
                // Composer: composer require smalot/pdfparser
                
                if (!class_exists('Smalot\PdfParser\Parser')) {
                    // Intentar incluir manualmente si no está disponible
                    $pdfparser_path = __DIR__ . '/../vendor/autoload.php';
                    if (file_exists($pdfparser_path)) {
                        require_once $pdfparser_path;
                    } else {
                        throw new Exception('Librería PDF Parser no encontrada. Por favor, instale smalot/pdfparser usando Composer.');
                    }
                }
                
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($tmp_name);
                $text = $pdf->getText();
                
                // Extraer información del PDF
                // Buscar nombre de rúbrica (generalmente después de "Evaluación" o similar)
                $nombre_rubrica = 'Rúbrica Importada';
                if (preg_match('/Evaluación\s*N°?\s*\d+\s*[-–]\s*([^\n]+)/i', $text, $matches)) {
                    $nombre_rubrica = trim($matches[1]);
                } elseif (preg_match('/Rúbrica[:\s]+([^\n]+)/i', $text, $matches)) {
                    $nombre_rubrica = trim($matches[1]);
                }
                
                $descripcion_rubrica = '';
                
                // Crear rúbrica
                $stmt = $pdo->prepare("INSERT INTO rubricas (asignatura_id, nombre, descripcion) VALUES (?, ?, ?)");
                $stmt->execute([$asignatura_id, $nombre_rubrica, $descripcion_rubrica]);
                $rubrica_id = $pdo->lastInsertId();
                
                // Extraer criterios y niveles del PDF
                // Buscar patrones de criterios y niveles
                $criterio_actual = null;
                $orden_criterio = 0;
                
                // Buscar sección de criterios (tabla principal)
                if (preg_match_all('/([A-ZÁÉÍÓÚÑ][^:]+):\s*([^\n]+)/u', $text, $matches_criterios, PREG_SET_ORDER)) {
                    foreach ($matches_criterios as $match) {
                        $linea = trim($match[0]);
                        // Detectar si es un criterio principal
                        if (preg_match('/^(Presentación|Calidad|Proceso|Interacción)/i', $linea)) {
                            $orden_criterio++;
                            $nombre_criterio = trim($match[1]);
                            $descripcion_criterio = trim($match[2]);
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO criterios (rubrica_id, nombre, descripcion, peso, orden) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$rubrica_id, $nombre_criterio, $descripcion_criterio, 0, $orden_criterio]);
                            $criterio_id = $pdo->lastInsertId();
                            $criterio_actual = $criterio_id;
                        }
                    }
                }
                
                // Si no se encontraron criterios con el patrón anterior, intentar otro método
                if (!$criterio_actual) {
                    // Buscar niveles de desempeño (Excelente, Satisfactorio, Básico, Insuficiente)
                    $niveles_nombres = ['Excelente', 'Satisfactorio', 'Básico', 'Insuficiente'];
                    $niveles_puntajes = [4, 3, 2, 1];
                    
                    // Crear un criterio por defecto
                    $orden_criterio++;
                    $stmt = $pdo->prepare("
                        INSERT INTO criterios (rubrica_id, nombre, descripcion, peso, orden) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$rubrica_id, 'Criterio Principal', $nombre_rubrica, 0, $orden_criterio]);
                    $criterio_id = $pdo->lastInsertId();
                    
                    // Crear niveles
                    foreach ($niveles_nombres as $index => $nivel_nombre) {
                        $stmt = $pdo->prepare("
                            INSERT INTO niveles (criterio_id, nombre, descripcion, puntaje, orden) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$criterio_id, $nivel_nombre, '', $niveles_puntajes[$index], $index + 1]);
                    }
                }
                
                $pdo->commit();
                $mensaje = 'Rúbrica importada desde PDF exitosamente. Por favor, revise y ajuste los criterios y niveles.';
                header('refresh:2;url=' . BASE_URL . 'admin/rubrica_editar.php?id=' . $rubrica_id);
            } else {
                // Importar desde CSV (código original)
                $handle = fopen($tmp_name, 'r');
                
                if ($handle !== false) {
                    // Leer primera línea (encabezados)
                    $encabezados = fgetcsv($handle, 1000, ',');
                    
                    // Leer nombre de rúbrica
                    $linea_rubrica = fgetcsv($handle, 1000, ',');
                    $nombre_rubrica = $linea_rubrica[0] ?? 'Rúbrica Importada';
                    $descripcion_rubrica = $linea_rubrica[1] ?? '';
                    
                    // Crear rúbrica
                    $stmt = $pdo->prepare("INSERT INTO rubricas (asignatura_id, nombre, descripcion) VALUES (?, ?, ?)");
                    $stmt->execute([$asignatura_id, $nombre_rubrica, $descripcion_rubrica]);
                    $rubrica_id = $pdo->lastInsertId();
                    
                    // Leer criterios
                    $criterio_actual = null;
                    $orden_criterio = 0;
                    
                    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                        if (count($data) >= 4) {
                            $tipo = trim($data[0]);
                            $nombre = trim($data[1]);
                            $descripcion = trim($data[2]);
                            $valor = trim($data[3]);
                            
                            if ($tipo === 'Criterio' && !empty($nombre)) {
                                // Crear criterio
                                $peso = isset($data[4]) ? floatval($data[4]) : 0;
                                $orden_criterio++;
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO criterios (rubrica_id, nombre, descripcion, peso, orden) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$rubrica_id, $nombre, $descripcion, $peso, $orden_criterio]);
                                $criterio_id = $pdo->lastInsertId();
                                $criterio_actual = $criterio_id;
                            } elseif ($tipo === 'Nivel' && $criterio_actual && !empty($nombre)) {
                                // Crear nivel
                                $puntaje = floatval($valor);
                                $orden_nivel = isset($data[4]) ? intval($data[4]) : 0;
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO niveles (criterio_id, nombre, descripcion, puntaje, orden) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$criterio_actual, $nombre, $descripcion, $puntaje, $orden_nivel]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    fclose($handle);
                    $mensaje = 'Rúbrica importada exitosamente';
                    header('refresh:2;url=' . BASE_URL . 'admin/rubrica_ver.php?id=' . $rubrica_id);
                } else {
                    throw new Exception('Error al leer el archivo CSV');
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error al importar rúbrica: " . $e->getMessage());
            $error = 'Error al importar la rúbrica: ' . $e->getMessage();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al importar rúbrica: " . $e->getMessage());
            $error = 'Error al importar la rúbrica. Verifique el formato del archivo.';
        }
    } else {
        $error = 'Error al subir el archivo o asignatura no seleccionada';
    }
}

// Obtener asignaturas
$stmt = $pdo->prepare("
    SELECT a.*, c.nombre as carrera_nombre 
    FROM asignaturas a
    JOIN carreras c ON a.carrera_id = c.id
    WHERE a.docente_id = ? AND a.activa = 1
    ORDER BY c.nombre, a.nombre
");
$stmt->execute([$docente_id]);
$asignaturas = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Importar Rúbrica</h2>
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
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Subir Archivo</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="asignatura_id" class="form-label">Asignatura <span class="text-danger">*</span></label>
                        <select class="form-select" id="asignatura_id" name="asignatura_id" required>
                            <option value="">-- Seleccione una asignatura --</option>
                            <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id']; ?>" 
                                        <?php echo ($asignatura_id == $asig['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asig['carrera_nombre'] . ' - ' . $asig['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo_archivo" class="form-label">Tipo de Archivo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo_archivo" name="tipo_archivo" required onchange="actualizarAceptacionArchivo()">
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="archivo" class="form-label">Archivo <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="archivo" name="archivo" 
                               accept=".csv,.pdf" required>
                        <small class="text-muted" id="ayuda_archivo">
                            <strong>Formato CSV esperado:</strong><br>
                            - Primera línea: Nombre de rúbrica, Descripción<br>
                            - Líneas siguientes: Tipo (Criterio/Nivel), Nombre, Descripción, Valor, Peso/Orden
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importar Rúbrica
                    </button>
                    <a href="<?php echo BASE_URL; ?>admin/rubricas.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Formato del Archivo</h5>
            </div>
            <div class="card-body">
                <div id="ayuda_csv">
                    <p>El archivo CSV debe tener el siguiente formato:</p>
                    <pre class="bg-light p-3">
Nombre Rúbrica,Descripción
Criterio,Nombre Criterio 1,Descripción,0,10
Nivel,Nombre Nivel 1,Descripción,7.0,1
Nivel,Nombre Nivel 2,Descripción,5.0,2
Criterio,Nombre Criterio 2,Descripción,0,20
Nivel,Nombre Nivel 1,Descripción,7.0,1
                    </pre>
                    <p class="small text-muted">
                        <strong>Columnas:</strong><br>
                        1. Tipo (Criterio/Nivel)<br>
                        2. Nombre<br>
                        3. Descripción<br>
                        4. Valor (Puntaje para niveles, 0 para criterios)<br>
                        5. Peso (para criterios) u Orden (para niveles)
                    </p>
                </div>
                <div id="ayuda_pdf" style="display: none;">
                    <p>El archivo PDF debe contener una rúbrica de evaluación con el siguiente formato:</p>
                    <ul class="small text-muted">
                        <li>Título de evaluación (ej: "Evaluación N°2 - Demo Day")</li>
                        <li>Tabla con criterios y sub-criterios</li>
                        <li>Niveles de desempeño (Excelente, Satisfactorio, Básico, Insuficiente)</li>
                        <li>Puntajes asociados a cada nivel</li>
                    </ul>
                    <p class="small text-warning">
                        <strong>Nota:</strong> La importación desde PDF requiere la librería <code>smalot/pdfparser</code>.<br>
                        Si no está instalada, puede instalarla con: <code>composer require smalot/pdfparser</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function actualizarAceptacionArchivo() {
        const tipoArchivo = document.getElementById('tipo_archivo').value;
        const archivoInput = document.getElementById('archivo');
        const ayudaArchivo = document.getElementById('ayuda_archivo');
        const ayudaCsv = document.getElementById('ayuda_csv');
        const ayudaPdf = document.getElementById('ayuda_pdf');
        
        if (tipoArchivo === 'pdf') {
            archivoInput.setAttribute('accept', '.pdf');
            ayudaArchivo.innerHTML = '<strong>Formato PDF:</strong><br>Seleccione un archivo PDF con una rúbrica de evaluación. El sistema intentará extraer automáticamente los criterios y niveles.';
            ayudaCsv.style.display = 'none';
            ayudaPdf.style.display = 'block';
        } else {
            archivoInput.setAttribute('accept', '.csv');
            ayudaArchivo.innerHTML = '<strong>Formato CSV esperado:</strong><br>- Primera línea: Nombre de rúbrica, Descripción<br>- Líneas siguientes: Tipo (Criterio/Nivel), Nombre, Descripción, Valor, Peso/Orden';
            ayudaCsv.style.display = 'block';
            ayudaPdf.style.display = 'none';
        }
    }
    </script>
</div>

<?php include '../includes/footer.php'; ?>

