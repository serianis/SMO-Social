<?php
namespace SMO_Social\Scheduling;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_ActionScheduler - Asynchronous Processing Manager
 * 
 * Manages background processing of social media posts using WordPress Action Scheduler.
 * Ensures reliable, asynchronous posting without blocking user interface.
 */
class ActionScheduler {

    private $platform_manager;
    private $driver_engine;
    private $queue_manager;
    private $retry_attempts = 3;
    private $retry_delays = array(300, 900, 1800); // 5min, 15min, 30min

    public function __construct() {
        $this->platform_manager = new \SMO_Social\Platforms\Manager();
        $this->driver_engine = new \SMO_Social\Core\DriverEngine(array(), array(), 0);
        $this->queue_manager = new \SMO_Social\Scheduling\QueueManager();
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook for when posts are published
        add_action('transition_post_status', array($this, 'handle_post_transition'), 10, 3);
        
        // Hook for scheduled actions
        add_action('smo_execute_social_share', array($this, 'process_social_share'), 10, 3);
        
        // Hook for failed action retry
        add_action('smo_retry_social_share', array($this, 'retry_social_share'), 10, 3);
        
        // Cleanup hook
        add_action('smo_cleanup_old_actions', array($this, 'cleanup_old_actions'));
        
        // Schedule cleanup daily
        if (!wp_next_scheduled('smo_cleanup_old_actions')) {
            wp_schedule_event(time(), 'daily', 'smo_cleanup_old_actions');
        }
    }

    /**
     * Handle post status transitions
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status  
     * @param WP_Post $post Post object
     */
    public function handle_post_transition($new_status, $old_status, $post) {
        // Only process when post becomes published
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Skip if post type not supported
        if (!in_array($post->post_type, array('post', 'page', 'product'))) {
            return;
        }

        // Check if user wants to skip social sharing
        $skip_social = get_post_meta($post->ID, '_smo_skip_social', true);
        if ($skip_social) {
            $this->log_activity('skipped', $post->ID, 'User opted out of social sharing');
            return;
        }

        // Get enabled platforms for this post
        $enabled_platforms = $this->get_enabled_platforms_for_post($post->ID);
        
        if (empty($enabled_platforms)) {
            $this->log_activity('no_platforms', $post->ID, 'No platforms enabled for this post');
            return;
        }

        // Schedule social shares
        $this->schedule_social_shares($post->ID, $enabled_platforms);
    }

    /**
     * Schedule social shares for a post
     * 
     * @param int   $post_id   WordPress post ID
     * @param array $platforms Array of platform slugs
     */
    private function schedule_social_shares($post_id, $platforms) {
        foreach ($platforms as $platform) {
            // Check if action already exists
            if ($this->action_exists($platform, $post_id)) {
                continue;
            }

            // Calculate optimal posting time
            $delay = $this->calculate_posting_delay($platform);
            
            // Schedule the action
            as_schedule_single_action(
                time() + $delay,
                'smo_execute_social_share',
                array(
                    'post_id' => $post_id,
                    'platform' => $platform,
                    'attempt' => 1
                ),
                'smo_social_group'
            );

            $this->log_activity('scheduled', $post_id, "Scheduled for {$platform} in {$delay} seconds");
        }

        // Update post meta to track scheduled shares
        update_post_meta($post_id, '_smo_scheduled_platforms', $platforms);
        update_post_meta($post_id, '_smo_scheduled_at', current_time('mysql'));
    }

    /**
     * Process a scheduled social share
     * 
     * @param int    $post_id   WordPress post ID
     * @param string $platform  Platform slug
     * @param int    $attempt   Attempt number
     */
    public function process_social_share($post_id, $platform, $attempt = 1) {
        try {
            // Get platform configuration
            $platform_obj = $this->platform_manager->get_platform($platform);
            if (!$platform_obj) {
                throw new \Exception("Platform configuration not found: {$platform}");
            }

            // Load platform credentials
            $credentials = $this->get_platform_credentials($platform);
            if (!$credentials) {
                throw new \Exception("No credentials found for platform: {$platform}");
            }

            // Get driver configuration
            $driver_config = $this->get_driver_config($platform);
            if (!$driver_config) {
                throw new \Exception("Driver configuration not found: {$platform}");
            }

            // Create driver engine instance
            $driver_engine = new \SMO_Social\Core\DriverEngine($driver_config, $credentials, $post_id);

            // Execute the social share
            $result = $driver_engine->publish();

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            // Handle success
            if ($result['success']) {
                $this->handle_share_success($post_id, $platform, $result);
            } else {
                throw new \Exception($result['error'] ?: 'Unknown error occurred');
            }

        } catch (\Exception $e) {
            $this->handle_share_failure($post_id, $platform, $e->getMessage(), $attempt);
        }
    }

