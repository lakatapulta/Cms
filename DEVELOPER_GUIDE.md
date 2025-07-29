# FlexCMS Developer Guide

Esta guía está diseñada para desarrolladores que quieren crear módulos y temas para FlexCMS.

## 🏗️ Arquitectura del Sistema

### Flujo de Aplicación

1. **Punto de entrada** (`public/index.php`)
   - Carga autoloader de Composer
   - Inicializa variables de entorno
   - Crea instancia de la aplicación
   - Ejecuta la aplicación

2. **Inicialización de la aplicación** (`app/Core/Application.php`)
   - Registra servicios base
   - Carga configuración
   - Inicializa base de datos
   - Descubre y carga módulos
   - Carga tema activo
   - Configura enrutamiento

3. **Procesamiento de requests**
   - Router analiza la URL
   - Ejecuta middleware
   - Llama al controlador/closure correspondiente
   - Renderiza la respuesta

### Servicios Principales

#### Application Container
FlexCMS utiliza un contenedor de dependencias basado en Illuminate Container:

```php
// Registrar servicio singleton
app()->singleton('my-service', function($app) {
    return new MyService($app);
});

// Resolver servicio
$service = app('my-service');

// Verificar si servicio está registrado
if (app()->bound('my-service')) {
    // Servicio disponible
}
```

#### Router
Sistema de enrutamiento robusto:

```php
$router = app('router');

// Rutas básicas
$router->get('/path', $action);
$router->post('/path', $action);
$router->put('/path', $action);
$router->delete('/path', $action);

// Rutas con parámetros
$router->get('/posts/{slug}', 'PostController@show');

// Grupos de rutas
$router->group(['prefix' => 'admin'], function($router) {
    $router->get('/', 'AdminController@index');
});

// Rutas con middleware
$router->group(['middleware' => 'auth'], function($router) {
    // Rutas protegidas
});

// Rutas nombradas
$router->get('/contact', 'ContactController@show', 'contact.show');
```

#### Database Manager
Gestión de base de datos con Eloquent:

```php
$db = app('database');

// Obtener conexión
$connection = $db->connection();

// Query builder
$posts = $connection->table('cms_posts')
    ->where('status', 'published')
    ->orderBy('created_at', 'desc')
    ->get();

// Schema builder
$schema = $db->schema();
if (!$schema->hasTable('my_table')) {
    $schema->create('my_table', function($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
}
```

#### View Engine
Motor de plantillas Twig:

```php
$view = app('view');

// Renderizar plantilla
$html = $view->render('template.twig', [
    'title' => 'Mi Página',
    'posts' => $posts
]);

// Verificar si plantilla existe
if ($view->exists('template.twig')) {
    // Plantilla disponible
}

// Agregar variables globales
$view->addGlobal('site_name', 'Mi Sitio');
```

## 🔌 Desarrollo de Módulos

### Estructura de un Módulo

```
modules/my-module/
├── module.json          # Configuración del módulo
├── src/                 # Código fuente
│   ├── MyModule.php    # Clase principal
│   ├── Controllers/    # Controladores
│   ├── Models/         # Modelos
│   ├── Services/       # Servicios
│   └── Middleware/     # Middleware
├── templates/          # Plantillas Twig
├── assets/            # CSS, JS, imágenes
├── migrations/        # Migraciones de BD
├── config/           # Configuración
├── tests/            # Tests del módulo
├── routes.php        # Rutas del módulo
├── install.php       # Script de instalación
└── README.md         # Documentación
```

### Configuración del Módulo (module.json)

```json
{
    "name": "E-commerce Module",
    "description": "Full featured e-commerce functionality",
    "version": "1.2.0",
    "author": "Your Name <email@example.com>",
    "homepage": "https://example.com/my-module",
    "license": "MIT",
    "main": "src/EcommerceModule.php",
    "class": "Modules\\Ecommerce\\EcommerceModule",
    "namespace": "Modules\\Ecommerce",
    "keywords": ["ecommerce", "shop", "products"],
    "dependencies": {
        "payment-gateway": "^1.0",
        "inventory": "^2.1"
    },
    "php_version": ">=8.1",
    "flexcms_version": ">=1.0",
    "supports": [
        "products",
        "orders",
        "payments",
        "inventory"
    ],
    "hooks": {
        "module_activated": "onModuleActivated",
        "module_deactivated": "onModuleDeactivated",
        "post_save": "onPostSave",
        "user_login": "onUserLogin"
    },
    "admin_menu": {
        "title": "E-commerce",
        "icon": "shopping-cart",
        "position": 30,
        "capability": "manage_shop",
        "submenu": [
            {
                "title": "Products",
                "route": "admin.ecommerce.products",
                "capability": "manage_products"
            },
            {
                "title": "Orders",
                "route": "admin.ecommerce.orders",
                "capability": "manage_orders"
            }
        ]
    },
    "permissions": [
        "manage_shop",
        "manage_products",
        "manage_orders",
        "view_reports"
    ],
    "settings": {
        "currency": "USD",
        "tax_rate": 0.1,
        "shipping_enabled": true
    }
}
```

