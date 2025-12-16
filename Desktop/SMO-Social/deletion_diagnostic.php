<?php
/**
 * SMO Social Deletion Failure Diagnostic Script
 * 
 * This script diagnoses potential causes of deletion failure errors
 * by systematically checking database, security, and WordPress compatibility
 * 
 * @package SMO_Social
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress environment, try to load WordPress
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

// Initialize diagnostic results
$diagnostic_results = [
    'timestamp' => current_time('mysql'),
    'wordpress_environment' => [],
    'database_status' => [],
    'table_status' => [],
    'user_permissions' => [],
    'ajax_security' => [],
    'error_logs' => [],
    'recommendations' => []
];

// 1. WordPress Environment Check
$diagnostic_results['wordpress_environment'] = [
    'wp_version' => get_bloginfo('version'),
    'php_version' => PHP_VERSION,
    'current_user_id' => get_current_user_id(),
    'current_user_login' => wp_get_current_user()->user_login,
    'user_capabilities' => array_keys(wp_get_current_user()->allcaps),
    'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
    'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce_life' => wp_verify_nonce('test', 'smo_social_nonce', 3600) ? 'valid' : 'invalid'
];

// 2. Database Connection and Tables Check
global $wpdb;
$diagnostic_results['database_status'] = [
    'connection_status' => $wpdb->last_error ? 'error' : 'ok',
    'last_error' => $wpdb->last_error,
    'prefix' => $wpdb->prefix
];

// Check if required tables exist
$required_tables = [
    'smo_content_categories' => $wpdb->prefix . 'smo_content_categories',
    'smo_content_ideas' => $wpdb->prefix . 'smo_content_ideas',
    'smo_content_sources' => $wpdb->prefix . 'smo_content_sources',
    'smo_imported_content' => $wpdb->prefix . 'smo_imported_content'
];

foreach ($required_tables as $table_name => $table_full_name) {
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_full_name)) === $table_full_name;
    
    $table_info = [
        'exists' => $table_exists,
        'name' => $table_full_name
    ];
    
    if ($table_exists) {
        // Check table structure
        $columns = $wpdb->get_results("DESCRIBE $table_full_name", ARRAY_A);
        $table_info['columns'] = array_column($columns, 'Field');
        $table_info['row_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_full_name");
        
        // Check permissions
        $permission_test = $wpdb->get_results("SELECT 1 FROM $table_full_name LIMIT 1");
        $table_info['read_permission'] = $permission_test !== false;
        
        $insert_test = $wpdb->insert($table_full_name, ['test_field' => 'test_value']);
        if ($insert_test) {
            $table_info['write_permission'] = true;
            // Clean up test record
            $wpdb->delete($table_full_name, ['test_field' => 'test_value']);
        } else {
            $table_info['write_permission'] = false;
            $table_info['write_error'] = $wpdb->last_error;
        }
        
        $delete_test = $wpdb->insert($table_full_name, ['test_field' => 'test_delete']);
        if ($delete_test) {
            $delete_result = $wpdb->delete($table_full_name, ['test_field' => 'test_delete']);
            $table_info['delete_permission'] = $delete_result !== false;
            if (!$delete_result) {
                $table_info['delete_error'] = $wpdb->last_error;
            }
        } else {
            $table_info['delete_permission'] = false;
            $table_info['delete_error'] = $wpdb->last_error;
        }
    }
    
    $diagnostic_results['table_status'][$table_name] = $table_info;
}

// 3. User Permissions Check
$current_user = wp_get_current_user();
$diagnostic_results['user_permissions'] = [
    'user_id' => $current_user->ID,
    'user_login' => $current_user->user_login,
    'user_email' => $current_user->user_email,
    'capabilities' => array_filter($current_user->allcaps, function($cap) { return $cap === true; }),
    'has_manage_options' => current_user_can('manage_options'),
    'has_delete_posts' => current_user_can('delete_posts'),
    'role' => implode(', ', $current_user->roles)
];

// 4. AJAX Security Check
$diagnostic_results['ajax_security'] = [
    'nonce_action' => 'smo_social_nonce',
    'nonce_check' => check_ajax_referer('smo_social_nonce', 'nonce', false),
    'user_can_ajax' => current_user_can('manage_options')
];

// Generate test nonce for validation
$test_nonce = wp_create_nonce('smo_social_nonce');
$diagnostic_results['ajax_security']['test_nonce'] = $test_nonce;
$diagnostic_results['ajax_security']['nonce_verification'] = wp_verify_nonce($test_nonce, 'smo_social_nonce');

// 5. Check Recent Error Logs
$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path)) {
    $log_content = file_get_contents($error_log_path);
    $recent_errors = [];
    
    // Look for recent SMO Social errors (last 24 hours)
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -1000); // Last 1000 lines
    
    foreach ($recent_lines as $line) {
        if (strpos($line, 'SMO Social') !== false || strpos($line, 'smo_') !== false) {
            $recent_errors[] = $line;
        }
    }
    
    $diagnostic_results['error_logs']['recent_smo_errors'] = array_slice($recent_errors, -20);
}

// Check WordPress debug log if enabled
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    $wp_debug_log = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($wp_debug_log)) {
        $wp_log_content = file_get_contents($wp_debug_log);
        $wp_recent_errors = [];
        $wp_lines = explode("\n", $wp_log_content);
        $wp_recent_lines = array_slice($wp_lines, -100);
        
        foreach ($wp_recent_lines as $line) {
            if (strpos($line, 'SMO Social') !== false || strpos($line, 'smo_') !== false) {
                $wp_recent_errors[] = $line;
            }
        }
        
        $diagnostic_results['error_logs']['wp_debug_log_errors'] = $wp_recent_errors;
    }
}

// 6. Generate Recommendations
$recommendations = [];

if (!$diagnostic_results['database_status']['connection_status'] === 'ok') {
    $recommendations[] = "Database connection error detected: " . $diagnostic_results['database_status']['last_error'];
}

foreach ($diagnostic_results['table_status'] as $table_name => $table_info) {
    if (!$table_info['exists']) {
        $recommendations[] = "Missing table: {$table_name}. Run database setup to create required tables.";
    } elseif (!$table_info['delete_permission']) {
        $recommendations[] = "No delete permission on table: {$table_name}. Check database user permissions.";
    }
}

if (!$diagnostic_results['ajax_security']['user_can_ajax']) {
    $recommendations[] = "Current user lacks manage_options capability. Check user role assignment.";
}

if (!$diagnostic_results['ajax_security']['nonce_check']) {
    $recommendations[] = "AJAX nonce verification failing. Check nonce implementation and timing.";
}

if (empty($recommendations)) {
    $recommendations[] = "No obvious issues detected. Check browser console for JavaScript errors and network tab for failed AJAX requests.";
}

$diagnostic_results['recommendations'] = $recommendations;

// Output results
?>
<!DOCTYPE html>
<html>
<head>
    <title>SMO Social Deletion Diagnostic Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
        .status-ok { color: #008000; font-weight: bold; }
        .status-error { color: #d63638; font-weight: bold; }
        .status-warning { color: #dba617; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .recommendation { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 5px 0; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SMO Social Deletion Failure Diagnostic Report</h1>
        <p><strong>Generated:</strong> <?php echo $diagnostic_results['timestamp']; ?></p>
        
        <!-- WordPress Environment -->
        <div class="section">
            <h3>WordPress Environment</h3>
            <table>
                <tr><th>Property</th><th>Value</th><th>Status</th></tr>
                <tr><td>WordPress Version</td><td><?php echo $diagnostic_results['wordpress_environment']['wp_version']; ?></td><td class="status-ok">OK</td></tr>
                <tr><td>PHP Version</td><td><?php echo $diagnostic_results['wordpress_environment']['php_version']; ?></td><td class="status-ok">OK</td></tr>
                <tr><td>Current User</td><td><?php echo $diagnostic_results['wordpress_environment']['current_user_login']; ?></td><td class="status-ok">OK</td></tr>
                <tr><td>WP Debug</td><td><?php echo $diagnostic_results['wordpress_environment']['wp_debug'] ? 'Enabled' : 'Disabled'; ?></td><td class="<?php echo $diagnostic_results['wordpress_environment']['wp_debug'] ? 'status-ok' : 'status-warning'; ?>"><?php echo $diagnostic_results['wordpress_environment']['wp_debug'] ? 'Good for debugging' : 'Disabled - enable for better error tracking'; ?></td></tr>
            </table>
        </div>

        <!-- Database Status -->
        <div class="section">
            <h3>Database Connection</h3>
            <table>
                <tr><th>Property</th><th>Value</th><th>Status</th></tr>
                <tr><td>Connection</td><td><?php echo $diagnostic_results['database_status']['connection_status']; ?></td><td class="<?php echo $diagnostic_results['database_status']['connection_status'] === 'ok' ? 'status-ok' : 'status-error'; ?>"><?php echo $diagnostic_results['database_status']['connection_status']; ?></td></tr>
                <tr><td>Table Prefix</td><td><?php echo $diagnostic_results['database_status']['prefix']; ?></td><td class="status-ok">OK</td></tr>
                <?php if ($diagnostic_results['database_status']['last_error']): ?>
                <tr><td>Last Error</td><td><?php echo htmlspecialchars($diagnostic_results['database_status']['last_error']); ?></td><td class="status-error">Error</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Table Status -->
        <div class="section">
            <h3>Database Tables Status</h3>
            <table>
                <tr><th>Table</th><th>Exists</th><th>Rows</th><th>Read</th><th>Write</th><th>Delete</th></tr>
                <?php foreach ($diagnostic_results['table_status'] as $table_name => $table_info): ?>
                <tr>
                    <td><?php echo $table_name; ?></td>
                    <td class="<?php echo $table_info['exists'] ? 'status-ok' : 'status-error'; ?>"><?php echo $table_info['exists'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo isset($table_info['row_count']) ? $table_info['row_count'] : 'N/A'; ?></td>
                    <td class="<?php echo isset($table_info['read_permission']) && $table_info['read_permission'] ? 'status-ok' : 'status-error'; ?>"><?php echo isset($table_info['read_permission']) && $table_info['read_permission'] ? 'Yes' : 'No'; ?></td>
                    <td class="<?php echo isset($table_info['write_permission']) && $table_info['write_permission'] ? 'status-ok' : 'status-error'; ?>"><?php echo isset($table_info['write_permission']) && $table_info['write_permission'] ? 'Yes' : 'No'; ?></td>
                    <td class="<?php echo isset($table_info['delete_permission']) && $table_info['delete_permission'] ? 'status-ok' : 'status-error'; ?>"><?php echo isset($table_info['delete_permission']) && $table_info['delete_permission'] ? 'Yes' : 'No'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- User Permissions -->
        <div class="section">
            <h3>User Permissions</h3>
            <table>
                <tr><th>Property</th><th>Value</th><th>Status</th></tr>
                <tr><td>User ID</td><td><?php echo $diagnostic_results['user_permissions']['user_id']; ?></td><td class="status-ok">OK</td></tr>
                <tr><td>User Role</td><td><?php echo $diagnostic_results['user_permissions']['role']; ?></td><td class="status-ok">OK</td></tr>
                <tr><td>Manage Options</td><td><?php echo $diagnostic_results['user_permissions']['has_manage_options'] ? 'Yes' : 'No'; ?></td><td class="<?php echo $diagnostic_results['user_permissions']['has_manage_options'] ? 'status-ok' : 'status-error'; ?>"><?php echo $diagnostic_results['user_permissions']['has_manage_options'] ? 'Yes' : 'No'; ?></td></tr>
                <tr><td>Delete Posts</td><td><?php echo $diagnostic_results['user_permissions']['has_delete_posts'] ? 'Yes' : 'No'; ?></td><td class="<?php echo $diagnostic_results['user_permissions']['has_delete_posts'] ? 'status-ok' : 'status-warning'; ?>"><?php echo $diagnostic_results['user_permissions']['has_delete_posts'] ? 'Yes' : 'No'; ?></td></tr>
            </table>
        </div>

        <!-- AJAX Security -->
        <div class="section">
            <h3>AJAX Security</h3>
            <table>
                <tr><th>Check</th><th>Result</th><th>Status</th></tr>
                <tr><td>Nonce Verification</td><td><?php echo $diagnostic_results['ajax_security']['nonce_check'] ? 'Valid' : 'Invalid'; ?></td><td class="<?php echo $diagnostic_results['ajax_security']['nonce_check'] ? 'status-ok' : 'status-error'; ?>"><?php echo $diagnostic_results['ajax_security']['nonce_check'] ? 'Valid' : 'Invalid'; ?></td></tr>
                <tr><td>User Can AJAX</td><td><?php echo $diagnostic_results['ajax_security']['user_can_ajax'] ? 'Yes' : 'No'; ?></td><td class="<?php echo $diagnostic_results['ajax_security']['user_can_ajax'] ? 'status-ok' : 'status-error'; ?>"><?php echo $diagnostic_results['ajax_security']['user_can_ajax'] ? 'Yes' : 'No'; ?></td></tr>
            </table>
        </div>

        <!-- Error Logs -->
        <?php if (!empty($diagnostic_results['error_logs']['recent_smo_errors'])): ?>
        <div class="section">
            <h3>Recent Error Logs</h3>
            <h4>SMO Social Errors</h4>
            <pre><?php echo implode("\n", array_slice($diagnostic_results['error_logs']['recent_smo_errors'], -10)); ?></pre>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <div class="section">
            <h3>Recommendations</h3>
            <?php foreach ($diagnostic_results['recommendations'] as $recommendation): ?>
            <div class="recommendation">
                <?php echo htmlspecialchars($recommendation); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Raw Data -->
        <div class="section">
            <h3>Raw Diagnostic Data</h3>
            <details>
                <summary>Click to expand raw data</summary>
                <pre><?php echo htmlspecialchars(json_encode($diagnostic_results, JSON_PRETTY_PRINT)); ?></pre>
            </details>
        </div>
    </div>
</body>
</html>