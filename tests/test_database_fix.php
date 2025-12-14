<?php
/**
 * Test script to verify the database schema fix
 * This script tests the integration between database schema and UniversalManager
 */

require_once __DIR__ . '/includes/Core/Activator.php';
require_once __DIR__ . '/includes/AI/Models/UniversalManager.php';
require_once __DIR__ . '/includes/AI/ProvidersConfig.php';
require_once __DIR__ . '/includes/AI/DatabaseProviderLoader.php';
require_once __DIR__ . '/includes/AI/DatabaseProviderMigrator.php';
require_once __DIR__ . '/includes/Chat/DatabaseSchema.php';

// Initialize database and migrate providers
function initialize_database() {
    echo "=== INITIALIZING DATABASE ===\n";

    // Ensure tables exist
    \SMO_Social\AI\DatabaseProviderMigrator::ensure_database_ready();

    // Check if migration was successful
    global $wpdb;
    $providers_table = $wpdb->prefix . 'smo_ai_providers';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");
    echo "Database providers count: {$count}\n";

    if ($count > 0) {
        echo "✓ Database migration successful\n";
    } else {
        echo "✗ Database migration failed\n";
    }
}

// Test 1: Test UniversalManager with database provider
function test_universal_manager_database() {
    echo "\n=== TESTING UNIVERSAL MANAGER WITH DATABASE ===\n";

    try {
        // Test with a known provider that should be in database
        $manager = new \SMO_Social\AI\Models\UniversalManager('openai');
        echo "✓ UniversalManager created successfully for 'openai'\n";

        // Check configuration
        $config = $manager->get_available_models();
        echo "Available models: " . implode(', ', $config) . "\n";

        // Check if configured
        $is_configured = $manager->is_configured();
        echo "Provider is configured: " . ($is_configured ? 'YES' : 'NO') . "\n";

    } catch (Exception $e) {
        echo "✗ UniversalManager failed: " . $e->getMessage() . "\n";
        return false;
    }

    return true;
}

// Test 2: Test ProvidersConfig with database
function test_providers_config_database() {
    echo "\n=== TESTING PROVIDERS CONFIG WITH DATABASE ===\n";

    // Test get_provider
    $provider = \SMO_Social\AI\ProvidersConfig::get_provider('openai');
    if ($provider) {
        echo "✓ get_provider('openai') returned data\n";
        echo "Provider name: " . $provider['name'] . "\n";
        echo "Provider type: " . $provider['type'] . "\n";
    } else {
        echo "✗ get_provider('openai') failed\n";
        return false;
    }

    // Test is_provider_configured
    $is_configured = \SMO_Social\AI\ProvidersConfig::is_provider_configured('openai');
    echo "is_provider_configured('openai'): " . ($is_configured ? 'YES' : 'NO') . "\n";

    // Test get_configured_providers
    $configured = \SMO_Social\AI\ProvidersConfig::get_configured_providers();
    echo "Configured providers count: " . count($configured) . "\n";

    return true;
}

// Test 3: Test DatabaseProviderLoader directly
function test_database_loader() {
    echo "\n=== TESTING DATABASE PROVIDER LOADER ===\n";

    // Test get_provider_from_database
    $provider = \SMO_Social\AI\DatabaseProviderLoader::get_provider_from_database('openai');
    if ($provider) {
        echo "✓ Database loader found 'openai'\n";
        echo "Provider ID: " . $provider['id'] . "\n";
        echo "Provider name: " . $provider['name'] . "\n";
    } else {
        echo "✗ Database loader failed to find 'openai'\n";
        return false;
    }

    // Test get_all_providers_from_database
    $all_providers = \SMO_Social\AI\DatabaseProviderLoader::get_all_providers_from_database();
    echo "All database providers count: " . count($all_providers) . "\n";

    // Test is_provider_configured
    $is_configured = \SMO_Social\AI\DatabaseProviderLoader::is_provider_configured('openai');
    echo "Database loader is_provider_configured('openai'): " . ($is_configured ? 'YES' : 'NO') . "\n";

    return true;
}

// Test 4: Test fallback to static config
function test_fallback_to_static() {
    echo "\n=== TESTING FALLBACK TO STATIC CONFIG ===\n";

    // Test with a provider that doesn't exist in database
    try {
        $manager = new \SMO_Social\AI\Models\UniversalManager('custom_api');
        echo "✓ UniversalManager created successfully for 'custom_api' (fallback to static)\n";

        // Check configuration
        $config = $manager->get_available_models();
        echo "Available models: " . implode(', ', $config) . "\n";

    } catch (Exception $e) {
        echo "✗ UniversalManager failed for 'custom_api': " . $e->getMessage() . "\n";
        return false;
    }

    return true;
}

// Run all tests
function run_all_tests() {
    echo "=== RUNNING ALL TESTS ===\n";

    $all_passed = true;

    $all_passed &= initialize_database();
    $all_passed &= test_universal_manager_database();
    $all_passed &= test_providers_config_database();
    $all_passed &= test_database_loader();
    $all_passed &= test_fallback_to_static();

    echo "\n=== TEST RESULTS ===\n";
    if ($all_passed) {
        echo "✓ ALL TESTS PASSED - Database schema fix is working correctly!\n";
    } else {
        echo "✗ SOME TESTS FAILED - Please check the error logs\n";
    }

    return $all_passed;
}

// Run tests
run_all_tests();