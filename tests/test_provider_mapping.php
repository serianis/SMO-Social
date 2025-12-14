<?php
/**
 * Test script to validate AI Provider ID Mapping fix
 */

// Include necessary files
require_once 'includes/Chat/ChatMessage.php';
require_once 'includes/AI/Manager.php';
require_once 'includes/Chat/DatabaseSchema.php';

// Mock WordPress functions for testing
if (!function_exists('get_option')) {
    function get_option($key, $default = null) {
        $options = [
            'smo_social_ai_settings' => [
                'primary_provider' => 'huggingface',
                'fallback_enabled' => true,
                'cache_enabled' => true,
                'api_keys' => [
                    'huggingface_api_key' => 'test_key_123',
                    'openai_api_key' => 'test_key_456'
                ]
            ]
        ];
        return $options[$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        // Mock function
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

// Mock database class
class MockWPDB {
    private $providers = [];
    private $sessions = [];
    private $messages = [];

    public function __construct() {
        // Initialize with test data
        $this->providers = [
            ['id' => 1, 'name' => 'huggingface', 'status' => 'active'],
            ['id' => 2, 'name' => 'openai', 'status' => 'active'],
            ['id' => 3, 'name' => 'localhost', 'status' => 'inactive']
        ];

        $this->sessions = [
            ['id' => 1, 'user_id' => 1, 'provider_id' => 1, 'model_name' => 'test-model', 'status' => 'active']
        ];
    }

    public function get_row($query, $output = ARRAY_A) {
        // Mock provider lookup
        if (strpos($query, 'SELECT name FROM') !== false && strpos($query, 'providers') !== false) {
            preg_match('/WHERE id = (\d+)/', $query, $matches);
            if (!empty($matches[1])) {
                $id = (int)$matches[1];
                foreach ($this->providers as $provider) {
                    if ($provider['id'] == $id && $provider['status'] == 'active') {
                        return $provider;
                    }
                }
            }
        }

        // Mock session lookup
        if (strpos($query, 'SELECT * FROM') !== false && strpos($query, 'sessions') !== false) {
            return $this->sessions[0] ?? null;
        }

        return null;
    }

    public function get_results($query, $output = ARRAY_A) {
        // Mock get all providers
        if (strpos($query, 'SELECT id, name FROM') !== false && strpos($query, 'providers') !== false) {
            return $this->providers;
        }

        return [];
    }

    public function prepare($query, ...$args) {
        return vsprintf($query, $args);
    }

    public function insert($table, $data, $format = null) {
        return 1; // Mock insert ID
    }

    public function query($query) {
        return true;
    }

    public function get_var($query) {
        return 1;
    }

    public function esc_like($text) {
        return addcslashes($text, '%_');
    }

    public function last_error() {
        return '';
    }

    public function prefix() {
        return 'wp_';
    }
}

// Test the fix
function test_provider_mapping() {
    global $wpdb;

    // Mock the global $wpdb
    $wpdb = new MockWPDB();

    echo "=== AI Provider ID Mapping Test ===\n";

    // Test 1: Valid provider ID mapping
    echo "Test 1: Valid provider ID (1 -> huggingface)\n";
    $chat_message = new \SMO_Social\Chat\ChatMessage();

    // Use reflection to test private method
    $reflection = new ReflectionClass($chat_message);
    $method = $reflection->getMethod('get_provider_name');
    $method->setAccessible(true);

    $provider_name = $method->invoke($chat_message, 1);
    echo "Result: " . ($provider_name === 'huggingface' ? 'PASS' : 'FAIL') . "\n";
    echo "Provider name: " . ($provider_name ?? 'null') . "\n\n";

    // Test 2: Invalid provider ID
    echo "Test 2: Invalid provider ID (999)\n";
    $provider_name = $method->invoke($chat_message, 999);
    echo "Result: " . ($provider_name === null ? 'PASS' : 'FAIL') . "\n";
    echo "Provider name: " . ($provider_name ?? 'null') . "\n\n";

    // Test 3: Inactive provider ID
    echo "Test 3: Inactive provider ID (3 -> localhost, but inactive)\n";
    $provider_name = $method->invoke($chat_message, 3);
    echo "Result: " . ($provider_name === null ? 'PASS' : 'FAIL') . "\n";
    echo "Provider name: " . ($provider_name ?? 'null') . "\n\n";

    // Test 4: String provider ID (legacy support)
    echo "Test 4: String provider ID (huggingface)\n";
    $provider_name = $method->invoke($chat_message, 'huggingface');
    echo "Result: " . ($provider_name === 'huggingface' ? 'PASS' : 'FAIL') . "\n";
    echo "Provider name: " . ($provider_name ?? 'null') . "\n\n";

    echo "=== Test Complete ===\n";
}

// Run the test
test_provider_mapping();