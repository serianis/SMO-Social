<?php
/**
 * Content Ideas Manager
 * Handles content idea capture, organization, and conversion to posts
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * Content Ideas Manager
 * 
 * Manages content ideas capture and organization:
 * - Quick idea capture from dashboard
 * - Idea categorization and tagging
 * - Converting ideas to drafts or scheduled posts
 * - Idea collaboration and sharing
 */
class ContentIdeasManager {

    public string $last_error = '';

    private $table_names;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'content_ideas' => $wpdb->prefix . 'smo_content_ideas',
            'scheduled_posts' => $wpdb->prefix . 'smo_scheduled_posts'
        );
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_add_content_idea', array($this, 'ajax_add_content_idea'));
        add_action('wp_ajax_smo_update_content_idea', array($this, 'ajax_update_content_idea'));
        add_action('wp_ajax_smo_delete_content_idea', array($this, 'ajax_delete_content_idea'));
        add_action('wp_ajax_smo_get_content_ideas', array($this, 'ajax_get_content_ideas'));
        add_action('wp_ajax_smo_idea_to_post', array($this, 'ajax_idea_to_post'));
        add_action('wp_ajax_smo_duplicate_idea', array($this, 'ajax_duplicate_idea'));
        add_action('wp_ajax_smo_bulk_update_ideas', array($this, 'ajax_bulk_update_ideas'));
    }
    
    /**
     * Add a new content idea
     */
    public function add_content_idea($data) {
        global $wpdb;
        
        $data = DataValidator::validate_content_idea($data);
        
        $result = $wpdb->insert(
            $this->table_names['content_ideas'],
            array(
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'content_type' => $data['content_type'],
                'target_platforms' => implode(',', $data['target_platforms']),
                'tags' => implode(',', $data['tags']),
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => $data['status'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            $this->last_error = $wpdb->last_error;
            throw new \Exception('Failed to add content idea: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get content ideas with filtering
     */
    public function get_content_ideas($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $where_conditions[] = "category = %s";
            $where_values[] = $filters['category'];
        }
        
        // Priority filter
        if (!empty($filters['priority'])) {
            $where_conditions[] = "priority = %s";
            $where_values[] = $filters['priority'];
        }
        
        // Content type filter
        if (!empty($filters['content_type'])) {
            $where_conditions[] = "content_type = %s";
            $where_values[] = $filters['content_type'];
        }
        
        // User filter
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id = %d";
            $where_values[] = $filters['user_id'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = "(title LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Build WHERE clause
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Order by
        $order_by = 'ORDER BY ';
        if (!empty($filters['order_by'])) {
            $order_by .= sanitize_sql_orderby($filters['order_by']);
        } else {
            $order_by .= 'created_at DESC';
        }
        
        if (!empty($filters['order'])) {
            $order_by .= ' ' . sanitize_text_field($filters['order']);
        }
        
        // Limit
        $limit = '';
        if (!empty($filters['limit'])) {
            $limit = 'LIMIT ' . intval($filters['limit']);
        }
        
        if (!empty($filters['offset'])) {
            $limit .= ' OFFSET ' . intval($filters['offset']);
        }
        
        // Build final query
        $query = "SELECT ci.*, u.display_name as created_by_name 
                  FROM {$this->table_names['content_ideas']} ci
                  LEFT JOIN {$wpdb->prefix}users u ON ci.user_id = u.ID
                  $where_clause $order_by $limit";
        
        // Prepare query if we have parameters
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**

    /**
     * Get the total count of content ideas matching filters
     *
     * @param array $filters
     * @return int
     */
    public function get_content_ideas_count($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $where_conditions[] = "category = %s";
            $where_values[] = $filters['category'];
        }
        
        // Priority filter
        if (!empty($filters['priority'])) {
            $where_conditions[] = "priority = %s";
            $where_values[] = $filters['priority'];
        }
        
        // Content type filter
        if (!empty($filters['content_type'])) {
            $where_conditions[] = "content_type = %s";
            $where_values[] = $filters['content_type'];
        }
        
        // User filter
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id = %d";
            $where_values[] = $filters['user_id'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = "(title LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Build WHERE clause
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Build final query with COUNT(*)
        $query = "SELECT COUNT(*) 
                  FROM {$this->table_names['content_ideas']} ci
                  $where_clause";
        
        // Prepare query if we have parameters
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return intval($wpdb->get_var($query));
    }
    
    /**
     * Update content idea
     * Update content idea
     */
    public function update_content_idea($idea_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $update_format = array();
        
        $allowed_fields = array('title', 'description', 'content_type', 'target_platforms', 'tags', 'category', 'priority', 'status', 'scheduled_date');
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'title':
                    case 'category':
                    case 'priority':
                    case 'status':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        $update_format[] = '%s';
                        break;
                    case 'description':
                        $update_data[$field] = wp_kses_post($data[$field]);
                        $update_format[] = '%s';
                        break;
                    case 'target_platforms':
                    case 'tags':
                        $update_data[$field] = is_array($data[$field]) ? implode(',', $data[$field]) : $data[$field];
                        $update_format[] = '%s';
                        break;
                    case 'content_type':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        $update_format[] = '%s';
                        break;
                    case 'scheduled_date':
                        $update_data[$field] = !empty($data[$field]) ? $data[$field] : null;
                        $update_format[] = '%s';
                        break;
                }
            }
        }
        
        if (empty($update_data)) {
            throw new \Exception('No valid fields to update');
        }
        
        $result = $wpdb->update(
            $this->table_names['content_ideas'],
            $update_data,
            array('id' => $idea_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            $this->last_error = $wpdb->last_error;
            throw new \Exception('Failed to update content idea: ' . $wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Delete content idea
     */
    public function delete_content_idea($idea_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_names['content_ideas'],
            array('id' => $idea_id),
            array('%d')
        );
        
        if ($result === false) {
            $this->last_error = $wpdb->last_error;
            throw new \Exception('Failed to delete content idea: ' . $wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Convert idea to post
     */
    public function idea_to_post($idea_id, $post_data = array()) {
        global $wpdb;
        
        // Get the idea
        $idea = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['content_ideas']} WHERE id = %d",
            $idea_id
        ), ARRAY_A);
        
        if (!$idea) {
            $this->last_error = 'Content idea not found';
            throw new \Exception('Content idea not found');
        }
        
        // Prepare post data
        $scheduled_post_data = array(
            'title' => $idea['title'],
            'content' => $idea['description'],
            'scheduled_time' => !empty($post_data['scheduled_time']) ? $post_data['scheduled_time'] : null,
            'status' => !empty($post_data['scheduled_time']) ? 'scheduled' : 'draft',
            'priority' => $idea['priority'],
            'user_id' => get_current_user_id(),
            'content_idea_id' => $idea_id,
            'created_at' => current_time('mysql')
        );
        
        // Insert the post
        $result = $wpdb->insert(
            $this->table_names['scheduled_posts'],
            $scheduled_post_data,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
        
        if ($result === false) {
            $this->last_error = $wpdb->last_error;
            throw new \Exception('Failed to create post from idea: ' . $wpdb->last_error);
        }
        
        $post_id = $wpdb->insert_id;
        
        // Update idea status
        $this->update_content_idea($idea_id, array('status' => 'scheduled'));
        
        return $post_id;
    }
    
    /**
     * Duplicate content idea
     */
    public function duplicate_idea($idea_id, $user_id = null) {
        global $wpdb;
        
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['content_ideas']} WHERE id = %d",
            $idea_id
        ), ARRAY_A);
        
        if (!$original) {
            $this->last_error = 'Content idea not found';
            throw new \Exception('Content idea not found');
        }
        
        // Modify for duplicate
        unset($original['id']);
        $original['title'] = $original['title'] . ' (Copy)';
        $original['status'] = 'idea';
        $original['user_id'] = $user_id ?: get_current_user_id();
        $original['created_at'] = current_time('mysql');
        $original['scheduled_date'] = null;
        
        $result = $wpdb->insert(
            $this->table_names['content_ideas'],
            $original,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            $this->last_error = $wpdb->last_error;
            throw new \Exception('Failed to duplicate content idea: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get idea statistics
     */
    public function get_idea_statistics($user_id = null) {
        global $wpdb;
        
        $where_clause = '';
        $where_values = array();
        
        if ($user_id) {
            $where_clause = 'WHERE user_id = %d';
            $where_values[] = $user_id;
        }
        
        // Total ideas
        // Debug: Log the where_values to understand the data flow
        error_log('DEBUG: get_idea_statistics where_values: ' . print_r($where_values, true));

        // Fix: Use prepare() first, then get_var() on the prepared query
        $query = "SELECT COUNT(*) FROM {$this->table_names['content_ideas']} $where_clause";
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        $total_ideas = $wpdb->get_var($query);
        
        // Ideas by status
        $status_breakdown = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_names['content_ideas']} $where_clause GROUP BY status",
            ARRAY_A
        );
        
        // Ideas by priority
        $priority_breakdown = $wpdb->get_results(
            "SELECT priority, COUNT(*) as count FROM {$this->table_names['content_ideas']} $where_clause GROUP BY priority",
            ARRAY_A
        );
        
        // Ideas by content type
        $type_breakdown = $wpdb->get_results(
            "SELECT content_type, COUNT(*) as count FROM {$this->table_names['content_ideas']} $where_clause GROUP BY content_type",
            ARRAY_A
        );
        
        // Recent ideas (last 30 days)
        // Debug: Log the where_values to understand the data flow
        error_log('DEBUG: recent_ideas where_values: ' . print_r($where_values, true));

        // Fix: Use prepare() first, then get_var() on the prepared query
        $query = "SELECT COUNT(*) FROM {$this->table_names['content_ideas']} $where_clause AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        $recent_ideas = $wpdb->get_var($query);
        
        return array(
            'total_ideas' => intval($total_ideas),
            'recent_ideas' => intval($recent_ideas),
            'status_breakdown' => $status_breakdown,
            'priority_breakdown' => $priority_breakdown,
            'type_breakdown' => $type_breakdown
        );
    }
    
    /**
     * Get popular tags
     */
    public function get_popular_tags($limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT tag, COUNT(*) as usage_count 
             FROM (
                 SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tags, ',', numbers.n), ',', -1)) as tag
                 FROM {$this->table_names['content_ideas']}
                 CROSS JOIN (
                     SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION 
                     SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                 ) numbers
                 WHERE CHAR_LENGTH(tags) - CHAR_LENGTH(REPLACE(tags, ',', '')) >= numbers.n - 1
                 AND tags IS NOT NULL AND tags != ''
             ) tag_list
             WHERE tag != ''
             GROUP BY tag
             ORDER BY usage_count DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    /**
     * AJAX: Add content idea
     * 
     * @global array $_POST The POST data from the AJAX request
     */
    public function ajax_add_content_idea() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => wp_kses_post($_POST['description']),
            'content_type' => sanitize_text_field($_POST['content_type']),
            'target_platforms' => array_map('sanitize_text_field', $_POST['target_platforms'] ?? array()),
            'tags' => array_map('sanitize_text_field', $_POST['tags'] ?? array()),
            'category' => sanitize_text_field($_POST['category']),
            'priority' => sanitize_text_field($_POST['priority']),
            'status' => 'idea'
        );
        
        try {
            $idea_id = $this->add_content_idea($data);
            wp_send_json_success(array('idea_id' => $idea_id));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Update content idea
     * 
     * @global array $_POST The POST data from the AJAX request
     */
    public function ajax_update_content_idea() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $idea_id = intval($_POST['idea_id']);
        $data = $_POST;
        unset($data['action'], $data['nonce'], $data['idea_id']);
        
        try {
            $this->update_content_idea($idea_id, $data);
            wp_send_json_success(__('Content idea updated successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Delete content idea
     * 
     * @global array $_POST The POST data from the AJAX request
     */
    public function ajax_delete_content_idea() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $idea_id = intval($_POST['idea_id']);
        
        try {
            $this->delete_content_idea($idea_id);
            wp_send_json_success(__('Content idea deleted successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Get content ideas
     * 
     * @global array $_POST The POST data from the AJAX request
     */
    public function ajax_get_content_ideas() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'priority' => sanitize_text_field($_POST['priority'] ?? ''),
            'content_type' => sanitize_text_field($_POST['content_type'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'limit' => intval($_POST['limit'] ?? 20),
            'offset' => intval($_POST['offset'] ?? 0)
        );
        
        $ideas = $this->get_content_ideas($filters);
        wp_send_json_success($ideas);
    }
    
    /**
     * AJAX: Convert idea to post
     * 
     * @global array $_POST The POST data from the AJAX request
     */
    public function ajax_idea_to_post() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $idea_id = intval($_POST['idea_id']);
        $post_data = array(
            'scheduled_time' => sanitize_text_field($_POST['scheduled_time'] ?? '')
        );
        
        try {
            $post_id = $this->idea_to_post($idea_id, $post_data);
            wp_send_json_success(array('post_id' => $post_id));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Duplicate idea
     * 
     * @global array $_POST The POST data from the AJAX request
     */
    public function ajax_duplicate_idea() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $idea_id = intval($_POST['idea_id']);
        
        try {
            $new_idea_id = $this->duplicate_idea($idea_id);
            wp_send_json_success(array('new_idea_id' => $new_idea_id));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Bulk update ideas
     * 
     * @global array $_POST The POST data from the AJAX request
     */
    public function ajax_bulk_update_ideas() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $idea_ids = array_map('intval', $_POST['idea_ids']);
        $action = sanitize_text_field($_POST['bulk_action']);
        $updated_count = 0;
        
        try {
            switch ($action) {
                case 'delete':
                    foreach ($idea_ids as $idea_id) {
                        $this->delete_content_idea($idea_id);
                        $updated_count++;
                    }
                    break;
                    
                case 'status':
                    $new_status = sanitize_text_field($_POST['new_status']);
                    foreach ($idea_ids as $idea_id) {
                        $this->update_content_idea($idea_id, array('status' => $new_status));
                        $updated_count++;
                    }
                    break;
                    
                case 'priority':
                    $new_priority = sanitize_text_field($_POST['new_priority']);
                    foreach ($idea_ids as $idea_id) {
                        $this->update_content_idea($idea_id, array('priority' => $new_priority));
                        $updated_count++;
                    }
                    break;
            }
            
            wp_send_json_success(array('updated_count' => $updated_count));
            } catch (\Exception $e) {
                wp_send_json_error($e->getMessage());
            }
        }
    
        /**
         * Count total ideas for current user
         */
        public function count_ideas() {
            global $wpdb;
    
            $user_id = get_current_user_id();
    
            return intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_names['content_ideas']} WHERE user_id = %d",
                $user_id
            )));
        }
    
        /**
         * Count ideas by status for current user
         */
        public function count_ideas_by_status($status) {
            global $wpdb;
    
            $user_id = get_current_user_id();
    
            return intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_names['content_ideas']} WHERE user_id = %d AND status = %s",
                $user_id, $status
            )));
        }
    
        /**
         * Save idea (used by Admin.php AJAX handler)
         */
        public function save_idea($data) {
            if (isset($data['id']) && $data['id'] > 0) {
                $this->update_content_idea($data['id'], $data);
                return $data['id'];
            } else {
                return $this->add_content_idea($data);
            }
        }
    
        /**
         * Get ideas grouped by status for Kanban board
         */
        public function get_ideas_by_status() {
            global $wpdb;
    
            $user_id = get_current_user_id();
    
            $ideas = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_names['content_ideas']} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            ), ARRAY_A);
    
            // Group by status for Kanban board
            $grouped = [
                'idea' => [],
                'draft' => [],
                'scheduled' => [],
                'published' => []
            ];
    
            foreach ($ideas as $idea) {
                $status = $idea['status'] ?? 'idea';
                if (isset($grouped[$status])) {
                    $grouped[$status][] = $idea;
                }
            }
    
            return $grouped;
        }
    }