### Clase Principal del Módulo

```php
<?php

namespace Modules\Ecommerce;

use FlexCMS\Core\Application;
use FlexCMS\Core\Module\BaseModule;

class EcommerceModule extends BaseModule
{
    /**
     * Versión del módulo
     */
    const VERSION = '1.2.0';

    /**
     * Inicializar módulo
     */
    public function boot()
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerCommands();
        $this->loadTranslations();
    }

    /**
     * Registrar servicios del módulo
     */
    protected function registerServices()
    {
        $this->app->singleton('ecommerce.cart', function($app) {
            return new Services\CartService($app);
        });

        $this->app->singleton('ecommerce.payment', function($app) {
            return new Services\PaymentService($app);
        });

        $this->app->singleton('ecommerce.inventory', function($app) {
            return new Services\InventoryService($app);
        });
    }

    /**
     * Registrar hooks del módulo
     */
    protected function registerHooks()
    {
        // Hook cuando se activa el módulo
        $this->addHook('module_activated', [$this, 'onModuleActivated']);
        
        // Hook cuando se desactiva el módulo
        $this->addHook('module_deactivated', [$this, 'onModuleDeactivated']);
        
        // Hook personalizado
        $this->addHook('product_purchased', [$this, 'onProductPurchased']);
        
        // Hook de template
        $this->addFilter('template_vars', [$this, 'addTemplateVars']);
    }

    /**
     * Registrar rutas del módulo
     */
    protected function registerRoutes()
    {
        $router = $this->app->get('router');
        
        // Incluir archivo de rutas
        if (file_exists($this->getPath('routes.php'))) {
            require $this->getPath('routes.php');
        }
    }

    /**
     * Registrar comandos de consola
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\ProcessOrdersCommand::class,
                Commands\UpdateInventoryCommand::class,
            ]);
        }
    }

    /**
     * Cargar traducciones
     */
    protected function loadTranslations()
    {
        $langPath = $this->getPath('lang');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'ecommerce');
        }
    }

    /**
     * Hook: Módulo activado
     */
    public function onModuleActivated()
    {
        // Crear tablas necesarias
        $this->createTables();
        
        // Configurar permisos
        $this->setupPermissions();
        
        // Configurar cron jobs
        $this->setupScheduledTasks();
        
        logger()->info('E-commerce module activated');
    }

    /**
     * Hook: Módulo desactivado
     */
    public function onModuleDeactivated()
    {
        // Limpiar cache
        $this->clearCache();
        
        // Desactivar cron jobs
        $this->removeScheduledTasks();
        
        logger()->info('E-commerce module deactivated');
    }

    /**
     * Hook: Producto comprado
     */
    public function onProductPurchased($product, $order)
    {
        // Actualizar inventario
        $inventory = $this->app->get('ecommerce.inventory');
        $inventory->decreaseStock($product->id, $order->quantity);
        
        // Enviar notificación
        $this->sendPurchaseNotification($product, $order);
        
        // Generar reporte
        $this->updateSalesReport($product, $order);
    }

    /**
     * Filter: Agregar variables a plantillas
     */
    public function addTemplateVars($vars)
    {
        $cart = $this->app->get('ecommerce.cart');
        
        $vars['cart_count'] = $cart->getItemCount();
        $vars['cart_total'] = $cart->getTotal();
        $vars['currency'] = $this->getSetting('currency', 'USD');
        
        return $vars;
    }

    /**
     * Crear tablas del módulo
     */
    protected function createTables()
    {
        $migrations = [
            'CreateProductsTable',
            'CreateOrdersTable',
            'CreateOrderItemsTable',
            'CreateCategoriesTable',
        ];

        foreach ($migrations as $migration) {
            $this->runMigration($migration);
        }
    }

    /**
     * Configurar permisos del módulo
     */
    protected function setupPermissions()
    {
        $permissions = [
            'manage_shop' => 'Manage Shop',
            'manage_products' => 'Manage Products',
            'manage_orders' => 'Manage Orders',
            'view_reports' => 'View Reports',
        ];

        foreach ($permissions as $key => $name) {
            $this->createPermission($key, $name);
        }
    }

    /**
     * Obtener configuración del módulo
     */
    public function getSetting($key, $default = null)
    {
        return config("modules.ecommerce.{$key}", $default);
    }

    /**
     * Establecer configuración del módulo
     */
    public function setSetting($key, $value)
    {
        config(["modules.ecommerce.{$key}" => $value]);
    }

    /**
     * Obtener ruta del módulo
     */
    public function getPath($path = '')
    {
        return module_path('ecommerce', $path);
    }

    /**
     * Obtener URL del módulo
     */
    public function getUrl($path = '')
    {
        return url("modules/ecommerce/{$path}");
    }

    /**
     * Obtener asset URL del módulo
     */
    public function getAssetUrl($asset)
    {
        return url("modules/ecommerce/assets/{$asset}");
    }
}
```

