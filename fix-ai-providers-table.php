<?php
/**
 * Critical Database Table Fix for SMO-Social AI Providers
 * 
 * This script creates the missing wp_smo_ai_providers table and populates it
 * with all configured AI providers to resolve the 140+ database errors.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

echo '<h1>🚀 SMO-Social AI Providers Database Fix</h1>';
echo '<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; padding: 20px; background: #f1f1f1; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .success { color: #46b450; background: #ecf7ed; padding: 15px; border-left: 4px solid #46b450; margin: 15px 0; border-radius: 4px; }
    .error { color: #dc3232; background: #fef7f7; padding: 15px; border-left: 4px solid #dc3232; margin: 15px 0; border-radius: 4px; }
    .info { color: #0073aa; background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin: 15px 0; border-radius: 4px; }
    .warning { color: #dba617; background: #fcf9e8; padding: 15px; border-left: 4px solid #dba617; margin: 15px 0; border-radius: 4px; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 14px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #0073aa; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .progress { background: #e0e0e0; border-radius: 4px; overflow: hidden; margin: 10px 0; }
    .progress-bar { background: #0073aa; height: 20px; transition: width 0.3s; }
    .step { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa; }
</style>';

echo '<div class="container">';

// Check current database state
echo '<div class="step">';
echo '<h2>📊 Step 1: Database State Analysis</h2>';

global $wpdb;
$providers_table = $wpdb->prefix . 'smo_ai_providers';

echo '<p><strong>Target Table:</strong> <code>' . esc_html($providers_table) . '</code></p>';

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$providers_table'");

if ($table_exists) {
    echo '<div class="warning">⚠️ Table already exists! Checking current state...</div>';
    
    // Check current provider count
    $current_count = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");
    echo '<p><strong>Current provider count:</strong> ' . intval($current_count) . '</p>';
    
    if ($current_count > 0) {
        echo '<div class="info">📋 Current providers in database:</div>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Display Name</th><th>Type</th><th>Status</th></tr>';
        
        $providers = $wpdb->get_results("SELECT id, name, display_name, provider_type, status FROM $providers_table ORDER BY name");
        foreach ($providers as $provider) {
            echo '<tr>';
            echo '<td>' . esc_html($provider->id) . '</td>';
            echo '<td><strong>' . esc_html($provider->name) . '</strong></td>';
            echo '<td>' . esc_html($provider->display_name) . '</td>';
            echo '<td>' . esc_html($provider->provider_type) . '</td>';
            echo '<td>' . esc_html($provider->status) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} else {
    echo '<div class="error">❌ Table does not exist - this is the source of the database errors!</div>';
}
echo '</div>';

// Create the table
echo '<div class="step">';
echo '<h2>🔧 Step 2: Creating Missing Database Table</h2>';

try {
    // Include the Database Schema class
    require_once(SMO_SOCIAL_PLUGIN_DIR . 'includes/Chat/DatabaseSchema.php');
    
    echo '<div class="info">📝 Creating table structure using SMO_Social\\Chat\\DatabaseSchema::create_tables()</div>';
    
    // Create the table
    \SMO_Social\Chat\DatabaseSchema::create_tables();
    
    // Verify table was created
    $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$providers_table'");
    
    if ($table_exists_after) {
        echo '<div class="success">✅ Table created successfully!</div>';
        
        // Check provider count after creation
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");
        echo '<p><strong>Provider count after creation:</strong> ' . intval($new_count) . '</p>';
        
        if ($new_count == 0) {
            echo '<div class="info">📦 Table created but empty - proceeding to populate with default providers...</div>';
        }
    } else {
        throw new Exception('Table creation failed - table still does not exist');
    }
    
} catch (Exception $e) {
    echo '<div class="error">❌ Table creation failed: ' . esc_html($e->getMessage()) . '</div>';
    echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
}
echo '</div>';

// Populate with providers
echo '<div class="step">';
echo '<h2>🚀 Step 3: Populating with AI Providers</h2>';

try {
    // Include required classes
    require_once(SMO_SOCIAL_PLUGIN_DIR . 'includes/AI/ProvidersConfig.php');
    require_once(SMO_SOCIAL_PLUGIN_DIR . 'includes/AI/DatabaseProviderMigrator.php');
    
    echo '<div class="info">🔄 Starting provider migration from static configuration...</div>';
    
    // Ensure database is ready and migrate providers
    \SMO_Social\AI\DatabaseProviderMigrator::ensure_database_ready();
    
    // Verify migration results
    $final_count = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");
    echo '<div class="success">✅ Migration completed! Final provider count: ' . intval($final_count) . '</div>';
    
    if ($final_count > 0) {
        echo '<div class="info">📋 Providers now available in database:</div>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Display Name</th><th>Type</th><th>Status</th><th>Default?</th></tr>';
        
        $providers = $wpdb->get_results("SELECT id, name, display_name, provider_type, status, is_default FROM $providers_table ORDER BY name");
        foreach ($providers as $provider) {
            $default_badge = $provider->is_default ? '✅' : '';
            echo '<tr>';
            echo '<td>' . esc_html($provider->id) . '</td>';
            echo '<td><strong>' . esc_html($provider->name) . '</strong></td>';
            echo '<td>' . esc_html($provider->display_name) . '</td>';
            echo '<td>' . esc_html($provider->provider_type) . '</td>';
            echo '<td>' . esc_html($provider->status) . '</td>';
            echo '<td>' . $default_badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">❌ Provider migration failed: ' . esc_html($e->getMessage()) . '</div>';
    echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
}
echo '</div>';

// Test the fix
echo '<div class="step">';
echo '<h2>🧪 Step 4: Testing the Fix</h2>';

try {
    // Test database provider loading
    require_once(SMO_SOCIAL_PLUGIN_DIR . 'includes/AI/DatabaseProviderLoader.php');
    
    echo '<div class="info">🔍 Testing database provider loading...</div>';
    
    // Test loading a few providers
    $test_providers = ['openai', 'huggingface', 'ollama', 'anthropic'];
    $successful_loads = 0;
    
    foreach ($test_providers as $provider_id) {
        $provider = \SMO_Social\AI\DatabaseProviderLoader::get_provider_from_database($provider_id);
        if ($provider) {
            echo '<div class="success">✅ Successfully loaded provider: ' . esc_html($provider_id) . '</div>';
            $successful_loads++;
        } else {
            echo '<div class="warning">⚠️ Provider not found: ' . esc_html($provider_id) . '</div>';
        }
    }
    
    if ($successful_loads > 0) {
        echo '<div class="success">🎉 Database provider loading is working! (' . $successful_loads . '/' . count($test_providers) . ' test providers loaded)</div>';
    }
    
    // Test static configuration still works
    echo '<div class="info">🔍 Testing static configuration fallback...</div>';
    $static_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();
    echo '<div class="success">✅ Static configuration has ' . count($static_providers) . ' providers</div>';
    
    // Test unified provider system
    echo '<div class="info">🔍 Testing unified provider system...</div>';
    $unified_provider = \SMO_Social\AI\ProvidersConfig::get_provider('openai');
    if ($unified_provider) {
        echo '<div class="success">✅ Unified provider system is working for OpenAI</div>';
    } else {
        echo '<div class="warning">⚠️ Unified provider system test failed for OpenAI</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="error">❌ Testing failed: ' . esc_html($e->getMessage()) . '</div>';
    echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
}
echo '</div>';

// Summary and next steps
echo '<div class="step">';
echo '<h2>📋 Step 5: Fix Summary</h2>';

$final_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$providers_table'");
$final_provider_count = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");

if ($final_table_exists && $final_provider_count > 0) {
    echo '<div class="success">🎉 <strong>FIX SUCCESSFUL!</strong></div>';
    echo '<div class="info">';
    echo '<strong>✅ What was fixed:</strong><br>';
    echo '• Created missing wp_smo_ai_providers table<br>';
    echo '• Populated with ' . $final_provider_count . ' AI provider configurations<br>';
    echo '• Database errors should now be resolved<br>';
    echo '• AI functionality should be restored<br><br>';
    
    echo '<strong>🔍 Expected results:</strong><br>';
    echo '• No more "table doesn\'t exist" errors in debug.log<br>';
    echo '• AI provider configurations accessible via database<br>';
    echo '• Plugin can initialize AI components without errors<br>';
    echo '• Settings page should show available providers<br><br>';
    
    echo '<strong>📝 Next steps:</strong><br>';
    echo '1. Check WordPress debug.log for errors<br>';
    echo '2. Test AI functionality in plugin settings<br>';
    echo '3. Configure API keys for desired providers<br>';
    echo '4. Test AI-powered features<br>';
    echo '</div>';
} else {
    echo '<div class="error">❌ <strong>FIX INCOMPLETE</strong></div>';
    echo '<div class="warning">';
    echo 'The table creation or population did not complete successfully.<br>';
    echo 'Please check the error messages above and try again.<br>';
    echo '</div>';
}

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=smo-social') . '">← Back to SMO Social Dashboard</a></p>';
echo '<p><a href="' . admin_url('admin.php?page=smo-social-settings') . '">→ Go to SMO Social Settings</a></p>';

echo '</div>'; // container
echo '</div>'; // container div

?>