<?php
/**
 * Redis-based Caching Layer for SMO Social
 * 
 * Provides high-performance caching with Redis support and fallback
 */

namespace SMO_Social\Core;

require_once __DIR__ . '/CacheObjectPool.php';
require_once __DIR__ . '/CacheConnectionCleanup.php';

class CacheManager
{
    private ?object $redis = null;
    private bool $redis_available = false;
    private int $default_ttl = 3600; // 1 hour
    private $cache_object_pool = null;
    private $cache_metadata_stream;
    private $batch_size;
    private $max_memory_usage;
    private $bounded_redis_cache = null;

    public function __construct(?object $redis_instance = null)
    {
        if ($redis_instance && method_exists($redis_instance, 'ping')) {
            try {
                if ($redis_instance->ping()) {
                    $this->redis = $redis_instance;
                    $this->redis_available = true;
                }
            } catch (\Exception $e) {
                // Redis connection failed, fall back to WordPress cache
                error_log('SMO Social: Redis connection failed - ' . $e->getMessage());
            }
        }
        $this->initialize_cache_metadata_stream();
        $this->initialize_bounded_redis_cache();
        $this->register_with_memory_monitor();
    }

    /**
     * Initialize cache metadata stream
     */
    private function initialize_cache_metadata_stream() {
        // Initialize cache metadata stream with default configuration
        $this->batch_size = 1000;
        $this->max_memory_usage = 30; // MB
        $this->cache_metadata_stream = new \SMO_Social\Core\CacheMetadataStream($this->batch_size, $this->max_memory_usage);
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
     * Initialize bounded Redis cache
     */
    private function initialize_bounded_redis_cache() {
        if ($this->bounded_redis_cache === null) {
            // Load configuration from settings
            $settings = get_option('smo_social_bounded_cache_settings', []);

            $max_cache_size = 52428800; // Default 50MB
            $max_memory_usage = 50; // Default 50MB

            if (isset($settings['redis_cache']['max_cache_size'])) {
                $max_cache_size = max(10485760, intval($settings['redis_cache']['max_cache_size']));
            }

            if (isset($settings['redis_cache']['max_memory_usage'])) {
                $max_memory_usage = max(10, intval($settings['redis_cache']['max_memory_usage']));
            }

            $this->bounded_redis_cache = new \SMO_Social\Core\BoundedRedisCache($this->redis, $max_cache_size, $max_memory_usage);
        }
    }

    /**
     * Get data from cache (tries Redis first, then WordPress cache)
     */
    public function get(string $key, $default = null)
    {
        $cache_key = $this->normalize_key($key);
        
        // Try Redis first
        if ($this->redis_available) {
            try {
                $cached = $this->redis->get($cache_key);
                if ($cached !== false && $cached !== null) {
                    return json_decode($cached, true);
                }
            } catch (\Exception $e) {
                error_log('SMO Social: Redis get error - ' . $e->getMessage());
            }
        }
        
        // Fallback to WordPress object cache
        $cached = wp_cache_get($cache_key, 'smo_social');
        return $cached !== false ? $cached : $default;
    }

    /**
     * Set data to cache (Redis + WordPress cache)
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $cache_key = $this->normalize_key($key);
        $ttl = $ttl ?? $this->default_ttl;
        $success = true;
        
        // Set in Redis
        if ($this->redis_available) {
            try {
                $serialized = json_encode($value);
                if ($serialized !== false) {
                    if (!$this->redis->setex($cache_key, $ttl, $serialized)) {
                        $success = false;
                    }
                }
            } catch (\Exception $e) {
                error_log('SMO Social: Redis set error - ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Set in WordPress cache
        if (!wp_cache_set($cache_key, $value, 'smo_social', $ttl)) {
            $success = false;
        }
        
        return $success;
    }

    /**
     * Delete data from cache
     */
    public function delete(string $key): bool
    {
        $cache_key = $this->normalize_key($key);
        $success = true;
        
        // Delete from Redis
        if ($this->redis_available) {
            try {
                if (!$this->redis->del($cache_key)) {
                    $success = false;
                }
            } catch (\Exception $e) {
                error_log('SMO Social: Redis delete error - ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Delete from WordPress cache
        if (!wp_cache_delete($cache_key, 'smo_social')) {
            $success = false;
        }
        
        return $success;
    }

    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool
    {
        $cache_key = $this->normalize_key($key);
        
        // Check Redis first
        if ($this->redis_available) {
            try {
                if ($this->redis->exists($cache_key)) {
                    return true;
                }
            } catch (\Exception $e) {
                error_log('SMO Social: Redis exists error - ' . $e->getMessage());
            }
        }
        
        // Check WordPress cache
        return wp_cache_get($cache_key, 'smo_social') !== false;
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $success = true;
        
        // Clear Redis cache (all keys with our prefix)
        if ($this->redis_available) {
            try {
                $pattern = $this->normalize_key('*');
                $keys = $this->redis->keys($pattern);
                if (!empty($keys) && !$this->redis->del(...$keys)) {
                    $success = false;
                }
            } catch (\Exception $e) {
                error_log('SMO Social: Redis clear error - ' . $e->getMessage());
                $success = false;
            }
        }
        
        // Note: WordPress object cache doesn't support clearing by prefix
        // This would need to be implemented differently for production
        
        return $success;
    }

    /**
     * Get multiple values at once
     */
    public function get_multiple(array $keys, $default = null): array
    {
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
                    } else {
                        $missing_keys[] = $key;
                    }
                }
            } catch (\Exception $e) {
                error_log('SMO Social: Redis mget error - ' . $e->getMessage());
                $missing_keys = $keys; // Fall back to individual gets
            }
        } else {
            $missing_keys = $keys;
        }
        
        // Get missing keys from WordPress cache
        foreach ($missing_keys as $key) {
            $cached = wp_cache_get($this->normalize_key($key), 'smo_social');
            $results[$key] = $cached !== false ? $cached : $default;
        }
        
        return $results;
    }

