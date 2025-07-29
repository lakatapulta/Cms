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

if (!function_exists('auth')) {
    /**
     * Get authentication service
     */
    function auth() {
        return app('auth');
    }
}

if (!function_exists('user')) {
    /**
     * Get authenticated user
     */
    function user() {
        return auth()->user();
    }
}

if (!function_exists('user_can')) {
    /**
     * Check if user has permission
     */
    function user_can($permission, $user = null) {
        $user = $user ?: user();
        return $user ? app('roles')->userCan($user, $permission) : false;
    }
}

if (!function_exists('user_has_role')) {
    /**
     * Check if user has role
     */
    function user_has_role($role, $user = null) {
        $user = $user ?: user();
        return $user ? app('roles')->userHasRole($user, $role) : false;
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if user is admin
     */
    function is_admin($user = null) {
        $user = $user ?: user();
        return $user ? app('roles')->isAdmin($user) : false;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate CSRF token
     */
    function csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF input field
     */
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('now')) {
    /**
     * Get current datetime
     */
    function now() {
        return new \DateTime();
    }
}

if (!function_exists('cache')) {
    /**
     * Get cache service
     */
    function cache() {
        return app('cache');
    }
}

if (!function_exists('backup')) {
    /**
     * Get backup service
     */
    function backup() {
        return app('backup');
    }
}

if (!function_exists('analytics')) {
    /**
     * Get analytics service
     */
    function analytics() {
        return app('analytics');
    }
}

if (!function_exists('forms')) {
    /**
     * Get form builder service
     */
    function forms() {
        return app('forms');
    }
}

if (!function_exists('integrations')) {
    /**
     * Get integration service
     */
    function integrations() {
        return app('integrations');
    }
}

if (!function_exists('notify')) {
    /**
     * Send notification
     */
    function notify($type, $data, $channels = ['email'], $users = null) {
        return app('notifications')->send($type, $data, $channels, $users);
    }
}

if (!function_exists('media')) {
    /**
     * Get media service
     */
    function media() {
        return app('media');
    }
}

if (!function_exists('seo')) {
    /**
     * Get SEO service
     */
    function seo() {
        return app('seo');
    }
}