<?php
namespace SMO_Social\AI;

require_once __DIR__ . '/../Core/CacheObjectPool.php';
require_once __DIR__ . '/../Core/CacheConnectionCleanup.php';

class CacheManager {
    private $cache_dir;
    private $default_ttl;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'evictions' => 0,
        'errors' => 0
    ];
    private $cache_object_pool = null;
    private $cache_metadata_stream;
    private $batch_size;
    private $max_memory_usage;
    private $bounded_cache_manager = null;

    public function __construct() {
        error_log('SMO Social CacheManager: Starting initialization');

        // Handle both WordPress and non-WordPress contexts
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $this->cache_dir = $upload_dir['basedir'] . '/smo-social/cache/';
            $this->default_ttl = \HOUR_IN_SECONDS * 6; // 6 hours
            error_log('SMO Social CacheManager: Using WordPress upload dir: ' . $this->cache_dir);
        } else {
            // Non-WordPress context - use current directory
            $this->cache_dir = dirname(__FILE__) . '/../../cache/smo-social/cache/';
            $this->default_ttl = 21600; // 6 hours in seconds
            error_log('SMO Social CacheManager: Using non-WordPress dir: ' . $this->cache_dir);
        }

        // Ensure cache directory exists
        if (!file_exists($this->cache_dir)) {
            error_log('SMO Social CacheManager: Cache directory does not exist, creating: ' . $this->cache_dir);
            $result = $this->create_directory($this->cache_dir);
            if (!$result) {
                error_log('SMO Social CacheManager: Failed to create cache directory: ' . $this->cache_dir);
            } else {
                error_log('SMO Social CacheManager: Successfully created cache directory');
            }
        } else {
            error_log('SMO Social CacheManager: Cache directory already exists');
        }

        // Initialize cache object pool
        $this->initialize_cache_object_pool();

        // Initialize cache metadata stream with default configuration
        $this->batch_size = 1000;
        $this->max_memory_usage = 30; // MB
        $this->cache_metadata_stream = new \SMO_Social\Core\CacheMetadataStream($this->batch_size, $this->max_memory_usage);

        // Initialize bounded cache manager
        $this->initialize_bounded_cache_manager();

        error_log('SMO Social CacheManager: Initialization completed');
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
            $pool_size = 20; // Default pool size

            // Get pool size from settings if available
            $settings = get_option('smo_social_settings', []);
            if (isset($settings['cache_pool_size']) && is_numeric($settings['cache_pool_size'])) {
                $pool_size = max(5, min(50, intval($settings['cache_pool_size'])));
            }

            $this->cache_object_pool = new \SMO_Social\Core\CacheObjectPool($pool_size);
        }
    }

    /**
     * Initialize bounded cache manager
     */
    private function initialize_bounded_cache_manager() {
        if ($this->bounded_cache_manager === null) {
            // Load configuration from settings
            $settings = get_option('smo_social_bounded_cache_settings', []);

            $max_cache_size = 104857600; // Default 100MB
            $max_memory_usage = 50; // Default 50MB

            if (isset($settings['ai_cache']['max_cache_size'])) {
                $max_cache_size = max(10485760, intval($settings['ai_cache']['max_cache_size']));
            }

            if (isset($settings['ai_cache']['max_memory_usage'])) {
                $max_memory_usage = max(10, intval($settings['ai_cache']['max_memory_usage']));
            }

            $this->bounded_cache_manager = new \SMO_Social\AI\BoundedCacheManager($max_cache_size, $max_memory_usage);
        }
    }

    /**
     * Create directory recursively (WordPress-independent)
     */
    private function create_directory($dir) {
        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }
        return true;
    }

    /**
     * Generate a unique cache key with namespace to prevent collisions
     */
    private function generate_cache_key($key, $namespace = 'default') {
        // Add namespace to prevent collisions between different cache types
        return $namespace . ':' . $key;
    }

    /**
     * Get cache file path with improved collision prevention
     */
    private function get_cache_file_path($key, $namespace = 'default') {
        // Generate namespaced key
        $namespaced_key = $this->generate_cache_key($key, $namespace);

        // Create subdirectory structure based on hash to avoid too many files in one directory
        $hash = md5($namespaced_key);
        $subdir = $this->cache_dir . substr($hash, 0, 2) . '/';

        if (!file_exists($subdir)) {
            $this->create_directory($subdir);
        }

        return $subdir . $hash . '.cache';
    }

    /**
     * Get cached data with improved collision handling
     */
    public function get($key, $namespace = 'default') {
        $file_path = $this->get_cache_file_path($key, $namespace);

        if (!file_exists($file_path)) {
            $this->stats['misses']++;
            return false;
        }

        // Check if cache is expired
        $cache_data = $this->read_cache_file($file_path);
        if ($cache_data === false) {
            $this->stats['misses']++;
            return false;
        }

        if ($cache_data['expires'] < time()) {
            // Cache expired
            unlink($file_path);
            $this->stats['misses']++;
            $this->stats['evictions']++;
            return false;
        }

        $this->stats['hits']++;
        return $cache_data['data'];
    }

    /**
     * Set cached data with improved collision prevention
     */
    public function set($key, $data, $ttl = null, $namespace = 'default') {
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }

        $file_path = $this->get_cache_file_path($key, $namespace);
        $cache_data = array(
            'data' => $data,
            'created' => time(),
            'expires' => time() + $ttl,
            'namespace' => $namespace,
            'key' => $key
        );

        $serialized_data = serialize($cache_data);

        // Ensure directory exists
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            $this->create_directory($dir);
        }

        // Write to file with error handling
        $result = file_put_contents($file_path, $serialized_data, LOCK_EX);

        if ($result === false) {
            if (function_exists('error_log')) {
                error_log("SMO Social Cache: Failed to write cache file: $file_path");
            }
            $this->stats['errors']++;
            return false;
        }

        $this->stats['sets']++;
        return true;
    }

    /**
     * Delete cached data with namespace support
     */
    public function delete($key, $namespace = 'default') {
        $file_path = $this->get_cache_file_path($key, $namespace);

        if (file_exists($file_path)) {
            $result = unlink($file_path);
            if ($result) {
                $this->stats['deletes']++;
            }
            return $result;
        }

        return true;
    }

    /**
     * Clear all cache or specific namespace
     *
     * @param string|null $namespace Optional namespace to clear
     */
    public function clear($namespace = null) {
        if ($namespace) {
            // Clear specific namespace
            $files = $this->find_files_by_namespace($namespace);
        } else {
            // Clear all cache
            $files = glob($this->cache_dir . '**/*');
        }

        if (!$files) {
            return true;
        }

        $deleted_count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $deleted_count++;
            }
        }

        $this->stats['deletes'] += $deleted_count;
        return true;
    }

    /**
     * Find files by namespace
     */
    private function find_files_by_namespace($namespace) {
        $files = [];
        $all_files = glob($this->cache_dir . '**/*');

        foreach ($all_files as $file) {
            if (is_file($file)) {
                $cache_data = $this->read_cache_file($file);
                if ($cache_data !== false && isset($cache_data['namespace']) && $cache_data['namespace'] === $namespace) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * Clean expired cache files with statistics using streaming approach
     */
    public function cleanup_expired() {
        try {
            // Use streaming approach for large cache cleanup operations
            $result = $this->cache_metadata_stream->process_cache_files_in_chunks('cleanup_expired');

            $this->stats['evictions'] += $result['processed'];
            return $result['processed'];
        } catch (\Exception $e) {
            error_log('SMO AI Cache: Streaming cleanup failed, falling back to original method - ' . $e->getMessage());

            // Fallback to original method
            $files = glob($this->cache_dir . '**/*');
            $cleaned_count = 0;

            foreach ($files as $file) {
                if (is_file($file)) {
                    $cache_data = $this->read_cache_file($file);
                    if ($cache_data !== false && $cache_data['expires'] < time()) {
                        unlink($file);
                        $cleaned_count++;
                        $this->stats['evictions']++;
                    }
                }
            }

            return $cleaned_count;
        }
    }

    /**
     * Get comprehensive cache statistics using streaming approach
     */
    public function get_stats() {
        try {
            // Use streaming approach for large cache datasets
            $metadata_summary = $this->cache_metadata_stream->stream_cache_metadata();

            $stats = [
                'total_files' => $metadata_summary['total_files'],
                'total_size' => $metadata_summary['total_size'],
                'expired_files' => $metadata_summary['expired_files'],
                'valid_files' => $metadata_summary['valid_files'],
                'hit_rate' => 0,
                'miss_rate' => 0,
                'operations' => $this->stats,
                'namespaces' => $metadata_summary['namespaces'],
                'size_by_namespace' => $metadata_summary['size_by_namespace']
            ];

            $total_requests = $stats['operations']['hits'] + $stats['operations']['misses'];
            if ($total_requests > 0) {
                $stats['hit_rate'] = $stats['operations']['hits'] / $total_requests;
                $stats['miss_rate'] = $stats['operations']['misses'] / $total_requests;
            }

            return $stats;
        } catch (\Exception $e) {
            error_log('SMO AI Cache: Streaming stats failed, falling back to original method - ' . $e->getMessage());

            // Fallback to original method
            $files = glob($this->cache_dir . '**/*');
            $stats = [
                'total_files' => 0,
                'total_size' => 0,
                'expired_files' => 0,
                'valid_files' => 0,
                'hit_rate' => 0,
                'miss_rate' => 0,
                'operations' => $this->stats
            ];

            $total_requests = $stats['operations']['hits'] + $stats['operations']['misses'];
            if ($total_requests > 0) {
                $stats['hit_rate'] = $stats['operations']['hits'] / $total_requests;
                $stats['miss_rate'] = $stats['operations']['misses'] / $total_requests;
            }

            foreach ($files as $file) {
                if (is_file($file)) {
                    $stats['total_files']++;
                    $stats['total_size'] += filesize($file);

                    $cache_data = $this->read_cache_file($file);
                    if ($cache_data === false) {
                        $stats['expired_files']++;
                    } else {
                        $stats['valid_files']++;
                    }
                }
            }

            return $stats;
        }
    }

    /**
     * Get cache size in bytes
     */
    public function get_cache_size() {
        $size = 0;
        $files = glob($this->cache_dir . '**/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);
            }
        }

        return $size;
    }

    /**
     * Check if cache key exists and is valid with namespace support
     */
    public function exists($key, $namespace = 'default') {
        $file_path = $this->get_cache_file_path($key, $namespace);

        if (!file_exists($file_path)) {
            return false;
        }

        $cache_data = $this->read_cache_file($file_path);
        if ($cache_data === false) {
            return false;
        }

        return $cache_data['expires'] >= time();
    }

    /**
     * Get remaining TTL for cache key with namespace support
     */
    public function get_ttl($key, $namespace = 'default') {
        $file_path = $this->get_cache_file_path($key, $namespace);

        if (!file_exists($file_path)) {
            return 0;
        }

        $cache_data = $this->read_cache_file($file_path);
        if ($cache_data === false) {
            return 0;
        }

        return max(0, $cache_data['expires'] - time());
    }

    /**
     * Increment numeric cache value with namespace support
     */
    public function increment($key, $value = 1, $namespace = 'default') {
        $current = $this->get($key, $namespace);

        if ($current === false) {
            $new_value = $value;
        } elseif (is_numeric($current)) {
            $new_value = $current + $value;
        } else {
            return false; // Can't increment non-numeric value
        }

        return $this->set($key, $new_value, null, $namespace);
    }

    /**
     * Decrement numeric cache value with namespace support
     */
    public function decrement($key, $value = 1, $namespace = 'default') {
        return $this->increment($key, -$value, $namespace);
    }

    /**
     * Add multiple cache entries with namespace support
     */
    public function set_multiple($items, $ttl = null, $namespace = 'default') {
        $results = array();

        foreach ($items as $key => $data) {
            $results[$key] = $this->set($key, $data, $ttl, $namespace);
        }

        return $results;
    }

    /**
     * Get multiple cache entries with namespace support
     */
    public function get_multiple($keys, $namespace = 'default') {
        $results = array();

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $namespace);
        }

        return $results;
    }

    /**
     * Delete multiple cache entries with namespace support
     */
    public function delete_multiple($keys, $namespace = 'default') {
        $results = array();

        foreach ($keys as $key) {
            $results[$key] = $this->delete($key, $namespace);
        }

        return $results;
    }

    /**
     * Read and unserialize cache file
     */
    private function read_cache_file($file_path) {
        $content = file_get_contents($file_path);

        if ($content === false) {
            return false;
        }

        $cache_data = unserialize($content);

        if ($cache_data === false || !isset($cache_data['data'], $cache_data['expires'])) {
            // Invalid cache file, delete it
            unlink($file_path);
            return false;
        }

        return $cache_data;
    }

    /**
     * Get cache hit rate based on actual statistics
     */
    public function get_hit_rate() {
        $total = $this->stats['hits'] + $this->stats['misses'];
        if ($total === 0) {
            return 0.0;
        }
        return $this->stats['hits'] / $total;
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
            'errors' => 0
        ];
        return true;
    }

    /**
     * Get current statistics counters
     */
    public function get_current_stats() {
        return $this->stats;
    }

    /**
     * Get cached AI provider configuration with enhanced caching
     */
    public function get_ai_provider_config($provider_id, $ttl = 3600) {
        $cache_key = "ai_provider_config:{$provider_id}";
        $namespace = 'ai_providers';

        // Try to get cached configuration
        $cached = $this->get($cache_key, $namespace);
        if ($cached !== false) {
            $this->stats['hits']++;
            return $cached;
        }

        // Cache miss - this would be populated by actual database query
        $this->stats['misses']++;
        return false;
    }

    /**
     * Cache AI provider configuration
     */
    public function set_ai_provider_config($provider_id, $config_data, $ttl = 3600) {
        $cache_key = "ai_provider_config:{$provider_id}";
        $namespace = 'ai_providers';

        $result = $this->set($cache_key, $config_data, $ttl, $namespace);
        if ($result) {
            $this->stats['sets']++;
        }
        return $result;
    }

    /**
     * Get cached AI model data
     */
    public function get_ai_model_cache($model_id, $ttl = 7200) {
        $cache_key = "ai_model:{$model_id}";
        $namespace = 'ai_models';

        // Try to get cached model data
        $cached = $this->get($cache_key, $namespace);
        if ($cached !== false) {
            $this->stats['hits']++;
            return $cached;
        }

        // Cache miss - this would be populated by actual database query
        $this->stats['misses']++;
        return false;
    }

    /**
     * Cache AI model data
     */
    public function set_ai_model_cache($model_id, $model_data, $ttl = 7200) {
        $cache_key = "ai_model:{$model_id}";
        $namespace = 'ai_models';

        $result = $this->set($cache_key, $model_data, $ttl, $namespace);
        if ($result) {
            $this->stats['sets']++;
        }
        return $result;
    }

    /**
     * Get cached AI response with content-based caching
     */
    public function get_ai_response_cache($provider_id, $prompt_hash, $ttl = 1800) {
        $cache_key = "ai_response:{$provider_id}:{$prompt_hash}";
        $namespace = 'ai_responses';

        // Try to get cached response
        $cached = $this->get($cache_key, $namespace);
        if ($cached !== false) {
            $this->stats['hits']++;
            return $cached;
        }

        // Cache miss - this would be populated by actual AI call
        $this->stats['misses']++;
        return false;
    }

    /**
     * Cache AI response
     */
    public function set_ai_response_cache($provider_id, $prompt_hash, $response_data, $ttl = 1800) {
        $cache_key = "ai_response:{$provider_id}:{$prompt_hash}";
        $namespace = 'ai_responses';

        $result = $this->set($cache_key, $response_data, $ttl, $namespace);
        if ($result) {
            $this->stats['sets']++;
        }
        return $result;
    }

    /**
     * Clear all AI provider caches
     */
    public function clear_ai_provider_caches() {
        return $this->clear('ai_providers');
    }

    /**
     * Clear all AI model caches
     */
    public function clear_ai_model_caches() {
        return $this->clear('ai_models');
    }

    /**
     * Clear all AI response caches
     */
    public function clear_ai_response_caches() {
        return $this->clear('ai_responses');
    }

    /**
     * Get AI cache with fallback to database/query
     */
    public function get_ai_cache_with_fallback($key, $namespace, $fallback_callback, $ttl = 3600) {
        // Try to get from cache
        $cached = $this->get($key, $namespace);
        if ($cached !== false) {
            $this->stats['hits']++;
            return $cached;
        }

        // Cache miss - execute fallback and cache result
        $this->stats['misses']++;
        $result = $fallback_callback();

        if ($result !== null) {
            $this->set($key, $result, $ttl, $namespace);
            $this->stats['sets']++;
        }

        return $result;
    }

    /**
     * Get AI provider cache statistics
     */
    public function get_ai_cache_stats() {
        $ai_stats = [
            'ai_provider_hits' => 0,
            'ai_model_hits' => 0,
            'ai_response_hits' => 0,
            'total_ai_cache_size' => 0,
            'ai_cache_efficiency' => 0.0
        ];

        // Analyze cache files for AI-specific data
        $files = glob($this->cache_dir . '**/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $cache_data = $this->read_cache_file($file);
                if ($cache_data !== false && isset($cache_data['namespace'])) {
                    $ai_stats['total_ai_cache_size'] += filesize($file);

                    if (strpos($cache_data['namespace'], 'ai_') === 0) {
                        if (strpos($cache_data['key'], 'ai_provider_') === 0) {
                            $ai_stats['ai_provider_hits']++;
                        } elseif (strpos($cache_data['key'], 'ai_model_') === 0) {
                            $ai_stats['ai_model_hits']++;
                        } elseif (strpos($cache_data['key'], 'ai_response_') === 0) {
                            $ai_stats['ai_response_hits']++;
                        }
                    }
                }
            }
        }

        $total_ai_hits = $ai_stats['ai_provider_hits'] + $ai_stats['ai_model_hits'] + $ai_stats['ai_response_hits'];
        if ($total_ai_hits > 0) {
            $ai_stats['ai_cache_efficiency'] = $total_ai_hits / count($files);
        }
