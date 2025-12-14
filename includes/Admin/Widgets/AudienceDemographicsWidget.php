<?php
/**
 * SMO Social Audience Demographics Widget
 *
 * Displays audience demographics summary
 */

namespace SMO_Social\Admin\Widgets;

class AudienceDemographicsWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'audience-demographics';
        $this->name = __('Audience Demographics', 'smo-social');
        $this->description = __('Key insights about your audience', 'smo-social');
        $this->category = 'analytics';
        $this->icon = '👥';
        $this->default_size = 'medium';
        $this->capabilities = array('view_woocommerce_reports');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $demographics = $this->get_demographics_data($settings);

        $html = '<div class="smo-audience-demographics">';

        foreach ($demographics as $metric) {
            $html .= '<div class="smo-demo-metric">';
            $html .= '<span class="smo-demo-icon">' . esc_html($metric['icon']) . '</span>';
            $html .= '<div class="smo-demo-content">';
            $html .= '<div class="smo-demo-value">' . esc_html($metric['value']) . '</div>';
            $html .= '<div class="smo-demo-label">' . esc_html($metric['label']) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<div class="smo-demo-actions">';
        $html .= '<button class="button button-small smo-view-details" data-widget-id="' . esc_attr($this->get_id()) . '">';
        $html .= __('View Details', 'smo-social');
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
        return $this->get_demographics_data($settings);
    }

    /**
     * Get demographics data
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_demographics_data($settings = array()) {
        $cache_key = 'smo_audience_demographics_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 600, function() {
            return array(
                array(
                    'icon' => '🎯',
                    'value' => '25-34',
                    'label' => __('Primary Age Group', 'smo-social')
                ),
                array(
                    'icon' => '🌍',
                    'value' => 'United States',
                    'label' => __('Top Location', 'smo-social')
                ),
                array(
                    'icon' => '⚧',
                    'value' => '52% Female',
                    'label' => __('Gender Split', 'smo-social')
                )
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
                'id' => 'show_details_button',
                'type' => 'checkbox',
                'label' => __('Show Details Button', 'smo-social'),
                'default' => true
            )
        );
    }
}