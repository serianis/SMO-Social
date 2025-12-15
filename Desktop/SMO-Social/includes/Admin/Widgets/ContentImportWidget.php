<?php
/**
 * SMO Social Content Import Widget
 *
 * Displays content import options and status
 */

namespace SMO_Social\Admin\Widgets;

class ContentImportWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'content-import';
        $this->name = __('Content Import', 'smo-social');
        $this->description = __('Import content from external sources', 'smo-social');
        $this->category = 'content';
        $this->icon = '📥';
        $this->default_size = 'small';
        $this->capabilities = array('edit_posts');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $import_status = $this->get_import_status($settings);

        $html = '<div class="smo-content-import">';

        $html .= '<div class="smo-import-status">';
        $html .= '<div class="smo-import-count">' . $this->format_number($import_status['imported_today']) . '</div>';
        $html .= '<div class="smo-import-label">' . __('Imported Today', 'smo-social') . '</div>';
        $html .= '</div>';

        $html .= '<div class="smo-import-actions">';
        $html .= '<button class="button button-small smo-import-content" data-widget-id="' . esc_attr($this->get_id()) . '">';
        $html .= __('Import Content', 'smo-social');
        $html .= '</button>';
        $html .= '</div>';

        $html .= '</div>';

        return $this->render_wrapper($html, $settings);
    }

    /**
     * Get widget data
     *
     * @param array $settings Widget settings
     * @return array
     */
    public function get_data($settings = array()) {
        return $this->get_import_status($settings);
    }

    /**
     * Get import status
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_import_status($settings = array()) {
        $cache_key = 'smo_content_import_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 300, function() {
            return array(
                'imported_today' => rand(0, 10),
                'total_imported' => rand(50, 200)
            );
        });
    }

    /**
     * Get widget settings fields
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            array(
                'id' => 'show_import_button',
                'type' => 'checkbox',
                'label' => __('Show Import Button', 'smo-social'),
                'default' => true
            )
        );
    }
}