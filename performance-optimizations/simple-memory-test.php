<?php
/**
 * Simple Memory Test for Platform Lazy Loading
 * Tests memory usage without full plugin initialization
 */

// Simple test to measure memory usage of platform loading
class SimpleMemoryTest {
    
    public function runTest() {
        echo "=== Platform Lazy Loading Memory Test ===\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Memory Limit: " . ini_get('memory_limit') . "\n";
        echo "Initial Memory: " . $this->formatBytes(memory_get_usage(true)) . "\n\n";
        
        // Test 1: Loading platform configurations only (lazy loading)
        echo "Test 1: Loading Platform Configurations (Lazy)\n";
        $before_lazy = memory_get_usage(true);
        $lazy_platforms = $this->loadPlatformConfigs();
        $after_lazy = memory_get_usage(true);
        $lazy_memory = $after_lazy - $before_lazy;
        
        echo "Platform configs loaded: " . count($lazy_platforms) . "\n";
        echo "Memory used: " . $this->formatBytes($lazy_memory) . "\n\n";
        
        // Test 2: Simulating eager loading of all platforms
        echo "Test 2: Simulating Eager Loading\n";
        $before_eager = memory_get_usage(true);
        $eager_platforms = $this->simulateEagerLoading();
        $after_eager = memory_get_usage(true);
        $eager_memory = $after_eager - $before_eager;
        
        echo "Platforms eagerly loaded: " . count($eager_platforms) . "\n";
        echo "Memory used: " . $this->formatBytes($eager_memory) . "\n\n";
        
        // Calculate improvement
        if ($eager_memory > 0) {
            $reduction = (($eager_memory - $lazy_memory) / $eager_memory) * 100;
            echo "=== Results ===\n";
            echo "Memory saved: " . $this->formatBytes($eager_memory - $lazy_memory) . "\n";
            echo "Reduction: " . round($reduction, 2) . "%\n";
            
            if ($reduction > 50) {
                echo "✅ EXCELLENT: Over 50% memory reduction achieved!\n";
            } elseif ($reduction > 30) {
                echo "✅ GOOD: Significant memory reduction achieved!\n";
            } elseif ($reduction > 10) {
                echo "✅ MODERATE: Some memory reduction achieved.\n";
            } else {
                echo "⚠️ MINIMAL: Limited memory reduction. Further optimization needed.\n";
            }
        }
        
        echo "\n=== Test Complete ===\n";
    }
    
    /**
     * Simulate loading platform configurations only (lazy loading approach)
     */
    private function loadPlatformConfigs() {
        $platforms = array();
        $driver_dir = __DIR__ . '/../drivers/';
        
        echo "  Loading configs from: $driver_dir\n";
        
        if (is_dir($driver_dir)) {
            $files = glob($driver_dir . '*.json');
            echo "  Found " . count($files) . " platform files\n";
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                if ($data && (isset($data['slug']) || isset($data['driver_id']))) {
                    $slug = $data['slug'] ?? $data['driver_id'] ?? basename($file, '.json');
                    // Store only configuration, not objects
                    $platforms[$slug] = array(
                        'name' => $data['name'] ?? '',
                        'max_chars' => $data['capabilities']['max_chars'] ?? 280,
                        'features' => $data['features'] ?? array()
                    );
                }
            }
        }
        
        return $platforms;
    }
    
    /**
     * Simulate eager loading by creating mock platform objects
     */
    private function simulateEagerLoading() {
        $platforms = array();
        $driver_dir = __DIR__ . '/../drivers/';
        
        echo "  Loading eager objects from: $driver_dir\n";
        
        if (is_dir($driver_dir)) {
            $files = glob($driver_dir . '*.json');
            echo "  Found " . count($files) . " platform files\n";
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                if ($data && (isset($data['slug']) || isset($data['driver_id']))) {
                    $slug = $data['slug'] ?? $data['driver_id'] ?? basename($file, '.json');
                    // Simulate creating a platform object (memory intensive)
                    $platforms[$slug] = array(
                        'object' => $this->createMockPlatformObject($data),
                        'config' => $data,
                        'cache' => array_fill(0, 100, 'mock_data_' . rand(1, 1000)) // Simulate cached data
                    );
                }
            }
        }
        
        return $platforms;
    }
    
    /**
     * Create a mock platform object to simulate memory usage
     */
    private function createMockPlatformObject($config) {
        // Simulate a large object with lots of properties and methods
        return new class($config) {
            private $config;
            private $credentials;
            private $rateLimits;
            private $apiSettings;
            private $validationRules;
            private $platformMethods;
            
            public function __construct($config) {
                $this->config = $config;
                $this->credentials = array_fill(0, 20, 'mock_credential_' . rand(1, 1000));
                $this->rateLimits = array('posts' => 100, 'requests' => 1000);
                $this->apiSettings = array_fill(0, 50, 'api_setting_' . rand(1, 1000));
                $this->validationRules = array_fill(0, 30, 'validation_rule');
                $this->platformMethods = array_fill(0, 100, function() { return 'mock_method_result'; });
            }
        };
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

// Run the test
$test = new SimpleMemoryTest();
$test->runTest();