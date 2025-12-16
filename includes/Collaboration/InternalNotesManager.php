<?php
/**
 * Internal Notes Manager
 * Handles internal comments and collaboration on posts
 */

namespace SMO_Social\Collaboration;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * Internal Notes Manager
 * 
 * Manages internal collaboration and notes:
 * - Add notes to posts
 * - Note types: general, feedback, revision, approval
 * - User mention system
 * - Note history and threading
 * - Notification system for new notes
 */
class InternalNotesManager {
    
    public $last_error = '';
    
    private $table_names;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'internal_notes' => $wpdb->prefix . 'smo_internal_notes',
            'scheduled_posts' => $wpdb->prefix . 'smo_scheduled_posts'
        );
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_add_internal_note', array($this, 'ajax_add_internal_note'));
        add_action('wp_ajax_smo_get_internal_notes', array($this, 'ajax_get_internal_notes'));
        add_action('wp_ajax_smo_update_internal_note', array($this, 'ajax_update_internal_note'));
        add_action('wp_ajax_smo_delete_internal_note', array($this, 'ajax_delete_internal_note'));
        add_action('wp_ajax_smo_get_note_history', array($this, 'ajax_get_note_history'));
    }
    
    /**
     * Add internal note to post
     */
    public function add_internal_note($post_id, $content, $note_type = 'general') {
        global $wpdb;
        
        // Validate note type
        if (!in_array($note_type, array('general', 'feedback', 'revision', 'approval'))) {
            throw new \Exception('Invalid note type');
        }
        
        // Check if user has permission to add notes to this post
        if (!$this->user_can_add_note($post_id)) {
            throw new \Exception('Insufficient permissions to add note to this post');
        }
        
        $result = $wpdb->insert(
            $this->table_names['internal_notes'],
            array(
                'post_id' => $post_id,
                'user_id' => get_current_user_id(),
                'content' => wp_kses_post($content),
                'note_type' => $note_type,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to add internal note: ' . $wpdb->last_error);
        }
        
        $note_id = $wpdb->insert_id;
        
        // Process mentions in the content
        $this->process_mentions($content, $note_id, $post_id);
        
        // Send notifications to mentioned users
        $this->send_mention_notifications($post_id, $content);
        
        return $note_id;
    }
    
    /**
     * Get internal notes for a post
     */
    public function get_internal_notes($post_id, $note_type = null) {
        global $wpdb;
        
        $where_clause = "WHERE post_id = %d";
        $where_values = array($post_id);
        
        if ($note_type) {
            $where_clause .= " AND note_type = %s";
            $where_values[] = $note_type;
        }
        
        $query = "SELECT n.*, u.display_name as user_name, u.user_email as user_email
                  FROM {$this->table_names['internal_notes']} n
                  LEFT JOIN {$wpdb->prefix}users u ON n.user_id = u.ID
                  $where_clause
                  ORDER BY n.created_at DESC";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $notes = $wpdb->get_results($query, ARRAY_A);
        
        // Enhance notes with additional data
        foreach ($notes as &$note) {
            $note['mentions'] = $this->extract_mentions($note['content']);
            $note['content_formatted'] = $this->format_note_content($note['content']);
        }
        
        return $notes;
    }
    
    /**
     * Update internal note
     */
    public function update_internal_note($note_id, $content) {
        global $wpdb;
        
        // Check if user can edit this note
        if (!$this->user_can_edit_note($note_id)) {
            throw new \Exception('Insufficient permissions to edit this note');
        }
        
        $result = $wpdb->update(
            $this->table_names['internal_notes'],
            array(
                'content' => wp_kses_post($content),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $note_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to update internal note: ' . $wpdb->last_error);
        }
        
        // Process mentions in updated content
        $this->process_mentions($content, $note_id, null);
        
        return $result;
    }
    
    /**
     * Delete internal note
     */
    public function delete_internal_note($note_id) {
        global $wpdb;
        
        // Check if user can delete this note
        if (!$this->user_can_delete_note($note_id)) {
            throw new \Exception('Insufficient permissions to delete this note');
        }
        
        $result = $wpdb->delete(
            $this->table_names['internal_notes'],
            array('id' => $note_id),
            array('%d')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to delete internal note: ' . $wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Process mentions in note content
     */
    private function process_mentions($content, $note_id, $post_id = null) {
        $mentions = $this->extract_mentions($content);
        
        foreach ($mentions as $username) {
            $user = get_user_by('login', $username);
            if ($user) {
                // Record the mention (you might want to create a separate mentions table)
                $this->record_mention($user->ID, $note_id, $post_id);
            }
        }
    }
    
    /**
     * Extract mentions from content
     */
    private function extract_mentions($content) {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        return $matches[1] ?? array();
    }
    
    /**
     * Format note content with mentions and line breaks
     */
    private function format_note_content($content) {
        // Convert @mentions to links
        $content = preg_replace('/@([a-zA-Z0-9_]+)/', '<a href="#" class="smo-mention" data-username="$1">@$1</a>', $content);
        
        // Convert line breaks to <br> tags
        $content = nl2br($content);
        
        return $content;
    }
    
    /**
     * Record mention
     */
    private function record_mention($user_id, $note_id, $post_id) {
        // This could be expanded to a separate mentions table
        // For now, just log it
        error_log("SMO Social: User {$user_id} mentioned in note {$note_id} for post {$post_id}");
    }
    
    /**
     * Send notifications for mentions
     */
    private function send_mention_notifications($post_id, $content) {
        $mentions = $this->extract_mentions($content);
        
        foreach ($mentions as $username) {
            $user = get_user_by('login', $username);
            if ($user) {
                $this->send_mention_notification($user->ID, $post_id, $username);
            }
        }
    }
    
    /**
     * Send mention notification
     */
    private function send_mention_notification($user_id, $post_id, $mentioner_username) {
        // Get post title for context
        global $wpdb;
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT title FROM {$this->table_names['scheduled_posts']} WHERE id = %d",
            $post_id
        ));
        
        $post_title = $post ? $post->title : 'Unknown Post';
        
        // Send email notification
        $user = get_userdata($user_id);
        if ($user && $user->user_email) {
            $subject = sprintf('[%s] You were mentioned in an internal note', get_bloginfo('name'));
            $message = sprintf(
                "Hi %s,\n\n%s mentioned you in an internal note for the post: \"%s\".\n\nYou can view the full context by logging into your dashboard.\n\nBest regards,\n%s Team",
                $user->display_name,
                $mentioner_username,
                $post_title,
                get_bloginfo('name')
            );
            
            wp_mail($user->user_email, $subject, $message);
        }
    }
    
    /**
     * Check if user can add notes to a post
     */
    private function user_can_add_note($post_id) {
        $user_id = get_current_user_id();
        
        // Check if user is the post creator
        global $wpdb;
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT created_by FROM {$this->table_names['scheduled_posts']} WHERE id = %d",
            $post_id
        ));
        
        if ($post && $post->created_by == $user_id) {
            return true;
        }
        
        // Check if user has general permission to add notes
        return current_user_can('edit_posts');
    }
    
    /**
     * Check if user can edit a note
     */
    private function user_can_edit_note($note_id) {
        global $wpdb;
        
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$this->table_names['internal_notes']} WHERE id = %d",
            $note_id
        ));
        
        // Users can edit their own notes, or admins can edit any note
        return $note && ($note->user_id == get_current_user_id() || current_user_can('manage_options'));
    }
    
    /**
     * Check if user can delete a note
     */
    private function user_can_delete_note($note_id) {
        global $wpdb;
        
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$this->table_names['internal_notes']} WHERE id = %d",
            $note_id
        ));
        
        // Users can delete their own notes, or admins can delete any note
        return $note && ($note->user_id == get_current_user_id() || current_user_can('manage_options'));
    }
    
    /**
     * Get note history/activity
     */
    public function get_note_history($post_id = null, $user_id = null, $limit = 50) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if ($post_id) {
            $where_conditions[] = "n.post_id = %d";
            $where_values[] = $post_id;
        }
        
        if ($user_id) {
            $where_conditions[] = "n.user_id = %d";
            $where_values[] = $user_id;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT n.*, u.display_name as user_name, sp.title as post_title
                  FROM {$this->table_names['internal_notes']} n
                  LEFT JOIN {$wpdb->prefix}users u ON n.user_id = u.ID
                  LEFT JOIN {$this->table_names['scheduled_posts']} sp ON n.post_id = sp.id
                  $where_clause
                  ORDER BY n.created_at DESC
                  LIMIT %d";
        
        $where_values[] = $limit;
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get note statistics for dashboard
     */
    public function get_note_statistics($user_id = null) {
        global $wpdb;
        
        $user_id = $user_id ?: get_current_user_id();
        
        $where_clause = $user_id ? "WHERE user_id = %d" : "WHERE 1=1";
        $where_values = $user_id ? array($user_id) : array();
        
        // Total notes
        $total_notes = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_names['internal_notes']} $where_clause",
            $where_values
        );
        
        // Notes by type
        $notes_by_type = $wpdb->get_results(
            "SELECT note_type, COUNT(*) as count FROM {$this->table_names['internal_notes']} $where_clause GROUP BY note_type",
            ARRAY_A
        );
        
        // Recent notes (last 7 days)
        $recent_notes = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_names['internal_notes']} $where_clause AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $where_values
        );
        
        return array(
            'total_notes' => intval($total_notes),
            'recent_notes' => intval($recent_notes),
            'notes_by_type' => $notes_by_type
        );
    }
    
    // AJAX handlers
    
    public function ajax_add_internal_note() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_id = intval($_POST['post_id']);
        $content = wp_kses_post($_POST['content']);
        $note_type = sanitize_text_field($_POST['note_type'] ?? 'general');
        
        if (empty($content)) {
            wp_send_json_error(__('Note content cannot be empty'));
        }
        
        try {
            $note_id = $this->add_internal_note($post_id, $content, $note_type);
            
            // Get the full note data to return
            $notes = $this->get_internal_notes($post_id);
            $new_note = null;
            foreach ($notes as $note) {
                if ($note['id'] == $note_id) {
                    $new_note = $note;
                    break;
                }
            }
            
            wp_send_json_success(array(
                'note_id' => $note_id,
                'note' => $new_note,
                'message' => __('Internal note added successfully')
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_internal_notes() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_id = intval($_POST['post_id']);
        $note_type = sanitize_text_field($_POST['note_type'] ?? '');
        
        $notes = $this->get_internal_notes($post_id, $note_type);
        wp_send_json_success($notes);
    }
    
    public function ajax_update_internal_note() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $note_id = intval($_POST['note_id']);
        $content = wp_kses_post($_POST['content']);
        
        if (empty($content)) {
            wp_send_json_error(__('Note content cannot be empty'));
        }
        
        try {
            $this->update_internal_note($note_id, $content);
            wp_send_json_success(__('Internal note updated successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_delete_internal_note() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $note_id = intval($_POST['note_id']);
        
        try {
            $this->delete_internal_note($note_id);
            wp_send_json_success(__('Internal note deleted successfully'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_note_history() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 50);
        
        $history = $this->get_note_history($post_id ?: null, $user_id ?: null, $limit);
        wp_send_json_success($history);
    }
}