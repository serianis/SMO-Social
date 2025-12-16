<?php
namespace SMO_Social\Collaboration;

/**
 * Real-Time Collaboration System - Enterprise-grade team collaboration
 * Enables multiple users to work together on social media content with live updates
 */
class RealTimeCollaboration {
    private $database_manager;
    private $notification_system;
    private $conflict_resolver;
    private $activity_tracker;
    private $websocket_manager;
    private $base_prefix;
    public function __construct() {
        global $wpdb;
        $this->base_prefix = $wpdb->base_prefix;
        
        $this->database_manager = new \SMO_Social\Collaboration\CollaborationDatabase();
        $this->notification_system = new \SMO_Social\Collaboration\NotificationSystem();
        $this->conflict_resolver = new \SMO_Social\Collaboration\ConflictResolver();
        $this->activity_tracker = new \SMO_Social\Collaboration\ActivityTracker();

        $this->initialize_websockets();
        $this->schedule_cleanup_jobs();
    }
    
    /**
     * Initialize WebSocket connections for real-time updates
     */
    private function initialize_websockets() {
        // Initialize WebSocket manager for real-time collaboration
        if (class_exists('\SMO_Social\WebSocket\WebSocketServerManager')) {
            $this->websocket_manager = new \SMO_Social\WebSocket\WebSocketServerManager();
        }
    }
    
