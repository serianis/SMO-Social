<?php
namespace SMO_Social\Admin\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use SMO_Social\Content\ContentCategoriesManager;
use SMO_Social\Content\ContentIdeasManager;

/**
 * Content Organizer AJAX Handler
 * 
 * Handles all AJAX requests related to Content Organizer features
 * (Categories, Ideas, RSS Feeds, Imports)
 */
class ContentOrganizerAjax extends BaseAjaxHandler {
    
    /**
     * Override nonce action to match frontend
     * @var string
     */
    protected $nonce_action = 'smo_social_nonce';

    /**
     * Register AJAX actions
     */
    public function register() {
        $actions = [
            'smo_get_organizer_stats' => 'ajax_get_organizer_stats',
            'smo_get_rss_feeds' => 'ajax_get_rss_feeds',
            'smo_add_rss_feed' => 'ajax_add_rss_feed',
            'smo_get_imported_content' => 'ajax_get_imported_content',
            'smo_save_category' => 'ajax_save_category',
            'smo_get_categories' => 'ajax_get_categories',
            'smo_delete_category' => 'ajax_delete_category',
            'smo_save_quick_idea' => 'ajax_save_quick_idea',
            'smo_save_idea' => 'ajax_save_idea',
            'smo_get_ideas' => 'ajax_get_ideas',
            'smo_update_idea_status' => 'ajax_update_idea_status',
            'smo_delete_idea' => 'ajax_delete_idea'
        ];

        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, [$this, $method]);
        }
    }

    /**
     * AJAX: Get content organizer stats
     */
    public function ajax_get_organizer_stats() {
        if (!$this->verify_request()) {
            return;
        }
        
        try {
            // Initialize Content Categories Manager
            $categories_manager = new ContentCategoriesManager();
            $ideas_manager = new ContentIdeasManager();
            
            // Get categories count
            $total_categories = $categories_manager->count_categories();
            
            // Get ideas count
            $total_ideas = $ideas_manager->count_ideas();
            
            // Get ideas by status for dashboard
            $total_drafts = $ideas_manager->count_ideas_by_status('draft');
            $total_scheduled = $ideas_manager->count_ideas_by_status('scheduled');
            
            $this->send_success(array(
                'total_categories' => intval($total_categories),
                'total_ideas' => intval($total_ideas),
                'total_drafts' => intval($total_drafts),
                'total_scheduled' => intval($total_scheduled)
            ));
        } catch (\Exception $e) {
            error_log('SMO Social: Error in ajax_get_organizer_stats: ' . $e->getMessage());
            $this->send_error(__('Failed to get organizer stats: ', 'smo-social') . $e->getMessage());
        }
    }

    /**
     * AJAX: Get RSS feeds
     */
    public function ajax_get_rss_feeds() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $sources_table = $wpdb->prefix . 'smo_content_sources';
        
        $feeds = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $sources_table WHERE user_id = %d AND type = 'rss' ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
        
        $this->send_success($feeds);
    }

    /**
     * AJAX: Add RSS feed
     */
    public function ajax_add_rss_feed() {
        if (!$this->verify_request()) {
            return;
        }
        
        $url = $this->get_text('url');
        $name = $this->get_text('name');
        $auto_import = isset($_POST['auto_import']) ? (bool)$_POST['auto_import'] : false;
        
        if (empty($url)) {
            $this->send_error(__('Feed URL is required', 'smo-social'));
            return;
        }
        
        global $wpdb;
        $sources_table = $wpdb->prefix . 'smo_content_sources';
        
        $result = $wpdb->insert($sources_table, array(
            'user_id' => get_current_user_id(),
            'name' => $name ?: parse_url($url, PHP_URL_HOST),
            'type' => 'rss',
            'url' => $url,
            'settings' => json_encode(array(
                'auto_import' => $auto_import
            )),
            'status' => 'active'
        ));
        
        if ($result === false) {
            $this->send_error(__('Failed to add RSS feed', 'smo-social'));
            return;
        }
        
        $this->send_success(array(
            'message' => __('RSS feed added successfully', 'smo-social'),
            'feed_id' => $wpdb->insert_id
        ));
    }

    /**
     * AJAX: Get imported content
     */
    public function ajax_get_imported_content() {
        if (!$this->verify_request()) {
            return;
        }
        
        $limit = $this->get_int('limit', 10);
        
        global $wpdb;
        $imports_table = $wpdb->prefix . 'smo_imported_content';
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $imports_table WHERE user_id = %d ORDER BY imported_at DESC LIMIT %d",
            get_current_user_id(),
            $limit
        ), ARRAY_A);
        
        $this->send_success($items);
    }
    
    /**
     * AJAX: Save category
     */
    public function ajax_save_category() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_content_categories';
        
        $id = $this->get_int('id');
        $name = $this->get_text('name');
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $color = $this->get_text('color', '#667eea');
        $icon = $this->get_text('icon', '📁');
        
        if (empty($name)) {
            $this->send_error(__('Category name is required', 'smo-social'));
            return;
        }
        
        $data = [
            'user_id' => get_current_user_id(),
            'name' => $name,
            'description' => $description,
            'color_code' => $color,
            'icon' => $icon,
            'updated_at' => current_time('mysql')
        ];
        
        if ($id > 0) {
            // Update existing category
            $result = $wpdb->update($table, $data, ['id' => $id, 'user_id' => get_current_user_id()]);
        } else {
            // Create new category
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            $this->send_success([
                'message' => __('Category saved successfully', 'smo-social'),
                'id' => $id
            ]);
        } else {
            $this->send_error(__('Failed to save category', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Get categories
     */
    public function ajax_get_categories() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_content_categories';
        
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND is_active = 1 ORDER BY sort_order ASC, name ASC",
            get_current_user_id()
        ), ARRAY_A);
        
        $this->send_success($categories);
    }
    
    /**
     * AJAX: Delete category
     */
    public function ajax_delete_category() {
        error_log('SMO Social: ajax_delete_category called - Starting deletion process');
        
        try {
            // Step 1: Verify request security
            error_log('SMO Social: Step 1 - Verifying request security');
            if (!$this->verify_request()) {
                error_log('SMO Social: Request verification failed - aborting');
                return;
            }
            error_log('SMO Social: Request verification passed');
            
            // Step 2: Get and validate category ID
            error_log('SMO Social: Step 2 - Getting category ID');
            $id = $this->get_int('id');
            error_log('SMO Social: Category ID received: ' . $id);
            
            if ($id <= 0) {
                error_log('SMO Social: Invalid category ID: ' . $id);
                $this->send_error(__('Invalid category ID', 'smo-social'));
                return;
            }
            
            // Step 3: Initialize database connection
            error_log('SMO Social: Step 3 - Initializing database connection');
            global $wpdb;
            $table = $wpdb->prefix . 'smo_content_categories';
            error_log('SMO Social: Table name: ' . $table);
            
            // Step 4: Check if table exists
            error_log('SMO Social: Step 4 - Checking table existence');
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            error_log('SMO Social: Table exists: ' . ($table_exists ? 'Yes' : 'No'));
            
            if (!$table_exists) {
                error_log('SMO Social: Table does not exist - database setup may be incomplete');
                $this->send_error(__('Database table not found. Please run plugin setup.', 'smo-social'));
                return;
            }
            
            // Step 5: Check current user permissions
            error_log('SMO Social: Step 5 - Checking user permissions');
            $user_id = get_current_user_id();
            error_log('SMO Social: Current user ID: ' . $user_id);
            error_log('SMO Social: User can manage options: ' . (current_user_can('manage_options') ? 'Yes' : 'No'));
            
            if (!current_user_can('manage_options')) {
                error_log('SMO Social: User lacks manage_options capability');
                $this->send_error(__('Insufficient permissions', 'smo-social'));
                return;
            }
            
            // Step 6: Check if category exists and belongs to user
            error_log('SMO Social: Step 6 - Verifying category ownership');
            $category_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE id = %d AND user_id = %d",
                $id,
                $user_id
            ));
            error_log('SMO Social: Category exists and belongs to user: ' . ($category_exists > 0 ? 'Yes' : 'No'));
            
            if (!$category_exists) {
                error_log('SMO Social: Category not found or does not belong to user');
                $this->send_error(__('Category not found or access denied', 'smo-social'));
                return;
            }
            
            // Step 7: Perform deletion
            error_log('SMO Social: Step 7 - Performing deletion');
            $result = $wpdb->delete($table, [
                'id' => $id,
                'user_id' => $user_id
            ]);
            
            error_log('SMO Social: Deletion result: ' . ($result !== false ? 'Success' : 'Failed'));
            error_log('SMO Social: Database last error: ' . ($wpdb->last_error ?: 'None'));
            error_log('SMO Social: Rows affected: ' . $result);
            
            if ($result !== false && $result > 0) {
                error_log('SMO Social: Category deleted successfully');
                $this->send_success(['message' => __('Category deleted successfully', 'smo-social')]);
            } else {
                error_log('SMO Social: Deletion failed - no rows affected or error occurred');
                $error_message = __('Failed to delete category', 'smo-social');
                if ($wpdb->last_error) {
                    $error_message .= ': ' . $wpdb->last_error;
                }
                $this->send_error($error_message);
            }
            
        } catch (\Exception $e) {
            error_log('SMO Social: Exception in ajax_delete_category: ' . $e->getMessage());
            error_log('SMO Social: Exception trace: ' . $e->getTraceAsString());
            $this->send_error(__('An unexpected error occurred while deleting the category', 'smo-social'));
        } catch (\Error $e) {
            error_log('SMO Social: Fatal error in ajax_delete_category: ' . $e->getMessage());
            error_log('SMO Social: Fatal error trace: ' . $e->getTraceAsString());
            $this->send_error(__('A critical error occurred', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Save quick idea
     */
    public function ajax_save_quick_idea() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_content_ideas';
        
        $title = $this->get_text('title');
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $category = $this->get_int('category');
        $priority = $this->get_text('priority', 'medium');
        
        if (empty($title)) {
            $this->send_error(__('Idea title is required', 'smo-social'));
            return;
        }
        
        $data = [
            'user_id' => get_current_user_id(),
            'title' => $title,
            'content' => $description,
            'category_id' => $category > 0 ? $category : null,
            'priority' => $priority,
            'status' => 'idea',
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            $this->send_success([
                'message' => __('Idea saved successfully', 'smo-social'),
                'id' => $wpdb->insert_id
            ]);
        } else {
            $this->send_error(__('Failed to save idea', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Save idea (full details)
     */
    public function ajax_save_idea() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_content_ideas';
        
        $id = $this->get_int('id');
        $title = $this->get_text('title');
        $content = wp_kses_post($_POST['content'] ?? '');
        $category = $this->get_int('category');
        $priority = $this->get_text('priority', 'medium');
        $status = $this->get_text('status', 'idea');
        $scheduled_date = $this->get_text('scheduled_date');
        $tags = $this->get_text('tags');
        
        if (empty($title)) {
            $this->send_error(__('Idea title is required', 'smo-social'));
            return;
        }
        
        $data = [
            'user_id' => get_current_user_id(),
            'title' => $title,
            'content' => $content,
            'category_id' => $category > 0 ? $category : null,
            'priority' => $priority,
            'status' => $status,
            'scheduled_date' => !empty($scheduled_date) ? $scheduled_date : null,
            'tags' => $tags,
            'updated_at' => current_time('mysql')
        ];
        
        if ($id > 0) {
            // Update existing idea
            $result = $wpdb->update($table, $data, ['id' => $id, 'user_id' => get_current_user_id()]);
        } else {
            // Create new idea
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            $this->send_success([
                'message' => __('Idea saved successfully', 'smo-social'),
                'id' => $id
            ]);
        } else {
            $this->send_error(__('Failed to save idea', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Get ideas
     */
    public function ajax_get_ideas() {
        if (!$this->verify_request()) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_content_ideas';
        $categories_table = $wpdb->prefix . 'smo_content_categories';
        
        $ideas = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, c.name as category_name, c.color_code as category_color 
             FROM $table i 
             LEFT JOIN $categories_table c ON i.category_id = c.id 
             WHERE i.user_id = %d 
             ORDER BY i.sort_order ASC, i.created_at DESC",
            get_current_user_id()
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
        
        $this->send_success($grouped);
    }
    
    /**
     * AJAX: Update idea status (for Kanban drag & drop)
     */
    public function ajax_update_idea_status() {
        if (!$this->verify_request()) {
            return;
        }
        
        $idea_id = $this->get_int('idea_id');
        $status = $this->get_text('status');
        
        if ($idea_id <= 0 || empty($status)) {
            $this->send_error(__('Invalid parameters', 'smo-social'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_content_ideas';
        
        $result = $wpdb->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $idea_id, 'user_id' => get_current_user_id()]
        );
        
        if ($result !== false) {
            $this->send_success(['message' => __('Status updated successfully', 'smo-social')]);
        } else {
            $this->send_error(__('Failed to update status', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Delete idea
     */
    public function ajax_delete_idea() {
        error_log('SMO Social: ajax_delete_idea called - Starting deletion process');
        
        try {
            // Step 1: Verify request security
            error_log('SMO Social: Step 1 - Verifying request security');
            if (!$this->verify_request()) {
                error_log('SMO Social: Request verification failed - aborting');
                return;
            }
            error_log('SMO Social: Request verification passed');
            
            // Step 2: Get and validate idea ID
            error_log('SMO Social: Step 2 - Getting idea ID');
            $id = $this->get_int('id');
            error_log('SMO Social: Idea ID received: ' . $id);
            
            if ($id <= 0) {
                error_log('SMO Social: Invalid idea ID: ' . $id);
                $this->send_error(__('Invalid idea ID', 'smo-social'));
                return;
            }
            
            // Step 3: Initialize database connection
            error_log('SMO Social: Step 3 - Initializing database connection');
            global $wpdb;
            $table = $wpdb->prefix . 'smo_content_ideas';
            error_log('SMO Social: Table name: ' . $table);
            
            // Step 4: Check if table exists
            error_log('SMO Social: Step 4 - Checking table existence');
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            error_log('SMO Social: Table exists: ' . ($table_exists ? 'Yes' : 'No'));
            
            if (!$table_exists) {
                error_log('SMO Social: Table does not exist - database setup may be incomplete');
                $this->send_error(__('Database table not found. Please run plugin setup.', 'smo-social'));
                return;
            }
            
            // Step 5: Check current user permissions
            error_log('SMO Social: Step 5 - Checking user permissions');
            $user_id = get_current_user_id();
            error_log('SMO Social: Current user ID: ' . $user_id);
            error_log('SMO Social: User can manage options: ' . (current_user_can('manage_options') ? 'Yes' : 'No'));
            
            if (!current_user_can('manage_options')) {
                error_log('SMO Social: User lacks manage_options capability');
                $this->send_error(__('Insufficient permissions', 'smo-social'));
                return;
            }
            
            // Step 6: Check if idea exists and belongs to user
            error_log('SMO Social: Step 6 - Verifying idea ownership');
            $idea_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE id = %d AND user_id = %d",
                $id,
                $user_id
            ));
            error_log('SMO Social: Idea exists and belongs to user: ' . ($idea_exists > 0 ? 'Yes' : 'No'));
            
            if (!$idea_exists) {
                error_log('SMO Social: Idea not found or does not belong to user');
                $this->send_error(__('Idea not found or access denied', 'smo-social'));
                return;
            }
            
            // Step 7: Perform deletion
            error_log('SMO Social: Step 7 - Performing deletion');
            $result = $wpdb->delete($table, [
                'id' => $id,
                'user_id' => $user_id
            ]);
            
            error_log('SMO Social: Deletion result: ' . ($result !== false ? 'Success' : 'Failed'));
            error_log('SMO Social: Database last error: ' . ($wpdb->last_error ?: 'None'));
            error_log('SMO Social: Rows affected: ' . $result);
            
            if ($result !== false && $result > 0) {
                error_log('SMO Social: Idea deleted successfully');
                $this->send_success(['message' => __('Idea deleted successfully', 'smo-social')]);
            } else {
                error_log('SMO Social: Deletion failed - no rows affected or error occurred');
                $error_message = __('Failed to delete idea', 'smo-social');
                if ($wpdb->last_error) {
                    $error_message .= ': ' . $wpdb->last_error;
                }
                $this->send_error($error_message);
            }
            
        } catch (\Exception $e) {
            error_log('SMO Social: Exception in ajax_delete_idea: ' . $e->getMessage());
            error_log('SMO Social: Exception trace: ' . $e->getTraceAsString());
            $this->send_error(__('An unexpected error occurred while deleting the idea', 'smo-social'));
        } catch (\Error $e) {
            error_log('SMO Social: Fatal error in ajax_delete_idea: ' . $e->getMessage());
            error_log('SMO Social: Fatal error trace: ' . $e->getTraceAsString());
            $this->send_error(__('A critical error occurred', 'smo-social'));
        }
    }
}
