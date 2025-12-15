<?php
/**
 * Database Performance Optimizations for SMO Social Plugin
 * 
 * This file contains optimized database queries and functions to improve
 * the performance of the SMO Social plugin by reducing query complexity
 * and implementing efficient data retrieval strategies.
 * 
 * @package SMO_Social
 * @subpackage Performance_Optimizations
 * @since 1.0.0
 */

namespace SMO_Social\Performance\Database;

// Ensure direct file access is blocked
if (!defined('ABSPATH')) {
    wp_die(__('Access denied', 'smo-social'));
}

/**
 * Database Optimizations Class
 * 
 * Contains optimized database queries and functions for improved performance
 */
class DatabaseOptimizations {
    
    /**
     * Get dashboard statistics using a single optimized query with intelligent caching
     *
     * @param bool $use_cache Whether to use cached results
     * @return array Dashboard statistics
     */
    public static function get_dashboard_stats_optimized($use_cache = true) {
        $current_user_id = get_current_user_id();
        $cache_key = 'smo_dashboard_stats_' . $current_user_id;
        $cache_ttl = 5 * 60; // 5 minutes cache

        // Check cache first
        if ($use_cache) {
            $cached_stats = get_transient($cache_key);
            if ($cached_stats !== false) {
                return $cached_stats;
            }
        }

        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $queue_table = $wpdb->prefix . 'smo_queue';

        // Single query with subqueries instead of multiple separate queries
        $query = "
            SELECT
                (SELECT COUNT(*) FROM $posts_table) as total_posts,
                (SELECT COUNT(*) FROM $posts_table WHERE status = 'scheduled') as scheduled_posts,
                (SELECT COUNT(*) FROM $posts_table WHERE status = 'published' AND DATE(created_at) = CURDATE()) as published_today,
                (SELECT COUNT(*) FROM $posts_table WHERE status = 'failed') as failed_posts,
                (SELECT COUNT(*) FROM $queue_table WHERE status = 'pending') as pending_queue,
                (SELECT COUNT(*) FROM $posts_table WHERE status = 'published' AND created_by = %d) as user_posts_today,
                (SELECT COUNT(*) FROM $queue_table WHERE status = 'failed') as failed_queue,
                (SELECT COUNT(*) FROM $posts_table WHERE status = 'draft') as draft_posts,
                (SELECT COUNT(*) FROM $queue_table WHERE status = 'processing') as processing_queue
        ";

        $results = $wpdb->get_row($wpdb->prepare($query, $current_user_id));

        $stats = array(
            'total_posts' => (int) $results->total_posts,
            'scheduled_posts' => (int) $results->scheduled_posts,
            'published_today' => (int) $results->published_today,
            'failed_posts' => (int) $results->failed_posts,
            'pending_queue' => (int) $results->pending_queue,
            'user_posts_today' => (int) $results->user_posts_today,
            'failed_queue' => (int) $results->failed_queue,
            'draft_posts' => (int) $results->draft_posts,
            'processing_queue' => (int) $results->processing_queue,
            'cached_at' => time(),
            'cache_expires' => time() + $cache_ttl
        );

        // Cache the results
        if ($use_cache) {
            set_transient($cache_key, $stats, $cache_ttl);
        }

        return $stats;
    }
    
