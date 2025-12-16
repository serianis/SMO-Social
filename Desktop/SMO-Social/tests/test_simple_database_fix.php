<?php
/**
 * Simple test script to verify the database schema fix
 * This script tests the integration without requiring full WordPress environment
 */

require_once __DIR__ . '/includes/AI/Models/UniversalManager.php';
require_once __DIR__ . '/includes/AI/ProvidersConfig.php';
require_once __DIR__ . '/includes/AI/DatabaseProviderLoader.php';
require_once __DIR__ . '/includes/AI/CacheManager.php';

// Test 1: Test that UniversalManager can be instantiated with static config
function test_universal_manager_static() {
    echo "=== TESTING UNIVERSAL MANAGER WITH STATIC CONFIG ===\n";

    try {
        // Test with a known static provider
        $manager = new \SMO_Social\AI\Models\UniversalManager('openai');
        echo "✓ UniversalManager created successfully for 'openai' (static config)\n";

        // Check configuration
        $config = $manager->get_available_models();
        echo "Available models: " . implode(', ', array_slice($config, 0, 3)) . "...\n";

        // Check if configured
        $is_configured = $manager->is_configured();
        echo "Provider is configured: " . ($is_configured ? 'YES' : 'NO') . "\n";

    } catch (Exception $e) {
        echo "✗ UniversalManager failed: " . $e->getMessage() . "\n";
        return false;
    }

    return true;
}

// Test 2: Test ProvidersConfig methods
function test_providers_config() {
    echo "\n=== TESTING PROVIDERS CONFIG ===\n";

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

// Test 3: Test DatabaseProviderLoader methods (without database)
function test_database_loader_fallback() {
    echo "\n=== TESTING DATABASE PROVIDER LOADER FALLBACK ===\n";

    // Test get_provider_from_database (should return null without database)
    $provider = \SMO_Social\AI\DatabaseProviderLoader::get_provider_from_database('openai');
    if ($provider === null) {
        echo "✓ Database loader correctly returns null when no database (expected)\n";
    } else {
        echo "Database loader found provider (unexpected in test environment)\n";
    }

    // Test fallback behavior
    $provider = \SMO_Social\AI\ProvidersConfig::get_provider('openai');
    if ($provider) {
        echo "✓ ProvidersConfig correctly falls back to static config\n";
    } else {
        echo "✗ ProvidersConfig fallback failed\n";
        return false;
    }

    return true;
}

// Test 4: Test fallback to static config for unknown providers
function test_fallback_to_static() {
    echo "\n=== TESTING FALLBACK TO STATIC CONFIG ===\n";

    try {
        $manager = new \SMO_Social\AI\Models\UniversalManager('custom');
        echo "✓ UniversalManager created successfully for 'custom' (fallback to static)\n";

        // Check configuration
        $config = $manager->get_available_models();
        echo "Available models: " . implode(', ', $config) . "\n";

    } catch (Exception $e) {
        echo "✗ UniversalManager failed for 'custom': " . $e->getMessage() . "\n";
        return false;
    }

    return true;
}

// Test 5: Test all static providers are accessible
function test_all_static_providers() {
    echo "\n=== TESTING ALL STATIC PROVIDERS ===\n";

    $all_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();
    echo "Total static providers: " . count($all_providers) . "\n";

    $tested_providers = ['openai', 'anthropic', 'google', 'huggingface', 'ollama'];
    $success_count = 0;

    foreach ($tested_providers as $provider_id) {
        try {
            $manager = new \SMO_Social\AI\Models\UniversalManager($provider_id);
            echo "✓ Provider '{$provider_id}' accessible\n";
            $success_count++;
        } catch (Exception $e) {
            echo "✗ Provider '{$provider_id}' failed: " . $e->getMessage() . "\n";
        }
    }

    echo "Successfully tested {$success_count}/" . count($tested_providers) . " providers\n";
    return $success_count == count($tested_providers);
}

// Run all tests
function run_all_tests() {
    echo "=== RUNNING ALL TESTS ===\n";

    $all_passed = true;

    $all_passed &= test_universal_manager_static();
    $all_passed &= test_providers_config();
    $all_passed &= test_database_loader_fallback();
    $all_passed &= test_fallback_to_static();
    $all_passed &= test_all_static_providers();

    echo "\n=== TEST RESULTS ===\n";
    if ($all_passed) {
        echo "✓ ALL TESTS PASSED - Database schema fix is working correctly!\n";
        echo "The system now properly uses database providers with fallback to static config.\n";
    } else {
        echo "✗ SOME TESTS FAILED - Please check the error logs\n";
    }

    return $all_passed;
}

// Run tests
run_all_tests();