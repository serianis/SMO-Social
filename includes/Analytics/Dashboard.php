<?php
namespace SMO_Social\Analytics;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_Analytics_Dashboard - Comprehensive Analytics System
 * 
 * Provides real-time analytics, performance tracking, and insights
 * across all 28 supported social media platforms.
 */
class Dashboard {

    private Collector $data_collector;
    private Processor $data_processor;
    private int $cache_duration = 1800; // 30 minutes

    /**
     * Constructor
     */
    public function __construct() {
        $this->data_collector = new Collector();
        $this->data_processor = new Processor();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Only add WordPress hooks if in WordPress mode
        if (function_exists('add_action')) {
            // Dashboard initialization
            add_action('admin_menu', array($this, 'add_analytics_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
            
            // AJAX handlers
            add_action('wp_ajax_smo_get_analytics_data', array($this, 'ajax_get_analytics_data'));
            add_action('wp_ajax_smo_export_analytics', array($this, 'ajax_export_analytics'));
            add_action('wp_ajax_smo_get_performance_data', array($this, 'ajax_get_performance_data'));
            
            // Real-time updates
            add_action('wp_ajax_smo_get_realtime_stats', array($this, 'ajax_get_realtime_stats'));
            
            // Scheduled data collection
            add_action('smo_collect_analytics_data', array($this->data_collector, 'collect_all_data'));
            
            // Only schedule events if WordPress scheduling functions exist
            if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
                if (!wp_next_scheduled('smo_collect_analytics_data')) {
                    wp_schedule_event(time(), 'hourly', 'smo_collect_analytics_data');
                }
            }
        }
    }

    /**
     * Add analytics menu page
     */
    public function add_analytics_menu() {
        add_submenu_page(
            'smo-social',
            __('Analytics', 'smo-social'),
            __('Analytics', 'smo-social'),
            'edit_posts',
            'smo-social-analytics',
            array($this, 'analytics_dashboard_page')
        );
    }

    /**
     * Enqueue analytics scripts
     */
    public function enqueue_analytics_scripts($hook) {
        if (strpos($hook, 'smo-social-analytics') === false) {
            return;
        }

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_script('smo-analytics-dashboard', SMO_SOCIAL_PLUGIN_URL . 'assets/js/analytics-dashboard.js', array('jquery', 'chart-js'), SMO_SOCIAL_VERSION, true);
        wp_enqueue_style('smo-analytics-dashboard', SMO_SOCIAL_PLUGIN_URL . 'assets/css/analytics-dashboard.css', array(), SMO_SOCIAL_VERSION);

        wp_localize_script('smo-analytics-dashboard', 'smoAnalytics', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_analytics_nonce'),
            'exportNonce' => wp_create_nonce('smo_export_nonce'),
            'realTimeNonce' => wp_create_nonce('smo_realtime_nonce'),
            'platforms' => $this->get_active_platforms()
        ));
    }

    /**
     * Main analytics dashboard page
     */
    public function analytics_dashboard_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Check if analytics tables exist, create if not
        $this->ensure_analytics_tables();

        ?>
        <div class="wrap smo-analytics-dashboard">
            <h1><?php _e('SMO Social Analytics Dashboard', 'smo-social'); ?></h1>
            
            <!-- Dashboard Controls -->
            <div class="smo-dashboard-controls">
                <div class="smo-control-group">
                    <label for="date-range"><?php _e('Date Range:', 'smo-social'); ?></label>
                    <select id="date-range">
                        <option value="7"><?php _e('Last 7 days', 'smo-social'); ?></option>
                        <option value="30" selected><?php _e('Last 30 days', 'smo-social'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'smo-social'); ?></option>
                        <option value="365"><?php _e('Last year', 'smo-social'); ?></option>
                    </select>
                </div>
                