### Sistema de Hooks y Filtros

```php
// Registrar hook/action
add_action('post_save', function($post) {
    // Ejecutar cuando se guarda un post
});

// Registrar filtro
add_filter('post_content', function($content) {
    // Modificar contenido del post
    return $content . '<p>Contenido adicional</p>';
});

// Ejecutar hooks
do_action('post_save', $post);

// Aplicar filtros
$content = apply_filters('post_content', $originalContent);

// Hook con prioridad
add_action('init', 'mi_funcion', 20); // Prioridad 20

// Remover hook
remove_action('post_save', 'mi_funcion');
```

### Controladores de Módulo

```php
<?php

namespace Modules\Ecommerce\Controllers;

use FlexCMS\Core\Controller\BaseController;
use Modules\Ecommerce\Models\Product;
use Symfony\Component\HttpFoundation\Request;

class ProductController extends BaseController
{
    /**
     * Mostrar lista de productos
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 12);
        $category = $request->get('category');
        
        $query = Product::where('status', 'active');
        
        if ($category) {
            $query->whereHas('categories', function($q) use ($category) {
                $q->where('slug', $category);
            });
        }
        
        $products = $query->paginate($perPage);
        
        return $this->view('ecommerce/products/index.twig', [
            'products' => $products,
            'category' => $category,
            'page_title' => 'Products'
        ]);
    }

    /**
     * Mostrar producto individual
     */
    public function show(Request $request, $slug)
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            ->with(['categories', 'images', 'reviews'])
            ->firstOrFail();

        // Incrementar vistas
        $product->increment('views_count');

        // Productos relacionados
        $relatedProducts = Product::where('id', '!=', $product->id)
            ->whereHas('categories', function($q) use ($product) {
                $q->whereIn('id', $product->categories->pluck('id'));
            })
            ->limit(4)
            ->get();

        return $this->view('ecommerce/products/show.twig', [
            'product' => $product,
            'related_products' => $relatedProducts,
            'page_title' => $product->name,
            'meta_description' => $product->description
        ]);
    }

    /**
     * Agregar al carrito
     */
    public function addToCart(Request $request)
    {
        $productId = $request->get('product_id');
        $quantity = $request->get('quantity', 1);
        
        $product = Product::findOrFail($productId);
        
        // Verificar disponibilidad
        if ($product->stock < $quantity) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Insufficient stock'
            ], 400);
        }
        
        // Agregar al carrito
        $cart = app('ecommerce.cart');
        $cart->add($product, $quantity);
        
        return $this->jsonResponse([
            'success' => true,
            'message' => 'Product added to cart',
            'cart_count' => $cart->getItemCount(),
            'cart_total' => $cart->getTotal()
        ]);
    }

    /**
     * Respuesta JSON
     */
    protected function jsonResponse($data, $status = 200)
    {
        return new JsonResponse($data, $status);
    }
}
```

### Modelos de Módulo