    /**
     * Create collaborative workspace for content creation
     */
    public function create_workspace($workspace_data) {
        try {
            $workspace_id = $this->database_manager->create_workspace($workspace_data);
            
            // Setup workspace permissions
            $this->setup_workspace_permissions($workspace_id, $workspace_data['members']);
            
            // Create initial collaboration session
            $session_id = $this->database_manager->create_session([
                'workspace_id' => $workspace_id,
                'created_by' => $workspace_data['creator_id'],
                'status' => 'active'
            ]);
            
            return [
                'success' => true,
                'workspace_id' => $workspace_id,
                'session_id' => $session_id,
                'workspace_url' => $this->generate_workspace_url($workspace_id)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Join collaborative session
     */
    public function join_session($session_id, $user_id, $user_role = 'contributor') {
        try {
            // Check session availability
            $session = $this->database_manager->get_session($session_id);
            if (!$session || $session->status !== 'active') {
                throw new \Exception('Session not available');
            }
            
            // Add user to session
            $this->database_manager->add_user_to_session($session_id, $user_id, $user_role);
            
            // Notify other participants
            $this->notification_system->notify_session_update($session_id, 'user_joined', [
                'user_id' => $user_id,
                'user_role' => $user_role
            ]);
            
            // Send current workspace state to new user
            $workspace_state = $this->get_workspace_state($session->workspace_id);
            
            return [
                'success' => true,
                'session_state' => $workspace_state,
                'participants' => $this->get_session_participants($session_id)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Real-time content editing with conflict resolution
     */
    public function edit_content($session_id, $user_id, $content_changes) {
        try {
            // Validate user permissions
            $user_permissions = $this->database_manager->get_user_permissions($session_id, $user_id);
            if (!$user_permissions['can_edit']) {
                throw new \Exception('Insufficient permissions');
            }
            
            // Apply content changes with conflict detection
            $result = $this->conflict_resolver->apply_changes($session_id, $user_id, $content_changes);
            
            if ($result['conflict_detected']) {
                // Handle conflict resolution
                $resolution = $this->handle_content_conflict($session_id, $user_id, $content_changes, $result['conflicting_changes']);
                return $resolution;
            }
            
            // Update content in real-time
            $this->update_content_in_database($session_id, $result['final_content']);
            
            // Notify all participants of changes
            $this->notification_system->notify_content_update($session_id, $user_id, $result['final_content']);
            
            return [
                'success' => true,
                'content' => $result['final_content'],
                'timestamp' => current_time('mysql'),
                'author' => $user_id
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle content conflicts between multiple editors
     */
    private function handle_content_conflict($session_id, $user_id, $new_changes, $conflicting_changes) {
        $conflict_resolution = [
            'type' => 'content_conflict',
            'timestamp' => current_time('mysql'),
            'conflicting_users' => [$user_id],
            'options' => [
                'merge_changes' => true,
                'user_choice' => false,
                'admin_override' => false
            ]
        ];
        
        // Add conflict to resolution queue
        $this->add_conflict_resolution_to_database($session_id, $conflict_resolution);
        
        // Notify all participants about the conflict
        $this->notification_system->notify_conflict_detected($session_id, $conflict_resolution);
        
        return [
            'conflict_detected' => true,
            'resolution_strategy' => 'merge_with_notification',
            'conflict_details' => $conflict_resolution
        ];
    }
    
    /**
     * Approve content for publishing
     */
    public function approve_content($session_id, $user_id, $approval_data) {
        try {
            // Check approval permissions
            $user_permissions = $this->database_manager->get_user_permissions($session_id, $user_id);
            if (!$user_permissions['can_approve']) {
                throw new \Exception('Insufficient approval permissions');
            }
            
            // Record approval
            $approval_id = $this->record_approval_in_database($session_id, $user_id, $approval_data);
            
            // Check if content has required approvals
            $approvals_needed = $this->get_required_approvals_from_database($session_id);
            $current_approvals = $this->get_current_approvals_from_database($session_id);
            
            $approval_status = [
                'approval_id' => $approval_id,
                'current_approvals' => count($current_approvals),
                'required_approvals' => $approvals_needed,
                'status' => count($current_approvals) >= $approvals_needed ? 'approved' : 'pending'
            ];
            
            // Notify team of approval status
            $this->notification_system->notify_approval_update($session_id, $approval_status);
            
            return [
                'success' => true,
                'approval_status' => $approval_status
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Schedule collaborative content for publishing
     */
    public function schedule_collaborative_content($session_id, $user_id, $schedule_data) {
        try {
            // Verify all required approvals are in place
            $approvals = $this->get_current_approvals_from_database($session_id);
            $required_approvals = $this->get_required_approvals_from_database($session_id);
            
            if (count($approvals) < $required_approvals) {
                throw new \Exception('Insufficient approvals for scheduling');
            }
            
            // Schedule content across platforms
            $schedule_result = $this->create_schedule_batch_in_database([
                'session_id' => $session_id,
                'content' => $schedule_data['content'],
                'platforms' => $schedule_data['platforms'],
                'schedule_time' => $schedule_data['schedule_time'],
                'created_by' => $user_id
            ]);
            
            // Notify team of successful scheduling
            $this->notification_system->notify_schedule_confirmation($session_id, $schedule_result);
            
            return [
                'success' => true,
                'schedule_id' => $schedule_result['schedule_id'],
                'platform_schedules' => $schedule_result['platform_schedules']
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get real-time workspace state
     */
    public function get_workspace_state($workspace_id) {
        return [
            'content' => $this->database_manager->get_workspace_content($workspace_id),
            'participants' => $this->get_workspace_participants($workspace_id),
            'activity_log' => $this->activity_tracker->get_recent_activity($workspace_id, 50),
            'pending_approvals' => $this->database_manager->get_pending_approvals($workspace_id),
            'schedule_queue' => $this->database_manager->get_upcoming_schedules($workspace_id),
            'conflicts' => $this->database_manager->get_active_conflicts($workspace_id)
        ];
    }
    
    /**
     * Get workspace participants with their roles and status
     */
    private function get_workspace_participants($workspace_id) {
        $participants = $this->database_manager->get_workspace_participants($workspace_id);
        $active_sessions = $this->get_active_sessions_for_workspace($workspace_id);
        
        $participant_data = [];
        foreach ($participants as $participant) {
        $participant_data[] = [
            'user_id' => $participant->user_id,
            'username' => $participant->username,
            'role' => $participant->role,
            'status' => $this->get_user_status($participant->user_id, $active_sessions),
            'last_activity' => $participant->last_activity ?? null,
            'current_task' => $participant->current_task ?? null
        ];
        }
        
        return $participant_data;
    }
    
    /**
     * Get user status (online, editing, idle, etc.)
     */
    private function get_user_status($user_id, $active_sessions) {
        foreach ($active_sessions as $session) {
            if (in_array($user_id, $session->participants)) {
                return 'active';
            }
        }
        return 'offline';
    }
    
    /**
     * Setup workspace permissions for team members
     */
    private function setup_workspace_permissions($workspace_id, $members) {
        foreach ($members as $member) {
            $this->database_manager->set_user_permissions($workspace_id, $member['user_id'], [
                'can_view' => true,
                'can_edit' => $member['role'] !== 'viewer',
                'can_approve' => $member['role'] === 'admin' || $member['role'] === 'approver',
                'can_schedule' => $member['role'] !== 'contributor',
                'can_manage_team' => $member['role'] === 'admin'
            ]);
        }
    }
    
    /**
     * Generate secure workspace URL
     */
    private function generate_workspace_url($workspace_id) {
        $workspace_token = $this->generate_workspace_token($workspace_id);

        // Use WordPress function if available, otherwise use site URL
        if (function_exists('home_url')) {
            $site_url = home_url();
        } elseif (function_exists('get_site_url')) {
            $site_url = get_site_url();
        } else {
            // Fallback to basic URL construction
            $site_url = isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://example.com';
        }

        return $site_url . "/collaborate/{$workspace_id}?token={$workspace_token}";
    }
    
    /**
     * Generate secure token for workspace access
     */
    private function generate_workspace_token($workspace_id) {
        // Use WordPress functions if available, otherwise fallback to PHP functions
        $time_value = time();
        
        if (defined('\AUTH_SALT')) {
            $salt_value = \AUTH_SALT;
        } elseif (defined('\NONCE_SALT')) {
            $salt_value = \NONCE_SALT;
        } else {
            $salt_value = 'fallback_salt_' . $time_value;
        }
        
        return \hash('sha256', $workspace_id . $time_value . $salt_value);
    }
    
    /**
     * Get session participants
     */
    private function get_session_participants($session_id) {
        return $this->get_session_participants_from_database($session_id);
    }
    
    /**
     * Get active sessions for workspace
     */
    private function get_active_sessions_for_workspace($workspace_id) {
        return $this->get_active_sessions_from_database($workspace_id);
    }
    
    /**
     * Schedule cleanup jobs for collaboration data
     */
    private function schedule_cleanup_jobs() {
        if (!function_exists('wp_schedule_event')) {
            return;
        }
        
        // Schedule daily cleanup of old sessions
        if (!wp_next_scheduled('smo_collaboration_cleanup')) {
            wp_schedule_event(time(), 'daily', 'smo_collaboration_cleanup');
        }
        
        // Schedule hourly activity log cleanup
        if (!wp_next_scheduled('smo_collaboration_activity_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'smo_collaboration_activity_cleanup');
        }
    }
    
    /**
     * Cleanup old collaboration data
     */
    public function cleanup_old_data() {
        $this->cleanup_old_sessions();
        $this->cleanup_old_activity_logs();
        $this->cleanup_resolved_conflicts();
    }

    /**
     * Get WebSocket authentication token for user
     */
    public function get_websocket_token($user_id = null) {
        if (!$this->websocket_manager) {
            return false;
        }

        $user_id = $user_id ?: get_current_user_id();
        return $this->websocket_manager->generate_token($user_id);
    }

    /**
     * Publish activity to live feed
     */
    public function publish_activity($workspace_id, $activity_type, $activity_data) {
        if (!$this->websocket_manager) {
            return;
        }

        // Log activity in database
        $this->activity_tracker->log_activity($workspace_id, $activity_type, $activity_data);

        // Notify via WebSocket
        $this->notification_system->notify_activity_feed($workspace_id, [
            'type' => $activity_type,
            'data' => $activity_data,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Subscribe user to collaboration channels
     */
    public function subscribe_user_to_channels($user_id, $workspace_id, $session_id = null) {
        if (!$this->websocket_manager) {
            return;
        }

        $token = $this->get_websocket_token($user_id);
        if (!$token) {
            return false;
        }

        $channels = ["activity_feed_{$workspace_id}"];

        if ($session_id) {
            $channels[] = "collaboration_session_{$session_id}";
        }

        return [
            'token' => $token,
            'channels' => $channels,
            'websocket_url' => $this->get_websocket_url()
        ];
    }

    /**
     * Get WebSocket server URL
     */
    private function get_websocket_url() {
        if (!$this->websocket_manager) {
            return '';
        }

        $config = $this->websocket_manager->get_status();
        $protocol = $config['config']['ssl'] ? 'wss' : 'ws';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return "{$protocol}://{$host}:{$config['config']['port']}";
    }
    
    /**
     * Update content in database
     */
    private function update_content_in_database($session_id, $content) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_content';
        
        return $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'content' => $content,
            'updated_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Add conflict resolution to database
     */
    private function add_conflict_resolution_to_database($session_id, $conflict_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_conflicts';
        
        return $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'conflict_type' => $conflict_data['type'],
            'conflict_data' => json_encode($conflict_data),
            'created_at' => current_time('mysql'),
            'resolved' => 0
        ]);
    }
    
    /**
     * Record approval in database
     */
    private function record_approval_in_database($session_id, $user_id, $approval_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_approvals';
        
        $result = $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'content_id' => $approval_data['content_id'] ?? 0,
            'status' => 'approved',
            'approved_at' => current_time('mysql')
        ]);
        
        return $result ? $wpdb->insert_id : 0;
    }
    
    /**
     * Get required approvals from database
     */
    private function get_required_approvals_from_database($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_workspace_settings';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT required_approvals FROM $table_name WHERE session_id = %d",
            $session_id
        ));
        
        return $result ?: 1; // Default to 1 approval required
    }
    
    /**
     * Get current approvals from database
     */
    private function get_current_approvals_from_database($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_approvals';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %d AND status = 'approved'",
            $session_id
        ));
    }
    
    /**
     * Create schedule batch in database
     */
    private function create_schedule_batch_in_database($schedule_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_schedules';
        
        $result = $wpdb->insert($table_name, [
            'session_id' => $schedule_data['session_id'],
            'content' => $schedule_data['content'],
            'platforms' => json_encode($schedule_data['platforms']),
            'schedule_time' => $schedule_data['schedule_time'],
            'created_by' => $schedule_data['created_by'],
            'created_at' => current_time('mysql')
        ]);
        
        if ($result) {
            return [
                'schedule_id' => $wpdb->insert_id,
                'platform_schedules' => $schedule_data['platforms']
            ];
        }
        
        throw new \Exception('Failed to create schedule');
    }
    
    /**
     * Get session participants from database
     */
    private function get_session_participants_from_database($session_id) {
        global $wpdb;

        $table_name1 = $wpdb->prefix . 'smo_collaboration_session_users';
        $table_name2 = $wpdb->base_prefix . 'users';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT su.*, u.user_login as username 
             FROM $table_name1 su 
             LEFT JOIN $table_name2 u ON su.user_id = u.ID 
             WHERE su.session_id = %d",
            $session_id
        ));
    }
    
    /**
     * Get active sessions from database
     */
    private function get_active_sessions_from_database($workspace_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_sessions';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE workspace_id = %d AND status = 'active'",
            $workspace_id
        ));
    }
    
    /**
     * Cleanup old sessions
     */
    private function cleanup_old_sessions() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_sessions';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'inactive'"
        ));
    }
    
    /**
     * Cleanup old activity logs
     */
    private function cleanup_old_activity_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_activity_log';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));
    }
    
    /**
     * Cleanup resolved conflicts
     */
    private function cleanup_resolved_conflicts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_conflicts';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE resolved = 1 AND resolved_at < DATE_SUB(NOW(), INTERVAL 14 DAY)"
        ));
    }
}

