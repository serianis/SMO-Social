<?php
/**
 * SMO Social Diagnostic Runner
 * 
 * This script helps you run all diagnostic tests to identify the exact cause
 * of the deletion failure error.
 */

// Load WordPress
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
    die('WordPress environment not found. Please ensure you\'re running this from your WordPress installation.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SMO Social Diagnostic Runner</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .button { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; }
        .button:hover { background: #005a87; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .status.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .status.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .status.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; border: 1px solid #dee2e6; }
        .step { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .step h3 { margin-top: 0; color: #0073aa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SMO Social Deletion Failure Diagnostic</h1>
        <p>This tool will help identify the exact cause of your deletion failure error.</p>
        
        <div class="step">
            <h3>Step 1: WordPress Environment Check</h3>
            <?php if (defined('ABSPATH')): ?>
                <div class="status success">✅ WordPress environment loaded successfully</div>
                <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                <p><strong>Current User:</strong> <?php echo wp_get_current_user()->user_login; ?></p>
                <p><strong>User Capabilities:</strong> 
                    <?php 
                    $caps = wp_get_current_user()->allcaps;
                    echo 'manage_options: ' . (isset($caps['manage_options']) && $caps['manage_options'] ? '✅ Yes' : '❌ No');
                    ?>
                </p>
            <?php else: ?>
                <div class="status error">❌ WordPress environment not loaded</div>
            <?php endif; ?>
        </div>

        <div class="step">
            <h3>Step 2: Database Connection Check</h3>
            <?php
            global $wpdb;
            if ($wpdb->last_error === '' || $wpdb->last_error === null):
            ?>
                <div class="status success">✅ Database connection OK</div>
            <?php else: ?>
                <div class="status error">❌ Database connection error: <?php echo htmlspecialchars($wpdb->last_error); ?></div>
            <?php endif; ?>
            <p><strong>Table Prefix:</strong> <?php echo $wpdb->prefix; ?></p>
        </div>

        <div class="step">
            <h3>Step 3: Check Required Tables</h3>
            <?php
            $required_tables = [
                'smo_content_categories' => $wpdb->prefix . 'smo_content_categories',
                'smo_content_ideas' => $wpdb->prefix . 'smo_content_ideas'
            ];
            
            foreach ($required_tables as $table_name => $table_full_name):
                $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_full_name)) === $table_full_name;
            ?>
                <p><strong><?php echo $table_name; ?>:</strong> 
                    <?php if ($table_exists): ?>
                        <span style="color: green;">✅ Exists</span>
                        <?php
                        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_full_name");
                        echo " ($row_count rows)";
                        ?>
                    <?php else: ?>
                        <span style="color: red;">❌ Missing</span>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>

        <div class="step">
            <h3>Step 4: AJAX Security Test</h3>
            <?php
            $nonce = wp_create_nonce('smo_social_nonce');
            $nonce_valid = wp_verify_nonce($nonce, 'smo_social_nonce');
            ?>
            <p><strong>Nonce Generation:</strong> <?php echo $nonce_valid ? '✅ Working' : '❌ Failed'; ?></p>
            <p><strong>User Can AJAX:</strong> <?php echo current_user_can('manage_options') ? '✅ Yes' : '❌ No'; ?></p>
        </div>

        <div class="step">
            <h3>Step 5: Run Comprehensive Diagnostics</h3>
            <p>Click the buttons below to run detailed diagnostic tests:</p>
            
            <a href="deletion_diagnostic.php" class="button" target="_blank">🔍 Run Full Diagnostic Report</a>
            <a href="ajax_security_database_test.php" class="button" target="_blank">🛡️ Test AJAX Security & Database</a>
        </div>

        <div class="step">
            <h3>Step 6: Check Error Logs</h3>
            <?php if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG): ?>
                <div class="status info">ℹ️ WordPress debug logging is enabled</div>
                <p>Check your error logs at: <code><?php echo WP_CONTENT_DIR; ?>/debug.log</code></p>
            <?php else: ?>
                <div class="status info">ℹ️ WordPress debug logging is disabled</div>
                <p>To enable detailed logging, add these lines to your <code>wp-config.php</code>:</p>
                <pre>define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);</pre>
            <?php endif; ?>
        </div>

        <div class="step">
            <h3>Next Steps Based on Results</h3>
            <div id="recommendations">
                <p><em>Run the diagnostic tests above to get specific recommendations...</em></p>
            </div>
        </div>
    </div>

    <script>
        // Simple recommendations based on basic checks
        document.addEventListener('DOMContentLoaded', function() {
            var recommendations = document.getElementById('recommendations');
            
            // Check for missing tables
            var tableChecks = document.querySelectorAll('p');
            var missingTables = [];
            
            tableChecks.forEach(function(p) {
                if (p.innerHTML.includes('❌ Missing')) {
                    missingTables.push(p);
                }
            });
            
            if (missingTables.length > 0) {
                recommendations.innerHTML = '<div class="status error"><h4>🔴 Critical Issue Found: Missing Database Tables</h4>' +
                    '<p>Your SMO Social plugin is missing required database tables. This is the most common cause of deletion failures.</p>' +
                    '<p><strong>Solution:</strong> Run the plugin setup/activation to create missing tables, or manually create them using the SQL provided in the diagnostic guide.</p>' +
                    '<p><a href="deletion_failure_debugging_guide.md" class="button">📖 View Fix Guide</a></p></div>';
            } else {
                // Check other potential issues
                var capabilityCheck = document.querySelector('p:contains("manage_options: ❌ No")');
                if (capabilityCheck) {
                    recommendations.innerHTML = '<div class="status error"><h4>🔴 Permission Issue</h4>' +
                        '<p>Your user account lacks the required permissions to perform deletions.</p>' +
                        '<p><strong>Solution:</strong> Ensure you\'re logged in as an Administrator.</p></div>';
                } else {
                    recommendations.innerHTML = '<div class="status success"><h4>✅ Basic Checks Passed</h4>' +
                        '<p>Your WordPress environment looks good. The issue may be with AJAX requests or specific database permissions.</p>' +
                        '<p>Run the comprehensive diagnostic tests above for detailed analysis.</p></div>';
                }
            }
        });
    </script>
</body>
</html>