<?php

namespace FlexCMS\Services;

class CacheService
{
    protected $driver;
    protected $prefix;
    protected $defaultTtl = 3600; // 1 hour

    public function __construct()
    {
        $this->driver = config('cache.driver', 'file');
        $this->prefix = config('cache.prefix', 'flexcms_');
        $this->initializeDriver();
    }

    /**
     * Initialize cache driver
     */
    protected function initializeDriver()
    {
        switch ($this->driver) {
            case 'redis':
                if (extension_loaded('redis')) {
                    $this->redis = new \Redis();
                    $this->redis->connect(
                        config('cache.redis.host', '127.0.0.1'),
                        config('cache.redis.port', 6379)
                    );
                    if (config('cache.redis.password')) {
                        $this->redis->auth(config('cache.redis.password'));
                    }
                } else {
                    $this->driver = 'file';
                }
                break;
            
            case 'memcached':
                if (extension_loaded('memcached')) {
                    $this->memcached = new \Memcached();
                    $this->memcached->addServer(
                        config('cache.memcached.host', '127.0.0.1'),
                        config('cache.memcached.port', 11211)
                    );
                } else {
                    $this->driver = 'file';
                }
                break;
            
            default:
                $this->driver = 'file';
                $this->ensureCacheDirectory();
                break;
        }
    }

    /**
     * Get cached item
     */
    public function get($key, $default = null)
    {
        $key = $this->prefix . $key;
        
        switch ($this->driver) {
            case 'redis':
                $value = $this->redis->get($key);
                return $value !== false ? unserialize($value) : $default;
            
            case 'memcached':
                $value = $this->memcached->get($key);
                return $value !== false ? $value : $default;
            
            default:
                return $this->getFromFile($key, $default);
        }
    }

    /**
     * Store item in cache
     */
    public function put($key, $value, $ttl = null)
    {
        $key = $this->prefix . $key;
        $ttl = $ttl ?: $this->defaultTtl;
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis->setex($key, $ttl, serialize($value));
            
            case 'memcached':
                return $this->memcached->set($key, $value, $ttl);
            
            default:
                return $this->putToFile($key, $value, $ttl);
        }
    }

    /**
     * Remember - get or store
     */
    public function remember($key, $callback, $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->put($key, $value, $ttl);
        }
        
        return $value;
    }

    /**
     * Cache posts
     */
    public function cachePosts($posts)
    {
        return $this->remember('posts_latest', function() use ($posts) {
            return $posts->take(10)->toArray();
        }, 1800); // 30 minutes
    }

    /**
     * Cache page content
     */
    public function cachePage($slug, $content)
    {
        return $this->put("page_{$slug}", $content, 3600);
    }

    /**
     * Cache navigation menu
     */
    public function cacheMenu($menuName, $items)
    {
        return $this->put("menu_{$menuName}", $items, 7200); // 2 hours
    }

    /**
     * Cache database queries
     */
    public function cacheQuery($sql, $bindings, $callback, $ttl = 300)
    {
        $key = 'query_' . md5($sql . serialize($bindings));
        return $this->remember($key, $callback, $ttl);
    }

    /**
     * Clear specific cache
     */
    public function forget($key)
    {
        $key = $this->prefix . $key;
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis->del($key);
            
            case 'memcached':
                return $this->memcached->delete($key);
            
            default:
                return $this->deleteFile($key);
        }
    }

    /**
     * Clear all cache
     */
    public function flush()
    {
        switch ($this->driver) {
            case 'redis':
                return $this->redis->flushAll();
            
            case 'memcached':
                return $this->memcached->flush();
            
            default:
                return $this->clearFileCache();
        }
    }

    /**
     * File cache operations
     */
    protected function getFromFile($key, $default)
    {
        $file = $this->getCacheFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $content = file_get_contents($file);
        $data = unserialize($content);
        
        if ($data['expires'] < time()) {
            unlink($file);
            return $default;
        }
        
        return $data['value'];
    }

    protected function putToFile($key, $value, $ttl)
    {
        $file = $this->getCacheFilePath($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }

    protected function getCacheFilePath($key)
    {
        return storage_path('cache/' . md5($key) . '.cache');
    }

    protected function ensureCacheDirectory()
    {
        $dir = storage_path('cache');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function deleteFile($key)
    {
        $file = $this->getCacheFilePath($key);
        return file_exists($file) ? unlink($file) : true;
    }

    protected function clearFileCache()
    {
        $dir = storage_path('cache');
        if (is_dir($dir)) {
            $files = glob($dir . '/*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        return true;
    }

    /**
     * Cache statistics
     */
    public function getStats()
    {
        switch ($this->driver) {
            case 'redis':
                return $this->redis->info();
            
            case 'memcached':
                return $this->memcached->getStats();
            
            default:
                $dir = storage_path('cache');
                $files = is_dir($dir) ? glob($dir . '/*.cache') : [];
                return [
                    'files' => count($files),
                    'size' => array_sum(array_map('filesize', $files))
                ];
        }
    }
}