# Dependencias e instrucciones de instalación

Este documento describe cómo instalar las dependencias necesarias para ejecutar el proyecto `autoeval` en entornos Windows (PowerShell), Linux y macOS. Incluye requisitos de PHP, extensiones, Composer y pasos básicos de configuración.

**Resumen rápido**
- **PHP:** >= 7.4
- **Dependencias de Composer:** `phpmailer/phpmailer` (^6.8), `smalot/pdfparser` (^2.12), `tecnickcom/tcpdf` (^6.10)

**1. Requisitos previos**

- Tener PHP (>= 7.4) instalado y accesible en la variable `PATH`.
- Tener Composer instalado globalmente.
- Cliente MySQL/MariaDB para crear/importar la base de datos.
- Acceso para editar `php.ini` si es necesario.

Revisa la versión de PHP y las extensiones cargadas:

```bash
php -v
php -m
```

**Extensiones PHP recomendadas (mínimas)**
- `mbstring`
- `xml`
- `gd`
- `pdo_mysql` (o `mysqlnd`)
- `curl`
- `openssl`
- `zip`
- `fileinfo`

-- Estas extensiones son requeridas o recomendadas por paquetes como TCPDF y pdfparser.

**2. Instalar Composer**

Windows (PowerShell):

1. Descarga el instalador de Composer desde https://getcomposer.org/Composer-Setup.exe y ejecútalo.
2. O (si tienes Chocolatey):

```powershell
choco install composer -y
```

Linux (Debian/Ubuntu):

```bash
sudo apt update
sudo apt install -y curl php-cli unzip
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

macOS (Homebrew):

```bash
brew update
brew install composer
composer --version
```

**3. Instalar extensiones PHP**

Debian/Ubuntu (ejemplo):

```bash
sudo apt update
sudo apt install -y php php-mbstring php-xml php-gd php-mysql php-curl php-zip php-intl
sudo systemctl restart apache2   # o php-fpm según tu stack
```

CentOS/RHEL (ejemplo):

```bash
sudo yum install -y epel-release yum-utils
sudo yum install -y php php-mbstring php-xml php-gd php-mysqlnd php-curl php-zip
sudo systemctl restart httpd
```

macOS (si faltan extensiones):

```bash
brew install php
# para extensiones adicionales, usar pecl si es necesario
# pecl install redis
```

Windows (XAMPP/WAMP):

1. Abre el `php.ini` (por ejemplo `C:\xampp\php\php.ini`).
2. Asegúrate de que las líneas de las extensiones no estén comentadas, por ejemplo:

```ini
extension=mbstring
extension=gd
extension=pdo_mysql
extension=curl
extension=openssl
extension=zip
```

3. Reinicia Apache o el servicio web que uses.

**4. Instalar dependencias del proyecto (Composer)**

Desde la raíz del proyecto (`e:\autoeval`) ejecuta:

PowerShell (Windows):

```powershell
cd e:\autoeval
composer install
# o para producción
composer install --no-dev --optimize-autoloader
```

Linux / macOS:

```bash
cd /ruta/al/proyecto/autoeval
composer install
```

Si Composer no está en `PATH`, ejecuta `php composer.phar install` en su lugar (si descargaste `composer.phar`).

**5. Configuración de la base de datos**

1. Crea la base de datos (ejemplo con MySQL):

```bash
mysql -u root -p -e "CREATE DATABASE autoeval CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

2. Importa el esquema provisto en `database/schema.sql`:

Windows (CMD o PowerShell si `mysql` está en PATH):

```powershell
mysql -u root -p autoeval < "e:\autoeval\database\schema.sql"
```

Linux / macOS:

```bash
mysql -u root -p autoeval < database/schema.sql
```

3. Copia y adapta configuraciones locales:

```bash
cp config/config.example.php config/config.php
cp database/database.example.php database/database.php
# Edita config/config.php y database/database.php con tus credenciales
```

**6. Permisos (Linux)**

Si despliegas en Linux, asegurarte de que `uploads/` y `logs/` sean escribibles por el usuario web:

```bash
sudo chown -R www-data:www-data /ruta/al/proyecto/autoeval
sudo find /ruta/al/proyecto/autoeval/uploads -type d -exec chmod 775 {} \;
sudo find /ruta/al/proyecto/autoeval/uploads -type f -exec chmod 664 {} \;
```

**7. Comandos útiles**

- Ejecutar servidor PHP embebido (desarrollo):

```bash
php -S localhost:8000 -t .
```

- Regenerar autoload de Composer:

```bash
composer dump-autoload -o
```

- Ver módulos PHP cargados:

```bash
php -m
```

**8. Verificación final**

- `php -v` debe mostrar PHP >= 7.4.
- `composer --version` debe mostrar Composer instalado.
- `php -m` debe listar las extensiones indicadas arriba.
- Acceder a `http://localhost:8000` o a la URL configurada en tu servidor web para probar la aplicación.

**9. Dependencias listadas en `composer.json`**

El `composer.json` del proyecto requiere:

- `php` >= 7.4
- `phpmailer/phpmailer` ^6.8
- `smalot/pdfparser` ^2.12
- `tecnickcom/tcpdf` ^6.10

Si necesitas que ejecute aquí `composer install` o que adapte este documento a un sistema operativo en particular, dime cuál y lo hago.

---
Archivo generado: `DEPENDENCIAS.md`
