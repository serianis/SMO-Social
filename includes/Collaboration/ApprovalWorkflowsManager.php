<?php
/**
 * Approval Workflows Manager
 * Handles content approval processes and team collaboration
 */

namespace SMO_Social\Collaboration;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * Approval Workflows Manager
 * 
 * Manages content approval processes:
 * - Assign reviewers to posts
 * - Track approval status (pending, approved, rejected, revision_requested)
 * - Approval comments and feedback
 * - Workflow automation and notifications
 * - Approval history and reporting
 */
class ApprovalWorkflowsManager {
    
    public $last_error = '';
    
    private $table_names;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'approval_workflows' => $wpdb->prefix . 'smo_approval_workflows',
            'scheduled_posts' => $wpdb->prefix . 'smo_scheduled_posts',
            'user_platform_permissions' => $wpdb->prefix . 'smo_user_platform_permissions'
        );
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_assign_reviewer', array($this, 'ajax_assign_reviewer'));
        add_action('wp_ajax_smo_update_approval_status', array($this, 'ajax_update_approval_status'));
        add_action('wp_ajax_smo_get_approval_queue', array($this, 'ajax_get_approval_queue'));
        add_action('wp_ajax_smo_get_approval_history', array($this, 'ajax_get_approval_history'));
        add_action('wp_ajax_smo_bulk_assign_reviewers', array($this, 'ajax_bulk_assign_reviewers'));
        add_action('wp_ajax_smo_get_approval_statistics', array($this, 'ajax_get_approval_statistics'));
        
        // Auto-assign reviewers based on platform permissions
        add_action('smo_post_status_changed', array($this, 'auto_assign_reviewer'), 10, 2);
    }
    
    /**
     * Assign reviewer to post
     */
    public function assign_reviewer($post_id, $reviewer_id, $auto_assign = false) {
        global $wpdb;
        
        // Check if user has permission to assign reviewers
        if (!$this->user_can_assign_reviewers()) {
            throw new \Exception('Insufficient permissions to assign reviewers');
        }
        
        // Check if post exists and is eligible for approval
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['scheduled_posts']} WHERE id = %d",
            $post_id
        ));
        
        if (!$post) {
            throw new \Exception('Post not found');
        }
        
        // Check if reviewer exists and has permission for this platform
        if (!$this->reviewer_can_review($reviewer_id, $post->platform)) {
            throw new \Exception('Reviewer does not have permission to review posts on this platform');
        }
        
        // Check if approval already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_names['approval_workflows']} 
             WHERE post_id = %d AND status = 'pending'",
            $post_id
        ));
        
        if ($existing) {
            // Update existing approval
            $result = $wpdb->update(
                $this->table_names['approval_workflows'],
                array(
                    'assigned_to' => $reviewer_id,
                    'assigned_at' => current_time('mysql'),
                    'status' => 'pending',
                    'comments' => $auto_assign ? 'Auto-assigned based on platform permissions' : null
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new approval workflow
            $result = $wpdb->insert(
                $this->table_names['approval_workflows'],
                array(
                    'post_id' => $post_id,
                    'assigned_to' => $reviewer_id,
                    'status' => 'pending',
                    'assigned_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s')
            );
        }
        
        if ($result === false) {
            throw new \Exception('Failed to assign reviewer: ' . $wpdb->last_error);
        }
        
        // Update post status to indicate it needs approval
        if ($post->status !== 'pending_approval') {
            $wpdb->update(
                $this->table_names['scheduled_posts'],
                array('approval_status' => 'pending_approval'),
                array('id' => $post_id),
                array('%s'),
                array('%d')
            );
        }
        
        // Send notification to reviewer
        $this->send_assignment_notification($reviewer_id, $post_id, $auto_assign);
        
        return true;
    }
    
    /**
     * Update approval status
     */
    public function update_approval_status($post_id, $status, $comments = '', $reviewer_id = null) {
        global $wpdb;
        
        $reviewer_id = $reviewer_id ?: get_current_user_id();
        
        // Validate status
        if (!in_array($status, array('approved', 'rejected', 'revision_requested'))) {
            throw new \Exception('Invalid approval status');
        }
        
        // Check if user can update this approval
        if (!$this->user_can_update_approval($post_id, $reviewer_id)) {
            throw new \Exception('Insufficient permissions to update approval status');
        }
        
        // Get current approval workflow
        $approval = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['approval_workflows']} 
             WHERE post_id = %d AND status = 'pending'",
            $post_id
        ));
        
        if (!$approval) {
            throw new \Exception('No pending approval found for this post');
        }
        
        // Update approval status
        $result = $wpdb->update(
            $this->table_names['approval_workflows'],
            array(
                'status' => $status,
                'comments' => wp_kses_post($comments),
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => $reviewer_id
            ),
            array('id' => $approval->id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to update approval status: ' . $wpdb->last_error);
        }
        
        // Update post approval status
        $post_approval_status = $status === 'approved' ? 'approved' : 
                               ($status === 'rejected' ? 'rejected' : 'pending_approval');
        
        $wpdb->update(
            $this->table_names['scheduled_posts'],
            array('approval_status' => $post_approval_status),
            array('id' => $post_id),
            array('%s'),
            array('%d')
        );
        
        // Send notifications based on status
        $this->send_status_notification($post_id, $status, $comments);
        
        return true;
    }
    
    /**
     * Auto-assign reviewer based on platform permissions
     */
    public function auto_assign_reviewer($post_id, $old_status) {
        global $wpdb;
        
        // Only auto-assign when post is moved to draft status
        if ($old_status !== 'draft' && $old_status !== 'pending_approval') {
            return;
        }
        
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['scheduled_posts']} WHERE id = %d",
            $post_id
        ));
        
        if (!$post) {
            return;
        }
        
        // Find eligible reviewers for this platform
        $eligible_reviewers = $this->get_eligible_reviewers($post->platform);
        
        if (!empty($eligible_reviewers)) {
            // Assign to the reviewer with the least pending approvals
            $reviewer_workload = $this->get_reviewer_workload($eligible_reviewers);
            $selected_reviewer = $this->select_reviewer_with_minimum_workload($reviewer_workload);
            
            if ($selected_reviewer) {
                $this->assign_reviewer($post_id, $selected_reviewer['user_id'], true);
            }
        }
    }
    
    /**
     * Get eligible reviewers for a platform
     */
    private function get_eligible_reviewers($platform) {
        global $wpdb;
        
        $query = "SELECT DISTINCT upp.user_id, u.display_name
                  FROM {$this->table_names['user_platform_permissions']} upp
                  LEFT JOIN {$wpdb->prefix}users u ON upp.user_id = u.ID
                  WHERE upp.platform = %s 
                  AND upp.expires_at IS NULL 
                  AND upp.expires_at > NOW()
                  ORDER BY u.display_name";
        
        return $wpdb->get_results($wpdb->prepare($query, $platform), ARRAY_A);
    }
    
    /**
     * Get reviewer workload (pending approvals)
     */
    private function get_reviewer_workload($reviewers) {
        global $wpdb;
        
        $workload = array();
        
        foreach ($reviewers as $reviewer) {
            $pending_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_names['approval_workflows']} 
                 WHERE assigned_to = %d AND status = 'pending'",
                $reviewer['user_id']
            ));
            
            $workload[] = array(
                'user_id' => $reviewer['user_id'],
                'display_name' => $reviewer['display_name'],
                'pending_count' => intval($pending_count)
            );
        }
        
        return $workload;
    }
    
    /**
     * Select reviewer with minimum workload
     */
    private function select_reviewer_with_minimum_workload($workload) {
        if (empty($workload)) {
            return null;
        }
        
        // Sort by pending count (ascending)
        usort($workload, function($a, $b) {
            return $a['pending_count'] - $b['pending_count'];
        });
        
        return $workload[0];
    }
    
    /**
     * Get approval queue
     */
    public function get_approval_queue($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('aw.status = %s');
        $where_values = array('pending');
        
        if (!empty($filters['assigned_to'])) {
            $where_conditions[] = "aw.assigned_to = %d";
            $where_values[] = $filters['assigned_to'];
        }
        
        if (!empty($filters['platform'])) {
            $where_conditions[] = "sp.platform = %s";
            $where_values[] = $filters['platform'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "sp.approval_status = %s";
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $where_conditions[] = "sp.priority = %s";
            $where_values[] = $filters['priority'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT aw.*, sp.title, sp.content, sp.platform, sp.scheduled_time, sp.priority,
                         sp.created_by, u1.display_name as assigned_to_name, 
                         u2.display_name as created_by_name
                  FROM {$this->table_names['approval_workflows']} aw
                  LEFT JOIN {$this->table_names['scheduled_posts']} sp ON aw.post_id = sp.id
                  LEFT JOIN {$wpdb->prefix}users u1 ON aw.assigned_to = u1.ID
                  LEFT JOIN {$wpdb->prefix}users u2 ON sp.created_by = u2.ID
                  $where_clause
                  ORDER BY aw.assigned_at ASC";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get approval history
     */
    public function get_approval_history($filters = array()) {
        global $wpdb;
        
        $where_conditions = array("aw.status != 'pending'");
        $where_values = array();
        
        if (!empty($filters['post_id'])) {
            $where_conditions[] = "aw.post_id = %d";
            $where_values[] = $filters['post_id'];
        }
        
        if (!empty($filters['reviewed_by'])) {
            $where_conditions[] = "aw.reviewed_by = %d";
            $where_values[] = $filters['reviewed_by'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "aw.status = %s";
            $where_values[] = $filters['status'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT aw.*, sp.title, sp.platform, sp.scheduled_time,
                         u1.display_name as reviewed_by_name,
                         u2.display_name as assigned_to_name
                  FROM {$this->table_names['approval_workflows']} aw
                  LEFT JOIN {$this->table_names['scheduled_posts']} sp ON aw.post_id = sp.id
                  LEFT JOIN {$wpdb->prefix}users u1 ON aw.reviewed_by = u1.ID
                  LEFT JOIN {$wpdb->prefix}users u2 ON aw.assigned_to = u2.ID
                  $where_clause
                  ORDER BY aw.reviewed_at DESC
                  LIMIT %d";
        
        $where_values[] = $filters['limit'] ?? 50;
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get approval statistics
     */
    public function get_approval_statistics($user_id = null) {
        global $wpdb;
        
        $user_id = $user_id ?: get_current_user_id();
        
        $where_clause = $user_id ? "WHERE assigned_to = %d" : "WHERE 1=1";
        $where_values = $user_id ? array($user_id) : array();
        
        // Pending approvals
        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_names['approval_workflows']} WHERE status = 'pending'",
            $where_values
        );
        
        // Approved this month
        $approved_this_month = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_names['approval_workflows']} 
             WHERE status = 'approved' AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            $where_values
        );
        
        // Average review time
        $avg_review_time = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, assigned_at, reviewed_at)) 
             FROM {$this->table_names['approval_workflows']} 
             WHERE status != 'pending' AND reviewed_at IS NOT NULL",
            array()
        );
        
        // Approval rate
        $total_reviews = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_names['approval_workflows']} 
             WHERE status != 'pending' AND reviewed_at IS NOT NULL",
            array()
        );
        
        $approval_rate = $total_reviews > 0 ? 
            ($approved_this_month / $total_reviews) * 100 : 0;
        
        return array(
            'pending_count' => intval($pending_count),
            'approved_this_month' => intval($approved_this_month),
            'avg_review_time_hours' => round($avg_review_time, 1),
            'approval_rate' => round($approval_rate, 1),
            'total_reviews' => intval($total_reviews)
        );
    }
    
    /**
     * Check if user can assign reviewers
     */
    private function user_can_assign_reviewers() {
        // Admins and users with manage_options can assign reviewers
        return current_user_can('manage_options') || current_user_can('edit_others_posts');
    }
    
    /**
     * Check if user can update approval
     */
    private function user_can_update_approval($post_id, $reviewer_id) {
        // Reviewer can update their own assignments, or admins can update any
        return $reviewer_id == get_current_user_id() || current_user_can('manage_options');
    }
    
    /**
     * Check if reviewer can review posts on platform
     */
    private function reviewer_can_review($reviewer_id, $platform) {
        global $wpdb;
        
        $permission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['user_platform_permissions']} 
             WHERE user_id = %d AND platform = %s",
            $reviewer_id, $platform
        ));
        
        return $permission !== null;
    }
    
    /**
     * Send assignment notification
     */
    private function send_assignment_notification($reviewer_id, $post_id, $auto_assign) {
        global $wpdb;
        
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT title, platform FROM {$this->table_names['scheduled_posts']} WHERE id = %d",
            $post_id
        ));
        
        $reviewer = get_userdata($reviewer_id);
        if (!$reviewer || !$post) {
            return;
        }
        
        $subject = sprintf('[%s] New content requires your review', get_bloginfo('name'));
        $assignment_type = $auto_assign ? 'auto-assigned' : 'assigned';
        
        $message = sprintf(
            "Hi %s,\n\nYou have been %s to review the following content:\n\nTitle: %s\nPlatform: %s\n\n%s\n\nYou can review and approve this content in your dashboard.\n\nBest regards,\n%s Team",
            $reviewer->display_name,
            $assignment_type,
            $post->title,
            ucfirst($post->platform),
            $auto_assign ? '(This was automatically assigned based on your platform permissions)' : '',
            get_bloginfo('name')
        );
        
        if ($reviewer->user_email) {
            wp_mail($reviewer->user_email, $subject, $message);
        }
    }
    
    /**
     * Send status notification
     */
    private function send_status_notification($post_id, $status, $comments) {
        global $wpdb;
        
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT sp.title, sp.platform, sp.created_by, u.display_name as creator_name
             FROM {$this->table_names['scheduled_posts']} sp
             LEFT JOIN {$wpdb->prefix}users u ON sp.created_by = u.ID
             WHERE sp.id = %d",
            $post_id
        ));
        
        if (!$post || !$post->creator_name) {
            return;
        }
        
        $creator = get_userdata($post->created_by);
        if (!$creator || !$creator->user_email) {
            return;
        }
        
        $status_messages = array(
            'approved' => 'approved',
            'rejected' => 'rejected',
            'revision_requested' => 'requested revisions for'
        );
        
        $subject = sprintf('[%s] Your content has been %s', get_bloginfo('name'), $status_messages[$status]);
        
        $message = sprintf(
            "Hi %s,\n\nYour content \"%s\" has been %s by the reviewer.\n\n%s\n\nYou can view the full details in your dashboard.\n\nBest regards,\n%s Team",
            $post->creator_name,
            $post->title,
            $status_messages[$status],
            $comments ? "Comments: {$comments}" : '',
            get_bloginfo('name')
        );
        
        wp_mail($creator->user_email, $subject, $message);
    }
    
    // AJAX handlers
    
    public function ajax_assign_reviewer() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_id = intval($_POST['post_id']);
        $reviewer_id = intval($_POST['reviewer_id']);
        
        try {
            $this->assign_reviewer($post_id, $reviewer_id);
            wp_send_json_success(__('Reviewer assigned successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_update_approval_status() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_id = intval($_POST['post_id']);
        $status = sanitize_text_field($_POST['status']);
        $comments = wp_kses_post($_POST['comments'] ?? '');
        
        try {
            $this->update_approval_status($post_id, $status, $comments);
            wp_send_json_success(__('Approval status updated successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_approval_queue() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'assigned_to' => intval($_POST['assigned_to'] ?? 0),
            'platform' => sanitize_text_field($_POST['platform'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'priority' => sanitize_text_field($_POST['priority'] ?? '')
        );
        
        $queue = $this->get_approval_queue($filters);
        wp_send_json_success($queue);
    }
    
    public function ajax_get_approval_history() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'post_id' => intval($_POST['post_id'] ?? 0),
            'reviewed_by' => intval($_POST['reviewed_by'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'limit' => intval($_POST['limit'] ?? 50)
        );
        
        $history = $this->get_approval_history($filters);
        wp_send_json_success($history);
    }
    
    public function ajax_bulk_assign_reviewers() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());
        $reviewer_id = intval($_POST['reviewer_id']);
        
        $assigned_count = 0;
        foreach ($post_ids as $post_id) {
            try {
                $this->assign_reviewer($post_id, $reviewer_id);
                $assigned_count++;
            } catch (\Exception $e) {
                error_log("SMO Social: Failed to assign reviewer to post {$post_id}: " . $e->getMessage());
            }
        }
        
        wp_send_json_success(array(
            'assigned_count' => $assigned_count,
            'message' => sprintf(__('Successfully assigned reviewer to %d posts', 'smo-social'), $assigned_count)
        ));
    }
    
    public function ajax_get_approval_statistics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $user_id = intval($_POST['user_id'] ?? get_current_user_id());
        $stats = $this->get_approval_statistics($user_id);
        wp_send_json_success($stats);
    }
}