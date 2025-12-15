<?php
namespace SMO_Social\Admin;

/**
 * CommunityMarketplace - Admin interface for community features
 * 
 * Provides UI for template browsing, installation, reputation display,
 * and community contribution management.
 */
class CommunityMarketplace {
    
    private $template_manager;
    private $reputation_manager;
    private $validation_pipeline;
    
    public function __construct() {
        $this->template_manager = new \SMO_Social\Community\TemplateManager();
        $this->reputation_manager = new \SMO_Social\Community\ReputationManager();
        $this->validation_pipeline = new \SMO_Social\Community\ValidationPipeline();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_smo_get_community_data', array($this, 'ajax_get_community_data'));
        add_action('wp_ajax_smo_install_template_from_marketplace', array($this, 'ajax_install_template'));
        add_action('wp_ajax_smo_rate_template', array($this, 'ajax_rate_template'));
        add_action('wp_ajax_smo_get_reputation_data', array($this, 'ajax_get_reputation_data'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_submenu_page(
            'smo-social',
            __('Community Marketplace', 'smo-social'),
            __('Community', 'smo-social'),
            'manage_options',
            'smo-community',
            array($this, 'marketplace_page')
        );
        
        add_submenu_page(
            'smo-social',
            __('Reputation Dashboard', 'smo-social'),
            __('Reputation', 'smo-social'),
            'manage_options',
            'smo-reputation',
            array($this, 'reputation_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'smo-community') === false && 
            strpos($hook, 'smo-templates') === false && 
            strpos($hook, 'smo-reputation') === false) {
            return;
        }
        
        wp_enqueue_script(
            'smo-community-admin',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/community-admin.js',
            array('wp-element', 'wp-api-fetch'),
            SMO_SOCIAL_VERSION,
            true
        );
        
        wp_enqueue_style(
            'smo-community-admin',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/community-admin.css',
            array(),
            SMO_SOCIAL_VERSION
        );
        
        // Localize script with data
        wp_localize_script('smo-community-admin', 'smoCommunityData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_community_nonce'),
            'installedTemplates' => $this->template_manager->get_installed_templates(),
            'reputationData' => $this->reputation_manager->get_all_reputation_data()
        ));
    }
    
    /**
     * Main marketplace page
     */
    public function marketplace_page() {
        ?>
        <div class="wrap smo-community-marketplace">
            <h1><?php echo esc_html(\get_admin_page_title()); ?></h1>
            <p><?php _e('Discover and install community-contributed templates and drivers', 'smo-social'); ?></p>
            
            <div id="smo-community-app"></div>
        </div>
        <?php
    }
    
    /**
     * Templates management page
     */
    public function templates_page() {
        ?>
        <div class="wrap smo-templates-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="smo-templates-header">
                <div class="smo-templates-stats">
                    <?php $this->display_template_stats(); ?>
                </div>
                <div class="smo-templates-actions">
                    <button type="button" class="button button-primary" id="smo-create-template">
                        <?php _e('Create New Template', 'smo-social'); ?>
                    </button>
                    <button type="button" class="button" id="smo-import-template">
                        <?php _e('Import Template', 'smo-social'); ?>
                    </button>
                </div>
            </div>
            
            <div id="smo-templates-list"></div>
        </div>
        
        <style>
        .smo-templates-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .smo-templates-stats {
            display: flex;
            gap: 30px;
        }
        
        .smo-template-stat {
            text-align: center;
        }
        
        .smo-template-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .smo-template-stat-label {
            font-size: 12px;
            color: #646970;
            text-transform: uppercase;
        }
        </style>
        <?php
    }
    
    /**
     * Reputation dashboard page
     */
    public function reputation_page() {
        ?>
        <div class="wrap smo-reputation-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="smo-reputation-overview">
                <?php $this->display_reputation_overview(); ?>
            </div>
            
            <div class="smo-reputation-content">
                <div class="smo-reputation-tabs">
                    <button class="tab-button active" data-tab="verified">Verified Items</button>
                    <button class="tab-button" data-tab="top-performers">Top Performers</button>
                    <button class="tab-button" data-tab="statistics">Statistics</button>
                </div>
                
                <div id="smo-reputation-content"></div>
            </div>
        </div>
        
        <style>
        .smo-reputation-overview {
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .smo-reputation-stats {
            display: flex;
            gap: 30px;
        }
        
        .smo-reputation-stat {
            text-align: center;
            flex: 1;
        }
        
        .smo-reputation-stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #00a32a;
        }
        
        .smo-reputation-stat-label {
            font-size: 14px;
            color: #646970;
            margin-top: 5px;
        }
        
        .smo-reputation-tabs {
            display: flex;
            border-bottom: 1px solid #ccd0d4;
            margin-bottom: 20px;
        }
        
        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            color: #646970;
            font-size: 14px;
        }
        
        .tab-button.active {
            color: #2271b1;
            border-bottom-color: #2271b1;
        }
        
        .tab-button:hover {
            color: #2271b1;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.tab-button').on('click', function() {
                const tab = $(this).data('tab');
                
                $('.tab-button').removeClass('active');
                $(this).addClass('active');
                
                // Load tab content via AJAX
                loadReputationTab(tab);
            });
            
