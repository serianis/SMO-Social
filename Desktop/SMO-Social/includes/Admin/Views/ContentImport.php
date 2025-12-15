<?php
/**
 * Content Import & Automation View - Enhanced
 * 
 * Comprehensive interface for content import, automation rules, and transformation
 * Features: Modern UI, Gradient Design, Responsive Layout, Smooth Animations
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../../Content/ContentImportManager.php';
require_once __DIR__ . '/../../Automation/ImportAutomationManager.php';

/**
 * Content Import & Automation Class
 */
class ContentImport {
    
    private $content_import_manager;
    private $automation_manager;
    
    public function __construct() {
        $this->content_import_manager = new \SMO_Social\Content\ContentImportManager();
        
        if (class_exists('\\SMO_Social\\Automation\\ImportAutomationManager')) {
            $this->automation_manager = new \SMO_Social\Automation\ImportAutomationManager();
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX handlers for content import
        add_action('wp_ajax_smo_get_import_dashboard_stats', array($this, 'ajax_get_import_dashboard_stats'));
        add_action('wp_ajax_smo_get_recent_imports', array($this, 'ajax_get_recent_imports'));
        add_action('wp_ajax_smo_bulk_import_from_source', array($this, 'ajax_bulk_import_from_source'));
        add_action('wp_ajax_smo_get_automation_performance', array($this, 'ajax_get_automation_performance'));
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
     * Render the Content Import & Automation page
     */
    public function render() {
        // Use Common Layout with AppLayout helpers
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('content-import', __('Content Import', 'smo-social'));
            
            // Render standardized gradient header using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_header([
                'icon' => '📥',
                'title' => __('Content Import & Automation', 'smo-social'),
                'subtitle' => __('Import and automate content from various sources with intelligent transformation', 'smo-social'),
                'actions' => [
                    [
                        'id' => 'smo-add-import-source',
                        'label' => __('Add Source', 'smo-social'),
                        'icon' => 'plus-alt',
                        'class' => 'smo-btn-primary'
                    ],
                    [
                        'id' => 'smo-create-automation-rule',
                        'label' => __('Create Rule', 'smo-social'),
                        'icon' => 'admin-tools',
                        'class' => 'smo-btn-secondary'
                    ]
                ]
            ]);
            
            // Render standardized stats dashboard using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
                [
                    'icon' => 'cloud-upload',
                    'value' => '-',
                    'label' => __('Total Imported', 'smo-social'),
                    'trend' => '📈 All time',
                    'id' => 'total-imported-items'
                ],
                [
                    'icon' => 'admin-tools',
                    'value' => '-',
                    'label' => __('Active Rules', 'smo-social'),
                    'trend' => '⚡ Running',
                    'id' => 'active-automation-rules'
                ],
                [
                    'icon' => 'update',
                    'value' => '-',
                    'label' => __('Auto-Shares Today', 'smo-social'),
                    'trend' => '🔄 Automated',
                    'id' => 'automated-shares-today'
                ],
                [
                    'icon' => 'chart-line',
                    'value' => '-',
                    'label' => __('Success Rate', 'smo-social'),
                    'trend' => '✅ Last 30 days',
                    'id' => 'automation-success-rate'
                ]
            ]);
        }
        ?>
            
        <!-- Quick Actions Bar -->
        <div class="smo-quick-actions">
            <button class="smo-quick-action-btn" id="smo-sync-all-sources">
                <span class="dashicons dashicons-update"></span>
                <span><?php esc_html_e('Sync All Sources', 'smo-social'); ?></span>
            </button>
            <button class="smo-quick-action-btn" id="smo-run-automation-batch">
                <span class="dashicons dashicons-controls-play"></span>
                <span><?php esc_html_e('Run Automation', 'smo-social'); ?></span>
            </button>
            <button class="smo-quick-action-btn" id="smo-view-logs">
                <span class="dashicons dashicons-list-view"></span>
                <span><?php esc_html_e('View Logs', 'smo-social'); ?></span>
            </button>
            <button class="smo-quick-action-btn" id="smo-export-settings">
                <span class="dashicons dashicons-download"></span>
                <span><?php esc_html_e('Export Settings', 'smo-social'); ?></span>
            </button>
        </div>
            
        <!-- Main Content Tabs -->
        <div class="smo-card">
            <div class="smo-tabs-wrapper">
                <nav class="smo-tabs-nav">
                    <a href="#import-sources" class="smo-tab-link active" data-tab="import-sources">
                        <span class="dashicons dashicons-cloud-upload"></span>
                        <?php esc_html_e('Import Sources', 'smo-social'); ?>
                        <span class="smo-tab-badge" id="sources-count">0</span>
                    </a>
                    <a href="#automation-rules" class="smo-tab-link" data-tab="automation-rules">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php esc_html_e('Automation Rules', 'smo-social'); ?>
                        <span class="smo-tab-badge" id="rules-count">0</span>
                    </a>
                    <a href="#transformation-templates" class="smo-tab-link" data-tab="transformation-templates">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e('Templates', 'smo-social'); ?>
                        <span class="smo-tab-badge" id="templates-count">0</span>
                    </a>
                    <a href="#imported-content" class="smo-tab-link" data-tab="imported-content">
                        <span class="dashicons dashicons-media-default"></span>
                        <?php esc_html_e('Imported Content', 'smo-social'); ?>
                    </a>
                    <a href="#analytics" class="smo-tab-link" data-tab="analytics">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php esc_html_e('Analytics', 'smo-social'); ?>
                    </a>
                </nav>
                
                <div class="smo-tabs-content">
                    <div class="smo-tab-panel active" id="import-sources-panel">
                        <?php $this->render_import_sources_panel(); ?>
                    </div>
                    <div class="smo-tab-panel" id="automation-rules-panel">
                        <?php $this->render_automation_rules_panel(); ?>
                    </div>
                    <div class="smo-tab-panel" id="transformation-templates-panel">
                        <?php $this->render_templates_panel(); ?>
                    </div>
                    <div class="smo-tab-panel" id="imported-content-panel">
                        <?php $this->render_imported_content_panel(); ?>
                    </div>
                    <div class="smo-tab-panel" id="analytics-panel">
                        <?php $this->render_analytics_panel(); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $this->render_modals();
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        }
    }
    
    private function render_import_sources_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Content Import Sources', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Connect and manage your content sources for automated importing', 'smo-social'); ?></p>
            </div>
        </div>
        <div id="import-sources-list" class="smo-sources-grid">
            <div class="smo-loading">
                <div class="smo-spinner"></div>
                <p><?php esc_html_e('Loading import sources...', 'smo-social'); ?></p>
            </div>
        </div>
        <?php
    }
    
    private function render_automation_rules_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Automation Rules', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Create intelligent rules to automate content transformation and publishing', 'smo-social'); ?></p>
            </div>
        </div>
        <div id="automation-rules-list" class="smo-rules-grid">
            <div class="smo-loading">
                <div class="smo-spinner"></div>
                <p><?php esc_html_e('Loading automation rules...', 'smo-social'); ?></p>
            </div>
        </div>
        <?php
    }
    
    private function render_templates_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Content Transformation Templates', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Pre-designed templates to transform imported content into engaging posts', 'smo-social'); ?></p>
            </div>
        </div>
        <div id="templates-list" class="smo-templates-grid">
            <div class="smo-loading">
                <div class="smo-spinner"></div>
                <p><?php esc_html_e('Loading templates...', 'smo-social'); ?></p>
            </div>
        </div>
        <?php
    }
    
    private function render_imported_content_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Imported Content Library', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Browse, manage, and publish your imported content', 'smo-social'); ?></p>
            </div>
        </div>
        <div id="imported-content-list" class="smo-content-grid">
            <div class="smo-loading">
                <div class="smo-spinner"></div>
                <p><?php esc_html_e('Loading imported content...', 'smo-social'); ?></p>
            </div>
        </div>
        <?php
    }
    
    private function render_analytics_panel() {
        ?>
        <div class="smo-panel-header">
            <div class="smo-panel-title">
                <h2><?php esc_html_e('Automation Performance Analytics', 'smo-social'); ?></h2>
                <p><?php esc_html_e('Track the performance and efficiency of your automation workflows', 'smo-social'); ?></p>
            </div>
        </div>
        <div id="performance-analytics-content" class="smo-analytics-content">
            <div class="smo-analytics-grid">
                <div class="smo-analytics-chart-container">
                    <h3><?php esc_html_e('Import Trends', 'smo-social'); ?></h3>
                    <canvas id="import-trends-chart"></canvas>
                </div>
                <div class="smo-analytics-chart-container">
                    <h3><?php esc_html_e('Automation Success Rate', 'smo-social'); ?></h3>
                    <canvas id="success-rate-chart"></canvas>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_modals() {
        include_once __DIR__ . '/ContentImportModal.php';
        if (class_exists('\\SMO_Social\\Admin\\Views\\ContentImportModal')) {
            \SMO_Social\Admin\Views\ContentImportModal::render();
        }
    }
    
    public function ajax_get_import_dashboard_stats() {
        check_ajax_referer('smo_content_import_enhanced', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        try {
            global $wpdb;
            $total_imported = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smo_imported_content WHERE user_id = " . get_current_user_id());
            $active_rules = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}smo_import_automation_rules WHERE user_id = %d AND is_active = 1", get_current_user_id()));
            wp_send_json_success(array(
                'total_imported' => intval($total_imported),
                'active_rules' => intval($active_rules),
                'auto_shares_today' => 0,
                'success_rate' => '0%'
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_recent_imports() {
        check_ajax_referer('smo_content_import_enhanced', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        try {
            $limit = intval($_POST['limit'] ?? 10);
            $content_items = $this->content_import_manager->get_imported_content($limit);
            wp_send_json_success($content_items);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_bulk_import_from_source() {
        check_ajax_referer('smo_content_import_enhanced', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        $source_id = intval($_POST['source_id'] ?? 0);
        try {
            $imported_count = $this->content_import_manager->sync_content_source($source_id);
            wp_send_json_success(array(
                'imported_count' => $imported_count,
                'message' => sprintf(__('Imported %d items', 'smo-social'), $imported_count)
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_automation_performance() {
        check_ajax_referer('smo_content_import_enhanced', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        try {
            $days = intval($_POST['days'] ?? 30);
            if ($this->automation_manager) {
                $analytics = $this->automation_manager->get_automation_analytics($days);
                wp_send_json_success($analytics);
            } else {
                wp_send_json_error(__('Automation manager not available', 'smo-social'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}