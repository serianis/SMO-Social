<?php
/**
 * Bounded Redis Cache for SMO Social Core
 *
 * Implements bounded cache sizes with automatic eviction policies
 * using LRU (Least Recently Used) eviction strategy for Redis caching
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

require_once __DIR__ . '/CacheObjectPool.php';
require_once __DIR__ . '/CacheConnectionCleanup.php';

class BoundedRedisCache {
    private ?object $redis = null;
    private bool $redis_available = false;
    private int $default_ttl = 3600; // 1 hour
    private $cache_object_pool = null;
    private $cache_metadata_stream;
    private $batch_size;
    private $max_memory_usage;
    private $max_cache_size;
    private $current_cache_size;
    private $lru_cache = [];
    private $lru_timestamp = 0;

    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'evictions' => 0,
        'errors' => 0,
        'memory_evictions' => 0,
        'size_evictions' => 0,
        'redis_operations' => 0
    ];

    /**
     * Constructor
     *
     * @param object|null $redis_instance Redis instance
     * @param int $max_cache_size Maximum cache size in bytes
     * @param int $max_memory_usage Maximum memory usage in MB
     */
    public function __construct(?object $redis_instance = null, $max_cache_size = 52428800, $max_memory_usage = 50) {
        $this->max_cache_size = $max_cache_size; // Default 50MB for Redis
        $this->current_cache_size = 0;

        if ($redis_instance && method_exists($redis_instance, 'ping')) {
            try {
                if ($redis_instance->ping()) {
                    $this->redis = $redis_instance;
                    $this->redis_available = true;
                    error_log('SMO Social BoundedRedisCache: Redis connection established');
                }
            } catch (\Exception $e) {
                // Redis connection failed, fall back to WordPress cache
                error_log('SMO Social BoundedRedisCache: Redis connection failed - ' . $e->getMessage());
            }
        }

        $this->initialize_cache_metadata_stream();
        $this->initialize_lru_cache();
    }

    /**
     * Initialize cache metadata stream
     */
    private function initialize_cache_metadata_stream() {
        // Initialize cache metadata stream with default configuration
        $this->batch_size = 1000;
        $this->max_memory_usage = $this->max_memory_usage;
        $this->cache_metadata_stream = new \SMO_Social\Core\CacheMetadataStream($this->batch_size, $this->max_memory_usage);
    }

    /**
     * Initialize LRU cache tracking
     */
    private function initialize_lru_cache() {
        $this->lru_cache = [];
        $this->lru_timestamp = time();
    }

    /**
     * Set batch processing configuration
     */
    public function set_batch_config($batch_size, $max_memory_usage) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage;
        $this->cache_metadata_stream->set_batch_config($batch_size, $max_memory_usage);
    }

    /**
     * Get current batch processing configuration
     */
    public function get_batch_config() {
        return $this->cache_metadata_stream->get_batch_config();
    }

    /**
     * Set maximum cache size
     *
     * @param int $max_cache_size Maximum cache size in bytes
     */
    public function set_max_cache_size($max_cache_size) {
        $this->max_cache_size = max(10485760, $max_cache_size); // Minimum 10MB
        $this->enforce_cache_size_limits();
    }

    /**
     * Get maximum cache size
     *
     * @return int Maximum cache size in bytes
     */
    public function get_max_cache_size() {
        return $this->max_cache_size;
    }

    /**
     * Get current cache size
     *
     * @return int Current cache size in bytes
     */
    public function get_current_cache_size() {
        return $this->current_cache_size;
    }

    /**
     * Initialize cache object pool
     */
    private function initialize_cache_object_pool() {
        if ($this->cache_object_pool === null) {
            $pool_size = 15; // Default pool size for core cache

            // Get pool size from settings if available
            $settings = get_option('smo_social_settings', []);
            if (isset($settings['core_cache_pool_size']) && is_numeric($settings['core_cache_pool_size'])) {
                $pool_size = max(5, min(30, intval($settings['core_cache_pool_size'])));
            }

            $this->cache_object_pool = new CacheObjectPool($pool_size);
        }
    }

    /**
     * Update LRU cache tracking
     */
    private function update_lru_cache($key) {
        $this->lru_timestamp++;

        // Update or add to LRU cache
        $this->lru_cache[$key] = $this->lru_timestamp;

        // Enforce LRU cache size limits
        $this->enforce_lru_cache_limits();
    }

    /**
     * Enforce LRU cache size limits
     */
    private function enforce_lru_cache_limits() {
        // If LRU cache grows too large, trim it
        if (count($this->lru_cache) > 10000) {
            // Sort by timestamp and keep only the most recent 5000
            asort($this->lru_cache);
            $this->lru_cache = array_slice($this->lru_cache, -5000, 5000, true);
        }
    }

    /**
     * Enforce cache size limits with automatic eviction
     */
    private function enforce_cache_size_limits() {
        // For Redis, we need to estimate size and evict if needed
        if ($this->current_cache_size > $this->max_cache_size) {
            $this->perform_lru_eviction();
        }
    }

    /**
     * Perform LRU eviction to reduce cache size for Redis
     */
    private function perform_lru_eviction() {
        if (!$this->redis_available) {
            return;
        }

        // Sort LRU cache by access time (oldest first)
        asort($this->lru_cache);

        $evicted_count = 0;
        $target_evictions = min(100, count($this->lru_cache) / 2); // Evict up to 50% of cache

        try {
            foreach ($this->lru_cache as $cache_key => $timestamp) {
                if ($evicted_count >= $target_evictions) {
                    break;
                }

                // Delete from Redis
                $this->redis->del($this->normalize_key($cache_key));
                unset($this->lru_cache[$cache_key]);
                $evicted_count++;
                $this->stats['evictions']++;
                $this->stats['size_evictions']++;
            }

            error_log("SMO BoundedRedisCache: LRU eviction completed. Evicted $evicted_count Redis keys");

            // Update current cache size estimate
            $this->current_cache_size = $this->estimate_redis_cache_size();

        } catch (\Exception $e) {
            error_log('SMO Social BoundedRedisCache: LRU eviction error - ' . $e->getMessage());
            $this->stats['errors']++;
        }
    }

    /**
     * Estimate Redis cache size
     */
    private function estimate_redis_cache_size() {
        if (!$this->redis_available) {
            return 0;
        }

        try {
            // Get Redis memory usage info
            $info = $this->redis->info('memory');
            if (isset($info['used_memory'])) {
                return $info['used_memory'];
            }
        } catch (\Exception $e) {
            error_log('SMO Social BoundedRedisCache: Failed to get Redis memory info - ' . $e->getMessage());
        }

        // Fallback: estimate based on key count
        try {
            $key_count = $this->redis->dbsize();
            return $key_count * 1024; // Average 1KB per key
        } catch (\Exception $e) {
            error_log('SMO Social BoundedRedisCache: Failed to get Redis key count - ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Normalize cache key to prevent conflicts
     */
    private function normalize_key(string $key): string {
        // Remove special characters and ensure consistent formatting
        return 'smo_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }

    /**
     * Get data from cache (tries Redis first, then WordPress cache) with LRU tracking
     */
    public function get(string $key, $default = null) {
        $cache_key = $this->normalize_key($key);
        $this->stats['redis_operations']++;

        // Try Redis first
        if ($this->redis_available) {
            try {
                $cached = $this->redis->get($cache_key);
                if ($cached !== false && $cached !== null) {
                    // Update LRU tracking
                    $this->update_lru_cache($key);
                    $this->stats['hits']++;
                    return json_decode($cached, true);
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Redis get error - ' . $e->getMessage());
                $this->stats['errors']++;
            }
        }

        // Fallback to WordPress object cache
        $cached = wp_cache_get($cache_key, 'smo_social');
        if ($cached !== false) {
            $this->stats['hits']++;
            return $cached;
        }

        $this->stats['misses']++;
        return $default;
    }

    /**
     * Set data to cache (Redis + WordPress cache) with size enforcement
     */
    public function set(string $key, $value, int $ttl = null): bool {
        $cache_key = $this->normalize_key($key);
        $ttl = $ttl ?? $this->default_ttl;
        $success = true;

        // Estimate data size
        $data_size = strlen(json_encode($value));

        // Check if we need to evict before adding new data
        if ($this->current_cache_size + $data_size > $this->max_cache_size) {
            $this->enforce_cache_size_limits();
        }

        // Set in Redis
        if ($this->redis_available) {
            try {
                $serialized = json_encode($value);
                if ($serialized !== false) {
                    if (!$this->redis->setex($cache_key, $ttl, $serialized)) {
                        $success = false;
                    } else {
                        // Update LRU tracking
                        $this->update_lru_cache($key);
                        $this->current_cache_size += $data_size;
                    }
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Redis set error - ' . $e->getMessage());
                $success = false;
                $this->stats['errors']++;
            }
        }

        // Set in WordPress cache
        if (!wp_cache_set($cache_key, $value, 'smo_social', $ttl)) {
            $success = false;
        }

        if ($success) {
            $this->stats['sets']++;
        }

        return $success;
    }

    /**
     * Delete data from cache
     */
    public function delete(string $key): bool {
        $cache_key = $this->normalize_key($key);
        $success = true;

        // Delete from Redis
        if ($this->redis_available) {
            try {
                $key_size = $this->redis->strlen($cache_key) ?: 0;
                if (!$this->redis->del($cache_key)) {
                    $success = false;
                } else {
                    $this->current_cache_size = max(0, $this->current_cache_size - $key_size);
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Redis delete error - ' . $e->getMessage());
                $success = false;
                $this->stats['errors']++;
            }
        }

        // Delete from WordPress cache
        if (!wp_cache_delete($cache_key, 'smo_social')) {
            $success = false;
        }

        // Remove from LRU tracking
        unset($this->lru_cache[$key]);

        if ($success) {
            $this->stats['deletes']++;
        }

        return $success;
    }

    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool {
        $cache_key = $this->normalize_key($key);

        // Check Redis first
        if ($this->redis_available) {
            try {
                if ($this->redis->exists($cache_key)) {
                    // Update LRU tracking
                    $this->update_lru_cache($key);
                    return true;
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Redis exists error - ' . $e->getMessage());
                $this->stats['errors']++;
            }
        }

        // Check WordPress cache
        $exists = wp_cache_get($cache_key, 'smo_social') !== false;
        if ($exists) {
            $this->update_lru_cache($key);
        }

        return $exists;
    }

    /**
     * Clear all cache with bounded size enforcement
     */
    public function clear(): bool {
        $success = true;

        // Clear Redis cache (all keys with our prefix)
        if ($this->redis_available) {
            try {
                $pattern = $this->normalize_key('*');
                $keys = $this->redis->keys($pattern);
                if (!empty($keys) && !$this->redis->del(...$keys)) {
                    $success = false;
                } else {
                    // Reset cache size tracking
                    $this->current_cache_size = 0;
                    $this->initialize_lru_cache();
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Redis clear error - ' . $e->getMessage());
                $success = false;
                $this->stats['errors']++;
            }
        }

        // Note: WordPress object cache doesn't support clearing by prefix
        // This would need to be implemented differently for production

        return $success;
    }

    /**
     * Get multiple values at once with LRU tracking
     */
    public function get_multiple(array $keys, $default = null): array {
        $results = [];
        $missing_keys = [];

        // Try to get as many as possible from Redis
        if ($this->redis_available) {
            try {
                $cache_keys = array_map([$this, 'normalize_key'], $keys);
                $cached_data = $this->redis->mget($cache_keys);

                foreach ($keys as $index => $key) {
                    if ($cached_data[$index] !== null && $cached_data[$index] !== false) {
                        $results[$key] = json_decode($cached_data[$index], true);
                        // Update LRU tracking for successful gets
                        $this->update_lru_cache($key);
                        $this->stats['hits']++;
                    } else {
                        $missing_keys[] = $key;
                        $this->stats['misses']++;
                    }
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Redis mget error - ' . $e->getMessage());
                $missing_keys = $keys; // Fall back to individual gets
                $this->stats['errors']++;
            }
        } else {
            $missing_keys = $keys;
        }

        // Get missing keys from WordPress cache
        foreach ($missing_keys as $key) {
            $cached = wp_cache_get($this->normalize_key($key), 'smo_social');
            if ($cached !== false) {
                $results[$key] = $cached;
                $this->update_lru_cache($key);
                $this->stats['hits']++;
            } else {
                $this->stats['misses']++;
            }
        }

        return $results;
    }

    /**
     * Set multiple values at once with size enforcement
     */
    public function set_multiple(array $data, int $ttl = null): array {
        $results = [];
        $ttl = $ttl ?? $this->default_ttl;

        // Calculate total size of data to be added
        $total_data_size = 0;
        foreach ($data as $value) {
            $total_data_size += strlen(json_encode($value));
        }

        // Check if we need to evict before adding new data
        if ($this->current_cache_size + $total_data_size > $this->max_cache_size) {
            $this->enforce_cache_size_limits();
        }

        // Set in Redis
        if ($this->redis_available) {
            try {
                $pipeline = $this->redis->pipeline();
                foreach ($data as $key => $value) {
                    $pipeline->setex($this->normalize_key($key), $ttl, json_encode($value));
                }
                $redis_results = $pipeline->exec();

                foreach ($redis_results as $index => $result) {
                    $key = array_keys($data)[$index];
                    $results[$key] = (bool) $result;
                    if ($result) {
                        $this->update_lru_cache($key);
                        $this->current_cache_size += strlen(json_encode($data[$key]));
                        $this->stats['sets']++;
                    }
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Redis mset error - ' . $e->getMessage());
                // Fall back to individual sets
                foreach ($data as $key => $value) {
                    $results[$key] = $this->set($key, $value, $ttl);
                }
                $this->stats['errors']++;
            }
        } else {
            // Use WordPress cache for all
            foreach ($data as $key => $value) {
                $results[$key] = $this->set($key, $value, $ttl);
            }
        }

        return $results;
    }

    /**
     * Get cache statistics with Redis-specific metrics
     */
    public function get_stats(): array {
        $stats = [
            'redis_available' => $this->redis_available,
            'redis_stats' => null,
            'cache_keys' => 0,
            'current_cache_size' => $this->current_cache_size,
            'max_cache_size' => $this->max_cache_size,
            'cache_size_percentage' => $this->max_cache_size > 0 ? ($this->current_cache_size / $this->max_cache_size) * 100 : 0,
            'memory_usage' => memory_get_usage(true) / (1024 * 1024),
            'hit_rate' => 0,
            'miss_rate' => 0,
            'operations' => $this->stats,
            'lru_cache_size' => count($this->lru_cache)
        ];

        if ($this->redis_available) {
            try {
                $redis_info = $this->redis->info();
                $stats['redis_stats'] = [
                    'connected_clients' => $redis_info['connected_clients'] ?? 0,
                    'used_memory' => $redis_info['used_memory'] ?? 0,
                    'used_memory_rss' => $redis_info['used_memory_rss'] ?? 0,
                    'keys' => $this->redis->dbsize() ?? 0,
                    'uptime' => $redis_info['uptime_in_seconds'] ?? 0
                ];

                $stats['redis_connected'] = true;
                $stats['redis_memory_usage'] = memory_get_usage(true) / (1024 * 1024);

                // Update current cache size from Redis info
                if (isset($redis_info['used_memory'])) {
                    $this->current_cache_size = $redis_info['used_memory'];
                }
            } catch (\Exception $e) {
                $stats['redis_error'] = $e->getMessage();
                $this->stats['errors']++;
            }
        }

        // Calculate hit/miss rates
        $total_requests = $stats['operations']['hits'] + $stats['operations']['misses'];
        if ($total_requests > 0) {
            $stats['hit_rate'] = $stats['operations']['hits'] / $total_requests;
            $stats['miss_rate'] = $stats['operations']['misses'] / $total_requests;
        }

        return $stats;
    }

    /**
     * Get cache size in bytes with Redis estimation
     */
    public function get_cache_size() {
        if ($this->redis_available) {
            try {
                $info = $this->redis->info('memory');
                if (isset($info['used_memory'])) {
                    return $info['used_memory'];
                }
            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Failed to get Redis memory info - ' . $e->getMessage());
            }
        }

        return $this->current_cache_size;
    }

    /**
     * Get LRU cache statistics
     */
    public function get_lru_stats() {
        return [
            'lru_cache_size' => count($this->lru_cache),
            'lru_timestamp' => $this->lru_timestamp,
            'recent_accesses' => array_slice($this->lru_cache, -10, 10, true) // Last 10 accesses
        ];
    }

    /**
     * Get a cache object from the pool
     *
     * @return object|null Cache object
     */
    public function get_cache_object_from_pool() {
        if ($this->cache_object_pool === null) {
            $this->initialize_cache_object_pool();
        }

        return $this->cache_object_pool->get_cache_object();
    }

    /**
     * Release a cache object back to the pool
     *
     * @param object $cache_object Cache object
     * @return bool True if object was released successfully
     */
    public function release_cache_object_to_pool($cache_object) {
        if ($this->cache_object_pool !== null) {
            return $this->cache_object_pool->release_cache_object($cache_object);
        }
        return false;
    }

    /**
     * Get cache object pool statistics
     *
     * @return array Pool statistics
     */
    public function get_cache_object_pool_stats() {
        if ($this->cache_object_pool === null) {
            $this->initialize_cache_object_pool();
        }

        return $this->cache_object_pool->get_stats();
    }

    /**
     * Cleanup cache object pool
     */
    public function cleanup_cache_object_pool() {
        if ($this->cache_object_pool !== null) {
            $this->cache_object_pool->cleanup_idle_objects();
        }
    }

    /**
     * Get cache connection cleanup instance
     *
     * @return CacheConnectionCleanup
     */
    public function get_cache_cleanup() {
        static $cleanup_instance = null;

        if ($cleanup_instance === null) {
            $cleanup_instance = new \SMO_Social\Core\CacheConnectionCleanup();
        }

        return $cleanup_instance;
    }

    /**
     * Perform comprehensive cache cleanup
     *
     * @return array Cleanup results
     */
    public function perform_comprehensive_cleanup() {
        $cleanup = $this->get_cache_cleanup();
        return [
            'idle_cleanup' => $cleanup->cleanup_idle_cache_objects(),
            'automatic_cleanup' => $cleanup->automatic_cleanup(),
            'health_stats' => $cleanup->get_health_statistics(),
            'validation_results' => $cleanup->perform_comprehensive_validation()
        ];
    }

    /**
     * Monitor cache health
     *
     * @return array Health monitoring results
     */
    public function monitor_cache_health() {
        $cleanup = $this->get_cache_cleanup();
        return [
            'health_checks' => $cleanup->check_cache_object_health_with_timeout(),
            'monitoring_results' => $cleanup->monitor_cache_object_states(),
            'pool_stats' => $cleanup->get_cache_object_pool_stats(),
            'cache_stats' => $cleanup->get_cache_cleanup_stats()
        ];
    }

    /**
     * Enhanced cleanup with Redis-specific optimizations
     */
    public function enhanced_cleanup() {
        if ($this->redis_available) {
            try {
                // Perform LRU eviction if needed
                $this->enforce_cache_size_limits();

                // Additional Redis maintenance
                $this->redis->bgrewriteaof();

                // Update cache size
                $this->current_cache_size = $this->estimate_redis_cache_size();

            } catch (\Exception $e) {
                error_log('SMO Social BoundedRedisCache: Enhanced cleanup error - ' . $e->getMessage());
                $this->stats['errors']++;
            }
        }

        // Additional cleanup using the new cleanup mechanism
        $cleanup = $this->get_cache_cleanup();
        $cleanup->cleanup_idle_cache_objects();

        // Check memory usage and cleanup if needed
        $cleanup->check_memory_based_cleanup();
    }

    /**
     * Get Redis connection status
     */
    public function get_redis_status() {
        return [
            'redis_available' => $this->redis_available,
            'redis_connected' => $this->redis_available && $this->redis !== null,
            'redis_ping' => $this->redis_available ? ($this->redis->ping() ?? false) : false,
            'redis_info' => $this->redis_available ? $this->get_redis_info() : null
        ];
    }

    /**
     * Get Redis server info
     */
    private function get_redis_info() {
        if (!$this->redis_available) {
            return null;
        }

        try {
            $info = $this->redis->info();
            return [
                'server' => $info['redis_version'] ?? 'unknown',
                'memory_usage' => $info['used_memory'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'keys' => $this->redis->dbsize() ?? 0
            ];
        } catch (\Exception $e) {
            error_log('SMO Social BoundedRedisCache: Failed to get Redis info - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reset statistics counters
     */
    public function reset_stats() {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'evictions' => 0,
            'errors' => 0,
            'memory_evictions' => 0,
            'size_evictions' => 0,
            'redis_operations' => 0
        ];
        return true;
    }

    /**
     * Get current statistics counters
     */
    public function get_current_stats() {
        return $this->stats;
    }
}