return array_merge($this->get_stats(), $ai_stats);
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
 * Enhanced cleanup expired cache with cleanup mechanisms
 */
public function enhanced_cleanup_expired() {
    // Original cleanup logic
    $result = $this->original_cleanup_expired();

    // Additional cleanup using the new cleanup mechanism
    $cleanup = $this->get_cache_cleanup();
    $cleanup->cleanup_idle_cache_objects();

    // Check memory usage and cleanup if needed
    $cleanup->check_memory_based_cleanup();

    return $result;
}

/**
 * Original cleanup expired method (renamed to avoid conflict)
 */
private function original_cleanup_expired() {
    try {
        // Use streaming approach for large cache cleanup operations
        $result = $this->cache_metadata_stream->process_cache_files_in_chunks('cleanup_expired');

        $this->stats['evictions'] += $result['processed'];
        return $result['processed'];
    } catch (\Exception $e) {
        error_log('SMO AI Cache: Streaming cleanup failed, falling back to original method - ' . $e->getMessage());

        // Fallback to original method
        $files = glob($this->cache_dir . '**/*');
        $cleaned_count = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $cache_data = $this->read_cache_file($file);
                if ($cache_data !== false && $cache_data['expires'] < time()) {
                    unlink($file);
                    $cleaned_count++;
                    $this->stats['evictions']++;
                }
            }
        }

        return $cleaned_count;
    }
}
}