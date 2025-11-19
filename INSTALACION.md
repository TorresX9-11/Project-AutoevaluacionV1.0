# Guía de Instalación - Plataforma de Autoevaluación TEC-UCT

## Requisitos del Sistema

- **Servidor Web**: Apache 2.4+ (incluido en XAMPP)
- **PHP**: Versión 7.4 o superior (recomendado PHP 8.0+)
- **Base de Datos**: MySQL 5.7+ o MariaDB 10.3+ (incluido en XAMPP)
- **Extensiones PHP requeridas**:
  - PDO
  - PDO_MySQL
  - mbstring
  - openssl
  - zip (para importar PDFs)
  - gd (opcional, para imágenes)

## Instalación Paso a Paso

### Paso 1: Instalar XAMPP (Windows) o Configurar Servidor (Linux)

#### Windows:
1. Descargar XAMPP desde [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Instalar XAMPP en `C:\xampp` (o la ruta de su preferencia)
3. Iniciar los servicios Apache y MySQL desde el Panel de Control de XAMPP

#### Linux:
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install apache2 mysql-server php php-mysql php-mbstring php-xml php-zip

# CentOS/RHEL
sudo yum install httpd mysql-server php php-mysql php-mbstring php-xml php-zip
```

### Paso 2: Clonar/Descargar el Proyecto

```bash
# Si usa Git
git clone <url-del-repositorio> autoeval
cd autoeval

# O descargar y extraer el ZIP en la carpeta htdocs
```

**Ubicación del proyecto:**
- Windows (XAMPP): `C:\xampp\htdocs\autoeval`
- Linux: `/var/www/html/autoeval` o según su configuración

### Paso 3: Instalar Dependencias con Composer

```bash
cd autoeval
composer install
```

Si no tiene Composer instalado:
- **Windows**: Descargar desde [https://getcomposer.org/](https://getcomposer.org/)
- **Linux**: `sudo apt-get install composer` o `sudo yum install composer`

**Dependencias instaladas:**
- `phpmailer/phpmailer`: Para envío de correos
- `smalot/pdfparser`: Para importar rúbricas desde PDF
- `tecnickcom/tcpdf`: Para exportar reportes a PDF

### Paso 4: Configurar la Base de Datos

1. **Crear la base de datos:**
   - Abrir phpMyAdmin: `http://localhost/phpmyadmin`
   - Crear una nueva base de datos llamada `autoeval_tecuct`
   - O ejecutar el script SQL directamente

2. **Importar el esquema:**
   - En phpMyAdmin, ir a la pestaña "Importar"
   - Seleccionar el archivo `database/schema.sql`
   - Hacer clic en "Continuar"

   **O desde línea de comandos:**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

   **Nota**: Si ya tiene una base de datos existente, puede eliminar todas las tablas y ejecutar `schema.sql` nuevamente

### Paso 5: Configurar Archivos de Configuración

1. **Copiar archivos de ejemplo:**
   ```bash
   cp config/config.example.php config/config.php
   cp config/database.example.php config/database.php
   ```

2. **Editar `config/database.php`:**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Su contraseña de MySQL
   define('DB_NAME', 'autoeval_tecuct');
   ```

3. **Editar `config/config.php`:**
   ```php
   // Cambiar según su entorno
   define('BASE_URL', 'http://localhost/autoeval/');
   
   // Configuración de correo (Gmail)
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'tu_correo@gmail.com');
   define('SMTP_PASS', 'tu_contraseña_de_aplicacion');
   ```

   **Nota para Gmail:**
   - Debe generar una "Contraseña de aplicación" desde la configuración de seguridad de su cuenta
   - No use su contraseña normal de Gmail

### Paso 6: Crear Carpetas Necesarias

```bash
# Crear carpeta de logs
mkdir -p logs
chmod 755 logs

# Crear carpeta de uploads (si no existe)
mkdir -p uploads
chmod 755 uploads
touch uploads/.gitkeep
```

### Paso 7: Configurar Permisos (Linux)

```bash
# Dar permisos al servidor web
sudo chown -R www-data:www-data /var/www/html/autoeval
sudo chmod -R 755 /var/www/html/autoeval

