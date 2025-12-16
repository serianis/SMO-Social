<?php
/**
 * SMO Social AJAX Security and Database Test Script
 * 
 * This script tests the AJAX security mechanisms and database permissions
 * that are critical for the deletion functionality to work properly.
 * 
 * @package SMO_Social
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Try to load WordPress environment
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php',
        '../../../../../../wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress environment not found. Please run this script from within WordPress admin.');
    }
}

header('Content-Type: application/json');

$test_results = [
    'timestamp' => current_time('mysql'),
    'ajax_security_tests' => [],
    'database_permission_tests' => [],
    'wordPress_compatibility_tests' => [],
    'summary' => [],
    'recommendations' => []
];

// Test 1: AJAX Security Tests
echo "Testing AJAX Security Mechanisms...\n";

// 1.1: Test nonce generation and validation
$nonce = wp_create_nonce('smo_social_nonce');
$nonce_valid = wp_verify_nonce($nonce, 'smo_social_nonce');
$test_results['ajax_security_tests']['nonce_generation'] = [
    'nonce_created' => $nonce !== false,
    'nonce_value' => substr($nonce, 0, 10) . '...',
    'nonce_validation' => $nonce_valid,
    'status' => $nonce_valid ? 'PASS' : 'FAIL'
];

// 1.2: Test current user capabilities
$current_user = wp_get_current_user();
$test_results['ajax_security_tests']['user_capabilities'] = [
    'user_id' => $current_user->ID,
    'user_login' => $current_user->user_login,
    'has_manage_options' => current_user_can('manage_options'),
    'has_delete_posts' => current_user_can('delete_posts'),
    'role' => implode(', ', $current_user->roles),
    'status' => current_user_can('manage_options') ? 'PASS' : 'FAIL'
];

// 1.3: Test AJAX referer check
$_REQUEST['nonce'] = $nonce;
$_REQUEST['_wpnonce'] = $nonce;
$_REQUEST['action'] = 'test_action';
$referer_valid = check_ajax_referer('smo_social_nonce', 'nonce', false);
$test_results['ajax_security_tests']['ajax_referer_check'] = [
    'referer_valid' => $referer_valid,
    'status' => $referer_valid ? 'PASS' : 'FAIL'
];

// Test 2: Database Permission Tests
echo "Testing Database Permissions...\n";

global $wpdb;
$test_results['database_permission_tests']['connection'] = [
    'connected' => $wpdb->last_error === '' || $wpdb->last_error === null,
    'last_error' => $wpdb->last_error,
    'status' => ($wpdb->last_error === '' || $wpdb->last_error === null) ? 'PASS' : 'FAIL'
];

// Test 2.1: Content Categories Table
$categories_table = $wpdb->prefix . 'smo_content_categories';
$categories_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $categories_table)) === $categories_table;

$test_results['database_permission_tests']['categories_table'] = [
    'table_exists' => $categories_exists,
    'table_name' => $categories_table,
    'status' => $categories_exists ? 'PASS' : 'FAIL'
];

if ($categories_exists) {
    // Test read permission
    $read_test = $wpdb->get_results("SELECT COUNT(*) as count FROM $categories_table LIMIT 1");
    $test_results['database_permission_tests']['categories_table']['read_permission'] = [
        'can_read' => $read_test !== false,
        'status' => $read_test !== false ? 'PASS' : 'FAIL'
    ];
    
    // Test write permission (insert and delete)
    $insert_result = $wpdb->insert($categories_table, [
        'user_id' => $current_user->ID,
        'name' => 'Test Category',
        'description' => 'Test description',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);
    
    $test_id = $wpdb->insert_id;
    
    if ($insert_result && $test_id) {
        // Test delete permission
        $delete_result = $wpdb->delete($categories_table, ['id' => $test_id]);
        $test_results['database_permission_tests']['categories_table']['write_permission'] = [
            'can_write' => $insert_result !== false,
            'can_delete' => $delete_result !== false,
            'test_id' => $test_id,
            'status' => ($insert_result !== false && $delete_result !== false) ? 'PASS' : 'FAIL'
        ];
    } else {
        $test_results['database_permission_tests']['categories_table']['write_permission'] = [
            'can_write' => false,
            'can_delete' => false,
            'insert_error' => $wpdb->last_error,
            'status' => 'FAIL'
        ];
    }
}

// Test 2.2: Content Ideas Table
$ideas_table = $wpdb->prefix . 'smo_content_ideas';
$ideas_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $ideas_table)) === $ideas_table;

$test_results['database_permission_tests']['ideas_table'] = [
    'table_exists' => $ideas_exists,
    'table_name' => $ideas_table,
    'status' => $ideas_exists ? 'PASS' : 'FAIL'
];

if ($ideas_exists) {
    // Test read permission
    $read_test = $wpdb->get_results("SELECT COUNT(*) as count FROM $ideas_table LIMIT 1");
    $test_results['database_permission_tests']['ideas_table']['read_permission'] = [
        'can_read' => $read_test !== false,
        'status' => $read_test !== false ? 'PASS' : 'FAIL'
    ];
    
    // Test write permission
    $insert_result = $wpdb->insert($ideas_table, [
        'user_id' => $current_user->ID,
        'title' => 'Test Idea',
        'content' => 'Test content',
        'status' => 'idea',
        'created_at' => current_time('mysql')
    ]);
    
    $test_id = $wpdb->insert_id;
    
    if ($insert_result && $test_id) {
        // Test delete permission
        $delete_result = $wpdb->delete($ideas_table, ['id' => $test_id]);
        $test_results['database_permission_tests']['ideas_table']['write_permission'] = [
            'can_write' => $insert_result !== false,
            'can_delete' => $delete_result !== false,
            'test_id' => $test_id,
            'status' => ($insert_result !== false && $delete_result !== false) ? 'PASS' : 'FAIL'
        ];
    } else {
        $test_results['database_permission_tests']['ideas_table']['write_permission'] = [
            'can_write' => false,
            'can_delete' => false,
            'insert_error' => $wpdb->last_error,
            'status' => 'FAIL'
        ];
    }
}

// Test 3: WordPress Compatibility Tests
echo "Testing WordPress Compatibility...\n";

$test_results['wordPress_compatibility_tests']['version'] = [
    'wp_version' => get_bloginfo('version'),
    'php_version' => PHP_VERSION,
    'mysql_version' => $wpdb->db_version(),
    'status' => 'PASS' // Assume pass unless specific issues found
];

// Test 3.1: Required functions availability
$required_functions = [
    'wp_create_nonce',
    'wp_verify_nonce',
    'check_ajax_referer',
    'wp_send_json_error',
    'wp_send_json_success',
    'current_user_can',
    'get_current_user_id'
];

$function_results = [];
foreach ($required_functions as $function) {
    $function_results[$function] = function_exists($function);
}
$all_functions_available = !in_array(false, $function_results);

$test_results['wordPress_compatibility_tests']['functions'] = [
    'all_required_functions_available' => $all_functions_available,
    'missing_functions' => array_keys(array_filter($function_results, function($available) { return !$available; })),
    'status' => $all_functions_available ? 'PASS' : 'FAIL'
];

// Test 3.2: WordPress constants
$required_constants = [
    'ABSPATH',
    'WP_DEBUG',
    'WP_DEBUG_LOG'
];

$constant_results = [];
foreach ($required_constants as $constant) {
    $constant_results[$constant] = defined($constant);
}

$test_results['wordPress_compatibility_tests']['constants'] = [
    'all_required_constants_defined' => !in_array(false, $constant_results),
    'missing_constants' => array_keys(array_filter($constant_results, function($defined) { return !$defined; })),
    'wp_debug_enabled' => defined('WP_DEBUG') ? WP_DEBUG : false,
    'wp_debug_log_enabled' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
    'status' => !in_array(false, $constant_results) ? 'PASS' : 'FAIL'
];

// Generate Summary
$ajax_passed = $test_results['ajax_security_tests']['nonce_generation']['status'] === 'PASS' && 
               $test_results['ajax_security_tests']['user_capabilities']['status'] === 'PASS' &&
               $test_results['ajax_security_tests']['ajax_referer_check']['status'] === 'PASS';

$db_passed = $test_results['database_permission_tests']['connection']['status'] === 'PASS' &&
             ($test_results['database_permission_tests']['categories_table']['status'] === 'PASS' || 
              $test_results['database_permission_tests']['ideas_table']['status'] === 'PASS');

$wp_passed = $test_results['wordPress_compatibility_tests']['version']['status'] === 'PASS' &&
             $test_results['wordPress_compatibility_tests']['functions']['status'] === 'PASS' &&
             $test_results['wordPress_compatibility_tests']['constants']['status'] === 'PASS';

$test_results['summary'] = [
    'ajax_security' => $ajax_passed ? 'PASS' : 'FAIL',
    'database_permissions' => $db_passed ? 'PASS' : 'FAIL',
    'wordpress_compatibility' => $wp_passed ? 'PASS' : 'FAIL',
    'overall_status' => ($ajax_passed && $db_passed && $wp_passed) ? 'PASS' : 'FAIL'
];

// Generate Recommendations
$recommendations = [];

if (!$ajax_passed) {
    $recommendations[] = "AJAX Security Issues Detected:";
    if ($test_results['ajax_security_tests']['nonce_generation']['status'] === 'FAIL') {
        $recommendations[] = "- Nonce generation/validation is failing. Check WordPress session handling.";
    }
    if ($test_results['ajax_security_tests']['user_capabilities']['status'] === 'FAIL') {
        $recommendations[] = "- Current user lacks manage_options capability. Ensure user is Administrator.";
    }
    if ($test_results['ajax_security_tests']['ajax_referer_check']['status'] === 'FAIL') {
        $recommendations[] = "- AJAX referer check failing. Verify nonce is being passed correctly in AJAX requests.";
    }
}

if (!$db_passed) {
    $recommendations[] = "Database Permission Issues Detected:";
    if ($test_results['database_permission_tests']['connection']['status'] === 'FAIL') {
        $recommendations[] = "- Database connection error: " . $test_results['database_permission_tests']['connection']['last_error'];
    }
    if ($test_results['database_permission_tests']['categories_table']['status'] === 'FAIL') {
        $recommendations[] = "- Content categories table missing or inaccessible.";
    }
    if ($test_results['database_permission_tests']['ideas_table']['status'] === 'FAIL') {
        $recommendations[] = "- Content ideas table missing or inaccessible.";
    }
}

if (!$wp_passed) {
    $recommendations[] = "WordPress Compatibility Issues Detected:";
    if ($test_results['wordPress_compatibility_tests']['functions']['status'] === 'FAIL') {
        $missing = implode(', ', $test_results['wordPress_compatibility_tests']['functions']['missing_functions']);
        $recommendations[] = "- Missing required WordPress functions: $missing";
    }
    if ($test_results['wordPress_compatibility_tests']['constants']['status'] === 'FAIL') {
        $missing = implode(', ', $test_results['wordPress_compatibility_tests']['constants']['missing_constants']);
        $recommendations[] = "- Missing required WordPress constants: $missing";
    }
}

if (empty($recommendations)) {
    $recommendations[] = "All tests passed successfully. If deletion still fails, check browser console for JavaScript errors.";
}

$test_results['recommendations'] = $recommendations;

// Output results as JSON
echo json_encode($test_results, JSON_PRETTY_PRINT);

// Also save results to a log file for debugging
$log_file = WP_CONTENT_DIR . '/smo_ajax_security_test_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($log_file, json_encode($test_results, JSON_PRETTY_PRINT));

echo "\n\nTest results saved to: $log_file\n";
echo "To view detailed results, open the log file or check the JSON output above.\n";
?>