# FlexCMS - Flexible Content Management System

FlexCMS es un sistema de gestión de contenidos modular y extensible inspirado en WordPress, construido con PHP moderno y tecnologías actuales.

## 🚀 Características Principales

- **Arquitectura Modular**: Sistema de plugins/módulos extensible
- **Sistema de Temas**: Temas intercambiables y personalizables
- **Moderno y Rápido**: Construido con PHP 8.1+, Twig y tecnologías modernas
- **Base de Datos Flexible**: Migraciones automáticas y múltiples conexiones
- **API RESTful**: API completa para integración externa
- **Panel de Administración**: Interfaz intuitiva para gestión de contenido
- **SEO Optimizado**: URLs amigables y meta tags configurables
- **Responsive**: Diseño adaptativo para todos los dispositivos

## 📋 Requisitos del Sistema

- PHP 8.1 o superior
- MySQL 5.7+ o PostgreSQL 10+ o SQLite 3
- Composer
- Extensiones PHP requeridas:
  - PDO
  - JSON
  - Mbstring
  - OpenSSL
  - Tokenizer
  - XML
  - Ctype
  - Fileinfo

## 🛠️ Instalación

### 1. Clonar el Repositorio

```bash
git clone https://github.com/flexcms/core.git flexcms
cd flexcms
```

### 2. Instalar Dependencias

```bash
composer install
```

### 3. Configuración del Entorno

```bash
cp .env.example .env
```

Edita el archivo `.env` con tu configuración:

```env
APP_NAME=FlexCMS
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexcms
DB_USERNAME=root
DB_PASSWORD=your_password

# Genera una clave de aplicación
APP_KEY=base64:your-generated-app-key
```

### 4. Configurar Servidor Web

#### Apache

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/flexcms/public
    
    <Directory /path/to/flexcms/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/flexcms/public;
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

### 5. Ejecutar Migraciones

```bash
php artisan migrate
```

### 6. Configurar Permisos

```bash
chmod -R 755 storage/
chmod -R 755 public/uploads/
chown -R www-data:www-data storage/
chown -R www-data:www-data public/uploads/
```

## 📚 Arquitectura del Sistema

### Estructura de Directorios

```
flexcms/
├── app/                    # Aplicación principal
│   ├── Core/              # Clases core del sistema
│   │   ├── Application.php # Aplicación principal
│   │   ├── Config.php     # Gestión de configuración
│   │   ├── Router.php     # Sistema de enrutamiento
│   │   ├── ViewEngine.php # Motor de plantillas Twig
│   │   ├── Database/      # Gestión de base de datos
│   │   ├── Module/        # Sistema de módulos
│   │   └── Theme/         # Sistema de temas
│   ├── Controllers/       # Controladores
│   ├── Models/           # Modelos Eloquent
│   ├── Middleware/       # Middleware de HTTP
│   └── Services/         # Servicios de negocio
├── public/               # Directorio público web
│   ├── index.php        # Punto de entrada
│   ├── assets/          # Assets estáticos
│   └── uploads/         # Archivos subidos
├── themes/              # Temas del CMS
│   └── default/         # Tema por defecto
│       ├── theme.json   # Configuración del tema
│       ├── templates/   # Plantillas Twig
│       └── assets/      # CSS, JS, imágenes
├── modules/             # Módulos/Plugins
│   └── core/           # Módulo core
├── storage/            # Almacenamiento del sistema
│   ├── logs/          # Logs del sistema
│   ├── cache/         # Cache del sistema
│   └── sessions/      # Sesiones
├── config/            # Archivos de configuración
├── vendor/            # Dependencias de Composer
└── tests/            # Tests automatizados
```

## 🔧 Desarrollo de Módulos

### Crear un Nuevo Módulo

1. **Crear estructura del módulo:**

```bash
mkdir -p modules/my-module/{src,templates,assets,migrations}
```

2. **Crear archivo de configuración** (`modules/my-module/module.json`):

```json
{
    "name": "My Module",
    "description": "Description of my module",
    "version": "1.0.0",
    "author": "Your Name",
    "main": "src/MyModule.php",
    "class": "Modules\\MyModule\\MyModule",
    "dependencies": [],
    "supports": ["posts", "pages"],
    "hooks": {
        "post_save": "onPostSave",
        "theme_setup": "onThemeSetup"
    }
}
```

