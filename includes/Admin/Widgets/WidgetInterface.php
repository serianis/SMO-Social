<?php
/**
 * SMO Social Widget Interface
 *
 * Defines the contract for all dashboard widgets
 */

namespace SMO_Social\Admin\Widgets;

interface WidgetInterface {
    /**
     * Get widget unique identifier
     *
     * @return string
     */
    public function get_id();

    /**
     * Get widget display name
     *
     * @return string
     */
    public function get_name();

    /**
     * Get widget description
     *
     * @return string
     */
    public function get_description();

    /**
     * Get widget category
     *
     * @return string
     */
    public function get_category();

    /**
     * Get widget icon
     *
     * @return string
     */
    public function get_icon();

    /**
     * Get default widget size (small, medium, large)
     *
     * @return string
     */
    public function get_default_size();

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array());

    /**
     * Get widget settings fields
     *
     * @return array Settings fields configuration
     */
    public function get_settings_fields();

    /**
     * Get widget data for AJAX calls
     *
     * @param array $settings Widget settings
     * @return array Data array
     */
    public function get_data($settings = array());

    /**
     * Check if widget is enabled for current user
     *
     * @return bool
     */
    public function is_enabled();

    /**
     * Get widget capabilities required
     *
     * @return array
     */
    public function get_capabilities();
}