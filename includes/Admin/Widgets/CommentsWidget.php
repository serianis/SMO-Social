<?php
/**
 * SMO Social Comments Widget
 *
 * Displays recent comments and engagement
 */

namespace SMO_Social\Admin\Widgets;

class CommentsWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'comments';
        $this->name = __('Comments', 'smo-social');
        $this->description = __('Recent comments and interactions', 'smo-social');
        $this->category = 'engagement';
        $this->icon = '💬';
        $this->default_size = 'medium';
        $this->capabilities = array('moderate_comments');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $comments = $this->get_recent_comments($settings);

        $html = '<div class="smo-comments-widget">';

        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $html .= '<div class="smo-comment-item">';
                $html .= '<div class="smo-comment-author">' . esc_html($comment['author']) . '</div>';
                $html .= '<div class="smo-comment-content">' . esc_html($comment['content']) . '</div>';
                $html .= '<div class="smo-comment-meta">' . esc_html($comment['platform']) . ' • ' . esc_html($comment['time']) . '</div>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>' . __('No recent comments', 'smo-social') . '</p>';
        }

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
        return $this->get_recent_comments($settings);
    }

    /**
     * Get recent comments
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_recent_comments($settings = array()) {
        $cache_key = 'smo_recent_comments_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 300, function() {
            // Mock comments data
            return array(
                array(
                    'author' => 'John Doe',
                    'content' => 'Great post! Thanks for sharing.',
                    'platform' => 'Facebook',
                    'time' => '2 hours ago'
                ),
                array(
                    'author' => 'Jane Smith',
                    'content' => 'This is very helpful.',
                    'platform' => 'Twitter',
                    'time' => '4 hours ago'
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
                'id' => 'comments_limit',
                'type' => 'select',
                'label' => __('Number of Comments', 'smo-social'),
                'options' => array(
                    '3' => __('3 comments', 'smo-social'),
                    '5' => __('5 comments', 'smo-social'),
                    '10' => __('10 comments', 'smo-social')
                ),
                'default' => '3'
            )
        );
    }
}