```php
<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'cms_ecommerce_products';

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'sale_price',
        'stock', 'sku', 'status', 'featured', 'meta'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock' => 'integer',
        'featured' => 'boolean',
        'meta' => 'array'
    ];

    /**
     * Categorías del producto
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'cms_ecommerce_product_categories');
    }

    /**
     * Imágenes del producto
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Reseñas del producto
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Órdenes que incluyen este producto
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'cms_ecommerce_order_items')
            ->withPivot('quantity', 'price', 'total');
    }

    /**
     * Scope: Productos activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Productos destacados
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope: Productos en oferta
     */
    public function scopeOnSale($query)
    {
        return $query->whereNotNull('sale_price')
            ->where('sale_price', '<', 'price');
    }

    /**
     * Accessor: Precio efectivo
     */
    public function getEffectivePriceAttribute()
    {
        return $this->sale_price ?: $this->price;
    }

    /**
     * Accessor: Está en oferta
     */
    public function getIsOnSaleAttribute()
    {
        return $this->sale_price && $this->sale_price < $this->price;
    }

    /**
     * Accessor: Porcentaje de descuento
     */
    public function getDiscountPercentageAttribute()
    {
        if (!$this->is_on_sale) {
            return 0;
        }

        return round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    /**
     * Accessor: URL del producto
     */
    public function getUrlAttribute()
    {
        return route('ecommerce.products.show', $this->slug);
    }

    /**
     * Mutator: Slug automático
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = str_slug($value);
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Generar SKU automático si no se proporciona
        static::creating(function($product) {
            if (!$product->sku) {
                $product->sku = 'PRD-' . strtoupper(uniqid());
            }
        });

        // Limpiar cache al actualizar
        static::saved(function($product) {
            cache()->forget("product.{$product->id}");
            cache()->forget("products.featured");
        });
    }
}
```

### Servicios de Módulo

```php
<?php

namespace Modules\Ecommerce\Services;

use Modules\Ecommerce\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    protected $items;
    protected $sessionKey = 'ecommerce_cart';

    public function __construct()
    {
        $this->loadFromSession();
    }

    /**
     * Agregar producto al carrito
     */
    public function add(Product $product, int $quantity = 1): void
    {
        $itemId = $product->id;
        
        if (isset($this->items[$itemId])) {
            $this->items[$itemId]['quantity'] += $quantity;
        } else {
            $this->items[$itemId] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->effective_price,
                'quantity' => $quantity,
                'image' => $product->featured_image
            ];
        }
        
        $this->saveToSession();
    }

    /**
     * Remover producto del carrito
     */
    public function remove(int $productId): void
    {
        unset($this->items[$productId]);
        $this->saveToSession();
    }

    /**
     * Actualizar cantidad
     */
    public function updateQuantity(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($productId);
            return;
        }

        if (isset($this->items[$productId])) {
            $this->items[$productId]['quantity'] = $quantity;
            $this->saveToSession();
        }
    }

    /**
     * Limpiar carrito
     */
    public function clear(): void
    {
        $this->items = [];
        $this->saveToSession();
    }

    /**
     * Obtener items del carrito
     */
    public function getItems(): Collection
    {
        return collect($this->items);
    }

    /**
     * Obtener cantidad total de items
     */
    public function getItemCount(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    /**
     * Obtener total del carrito
     */
    public function getTotal(): float
    {
        $total = 0;
        
        foreach ($this->items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        return $total;
    }

    /**
     * Verificar si el carrito está vacío
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Cargar carrito desde sesión
     */
    protected function loadFromSession(): void
    {
        $this->items = session($this->sessionKey, []);
    }

    /**
     * Guardar carrito en sesión
     */
    protected function saveToSession(): void
    {
        session([$this->sessionKey => $this->items]);
    }
}
```

### Middleware de Módulo

```php
<?php

namespace Modules\Ecommerce\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckoutMiddleware
{
    /**
     * Manejar request
     */
    public function handle(Request $request, \Closure $next)
    {
        $cart = app('ecommerce.cart');
        
        // Verificar que el carrito no esté vacío
        if ($cart->isEmpty()) {
            return redirect('/cart')->with('error', 'Your cart is empty');
        }
        
        // Verificar disponibilidad de productos
        foreach ($cart->getItems() as $item) {
            $product = Product::find($item['product_id']);
            
            if (!$product || $product->stock < $item['quantity']) {
                return redirect('/cart')->with('error', 'Some products are no longer available');
            }
        }
        
        return $next($request);
    }
}
```

## 🎨 Desarrollo de Temas

### Estructura de un Tema

```
themes/my-theme/
├── theme.json           # Configuración del tema
├── screenshot.png       # Captura del tema (1200x900px)
├── templates/          # Plantillas Twig
│   ├── layout.twig     # Layout principal
│   ├── home.twig       # Página de inicio
│   ├── post.twig       # Post individual
│   ├── page.twig       # Página individual
│   ├── archive.twig    # Lista de posts
│   ├── category.twig   # Categoría
│   ├── search.twig     # Resultados de búsqueda
│   ├── 404.twig        # Página no encontrada
│   └── partials/       # Componentes reutilizables
│       ├── header.twig
│       ├── footer.twig
│       ├── sidebar.twig
│       └── navigation.twig
├── assets/             # Assets del tema
│   ├── css/
│   │   ├── style.css   # CSS principal
│   │   └── admin.css   # CSS para admin
│   ├── js/
│   │   ├── app.js      # JavaScript principal
│   │   └── admin.js    # JS para admin
│   ├── images/         # Imágenes del tema
│   └── fonts/          # Fuentes personalizadas
├── functions.php       # Funciones del tema
├── customizer.php      # Configuraciones del customizer
├── config/            # Configuraciones adicionales
└── lang/              # Traducciones
```

