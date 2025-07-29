<?php

namespace FlexCMS\Core\Theme;

use FlexCMS\Core\Application;
use FlexCMS\Core\Router;

class ThemeManager
{
    /**
     * Application instance
     */
    protected $app;

    /**
     * Available themes
     */
    protected $themes = [];

    /**
     * Active theme
     */
    protected $activeTheme;

    /**
     * Boot status
     */
    protected $booted = false;

    /**
     * Create theme manager
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Boot theme system
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->discoverThemes();
        $this->loadActiveTheme();

        $this->booted = true;
    }

    /**
     * Discover available themes
     */
    protected function discoverThemes()
    {
        $themesPath = ROOT_PATH . '/themes';
        
        if (!is_dir($themesPath)) {
            return;
        }

        $directories = glob($themesPath . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $themeName = basename($dir);
            $configFile = $dir . '/theme.json';

            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                
                if ($config && $this->validateThemeConfig($config)) {
                    $this->themes[$themeName] = array_merge($config, [
                        'path' => $dir,
                        'name' => $themeName,
                    ]);
                }
            }
        }
    }

    /**
     * Validate theme configuration
     */
    protected function validateThemeConfig($config)
    {
        $required = ['name', 'version', 'description'];
        
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Load active theme
     */
    protected function loadActiveTheme()
    {
        $activeThemeName = config('app.active_theme', 'default');
        
        if (isset($this->themes[$activeThemeName])) {
            $this->activeTheme = $this->themes[$activeThemeName];
        } else {
            // Fallback to first available theme
            $this->activeTheme = reset($this->themes);
        }

        // Load theme functions
        if ($this->activeTheme) {
            $this->loadThemeFunctions();
        }
    }

    /**
     * Load theme functions
     */
    protected function loadThemeFunctions()
    {
        $functionsFile = $this->activeTheme['path'] . '/functions.php';
        
        if (file_exists($functionsFile)) {
            require_once $functionsFile;
        }
    }

    /**
     * Load theme routes
     */
    public function loadRoutes(Router $router)
    {
        if (!$this->activeTheme) {
            return;
        }

        $routesFile = $this->activeTheme['path'] . '/routes.php';
        
        if (file_exists($routesFile)) {
            require $routesFile;
        }
    }

    /**
     * Get template path
     */
    public function getTemplatePath($template = '')
    {
        if (!$this->activeTheme) {
            return null;
        }

        $path = $this->activeTheme['path'];
        
        if ($template) {
            $path .= '/templates/' . ltrim($template, '/');
        }

        return $path;
    }

    /**
     * Check if template exists
     */
    public function templateExists($template)
    {
        $templatePath = $this->getTemplatePath($template);
        return $templatePath && file_exists($templatePath);
    }

    /**
     * Get asset URL for active theme
     */
    public function getAssetUrl($asset)
    {
        if (!$this->activeTheme) {
            return null;
        }

        $themeName = $this->activeTheme['name'];
        return url("themes/{$themeName}/assets/" . ltrim($asset, '/'));
    }

    /**
     * Get theme info
     */
    public function getThemeInfo($themeName = null)
    {
        if ($themeName) {
            return $this->themes[$themeName] ?? null;
        }

        return $this->activeTheme;
    }

    /**
     * Get all themes
     */
    public function getThemes()
    {
        return $this->themes;
    }

    /**
     * Activate theme
     */
    public function activate($themeName)
    {
        if (!isset($this->themes[$themeName])) {
            throw new \Exception("Theme {$themeName} not found");
        }

        $this->activeTheme = $this->themes[$themeName];
        
        // Update configuration
        config(['app.active_theme' => $themeName]);
        
        // Save to environment or database
        $this->saveActiveTheme($themeName);

        // Reload theme functions
        $this->loadThemeFunctions();
    }

    /**
     * Save active theme
     */
    protected function saveActiveTheme($themeName)
    {
        // In a real implementation, this would save to database
        // For now, we'll update the .env file
        $envFile = ROOT_PATH . '/.env';
        
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            $envContent = preg_replace(
                '/^ACTIVE_THEME=.*$/m',
                "ACTIVE_THEME={$themeName}",
                $envContent
            );
            file_put_contents($envFile, $envContent);
        }
    }

    /**
     * Get active theme name
     */
    public function getActiveTheme()
    {
        return $this->activeTheme ? $this->activeTheme['name'] : null;
    }

    /**
     * Get theme configuration
     */
    public function getThemeConfig($key = null, $default = null)
    {
        if (!$this->activeTheme) {
            return $default;
        }

        if ($key) {
            return $this->activeTheme[$key] ?? $default;
        }

        return $this->activeTheme;
    }

    /**
     * Check if theme supports feature
     */
    public function supports($feature)
    {
        if (!$this->activeTheme) {
            return false;
        }

        $supports = $this->activeTheme['supports'] ?? [];
        return in_array($feature, $supports);
    }

    /**
     * Add theme support
     */
    public function addSupport($feature)
    {
        if (!$this->activeTheme) {
            return;
        }

        $supports = $this->activeTheme['supports'] ?? [];
        
        if (!in_array($feature, $supports)) {
            $supports[] = $feature;
            $this->activeTheme['supports'] = $supports;
        }
    }

    /**
     * Remove theme support
     */
    public function removeSupport($feature)
    {
        if (!$this->activeTheme) {
            return;
        }

        $supports = $this->activeTheme['supports'] ?? [];
        $key = array_search($feature, $supports);
        
        if ($key !== false) {
            unset($supports[$key]);
            $this->activeTheme['supports'] = array_values($supports);
        }
    }
}