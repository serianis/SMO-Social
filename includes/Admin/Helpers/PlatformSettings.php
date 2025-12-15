<?php
/**
 * Platform Settings Helper
 * 
 * Provides UI and methods for configuring platform OAuth credentials
 * 
 * @package SMO_Social
 * @subpackage Admin
 */

namespace SMO_Social\Admin\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class PlatformSettings {
    
    /**
     * Render platform credentials form
     * 
     * @param string $platform_slug Platform identifier
     * @return string HTML form
     */
    public static function render_credentials_form($platform_slug) {
        $settings = get_option('smo_social_platform_settings', array());
        $platform_settings = isset($settings[$platform_slug]) ? $settings[$platform_slug] : array();
        
        $platform_names = array(
            'facebook' => 'Facebook',
            'twitter' => 'Twitter/X',
            'linkedin' => 'LinkedIn',
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest'
        );
        
        $platform_name = isset($platform_names[$platform_slug]) ? $platform_names[$platform_slug] : ucfirst($platform_slug);
        
        ob_start();
        ?>
        <div class="smo-platform-credentials-form">
            <h3><?php echo esc_html(sprintf(__('%s OAuth Credentials', 'smo-social'), $platform_name)); ?></h3>
            
            <form id="smo-credentials-form-<?php echo esc_attr($platform_slug); ?>" class="smo-credentials-form">
                <input type="hidden" name="platform" value="<?php echo esc_attr($platform_slug); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="client_id_<?php echo esc_attr($platform_slug); ?>">
                                <?php _e('Client ID / App ID', 'smo-social'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="client_id_<?php echo esc_attr($platform_slug); ?>" 
                                   name="client_id" 
                                   value="<?php echo esc_attr($platform_settings['client_id'] ?? ''); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php echo self::get_client_id_help_text($platform_slug); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="client_secret_<?php echo esc_attr($platform_slug); ?>">
                                <?php _e('Client Secret / App Secret', 'smo-social'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="client_secret_<?php echo esc_attr($platform_slug); ?>" 
                                   name="client_secret" 
                                   value="<?php echo esc_attr($platform_settings['client_secret'] ?? ''); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php _e('Your app secret (will be stored encrypted)', 'smo-social'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Redirect URI', 'smo-social'); ?></label>
                        </th>
                        <td>
                            <code class="smo-redirect-uri">
                                <?php echo esc_url(admin_url('admin.php?page=smo-social&action=oauth_callback&platform=' . $platform_slug)); ?>
                            </code>
                            <button type="button" class="button button-small smo-copy-redirect-uri" data-platform="<?php echo esc_attr($platform_slug); ?>">
                                <?php _e('Copy', 'smo-social'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Add this URL to your app\'s authorized redirect URIs', 'smo-social'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="smo-setup-instructions">
                    <h4><?php _e('Setup Instructions', 'smo-social'); ?></h4>
                    <?php echo self::get_setup_instructions($platform_slug); ?>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save Credentials', 'smo-social'); ?>
                    </button>
                    <button type="button" class="button smo-test-credentials" data-platform="<?php echo esc_attr($platform_slug); ?>">
                        <?php _e('Test Connection', 'smo-social'); ?>
                    </button>
                </p>
            </form>
        </div>
<?php
        return ob_get_clean();
    }
    
    /**
     * Get help text for client ID field
     */
    private static function get_client_id_help_text($platform_slug) {
        $help_texts = array(
            'facebook' => __('Get your App ID from Facebook Developers', 'smo-social'),
            'twitter' => __('Get your Client ID from Twitter Developer Portal', 'smo-social'),
            'linkedin' => __('Get your Client ID from LinkedIn Developers', 'smo-social'),
            'instagram' => __('Instagram uses Facebook App ID', 'smo-social'),
            'youtube' => __('Get your Client ID from Google Cloud Console', 'smo-social')
        );
        
        return isset($help_texts[$platform_slug]) ? $help_texts[$platform_slug] : __('Get your Client ID from the platform\'s developer portal', 'smo-social');
    }
    
    /**
     * Get setup instructions for each platform
     */
    private static function get_setup_instructions($platform_slug) {
        $instructions = array(
            'facebook' => '
                <ol>
                    <li>Go to <a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a></li>
                    <li>Create a new app or select an existing one</li>
                    <li>Add "Facebook Login" product to your app</li>
                    <li>Copy your App ID and App Secret from Settings > Basic</li>
                    <li>Add the Redirect URI above to Settings > Facebook Login > Valid OAuth Redirect URIs</li>
                    <li>Request permissions: pages_manage_posts, pages_read_engagement</li>
                </ol>
            ',
            'twitter' => '
                <ol>
                    <li>Go to <a href="https://developer.twitter.com/en/portal/dashboard" target="_blank">Twitter Developer Portal</a></li>
                    <li>Create a new app or select an existing one</li>
                    <li>Enable OAuth 2.0 in User authentication settings</li>
                    <li>Copy your Client ID and Client Secret</li>
                    <li>Add the Redirect URI above to your app settings</li>
                    <li>Request scopes: tweet.read, tweet.write, users.read, offline.access</li>
                </ol>
            ',
            'linkedin' => '
                <ol>
                    <li>Go to <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developers</a></li>
                    <li>Create a new app or select an existing one</li>
                    <li>Copy your Client ID and Client Secret from Auth tab</li>
                    <li>Add the Redirect URI above to Authorized redirect URLs</li>
                    <li>Request permissions: r_liteprofile, w_member_social</li>
                </ol>
            ',
            'youtube' => '
                <ol>
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li>Create a new project or select an existing one</li>
                    <li>Enable YouTube Data API v3</li>
                    <li>Create OAuth 2.0 credentials</li>
                    <li>Copy your Client ID and Client Secret</li>
                    <li>Add the Redirect URI above to Authorized redirect URIs</li>
                </ol>
            '
        );
        
        return isset($instructions[$platform_slug]) ? $instructions[$platform_slug] : '<p>' . __('Please refer to the platform\'s developer documentation for setup instructions.', 'smo-social') . '</p>';
    }
    
    /**
     * Save platform credentials
     */
    public static function save_credentials($platform_slug, $credentials) {
        $settings = get_option('smo_social_platform_settings', array());
        
        if (!isset($settings[$platform_slug])) {
            $settings[$platform_slug] = array();
        }
        
        // Sanitize and save
        $settings[$platform_slug]['client_id'] = sanitize_text_field($credentials['client_id']);
        $settings[$platform_slug]['client_secret'] = sanitize_text_field($credentials['client_secret']);
        
        return update_option('smo_social_platform_settings', $settings);
    }
}
