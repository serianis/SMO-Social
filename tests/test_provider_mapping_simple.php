<?php
// Define WordPress constants for testing
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
/**
 * Simple test script to validate AI Provider ID Mapping fix
 * Focuses only on the provider mapping logic without full dependencies
 */

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

// Mock DatabaseSchema
class DatabaseSchema {
    public static function get_table_names() {
        return [
            'sessions' => 'wp_smo_chat_sessions',
            'messages' => 'wp_smo_chat_messages',
            'providers' => 'wp_smo_ai_providers',
            'models' => 'wp_smo_ai_models',
            'templates' => 'wp_smo_chat_templates',
            'audit' => 'wp_smo_chat_audit',
            'moderation' => 'wp_smo_chat_moderation',
            'rate_limits' => 'wp_smo_chat_rate_limits'
        ];
    }
}

// Test the provider mapping logic directly
function test_provider_mapping_logic() {
    global $wpdb;

    // Mock the global $wpdb
    $wpdb = new MockWPDB();

    echo "=== AI Provider ID Mapping Test ===\n";

    // Test the get_provider_name method logic directly
    $db = $wpdb;
    $table_name = DatabaseSchema::get_table_names()['providers'];

    // Test 1: Valid provider ID mapping
    echo "Test 1: Valid provider ID (1 -> huggingface)\n";
    $numeric_provider_id = 1;

    $provider = $db->get_row($db->prepare(
        "SELECT name FROM {$table_name}
        WHERE id = %d AND status = 'active'
    ", $numeric_provider_id), ARRAY_A);

    if ($provider) {
        $provider_name = $provider['name'];
        echo "Result: PASS\n";
        echo "Provider name: " . $provider_name . "\n\n";
    } else {
        echo "Result: FAIL\n";
        echo "Provider not found\n\n";
    }

    // Test 2: Invalid provider ID
    echo "Test 2: Invalid provider ID (999)\n";
    $numeric_provider_id = 999;

    $provider = $db->get_row($db->prepare(
        "SELECT name FROM {$table_name}
        WHERE id = %d AND status = 'active'
    ", $numeric_provider_id), ARRAY_A);

    if ($provider) {
        echo "Result: FAIL (should not find provider)\n";
        echo "Provider name: " . $provider['name'] . "\n\n";
    } else {
        echo "Result: PASS\n";
        echo "Provider not found (as expected)\n\n";
    }

    // Test 3: Inactive provider ID
    echo "Test 3: Inactive provider ID (3 -> localhost, but inactive)\n";
    $numeric_provider_id = 3;

    $provider = $db->get_row($db->prepare(
        "SELECT name FROM {$table_name}
        WHERE id = %d AND status = 'active'
    ", $numeric_provider_id), ARRAY_A);

    if ($provider) {
        echo "Result: FAIL (should not find inactive provider)\n";
        echo "Provider name: " . $provider['name'] . "\n\n";
    } else {
        echo "Result: PASS\n";
        echo "Provider not found (inactive, as expected)\n\n";
    }

    // Test 4: String provider ID (legacy support)
    echo "Test 4: String provider ID (huggingface)\n";
    $provider_id = 'huggingface';

    $provider = $db->get_row($db->prepare(
        "SELECT name FROM {$table_name}
        WHERE name = %s AND status = 'active'
    ", $provider_id), ARRAY_A);

    if ($provider) {
        $provider_name = $provider['name'];
        echo "Result: PASS\n";
        echo "Provider name: " . $provider_name . "\n\n";
    } else {
        echo "Result: FAIL\n";
        echo "Provider not found\n\n";
    }

    // Test 5: Debug logging simulation
    echo "Test 5: Debug logging simulation\n";
    $numeric_provider_id = 999; // Non-existent provider

    $provider = $db->get_row($db->prepare(
        "SELECT name FROM {$table_name}
        WHERE id = %d AND status = 'active'
    ", $numeric_provider_id), ARRAY_A);

    if (!$provider) {
        echo "SMO Social Chat: Provider not found for ID: " . $numeric_provider_id . "\n";
        $all_providers = $db->get_results("SELECT id, name FROM {$table_name}", ARRAY_A);
        echo "SMO Social Chat: Available providers in database: \n";
        foreach ($all_providers as $p) {
            echo "  ID: " . $p['id'] . ", Name: " . $p['name'] . ", Status: " . ($p['status'] ?? 'unknown') . "\n";
        }
        echo "\n";
    }

    echo "=== Test Complete ===\n";
}

// Run the test
test_provider_mapping_logic();