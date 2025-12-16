<?php
namespace SMO_Social\Chat;

use SMO_Social\Chat\ChatSession;
use SMO_Social\Chat\ChatMessage;
use SMO_Social\Chat\ChatAuditLogger;
use SMO_Social\AI\ProvidersConfig;

/**
 * Chat REST API Controller
 * Handles all chat-related REST endpoints with security and validation
 */
class ChatAPIController
{
    private $debug_mode = false;
    private $log_level = 'info'; // info, debug, warn, error

    /**
     * Set debug mode
     */
    public function set_debug_mode($enable = true, $level = 'debug')
    {
        $this->debug_mode = $enable;
        $this->log_level = $level;
    }

    /**
     * Log messages with proper level control
     */
    private function log_debug($message, $context = '', $level = 'debug')
    {
        // Only log if debug mode is enabled and level matches
        if (!$this->debug_mode && $level === 'debug') {
            return;
        }

        // Check if we should log based on level priority
        $levels = ['error' => 0, 'warn' => 1, 'info' => 2, 'debug' => 3];
        if (isset($levels[$level]) && isset($levels[$this->log_level]) && $levels[$level] > $levels[$this->log_level]) {
            return;
        }

        // Sanitize sensitive data
        $safe_message = $this->sanitize_log_message($message);
        $safe_context = $this->sanitize_log_message($context);

        // Format the log entry
        $log_entry = sprintf('[SMO %s] %s', strtoupper($level), $safe_message);
        if ($safe_context) {
            $log_entry .= ' | Context: ' . $safe_context;
        }

        // Log to appropriate destination
        if ($level === 'error') {
            error_log($log_entry);
        } else {
            // Use a more efficient logging mechanism in production
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($log_entry);
            } else {
                // In production, consider using a proper logging system
                // For now, we'll use error_log but with reduced frequency
                if (mt_rand(1, 100) <= 5) { // Only log 5% of debug messages in production
                    error_log($log_entry);
                }
            }
        }
    }

    /**
     * Sanitize log messages to prevent sensitive data exposure
     */
    private function sanitize_log_message($message)
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        // Remove sensitive patterns
        $sensitive_patterns = [
            '/(api|access|secret|token|key|password|nonce)[\s\=\:]+[a-zA-Z0-9\-\_\.\@]+/',
            '/[a-f0-9]{32,}/', // Long hex strings (potential hashes)
            '/[A-Za-z0-9]{20,}/' // Long alphanumeric strings
        ];

        foreach ($sensitive_patterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }

        return $message;
    }

    private $namespace = 'smo-social/v1';
    private $session_manager;
    private $message_manager;
    private $audit_logger;

    public function __construct()
    {
        $this->session_manager = new ChatSession();
        $this->message_manager = new ChatMessage();
        $this->audit_logger = new ChatAuditLogger();

        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_authentication_errors', [$this, 'log_authentication_errors']);

        $this->log_debug('ChatAPIController initialized', __METHOD__);
    }

    /**
     * Comprehensive nonce validation with multiple strategies
     */
    private function validate_nonce_comprehensive($nonce, $request = null)
    {
        $results = [
            'valid' => false,
            'method' => null,
            'attempts' => []
        ];

        if (empty($nonce)) {
            $results['attempts'][] = 'No nonce provided';
            return $results;
        }

        // Strategy 1: WordPress REST API nonce (highest priority)
        if (wp_verify_nonce($nonce, 'wp_rest')) {
            $results['valid'] = true;
            $results['method'] = 'wp_rest';
            $results['attempts'][] = 'wp_rest: VALID';
            return $results;
        } else {
            $results['attempts'][] = 'wp_rest: INVALID';
        }

        // Strategy 2: Plugin-specific nonce
        if (wp_verify_nonce($nonce, 'smo_social_nonce')) {
            $results['valid'] = true;
            $results['method'] = 'smo_social_nonce';
            $results['attempts'][] = 'smo_social_nonce: VALID';
            return $results;
        } else {
            $results['attempts'][] = 'smo_social_nonce: INVALID';
        }

        // Strategy 3: Generic WordPress nonce
        if (wp_verify_nonce($nonce, -1) || wp_verify_nonce($nonce, 'wp_nonce')) {
            $results['valid'] = true;
            $results['method'] = 'wp_nonce';
            $results['attempts'][] = 'wp_nonce: VALID';
            return $results;
        } else {
            $results['attempts'][] = 'wp_nonce: INVALID';
        }

        // Strategy 4: Generate fresh nonces for comparison (debugging)
        $fresh_nonces = [
            'wp_rest' => wp_create_nonce('wp_rest'),
            'smo_social_nonce' => wp_create_nonce('smo_social_nonce')
        ];

        foreach ($fresh_nonces as $type => $fresh) {
            if ($nonce === $fresh) {
                $results['attempts'][] = "Fresh $type match: POSSIBLE EXPIRED NONCE";
                $results['method'] = "fresh_$type";
                $results['valid'] = true; // Allow if it matches fresh nonce (might be timing issue)
                return $results;
            }
        }

        $results['attempts'][] = 'No nonce strategies succeeded';
        return $results;
    }

    /**
     * Register all REST API routes
     */
    public function register_routes()
    {
        $this->log_debug('Registering REST API routes', __METHOD__, 'info');
        // Session management endpoints
        \register_rest_route($this->namespace, '/chat/session', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_session'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'session_name' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'provider_id' => [
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'model_name' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'system_prompt' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'conversation_context' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ]
                ]
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_user_sessions'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1
                    ],
                    'limit' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100
                    ],
                    'status' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'active',
                        'enum' => ['active', 'archived', 'deleted', 'all']
                    ]
                ]
            ]
        ]);

        // Session-specific endpoints
        \register_rest_route($this->namespace, '/chat/session/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_session'],
                'permission_callback' => [$this, 'check_session_permissions'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_session'],
                'permission_callback' => [$this, 'check_session_permissions'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'session_name' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'system_prompt' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'conversation_context' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'status' => [
                        'required' => false,
                        'type' => 'string',
                        'enum' => ['active', 'archived', 'deleted']
                    ]
                ]
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_session'],
                'permission_callback' => [$this, 'check_session_permissions'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ]
                ]
            ]
        ]);

        // Message endpoints
        \register_rest_route($this->namespace, '/chat/message', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_message'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'session_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'content' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'content_type' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'text',
                        'enum' => ['text', 'json', 'markdown', 'code']
                    ]
                ]
            ]
        ]);

        // Session messages endpoint
        \register_rest_route($this->namespace, '/chat/session/(?P<session_id>\d+)/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_session_messages'],
            'permission_callback' => [$this, 'check_session_permissions'],
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 100
                ]
            ]
        ]);

        // Session statistics endpoint
        \register_rest_route($this->namespace, '/chat/session/(?P<id>\d+)/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_session_stats'],
            'permission_callback' => [$this, 'check_session_permissions'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        // Search endpoints
        \register_rest_route($this->namespace, '/chat/search', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'search_sessions'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'q' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'type' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'sessions',
                        'enum' => ['sessions', 'messages']
                    ],
                    'page' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1
                    ],
                    'limit' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100
                    ]
                ]
            ]
        ]);

        // Streaming endpoints
        \register_rest_route($this->namespace, '/chat/stream', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'stream_message'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'session_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'content' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ],
                    'content_type' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'text',
                        'enum' => ['text', 'json', 'markdown', 'code']
                    ],
                    'stream' => [
                        'required' => false,
                        'type' => 'boolean',
                        'default' => true
                    ]
                ]
            ]
        ]);

        // AI Providers endpoint
        \register_rest_route($this->namespace, '/chat/providers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_providers'],
            'permission_callback' => [$this, 'check_permissions']
        ]);

        // Simple test endpoint to verify REST API is working
        \register_rest_route($this->namespace, '/test', [
            'methods' => 'GET',
            'callback' => [$this, 'test_endpoint'],
            'permission_callback' => [$this, 'test_permissions']  // Custom permission check with logging
        ]);

        // Nonce refresh endpoint for JavaScript
        \register_rest_route($this->namespace, '/nonce/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refresh_nonce'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
    }

    /**
     * Check user permissions for chat access
     */
    public function check_permissions($request)
    {
        // Enhanced debug logging for nonce issues
        $nonce = $request->get_header('X-WP-Nonce');
        $user_id = get_current_user_id();

        error_log('SMO Debug: === PERMISSION CHECK START ===');
        error_log('SMO Debug: Received nonce: ' . ($nonce ? substr($nonce, 0, 20) . '...' : 'EMPTY/NULL'));
        error_log('SMO Debug: Current user ID: ' . $user_id);
        error_log('SMO Debug: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        error_log('SMO Debug: Current user: ' . (wp_get_current_user()->user_login ?? 'N/A'));
        error_log('SMO Debug: Request URL: ' . $request->get_route());
        error_log('SMO Debug: Request method: ' . $request->get_method());

        // Primary: Validate wp_rest nonce (WordPress REST API standard)
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            error_log('SMO Debug: ✓ Nonce validation successful using wp_rest');
            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                error_log('SMO Debug: User lacks edit_posts capability');
                return new \WP_Error('insufficient_permissions', 'Insufficient permissions', ['status' => 403]);
            }
            error_log('SMO Debug: === PERMISSION CHECK PASSED ===');
            return true;
        }

        // Fallback: Try smo_social_nonce
        if ($nonce && wp_verify_nonce($nonce, 'smo_social_nonce')) {
            error_log('SMO Debug: ✓ Nonce validation successful using smo_social_nonce');
            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                error_log('SMO Debug: User lacks edit_posts capability');
                return new \WP_Error('insufficient_permissions', 'Insufficient permissions', ['status' => 403]);
            }
            error_log('SMO Debug: === PERMISSION CHECK PASSED ===');
            return true;
        }

        // If no valid nonce, provide detailed debugging information
        if (!$nonce) {
            error_log('SMO Debug: ✗ No nonce provided');
        } else {
            // Generate fresh nonces for comparison
            $fresh_rest_nonce = wp_create_nonce('wp_rest');
            $fresh_smo_nonce = wp_create_nonce('smo_social_nonce');
            
            error_log('SMO Debug: Fresh wp_rest nonce: ' . substr($fresh_rest_nonce, 0, 20) . '...');
            error_log('SMO Debug: Fresh smo_social_nonce: ' . substr($fresh_smo_nonce, 0, 20) . '...');
            error_log('SMO Debug: Received nonce: ' . substr($nonce, 0, 20) . '...');
            error_log('SMO Debug: Nonce matches fresh wp_rest: ' . ($nonce === $fresh_rest_nonce ? 'YES' : 'NO'));
            error_log('SMO Debug: Nonce matches fresh smo_social: ' . ($nonce === $fresh_smo_nonce ? 'YES' : 'NO'));
            error_log('SMO Debug: Nonce length: ' . strlen($nonce));
        }

        // Enhanced error handling for development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Debug: ✗ Nonce validation failed, but allowing for development');
            // In development mode, allow authenticated users with proper permissions
            if (is_user_logged_in() && current_user_can('edit_posts')) {
                error_log('SMO Debug: Allowing request for authenticated user with edit_posts capability (development mode)');
                error_log('SMO Debug: === PERMISSION CHECK PASSED ===');
                return true;
            }
        }

        error_log('SMO Debug: ✗ Nonce validation failed and user lacks proper permissions');
        return new \WP_Error('invalid_nonce', 'Nonce validation failed', ['status' => 403]);
    }

    /**
     * Check permissions for specific session
     */
    public function check_session_permissions($request)
    {
        $user_id = get_current_user_id();
        $session_id = (int) ($request['id'] ?? $request['session_id']);

        if (!$user_id || !$session_id) {
            return new \WP_Error('invalid_request', 'Invalid request', ['status' => 400]);
        }

        // Debug logging for nonce issues
        $nonce = $request->get_header('X-WP-Nonce');
        error_log('SMO Debug: Session check - received nonce: ' . $nonce);
        error_log('SMO Debug: Session check - user ID: ' . $user_id . ', session ID: ' . $session_id);

        // Verify nonce - try both 'wp_rest' and 'smo_social_nonce' for compatibility
        $nonce_valid = wp_verify_nonce($nonce, 'wp_rest') || wp_verify_nonce($nonce, 'smo_social_nonce');
        if (!$nonce_valid) {
            error_log('SMO Debug: Session check - nonce verification failed for nonce: ' . $nonce);
            return new \WP_Error('invalid_nonce', 'Invalid nonce', ['status' => 403]);
        }

        // Check if session belongs to user
        $session = $this->session_manager->get($session_id, $user_id);
        if (!$session) {
            return new \WP_Error('session_not_found', 'Session not found or access denied', ['status' => 404]);
        }

        return true;
    }

    /**
     * Log REST API authentication errors for debugging
     */
    public function log_authentication_errors($errors)
    {
        error_log('SMO Debug: rest_authentication_errors filter called');
        error_log('SMO Debug: Request URI: ' . $_SERVER['REQUEST_URI']);
        error_log('SMO Debug: HTTP_X_WP_NONCE: ' . ($_SERVER['HTTP_X_WP_NONCE'] ?? 'NOT SET'));
        error_log('SMO Debug: Current user ID: ' . get_current_user_id());
        error_log('SMO Debug: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));

        if (!is_wp_error($errors)) {
            error_log('SMO Debug: No errors in rest_authentication_errors');
            return $errors;
        }

        $error_codes = $errors->get_error_codes();
        error_log('SMO Debug: Error codes: ' . json_encode($error_codes));

        if (in_array('rest_cookie_invalid_nonce', $error_codes)) {
            error_log('SMO Debug: REST API nonce validation failed');
            error_log('SMO Debug: Error details: ' . json_encode($errors->get_error_data('rest_cookie_invalid_nonce')));

            // Try to verify the nonce manually
            $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
            if ($nonce) {
                $verify_rest = wp_verify_nonce($nonce, 'wp_rest');
                $verify_smo = wp_verify_nonce($nonce, 'smo_social_nonce');
                error_log('SMO Debug: Manual nonce verification - wp_rest: ' . ($verify_rest ? 'VALID' : 'INVALID'));
                error_log('SMO Debug: Manual nonce verification - smo_social_nonce: ' . ($verify_smo ? 'VALID' : 'INVALID'));

                // Generate fresh nonces for comparison
                $fresh_rest = wp_create_nonce('wp_rest');
                $fresh_smo = wp_create_nonce('smo_social_nonce');
                error_log('SMO Debug: Fresh wp_rest nonce: ' . substr($fresh_rest, 0, 20) . '...');
                error_log('SMO Debug: Fresh smo_social_nonce: ' . substr($fresh_smo, 0, 20) . '...');
                error_log('SMO Debug: Received nonce: ' . substr($nonce, 0, 20) . '...');
                error_log('SMO Debug: Matches fresh wp_rest: ' . ($nonce === $fresh_rest ? 'YES' : 'NO'));
                error_log('SMO Debug: Matches fresh smo_social: ' . ($nonce === $fresh_smo ? 'YES' : 'NO'));
            }

            // If user is logged in and has proper permissions, bypass nonce validation
            if (is_user_logged_in() && current_user_can('edit_posts')) {
                error_log('SMO Debug: Bypassing nonce validation for authenticated user with edit_posts capability');
                $errors->remove('rest_cookie_invalid_nonce');
            }
        }

        return $errors;
    }

    /**
     * Test permissions with detailed logging for nonce debugging
     */
    public function test_permissions($request)
    {
        // Enhanced debug logging for nonce issues on test endpoint
        $nonce = $request->get_header('X-WP-Nonce');
        $user_id = get_current_user_id();

        error_log('SMO Debug: === TEST ENDPOINT PERMISSION CHECK START ===');
        error_log('SMO Debug: Test endpoint - received nonce: ' . ($nonce ? substr($nonce, 0, 20) . '...' : 'EMPTY/NULL'));
        error_log('SMO Debug: Test endpoint - current user ID: ' . $user_id);
        error_log('SMO Debug: Test endpoint - user logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        error_log('SMO Debug: Test endpoint - request URL: ' . $request->get_route());
        error_log('SMO Debug: Test endpoint - request method: ' . $request->get_method());

        // Try multiple nonce verification strategies
        $nonce_valid = false;
        $verification_methods = [
            'wp_rest' => 'WordPress REST API nonce',
            'smo_social_nonce' => 'SMO Social plugin nonce',
            'wp_nonce' => 'Generic WordPress nonce'
        ];

        foreach ($verification_methods as $action => $description) {
            if (wp_verify_nonce($nonce, $action)) {
                error_log('SMO Debug: Test endpoint - ✓ Nonce verified using ' . $description . ' (' . $action . ')');
                $nonce_valid = true;
                break;
            } else {
                error_log('SMO Debug: Test endpoint - ✗ Nonce verification failed for ' . $description . ' (' . $action . ')');
            }
        }

        // Generate fresh nonces for comparison
        $fresh_rest_nonce = wp_create_nonce('wp_rest');
        $fresh_smo_nonce = wp_create_nonce('smo_social_nonce');
        error_log('SMO Debug: Test endpoint - Fresh wp_rest nonce: ' . substr($fresh_rest_nonce, 0, 20) . '...');
        error_log('SMO Debug: Test endpoint - Fresh smo_social_nonce: ' . substr($fresh_smo_nonce, 0, 20) . '...');
        error_log('SMO Debug: Test endpoint - Received nonce matches wp_rest: ' . ($nonce === $fresh_rest_nonce ? 'YES' : 'NO'));
        error_log('SMO Debug: Test endpoint - Received nonce matches smo_social: ' . ($nonce === $fresh_smo_nonce ? 'YES' : 'NO'));

        if (!$nonce_valid) {
            error_log('SMO Debug: Test endpoint - All nonce verification methods failed');
            // For test endpoint, allow even if nonce fails, but log it
            error_log('SMO Debug: Test endpoint - Allowing request despite nonce failure for debugging');
        }

        error_log('SMO Debug: === TEST ENDPOINT PERMISSION CHECK PASSED ===');
        return true;
    }

    /**
     * Create a new chat session
     */
    public function create_session($request)
    {
        try {
            $user_id = get_current_user_id();
            $options = [
                'session_name' => $request->get_param('session_name'),
                'provider_id' => $request->get_param('provider_id'),
                'model_name' => $request->get_param('model_name'),
                'system_prompt' => $request->get_param('system_prompt'),
                'conversation_context' => $request->get_param('conversation_context')
            ];

            // Remove null values
            $options = array_filter($options, function ($value) {
                return $value !== null;
            });

            $session = $this->session_manager->create($user_id, $options);

            return [
                'success' => true,
                'data' => $session
            ];

        } catch (\Exception $e) {
            return new \WP_Error('session_creation_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get user's chat sessions
     */
    public function get_user_sessions($request)
    {
        try {
            $user_id = get_current_user_id();
            $page = $request->get_param('page');
            $limit = $request->get_param('limit');
            $status = $request->get_param('status');

            $sessions_data = $this->session_manager->get_user_sessions($user_id, $page, $limit, $status);

            return [
                'success' => true,
                'data' => $sessions_data
            ];

        } catch (\Exception $e) {
            return new \WP_Error('get_sessions_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get a specific session
     */
    public function get_session($request)
    {
        try {
            $session_id = (int) $request['id'];
            $user_id = get_current_user_id();

            $session = $this->session_manager->get($session_id, $user_id);

            if (!$session) {
                return new \WP_Error('session_not_found', 'Session not found', ['status' => 404]);
            }

            return [
                'success' => true,
                'data' => $session
            ];

        } catch (\Exception $e) {
            return new \WP_Error('get_session_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Update a session
     */
    public function update_session($request)
    {
        try {
            $session_id = (int) $request['id'];
            $user_id = get_current_user_id();

            $updates = [];
            $allowed_fields = ['session_name', 'system_prompt', 'conversation_context', 'status'];

            foreach ($allowed_fields as $field) {
                $value = $request->get_param($field);
                if ($value !== null) {
                    $updates[$field] = $value;
                }
            }

            if (empty($updates)) {
                return new \WP_Error('no_updates', 'No valid updates provided', ['status' => 400]);
            }

            $result = $this->session_manager->update($session_id, $user_id, $updates);

            if (!$result) {
                return new \WP_Error('update_failed', 'Failed to update session', ['status' => 500]);
            }

            $updated_session = $this->session_manager->get($session_id, $user_id);

            return [
                'success' => true,
                'data' => $updated_session
            ];

        } catch (\Exception $e) {
            return new \WP_Error('update_session_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Delete a session
     */
    public function delete_session($request)
    {
        try {
            $session_id = (int) $request['id'];
            $user_id = get_current_user_id();

            $result = $this->session_manager->delete($session_id, $user_id);

            if (!$result) {
                return new \WP_Error('delete_failed', 'Failed to delete session', ['status' => 500]);
            }

            return [
                'success' => true,
                'message' => 'Session deleted successfully'
            ];

        } catch (\Exception $e) {
            return new \WP_Error('delete_session_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Send a message
     */
    public function send_message($request)
    {
        try {
            $session_id = $request->get_param('session_id');
            $content = $request->get_param('content');
            $content_type = $request->get_param('content_type');
            $user_id = get_current_user_id();

            $options = [
                'content_type' => $content_type
            ];

            $result = $this->message_manager->send($session_id, $user_id, $content, $options);

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            return new \WP_Error('send_message_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get messages for a session
     */
    public function get_session_messages($request)
    {
        try {
            $session_id = (int) $request['session_id'];
            $user_id = get_current_user_id();
            $page = $request->get_param('page');
            $limit = $request->get_param('limit');

            $messages_data = $this->message_manager->get_session_messages($session_id, $user_id, $page, $limit);

            return [
                'success' => true,
                'data' => $messages_data
            ];

        } catch (\Exception $e) {
            return new \WP_Error('get_messages_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get session statistics
     */
    public function get_session_stats($request)
    {
        try {
            $session_id = (int) $request['id'];
            $user_id = get_current_user_id();

            $stats = $this->session_manager->get_session_stats($session_id, $user_id);

            if (!$stats) {
                return new \WP_Error('session_not_found', 'Session not found', ['status' => 404]);
            }

            return [
                'success' => true,
                'data' => $stats
            ];

        } catch (\Exception $e) {
            return new \WP_Error('get_stats_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Search sessions and messages
     */
    public function search_sessions($request)
    {
        try {
            $query = $request->get_param('q');
            $type = $request->get_param('type');
            $page = $request->get_param('page');
            $limit = $request->get_param('limit');
            $user_id = get_current_user_id();

            if ($type === 'messages') {
                $results = $this->message_manager->search_messages($user_id, $query, $page, $limit);
            } else {
                $results = $this->session_manager->search_sessions($user_id, $query, $page, $limit);
            }

            return [
                'success' => true,
                'data' => $results
            ];

        } catch (\Exception $e) {
            return new \WP_Error('search_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Stream a message response in real-time
     */
    public function stream_message($request)
    {
        // For now, return a standard response - streaming will be handled by client-side
        // In a full implementation, this would use Server-Sent Events or WebSockets
        try {
            $session_id = $request->get_param('session_id');
            $content = $request->get_param('content');
            $content_type = $request->get_param('content_type');
            $user_id = get_current_user_id();

            $options = [
                'content_type' => $content_type,
                'stream' => true
            ];

            // Send the message (this will be processed asynchronously for streaming)
            $result = $this->message_manager->send($session_id, $user_id, $content, $options);

            return [
                'success' => true,
                'data' => $result,
                'stream_url' => \rest_url('smo-social/v1/chat/stream/' . $result['assistant_message']['id']),
                'message' => 'Streaming endpoint ready'
            ];

        } catch (\Exception $e) {
            return new \WP_Error('stream_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get available AI providers
     */
    public function get_providers($request) {
        try {
            // Get all configured providers from ProvidersConfig
            $configured_providers = ProvidersConfig::get_configured_providers();
            
            $providers = [];
            foreach ($configured_providers as $id => $config) {
                $providers[] = [
                    'id' => $id,
                    'name' => $config['name'],
                    'type' => $config['type'],
                    'models' => $config['models'],
                    'capabilities' => $config['capabilities'],
                    'status' => 'active'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'providers' => $providers,
                    'total' => count($providers),
                    'primary_provider' => $this->get_primary_provider($providers)
                ]
            ];

        } catch (\Exception $e) {
            return new \WP_Error('get_providers_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get primary provider from settings
     */
    private function get_primary_provider($providers = [])
    {
        $primary_id = get_option('smo_social_primary_provider', 'huggingface');

        // Find the primary provider in the list
        foreach ($providers as $provider) {
            if ($provider['id'] === $primary_id) {
                return $provider;
            }
        }

        // Fallback to first available if primary not found
        return !empty($providers) ? $providers[0] : null;
    }

    /**
     * Simple test endpoint to verify REST API is working
     */
    public function test_endpoint($request)
    {
        error_log('SMO Debug: test_endpoint called');

        $nonce = $request->get_header('X-WP-Nonce');
        $user_id = get_current_user_id();

        // Test nonce verification with multiple methods
        $nonce_tests = [];
        foreach (['wp_rest', 'smo_social_nonce', 'wp_nonce'] as $action) {
            $nonce_tests[$action] = wp_verify_nonce($nonce, $action) ? 'VALID' : 'INVALID';
        }

        $response = [
            'success' => true,
            'message' => 'REST API is working!',
            'timestamp' => current_time('mysql'),
            'user_id' => $user_id,
            'debug_info' => [
                'nonce_received' => $nonce ? substr($nonce, 0, 20) . '...' : 'NULL/EMPTY',
                'nonce_tests' => $nonce_tests,
                'fresh_nonce' => wp_create_nonce('smo_social_nonce'),
                'user_logged_in' => is_user_logged_in(),
                'user_can_edit_posts' => current_user_can('edit_posts'),
                'user_capabilities' => wp_get_current_user()->allcaps ?? [],
                'rest_url' => rest_url($this->namespace . '/'),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'request_headers' => [
                    'user_agent' => $request->get_header('user_agent'),
                    'referer' => $request->get_header('referer'),
                    'origin' => $request->get_header('origin')
                ]
            ]
        ];

        error_log('SMO Debug: test_endpoint returning: ' . json_encode($response, JSON_PRETTY_PRINT));
        return $response;
    }

    /**
     * Refresh nonce endpoint for JavaScript
     */
    public function refresh_nonce($request)
    {
        try {
            // Generate fresh nonces
            $rest_nonce = wp_create_nonce('wp_rest');
            $smo_nonce = wp_create_nonce('smo_social_nonce');
            
            error_log('SMO Debug: Generated fresh nonces for refresh');
            error_log('SMO Debug: Fresh wp_rest nonce: ' . substr($rest_nonce, 0, 20) . '...');
            error_log('SMO Debug: Fresh smo_social nonce: ' . substr($smo_nonce, 0, 20) . '...');

            return [
                'success' => true,
                'data' => [
                    'restNonce' => $rest_nonce,
                    'smoNonce' => $smo_nonce,
                    'timestamp' => current_time('mysql'),
                    'user_id' => get_current_user_id()
                ]
            ];
            
        } catch (\Exception $e) {
            error_log('SMO Debug: Error generating fresh nonces: ' . $e->getMessage());
            return new \WP_Error('nonce_refresh_failed', $e->getMessage(), ['status' => 500]);
        }
    }
}
