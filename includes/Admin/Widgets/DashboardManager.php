<?php
/**
 * SMO Social Dashboard Manager
 *
 * Handles dashboard layout, drag-and-drop functionality, and widget management interface
 */

namespace SMO_Social\Admin\Widgets;

class DashboardManager {
    /**
     * Initialize the dashboard manager
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_smo_save_dashboard_layout', array(__CLASS__, 'ajax_save_layout'));
        add_action('wp_ajax_smo_get_widget_library', array(__CLASS__, 'ajax_get_widget_library'));
        add_action('wp_ajax_smo_add_widget_to_dashboard', array(__CLASS__, 'ajax_add_widget'));
        add_action('wp_ajax_smo_remove_widget_from_dashboard', array(__CLASS__, 'ajax_remove_widget'));
    }

    /**
     * Enqueue dashboard scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }

        // Dashboard specific assets
        wp_enqueue_script(
            'smo-dashboard-widgets',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/dashboard-widgets.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'),
            SMO_SOCIAL_VERSION,
            true
        );

        wp_enqueue_style(
            'smo-dashboard-widgets',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/dashboard-widgets.css',
            array(),
            SMO_SOCIAL_VERSION
        );

        // Localize script
        wp_localize_script('smo-dashboard-widgets', 'smo_dashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_dashboard_nonce'),
            'strings' => array(
                'confirm_remove' => __('Are you sure you want to remove this widget?', 'smo-social'),
                'saving' => __('Saving...', 'smo-social'),
                'saved' => __('Layout saved', 'smo-social'),
                'error' => __('Error saving layout', 'smo-social'),
                'loading' => __('Loading...', 'smo-social')
            )
        ));
    }

    /**
     * Render the customizable dashboard
     *
     * @param array $config User configuration
     * @return string HTML output
     */
    public static function render_dashboard($config = null) {
        if (!$config) {
            $config = WidgetManager::get_user_config();
        }

        ob_start();
        ?>
        <div class="smo-custom-dashboard" id="smo-custom-dashboard">
            <!-- Dashboard Toolbar -->
            <div class="smo-dashboard-toolbar">
                <div class="smo-toolbar-left">
                    <h2><?php _e('Dashboard Widgets', 'smo-social'); ?></h2>
                </div>

                <div class="smo-toolbar-right">
                    <button type="button" class="button" id="smo-add-widget-btn">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Add Widget', 'smo-social'); ?>
                    </button>

                    <button type="button" class="button" id="smo-dashboard-settings-btn">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Settings', 'smo-social'); ?>
                    </button>

                    <button type="button" class="button button-primary" id="smo-save-layout-btn">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Layout', 'smo-social'); ?>
                    </button>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="smo-dashboard-grid" id="smo-dashboard-grid">
                <?php echo self::render_dashboard_rows($config); ?>
            </div>

            <!-- Empty State -->
            <div class="smo-dashboard-empty" id="smo-dashboard-empty" style="display: none;">
                <div class="smo-empty-content">
                    <span class="dashicons dashicons-layout"></span>
                    <h3><?php _e('No widgets added yet', 'smo-social'); ?></h3>
                    <p><?php _e('Add widgets to customize your dashboard', 'smo-social'); ?></p>
                    <button type="button" class="button button-primary" id="smo-add-first-widget-btn">
                        <?php _e('Add Your First Widget', 'smo-social'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Widget Library Modal -->
        <div id="smo-widget-library-modal" class="smo-modal" style="display: none;">
            <div class="smo-modal-content large-modal">
                <span class="smo-modal-close">&times;</span>
                <h3><?php _e('Add Widget to Dashboard', 'smo-social'); ?></h3>
                <div class="smo-modal-body">
                    <div class="smo-widget-categories" id="smo-widget-categories">
                        <!-- Categories will be loaded here -->
                    </div>
                    <div class="smo-widget-library" id="smo-widget-library">
                        <!-- Widgets will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Settings Modal -->
        <div id="smo-dashboard-settings-modal" class="smo-modal" style="display: none;">
            <div class="smo-modal-content">
                <span class="smo-modal-close">&times;</span>
                <h3><?php _e('Dashboard Settings', 'smo-social'); ?></h3>
                <div class="smo-modal-body">
                    <form id="smo-dashboard-settings-form">
                        <div class="smo-form-group">
                            <label for="smo-dashboard-name">
                                <?php _e('Dashboard Name', 'smo-social'); ?>
                            </label>
                            <input type="text" id="smo-dashboard-name" name="dashboard_name"
                                   value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'smo_dashboard_name', true) ?: __('My Dashboard', 'smo-social')); ?>">
                        </div>

