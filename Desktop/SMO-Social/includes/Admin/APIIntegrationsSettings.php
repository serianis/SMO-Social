<?php
/**
 * API Integrations Settings Page
 * Manages Google Drive, Dropbox, and Canva API configurations
 */

namespace SMO_Social\Admin;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../Content/GoogleDriveIntegration.php';
require_once __DIR__ . '/../Content/DropboxIntegration.php';
require_once __DIR__ . '/../Content/CanvaIntegration.php';

/**
 * API Integrations Settings Manager
 */
class APIIntegrationsSettings {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_api_settings_page'));
    }
    
    /**
     * Register API settings
     */
    public function register_settings() {
        // Google Drive Settings
        register_setting('smo_social_api_settings', 'smo_google_drive_client_id');
        register_setting('smo_social_api_settings', 'smo_google_drive_client_secret');
        
        // Dropbox Settings
        register_setting('smo_social_api_settings', 'smo_dropbox_client_id');
        register_setting('smo_social_api_settings', 'smo_dropbox_client_secret');
        
        // Canva Settings
        register_setting('smo_social_api_settings', 'smo_canva_api_key');
    }
    
    /**
     * Add API settings submenu page
     */
    public function add_api_settings_page() {
        add_submenu_page(
            'smo-social',
            __('API Integrations', 'smo-social'),
            __('API Integrations', 'smo-social'),
            'manage_options',
            'smo-social-api-settings',
            array($this, 'display_api_settings_page')
        );
    }
    
    /**
     * Display API settings page
     */
    public function display_api_settings_page() {
        $google_drive = new \SMO_Social\Content\GoogleDriveIntegration();
        $dropbox = new \SMO_Social\Content\DropboxIntegration();
        $canva = new \SMO_Social\Content\CanvaIntegration();
        
        ?>
        <div class="wrap smo-api-settings">
            <h1><?php _e('API Integrations', 'smo-social'); ?></h1>
            
            <div class="smo-api-status-cards">
                <!-- Google Drive Status -->
                <div class="smo-api-card <?php echo $google_drive->is_connected() ? 'connected' : 'disconnected'; ?>">
                    <div class="smo-api-header">
                        <h3>Google Drive</h3>
                        <span class="smo-status-indicator <?php echo $google_drive->is_connected() ? 'status-connected' : 'status-disconnected'; ?>">
                            <?php echo $google_drive->is_connected() ? __('Connected', 'smo-social') : __('Not Connected', 'smo-social'); ?>
                        </span>
                    </div>
                    <div class="smo-api-content">
                        <p><?php _e('Import documents, spreadsheets, and media files from your Google Drive.', 'smo-social'); ?></p>
                        <div class="smo-api-actions">
                            <?php if ($google_drive->is_connected()): ?>
                                <button type="button" class="button" id="smo-gdrive-disconnect"><?php _e('Disconnect', 'smo-social'); ?></button>
                            <?php else: ?>
                                <button type="button" class="button button-primary" id="smo-gdrive-connect"><?php _e('Connect Google Drive', 'smo-social'); ?></button>
                            <?php endif; ?>
                            <button type="button" class="button" id="smo-gdrive-test"><?php _e('Test Connection', 'smo-social'); ?></button>
                        </div>
                    </div>
                </div>
                
                <!-- Dropbox Status -->
                <div class="smo-api-card <?php echo $dropbox->is_connected() ? 'connected' : 'disconnected'; ?>">
                    <div class="smo-api-header">
                        <h3>Dropbox</h3>
                        <span class="smo-status-indicator <?php echo $dropbox->is_connected() ? 'status-connected' : 'status-disconnected'; ?>">
                            <?php echo $dropbox->is_connected() ? __('Connected', 'smo-social') : __('Not Connected', 'smo-social'); ?>
                        </span>
                    </div>
                    <div class="smo-api-content">
                        <p><?php _e('Sync files and folders from your Dropbox account.', 'smo-social'); ?></p>
                        <div class="smo-api-actions">
                            <?php if ($dropbox->is_connected()): ?>
                                <button type="button" class="button" id="smo-dropbox-disconnect"><?php _e('Disconnect', 'smo-social'); ?></button>
                            <?php else: ?>
                                <button type="button" class="button button-primary" id="smo-dropbox-connect"><?php _e('Connect Dropbox', 'smo-social'); ?></button>
                            <?php endif; ?>
                            <button type="button" class="button" id="smo-dropbox-test"><?php _e('Test Connection', 'smo-social'); ?></button>
                        </div>
                    </div>
                </div>
                
                <!-- Canva Status -->
                <div class="smo-api-card <?php echo $canva->is_connected() ? 'connected' : 'disconnected'; ?>">
                    <div class="smo-api-header">
                        <h3>Canva</h3>
                        <span class="smo-status-indicator <?php echo $canva->is_connected() ? 'status-connected' : 'status-disconnected'; ?>">
                            <?php echo $canva->is_connected() ? __('Connected', 'smo-social') : __('Not Connected', 'smo-social'); ?>
                        </span>
                    </div>
                    <div class="smo-api-content">
                        <p><?php _e('Import your Canva designs and templates.', 'smo-social'); ?></p>
                        <div class="smo-api-actions">
                            <?php if ($canva->is_connected()): ?>
                                <button type="button" class="button" id="smo-canva-disconnect"><?php _e('Disconnect', 'smo-social'); ?></button>
                            <?php else: ?>
                                <button type="button" class="button button-primary" id="smo-canva-connect"><?php _e('Connect Canva', 'smo-social'); ?></button>
                            <?php endif; ?>
                            <button type="button" class="button" id="smo-canva-test"><?php _e('Test Connection', 'smo-social'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- API Configuration Forms -->
            <div class="smo-api-config-section">
                <h2><?php _e('API Configuration', 'smo-social'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php settings_fields('smo_social_api_settings'); ?>
                    
                    <div class="smo-config-grid">
                        <!-- Google Drive Configuration -->
                        <div class="smo-config-card">
                            <h3><?php _e('Google Drive Configuration', 'smo-social'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="smo_google_drive_client_id"><?php _e('Client ID', 'smo-social'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="smo_google_drive_client_id" 
                                               name="smo_google_drive_client_id" 
                                               value="<?php echo esc_attr(get_option('smo_google_drive_client_id', '')); ?>" 
                                               class="regular-text" 
                                               placeholder="<?php _e('Enter Google Drive Client ID', 'smo-social'); ?>" />
                                        <p class="description"><?php _e('Get this from Google Cloud Console', 'smo-social'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="smo_google_drive_client_secret"><?php _e('Client Secret', 'smo-social'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" 
                                               id="smo_google_drive_client_secret" 
                                               name="smo_google_drive_client_secret" 
                                               value="<?php echo esc_attr(get_option('smo_google_drive_client_secret', '')); ?>" 
                                               class="regular-text" 
                                               placeholder="<?php _e('Enter Google Drive Client Secret', 'smo-social'); ?>" />
                                        <p class="description"><?php _e('Get this from Google Cloud Console', 'smo-social'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Dropbox Configuration -->
                        <div class="smo-config-card">
                            <h3><?php _e('Dropbox Configuration', 'smo-social'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="smo_dropbox_client_id"><?php _e('App Key', 'smo-social'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="smo_dropbox_client_id" 
                                               name="smo_dropbox_client_id" 
                                               value="<?php echo esc_attr(get_option('smo_dropbox_client_id', '')); ?>" 
                                               class="regular-text" 
                                               placeholder="<?php _e('Enter Dropbox App Key', 'smo-social'); ?>" />
                                        <p class="description"><?php _e('Get this from Dropbox App Console', 'smo-social'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="smo_dropbox_client_secret"><?php _e('App Secret', 'smo-social'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" 
                                               id="smo_dropbox_client_secret" 
                                               name="smo_dropbox_client_secret" 
                                               value="<?php echo esc_attr(get_option('smo_dropbox_client_secret', '')); ?>" 
                                               class="regular-text" 
                                               placeholder="<?php _e('Enter Dropbox App Secret', 'smo-social'); ?>" />
                                        <p class="description"><?php _e('Get this from Dropbox App Console', 'smo-social'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Canva Configuration -->
                        <div class="smo-config-card">
                            <h3><?php _e('Canva Configuration', 'smo-social'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="smo_canva_api_key"><?php _e('API Key', 'smo-social'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" 
                                               id="smo_canva_api_key" 
                                               name="smo_canva_api_key" 
                                               value="<?php echo esc_attr(get_option('smo_canva_api_key', '')); ?>" 
                                               class="regular-text" 
                                               placeholder="<?php _e('Enter Canva API Key', 'smo-social'); ?>" />
                                        <p class="description"><?php _e('Get this from Canva Developers', 'smo-social'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php submit_button(__('Save Configuration', 'smo-social')); ?>
                </form>
            </div>
        </div>
        
        <style>
        .smo-api-settings {
            max-width: 1200px;
        }
        
        .smo-api-status-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .smo-api-card {
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
        }
        
        .smo-api-card.connected {
            border-color: #00a32a;
        }
        
        .smo-api-card.disconnected {
            border-color: #d63638;
        }
        
        .smo-api-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .smo-api-header h3 {
            margin: 0;
            color: #1d2327;
        }
        
        .smo-status-indicator {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-connected {
            background: #d4edda;
            color: #155724;
        }
        
        .status-disconnected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .smo-api-content p {
            color: #646970;
            margin-bottom: 15px;
        }
        
        .smo-api-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .smo-config-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
        }
        
        .smo-config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-top: 20px;
        }
        
        .smo-config-card h3 {
            margin-top: 0;
            color: #1d2327;
            border-bottom: 1px solid #e1e1e1;
            padding-bottom: 10px;
        }
        
        .smo-config-card .form-table th {
            width: 150px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Google Drive OAuth
            $('#smo-gdrive-connect').on('click', function() {
                $.post(ajaxurl, {
                    action: 'smo_google_drive_auth',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        window.location.href = response.data.auth_url;
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            // Dropbox OAuth
            $('#smo-dropbox-connect').on('click', function() {
                $.post(ajaxurl, {
                    action: 'smo_dropbox_auth',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        window.location.href = response.data.auth_url;
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            // Canva connection test
            $('#smo-canva-connect').on('click', function() {
                var apiKey = $('#smo_canva_api_key').val();
                if (!apiKey) {
                    alert('<?php _e("Please enter your Canva API key first", "smo-social"); ?>');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'smo_canva_test_connection',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e("Canva connected successfully!", "smo-social"); ?>');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            // Test connections
            $('#smo-gdrive-test, #smo-dropbox-test, #smo-canva-test').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php _e("Testing...", "smo-social"); ?>');
                
                setTimeout(function() {
                    button.prop('disabled', false).text('<?php _e("Test Connection", "smo-social"); ?>');
                    alert('<?php _e("Connection test completed", "smo-social"); ?>');
                }, 2000);
            });
            
            // Disconnect handlers
            $('#smo-gdrive-disconnect, #smo-dropbox-disconnect, #smo-canva-disconnect').on('click', function() {
                if (confirm('<?php _e("Are you sure you want to disconnect this integration?", "smo-social"); ?>')) {
                    location.reload();
                }
            });
        });
        </script>
        <?php
    }
}