# Dar permisos de escritura a carpetas específicas
sudo chmod -R 775 /var/www/html/autoeval/logs
sudo chmod -R 775 /var/www/html/autoeval/uploads
```

### Paso 8: Configurar Apache (Opcional)

Si desea usar un dominio personalizado, editar `/etc/apache2/sites-available/autoeval.conf`:

```apache
<VirtualHost *:80>
    ServerName autoeval.local
    DocumentRoot /var/www/html/autoeval
    
    <Directory /var/www/html/autoeval>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Activar el sitio:
```bash
sudo a2ensite autoeval.conf
sudo systemctl reload apache2
```

### Paso 9: Verificar Instalación

1. Abrir el navegador y acceder a: `http://localhost/autoeval/`
2. Debería ver la página de login
3. Iniciar sesión con el usuario administrador supremo:
   - **Email**: `admin_supremo@uct.cl`
   - **Contraseña**: `admin123`
4. **IMPORTANTE**: Cambiar la contraseña después del primer acceso

**Nota**: El usuario `admin_supremo` es el principal del sistema y tiene acceso completo a todas las funcionalidades de administración.

## Configuración para Producción

### 1. Cambiar Entorno en `config/config.php`:

```php
$entorno = 'produccion'; // Cambiar de 'desarrollo' a 'produccion'
```

### 2. Configurar HTTPS:

Editar `.htaccess` y descomentar las líneas de redirección HTTPS:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 3. Actualizar BASE_URL:

```php
define('BASE_URL', 'https://tu-dominio.com/autoeval/');
```

### 4. Configurar Permisos de Archivos:

```bash
# Archivos de configuración solo lectura
chmod 644 config/config.php
chmod 644 config/database.php

# Carpetas de logs y uploads con permisos de escritura
chmod 775 logs uploads
```

## Solución de Problemas

### Error de conexión a la base de datos
- Verificar que MySQL esté corriendo
- Verificar las credenciales en `config/database.php`
- Verificar que la base de datos `autoeval_tecuct` exista

### Error al enviar correos
- Verificar la configuración SMTP en `config/config.php`
- Para Gmail, usar "Contraseña de aplicación"
- Verificar que la extensión `openssl` esté habilitada: `php -m | grep openssl`

### Error 404 en las páginas
- Verificar que `mod_rewrite` esté habilitado: `sudo a2enmod rewrite`
- Verificar que el archivo `.htaccess` exista
- Verificar la constante `BASE_URL` en `config/config.php`

### Error al importar PDF
- Verificar que la extensión `zip` esté habilitada: `php -m | grep zip`
- En XAMPP, editar `php.ini` y descomentar: `extension=zip`

### Error de permisos (Linux)
- Verificar que el usuario del servidor web tenga permisos de lectura/escritura
- Usar `ls -la` para verificar permisos de archivos

## Estructura del Proyecto

```
autoeval/
├── admin/              # Módulos de administración (docente/admin)
├── admin_supremo/    # Módulos de administración suprema
├── estudiante/        # Módulos para estudiantes
├── ajax/              # Endpoints AJAX
├── assets/            # Recursos estáticos (CSS, JS, imágenes)
├── config/             # Archivos de configuración
│   ├── config.php     # Configuración principal (crear desde .example)
│   ├── database.php   # Configuración de BD (crear desde .example)
│   └── email.php      # Funciones de correo
├── database/          # Scripts de base de datos
│   ├── schema.sql     # Esquema completo de la base de datos
│   └── README.md      # Documentación de la base de datos
├── includes/          # Archivos incluidos (header, footer, notificaciones)
├── install/           # Scripts de instalación
├── logs/              # Archivos de log
├── uploads/           # Archivos subidos (CSV, PDF)
├── vendor/            # Dependencias de Composer
├── .htaccess          # Configuración de Apache
├── .gitignore         # Archivos ignorados por Git
├── composer.json      # Dependencias del proyecto
└── README.md          # Documentación principal
```

## Soporte

Para más información o soporte, contactar al equipo de desarrollo.

---

**Desarrollado para TEC-UCT - Universidad Católica de Temuco**