    /**
     * Handle successful social share
     * 
     * @param int    $post_id   WordPress post ID
     * @param string $platform  Platform slug
     * @param array  $result    Share result
     */
    private function handle_share_success($post_id, $platform, $result) {
        // Update post meta
        update_post_meta($post_id, "_smo_{$platform}_status", 'published');
        update_post_meta($post_id, "_smo_{$platform}_published_at", current_time('mysql'));
        update_post_meta($post_id, "_smo_{$platform}_post_id", $result['post_id'] ?? '');

        // Update platform statistics
        $this->update_platform_stats($platform, true);

        // Log success
        $this->log_activity('success', $post_id, "Successfully published to {$platform}");
        
        // Send notification if configured
        $this->send_success_notification($post_id, $platform, $result);
    }

    /**
     * Handle failed social share
     * 
     * @param int    $post_id   WordPress post ID
     * @param string $platform  Platform slug
     * @param string $error     Error message
     * @param int    $attempt   Attempt number
     */
    private function handle_share_failure($post_id, $platform, $error, $attempt) {
        // Update post meta with failure info
        update_post_meta($post_id, "_smo_{$platform}_status", 'failed');
        update_post_meta($post_id, "_smo_{$platform}_failed_at", current_time('mysql'));
        update_post_meta($post_id, "_smo_{$platform}_error", $error);
        update_post_meta($post_id, "_smo_{$platform}_attempt", $attempt);

        // Update platform statistics
        $this->update_platform_stats($platform, false);

        // Log failure
        $this->log_activity('failed', $post_id, "Failed to publish to {$platform}: {$error}");

        // Retry logic
        if ($attempt < $this->retry_attempts) {
            $retry_delay = $this->retry_delays[$attempt - 1] ?? 1800;
            
            as_schedule_single_action(
                time() + $retry_delay,
                'smo_retry_social_share',
                array(
                    'post_id' => $post_id,
                    'platform' => $platform,
                    'attempt' => $attempt + 1,
                    'error' => $error
                ),
                'smo_social_retry_group'
            );

            $this->log_activity('retry_scheduled', $post_id, "Retry {$attempt} scheduled for {$platform} in {$retry_delay} seconds");
        } else {
            // Max retries reached
            update_post_meta($post_id, "_smo_{$platform}_status", 'max_retries_reached');
            $this->log_activity('max_retries', $post_id, "Max retries reached for {$platform}");
            
            // Send failure notification
            $this->send_failure_notification($post_id, $platform, $error);
        }
    }

    /**
     * Retry a failed social share
     * 
     * @param int    $post_id   WordPress post ID
     * @param string $platform  Platform slug
     * @param int    $attempt   Attempt number
     * @param string $error     Previous error message
     */
    public function retry_social_share($post_id, $platform, $attempt, $error = '') {
        $this->log_activity('retry_start', $post_id, "Starting retry {$attempt} for {$platform}");
        
        // Add error context to metadata
        if ($error) {
            update_post_meta($post_id, "_smo_{$platform}_last_error", $error);
        }

        // Process the share
        $this->process_social_share($post_id, $platform, $attempt);
    }