                <div class="smo-control-group">
                    <label for="platform-filter"><?php _e('Platform:', 'smo-social'); ?></label>
                    <select id="platform-filter">
                        <option value="all" selected><?php _e('All Platforms', 'smo-social'); ?></option>
                        <?php foreach ($this->get_active_platforms() as $platform): ?>
                            <option value="<?php echo esc_attr($platform['slug']); ?>">
                                <?php echo esc_html($platform['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="smo-control-group">
                    <button id="refresh-analytics" class="button"><?php _e('Refresh Data', 'smo-social'); ?></button>
                    <button id="export-analytics" class="button button-primary"><?php _e('Export Data', 'smo-social'); ?></button>
                </div>
            </div>

            <!-- Key Metrics Row -->
            <div class="smo-metrics-row">
                <div class="smo-metric-card">
                    <h3><?php _e('Total Posts', 'smo-social'); ?></h3>
                    <div class="smo-metric-value" id="total-posts">-</div>
                    <div class="smo-metric-change" id="total-posts-change">-</div>
                </div>
                
                <div class="smo-metric-card">
                    <h3><?php _e('Total Reach', 'smo-social'); ?></h3>
                    <div class="smo-metric-value" id="total-reach">-</div>
                    <div class="smo-metric-change" id="total-reach-change">-</div>
                </div>
                
                <div class="smo-metric-card">
                    <h3><?php _e('Engagement Rate', 'smo-social'); ?></h3>
                    <div class="smo-metric-value" id="engagement-rate">-</div>
                    <div class="smo-metric-change" id="engagement-rate-change">-</div>
                </div>
                
                <div class="smo-metric-card">
                    <h3><?php _e('Best Platform', 'smo-social'); ?></h3>
                    <div class="smo-metric-value" id="best-platform">-</div>
                    <div class="smo-metric-change" id="best-platform-score">-</div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="smo-charts-row">
                <div class="smo-chart-container">
                    <h3><?php _e('Posts Over Time', 'smo-social'); ?></h3>
                    <canvas id="posts-timeline-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="smo-chart-container">
                    <h3><?php _e('Platform Performance', 'smo-social'); ?></h3>
                    <canvas id="platform-performance-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Engagement Analysis -->
            <div class="smo-engagement-section">
                <h3><?php _e('Engagement Analysis', 'smo-social'); ?></h3>
                <div class="smo-chart-container">
                    <canvas id="engagement-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Best Times Analysis -->
            <div class="smo-best-times-section">
                <h3><?php _e('Best Posting Times', 'smo-social'); ?></h3>
                <div id="best-times-grid" class="smo-best-times-grid">
                    <!-- Generated by JavaScript -->
                </div>
            </div>

            <!-- Content Performance Table -->
            <div class="smo-performance-table">
                <h3><?php _e('Top Performing Content', 'smo-social'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Content', 'smo-social'); ?></th>
                            <th><?php _e('Platform', 'smo-social'); ?></th>
                            <th><?php _e('Date', 'smo-social'); ?></th>
                            <th><?php _e('Reach', 'smo-social'); ?></th>
                            <th><?php _e('Engagement', 'smo-social'); ?></th>
                            <th><?php _e('Engagement Rate', 'smo-social'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="performance-table-body">
                        <!-- Generated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Real-time Updates -->
            <div class="smo-realtime-section">
                <h3><?php _e('Real-time Activity', 'smo-social'); ?></h3>
                <div id="realtime-activity" class="smo-realtime-activity">
                    <div class="smo-status-indicator">
                        <span class="smo-status-dot"></span>
                        <span class="smo-status-text"><?php _e('Connected', 'smo-social'); ?></span>
                    </div>
                    <div id="realtime-logs" class="smo-realtime-logs">
                        <!-- Real-time logs will appear here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Modal -->
        <div id="export-modal" class="smo-modal" style="display: none;">
            <div class="smo-modal-content">
                <span class="smo-modal-close">&times;</span>
                <h3><?php _e('Export Analytics Data', 'smo-social'); ?></h3>
                
                <div class="smo-export-options">
                    <div class="smo-export-group">
                        <label for="export-format"><?php _e('Format:', 'smo-social'); ?></label>
                        <select id="export-format">
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    
                    <div class="smo-export-group">
                        <label for="export-data"><?php _e('Data Type:', 'smo-social'); ?></label>
                        <select id="export-data">
                            <option value="summary">Summary</option>
                            <option value="detailed">Detailed</option>
                            <option value="raw">Raw Data</option>
                        </select>
                    </div>
                    
                    <div class="smo-export-group">
                        <button id="start-export" class="button button-primary"><?php _e('Export', 'smo-social'); ?></button>
                        <button id="cancel-export" class="button"><?php _e('Cancel', 'smo-social'); ?></button>
                    </div>
                </div>
                
                <div id="export-progress" class="smo-export-progress" style="display: none;">
                    <div class="smo-progress-bar">
                        <div class="smo-progress-fill"></div>
                    </div>
                    <div class="smo-progress-text"><?php _e('Preparing export...', 'smo-social'); ?></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Ensure analytics tables exist
     */
    public function ensure_analytics_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_analytics';
        $charset_collate = $wpdb->get_charset_collate();

        // First, create the table if it doesn't exist
        $create_sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            platform_post_id varchar(255) DEFAULT '',
            post_date datetime DEFAULT CURRENT_TIMESTAMP,
            data_type varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value bigint(20) DEFAULT 0,
            additional_data longtext DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_platform (post_id, platform),
            KEY metric_type (data_type, metric_name),
            KEY post_date (post_date)
        ) $charset_collate;";

        $wpdb->query($create_sql);

        // Check if table exists and get current columns
        $existing_columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
        $current_columns = array_column($existing_columns, 'Field');

        error_log("SMO Social: Current analytics table columns: " . json_encode($current_columns));

        // Define required columns and their definitions
        $required_columns = array(
            'post_id' => "ALTER TABLE {$table_name} ADD COLUMN post_id bigint(20) NOT NULL AFTER id",
            'platform' => "ALTER TABLE {$table_name} ADD COLUMN platform varchar(50) NOT NULL AFTER post_id",
            'platform_post_id' => "ALTER TABLE {$table_name} ADD COLUMN platform_post_id varchar(255) DEFAULT '' AFTER platform",
            'post_date' => "ALTER TABLE {$table_name} ADD COLUMN post_date datetime DEFAULT CURRENT_TIMESTAMP AFTER platform_post_id",
            'data_type' => "ALTER TABLE {$table_name} ADD COLUMN data_type varchar(50) NOT NULL AFTER post_date",
            'metric_name' => "ALTER TABLE {$table_name} ADD COLUMN metric_name varchar(100) NOT NULL AFTER data_type",
            'metric_value' => "ALTER TABLE {$table_name} ADD COLUMN metric_value bigint(20) DEFAULT 0 AFTER metric_name",
            'additional_data' => "ALTER TABLE {$table_name} ADD COLUMN additional_data longtext DEFAULT '' AFTER metric_value",
            'created_at' => "ALTER TABLE {$table_name} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER additional_data",
            'updated_at' => "ALTER TABLE {$table_name} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        );

        // Add missing columns
        foreach ($required_columns as $column => $alter_sql) {
            if (!in_array($column, $current_columns)) {
                error_log("SMO Social: Adding missing column {$column} with SQL: {$alter_sql}");
                $result = $wpdb->query($alter_sql);
                if ($result === false) {
                    error_log("SMO Social: Failed to add column {$column}: " . $wpdb->last_error);
                } else {
                    error_log("SMO Social: Successfully added column {$column}");
                }
            }
        }

        // Create indexes for performance
        $indexes = array(
            "CREATE INDEX IF NOT EXISTS idx_smo_analytics_platform ON {$table_name} (platform)",
            "CREATE INDEX IF NOT EXISTS idx_smo_analytics_date ON {$table_name} (post_date)",
            "CREATE INDEX IF NOT EXISTS idx_smo_analytics_data_type ON {$table_name} (data_type)"
        );

        foreach ($indexes as $index_sql) {
            $index_result = $wpdb->query($index_sql);
            if ($index_result === false) {
                error_log("SMO Social: Failed to create index: " . $wpdb->last_error);
            }
        }

        error_log("SMO Social: Analytics table setup completed");
    }

    /**
     * Get active platforms
     */
    public function get_active_platforms() {
        $platforms = array();
        $driver_dir = SMO_SOCIAL_PLUGIN_DIR . 'drivers/';
        
        if (is_dir($driver_dir)) {
            $files = glob($driver_dir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['slug']) && isset($data['name'])) {
                    $platforms[] = array(
                        'slug' => $data['slug'],
                        'name' => $data['name']
                    );
                }
            }
        }
        
        return $platforms;
    }

    /**
     * AJAX handler for analytics data with enhanced security
     */
    public function ajax_get_analytics_data(): void
    {
        check_ajax_referer('smo_analytics_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            // Enhanced input validation
            $date_range = filter_input(INPUT_POST, 'date_range', FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 365]
            ]);
            
            if ($date_range === false) {
                wp_send_json_error('Invalid date range. Must be between 1 and 365 days.');
            }

            $platform = filter_input(INPUT_POST, 'platform', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $platform = sanitize_text_field($platform);
            
            // Validate platform parameter
            $valid_platforms = array_column($this->get_active_platforms(), 'slug');
            if ($platform !== 'all' && !in_array($platform, $valid_platforms)) {
                wp_send_json_error('Invalid platform specified.');
            }

            // Rate limiting check
            if (!$this->check_rate_limit()) {
                wp_send_json_error('Too many requests. Please try again later.');
            }

            $data = $this->data_collector->get_analytics_data($date_range, $platform);
            $processed_data = $this->data_processor->process_analytics_data($data);
            
            wp_send_json_success($processed_data);
        } catch (\Exception $e) {
            error_log('Analytics data retrieval failed: ' . $e->getMessage());
            wp_send_json_error('Failed to retrieve analytics data. Please try again.');
        }
    }

