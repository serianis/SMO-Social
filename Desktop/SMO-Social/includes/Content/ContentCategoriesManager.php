<?php
/**
 * Content Categories Manager
 * Organize your content into easy-to-find categories
 * 
 * @package SMO_Social
 * @subpackage Content
 * @since 1.0.0
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

// Fallback for sanitize_hex_color if not available
if (!function_exists('sanitize_hex_color')) {
    /**
     * Sanitize hex color
     * @param string $color
     * @return string|null
     */
    function sanitize_hex_color($color) {
        if ('' === $color) {
            return '';
        }
        
        // 3 or 6 hex digits, or the empty string.
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) {
            return $color;
        }
        
        return null;
    }
}

/**
 * Content Categories Manager
 * 
 * Provides content organization through categories:
 * - Create custom categories for posts
 * - Organize future posts by category
 * - Filter and view posts by category
 * - Category-based analytics
 * - Color-coded category system
 */
class ContentCategoriesManager {
    
    public function __construct() {
        $this->init_hooks();
        $this->create_tables();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_add_content_category', array($this, 'ajax_add_category'));
        add_action('wp_ajax_smo_update_content_category', array($this, 'ajax_update_category'));
        add_action('wp_ajax_smo_delete_content_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_smo_get_content_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_smo_assign_post_to_category', array($this, 'ajax_assign_post_to_category'));
        add_action('wp_ajax_smo_get_posts_by_category', array($this, 'ajax_get_posts_by_category'));
        add_action('wp_ajax_smo_get_category_analytics', array($this, 'ajax_get_category_analytics'));
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Content categories table
        $categories_table = $wpdb->prefix . 'smo_content_categories';
        $sql = "CREATE TABLE IF NOT EXISTS $categories_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            color_code varchar(7) DEFAULT '#007cba',
            icon varchar(50) DEFAULT 'dashicons-category',
            parent_id bigint(20) unsigned DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            post_count int(11) DEFAULT 0,
            is_default boolean DEFAULT 0,
            is_active boolean DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_parent_id (parent_id),
            KEY idx_is_active (is_active),
            KEY idx_sort_order (sort_order)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
        
        // Post category assignments table
        $assignments_table = $wpdb->prefix . 'smo_post_category_assignments';
        $sql = "CREATE TABLE IF NOT EXISTS $assignments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_category (post_id, category_id),
            KEY idx_post_id (post_id),
            KEY idx_category_id (category_id),
            KEY idx_user_id (user_id)
        ) $charset_collate;";
        
