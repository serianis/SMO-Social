<?php
/**
 * AI Performance Optimizations for SMO Social Plugin
 * 
 * This file contains optimized AI processing functions and strategies
 * to improve the performance of AI operations by implementing
 * parallel processing, intelligent caching, and efficient resource management.
 * 
 * @package SMO_Social
 * @subpackage Performance_Optimizations
 * @since 1.0.0
 */

namespace SMO_Social\Performance\AI;

// Ensure direct file access is blocked
if (!defined('ABSPATH')) {
    wp_die(__('Access denied', 'smo-social'));
}

/**
 * AI Performance Optimizations Class
 * 
 * Contains optimized AI processing functions for improved performance
 */
class AIoptimizations {
    
    /**
     * Process multiple platforms in parallel with intelligent batching
     * 
     * @param array $platforms Array of platform slugs
     * @param string $content Content to process
     * @param string $task_type Type of AI task
     * @param array $options Additional options
     * @return array Results from all platforms
     */
    public static function process_platforms_parallel($platforms, $content, $task_type = 'caption', $options = array()) {
        if (empty($platforms) || empty($content)) {
            return array();
        }
        
        // Get AI manager instance
        $ai_manager = \SMO_Social\AI\Manager::getInstance();
        
        // Generate cache key for this batch
        $cache_key = 'smo_ai_batch_' . md5(serialize([$platforms, $content, $task_type, $options]));
        $cache_ttl = 30 * 60; // 30 minutes
        
        // Check cache first
        $cached_results = get_transient($cache_key);
        if ($cached_results !== false) {
            return $cached_results;
        }
        
        // Process platforms in intelligent batches
        $batch_size = self::calculate_optimal_batch_size($platforms, $task_type);
        $platform_batches = array_chunk($platforms, $batch_size);
        
        $all_results = array();
        $errors = array();
        
        foreach ($platform_batches as $batch) {
            $batch_results = self::process_batch_async($batch, $content, $task_type, $options);
            $all_results = array_merge($all_results, $batch_results['success']);
            $errors = array_merge($errors, $batch_results['errors']);
            
            // Small delay between batches to avoid overwhelming APIs
            if (count($platform_batches) > 1) {
                usleep(500000); // 0.5 second
            }
        }
        
        // Combine results and errors
        $results = array(
            'success' => $all_results,
            'errors' => $errors,
            'total_processed' => count($all_results),
            'total_errors' => count($errors),
            'processing_time' => microtime(true)
        );
        
        // Cache results
        set_transient($cache_key, $results, $cache_ttl);
        
        return $results;
    }
    
    /**
     * Calculate optimal batch size based on task type and platform load
     * 
     * @param array $platforms Array of platforms
     * @param string $task_type Type of task
     * @return int Optimal batch size
     */
    private static function calculate_optimal_batch_size($platforms, $task_type) {
        $base_batch_size = 3;
        
        // Adjust based on task complexity
        switch ($task_type) {
            case 'caption':
            case 'hashtag':
                $complexity_factor = 1; // Simple tasks
                break;
            case 'analyze':
            case 'sentiment':
                $complexity_factor = 0.7; // Medium complexity
                break;
            case 'repurpose':
            case 'generate':
                $complexity_factor = 0.5; // Complex tasks
                break;
            default:
                $complexity_factor = 0.8;
        }
        
        // Adjust based on platform count
        $platform_factor = min(1.0, count($platforms) / 6);
        
        // Calculate final batch size
        $optimal_size = max(1, (int) ($base_batch_size * $complexity_factor * $platform_factor));
        
        return min($optimal_size, count($platforms));
    }
    