    /**
     * AJAX handler for performance data with enhanced security
     */
    public function ajax_get_performance_data(): void
    {
        check_ajax_referer('smo_analytics_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            // Rate limiting check
            if (!$this->check_rate_limit()) {
                wp_send_json_error('Too many requests. Please try again later.');
            }

            $performance_data = $this->data_processor->get_performance_insights();
            wp_send_json_success($performance_data);
        } catch (\Exception $e) {
            error_log('Performance data retrieval failed: ' . $e->getMessage());
            wp_send_json_error('Failed to retrieve performance data. Please try again.');
        }
    }

    /**
     * AJAX handler for real-time stats with enhanced security
     */
    public function ajax_get_realtime_stats(): void
    {
        check_ajax_referer('smo_realtime_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            // Rate limiting check for real-time data
            if (!$this->check_rate_limit('realtime', 10, 60)) { // 10 requests per 60 seconds
                wp_send_json_error('Too many requests for real-time data. Please wait before trying again.');
            }

            $realtime_stats = $this->data_collector->get_realtime_stats();
            wp_send_json_success($realtime_stats);
        } catch (\Exception $e) {
            error_log('Real-time stats retrieval failed: ' . $e->getMessage());
            wp_send_json_error('Failed to retrieve real-time stats. Please try again.');
        }
    }

