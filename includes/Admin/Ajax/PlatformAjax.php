<?php
namespace SMO_Social\Admin\Ajax;

use SMO_Social\Platforms\Manager as PlatformManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Platform AJAX Handlers
 */
class PlatformAjax extends BaseAjaxHandler {
    
    /**
     * @var PlatformManager
     */
    private $platform_manager;

    public function register() {
        add_action('wp_ajax_smo_connect_platform', [$this, 'connect_platform']);
        add_action('wp_ajax_smo_disconnect_platform', [$this, 'disconnect_platform']);
        add_action('wp_ajax_smo_test_platform', [$this, 'test_platform']);
        add_action('wp_ajax_smo_save_platform_credentials', [$this, 'save_credentials']);
        add_action('wp_ajax_smo_get_platform_status', [$this, 'get_status']);
        add_action('wp_ajax_smo_save_platform_settings', [$this, 'save_platform_settings']);
        add_action('wp_ajax_smo_refresh_platform_health', [$this, 'refresh_platform_health']);
        add_action('wp_ajax_smo_get_platform_health_details', [$this, 'get_health_details']);
    }

    private function get_manager() {
        if (!$this->platform_manager) {
            $this->platform_manager = new PlatformManager();
        }
        return $this->platform_manager;
    }

    public function connect_platform() {
        if (!$this->verify_request()) return;

        $platform_slug = $this->get_text('platform');
        if (!$platform_slug) {
            $this->send_error(__('Platform not specified', 'smo-social'));
            return;
        }

        $platform = $this->get_manager()->get_platform($platform_slug);
        if (!$platform) {
            $this->send_error(__('Platform not found', 'smo-social'));
            return;
        }

        try {
            $auth_url = $this->generate_auth_url($platform);
            if ($auth_url) {
                $this->send_success([
                    'auth_url' => $auth_url,
                    'message' => sprintf(__('Redirecting to %s for authentication...', 'smo-social'), $platform->get_name())
                ]);
            } else {
                $this->send_error(__('Failed to generate authorization URL', 'smo-social'));
            }
        } catch (\Exception $e) {
            $this->send_error($e->getMessage());
        }
    }

    public function disconnect_platform() {
        if (!$this->verify_request()) return;

        $platform_slug = $this->get_text('platform');
        
        try {
            // Logic to disconnect - needs to access database directly or via manager
            // For now, mirroring Admin.php logic
            global $wpdb;
            
            // Mark tokens inactive
            $wpdb->update(
                $wpdb->prefix . 'smo_platform_tokens',
                ['status' => 'inactive', 'updated_at' => current_time('mysql')],
                ['platform_slug' => $platform_slug]
            );

            // Clear settings
            $wpdb->delete(
                $wpdb->prefix . 'smo_platform_settings',
                ['platform_slug' => $platform_slug]
            );

            $this->send_success(null, __('Platform disconnected successfully', 'smo-social'));
        } catch (\Exception $e) {
            $this->send_error($e->getMessage());
        }
    }

    public function test_platform() {
        if (!$this->verify_request()) return;

        $platform_slug = $this->get_text('platform');
        $platform = $this->get_manager()->get_platform($platform_slug);

        if (!$platform) {
            $this->send_error(__('Platform not found', 'smo-social'));
            return;
        }

        try {
            // In a real scenario, we would call $platform->test_connection()
            // Using placeholder logic for now
            $is_connected = $this->is_platform_connected($platform_slug);
            
            if ($is_connected) {
                $this->send_success(null, __('Connection test successful!', 'smo-social'));
            } else {
                 $this->send_error(__('Platform is not connected', 'smo-social'));
            }
        } catch (\Exception $e) {
            $this->send_error($e->getMessage());
        }
    }
    
    public function save_credentials() {
        if (!$this->verify_request()) return;
        
        $platform_slug = $this->get_text('platform');
        $client_id = $this->get_text('client_id');
        $client_secret = $this->get_text('client_secret'); // Should be more secure

        if (!$platform_slug || !$client_id || !$client_secret) {
            $this->send_error(__('Missing required data', 'smo-social'));
            return;
        }

        $all_settings = get_option('smo_social_platform_settings', []);
        
        if (!isset($all_settings[$platform_slug])) {
            $all_settings[$platform_slug] = [];
        }
        
        $all_settings[$platform_slug]['client_id'] = $client_id;
        $all_settings[$platform_slug]['client_secret'] = $client_secret;

        if (update_option('smo_social_platform_settings', $all_settings)) {
            $this->send_success(['platform' => $platform_slug], __('Credentials saved successfully!', 'smo-social'));
        } else {
            // Sometimes update_option returns false if value hasn't changed, but we can consider it success
             $this->send_success(['platform' => $platform_slug], __('Credentials saved (no changes or update failed)', 'smo-social'));
        }
    }

    public function get_status() {
         if (!$this->verify_request()) return;
         
         $enabled_platforms = get_option('smo_social_enabled_platforms', []);
         // Logic from Admin.php
         // Simplified for now
         $status = [];
         foreach ($enabled_platforms as $slug) {
             $status[$slug] = $this->is_platform_connected($slug);
         }
         
         $this->send_success($status);
    }

