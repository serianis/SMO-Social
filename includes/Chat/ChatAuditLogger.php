<?php
namespace SMO_Social\Chat;

use SMO_Social\Chat\DatabaseSchema;

/**
 * Chat Audit Logger
 * Handles logging of all chat interactions and actions for security and compliance
 */
class ChatAuditLogger {
    
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Log an audit event
     */
    public function log($user_id, $action, $resource_type = null, $resource_id = null, $details = []) {
        $audit_data = [
            'user_id' => $user_id,
            'session_id' => $resource_type === 'chat_session' ? $resource_id : null,
            'action' => $action,
            'resource_type' => $resource_type,
            'resource_id' => $resource_id,
            'details' => json_encode($details),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        ];
        
        $result = $this->db->insert(
            $this->get_table_name('audit'),
            $audit_data,
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get audit logs with filtering
     */
    public function get_logs($user_id = null, $action = null, $resource_type = null, $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        
        if ($user_id !== null) {
            $where_conditions[] = "a.user_id = %d";
            $params[] = $user_id;
        }
        
        if ($action !== null) {
            $where_conditions[] = "a.action = %s";
            $params[] = $action;
        }
        
        if ($resource_type !== null) {
            $where_conditions[] = "a.resource_type = %s";
            $params[] = $resource_type;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "
            SELECT a.*, u.display_name as user_name, s.session_name
            FROM {$this->get_table_name('audit')} a
            LEFT JOIN {$this->db->users} u ON a.user_id = u.ID
            LEFT JOIN {$this->get_table_name('sessions')} s ON a.session_id = s.id
            $where_clause
            ORDER BY a.created_at DESC
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $logs = $this->db->get_results(
            !empty($where_conditions) ? $this->db->prepare($query, ...$params) : $query,
            ARRAY_A
        );
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->get_table_name('audit')} a $where_clause";
        $total = !empty($where_conditions) ? 
            $this->db->get_var($this->db->prepare($count_query, ...array_slice($params, 0, -2))) :
            $this->db->get_var($count_query);
        
        // Parse details JSON
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }
        
        return [
            'logs' => $logs,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Get logs for a specific session
     */
    public function get_session_logs($session_id, $page = 1, $limit = 50) {
        return $this->get_logs(null, null, 'chat_session', $page, $limit);
    }
    
    /**
     * Get user activity summary
     */
    public function get_user_activity_summary($user_id, $days = 30) {
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $summary = $this->db->get_row($this->db->prepare("
            SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT session_id) as sessions_used,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COUNT(CASE WHEN action = 'message_send' THEN 1 END) as messages_sent,
                COUNT(CASE WHEN action = 'session_create' THEN 1 END) as sessions_created
            FROM {$this->get_table_name('audit')}
            WHERE user_id = %d AND created_at >= %s
        ", $user_id, $date_from), ARRAY_A);
        
        // Get daily activity breakdown
        $daily_activity = $this->db->get_results($this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as actions,
                COUNT(CASE WHEN action = 'message_send' THEN 1 END) as messages
            FROM {$this->get_table_name('audit')}
            WHERE user_id = %d AND created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", $user_id, $date_from), ARRAY_A);
        
        $summary['daily_activity'] = $daily_activity;
        return $summary;
    }
    
    /**
     * Clean up old audit logs
     */
    public function cleanup_old_logs($days = 365) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted_count = $this->db->query($this->db->prepare("
            DELETE FROM {$this->get_table_name('audit')}
            WHERE created_at < %s
        ", $cutoff_date));
        
        return $deleted_count;
    }
    
    /**
     * Get most active actions
     */
    public function get_action_statistics($days = 30, $limit = 10) {
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->db->get_results($this->db->prepare("
            SELECT 
                action,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users
            FROM {$this->get_table_name('audit')}
            WHERE created_at >= %s
            GROUP BY action
            ORDER BY count DESC
            LIMIT %d
        ", $date_from, $limit), ARRAY_A);
    }
    
    /**
     * Get table name helper
     */
    private function get_table_name($type) {
        $tables = DatabaseSchema::get_table_names();
        return $tables[$type] ?? '';
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $server_vars = isset($_SERVER) ? $_SERVER : [];
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $server_vars) && !empty($server_vars[$key])) {
                $ip = $server_vars[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return array_key_exists('REMOTE_ADDR', $server_vars) ? $server_vars['REMOTE_ADDR'] : '127.0.0.1';
    }
}