### Configuración del Tema (theme.json)

```json
{
    "name": "Modern Blog Theme",
    "description": "A modern and responsive blog theme",
    "version": "2.1.0",
    "author": "Theme Developer <dev@example.com>",
    "homepage": "https://example.com/themes/modern-blog",
    "license": "GPL-2.0",
    "screenshot": "screenshot.png",
    "tags": ["blog", "responsive", "modern", "clean"],
    "flexcms_version": ">=1.0",
    "supports": [
        "menus",
        "widgets",
        "post-thumbnails",
        "custom-headers",
        "custom-backgrounds",
        "custom-logo",
        "post-formats"
    ],
    "post_formats": [
        "video",
        "gallery",
        "quote",
        "link"
    ],
    "image_sizes": {
        "theme-thumbnail": [300, 200, true],
        "theme-medium": [600, 400, true],
        "theme-large": [1200, 800, true]
    },
    "customizer": {
        "sections": {
            "colors": {
                "title": "Colors",
                "settings": {
                    "primary_color": {
                        "type": "color",
                        "default": "#007cba",
                        "label": "Primary Color"
                    },
                    "secondary_color": {
                        "type": "color",
                        "default": "#6c757d",
                        "label": "Secondary Color"
                    }
                }
            },
            "typography": {
                "title": "Typography",
                "settings": {
                    "body_font": {
                        "type": "select",
                        "choices": {
                            "inter": "Inter",
                            "roboto": "Roboto",
                            "open-sans": "Open Sans"
                        },
                        "default": "inter",
                        "label": "Body Font"
                    },
                    "heading_font": {
                        "type": "select",
                        "choices": {
                            "inter": "Inter",
                            "roboto": "Roboto",
                            "playfair": "Playfair Display"
                        },
                        "default": "inter",
                        "label": "Heading Font"
                    }
                }
            },
            "layout": {
                "title": "Layout",
                "settings": {
                    "sidebar_position": {
                        "type": "radio",
                        "choices": {
                            "right": "Right",
                            "left": "Left",
                            "none": "No Sidebar"
                        },
                        "default": "right",
                        "label": "Sidebar Position"
                    },
                    "container_width": {
                        "type": "range",
                        "min": 1000,
                        "max": 1400,
                        "step": 50,
                        "default": 1200,
                        "label": "Container Width"
                    }
                }
            }
        }
    },
    "menus": {
        "primary": "Primary Menu",
        "footer": "Footer Menu",
        "social": "Social Links"
    },
    "widget_areas": {
        "sidebar": "Main Sidebar",
        "footer-1": "Footer Column 1",
        "footer-2": "Footer Column 2",
        "footer-3": "Footer Column 3"
    }
}
```

### Funciones del Tema (functions.php)

