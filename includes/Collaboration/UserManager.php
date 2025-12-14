<?php
namespace SMO_Social\Collaboration;

if (!defined('ABSPATH')) {
    exit; // Security check
}

// PHP Superglobal variables (for Intelephense compatibility) - Direct declarations
global $_POST, $_SERVER, $_GET, $_COOKIE, $_FILES;
if (!isset($_POST)) $_POST = array();
if (!isset($_SERVER)) $_SERVER = array('REMOTE_ADDR' => '127.0.0.1');
if (!isset($_GET)) $_GET = array();
if (!isset($_COOKIE)) $_COOKIE = array();
if (!isset($_FILES)) $_FILES = array();

/**
 * SMO_UserManager - Multi-user Collaboration System
 * 
 * Handles user management, role-based permissions, and collaboration features
 * for team-based social media management.
 * 
 * @property-read array $_POST Superglobal POST data
 * @property-read array $_GET Superglobal GET data  
 * @property-read array $_SERVER Superglobal server data
 * @property-read array $_COOKIE Superglobal cookie data
 */
class UserManager {

    private $wpdb;
    private $table_name;
    private $activity_table;

    // Role definitions
    const ROLE_ADMIN = 'smo_admin';
    const ROLE_EDITOR = 'smo_editor';
    const ROLE_VIEWER = 'smo_viewer';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'smo_collaboration_users';
        $this->activity_table = $wpdb->prefix . 'smo_collaboration_activity';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // User management hooks
        add_action('show_user_profile', array($this, 'add_smo_user_fields'));
        add_action('edit_user_profile', array($this, 'add_smo_user_fields'));
        add_action('personal_options_update', array($this, 'save_smo_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_smo_user_fields'));

        // AJAX handlers
        add_action('wp_ajax_smo_add_team_member', array($this, 'ajax_add_team_member'));
        add_action('wp_ajax_smo_update_user_role', array($this, 'ajax_update_user_role'));
        add_action('wp_ajax_smo_remove_team_member', array($this, 'ajax_remove_team_member'));
        add_action('wp_ajax_smo_get_team_members', array($this, 'ajax_get_team_members'));
        add_action('wp_ajax_smo_get_user_activity', array($this, 'ajax_get_user_activity'));

        // Activity logging
        add_action('smo_user_action', array($this, 'log_user_activity'), 10, 3);

