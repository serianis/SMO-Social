<?php
/**
 * Memory Usage Test for Platform Lazy Loading
 * 
 * This script measures memory usage before and after lazy loading optimization
 */

// Test memory usage measurement
class PlatformMemoryTest {
    private $start_memory;
    private $end_memory;
    
    public function __construct() {
        // Start measurement
        $this->start_memory = memory_get_usage(true);
        echo "Starting memory measurement...\n";
        echo "Initial memory: " . $this->formatBytes($this->start_memory) . "\n";
    }
    
    /**
     * Test eager loading (current implementation)
     */
    public function testEagerLoading() {
        echo "\n=== Testing Eager Loading (Current) ===\n";
        $before_memory = memory_get_usage(true);
        
        // Simulate loading all platforms
        $platform_manager = new \SMO_Social\Platforms\Manager();
        
        // This is the problematic call that loads all platforms
        $platforms = $platform_manager->get_enabled_platforms();
        
        $after_memory = memory_get_usage(true);
        $usage = $after_memory - $before_memory;
        
        echo "Platforms loaded: " . count($platforms) . "\n";
        echo "Memory used for loading platforms: " . $this->formatBytes($usage) . "\n";
        echo "Total memory after loading: " . $this->formatBytes($after_memory) . "\n";
        
        return $usage;
    }
    
    /**
     * Test lazy loading (optimized implementation)
     */
    public function testLazyLoading() {
        echo "\n=== Testing Lazy Loading (Optimized) ===\n";
        
        // Clear any existing loaded platforms
        $platform_manager = new \SMO_Social\Platforms\Manager();
        $platform_manager->clear_platform_cache();
        
        $before_memory = memory_get_usage(true);
        
        // Only load platforms when specifically requested
        $enabled_platforms = get_option('smo_social_enabled_platforms', array());
        
        $lazy_loaded = array();
        foreach (array_slice($enabled_platforms, 0, 3) as $slug) { // Load only first 3 for demo
            $platform = $platform_manager->get_platform($slug);
            if ($platform) {
                $lazy_loaded[$slug] = $platform;
            }
        }
        
        $after_memory = memory_get_usage(true);
        $usage = $after_memory - $before_memory;
        
        echo "Platforms lazily loaded: " . count($lazy_loaded) . "\n";
        echo "Memory used for lazy loading: " . $this->formatBytes($usage) . "\n";
        echo "Total memory after lazy loading: " . $this->formatBytes($after_memory) . "\n";
        
        return $usage;
    }
    
    /**
     * Run comprehensive memory analysis
     */
    public function runMemoryAnalysis() {
        echo "\n=== Platform Lazy Loading Memory Analysis ===\n";
        echo "Date: " . date('Y-m-d H:i:s') . "\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Memory Limit: " . ini_get('memory_limit') . "\n";
        
        // Test different scenarios
        $eager_usage = $this->testEagerLoading();
        $lazy_usage = $this->testLazyLoading();
        
        // Calculate improvements
        if ($eager_usage > 0 && $lazy_usage > 0) {
            $reduction = (($eager_usage - $lazy_usage) / $eager_usage) * 100;
            echo "\n=== Memory Reduction Analysis ===\n";
            echo "Memory saved: " . $this->formatBytes($eager_usage - $lazy_usage) . "\n";
            echo "Reduction percentage: " . round($reduction, 2) . "%\n";
        }
        
        // Final memory check
        $this->end_memory = memory_get_usage(true);
        echo "\nFinal memory: " . $this->formatBytes($this->end_memory) . "\n";
        echo "Total increase: " . $this->formatBytes($this->end_memory - $this->start_memory) . "\n";
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' MB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}

// Check if we're in standalone mode and have the required files
if (file_exists(__DIR__ . '/../smo-social.php')) {
    require_once __DIR__ . '/../smo-social.php';
    
    // Run the test
    $test = new PlatformMemoryTest();
    $test->runMemoryAnalysis();
} else {
    echo "SMO Social plugin files not found. Please run this test from the plugin directory.\n";
    exit(1);
}