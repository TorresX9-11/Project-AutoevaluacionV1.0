-- Migración: Agregar campos para escala de notas personalizada en rúbricas
-- Ejecutar este script si ya tiene una base de datos existente

USE autoeval_tecuct;

-- Agregar campos para almacenar la escala de notas personalizada
-- Nota: Si las columnas ya existen, estos comandos fallarán. Ejecutar solo si es necesario.

-- Verificar y agregar columna escala_personalizada
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = 'autoeval_tecuct' 
               AND TABLE_NAME = 'rubricas' 
               AND COLUMN_NAME = 'escala_personalizada');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE rubricas ADD COLUMN escala_personalizada BOOLEAN DEFAULT FALSE', 
    'SELECT "Columna escala_personalizada ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar columna escala_notas
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = 'autoeval_tecuct' 
               AND TABLE_NAME = 'rubricas' 
               AND COLUMN_NAME = 'escala_notas');
SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE rubricas ADD COLUMN escala_notas JSON NULL COMMENT "Escala de notas personalizada en formato JSON"', 
    'SELECT "Columna escala_notas ya existe" AS mensaje');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Si su versión de MySQL no soporta JSON (MySQL < 5.7), usar TEXT en su lugar:
-- ALTER TABLE rubricas ADD COLUMN escala_notas TEXT NULL;

