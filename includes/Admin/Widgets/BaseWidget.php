<?php
/**
 * SMO Social Base Widget Class
 *
 * Abstract base class for all dashboard widgets
 */

namespace SMO_Social\Admin\Widgets;

abstract class BaseWidget implements WidgetInterface {
    /**
     * Widget unique identifier
     *
     * @var string
     */
    protected $id;

    /**
     * Widget display name
     *
     * @var string
     */
    protected $name;

    /**
     * Widget description
     *
     * @var string
     */
    protected $description;

    /**
     * Widget category
     *
     * @var string
     */
    protected $category;

    /**
     * Widget icon
     *
     * @var string
     */
    protected $icon;

    /**
     * Default widget size
     *
     * @var string
     */
    protected $default_size;

    /**
     * Required capabilities
     *
     * @var array
     */
    protected $capabilities;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize widget properties
     * Must be implemented by child classes
     */
    abstract protected function init();

    /**
     * Get widget unique identifier
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get widget display name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get widget description
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get widget category
     *
     * @return string
     */
    public function get_category() {
        return $this->category;
    }

    /**
     * Get widget icon
     *
     * @return string
     */
    public function get_icon() {
        return $this->icon;
    }

    /**
     * Get default widget size
     *
     * @return string
     */
    public function get_default_size() {
        return $this->default_size;
    }

    /**
     * Get widget capabilities
     *
     * @return array
     */
    public function get_capabilities() {
        return $this->capabilities ?: array('manage_options');
    }

    /**
     * Check if widget is enabled for current user
     *
     * @return bool
     */
    public function is_enabled() {
        $capabilities = $this->get_capabilities();
        foreach ($capabilities as $cap) {
            if (!current_user_can($cap)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get widget settings fields
     *
     * @return array
     */
    public function get_settings_fields() {
        return array();
    }

    /**
     * Render widget header
     *
     * @param array $settings Widget settings
     * @return string
     */
    protected function render_header($settings = array()) {
        $title = isset($settings['title']) ? $settings['title'] : $this->get_name();
        $show_settings = isset($settings['show_settings']) ? $settings['show_settings'] : true;

        $html = '<div class="smo-widget-header">';
        $html .= '<h3 class="smo-widget-title">' . esc_html($title) . '</h3>';

        if ($show_settings && !empty($this->get_settings_fields())) {
            $html .= '<button type="button" class="smo-widget-settings-btn" data-widget-id="' . esc_attr($this->get_id()) . '">';
            $html .= '<span class="dashicons dashicons-admin-generic"></span>';
            $html .= '</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render widget content wrapper
     *
     * @param string $content Inner content
     * @param array $settings Widget settings
     * @return string
     */
    protected function render_wrapper($content, $settings = array()) {
        $size = isset($settings['size']) ? $settings['size'] : $this->get_default_size();
        $classes = array('smo-dashboard-widget', 'smo-widget-' . $this->get_id(), 'smo-widget-size-' . $size);

        if (isset($settings['custom_class'])) {
            $classes[] = $settings['custom_class'];
        }

        $html = '<div class="' . esc_attr(implode(' ', $classes)) . '" data-widget-id="' . esc_attr($this->get_id()) . '">';
        $html .= $this->render_header($settings);
        $html .= '<div class="smo-widget-content">';
        $html .= $content;
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get cached data or fetch fresh data
     *
     * @param string $cache_key Cache key
     * @param int $cache_time Cache time in seconds
     * @param callable $callback Data fetching callback
     * @return mixed
     */
    protected function get_cached_data($cache_key, $cache_time = 300, $callback = null) {
        $cache = get_transient($cache_key);

        if ($cache !== false) {
            return $cache;
        }

        if ($callback && is_callable($callback)) {
            $data = call_user_func($callback);
            set_transient($cache_key, $data, $cache_time);
            return $data;
        }

        return null;
    }

    /**
     * Clear widget cache
     *
     * @param string $cache_key Cache key
     */
    protected function clear_cache($cache_key) {
        delete_transient($cache_key);
    }

    /**
     * Format number for display
     *
     * @param int|float $number Number to format
     * @param int $decimals Number of decimals
     * @return string
     */
    protected function format_number($number, $decimals = 0) {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        }

        return number_format($number, $decimals);
    }

    /**
     * Format percentage for display
     *
     * @param float $percentage Percentage value
     * @param int $decimals Number of decimals
     * @return string
     */
    protected function format_percentage($percentage, $decimals = 1) {
        return number_format($percentage, $decimals) . '%';
    }

    /**
     * Get widget data for AJAX calls
     *
     * @param array $settings Widget settings
     * @return array
     */
    public function get_data($settings = array()) {
        // Default implementation - override in child classes
        return array();
    }
}