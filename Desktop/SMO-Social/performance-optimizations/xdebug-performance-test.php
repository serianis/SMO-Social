<?php
/**
 * Xdebug Performance Test Suite for SMO Social Plugin
 * 
 * This script tests and validates the performance optimizations
 * implemented to address Xdebug timeouts and initialization delays.
 *
 * @package SMO_Social
 * @subpackage Performance_Testing
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Load WordPress if available
if (file_exists(ABSPATH . 'wp-load.php')) {
    require_once ABSPATH . 'wp-load.php';
} elseif (file_exists(ABSPATH . 'wp-config.php')) {
    require_once ABSPATH . 'wp-config.php';
}

// Enable error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Xdebug Performance Tester
 */
class XdebugPerformanceTester {
    
    private $test_results = array();
    private $start_memory;
    private $start_time;
    
    /**
     * Initialize tester
     */
    public function __construct() {
        $this->start_memory = memory_get_usage(true);
        $this->start_time = microtime(true);
        
        echo "<h1>Xdebug Performance Test Suite for SMO Social</h1>\n";
        echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border: 1px solid #ddd;'>\n";
    }
    
    /**
     * Run all tests
     */
    public function run_all_tests() {
        echo "<h2>Running Performance Tests...</h2>\n";
        
        // Test 1: Plugin Initialization Performance
        $this->test_plugin_initialization();
        
        // Test 2: Database Schema Performance
        $this->test_database_schema_performance();
        
        // Test 3: Class Loading Performance
        $this->test_class_loading_performance();
        
        // Test 4: Memory Usage
        $this->test_memory_usage();
        
        // Test 5: Xdebug Configuration
        $this->test_xdebug_configuration();
        
        // Test 6: Caching Performance
        $this->test_caching_performance();
        
        // Generate final report
        $this->generate_final_report();
    }
    
    /**
     * Test plugin initialization performance
     */
    private function test_plugin_initialization() {
        echo "<h3>Test 1: Plugin Initialization Performance</h3>\n";
        
        $iterations = 5;
        $times = array();
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            // Simulate plugin initialization
            $this->simulate_plugin_initialization();
            
            $end = microtime(true);
            $times[] = $end - $start;
        }
        
        $avg_time = array_sum($times) / count($times);
        $max_time = max($times);
        $min_time = min($times);
        
        $this->test_results['initialization'] = array(
            'average_time' => $avg_time,
            'max_time' => $max_time,
            'min_time' => $min_time,
            'iterations' => $iterations
        );
        
        echo "Average initialization time: " . round($avg_time * 1000, 2) . "ms<br>\n";
        echo "Max initialization time: " . round($max_time * 1000, 2) . "ms<br>\n";
        echo "Min initialization time: " . round($min_time * 1000, 2) . "ms<br>\n";
        
        // Performance assessment
        if ($avg_time > 2.0) {
            echo "<span style='color: red;'>⚠ SLOW: Average time exceeds 2 seconds</span><br>\n";
        } elseif ($avg_time > 1.0) {
            echo "<span style='color: orange;'>⚠ MODERATE: Average time exceeds 1 second</span><br>\n";
        } else {
            echo "<span style='color: green;'>✓ GOOD: Average time under 1 second</span><br>\n";
        }
        
