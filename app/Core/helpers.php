<?php

/**
 * FlexCMS Global Helper Functions
 */

if (!function_exists('env')) {
    /**
     * Get environment variable value
     */
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert boolean strings
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config($key, $default = null) {
        return FlexCMS\Core\Config::get($key, $default);
    }
}

if (!function_exists('app')) {
    /**
     * Get application instance or resolve from container
     */
    function app($abstract = null) {
        $instance = FlexCMS\Core\Application::getInstance();
        
        if (is_null($abstract)) {
            return $instance;
        }
        
        return $instance->get($abstract);
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url($path = '') {
        $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset($path) {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('route')) {
    /**
     * Generate route URL
     */
    function route($name, $parameters = []) {
        return FlexCMS\Core\Router::url($name, $parameters);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create redirect response
     */
    function redirect($url = null) {
        return new FlexCMS\Core\Response\RedirectResponse($url);
    }
}

if (!function_exists('view')) {
    /**
     * Render view
     */
    function view($template, $data = []) {
        return app('view')->render($template, $data);
    }
}

if (!function_exists('theme_path')) {
    /**
     * Get theme path
     */
    function theme_path($path = '') {
        $activeTheme = config('app.active_theme', 'default');
        return ROOT_PATH . '/themes/' . $activeTheme . '/' . ltrim($path, '/');
    }
}

if (!function_exists('module_path')) {
    /**
     * Get module path
     */
    function module_path($module, $path = '') {
        return ROOT_PATH . '/modules/' . $module . '/' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     */
    function storage_path($path = '') {
        return STORAGE_PATH . '/' . ltrim($path, '/');
    }
}

if (!function_exists('public_path')) {
    /**
     * Get public path
     */
    function public_path($path = '') {
        return PUBLIC_PATH . '/' . ltrim($path, '/');
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(...$vars) {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
    }
}

if (!function_exists('logger')) {
    /**
     * Get logger instance
     */
    function logger($message = null, $context = []) {
        $logger = app('logger');
        
        if (is_null($message)) {
            return $logger;
        }
        
        return $logger->info($message, $context);
    }
}