    /**
     * Calculate optimal posting delay
     * 
     * @param string $platform Platform slug
     * @return int Delay in seconds
     */
    private function calculate_posting_delay($platform) {
        // Get platform configuration
        $driver_config = $this->get_driver_config($platform);
        if (!$driver_config) {
            return 300; // Default 5 minutes
        }

        // Use platform's optimal posting times if available
        if (isset($driver_config['optimal_posting_times'])) {
            $current_time = current_time('H:i');
            $optimal_times = $driver_config['optimal_posting_times'];
            
            // Find next optimal time
            foreach ($optimal_times as $optimal_time) {
                if ($this->is_time_in_future($optimal_time)) {
                    $delay = $this->calculate_time_difference($current_time, $optimal_time);
                    return max($delay, 60); // Minimum 1 minute delay
                }
            }
            
            // If no future time today, schedule for first time tomorrow
            $first_time = $optimal_times[0];
            $delay = $this->calculate_time_difference($current_time, $first_time, true);
            return $delay;
        }

        // Default staggered delays to prevent rate limiting
        $base_delay = 300; // 5 minutes
        
        // Memory optimization: Get platform index without loading platform objects
        $enabled_platforms = $this->platform_manager->get_enabled_platform_slugs();
        $platform_index = array_search($platform, $enabled_platforms);
        
        return $base_delay + ($platform_index * 60); // Add 1 minute per platform
    }

    /**
     * Check if a time is in the future
     * 
     * @param string $time Time in HH:MM format
     * @return bool
     */
    private function is_time_in_future($time) {
        list($hours, $minutes) = explode(':', $time);
        $target_time = mktime($hours, $minutes, 0, date('n'), date('j'), date('Y'));
        return $target_time > current_time('timestamp');
    }

    /**
     * Calculate time difference between two times
     * 
     * @param string $current_time Current time
     * @param string $target_time  Target time
     * @param bool   $next_day     If target is next day
     * @return int Difference in seconds
     */
    private function calculate_time_difference($current_time, $target_time, $next_day = false) {
        list($current_hour, $current_min) = explode(':', $current_time);
        list($target_hour, $target_min) = explode(':', $target_time);
        
        $current_timestamp = current_time('timestamp');
        $target_timestamp = mktime($target_hour, $target_min, 0, 
                                 date('n'), 
                                 $next_day ? date('j') + 1 : date('j'), 
                                 date('Y'));
        
        return $target_timestamp - $current_timestamp;
    }

    /**
     * Check if action already exists
     * 
     * @param string $platform Platform slug
     * @param int    $post_id   WordPress post ID
     * @return bool
     */
    private function action_exists($platform, $post_id) {
        $scheduled_actions = as_get_scheduled_actions(array(
            'hook' => 'smo_execute_social_share',
            'args' => array('platform' => $platform, 'post_id' => $post_id),
            'status' => 'pending'
        ));
        
        return !empty($scheduled_actions);
    }

    /**
     * Get enabled platforms for a specific post
     * 
     * @param int $post_id WordPress post ID
     * @return array Array of platform slugs
     */
    private function get_enabled_platforms_for_post($post_id) {
        // Get global enabled platforms
        $global_enabled = get_option('smo_social_enabled_platforms', array());
        
        // Get post-specific overrides
        $post_enabled = get_post_meta($post_id, '_smo_enabled_platforms', true);
        if (is_array($post_enabled)) {
            return array_intersect($global_enabled, $post_enabled);
        }
        
        return $global_enabled;
    }

    /**
     * Get all enabled platforms
     * 
     * @return array Array of platform slugs
     */
    private function get_enabled_platforms() {
        return get_option('smo_social_enabled_platforms', array());
    }

    /**
     * Get platform credentials from secure storage
     * 
     * @param string $platform Platform slug
     * @return array|null Credentials array
     */
    private function get_platform_credentials($platform) {
        $storage = new \SMO_Social\Security\TokenStorage($platform);
        return $storage->get_tokens();
    }

    /**
     * Get driver configuration for platform
     * 
     * @param string $platform Platform slug
     * @return array|null Driver configuration
     */
    private function get_driver_config($platform) {
        $driver_file = SMO_SOCIAL_PLUGIN_DIR . 'drivers/' . $platform . '.json';
        if (!file_exists($driver_file)) {
            return null;
        }
        
        return json_decode(file_get_contents($driver_file), true);
    }

    /**
     * Update platform statistics
     * 
     * @param string $platform Platform slug
     * @param bool   $success  Whether the operation was successful
     */
    private function update_platform_stats($platform, $success) {
        $stats = get_option('smo_platform_stats', array());
        
        if (!isset($stats[$platform])) {
            $stats[$platform] = array(
                'total_posts' => 0,
                'successful_posts' => 0,
                'failed_posts' => 0,
                'last_post' => null
            );
        }
        
        $stats[$platform]['total_posts']++;
        if ($success) {
            $stats[$platform]['successful_posts']++;
            $stats[$platform]['last_post'] = current_time('mysql');
        } else {
            $stats[$platform]['failed_posts']++;
        }
        
        update_option('smo_platform_stats', $stats);
    }