        // Ensure tables exist
        add_action('init', array($this, 'ensure_collaboration_tables'));
    }

    /**
     * Ensure collaboration tables exist
     */
    public function ensure_collaboration_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Collaboration users table
        $users_table = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            role varchar(50) NOT NULL DEFAULT 'viewer',
            permissions longtext DEFAULT '',
            assigned_platforms longtext DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            added_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_role (user_id, role),
            KEY user_status (status)
        ) $charset_collate;";

        // Activity log table
        $activity_table = "CREATE TABLE IF NOT EXISTS {$this->activity_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            target_type varchar(50) DEFAULT '',
            target_id bigint(20) DEFAULT NULL,
            details longtext DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_action (user_id, action),
            KEY action_type (action, target_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($users_table);
        dbDelta($activity_table);

        // Add current admin user as SMO admin if not exists
        $this->ensure_admin_user_exists();
    }

    /**
     * Ensure at least one admin user exists
     */
    private function ensure_admin_user_exists() {
        $admin_role = get_option('smo_admin_role_assigned', false);
        if (!$admin_role) {
            $current_user = wp_get_current_user();
            if ($current_user->ID) {
                $this->add_user_to_smo($current_user->ID, self::ROLE_ADMIN, $current_user->ID);
                update_option('smo_admin_role_assigned', true);
                do_action('smo_user_action', $current_user->ID, 'admin_assigned', 'system');
            }
        }
    }

    /**
     * Add user to SMO collaboration system
     */
    public function add_user_to_smo($user_id, $role = self::ROLE_VIEWER, $added_by = null) {
        // Validate role
        if (!$this->is_valid_role($role)) {
            return false;
        }

        // Check if user already exists
        $existing = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            // Update existing user
            $result = $this->wpdb->update(
                $this->table_name,
                array(
                    'role' => $role,
                    'status' => 'active'
                ),
                array('user_id' => $user_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new user
            $result = $this->wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'role' => $role,
                    'status' => 'active',
                    'added_by' => $added_by,
                    'permissions' => json_encode($this->get_default_permissions($role))
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
        }

        if ($result !== false) {
            do_action('smo_user_action', $user_id, 'added_to_smo', array(
                'role' => $role,
                'added_by' => $added_by
            ));
            return true;
        }

        return false;
    }

    /**
     * Remove user from SMO collaboration system
     */
    public function remove_user_from_smo($user_id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('user_id' => $user_id),
            array('%d')
        );

        if ($result !== false) {
            do_action('smo_user_action', $user_id, 'removed_from_smo', 'system');
            return true;
        }

        return false;
    }

    /**
     * Get user SMO role
     */
    public function get_user_smo_role($user_id) {
        $user = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT role FROM {$this->table_name} WHERE user_id = %d AND status = 'active'",
            $user_id
        ));

        return $user ? $user->role : null;
    }

    /**
     * Get all team members
     */
    public function get_team_members($status = 'active') {
        $query = "
            SELECT u.*, um.display_name, um.user_email 
            FROM {$this->table_name} u
            INNER JOIN {$this->wpdb->users} um ON u.user_id = um.ID
            WHERE u.status = %s
            ORDER BY u.created_at ASC
        ";

        return $this->wpdb->get_results($this->wpdb->prepare($query, $status));
    }

    /**
     * Check if user has permission
     */
    public function user_has_permission($user_id, $permission) {
        $role = $this->get_user_smo_role($user_id);
        if (!$role) {
            return false;
        }

        $user = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT permissions FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ));

        if (!$user) {
            return false;
        }

        $permissions = json_decode($user->permissions, true);
        
        // Admin has all permissions
        if ($role === self::ROLE_ADMIN) {
            return true;
        }

        return isset($permissions[$permission]) && $permissions[$permission] === true;
    }

    /**
     * Get user permissions
     */
    public function get_user_permissions($user_id) {
        $user = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT permissions FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ));

        return $user ? json_decode($user->permissions, true) : array();
    }

    /**
     * Update user permissions
     */
    public function update_user_permissions($user_id, $permissions) {
        $result = $this->wpdb->update(
            $this->table_name,
            array('permissions' => json_encode($permissions)),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            do_action('smo_user_action', $user_id, 'permissions_updated', $permissions);
            return true;
        }

        return false;
    }

    /**
     * Get user activity log
     */
    public function get_user_activity($user_id, $limit = 50, $offset = 0) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->activity_table} 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
    }

    /**
     * Log user activity
     */
    public function log_user_activity($user_id, $action, $details = '') {
        global $_SERVER;
        $this->wpdb->insert(
            $this->activity_table,
            array(
                'user_id' => $user_id,
                'action' => $action,
                'details' => is_array($details) ? json_encode($details) : $details,
                'ip_address' => $this->get_user_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        // Clean old activity logs (keep last 1000 entries per user)
        $this->cleanup_old_activity_logs($user_id);
    }

    /**
     * Get collaboration statistics
     */
    public function get_collaboration_stats() {
        $stats = array();

        // Team size by role
        $stats['team_by_role'] = $this->wpdb->get_results(
            "SELECT role, COUNT(*) as count FROM {$this->table_name} 
             WHERE status = 'active' GROUP BY role"
        );

        // Recent activity count
        $stats['recent_activity'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->activity_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // Most active users
        $stats['most_active_users'] = $this->wpdb->get_results(
            "SELECT a.user_id, u.display_name, COUNT(*) as activity_count 
             FROM {$this->activity_table} a
             INNER JOIN {$this->wpdb->users} u ON a.user_id = u.ID
             WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY a.user_id
             ORDER BY activity_count DESC
             LIMIT 10"
        );

        return $stats;
    }

    /**
     * AJAX: Add team member
     */
    public function ajax_add_team_member() {
        /** @var array $_POST */
        check_ajax_referer('smo_collaboration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Copy POST data to local variables to avoid IDE errors
        $post_data = $_POST;
        $user_email = sanitize_email($post_data['user_email']);
        $role = sanitize_text_field($post_data['role']);
        $platforms = isset($post_data['platforms']) ? array_map('sanitize_text_field', $post_data['platforms']) : array();

        // Find user by email
        $user = get_user_by('email', $user_email);
        if (!$user) {
            wp_send_json_error('User not found');
        }

        // Check if user already exists in SMO
        $existing_role = $this->get_user_smo_role($user->ID);
        if ($existing_role) {
            wp_send_json_error('User is already a team member with role: ' . $existing_role);
        }

        // Add user to SMO
        $result = $this->add_user_to_smo($user->ID, $role, get_current_user_id());
        if ($result) {
            // Set platform assignments
            if (!empty($platforms)) {
                $this->assign_platforms_to_user($user->ID, $platforms);
            }

            wp_send_json_success(array(
                'message' => 'Team member added successfully',
                'user_id' => $user->ID,
                'display_name' => $user->display_name
            ));
        } else {
            wp_send_json_error('Failed to add team member');
        }
    }

    /**
     * AJAX: Update user role
     */
    public function ajax_update_user_role() {
        /** @var array $_POST */
        check_ajax_referer('smo_collaboration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        /** @var array $_POST */
        $user_id = intval($_POST['user_id']);
        $new_role = sanitize_text_field($_POST['role']);

        if (!$this->is_valid_role($new_role)) {
            wp_send_json_error('Invalid role');
        }

        $result = $this->wpdb->update(
            $this->table_name,
            array('role' => $new_role),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            do_action('smo_user_action', $user_id, 'role_updated', array(
                'new_role' => $new_role,
                'updated_by' => get_current_user_id()
            ));
            wp_send_json_success('Role updated successfully');
        } else {
            wp_send_json_error('Failed to update role');
        }
    }

    /**
     * AJAX: Remove team member
     */
    public function ajax_remove_team_member() {
        /** @var array $_POST */
        check_ajax_referer('smo_collaboration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        /** @var array $_POST */
        $user_id = intval($_POST['user_id']);

        $result = $this->remove_user_from_smo($user_id);
        if ($result) {
            wp_send_json_success('Team member removed successfully');
        } else {
            wp_send_json_error('Failed to remove team member');
        }
    }

    /**
     * AJAX: Get team members
     */
    public function ajax_get_team_members() {
        check_ajax_referer('smo_collaboration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $members = $this->get_team_members();
        wp_send_json_success($members);
    }

    /**
     * AJAX: Get user activity
     */
    public function ajax_get_user_activity() {
        /** @var array $_POST */
        check_ajax_referer('smo_collaboration_nonce', 'nonce');

        $user_id = intval($_POST['user_id']);
        $limit = intval($_POST['limit']) ?: 50;

        if (!current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            wp_send_json_error('Insufficient permissions');
        }

        /** @var array $_POST */
        $activity = $this->get_user_activity($user_id, $limit);
        wp_send_json_success($activity);
    }

    /**
     * Helper methods
     */

    private function is_valid_role($role) {
        return in_array($role, array(self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_VIEWER));
    }

    private function get_default_permissions($role) {
        $base_permissions = array();

        switch ($role) {
            case self::ROLE_ADMIN:
                $base_permissions = array(
                    'manage_team' => true,
                    'manage_platforms' => true,
                    'manage_content' => true,
                    'approve_content' => true,
                    'view_analytics' => true,
                    'export_data' => true,
                    'manage_settings' => true
                );
                break;

            case self::ROLE_EDITOR:
                $base_permissions = array(
                    'manage_content' => true,
                    'approve_content' => true,
                    'view_analytics' => true,
                    'export_data' => false,
                    'manage_settings' => false,
                    'manage_team' => false
                );
                break;

            case self::ROLE_VIEWER:
                $base_permissions = array(
                    'manage_content' => false,
                    'approve_content' => false,
                    'view_analytics' => true,
                    'export_data' => false,
                    'manage_settings' => false,
                    'manage_team' => false
                );
                break;
        }

        return $base_permissions;
    }

    private function assign_platforms_to_user($user_id, $platforms) {
        $this->wpdb->update(
            $this->table_name,
            array('assigned_platforms' => json_encode($platforms)),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
    }

    private function get_user_ip() {
        global $_SERVER;
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    private function cleanup_old_activity_logs($user_id) {
        // Keep only the most recent 1000 entries per user
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->activity_table} 
             WHERE user_id = %d 
             AND id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM {$this->activity_table} 
                     WHERE user_id = %d 
                     ORDER BY created_at DESC 
                     LIMIT 1000
                 ) AS temp
             )",
            $user_id, $user_id
        ));
    }

    /**
     * Add SMO user fields to user profile
     */
    public function add_smo_user_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $role = $this->get_user_smo_role($user->ID);
        $permissions = $this->get_user_permissions($user->ID);
        ?>
        <h3>SMO Social Collaboration</h3>
        <table class="form-table">
            <tr>
                <th><label for="smo_role">SMO Role</label></th>
                <td>
                    <select name="smo_role" id="smo_role">
                        <option value="">Not in SMO Team</option>
                        <option value="<?php echo self::ROLE_ADMIN; ?>" <?php selected($role, self::ROLE_ADMIN); ?>>Admin</option>
                        <option value="<?php echo self::ROLE_EDITOR; ?>" <?php selected($role, self::ROLE_EDITOR); ?>>Editor</option>
                        <option value="<?php echo self::ROLE_VIEWER; ?>" <?php selected($role, self::ROLE_VIEWER); ?>>Viewer</option>
                    </select>
                    <br><span class="description">User's role in SMO Social collaboration</span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save SMO user fields
     */
    public function save_smo_user_fields($user_id) {
        /** @var array $_POST */
        if (!current_user_can('manage_options')) {
            return;
        }

        $new_role = isset($_POST['smo_role']) ? sanitize_text_field($_POST['smo_role']) : '';
        $current_role = $this->get_user_smo_role($user_id);

        if (empty($new_role)) {
            // Remove user from SMO if role is empty
            if ($current_role) {
                $this->remove_user_from_smo($user_id);
            }
        } else {
            // Add or update user role
            if ($current_role !== $new_role) {
                $this->add_user_to_smo($user_id, $new_role, get_current_user_id());
            }
        }
    }
}
