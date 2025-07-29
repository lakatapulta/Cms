<?php

namespace FlexCMS\Core\Module;

use FlexCMS\Core\Application;
use FlexCMS\Core\Router;

class ModuleManager
{
    /**
     * Application instance
     */
    protected $app;

    /**
     * Registered modules
     */
    protected $modules = [];

    /**
     * Active modules
     */
    protected $activeModules = [];

    /**
     * Boot status
     */
    protected $booted = false;

    /**
     * Create module manager
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Boot module system
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->discoverModules();
        $this->loadActiveModules();
        $this->bootModules();

        $this->booted = true;
    }

    /**
     * Discover available modules
     */
    protected function discoverModules()
    {
        $modulesPath = ROOT_PATH . '/modules';
        
        if (!is_dir($modulesPath)) {
            return;
        }

        $directories = glob($modulesPath . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $moduleName = basename($dir);
            $configFile = $dir . '/module.json';

            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                
                if ($config && $this->validateModuleConfig($config)) {
                    $this->modules[$moduleName] = array_merge($config, [
                        'path' => $dir,
                        'name' => $moduleName,
                    ]);
                }
            }
        }
    }

    /**
     * Validate module configuration
     */
    protected function validateModuleConfig($config)
    {
        $required = ['name', 'version', 'description', 'main'];
        
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Load active modules
     */
    protected function loadActiveModules()
    {
        // Get active modules from database or config
        $activeModules = $this->getActiveModulesFromDatabase();
        
        foreach ($activeModules as $moduleName) {
            if (isset($this->modules[$moduleName])) {
                $this->activeModules[$moduleName] = $this->modules[$moduleName];
            }
        }
    }

    /**
     * Get active modules from database
     */
    protected function getActiveModulesFromDatabase()
    {
        // For now, return default core modules
        // In a real implementation, this would query the database
        return ['core', 'users', 'posts', 'pages'];
    }

    /**
     * Boot individual modules
     */
    protected function bootModules()
    {
        foreach ($this->activeModules as $moduleName => $module) {
            $this->bootModule($moduleName, $module);
        }
    }

    /**
     * Boot single module
     */
    protected function bootModule($moduleName, $module)
    {
        $mainFile = $module['path'] . '/' . $module['main'];
        
        if (file_exists($mainFile)) {
            require_once $mainFile;
            
            $moduleClass = $this->getModuleClass($module);
            
            if (class_exists($moduleClass)) {
                $instance = new $moduleClass($this->app);
                
                if (method_exists($instance, 'boot')) {
                    $instance->boot();
                }
                
                // Register module instance
                $this->app->instance("module.{$moduleName}", $instance);
            }
        }
    }

    /**
     * Get module class name
     */
    protected function getModuleClass($module)
    {
        return isset($module['class']) 
            ? $module['class'] 
            : 'Modules\\' . ucfirst($module['name']) . '\\' . ucfirst($module['name']) . 'Module';
    }

    /**
     * Load module routes
     */
    public function loadRoutes(Router $router)
    {
        foreach ($this->activeModules as $moduleName => $module) {
            $routesFile = $module['path'] . '/routes.php';
            
            if (file_exists($routesFile)) {
                require $routesFile;
            }
        }
    }

    /**
     * Activate module
     */
    public function activate($moduleName)
    {
        if (!isset($this->modules[$moduleName])) {
            throw new \Exception("Module {$moduleName} not found");
        }

        if (isset($this->activeModules[$moduleName])) {
            return; // Already active
        }

        $module = $this->modules[$moduleName];
        
        // Check dependencies
        if (isset($module['dependencies'])) {
            $this->checkDependencies($module['dependencies']);
        }

        // Run module installation if needed
        $this->installModule($moduleName, $module);

        // Add to active modules
        $this->activeModules[$moduleName] = $module;
        $this->saveActiveModules();

        // Boot module if system is already booted
        if ($this->booted) {
            $this->bootModule($moduleName, $module);
        }
    }

    /**
     * Deactivate module
     */
    public function deactivate($moduleName)
    {
        if (!isset($this->activeModules[$moduleName])) {
            return; // Not active
        }

        unset($this->activeModules[$moduleName]);
        $this->saveActiveModules();

        // Unregister module from container
        if ($this->app->bound("module.{$moduleName}")) {
            $this->app->forgetInstance("module.{$moduleName}");
        }
    }

    /**
     * Install module
     */
    protected function installModule($moduleName, $module)
    {
        $installFile = $module['path'] . '/install.php';
        
        if (file_exists($installFile)) {
            require_once $installFile;
        }

        // Run module migrations
        $this->runModuleMigrations($moduleName);
    }

    /**
     * Run module migrations
     */
    protected function runModuleMigrations($moduleName)
    {
        $migrationsPath = module_path($moduleName, 'migrations');
        
        if (is_dir($migrationsPath)) {
            $database = $this->app->get('database');
            $migrationFiles = glob($migrationsPath . '/*.php');
            
            foreach ($migrationFiles as $file) {
                $migrationClass = pathinfo($file, PATHINFO_FILENAME);
                
                require_once $file;
                
                if (class_exists($migrationClass)) {
                    $migration = new $migrationClass;
                    $migration->up();
                }
            }
        }
    }

    /**
     * Check module dependencies
     */
    protected function checkDependencies($dependencies)
    {
        foreach ($dependencies as $dependency) {
            if (!isset($this->activeModules[$dependency])) {
                throw new \Exception("Module dependency {$dependency} is not active");
            }
        }
    }

    /**
     * Save active modules to database
     */
    protected function saveActiveModules()
    {
        // In a real implementation, this would save to database
        // For now, we'll use a simple file storage
        $activeModulesList = array_keys($this->activeModules);
        file_put_contents(
            storage_path('active_modules.json'),
            json_encode($activeModulesList, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Get all available modules
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Get active modules
     */
    public function getActiveModules()
    {
        return array_keys($this->activeModules);
    }

    /**
     * Check if module is active
     */
    public function isActive($moduleName)
    {
        return isset($this->activeModules[$moduleName]);
    }

    /**
     * Get module info
     */
    public function getModule($moduleName)
    {
        return $this->modules[$moduleName] ?? null;
    }
}