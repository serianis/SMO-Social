<?php
namespace SMO_Social\Security;

/**
 * Security Helper
 * 
 * Unified security interface that integrates all security components for easy use
 * across the SMO-Social plugin.
 * 
 * @since 2.1.0
 */
class SecurityHelper
{
    /**
     * Process form data with comprehensive security checks
     *
     * @param array $form_data Raw form data
     * @param array $validation_rules Validation rules
     * @param string $form_name Form identifier for logging
     * @param string $csrf_action CSRF action name
     * @return array|WP_Error Processed data or error
     */
    public static function processSecureForm(array $form_data, array $validation_rules, string $form_name, string $csrf_action = '')
    {
        // CSRF validation
        if (!empty($csrf_action)) {
            $csrf_result = CSRFManager::checkRequest($csrf_action);
            if (!$csrf_result['valid']) {
                SecurityAuditLogger::logCSRFValidation($csrf_action, false, [
                    'form' => $form_name,
                    'error' => $csrf_result['error']
                ]);
                return new \WP_Error('csrf_failed', $csrf_result['error']);
            }
        }
        
        // Input validation and sanitization
        $sanitizer = new DataSanitizer();
        $validation_result = $sanitizer->validateInput($form_data, $validation_rules, $form_name);
        
        if (!$validation_result['valid']) {
            return new \WP_Error('validation_failed', 'Form validation failed', $validation_result['errors']);
        }
        
        // Prevent duplicate submissions
        $submission_check = CSRFManager::preventDuplicateSubmission($form_name . '_' . md5(serialize($form_data)));
        if (!$submission_check['allowed']) {
            return new \WP_Error('duplicate_submission', $submission_check['error']);
        }
        
        return [
            'success' => true,
            'data' => $validation_result['sanitized_data'],
            'form_name' => $form_name
        ];
    }
    
    /**
     * Process AJAX request with security checks
     *
     * @param array $request_data AJAX request data
     * @param array $validation_rules Validation rules
     * @param string $action AJAX action name
     * @return array|WP_Error Processed data or error
     */
    public static function processSecureAjax(array $request_data, array $validation_rules, string $action)
    {
        // CSRF validation for AJAX
        $csrf_result = CSRFManager::checkAjaxRequest($action);
        if (!$csrf_result['valid']) {
            SecurityAuditLogger::logCSRFValidation($action, false, [
                'method' => 'ajax',
                'error' => $csrf_result['error']
            ]);
            return new \WP_Error('csrf_failed', $csrf_result['error']);
        }
        
        // Input validation and sanitization
        $sanitizer = new DataSanitizer();
        $validation_result = $sanitizer->validateInput($request_data, $validation_rules, 'ajax_' . $action);
        
        if (!$validation_result['valid']) {
            return new \WP_Error('validation_failed', 'AJAX validation failed', $validation_result['errors']);
        }
        
        return [
            'success' => true,
            'data' => $validation_result['sanitized_data'],
            'action' => $action
        ];
    }
    
    /**
     * Generate secure form HTML with CSRF protection
     *
     * @param string $action Form action
     * @param string $method Form method
     * @param array $additional_fields Additional form fields
     * @return string Form HTML
     */
    public static function generateSecureForm(string $action, string $method = 'post', array $additional_fields = []): string
    {
        $csrf_token = CSRFManager::generateToken($action);
        
        $html = sprintf(
            '<form method="%s" action="%s" class="smo-secure-form">',
            esc_attr($method),
            esc_attr($action)
        );
        
        // Add CSRF token
        $html .= CSRFManager::getFormTokenField($action);
        
        // Add additional fields
        foreach ($additional_fields as $field) {
            $html .= $field;
        }
        
        $html .= '</form>';
        
        return $html;
    }
    
    /**
     * Validate and process file upload securely
     *
     * @param array $file_data File data from $_FILES
     * @param array $options Upload options
     * @return array|WP_Error Upload result or error
     */
    public static function processSecureFileUpload(array $file_data, array $options = [])
    {
        $uploader = new FileUploadSecurity();
        $validation = $uploader->validate_file_upload($file_data);
        
        if (!$validation['valid']) {
            SecurityAuditLogger::logFileUpload($file_data['name'] ?? 'unknown', false, $validation['error']);
            return new \WP_Error('file_upload_failed', $validation['error']);
        }
        
        // Scan for malicious content
        $scan_result = $uploader->scan_for_viruses($file_data['tmp_name']);
        if (!$scan_result['clean']) {
            SecurityAuditLogger::logFileUpload($file_data['name'], false, $scan_result['threat']);
            return new \WP_Error('malicious_file', 'File contains potentially malicious content');
        }
        
        // Move file securely
        $upload_result = $uploader->move_uploaded_file($file_data, $options['destination'] ?? '');
        
        if ($upload_result['success']) {
            SecurityAuditLogger::logFileUpload($file_data['name'], true);
            return $upload_result;
        } else {
            SecurityAuditLogger::logFileUpload($file_data['name'], false, $upload_result['error']);
            return new \WP_Error('upload_failed', $upload_result['error']);
        }
    }
    