    /**
     * Process a batch of platforms asynchronously
     * 
     * @param array $batch Array of platform slugs
     * @param string $content Content to process
     * @param string $task_type Type of AI task
     * @param array $options Additional options
     * @return array Results and errors
     */
    private static function process_batch_async($batch, $content, $task_type, $options) {
        $ai_manager = \SMO_Social\AI\Manager::getInstance();
        $results = array('success' => array(), 'errors' => array());
        
        // Create async requests
        $requests = array();
        foreach ($batch as $platform) {
            $requests[$platform] = self::create_async_ai_request($platform, $content, $task_type, $options);
        }
        
        // Wait for all requests to complete with timeout
        $timeout = 30; // 30 seconds max
        $start_time = time();
        
        foreach ($requests as $platform => $request) {
            // Check timeout
            if (time() - $start_time > $timeout) {
                $results['errors'][$platform] = 'Timeout exceeded';
                continue;
            }
            
            // Wait for response (simulated since we can't actually wait for async requests)
            $result = self::simulate_async_response($platform, $content, $task_type, $options);
            
            if (isset($result['error'])) {
                $results['errors'][$platform] = $result['error'];
            } else {
                $results['success'][$platform] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * Create async AI request (simulation for WordPress environment)
     * 
     * @param string $platform Platform slug
     * @param string $content Content to process
     * @param string $task_type Type of task
     * @param array $options Additional options
     * @return mixed Request identifier
     */
    private static function create_async_ai_request($platform, $content, $task_type, $options) {
        // In a real implementation, this would create actual async HTTP requests
        // For WordPress, we'll simulate with a unique identifier
        return array(
            'platform' => $platform,
            'task_id' => uniqid($platform . '_'),
            'timestamp' => time()
        );
    }
    
    /**
     * Simulate async response processing
     * 
     * @param string $platform Platform slug
     * @param string $content Content to process
     * @param string $task_type Type of task
     * @param array $options Additional options
     * @return array Result or error
     */
    private static function simulate_async_response($platform, $content, $task_type, $options) {
        try {
            $ai_manager = \SMO_Social\AI\Manager::getInstance();
            
            switch ($task_type) {
                case 'caption':
                    return $ai_manager->generate_captions($content, array($platform), $options);
                    
                case 'hashtag':
                    return $ai_manager->optimize_hashtags($content, array($platform), $options);
                    
                case 'analyze':
                    return array('sentiment' => $ai_manager->analyze_sentiment($content, $options));
                    
                case 'repurpose':
                    return $ai_manager->repurpose_content($content, array($platform), $options);
                    
                default:
                    return array('error' => 'Unknown task type: ' . $task_type);
            }
            
        } catch (\Exception $e) {
            error_log('SMO Social AI cache warming failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Intelligent cache warming for AI operations
     * 
     * @param array $common_tasks Common AI tasks to pre-cache
     * @return array Cache warming results
     */
    public static function warm_ai_cache($common_tasks = array()) {
        if (empty($common_tasks)) {
            $common_tasks = self::get_common_ai_tasks();
        }
        
        $results = array(
            'warmed' => array(),
            'failed' => array(),
            'total_time' => 0
        );
        
        $start_time = microtime(true);
        
        foreach ($common_tasks as $task) {
            try {
                $cache_key = 'smo_ai_warm_' . md5(serialize($task));
                
                // Only warm if not already cached
                if (get_transient($cache_key) === false) {
                    $result = self::execute_ai_task($task);
                    
                    if ($result !== false) {
                        set_transient($cache_key, $result, 60 * 60); // 1 hour
                        $results['warmed'][] = $task['type'];
                    } else {
                        $results['failed'][] = $task['type'];
                    }
                }
            } catch (\Exception $e) {
                $results['failed'][] = $task['type'] . ': ' . $e->getMessage();
            }
        }
        
        $results['total_time'] = microtime(true) - $start_time;
        
        return $results;
    }
    
    /**
     * Get common AI tasks for cache warming
     * 
     * @return array Common tasks
     */
    private static function get_common_ai_tasks() {
        return array(
            array(
                'type' => 'caption',
                'content' => 'Default social media content for optimization',
                'platforms' => array('twitter', 'facebook', 'instagram'),
                'options' => array('tone' => 'professional')
            ),
            array(
                'type' => 'hashtag',
                'content' => 'Default content for hashtag optimization',
                'platforms' => array('twitter', 'instagram'),
                'options' => array('max_hashtags' => 5)
            ),
            array(
                'type' => 'sentiment',
                'content' => 'Default content for sentiment analysis',
                'options' => array()
            )
        );
    }
    
    /**
     * Execute AI task with error handling and fallback
     * 
     * @param array $task Task definition
     * @return mixed Task result or false on failure
     */
    private static function execute_ai_task($task) {
        try {
            $ai_manager = \SMO_Social\AI\Manager::getInstance();
            
            switch ($task['type']) {
                case 'caption':
                    return $ai_manager->generate_captions(
                        $task['content'],
                        $task['platforms'],
                        $task['options']
                    );
                    
                case 'hashtag':
                    return $ai_manager->optimize_hashtags(
                        $task['content'],
                        $task['platforms'],
                        $task['options']
                    );
                    
                case 'sentiment':
                    return $ai_manager->analyze_sentiment(
                        $task['content'],
                        $task['options']
                    );
                    
                default:
                    return false;
            }
            
        } catch (\Exception $e) {
            error_log('SMO Social AI cache warming failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimize AI cache by removing old and unused entries
     * 
     * @param int $max_age Maximum age in seconds
     * @param int $max_size Maximum cache size in MB
     * @return array Optimization results
     */
    public static function optimize_ai_cache($max_age = 24 * 60 * 60, $max_size = 50) {
        global $wpdb;
        
        $cache_dir = wp_upload_dir()['basedir'] . '/smo-social/cache/';
        $current_time = time();
        
        if (!file_exists($cache_dir)) {
            return array('status' => 'no_cache_dir', 'cleared' => 0);
        }
        
        $files = glob($cache_dir . '*.cache');
        $total_size = 0;
        $cleared_size = 0;
        $files_cleared = 0;
        
        $file_info = array();
        
        // Collect file information
        foreach ($files as $file) {
            $size = filesize($file);
            $mtime = filemtime($file);
            $age = $current_time - $mtime;
            
            $file_info[] = array(
                'path' => $file,
                'size' => $size,
                'age' => $age,
                'mtime' => $mtime
            );
            
            $total_size += $size;
        }
        
        // Sort by age (oldest first)
        usort($file_info, function($a, $b) {
            return $a['age'] - $b['age'];
        });
        
        // Remove files that are too old or if cache is too large
        $size_threshold = $max_size * 1024 * 1024; // Convert MB to bytes
        
        foreach ($file_info as $file) {
            $should_delete = false;
            
            // Delete if older than max age
            if ($file['age'] > $max_age) {
                $should_delete = true;
            }
            
            // Delete if cache is too large (start with oldest files)
            if ($total_size > $size_threshold) {
                $should_delete = true;
                $total_size -= $file['size'];
            }
            
            if ($should_delete) {
                if (@unlink($file['path'])) {
                    $cleared_size += $file['size'];
                    $files_cleared++;
                }
            }
        }
        
        return array(
            'status' => 'optimized',
            'files_cleared' => $files_cleared,
            'size_cleared_mb' => round($cleared_size / (1024 * 1024), 2),
            'remaining_size_mb' => round(($total_size - $cleared_size) / (1024 * 1024), 2)
        );
    }
    
    /**
     * Get AI processing statistics
     * 
     * @return array AI performance statistics
     */
    public static function get_ai_statistics() {
        $stats = array(
            'cache_hit_rate' => 0,
            'avg_processing_time' => 0,
            'error_rate' => 0,
            'active_tasks' => 0,
            'queued_tasks' => 0
        );
        
        try {
            $ai_manager = \SMO_Social\AI\Manager::getInstance();
            $stats = $ai_manager->get_processing_stats();
        } catch (\Exception $e) {
            error_log('SMO Social AI statistics error: ' . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Schedule AI optimization tasks
     * 
     * @return void
     */
    public static function schedule_ai_optimizations() {
        // Only schedule events if WordPress functions are available
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            // Schedule cache optimization
            if (!wp_next_scheduled('smo_ai_cache_optimization')) {
                wp_schedule_event(time(), 'daily', 'smo_ai_cache_optimization');
            }
            
            // Schedule cache warming
            if (!wp_next_scheduled('smo_ai_cache_warming')) {
                wp_schedule_event(time(), 'twicedaily', 'smo_ai_cache_warming');
            }
            
            // Schedule AI statistics collection
            if (!wp_next_scheduled('smo_ai_statistics_collection')) {
                wp_schedule_event(time(), 'hourly', 'smo_ai_statistics_collection');
            }
        }
    }
    
    /**
     * Performance benchmark for AI operations
     * 
     * @param callable $function Function to benchmark
     * @param array $args Arguments for the function
     * @param int $iterations Number of iterations
     * @return array Benchmark results
     */
    public static function benchmark_ai_operation($function, $args = array(), $iterations = 10) {
        $times = array();
        $results = array();
        
        for ($i = 0; $i < $iterations; $i++) {
            $start_time = microtime(true);
            
            try {
                $result = call_user_func_array($function, $args);
                $end_time = microtime(true);
                
                $times[] = $end_time - $start_time;
                $results[] = $result;
            } catch (\Exception $e) {
                $times[] = -1; // Mark as failed
                $results[] = $e->getMessage();
            }
        }
        
        // Calculate statistics
        $valid_times = array_filter($times, function($time) {
            return $time > 0;
        });
        
        return array(
            'iterations' => $iterations,
            'successful_runs' => count($valid_times),
            'failed_runs' => $iterations - count($valid_times),
            'min_time' => !empty($valid_times) ? min($valid_times) : 0,
            'max_time' => !empty($valid_times) ? max($valid_times) : 0,
            'avg_time' => !empty($valid_times) ? array_sum($valid_times) / count($valid_times) : 0,
            'total_time' => array_sum(array_filter($times, function($time) { return $time > 0; })),
            'success_rate' => !empty($times) ? (count($valid_times) / count($times)) * 100 : 0
        );
    }
    
    /**
     * Adaptive AI processing based on system load
     * 
     * @param array $platforms Platforms to process
     * @param string $content Content to process
     * @param string $task_type Task type
     * @return array Processing results
     */
    public static function adaptive_ai_processing($platforms, $content, $task_type) {
        // Check system load
        $load_avg = sys_getloadavg();
        $cpu_count = shell_exec('nproc 2>/dev/null') ?: 1;
        
        // Calculate load percentage
        $load_percentage = ($load_avg[0] / $cpu_count) * 100;
        
        // Adjust batch size based on load
        if ($load_percentage > 80) {
            // High load - reduce batch size
            $batch_size = 1;
        } elseif ($load_percentage > 60) {
            // Medium load - medium batch size
            $batch_size = 2;
        } else {
            // Low load - normal batch size
            $batch_size = 3;
        }
        
        // Process with adjusted batch size
        return self::process_platforms_with_batch_size(
            $platforms,
            $content,
            $task_type,
            $batch_size
        );
    }
    
    /**
     * Process platforms with specific batch size
     * 
     * @param array $platforms Platforms to process
     * @param string $content Content to process
     * @param string $task_type Task type
     * @param int $batch_size Batch size
     * @return array Processing results
     */
    private static function process_platforms_with_batch_size($platforms, $content, $task_type, $batch_size) {
        $batches = array_chunk($platforms, $batch_size);
        $results = array();
        
        foreach ($batches as $batch) {
            $batch_results = self::process_batch_async(
                $batch,
                $content,
                $task_type,
                array()
            );
            
            $results = array_merge($results, $batch_results['success']);
            
            // Delay between batches
            usleep(200000); // 0.2 seconds
        }
        
        return $results;
    }
}
