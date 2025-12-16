<?php
namespace SMO_Social\Team;

class PermissionValidator {
    private $role_manager;
    private $user_cache;
    
    public function __construct($role_manager = null) {
        $this->role_manager = $role_manager ?: new RoleManager();
        $this->user_cache = array();
    }
    
    public function validate_user_permission($user_id, $permission, $context = array()) {
        // Check cache first
        $cache_key = $user_id . '_' . $permission;
        if (isset($this->user_cache[$cache_key])) {
            return $this->user_cache[$cache_key];
        }
        
        // Validate permission
        $has_permission = $this->role_manager->user_has_permission($user_id, $permission);
        
        $result = array(
            'has_permission' => $has_permission,
            'user_id' => $user_id,
            'permission' => $permission,
            'context' => $context,
            'timestamp' => current_time('mysql')
        );
        
        // Cache result
        $this->user_cache[$cache_key] = $result;
        
        return $result;
    }
    
    public function validate_bulk_permissions($user_id, $permissions) {
        $results = array();
        
        foreach ($permissions as $permission) {
            $results[$permission] = $this->validate_user_permission($user_id, $permission);
        }
        
        return $results;
    }
    
    public function require_permission($user_id, $permission, $error_message = '') {
        $validation = $this->validate_user_permission($user_id, $permission);
        
        if (!$validation['has_permission']) {
            $default_message = "Access denied: User {$user_id} does not have permission '{$permission}'";
            $message = $error_message ?: $default_message;
            
            return array(
                'success' => false,
                'error' => $message,
                'code' => 'PERMISSION_DENIED'
            );
        }
        
        return array('success' => true);
    }
    
    public function check_content_access($user_id, $content_id, $action = 'view') {
        // Check if user can access specific content
        $content_permissions = array(
            'view' => 'view_content',
            'edit' => 'edit_content',
            'delete' => 'delete_content',
            'publish' => 'publish_content'
        );
        
        if (!isset($content_permissions[$action])) {
            return array(
                'success' => false,
                'error' => 'Invalid content action'
            );
        }
        
        $permission = $content_permissions[$action];
        return $this->require_permission($user_id, $permission);
    }
    
    public function check_platform_access($user_id, $platform_slug) {
        // Check if user can access specific platform
        $validation = $this->validate_user_permission($user_id, 'manage_platforms');
        
        if (!$validation['has_permission']) {
            return array(
                'success' => false,
                'error' => "User does not have access to manage platforms"
            );
        }
        
        return array('success' => true);
    }
    
    public function check_scheduling_permission($user_id, $content_type = 'post') {
        $required_permissions = array(
            'post' => 'schedule_posts',
            'story' => 'schedule_stories',
            'reel' => 'schedule_reels'
        );
        
        if (!isset($required_permissions[$content_type])) {
            return array(
                'success' => false,
                'error' => 'Invalid content type for scheduling'
            );
        }
        
        $permission = $required_permissions[$content_type];
        return $this->require_permission($user_id, $permission);
    }
    
    public function check_analytics_access($user_id, $platform_slug = '') {
        $validation = $this->validate_user_permission($user_id, 'view_analytics');
        
        if (!$validation['has_permission']) {
            return array(
                'success' => false,
                'error' => "User does not have access to view analytics"
            );
        }
        
        return array('success' => true);
    }
    
    public function get_user_capabilities($user_id) {
        $permissions = $this->role_manager->get_user_permissions($user_id);
        
        $capabilities = array(
            'user_id' => $user_id,
            'permissions' => $permissions,
            'can_manage_platforms' => in_array('manage_platforms', $permissions),
            'can_manage_users' => in_array('manage_users', $permissions),
            'can_manage_content' => in_array('manage_content', $permissions),
            'can_view_analytics' => in_array('view_analytics', $permissions),
            'can_manage_settings' => in_array('manage_settings', $permissions),
            'can_create_content' => in_array('create_content', $permissions),
            'can_schedule_posts' => in_array('schedule_posts', $permissions),
            'can_view_dashboard' => in_array('view_dashboard', $permissions)
        );
        
        return $capabilities;
    }
    
    public function clear_user_cache($user_id = null) {
        if ($user_id) {
            // Clear cache for specific user
            foreach (array_keys($this->user_cache) as $cache_key) {
                if (strpos($cache_key, $user_id . '_') === 0) {
                    unset($this->user_cache[$cache_key]);
                }
            }
        } else {
            // Clear all cache
            $this->user_cache = array();
        }
        
        return array('success' => true);
    }
    
    public function log_permission_check($user_id, $permission, $result, $context = array()) {
        // Log permission check for auditing
        if (function_exists('is_plugin_active')) {
            global $wpdb;
            if ($wpdb) {
                $table_name = $wpdb->prefix . 'smo_permission_logs';
                $wpdb->insert($table_name, array(
                    'user_id' => $user_id,
                    'permission' => $permission,
                    'result' => $result ? 'granted' : 'denied',
                    'context' => json_encode($context),
                    'timestamp' => current_time('mysql')
                ));
            }
        }
        
        return array('success' => true);
    }
    
    public function validate_api_access($api_key, $required_permission) {
        // Validate API key access
        $api_validator = new \SMO_Social\Security\APIKeyValidator();
        $validation = $api_validator->validate_api_key($api_key);
        
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'error' => 'Invalid API key'
            );
        }
        
        $has_permission = $api_validator->check_permissions($api_key, $required_permission);
        
        if (!$has_permission) {
            return array(
                'success' => false,
                'error' => "API key does not have permission: {$required_permission}"
            );
        }
        
        return array('success' => true);
    }

    // Alias methods for test compatibility
    public function can_create_post($user_id) {
        return $this->validate_user_permission($user_id, 'create_content')['has_permission'];
    }

    public function can_access_platform($user_id, $platform_slug) {
        return $this->check_platform_access($user_id, $platform_slug)['success'];
    }

    public function can_view_analytics($user_id) {
        return $this->check_analytics_access($user_id)['success'];
    }

    public function can_manage_team($user_id) {
        return $this->validate_user_permission($user_id, 'manage_users')['has_permission'];
    }
}