    /**
     * Get optimized platform status with batch token queries
     * 
     * @param array $enabled_platforms List of enabled platform slugs
     * @return array Platform status information
     */
    public static function get_platform_status_optimized($enabled_platforms = array()) {
        if (empty($enabled_platforms)) {
            $enabled_platforms = get_option('smo_social_enabled_platforms', array());
        }
        
        if (!is_countable($enabled_platforms)) {
            $enabled_platforms = array();
        }
        
        if (empty($enabled_platforms)) {
            return array();
        }
        
        global $wpdb;
        $tokens_table = $wpdb->prefix . 'smo_platform_tokens';
        
        // Batch fetch platform token data to reduce number of queries
        $platforms_str = "'" . implode("','", $enabled_platforms) . "'";
        $token_data = $wpdb->get_results("
            SELECT platform_slug, COUNT(*) as token_count, MAX(updated_at) as last_updated
            FROM $tokens_table 
            WHERE platform_slug IN ($platforms_str) 
            GROUP BY platform_slug
        ", ARRAY_A);
        
        $token_counts = array_column($token_data, 'token_count', 'platform_slug');
        $last_updated = array_column($token_data, 'last_updated', 'platform_slug');
        
        // Get platform manager instance
        $platform_manager = new \SMO_Social\Platforms\Manager();
        $platforms_status = array();
        
        foreach ($enabled_platforms as $slug) {
            $platform = $platform_manager->get_platform($slug);
            if ($platform) {
                $platforms_status[$slug] = array(
                    'name' => $platform->get_name(),
                    'connected' => isset($token_counts[$slug]) && $token_counts[$slug] > 0,
                    'token_count' => $token_counts[$slug] ?? 0,
                    'last_updated' => $last_updated[$slug] ?? null,
                    'health' => $platform->health_check(),
                    'features' => $platform->get_features()
                );
            }
        }
        
        return $platforms_status;
    }
    
    /**
     * Get recent activity with optimized query
     * 
     * @param int $limit Number of activities to retrieve
     * @return array Recent activity data
     */
    public static function get_recent_activity_optimized($limit = 10) {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'smo_activity_logs';
        
        // Optimized query with proper indexing and limits
        $activities = $wpdb->get_results($wpdb->prepare("
            SELECT 
                action, 
                details, 
                created_at,
                user_id,
                resource_type,
                resource_id
            FROM $activity_table 
            ORDER BY created_at DESC 
            LIMIT %d
        ", $limit), ARRAY_A);
        
        return $activities;
    }
    
    /**
     * Get queue statistics with platform breakdown
     * 
     * @return array Queue statistics
     */
    public static function get_queue_stats_optimized() {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'smo_queue';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_pending,
                COUNT(CASE WHEN platform_slug = 'twitter' THEN 1 END) as twitter_pending,
                COUNT(CASE WHEN platform_slug = 'facebook' THEN 1 END) as facebook_pending,
                COUNT(CASE WHEN platform_slug = 'instagram' THEN 1 END) as instagram_pending,
                COUNT(CASE WHEN platform_slug = 'linkedin' THEN 1 END) as linkedin_pending,
                COUNT(CASE WHEN platform_slug = 'mastodon' THEN 1 END) as mastodon_pending,
                AVG(attempts) as avg_attempts,
                COUNT(CASE WHEN attempts >= max_attempts THEN 1 END) as maxed_out_items
            FROM $queue_table 
            WHERE status = 'pending'
        ", ARRAY_A);
        
        return array(
            'total_pending' => (int) $stats->total_pending,
            'platform_breakdown' => array(
                'twitter' => (int) $stats->twitter_pending,
                'facebook' => (int) $stats->facebook_pending,
                'instagram' => (int) $stats->instagram_pending,
                'linkedin' => (int) $stats->linkedin_pending,
                'mastodon' => (int) $stats->mastodon_pending
            ),
            'avg_attempts' => (float) $stats->avg_attempts,
            'maxed_out_items' => (int) $stats->maxed_out_items
        );
    }
    
    /**
     * Get post analytics with optimized queries
     * 
     * @param int $limit Number of posts to analyze
     * @param string $start_date Start date for analysis
     * @param string $end_date End date for analysis
     * @return array Analytics data
     */
    public static function get_post_analytics_optimized($limit = 20, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $analytics_table = $wpdb->prefix . 'smo_analytics';
        
        if (!$start_date) {
            $start_date = date('Y-m-01 00:00:00'); // First day of current month
        }
        
        if (!$end_date) {
            $end_date = date('Y-m-t 23:59:59'); // Last day of current month
        }
        
        // Get top performing posts
        $posts_query = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.id,
                p.title,
                p.content,
                p.status,
                p.scheduled_time,
                p.created_at,
                COUNT(a.id) as analytics_count,
                GROUP_CONCAT(DISTINCT a.platform_slug) as platforms
            FROM $posts_table p
            LEFT JOIN $analytics_table a ON p.id = a.scheduled_post_id
            WHERE p.created_at BETWEEN %s AND %s
            AND p.status IN ('published', 'scheduled')
            GROUP BY p.id
            ORDER BY analytics_count DESC, p.created_at DESC
            LIMIT %d
        ", $start_date, $end_date, $limit), ARRAY_A);
        
        return $posts_query;
    }
    
    /**
     * Get user statistics with optimized queries
     * 
     * @param int $user_id User ID to get stats for
     * @return array User statistics
     */
    public static function get_user_stats_optimized($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $queue_table = $wpdb->prefix . 'smo_queue';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(CASE WHEN p.status = 'published' THEN 1 END) as published_posts,
                COUNT(CASE WHEN p.status = 'scheduled' THEN 1 END) as scheduled_posts,
                COUNT(CASE WHEN p.status = 'failed' THEN 1 END) as failed_posts,
                COUNT(CASE WHEN p.status = 'draft' THEN 1 END) as draft_posts,
                COUNT(q.id) as pending_queue_items,
                AVG(CASE WHEN p.status = 'published' THEN 1 END) as success_rate
            FROM $posts_table p
            LEFT JOIN $queue_table q ON p.id = q.scheduled_post_id AND q.status = 'pending'
            WHERE p.created_by = %d
        ", $user_id), ARRAY_A);
        
        return array(
            'published_posts' => (int) $stats->published_posts,
            'scheduled_posts' => (int) $stats->scheduled_posts,
            'failed_posts' => (int) $stats->failed_posts,
            'draft_posts' => (int) $stats->draft_posts,
            'pending_queue_items' => (int) $stats->pending_queue_items,
            'success_rate' => $stats->success_rate > 0 ? round($stats->success_rate * 100, 1) : 0
        );
    }
    
    /**
     * Batch insert multiple records efficiently
     * 
     * @param string $table Table name
     * @param array $data Array of data to insert
     * @param array $format Format array for placeholders
     * @return bool Success status
     */
    public static function batch_insert_optimized($table, $data, $format = array()) {
        global $wpdb;
        
        if (empty($data)) {
            return false;
        }
        
        // Prepare column names from first data row
        $columns = array_keys($data[0]);
        $column_list = '`' . implode('`, `', $columns) . '`';
        
        // Prepare values for batch insert
        $values = array();
        $place_holders = array();
        
        foreach ($data as $row) {
            $row_values = array();
            $row_placeholders = array();
            
            foreach ($columns as $column) {
                $row_values[] = $row[$column];
                $row_placeholders[] = is_numeric($row[$column]) ? '%d' : '%s';
            }
            
            $values = array_merge($values, $row_values);
            $place_holders[] = '(' . implode(', ', $row_placeholders) . ')';
        }
        
        // Build and execute query
        $query = "INSERT INTO `$table` ($column_list) VALUES " . implode(', ', $place_holders);
        
        if (!empty($format)) {
            return $wpdb->query($wpdb->prepare($query, $values));
        } else {
            return $wpdb->query($query);
        }
    }
    
    /**
     * Get platform token counts efficiently
     * 
     * @param array $platforms Array of platform slugs
     * @return array Token counts per platform
     */
    public static function get_platform_token_counts($platforms = array()) {
        global $wpdb;
        $tokens_table = $wpdb->prefix . 'smo_platform_tokens';
        
        if (empty($platforms)) {
            return array();
        }
        
        $platforms_str = "'" . implode("','", $platforms) . "'";
        
        $results = $wpdb->get_results("
            SELECT 
                platform_slug,
                COUNT(*) as token_count,
                COUNT(CASE WHEN token_expires > NOW() THEN 1 END) as valid_tokens,
                COUNT(CASE WHEN token_expires <= NOW() THEN 1 END) as expired_tokens
            FROM $tokens_table 
            WHERE platform_slug IN ($platforms_str)
            GROUP BY platform_slug
        ", ARRAY_A);
        
        $token_counts = array();
        foreach ($results as $result) {
            $token_counts[$result->platform_slug] = array(
                'total' => (int) $result->token_count,
                'valid' => (int) $result->valid_tokens,
                'expired' => (int) $result->expired_tokens
            );
        }
        
        return $token_counts;
    }
    
    /**
     * Clean up old records efficiently
     * 
     * @param string $table Table name
     * @param string $date_column Date column to check
     * @param int $days_old Delete records older than this many days
     * @param int $batch_size Number of records to delete per batch
     * @return int Total records deleted
     */
    public static function cleanup_old_records($table, $date_column = 'created_at', $days_old = 90, $batch_size = 1000) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $table;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        $total_deleted = 0;
        
        do {
            // Get IDs to delete in batches
            $ids_to_delete = $wpdb->get_col($wpdb->prepare("
                SELECT id FROM $table_name 
                WHERE $date_column < %s 
                LIMIT %d
            ", $cutoff_date, $batch_size));
            
            if (!empty($ids_to_delete)) {
                $ids_str = implode(',', $ids_to_delete);
                $deleted = $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_str)");
                $total_deleted += $deleted;
                
                // Small delay to avoid overwhelming the database
                usleep(100000); // 0.1 second
            }
            
        } while (count($ids_to_delete) == $batch_size);
        
        return $total_deleted;
    }
    
    /**
     * Get posts with lazy loading and pagination for large datasets
     *
     * @param int $page Page number (starting from 1)
     * @param int $per_page Number of posts per page
     * @param array $filters Additional filters
     * @return array Posts data with pagination info
     */
    public static function get_posts_lazy_loading($page = 1, $per_page = 20, $filters = array()) {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();

        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['platform'])) {
            $where_conditions[] = 'platform_slug = %s';
            $where_values[] = $filters['platform'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }

        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'created_by = %d';
            $where_values[] = $filters['user_id'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count for pagination
        $total_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $posts_table $where_clause
        ", $where_values));

        // Get posts with optimized query
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT
                id,
                title,
                content,
                status,
                platform_slug,
                scheduled_time,
                created_at,
                created_by,
                error_message
            FROM $posts_table
            $where_clause
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($per_page, $offset))), ARRAY_A);

        return array(
            'posts' => $posts,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_count' => (int) $total_count,
                'total_pages' => ceil($total_count / $per_page),
                'has_next' => $page < ceil($total_count / $per_page),
                'has_prev' => $page > 1
            ),
            'filters_applied' => $filters
        );
    }