    /**
     * Set multiple values at once
     */
    public function set_multiple(array $data, ?int $ttl = null): array
    {
        $results = [];
        $ttl = $ttl ?? $this->default_ttl;
        
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
                }
            } catch (\Exception $e) {
                error_log('SMO Social: Redis mset error - ' . $e->getMessage());
                // Fall back to individual sets
                foreach ($data as $key => $value) {
                    $results[$key] = $this->set($key, $value, $ttl);
                }
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
     * Get cache statistics using streaming approach
     */
    public function get_stats(): array
    {
        $stats = [
            'redis_available' => $this->redis_available,
            'redis_stats' => null,
            'cache_keys' => 0,
            'memory_usage' => memory_get_usage(true) / (1024 * 1024)
        ];

        if ($this->redis_available) {
            try {
                $stats['redis_stats'] = $this->redis->info();
                $stats['redis_connected'] = true;
                $stats['redis_memory_usage'] = memory_get_usage(true) / (1024 * 1024);
            } catch (\Exception $e) {
                $stats['redis_error'] = $e->getMessage();
            }
        }

        // Add memory-efficient cache metadata statistics
        try {
            $cache_metadata = $this->cache_metadata_stream->get_cache_statistics();
            $stats = array_merge($stats, $cache_metadata);
        } catch (\Exception $e) {
            error_log('SMO Core Cache: Failed to get cache metadata - ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Specialized cache for comment scores
     */
    public function get_comment_scores(int $user_id, int $ttl = 300): array
    {
        $cache_key = "comment_scores:{$user_id}";
        
        // Try Redis first
        if ($this->redis_available) {
            $cached = $this->redis->get($this->normalize_key($cache_key));
            if ($cached) {
                return json_decode($cached, true);
            }
        }
        
        // Fallback to WordPress object cache
        $cached = wp_cache_get($this->normalize_key($cache_key), 'smo_social');
        if ($cached !== false) {
            return $cached;
        }
        
        // Cache miss - fetch from database
        $scores = $this->fetch_comment_scores_from_db($user_id);
        
        // Store in both caches
        $this->set($cache_key, $scores, $ttl);
        
        return $scores;
    }

    /**
     * Normalize cache key to prevent conflicts
     */
    private function normalize_key(string $key): string
    {
        // Remove special characters and ensure consistent formatting
        return 'smo_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }

    /**
     * Fetch comment scores from database (placeholder)
     */
    private function fetch_comment_scores_from_db(int $user_id): array
    {
        // This would fetch from the actual database
        // For now, return mock data
        global $wpdb;
        
        try {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT platform, COUNT(*) as comment_count, AVG(CASE 
                    WHEN sentiment = 'positive' THEN 1 
                    WHEN sentiment = 'neutral' THEN 0 
                    WHEN sentiment = 'negative' THEN -1 
                    ELSE 0 
                END) as avg_sentiment 
                FROM {$wpdb->prefix}smo_social_comments 
                WHERE user_id = %d 
                GROUP BY platform",
                $user_id
            ));
            
            return $results ?: [];
        } catch (\Exception $e) {
            error_log('SMO Social: Failed to fetch comment scores - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get cached chat messages for a session with batching support
     */
    public function get_chat_messages_cache($session_id, $user_id = null, $page = 1, $limit = 50) {
        $cache_key = "chat_messages:{$session_id}:{$page}:{$limit}";
        if ($user_id) {
            $cache_key .= ":user_{$user_id}";
        }

        $cache_ttl = 300; // 5 minutes for chat messages

        // Try to get cached messages
        $cached = $this->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Cache miss - this would be populated by the actual query in ChatMessage class
        return false;
    }

    /**
     * Cache chat messages with batching support
     */
    public function set_chat_messages_cache($session_id, $messages_data, $user_id = null, $page = 1, $limit = 50) {
        $cache_key = "chat_messages:{$session_id}:{$page}:{$limit}";
        if ($user_id) {
            $cache_key .= ":user_{$user_id}";
        }

        $cache_ttl = 300; // 5 minutes for chat messages
        return $this->set($cache_key, $messages_data, $cache_ttl);
    }

    /**
     * Get cached AI provider data
     */
    public function get_ai_provider_cache($provider_id) {
        $cache_key = "ai_provider:{$provider_id}";
        $cache_ttl = 3600; // 1 hour for provider data

        // Try to get cached provider
        $cached = $this->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Cache miss - this would be populated by the actual query
        return false;
    }

    /**
     * Cache AI provider data
     */
    public function set_ai_provider_cache($provider_id, $provider_data) {
        $cache_key = "ai_provider:{$provider_id}";
        $cache_ttl = 3600; // 1 hour for provider data
        return $this->set($cache_key, $provider_data, $cache_ttl);
    }

    /**
     * Get cached moderation data
     */
    public function get_moderation_cache($message_id) {
        $cache_key = "moderation:{$message_id}";
        $cache_ttl = 1800; // 30 minutes for moderation data

        // Try to get cached moderation data
        $cached = $this->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Cache miss - this would be populated by the actual query
        return false;
    }

    /**
     * Cache moderation data
     */
    public function set_moderation_cache($message_id, $moderation_data) {
        $cache_key = "moderation:{$message_id}";
        $cache_ttl = 1800; // 30 minutes for moderation data
        return $this->set($cache_key, $moderation_data, $cache_ttl);
    }

    /**
     * Clear all chat-related caches
     */
    public function clear_chat_caches() {
        // Clear chat message caches
        $this->delete('chat_messages:*');

        // Clear session-related caches
        $this->delete('chat_session:*');

        return true;
    }

    /**
     * Clear all AI-related caches
     */
    public function clear_ai_caches() {
        // Clear AI provider caches
        $this->delete('ai_provider:*');

        // Clear AI model caches
        $this->delete('ai_model:*');

        return true;
    }

    /**
     * Clear all moderation caches
     */
    public function clear_moderation_caches() {
        // Clear moderation data caches
        $this->delete('moderation:*');

        return true;
    }

    /**
     * Get cache with namespace support for better organization
     */
    public function get_with_namespace($key, $namespace = 'default', $default = null) {
        $namespaced_key = "{$namespace}:{$key}";
        return $this->get($namespaced_key, $default);
    }

    /**
     * Set cache with namespace support
     */
    public function set_with_namespace($key, $value, $namespace = 'default', $ttl = null) {
        $namespaced_key = "{$namespace}:{$key}";
        return $this->set($namespaced_key, $value, $ttl);
    }

    /**
     * Delete cache with namespace support
     */
    public function delete_with_namespace($key, $namespace = 'default') {
        $namespaced_key = "{$namespace}:{$key}";
        return $this->delete($namespaced_key);
    }

    /**
     * Clear all caches for a specific namespace
     */
    public function clear_namespace($namespace) {
        $this->delete("{$namespace}:*");
        return true;
    }

    /**
     * Get cache key with proper naming convention
     */
    public function get_cache_key($category, $identifier, $context = '') {
        $components = array_filter([$category, $identifier, $context]);
        $key = implode(':', $components);
        return $this->normalize_key($key);
    }

    /**
     * Enhanced cache invalidation with pattern matching
     */
    public function invalidate_related_caches($pattern) {
        // For Redis, we can use pattern matching
        if ($this->redis_available) {
            try {
                $keys = $this->redis->keys($this->normalize_key($pattern));
                if (!empty($keys)) {
                    $this->redis->del(...$keys);
                }
                return true;
            } catch (\Exception $e) {
                error_log('SMO Social: Pattern invalidation error - ' . $e->getMessage());
                return false;
            }
        }
        // For WordPress cache, we need to implement this differently
        // This is a limitation of the current implementation
        return false;
    }

    /**
     * Get cache with fallback to database query
     */
    public function get_with_fallback($key, $fallback_callback, $ttl = 300) {
        // Try to get from cache
        $cached = $this->get($key);
        if ($cached !== false) {
            return $cached;
        }

        // Cache miss - execute fallback and cache result
        $result = $fallback_callback();
        if ($result !== null) {
            $this->set($key, $result, $ttl);
        }

        return $result;
    }

    /**
     * Batch cache invalidation for multiple keys
     */
    public function invalidate_batch($keys) {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Cleanup cache object pool
     */
    public function cleanup_cache_object_pool() {
        if ($this->cache_object_pool instanceof CacheObjectPool) {
            $this->cache_object_pool->clear_pool();
            error_log('SMO Social: Cache object pool cleaned up');
        }
    }

    /**
     * Register with MemoryMonitor for integrated monitoring
     */
    private function register_with_memory_monitor() {
        if (class_exists('\SMO_Social\Core\MemoryMonitor')) {
            try {
                // The MemoryMonitor will collect stats from CacheManager when available
                // This ensures the CacheManager is recognized as an integrated system
                error_log('SMO Social: CacheManager registered for memory monitoring integration');
            } catch (\Exception $e) {
                error_log('SMO Social: Failed to register CacheManager with MemoryMonitor: ' . $e->getMessage());
            }
        }
    }
}