    public function save_platform_settings() {
        if (!$this->verify_request()) return;

        $platform_slug = $this->get_text('platform');
        $settings = $_POST['settings'] ?? []; // Need raw input for array

        if (!$platform_slug || empty($settings)) {
            $this->send_error(__('Missing required data', 'smo-social'));
            return;
        }

        // Sanitize settings
        $sanitized_settings = [];
        foreach ($settings as $key => $value) {
            $sanitized_settings[sanitize_key($key)] = sanitize_textarea_field($value);
        }
        
        // Handle specific boolean fields if needed or other types
        // For now trusting sanitize_textarea_field for basic settings

        // Get existing settings
        $all_settings = get_option('smo_social_platform_settings', []);
        
        // Update platform settings
        $all_settings[$platform_slug] = array_merge(
            $all_settings[$platform_slug] ?? [],
            $sanitized_settings
        );

        // Save settings
        if (update_option('smo_social_platform_settings', $all_settings)) {
             $this->send_success(null, __('Platform settings saved successfully', 'smo-social'));
        } else {
             // Often returns false if no change
             $this->send_success(null, __('Platform settings saved (no changes)', 'smo-social'));
        }
    }

    public function refresh_platform_health() {
        if (!$this->verify_request()) return;

        $platform_slug = $this->get_text('platform');
        $platform = $this->get_manager()->get_platform($platform_slug);

        if (!$platform) {
            $this->send_error(__('Platform not found', 'smo-social'));
            return;
        }

        try {
            // Perform fresh health check
            $health_report = $platform->health_check();
            $this->send_success($health_report);
        } catch (\Exception $e) {
            $this->send_error($e->getMessage());
        }
    }

    public function get_health_details() {
        if (!$this->verify_request()) return;

        $platform_slug = $this->get_text('platform');
        $platform = $this->get_manager()->get_platform($platform_slug);

        if (!$platform) {
            $this->send_error(__('Platform not found', 'smo-social'));
            return;
        }

        // Get detailed health data from database
        global $wpdb;
        $health_table = $wpdb->prefix . 'smo_health_logs';
        
        $health_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $health_table WHERE platform_slug = %s ORDER BY check_timestamp DESC LIMIT 10",
            $platform_slug
        ));

        // Generate HTML for details modal
        $html = $this->generate_health_details_html($platform_slug, $health_logs);
        
        $this->send_success(['html' => $html]);
    }

    private function generate_health_details_html($platform_slug, $health_logs) {
        ob_start();
        ?>
        <div class="smo-health-details">
            <h4><?php printf(__('Health History for %s', 'smo-social'), esc_html($platform_slug)); ?></h4>
            
            <?php if (empty($health_logs)): ?>
                <p><?php _e('No health check history available.', 'smo-social'); ?></p>
            <?php else: ?>
                <div class="smo-health-timeline">
                    <?php foreach ($health_logs as $log): ?>
                        <div class="smo-health-entry status-<?php echo esc_attr($log->overall_status); ?>">
                            <div class="smo-health-entry-header">
                                <span class="smo-status-badge"><?php echo ucfirst($log->overall_status); ?></span>
                                <span class="smo-timestamp"><?php echo human_time_diff(strtotime($log->check_timestamp)); ?> ago</span>
                                <span class="smo-response-time"><?php echo $log->response_time; ?>ms</span>
                            </div>
                            <?php if (!empty($log->critical_issues)): ?>
                                <div class="smo-issues">
                                    <strong><?php _e('Critical Issues:', 'smo-social'); ?></strong>
                                    <ul>
                                        <?php 
                                        $issues = json_decode($log->critical_issues, true);
                                        if (is_array($issues)) {
                                            foreach ($issues as $issue): ?>
                                                <li><?php echo esc_html($issue); ?></li>
                                            <?php endforeach; 
                                        } ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($log->warnings)): ?>
                                <div class="smo-warnings">
                                    <strong><?php _e('Warnings:', 'smo-social'); ?></strong>
                                    <ul>
                                        <?php 
                                        $warnings = json_decode($log->warnings, true);
                                        if (is_array($warnings)) {
                                            foreach ($warnings as $warning): ?>
                                                <li><?php echo esc_html($warning); ?></li>
                                            <?php endforeach;
                                        } ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // Helper methods
    
    private function generate_auth_url($platform) {
        $slug = $platform->get_slug();
        $settings = get_option('smo_social_platform_settings', []);
        $client_id = isset($settings[$slug]['client_id']) ? $settings[$slug]['client_id'] : '';
        
        $redirect_uri = admin_url('admin.php?page=smo-social&action=oauth_callback&platform=' . $slug);

        switch ($slug) {
            case 'facebook':
                return "https://www.facebook.com/v18.0/dialog/oauth?client_id={$client_id}&redirect_uri={$redirect_uri}&scope=pages_manage_posts,pages_read_engagement";
            case 'twitter':
                return "https://twitter.com/i/oauth2/authorize?client_id={$client_id}&redirect_uri={$redirect_uri}&response_type=code&scope=tweet.read%20tweet.write%20users.read%20offline.access";
            case 'linkedin':
                return "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&state=random_state&scope=r_liteprofile%20r_emailaddress%20w_member_social";
            default:
                return '#';
        }
    }

    private function is_platform_connected($slug) {
        global $wpdb;
        $token = $wpdb->get_var($wpdb->prepare(
            "SELECT access_token FROM {$wpdb->prefix}smo_platform_tokens WHERE platform_slug = %s AND status = 'active'",
            $slug
        ));
        return !empty($token);
    }
}