        \dbDelta($sql);
    }
    
    /**
     * Add a new content category
     */
    public function add_category($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_content_categories';
        
        $data = DataValidator::validate_content_category($data);
        
        $insert_data = array(
            'user_id' => get_current_user_id(),
            'name' => $data['name'],
            'description' => $data['description'],
            'color_code' => $data['color_code'],
            'icon' => $data['icon'],
            'parent_id' => $data['parent_id'],
            'sort_order' => $data['sort_order'],
            'is_default' => $data['is_default'] ? 1 : 0
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            throw new \Exception(__('Failed to create category', 'smo-social'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a content category
     */
    public function update_category($category_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_content_categories';
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['color_code'])) {
            $update_data['color_code'] = sanitize_hex_color($data['color_code']);
        }
        if (isset($data['icon'])) {
            $update_data['icon'] = sanitize_text_field($data['icon']);
        }
        if (isset($data['parent_id'])) {
            $update_data['parent_id'] = intval($data['parent_id']);
        }
        if (isset($data['sort_order'])) {
            $update_data['sort_order'] = intval($data['sort_order']);
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = (bool)$data['is_active'];
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $category_id, 'user_id' => get_current_user_id())
        );
        
        if ($result === false) {
            throw new \Exception(__('Failed to update category', 'smo-social'));
        }
        
        return true;
    }
    
    /**
     * Delete a content category
     */
    public function delete_category($category_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_content_categories';
        
        // First, remove all post assignments
        $assignments_table = $wpdb->prefix . 'smo_post_category_assignments';
        $wpdb->delete($assignments_table, array('category_id' => $category_id));
        
        // Then delete the category
        $result = $wpdb->delete(
            $table_name,
            array('id' => $category_id, 'user_id' => get_current_user_id())
        );
        
        if ($result === false) {
            throw new \Exception(__('Failed to delete category', 'smo-social'));
        }
        
        return true;
    }
    
    /**
     * Get all categories for current user
     */
    public function get_categories($filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_content_categories';
        $user_id = get_current_user_id();
        
        $where = array("user_id = %d");
        $values = array($user_id);
        
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === null) {
                $where[] = "parent_id IS NULL";
            } else {
                $where[] = "parent_id = %d";
                $values[] = intval($filters['parent_id']);
            }
        }
        
        if (isset($filters['is_active'])) {
            $where[] = "is_active = %d";
            $values[] = (bool)$filters['is_active'] ? 1 : 0;
        }
        
        $where_clause = implode(' AND ', $where);
        $order_by = "sort_order ASC, name ASC";
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $order_by";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $categories = $wpdb->get_results($query, ARRAY_A);
        
        // Update post counts
        foreach ($categories as &$category) {
            $category['post_count'] = $this->get_category_post_count($category['id']);
        }
        
        return $categories;
    }
    
    /**
     * Get category post count
     */
    private function get_category_post_count($category_id) {
        global $wpdb;
        
        $assignments_table = $wpdb->prefix . 'smo_post_category_assignments';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $assignments_table WHERE category_id = %d",
            $category_id
        ));
        
        return intval($count);
    }
    
    /**
     * Assign post to category
     */
    public function assign_post_to_category($post_id, $category_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_post_category_assignments';
        
        $result = $wpdb->replace($table_name, array(
            'post_id' => intval($post_id),
            'category_id' => intval($category_id),
            'user_id' => get_current_user_id()
        ));
        
        if ($result === false) {
            throw new \Exception(__('Failed to assign post to category', 'smo-social'));
        }
        
        return true;
    }
    
    /**
     * Remove post from category
     */
    public function remove_post_from_category($post_id, $category_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_post_category_assignments';
        
        $result = $wpdb->delete($table_name, array(
            'post_id' => intval($post_id),
            'category_id' => intval($category_id)
        ));
        
        return $result !== false;
    }
    
    /**
     * Get posts by category
     */
    public function get_posts_by_category($category_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $assignments_table = $wpdb->prefix . 'smo_post_category_assignments';
        $posts_table = $wpdb->prefix . 'smo_posts';
        
        $query = $wpdb->prepare(
            "SELECT p.* FROM $posts_table p
             INNER JOIN $assignments_table a ON p.id = a.post_id
             WHERE a.category_id = %d AND a.user_id = %d
             ORDER BY p.created_at DESC
             LIMIT %d OFFSET %d",
            $category_id,
            get_current_user_id(),
            $limit,
            $offset
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get category analytics
     */
    public function get_category_analytics($category_id) {
        global $wpdb;
        
        $assignments_table = $wpdb->prefix . 'smo_post_category_assignments';
        $posts_table = $wpdb->prefix . 'smo_posts';
        
        $analytics = array(
            'total_posts' => 0,
            'scheduled_posts' => 0,
            'published_posts' => 0,
            'draft_posts' => 0,
            'platforms_distribution' => array(),
            'upcoming_posts' => array()
        );
        
        // Get total posts
        $analytics['total_posts'] = $this->get_category_post_count($category_id);
        
        // Get posts by status
        $status_query = $wpdb->prepare(
            "SELECT p.status, COUNT(*) as count
             FROM $posts_table p
             INNER JOIN $assignments_table a ON p.id = a.post_id
             WHERE a.category_id = %d AND a.user_id = %d
             GROUP BY p.status",
            $category_id,
            get_current_user_id()
        );
        
        $status_results = $wpdb->get_results($status_query, ARRAY_A);
        
        foreach ($status_results as $row) {
            if ($row['status'] === 'scheduled') {
                $analytics['scheduled_posts'] = intval($row['count']);
            } elseif ($row['status'] === 'published') {
                $analytics['published_posts'] = intval($row['count']);
            } elseif ($row['status'] === 'draft') {
                $analytics['draft_posts'] = intval($row['count']);
            }
        }
        
        // Get upcoming posts (next 7 days)
        $upcoming_query = $wpdb->prepare(
            "SELECT p.* FROM $posts_table p
             INNER JOIN $assignments_table a ON p.id = a.post_id
             WHERE a.category_id = %d AND a.user_id = %d
             AND p.status = 'scheduled'
             AND p.scheduled_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
             ORDER BY p.scheduled_time ASC
             LIMIT 10",
            $category_id,
            get_current_user_id()
        );
        
        $analytics['upcoming_posts'] = $wpdb->get_results($upcoming_query, ARRAY_A);
        
        return $analytics;
    }
    
    /**
     * AJAX: Add category
     */
    public function ajax_add_category() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        try {
            $data = array(
                'name' => sanitize_text_field($_POST['name'] ?? ''),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'color_code' => sanitize_hex_color($_POST['color_code'] ?? '#007cba'),
                'icon' => sanitize_text_field($_POST['icon'] ?? 'dashicons-category'),
                'parent_id' => isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null,
                'sort_order' => intval($_POST['sort_order'] ?? 0)
            );
            
            $category_id = $this->add_category($data);
            
            wp_send_json_success(array(
                'message' => __('Category created successfully', 'smo-social'),
                'category_id' => $category_id
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Update category
     */
    public function ajax_update_category() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        try {
            $category_id = intval($_POST['category_id'] ?? 0);
            
            if ($category_id === 0) {
                throw new \Exception(__('Invalid category ID', 'smo-social'));
            }
            
            $data = array();
            if (isset($_POST['name'])) {
                $data['name'] = sanitize_text_field($_POST['name']);
            }
            if (isset($_POST['description'])) {
                $data['description'] = sanitize_textarea_field($_POST['description']);
            }
            if (isset($_POST['color_code'])) {
                $data['color_code'] = sanitize_hex_color($_POST['color_code']);
            }
            if (isset($_POST['icon'])) {
                $data['icon'] = sanitize_text_field($_POST['icon']);
            }
            if (isset($_POST['sort_order'])) {
                $data['sort_order'] = intval($_POST['sort_order']);
            }
            
            $this->update_category($category_id, $data);
            
            wp_send_json_success(array(
                'message' => __('Category updated successfully', 'smo-social')
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Delete category
     */
    public function ajax_delete_category() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        try {
            $category_id = intval($_POST['category_id'] ?? 0);
            
            if ($category_id === 0) {
                throw new \Exception(__('Invalid category ID', 'smo-social'));
            }
            
            $this->delete_category($category_id);
            
            wp_send_json_success(array(
                'message' => __('Category deleted successfully', 'smo-social')
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Get categories
     */
    public function ajax_get_categories() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        try {
            $filters = array();
            
            if (isset($_POST['parent_id'])) {
                $filters['parent_id'] = $_POST['parent_id'] === 'null' ? null : intval($_POST['parent_id']);
            }
            
            $categories = $this->get_categories($filters);
            
            wp_send_json_success($categories);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Assign post to category
     */
    public function ajax_assign_post_to_category() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        try {
            $post_id = intval($_POST['post_id'] ?? 0);
            $category_id = intval($_POST['category_id'] ?? 0);
            
            if ($post_id === 0 || $category_id === 0) {
                throw new \Exception(__('Invalid post or category ID', 'smo-social'));
            }
            
            $this->assign_post_to_category($post_id, $category_id);
            
            wp_send_json_success(array(
                'message' => __('Post assigned to category successfully', 'smo-social')
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Get posts by category
     */
    public function ajax_get_posts_by_category() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        try {
            $category_id = intval($_POST['category_id'] ?? 0);
            $limit = intval($_POST['limit'] ?? 50);
            $offset = intval($_POST['offset'] ?? 0);
            
            if ($category_id === 0) {
                throw new \Exception(__('Invalid category ID', 'smo-social'));
            }
            
            $posts = $this->get_posts_by_category($category_id, $limit, $offset);
            
            wp_send_json_success($posts);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Get category analytics
     */
    public function ajax_get_category_analytics() {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }

        try {
            $category_id = intval($_POST['category_id'] ?? 0);

            if ($category_id === 0) {
                throw new \Exception(__('Invalid category ID', 'smo-social'));
            }

            $analytics = $this->get_category_analytics($category_id);

            wp_send_json_success($analytics);

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Count total categories for current user
     */
    public function count_categories() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_content_categories';
        $user_id = get_current_user_id();

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        )));
    }

    /**
     * Save category (used by Admin.php AJAX handler)
     */
    public function save_category($data) {
        if (isset($data['id']) && $data['id'] > 0) {
            return $this->update_category($data['id'], $data);
        } else {
            return $this->add_category($data);
        }
    }
}
