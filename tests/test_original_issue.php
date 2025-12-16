<?php
/**
 * Test script to reproduce the original issue described in the task
 * "AI Manager fails to initialize AI components, falls back to stubs"
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

// Mock WordPress functions to simulate the exact scenario from the task
// Global options storage for mock
$GLOBAL_MOCK_OPTIONS = [
    'smo_social_ai_settings' => [
        'primary_provider' => 'huggingface',  // This is set as primary
        'fallback_enabled' => true,
        'cache_enabled' => true,
        'api_keys' => [
            'huggingface_api_key' => '',  // But no API key is configured
            'localhost_api_url' => '',
            'custom_api_url' => '',
            'custom_api_key' => ''
        ]
    ],
    // No API keys configured for any provider
    'smo_social_huggingface_api_key' => '',
    'smo_social_openai_api_key' => '',
    'smo_social_ollama_url' => ''  // Also no Ollama URL
];

if (!function_exists('get_option')) {
    function get_option($option, $default = null) {
        global $GLOBAL_MOCK_OPTIONS;
        return $GLOBAL_MOCK_OPTIONS[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        global $GLOBAL_MOCK_OPTIONS;
        $GLOBAL_MOCK_OPTIONS[$option] = $value;
        error_log("MOCK: Updated option $option to " . print_r($value, true));
        return true;
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

// Mock WordPress HTTP functions
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        // Mock a successful response for testing
        return [
            'body' => json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'This is a mock AI response for testing purposes.'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30
                ]
            ]),
            'response' => ['code' => 200],
            'headers' => [],
            'cookies' => []
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($response) {
        return false;
    }
}

echo "=== Reproducing Original Issue ===\n\n";

// Test the exact scenario: huggingface is primary but not configured, no other providers configured
echo "1. Provider Configuration Status:\n";
$all_providers = SMO_Social\AI\ProvidersConfig::get_all_providers();
$configured_providers = SMO_Social\AI\ProvidersConfig::get_configured_providers();

echo "Total providers available: " . count($all_providers) . "\n";
echo "Configured providers: " . count($configured_providers) . "\n";

if (count($configured_providers) === 0) {
    echo "❌ NO providers are configured!\n";
} else {
    echo "Configured providers: " . implode(', ', array_keys($configured_providers)) . "\n";
}

echo "\n2. Testing Primary Provider Selection:\n";
$primary_provider_id = 'huggingface'; // This is what's set in settings
$is_primary_configured = SMO_Social\AI\ProvidersConfig::is_provider_configured($primary_provider_id);
echo "Primary provider (huggingface) configured: " . ($is_primary_configured ? 'YES' : 'NO') . "\n";

echo "\n3. Initializing AI Manager:\n";
try {
    $ai_manager = SMO_Social\AI\Manager::getInstance();
    echo "✅ AI Manager initialized\n";

    // Check what actually got initialized
    $actual_primary = $ai_manager->get_primary_provider_id();
    echo "Actual primary provider used: " . ($actual_primary ?? 'NONE') . "\n";

    // Test if components are using real AI or fallbacks
    echo "\n4. Testing AI Component Functionality:\n";

    // Test caption generation
    $caption_result = $ai_manager->generate_captions("Test content for social media", ['twitter']);
    echo "Caption generation result: " . print_r($caption_result, true) . "\n";

    // Test hashtag optimization
    $hashtag_result = $ai_manager->optimize_hashtags("Test content for hashtags", ['twitter']);
    echo "Hashtag optimization result: " . print_r($hashtag_result, true) . "\n";

    // Test sentiment analysis
    $sentiment_result = $ai_manager->analyze_sentiment("This is great content!");
    echo "Sentiment analysis result: " . print_r($sentiment_result, true) . "\n";

} catch (Exception $e) {
    echo "❌ AI Manager initialization failed: " . $e->getMessage() . "\n";
}

echo "\n=== Issue Reproduction Complete ===\n";