3. **Crear clase principal del módulo** (`modules/my-module/src/MyModule.php`):

```php
<?php

namespace Modules\MyModule;

use FlexCMS\Core\Application;

class MyModule
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function boot()
    {
        // Inicialización del módulo
        $this->registerHooks();
        $this->registerRoutes();
    }

    protected function registerHooks()
    {
        // Registrar hooks del módulo
    }

    protected function registerRoutes()
    {
        // Registrar rutas del módulo
    }

    public function onPostSave($post)
    {
        // Hook ejecutado cuando se guarda un post
    }

    public function onThemeSetup()
    {
        // Hook ejecutado cuando se configura el tema
    }
}
```

4. **Crear rutas del módulo** (`modules/my-module/routes.php`):

```php
<?php

$router = app('router');

$router->group(['prefix' => 'my-module'], function($router) {
    $router->get('/', 'Modules\MyModule\Controllers\MyController@index');
    $router->get('/action', 'Modules\MyModule\Controllers\MyController@action');
});
```

### Sistema de Hooks

FlexCMS utiliza un sistema de hooks para permitir la extensión:

```php
// Registrar un hook
app('hooks')->register('post_save', function($post) {
    // Tu código aquí
});

// Ejecutar hooks
app('hooks')->execute('post_save', $post);
```

## 🎨 Desarrollo de Temas

### Crear un Nuevo Tema

1. **Crear estructura del tema:**

```bash
mkdir -p themes/my-theme/{templates,assets/{css,js,images}}
```

2. **Configuración del tema** (`themes/my-theme/theme.json`):

```json
{
    "name": "My Theme",
    "description": "Description of my theme",
    "version": "1.0.0",
    "author": "Your Name",
    "screenshot": "screenshot.png",
    "supports": [
        "menus",
        "widgets",
        "post-thumbnails",
        "custom-headers"
    ],
    "customizer": {
        "colors": {
            "primary": "#007cba",
            "secondary": "#6c757d"
        },
        "typography": {
            "font_family": "Inter, sans-serif"
        }
    }
}
```

3. **Template principal** (`themes/my-theme/templates/layout.twig`):

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}{{ config('site_title') }}{% endblock %}</title>
    
    <link href="{{ theme_asset('css/style.css') }}" rel="stylesheet">
    {% block head %}{% endblock %}
</head>
<body>
    <header>
        <!-- Navegación del tema -->
    </header>

    <main>
        {% block content %}{% endblock %}
    </main>

    <footer>
        <!-- Footer del tema -->
    </footer>

    <script src="{{ theme_asset('js/app.js') }}"></script>
    {% block scripts %}{% endblock %}
</body>
</html>
```

4. **Funciones del tema** (`themes/my-theme/functions.php`):

```php
<?php

// Agregar soporte para características
add_theme_support('post-thumbnails');
add_theme_support('menus');

// Registrar menús
register_nav_menus([
    'primary' => 'Primary Menu',
    'footer' => 'Footer Menu'
]);