```php
<?php

/**
 * Configurar tema
 */
function modern_blog_setup()
{
    // Agregar soporte para características
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('custom-header');
    add_theme_support('custom-background');
    add_theme_support('title-tag');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption'
    ]);

    // Registrar tamaños de imagen
    add_image_size('theme-thumbnail', 300, 200, true);
    add_image_size('theme-medium', 600, 400, true);
    add_image_size('theme-large', 1200, 800, true);

    // Registrar menús
    register_nav_menus([
        'primary' => __('Primary Menu', 'modern-blog'),
        'footer' => __('Footer Menu', 'modern-blog'),
        'social' => __('Social Links', 'modern-blog')
    ]);
}
add_action('after_setup_theme', 'modern_blog_setup');

/**
 * Enqueue assets
 */
function modern_blog_scripts()
{
    // Estilos
    wp_enqueue_style(
        'modern-blog-style',
        theme_asset('css/style.css'),
        [],
        theme_version()
    );

    // Estilos personalizados del customizer
    wp_add_inline_style('modern-blog-style', modern_blog_custom_css());

    // JavaScript
    wp_enqueue_script(
        'modern-blog-script',
        theme_asset('js/app.js'),
        [],
        theme_version(),
        true
    );

    // Localizar script para AJAX
    wp_localize_script('modern-blog-script', 'theme_ajax', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('theme_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'modern_blog_scripts');

/**
 * Registrar áreas de widgets
 */
function modern_blog_widgets_init()
{
    register_sidebar([
        'name' => __('Main Sidebar', 'modern-blog'),
        'id' => 'sidebar',
        'description' => __('Add widgets here.', 'modern-blog'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        register_sidebar([
            'name' => sprintf(__('Footer Column %d', 'modern-blog'), $i),
            'id' => "footer-{$i}",
            'description' => sprintf(__('Footer column %d widgets.', 'modern-blog'), $i),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4 class="widget-title">',
            'after_title' => '</h4>',
        ]);
    }
}
add_action('widgets_init', 'modern_blog_widgets_init');

/**
 * CSS personalizado del customizer
 */
function modern_blog_custom_css()
{
    $primary_color = get_theme_mod('primary_color', '#007cba');
    $secondary_color = get_theme_mod('secondary_color', '#6c757d');
    $container_width = get_theme_mod('container_width', 1200);
    $body_font = get_theme_mod('body_font', 'inter');
    $heading_font = get_theme_mod('heading_font', 'inter');

    $css = "
        :root {
            --primary-color: {$primary_color};
            --secondary-color: {$secondary_color};
            --container-width: {$container_width}px;
        }
        
        body {
            font-family: '{$body_font}', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: '{$heading_font}', sans-serif;
        }
        
        .container {
            max-width: var(--container-width);
        }
    ";

    return $css;
}

/**
 * Configuración del customizer
 */
function modern_blog_customize_register($wp_customize)
{
    // Sección de colores
    $wp_customize->add_section('colors', [
        'title' => __('Colors', 'modern-blog'),
        'priority' => 30,
    ]);

    // Color primario
    $wp_customize->add_setting('primary_color', [
        'default' => '#007cba',
        'sanitize_callback' => 'sanitize_hex_color',
    ]);

    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'primary_color', [
        'label' => __('Primary Color', 'modern-blog'),
        'section' => 'colors',
    ]));

    // Más configuraciones...
}
add_action('customize_register', 'modern_blog_customize_register');

/**
 * Helpers del tema
 */
function theme_version()
{
    $theme = app('themes')->getActiveTheme();
    return $theme['version'] ?? '1.0.0';
}

function theme_asset($path)
{
    $themeManager = app('themes');
    return $themeManager->getAssetUrl($path);
}

function get_theme_mod($setting, $default = null)
{
    // Obtener configuración del customizer
    return config("theme.{$setting}", $default);
}

/**
 * Funciones de template
 */
function modern_blog_post_meta()
{
    echo '<div class="post-meta">';
    echo '<span class="post-date">' . get_the_date() . '</span>';
    echo '<span class="post-author">' . get_the_author() . '</span>';
    echo '<span class="post-categories">' . get_the_category_list(', ') . '</span>';
    echo '</div>';
}

function modern_blog_pagination($query = null)
{
    if (!$query) {
        global $wp_query;
        $query = $wp_query;
    }

    $total_pages = $query->max_num_pages;
    $current_page = max(1, get_query_var('paged'));

    if ($total_pages > 1) {
        echo '<nav class="pagination">';
        echo paginate_links([
            'total' => $total_pages,
            'current' => $current_page,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        ]);
        echo '</nav>';
    }
}

/**
 * Shortcodes del tema
 */
function modern_blog_button_shortcode($atts, $content = null)
{
    $atts = shortcode_atts([
        'url' => '#',
        'style' => 'primary',
        'size' => 'medium',
        'target' => '_self'
    ], $atts);

    $classes = "btn btn-{$atts['style']} btn-{$atts['size']}";

    return sprintf(
        '<a href="%s" class="%s" target="%s">%s</a>',
        esc_url($atts['url']),
        esc_attr($classes),
        esc_attr($atts['target']),
        $content
    );
}
add_shortcode('button', 'modern_blog_button_shortcode');

/**
 * AJAX handlers
 */
function modern_blog_load_more_posts()
{
    check_ajax_referer('theme_nonce', 'nonce');

    $page = intval($_POST['page']);
    $posts_per_page = intval($_POST['posts_per_page']);

    $query = new WP_Query([
        'post_type' => 'post',
        'posts_per_page' => $posts_per_page,
        'paged' => $page,
        'post_status' => 'publish'
    ]);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            get_template_part('templates/partials/post', 'card');
        }
    }

    wp_die();
}
add_action('wp_ajax_load_more_posts', 'modern_blog_load_more_posts');
add_action('wp_ajax_nopriv_load_more_posts', 'modern_blog_load_more_posts');
```