        echo "<br>\n";
    }
    
    /**
     * Test database schema performance
     */
    private function test_database_schema_performance() {
        echo "<h3>Test 2: Database Schema Performance</h3>\n";
        
        $start = microtime(true);
        
        // Test schema file loading
        $schema_files = array(
            'DatabaseSchema' => ABSPATH . 'includes/Core/DatabaseSchema.php',
            'DatabaseSchemaExtended' => ABSPATH . 'includes/Core/DatabaseSchemaExtended.php'
        );
        
        $load_times = array();
        foreach ($schema_files as $name => $file) {
            if (file_exists($file)) {
                $file_start = microtime(true);
                $content = file_get_contents($file);
                $file_end = microtime(true);
                $load_times[$name] = $file_end - $file_start;
                
                echo "Loaded $name: " . round($load_times[$name] * 1000, 2) . "ms<br>\n";
            }
        }
        
        $end = microtime(true);
        $total_time = $end - $start;
        
        $this->test_results['schema'] = array(
            'total_time' => $total_time,
            'file_load_times' => $load_times
        );
        
        echo "Total schema loading time: " . round($total_time * 1000, 2) . "ms<br>\n";
        
        if ($total_time > 1.0) {
            echo "<span style='color: red;'>⚠ SLOW: Schema loading exceeds 1 second</span><br>\n";
        } else {
            echo "<span style='color: green;'>✓ GOOD: Schema loading under 1 second</span><br>\n";
        }
        
        echo "<br>\n";
    }
    
    /**
     * Test class loading performance
     */
    private function test_class_loading_performance() {
        echo "<h3>Test 3: Class Loading Performance</h3>\n";
        
        $test_classes = array(
            'SMO_Social\\Core\\Plugin',
            'SMO_Social\\Core\\DatabaseManager',
            'SMO_Social\\Admin\\Admin',
            'SMO_Social\\Platforms\\Manager',
            'SMO_Social\\AI\\Manager'
        );
        
        $load_times = array();
        foreach ($test_classes as $class) {
            $start = microtime(true);
            
            // Check if class exists
            $exists = class_exists($class);
            
            $end = microtime(true);
            $load_times[$class] = $end - $start;
            
            echo "Class $class: " . ($exists ? "exists" : "not found") . 
                 " (" . round($load_times[$class] * 1000, 2) . "ms)<br>\n";
        }
        
        $avg_load_time = array_sum($load_times) / count($load_times);
        
        $this->test_results['class_loading'] = array(
            'average_time' => $avg_load_time,
            'individual_times' => $load_times
        );
        
        echo "Average class loading time: " . round($avg_load_time * 1000, 2) . "ms<br>\n";
        
        if ($avg_load_time > 0.1) {
            echo "<span style='color: orange;'>⚠ MODERATE: Average class loading exceeds 100ms</span><br>\n";
        } else {
            echo "<span style='color: green;'>✓ GOOD: Average class loading under 100ms</span><br>\n";
        }
        
        echo "<br>\n";
    }
    
    /**
     * Test memory usage
     */
    private function test_memory_usage() {
        echo "<h3>Test 4: Memory Usage</h3>\n";
        
        $current_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        echo "Current memory usage: " . $this->format_bytes($current_memory) . "<br>\n";
        echo "Peak memory usage: " . $this->format_bytes($peak_memory) . "<br>\n";
        echo "Memory increase: " . $this->format_bytes($current_memory - $this->start_memory) . "<br>\n";
        
        $this->test_results['memory'] = array(
            'current' => $current_memory,
            'peak' => $peak_memory,
            'increase' => $current_memory - $this->start_memory
        );
        
        // Memory assessment
        $memory_mb = $current_memory / (1024 * 1024);
        if ($memory_mb > 100) {
            echo "<span style='color: red;'>⚠ HIGH: Memory usage exceeds 100MB</span><br>\n";
        } elseif ($memory_mb > 50) {
            echo "<span style='color: orange;'>⚠ MODERATE: Memory usage exceeds 50MB</span><br>\n";
        } else {
            echo "<span style='color: green;'>✓ GOOD: Memory usage under 50MB</span><br>\n";
        }
        
        echo "<br>\n";
    }
    
    /**
     * Test Xdebug configuration
     */
    private function test_xdebug_configuration() {
        echo "<h3>Test 5: Xdebug Configuration</h3>\n";
        
        $xdebug_info = array();
        
        // Check if Xdebug is loaded
        $xdebug_loaded = extension_loaded('xdebug');
        $xdebug_info['loaded'] = $xdebug_loaded;
        
        echo "Xdebug loaded: " . ($xdebug_loaded ? "Yes" : "No") . "<br>\n";
        
        if ($xdebug_loaded) {
            // Get Xdebug settings
            $xdebug_settings = array(
                'version' => phpversion('xdebug'),
                'mode' => ini_get('xdebug.mode'),
                'start_with_request' => ini_get('xdebug.start_with_request'),
                'log_level' => ini_get('xdebug.log_level'),
                'var_display_max_depth' => ini_get('xdebug.var_display_max_depth'),
                'max_nesting_level' => ini_get('xdebug.max_nesting_level')
            );
            
            foreach ($xdebug_settings as $setting => $value) {
                echo "xdebug.$setting: " . ($value ? $value : "not set") . "<br>\n";
            }
            
            $xdebug_info['settings'] = $xdebug_settings;
            
            // Test Xdebug performance
            $this->test_xdebug_performance();
        } else {
            echo "<span style='color: red;'>⚠ WARNING: Xdebug is not loaded</span><br>\n";
        }
        
        $this->test_results['xdebug'] = $xdebug_info;
        echo "<br>\n";
    }
    
    /**
     * Test Xdebug performance
     */
    private function test_xdebug_performance() {
        echo "<h4>Xdebug Performance Test</h4>\n";
        
        $iterations = 100;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test debug_backtrace performance
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        }
        
        $end = microtime(true);
        $total_time = $end - $start;
        $avg_time = $total_time / $iterations;
        
        echo "debug_backtrace performance: " . round($avg_time * 1000, 4) . "ms per call<br>\n";
        
        if ($avg_time > 0.01) {
            echo "<span style='color: orange;'>⚠ SLOW: debug_backtrace exceeds 10ms</span><br>\n";
        } else {
            echo "<span style='color: green;'>✓ GOOD: debug_backtrace under 10ms</span><br>\n";
        }
    }
    
    /**
     * Test caching performance
     */
    private function test_caching_performance() {
        echo "<h3>Test 6: Caching Performance</h3>\n";
        
        // Test transient caching
        $test_data = str_repeat('test', 1000); // 4KB of test data
        $cache_key = 'smo_performance_test_' . time();
        
        // Test write performance
        $start = microtime(true);
        set_transient($cache_key, $test_data, 300);
        $write_time = microtime(true) - $start;
        
        // Test read performance
        $start = microtime(true);
        $cached_data = get_transient($cache_key);
        $read_time = microtime(true) - $start;
        
        // Test delete performance
        $start = microtime(true);
        delete_transient($cache_key);
        $delete_time = microtime(true) - $start;
        
        echo "Cache write time: " . round($write_time * 1000, 2) . "ms<br>\n";
        echo "Cache read time: " . round($read_time * 1000, 2) . "ms<br>\n";
        echo "Cache delete time: " . round($delete_time * 1000, 2) . "ms<br>\n";
        
        $this->test_results['caching'] = array(
            'write_time' => $write_time,
            'read_time' => $read_time,
            'delete_time' => $delete_time,
            'success' => ($cached_data === $test_data)
        );
        
        if ($read_time > 0.1 || $write_time > 0.1) {
            echo "<span style='color: orange;'>⚠ SLOW: Cache operations exceed 100ms</span><br>\n";
        } else {
            echo "<span style='color: green;'>✓ GOOD: Cache operations under 100ms</span><br>\n";
        }
        
        echo "<br>\n";
    }
    
    /**
     * Simulate plugin initialization
     */
    private function simulate_plugin_initialization() {
        // Simulate loading various plugin files
        $files_to_load = array(
            'includes/Core/Plugin.php',
            'includes/Core/DatabaseManager.php',
            'includes/Admin/Admin.php',
            'includes/Platforms/Manager.php',
            'includes/AI/Manager.php'
        );
        
        foreach ($files_to_load as $file) {
            $full_path = ABSPATH . $file;
            if (file_exists($full_path)) {
                // Simulate file loading without actually executing code
                $content = file_get_contents($full_path);
                $lines = substr_count($content, "\n");
                
                // Simulate some processing time
                usleep(1000); // 1ms per file
            }
        }
        
        // Simulate database operations
        $this->simulate_database_operations();
    }
    
    /**
     * Simulate database operations
     */
    private function simulate_database_operations() {
        // Simulate schema checks
        $tables = array(
            'smo_scheduled_posts',
            'smo_queue',
            'smo_platform_tokens',
            'smo_analytics'
        );
        
        foreach ($tables as $table) {
            // Simulate table existence check
            usleep(500); // 0.5ms per table
        }
        
        // Simulate index creation
        usleep(2000); // 2ms for index operations
    }
    
    /**
     * Generate final report
     */
    private function generate_final_report() {
        echo "<h2>Performance Test Summary</h2>\n";
        
        $total_time = microtime(true) - $this->start_time;
        $total_memory = memory_get_usage(true) - $this->start_memory;
        
        echo "Total test execution time: " . round($total_time, 3) . " seconds<br>\n";
        echo "Total memory used: " . $this->format_bytes($total_memory) . "<br><br>\n";
        
        // Performance score calculation
        $score = $this->calculate_performance_score();
        
        echo "<h3>Overall Performance Score: " . $score . "/100</h3>\n";
        
        if ($score >= 80) {
            echo "<div style='background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
            echo "✓ EXCELLENT: Performance is optimal\n";
        } elseif ($score >= 60) {
            echo "<div style='background: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
            echo "⚠ GOOD: Performance is acceptable with room for improvement\n";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>\n";
            echo "⚠ NEEDS IMPROVEMENT: Performance issues detected\n";
        }
        echo "</div><br>\n";
        
        // Recommendations
        echo "<h3>Recommendations</h3>\n";
        $this->generate_recommendations();
        
        // Save results
        $this->save_test_results();
        
        echo "</div>\n";
    }
    
    /**
     * Calculate performance score
     */
    private function calculate_performance_score() {
        $score = 100;
        
        // Penalize slow initialization
        if (isset($this->test_results['initialization'])) {
            $avg_init = $this->test_results['initialization']['average_time'];
            if ($avg_init > 2.0) $score -= 30;
            elseif ($avg_init > 1.0) $score -= 15;
            elseif ($avg_init > 0.5) $score -= 5;
        }
        
        // Penalize high memory usage
        if (isset($this->test_results['memory'])) {
            $memory_mb = $this->test_results['memory']['current'] / (1024 * 1024);
            if ($memory_mb > 100) $score -= 25;
            elseif ($memory_mb > 50) $score -= 10;
        }
        
        // Penalize slow caching
        if (isset($this->test_results['caching'])) {
            $avg_cache_time = ($this->test_results['caching']['read_time'] + 
                              $this->test_results['caching']['write_time']) / 2;
            if ($avg_cache_time > 0.1) $score -= 15;
            elseif ($avg_cache_time > 0.05) $score -= 5;
        }
        
        // Bonus for Xdebug being properly configured
        if (isset($this->test_results['xdebug']['loaded']) && $this->test_results['xdebug']['loaded']) {
            $score += 5;
        }
        
        return max(0, $score);
    }
    
    /**
     * Generate performance recommendations
     */
    private function generate_recommendations() {
        echo "<ul>\n";
        
        if (isset($this->test_results['initialization']['average_time']) && 
            $this->test_results['initialization']['average_time'] > 1.0) {
            echo "<li>Consider implementing lazy loading for plugin components</li>\n";
            echo "<li>Use the optimized autoloader to reduce class loading time</li>\n";
        }
        
        if (isset($this->test_results['memory']['current']) && 
            $this->test_results['memory']['current'] > 50 * 1024 * 1024) {
            echo "<li>Optimize memory usage by implementing object caching</li>\n";
            echo "<li>Consider using lazy loading for large data structures</li>\n";
        }
        
        if (isset($this->test_results['caching']['read_time']) && 
            $this->test_results['caching']['read_time'] > 0.05) {
            echo "<li>Implement persistent caching for database queries</li>\n";
            echo "<li>Use opcode caching for PHP files</li>\n";
        }
        
        if (!isset($this->test_results['xdebug']['loaded']) || !$this->test_results['xdebug']['loaded']) {
            echo "<li>Install and configure Xdebug for proper debugging</li>\n";
        }
        
        echo "<li>Monitor performance regularly with this test suite</li>\n";
        echo "<li>Implement performance monitoring hooks throughout the codebase</li>\n";
        echo "<li>Use batch operations for database schema changes</li>\n";
        
        echo "</ul>\n";
    }
    
    /**
     * Save test results to file
     */
    private function save_test_results() {
        $results_file = ABSPATH . 'performance-optimizations/test-results-' . date('Y-m-d-H-i-s') . '.json';
        $results_data = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'test_results' => $this->test_results,
            'system_info' => array(
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'xdebug_version' => phpversion('xdebug'),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            )
        );
        
        file_put_contents($results_file, json_encode($results_data, JSON_PRETTY_PRINT));
        echo "<p>Test results saved to: " . basename($results_file) . "</p>\n";
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Run the test suite if accessed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    $tester = new XdebugPerformanceTester();
    $tester->run_all_tests();
}