    /**
     * AJAX handler for export with enhanced security
     */
    public function ajax_export_analytics(): void
    {
        check_ajax_referer('smo_export_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            // Validate export parameters
            $format = filter_input(INPUT_POST, 'format', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $format = sanitize_text_field($format);
            
            $valid_formats = ['csv', 'json', 'pdf'];
            if (!in_array($format, $valid_formats)) {
                wp_send_json_error('Invalid export format specified.');
            }

            $data_type = filter_input(INPUT_POST, 'data_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $data_type = sanitize_text_field($data_type);
            
            $valid_data_types = ['summary', 'detailed', 'raw'];
            if (!in_array($data_type, $valid_data_types)) {
                wp_send_json_error('Invalid data type specified.');
            }

            $date_range = filter_input(INPUT_POST, 'date_range', FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 365]
            ]);
            
            if ($date_range === false) {
                wp_send_json_error('Invalid date range for export.');
            }

            $platform = filter_input(INPUT_POST, 'platform', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $platform = sanitize_text_field($platform);
            
            $valid_platforms = array_column($this->get_active_platforms(), 'slug');
            if ($platform !== 'all' && !in_array($platform, $valid_platforms)) {
                wp_send_json_error('Invalid platform specified for export.');
            }

            // Export rate limiting
            if (!$this->check_rate_limit('export', 5, 300)) { // 5 exports per 5 minutes
                wp_send_json_error('Too many export requests. Please wait before trying again.');
            }

            $export_data = $this->data_processor->generate_export_data($format, $data_type, $date_range, $platform);

            if ($export_data) {
                wp_send_json_success($export_data);
            } else {
                wp_send_json_error('Failed to generate export data. Please try again with different parameters.');
            }
        } catch (\Exception $e) {
            error_log('Export data generation failed: ' . $e->getMessage());
            wp_send_json_error('Export failed due to an error. Please try again.');
        }
    }

    /**
     * Get overview data for dashboard
     */
    public function get_overview_data($date_range = 30, $platform = 'all') {
        $data = $this->data_collector->get_analytics_data($date_range, $platform);
        return $this->data_processor->process_analytics_data($data);
    }

    /**
     * Filter data by date range with enhanced validation
     *
     * @param string $start_date Start date in Y-m-d H:i:s format
     * @param string $end_date End date in Y-m-d H:i:s format
     * @param string $platform Platform filter
     * @return array Filtered results
     */
    public function filter_by_date_range(string $start_date, string $end_date, string $platform = 'all'): array
    {
        global $wpdb;

        // Validate date format
        $date_format = 'Y-m-d H:i:s';
        $start = \DateTime::createFromFormat($date_format, $start_date);
        $end = \DateTime::createFromFormat($date_format, $end_date);
        
        if (!$start || !$end) {
            error_log('Invalid date format in filter_by_date_range');
            return [];
        }
        
        // Ensure start date is not after end date
        if ($start > $end) {
            error_log('Start date is after end date in filter_by_date_range');
            return [];
        }

        // Validate platform parameter
        if ($platform !== 'all') {
            $valid_platforms = array_column($this->get_active_platforms(), 'slug');
            if (!in_array($platform, $valid_platforms)) {
                error_log("Invalid platform {$platform} in filter_by_date_range");
                return [];
            }
        }

        $table_name = $wpdb->prefix . 'smo_analytics';
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE post_date BETWEEN %s AND %s",
            $start_date, $end_date
        );

        if ($platform !== 'all') {
            $query .= $wpdb->prepare(" AND platform = %s", $platform);
        }

        // Limit results to prevent memory issues
        $query .= " ORDER BY post_date DESC LIMIT 10000";

        try {
            $results = $wpdb->get_results($query, ARRAY_A);
            return $results ?: [];
        } catch (\Exception $e) {
            error_log('Database query failed in filter_by_date_range: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Filter data by platform with validation
     *
     * @param string $platform Platform to filter by
     * @param int $date_range Number of days to look back
     * @return array Filtered analytics data
     */
    public function filter_by_platform(string $platform, int $date_range = 30): array
    {
        // Validate platform
        $valid_platforms = array_column($this->get_active_platforms(), 'slug');
        if ($platform !== 'all' && !in_array($platform, $valid_platforms)) {
            error_log("Invalid platform {$platform} in filter_by_platform");
            return [];
        }
        
        // Validate date range
        if ($date_range < 1 || $date_range > 365) {
            error_log("Invalid date range {$date_range} in filter_by_platform");
            return [];
        }
        
        return $this->get_overview_data($date_range, $platform);
    }

    /**
     * Rate limiting implementation for API endpoints
     *
     * @param string $action Rate limit action type
     * @param int $max_requests Maximum requests allowed
     * @param int $window_seconds Time window in seconds
     * @return bool True if within limits, false otherwise
     */
    private function check_rate_limit(string $action = 'default', int $max_requests = 100, int $window_seconds = 3600): bool
    {
        if (!function_exists('wp_cache_get')) {
            return true; // Skip rate limiting if no cache available
        }
        
        $identifier = md5($_SERVER['REMOTE_ADDR'] ?? '' . $action);
        $current_time = time();
        $window_start = $current_time - $window_seconds;
        
        // Get existing request count
        $request_count = wp_cache_get($identifier, 'smo_rate_limit') ?: 0;
        $request_timestamps = wp_cache_get($identifier . '_timestamps', 'smo_rate_limit') ?: [];
        
        // Remove old timestamps
        $request_timestamps = array_filter($request_timestamps, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        // Check if within limits
        if (count($request_timestamps) >= $max_requests) {
            return false;
        }
        
        // Add current request
        $request_timestamps[] = $current_time;
        
        // Cache updated data
        wp_cache_set($identifier, count($request_timestamps), 'smo_rate_limit', $window_seconds);
        wp_cache_set($identifier . '_timestamps', $request_timestamps, 'smo_rate_limit', $window_seconds);
        
        return true;
    }
}
