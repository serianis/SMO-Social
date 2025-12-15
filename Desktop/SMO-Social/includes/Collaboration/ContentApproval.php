<?php
namespace SMO_Social\Collaboration;

if (!defined('ABSPATH')) {
    exit; // Security check
}

use SMO_Social\Core\AjaxSecurityHelper;
use SMO_Social\Core\WordPressCompatibilityHelper;
use SMO_Social\Content\DataValidator;

/**
 * SMO_ContentApproval - Content Approval Workflow System
 * 
 * Handles content approval workflows, queues, notifications,
 * and team collaboration for social media content.
 */
class ContentApproval {

    private $wpdb;
    private $user_manager;
    private $table_name;

    // Approval statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DRAFT = 'draft';

    // Action types
    const ACTION_SUBMITTED = 'submitted';
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';
    const ACTION_EDITED = 'edited';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_manager = new UserManager();
        $this->table_name = $wpdb->prefix . 'smo_content_approvals';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Content workflow hooks
        add_action('smo_content_created', array($this, 'handle_new_content'), 10, 2);
        add_action('smo_content_updated', array($this, 'handle_content_update'), 10, 2);
        add_action('smo_post_published', array($this, 'handle_post_published'), 10, 3);

        // Approval workflow hooks
        add_action('wp_ajax_smo_submit_for_approval', array($this, 'ajax_submit_for_approval'));
        add_action('wp_ajax_smo_approve_content', array($this, 'ajax_approve_content'));
        add_action('wp_ajax_smo_reject_content', array($this, 'ajax_reject_content'));
        add_action('wp_ajax_smo_get_approval_queue', array($this, 'ajax_get_approval_queue'));
        add_action('wp_ajax_smo_get_content_details', array($this, 'ajax_get_content_details'));
        add_action('wp_ajax_smo_bulk_approve', array($this, 'ajax_bulk_approve'));

        // Dashboard hooks
        add_action('admin_menu', array($this, 'add_approval_menu'));

