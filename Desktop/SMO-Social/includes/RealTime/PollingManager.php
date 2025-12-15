<?php
/**
 * Polling Manager
 * Διαχειρίζεται τα polling sessions για real-time functionality
 */

namespace SMO_Social\RealTime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Polling Manager
 * Handles polling sessions and state management
 */
class PollingManager {
    /** @var array */
    private $polling_sessions = [];
    /** @var array */
    private $config;

    public function __construct() {
        $this->config = [
            'max_session_duration' => 3600, // 1 hour
            'max_idle_time' => 300, // 5 minutes
            'max_concurrent_sessions' => 1000,
            'cleanup_interval' => 600 // 10 minutes
        ];
        
        $this->load_config();
        $this->load_sessions();
    }

    /**
     * Initialize the polling manager
     */
    public function initialize() {
        $this->load_sessions();
        error_log('SMO RealTime: PollingManager initialized');
    }

    /**
     * Load configuration from WordPress options
     */
    private function load_config() {
        $saved_config = get_option('smo_polling_config', []);
        if (is_array($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }
    }

    /**
     * Load polling sessions from WordPress options
     */
    private function load_sessions() {
        $this->polling_sessions = get_option('smo_realtime_polling_sessions', []);
        if (!is_array($this->polling_sessions)) {
            $this->polling_sessions = [];
        }
    }

    /**
     * Save polling sessions to WordPress options
     */
    private function save_sessions() {
        update_option('smo_realtime_polling_sessions', $this->polling_sessions);
    }

    /**
     * Create or update polling session
     */
    public function create_polling_session($user_id, $channels, $token = null) {
        // Check if user already has a session
        $existing_session = $this->get_user_session($user_id);
        
        if ($existing_session) {
            // Update existing session
            $this->update_polling_session($existing_session['session_id'], $channels);
            return $existing_session['session_id'];
        }

        // Check session limit
        if (count($this->polling_sessions) >= $this->config['max_concurrent_sessions']) {
            // Clean up old sessions first
            $this->cleanup_idle_sessions();
            
            // Check again
            if (count($this->polling_sessions) >= $this->config['max_concurrent_sessions']) {
                error_log('SMO RealTime: Maximum polling sessions reached');
                return false;
            }
        }

        // Create new session
        $session_id = $this->generate_session_id();
        $session = [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'channels' => $channels,
            'token' => $token,
            'created_at' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'request_count' => 0,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        $this->polling_sessions[$session_id] = $session;
        $this->save_sessions();

        error_log("SMO RealTime: Created polling session {$session_id} for user {$user_id}");
        return $session_id;
    }

    /**
     * Update polling session
     */
    public function update_polling_session($session_id, $channels) {
        if (!isset($this->polling_sessions[$session_id])) {
            return false;
        }

        $this->polling_sessions[$session_id]['channels'] = $channels;
        $this->polling_sessions[$session_id]['last_activity'] = current_time('mysql');
        $this->polling_sessions[$session_id]['request_count']++;

        $this->save_sessions();
        return true;
    }

    /**
     * Get polling session
     */
    public function get_polling_session($session_id) {
        return isset($this->polling_sessions[$session_id]) ? $this->polling_sessions[$session_id] : null;
    }

    /**
     * Get user's active session
     */
    public function get_user_session($user_id) {
        foreach ($this->polling_sessions as $session) {
            if ($session['user_id'] == $user_id) {
                return $session;
            }
        }
        return null;
    }

    /**
     * Check if session is valid
     */
    public function is_session_valid($session_id) {
        if (!isset($this->polling_sessions[$session_id])) {
            return false;
        }

        $session = $this->polling_sessions[$session_id];
        $now = time();
        $last_activity = strtotime($session['last_activity']);
        $created_at = strtotime($session['created_at']);

        // Check session duration
        if ($now - $created_at > $this->config['max_session_duration']) {
            $this->remove_polling_session($session_id);
            return false;
        }

        // Check idle time
        if ($now - $last_activity > $this->config['max_idle_time']) {
            $this->remove_polling_session($session_id);
            return false;
        }

        return true;
    }

    /**
     * Get messages for polling session
     */
    public function get_session_messages($session_id, $data_manager) {
        if (!$this->is_session_valid($session_id)) {
            return ['error' => 'invalid_session'];
        }

        $session = $this->polling_sessions[$session_id];
        $messages = [];

        // Get messages for each subscribed channel
        foreach ($session['channels'] as $channel) {
            $channel_messages = $data_manager->get_channel_messages($channel);
            foreach ($channel_messages as $message) {
                $messages[] = array_merge($message, ['channel' => $channel]);
            }
        }

        // Sort messages by timestamp
        usort($messages, function($a, $b) {
            return strtotime($a['received_at']) - strtotime($b['received_at']);
        });

        // Update session activity
        $this->update_session_activity($session_id);

        return [
            'success' => true,
            'messages' => $messages,
            'session_id' => $session_id,
            'channels' => $session['channels'],
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Update session activity
     */
    private function update_session_activity($session_id) {
        if (isset($this->polling_sessions[$session_id])) {
            $this->polling_sessions[$session_id]['last_activity'] = current_time('mysql');
            $this->save_sessions();
        }
    }

    /**
     * Remove polling session
     */
    public function remove_polling_session($session_id) {
        if (isset($this->polling_sessions[$session_id])) {
            $user_id = $this->polling_sessions[$session_id]['user_id'];
            unset($this->polling_sessions[$session_id]);
            $this->save_sessions();
            
            error_log("SMO RealTime: Removed polling session {$session_id} for user {$user_id}");
            return true;
        }
        return false;
    }

    /**
     * Remove user's session
     */
    public function remove_user_session($user_id) {
        $session = $this->get_user_session($user_id);
        if ($session) {
            return $this->remove_polling_session($session['session_id']);
        }
        return false;
    }

    /**
     * Cleanup idle sessions
     */
    public function cleanup_idle_sessions() {
        $now = time();
        $cutoff_time = $now - $this->config['max_idle_time'];
        $removed_sessions = [];

        foreach ($this->polling_sessions as $session_id => $session) {
            $last_activity = strtotime($session['last_activity']);
            
            if ($last_activity < $cutoff_time) {
                $removed_sessions[] = $session_id;
                unset($this->polling_sessions[$session_id]);
            }
        }

        if (!empty($removed_sessions)) {
            $this->save_sessions();
            error_log('SMO RealTime: Cleaned up ' . count($removed_sessions) . ' idle sessions');
        }

        return $removed_sessions;
    }

    /**
     * Get polling statistics
     */
    public function get_statistics() {
        $total_sessions = count($this->polling_sessions);
        $active_sessions = 0;
        $total_requests = 0;

        foreach ($this->polling_sessions as $session) {
            if ($this->is_session_valid($session['session_id'])) {
                $active_sessions++;
            }
            $total_requests += $session['request_count'];
        }

        return [
            'total_sessions' => $total_sessions,
            'active_sessions' => $active_sessions,
            'total_requests' => $total_requests,
            'average_requests_per_session' => $total_sessions > 0 ? round($total_requests / $total_sessions, 2) : 0,
            'cleanup_config' => [
                'max_idle_time' => $this->config['max_idle_time'],
                'max_session_duration' => $this->config['max_session_duration'],
                'max_concurrent_sessions' => $this->config['max_concurrent_sessions']
            ]
        ];
    }

    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        do {
            $session_id = 'poll_' . wp_generate_password(20, false);
        } while (isset($this->polling_sessions[$session_id]));
        
        return $session_id;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Get all active sessions
     */
    public function get_active_sessions() {
        $active_sessions = [];
        
        foreach ($this->polling_sessions as $session_id => $session) {
            if ($this->is_session_valid($session_id)) {
                $active_sessions[$session_id] = $session;
            }
        }
        
        return $active_sessions;
    }

    /**
     * Force cleanup old sessions
     */
    public function force_cleanup() {
        $removed_count = count($this->cleanup_idle_sessions());
        
        // Also remove sessions that exceeded maximum duration
        $now = time();
        $duration_cutoff = $now - $this->config['max_session_duration'];
        
        foreach ($this->polling_sessions as $session_id => $session) {
            $created_at = strtotime($session['created_at']);
            if ($created_at < $duration_cutoff) {
                unset($this->polling_sessions[$session_id]);
                $removed_count++;
            }
        }
        
        if ($removed_count > 0) {
            $this->save_sessions();
            error_log("SMO RealTime: Force cleanup removed {$removed_count} sessions");
        }
        
        return $removed_count;
    }
}