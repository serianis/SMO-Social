<?php
/**
 * Team Manager
 * 
 * Handles all team management functionality including members, assignments,
 * permissions, network groups, and multisite access
 * 
 * @package SMO_Social
 * @subpackage Team
 * @since 1.0.0
 */

namespace SMO_Social\Team;

if (!defined('ABSPATH')) {
    exit;
}

class TeamManager {
    
    /**
     * Get all team members
     * 
     * @return array
     */
    public static function get_team_members() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_members';
        
        $members = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY joined_at DESC",
            ARRAY_A
        );
        
        // Enhance with post statistics
        foreach ($members as &$member) {
            $member['scheduled_posts'] = self::get_member_scheduled_posts_count($member['user_id']);
            $member['published_posts'] = self::get_member_published_posts_count($member['user_id']);
            $member['name'] = $member['display_name'];
        }
        
        return $members;
    }
    
    /**
     * Get member scheduled posts count
     * 
     * @param int $user_id
     * @return int
     */
    private static function get_member_scheduled_posts_count($user_id) {
        global $wpdb;
        $posts_table = $wpdb->prefix . 'smo_posts';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE user_id = %d AND status = 'scheduled'",
            $user_id
        ));
    }
    
    /**
     * Get member published posts count
     * 
     * @param int $user_id
     * @return int
     */
    private static function get_member_published_posts_count($user_id) {
        global $wpdb;
        $posts_table = $wpdb->prefix . 'smo_posts';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE user_id = %d AND status = 'published'",
            $user_id
        ));
    }
    
    /**
     * Get user network assignments
     * 
     * @return array
     */
    public static function get_user_network_assignments() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_assignments';
        $members_table = $wpdb->prefix . 'smo_team_members';
        
        $assignments = $wpdb->get_results(
            "SELECT a.*, m.display_name as member_name 
             FROM $table_name a
             LEFT JOIN $members_table m ON a.user_id = m.user_id
             ORDER BY a.created_at DESC",
            ARRAY_A
        );
        
        // Decode JSON fields
        foreach ($assignments as &$assignment) {
            $assignment['platforms'] = json_decode($assignment['platforms'] ?? '[]', true) ?: [];
            $assignment['url_tracking'] = !empty($assignment['url_tracking_params']);
            $assignment['type'] = ucfirst(str_replace('_', ' ', $assignment['assignment_type']));
        }
        
        return $assignments;
    }
    
    /**
     * Get team permissions
     * 
     * @return array
     */
    public static function get_team_permissions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_permissions';
        
        $permissions = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY user_id, permission_key",
            ARRAY_A
        );
        
        // Organize by user_id
        $organized = [];
        foreach ($permissions as $perm) {
            if (!isset($organized[$perm['user_id']])) {
                $organized[$perm['user_id']] = [];
            }
            $organized[$perm['user_id']][$perm['permission_key']] = (bool) $perm['permission_value'];
        }
        
        return $organized;
    }
    
    /**
     * Get team calendar data
     * 
     * @return array
     */
    public static function get_team_calendar_data() {
        global $wpdb;
        $posts_table = $wpdb->prefix . 'smo_posts';
        
        // Get total scheduled posts
        $total_scheduled = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $posts_table WHERE status = 'scheduled'"
        );
        
        // Get published today
        $published_today = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $posts_table 
             WHERE status = 'published' 
             AND DATE(created_at) = CURDATE()"
        );
        
        // Get all scheduled posts with user info
        $posts = $wpdb->get_results(
            "SELECT p.*, u.display_name as author_name
             FROM $posts_table p
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.status = 'scheduled'
             ORDER BY p.scheduled_time ASC
             LIMIT 100",
            ARRAY_A
        );
        
        return [
            'total_scheduled' => $total_scheduled,
            'published_today' => $published_today,
            'posts' => $posts
        ];
    }
    
    /**
     * Get network groups
     * 
     * @return array
     */
    public static function get_network_groups() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_network_groups';
        
        $groups = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC",
            ARRAY_A
        );
        
        // Decode JSON fields
        foreach ($groups as &$group) {
            $group['platforms'] = json_decode($group['platforms'] ?? '[]', true) ?: [];
            $member_ids = json_decode($group['members'] ?? '[]', true) ?: [];
            
            // Get member details
            $group['members'] = self::get_members_by_ids($member_ids);
        }
        
        return $groups;
    }
    
    /**
     * Get members by IDs
     * 
     * @param array $member_ids
     * @return array
     */
    private static function get_members_by_ids($member_ids) {
        if (empty($member_ids)) {
            return [];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_members';
        $placeholders = implode(',', array_fill(0, count($member_ids), '%d'));
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, display_name as name, email FROM $table_name WHERE user_id IN ($placeholders)",
                $member_ids
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get permission checkbox HTML
     * 
     * @param int $user_id
     * @param string $permission_key
     * @return string
     */
    public static function get_permission_checkbox($user_id, $permission_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_permissions';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT permission_value FROM $table_name WHERE user_id = %d AND permission_key = %s",
            $user_id,
            $permission_key
        ));
        
        $checked = $value ? 'checked' : '';
        
        return sprintf(
            '<input type="checkbox" class="smo-permission-checkbox" data-user-id="%d" data-permission="%s" %s>',
            $user_id,
            esc_attr($permission_key),
            $checked
        );
    }
    
    /**
     * Render team calendar
     * 
     * @param array $calendar_data
     * @return string
     */
    public static function render_team_calendar($calendar_data) {
        if (empty($calendar_data['posts'])) {
            return '<p>' . __('No scheduled posts found.', 'smo-social') . '</p>';
        }
        
        $html = '<div class="smo-calendar-grid">';
        
        // Group posts by date
        $posts_by_date = [];
        foreach ($calendar_data['posts'] as $post) {
            $date = date('Y-m-d', strtotime($post['scheduled_time']));
            if (!isset($posts_by_date[$date])) {
                $posts_by_date[$date] = [];
            }
            $posts_by_date[$date][] = $post;
        }
        
        // Render calendar
        foreach ($posts_by_date as $date => $posts) {
            $html .= '<div class="smo-calendar-day">';
            $html .= '<h4>' . date('F j, Y', strtotime($date)) . '</h4>';
            
            foreach ($posts as $post) {
                $html .= '<div class="smo-calendar-post" data-created-by="' . esc_attr($post['user_id']) . '">';
                $html .= '<div class="smo-post-time">' . date('g:i A', strtotime($post['scheduled_time'])) . '</div>';
                $html .= '<div class="smo-post-author">' . esc_html($post['author_name'] ?? 'Unknown') . '</div>';
                $html .= '<div class="smo-post-content">' . esc_html(wp_trim_words($post['content'], 10)) . '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Add team member
     * 
     * @param array $data
     * @return int|false
     */
    public static function add_team_member($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_members';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $data['user_id'],
                'role' => $data['role'] ?? 'member',
                'display_name' => $data['name'],
                'email' => $data['email'],
                'status' => 'active',
                'invited_by' => get_current_user_id()
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Create network assignment
     * 
     * @param array $data
     * @return int|false
     */
    public static function create_assignment($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_assignments';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'assignment_name' => $data['name'],
                'user_id' => $data['user_id'],
                'assignment_type' => $data['type'],
                'resource_id' => $data['resource_id'] ?? null,
                'platforms' => json_encode($data['platforms'] ?? []),
                'url_tracking_params' => json_encode($data['url_params'] ?? []),
                'access_level' => $data['access_level'] ?? 'view',
                'created_by' => get_current_user_id()
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update team permission
     * 
     * @param int $user_id
     * @param string $permission_key
     * @param bool $value
     * @return bool
     */
    public static function update_permission($user_id, $permission_key, $value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_permissions';
        
        // Check if permission exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND permission_key = %s",
            $user_id,
            $permission_key
        ));
        
        if ($exists) {
            // Update existing
            return $wpdb->update(
                $table_name,
                ['permission_value' => $value ? 1 : 0],
                ['user_id' => $user_id, 'permission_key' => $permission_key],
                ['%d'],
                ['%d', '%s']
            ) !== false;
        } else {
            // Insert new
            return $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'permission_key' => $permission_key,
                    'permission_value' => $value ? 1 : 0,
                    'granted_by' => get_current_user_id()
                ],
                ['%d', '%s', '%d', '%d']
            ) !== false;
        }
    }
    
    /**
     * Create network group
     * 
     * @param array $data
     * @return int|false
     */
    public static function create_network_group($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_network_groups';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'platforms' => json_encode($data['platforms'] ?? []),
                'members' => json_encode($data['members'] ?? []),
                'settings' => json_encode($data['settings'] ?? []),
                'color' => $data['color'] ?? '#3b82f6',
                'icon' => $data['icon'] ?? 'users',
                'created_by' => get_current_user_id()
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get multisite sites (if multisite is enabled)
     * 
     * @return array
     */
    public static function get_multisite_sites() {
        if (!is_multisite()) {
            return [];
        }
        
        $sites = get_sites(['number' => 100]);
        $result = [];
        
        foreach ($sites as $site) {
            $result[] = [
                'id' => $site->blog_id,
                'name' => get_blog_option($site->blog_id, 'blogname'),
                'url' => get_site_url($site->blog_id),
                'active' => $site->deleted == 0
            ];
        }
        
        return $result;
    }
    
    /**
     * Grant multisite access
     * 
     * @param int $user_id
     * @param int $site_id
     * @param string $access_level
     * @return bool
     */
    public static function grant_multisite_access($user_id, $site_id, $access_level = 'view') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_multisite_access';
        
        // Check if access already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND site_id = %d",
            $user_id,
            $site_id
        ));
        
        if ($exists) {
            // Update existing
            return $wpdb->update(
                $table_name,
                ['access_level' => $access_level],
                ['user_id' => $user_id, 'site_id' => $site_id],
                ['%s'],
                ['%d', '%d']
            ) !== false;
        } else {
            // Insert new
            return $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'site_id' => $site_id,
                    'access_level' => $access_level,
                    'granted_by' => get_current_user_id()
                ],
                ['%d', '%d', '%s', '%d']
            ) !== false;
        }
    }
}
