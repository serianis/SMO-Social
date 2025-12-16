<?php
/**
 * Quick Database Setup Script
 * 
 * Run this script to manually create all database tables
 * Use this if you don't want to deactivate/reactivate the plugin
 * 
 * Usage: Navigate to this file in your browser or run via WP-CLI
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

echo '<h1>SMO Social - Database Setup</h1>';
echo '<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; padding: 20px; }
    .success { color: #46b450; background: #ecf7ed; padding: 10px; border-left: 4px solid #46b450; margin: 10px 0; }
    .error { color: #dc3232; background: #fef7f7; padding: 10px; border-left: 4px solid #dc3232; margin: 10px 0; }
    .info { color: #0073aa; background: #f0f6fc; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #0073aa; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
</style>';

// Include the Database Schema
require_once(SMO_SOCIAL_PLUGIN_DIR . 'includes/Database/DatabaseSchema.php');

echo '<div class="info"><strong>Step 1:</strong> Creating database tables...</div>';

try {
    // Create all tables
    \SMO_Social\Database\DatabaseSchema::create_tables();
    
    echo '<div class="success">✓ Database tables created successfully!</div>';
    
    // Check which tables were created
    global $wpdb;
    
    $tables = array(
        'Posts' => $wpdb->prefix . 'smo_posts',
        'Platforms' => $wpdb->prefix . 'smo_platforms',
        'Queue' => $wpdb->prefix . 'smo_queue',
        'Analytics' => $wpdb->prefix . 'smo_analytics',
        'Content Categories' => $wpdb->prefix . 'smo_content_categories',
        'Category Relationships' => $wpdb->prefix . 'smo_content_category_relationships',
        'RSS Feeds' => $wpdb->prefix . 'smo_rss_feeds',
        'Imported Content' => $wpdb->prefix . 'smo_imported_content',
        'Content Ideas' => $wpdb->prefix . 'smo_content_ideas',
        'Content Sources' => $wpdb->prefix . 'smo_content_sources'
    );
    
    echo '<h2>Database Tables Status</h2>';
    echo '<table>';
    echo '<tr><th>Table Name</th><th>Full Name</th><th>Status</th><th>Records</th></tr>';
    
    foreach ($tables as $name => $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            echo '<tr>';
            echo '<td><strong>' . esc_html($name) . '</strong></td>';
            echo '<td><code>' . esc_html($table) . '</code></td>';
            echo '<td><span style="color: #46b450;">✓ Exists</span></td>';
            echo '<td>' . intval($count) . '</td>';
            echo '</tr>';
        } else {
            echo '<tr>';
            echo '<td><strong>' . esc_html($name) . '</strong></td>';
            echo '<td><code>' . esc_html($table) . '</code></td>';
            echo '<td><span style="color: #dc3232;">✗ Missing</span></td>';
            echo '<td>-</td>';
            echo '</tr>';
        }
    }
    
    echo '</table>';
    
    // Show table structures
    echo '<h2>Table Structures</h2>';
    echo '<div class="info">Showing structure of created tables...</div>';
    
    foreach ($tables as $name => $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($exists) {
            echo '<h3>' . esc_html($name) . ' (<code>' . esc_html($table) . '</code>)</h3>';
            $columns = $wpdb->get_results("DESCRIBE $table");
            
            echo '<table>';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            foreach ($columns as $column) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($column->Field) . '</strong></td>';
                echo '<td>' . esc_html($column->Type) . '</td>';
                echo '<td>' . esc_html($column->Null) . '</td>';
                echo '<td>' . esc_html($column->Key) . '</td>';
                echo '<td>' . esc_html($column->Default) . '</td>';
                echo '<td>' . esc_html($column->Extra) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }
    
    echo '<div class="success"><strong>✓ Setup Complete!</strong> All database tables have been created successfully.</div>';
    echo '<div class="info"><strong>Next Steps:</strong><br>';
    echo '1. Navigate to SMO Social → Content Organizer<br>';
    echo '2. Test adding RSS feeds<br>';
    echo '3. Check that stats are displaying correctly<br>';
    echo '4. Monitor the browser console for any JavaScript errors</div>';
    
} catch (Exception $e) {
    echo '<div class="error"><strong>Error:</strong> ' . esc_html($e->getMessage()) . '</div>';
    echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
}

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=smo-social') . '">← Back to SMO Social Dashboard</a></p>';