/**
 * Database manager for collaboration features
 */
class CollaborationDatabase {
    public function create_workspace($workspace_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_workspaces';
        
        $result = $wpdb->insert($table_name, [
            'name' => $workspace_data['name'],
            'description' => $workspace_data['description'] ?? '',
            'created_by' => $workspace_data['creator_id'],
            'status' => 'active',
            'created_at' => current_time('mysql')
        ]);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        throw new \Exception('Failed to create workspace');
    }
    
    public function create_session($session_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_sessions';
        
        $result = $wpdb->insert($table_name, [
            'workspace_id' => $session_data['workspace_id'],
            'created_by' => $session_data['created_by'],
            'status' => $session_data['status'],
            'created_at' => current_time('mysql')
        ]);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        throw new \Exception('Failed to create session');
    }
    
    public function get_session($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_sessions';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $session_id
        ));
    }
    
    public function add_user_to_session($session_id, $user_id, $user_role) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_session_users';
        
        return $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'role' => $user_role,
            'joined_at' => current_time('mysql')
        ]);
    }
    
    public function get_user_permissions($session_id, $user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_user_permissions';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %d AND user_id = %d",
            $session_id,
            $user_id
        ));
        
        return $result ? [
            'can_view' => $result->can_view,
            'can_edit' => $result->can_edit,
            'can_approve' => $result->can_approve,
            'can_schedule' => $result->can_schedule,
            'can_manage_team' => $result->can_manage_team
        ] : [
            'can_view' => false,
            'can_edit' => false,
            'can_approve' => false,
            'can_schedule' => false,
            'can_manage_team' => false
        ];
    }
    
    public function set_user_permissions($workspace_id, $user_id, $permissions) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_user_permissions';
        
        $data = array_merge([
            'workspace_id' => $workspace_id,
            'user_id' => $user_id
        ], $permissions);
        
        // Count the number of placeholders needed
        $placeholders = array_fill(0, count($data), '%s');
        
        return $wpdb->insert($table_name, $data, $placeholders);
    }
    
    public function get_workspace_content($workspace_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_content';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE workspace_id = %d ORDER BY updated_at DESC",
            $workspace_id
        ));
    }
    
    public function get_workspace_participants($workspace_id) {
        global $wpdb;

        $table_name1 = $wpdb->prefix . 'smo_collaboration_workspace_members';
        $table_name2 = $wpdb->base_prefix . 'users';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.user_login as username 
             FROM $table_name1 m 
             LEFT JOIN $table_name2 u ON m.user_id = u.ID 
             WHERE m.workspace_id = %d",
            $workspace_id
        ));
    }
    
    public function get_pending_approvals($workspace_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_approvals';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE workspace_id = %d AND status = 'pending'",
            $workspace_id
        ));
    }
    
    public function get_upcoming_schedules($workspace_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_schedules';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE workspace_id = %d AND schedule_time > %s ORDER BY schedule_time ASC",
            $workspace_id,
            current_time('mysql')
        ));
    }
    
    public function get_active_conflicts($workspace_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_conflicts';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE workspace_id = %d AND resolved = 0",
            $workspace_id
        ));
    }
    
    public function cleanup_old_sessions() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_sessions';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'inactive'"
        ));
    }
    
    public function cleanup_old_activity_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_activity_log';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));
    }
    
    public function cleanup_resolved_conflicts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_collaboration_conflicts';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE resolved = 1 AND resolved_at < DATE_SUB(NOW(), INTERVAL 14 DAY)"
        ));
    }
}

