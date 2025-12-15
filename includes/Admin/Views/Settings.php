<?php
/**
 * SMO Social Settings View - Enhanced
 *
 * Modern settings interface with gradient design and responsive layout
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings View Class
 */
class Settings
{

    /**
     * Initialize WordPress hooks
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }

        wp_enqueue_style(
            'smo-content-import-enhanced',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-content-import-enhanced.css',
            array('smo-social-admin'),
            SMO_SOCIAL_VERSION
        );

        wp_enqueue_script(
            'smo-content-import-enhanced',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-content-import-enhanced.js',
            array('jquery', 'wp-util', 'smo-social-admin'),
            SMO_SOCIAL_VERSION,
            true
        );

        wp_localize_script('smo-content-import-enhanced', 'smoContentImportEnhanced', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_content_import_enhanced'),
            'strings' => array(
                'importing' => __('Importing...', 'smo-social'),
                'success' => __('Success!', 'smo-social'),
                'error' => __('Error occurred', 'smo-social'),
                'confirmDelete' => __('Are you sure you want to delete this item?', 'smo-social'),
                'processing' => __('Processing...', 'smo-social'),
                'completed' => __('Completed', 'smo-social'),
                'failed' => __('Failed', 'smo-social'),
                'running' => __('Running', 'smo-social'),
                'paused' => __('Paused', 'smo-social')
            )
        ));
    }

    /**
     * Render the Settings page
     */
    public function render() {
        // Get current settings
        $enabled = get_option('smo_social_enabled', 0);
        $timezone = get_option('smo_social_timezone', 'UTC');
        $date_format = get_option('smo_social_date_format', 'Y-m-d H:i:s');
        $ai_provider = get_option('smo_social_primary_provider', 'huggingface');

        // Use Common Layout wrapper
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('settings', __('Settings', 'smo-social'));
            
            // Render standardized gradient header using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_header([
                'icon' => '⚙️',
                'title' => __('System Settings', 'smo-social'),
                'subtitle' => __('Configure your preferences', 'smo-social'),
                'actions' => [
                    [
                        'id' => 'smo-save-settings',
                        'label' => __('Save Settings', 'smo-social'),
                        'icon' => 'yes',
                        'class' => 'smo-btn-primary'
                    ],
                    [
                        'id' => 'smo-export-settings',
                        'label' => __('Export Settings', 'smo-social'),
                        'icon' => 'download',
                        'class' => 'smo-btn-secondary'
                    ]
                ]
            ]);
            
            // Render standardized stats dashboard using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
                [
                    'icon' => 'admin-tools',
                    'value' => '-',
                    'label' => __('Active Providers', 'smo-social'),
                    'trend' => '⚡ Configured',
                    'id' => 'active-providers'
                ],
                [
                    'icon' => 'share',
                    'value' => '-',
                    'label' => __('Connected Platforms', 'smo-social'),
                    'trend' => '🔗 Active',
                    'id' => 'connected-platforms'
                ],
                [
                    'icon' => 'chart-line',
                    'value' => '-',
                    'label' => __('Automation Rules', 'smo-social'),
                    'trend' => '📈 Active',
                    'id' => 'automation-rules'
                ],
                [
                    'icon' => 'admin-generic',
                    'value' => '-',
                    'label' => __('System Status', 'smo-social'),
                    'trend' => '✅ Operational',
                    'id' => 'system-status'
                ]
            ]);
            
            // Render standardized quick actions using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_quick_actions([
                [
                    'icon' => 'admin-network',
                    'label' => __('Test Connections', 'smo-social'),
                    'id' => 'smo-test-connections'
                ],
                [
                    'icon' => 'trash',
                    'label' => __('Clear Cache', 'smo-social'),
                    'id' => 'smo-clear-cache'
                ],
                [
                    'icon' => 'list-view',
                    'label' => __('View Logs', 'smo-social'),
                    'id' => 'smo-view-logs'
                ],
                [
                    'icon' => 'upload',
                    'label' => __('Import Settings', 'smo-social'),
                    'id' => 'smo-import-settings'
                ]
            ]);
        }
        ?>

            <!-- Main Content Tabs -->
            <div class="smo-tabs-wrapper">
                <nav class="smo-tabs-nav">
                    <a href="#general-settings" class="smo-tab-link active" data-tab="general-settings">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('General Settings', 'smo-social'); ?>
                    </a>
                    <a href="#ai-providers" class="smo-tab-link" data-tab="ai-providers">
                        <span class="dashicons dashicons-superhero"></span>
                        <?php esc_html_e('AI Providers', 'smo-social'); ?>
                    </a>
                    <a href="#social-networks" class="smo-tab-link" data-tab="social-networks">
                        <span class="dashicons dashicons-share"></span>
                        <?php esc_html_e('Social Networks', 'smo-social'); ?>
                    </a>
                    <a href="#dashboard-widgets" class="smo-tab-link" data-tab="dashboard-widgets">
                        <span class="dashicons dashicons-dashboard"></span>
                        <?php esc_html_e('Dashboard Widgets', 'smo-social'); ?>
                    </a>
                    <a href="#advanced-settings" class="smo-tab-link" data-tab="advanced-settings">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php esc_html_e('Advanced Settings', 'smo-social'); ?>
                    </a>
                </nav>

                <div class="smo-tabs-content">
                    <!-- General Settings Tab -->
                    <div class="smo-tab-panel active" id="general-settings-panel">
                        <?php $this->render_general_settings_panel(); ?>
                    </div>

                    <!-- AI Providers Tab -->
                    <div class="smo-tab-panel" id="ai-providers-panel">
                        <?php $this->render_ai_providers_panel(); ?>
                    </div>

                    <!-- Social Networks Tab -->
                    <div class="smo-tab-panel" id="social-networks-panel">
                        <?php $this->render_social_networks_panel(); ?>
                    </div>

                    <!-- Dashboard Widgets Tab -->
                    <div class="smo-tab-panel" id="dashboard-widgets-panel">
                        <?php $this->render_dashboard_widgets_panel(); ?>
                    </div>

                    <!-- Advanced Settings Tab -->
                    <div class="smo-tab-panel" id="advanced-settings-panel">
                        <?php $this->render_advanced_settings_panel(); ?>
                    </div>
                </div>
            </div>

        <!-- Settings Form (hidden, handled via AJAX) -->
        <form method="post" action="options.php" id="smo-settings-form" style="display: none;">
            <?php settings_fields('smo_social_settings'); ?>
        </form>

        <?php
        // Close AppLayout wrapper
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        }
        
        $this->render_modals();
    }

    /**
     * Render General Settings Panel
     */
    private function render_general_settings_panel() {
        $enabled = get_option('smo_social_enabled', 0);
        $timezone = get_option('smo_social_timezone', 'UTC');
        $date_format = get_option('smo_social_date_format', 'Y-m-d H:i:s');
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('General Configuration', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Basic settings for your social media operations', 'smo-social'); ?></p>
            </div>
        </div>

        <div class="smo-form-grid two-columns">
            <div class="smo-form-field">
                <label class="smo-toggle">
                    <input type="checkbox" name="smo_social_enabled" value="1" <?php checked($enabled, 1); ?> class="smo-toggle-input">
                    <span class="smo-toggle-switch"></span>
                    <span class="smo-form-label"><?php esc_html_e('Enable Plugin Features', 'smo-social'); ?></span>
                </label>
                <p class="smo-form-help">
                    <span class="icon">⚙️</span>
                    <?php esc_html_e('Master switch to enable/disable all plugin automated features', 'smo-social'); ?>
                </p>
            </div>

            <div class="smo-form-field">
                <label class="smo-form-label" for="smo_social_timezone"><?php esc_html_e('Timezone', 'smo-social'); ?></label>
                <select name="smo_social_timezone" id="smo_social_timezone" class="smo-select">
                    <?php echo wp_timezone_choice($timezone); ?>
                </select>
            </div>

            <div class="smo-form-field">
                <label class="smo-form-label" for="smo_social_date_format"><?php esc_html_e('Date Format', 'smo-social'); ?></label>
                <select name="smo_social_date_format" id="smo_social_date_format" class="smo-select">
                    <option value="Y-m-d H:i:s" <?php selected($date_format, 'Y-m-d H:i:s'); ?>>2024-11-24 14:30:00</option>
                    <option value="m/d/Y H:i" <?php selected($date_format, 'm/d/Y H:i'); ?>>11/24/2024 14:30</option>
                    <option value="d/m/Y H:i" <?php selected($date_format, 'd/m/Y H:i'); ?>>24/11/2024 14:30</option>
                </select>
                <p class="smo-form-help">
                    <span class="icon">📅</span>
                    <?php esc_html_e('Choose how dates and times are displayed throughout the interface', 'smo-social'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render AI Providers Panel
     */
    private function render_ai_providers_panel() {
        $ai_provider = get_option('smo_social_primary_provider', 'huggingface');
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('AI Provider Configuration', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Configure your AI providers with API keys, URLs, and model names', 'smo-social'); ?></p>
            </div>
        </div>

        <div class="smo-settings-grid">
            <div class="smo-form-group">
                <label class="smo-form-label"><?php esc_html_e('Primary AI Provider', 'smo-social'); ?></label>
                <select name="smo_social_primary_provider" id="smo-primary-provider" class="smo-form-select">
                    <?php
                    if (class_exists('\SMO_Social\AI\ProvidersHelper')) {
                        $categories = \SMO_Social\AI\ProvidersHelper::get_providers_by_category();
                        foreach ($categories as $type => $category) {
                            echo '<optgroup label="' . esc_attr($category['name']) . '">';
                            foreach ($category['providers'] as $id => $provider) {
                                $selected = selected($ai_provider, $id, false);
                                echo '<option value="' . esc_attr($id) . '" ' . $selected . '>';
                                echo esc_html($provider['name']);
                                echo '</option>';
                            }
                            echo '</optgroup>';
                        }
                    } else {
                        ?>
                        <option value="huggingface" <?php selected($ai_provider, 'huggingface'); ?>>HuggingFace (Free)</option>
                        <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI</option>
                        <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic</option>
                        <option value="openrouter" <?php selected($ai_provider, 'openrouter'); ?>>OpenRouter</option>
                        <?php
                    }
                    ?>
                </select>
                <p class="description"><?php esc_html_e('Select which AI provider to use for content generation', 'smo-social'); ?></p>
            </div>

            <!-- Dynamic Provider Configuration Cards -->
            <?php
            if (class_exists('\SMO_Social\AI\ProvidersConfig')) {
                $all_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();

                foreach ($all_providers as $provider_id => $provider) {
                    $is_active = ($ai_provider === $provider_id);
                    $display_style = $is_active ? 'block' : 'none';
                    ?>
                    <div class="smo-form-section" id="provider-config-<?php echo esc_attr($provider_id); ?>" style="display: <?php echo $display_style; ?>;">
                        <h4 class="smo-form-section-title">
                            <span class="icon">🤖</span>
                            <?php echo esc_html($provider['name']); ?> Configuration
                        </h4>
                        <p class="smo-form-section-description">
                            <?php printf(__('Configure your %s AI provider settings', 'smo-social'), esc_html($provider['name'])); ?>
                        </p>

                        <div class="smo-form-grid">
                            <?php if ($provider['requires_key'] && isset($provider['key_option'])):
                                $current_key = get_option($provider['key_option'], '');
                            ?>
                                <div class="smo-form-field required">
                                    <label class="smo-form-label" for="<?php echo esc_attr($provider['key_option']); ?>">
                                        <?php esc_html_e('API Key', 'smo-social'); ?>
                                    </label>
                                    <input type="password"
                                           id="<?php echo esc_attr($provider['key_option']); ?>"
                                           name="<?php echo esc_attr($provider['key_option']); ?>"
                                           value="<?php echo esc_attr($current_key); ?>"
                                           class="smo-input"
                                           placeholder="<?php echo esc_attr('Enter your ' . $provider['name'] . ' API key'); ?>"
                                           autocomplete="off"
                                           aria-describedby="<?php echo esc_attr($provider['key_option']); ?>-help">
                                    <p id="<?php echo esc_attr($provider['key_option']); ?>-help" class="smo-form-help">
                                        <span class="icon">🔑</span>
                                        <?php printf(__('Your %s API key for authentication', 'smo-social'), esc_html($provider['name'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($provider['models']) && !empty($provider['models'])): 
                                $model_option = 'smo_social_' . $provider_id . '_model';
                                $current_model = get_option($model_option, $provider['models'][0]);
                            ?>
                                <div class="smo-form-field">
                                    <label class="smo-form-label" for="<?php echo esc_attr($model_option); ?>">
                                        <?php esc_html_e('Model', 'smo-social'); ?>
                                    </label>
                                    <select name="<?php echo esc_attr($model_option); ?>" id="<?php echo esc_attr($model_option); ?>" class="smo-select">
                                        <?php foreach ($provider['models'] as $model): ?>
                                            <option value="<?php echo esc_attr($model); ?>" <?php selected($current_model, $model); ?>>
                                                <?php echo esc_html($model); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="smo-form-help">
                                        <span class="icon">🤖</span>
                                        <?php esc_html_e('Select the AI model to use for content generation', 'smo-social'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render Social Networks Panel
     */
    private function render_social_networks_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Social Networks', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Configure connections to your social media platforms', 'smo-social'); ?></p>
            </div>
        </div>
        <div class="smo-settings-grid">
            <p><?php esc_html_e('For detailed platform connections, visit the Platforms page.', 'smo-social'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=smo-social-platforms'); ?>" class="smo-btn smo-btn-primary">
                <?php esc_html_e('Go to Platforms', 'smo-social'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render Dashboard Widgets Panel
     */
    private function render_dashboard_widgets_panel() {
        $widgets_enabled = get_option('smo_social_dashboard_widgets', array());
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Dashboard Widgets', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Configure which widgets appear on your dashboard', 'smo-social'); ?></p>
            </div>
        </div>

        <div class="smo-form-grid">
            <div class="smo-form-field">
                <label class="smo-toggle">
                    <input type="checkbox" name="smo_social_dashboard_widgets[overview]" value="1" <?php checked(isset($widgets_enabled['overview']) ? $widgets_enabled['overview'] : 1, 1); ?> class="smo-toggle-input">
                    <span class="smo-toggle-switch"></span>
                    <span class="smo-form-label"><?php esc_html_e('Overview Widget', 'smo-social'); ?></span>
                </label>
                <p class="smo-form-help">
                    <span class="icon">📊</span>
                    <?php esc_html_e('Show general overview statistics on the dashboard', 'smo-social'); ?>
                </p>
            </div>

            <div class="smo-form-field">
                <label class="smo-toggle">
                    <input type="checkbox" name="smo_social_dashboard_widgets[analytics]" value="1" <?php checked(isset($widgets_enabled['analytics']) ? $widgets_enabled['analytics'] : 1, 1); ?> class="smo-toggle-input">
                    <span class="smo-toggle-switch"></span>
                    <span class="smo-form-label"><?php esc_html_e('Analytics Widget', 'smo-social'); ?></span>
                </label>
                <p class="smo-form-help">
                    <span class="icon">📈</span>
                    <?php esc_html_e('Display analytics and performance metrics', 'smo-social'); ?>
                </p>
            </div>

            <div class="smo-form-field">
                <label class="smo-toggle">
                    <input type="checkbox" name="smo_social_dashboard_widgets[schedule]" value="1" <?php checked(isset($widgets_enabled['schedule']) ? $widgets_enabled['schedule'] : 1, 1); ?> class="smo-toggle-input">
                    <span class="smo-toggle-switch"></span>
                    <span class="smo-form-label"><?php esc_html_e('Schedule Widget', 'smo-social'); ?></span>
                </label>
                <p class="smo-form-help">
                    <span class="icon">📅</span>
                    <?php esc_html_e('View and manage scheduled posts', 'smo-social'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Advanced Settings Panel
     */
    private function render_advanced_settings_panel() {
        $debug_mode = get_option('smo_social_debug_mode', 0);
        $cache_ttl = get_option('smo_social_cache_ttl', 3600);
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Advanced Settings', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Advanced configuration options for power users', 'smo-social'); ?></p>
            </div>
        </div>

        <div class="smo-settings-grid">
            <div class="smo-form-group">
                <label class="smo-form-label"><?php esc_html_e('Debug Mode', 'smo-social'); ?></label>
                <label class="smo-toggle-switch">
                    <input type="checkbox" name="smo_social_debug_mode" value="1" <?php checked($debug_mode, 1); ?>>
                    <span class="smo-slider"></span>
                </label>
                <p class="description"><?php esc_html_e('Enable debug logging for troubleshooting', 'smo-social'); ?></p>
            </div>

            <div class="smo-form-group">
                <label class="smo-form-label"><?php esc_html_e('Cache Duration (seconds)', 'smo-social'); ?></label>
                <input type="number" name="smo_social_cache_ttl" value="<?php echo esc_attr($cache_ttl); ?>" class="smo-form-input" min="60" max="86400">
                <p class="description"><?php esc_html_e('How long to cache API responses (default: 3600 seconds)', 'smo-social'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render modals
     */
    private function render_modals() {
        ?>
        <!-- Import Settings Modal -->
        <div class="smo-modal" id="smo-import-modal" style="display: none;">
            <div class="smo-modal-overlay"></div>
            <div class="smo-modal-content">
                <div class="smo-modal-header">
                    <h3><?php esc_html_e('Import Settings', 'smo-social'); ?></h3>
                    <button class="smo-modal-close">&times;</button>
                </div>
                <div class="smo-modal-body">
                    <textarea id="smo-import-data" class="smo-form-textarea" placeholder="<?php esc_attr_e('Paste exported settings JSON here...', 'smo-social'); ?>"></textarea>
                </div>
                <div class="smo-modal-footer">
                    <button class="smo-btn smo-btn-secondary smo-modal-close"><?php esc_html_e('Cancel', 'smo-social'); ?></button>
                    <button class="smo-btn smo-btn-primary" id="smo-do-import"><?php esc_html_e('Import', 'smo-social'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}