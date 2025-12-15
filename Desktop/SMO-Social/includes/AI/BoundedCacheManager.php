<?php
/**
 * Bounded Cache Manager for SMO Social AI
 *
 * Implements bounded cache sizes with automatic eviction policies
 * using LRU (Least Recently Used) eviction strategy for AI caching
 *
 * @package SMO_Social
 * @subpackage AI
 * @since 1.0.0
 */

namespace SMO_Social\AI;

require_once __DIR__ . '/../Core/CacheObjectPool.php';
require_once __DIR__ . '/../Core/CacheConnectionCleanup.php';

class BoundedCacheManager {
    private $cache_dir;
    private $default_ttl;
    private $max_cache_size;
    private $current_cache_size;
    private $cache_object_pool = null;
    private $cache_metadata_stream;
    private $batch_size;
    private $max_memory_usage;
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
        'size_evictions' => 0
    ];

    /**
     * Constructor
     *
     * @param int $max_cache_size Maximum cache size in bytes
     * @param int $max_memory_usage Maximum memory usage in MB
     */
    public function __construct($max_cache_size = 104857600, $max_memory_usage = 50) {
        error_log('SMO Social BoundedCacheManager: Starting initialization');

        // Handle both WordPress and non-WordPress contexts
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $this->cache_dir = $upload_dir['basedir'] . '/smo-social/cache/';
            $this->default_ttl = \HOUR_IN_SECONDS * 6; // 6 hours
            error_log('SMO Social BoundedCacheManager: Using WordPress upload dir: ' . $this->cache_dir);
        } else {
            // Non-WordPress context - use current directory
            $this->cache_dir = dirname(__FILE__) . '/../../cache/smo-social/cache/';
            $this->default_ttl = 21600; // 6 hours in seconds
            error_log('SMO Social BoundedCacheManager: Using non-WordPress dir: ' . $this->cache_dir);
        }

        $this->max_cache_size = $max_cache_size; // Default 100MB
        $this->current_cache_size = 0;

        // Ensure cache directory exists
        if (!file_exists($this->cache_dir)) {
            error_log('SMO Social BoundedCacheManager: Cache directory does not exist, creating: ' . $this->cache_dir);
            $result = $this->create_directory($this->cache_dir);
            if (!$result) {
                error_log('SMO Social BoundedCacheManager: Failed to create cache directory: ' . $this->cache_dir);
            } else {
                error_log('SMO Social BoundedCacheManager: Successfully created cache directory');
            }
        } else {
            error_log('SMO Social BoundedCacheManager: Cache directory already exists');
        }

        // Initialize cache object pool
        $this->initialize_cache_object_pool();

        // Initialize cache metadata stream with default configuration
        $this->batch_size = 1000;
        $this->max_memory_usage = $max_memory_usage;
        $this->cache_metadata_stream = new \SMO_Social\Core\CacheMetadataStream($this->batch_size, $this->max_memory_usage);

        // Initialize LRU cache tracking
        $this->initialize_lru_cache();

        error_log('SMO Social BoundedCacheManager: Initialization completed');

        // Register with MemoryMonitor
        $this->register_with_memory_monitor();
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
     * Update LRU cache tracking
     */
    private function update_lru_cache($key, $namespace = 'default') {
        $cache_key = $this->generate_cache_key($key, $namespace);
        $this->lru_timestamp++;

        // Update or add to LRU cache
        $this->lru_cache[$cache_key] = $this->lru_timestamp;

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
        $current_size = $this->get_cache_size();

        // If we exceed max cache size, perform eviction
        if ($current_size > $this->max_cache_size) {
            $excess_size = $current_size - $this->max_cache_size;
            $this->perform_lru_eviction($excess_size);
        }
    }

    /**
     * Perform LRU eviction to reduce cache size
     *
     * @param int $target_reduction Target size reduction in bytes
     */
    private function perform_lru_eviction($target_reduction) {
        // Sort LRU cache by access time (oldest first)
        asort($this->lru_cache);

        $evicted_count = 0;
        $size_reduced = 0;

        foreach ($this->lru_cache as $cache_key => $timestamp) {
            if ($size_reduced >= $target_reduction) {
                break;
            }

            // Parse namespace and key from cache key
            list($namespace, $key) = $this->parse_cache_key($cache_key);

            $file_path = $this->get_cache_file_path($key, $namespace);

            if (file_exists($file_path)) {
                $file_size = filesize($file_path);
                if (unlink($file_path)) {
                    $size_reduced += $file_size;
                    $evicted_count++;
                    $this->stats['evictions']++;
                    $this->stats['size_evictions']++;

                    // Remove from LRU tracking
                    unset($this->lru_cache[$cache_key]);
                }
            }
        }

        // Update current cache size
        $this->current_cache_size = max(0, $this->current_cache_size - $size_reduced);

        error_log("SMO BoundedCache: LRU eviction completed. Evicted $evicted_count items, reduced size by " . format_bytes($size_reduced));
    }

    /**
     * Parse cache key to extract namespace and key
     */
    private function parse_cache_key($cache_key) {
        $parts = explode(':', $cache_key, 2);
        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }
        return ['default', $cache_key];
    }

    /**
     * Get cached data with improved collision handling and LRU tracking
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

        // Update LRU tracking for this cache access
        $this->update_lru_cache($key, $namespace);

        $this->stats['hits']++;
        return $cache_data['data'];
    }

    /**
     * Set cached data with improved collision prevention and size enforcement
     */
    public function set($key, $data, $ttl = null, $namespace = 'default') {
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }

        $file_path = $this->get_cache_file_path($key, $namespace);

        // Calculate data size
        $data_size = strlen(serialize([
            'data' => $data,
            'created' => time(),
            'expires' => time() + $ttl,
            'namespace' => $namespace,
            'key' => $key
        ]));

        // Check if we need to evict before adding new data
        if ($this->current_cache_size + $data_size > $this->max_cache_size) {
            $this->enforce_cache_size_limits();
        }

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
                error_log("SMO Social BoundedCache: Failed to write cache file: $file_path");
            }
            $this->stats['errors']++;
            return false;
        }

        // Update cache size tracking
        $this->current_cache_size += $result;
        $this->stats['sets']++;

        // Update LRU tracking
        $this->update_lru_cache($key, $namespace);

        return true;
    }

    /**
     * Delete cached data with namespace support
     */
    public function delete($key, $namespace = 'default') {
        $file_path = $this->get_cache_file_path($key, $namespace);

        if (file_exists($file_path)) {
            $file_size = filesize($file_path);
            $result = unlink($file_path);
            if ($result) {
                $this->current_cache_size = max(0, $this->current_cache_size - $file_size);
                $this->stats['deletes']++;

                // Remove from LRU tracking
                $cache_key = $this->generate_cache_key($key, $namespace);
                unset($this->lru_cache[$cache_key]);
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
        $size_reduced = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $size_reduced += filesize($file);
                unlink($file);
                $deleted_count++;
            }
        }

        $this->current_cache_size = max(0, $this->current_cache_size - $size_reduced);
        $this->stats['deletes'] += $deleted_count;

        // Clear LRU tracking for cleared namespace
        if ($namespace) {
            $this->clear_lru_by_namespace($namespace);
        } else {
            $this->initialize_lru_cache();
        }

        return true;
    }

    /**
     * Clear LRU tracking by namespace
     */
    private function clear_lru_by_namespace($namespace) {
        foreach ($this->lru_cache as $cache_key => $timestamp) {
            if (strpos($cache_key, $namespace . ':') === 0) {
                unset($this->lru_cache[$cache_key]);
            }
        }
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

            // Update cache size after cleanup
            $this->current_cache_size = $this->get_cache_size();

            return $result['processed'];
        } catch (\Exception $e) {
            error_log('SMO AI BoundedCache: Streaming cleanup failed, falling back to original method - ' . $e->getMessage());

            // Fallback to original method
            $files = glob($this->cache_dir . '**/*');
            $cleaned_count = 0;
            $size_reduced = 0;

            foreach ($files as $file) {
                if (is_file($file)) {
                    $cache_data = $this->read_cache_file($file);
                    if ($cache_data !== false && $cache_data['expires'] < time()) {
                        $size_reduced += filesize($file);
                        unlink($file);
                        $cleaned_count++;
                        $this->stats['evictions']++;
                    }
                }
            }

            $this->current_cache_size = max(0, $this->current_cache_size - $size_reduced);
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
                'current_cache_size' => $this->current_cache_size,
                'max_cache_size' => $this->max_cache_size,
                'cache_size_percentage' => $this->max_cache_size > 0 ? ($this->current_cache_size / $this->max_cache_size) * 100 : 0,
                'hit_rate' => 0,
                'miss_rate' => 0,
                'operations' => $this->stats,
                'namespaces' => $metadata_summary['namespaces'],
                'size_by_namespace' => $metadata_summary['size_by_namespace'],
                'lru_cache_size' => count($this->lru_cache),
                'memory_usage' => memory_get_usage(true) / (1024 * 1024)
            ];

            $total_requests = $stats['operations']['hits'] + $stats['operations']['misses'];
            if ($total_requests > 0) {
                $stats['hit_rate'] = $stats['operations']['hits'] / $total_requests;
                $stats['miss_rate'] = $stats['operations']['misses'] / $total_requests;
            }

            return $stats;
        } catch (\Exception $e) {
            error_log('SMO AI BoundedCache: Streaming stats failed, falling back to original method - ' . $e->getMessage());

            // Fallback to original method
            $files = glob($this->cache_dir . '**/*');
            $stats = [
                'total_files' => 0,
                'total_size' => 0,
                'expired_files' => 0,
                'valid_files' => 0,
                'current_cache_size' => $this->current_cache_size,
                'max_cache_size' => $this->max_cache_size,
                'cache_size_percentage' => $this->max_cache_size > 0 ? ($this->current_cache_size / $this->max_cache_size) * 100 : 0,
                'hit_rate' => 0,
                'miss_rate' => 0,
                'operations' => $this->stats,
                'lru_cache_size' => count($this->lru_cache),
                'memory_usage' => memory_get_usage(true) / (1024 * 1024)
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
            'errors' => 0,
            'memory_evictions' => 0,
            'size_evictions' => 0
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
        $result = $this->cleanup_expired();

        // Additional cleanup using the new cleanup mechanism
        $cleanup = $this->get_cache_cleanup();
        $cleanup->cleanup_idle_cache_objects();

        // Check memory usage and cleanup if needed
        $cleanup->check_memory_based_cleanup();

        return $result;
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
     * Register with MemoryMonitor for integrated monitoring
     */
    private function register_with_memory_monitor() {
        if (class_exists('\SMO_Social\Core\MemoryMonitor')) {
            try {
                // The MemoryMonitor will collect stats from BoundedCacheManager when available
                // This ensures the BoundedCacheManager is recognized as an integrated system
                error_log('SMO Social: BoundedCacheManager registered for memory monitoring integration');
            } catch (\Exception $e) {
                error_log('SMO Social: Failed to register BoundedCacheManager with MemoryMonitor: ' . $e->getMessage());
            }
        }
    }

    /**
     * Helper function to format bytes
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}

// Helper function for byte formatting
if (!function_exists('format_bytes')) {
    function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}