        // Ensure table exists
        add_action('init', array($this, 'ensure_approval_table'));
    }

    /**
     * Ensure approval table exists
     */
    public function ensure_approval_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $table_sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            content_hash varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            submitted_by bigint(20) NOT NULL,
            assigned_to bigint(20) DEFAULT NULL,
            approved_by bigint(20) DEFAULT NULL,
            rejected_by bigint(20) DEFAULT NULL,
            rejection_reason text DEFAULT '',
            submission_data longtext DEFAULT '',
            approval_notes text DEFAULT '',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            approved_at datetime DEFAULT NULL,
            rejected_at datetime DEFAULT NULL,
            deadline datetime DEFAULT NULL,
            priority varchar(10) NOT NULL DEFAULT 'normal',
            platforms longtext DEFAULT '',
            scheduled_time datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_content (post_id, content_hash),
            KEY status_deadline (status, deadline),
            KEY assigned_to_status (assigned_to, status),
            KEY submitted_by (submitted_by)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($table_sql);
    }

    /**
     * Handle new content creation
     */
    public function handle_new_content($post_id, $content_data) {
        // Check if content requires approval
        if (!$this->content_requires_approval()) {
            return;
        }

        $this->submit_for_approval($post_id, $content_data);
    }

    /**
     * Handle content updates
     */
    public function handle_content_update($post_id, $content_data) {
        // If content was already approved, resubmit for approval
        $existing_approval = $this->get_existing_approval($post_id);
        
        if ($existing_approval && $existing_approval->status === self::STATUS_APPROVED) {
            $this->submit_for_approval($post_id, $content_data, $existing_approval->id);
        }
    }

    /**
     * Submit content for approval
     */
    public function submit_for_approval($post_id, $content_data, $existing_id = null) {
        $current_user_id = get_current_user_id();
        $content_hash = $this->generate_content_hash($content_data);

        $submission_data = array(
            'content' => $content_data['content'] ?? '',
            'media' => $content_data['media'] ?? array(),
            'platforms' => $content_data['platforms'] ?? array(),
            'scheduled_time' => $content_data['scheduled_time'] ?? null,
            'hashtags' => $content_data['hashtags'] ?? array()
        );

        // Check if there's already a pending approval for this content
        if ($existing_id) {
            $result = $this->wpdb->update(
                $this->table_name,
                array(
                    'content_hash' => $content_hash,
                    'submission_data' => json_encode($submission_data),
                    'status' => self::STATUS_PENDING,
                    'submitted_at' => current_time('mysql'),
                    'approved_by' => null,
                    'rejected_by' => null,
                    'approved_at' => null,
                    'rejected_at' => null
                ),
                array('id' => $existing_id),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'),
                array('%d')
            );
        } else {
            $result = $this->wpdb->insert(
                $this->table_name,
                array(
                    'post_id' => $post_id,
                    'content_hash' => $content_hash,
                    'status' => self::STATUS_PENDING,
                    'submitted_by' => $current_user_id,
                    'submission_data' => json_encode($submission_data)
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            $existing_id = $this->wpdb->insert_id;
        }

        if ($result !== false) {
            // Log activity
            $this->user_manager->log_user_activity($current_user_id, 'content_submitted', array(
                'post_id' => $post_id,
                'approval_id' => $existing_id
            ));

            // Send notifications
            $this->send_approval_notifications($existing_id);

            // Assign to approvers
            $this->assign_to_approvers($existing_id);

            return $existing_id;
        }

        return false;
    }

    /**
     * Approve content
     */
    public function approve_content($approval_id, $approver_id, $notes = '') {
        $result = $this->wpdb->update(
            $this->table_name,
            array(
                'status' => self::STATUS_APPROVED,
                'approved_by' => $approver_id,
                'approved_at' => current_time('mysql'),
                'approval_notes' => $notes
            ),
            array('id' => $approval_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Log activity
            $this->user_manager->log_user_activity($approver_id, 'content_approved', array(
                'approval_id' => $approval_id,
                'notes' => $notes
            ));

            // Send notifications
            $this->send_approval_notification($approval_id, 'approved');

            // Trigger content publishing if scheduled
            $this->handle_approved_content($approval_id);

            return true;
        }

        return false;
    }

    /**
     * Reject content
     */
    public function reject_content($approval_id, $approver_id, $reason = '') {
        $result = $this->wpdb->update(
            $this->table_name,
            array(
                'status' => self::STATUS_REJECTED,
                'rejected_by' => $approver_id,
                'rejected_at' => current_time('mysql'),
                'rejection_reason' => $reason
            ),
            array('%s', '%d', '%s', '%s'),
            array('%d'),
            array('id' => $approval_id)
        );

        if ($result !== false) {
            // Log activity
            $this->user_manager->log_user_activity($approver_id, 'content_rejected', array(
                'approval_id' => $approval_id,
                'reason' => $reason
            ));

            // Send notifications
            $this->send_approval_notification($approval_id, 'rejected');

            return true;
        }

        return false;
    }

    /**
     * Get approval queue
     */
    public function get_approval_queue($status = self::STATUS_PENDING, $limit = 50, $offset = 0, $user_id = null) {
        $where_conditions = array('ca.status = %s');
        $query_params = array($status);

        // Filter by assigned user if specified
        if ($user_id) {
            $where_conditions[] = 'ca.assigned_to = %d';
            $query_params[] = $user_id;
        }

        // Filter by user permissions
        $current_user_id = get_current_user_id();
        $user_role = $this->user_manager->get_user_smo_role($current_user_id);
        
        if ($user_role !== UserManager::ROLE_ADMIN) {
            $where_conditions[] = '(ca.assigned_to = %d OR ca.submitted_by = %d)';
            $query_params[] = $current_user_id;
            $query_params[] = $current_user_id;
        }

        $query = "
            SELECT ca.*, 
                   su.display_name as submitted_by_name,
                   au.display_name as assigned_to_name,
                   p.post_title,
                   p.post_date
            FROM {$this->table_name} ca
            LEFT JOIN {$this->wpdb->users} su ON ca.submitted_by = su.ID
            LEFT JOIN {$this->wpdb->users} au ON ca.assigned_to = au.ID
            LEFT JOIN {$this->wpdb->posts} p ON ca.post_id = p.ID
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY 
                CASE ca.priority 
                    WHEN 'high' THEN 1 
                    WHEN 'normal' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                ca.submitted_at ASC
            LIMIT %d OFFSET %d
        ";

        $query_params[] = $limit;
        $query_params[] = $offset;

        return $this->wpdb->get_results($this->wpdb->prepare($query, $query_params));
    }

    /**
     * Get content approval statistics
     */
    public function get_approval_stats($user_id = null) {
        $where_clause = '';
        $query_params = array();

        if ($user_id) {
            $where_clause = 'WHERE submitted_by = %d';
            $query_params[] = $user_id;
        }

        $stats = array();

        // Total submissions
        $stats['total'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}",
            $query_params
        );

        // Pending approvals
        $stats['pending'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s" . 
            ($user_id ? ' AND submitted_by = %d' : ''),
            array_merge(array(self::STATUS_PENDING), $query_params)
        ));

        // Approved count
        $stats['approved'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s" . 
            ($user_id ? ' AND submitted_by = %d' : ''),
            array_merge(array(self::STATUS_APPROVED), $query_params)
        ));

        // Rejected count
        $stats['rejected'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s" . 
            ($user_id ? ' AND submitted_by = %d' : ''),
            array_merge(array(self::STATUS_REJECTED), $query_params)
        ));

        // Approval rate
        if ($stats['total'] > 0) {
            $stats['approval_rate'] = round(($stats['approved'] / $stats['total']) * 100, 2);
        } else {
            $stats['approval_rate'] = 0;
        }

        return $stats;
    }

    /**
     * Add approval menu to admin
     */
    public function add_approval_menu() {
        add_submenu_page(
            'smo-social',
            __('Content Approval', 'smo-social'),
            __('Approval Queue', 'smo-social'),
            'edit_posts',
            'smo-approval-queue',
            array($this, 'approval_queue_page')
        );
    }

    /**
     * Approval queue admin page
     */
    public function approval_queue_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $stats = $this->get_approval_stats();
        $pending_items = $this->get_approval_queue(self::STATUS_PENDING, 20);
        
        ?>
        <div class="wrap smo-approval-queue">
            <h1><?php _e('Content Approval Queue', 'smo-social'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="smo-stats-row">
                <div class="smo-stat-card">
                    <h3><?php _e('Total Submissions', 'smo-social'); ?></h3>
                    <div class="smo-stat-number"><?php echo $stats['total']; ?></div>
                </div>
                <div class="smo-stat-card">
                    <h3><?php _e('Pending Approval', 'smo-social'); ?></h3>
                    <div class="smo-stat-number"><?php echo $stats['pending']; ?></div>
                </div>
                <div class="smo-stat-card">
                    <h3><?php _e('Approved', 'smo-social'); ?></h3>
                    <div class="smo-stat-number"><?php echo $stats['approved']; ?></div>
                </div>
                <div class="smo-stat-card">
                    <h3><?php _e('Approval Rate', 'smo-social'); ?></h3>
                    <div class="smo-stat-number"><?php echo $stats['approval_rate']; ?>%</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="smo-quick-actions">
                <button id="refresh-queue" class="button"><?php _e('Refresh Queue', 'smo-social'); ?></button>
                <button id="bulk-approve" class="button button-primary"><?php _e('Bulk Approve', 'smo-social'); ?></button>
            </div>

            <!-- Approval Queue -->
            <div class="smo-queue-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="50"><input type="checkbox" id="select-all"></th>
                            <th><?php _e('Content', 'smo-social'); ?></th>
                            <th><?php _e('Submitted By', 'smo-social'); ?></th>
                            <th><?php _e('Assigned To', 'smo-social'); ?></th>
                            <th><?php _e('Priority', 'smo-social'); ?></th>
                            <th><?php _e('Submitted', 'smo-social'); ?></th>
                            <th><?php _e('Deadline', 'smo-social'); ?></th>
                            <th><?php _e('Actions', 'smo-social'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="approval-queue-body">
                        <?php foreach ($pending_items as $item): ?>
                            <tr data-approval-id="<?php echo $item->id; ?>">
                                <td><input type="checkbox" class="approval-checkbox" value="<?php echo $item->id; ?>"></td>
                                <td>
                                    <div class="smo-content-preview">
                                        <strong><?php echo esc_html($item->post_title); ?></strong>
                                        <div class="smo-content-snippet">
                                            <?php 
                                            $submission_data = json_decode($item->submission_data, true);
                                            echo esc_html(wp_trim_words($submission_data['content'] ?? '', 15));
                                            ?>
                                        </div>
                                        <div class="smo-platforms">
                                            <?php 
                                            $platforms = json_decode($item->platforms, true) ?: array();
                                            foreach ($platforms as $platform) {
                                                echo '<span class="smo-platform-tag">' . esc_html($platform) . '</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($item->submitted_by_name); ?></td>
                                <td><?php echo $item->assigned_to_name ? esc_html($item->assigned_to_name) : '—'; ?></td>
                                <td>
                                    <span class="smo-priority priority-<?php echo $item->priority; ?>">
                                        <?php echo ucfirst($item->priority); ?>
                                    </span>
                                </td>
                                <td><?php echo human_time_diff(strtotime($item->submitted_at)); ?> ago</td>
                                <td><?php echo $item->deadline ? date('M j, Y', strtotime($item->deadline)) : '—'; ?></td>
                                <td>
                                    <button class="button button-small smo-view-content" data-approval-id="<?php echo $item->id; ?>">
                                        <?php _e('View', 'smo-social'); ?>
                                    </button>
                                    <button class="button button-small button-primary smo-approve-content" data-approval-id="<?php echo $item->id; ?>">
                                        <?php _e('Approve', 'smo-social'); ?>
                                    </button>
                                    <button class="button button-small smo-reject-content" data-approval-id="<?php echo $item->id; ?>">
                                        <?php _e('Reject', 'smo-social'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Content Details Modal -->
        <div id="content-details-modal" class="smo-modal" style="display: none;">
            <div class="smo-modal-content">
                <span class="smo-modal-close">&times;</span>
                <h3><?php _e('Content Details', 'smo-social'); ?></h3>
                <div id="content-details-body">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="smo-modal-actions">
                    <button id="approve-from-modal" class="button button-primary"><?php _e('Approve', 'smo-social'); ?></button>
                    <button id="reject-from-modal" class="button"><?php _e('Reject', 'smo-social'); ?></button>
                    <button id="edit-from-modal" class="button"><?php _e('Edit Content', 'smo-social'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Submit content for approval
     */
    public function ajax_submit_for_approval() {
        AjaxSecurityHelper::validateAjaxRequest('smo_collaboration_nonce', 'nonce', 'edit_posts');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        $submission_data = array(
            'post_id' => $post_id,
            'content' => $_POST['content'] ?? '',
            'platforms' => isset($_POST['platforms']) ? $_POST['platforms'] : [],
            'media' => isset($_POST['media']) ? $_POST['media'] : [],
            'scheduled_time' => $_POST['scheduled_time'] ?? null,
            'hashtags' => isset($_POST['hashtags']) ? $_POST['hashtags'] : []
        );

        try {
            $validated = DataValidator::validate_collaboration_submission($submission_data);
            
            $content_data = array(
                'content' => $validated['content'],
                'platforms' => $validated['platforms'],
                'media' => $validated['media'],
                'scheduled_time' => $validated['scheduled_time'],
                'hashtags' => $validated['hashtags']
            );

            $approval_id = $this->submit_for_approval($validated['post_id'], $content_data);
            
            if ($approval_id) {
                wp_send_json_success(array('approval_id' => $approval_id));
            } else {
                wp_send_json_error('Failed to submit for approval');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Approve content
     */
    public function ajax_approve_content() {
        AjaxSecurityHelper::validateAjaxRequest('smo_collaboration_nonce', 'nonce', 'edit_posts');

        $approval_id = isset($_POST['approval_id']) ? intval($_POST['approval_id']) : 0;
        $notes = WordPressCompatibilityHelper::sanitizeText($_POST['notes'] ?? '');

        $result = $this->approve_content($approval_id, get_current_user_id(), $notes);
        
        if ($result) {
            wp_send_json_success('Content approved successfully');
        } else {
            wp_send_json_error('Failed to approve content');
        }
    }

    /**
     * AJAX: Reject content
     */
    public function ajax_reject_content() {
        AjaxSecurityHelper::validateAjaxRequest('smo_collaboration_nonce', 'nonce', 'edit_posts');

        $approval_id = isset($_POST['approval_id']) ? intval($_POST['approval_id']) : 0;
        $reason = WordPressCompatibilityHelper::sanitizeText($_POST['reason'] ?? '');

        $result = $this->reject_content($approval_id, get_current_user_id(), $reason);
        
        if ($result) {
            wp_send_json_success('Content rejected');
        } else {
            wp_send_json_error('Failed to reject content');
        }
    }

    /**
     * AJAX: Get approval queue
     */
    public function ajax_get_approval_queue() {
        AjaxSecurityHelper::validateAjaxRequest('smo_collaboration_nonce', 'nonce', 'edit_posts');

        $status = WordPressCompatibilityHelper::sanitizeText($_POST['status'] ?? self::STATUS_PENDING);
        $queue = $this->get_approval_queue($status);
        
        wp_send_json_success($queue);
    }

    /**
     * AJAX: Get content details
     */
    public function ajax_get_content_details() {
        AjaxSecurityHelper::validateAjaxRequest('smo_collaboration_nonce', 'nonce', 'edit_posts');

        $approval_id = isset($_POST['approval_id']) ? intval($_POST['approval_id']) : 0;
        $approval = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $approval_id
        ));

        if ($approval) {
            $approval->submission_data = json_decode($approval->submission_data, true);
            wp_send_json_success($approval);
        } else {
            wp_send_json_error('Content not found');
        }
    }

    /**
     * AJAX: Bulk approve
     */
    public function ajax_bulk_approve() {
        AjaxSecurityHelper::validateAjaxRequest('smo_collaboration_nonce', 'nonce', 'edit_posts');

        $approval_ids = isset($_POST['approval_ids']) && is_array($_POST['approval_ids']) ? array_map('intval', $_POST['approval_ids']) : [];
        $notes = WordPressCompatibilityHelper::sanitizeText($_POST['notes'] ?? '');

        $success_count = 0;
        foreach ($approval_ids as $approval_id) {
            if ($this->approve_content($approval_id, get_current_user_id(), $notes)) {
                $success_count++;
            }
        }

        wp_send_json_success(array('approved_count' => $success_count));
    }

    /**
     * Helper methods
     */

    private function content_requires_approval() {
        return get_option('smo_require_approval', true);
    }

    private function generate_content_hash($content_data) {
        return hash('sha256', serialize($content_data));
    }

    private function get_existing_approval($post_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND status = %s",
            $post_id, self::STATUS_APPROVED
        ));
    }

    private function send_approval_notifications($approval_id) {
        // Get approvers
        $approvers = $this->wpdb->get_results(
            "SELECT user_id FROM {$this->user_manager->table_name} 
             WHERE role = '" . UserManager::ROLE_ADMIN . "' OR role = '" . UserManager::ROLE_EDITOR . "'"
        );

        foreach ($approvers as $approver) {
            // Send notification (email, in-app notification, etc.)
            // This would integrate with WordPress notifications or email system
        }
    }

    private function send_approval_notification($approval_id, $status) {
        // Send notification to content submitter
        // This would send an email or in-app notification
    }

    private function assign_to_approvers($approval_id) {
        // Auto-assign to available approvers based on workload
        // For now, assign to all admins and editors
        $approvers = $this->wpdb->get_results(
            "SELECT user_id FROM {$this->user_manager->table_name} 
             WHERE role IN ('" . UserManager::ROLE_ADMIN . "', '" . UserManager::ROLE_EDITOR . "') 
             AND status = 'active'"
        );

        if (!empty($approvers)) {
            // Assign to the first available approver (round-robin would be better)
            $assigned_user = $approvers[0]->user_id;
            
            $this->wpdb->update(
                $this->table_name,
                array('assigned_to' => $assigned_user),
                array('id' => $approval_id),
                array('%d'),
                array('%d')
            );
        }
    }

    private function handle_approved_content($approval_id) {
        $approval = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $approval_id
        ));

        if ($approval && $approval->scheduled_time) {
            // Schedule the content for publishing
            wp_schedule_single_event(
                strtotime($approval->scheduled_time),
                'smo_publish_scheduled_content',
                array($approval_id)
            );
        }
    }
}