            // Load initial tab content
            loadReputationTab('verified');
            
            function loadReputationTab(tab) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_get_reputation_data',
                        tab: tab,
                        nonce: smoCommunityData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#smo-reputation-content').html(response.data.html);
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Display template statistics
     */
    private function display_template_stats() {
        $installed_templates = $this->template_manager->get_installed_templates();
        $total_templates = count($installed_templates);
        $verified_templates = count(array_filter($installed_templates, function($t) { return $t['verified']; }));
        $total_installs = array_sum(array_column($installed_templates, 'install_count'));
        
        echo '<div class="smo-template-stat">';
        echo '<div class="smo-template-stat-number">' . $total_templates . '</div>';
        echo '<div class="smo-template-stat-label">Installed Templates</div>';
        echo '</div>';
        
        echo '<div class="smo-template-stat">';
        echo '<div class="smo-template-stat-number">' . $verified_templates . '</div>';
        echo '<div class="smo-template-stat-label">Verified Templates</div>';
        echo '</div>';
        
        echo '<div class="smo-template-stat">';
        echo '<div class="smo-template-stat-number">' . $total_installs . '</div>';
        echo '<div class="smo-template-stat-label">Total Installs</div>';
        echo '</div>';
    }
    
    /**
     * Display reputation overview
     */
    private function display_reputation_overview() {
        $stats = $this->reputation_manager->get_reputation_statistics();
        
        echo '<div class="smo-reputation-stats">';
        
        echo '<div class="smo-reputation-stat">';
        echo '<div class="smo-reputation-stat-number">' . $stats['total_items'] . '</div>';
        echo '<div class="smo-reputation-stat-label">Total Items</div>';
        echo '</div>';
        
        echo '<div class="smo-reputation-stat">';
        echo '<div class="smo-reputation-stat-number">' . $stats['verified_items'] . '</div>';
        echo '<div class="smo-reputation-stat-label">Verified Items</div>';
        echo '</div>';
        
        echo '<div class="smo-reputation-stat">';
        echo '<div class="smo-reputation-stat-number">' . $stats['total_installs'] . '</div>';
        echo '<div class="smo-reputation-stat-label">Total Installs</div>';
        echo '</div>';
        
        echo '<div class="smo-reputation-stat">';
        echo '<div class="smo-reputation-stat-number">' . $stats['average_success_rate'] . '%</div>';
        echo '<div class="smo-reputation-stat-label">Avg Success Rate</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * AJAX handler for community data
     */
    public function ajax_get_community_data() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        $data = array(
            'templates' => $this->get_available_templates(),
            'reputation' => $this->reputation_manager->get_reputation_statistics(),
            'validation_results' => $this->validation_pipeline->get_all_validation_results()
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX handler for template installation
     */
    public function ajax_install_template() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!isset($_POST['template_id'])) {
            wp_send_json_error('Template ID is required');
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $template_path = SMO_SOCIAL_PLUGIN_DIR . "templates/{$template_id}.json";
        
        $result = $this->template_manager->install_template($template_path);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * AJAX handler for template rating
     */
    public function ajax_rate_template() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!isset($_POST['template_id'])) {
            wp_send_json_error('Template ID is required');
        }
        
        if (!isset($_POST['rating'])) {
            wp_send_json_error('Rating is required');
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $rating = intval($_POST['rating']);
        $review = sanitize_textarea_field($_POST['review'] ?? '');
        
        // Store rating (implementation would depend on rating storage)
        $ratings = get_option('smo_template_ratings', array());
        $ratings[$template_id][] = array(
            'rating' => $rating,
            'review' => $review,
            'date' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
        
        update_option('smo_template_ratings', $ratings);
        
        wp_send_json_success(array('message' => 'Rating submitted successfully'));
    }
    
    /**
     * AJAX handler for reputation data
     */
    public function ajax_get_reputation_data() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!isset($_POST['tab'])) {
            wp_send_json_error('Tab parameter is required');
        }
        
        $tab = sanitize_text_field($_POST['tab']);
        
        switch ($tab) {
            case 'verified':
                $data = $this->get_verified_items_html();
                break;
            case 'top-performers':
                $data = $this->get_top_performers_html();
                break;
            case 'statistics':
                $data = $this->get_detailed_statistics_html();
                break;
            default:
                $data = $this->get_verified_items_html();
        }
        
        wp_send_json_success(array('html' => $data));
    }
    
