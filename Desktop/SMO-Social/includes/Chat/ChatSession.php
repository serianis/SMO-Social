<?php
namespace SMO_Social\Chat;

use SMO_Social\AI\Manager as AIManager;
use SMO_Social\Security\TokenStorage;
use SMO_Social\Chat\DatabaseSchema;

/**
 * Chat Session Management
 * Handles creation, retrieval, update, and deletion of chat sessions
 */
class ChatSession {
    
    private $db;
    private $ai_manager;
    private $token_storage;
    private $audit_logger;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->ai_manager = AIManager::getInstance();
        $this->audit_logger = new ChatAuditLogger();
    }
    
    /**
     * Create a new chat session
     */
    public function create($user_id, $options = []) {
        // Validate user permissions
        if (!current_user_can('edit_posts')) {
            throw new \Exception('Insufficient permissions to create chat session');
        }
        
        $session_data = [
            'user_id' => $user_id,
            'session_name' => $options['session_name'] ?? 'Chat Session ' . date('Y-m-d H:i:s'),
            'provider_id' => $options['provider_id'] ?? $this->get_default_provider_id(),
            'model_name' => $options['model_name'] ?? 'microsoft/DialoGPT-medium',
            'system_prompt' => $options['system_prompt'] ?? $this->get_default_system_prompt(),
            'conversation_context' => $options['conversation_context'] ?? '',
            'status' => 'active',
            'message_count' => 0,
            'token_usage' => 0,
            'cost_estimate' => 0.000000
        ];
        
        $result = $this->db->insert(
            $this->get_table_name('sessions'),
            $session_data,
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%f']
        );
        
        if ($result === false) {
            throw new \Exception('Failed to create chat session: ' . $this->db->last_error);
        }
        
        $session_id = $this->db->insert_id;
        
        // Log session creation
        $this->audit_logger->log($user_id, 'session_create', 'chat_session', $session_id, [
            'provider_id' => $session_data['provider_id'],
            'model_name' => $session_data['model_name']
        ]);
        
        return $this->get($session_id);
    }
    
    /**
     * Get a chat session by ID
     */
    public function get($session_id, $user_id = null) {
        $query = "
            SELECT s.*, p.name as provider_name, p.display_name as provider_display_name,
                   p.base_url, p.auth_type, p.features
            FROM {$this->get_table_name('sessions')} s
            LEFT JOIN {$this->get_table_name('providers')} p ON s.provider_id = p.id
            WHERE s.id = %d
        ";
        
        $params = [$session_id];
        
        if ($user_id !== null) {
            $query .= " AND s.user_id = %d";
            $params[] = $user_id;
        }
        
        $session = $this->db->get_row(
            $this->db->prepare($query, ...$params),
            ARRAY_A
        );
        
        if (!$session) {
            return null;
        }
        
        // Parse JSON fields
        $session['features'] = json_decode($session['features'] ?? '{}', true);
        $session['default_params'] = json_decode($session['default_params'] ?? '{}', true);
        
        return $session;
    }
    
    /**
     * Get user's chat sessions with pagination
     */
    public function get_user_sessions($user_id, $page = 1, $limit = 20, $status = 'active') {
        $offset = ($page - 1) * $limit;
        
        $query = "
            SELECT s.*, p.display_name as provider_display_name,
                   COUNT(m.id) as actual_message_count
            FROM {$this->get_table_name('sessions')} s
            LEFT JOIN {$this->get_table_name('providers')} p ON s.provider_id = p.id
            LEFT JOIN {$this->get_table_name('messages')} m ON s.id = m.session_id
            WHERE s.user_id = %d
        ";
        
        $params = [$user_id];
        
        if ($status !== 'all') {
            $query .= " AND s.status = %s";
            $params[] = $status;
        }
        
        $query .= " GROUP BY s.id ORDER BY s.last_activity DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $sessions = $this->db->get_results(
            $this->db->prepare($query, ...$params),
            ARRAY_A
        );
        
        // Get total count for pagination
        $count_query = "
            SELECT COUNT(*) FROM {$this->get_table_name('sessions')}
            WHERE user_id = %d
        ";
        $count_params = [$user_id];
        
        if ($status !== 'all') {
            $count_query .= " AND status = %s";
            $count_params[] = $status;
        }
        
        $total = $this->db->get_var(
            $this->db->prepare($count_query, ...$count_params)
        );
        
        return [
            'sessions' => $sessions,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Update a chat session
     */
    public function update($session_id, $user_id, $updates) {
        $allowed_fields = ['session_name', 'system_prompt', 'conversation_context', 'status'];
        
        // Validate session ownership
        $session = $this->get($session_id, $user_id);
        if (!$session) {
            throw new \Exception('Session not found or access denied');
        }
        
        $update_data = [];
        $update_format = [];
        
        foreach ($updates as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_data[$field] = $value;
                $update_format[] = '%s';
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $this->db->update(
            $this->get_table_name('sessions'),
            $update_data,
            ['id' => $session_id, 'user_id' => $user_id],
            $update_format,
            ['%d', '%d']
        );
        
        if ($result !== false) {
            $this->audit_logger->log($user_id, 'session_update', 'chat_session', $session_id, $updates);
        }
        
        return $result !== false;
    }
    
    /**
     * Delete a chat session
     */
    public function delete($session_id, $user_id) {
        // Validate session ownership
        $session = $this->get($session_id, $user_id);
        if (!$session) {
            throw new \Exception('Session not found or access denied');
        }
        
        // Soft delete by setting status to 'deleted'
        $result = $this->db->update(
            $this->get_table_name('sessions'),
            ['status' => 'deleted', 'updated_at' => current_time('mysql')],
            ['id' => $session_id, 'user_id' => $user_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
        
        if ($result !== false) {
            $this->audit_logger->log($user_id, 'session_delete', 'chat_session', $session_id, [
                'session_name' => $session['session_name']
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Archive a chat session
     */
    public function archive($session_id, $user_id) {
        return $this->update($session_id, $user_id, ['status' => 'archived']);
    }
    
    /**
     * Restore a chat session from archive
     */
    public function restore($session_id, $user_id) {
        return $this->update($session_id, $user_id, ['status' => 'active']);
    }
    
    /**
     * Get session statistics
     */
    public function get_session_stats($session_id, $user_id) {
        $session = $this->get($session_id, $user_id);
        if (!$session) {
            return null;
        }
        
        // Get message statistics
        $stats = $this->db->get_row($this->db->prepare("
            SELECT 
                COUNT(*) as total_messages,
                COUNT(CASE WHEN role = 'user' THEN 1 END) as user_messages,
                COUNT(CASE WHEN role = 'assistant' THEN 1 END) as assistant_messages,
                SUM(tokens_used) as total_tokens,
                AVG(processing_time_ms) as avg_response_time,
                MAX(created_at) as last_message_at
            FROM {$this->get_table_name('messages')}
            WHERE session_id = %d
        ", $session_id), ARRAY_A);
        
        // Get cost estimation
        $cost_stats = $this->get_cost_estimation($session_id);
        
        return array_merge($session, $stats, $cost_stats);
    }
    
    /**
     * Search sessions by name or content
     */
    public function search_sessions($user_id, $query, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $search_query = "
            SELECT DISTINCT s.*, p.display_name as provider_display_name,
                   COUNT(m.id) as message_count
            FROM {$this->get_table_name('sessions')} s
            LEFT JOIN {$this->get_table_name('providers')} p ON s.provider_id = p.id
            LEFT JOIN {$this->get_table_name('messages')} m ON s.id = m.session_id
            WHERE s.user_id = %d 
            AND (s.session_name LIKE %s 
                 OR s.conversation_context LIKE %s 
                 OR m.content LIKE %s)
            AND s.status != 'deleted'
            GROUP BY s.id
            ORDER BY s.last_activity DESC
            LIMIT %d OFFSET %d
        ";
        
        $search_term = '%' . $this->db->esc_like($query) . '%';
        
        $sessions = $this->db->get_results($this->db->prepare($search_query, 
            $user_id, $search_term, $search_term, $search_term, $limit, $offset
        ), ARRAY_A);
        
        // Get total count
        $count_query = "
            SELECT COUNT(DISTINCT s.id)
            FROM {$this->get_table_name('sessions')} s
            LEFT JOIN {$this->get_table_name('messages')} m ON s.id = m.session_id
            WHERE s.user_id = %d 
            AND (s.session_name LIKE %s 
                 OR s.conversation_context LIKE %s 
                 OR m.content LIKE %s)
            AND s.status != 'deleted'
        ";
        
        $total = $this->db->get_var($this->db->prepare($count_query, 
            $user_id, $search_term, $search_term, $search_term
        ));
        
        return [
            'sessions' => $sessions,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Clean up old sessions
     */
    public function cleanup_old_sessions($days = 90) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted_count = $this->db->query($this->db->prepare("
            UPDATE {$this->get_table_name('sessions')}
            SET status = 'deleted'
            WHERE status = 'archived' 
            AND last_activity < %s
        ", $cutoff_date));
        
        return $deleted_count;
    }
    
    /**
     * Get default system prompt based on context
     */
    private function get_default_system_prompt() {
        // Check if WordPress documentation manager is available
        if (class_exists('SMO_Social\WordPress\DocumentationManager')) {
            $doc_manager = new \SMO_Social\WordPress\DocumentationManager();
            return $doc_manager->getEnhancedSystemPrompt('Chat interface');
        }
        
        return "You are SMO Social's AI assistant, helping create engaging social media content. " .
               "Provide helpful, creative, and platform-appropriate responses. " .
               "Keep responses concise and optimized for social media engagement.";
    }
    
    /**
     * Get default provider ID
     */
    private function get_default_provider_id() {
        $default_provider = $this->db->get_row("
            SELECT id FROM {$this->get_table_name('providers')}
            WHERE is_default = 1 AND status = 'active'
            LIMIT 1
        ");
        
        return $default_provider ? $default_provider->id : 1;
    }
    
    /**
     * Calculate cost estimation for session
     */
    private function get_cost_estimation($session_id) {
        // This would integrate with provider pricing APIs
        // For now, return estimated values
        return [
            'estimated_cost' => 0.000000,
            'cost_breakdown' => [],
            'remaining_quota' => 1000,
            'quota_reset_date' => date('Y-m-d', strtotime('+1 month'))
        ];
    }
    
    /**
     * Get table name helper
     */
    private function get_table_name($type) {
        $tables = DatabaseSchema::get_table_names();
        return $tables[$type] ?? '';
    }
}