                        <div class="smo-form-group">
                            <label for="smo-dashboard-columns">
                                <?php _e('Layout Columns', 'smo-social'); ?>
                            </label>
                            <select id="smo-dashboard-columns" name="dashboard_columns">
                                <option value="1" <?php selected(get_user_meta(get_current_user_id(), 'smo_dashboard_columns', true), '1'); ?>>
                                    <?php _e('Single Column', 'smo-social'); ?>
                                </option>
                                <option value="2" <?php selected(get_user_meta(get_current_user_id(), 'smo_dashboard_columns', true) ?: '2', '2'); ?>>
                                    <?php _e('Two Columns', 'smo-social'); ?>
                                </option>
                                <option value="3" <?php selected(get_user_meta(get_current_user_id(), 'smo_dashboard_columns', true), '3'); ?>>
                                    <?php _e('Three Columns', 'smo-social'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="smo-form-group">
                            <label>
                                <input type="checkbox" name="auto_refresh" value="1"
                                       <?php checked(get_user_meta(get_current_user_id(), 'smo_dashboard_auto_refresh', true), '1'); ?>>
                                <?php _e('Auto-refresh widgets', 'smo-social'); ?>
                            </label>
                        </div>

                        <div class="smo-form-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e('Save Settings', 'smo-social'); ?>
                            </button>
                            <button type="button" class="button" id="smo-reset-dashboard-btn">
                                <?php _e('Reset to Default', 'smo-social'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render dashboard rows and widgets
     *
     * @param array $config Dashboard configuration
     * @return string HTML output
     */
    private static function render_dashboard_rows($config) {
        $html = '';

        if (!empty($config['layout'])) {
            foreach ($config['layout'] as $row_index => $row) {
                $html .= '<div class="smo-dashboard-row" data-row="' . $row_index . '">';

                foreach ($row as $widget_config) {
                    $widget_id = $widget_config['id'];
                    $widget = WidgetManager::get_widget($widget_id);

                    if ($widget && $widget->is_enabled()) {
                        $settings = isset($config['settings'][$widget_id]) ? $config['settings'][$widget_id] : array();
                        $settings['size'] = isset($widget_config['size']) ? $widget_config['size'] : $widget->get_default_size();

                        $html .= '<div class="smo-dashboard-widget-wrapper" data-widget-id="' . esc_attr($widget_id) . '">';
                        $html .= '<div class="smo-widget-controls">';
                        $html .= '<button type="button" class="smo-widget-remove-btn" data-widget-id="' . esc_attr($widget_id) . '">';
                        $html .= '<span class="dashicons dashicons-no"></span>';
                        $html .= '</button>';
                        $html .= '<span class="smo-widget-drag-handle dashicons dashicons-move"></span>';
                        $html .= '</div>';

                        $html .= $widget->render($settings);
                        $html .= '</div>';
                    }
                }

                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * AJAX handler for saving dashboard layout
     */
    public static function ajax_save_layout() {
        check_ajax_referer('smo_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        $layout = isset($_POST['layout']) ? json_decode(stripslashes($_POST['layout']), true) : array();

        if (empty($layout)) {
            wp_send_json_error(__('Invalid layout data'));
        }

        // Save layout to user meta
        $config = WidgetManager::get_user_config();
        $config['layout'] = $layout;

        $saved = WidgetManager::save_user_config($config);

        if ($saved) {
            wp_send_json_success(__('Dashboard layout saved successfully', 'smo-social'));
        } else {
            wp_send_json_error(__('Failed to save dashboard layout', 'smo-social'));
        }
    }

    /**
     * AJAX handler for getting widget library
     */
    public static function ajax_get_widget_library() {
        check_ajax_referer('smo_dashboard_nonce', 'nonce');

        $widgets = WidgetManager::get_widgets();
        $categories = WidgetManager::get_categories();

        $library_data = array();

        foreach ($categories as $category_slug => $category_name) {
            $library_data[$category_slug] = array(
                'name' => $category_name,
                'widgets' => array()
            );
        }

        foreach ($widgets as $widget) {
            if ($widget->is_enabled()) {
                $category = $widget->get_category();
                if (!isset($library_data[$category])) {
                    $library_data[$category] = array(
                        'name' => ucfirst($category),
                        'widgets' => array()
                    );
                }

                $library_data[$category]['widgets'][] = array(
                    'id' => $widget->get_id(),
                    'name' => $widget->get_name(),
                    'description' => $widget->get_description(),
                    'icon' => $widget->get_icon(),
                    'size' => $widget->get_default_size()
                );
            }
        }

        wp_send_json_success($library_data);
    }

    /**
     * AJAX handler for adding widget to dashboard
     */
    public static function ajax_add_widget() {
        check_ajax_referer('smo_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $position = isset($_POST['position']) ? intval($_POST['position']) : 0;

        if (!$widget_id) {
            wp_send_json_error(__('Widget ID is required'));
        }

        $widget = WidgetManager::get_widget($widget_id);
        if (!$widget || !$widget->is_enabled()) {
            wp_send_json_error(__('Widget not found or not available'));
        }

        // Get current config
        $config = WidgetManager::get_user_config();

        // Add widget to enabled widgets
        if (!in_array($widget_id, $config['enabled_widgets'])) {
            $config['enabled_widgets'][] = $widget_id;
        }

        // Add widget to layout
        if (!isset($config['layout']) || empty($config['layout'])) {
            $config['layout'] = array();
        }

        // Add to first row or create new row
        if (empty($config['layout'])) {
            $config['layout'][] = array(
                array('id' => $widget_id, 'size' => $widget->get_default_size())
            );
        } else {
            $config['layout'][0][] = array('id' => $widget_id, 'size' => $widget->get_default_size());
        }

        $saved = WidgetManager::save_user_config($config);

        if ($saved) {
            ob_start();
            $settings = array('size' => $widget->get_default_size());
            echo '<div class="smo-dashboard-widget-wrapper" data-widget-id="' . esc_attr($widget_id) . '">';
            echo '<div class="smo-widget-controls">';
            echo '<button type="button" class="smo-widget-remove-btn" data-widget-id="' . esc_attr($widget_id) . '">';
            echo '<span class="dashicons dashicons-no"></span>';
            echo '</button>';
            echo '<span class="smo-widget-drag-handle dashicons dashicons-move"></span>';
            echo '</div>';
            echo $widget->render($settings);
            echo '</div>';
            $html = ob_get_clean();

            wp_send_json_success(array(
                'html' => $html,
                'message' => sprintf(__('Widget "%s" added successfully', 'smo-social'), $widget->get_name())
            ));
        } else {
            wp_send_json_error(__('Failed to add widget', 'smo-social'));
        }
    }

    /**
     * AJAX handler for removing widget from dashboard
     */
    public static function ajax_remove_widget() {
        check_ajax_referer('smo_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');

        if (!$widget_id) {
            wp_send_json_error(__('Widget ID is required'));
        }

        // Get current config
        $config = WidgetManager::get_user_config();

        // Remove from enabled widgets
        if (isset($config['enabled_widgets']) && ($key = array_search($widget_id, $config['enabled_widgets'])) !== false) {
            unset($config['enabled_widgets'][$key]);
            $config['enabled_widgets'] = array_values($config['enabled_widgets']);
        }

        // Remove from layout
        if (isset($config['layout'])) {
            foreach ($config['layout'] as $row_index => $row) {
                foreach ($row as $col_index => $widget_config) {
                    if ($widget_config['id'] === $widget_id) {
                        unset($config['layout'][$row_index][$col_index]);
                        $config['layout'][$row_index] = array_values($config['layout'][$row_index]);

                        // Remove empty rows
                        if (empty($config['layout'][$row_index])) {
                            unset($config['layout'][$row_index]);
                            $config['layout'] = array_values($config['layout']);
                        }
                        break 2;
                    }
                }
            }
        }

        $saved = WidgetManager::save_user_config($config);

        if ($saved) {
            wp_send_json_success(__('Widget removed successfully', 'smo-social'));
        } else {
            wp_send_json_error(__('Failed to remove widget', 'smo-social'));
        }
    }
}