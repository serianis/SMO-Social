<?php
namespace SMO_Social\Security;

/**
 * Enhanced CSRF Protection Manager
 * 
 * Provides comprehensive CSRF protection with token validation and rate limiting.
 * 
 * @since 2.1.0
 */
class CSRFManager
{
    private const TOKEN_LIFETIME = 3600; // 1 hour
    private const MAX_TOKENS_PER_USER = 10;
    private const TOKEN_PREFIX = 'smo_csrf_';
    
    /**
     * Generate a new CSRF token for an action
     *
     * @param string $action The action the token is for
     * @param string|null $user_identifier Optional user identifier
     * @return string CSRF token
     */
    public static function generateToken(string $action, ?string $user_identifier = null): string
    {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + self::TOKEN_LIFETIME;
        
        // Create token data
        $token_data = [
            'token' => \hash('sha256', $token),
            'expiry' => $expiry,
            'action' => $action,
            'ip' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'user_id' => $user_identifier ?? self::getCurrentUserId()
        ];
        
        // Store token in session
        $session_key = self::getTokenSessionKey($action);
        $tokens = self::getStoredTokens();
        
        // Limit number of tokens per user
        if (count($tokens) >= self::MAX_TOKENS_PER_USER) {
            $tokens = array_slice($tokens, -self::MAX_TOKENS_PER_USER + 1);
        }
        
        $tokens[$session_key] = $token_data;
        self::storeTokens($tokens);
        
        return $token;
    }
    
    /**
     * Validate a CSRF token
     *
     * @param string $token The token to validate
     * @param string $action The action the token is for
     * @param bool $consume Whether to consume the token after validation
     * @return bool True if valid, false otherwise
     */
    public static function validateToken(string $token, string $action, bool $consume = true): bool
    {
        if (empty($token) || empty($action)) {
            return false;
        }
        
        $session_key = self::getTokenSessionKey($action);
        $tokens = self::getStoredTokens();
        
        if (!isset($tokens[$session_key])) {
            return false;
        }
        
        $token_data = $tokens[$session_key];
        
        // Check expiration
        if (time() > $token_data['expiry']) {
            if ($consume) {
                unset($tokens[$session_key]);
                self::storeTokens($tokens);
            }
            return false;
        }
        
        // Check IP match
        if ($token_data['ip'] !== self::getClientIP()) {
            return false;
        }
        
        // Check User Agent match (for additional security)
        if ($token_data['user_agent'] !== self::getUserAgent()) {
            return false;
        }
        
        // Verify token
        $hashed_token = \hash('sha256', $token);
        if (!hash_equals($token_data['token'], $hashed_token)) {
            return false;
        }
        
        // Remove token after use if consuming
        if ($consume) {
            unset($tokens[$session_key]);
            self::storeTokens($tokens);
        }
        
        return true;
    }
    

    
    /**
     * Generate nonce for WordPress integration
     *
     * @param string $action The action name
     * @return string Nonce value
     */
    public static function createNonce(string $action): string
    {
        return self::generateToken($action);
    }
    
    /**
     * Verify nonce for WordPress integration
     *
     * @param string $nonce The nonce value
     * @param string $action The action name
     * @return int 1 if valid, false if invalid
     */
    public static function verifyNonce(string $nonce, string $action): int|bool
    {
        return self::validateToken($nonce, $action) ? 1 : false;
    }
    
    /**
     * Clean up expired tokens
     *
     * @return void
     */
    public static function cleanupExpiredTokens(): void
    {
        $tokens = self::getStoredTokens();
        $current_time = time();
        $cleaned_tokens = [];
        
        foreach ($tokens as $key => $token_data) {
            if ($token_data['expiry'] > $current_time) {
                $cleaned_tokens[$key] = $token_data;
            }
        }
        
        if (count($cleaned_tokens) !== count($tokens)) {
            self::storeTokens($cleaned_tokens);
        }
    }
    
    /**
     * Get client IP address with proxy support
     *
     * @return string Client IP address
     */
    private static function getClientIP(): string
    {
        $ip_keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_TRUE_CLIENT_IP'
        ];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get user agent string
     *
     * @return string User agent
     */
    private static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * Get current user ID
     *
     * @return string User identifier
     */
    private static function getCurrentUserId(): string
    {
        if (function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
            return $user_id ? 'user_' . $user_id : 'guest';
        }
        
        return session_id() ?? 'unknown';
    }
    
    /**
     * Get session key for token storage
     *
     * @param string $action The action name
     * @return string Session key
     */
    private static function getTokenSessionKey(string $action): string
    {
        return self::TOKEN_PREFIX . \hash('sha256', $action);
    }
    
    /**
     * Get stored tokens from session
     *
     * @return array Stored tokens
     */
    private static function getStoredTokens(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
        
        return $_SESSION['smo_csrf_tokens'] ?? [];
    }
    
    /**
     * Store tokens in session
     *
     * @param array $tokens Tokens to store
     * @return void
     */
    private static function storeTokens(array $tokens): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
        