// Enqueue assets
function my_theme_assets() {
    wp_enqueue_style('my-theme-style', theme_asset('css/style.css'));
    wp_enqueue_script('my-theme-script', theme_asset('js/app.js'));
}
add_action('wp_enqueue_scripts', 'my_theme_assets');
```

### Funciones Disponibles en Templates

```twig
{# URLs #}
{{ url('/path') }}
{{ route('route.name', {param: 'value'}) }}
{{ asset('path/to/asset') }}
{{ theme_asset('css/style.css') }}

{# Configuración #}
{{ config('app.name') }}
{{ config('site_title') }}

{# Condicionales #}
{% if user %}
    <p>Usuario autenticado: {{ user.username }}</p>
{% endif %}

{# Incluir templates de módulos #}
{{ include_module('module_name', 'template.twig', {data: 'value'}) }}

{# Menús y widgets #}
{{ menu('primary') }}
{{ widget('sidebar', {title: 'Mi Widget'}) }}
```

## 🗄️ Base de Datos

### Migraciones

Las migraciones se ejecutan automáticamente. Para crear una nueva migración:

```php
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateMyTable
{
    public function up()
    {
        $schema = app('database')->schema();
        
        $schema->create('my_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        $schema = app('database')->schema();
        $schema->dropIfExists('my_table');
    }
}
```

### Modelos Eloquent

```php
<?php

namespace FlexCMS\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'cms_posts';
    
    protected $fillable = [
        'title', 'slug', 'content', 'status'
    ];

    protected $casts = [
        'meta' => 'array',
        'published_at' => 'datetime'
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'cms_post_categories');
    }
}
```

## 🔐 Autenticación y Autorización

### Middleware de Autenticación

```php
<?php

namespace FlexCMS\Middleware;

use Symfony\Component\HttpFoundation\Request;

class AuthMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        if (!$this->isAuthenticated($request)) {
            return redirect('/login');
        }

        return $next($request);
    }

    protected function isAuthenticated(Request $request)
    {
        // Verificar autenticación
        return session('user_id') !== null;
    }
}
```

### Control de Roles

```php
// Verificar permisos
if (user_can('manage_posts')) {
    // El usuario puede gestionar posts
}

// Verificar rol
if (user_has_role('admin')) {
    // El usuario es administrador
}
```

## 📡 API RESTful

### Endpoints Disponibles

```
GET    /api/v1/posts              # Listar posts
GET    /api/v1/posts/{id}         # Obtener post específico
GET    /api/v1/categories         # Listar categorías
GET    /api/v1/search?q=query     # Buscar contenido
```

### Crear Endpoint Personalizado

```php
<?php

$router->group(['prefix' => 'api/v1'], function($router) {
    $router->get('/my-endpoint', function() {
        return json_response([
            'status' => 'success',
            'data' => []
        ]);
    });
});
```

## ⚙️ Configuración

### Variables de Entorno

```env
# Aplicación
APP_NAME=FlexCMS
APP_ENV=production|development
APP_DEBUG=true|false
APP_URL=http://localhost

# Base de datos
DB_CONNECTION=mysql|sqlite|pgsql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexcms
DB_USERNAME=root
DB_PASSWORD=

# Cache
CACHE_DRIVER=file|redis|memcached

# Sesiones
SESSION_DRIVER=file|database|redis
SESSION_LIFETIME=120

# Email
MAIL_MAILER=smtp|log|array
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=

# Tema y módulos
ACTIVE_THEME=default
MODULES_AUTO_DISCOVERY=true
```

### Configuración Programática

```php
// Establecer configuración
config(['app.custom_setting' => 'value']);

// Obtener configuración
$value = config('app.custom_setting', 'default');

// Verificar si existe
if (config()->has('app.custom_setting')) {
    // Configuración existe
}
```

## 🧪 Testing

### Ejecutar Tests

```bash
# Todos los tests
composer test

# Tests específicos
composer test -- --filter TestClassName
```

### Crear Tests

```php
<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class MyModuleTest extends TestCase
{
    public function testModuleLoads()
    {
        $app = new FlexCMS\Core\Application();
        $moduleManager = $app->get('modules');
        
        $this->assertTrue($moduleManager->isActive('my-module'));
    }
}
```

## 🚀 Despliegue

### Optimización para Producción

```bash
# Optimizar autoloader
composer install --no-dev --optimize-autoloader

# Generar clave de aplicación
php artisan key:generate

# Limpiar cache
php artisan cache:clear
php artisan config:clear
```

### Configuración de Servidor

1. **Asegurar directorios de escritura:**
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public/uploads/
   ```

2. **Configurar HTTPS:**
   ```apache
   # En .htaccess
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

3. **Configurar cache de aplicación:**
   ```env
   CACHE_DRIVER=redis
   SESSION_DRIVER=redis
   ```

## 🤝 Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 🆘 Soporte

- **Documentación**: [https://docs.flexcms.org](https://docs.flexcms.org)
- **Issues**: [https://github.com/flexcms/core/issues](https://github.com/flexcms/core/issues)
- **Discusiones**: [https://github.com/flexcms/core/discussions](https://github.com/flexcms/core/discussions)
- **Email**: support@flexcms.org

## 🔄 Changelog

### v1.0.0 (2024-01-01)
- ✨ Lanzamiento inicial
- 🎨 Sistema de temas completo
- 🔌 Sistema de módulos extensible
- 🗄️ Migraciones automáticas de base de datos
- 📱 Interfaz responsive
- 🔐 Sistema de autenticación
- 📡 API RESTful
- ⚡ Optimizaciones de rendimiento

---

**FlexCMS** - Un CMS flexible y moderno para el desarrollo web actual.