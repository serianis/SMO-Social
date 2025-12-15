<?php
/**
 * Posts Per Day Analytics Manager
 * 
 * Comprehensive analytics and tracking for social media posts per day
 *
 * @package SMO_Social
 * @subpackage Analytics
 * @since 1.0.0
 */

namespace SMO_Social\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Posts Per Day Analytics Manager
 */
class PostsPerDayManager {
    
    /**
     * Initialize analytics tracking
     */
    public function __construct() {
        // Add WordPress hooks only in WordPress mode
        if (function_exists('add_action')) {
            add_action('smo_post_published', array($this, 'track_published_post'), 10, 2);
            add_action('smo_post_failed', array($this, 'track_failed_post'), 10, 2);
            add_action('smo_post_updated', array($this, 'update_post_analytics'), 10, 2);
            add_action('smo_analytics_daily_cron', array($this, 'process_daily_analytics'));
            
            // Schedule daily analytics processing
            if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
                if (!wp_next_scheduled('smo_analytics_daily_cron')) {
                    wp_schedule_event(time(), 'daily', 'smo_analytics_daily_cron');
                }
            }
        }
    }
    
    /**
     * Track published post
     */
    public function track_published_post($post_id, $platform) {
        global $wpdb;
        
        // Get user ID - use WordPress function if available, otherwise use fallback
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 1;
        $today = date('Y-m-d');
        
        // Insert or update daily analytics record
        $table_name = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        // Use WordPress current_time if available, otherwise use date()
        $timestamp = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
        
        $wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'date' => $today,
                'platform' => $platform,
                'published_count' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
        
        // Update aggregate statistics
        $this->update_daily_aggregates($user_id, $today, $platform);
    }
    
    /**
     * Track failed post
     */
    public function track_failed_post($post_id, $platform) {
        global $wpdb;
        
        // Get user ID - use WordPress function if available, otherwise use fallback
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 1;
        $today = date('Y-m-d');
        
        $table_name = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        // Use WordPress current_time if available, otherwise use date()
        $timestamp = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
        
        $wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'date' => $today,
                'platform' => $platform,
                'failed_count' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Update post analytics with engagement data
     */
    public function update_post_analytics($post_id, $engagement_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        // Update today's record with engagement data
        $today = date('Y-m-d');
        
        // Get user ID - use WordPress functions if available, otherwise use fallback
        $user_id = null;
        if (function_exists('get_post_meta')) {
            $user_id = \get_post_meta($post_id, 'created_by', true);
        }
        if (!$user_id && function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
        }
        if (!$user_id) {
            $user_id = 1; // Default fallback
        }
        
        foreach ($engagement_data as $platform => $data) {
            $wpdb->update(
                $table_name,
                array(
                    'total_engagement' => $data['engagement'] ?? 0,
                    'total_reach' => $data['reach'] ?? 0,
                    'engagement_rate' => $data['engagement_rate'] ?? 0.0
                ),
                array(
                    'user_id' => $user_id,
                    'date' => $today,
                    'platform' => $platform
                ),
                array('%d', '%d', '%.2f'),
                array('%d', '%s', '%s')
            );
        }
    }
    
    /**
     * Update daily aggregates
     */
    private function update_daily_aggregates($user_id, $date, $platform) {
        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        // Get post statistics for the day
        $post_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table 
             WHERE created_by = %d 
             AND DATE(created_at) = %s 
             AND FIND_IN_SET(%s, platforms)",
            $user_id, $date, $platform
        ));
        
        $avg_length = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(LENGTH(content)) FROM $posts_table 
             WHERE created_by = %d 
             AND DATE(created_at) = %s 
             AND FIND_IN_SET(%s, platforms)",
            $user_id, $date, $platform
        ));
        
        // Update analytics record
        $wpdb->update(
            $analytics_table,
            array(
                'post_count' => $post_count,
                'avg_post_length' => round($avg_length)
            ),
            array(
                'user_id' => $user_id,
                'date' => $date,
                'platform' => $platform
            ),
            array('%d', '%d'),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Get posts per day statistics for dashboard
     */
    public function get_dashboard_stats($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 1;
        }
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        // Get today's stats
        $today_stats = $this->get_today_stats($user_id);
        
        // Get yesterday's stats
        $yesterday_stats = $this->get_yesterday_stats($user_id);
        
        // Get 7-day average
        $week_average = $this->get_week_average($user_id);
        
        // Get 30-day average
        $month_average = $this->get_month_average($user_id);
        
        // Get peak posting day
        $peak_day = $this->get_peak_posting_day($user_id);
        
        // Get platform distribution today
        $platform_today = $this->get_platform_distribution_today($user_id);
        
        // Get trends data
        $trends = $this->get_trends_data($user_id);
        
        return array(
            'today' => $today_stats,
            'yesterday' => $yesterday_stats,
            'week_average' => $week_average,
            'month_average' => $month_average,
            'peak_day' => $peak_day,
            'platform_today' => $platform_today,
            'trends' => $trends,
            'insights' => $this->generate_insights($today_stats, $yesterday_stats, $week_average)
        );
    }
    
    /**
     * Get today's posting statistics
     */
    private function get_today_stats($user_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        $today = date('Y-m-d');
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COALESCE(SUM(post_count), 0) as total_posts,
                COALESCE(SUM(published_count), 0) as published_posts,
                COALESCE(SUM(failed_count), 0) as failed_posts,
                COALESCE(AVG(engagement_rate), 0) as avg_engagement_rate,
                COALESCE(SUM(total_engagement), 0) as total_engagement,
                COALESCE(SUM(total_reach), 0) as total_reach
            FROM $analytics_table 
            WHERE user_id = %d AND date = %s",
            $user_id, $today
        ), ARRAY_A);
        
        return array(
            'total_posts' => intval($stats['total_posts'] ?? 0),
            'published_posts' => intval($stats['published_posts'] ?? 0),
            'failed_posts' => intval($stats['failed_posts'] ?? 0),
            'success_rate' => $stats['total_posts'] > 0 ? round(($stats['published_posts'] / $stats['total_posts']) * 100, 1) : 0,
            'avg_engagement_rate' => round($stats['avg_engagement_rate'] ?? 0, 2),
            'total_engagement' => intval($stats['total_engagement'] ?? 0),
            'total_reach' => intval($stats['total_reach'] ?? 0)
        );
    }
    
    /**
     * Get yesterday's posting statistics
     */
    private function get_yesterday_stats($user_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COALESCE(SUM(post_count), 0) as total_posts,
                COALESCE(SUM(published_count), 0) as published_posts,
                COALESCE(AVG(engagement_rate), 0) as avg_engagement_rate
            FROM $analytics_table 
            WHERE user_id = %d AND date = %s",
            $user_id, $yesterday
        ), ARRAY_A);
        
        return array(
            'total_posts' => intval($stats['total_posts'] ?? 0),
            'published_posts' => intval($stats['published_posts'] ?? 0),
            'avg_engagement_rate' => round($stats['avg_engagement_rate'] ?? 0, 2)
        );
    }
    
    /**
     * Get 7-day average statistics
     */
    private function get_week_average($user_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COALESCE(AVG(daily_posts), 0) as avg_posts_per_day,
                COALESCE(AVG(daily_published), 0) as avg_published_per_day,
                COALESCE(AVG(avg_engagement_rate), 0) as avg_engagement_rate
            FROM (
                SELECT 
                    SUM(post_count) as daily_posts,
                    SUM(published_count) as daily_published,
                    AVG(engagement_rate) as avg_engagement_rate
                FROM $analytics_table 
                WHERE user_id = %d AND date >= %s
                GROUP BY date
            ) as daily_stats",
            $user_id, $week_ago
        ), ARRAY_A);
        
        return array(
            'avg_posts_per_day' => round($stats['avg_posts_per_day'] ?? 0, 1),
            'avg_published_per_day' => round($stats['avg_published_per_day'] ?? 0, 1),
            'avg_engagement_rate' => round($stats['avg_engagement_rate'] ?? 0, 2)
        );
    }
    
    /**
     * Get 30-day average statistics
     */
    private function get_month_average($user_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        $month_ago = date('Y-m-d', strtotime('-30 days'));
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COALESCE(AVG(daily_posts), 0) as avg_posts_per_day,
                COALESCE(AVG(daily_published), 0) as avg_published_per_day,
                COALESCE(AVG(avg_engagement_rate), 0) as avg_engagement_rate
            FROM (
                SELECT 
                    SUM(post_count) as daily_posts,
                    SUM(published_count) as daily_published,
                    AVG(engagement_rate) as avg_engagement_rate
                FROM $analytics_table 
                WHERE user_id = %d AND date >= %s
                GROUP BY date
            ) as daily_stats",
            $user_id, $month_ago
        ), ARRAY_A);
        
        return array(
            'avg_posts_per_day' => round($stats['avg_posts_per_day'] ?? 0, 1),
            'avg_published_per_day' => round($stats['avg_published_per_day'] ?? 0, 1),
            'avg_engagement_rate' => round($stats['avg_engagement_rate'] ?? 0, 2)
        );
    }
    
    /**
     * Get peak posting day
     */
    private function get_peak_posting_day($user_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        $month_ago = date('Y-m-d', strtotime('-30 days'));
        
        $peak_day = $wpdb->get_row($wpdb->prepare(
            "SELECT date, SUM(post_count) as total_posts
            FROM $analytics_table 
            WHERE user_id = %d AND date >= %s
            GROUP BY date
            ORDER BY total_posts DESC, date DESC
            LIMIT 1",
            $user_id, $month_ago
        ), ARRAY_A);
        
        return $peak_day ? array(
            'date' => $peak_day['date'],
            'total_posts' => intval($peak_day['total_posts']),
            'formatted_date' => date('F j, Y', strtotime($peak_day['date']))
        ) : null;
    }
    
    /**
     * Get platform distribution for today
     */
    private function get_platform_distribution_today($user_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        $today = date('Y-m-d');
        
        $platforms = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                platform,
                post_count,
                published_count,
                engagement_rate,
                total_engagement
            FROM $analytics_table 
            WHERE user_id = %d AND date = %s
            ORDER BY post_count DESC",
            $user_id, $today
        ), ARRAY_A);
        
        return array_map(function($platform) {
            return array(
                'platform' => $platform['platform'],
                'posts' => intval($platform['post_count']),
                'published' => intval($platform['published_count']),
                'engagement_rate' => round($platform['engagement_rate'] ?? 0, 2),
                'total_engagement' => intval($platform['total_engagement'] ?? 0)
            );
        }, $platforms);
    }
    
    /**
     * Get trends data for charts
     */
    private function get_trends_data($user_id) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        $month_ago = date('Y-m-d', strtotime('-30 days'));
        
        $trends = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                date,
                SUM(post_count) as total_posts,
                SUM(published_count) as published_posts,
                AVG(engagement_rate) as avg_engagement_rate
            FROM $analytics_table 
            WHERE user_id = %d AND date >= %s
            GROUP BY date
            ORDER BY date ASC",
            $user_id, $month_ago
        ), ARRAY_A);
        
        return array_map(function($day) {
            return array(
                'date' => $day['date'],
                'posts' => intval($day['total_posts']),
                'published' => intval($day['published_posts']),
                'engagement_rate' => round($day['avg_engagement_rate'] ?? 0, 2)
            );
        }, $trends);
    }
    
    /**
     * Generate insights based on statistics
     */
    private function generate_insights($today, $yesterday, $week_avg) {
        $insights = array();
        
        // Posts per day insight
        if ($today['total_posts'] > $week_avg['avg_posts_per_day'] * 1.5) {
            $insights[] = array(
                'type' => 'success',
                'title' => __('High Activity Day', 'smo-social'),
                'message' => sprintf(__('You\'ve posted %d times today, which is above your 7-day average of %.1f.', 'smo-social'), 
                    $today['total_posts'], $week_avg['avg_posts_per_day'])
            );
        } elseif ($today['total_posts'] < $week_avg['avg_posts_per_day'] * 0.5) {
            $insights[] = array(
                'type' => 'info',
                'title' => __('Low Activity Day', 'smo-social'),
                'message' => sprintf(__('You\'ve posted %d times today, below your 7-day average. Consider scheduling more content.', 'smo-social'), 
                    $today['total_posts'])
            );
        }
        
        // Success rate insight
        if ($today['success_rate'] < 80 && $today['total_posts'] > 0) {
            $insights[] = array(
                'type' => 'warning',
                'title' => __('Publishing Issues', 'smo-social'),
                'message' => sprintf(__('Your success rate is %.1f%% today. Check platform connections for issues.', 'smo-social'), 
                    $today['success_rate'])
            );
        }
        
        // Engagement insight
        if ($today['avg_engagement_rate'] > 5.0) {
            $insights[] = array(
                'type' => 'success',
                'title' => __('Great Engagement', 'smo-social'),
                'message' => sprintf(__('Your average engagement rate is %.1f%%, which is excellent!', 'smo-social'), 
                    $today['avg_engagement_rate'])
            );
        }
        
        return $insights;
    }
    
    /**
     * Process daily analytics cron job
     */
    public function process_daily_analytics() {
        global $wpdb;
        
        // Clean up old analytics data (older than 1 year)
        $cleanup_date = date('Y-m-d', strtotime('-1 year'));
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $analytics_table WHERE date < %s",
            $cleanup_date
        ));
        
        // Recalculate aggregates for yesterday
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $users = $wpdb->get_col("SELECT DISTINCT user_id FROM $analytics_table WHERE date = '$yesterday'");
        
        foreach ($users as $user_id) {
            $this->update_daily_aggregates($user_id, $yesterday, 'all');
        }
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics_data($user_id, $start_date, $end_date, $format = 'csv') {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                date,
                platform,
                post_count,
                published_count,
                failed_count,
                total_engagement,
                total_reach,
                avg_engagement_rate,
                engagement_rate
            FROM $analytics_table 
            WHERE user_id = %d AND date BETWEEN %s AND %s
            ORDER BY date DESC, platform ASC",
            $user_id, $start_date, $end_date
        ), ARRAY_A);
        
        if ($format === 'csv') {
            return $this->format_csv_data($data);
        }
        
        return $data;
    }
    
    /**
     * Format data as CSV
     */
    private function format_csv_data($data) {
        $output = fopen('php://temp', 'r+');
        
        // Add headers
        fputcsv($output, array(
            'Date', 'Platform', 'Total Posts', 'Published', 'Failed', 
            'Total Engagement', 'Total Reach', 'Avg Engagement Rate', 'Engagement Rate'
        ));
        
        foreach ($data as $row) {
            fputcsv($output, array(
                $row['date'],
                $row['platform'],
                $row['post_count'],
                $row['published_count'],
                $row['failed_count'],
                $row['total_engagement'],
                $row['total_reach'],
                $row['avg_engagement_rate'],
                $row['engagement_rate']
            ));
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
