<?php
namespace SMO_Social\Security;

/**
 * Enhanced Data Sanitizer with improved security and type safety
 * 
 * @since 2.1.0
 */
class DataSanitizer
{
    private array $sanitization_rules;
    private const MAX_STRING_LENGTH = 65535;
    private const MAX_FILE_NAME_LENGTH = 255;
    
    public function __construct()
    {
        $this->sanitization_rules = array(
            'string' => array($this, 'sanitizeString'),
            'email' => array($this, 'sanitizeEmail'),
            'url' => array($this, 'sanitizeUrl'),
            'html' => array($this, 'sanitizeHtml'),
            'sql' => array($this, 'sanitizeSql'),
            'js' => array($this, 'sanitizeJs'),
            'filename' => array($this, 'sanitizeFilename'),
            'username' => array($this, 'sanitizeUsername')
        );
    }
    
    /**
     * Sanitize input data based on type
     *
     * @param string|array $data Input data to sanitize
     * @param string $type Sanitization type
     * @param array $options Additional options
     * @return string|array Sanitized data
     */
    public function sanitize(string|array $data, string $type = 'string', array $options = []): string|array
    {
        if (is_array($data)) {
            return $this->sanitizeArray($data, $type, $options);
        }
        
        if (isset($this->sanitization_rules[$type])) {
            return call_user_func($this->sanitization_rules[$type], $data, $options);
        }
        
        // Default sanitization
        return $this->sanitizeString($data, $options);
    }
    