    /**
     * Sanitize user input with enhanced security
     *
     * @param mixed $data Input data
     * @param string $type Sanitization type
     * @param array $options Sanitization options
     * @return mixed Sanitized data
     */
    public static function sanitizeSecure($data, string $type = 'string', array $options = [])
    {
        $sanitizer = new DataSanitizer();
        return $sanitizer->sanitizeWithLogging($data, $type, $options, 'general');
    }
    
    /**
     * Validate data using InputValidator
     *
     * @param mixed $data Data to validate
     * @param string $type Validation type
     * @param array $options Validation options
     * @return mixed Validated data or empty string if invalid
     */
    public static function validateSecure($data, string $type, array $options = [])
    {
        switch ($type) {
            case 'email':
                return InputValidator::validateEmail((string)$data);
            case 'url':
                return InputValidator::validateUrl((string)$data, $options);
            case 'filename':
                return InputValidator::validateFilename((string)$data, $options);
            case 'username':
                return InputValidator::validateUsername((string)$data, $options);
            case 'platform':
                return InputValidator::validatePlatform((string)$data) ? $data : '';
            case 'content':
                return InputValidator::sanitizeContent((string)$data, $options['max_length'] ?? 280);
            case 'hashtag':
                return InputValidator::validateHashtag((string)$data);
            default:
                return $data;
        }
    }
    
    /**
     * Check user permissions for an action
     *
     * @param string $capability Required capability
     * @param int $user_id User ID (optional, uses current user if not provided)
     * @return bool True if user has permission
     */
    public static function checkPermission(string $capability, int $user_id = 0): bool
    {
        if (!$user_id && function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
        }
        
        $has_permission = \user_can($user_id, $capability);
        
        SecurityAuditLogger::logPermission($capability, $has_permission ? 'granted' : 'denied', [
            'user_id' => $user_id
        ]);
        
        return $has_permission;
    }
    
    /**
     * Log security event
     *
     * @param string $event_type Type of event
     * @param string $level Log level
     * @param string $message Event message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function logSecurityEvent(string $event_type, string $level, string $message, array $context = []): bool
    {
        return SecurityAuditLogger::log($event_type, $level, $message, $context);
    }
    
    /**
     * Initialize all security components
     *
     * @return void
     */
    public static function init(): void
    {
        // Initialize CSRF protection
        CSRFManager::init();
        
        // Create security log table if needed
        SecurityAuditLogger::createLogTable();
        
        // Schedule log cleanup
        if (!wp_next_scheduled('smo_cleanup_security_logs')) {
            wp_schedule_event(time(), 'weekly', 'smo_cleanup_security_logs');
        }
        
        // Add cleanup hook
        add_action('smo_cleanup_security_logs', [__CLASS__, 'cleanupOldLogs']);
        
        // Add security headers
        add_action('send_headers', [__CLASS__, 'addSecurityHeaders']);
    }
    
    /**
     * Add security headers to HTTP responses
     *
     * @return void
     */
    public static function addSecurityHeaders(): void
    {
        if (!headers_sent()) {
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            
            // Prevent MIME type sniffing
            header('X-Content-Type-Options: nosniff');
            
            // Enable XSS protection
            header('X-XSS-Protection: 1; mode=block');
            
            // Referrer policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
    
    /**
     * Clean up old security logs
     *
     * @return void
     */
    public static function cleanupOldLogs(): void
    {
        $deleted = SecurityAuditLogger::cleanupOldLogs(90); // Keep 90 days
        
        SecurityAuditLogger::log(
            SecurityAuditLogger::EVENT_CONFIGURATION,
            SecurityAuditLogger::LOG_LEVEL_INFO,
            "Security log cleanup completed",
            ['deleted_records' => $deleted]
        );
    }
    
    /**
     * Get security statistics
     *
     * @param int $days Number of days to analyze
     * @return array Security statistics
     */
    public static function getSecurityStats(int $days = 30): array
    {
        return SecurityAuditLogger::getSecurityStats($days);
    }
    
    /**
     * Get security logs with filtering
     *
     * @param array $filters Filter options
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @return array Log entries
     */
    public static function getSecurityLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return SecurityAuditLogger::getLogs($filters, $limit, $offset);
    }
    
    /**
     * Rate limiting check
     *
     * @param string $key Rate limit key
     * @param int $max_attempts Maximum attempts allowed
     * @param int $time_window Time window in seconds
     * @return array Result with 'allowed' boolean and 'remaining' attempts
     */
    public static function checkRateLimit(string $key, int $max_attempts = 5, int $time_window = 900): array
    {
        $transient_key = 'smo_rate_limit_' . md5($key);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            // First attempt
            set_transient($transient_key, 1, $time_window);
            return ['allowed' => true, 'remaining' => $max_attempts - 1];
        }
        
        if ($attempts >= $max_attempts) {
            SecurityAuditLogger::log(
                SecurityAuditLogger::EVENT_API_ACCESS,
                SecurityAuditLogger::LOG_LEVEL_WARNING,
                "Rate limit exceeded",
                ['key' => $key, 'attempts' => $attempts, 'max' => $max_attempts]
            );
            return ['allowed' => false, 'remaining' => 0];
        }
        
        // Increment attempts
        set_transient($transient_key, $attempts + 1, $time_window);
        return ['allowed' => true, 'remaining' => $max_attempts - $attempts - 1];
    }
}