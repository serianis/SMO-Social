<?php
/**
 * Bounded Cache Object Pool for SMO Social
 *
 * Implements bounded pool sizes with automatic cleanup
 * using memory-based eviction policies for cache object pooling
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class BoundedCacheObjectPool {
    /**
     * @var array Cache object pool
     */
    private $pool = [];

    /**
     * @var array Available cache objects
     */
    private $available_objects = [];

    /**
     * @var array In-use cache objects
     */
    private $in_use_objects = [];

    /**
     * @var int Maximum pool size
     */
    private $max_pool_size = 20;

    /**
     * @var int Current pool size
     */
    private $current_pool_size = 0;

    /**
     * @var int Object timeout in seconds
     */
    private $object_timeout = 600;

    /**
     * @var int Maximum memory usage in MB
     */
    private $max_memory_usage = 50;

    /**
     * @var int Current memory usage in MB
     */
    private $current_memory_usage = 0;

    /**
     * @var array Memory usage tracking
     */
    private $memory_usage = [
        'total_allocated' => 0,
        'peak_usage' => 0,
        'current_usage' => 0
    ];

    /**
     * @var array Pool statistics
     */
    private $stats = [
        'objects_created' => 0,
        'objects_reused' => 0,
        'objects_closed' => 0,
        'object_errors' => 0,
        'hit_rate' => 0.0,
        'miss_rate' => 0.0,
        'evictions' => 0,
        'memory_evictions' => 0,
        'size_evictions' => 0
    ];

    /**
     * @var array LRU tracking for objects
     */
    private $lru_tracking = [];

    /**
     * @var int LRU timestamp counter
     */
    private $lru_timestamp = 0;

    /**
     * BoundedCacheObjectPool constructor
     *
     * @param int $max_pool_size Maximum pool size
     * @param int $object_timeout Object timeout in seconds
     * @param int $max_memory_usage Maximum memory usage in MB
     */
    public function __construct(int $max_pool_size = 20, int $object_timeout = 600, int $max_memory_usage = 50) {
        $this->max_pool_size = max(5, min(100, $max_pool_size));
        $this->object_timeout = max(300, min(3600, $object_timeout));
        $this->max_memory_usage = max(10, $max_memory_usage); // Minimum 10MB
        $this->initialize_pool();
        $this->initialize_lru_tracking();
    }

    /**
     * Initialize LRU tracking
     */
    private function initialize_lru_tracking() {
        $this->lru_tracking = [];
        $this->lru_timestamp = time();
    }

    /**
     * Initialize the object pool
     */
    private function initialize_pool() {
        // Start with a few pre-created cache objects
        for ($i = 0; $i < min(5, $this->max_pool_size); $i++) {
            $this->create_new_cache_object();
        }
    }

    /**
     * Update LRU tracking for an object
     */
    private function update_lru_tracking($object_id) {
        $this->lru_timestamp++;
        $this->lru_tracking[$object_id] = $this->lru_timestamp;
    }

    /**
     * Create a new cache object with memory tracking
     *
     * @return string|null Cache object ID
     */
    private function create_new_cache_object() {
        if ($this->current_pool_size >= $this->max_pool_size) {
            return null;
        }

        try {
            // Check memory usage before creating new object
            $current_memory = memory_get_usage(true) / (1024 * 1024);
            if ($current_memory > $this->max_memory_usage) {
                // Memory limit reached, perform eviction
                $this->perform_memory_based_eviction();
                return null;
            }

            // Track memory before object creation
            $memory_before = memory_get_usage(true);

            // Create a new cache object with minimal memory footprint
            $cache_object = new \stdClass();
            $cache_object->data = [];
            $cache_object->metadata = [
                'created_at' => time(),
                'last_used' => time(),
                'access_count' => 0,
                'memory_usage' => 0,
                'is_valid' => true
            ];

            // Track memory after object creation
            $memory_after = memory_get_usage(true);
            $object_memory = $memory_after - $memory_before;

            $object_id = uniqid('cache_obj_', true);
            $this->pool[$object_id] = [
                'object' => $cache_object,
                'created_at' => time(),
                'last_used' => time(),
                'memory_usage' => $object_memory,
                'access_count' => 0,
                'is_valid' => true
            ];

            $this->available_objects[] = $object_id;
            $this->current_pool_size++;
            $this->stats['objects_created']++;

            // Update memory tracking
            $this->memory_usage['total_allocated'] += $object_memory;
            $this->memory_usage['current_usage'] += $object_memory;
            $this->memory_usage['peak_usage'] = max($this->memory_usage['peak_usage'], $this->memory_usage['current_usage']);

            // Update LRU tracking
            $this->update_lru_tracking($object_id);

            return $object_id;

        } catch (\Exception $e) {
            error_log('SMO Social BoundedCacheObjectPool: ' . $e->getMessage());
            $this->stats['object_errors']++;
            return null;
        }
    }

    /**
     * Get a cache object from the pool with bounded size enforcement
     *
     * @return object|null Cache object
     */
    public function get_cache_object() {
        $this->stats['total_requests'] = ($this->stats['total_requests'] ?? 0) + 1;

        // Check memory usage before getting object
        $this->check_memory_usage();

        // Try to get an available object first
        if (!empty($this->available_objects)) {
            $object_id = array_shift($this->available_objects);

            if (isset($this->pool[$object_id])) {
                $object_data =& $this->pool[$object_id];

                // Validate object before reuse
                if ($this->validate_cache_object($object_data)) {
                    $object_data['last_used'] = time();
                    $object_data['access_count']++;
                    $this->in_use_objects[$object_id] = true;
                    $this->stats['objects_reused']++;

                    // Update LRU tracking
                    $this->update_lru_tracking($object_id);

                    // Reset object data for new use
                    $object_data['object']->data = [];
                    $object_data['object']->metadata['access_count'] = 0;

                    return $object_data['object'];
                } else {
                    // Object is invalid, remove it
                    $this->close_cache_object($object_id);
                }
            }
        }

        // No available objects, try to create a new one
        if ($this->current_pool_size < $this->max_pool_size) {
            $new_object_id = $this->create_new_cache_object();
            if ($new_object_id) {
                $this->in_use_objects[$new_object_id] = true;
                return $this->pool[$new_object_id]['object'];
            }
        }

        // Pool is full and no objects available - implement LRU eviction
        if ($this->current_pool_size >= $this->max_pool_size) {
            $this->evict_lru_object();
            return $this->get_cache_object(); // Recursive call after eviction
        }

        // Shouldn't reach here
        $this->stats['pool_exhausted'] = ($this->stats['pool_exhausted'] ?? 0) + 1;
        return null;
    }

    /**
     * Validate a cache object
     *
     * @param array $object_data Cache object data
     * @return bool True if object is valid
     */
    private function validate_cache_object($object_data) {
        try {
            if (!isset($object_data['object']) || !$object_data['is_valid']) {
                return false;
            }

            // Check if object has been idle too long
            if ((time() - $object_data['last_used']) > $this->object_timeout) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Release a cache object back to the pool
     *
     * @param object $cache_object Cache object
     * @return bool True if object was released successfully
     */
    public function release_cache_object($cache_object) {
        foreach ($this->pool as $object_id => $object_data) {
            if ($object_data['object'] === $cache_object) {
                if (isset($this->in_use_objects[$object_id])) {
                    unset($this->in_use_objects[$object_id]);

                    // Clean up object data before releasing
                    $cache_object->data = [];
                    $cache_object->metadata['access_count'] = 0;

                    $this->available_objects[] = $object_id;
                    $this->pool[$object_id]['last_used'] = time();

                    // Update LRU tracking
                    $this->update_lru_tracking($object_id);

                    return true;
                }
                return false;
            }
        }
        return false;
    }

    /**
     * Close a cache object and remove it from the pool
     *
     * @param string $object_id Object ID
     */
    private function close_cache_object($object_id) {
        if (isset($this->pool[$object_id])) {
            $object_data = $this->pool[$object_id];

            // Clean up object data
            if (isset($object_data['object']->data)) {
                $object_data['object']->data = [];
            }

            // Update memory tracking
            $this->memory_usage['current_usage'] -= $object_data['memory_usage'];
            $this->current_pool_size--;
            $this->stats['objects_closed']++;
            $this->stats['evictions']++;

            // Remove from all tracking arrays
            unset($this->pool[$object_id]);
            unset($this->in_use_objects[$object_id]);

            // Remove from available objects if present
            $index = array_search($object_id, $this->available_objects);
            if ($index !== false) {
                unset($this->available_objects[$index]);
            }

            // Remove from LRU tracking
            unset($this->lru_tracking[$object_id]);
        }
    }

    /**
     * Evict least recently used object when pool is full
     */
    private function evict_lru_object() {
        $lru_object_id = null;
        $lru_time = PHP_INT_MAX;

        // Find the least recently used available object
        foreach ($this->available_objects as $object_id) {
            if (isset($this->pool[$object_id]) &&
                $this->pool[$object_id]['last_used'] < $lru_time) {
                $lru_object_id = $object_id;
                $lru_time = $this->pool[$object_id]['last_used'];
            }
        }

        if ($lru_object_id) {
            $this->close_cache_object($lru_object_id);
            $this->stats['size_evictions']++;
        }
    }

    /**
     * Perform memory-based eviction when memory usage is high
     */
    private function perform_memory_based_eviction() {
        $current_memory = memory_get_usage(true) / (1024 * 1024);

        if ($current_memory > $this->max_memory_usage) {
            error_log('SMO Social BoundedCacheObjectPool: High memory usage detected - ' . $current_memory . 'MB, performing memory-based eviction');

            // Sort objects by last used time (oldest first)
            $objects_by_lru = $this->lru_tracking;
            asort($objects_by_lru);

            $evicted_count = 0;
            $target_evictions = min(5, count($objects_by_lru) / 2); // Evict up to 50% of objects

            foreach ($objects_by_lru as $object_id => $timestamp) {
                if ($evicted_count >= $target_evictions) {
                    break;
                }

                if (isset($this->pool[$object_id]) && !isset($this->in_use_objects[$object_id])) {
                    $this->close_cache_object($object_id);
                    $evicted_count++;
                    $this->stats['memory_evictions']++;
                }
            }

            // Force garbage collection
            gc_collect_cycles();

            error_log("SMO Social BoundedCacheObjectPool: Memory-based eviction completed. Evicted $evicted_count objects");
        }
    }

    /**
     * Check memory usage and perform cleanup if needed
     */
    private function check_memory_usage() {
        $current_memory = memory_get_usage(true) / (1024 * 1024);
        $this->current_memory_usage = $current_memory;

        if ($current_memory > $this->max_memory_usage * 0.9) { // 90% of limit
            $this->perform_memory_based_eviction();
        }
    }

    /**
     * Clean up idle cache objects with memory awareness
     *
     * @param int $max_idle_time Maximum idle time in seconds
     */
    public function cleanup_idle_objects($max_idle_time = 300) {
        $current_time = time();
        $objects_to_remove = [];

        foreach ($this->pool as $object_id => $object_data) {
            if (!isset($this->in_use_objects[$object_id]) &&
                ($current_time - $object_data['last_used']) > $max_idle_time) {
                $objects_to_remove[] = $object_id;
            }
        }

        foreach ($objects_to_remove as $object_id) {
            $this->close_cache_object($object_id);
        }
    }

    /**
     * Get pool statistics with memory and eviction info
     *
     * @return array Pool statistics
     */
    public function get_stats() {
        $total_requests = $this->stats['total_requests'] ?? 1;
        $this->stats['hit_rate'] = $total_requests > 0
            ? ($this->stats['objects_reused'] / $total_requests)
            : 0.0;
        $this->stats['miss_rate'] = $total_requests > 0
            ? (($total_requests - $this->stats['objects_reused']) / $total_requests)
            : 0.0;

        return array_merge($this->stats, [
            'current_pool_size' => $this->current_pool_size,
            'available_objects' => count($this->available_objects),
            'in_use_objects' => count($this->in_use_objects),
            'max_pool_size' => $this->max_pool_size,
            'memory_usage' => $this->memory_usage,
            'lru_cache_size' => count($this->lru_tracking),
            'current_memory_usage' => $this->current_memory_usage,
            'memory_limit' => $this->max_memory_usage,
            'memory_percentage' => $this->max_memory_usage > 0 ? ($this->current_memory_usage / $this->max_memory_usage) * 100 : 0
        ]);
    }

    /**
     * Get memory usage information
     *
     * @return array Memory usage data
     */
    public function get_memory_usage() {
        return $this->memory_usage;
    }

    /**
     * Set maximum pool size with memory awareness
     *
     * @param int $size Maximum pool size
     */
    public function set_max_pool_size($size) {
        $this->max_pool_size = max(5, min(100, $size));

        // If reducing pool size, cleanup excess objects
        if ($this->current_pool_size > $this->max_pool_size) {
            $this->cleanup_excess_objects();
        }
    }

    /**
     * Set maximum memory usage
     *
     * @param int $max_memory_usage Maximum memory usage in MB
     */
    public function set_max_memory_usage($max_memory_usage) {
        $this->max_memory_usage = max(10, $max_memory_usage); // Minimum 10MB
        $this->check_memory_usage();
    }

    /**
     * Get maximum memory usage
     *
     * @return int Maximum memory usage in MB
     */
    public function get_max_memory_usage() {
        return $this->max_memory_usage;
    }

    /**
     * Cleanup excess objects when pool size is reduced
     */
    private function cleanup_excess_objects() {
        while ($this->current_pool_size > $this->max_pool_size) {
            // Find the least recently used available object
            $lru_object_id = null;
            $lru_time = PHP_INT_MAX;

            foreach ($this->available_objects as $object_id) {
                if (isset($this->pool[$object_id]) &&
                    $this->pool[$object_id]['last_used'] < $lru_time) {
                    $lru_object_id = $object_id;
                    $lru_time = $this->pool[$object_id]['last_used'];
                }
            }

            if ($lru_object_id) {
                $this->close_cache_object($lru_object_id);
            } else {
                break; // No more objects to remove
            }
        }
    }

    /**
     * Get current pool size
     *
     * @return int Current pool size
     */
    public function get_current_pool_size() {
        return $this->current_pool_size;
    }

    /**
     * Get available object count
     *
     * @return int Available objects
     */
    public function get_available_objects_count() {
        return count($this->available_objects);
    }

    /**
     * Clear the entire object pool with memory cleanup
     */
    public function clear_pool() {
        foreach ($this->pool as $object_id => $object_data) {
            $this->close_cache_object($object_id);
        }

        $this->pool = [];
        $this->available_objects = [];
        $this->in_use_objects = [];
        $this->current_pool_size = 0;
        $this->memory_usage = [
            'total_allocated' => 0,
            'peak_usage' => 0,
            'current_usage' => 0
        ];

        $this->lru_tracking = [];
        $this->lru_timestamp = 0;

        // Force garbage collection
        gc_collect_cycles();
    }

    /**
     * Get cache object with specific data
     *
     * @param array $initial_data Initial data for the cache object
     * @return object|null Cache object
     */
    public function get_cache_object_with_data(array $initial_data = []) {
        $cache_object = $this->get_cache_object();
        if ($cache_object) {
            $cache_object->data = $initial_data;
            $cache_object->metadata['access_count'] = 1;
        }
        return $cache_object;
    }

    /**
     * Get pool status information with memory details
     *
     * @return array Pool status information
     */
    public function get_pool_status() {
        $objects_info = [];
        foreach ($this->pool as $object_id => $object_data) {
            $objects_info[] = [
                'id' => $object_id,
                'created_at' => $object_data['created_at'],
                'last_used' => $object_data['last_used'],
                'access_count' => $object_data['access_count'],
                'memory_usage' => $object_data['memory_usage'],
                'is_in_use' => isset($this->in_use_objects[$object_id]),
                'is_valid' => $object_data['is_valid'],
                'lru_timestamp' => $this->lru_tracking[$object_id] ?? 0
            ];
        }

        return [
            'objects' => $objects_info,
            'pool_size' => $this->current_pool_size,
            'max_pool_size' => $this->max_pool_size,
            'available' => count($this->available_objects),
            'in_use' => count($this->in_use_objects),
            'memory_usage' => $this->memory_usage,
            'stats' => $this->get_stats(),
            'lru_tracking' => [
                'size' => count($this->lru_tracking),
                'recent' => array_slice($this->lru_tracking, -5, 5, true)
            ]
        ];
    }

    /**
     * Get LRU tracking statistics
     */
    public function get_lru_stats() {
        return [
            'lru_tracking_size' => count($this->lru_tracking),
            'lru_timestamp' => $this->lru_timestamp,
            'recent_accesses' => array_slice($this->lru_tracking, -10, 10, true)
        ];
    }

    /**
     * Perform comprehensive memory cleanup
     */
    public function perform_memory_cleanup() {
        // Clean up idle objects
        $this->cleanup_idle_objects();

        // Perform memory-based eviction
        $this->perform_memory_based_eviction();

        // Force garbage collection
        gc_collect_cycles();

        return [
            'memory_before' => $this->current_memory_usage,
            'memory_after' => memory_get_usage(true) / (1024 * 1024),
            'objects_evicted' => $this->stats['memory_evictions'] ?? 0
        ];
    }

    /**
     * Reset statistics counters
     */
    public function reset_stats() {
        $this->stats = [
            'objects_created' => 0,
            'objects_reused' => 0,
            'objects_closed' => 0,
            'object_errors' => 0,
            'hit_rate' => 0.0,
            'miss_rate' => 0.0,
            'evictions' => 0,
            'memory_evictions' => 0,
            'size_evictions' => 0
        ];
        return true;
    }
}