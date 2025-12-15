<?php
/**
 * Test script to diagnose UniversalManager initialization issues
 */

require_once __DIR__ . '/includes/AI/Manager.php';
require_once __DIR__ . '/includes/AI/Models/UniversalManager.php';
require_once __DIR__ . '/includes/AI/ProvidersConfig.php';
require_once __DIR__ . '/includes/AI/CacheManager.php';
require_once __DIR__ . '/includes/AI/Content/CaptionGenerator.php';
require_once __DIR__ . '/includes/AI/Content/HashtagOptimizer.php';
require_once __DIR__ . '/includes/AI/Content/AltTextGenerator.php';
require_once __DIR__ . '/includes/AI/Analysis/SentimentAnalyzer.php';
require_once __DIR__ . '/includes/AI/Optimization/TimePredictor.php';
require_once __DIR__ . '/includes/AI/Processing/ContentRepurposer.php';

// Mock WordPress functions for testing
if (!function_exists('get_option')) {
    function get_option($option, $default = null) {
        // Simulate WordPress options
        $mock_options = [
            'smo_social_ai_settings' => [
                'primary_provider' => 'huggingface',
                'fallback_enabled' => true,
                'cache_enabled' => true,
                'api_keys' => [
                    'huggingface_api_key' => '', // Empty key to test configuration failure
                    'localhost_api_url' => 'http://localhost:11434/api/generate',
                    'custom_api_url' => '',
                    'custom_api_key' => ''
                ]
            ],
            'smo_social_huggingface_api_key' => '', // Empty key
            'smo_social_openai_api_key' => '',
            'smo_social_ollama_url' => 'http://localhost:11434'
        ];

        return $mock_options[$option] ?? $default;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook) {
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook) {
        return true;
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url() {
        return 'http://localhost';
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($info) {
        return 'Test Site';
    }
}

echo "=== UniversalManager Initialization Test ===\n\n";

// Test 1: Check available providers
echo "1. Available Providers:\n";
$providers = SMO_Social\AI\ProvidersConfig::get_all_providers();
echo "Total providers: " . count($providers) . "\n";

// Test 2: Check configured providers
echo "\n2. Configured Providers:\n";
$configured = SMO_Social\AI\ProvidersConfig::get_configured_providers();
echo "Configured providers: " . count($configured) . "\n";
foreach ($configured as $id => $config) {
    echo " - $id: " . ($config['requires_key'] ? 'requires key' : 'no key required') . "\n";
}

// Test 3: Test specific provider configuration
echo "\n3. Testing HuggingFace Configuration:\n";
$hf_configured = SMO_Social\AI\ProvidersConfig::is_provider_configured('huggingface');
echo "HuggingFace configured: " . ($hf_configured ? 'YES' : 'NO') . "\n";

$hf_config = SMO_Social\AI\ProvidersConfig::get_provider('huggingface');
echo "HuggingFace requires key: " . ($hf_config['requires_key'] ? 'YES' : 'NO') . "\n";
echo "HuggingFace API key from options: '" . get_option('smo_social_huggingface_api_key') . "'\n";

// Test 4: Test Ollama (local provider that doesn't require key)
echo "\n4. Testing Ollama Configuration:\n";
$ollama_configured = SMO_Social\AI\ProvidersConfig::is_provider_configured('ollama');
echo "Ollama configured: " . ($ollama_configured ? 'YES' : 'NO') . "\n";

$ollama_config = SMO_Social\AI\ProvidersConfig::get_provider('ollama');
echo "Ollama requires key: " . ($ollama_config['requires_key'] ? 'YES' : 'NO') . "\n";
echo "Ollama URL from options: '" . get_option('smo_social_ollama_url') . "'\n";

// Test 5: Initialize AI Manager
echo "\n5. Initializing AI Manager:\n";
try {
    $ai_manager = SMO_Social\AI\Manager::getInstance();
    echo "AI Manager initialized successfully\n";

    // Check if components are using real AI or fallbacks
    $primary_provider = $ai_manager->get_primary_provider_id();
    echo "Primary provider: " . ($primary_provider ?? 'NONE') . "\n";

    $stats = $ai_manager->get_processing_stats();
    echo "Processing stats: " . print_r($stats, true) . "\n";

} catch (Exception $e) {
    echo "AI Manager initialization failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";