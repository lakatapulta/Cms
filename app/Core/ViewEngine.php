<?php

namespace FlexCMS\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use FlexCMS\Core\Application;

class ViewEngine
{
    /**
     * Application instance
     */
    protected $app;

    /**
     * Twig environment
     */
    protected $twig;

    /**
     * Template paths
     */
    protected $paths = [];

    /**
     * Global variables
     */
    protected $globals = [];

    /**
     * Create view engine
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->setupPaths();
        $this->setupTwig();
        $this->registerGlobals();
        $this->registerFunctions();
    }

    /**
     * Setup template paths
     */
    protected function setupPaths()
    {
        // Add theme template path
        $themeManager = $this->app->get('themes');
        $activeTheme = $themeManager->getActiveTheme();
        
        if ($activeTheme) {
            $this->paths[] = theme_path('templates');
        }

        // Add core template path
        $this->paths[] = APP_PATH . '/templates';
        
        // Add module template paths
        $moduleManager = $this->app->get('modules');
        $activeModules = $moduleManager->getActiveModules();
        
        foreach ($activeModules as $module) {
            $modulePath = module_path($module, 'templates');
            if (is_dir($modulePath)) {
                $this->paths[] = $modulePath;
            }
        }
    }

    /**
     * Setup Twig environment
     */
    protected function setupTwig()
    {
        $loader = new FilesystemLoader($this->paths);
        
        $options = [
            'cache' => storage_path('cache/views'),
            'debug' => config('app.debug', false),
            'auto_reload' => config('app.debug', false),
        ];

        $this->twig = new Environment($loader, $options);

        if ($options['debug']) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    /**
     * Register global variables
     */
    protected function registerGlobals()
    {
        $this->twig->addGlobal('app', $this->app);
        $this->twig->addGlobal('config', function($key, $default = null) {
            return config($key, $default);
        });
        
        // Add theme globals
        $themeManager = $this->app->get('themes');
        $this->twig->addGlobal('theme', $themeManager);
        
        // Add request global if available
        if ($this->app->bound('request')) {
            $this->twig->addGlobal('request', $this->app->get('request'));
        }
    }

    /**
     * Register Twig functions
     */
    protected function registerFunctions()
    {
        // URL function
        $this->twig->addFunction(new \Twig\TwigFunction('url', function($path = '') {
            return url($path);
        }));

        // Asset function
        $this->twig->addFunction(new \Twig\TwigFunction('asset', function($path) {
            return asset($path);
        }));

        // Route function
        $this->twig->addFunction(new \Twig\TwigFunction('route', function($name, $parameters = []) {
            return route($name, $parameters);
        }));

        // Theme asset function
        $this->twig->addFunction(new \Twig\TwigFunction('theme_asset', function($path) {
            $themeManager = $this->app->get('themes');
            return $themeManager->getAssetUrl($path);
        }));

        // Config function
        $this->twig->addFunction(new \Twig\TwigFunction('config', function($key, $default = null) {
            return config($key, $default);
        }));

        // Include function for modules
        $this->twig->addFunction(new \Twig\TwigFunction('include_module', function($module, $template, $data = []) {
            $modulePath = module_path($module, 'templates/' . $template);
            if (file_exists($modulePath)) {
                return $this->render($template, $data, $module);
            }
            return '';
        }));

        // Menu function
        $this->twig->addFunction(new \Twig\TwigFunction('menu', function($name) {
            // This would integrate with a menu system
            return $this->renderMenu($name);
        }));

        // Widget function
        $this->twig->addFunction(new \Twig\TwigFunction('widget', function($name, $args = []) {
            return $this->renderWidget($name, $args);
        }));
    }

    /**
     * Render template
     */
    public function render($template, $data = [], $module = null)
    {
        // Add module-specific template path if specified
        if ($module) {
            $modulePath = module_path($module, 'templates');
            if (is_dir($modulePath)) {
                $loader = $this->twig->getLoader();
                $loader->prependPath($modulePath);
            }
        }

        // Merge global data
        $data = array_merge($this->globals, $data);

        try {
            return $this->twig->render($template, $data);
        } catch (\Exception $e) {
            if (config('app.debug', false)) {
                throw $e;
            }
            
            logger()->error('Template rendering error: ' . $e->getMessage(), [
                'template' => $template,
                'module' => $module,
                'exception' => $e,
            ]);
            
            return '<div class="error">Template error</div>';
        }
    }

    /**
     * Check if template exists
     */
    public function exists($template, $module = null)
    {
        if ($module) {
            $modulePath = module_path($module, 'templates/' . $template);
            return file_exists($modulePath);
        }

        return $this->twig->getLoader()->exists($template);
    }

    /**
     * Add global variable
     */
    public function addGlobal($name, $value)
    {
        $this->globals[$name] = $value;
        $this->twig->addGlobal($name, $value);
    }

    /**
     * Add template path
     */
    public function addPath($path, $namespace = null)
    {
        $this->paths[] = $path;
        $this->twig->getLoader()->addPath($path, $namespace);
    }

    /**
     * Render menu
     */
    protected function renderMenu($name)
    {
        // This would integrate with a menu management system
        // For now, return empty string
        return '';
    }

    /**
     * Render widget
     */
    protected function renderWidget($name, $args = [])
    {
        // This would integrate with a widget system
        // For now, return empty string
        return '';
    }

    /**
     * Get Twig environment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * Extend Twig with custom functions/filters
     */
    public function extend(\Closure $callback)
    {
        $callback($this->twig);
    }
}