    /**
     * Get available templates (this would be expanded to fetch from external marketplace)
     */
    private function get_available_templates() {
        $templates_dir = SMO_SOCIAL_PLUGIN_DIR . 'templates/';
        $templates = array();
        
        if (is_dir($templates_dir)) {
            $files = glob($templates_dir . '*.json');
            foreach ($files as $file) {
                $template_data = json_decode(file_get_contents($file), true);
                if ($template_data) {
                    $template_id = pathinfo($file, PATHINFO_FILENAME);
                    $templates[$template_id] = $template_data;
                }
            }
        }
        
        return $templates;
    }
    
    /**
     * Get HTML for verified items
     */
    private function get_verified_items_html() {
        $verified_items = $this->reputation_manager->get_verified_items();
        
        ob_start();
        ?>
        <div class="smo-verified-items">
            <?php if (empty($verified_items)): ?>
                <p><?php _e('No verified items yet.', 'smo-social'); ?></p>
            <?php else: ?>
                <div class="smo-items-grid">
                    <?php foreach ($verified_items as $item_id => $data): ?>
                        <div class="smo-item-card">
                            <div class="smo-item-header">
                                <h3><?php echo esc_html($data['name'] ?? $item_id); ?></h3>
                                <span class="smo-verified-badge">✓ Verified</span>
                            </div>
                            <div class="smo-item-stats">
                                <span>Installs: <?php echo intval($data['install_count'] ?? 0); ?></span>
                                <span>Success Rate: <?php echo round($data['success_rate'] ?? 0, 1); ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .smo-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .smo-item-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .smo-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .smo-item-header h3 {
            margin: 0;
            font-size: 16px;
        }
        
        .smo-verified-badge {
            background: #00a32a;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .smo-item-stats {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #646970;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get HTML for top performers
     */
    private function get_top_performers_html() {
        $top_performers = $this->reputation_manager->get_top_performing_items(null, 10);
        
        ob_start();
        ?>
        <div class="smo-top-performers">
            <?php if (empty($top_performers)): ?>
                <p><?php _e('No performance data available yet.', 'smo-social'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Item', 'smo-social'); ?></th>
                            <th><?php _e('Type', 'smo-social'); ?></th>
                            <th><?php _e('Installs', 'smo-social'); ?></th>
                            <th><?php _e('Success Rate', 'smo-social'); ?></th>
                            <th><?php _e('Trust Score', 'smo-social'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_performers as $item_id => $data): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($data['name'] ?? $item_id); ?></strong>
                                    <?php if ($data['verified']): ?>
                                        <span class="smo-verified-badge" style="margin-left: 8px;">✓</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(ucfirst($data['type'] ?? 'unknown')); ?></td>
                                <td><?php echo intval($data['install_count'] ?? 0); ?></td>
                                <td><?php echo round($data['success_rate'] ?? 0, 1); ?>%</td>
                                <td><?php echo $this->reputation_manager->calculate_trust_score($item_id); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get HTML for detailed statistics
     */
    private function get_detailed_statistics_html() {
        $stats = $this->reputation_manager->get_reputation_statistics();
        
        ob_start();
        ?>
        <div class="smo-detailed-stats">
            <div class="smo-stats-grid">
                <div class="smo-stat-card">
                    <h4><?php _e('Overview', 'smo-social'); ?></h4>
                    <ul>
                        <li><?php printf(__('Total Items: %d', 'smo-social'), $stats['total_items']); ?></li>
                        <li><?php printf(__('Verified Items: %d', 'smo-social'), $stats['verified_items']); ?></li>
                        <li><?php printf(__('Verification Rate: %s%%', 'smo-social'), round(($stats['verified_items'] / max($stats['total_items'], 1)) * 100, 1)); ?></li>
                    </ul>
                </div>
                
                <div class="smo-stat-card">
                    <h4><?php _e('Installs', 'smo-social'); ?></h4>
                    <ul>
                        <li><?php printf(__('Total Installs: %d', 'smo-social'), $stats['total_installs']); ?></li>
                        <li><?php printf(__('Average per Item: %s', 'smo-social'), round($stats['total_installs'] / max($stats['total_items'], 1), 1)); ?></li>
                    </ul>
                </div>
                
                <div class="smo-stat-card">
                    <h4><?php _e('Quality Metrics', 'smo-social'); ?></h4>
                    <ul>
                        <li><?php printf(__('Average Success Rate: %s%%', 'smo-social'), $stats['average_success_rate']); ?></li>
                        <li><?php printf(__('Total Errors: %d', 'smo-social'), $stats['total_errors']); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
        .smo-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .smo-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .smo-stat-card h4 {
            margin-top: 0;
            color: #2271b1;
        }
        
        .smo-stat-card ul {
            list-style: none;
            padding: 0;
        }
        
        .smo-stat-card li {
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .smo-stat-card li:last-child {
            border-bottom: none;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}