### Plantillas Twig del Tema

**Layout Principal (templates/layout.twig):**

```twig
<!DOCTYPE html>
<html lang="{{ app.locale | default('en') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    {% block title %}
        <title>
            {%- if page_title -%}
                {{ page_title }} - {{ config('site_title') }}
            {%- else -%}
                {{ config('site_title') }}
            {%- endif -%}
        </title>
    {% endblock %}
    
    {% block meta %}
        <meta name="description" content="{{ meta_description | default(config('seo_meta_description')) }}">
        <meta name="keywords" content="{{ meta_keywords | default(config('seo_meta_keywords')) }}">
        
        <!-- Open Graph -->
        <meta property="og:title" content="{{ page_title | default(config('site_title')) }}">
        <meta property="og:description" content="{{ meta_description | default(config('seo_meta_description')) }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ current_url }}">
        {% if featured_image %}
            <meta property="og:image" content="{{ featured_image }}">
        {% endif %}
        
        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ page_title | default(config('site_title')) }}">
        <meta name="twitter:description" content="{{ meta_description | default(config('seo_meta_description')) }}">
    {% endblock %}
    
    {% block styles %}
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="{{ theme_asset('css/style.css') }}" rel="stylesheet">
    {% endblock %}
    
    {% block head %}{% endblock %}
    
    {{ wp_head() }}
</head>
<body class="{% block body_class %}{% endblock %}">
    {% include 'partials/header.twig' %}
    
    <main class="main-content">
        {% block content %}{% endblock %}
    </main>
    
    {% include 'partials/footer.twig' %}
    
    {% block scripts %}
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ theme_asset('js/app.js') }}"></script>
    {% endblock %}
    
    {{ wp_footer() }}
</body>
</html>
```

**Post Individual (templates/post.twig):**

```twig
{% extends "layout.twig" %}

{% block title %}
    <title>{{ post.title }} - {{ config('site_title') }}</title>
{% endblock %}

{% block meta %}
    {{ parent() }}
    <meta name="description" content="{{ post.excerpt | striptags | slice(0, 160) }}">
    <meta property="og:type" content="article">
    <meta property="article:author" content="{{ post.author.display_name }}">
    <meta property="article:published_time" content="{{ post.published_at | date('c') }}">
    {% for category in post.categories %}
        <meta property="article:section" content="{{ category.name }}">
    {% endfor %}
{% endblock %}

{% block body_class %}single single-post{% endblock %}

{% block content %}
<div class="container">
    <div class="row">
        <div class="col-lg-8">
            <article class="post-single">
                {% if post.featured_image %}
                    <div class="post-featured-image">
                        <img src="{{ post.featured_image }}" 
                             alt="{{ post.title }}" 
                             class="img-fluid rounded">
                    </div>
                {% endif %}
                
                <header class="post-header">
                    <h1 class="post-title">{{ post.title }}</h1>
                    
                    <div class="post-meta">
                        <span class="post-date">
                            <i class="far fa-calendar"></i>
                            {{ post.published_at | date('F j, Y') }}
                        </span>
                        
                        <span class="post-author">
                            <i class="far fa-user"></i>
                            <a href="{{ url('/author/' ~ post.author.username) }}">
                                {{ post.author.display_name }}
                            </a>
                        </span>
                        
                        {% if post.categories | length > 0 %}
                            <span class="post-categories">
                                <i class="far fa-folder"></i>
                                {% for category in post.categories %}
                                    <a href="{{ url('/category/' ~ category.slug) }}">
                                        {{ category.name }}
                                    </a>
                                    {%- if not loop.last -%}, {% endif %}
                                {% endfor %}
                            </span>
                        {% endif %}
                        
                        <span class="post-views">
                            <i class="far fa-eye"></i>
                            {{ post.views_count }} views
                        </span>
                    </div>
                </header>
                
                <div class="post-content">
                    {{ post.content | raw }}
                </div>
                
                {% if post.tags | length > 0 %}
                    <div class="post-tags">
                        <h5>Tags:</h5>
                        {% for tag in post.tags %}
                            <a href="{{ url('/tag/' ~ tag.slug) }}" class="badge bg-secondary">
                                {{ tag.name }}
                            </a>
                        {% endfor %}
                    </div>
                {% endif %}
                
                <div class="post-share">
                    <h5>Share this post:</h5>
                    <div class="share-buttons">
                        <a href="https://twitter.com/intent/tweet?url={{ current_url | url_encode }}&text={{ post.title | url_encode }}" 
                           class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fab fa-twitter"></i> Twitter
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ current_url | url_encode }}" 
                           class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fab fa-facebook"></i> Facebook
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ current_url | url_encode }}" 
                           class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fab fa-linkedin"></i> LinkedIn
                        </a>
                    </div>
                </div>
                
                <!-- Navegación entre posts -->
                <nav class="post-navigation">
                    <div class="row">
                        {% if previous_post %}
                            <div class="col-md-6">
                                <a href="{{ previous_post.url }}" class="post-nav-link prev">
                                    <span class="nav-direction">Previous Post</span>
                                    <span class="nav-title">{{ previous_post.title }}</span>
                                </a>
                            </div>
                        {% endif %}
                        
                        {% if next_post %}
                            <div class="col-md-6 text-end">
                                <a href="{{ next_post.url }}" class="post-nav-link next">
                                    <span class="nav-direction">Next Post</span>
                                    <span class="nav-title">{{ next_post.title }}</span>
                                </a>
                            </div>
                        {% endif %}
                    </div>
                </nav>
                
                <!-- Caja de autor -->
                <div class="author-box">
                    <div class="author-avatar">
                        <img src="{{ post.author.avatar | default(asset('images/default-avatar.png')) }}" 
                             alt="{{ post.author.display_name }}" 
                             class="rounded-circle">
                    </div>
                    <div class="author-info">
                        <h5 class="author-name">{{ post.author.display_name }}</h5>
                        {% if post.author.bio %}
                            <p class="author-bio">{{ post.author.bio }}</p>
                        {% endif %}
                        <a href="{{ url('/author/' ~ post.author.username) }}" class="btn btn-sm btn-outline-primary">
                            View all posts
                        </a>
                    </div>
                </div>
                
                <!-- Posts relacionados -->
                {% if related_posts | length > 0 %}
                    <div class="related-posts">
                        <h3>Related Posts</h3>
                        <div class="row">
                            {% for related_post in related_posts %}
                                <div class="col-md-6 mb-4">
                                    {% include 'partials/post-card.twig' with {'post': related_post} %}
                                </div>
                            {% endfor %}
                        </div>
                    </div>
                {% endif %}
                
                <!-- Comentarios -->
                {% if config('comments_enabled') %}
                    <div class="comments-section">
                        <h3>Comments</h3>
                        {{ comments_template() }}
                    </div>
                {% endif %}
            </article>
        </div>
        
        <div class="col-lg-4">
            {% include 'partials/sidebar.twig' %}
        </div>
    </div>
</div>
{% endblock %}
```