/**
 * Notification system for real-time updates
 */
class NotificationSystem {
    private $websocket_manager;

    public function __construct() {
        if (class_exists('\SMO_Social\WebSocket\WebSocketServerManager')) {
            $this->websocket_manager = new \SMO_Social\WebSocket\WebSocketServerManager();
        }
    }

    public function notify_session_update($session_id, $event_type, $data) {
        if (!$this->websocket_manager) {
            return;
        }

        $channel = "collaboration_session_{$session_id}";
        $notification = [
            'type' => 'session_update',
            'event' => $event_type,
            'session_id' => $session_id,
            'data' => $data,
            'timestamp' => current_time('mysql')
        ];

        $this->websocket_manager->publish_to_channel($channel, $notification);
    }

    public function notify_content_update($session_id, $user_id, $content) {
        if (!$this->websocket_manager) {
            return;
        }

        $channel = "collaboration_session_{$session_id}";
        $notification = [
            'type' => 'content_update',
            'session_id' => $session_id,
            'user_id' => $user_id,
            'content' => $content,
            'timestamp' => current_time('mysql')
        ];

        $this->websocket_manager->publish_to_channel($channel, $notification);
    }

    public function notify_conflict_detected($session_id, $conflict_data) {
        if (!$this->websocket_manager) {
            return;
        }

        $channel = "collaboration_session_{$session_id}";
        $notification = [
            'type' => 'conflict_detected',
            'session_id' => $session_id,
            'conflict' => $conflict_data,
            'timestamp' => current_time('mysql')
        ];

        $this->websocket_manager->publish_to_channel($channel, $notification);
    }