        $_SESSION['smo_csrf_tokens'] = $tokens;
    }
    
    /**
     * Initialize CSRF protection
     *
     * @return void
     */
    public static function init(): void
    {
        // Clean up expired tokens periodically
        if (rand(1, 100) <= 10) { // 10% chance to clean up
            self::cleanupExpiredTokens();
        }
        
        // Add cleanup hook only in WordPress mode
        if (function_exists('add_action')) {
            add_action('wp_cleanup_csrf_tokens', [__CLASS__, 'cleanupExpiredTokens']);
            if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
                if (!wp_next_scheduled('wp_cleanup_csrf_tokens')) {
                    wp_schedule_event(time(), 'hourly', 'wp_cleanup_csrf_tokens');
                }
            }
        }
    }
    
    /**
     * Generate form token field for HTML forms
     *
     * @param string $action The action the token is for
     * @return string HTML input fields
     */
    public static function getFormTokenField(string $action): string
    {
        $token = self::generateToken($action);
        
        // Use WordPress esc_attr if available, otherwise use htmlspecialchars
        $escaper = function_exists('esc_attr') ? 'esc_attr' : function($value) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        };
        
        return '<input type="hidden" name="smo_csrf_token" value="' . $escaper($token) . '">' .
               '<input type="hidden" name="smo_csrf_action" value="' . $escaper($action) . '">';
    }
    
    /**
     * Check request for CSRF token (POST forms)
     *
     * @param string $action The action to validate
     * @return array Result with 'valid' boolean and 'error' message if invalid
     */
    public static function checkRequest(string $action): array
    {
        // Log CSRF validation attempt
        SecurityAuditLogger::logCSRFValidation($action, false, ['method' => 'POST']);
        
        if (empty($_POST['smo_csrf_token']) || empty($_POST['smo_csrf_action'])) {
            $error = 'Missing CSRF token';
            SecurityAuditLogger::logCSRFValidation($action, false, [
                'method' => 'POST',
                'error' => $error
            ]);
            return [
                'valid' => false,
                'error' => $error
            ];
        }
        
        $token = $_POST['smo_csrf_token'];
        $token_action = $_POST['smo_csrf_action'];
        
        // Verify action matches
        if ($token_action !== $action) {
            $error = 'CSRF token action mismatch';
            SecurityAuditLogger::logCSRFValidation($action, false, [
                'method' => 'POST',
                'error' => $error,
                'expected_action' => $action,
                'provided_action' => $token_action
            ]);
            return [
                'valid' => false,
                'error' => $error
            ];
        }
        
        $is_valid = self::validateToken($token, $action);
        
        SecurityAuditLogger::logCSRFValidation($action, $is_valid, [
            'method' => 'POST',
            'token_provided' => !empty($token)
        ]);
        
        if (!$is_valid) {
            return [
                'valid' => false,
                'error' => 'Invalid or expired CSRF token'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check AJAX request for CSRF token
     *
     * @param string $action The action to validate
     * @return array Result with 'valid' boolean and 'error' message if invalid
     */
    public static function checkAjaxRequest(string $action): array
    {
        // Log CSRF validation attempt
        SecurityAuditLogger::logCSRFValidation($action, false, ['method' => 'AJAX']);
        
        $headers = getallheaders();
        
        if (empty($headers['X-CSRF-Token'])) {
            $error = 'Missing CSRF token header';
            SecurityAuditLogger::logCSRFValidation($action, false, [
                'method' => 'AJAX',
                'error' => $error
            ]);
            return [
                'valid' => false,
                'error' => $error
            ];
        }
        
        $token = $headers['X-CSRF-Token'];
        $token_action = $headers['X-CSRF-Action'] ?? $action;
        
        // Verify action matches
        if ($token_action !== $action) {
            $error = 'CSRF token action mismatch';
            SecurityAuditLogger::logCSRFValidation($action, false, [
                'method' => 'AJAX',
                'error' => $error,
                'expected_action' => $action,
                'provided_action' => $token_action
            ]);
            return [
                'valid' => false,
                'error' => $error
            ];
        }
        
        $is_valid = self::validateToken($token, $action);
        
        SecurityAuditLogger::logCSRFValidation($action, $is_valid, [
            'method' => 'AJAX',
            'token_provided' => !empty($token)
        ]);
        
        if (!$is_valid) {
            return [
                'valid' => false,
                'error' => 'Invalid or expired CSRF token'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Get AJAX headers for client-side requests
     *
     * @param string $action The action the headers are for
     * @return array Headers to include in AJAX request
     */
    public static function getAjaxHeaders(string $action): array
    {
        $token = self::generateToken($action);
        
        return [
            'X-CSRF-Token' => $token,
            'X-CSRF-Action' => $action
        ];
    }
    
    /**
     * Prevent duplicate form submissions
     *
     * @param string $form_id Unique form identifier
     * @param int $timeout Timeout in seconds
     * @return array Result with 'allowed' boolean and 'error' message if not allowed
     */
    public static function preventDuplicateSubmission(string $form_id, int $timeout = 30): array
    {
        $transient_key = 'smo_form_submission_' . hash('sha256', $form_id);
        
        // Use WordPress transients if available, otherwise use session storage
        if (function_exists('get_transient') && function_exists('set_transient')) {
            if (get_transient($transient_key)) {
                $error = 'Form already submitted recently';
                SecurityAuditLogger::logInputValidation($form_id, false, [$error], [
                    'duplicate_submission' => true
                ]);
                return [
                    'allowed' => false,
                    'error' => $error
                ];
            }
            
            set_transient($transient_key, time(), $timeout);
        } else {
            // Fallback to session storage for standalone mode
            if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
                session_start();
            }
            
            $session_key = 'smo_form_submissions';
            $submissions = $_SESSION[$session_key] ?? [];
            
            if (isset($submissions[$form_id]) && (time() - $submissions[$form_id]) < $timeout) {
                $error = 'Form already submitted recently';
                SecurityAuditLogger::logInputValidation($form_id, false, [$error], [
                    'duplicate_submission' => true
                ]);
                return [
                    'allowed' => false,
                    'error' => $error
                ];
            }
            
            $submissions[$form_id] = time();
            $_SESSION[$session_key] = $submissions;
        }
        
        return ['allowed' => true];
    }
}
