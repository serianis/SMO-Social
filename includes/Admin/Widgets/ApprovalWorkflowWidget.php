<?php
/**
 * SMO Social Approval Workflow Widget
 *
 * Displays pending approvals and workflow status
 */

namespace SMO_Social\Admin\Widgets;

class ApprovalWorkflowWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'approval-workflow';
        $this->name = __('Approval Workflow', 'smo-social');
        $this->description = __('Pending approvals and workflow items', 'smo-social');
        $this->category = 'team';
        $this->icon = '✅';
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
        $approvals = $this->get_pending_approvals($settings);

        $html = '<div class="smo-approval-workflow">';

        if (!empty($approvals)) {
            foreach ($approvals as $approval) {
                $html .= '<div class="smo-approval-item">';
                $html .= '<div class="smo-approval-title">' . esc_html($approval['title']) . '</div>';
                $html .= '<div class="smo-approval-meta">' . esc_html($approval['submitted_by']) . ' • ' . esc_html($approval['time']) . '</div>';
                $html .= '<div class="smo-approval-actions">';
                $html .= '<button class="button button-small button-primary smo-approve" data-id="' . esc_attr($approval['id']) . '">Approve</button>';
                $html .= '<button class="button button-small smo-reject" data-id="' . esc_attr($approval['id']) . '">Reject</button>';
                $html .= '</div>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>' . __('No pending approvals', 'smo-social') . '</p>';
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
        return $this->get_pending_approvals($settings);
    }

    /**
     * Get pending approvals
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_pending_approvals($settings = array()) {
        $cache_key = 'smo_pending_approvals_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 300, function() {
            // Mock approval data
            return array(
                array(
                    'id' => '1',
                    'title' => 'Q4 Marketing Campaign Post',
                    'submitted_by' => 'John Doe',
                    'time' => '2 hours ago'
                ),
                array(
                    'id' => '2',
                    'title' => 'Product Launch Announcement',
                    'submitted_by' => 'Jane Smith',
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
                'id' => 'show_actions',
                'type' => 'checkbox',
                'label' => __('Show Action Buttons', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'approvals_limit',
                'type' => 'select',
                'label' => __('Number of Approvals', 'smo-social'),
                'options' => array(
                    '3' => __('3 approvals', 'smo-social'),
                    '5' => __('5 approvals', 'smo-social'),
                    '10' => __('10 approvals', 'smo-social')
                ),
                'default' => '3'
            )
        );
    }
}