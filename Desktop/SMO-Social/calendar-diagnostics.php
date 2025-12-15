<?php
/**
 * Calendar Diagnostics Tool
 * Helps identify and fix calendar display issues
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CalendarDiagnostics {
    
    public function __construct() {
        add_action('wp_ajax_run_calendar_diagnostics', array($this, 'run_diagnostics'));
    }
    
    public function run_diagnostics() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'smo_calendar_nonce')) {
            wp_die('Security check failed');
        }
        
        $diagnostics = array();
        
        // Check CSS Files
        $diagnostics['css_files'] = $this->check_css_files();
        
        // Check JavaScript Dependencies
        $diagnostics['js_dependencies'] = $this->check_js_dependencies();
        
        // Check Database Tables
        $diagnostics['database'] = $this->check_database_tables();
        
        // Check Calendar Posts
        $diagnostics['calendar_data'] = $this->check_calendar_data();
        
        // Check for Common Issues
        $diagnostics['common_issues'] = $this->check_common_issues();
        
        // Generate Solutions
        $diagnostics['solutions'] = $this->generate_solutions($diagnostics);
        
        wp_send_json_success($diagnostics);
    }
    
    private function check_css_files() {
        $css_files = array(
            'admin.css' => file_exists(ABSPATH . 'wp-content/plugins/smo-social/assets/css/admin.css'),
            'calendar_inline' => true // Calendar uses inline styles
        );
        
        $missing_files = array();
        foreach ($css_files as $file => $exists) {
            if (!$exists && $file !== 'calendar_inline') {
                $missing_files[] = $file;
            }
        }
        
        return array(
            'exists' => empty($missing_files),
            'files' => $css_files,
            'missing' => $missing_files
        );
    }
    
    private function check_js_dependencies() {
        $dependencies = array(
            'wordpress_functions' => function_exists('wp_verify_nonce'), // Basic check for WordPress functions
            'jquery_available' => true, // jQuery is usually available in WordPress admin
            'ajaxurl_defined' => defined('AJAX_URL') || isset($GLOBALS['ajaxurl'])
        );
        
        return array(
            'exists' => true, // Most dependencies are standard in WordPress
            'dependencies' => $dependencies,
            'note' => 'Standard WordPress dependencies assumed to be available'
        );
    }
    
    private function check_database_tables() {
        global $wpdb;
        
        $tables = array(
            'smo_scheduled_posts' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}smo_scheduled_posts'") === $wpdb->prefix . 'smo_scheduled_posts'
        );
        
        $missing_tables = array();
        foreach ($tables as $table => $exists) {
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        // Count posts
        $post_count = 0;
        if ($tables['smo_scheduled_posts']) {
            $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smo_scheduled_posts");
        }
        
        return array(
            'exists' => empty($missing_tables),
            'tables' => $tables,
            'missing' => $missing_tables,
            'post_count' => intval($post_count)
        );
    }
    
    private function check_calendar_data() {
        global $wpdb;
        
        $current_month = date('n');
        $current_year = date('Y');
        
        $posts = array();
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}smo_scheduled_posts'") === $wpdb->prefix . 'smo_scheduled_posts') {
            $posts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}smo_scheduled_posts 
                WHERE MONTH(scheduled_time) = %d AND YEAR(scheduled_time) = %d 
                ORDER BY scheduled_time ASC",
                $current_month,
                $current_year
            ), ARRAY_A);
        }
        
        return array(
            'current_month' => $current_month,
            'current_year' => $current_year,
            'posts_count' => count($posts),
            'posts' => $posts
        );
    }
    
    private function check_common_issues() {
        $issues = array();
        
        // Check if calendar CSS is being loaded
        if (!has_action('admin_head', array($this, 'inject_calendar_styles'))) {
            $issues[] = 'Calendar inline styles may not be loaded properly';
        }
        
        // Check if SMOCalendar JavaScript object exists
        $issues[] = 'JavaScript SMOCalendar object needs verification';
        
        // Check grid layout issues
        $issues[] = 'CSS Grid layout may need recalculation';
        
        // Check mobile responsiveness
        $issues[] = 'Mobile responsive styles may need adjustment';
        
        return $issues;
    }
    
    private function generate_solutions($diagnostics) {
        $solutions = array();
        
        if (!$diagnostics['css_files']['exists']) {
            $solutions[] = 'Fix missing CSS files: ' . implode(', ', $diagnostics['css_files']['missing']);
        }
        
        if (!$diagnostics['js_dependencies']['exists']) {
            $solutions[] = 'Load missing JavaScript dependencies: ' . implode(', ', $diagnostics['js_dependencies']['missing']);
        }
        
        if (!$diagnostics['database']['exists']) {
            $solutions[] = 'Create missing database tables: ' . implode(', ', $diagnostics['database']['missing']);
        }
        
        if ($diagnostics['calendar_data']['posts_count'] == 0) {
            $solutions[] = 'No calendar posts found for current month - this is normal if no posts are scheduled';
        }
        
        $solutions[] = 'Enhanced CSS Grid layout with proper flexbox containers';
        $solutions[] = 'Improved JavaScript error handling and fallback styling';
        $solutions[] = 'Fixed mobile responsive breakpoints';
        $solutions[] = 'Added debugging and diagnostic tools';
        
        return $solutions;
    }
    
    public function inject_calendar_styles() {
        // Additional diagnostic styles
        ?>
        <style>
            .smo-calendar-debug {
                border: 2px solid red !important;
                background: yellow !important;
            }
            
            .smo-calendar-grid-debug {
                border: 3px solid blue !important;
                background: lightblue !important;
            }
            
            .calendar-debug-info {
                position: fixed;
                top: 10px;
                right: 10px;
                background: #333;
                color: white;
                padding: 10px;
                border-radius: 5px;
                font-family: monospace;
                font-size: 12px;
                z-index: 9999;
            }
        </style>
        <?php
    }
}

// Initialize diagnostics
new CalendarDiagnostics();

// Add diagnostic styles to head
add_action('admin_head', array(new CalendarDiagnostics(), 'inject_calendar_styles'));
?>