    /**
     * Get analytics data with lazy loading for large datasets
     *
     * @param int $page Page number
     * @param int $per_page Items per page
     * @param array $filters Filters
     * @return array Analytics data with pagination
     */
    public static function get_analytics_lazy_loading($page = 1, $per_page = 50, $filters = array()) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'smo_analytics';
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where_conditions = array('1=1'); // Always true for easier appending
        $where_values = array();

        if (!empty($filters['platform'])) {
            $where_conditions[] = 'a.platform_slug = %s';
            $where_values[] = $filters['platform'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'a.created_at >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'a.created_at <= %s';
            $where_values[] = $filters['date_to'];
        }

        if (!empty($filters['post_id'])) {
            $where_conditions[] = 'a.scheduled_post_id = %d';
            $where_values[] = $filters['post_id'];
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        // Get total count
        $total_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $analytics_table a $where_clause
        ", $where_values));

        // Get analytics data with post info
        $analytics = $wpdb->get_results($wpdb->prepare("
            SELECT
                a.id,
                a.scheduled_post_id,
                a.platform_slug,
                a.engagement_metrics,
                a.reach_metrics,
                a.created_at,
                p.title as post_title,
                p.status as post_status
            FROM $analytics_table a
            LEFT JOIN $posts_table p ON a.scheduled_post_id = p.id
            $where_clause
            ORDER BY a.created_at DESC
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($per_page, $offset))), ARRAY_A);

        return array(
            'analytics' => $analytics,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_count' => (int) $total_count,
                'total_pages' => ceil($total_count / $per_page),
                'has_next' => $page < ceil($total_count / $per_page),
                'has_prev' => $page > 1
            ),
            'filters_applied' => $filters
        );
    }

    /**
     * Get database performance metrics
     *
     * @return array Performance metrics
     */
    public static function get_performance_metrics() {
        global $wpdb;

        $metrics = array();

        // Get table sizes and row counts
        $tables = array(
            'smo_scheduled_posts',
            'smo_queue',
            'smo_platform_tokens',
            'smo_activity_logs',
            'smo_analytics'
        );

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $data_size = $wpdb->get_var("SHOW TABLE STATUS LIKE '$table_name'");

            $metrics[$table] = array(
                'row_count' => (int) $row_count,
                'size_mb' => round(strlen($data_size) / (1024 * 1024), 2) // Rough estimate
            );
        }

        return $metrics;
    }
}