    /**
     * Sanitize array data recursively with depth tracking
     *
     * @param array $data Input array to sanitize
     * @param string $type Sanitization type
     * @param array $options Additional options
     * @param int $depth Current recursion depth
     * @return array Sanitized array
     */
    private function sanitizeArray(array $data, string $type = 'string', array $options = [], int $depth = 0): array
    {
        $max_depth = $options['max_depth'] ?? 10;
        
        // Prevent infinite recursion
        if ($depth >= $max_depth) {
            return [];
        }
        
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            // Sanitize array keys
            $sanitized_key = $this->sanitizeString((string)$key, ['max_length' => 255]);
            
            if (is_array($value)) {
                // Recursively sanitize nested arrays with increased depth
                $sanitized[$sanitized_key] = $this->sanitizeArray($value, $type, $options, $depth + 1);
            } else {
                // Sanitize the value
                $sanitized[$sanitized_key] = $this->sanitize($value, $type, $options);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize string input with enhanced security
     *
     * @param string $data Input string
     * @param array $options Sanitization options
     * @return string Sanitized string
     */
    private function sanitizeString(string $data, array $options = []): string
    {
        $default_options = array(
            'max_length' => 255,
            'allow_newlines' => false,
            'strip_tags' => true,
            'remove_control_chars' => true
        );
        
        $options = array_merge($default_options, $options);
        
        // Limit maximum length to prevent DoS
        $options['max_length'] = min($options['max_length'], self::MAX_STRING_LENGTH);
        
        // Strip HTML tags if requested
        if ($options['strip_tags']) {
            $data = strip_tags($data);
        }
        
        // Remove control characters except allowed ones
        if ($options['remove_control_chars']) {
            $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
        }
        
        // Replace newlines if not allowed
        if (!$options['allow_newlines']) {
            $data = str_replace(array("\r\n", "\r", "\n"), ' ', $data);
        }
        
        // Truncate to max length
        if (strlen($data) > $options['max_length']) {
            $data = substr($data, 0, $options['max_length']);
        }
        
        // Trim whitespace
        $data = trim($data);
        
        return $data;
    }
    
    /**
     * Sanitize email address with strict validation
     *
     * @param string $data Input email
     * @param array $options Sanitization options
     * @return string Sanitized email or empty string
     */
    private function sanitizeEmail(string $data, array $options = []): string
    {
        // Remove leading/trailing whitespace first
        $data = trim($data);
        
        // Limit length
        if (strlen($data) > 254) {
            return '';
        }
        
        // Use strict email validation
        $email = filter_var($data, FILTER_SANITIZE_EMAIL);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        
        return $email;
    }
    
    /**
     * Sanitize URL with protocol validation
     *
     * @param string $data Input URL
     * @param array $options Sanitization options
     * @return string Sanitized URL or empty string
     */
    private function sanitizeUrl(string $data, array $options = []): string
    {
        $default_options = array(
            'allowed_protocols' => array('http', 'https'),
            'require_tld' => true
        );
        
        $options = array_merge($default_options, $options);
        
        // Limit URL length
        if (strlen($data) > 2048) {
            return '';
        }
        
        $url = filter_var($data, FILTER_SANITIZE_URL);
        
        if (empty($url)) {
            return '';
        }
        
        // Additional validation for protocols
        $parsed_url = parse_url($url);
        if ($parsed_url && isset($parsed_url['scheme'])) {
            if (!in_array($parsed_url['scheme'], $options['allowed_protocols'])) {
                return '';
            }
        }
        
        // Optional TLD validation
        if ($options['require_tld'] && $parsed_url) {
            $host = $parsed_url['host'] ?? '';
            if (!empty($host) && !preg_match('/\.[a-zA-Z]{2,}$/', $host)) {
                return '';
            }
        }
        
        return $url;
    }
    
    /**
     * Sanitize HTML content with whitelist approach
     *
     * @param string $data Input HTML
     * @param array $options Sanitization options
     * @return string Sanitized HTML
     */
    private function sanitizeHtml(string $data, array $options = []): string
    {
        $default_options = array(
            'allowed_tags' => '<p><br><strong><em><a><img><ul><li><ol><h1><h2><h3><blockquote>',
            'allowed_attributes' => array('href', 'src', 'alt', 'title', 'class'),
            'max_length' => 50000
        );
        
        $options = array_merge($default_options, $options);
        
        // Limit length to prevent memory exhaustion
        if (strlen($data) > $options['max_length']) {
            $data = substr($data, 0, $options['max_length']);
        }
        
        // Remove potentially dangerous content using DOM approach
        $dangerous_patterns = array(
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/is',
            '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/is',
            '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/is',
            '/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/is',
            '/javascript:/i',
            '/data:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i' // Remove inline event handlers
        );
        
        $data = preg_replace($dangerous_patterns, '', $data);
        
        // Allow only specified tags
        if ($options['allowed_tags']) {
            $data = strip_tags($data, $options['allowed_tags']);
        }
        
        // Remove dangerous attributes
        $data = preg_replace('/\s+(onclick|onload|onerror|onmouseover|onfocus|onblur)\s*=\s*["\'][^"\']*["\']/i', '', $data);
        
        return $data;
    }
    
    /**
     * Sanitize data for SQL queries - deprecated in favor of prepared statements
     *
     * @param string $data Input data
     * @param array $options Sanitization options
     * @return string SQL-safe string
     * @deprecated Use prepared statements instead
     */
    private function sanitizeSql(string $data, array $options = []): string
    {
        // Log deprecation warning
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social: sanitize_sql is deprecated. Use prepared statements instead.');
        }
        
        // Escape SQL special characters
        global $wpdb;
        if ($wpdb && method_exists($wpdb, 'prepare')) {
            // This is still not ideal, but better than raw escaping
            return $wpdb->prepare('%s', $data);
        }
        
        if (function_exists('\mysqli_real_escape_string')) {
            // Use MySQLi if available
            global $wpdb;
            if ($wpdb && isset($wpdb->dbh)) {
                return \mysqli_real_escape_string($wpdb->dbh, $data);
            } else {
                return addslashes($data);
            }
        } else {
            // Fallback to addslashes
            return addslashes($data);
        }
    }
    
    /**
     * Sanitize JavaScript content for safe output
     *
     * @param string $data Input JavaScript
     * @param array $options Sanitization options
     * @return string Sanitized JavaScript
     */
    private function sanitizeJs(string $data, array $options = []): string
    {
        $default_options = array(
            'context' => 'json' // 'json', 'html', 'attribute'
        );
        
        $options = array_merge($default_options, $options);
        
        // Remove dangerous patterns
        $dangerous_patterns = array(
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/onmouseover\s*=/i',
            '/onmouseout\s*=/i'
        );
        
        $data = preg_replace($dangerous_patterns, '', $data);
        
        // Context-specific escaping
        switch ($options['context']) {
            case 'json':
                // Escape for JSON context
                $data = json_encode($data);
                $data = substr($data, 1, -1); // Remove surrounding quotes
                break;
                
            case 'html':
                // Escape for HTML content
                $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                break;
                
            case 'attribute':
                // Escape for HTML attributes
                $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                break;
        }
        
        return $data;
    }
    
    /**
     * Sanitize filename with enhanced security
     *
     * @param string $data Input filename
     * @param array $options Sanitization options
     * @return string Sanitized filename or empty string
     */
    private function sanitizeFilename(string $data, array $options = []): string
    {
        $default_options = array(
            'max_length' => self::MAX_FILE_NAME_LENGTH,
            'replace_spaces' => '-',
            'allowed_extensions' => array(),
            'prevent_path_traversal' => true
        );
        
        $options = array_merge($default_options, $options);
        
        // Prevent path traversal attacks
        if ($options['prevent_path_traversal']) {
            $data = basename($data);
        }
        
        // Remove dangerous characters
        $dangerous_chars = array('\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0");
        $data = str_replace($dangerous_chars, '', $data);
        
        // Remove control characters
        $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
        
        // Replace spaces
        $data = str_replace(' ', $options['replace_spaces'], $data);
        
        // Remove multiple consecutive dots or dashes
        $data = preg_replace('/[\.]+/', '.', $data);
        $data = preg_replace('/[\-]+/', '-', $data);
        
        // Remove leading dots to prevent hidden files
        $data = ltrim($data, '.');
        
        // Validate extension if specified
        if (!empty($options['allowed_extensions'])) {
            $extension = pathinfo($data, PATHINFO_EXTENSION);
            if (!in_array(strtolower($extension), array_map('strtolower', $options['allowed_extensions']))) {
                return '';
            }
        }
        
        // Truncate to max length
        if (strlen($data) > $options['max_length']) {
            $data = substr($data, 0, $options['max_length']);
        }
        
        return $data;
    }
    
    /**
     * Sanitize username with strict validation
     *
     * @param string $data Input username
     * @param array $options Sanitization options
     * @return string Sanitized username or empty string
     */
    private function sanitizeUsername(string $data, array $options = []): string
    {
        $default_options = array(
            'min_length' => 3,
            'max_length' => 32,
            'allow_special_chars' => false,
            'allow_unicode' => false
        );
        
        $options = array_merge($default_options, $options);
        
        // Remove control characters
        $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
        
        // Handle Unicode usernames
        if (!$options['allow_unicode']) {
            // ASCII only
            if ($options['allow_special_chars']) {
                $data = preg_replace('/[^a-zA-Z0-9_\-.@]/', '', $data);
            } else {
                $data = preg_replace('/[^a-zA-Z0-9_]/', '', $data);
            }
        } else {
            // Allow Unicode letters and numbers
            if ($options['allow_special_chars']) {
                $data = preg_replace('/[^\p{L}\p{N}_\-.@]/u', '', $data);
            } else {
                $data = preg_replace('/[^\p{L}\p{N}_]/u', '', $data);
            }
        }
        
        // Check length constraints
        $length = strlen($data);
        if ($length < $options['min_length'] || $length > $options['max_length']) {
            return '';
        }
        
        // Remove leading/trailing whitespace
        $data = trim($data);
        
        return $data;
    }
    
    /**
     * Validate input data against defined rules with audit logging
     *
     * @param array $data Input data to validate
     * @param array $rules Validation rules
     * @param string $form_name Name of the form being validated
     * @return array Validation result
     */
    public function validateInput(array $data, array $rules, string $form_name = 'unknown'): array
    {
        $errors = array();
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                if ($rule['required'] ?? false) {
                    $errors[$field] = 'This field is required';
                }
                continue;
            }
            
            $value = $data[$field];
            
            // Skip validation for empty values unless required
            if (empty($value) && !($rule['required'] ?? false)) {
                continue;
            }
            
            $sanitized_value = $this->sanitize($value, $rule['type'] ?? 'string', $rule['options'] ?? array());
            
            // Check if sanitization changed the value (indicating potential issues)
            if ($value !== $sanitized_value && ($rule['strict'] ?? false)) {
                $errors[$field] = 'Invalid format for ' . $field;
            }
            
            // Additional validation rules
            if (isset($rule['min_length']) && strlen((string)$sanitized_value) < $rule['min_length']) {
                $errors[$field] = 'Minimum length for ' . $field . ' is ' . $rule['min_length'];
            }
            
            if (isset($rule['max_length']) && strlen((string)$sanitized_value) > $rule['max_length']) {
                $errors[$field] = 'Maximum length for ' . $field . ' is ' . $rule['max_length'];
            }
            
            // Custom validation callback
            if (isset($rule['callback']) && is_callable($rule['callback'])) {
                $callback_result = call_user_func($rule['callback'], $sanitized_value, $data);
                if ($callback_result !== true) {
                    $errors[$field] = is_string($callback_result) ? $callback_result : 'Invalid value for ' . $field;
                }
            }
        }
        
        $is_valid = empty($errors);
        
        // Log validation attempt
        if (class_exists('SMO_Social\\Security\\SecurityAuditLogger')) {
            SecurityAuditLogger::logInputValidation($form_name, $is_valid, $errors, [
                'fields_validated' => count($rules),
                'errors_found' => count($errors)
            ]);
        }
        
        return array(
            'valid' => $is_valid,
            'errors' => $errors,
            'sanitized_data' => $this->sanitize($data, 'string', []) // Return sanitized version of all data
        );
    }
    
    /**
     * Enhanced sanitization with security logging
     *
     * @param string|array $data Input data to sanitize
     * @param string $type Sanitization type
     * @param array $options Additional options
     * @param string $context Context for logging
     * @return string|array Sanitized data
     */
    public function sanitizeWithLogging(string|array $data, string $type = 'string', array $options = [], string $context = 'general'): string|array
    {
        $original_data = $data;
        $sanitized_data = $this->sanitize($data, $type, $options);
        
        // Log if data was modified during sanitization (potential security issue)
        if ($original_data !== $sanitized_data && class_exists('SMO_Social\\Security\\SecurityAuditLogger')) {
            SecurityAuditLogger::log(
                SecurityAuditLogger::EVENT_INPUT_VALIDATION,
                SecurityAuditLogger::LOG_LEVEL_WARNING,
                "Data sanitization modified input in context: {$context}",
                [
                    'type' => $type,
                    'context' => $context,
                    'original_length' => strlen(json_encode($original_data)),
                    'sanitized_length' => strlen(json_encode($sanitized_data))
                ]
            );
        }
        
        return $sanitized_data;
    }
}
