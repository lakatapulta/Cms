# FlexCMS - Guía de Instalación Rápida

## 🚀 Instalación Rápida

### 1. Requisitos Previos

- **PHP**: 8.1 o superior
- **Base de datos**: MySQL 5.7+ / PostgreSQL 10+ / SQLite 3
- **Composer**: Para gestión de dependencias
- **Servidor web**: Apache o Nginx

### 2. Instalación

```bash
# 1. Clonar el repositorio
git clone https://github.com/flexcms/core.git flexcms
cd flexcms

# 2. Instalar dependencias
composer install

# 3. Configurar entorno
cp .env.example .env

# 4. Editar configuración en .env
nano .env
```

### 3. Configuración Base de Datos

Edita el archivo `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexcms
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

### 4. Permisos

```bash
chmod -R 755 storage/
chmod -R 755 public/uploads/
chown -R www-data:www-data storage/
chown -R www-data:www-data public/uploads/
```

### 5. Configuración del Servidor

#### Apache (.htaccess)

Crear `public/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

#### Nginx

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /ruta/a/flexcms/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Ejecutar Migraciones

```bash
# Dentro del directorio de FlexCMS
php -r "
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->load();
\$app = new FlexCMS\Core\Application();
\$app->get('database')->migrate();
echo 'Migraciones completadas exitosamente' . PHP_EOL;
"
```

### 7. Verificar Instalación

Visita tu sitio web en el navegador. Deberías ver la página de inicio de FlexCMS.

## 🔧 Configuración Adicional

### Generar Clave de Aplicación

```bash
php -r "echo 'APP_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

Copia la clave generada y pégala en tu archivo `.env`.

### Configurar Cache (Opcional)

Para mejor rendimiento, configura Redis:

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Configurar Email

```env
MAIL_MAILER=smtp
MAIL_HOST=tu-servidor-smtp.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@ejemplo.com
MAIL_PASSWORD=tu-contraseña
MAIL_ENCRYPTION=tls
```

## 🐳 Instalación con Docker

### 1. Crear docker-compose.yml

```yaml
version: '3.8'

services:
  web:
    image: php:8.1-apache
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html/public
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: flexcms
      MYSQL_USER: flexcms
      MYSQL_PASSWORD: flexcms
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:alpine
    ports:
      - "6379:6379"

volumes:
  mysql_data:
```

### 2. Ejecutar Docker

```bash
# Iniciar contenedores
docker-compose up -d

# Instalar dependencias
docker-compose exec web composer install

# Configurar permisos
docker-compose exec web chown -R www-data:www-data storage/
```

## 🚨 Resolución de Problemas

### Error: "Class not found"

```bash
# Regenerar autoloader
composer dump-autoload
```

### Error de permisos

```bash
# Verificar propietario
ls -la storage/
chown -R www-data:www-data storage/
chmod -R 755 storage/
```

### Error de base de datos

1. Verificar credenciales en `.env`
2. Asegurar que la base de datos existe
3. Verificar que el usuario tiene permisos

### 500 Internal Server Error

1. Verificar logs del servidor web
2. Verificar logs de PHP
3. Verificar permisos de archivos
4. Asegurar que mod_rewrite está habilitado (Apache)

## 📞 Soporte

Si encuentras problemas durante la instalación:

- **Documentación**: [https://docs.flexcms.org](https://docs.flexcms.org)
- **Issues**: [https://github.com/flexcms/core/issues](https://github.com/flexcms/core/issues)
- **Comunidad**: [https://discord.gg/flexcms](https://discord.gg/flexcms)

## ✅ Checklist Post-Instalación

- [ ] Sitio web accesible
- [ ] Base de datos conectada
- [ ] Permisos configurados
- [ ] Cache funcionando (si aplica)
- [ ] Email configurado (si aplica)
- [ ] SSL/HTTPS configurado (producción)
- [ ] Backups configurados (producción)

¡Felicidades! FlexCMS está listo para usar. 🎉