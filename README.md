# Plataforma de Autoevaluación TEC-UCT

Sistema de autoevaluación estudiantil con validación docente para el Instituto Tecnológico TEC-UCT.

## Requisitos del Sistema

- **Servidor Web**: Apache (incluido en XAMPP)
- **PHP**: Versión 7.4 o superior
- **Base de Datos**: MySQL 5.7 o superior (incluido en XAMPP)
- **Extensiones PHP requeridas**:
  - PDO
  - PDO_MySQL
  - mbstring
  - openssl
  - mail (para envío de correos)

## Instalación en Local (XAMPP)

### Paso 1: Instalar XAMPP

1. Descargar XAMPP desde [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Instalar XAMPP en `C:\xampp` (o la ruta de su preferencia)
3. Iniciar los servicios Apache y MySQL desde el Panel de Control de XAMPP

### Paso 2: Clonar/Descargar el Proyecto

1. Copiar la carpeta del proyecto `autoeval` a la carpeta `htdocs` de XAMPP:
   ```
   C:\xampp\htdocs\autoeval
   ```

### Paso 3: Instalar Dependencias con Composer

```bash
cd C:\xampp\htdocs\autoeval
composer install
```

Si no tiene Composer instalado, descargarlo desde [https://getcomposer.org/](https://getcomposer.org/)

**Dependencias instaladas:**
- `phpmailer/phpmailer`: Para envío de correos
- `smalot/pdfparser`: Para importar rúbricas desde PDF
- `tecnickcom/tcpdf`: Para exportar reportes a PDF

### Paso 4: Configurar Archivos de Configuración

1. **Copiar archivos de ejemplo:**
   ```bash
   # Si usa Git Bash o PowerShell
   cp config/config.example.php config/config.php
   cp config/database.example.php config/database.php
   ```

   O manualmente:
   - Copiar `config/config.example.php` a `config/config.php`
   - Copiar `config/database.example.php` a `config/database.php`

2. **Editar `config/database.php`:**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Dejar vacío si no hay contraseña en XAMPP
   define('DB_NAME', 'autoeval_tecuct');
   ```

3. **Editar `config/config.php`:**
   ```php
   define('BASE_URL', 'http://localhost/autoeval/');
   define('SMTP_USER', 'tu_correo@gmail.com');
   define('SMTP_PASS', 'tu_contraseña_de_aplicacion');
   ```

### Paso 5: Configurar la Base de Datos

1. Abrir phpMyAdmin: `http://localhost/phpmyadmin`
2. Crear una nueva base de datos llamada `autoeval_tecuct` (o importar el archivo `database/schema.sql`)
3. Importar el archivo SQL:
   - En phpMyAdmin, seleccionar la base de datos `autoeval_tecuct`
   - Ir a la pestaña "Importar"
   - Seleccionar el archivo `database/schema.sql`
   - Hacer clic en "Continuar"

   **Nota**: Si ya tiene una base de datos existente, puede eliminar todas las tablas y ejecutar `schema.sql` nuevamente

### Paso 6: Crear Carpetas Necesarias

Crear las siguientes carpetas si no existen:
- `assets/img/` (para el logo y favicon)
- `uploads/` (para archivos CSV subidos)

### Paso 7: Colocar el Logo

1. Descargar el logo de TEC-UCT desde [https://tec.uct.cl/](https://tec.uct.cl/)
2. Colocar el logo en `assets/img/logo-uct.png`
3. Colocar el favicon en `assets/img/favicon.png` (opcional)

### Paso 8: Acceder a la Plataforma

1. Abrir el navegador y acceder a: `http://localhost/autoeval/`
2. Iniciar sesión con el usuario administrador supremo por defecto:
   - **Email**: `admin_supremo@uct.cl`
   - **Contraseña**: `admin123` (cambiar inmediatamente después del primer acceso)

## Estructura del Proyecto

```
autoeval/
├── admin/              # Módulos de administración (docente/admin)
├── admin_supremo/      # Módulos de administración suprema
├── estudiante/         # Módulos para estudiantes
├── ajax/               # Endpoints AJAX
├── assets/             # Recursos estáticos (CSS, JS, imágenes)
├── config/             # Archivos de configuración
│   ├── config.php      # Configuración principal (crear desde .example)
│   ├── database.php    # Configuración de BD (crear desde .example)
│   └── email.php       # Funciones de correo
├── database/           # Scripts de base de datos
│   ├── schema.sql      # Esquema completo de la base de datos
│   └── README.md       # Documentación de la base de datos
├── includes/           # Archivos incluidos (header, footer, notificaciones)
├── install/            # Scripts de instalación
├── logs/               # Archivos de log
├── uploads/            # Archivos subidos (CSV, PDF)
├── vendor/             # Dependencias de Composer
├── .htaccess           # Configuración de Apache
├── .gitignore          # Archivos ignorados por Git
├── composer.json       # Dependencias del proyecto
└── README.md           # Este archivo
```

**Nota**: Para más detalles sobre la instalación, consulte `INSTALACION.md`

## Funcionalidades Principales

### Para Estudiantes
- Autoevaluación con contador de 5 minutos
- Historial de autoevaluaciones
- Visualización de notas
- Pausar/reanudar autoevaluación

### Para Docentes/Administradores
- Gestión de rúbricas (CRUD)
- Importar rúbricas desde CSV o PDF
- Carga masiva de estudiantes (CSV)
- Gestión de asignaturas y carreras
- Validación y ajuste de notas
- Pausar/reiniciar tiempo de autoevaluación
- Reiniciar autoevaluaciones incompletas
- Generación de reportes (CSV, PDF)
- Exportar rúbricas

### Para Administrador Supremo
- Gestión completa de carreras (CRUD)
- Gestión completa de asignaturas (CRUD)
- Gestión completa de docentes (CRUD)
- Asignar docentes a múltiples carreras
- Asignar estudiantes a asignaturas
- Carga masiva de estudiantes y docentes
- **No puede** ver rúbricas ni autoevaluaciones (por seguridad)

## Seguridad

- Validación de correos institucionales (@alu.uct.cl para estudiantes, @uct.cl para docentes)
- Manejo de sesiones seguras
- Sanitización de entradas
- Protección contra SQL Injection (usando PDO con prepared statements)
- Tokens de recuperación de contraseña con expiración

## Correos Institucionales

- **Estudiantes**: Deben usar correos con dominio `@alu.uct.cl`
- **Docentes/Administradores**: Deben usar correos con dominio `@uct.cl`

## Solución de Problemas

### Error de conexión a la base de datos
- Verificar que MySQL esté corriendo en XAMPP
- Verificar las credenciales en `config/database.php`
- Verificar que la base de datos `autoeval_tecuct` exista

### Error al enviar correos
- Verificar la configuración SMTP en `config/config.php`
- Para Gmail, usar "Contraseña de aplicación" en lugar de la contraseña normal
- Verificar que la extensión `openssl` esté habilitada en PHP

### Error 404 en las páginas
- Verificar que el archivo `.htaccess` esté configurado correctamente
- Verificar que `mod_rewrite` esté habilitado en Apache
- Verificar la constante `BASE_URL` en `config/config.php`

### Problemas con el contador de tiempo
- Verificar que JavaScript esté habilitado en el navegador
- Verificar la consola del navegador para errores

## Soporte

Para más información o soporte, contactar al equipo de desarrollo.

## Licencia

Este proyecto es propiedad del Instituto Tecnológico TEC-UCT.

---

**Desarrollado para TEC-UCT - Universidad Católica de Temuco**

