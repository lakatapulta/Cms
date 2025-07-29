<?php

namespace FlexCMS\Core;

class Config
{
    /**
     * Configuration data
     */
    protected static $data = [];

    /**
     * Load configuration files
     */
    public function load()
    {
        $this->loadAppConfig();
        $this->loadDatabaseConfig();
    }

    /**
     * Load app configuration
     */
    protected function loadAppConfig()
    {
        static::$data['app'] = [
            'name' => env('APP_NAME', 'FlexCMS'),
            'env' => env('APP_ENV', 'production'),
            'debug' => env('APP_DEBUG', false),
            'url' => env('APP_URL', 'http://localhost'),
            'key' => env('APP_KEY'),
            'active_theme' => env('ACTIVE_THEME', 'default'),
            'modules_auto_discovery' => env('MODULES_AUTO_DISCOVERY', true),
            'timezone' => 'UTC',
            'locale' => 'en',
        ];
    }

    /**
     * Load database configuration
     */
    protected function loadDatabaseConfig()
    {
        static::$data['database'] = [
            'default' => env('DB_CONNECTION', 'mysql'),
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '3306'),
                    'database' => env('DB_DATABASE', 'flexcms'),
                    'username' => env('DB_USERNAME', 'root'),
                    'password' => env('DB_PASSWORD', ''),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => 'cms_',
                    'strict' => true,
                    'engine' => null,
                ],
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => storage_path('database.sqlite'),
                    'prefix' => 'cms_',
                ],
            ],
        ];

        static::$data['session'] = [
            'driver' => env('SESSION_DRIVER', 'file'),
            'lifetime' => env('SESSION_LIFETIME', 120),
            'path' => storage_path('sessions'),
        ];

        static::$data['cache'] => [
            'default' => env('CACHE_DRIVER', 'file'),
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => storage_path('cache'),
                ],
            ],
        ];

        static::$data['mail'] = [
            'default' => env('MAIL_MAILER', 'smtp'),
            'mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => env('MAIL_HOST'),
                    'port' => env('MAIL_PORT', 587),
                    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
                    'username' => env('MAIL_USERNAME'),
                    'password' => env('MAIL_PASSWORD'),
                ],
            ],
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@flexcms.local'),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'FlexCMS')),
            ],
        ];
    }

    /**
     * Get configuration value
     */
    public static function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = static::$data;

        foreach ($keys as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public static function set($key, $value)
    {
        $keys = explode('.', $key);
        $config = &static::$data;

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($config[$key]) || !is_array($config[$key])) {
                $config[$key] = [];
            }
            $config = &$config[$key];
        }

        $config[array_shift($keys)] = $value;
    }

    /**
     * Check if configuration key exists
     */
    public static function has($key)
    {
        return static::get($key) !== null;
    }

    /**
     * Get all configuration
     */
    public static function all()
    {
        return static::$data;
    }
}