<?php
namespace SMO_Social\Chat;

use SMO_Social\AI\Manager as AIManager;
use SMO_Social\Chat\DatabaseSchema;
use SMO_Social\Core\CacheManager;

/**
 * Class ChatMessage
 * Handles chat message operations
 * Version: 1.3 (Fixed AI Provider ID mapping with robust fallback)
 */
class ChatMessage {
    /**
     * @var \wpdb
     */
    private $db;

    /**
     * @var AIManager
     */
    private $ai_manager;

    /**
     * @var CacheManager
     */
    private $cache_manager;

    /**
     * @var object
     */
    private $audit_logger;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->ai_manager = AIManager::getInstance();
        $this->cache_manager = new CacheManager();

        // Initialize audit logger if available, otherwise use a placeholder
        if (class_exists('\SMO_Social\Chat\ChatAuditLogger')) {
            $this->audit_logger = new \SMO_Social\Chat\ChatAuditLogger();
        } else {
            // Simple fallback logger
            $this->audit_logger = new class {
                public function log($user_id, $action, $entity_type, $entity_id, $details = []) {
                    // Log to error log for now
                    error_log("SMO Audit: User $user_id performed $action on $entity_type $entity_id");
                }
            };
        }
    }

    /**
     * Send a message in a session
     */
    public function send($session_id, $user_id, $content, $options = []) {
        $start_time = microtime(true);

        // Validate session access
        $session = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->get_table_name('sessions')}
            WHERE id = %d AND (user_id = %d OR is_public = 1)
        ", $session_id, $user_id), ARRAY_A);

        if (!$session) {
            throw new \Exception('Session not found or access denied');
        }

        // Check rate limits
        if (!$this->check_rate_limit($user_id, $session['provider_id'])) {
            throw new \Exception('Rate limit exceeded. Please try again later.');
        }

        // Store user message
        $user_message_id = $this->create_message([
            'session_id' => $session_id,
            'role' => 'user',
            'content' => $content,
            'content_type' => $options['content_type'] ?? 'text',
            'metadata' => json_encode([
                'timestamp' => time(),
                'client_info' => $this->get_client_info()
            ])
        ]);

        // Get conversation context
        $conversation_context = $this->get_conversation_context($session_id);

        // Generate AI response
        $ai_response = $this->generate_ai_response($session, $conversation_context, $options);

        $processing_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        // Store AI response
        $assistant_message_id = $this->create_message([
            'session_id' => $session_id,
            'role' => 'assistant',
            'content' => $ai_response['content'],
            'content_type' => $ai_response['content_type'] ?? 'text',
            'tokens_used' => $ai_response['tokens_used'] ?? 0,
            'processing_time_ms' => round($processing_time),
            'model_used' => $session['model_name'],
            'provider_response' => json_encode($ai_response['raw_response'] ?? []),
            'metadata' => json_encode([
                'timestamp' => time(),
                'usage' => $ai_response['usage'] ?? [],
                'finish_reason' => $ai_response['finish_reason'] ?? 'stop'
            ])
        ]);

        // Update session statistics
        $this->update_session_stats($session_id, $ai_response['tokens_used'] ?? 0);

        // Log the message exchange
        $this->audit_logger->log($user_id, 'message_send', 'chat_message', $user_message_id, [
            'session_id' => $session_id,
            'content_length' => strlen($content),
            'response_length' => strlen($ai_response['content']),
            'tokens_used' => $ai_response['tokens_used'] ?? 0,
            'processing_time_ms' => round($processing_time)
        ]);

        return [
            'user_message' => $this->get($user_message_id),
            'assistant_message' => $this->get($assistant_message_id),
            'usage' => $ai_response['usage'] ?? [],
            'processing_time_ms' => round($processing_time)
        ];
    }

    /**
     * Create a new message
     */
    private function create_message($data) {
        $message_data = array_merge([
            'created_at' => current_time('mysql')
        ], $data);

        $result = $this->db->insert(
            $this->get_table_name('messages'),
            $message_data,
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            throw new \Exception('Failed to create message: ' . $this->db->last_error);
        }

        return $this->db->insert_id;
    }

    /**
     * Get a message by ID
     */
    public function get($message_id, $user_id = null) {
        $query =
            "SELECT m.*, s.user_id as session_owner_id, s.session_name
            FROM {$this->get_table_name('messages')} m
            JOIN {$this->get_table_name('sessions')} s ON m.session_id = s.id
            WHERE m.id = %d
        ";

        $params = [$message_id];

        if ($user_id !== null) {
            $query .= " AND s.user_id = %d";
            $params[] = $user_id;
        }

        $message = $this->db->get_row(
            $this->db->prepare($query, ...$params),
            ARRAY_A
        );

        if (!$message) {
            return null;
        }

        // Parse JSON fields
        $message['metadata'] = json_decode($message['metadata'], true);
        $message['provider_response'] = json_decode($message['provider_response'], true);

        return $message;
    }

    /**
     * Get messages for a session with caching
     */
    public function get_session_messages($session_id, $user_id = null, $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;

        // Generate cache key
        $cache_key = "chat_messages:{$session_id}:{$page}:{$limit}";
        if ($user_id) {
            $cache_key .= ":user_{$user_id}";
        }

        // Try to get cached messages first
        $cached_messages = $this->cache_manager->get_chat_messages_cache($session_id, $user_id, $page, $limit);
        if ($cached_messages !== false) {
            return $cached_messages;
        }

        // Verify session ownership if user_id provided
        if ($user_id !== null) {
            $session_check = $this->db->get_var($this->db->prepare(
                "SELECT id FROM {$this->get_table_name('sessions')} WHERE id = %d AND user_id = %d",
                $session_id, $user_id
            ));

            if (!$session_check) {
                throw new \Exception('Session not found or access denied');
            }
        }

        $messages = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->get_table_name('messages')}
            WHERE session_id = %d
            ORDER BY created_at ASC, id ASC
            LIMIT %d OFFSET %d
        ", $session_id, $limit, $offset), ARRAY_A);

        // Get total count
        $total = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->get_table_name('messages')}
            WHERE session_id = %d
        ", $session_id));

        // Parse JSON fields
        foreach ($messages as &$message) {
            $message['metadata'] = json_decode($message['metadata'], true);
            $message['provider_response'] = json_decode($message['provider_response'], true);
        }

        $result = [
            'messages' => $messages,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];

        // Cache the result for future requests
        $this->cache_manager->set_chat_messages_cache($session_id, $result, $user_id, $page, $limit);

        return $result;
    }

    /**
     * Get conversation context for AI processing with caching
     */
    private function get_conversation_context($session_id, $limit = 20) {
        $cache_key = "conversation_context:{$session_id}:{$limit}";

        // Try to get cached conversation context
        $cached_context = $this->cache_manager->get($cache_key);
        if ($cached_context !== false) {
            return $cached_context;
        }

        $messages = $this->db->get_results($this->db->prepare(
            "SELECT role, content FROM {$this->get_table_name('messages')}
            WHERE session_id = %d
            ORDER BY created_at ASC, id ASC
            LIMIT %d
        ", $session_id, $limit), ARRAY_A);

        $context = array_map(function($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }, $messages);

        // Cache the conversation context
        $this->cache_manager->set($cache_key, $context, 300); // 5 minutes cache

        return $context;
    }

    /**
     * Generate AI response using the session's provider
     */
    private function generate_ai_response($session, $conversation_context, $options = []) {
        // Prepare messages for AI API
        $messages = [];

        // Add system prompt if provided
        if (!empty($session['system_prompt'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $session['system_prompt']
            ];
        }

        // Add conversation context
        $messages = array_merge($messages, $conversation_context);

        // Get the latest user message for processing
        $latest_user_message = end($conversation_context);

        // Prepare API request parameters
        $request_params = array_merge([
            'model' => $session['model_name'],
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 512,
            'stream' => false
        ], json_decode($session['default_params'] ?? '{}', true));

        error_log('SMO Debug: Sending to AI Provider (' . $session['provider_id'] . '): ' . json_encode($request_params));

        // Call AI provider through existing AI Manager
        try {
            $ai_response = $this->call_ai_provider($session['provider_id'], $request_params);

            return [
                'content' => $ai_response['content'] ?? 'Sorry, I couldn\'t generate a response.',
                'content_type' => 'text',
                'tokens_used' => $ai_response['usage']['total_tokens'] ?? 0,
                'usage' => $ai_response['usage'] ?? [],
                'raw_response' => $ai_response,
                'finish_reason' => $ai_response['finish_reason'] ?? 'stop'
            ];

        } catch (\Exception $e) {
            error_log('SMO Social Chat AI Error: ' . $e->getMessage());

            return [
                'content' => 'I apologize, but I encountered an error processing your request. Please try again.',
                'content_type' => 'text',
                'tokens_used' => 0,
                'usage' => [],
                'raw_response' => ['error' => $e->getMessage()],
                'finish_reason' => 'error'
            ];
        }
    }

    /**
     * Call AI provider using the central AI Manager
     */
    private function call_ai_provider($provider_id, $params) {
        // Extract messages and options
        $messages = $params['messages'] ?? [];
        $options = $params;

        // Map numeric provider ID to string provider name
        $provider_name = $this->get_provider_name($provider_id);

        if (!$provider_name) {
            error_log('SMO Social Chat: Invalid provider ID: ' . $provider_id);
            // Try to get the primary provider as fallback
            $primary_provider = $this->ai_manager->get_primary_provider_id();
            if ($primary_provider) {
                error_log('SMO Social Chat: Falling back to primary provider: ' . $primary_provider);
                $options['provider_id'] = $primary_provider;
                return $this->ai_manager->chat($messages, $options);
            } else {
                throw new \Exception('Invalid provider ID: ' . $provider_id);
            }
        }

        error_log('SMO Social Chat: Using provider: ' . $provider_name . ' (ID: ' . $provider_id . ')');
        $options['provider_id'] = strtolower($provider_name);

        // Remove messages from options to avoid duplication
        unset($options['messages']);

        // Call AI Manager
        return $this->ai_manager->chat($messages, $options);
    }

    /**
     * Get provider name (string ID) from numeric provider ID with caching
     */
    private function get_provider_name($numeric_provider_id) {
        if (empty($numeric_provider_id)) {
            error_log('SMO Social Chat: Empty provider ID provided');
            return null;
        }

        // Use cache key for provider name lookup
        $cache_key = "provider_name:{$numeric_provider_id}";

        // Try to get cached provider name
        $cached_provider_name = $this->cache_manager->get_ai_provider_cache($numeric_provider_id);
        if ($cached_provider_name !== false) {
            error_log('SMO Social Chat: Using cached provider name: ' . $cached_provider_name);
            return $cached_provider_name;
        }

        // Enhanced debug logging
        error_log('SMO Social Chat: Looking up provider name for numeric ID: ' . $numeric_provider_id);

        // Query the database for the provider name
        $provider = $this->db->get_row($this->db->prepare(
            "SELECT name FROM {$this->get_table_name('providers')}
            WHERE id = %d AND status = 'active'
        ", $numeric_provider_id), ARRAY_A);

        if ($provider) {
            error_log('SMO Social Chat: Found provider name: ' . $provider['name'] . ' for ID: ' . $numeric_provider_id);

            // Cache the provider name for future lookups
            $this->cache_manager->set_ai_provider_cache($numeric_provider_id, $provider['name']);

            return $provider['name'];
        } else {
            error_log('SMO Social Chat: Provider not found for ID: ' . $numeric_provider_id);
            // Additional debug: check what providers exist in database
            $all_providers = $this->db->get_results("SELECT id, name FROM {$this->get_table_name('providers')}", ARRAY_A);
            error_log('SMO Social Chat: Available providers in database: ' . print_r($all_providers, true));

            // Try to find provider by name if the ID is actually a string (legacy support)
            if (is_numeric($numeric_provider_id)) {
                // Check if any provider has this as their name
                $provider_by_name = $this->db->get_row($this->db->prepare(
                    "SELECT name FROM {$this->get_table_name('providers')}
                    WHERE name = %s AND status = 'active'
                ", $numeric_provider_id), ARRAY_A);

                if ($provider_by_name) {
                    error_log('SMO Social Chat: Found provider by name match: ' . $provider_by_name['name']);

                    // Cache the provider name for future lookups
                    $this->cache_manager->set_ai_provider_cache($numeric_provider_id, $provider_by_name['name']);

                    return $provider_by_name['name'];
                }
            }

            return null;
        }
    }

    /**
     * Check rate limiting (simplified for MVP)
     */
    private function check_rate_limit($user_id, $provider_id = null) {
        // Basic rate limiting - can be enhanced later
        return true; // Always allow for now
    }

    /**
     * Queue content for moderation with caching
     */
    private function queue_for_moderation($session_id, $content, $moderation_result) {
        // Generate content hash for caching
        $content_hash = md5($content);

        // Try to get cached moderation result
        $cached_moderation = $this->cache_manager->get_moderation_cache($content_hash);
        if ($cached_moderation !== false) {
            error_log("SMO Social: Using cached moderation result for content hash: {$content_hash}");
            return $cached_moderation;
        }

        // Perform actual moderation (placeholder - this would call the real moderation service)
        $moderation_data = [
            'content_hash' => $content_hash,
            'moderation_result' => $moderation_result,
            'timestamp' => time(),
            'content_preview' => substr($content, 0, 100) . '...'
        ];

        // Cache the moderation result
        $this->cache_manager->set_moderation_cache($content_hash, $moderation_data);

        return true;
    }

    /**
     * Update session statistics
     */
    private function update_session_stats($session_id, $tokens_used) {
        $this->db->query($this->db->prepare(
            "UPDATE {$this->get_table_name('sessions')}
            SET message_count = message_count + 2,
                token_usage = token_usage + %d,
                last_activity = %s
            WHERE id = %d
        ", $tokens_used, current_time('mysql'), $session_id));
    }

    /**
     * Delete a message with cache invalidation
     */
    public function delete($message_id, $user_id) {
        // Verify ownership through session
        $message = $this->get($message_id, $user_id);
        if (!$message) {
            throw new \Exception('Message not found or access denied');
        }

        $session_id = $message['session_id'];

        $result = $this->db->delete(
            $this->get_table_name('messages'),
            ['id' => $message_id],
            ['%d']
        );

        if ($result !== false) {
            $this->audit_logger->log($user_id, 'message_delete', 'chat_message', $message_id, [
                'session_id' => $session_id
            ]);

            // Invalidate caches related to this message and session
            $this->invalidate_message_caches($message_id, $session_id);
        }

        return $result !== false;
    }

    /**
     * Invalidate caches when a message is created, updated, or deleted
     */
    private function invalidate_message_caches($message_id, $session_id) {
        // Invalidate message-specific caches
        $this->cache_manager->delete("chat_messages:{$session_id}:*");

        // Invalidate conversation context cache
        $this->cache_manager->delete("conversation_context:{$session_id}:*");

        // Invalidate moderation cache if it exists
        $this->cache_manager->delete("moderation:{$message_id}");

        error_log("SMO Social: Invalidated caches for message {$message_id} in session {$session_id}");
    }

    /**
     * Search messages across sessions
     */
    public function search_messages($user_id, $query, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $search_term = '%' . $this->db->esc_like($query) . '%';

        $messages = $this->db->get_results($this->db->prepare(
            "SELECT m.*, s.session_name, s.id as session_id
            FROM {$this->get_table_name('messages')} m
            JOIN {$this->get_table_name('sessions')} s ON m.session_id = s.id
            WHERE s.user_id = %d
            AND m.content LIKE %s
            ORDER BY m.created_at DESC
            LIMIT %d OFFSET %d
        ", $user_id, $search_term, $limit, $offset), ARRAY_A);

        // Get total count
        $total = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*)
            FROM {$this->get_table_name('messages')} m
            JOIN {$this->get_table_name('sessions')} s ON m.session_id = s.id
            WHERE s.user_id = %d
            AND m.content LIKE %s
        ", $user_id, $search_term));

        // Parse JSON fields
        foreach ($messages as &$message) {
            $message['metadata'] = json_decode($message['metadata'], true);
        }

        return [
            'messages' => $messages,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get client information for logging
     */
    private function get_client_info() {
        return [
            'ip' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'timestamp' => time()
        ];
    }

    /**
     * Test cache performance and consistency
     */
    public function test_cache_performance() {
        $test_results = [
            'cache_hit_rate' => 0,
            'query_reduction' => 0,
            'cache_consistency' => true,
            'performance_improvement' => 0,
            'errors' => []
        ];

        try {
            // Test chat message caching
            $session_id = 1; // Example session ID
            $user_id = 1; // Example user ID

            // First call - should miss cache
            $start_time = microtime(true);
            $result1 = $this->get_session_messages($session_id, $user_id, 1, 10);
            $first_call_time = microtime(true) - $start_time;

            // Second call - should hit cache
            $start_time = microtime(true);
            $result2 = $this->get_session_messages($session_id, $user_id, 1, 10);
            $second_call_time = microtime(true) - $start_time;

            // Verify cache consistency
            $test_results['cache_consistency'] = ($result1 === $result2);

            // Calculate performance improvement
            if ($first_call_time > 0) {
                $test_results['performance_improvement'] = (($first_call_time - $second_call_time) / $first_call_time) * 100;
            }

            // Test provider name caching
            $provider_id = 1; // Example provider ID
            $provider_name1 = $this->get_provider_name($provider_id);
            $provider_name2 = $this->get_provider_name($provider_id);
            $test_results['provider_cache_consistency'] = ($provider_name1 === $provider_name2);

            // Test conversation context caching
            $context1 = $this->get_conversation_context($session_id);
            $context2 = $this->get_conversation_context($session_id);
            $test_results['context_cache_consistency'] = ($context1 === $context2);

            // Get cache statistics
            $cache_stats = $this->cache_manager->get_stats();
            $test_results['cache_hit_rate'] = $cache_stats['hit_rate'] ?? 0;

            error_log("SMO Social Cache Test Results: " . print_r($test_results, true));

        } catch (\Exception $e) {
            $test_results['errors'][] = $e->getMessage();
            error_log("SMO Social Cache Test Error: " . $e->getMessage());
        }

        return $test_results;
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

    /**
     * Get table name helper
     */
    private function get_table_name($type) {
        $tables = DatabaseSchema::get_table_names();
        return $tables[$type] ?? '';
    }
}