## 🚀 Optimización y Mejores Prácticas

### Cache

```php
// Cache de configuración
$cachedConfig = cache()->remember('site_config', 3600, function() {
    return config()->all();
});

// Cache de consultas
$posts = cache()->remember('recent_posts', 1800, function() {
    return Post::published()->latest()->limit(5)->get();
});

// Invalidar cache
cache()->forget('recent_posts');
cache()->flush(); // Limpiar todo el cache
```

### Optimización de Base de Datos

```php
// Eager loading para evitar N+1 queries
$posts = Post::with(['author', 'categories', 'tags'])->get();

// Índices en migraciones
$table->index(['status', 'published_at']);
$table->index('author_id');
$table->fullText(['title', 'content']);

// Paginación eficiente
$posts = Post::published()->paginate(10);
```

### Seguridad

```php
// Sanitizar input
$title = sanitize_text_field($_POST['title']);
$content = wp_kses_post($_POST['content']);

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Nonces para formularios
wp_nonce_field('my_action', 'my_nonce');

// Verificar nonce
if (!wp_verify_nonce($_POST['my_nonce'], 'my_action')) {
    wp_die('Security check failed');
}

// Escapar output
echo esc_html($user_input);
echo esc_url($url);
echo esc_attr($attribute);
```

### Performance

```php
// Minificar assets en producción
if (config('app.env') === 'production') {
    $css = file_get_contents($cssFile);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = str_replace(['; ', ' {', '{ ', ' }', '} '], [';', '{', '{', '}', '}'], $css);
}

// Lazy loading de imágenes
function add_lazy_loading($content) {
    return preg_replace('/<img(.*?)src=(.*?)>/i', '<img$1data-src=$2 src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="lazy">', $content);
}
add_filter('the_content', 'add_lazy_loading');

// Comprimir output
if (config('app.env') === 'production') {
    ob_start('ob_gzhandler');
}
```

Este guide proporciona una base sólida para desarrollar módulos y temas para FlexCMS. El sistema está diseñado para ser extensible y seguir las mejores prácticas de desarrollo moderno.