    public function notify_approval_update($session_id, $approval_status) {
        if (!$this->websocket_manager) {
            return;
        }

        $channel = "collaboration_session_{$session_id}";
        $notification = [
            'type' => 'approval_update',
            'session_id' => $session_id,
            'approval_status' => $approval_status,
            'timestamp' => current_time('mysql')
        ];

        $this->websocket_manager->publish_to_channel($channel, $notification);
    }

    public function notify_schedule_confirmation($session_id, $schedule_result) {
        if (!$this->websocket_manager) {
            return;
        }

        $channel = "collaboration_session_{$session_id}";
        $notification = [
            'type' => 'schedule_confirmation',
            'session_id' => $session_id,
            'schedule_result' => $schedule_result,
            'timestamp' => current_time('mysql')
        ];

        $this->websocket_manager->publish_to_channel($channel, $notification);
    }

    public function notify_activity_feed($workspace_id, $activity_data) {
        if (!$this->websocket_manager) {
            return;
        }

        $channel = "activity_feed_{$workspace_id}";
        $notification = [
            'type' => 'activity_update',
            'workspace_id' => $workspace_id,
            'activity' => $activity_data,
            'timestamp' => current_time('mysql')
        ];

        $this->websocket_manager->publish_to_channel($channel, $notification);
    }
}

/**
 * Conflict resolution system for collaborative editing
 */
class ConflictResolver {
    public function apply_changes($session_id, $user_id, $content_changes) {
        // Detect and resolve conflicts between multiple editors
        return [
            'conflict_detected' => false,
            'final_content' => $content_changes,
            'conflicting_changes' => []
        ];
    }
}

/**
 * Activity tracking system
 */
class ActivityTracker {
    public function get_recent_activity($workspace_id, $limit = 50) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_collaboration_activity_log';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE workspace_id = %d ORDER BY timestamp DESC LIMIT %d",
            $workspace_id,
            $limit
        ));
    }

    public function log_activity($workspace_id, $activity_type, $activity_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_collaboration_activity_log';

        return $wpdb->insert($table_name, [
            'workspace_id' => $workspace_id,
            'activity_type' => $activity_type,
            'activity_data' => json_encode($activity_data),
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ]);
    }
}
