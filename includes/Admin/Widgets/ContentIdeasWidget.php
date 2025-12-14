<?php
/**
 * SMO Social Content Ideas Widget
 *
 * Displays content ideas and suggestions
 */

namespace SMO_Social\Admin\Widgets;

class ContentIdeasWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'content-ideas';
        $this->name = __('Content Ideas', 'smo-social');
        $this->description = __('AI-powered content suggestions', 'smo-social');
        $this->category = 'content';
        $this->icon = '💡';
        $this->default_size = 'medium';
        $this->capabilities = array('edit_posts');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $ideas = $this->get_content_ideas($settings);

        $html = '<div class="smo-content-ideas">';

        if (!empty($ideas)) {
            foreach ($ideas as $idea) {
                $html .= '<div class="smo-idea-item">';
                $html .= '<div class="smo-idea-title">' . esc_html($idea['title']) . '</div>';
                $html .= '<div class="smo-idea-type">' . esc_html($idea['type']) . '</div>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>' . __('No content ideas available', 'smo-social') . '</p>';
        }

        $html .= '<div class="smo-idea-actions">';
        $html .= '<button class="button button-small smo-generate-ideas" data-widget-id="' . esc_attr($this->get_id()) . '">';
        $html .= __('Generate Ideas', 'smo-social');
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
        return $this->get_content_ideas($settings);
    }

    /**
     * Get content ideas
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_content_ideas($settings = array()) {
        $cache_key = 'smo_content_ideas_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 3600, function() {
            // Mock content ideas
            return array(
                array(
                    'title' => 'Behind the Scenes: Product Development',
                    'type' => 'Video'
                ),
                array(
                    'title' => 'Customer Success Stories',
                    'type' => 'Blog Post'
                ),
                array(
                    'title' => 'Industry Trends Q&A',
                    'type' => 'Live Stream'
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
                'id' => 'show_generate_button',
                'type' => 'checkbox',
                'label' => __('Show Generate Button', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'ideas_limit',
                'type' => 'select',
                'label' => __('Number of Ideas', 'smo-social'),
                'options' => array(
                    '3' => __('3 ideas', 'smo-social'),
                    '5' => __('5 ideas', 'smo-social'),
                    '10' => __('10 ideas', 'smo-social')
                ),
                'default' => '3'
            )
        );
    }
}