    /**
     * Send success notification
     * 
     * @param int    $post_id   WordPress post ID
     * @param string $platform  Platform slug
     * @param array  $result    Share result
     */
    private function send_success_notification($post_id, $platform, $result) {
        if (!get_option('smo_enable_notifications', false)) {
            return;
        }
        
        $post = get_post($post_id);
        $message = sprintf(
            __('Successfully posted "%s" to %s', 'smo-social'),
            $post->post_title,
            $platform
        );
        
        $this->send_admin_notification($message, 'success');
    }

    /**
     * Send failure notification
     * 
     * @param int    $post_id   WordPress post ID
     * @param string $platform  Platform slug
     * @param string $error     Error message
     */
    private function send_failure_notification($post_id, $platform, $error) {
        if (!get_option('smo_enable_notifications', false)) {
            return;
        }
        
        $post = get_post($post_id);
        $message = sprintf(
            __('Failed to post "%s" to %s: %s', 'smo-social'),
            $post->post_title,
            $platform,
            $error
        );
        
        $this->send_admin_notification($message, 'error');
    }

    /**
     * Send admin notification
     * 
     * @param string $message Notification message
     * @param string $type    Notification type (success, error, info)
     */
    private function send_admin_notification($message, $type = 'info') {
        // Add to WordPress admin notices
        add_action('admin_notices', function() use ($message, $type) {
            echo '<div class="notice notice-' . $type . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }

    /**
     * Log activity for debugging
     * 
     * @param string $action   Action type
     * @param int    $post_id  WordPress post ID
     * @param string $message  Log message
     */
    private function log_activity($action, $post_id, $message) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'post_id' => $post_id,
            'message' => $message,
            'user_id' => get_current_user_id()
        );
        
        $logs = get_post_meta($post_id, '_smo_scheduler_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }
        
        $logs[] = $log_entry;
        
        // Keep only last 20 logs per post
        if (count($logs) > 20) {
            $logs = array_slice($logs, -20);
        }
        
        update_post_meta($post_id, '_smo_scheduler_logs', $logs);
    }

    /**
     * Cleanup old scheduled actions
     */
    public function cleanup_old_actions() {
        // Get all completed actions older than 7 days
        $old_actions = as_get_scheduled_actions(array(
            'status' => 'completed',
            'date_query' => array(
                array(
                    'before' => current_time('mysql', '-7 days')
                )
            )
        ));
        
        foreach ($old_actions as $action) {
            as_delete_action($action->ID);
        }
        
        // Cleanup old logs
        $this->cleanup_old_logs();
    }

    /**
     * Cleanup old log entries
     */
    private function cleanup_old_logs() {
        $cutoff_date = current_time('mysql', '-30 days');
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_smo_scheduler_logs',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        foreach ($posts as $post) {
            $logs = get_post_meta($post->ID, '_smo_scheduler_logs', true);
            if (is_array($logs)) {
                $recent_logs = array_filter($logs, function($log) use ($cutoff_date) {
                    return strtotime($log['timestamp']) > strtotime($cutoff_date);
                });
                
                if (count($recent_logs) !== count($logs)) {
                    update_post_meta($post->ID, '_smo_scheduler_logs', array_values($recent_logs));
                }
            }
        }
    }

    /**
     * Get scheduled actions for admin display
     * 
     * @return array Array of scheduled actions
     */
    public function get_scheduled_actions() {
        $actions = as_get_scheduled_actions(array(
            'status' => 'pending'
        ));
        
        $scheduled_list = array();
        
        foreach ($actions as $action) {
            if ($action->hook === 'smo_execute_social_share') {
                $args = $action->args;
                $scheduled_list[] = array(
                    'id' => $action->ID,
                    'post_id' => $args['post_id'] ?? 0,
                    'platform' => $args['platform'] ?? 'unknown',
                    'scheduled_date' => $action->date,
                    'status' => $action->status
                );
            }
        }
        
        return $scheduled_list;
    }
}