<?php
/**
 * Test script to validate database schema vs UniversalManager expectations
 * This script will help confirm the diagnosis of the provider configuration issues
 */

require_once __DIR__ . '/includes/Core/Activator.php';
require_once __DIR__ . '/includes/AI/Models/UniversalManager.php';
require_once __DIR__ . '/includes/AI/ProvidersConfig.php';

// Test 1: Check if database tables exist and have expected structure
function test_database_schema() {
    global $wpdb;

    echo "=== DATABASE SCHEMA TEST ===\n";

    $providers_table = $wpdb->prefix . 'smo_ai_providers';
    $models_table = $wpdb->prefix . 'smo_ai_models';

    // Check if tables exist
    $providers_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $providers_table
    )) === $providers_table;

    $models_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $models_table
    )) === $models_table;

    echo "Providers table exists: " . ($providers_exists ? 'YES' : 'NO') . "\n";
    echo "Models table exists: " . ($models_exists ? 'YES' : 'NO') . "\n";

    if ($providers_exists) {
        // Get table structure
        $structure = $wpdb->get_results("DESCRIBE $providers_table", ARRAY_A);
        echo "Providers table columns:\n";
        foreach ($structure as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }

    if ($models_exists) {
        // Get table structure
        $structure = $wpdb->get_results("DESCRIBE $models_table", ARRAY_A);
        echo "Models table columns:\n";
        foreach ($structure as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
}

// Test 2: Check static configuration vs database configuration
function test_configuration_sources() {
    echo "\n=== CONFIGURATION SOURCES TEST ===\n";

    // Get all providers from static config
    $static_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();
    echo "Static providers count: " . count($static_providers) . "\n";

    // Get configured providers from static config
    $configured_static = \SMO_Social\AI\ProvidersConfig::get_configured_providers();
    echo "Configured static providers count: " . count($configured_static) . "\n";

    // Check database providers
    global $wpdb;
    $providers_table = $wpdb->prefix . 'smo_ai_providers';

    $db_providers = $wpdb->get_results("SELECT * FROM $providers_table", ARRAY_A);
    echo "Database providers count: " . count($db_providers) . "\n";

    // Compare field structures
    if (!empty($db_providers)) {
        $first_db_provider = $db_providers[0];
        echo "First DB provider fields: " . implode(', ', array_keys($first_db_provider)) . "\n";

        $first_static_provider = reset($static_providers);
        echo "First static provider fields: " . implode(', ', array_keys($first_static_provider)) . "\n";

        // Check for key differences
        $db_fields = array_keys($first_db_provider);
        $static_fields = array_keys($first_static_provider);

        $missing_in_db = array_diff($static_fields, $db_fields);
        $missing_in_static = array_diff($db_fields, $static_fields);

        echo "Fields in static but not in DB: " . implode(', ', $missing_in_db) . "\n";
        echo "Fields in DB but not in static: " . implode(', ', $missing_in_static) . "\n";
    }
}

// Test 3: Try to instantiate UniversalManager and see what happens
function test_universal_manager() {
    echo "\n=== UNIVERSAL MANAGER TEST ===\n";

    try {
        // Try with a known provider
        $manager = new \SMO_Social\AI\Models\UniversalManager('openai');
        echo "UniversalManager created successfully for 'openai'\n";

        // Check if it's configured
        $is_configured = $manager->is_configured();
        echo "Provider is configured: " . ($is_configured ? 'YES' : 'NO') . "\n";

    } catch (Exception $e) {
        echo "UniversalManager failed: " . $e->getMessage() . "\n";
    }

    // Try with a database-only provider
    global $wpdb;
    $providers_table = $wpdb->prefix . 'smo_ai_providers';

    $db_provider = $wpdb->get_row("SELECT name FROM $providers_table LIMIT 1", ARRAY_A);
    if ($db_provider) {
        try {
            $manager = new \SMO_Social\AI\Models\UniversalManager($db_provider['name']);
            echo "UniversalManager created successfully for DB provider: " . $db_provider['name'] . "\n";
        } catch (Exception $e) {
            echo "UniversalManager failed for DB provider: " . $e->getMessage() . "\n";
        }
    }
}

// Run all tests
test_database_schema();
test_configuration_sources();
test_universal_manager();

echo "\n=== TEST COMPLETE ===\n";