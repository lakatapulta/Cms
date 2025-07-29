<?php

namespace FlexCMS\Core;

use Illuminate\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FlexCMS\Core\Database\DatabaseManager;
use FlexCMS\Core\Module\ModuleManager;
use FlexCMS\Core\Theme\ThemeManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Application extends Container
{
    /**
     * Application version
     */
    const VERSION = '1.0.0';

    /**
     * Application instance
     */
    protected static $instance;

    /**
     * Application services
     */
    protected $services = [
        'config' => Config::class,
        'router' => Router::class,
        'database' => DatabaseManager::class,
        'modules' => ModuleManager::class,
        'themes' => ThemeManager::class,
        'view' => ViewEngine::class,
        'logger' => Logger::class,
    ];

    /**
     * Boot status
     */
    protected $booted = false;

    /**
     * Create application instance
     */
    public function __construct()
    {
        static::setInstance($this);
        $this->registerBaseBindings();
        $this->registerServices();
        $this->bootApplication();
    }

    /**
     * Get application instance
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * Set application instance
     */
    public static function setInstance($app)
    {
        static::$instance = $app;
    }

    /**
     * Register base bindings
     */
    protected function registerBaseBindings()
    {
        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance(Application::class, $this);
    }

    /**
     * Register application services
     */
    protected function registerServices()
    {
        // Config service
        $this->singleton('config', function () {
            return new Config();
        });

        // Logger service
        $this->singleton('logger', function () {
            $logger = new Logger('flexcms');
            $logger->pushHandler(new StreamHandler(storage_path('logs/app.log')));
            return $logger;
        });

        // Database service
        $this->singleton('database', function ($app) {
            return new DatabaseManager($app);
        });

        // Router service
        $this->singleton('router', function () {
            return new Router();
        });

        // Module manager
        $this->singleton('modules', function ($app) {
            return new ModuleManager($app);
        });

        // Theme manager
        $this->singleton('themes', function ($app) {
            return new ThemeManager($app);
        });

        // View engine
        $this->singleton('view', function ($app) {
            return new ViewEngine($app);
        });
    }

    /**
     * Boot application
     */
    protected function bootApplication()
    {
        if ($this->booted) {
            return;
        }

        // Load configuration
        $this->get('config')->load();

        // Boot database
        $this->get('database')->boot();

        // Boot modules
        $this->get('modules')->boot();

        // Boot themes
        $this->get('themes')->boot();

        // Load routes
        $this->loadRoutes();

        $this->booted = true;
    }

    /**
     * Load application routes
     */
    protected function loadRoutes()
    {
        $router = $this->get('router');

        // Load core routes
        if (file_exists(ROOT_PATH . '/app/routes.php')) {
            require ROOT_PATH . '/app/routes.php';
        }

        // Load module routes
        $this->get('modules')->loadRoutes($router);

        // Load theme routes
        $this->get('themes')->loadRoutes($router);
    }

    /**
     * Run application
     */
    public function run()
    {
        try {
            $request = Request::createFromGlobals();
            $response = $this->handle($request);
            $response->send();
        } catch (\Exception $e) {
            $this->get('logger')->error('Application error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle HTTP request
     */
    public function handle(Request $request)
    {
        $this->instance('request', $request);
        
        $router = $this->get('router');
        $response = $router->dispatch($request);
        
        if (!$response instanceof Response) {
            $response = new Response($response);
        }
        
        return $response;
    }

    /**
     * Get application version
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * Check if application is in debug mode
     */
    public function isDebug()
    {
        return config('app.debug', false);
    }

    /**
     * Get application environment
     */
    public function environment()
    {
        return config('app.env', 'production');
    }
}