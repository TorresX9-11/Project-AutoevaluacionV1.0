# Archivos de Base de Datos

Este directorio contiene el script SQL necesario para configurar la base de datos.

## Instalación

### Para una **nueva instalación**:
Use **`schema.sql`**

Este archivo contiene:
- Creación de la base de datos `autoeval_tecuct`
- Creación de todas las tablas necesarias
- Inserción de datos iniciales (usuario admin_supremo, carreras de ejemplo)

**Cómo usar:**
1. Abrir phpMyAdmin: `http://localhost/phpmyadmin`
2. Ir a la pestaña "Importar"
3. Seleccionar el archivo `database/schema.sql`
4. Hacer clic en "Continuar"

**O desde línea de comandos:**
```bash
mysql -u root -p < database/schema.sql
```

**Nota**: Si ya tiene una base de datos existente, puede eliminar todas las tablas y ejecutar `schema.sql` nuevamente, o ejecutar solo las partes necesarias del script.

## Estructura de la Base de Datos

### Tablas Principales:
- `usuarios`: Usuarios del sistema (estudiantes, docentes, admins)
- `carreras`: Carreras académicas
- `docentes_carreras`: Relación muchos a muchos entre docentes y carreras
- `asignaturas`: Asignaturas por carrera
- `estudiantes_asignaturas`: Relación muchos a muchos entre estudiantes y asignaturas
- `rubricas`: Rúbricas de evaluación
- `criterios`: Criterios de evaluación dentro de una rúbrica
- `niveles`: Niveles de desempeño para cada criterio
- `autoevaluaciones`: Autoevaluaciones realizadas por estudiantes
- `respuestas_autoevaluacion`: Respuestas de los estudiantes en cada autoevaluación
- `notificaciones`: Notificaciones del sistema para usuarios
- `sesiones`: Sesiones activas de usuarios (opcional)

### Tipos de Usuario:
- `estudiante`: Puede realizar autoevaluaciones
- `docente`: Puede gestionar asignaturas, rúbricas y validar autoevaluaciones
- `admin`: Mismo que docente (mantenido por compatibilidad)
- `admin_supremo`: Puede gestionar carreras, asignaturas, docentes y estudiantes (no puede ver rúbricas ni autoevaluaciones)

## Usuario por Defecto

Después de ejecutar `schema.sql`, se crea el usuario principal del sistema:

- **Email**: `admin_supremo@uct.cl`
- **Contraseña**: `admin123`
- **Tipo**: `admin_supremo`
- **IMPORTANTE**: Cambiar la contraseña después del primer acceso

Este es el usuario principal que tiene acceso completo a todas las funcionalidades de administración (carreras, asignaturas, docentes, estudiantes), pero **no puede** ver rúbricas ni autoevaluaciones por seguridad.

## Solución de Problemas

### Error: "Table already exists"
- Elimine todas las tablas existentes antes de ejecutar `schema.sql`
- O ejecute solo las partes del script que necesite (las tablas usan `IF NOT EXISTS`)

### Error: "Unknown column 'tipo' in 'field list'"
- Asegúrese de ejecutar `schema.sql` completo para crear todas las tablas con la estructura correcta

### Error: "Table 'docentes_carreras' doesn't exist"
- Ejecute `schema.sql` completo para crear todas las tablas necesarias

---

**Desarrollado para TEC-UCT - Universidad Católica de Temuco**

