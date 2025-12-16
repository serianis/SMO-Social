<?php
/**
 * User Management and Permissions System
 * Handles user access control and platform permissions
 */

namespace SMO_Social\Team;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * User Management and Permissions System
 * 
 * Manages user access and permissions:
 * - Platform-specific permissions
 * - User roles and capabilities
 * - Team member management
 * - Access control and restrictions
 * - Permission inheritance and delegation
 */
class UserManagementSystem {
    
    public $last_error = '';
    
    private $table_names;
    private $user_roles;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'user_platform_permissions' => $wpdb->prefix . 'smo_user_platform_permissions',
            'scheduled_posts' => $wpdb->prefix . 'smo_scheduled_posts',
            'team_members' => $wpdb->prefix . 'smo_team_members'
        );
        
        $this->init_user_roles();
        $this->init_hooks();
    }
    
    /**
     * Initialize user roles and their capabilities
     */
    private function init_user_roles() {
        $this->user_roles = array(
            'admin' => array(
                'name' => 'Administrator',
                'capabilities' => array('manage_all', 'approve_all', 'view_all_analytics', 'manage_team'),
                'platform_permissions' => 'all'
            ),
            'content_manager' => array(
                'name' => 'Content Manager',
                'capabilities' => array('create_posts', 'edit_own_posts', 'view_analytics', 'manage_ideas'),
                'platform_permissions' => 'assigned'
            ),
            'reviewer' => array(
                'name' => 'Reviewer',
                'capabilities' => array('review_posts', 'approve_assigned', 'view_analytics'),
                'platform_permissions' => 'assigned'
            ),
            'creator' => array(
                'name' => 'Creator',
                'capabilities' => array('create_posts', 'edit_own_posts'),
                'platform_permissions' => 'assigned'
            ),
            'viewer' => array(
                'name' => 'Viewer',
                'capabilities' => array('view_analytics', 'view_posts'),
                'platform_permissions' => 'none'
            )
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_assign_user_role', array($this, 'ajax_assign_user_role'));
        add_action('wp_ajax_smo_grant_platform_permission', array($this, 'ajax_grant_platform_permission'));
        add_action('wp_ajax_smo_revoke_platform_permission', array($this, 'ajax_revoke_platform_permission'));
        add_action('wp_ajax_smo_get_team_members', array($this, 'ajax_get_team_members'));
        add_action('wp_ajax_smo_get_user_permissions', array($this, 'ajax_get_user_permissions'));
        add_action('wp_ajax_smo_invite_team_member', array($this, 'ajax_invite_team_member'));
        add_action('wp_ajax_smo_remove_team_member', array($this, 'ajax_remove_team_member'));
        add_action('wp_ajax_smo_bulk_update_permissions', array($this, 'ajax_bulk_update_permissions'));
    }
    
    /**
     * Assign role to user
     */
    public function assign_user_role($user_id, $role) {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Insufficient permissions to assign user roles');
        }
        
        if (!isset($this->user_roles[$role])) {
            throw new \Exception('Invalid user role');
        }
        
        // Update user meta with role
        update_user_meta($user_id, 'smo_social_role', $role);
        
        // Update WordPress user role
        $user = new \WP_User($user_id);
        $user->remove_role('subscriber'); // Remove default role
        $user->add_role('smo_' . $role); // Add custom role
        
        // Log the role assignment
        $this->log_user_action($user_id, 'role_assigned', array('role' => $role));
        
        return true;
    }
    
    /**
     * Grant platform permission to user
     */
    public function grant_platform_permission($user_id, $platform, $permissions = array(), $expires_at = null) {
        if (!$this->current_user_can_grant_permissions()) {
            throw new \Exception('Insufficient permissions to grant platform access');
        }
        
        global $wpdb;
        
        // Validate permissions array
        $allowed_permissions = array('view', 'create', 'edit', 'approve', 'delete', 'reply');
        $valid_permissions = array_intersect($permissions, $allowed_permissions);
        
        if (empty($valid_permissions)) {
            throw new \Exception('No valid permissions specified');
        }
        
        // Check if permission already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_names['user_platform_permissions']} 
             WHERE user_id = %d AND platform = %s",
            $user_id, $platform
        ));
        
        if ($existing) {
            // Update existing permission
            $result = $wpdb->update(
                $this->table_names['user_platform_permissions'],
                array(
                    'permissions' => implode(',', $valid_permissions),
                    'expires_at' => $expires_at,
                    'granted_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new permission
            $result = $wpdb->insert(
                $this->table_names['user_platform_permissions'],
                array(
                    'user_id' => $user_id,
                    'platform' => $platform,
                    'permissions' => implode(',', $valid_permissions),
                    'granted_by' => get_current_user_id(),
                    'granted_at' => current_time('mysql'),
                    'expires_at' => $expires_at
                ),
                array('%d', '%s', '%s', '%d', '%s', '%s')
            );
        }
        
        if ($result === false) {
            throw new \Exception('Failed to grant platform permission: ' . $wpdb->last_error);
        }
        
        // Log the permission grant
        $this->log_user_action($user_id, 'permission_granted', array(
            'platform' => $platform,
            'permissions' => $valid_permissions
        ));
        
        // Send notification to user
        $this->send_permission_notification($user_id, $platform, $valid_permissions, 'granted');
        
        return true;
    }
    
    /**
     * Revoke platform permission from user
     */
    public function revoke_platform_permission($user_id, $platform) {
        if (!$this->current_user_can_grant_permissions()) {
            throw new \Exception('Insufficient permissions to revoke platform access');
        }
        
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_names['user_platform_permissions'],
            array(
                'user_id' => $user_id,
                'platform' => $platform
            ),
            array('%d', '%s')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to revoke platform permission: ' . $wpdb->last_error);
        }
        
        // Log the permission revocation
        $this->log_user_action($user_id, 'permission_revoked', array('platform' => $platform));
        
        // Send notification to user
        $this->send_permission_notification($user_id, $platform, array(), 'revoked');
        
        return true;
    }
    
    /**
     * Get team members with their roles and permissions
     */
    public function get_team_members($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('u.ID IS NOT NULL');
        $where_values = array();
        
        if (!empty($filters['role'])) {
            $where_conditions[] = "um.meta_value = %s";
            $where_values[] = $filters['role'];
        }
        
        if (!empty($filters['platform'])) {
            $where_conditions[] = "upp.platform = %s";
            $where_values[] = $filters['platform'];
        }
        
        if (isset($filters['status'])) {
            $where_conditions[] = "tm.status = %s";
            $where_values[] = $filters['status'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT DISTINCT u.ID, u.user_login, u.display_name, u.user_email, u.user_registered,
                         um.meta_value as role,
                         tm.status as team_status, tm.joined_at, tm.invited_by,
                         GROUP_CONCAT(DISTINCT upp.platform) as platforms,
                         COUNT(DISTINCT upp.id) as platform_count
                  FROM {$wpdb->prefix}users u
                  LEFT JOIN {$wpdb->prefix}usermeta um ON u.ID = um.user_id AND um.meta_key = 'smo_social_role'
                  LEFT JOIN {$this->table_names['team_members']} tm ON u.ID = tm.user_id
                  LEFT JOIN {$this->table_names['user_platform_permissions']} upp ON u.ID = upp.user_id
                  $where_clause
                  GROUP BY u.ID
                  ORDER BY u.display_name";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $members = $wpdb->get_results($query, ARRAY_A);
        
        // Enhance with additional data
        foreach ($members as &$member) {
            $member['role_info'] = $this->user_roles[$member['role']] ?? null;
            $member['platforms'] = $member['platforms'] ? explode(',', $member['platforms']) : array();
            $member['permissions_count'] = $this->get_user_total_permissions($member['ID']);
            $member['recent_activity'] = $this->get_user_recent_activity($member['ID']);
        }
        
        return $members;
    }
    
    /**
     * Get user permissions for specific platforms
     */
    public function get_user_permissions($user_id = null) {
        global $wpdb;
        
        $user_id = $user_id ?: get_current_user_id();
        
        $permissions = $wpdb->get_results($wpdb->prepare(
            "SELECT upp.*, u.display_name as granted_by_name
             FROM {$this->table_names['user_platform_permissions']} upp
             LEFT JOIN {$wpdb->prefix}users u ON upp.granted_by = u.ID
             WHERE upp.user_id = %d
             ORDER BY upp.platform",
            $user_id
        ), ARRAY_A);
        
        // Process permissions for each platform
        foreach ($permissions as &$permission) {
            $permission['permissions_array'] = explode(',', $permission['permissions']);
            $permission['is_expired'] = $permission['expires_at'] && strtotime($permission['expires_at']) < time();
        }
        
        return $permissions;
    }
    
    /**
     * Invite team member
     */
    public function invite_team_member($email, $role, $platforms = array(), $message = '') {
        if (!current_user_can('manage_options') && !$this->current_user_can_invite_members()) {
            throw new \Exception('Insufficient permissions to invite team members');
        }
        
        // Check if user already exists
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            throw new \Exception('User with this email already exists');
        }
        
        // Generate invitation token
        $token = wp_generate_password(32, false);
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store invitation in database (you might want a separate invitations table)
        update_option('smo_invitation_' . $token, array(
            'email' => $email,
            'role' => $role,
            'platforms' => $platforms,
            'message' => $message,
            'expires' => $expires,
            'invited_by' => get_current_user_id()
        ));
        
        // Send invitation email
        $this->send_invitation_email($email, $token, $role, $message);
        
        // Log the invitation
        error_log("SMO Social: Team member invited - Email: {$email}, Role: {$role}");
        
        return true;
    }
    
    /**
     * Process invitation acceptance
     */
    public function accept_invitation($token, $user_data) {
        $invitation = get_option('smo_invitation_' . $token);
        
        if (!$invitation) {
            throw new \Exception('Invalid or expired invitation');
        }
        
        if (strtotime($invitation['expires']) < time()) {
            throw new \Exception('Invitation has expired');
        }
        
        if ($invitation['email'] !== $user_data['email']) {
            throw new \Exception('Email does not match invitation');
        }
        
        // Create user account
        $user_id = wp_create_user($user_data['username'], $user_data['password'], $user_data['email']);

        if (is_wp_error($user_id)) {
            /** @var \WP_Error $user_id */
            throw new \Exception($user_id->get_error_message());
        }
        
        // Update user display name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $user_data['display_name']
        ));
        
        // Assign role
        $this->assign_user_role($user_id, $invitation['role']);
        
        // Grant platform permissions
        foreach ($invitation['platforms'] as $platform) {
            $this->grant_platform_permission($user_id, $platform);
        }
        
        // Clean up invitation
        delete_option('smo_invitation_' . $token);
        
        // Log the acceptance
        $this->log_user_action($user_id, 'invitation_accepted', array(
            'role' => $invitation['role'],
            'platforms' => $invitation['platforms']
        ));
        
        return $user_id;
    }
    
    /**
     * Remove team member
     */
    public function remove_team_member($user_id) {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Insufficient permissions to remove team members');
        }
        
        // Remove user permissions
        global $wpdb;
        $wpdb->delete(
            $this->table_names['user_platform_permissions'],
            array('user_id' => $user_id),
            array('%d')
        );
        
        // Remove user meta
        delete_user_meta($user_id, 'smo_social_role');
        
        // Log the removal
        $this->log_user_action($user_id, 'removed_from_team');
        
        return true;
    }
    
    /**
     * Check if user can perform action on platform
     */
    public function user_can($action, $platform, $user_id = null) {
        global $wpdb;

        $user_id = $user_id ?: get_current_user_id();

        // Get user role
        $role = get_user_meta($user_id, 'smo_social_role', true);

        // Admin can do everything
        if ($role === 'admin') {
            return true;
        }

        // Check specific permissions
        $permissions = $wpdb->get_row($wpdb->prepare(
            "SELECT permissions FROM {$this->table_names['user_platform_permissions']} 
             WHERE user_id = %d AND platform = %s",
            $user_id, $platform
        ));
        
        if (!$permissions) {
            return false;
        }
        
        $user_permissions = explode(',', $permissions->permissions);
        
        switch ($action) {
            case 'view':
                return in_array('view', $user_permissions);
            case 'create':
                return in_array('create', $user_permissions);
            case 'edit':
                return in_array('edit', $user_permissions);
            case 'approve':
                return in_array('approve', $user_permissions);
            case 'delete':
                return in_array('delete', $user_permissions);
            case 'reply':
                return in_array('reply', $user_permissions);
            default:
                return false;
        }
    }
    
    /**
     * Get user total permissions count
     */
    private function get_user_total_permissions($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_names['user_platform_permissions']} 
             WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get user recent activity
     */
    private function get_user_recent_activity($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_names['scheduled_posts']} 
             WHERE created_by = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));
    }
    
    /**
     * Check if current user can grant permissions
     */
    private function current_user_can_grant_permissions() {
        $role = get_user_meta(get_current_user_id(), 'smo_social_role', true);
        return current_user_can('manage_options') || $role === 'admin' || $role === 'content_manager';
    }
    
    /**
     * Check if current user can invite members
     */
    private function current_user_can_invite_members() {
        $role = get_user_meta(get_current_user_id(), 'smo_social_role', true);
        return $role === 'admin' || $role === 'content_manager';
    }
    
    /**
     * Log user action
     */
    private function log_user_action($user_id, $action, $data = array()) {
        // This could be expanded to a proper logging system
        error_log("SMO Social User Action: User {$user_id} - {$action} - " . json_encode($data));
    }
    
    /**
     * Send permission notification
     */
    private function send_permission_notification($user_id, $platform, $permissions, $type) {
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return;
        }
        
        $subject = sprintf('[%s] Platform permission %s', get_bloginfo('name'), $type);
        
        if ($type === 'granted') {
            $message = sprintf(
                "Hi %s,\n\nYou have been granted %s permissions for %s.\n\nYou can now %s on this platform.\n\nBest regards,\n%s Team",
                $user->display_name,
                implode(', ', $permissions),
                ucfirst($platform),
                implode(' and ', $permissions),
                get_bloginfo('name')
            );
        } else {
            $message = sprintf(
                "Hi %s,\n\nYour permissions for %s have been revoked.\n\nYou can no longer perform actions on this platform.\n\nBest regards,\n%s Team",
                $user->display_name,
                ucfirst($platform),
                get_bloginfo('name')
            );
        }
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send invitation email
     */
    private function send_invitation_email($email, $token, $role, $message) {
        $invitation_link = admin_url('admin.php?page=smo-social-invite&token=' . $token);
        
        $subject = sprintf('[%s] You\'ve been invited to join our team', get_bloginfo('name'));
        $message_body = sprintf(
            "You've been invited to join the social media management team as a %s.\n\n%s\n\nClick the link below to accept the invitation:\n%s\n\nThis invitation will expire in 7 days.\n\nBest regards,\n%s Team",
            $this->user_roles[$role]['name'],
            $message ?: '',
            $invitation_link,
            get_bloginfo('name')
        );
        
        wp_mail($email, $subject, $message_body);
    }
    
    // AJAX handlers
    
    public function ajax_assign_user_role() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // Validate required POST parameters
        if (!isset($_POST['user_id']) || !isset($_POST['role'])) {
            wp_send_json_error(__('Missing required parameters'));
        }
        
        $user_id = intval($_POST['user_id']);
        $role = sanitize_text_field($_POST['role']);
        
        try {
            $this->assign_user_role($user_id, $role);
            wp_send_json_success(__('User role updated successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_grant_platform_permission() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // Validate required POST parameters
        if (!isset($_POST['user_id']) || !isset($_POST['platform'])) {
            wp_send_json_error(__('Missing required parameters'));
        }
        
        $user_id = intval($_POST['user_id']);
        $platform = sanitize_text_field($_POST['platform']);
        $permissions = array_map('sanitize_text_field', $_POST['permissions'] ?? array());
        $expires_at = sanitize_text_field($_POST['expires_at'] ?? null);
        
        try {
            $this->grant_platform_permission($user_id, $platform, $permissions, $expires_at);
            wp_send_json_success(__('Platform permission granted successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_revoke_platform_permission() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // Validate required POST parameters
        if (!isset($_POST['user_id']) || !isset($_POST['platform'])) {
            wp_send_json_error(__('Missing required parameters'));
        }
        
        $user_id = intval($_POST['user_id']);
        $platform = sanitize_text_field($_POST['platform']);
        
        try {
            $this->revoke_platform_permission($user_id, $platform);
            wp_send_json_success(__('Platform permission revoked successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_team_members() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'role' => sanitize_text_field($_POST['role'] ?? ''),
            'platform' => sanitize_text_field($_POST['platform'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? '')
        );
        
        $members = $this->get_team_members($filters);
        wp_send_json_success($members);
    }
    
    public function ajax_get_user_permissions() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $user_id = intval($_POST['user_id'] ?? get_current_user_id());
        $permissions = $this->get_user_permissions($user_id);
        wp_send_json_success($permissions);
    }
    
    public function ajax_invite_team_member() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // Validate required POST parameters
        if (!isset($_POST['email']) || !isset($_POST['role'])) {
            wp_send_json_error(__('Missing required parameters'));
        }
        
        $email = sanitize_email($_POST['email']);
        $role = sanitize_text_field($_POST['role']);
        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        try {
            $this->invite_team_member($email, $role, $platforms, $message);
            wp_send_json_success(__('Team member invitation sent successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_remove_team_member() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // Validate required POST parameters
        if (!isset($_POST['user_id'])) {
            wp_send_json_error(__('Missing required parameters'));
        }
        
        $user_id = intval($_POST['user_id']);
        
        try {
            $this->remove_team_member($user_id);
            wp_send_json_success(__('Team member removed successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_bulk_update_permissions() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        // Validate required POST parameters
        if (!isset($_POST['action']) || !isset($_POST['platform'])) {
            wp_send_json_error(__('Missing required parameters'));
        }
        
        $user_ids = array_map('intval', $_POST['user_ids'] ?? array());
        $action = sanitize_text_field($_POST['action']); // 'grant' or 'revoke'
        $platform = sanitize_text_field($_POST['platform']);
        $permissions = array_map('sanitize_text_field', $_POST['permissions'] ?? array());
        
        $updated_count = 0;
        foreach ($user_ids as $user_id) {
            try {
                if ($action === 'grant') {
                    $this->grant_platform_permission($user_id, $platform, $permissions);
                } else {
                    $this->revoke_platform_permission($user_id, $platform);
                }
                $updated_count++;
            } catch (\Exception $e) {
                error_log("SMO Social: Failed to {$action} permission for user {$user_id}: " . $e->getMessage());
            }
        }
        
        wp_send_json_success(array(
            'updated_count' => $updated_count,
            'message' => sprintf(__('Successfully %s permissions for %d users', 'smo-social'), $action, $updated_count)
        ));
    }
}
