<?php
/**
 * SMO Social Widget Manager
 *
 * Manages widget registration, loading, and user configurations
 */

namespace SMO_Social\Admin\Widgets;

class WidgetManager
{
    /**
     * Registered widgets
     *
     * @var array
     */
    private static $widgets = array();

    /**
     * Widget categories
     *
     * @var array
     */
    private static $categories = array(
        'overview' => 'Overview',
        'analytics' => 'Analytics',
        'content' => 'Content',
        'engagement' => 'Engagement',
        'performance' => 'Performance',
        'automation' => 'Automation',
        'team' => 'Team & Collaboration'
    );

    /**
     * Register a widget
     *
     * @param WidgetInterface $widget Widget instance
     */
    public static function register_widget(WidgetInterface $widget)
    {
        self::$widgets[$widget->get_id()] = $widget;
    }

    /**
     * Get registered widget by ID
     *
     * @param string $widget_id Widget ID
     * @return WidgetInterface|null
     */
    public static function get_widget($widget_id)
    {
        return isset(self::$widgets[$widget_id]) ? self::$widgets[$widget_id] : null;
    }

    /**
     * Get all registered widgets
     *
     * @return array
     */
    public static function get_widgets()
    {
        return self::$widgets;
    }

    /**
     * Get widgets by category
     *
     * @param string $category Category slug
     * @return array
     */
    public static function get_widgets_by_category($category)
    {
        $widgets = array();

        foreach (self::$widgets as $widget) {
            if ($widget->get_category() === $category && $widget->is_enabled()) {
                $widgets[] = $widget;
            }
        }

        return $widgets;
    }

    /**
     * Get available categories
     *
     * @return array
     */
    public static function get_categories()
    {
        return self::$categories;
    }

    /**
     * Get user widget configuration
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_user_config($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $config = get_user_meta($user_id, 'smo_dashboard_widgets_config', true);

        if (!$config) {
            $config = self::get_default_config();
        }

        return $config;
    }

    /**
     * Save user widget configuration
     *
     * @param array $config Configuration array
     * @param int $user_id User ID
     * @return bool
     */
    public static function save_user_config($config, $user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return update_user_meta($user_id, 'smo_dashboard_widgets_config', $config);
    }

    /**
     * Get default widget configuration
     *
     * @return array
     */
    public static function get_default_config()
    {
        return array(
            'layout' => array(
                array(
                    array('id' => 'overview', 'size' => 'medium'),
                    array('id' => 'posts-chart', 'size' => 'medium')
                ),
                array(
                    array('id' => 'engagement-metrics', 'size' => 'large')
                ),
                array(
                    array('id' => 'platform-performance', 'size' => 'medium'),
                    array('id' => 'content-analytics', 'size' => 'medium')
                )
            ),
            'enabled_widgets' => array('overview', 'posts-chart', 'engagement-metrics', 'platform-performance', 'content-analytics'),
            'settings' => array()
        );
    }

    /**
     * Render dashboard widgets
     *
     * @param array $config User configuration
     * @return string HTML output
     */
    public static function render_dashboard($config = null)
    {
        if (!$config) {
            $config = self::get_user_config();
        }

        $html = '<div class="smo-dashboard-grid-widgets" id="smo-dashboard-widgets">';

        if (!empty($config['layout'])) {
            foreach ($config['layout'] as $row) {
                $html .= '<div class="smo-dashboard-row">';

                foreach ($row as $widget_config) {
                    $widget_id = $widget_config['id'];
                    $widget = self::get_widget($widget_id);

                    if ($widget && $widget->is_enabled()) {
                        $settings = isset($config['settings'][$widget_id]) ? $config['settings'][$widget_id] : array();
                        $settings['size'] = isset($widget_config['size']) ? $widget_config['size'] : $widget->get_default_size();

                        $html .= $widget->render($settings);
                    }
                }

                $html .= '</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get widget data via AJAX
     *
     * @param string $widget_id Widget ID
     * @param array $settings Widget settings
     * @return array
     */
    public static function get_widget_data($widget_id, $settings = array())
    {
        $widget = self::get_widget($widget_id);

        if (!$widget || !$widget->is_enabled()) {
            return array('error' => 'Widget not found or not enabled');
        }

        return $widget->get_data($settings);
    }

    /**
     * Initialize the widget system
     */
    public static function init()
    {
        // Register default widgets
        self::register_default_widgets();

        // Add AJAX handlers
        add_action('wp_ajax_smo_get_widget_data', array(__CLASS__, 'ajax_get_widget_data'));
        add_action('wp_ajax_smo_save_widget_config', array(__CLASS__, 'ajax_save_widget_config'));
        add_action('wp_ajax_smo_reset_widget_config', array(__CLASS__, 'ajax_reset_widget_config'));
    }

    /**
     * Register default widgets
     */
    private static function register_default_widgets()
    {
        $widgets_to_register = array(
            // Overview widgets
            '\SMO_Social\Admin\Widgets\OverviewWidget',
            '\SMO_Social\Admin\Widgets\QuickStatsWidget',

            // Analytics widgets
            '\SMO_Social\Admin\Widgets\PostsChartWidget',
            '\SMO_Social\Admin\Widgets\EngagementMetricsWidget',
            '\SMO_Social\Admin\Widgets\PlatformPerformanceWidget',
            '\SMO_Social\Admin\Widgets\ContentAnalyticsWidget',
            '\SMO_Social\Admin\Widgets\AudienceDemographicsWidget',

            // Content widgets
            '\SMO_Social\Admin\Widgets\ContentIdeasWidget',
            '\SMO_Social\Admin\Widgets\ContentImportWidget',

            // Engagement widgets
            '\SMO_Social\Admin\Widgets\CommentsWidget',
            '\SMO_Social\Admin\Widgets\RecentActivityWidget',

            // Team widgets
            '\SMO_Social\Admin\Widgets\TeamActivityWidget',
            '\SMO_Social\Admin\Widgets\ApprovalWorkflowWidget',

            // New Feature Widgets
            '\SMO_Social\Admin\Widgets\PostsPerDayWidget',
            '\SMO_Social\Admin\Widgets\AutoPublishWidget',
            '\SMO_Social\Admin\Widgets\LinkPostsWidget'
        );

        foreach ($widgets_to_register as $widget_class) {
            try {
                if (class_exists($widget_class)) {
                    self::register_widget(new $widget_class());
                } else {
                    error_log("SMO Social Debug: Widget class {$widget_class} does not exist");
                }
            } catch (\Exception $e) {
                error_log("SMO Social Debug: Failed to register widget {$widget_class}: " . $e->getMessage());
            }
        }
    }

    /**
     * AJAX handler for getting widget data
     */
    public static function ajax_get_widget_data()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : array();

        if (!$widget_id) {
            wp_send_json_error('Widget ID is required');
        }

        $data = self::get_widget_data($widget_id, $settings);
        wp_send_json_success($data);
    }

    /**
     * AJAX handler for saving widget configuration
     */
    public static function ajax_save_widget_config()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : array();

        if (empty($config)) {
            wp_send_json_error('Configuration is required');
        }

        $saved = self::save_user_config($config);

        if ($saved) {
            wp_send_json_success('Configuration saved successfully');
        } else {
            wp_send_json_error('Failed to save configuration');
        }
    }

    /**
     * AJAX handler for resetting widget configuration
     */
    public static function ajax_reset_widget_config()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $default_config = self::get_default_config();
        $saved = self::save_user_config($default_config);

        if ($saved) {
            wp_send_json_success(array('config' => $default_config));
        } else {
            wp_send_json_error('Failed to reset configuration');
        }
    }
}