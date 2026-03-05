<?php
/**
 * Simple file-based cache utility
 * 
 * Dependency injectable cache with file driver.
 * No magic, no over-engineering. Just works.
 * 
 * Usage:
 *   $cache = new Cache('/path/to/cache');
 *   $cache->set('key', $data, 3600);  // TTL in seconds
 *   $value = $cache->get('key');
 *   $cache->forget('key');
 * 
 * @package Monstein\Helpers
 */
namespace Monstein\Helpers;

class Cache
{
    /** @var string */
    private $path;

    /** @var string */
    private $prefix;

    /**
     * @param string $path   Directory to store cache files
     * @param string $prefix Key prefix to avoid collisions
     */
    public function __construct($path = null, $prefix = 'cache_')
    {
        $this->path = $path ?: sys_get_temp_dir() . '/monstein_cache';
        $this->prefix = $prefix;

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Get a cached value
     * 
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = $this->readFile($file);
        if ($data === false) {
            return $default;
        }

        // Check expiration
        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            $this->forget($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Store a value in cache
     * 
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl   Seconds until expiration (0 = never)
     * @return bool
     */
    public function set($key, $value, $ttl = 3600)
    {
        $file = $this->getFilePath($key);
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $data = [
            'expires' => $expires,
            'value' => $value,
        ];

        return $this->writeFile($file, $data);
    }

    /**
     * Check if key exists and is not expired
     * 
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * Remove a cached value
     * 
     * @param string $key
     * @return bool
     */
    public function forget($key)
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    /**
     * Get or set a value (cache-aside pattern)
     * 
     * @param string   $key
     * @param callable $callback
     * @param int      $ttl
     * @return mixed
     */
    public function remember($key, callable $callback, $ttl = 3600)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Clear all cache files
     * 
     * @return int Number of files deleted
     */
    public function flush()
    {
        $count = 0;
        $files = glob($this->path . '/' . $this->prefix . '*.cache');

        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Increment a numeric value
     * 
     * @param string $key
     * @param int    $step
     * @return int|false
     */
    public function increment($key, $step = 1)
    {
        $value = $this->get($key, 0);
        
        if (!is_numeric($value)) {
            return false;
        }

        $newValue = $value + $step;
        $this->set($key, $newValue, 0);

        return $newValue;
    }

    /**
     * Decrement a numeric value
     * 
     * @param string $key
     * @param int    $step
     * @return int|false
     */
    public function decrement($key, $step = 1)
    {
        return $this->increment($key, -$step);
    }

    /**
     * Get file path for a cache key
     * 
     * @param string $key
     * @return string
     */
    private function getFilePath($key)
    {
        $hash = md5($key);
        return $this->path . '/' . $this->prefix . $hash . '.cache';
    }

    /**
     * Read and unserialize cache file
     * 
     * @param string $file
     * @return array|false
     */
    private function readFile($file)
    {
        $content = @file_get_contents($file);
        
        if ($content === false) {
            return false;
        }

        $data = @unserialize($content);
        
        if ($data === false || !isset($data['expires'], $data['value'])) {
            return false;
        }

        return $data;
    }

    /**
     * Serialize and write cache file
     * 
     * @param string $file
     * @param array  $data
     * @return bool
     */
    private function writeFile($file, array $data)
    {
        $content = serialize($data);
        return file_put_contents($file, $content, LOCK_EX) !== false;
    }
}
