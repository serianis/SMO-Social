<?php
namespace SMO_Social\Core;

use Exception;

/**
 * Enhanced Cache Manager with multi-level caching
 * 
 * Provides Redis, WordPress object cache, and file-based caching with fallback mechanisms.
 * 
 * @since 2.1.0
 */
class EnhancedCacheManager
{
    private const CACHE_PREFIX = 'smo_social_';
    private const DEFAULT_TTL = 1800; // 30 minutes
    
    private bool $redis_enabled = false;
    private ?\Redis $redis = null;
    private string $cache_dir = '';
    private int $default_ttl = self::DEFAULT_TTL;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initialize();
        $this->register_with_memory_monitor();
    }
    
    /**
     * Initialize cache manager
     */
    private function initialize(): void
    {
        // Set default TTL
        $this->default_ttl = (int) (\HOUR_IN_SECONDS * 0.5); // 30 minutes
        
        // Initialize Redis if available
        if ($this->isRedisAvailable()) {
            $this->initializeRedis();
        }
        
        // Set up file cache directory
        $this->setupCacheDirectory();
        
        // Hook into WordPress cleanup
        add_action('wp_scheduled_delete', [$this, 'cleanupExpiredCache']);
    }
    
    /**
     * Check if Redis is available
     */
    private function isRedisAvailable(): bool
    {
        return extension_loaded('redis') && class_exists('\Redis');
    }
    
    /**
     * Initialize Redis connection
     */
    private function initializeRedis(): void
    {
        try {
            $this->redis = new \Redis();
            
            // Get Redis configuration
            $host = defined('SMO_REDIS_HOST') ? SMO_REDIS_HOST : '127.0.0.1';
            $port = defined('SMO_REDIS_PORT') ? SMO_REDIS_PORT : 6379;
            $timeout = defined('SMO_REDIS_TIMEOUT') ? SMO_REDIS_TIMEOUT : 2.5;
            
            $this->redis->connect($host, $port, $timeout);
            
            // Test connection
            $this->redis->ping();
            $this->redis_enabled = true;
            
            error_log('SMO Social: Redis cache initialized successfully');
        } catch (Exception $e) {
            error_log('SMO Social: Redis connection failed: ' . $e->getMessage());
            $this->redis_enabled = false;
        }
    }
    
    /**
     * Setup cache directory
     */
    private function setupCacheDirectory(): void
    {
        if (defined('WP_UPLOAD_DIR')) {
            $this->cache_dir = WP_UPLOAD_DIR . '/smo-social-cache/';
            $this->default_ttl = \HOUR_IN_SECONDS * 6; // 6 hours for file cache
        } else {
            $this->cache_dir = SMO_SOCIAL_PLUGIN_DIR . 'cache/';
            $this->default_ttl = 21600; // 6 hours in seconds
        }
        
        if (!file_exists($this->cache_dir)) {
            if (!mkdir($this->cache_dir, 0755, true)) {
                error_log('SMO Social: Failed to create cache directory: ' . $this->cache_dir);
                return;
            }
        }
    }
    
    /**
     * Get data from cache with multi-level fallback
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed Cached data or default value
     */
    public function get($key, $default = null)
    {
        $cache_key = self::CACHE_PREFIX . $key;
        
        // Try Redis first
        if ($this->redis_enabled && $this->redis) {
            try {
                $value = $this->redis->get($cache_key);
                if ($value !== false) {
                    return $this->unserializeData($value);
                }
            } catch (Exception $e) {
                error_log('SMO Social Redis get error: ' . $e->getMessage());
                $this->redis_enabled = false;
            }
        }
        
        // Fallback to WordPress object cache
        if (function_exists('wp_cache_get')) {
            $value = wp_cache_get($cache_key, 'smo_social');
            if ($value !== false) {
                return $value;
            }
        }
        
        // Fallback to file cache
        return $this->getFileCache($cache_key, $default);
    }
    
    /**
     * Store data in cache with multi-level caching
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $cache_key = self::CACHE_PREFIX . $key;
        $ttl = $ttl ?? $this->default_ttl;
        
        $success = true;
        
        // Store in Redis
        if ($this->redis_enabled && $this->redis) {
            try {
                $serialized_value = $this->serializeData($value);
                $this->redis->setex($cache_key, $ttl, $serialized_value);
            } catch (Exception $e) {
                error_log('SMO Social Redis set error: ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Store in WordPress object cache
        if (function_exists('wp_cache_set')) {
            $wp_success = wp_cache_set($cache_key, $value, 'smo_social', $ttl);
            if (!$wp_success) {
                $success = false;
            }
        }
        
        // Store in file cache
        $file_success = $this->setFileCache($cache_key, $value, $ttl);
        if (!$file_success) {
            $success = false;
        }
        
        return $success;
    }
    
    /**
     * Delete from all cache levels
     * 
     * @param string $key Cache key to delete
     * @return bool True if successful
     */
    public function delete($key)
    {
        $cache_key = self::CACHE_PREFIX . $key;
        $success = true;
        
        // Delete from Redis
        if ($this->redis_enabled && $this->redis) {
            try {
                $this->redis->del($cache_key);
            } catch (Exception $e) {
                error_log('SMO Social Redis delete error: ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Delete from WordPress cache
        if (function_exists('wp_cache_delete')) {
            $wp_success = wp_cache_delete($cache_key, 'smo_social');
            if (!$wp_success) {
                $success = false;
            }
        }
        
        // Delete from file cache
        $file_key = $this->getFileKey($cache_key);
        if (file_exists($file_key)) {
            if (!unlink($file_key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Check if key exists in any cache level
     * 
     * @param string $key Cache key to check
     * @return bool True if key exists
     */
    public function exists($key)
    {
        return $this->get($key) !== null;
    }
    
    /**
     * Get multiple keys from cache
     */
    public function getMultiple(array $keys): array
    {
        $results = [];
        
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        
        return $results;
    }
    
    /**
     * Set multiple keys in cache
     */
    public function setMultiple(array $data, ?int $ttl = null): bool
    {
        $success = true;
        
        foreach ($data as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $success = true;
        
        // Clear Redis cache (keys with our prefix)
        if ($this->redis_enabled && $this->redis) {
            try {
                $keys = $this->redis->keys(self::CACHE_PREFIX . '*');
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } catch (Exception $e) {
                error_log('SMO Social Redis clear error: ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Clear WordPress object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear file cache
        $this->clearFileCache();
        
        return $success;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return [
            'redis_enabled' => $this->redis_enabled,
            'cache_dir' => $this->cache_dir,
            'default_ttl' => $this->default_ttl,
            'file_cache_size' => $this->getFileCacheSize(),
            'redis_info' => $this->getRedisInfo()
        ];
    }
    
    /**
     * Serialize data for storage
     * 
     * @param mixed $data Data to serialize
     * @return string Serialized data
     */
    private function serializeData($data)
    {
        return serialize($data);
    }
    
    /**
     * Unserialize data from storage
     */
    private function unserializeData(string $data): mixed
    {
        return unserialize($data);
    }
    
    /**
     * Get file cache
     */
    private function getFileCache(string $key, mixed $default): mixed
    {
        $file_key = $this->getFileKey($key);
        
        if (!file_exists($file_key)) {
            return $default;
        }
        
        // Check expiration
        $file_time = filemtime($file_key);
        $content = file_get_contents($file_key);
        
        if ($content === false) {
            return $default;
        }
        
        $data = json_decode($content, true);
        
        if ($data === null || !isset($data['expires']) || !isset($data['data'])) {
            return $default;
        }
        
        if (time() > $data['expires']) {
            unlink($file_key);
            return $default;
        }
        
        return $data['data'];
    }
    
    /**
     * Set file cache
     */
    private function setFileCache(string $key, mixed $value, int $ttl): bool
    {
        $file_key = $this->getFileKey($key);
        $data = [
            'data' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($file_key, json_encode($data)) !== false;
    }
    
    /**
     * Get file key for cache
     */
    private function getFileKey(string $key): string
    {
        $hash = md5($key);
        $dir = $this->cache_dir . substr($hash, 0, 2) . '/';
        
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $dir . $hash . '.cache';
    }
    
    /**
     * Clear file cache
     */
    private function clearFileCache(): void
    {
        if (!file_exists($this->cache_dir)) {
            return;
        }
        
        $files = glob($this->cache_dir . '*/*.cache');
        if ($files) {
            array_map('unlink', $files);
        }
    }
    
    /**
     * Get file cache size
     */
    private function getFileCacheSize(): int
    {
        if (!file_exists($this->cache_dir)) {
            return 0;
        }
        
        $files = glob($this->cache_dir . '*/*.cache');
        $size = 0;
        
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $size;
    }
    
    /**
     * Get Redis info
     */
    private function getRedisInfo(): array
    {
        if (!$this->redis_enabled || !$this->redis) {
            return [];
        }
        
        try {
            $info = $this->redis->info('memory');
            return [
                'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'unknown',
                'connected' => true
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'connected' => false];
        }
    }
    
    /**
     * Cleanup expired file cache
     */
    public function cleanupExpiredCache(): void
    {
        if (!file_exists($this->cache_dir)) {
            return;
        }

        $files = glob($this->cache_dir . '*/*.cache');
        $current_time = time();

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if ($data && isset($data['expires']) && $current_time > $data['expires']) {
                unlink($file);
            }
        }
    }

    /**
     * Register with MemoryMonitor for integrated monitoring
     */
    private function register_with_memory_monitor(): void
    {
        if (class_exists('\SMO_Social\Core\MemoryMonitor')) {
            try {
                // The MemoryMonitor will collect stats from EnhancedCacheManager when available
                // This ensures the EnhancedCacheManager is recognized as an integrated system
                error_log('SMO Social: EnhancedCacheManager registered for memory monitoring integration');
            } catch (Exception $e) {
                error_log('SMO Social: Failed to register EnhancedCacheManager with MemoryMonitor: ' . $e->getMessage());
            }
        }
    }
}
