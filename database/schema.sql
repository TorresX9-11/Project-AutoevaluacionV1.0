-- Base de datos para Plataforma de Autoevaluación TEC-UCT
CREATE DATABASE IF NOT EXISTS autoeval_tecuct CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE autoeval_tecuct;

-- Tabla de carreras
CREATE TABLE IF NOT EXISTS carreras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de usuarios (estudiantes y docentes)
-- Se crea antes de asignaturas porque asignaturas tiene FK a usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    apellido VARCHAR(255) NOT NULL,
    rut VARCHAR(20),
    tipo ENUM('estudiante', 'docente', 'admin', 'admin_supremo') NOT NULL,
    carrera_id INT,
    activo BOOLEAN DEFAULT TRUE,
    primera_vez BOOLEAN DEFAULT TRUE,
    token_recuperacion VARCHAR(255) NULL,
    token_expiracion DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de relación docentes-carreras (muchos a muchos)
CREATE TABLE IF NOT EXISTS docentes_carreras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    carrera_id INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE CASCADE,
    UNIQUE KEY unique_docente_carrera (docente_id, carrera_id),
    INDEX idx_docente (docente_id),
    INDEX idx_carrera (carrera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de asignaturas
CREATE TABLE IF NOT EXISTS asignaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrera_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    docente_id INT NOT NULL,
    semestre VARCHAR(20),
    anio INT,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE CASCADE,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_asignatura (carrera_id, codigo, docente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de estudiantes por asignatura
CREATE TABLE IF NOT EXISTS estudiantes_asignaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    asignatura_id INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_estudiante_asignatura (estudiante_id, asignatura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de rúbricas
CREATE TABLE IF NOT EXISTS rubricas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asignatura_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de criterios de evaluación
CREATE TABLE IF NOT EXISTS criterios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrica_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    peso DECIMAL(5,2) DEFAULT 0.00,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rubrica_id) REFERENCES rubricas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de niveles de evaluación
CREATE TABLE IF NOT EXISTS niveles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criterio_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    puntaje DECIMAL(5,2) NOT NULL,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criterio_id) REFERENCES criterios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de autoevaluaciones
CREATE TABLE IF NOT EXISTS autoevaluaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    asignatura_id INT NOT NULL,
    rubrica_id INT NOT NULL,
    estado ENUM('en_proceso', 'completada', 'incompleta', 'pausada') DEFAULT 'en_proceso',
    nota_autoevaluada DECIMAL(5,2) NULL,
    nota_ajustada DECIMAL(5,2) NULL,
    nota_final DECIMAL(5,2) NULL,
    comentario_estudiante TEXT,
    comentario_docente TEXT,
    tiempo_inicio DATETIME,
    tiempo_fin DATETIME,
    tiempo_pausa DATETIME NULL,
    tiempo_restante INT DEFAULT 300,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (rubrica_id) REFERENCES rubricas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de respuestas de autoevaluación
CREATE TABLE IF NOT EXISTS respuestas_autoevaluacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    autoevaluacion_id INT NOT NULL,
    criterio_id INT NOT NULL,
    nivel_id INT NOT NULL,
    puntaje DECIMAL(5,2) NOT NULL,
    comentario TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (autoevaluacion_id) REFERENCES autoevaluaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (criterio_id) REFERENCES criterios(id) ON DELETE CASCADE,
    FOREIGN KEY (nivel_id) REFERENCES niveles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_respuesta (autoevaluacion_id, criterio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de escalas de notas
CREATE TABLE IF NOT EXISTS escalas_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asignatura_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    nota_minima DECIMAL(5,2) NOT NULL,
    nota_maxima DECIMAL(5,2) NOT NULL,
    nota_aprobacion DECIMAL(5,2) NOT NULL,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de sesiones
CREATE TABLE IF NOT EXISTS sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    activa BOOLEAN DEFAULT TRUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de notificaciones
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    enlace VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_leida (usuario_id, leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar usuario admin_supremo por defecto (password: admin123 - debe cambiarse)
-- Hash generado para la contraseña "admin123"
-- Este es el usuario principal del sistema
INSERT INTO usuarios (email, password, nombre, apellido, tipo, primera_vez) VALUES 
('admin_supremo@uct.cl', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Admin', 'Supremo', 'admin_supremo', FALSE);

-- Insertar carreras de Técnico Universitario
INSERT INTO carreras (nombre, codigo, activa) VALUES 
('Técnico Universitario en Educación Parvularia y Nivel Básico 1', 'TUP001', TRUE),
('Técnico Universitario en Electricidad y Eficiencia Energética', 'TUE001', TRUE),
('Técnico Universitario en Gestión y Administración de Empresas', 'TUG001', TRUE),
('Técnico Universitario en Informática', 'TUI001', TRUE),
('Técnico Universitario en Producción Agropecuaria Sostenible', 'TUA001', TRUE),
('Técnico Universitario en Educación Diferencial', 'TUD001', TRUE);

-- Insertar asignaturas para cada carrera
-- NOTA: Las asignaturas se crean con docente_id = 1 (admin_supremo)
-- El admin_supremo deberá asignar docentes reales después

-- Técnico Universitario en Educación Parvularia y Nivel Básico 1
SET @carrera_id = (SELECT id FROM carreras WHERE codigo = 'TUP001');
INSERT INTO asignaturas (carrera_id, nombre, codigo, docente_id, semestre, anio, activa) VALUES
(@carrera_id, 'Desarrollo Infantil y Psicología del Aprendizaje', 'TUP001-001', 1, '1', 2024, TRUE),
(@carrera_id, 'Didáctica de la Educación Parvularia', 'TUP001-002', 1, '1', 2024, TRUE),
(@carrera_id, 'Lenguaje y Comunicación en Educación Inicial', 'TUP001-003', 1, '1', 2024, TRUE);

-- Técnico Universitario en Electricidad y Eficiencia Energética
SET @carrera_id = (SELECT id FROM carreras WHERE codigo = 'TUE001');
INSERT INTO asignaturas (carrera_id, nombre, codigo, docente_id, semestre, anio, activa) VALUES
(@carrera_id, 'Fundamentos de Electricidad', 'TUE001-001', 1, '1', 2024, TRUE),
(@carrera_id, 'Instalaciones Eléctricas Residenciales', 'TUE001-002', 1, '1', 2024, TRUE),
(@carrera_id, 'Eficiencia Energética y Energías Renovables', 'TUE001-003', 1, '1', 2024, TRUE);

-- Técnico Universitario en Gestión y Administración de Empresas
SET @carrera_id = (SELECT id FROM carreras WHERE codigo = 'TUG001');
INSERT INTO asignaturas (carrera_id, nombre, codigo, docente_id, semestre, anio, activa) VALUES
(@carrera_id, 'Fundamentos de Administración', 'TUG001-001', 1, '1', 2024, TRUE),
(@carrera_id, 'Contabilidad Básica', 'TUG001-002', 1, '1', 2024, TRUE),
(@carrera_id, 'Gestión de Recursos Humanos', 'TUG001-003', 1, '1', 2024, TRUE);

-- Técnico Universitario en Informática
SET @carrera_id = (SELECT id FROM carreras WHERE codigo = 'TUI001');
INSERT INTO asignaturas (carrera_id, nombre, codigo, docente_id, semestre, anio, activa) VALUES
(@carrera_id, 'Programación I', 'TUI001-001', 1, '1', 2024, TRUE),
(@carrera_id, 'Bases de Datos', 'TUI001-002', 1, '1', 2024, TRUE),
(@carrera_id, 'Redes de Computadores', 'TUI001-003', 1, '1', 2024, TRUE);

-- Técnico Universitario en Producción Agropecuaria Sostenible
SET @carrera_id = (SELECT id FROM carreras WHERE codigo = 'TUA001');
INSERT INTO asignaturas (carrera_id, nombre, codigo, docente_id, semestre, anio, activa) VALUES
(@carrera_id, 'Fundamentos de Producción Agropecuaria', 'TUA001-001', 1, '1', 2024, TRUE),
(@carrera_id, 'Sustentabilidad en la Agricultura', 'TUA001-002', 1, '1', 2024, TRUE),
(@carrera_id, 'Manejo de Suelos y Cultivos', 'TUA001-003', 1, '1', 2024, TRUE);

-- Técnico Universitario en Educación Diferencial
SET @carrera_id = (SELECT id FROM carreras WHERE codigo = 'TUD001');
INSERT INTO asignaturas (carrera_id, nombre, codigo, docente_id, semestre, anio, activa) VALUES
(@carrera_id, 'Fundamentos de la Educación Diferencial', 'TUD001-001', 1, '1', 2024, TRUE),
(@carrera_id, 'Necesidades Educativas Especiales', 'TUD001-002', 1, '1', 2024, TRUE),
(@carrera_id, 'Estrategias de Inclusión Educativa', 'TUD001-003', 1, '1', 2024, TRUE);

