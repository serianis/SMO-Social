<?php
namespace SMO_Social\Analytics;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_Analytics_Collector - Data Collection Engine
 * 
 * Collects and stores analytics data from all 28 platforms
 * with intelligent scheduling and error handling.
 */
class Collector {

    private $wpdb;
    private $cache_key_prefix = 'smo_analytics_';
    private $batch_size = 100;
    private $retry_attempts = 3;
    private $stream_batch_size = 1000;
    private $max_memory_usage = 50; // MB
    private $current_memory_usage = 0;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into post publication to collect initial data
        add_action('smo_post_published', array($this, 'collect_post_data'), 10, 3);

        // Scheduled data refresh
        add_action('smo_refresh_analytics_data', array($this, 'refresh_all_platform_data'));

        // Webhook handlers for platform updates
        add_action('smo_webhook_received', array($this, 'handle_webhook_data'));
    }

    /**
     * Collect analytics data for a specific date range and platform
     */
    /**
     * Set batch processing configuration
     */
    public function set_batch_config($batch_size, $max_memory_usage) {
        $this->stream_batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage;
    }

    /**
     * Get current batch processing configuration
     */
    public function get_batch_config() {
        return array(
            'batch_size' => $this->stream_batch_size,
            'max_memory_usage' => $this->max_memory_usage,
            'current_memory_usage' => $this->current_memory_usage
        );
    }

    /**
     * Check memory usage and clean up if needed
     */
    private function check_memory_usage() {
        $this->current_memory_usage = memory_get_usage(true) / (1024 * 1024);

        if ($this->current_memory_usage > $this->max_memory_usage) {
            $this->cleanup_resources();
        }
    }

    /**
     * Cleanup resources to free memory
     */
    private function cleanup_resources() {
        gc_collect_cycles();
        if (function_exists('error_log')) {
            error_log('SMO Analytics Collector: Garbage collection completed. Current memory usage: ' . $this->current_memory_usage . 'MB');
        }
    }

    public function get_analytics_data($date_range = 30, $platform = 'all') {
        $cache_key = $this->cache_key_prefix . 'data_' . $date_range . '_' . $platform;

        // Check cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Build query
        $table_name = $this->wpdb->prefix . 'smo_analytics';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));

        $where_clause = "WHERE post_date >= %s";
        $query_params = array($date_from);

        if ($platform !== 'all') {
            $where_clause .= " AND platform = %s";
            $query_params[] = $platform;
        }

        // Use batch processing for large datasets
        $processed_data = $this->process_analytics_results_with_batching($where_clause, $query_params);

        // Cache for 30 minutes
        set_transient($cache_key, $processed_data, 1800);

        return $processed_data;
    }

    /**
     * Get real-time statistics
     */
    public function get_realtime_stats() {
        $cache_key = $this->cache_key_prefix . 'realtime';
        
        $cached_stats = get_transient($cache_key);
        if ($cached_stats !== false) {
            return $cached_stats;
        }

        $table_name = $this->wpdb->prefix . 'smo_analytics';
        $today = date('Y-m-d');
        
        // Get today's metrics
        $today_stats = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT platform, metric_name, SUM(metric_value) as value 
             FROM {$table_name} 
             WHERE DATE(post_date) = %s 
             GROUP BY platform, metric_name",
            $today
        ));

        // Get recent activity (last 24 hours)
        $recent_activity = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE post_date >= %s 
             ORDER BY created_at DESC 
             LIMIT 20",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));

        $stats = array(
            'today' => $this->group_stats_by_platform($today_stats),
            'recent_activity' => $recent_activity,
            'last_updated' => current_time('mysql'),
            'platform_status' => $this->get_platform_status()
        );

        // Cache for 5 minutes
        set_transient($cache_key, $stats, 300);
        
        return $stats;
    }

    /**
     * Collect data for a newly published post
     */
    public function collect_post_data($post_id, $platforms, $platform_results) {
        foreach ($platform_results as $platform => $result) {
            if (isset($result['success']) && $result['success']) {
                $this->record_post_data($post_id, $platform, $result);
            }
        }
    }

    /**
     * Record analytics data for a post
     */
    private function record_post_data($post_id, $platform, $result) {
        $table_name = $this->wpdb->prefix . 'smo_analytics';
        $post_date = get_the_date('Y-m-d H:i:s', $post_id);
        
        // Base metrics that apply to all posts
        $metrics = array(
            'posts' => 1,
            'published' => $result['success'] ? 1 : 0,
            'failed' => $result['success'] ? 0 : 1
        );

        // Platform-specific metrics
        if (isset($result['platform_data'])) {
            $platform_data = $result['platform_data'];
            
            // Engagement metrics
            if (isset($platform_data['likes'])) {
                $metrics['likes'] = intval($platform_data['likes']);
            }
            if (isset($platform_data['shares'])) {
                $metrics['shares'] = intval($platform_data['shares']);
            }
            if (isset($platform_data['comments'])) {
                $metrics['comments'] = intval($platform_data['comments']);
            }
            if (isset($platform_data['reach'])) {
                $metrics['reach'] = intval($platform_data['reach']);
            }
            if (isset($platform_data['impressions'])) {
                $metrics['impressions'] = intval($platform_data['impressions']);
            }
            if (isset($platform_data['clicks'])) {
                $metrics['clicks'] = intval($platform_data['clicks']);
            }
            
            // Platform-specific metadata
            $additional_data = array(
                'platform_post_id' => $platform_data['post_id'] ?? '',
                'url' => $platform_data['url'] ?? '',
                'scheduled_time' => $platform_data['scheduled_time'] ?? '',
                'media_count' => count($platform_data['media'] ?? array())
            );
        } else {
            $additional_data = array();
        }

        // Insert each metric as a separate record
        foreach ($metrics as $metric_name => $metric_value) {
            $this->wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'platform' => $platform,
                    'platform_post_id' => $additional_data['platform_post_id'] ?? '',
                    'post_date' => $post_date,
                    'data_type' => 'engagement',
                    'metric_name' => $metric_name,
                    'metric_value' => $metric_value,
                    'additional_data' => json_encode($additional_data)
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
            );
        }
    }

    /**
     * Collect all data (alias for refresh_all_platform_data)
     */
    public function collect_all_data() {
        $this->refresh_all_platform_data();
    }

    /**
     * Refresh data from all platforms
     */
    public function refresh_all_platform_data() {
        $enabled_platforms = $this->get_enabled_platforms();

        foreach ($enabled_platforms as $platform) {
            $this->refresh_platform_data($platform);
        }
    }

    /**
     * Refresh data from a specific platform
     */
    public function refresh_platform_data($platform) {
        // Get recent posts for this platform
        $posts = $this->get_recent_posts_for_platform($platform, 100);
        
        foreach ($posts as $post) {
            $this->update_post_metrics($post, $platform);
        }
    }

    /**
     * Update post metrics for a specific platform
     */
    private function update_post_metrics($post, $platform) {
        // This would fetch updated metrics from the platform API
        // For now, simulating updated metrics
        $updated_metrics = array(
            'likes' => rand(0, 100),
            'shares' => rand(0, 50),
            'comments' => rand(0, 25),
            'reach' => rand(100, 5000)
        );

        // Update the metrics in the database
        foreach ($updated_metrics as $metric_name => $metric_value) {
            $this->update_metric($post->post_id, $platform, $metric_name, $metric_value);
        }
    }

    /**
     * Handle webhook data from platforms
     */
    public function handle_webhook_data($webhook_data) {
        $platform = $webhook_data['platform'] ?? '';
        $post_id = $webhook_data['post_id'] ?? '';
        $metrics = $webhook_data['metrics'] ?? array();
        
        if (!$platform || !$post_id || empty($metrics)) {
            return;
        }

        // Update metrics from webhook
        foreach ($metrics as $metric_name => $metric_value) {
            $this->update_metric($post_id, $platform, $metric_name, $metric_value);
        }
    }

    /**
     * Process analytics results with batch processing for memory efficiency
     */
    private function process_analytics_results_with_batching($where_clause, $query_params) {
        $this->check_memory_usage();

        $processed = array(
            'summary' => array(
                'total_posts' => 0,
                'total_reach' => 0,
                'total_engagement' => 0,
                'average_engagement_rate' => 0
            ),
            'platforms' => array(),
            'timeline' => array(),
            'top_posts' => array()
        );

        $platform_totals = array();
        $daily_totals = array();

        // Use batch processing to avoid memory overload
        $offset = 0;
        $has_more_data = true;

        while ($has_more_data) {
            $this->check_memory_usage();

            $query = "
                SELECT
                    platform,
                    data_type,
                    metric_name,
                    SUM(metric_value) as total_value,
                    AVG(metric_value) as avg_value,
                    COUNT(*) as record_count,
                    post_date
                FROM {$this->wpdb->prefix}smo_analytics
                {$where_clause}
                GROUP BY platform, data_type, metric_name, DATE(post_date)
                ORDER BY post_date DESC
                LIMIT %d OFFSET %d
            ";

            array_push($query_params, $this->stream_batch_size, $offset);

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $query_params)
            );

            if (empty($results)) {
                $has_more_data = false;
                break;
            }

            // Process this batch
            foreach ($results as $result) {
                $platform = $result->platform;
                $date = date('Y-m-d', strtotime($result->post_date));

                // Initialize platform if not exists
                if (!isset($platform_totals[$platform])) {
                    $platform_totals[$platform] = array(
                        'posts' => 0, 'reach' => 0, 'engagement' => 0
                    );
                }

                // Initialize date if not exists
                if (!isset($daily_totals[$date])) {
                    $daily_totals[$date] = array();
                }

                // Process metric
                $metric_name = $result->metric_name;
                $value = intval($result->total_value);

                switch ($metric_name) {
                    case 'posts':
                    case 'published':
                        $platform_totals[$platform]['posts'] += $value;
                        $processed['summary']['total_posts'] += $value;
                        if (!isset($daily_totals[$date]['posts'])) {
                            $daily_totals[$date]['posts'] = 0;
                        }
                        $daily_totals[$date]['posts'] += $value;
                        break;

                    case 'reach':
                    case 'impressions':
                        $platform_totals[$platform]['reach'] += $value;
                        $processed['summary']['total_reach'] += $value;
                        if (!isset($daily_totals[$date]['reach'])) {
                            $daily_totals[$date]['reach'] = 0;
                        }
                        $daily_totals[$date]['reach'] += $value;
                        break;

                    case 'likes':
                    case 'shares':
                    case 'comments':
                    case 'clicks':
                        $platform_totals[$platform]['engagement'] += $value;
                        $processed['summary']['total_engagement'] += $value;
                        if (!isset($daily_totals[$date]['engagement'])) {
                            $daily_totals[$date]['engagement'] = 0;
                        }
                        $daily_totals[$date]['engagement'] += $value;
                        break;
                }
            }

            $offset += $this->stream_batch_size;

            // Reset query params for next iteration
            $query_params = array($query_params[0]);
            if (isset($query_params[1])) {
                $query_params[] = $query_params[1];
            }
        }

        $processed['platforms'] = $platform_totals;
        $processed['timeline'] = $daily_totals;

        // Calculate engagement rate
        if ($processed['summary']['total_reach'] > 0) {
            $processed['summary']['average_engagement_rate'] =
                ($processed['summary']['total_engagement'] / $processed['summary']['total_reach']) * 100;
        }

        return $processed;
    }

    /**
     * Process analytics results into structured format (original method for compatibility)
     */
    private function process_analytics_results($results) {
        $processed = array(
            'summary' => array(
                'total_posts' => 0,
                'total_reach' => 0,
                'total_engagement' => 0,
                'average_engagement_rate' => 0
            ),
            'platforms' => array(),
            'timeline' => array(),
            'top_posts' => array()
        );

        $platform_totals = array();
        $daily_totals = array();

        foreach ($results as $result) {
            $platform = $result->platform;
            $date = date('Y-m-d', strtotime($result->post_date));

            // Initialize platform if not exists
            if (!isset($platform_totals[$platform])) {
                $platform_totals[$platform] = array(
                    'posts' => 0, 'reach' => 0, 'engagement' => 0
                );
            }

            // Initialize date if not exists
            if (!isset($daily_totals[$date])) {
                $daily_totals[$date] = array();
            }

            // Process metric
            $metric_name = $result->metric_name;
            $value = intval($result->total_value);

            switch ($metric_name) {
                case 'posts':
                case 'published':
                    $platform_totals[$platform]['posts'] += $value;
                    $processed['summary']['total_posts'] += $value;
                    if (!isset($daily_totals[$date]['posts'])) {
                        $daily_totals[$date]['posts'] = 0;
                    }
                    $daily_totals[$date]['posts'] += $value;
                    break;

                case 'reach':
                case 'impressions':
                    $platform_totals[$platform]['reach'] += $value;
                    $processed['summary']['total_reach'] += $value;
                    if (!isset($daily_totals[$date]['reach'])) {
                        $daily_totals[$date]['reach'] = 0;
                    }
                    $daily_totals[$date]['reach'] += $value;
                    break;

                case 'likes':
                case 'shares':
                case 'comments':
                case 'clicks':
                    $platform_totals[$platform]['engagement'] += $value;
                    $processed['summary']['total_engagement'] += $value;
                    if (!isset($daily_totals[$date]['engagement'])) {
                        $daily_totals[$date]['engagement'] = 0;
                    }
                    $daily_totals[$date]['engagement'] += $value;
                    break;
            }
        }

        $processed['platforms'] = $platform_totals;
        $processed['timeline'] = $daily_totals;

        // Calculate engagement rate
        if ($processed['summary']['total_reach'] > 0) {
            $processed['summary']['average_engagement_rate'] =
                ($processed['summary']['total_engagement'] / $processed['summary']['total_reach']) * 100;
        }

        return $processed;
    }

    /**
     * Group stats by platform
     */
    private function group_stats_by_platform($stats) {
        $grouped = array();
        
        foreach ($stats as $stat) {
            $platform = $stat->platform;
            if (!isset($grouped[$platform])) {
                $grouped[$platform] = array();
            }
            $grouped[$platform][$stat->metric_name] = intval($stat->value);
        }
        
        return $grouped;
    }

    /**
     * Get platform status
     */
    private function get_platform_status() {
        $platforms = $this->get_enabled_platforms();
        $status = array();
        
        foreach ($platforms as $platform) {
            $status[$platform] = array(
                'online' => true, // Would check actual API status
                'last_check' => current_time('mysql'),
                'error_count' => 0
            );
        }
        
        return $status;
    }

    /**
     * Get enabled platforms
     */
    private function get_enabled_platforms() {
        $platforms = array();
        $driver_dir = SMO_SOCIAL_PLUGIN_DIR . 'drivers/';
        
        if (is_dir($driver_dir)) {
            $files = glob($driver_dir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['slug'])) {
                    // Check if platform is enabled in settings
                    $enabled = get_option('smo_platform_' . $data['slug'] . '_enabled', false);
                    if ($enabled) {
                        $platforms[] = $data['slug'];
                    }
                }
            }
        }
        
        return $platforms;
    }

    /**
     * Get recent posts for a platform
     */
    private function get_recent_posts_for_platform($platform, $limit = 50) {
        $table_name = $this->wpdb->prefix . 'smo_analytics';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT post_id, post_date 
             FROM {$table_name} 
             WHERE platform = %s 
             ORDER BY post_date DESC 
             LIMIT %d",
            $platform, $limit
        ));
    }

    /**
     * Update metric for a specific post
     */
    private function update_metric($post_id, $platform, $metric_name, $value) {
        $table_name = $this->wpdb->prefix . 'smo_analytics';
        
        // Update existing record or insert new one
        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id FROM {$table_name} 
             WHERE post_id = %d AND platform = %s AND metric_name = %s",
            $post_id, $platform, $metric_name
        ));

        if ($existing) {
            $this->wpdb->update(
                $table_name,
                array('metric_value' => $value, 'updated_at' => current_time('mysql')),
                array('id' => $existing->id),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            $this->wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'platform' => $platform,
                    'post_date' => current_time('mysql'),
                    'data_type' => 'engagement',
                    'metric_name' => $metric_name,
                    'metric_value' => $value
                ),
                array('%d', '%s', '%s', '%s', '%s', '%d')
            );
        }
    }
}
