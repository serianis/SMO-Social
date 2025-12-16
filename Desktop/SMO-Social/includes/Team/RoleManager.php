<?php
namespace SMO_Social\Team;

class RoleManager {
    private $roles;
    private $permissions;
    private $user_roles;
    
    public function __construct() {
        $this->roles = array();
        $this->permissions = array();
        $this->user_roles = array();
        
        $this->initialize_default_roles();
    }
    
    public function initialize_default_roles() {
        $default_roles = array(
            'admin' => array(
                'name' => 'Administrator',
                'permissions' => array(
                    'manage_platforms',
                    'manage_users',
                    'manage_content',
                    'view_analytics',
                    'manage_settings',
                    'view_dashboard'
                )
            ),
            'editor' => array(
                'name' => 'Editor',
                'permissions' => array(
                    'manage_content',
                    'schedule_posts',
                    'view_analytics',
                    'view_dashboard'
                )
            ),
            'author' => array(
                'name' => 'Author',
                'permissions' => array(
                    'create_content',
                    'edit_own_content',
                    'schedule_own_posts',
                    'view_dashboard'
                )
            ),
            'contributor' => array(
                'name' => 'Contributor',
                'permissions' => array(
                    'create_content',
                    'edit_own_content'
                )
            ),
            'viewer' => array(
                'name' => 'Viewer',
                'permissions' => array(
                    'view_dashboard'
                )
            )
        );
        
        foreach ($default_roles as $role_slug => $role_data) {
            $this->add_role($role_slug, $role_data);
        }
    }
    
    public function add_role($role_slug, $role_data) {
        $this->roles[$role_slug] = array(
            'slug' => $role_slug,
            'name' => $role_data['name'],
            'permissions' => $role_data['permissions'],
            'created_at' => current_time('mysql')
        );
        
        return array('success' => true, 'role_slug' => $role_slug);
    }
    
    public function assign_role_to_user($user_id, $role_slug) {
        if (!isset($this->roles[$role_slug])) {
            return array(
                'success' => false,
                'error' => 'Role does not exist'
            );
        }
        
        $this->user_roles[$user_id] = $role_slug;
        
        return array(
            'success' => true,
            'user_id' => $user_id,
            'role_slug' => $role_slug
        );
    }
    
    public function remove_role_from_user($user_id) {
        if (isset($this->user_roles[$user_id])) {
            unset($this->user_roles[$user_id]);
            return array('success' => true);
        }
        
        return array(
            'success' => false,
            'error' => 'User has no role assigned'
        );
    }
    
    public function user_has_permission($user_id, $permission) {
        $user_role = $this->get_user_role($user_id);
        
        if (!$user_role) {
            return false;
        }
        
        $role_permissions = $this->get_role_permissions($user_role);
        
        return in_array($permission, $role_permissions);
    }
    
    public function get_user_role($user_id) {
        return $this->user_roles[$user_id] ?? null;
    }
    
    public function get_role_permissions($role_slug) {
        return $this->roles[$role_slug]['permissions'] ?? array();
    }
    
    public function add_permission_to_role($role_slug, $permission) {
        if (!isset($this->roles[$role_slug])) {
            return array(
                'success' => false,
                'error' => 'Role does not exist'
            );
        }
        
        if (!in_array($permission, $this->roles[$role_slug]['permissions'])) {
            $this->roles[$role_slug]['permissions'][] = $permission;
        }
        
        return array('success' => true);
    }
    
    public function remove_permission_from_role($role_slug, $permission) {
        if (!isset($this->roles[$role_slug])) {
            return array(
                'success' => false,
                'error' => 'Role does not exist'
            );
        }
        
        $key = array_search($permission, $this->roles[$role_slug]['permissions']);
        if ($key !== false) {
            unset($this->roles[$role_slug]['permissions'][$key]);
        }
        
        return array('success' => true);
    }
    
    public function get_all_roles() {
        return $this->roles;
    }
    
    public function get_users_with_role($role_slug) {
        $users = array();
        
        foreach ($this->user_roles as $user_id => $user_role) {
            if ($user_role === $role_slug) {
                $users[] = $user_id;
            }
        }
        
        return $users;
    }
    
    public function create_custom_role($role_slug, $role_name, $permissions = array()) {
        return $this->add_role($role_slug, array(
            'name' => $role_name,
            'permissions' => $permissions
        ));
    }
    
    public function delete_role($role_slug) {
        // Check if role is assigned to any users
        $users_with_role = $this->get_users_with_role($role_slug);
        
        if (!empty($users_with_role)) {
            return array(
                'success' => false,
                'error' => 'Cannot delete role that is assigned to users'
            );
        }
        
        if (isset($this->roles[$role_slug])) {
            unset($this->roles[$role_slug]);
            return array('success' => true);
        }
        
        return array(
            'success' => false,
            'error' => 'Role not found'
        );
    }
    
    public function get_user_permissions($user_id) {
        $role_slug = $this->get_user_role($user_id);
        
        if (!$role_slug) {
            return array();
        }
        
        return $this->get_role_permissions($role_slug);
    }
    
    public function validate_user_access($user_id, $required_permissions) {
        if (!is_array($required_permissions)) {
            $required_permissions = array($required_permissions);
        }

        foreach ($required_permissions as $permission) {
            if (!$this->user_has_permission($user_id, $permission)) {
                return array(
                    'has_access' => false,
                    'missing_permission' => $permission
                );
            }
        }

        return array('has_access' => true);
    }

    // Alias methods for test compatibility
    public function assign_role($user_id, $role_slug) {
        return $this->assign_role_to_user($user_id, $role_slug);
    }

    public function has_permission($user_id, $permission) {
        return $this->user_has_permission($user_id, $permission);
    }

    public function get_role_hierarchy() {
        $hierarchy = array();
        foreach ($this->roles as $role_slug => $role_data) {
            $hierarchy[$role_slug] = array(
                'name' => $role_data['name'],
                'permissions' => $role_data['permissions'],
                'level' => $this->get_role_level($role_slug)
            );
        }
        return $hierarchy;
    }

    private function get_role_level($role_slug) {
        $levels = array(
            'viewer' => 1,
            'contributor' => 2,
            'author' => 3,
            'editor' => 4,
            'admin' => 5
        );
        return $levels[$role_slug] ?? 0;
    }
}
