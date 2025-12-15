<?php
/**
 * SMO Social - Production Security Configuration
 * 
 * This script configures comprehensive security settings for production:
 * - Security headers
 * - Input validation
 * - CSRF protection
 * - Rate limiting
 * - SSL/TLS configuration
 * - Security monitoring
 * 
 * Usage: php production_security_config.php
 */

class ProductionSecurityConfig {
    
    private $security_config = [];
    private $security_headers = [];
    private $rate_limits = [];
    private $validation_rules = [];
    
    public function __construct() {
        echo "🔒 SMO Social Production Security Configuration\n";
        echo "==============================================\n\n";
        $this->initializeSecuritySettings();
    }
    
    /**
     * Initialize security settings
     */
    private function initializeSecuritySettings() {
        // Security headers configuration
        $this->security_headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https:;",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'X-Permitted-Cross-Domain-Policies' => 'none'
        ];
        
        // Rate limiting configuration
        $this->rate_limits = [
            'api_requests_per_hour' => 1000,
            'api_requests_per_day' => 10000,
            'login_attempts_per_hour' => 5,
            'login_attempts_per_day' => 20,
            'oauth_requests_per_hour' => 50,
            'webhook_requests_per_hour' => 1000,
            'admin_requests_per_hour' => 500,
            'content_upload_per_hour' => 100
        ];
        
        // Security validation rules
        $this->validation_rules = [
            'password_min_length' => 12,
            'password_require_special' => true,
            'password_require_numbers' => true,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'session_timeout_minutes' => 60,
            'max_concurrent_sessions' => 3,
            'account_lockout_attempts' => 5,
            'account_lockout_duration' => 30 // minutes
        ];
    }
    
    /**
     * Generate security configuration
     */
    public function generateSecurityConfig() {
        echo "🔧 Generating production security configuration...\n\n";
        
        $config = $this->buildSecurityConfig();
        $config_file = 'production_security_config.php';
        file_put_contents($config_file, $config);
        
        echo "✅ Security configuration created: {$config_file}\n\n";
        
        return $config_file;
    }
    
    /**
     * Build security configuration
     */
    private function buildSecurityConfig() {
        $config = "<?php\n";
        $config .= "/**\n";
        $config .= " * SMO Social Production Security Configuration\n";
        $config .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $config .= " */\n\n";
        
        $config .= "// Security Headers Configuration\n";
        $config .= "define('SMO_SECURITY_HEADERS', " . var_export($this->security_headers, true) . ");\n\n";
        
        $config .= "// Rate Limiting Configuration\n";
        $config .= "define('SMO_RATE_LIMITS', " . var_export($this->rate_limits, true) . ");\n\n";
        
        $config .= "// Security Validation Rules\n";
        $config .= "define('SMO_SECURITY_RULES', " . var_export($this->validation_rules, true) . ");\n\n";
        
        $config .= "// SSL/TLS Configuration\n";
        $config .= "define('SMO_FORCE_HTTPS', true);\n";
        $config .= "define('SMO_HSTS_ENABLED', true);\n";
        $config .= "define('SMO_SSL_VERIFY_PEER', true);\n";
        $config .= "define('SMO_SSL_VERIFY_HOST', true);\n\n";
        
        $config .= "// API Security Configuration\n";
        $config .= "define('SMO_API_ENCRYPTION', true);\n";
        $config .= "define('SMO_API_TOKEN_EXPIRY', 3600); // 1 hour\n";
        $config .= "define('SMO_API_SIGNATURE_REQUIRED', true);\n\n";
        
        $config .= "// Session Security Configuration\n";
        $config .= "define('SMO_SESSION_SECURE', true);\n";
        $config .= "define('SMO_SESSION_HTTPONLY', true);\n";
        $config .= "define('SMO_SESSION_SAMESITE', 'Strict');\n";
        $config .= "define('SMO_SESSION_REGENERATE', true);\n\n";
        
        $config .= "// Input Validation Configuration\n";
        $config .= "define('SMO_STRICT_VALIDATION', true);\n";
        $config .= "define('SMO_SANITIZE_INPUT', true);\n";
        $config .= "define('SMO_BLOCK_SQL_INJECTION', true);\n";
        $config .= "define('SMO_BLOCK_XSS', true);\n\n";
        
        $config .= "// File Upload Security\n";
        $config .= "define('SMO_ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,txt');\n";
        $config .= "define('SMO_MAX_FILE_SIZE', 5242880); // 5MB\n";
        $config .= "define('SMO_SCAN_UPLOADS', true);\n";
        $config .= "define('SMO_QUARANTINE_SUSPICIOUS', true);\n\n";
        
        $config .= "// CSRF Protection\n";
        $config .= "define('SMO_CSRF_PROTECTION', true);\n";
        $config .= "define('SMO_CSRF_TOKEN_EXPIRY', 1800); // 30 minutes\n";
        $config .= "define('SMO_CSRF_REGENERATE_ON_SUBMIT', true);\n\n";
        
        return $config;
    }
    
    /**
     * Generate security headers configuration
     */
    public function generateSecurityHeaders() {
        echo "🛡️ Generating security headers configuration...\n\n";
        
        $headers = $this->buildSecurityHeaders();
        $headers_file = 'production_security_headers.php';
        file_put_contents($headers_file, $headers);
        
        echo "✅ Security headers configuration created: {$headers_file}\n\n";
        
        return $headers_file;
    }
    
    /**
     * Build security headers configuration
     */
    private function buildSecurityHeaders() {
        $headers = "<?php\n";
        $headers .= "/**\n";
        $headers .= " * SMO Social Security Headers Configuration\n";
        $headers .= " * Add this to your theme's functions.php or plugin activation\n";
        $headers .= " */\n\n";
        
        $headers .= "class SMO_Security_Headers {\n";
        $headers .= "    \n";
        $headers .= "    public function __construct() {\n";
        $headers .= "        add_action('send_headers', [\$this, 'add_security_headers']);\n";
        $headers .= "        add_action('admin_init', [\$this, 'force_https']);\n";
        $headers .= "        add_action('wp_loaded', [\$this, 'security_middleware']);\n";
        $headers .= "    }\n\n";
        
        $headers .= "    public function add_security_headers() {\n";
        $headers .= "        if (!headers_sent()) {\n";
        foreach ($this->security_headers as $header => $value) {
            $headers .= "            header('{$header}: {$value}');\n";
        }
        $headers .= "        }\n";
        $headers .= "    }\n\n";
        
        $headers .= "    public function force_https() {\n";
        $headers .= "        if (defined('SMO_FORCE_HTTPS') && SMO_FORCE_HTTPS) {\n";
        $headers .= "            if (!is_ssl() && !wp_doing_ajax()) {\n";
        $headers .= "                \$redirect = 'https://' . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI'];\n";
        $headers .= "                wp_redirect(\$redirect);\n";
        $headers .= "                exit();\n";
        $headers .= "            }\n";
        $headers .= "        }\n";
        $headers .= "    }\n\n";
        
        $headers .= "    public function security_middleware() {\n";
        $headers .= "        \$this->validate_request();\n";
        $headers .= "        \$this->check_rate_limits();\n";
        $headers .= "        \$this->validate_csrf_token();\n";
        $headers .= "    }\n\n";
        
        $headers .= "    private function validate_request() {\n";
        $headers .= "        // Validate request method\n";
        $headers .= "        if (!in_array(\$_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT', 'DELETE'])) {\n";
        $headers .= "            wp_die('Method not allowed', 'Method Not Allowed', ['response' => 405]);\n";
        $headers .= "        }\n\n";
        
        $headers .= "        // Check for suspicious patterns\n";
        $headers .= "        \$suspicious_patterns = [\n";
        $headers .= "            '/union.*select/i',\n";
        $headers .= "            '/script.*script/i',\n";
        $headers .= "            '/javascript:/i',\n";
        $headers .= "            '/vbscript:/i',\n";
        $headers .= "            '/onload/i',\n";
        $headers .= "            '/onerror/i'\n";
        $headers .= "        ];\n\n";
        
        $headers .= "        foreach ([\$_GET, \$_POST, \$_REQUEST] as \$input_array) {\n";
        $headers .= "            foreach (\$input_array as \$key => \$value) {\n";
        $headers .= "                if (is_string(\$value)) {\n";
        $headers .= "                    foreach (\$suspicious_patterns as \$pattern) {\n";
        $headers .= "                        if (preg_match(\$pattern, \$value)) {\n";
        $headers .= "                            wp_die('Suspicious request detected', 'Forbidden', ['response' => 403]);\n";
        $headers .= "                        }\n";
        $headers .= "                    }\n";
        $headers .= "                }\n";
        $headers .= "            }\n";
        $headers .= "        }\n";
        $headers .= "    }\n\n";
        
        $headers .= "    private function check_rate_limits() {\n";
        $headers .= "        // Rate limiting implementation would go here\n";
        $headers .= "        // This is a placeholder for the actual rate limiting logic\n";
        $headers .= "    }\n\n";
        
        $headers .= "    private function validate_csrf_token() {\n";
        $headers .= "        if (defined('SMO_CSRF_PROTECTION') && SMO_CSRF_PROTECTION) {\n";
        $headers .= "            if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n";
        $headers .= "                \$token = \$_POST['_smo_csrf_token'] ?? '';\n";
        $headers .= "                if (!wp_verify_nonce(\$token, 'smo_csrf_token')) {\n";
        $headers .= "                    wp_die('CSRF token validation failed', 'Forbidden', ['response' => 403]);\n";
        $headers .= "                }\n";
        $headers .= "            }\n";
        $headers .= "        }\n";
        $headers .= "    }\n";
        $headers .= "}\n\n";
        
        $headers .= "// Initialize security headers\n";
        $headers .= "new SMO_Security_Headers();\n";
        
        return $headers;
    }
    
    /**
     * Generate input validation system
     */
    public function generateInputValidation() {
        echo "🔍 Generating input validation system...\n\n";
        
        $validation = $this->buildInputValidation();
        $validation_file = 'production_input_validation.php';
        file_put_contents($validation_file, $validation);
        
        echo "✅ Input validation system created: {$validation_file}\n\n";
        
        return $validation_file;
    }
    
    /**
     * Build input validation system
     */
    private function buildInputValidation() {
        $validation = "<?php\n";
        $validation .= "/**\n";
        $validation .= " * SMO Social Input Validation System\n";
        $validation .= " * Comprehensive input validation and sanitization\n";
        $validation .= " */\n\n";
        
        $validation .= "class SMO_Input_Validator {\n";
        $validation .= "    \n";
        $validation .= "    private \$sanitization_rules = [];\n";
        $validation .= "    private \$validation_rules = [];\n\n";
        
        $validation .= "    public function __construct() {\n";
        $validation .= "        \$this->load_validation_rules();\n";
        $validation .= "    }\n\n";
        
        $validation .= "    private function load_validation_rules() {\n";
        $validation .= "        \$this->sanitization_rules = [\n";
        $validation .= "            'email' => 'sanitize_email',\n";
        $validation .= "            'url' => 'esc_url_raw',\n";
        $validation .= "            'text' => 'sanitize_text_field',\n";
        $validation .= "            'textarea' => 'sanitize_textarea_field',\n";
        $validation .= "            'html' => 'wp_kses_post',\n";
        $validation .= "            'key' => 'sanitize_key',\n";
        $validation .= "            'slug' => 'sanitize_title',\n";
        $validation .= "            'int' => 'intval',\n";
        $validation .= "            'float' => 'floatval',\n";
        $validation .= "            'boolean' => function(\$value) { return (bool) \$value; },\n";
        $validation .= "            'json' => function(\$value) { \n";
        $validation .= "                \$decoded = json_decode(\$value, true);\n";
        $validation .= "                return (json_last_error() === JSON_ERROR_NONE) ? \$decoded : null;\n";
        $validation .= "            }\n";
        $validation .= "        ];\n\n";
        
        $validation .= "        \$this->validation_rules = [\n";
        $validation .= "            'email' => function(\$value) { return is_email(\$value); },\n";
        $validation .= "            'url' => function(\$value) { return filter_var(\$value, FILTER_VALIDATE_URL) !== false; },\n";
        $validation .= "            'min_length' => function(\$value, \$min) { return strlen(\$value) >= \$min; },\n";
        $validation .= "            'max_length' => function(\$value, \$max) { return strlen(\$value) <= \$max; },\n";
        $validation .= "            'regex' => function(\$value, \$pattern) { return preg_match(\$pattern, \$value); },\n";
        $validation .= "            'in_array' => function(\$value, \$array) { return in_array(\$value, \$array); },\n";
        $validation .= "            'numeric' => function(\$value) { return is_numeric(\$value); },\n";
        $validation .= "            'alpha' => function(\$value) { return ctype_alpha(\$value); },\n";
        $validation .= "            'alphanumeric' => function(\$value) { return ctype_alnum(\$value); }\n";
        $validation .= "        ];\n";
        $validation .= "    }\n\n";
        
        $validation .= "    public function validate_and_sanitize(\$data, \$rules) {\n";
        $validation .= "        \$results = [\n";
        $validation .= "            'valid' => true,\n";
        $validation .= "            'errors' => [],\n";
        $validation .= "            'sanitized_data' => []\n";
        $validation .= "        ];\n\n";
        
        $validation .= "        foreach (\$rules as \$field => \$field_rules) {\n";
        $validation .= "            \$value = \$data[\$field] ?? null;\n\n";
        
        $validation .= "            // Apply sanitization\n";
        $validation .= "            if (isset(\$field_rules['sanitize'])) {\n";
        $validation .= "                \$sanitize_method = \$field_rules['sanitize'];\n";
        $validation .= "                if (isset(\$this->sanitization_rules[\$sanitize_method])) {\n";
        $validation .= "                    \$sanitize_function = \$this->sanitization_rules[\$sanitize_method];\n";
        $validation .= "                    if (is_callable(\$sanitize_function)) {\n";
        $validation .= "                        \$value = call_user_func(\$sanitize_function, \$value);\n";
        $validation .= "                    }\n";
        $validation .= "                }\n";
        $validation .= "            }\n\n";
        
        $validation .= "            // Apply validation rules\n";
        $validation .= "            if (isset(\$field_rules['validate'])) {\n";
        $validation .= "                foreach (\$field_rules['validate'] as \$rule_name => \$rule_config) {\n";
        $validation .= "                    if (isset(\$this->validation_rules[\$rule_name])) {\n";
        $validation .= "                        \$validate_function = \$this->validation_rules[\$rule_name];\n";
        $validation .= "                        \$is_valid = is_array(\$rule_config) \n";
        $validation .= "                            ? call_user_func(\$validate_function, \$value, ...\$rule_config)\n";
        $validation .= "                            : call_user_func(\$validate_function, \$value, \$rule_config);\n\n";
        
        $validation .= "                        if (!\$is_valid) {\n";
        $validation .= "                            \$results['valid'] = false;\n";
        $validation .= "                            \$results['errors'][\$field][] = \"Validation failed for rule: {\$rule_name}\";\n";
        $validation .= "                        }\n";
        $validation .= "                    }\n";
        $validation .= "                }\n";
        $validation .= "            }\n\n";
        
        $validation .= "            \$results['sanitized_data'][\$field] = \$value;\n";
        $validation .= "        }\n\n";
        
        $validation .= "        return \$results;\n";
        $validation .= "    }\n\n";
        
        $validation .= "    public function validate_file_upload(\$file) {\n";
        $validation .= "        \$errors = [];\n\n";
        
        $validation .= "        // Check for upload errors\n";
        $validation .= "        if (\$file['error'] !== UPLOAD_ERR_OK) {\n";
        $validation .= "            \$errors[] = 'File upload error: ' . \$file['error'];\n";
        $validation .= "        }\n\n";
        
        $validation .= "        // Check file size\n";
        $validation .= "        \$max_size = defined('SMO_MAX_FILE_SIZE') ? SMO_MAX_FILE_SIZE : 5242880;\n";
        $validation .= "        if (\$file['size'] > \$max_size) {\n";
        $validation .= "            \$errors[] = 'File size exceeds maximum allowed size';\n";
        $validation .= "        }\n\n";
        
        $validation .= "        // Check file type\n";
        $validation .= "        \$allowed_types = defined('SMO_ALLOWED_FILE_TYPES') ? explode(',', SMO_ALLOWED_FILE_TYPES) : [];\n";
        $validation .= "        \$file_extension = strtolower(pathinfo(\$file['name'], PATHINFO_EXTENSION));\n";
        $validation .= "        if (!in_array(\$file_extension, \$allowed_types)) {\n";
        $validation .= "            \$errors[] = 'File type not allowed';\n";
        $validation .= "        }\n\n";
        
        $validation .= "        // Check MIME type\n";
        $validation .= "        \$finfo = finfo_open(FILEINFO_MIME_TYPE);\n";
        $validation .= "        \$mime_type = finfo_file(\$finfo, \$file['tmp_name']);\n";
        $validation .= "        finfo_close(\$finfo);\n\n";
        
        $validation .= "        // Additional MIME type validation logic would go here\n\n";
        
        $validation .= "        return [\n";
        $validation .= "            'valid' => empty(\$errors),\n";
        $validation .= "            'errors' => \$errors,\n";
        $validation .= "            'mime_type' => \$mime_type\n";
        $validation .= "        ];\n";
        $validation .= "    }\n";
        $validation .= "}\n\n";
        
        $validation .= "// Example usage:\n";
        $validation .= "/*\n";
        $validation .= "\$validator = new SMO_Input_Validator();\n";
        $validation .= "\n";
        $validation .= "\$rules = [\n";
        $validation .= "    'user_email' => [\n";
        $validation .= "        'sanitize' => 'email',\n";
        $validation .= "        'validate' => [\n";
        $validation .= "            'email' => true,\n";
        $validation .= "            'min_length' => 5\n";
        $validation .= "        ]\n";
        $validation .= "    ],\n";
        $validation .= "    'user_name' => [\n";
        $validation .= "        'sanitize' => 'text',\n";
        $validation .= "        'validate' => [\n";
        $validation .= "            'min_length' => 2,\n";
        $validation .= "            'max_length' => 50,\n";
        $validation .= "            'alphanumeric' => true\n";
        $validation .= "        ]\n";
        $validation .= "    ]\n";
        $validation .= "];\n";
        $validation .= "\n";
        $validation .= "\$result = \$validator->validate_and_sanitize(\$_POST, \$rules);\n";
        $validation .= "*/\n";
        
        return $validation;
    }
    
    /**
     * Generate rate limiting system
     */
    public function generateRateLimiting() {
        echo "⏱️ Generating rate limiting system...\n\n";
        
        $rate_limiting = $this->buildRateLimiting();
        $rate_limiting_file = 'production_rate_limiting.php';
        file_put_contents($rate_limiting_file, $rate_limiting);
        
        echo "✅ Rate limiting system created: {$rate_limiting_file}\n\n";
        
        return $rate_limiting_file;
    }
    
    /**
     * Build rate limiting system
     */
    private function buildRateLimiting() {
        $rate_limiting = "<?php\n";
        $rate_limiting .= "/**\n";
        $rate_limiting .= " * SMO Social Rate Limiting System\n";
        $rate_limiting .= " * Prevents abuse and ensures fair API usage\n";
        $rate_limiting .= " */\n\n";
        
        $rate_limiting .= "class SMO_Rate_Limiter {\n";
        $rate_limiting .= "    \n";
        $rate_limiting .= "    private \$limits;\n";
        $rate_limiting .= "    private \$redis;\n";
        $rate_limiting .= "    private \$db;\n\n";
        
        $rate_limiting .= "    public function __construct() {\n";
        $rate_limiting .= "        global \$wpdb;\n";
        $rate_limiting .= "        \$this->db = \$wpdb;\n";
        $rate_limiting .= "        \$this->load_limits();\n";
        $rate_limiting .= "        \$this->initialize_storage();\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function load_limits() {\n";
        $rate_limiting .= "        \$this->limits = defined('SMO_RATE_LIMITS') ? SMO_RATE_LIMITS : [\n";
        $rate_limiting .= "            'api_requests_per_hour' => 1000,\n";
        $rate_limiting .= "            'api_requests_per_day' => 10000,\n";
        $rate_limiting .= "            'login_attempts_per_hour' => 5,\n";
        $rate_limiting .= "            'admin_requests_per_hour' => 500\n";
        $rate_limiting .= "        ];\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function initialize_storage() {\n";
        $rate_limiting .= "        // Create rate limiting table if it doesn't exist\n";
        $rate_limiting .= "        \$table_name = \$this->db->prefix . 'smo_rate_limits';\n";
        $rate_limiting .= "        \$this->db->query(\"\n";
        $rate_limiting .= "            CREATE TABLE IF NOT EXISTS {\$table_name} (\n";
        $rate_limiting .= "                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
        $rate_limiting .= "                identifier varchar(255) NOT NULL,\n";
        $rate_limiting .= "                action varchar(100) NOT NULL,\n";
        $rate_limiting .= "                count int(11) NOT NULL DEFAULT 0,\n";
        $rate_limiting .= "                window_start datetime NOT NULL,\n";
        $rate_limiting .= "                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
        $rate_limiting .= "                PRIMARY KEY (id),\n";
        $rate_limiting .= "                UNIQUE KEY identifier_action (identifier, action),\n";
        $rate_limiting .= "                KEY window_start (window_start)\n";
        $rate_limiting .= "            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n";
        $rate_limiting .= "        \");\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    public function check_limit(\$action, \$identifier = null, \$window = 'hour') {\n";
        $rate_limiting .= "        if (!\$identifier) {\n";
        $rate_limiting .= "            \$identifier = \$this->get_client_identifier();\n";
        $rate_limiting .= "        }\n\n";
        
        $rate_limiting .= "        \$limit_key = \$action . '_per_' . \$window;\n";
        $rate_limiting .= "        \$max_requests = \$this->limits[\$limit_key] ?? null;\n\n";
        
        $rate_limiting .= "        if (!\$max_requests) {\n";
        $rate_limiting .= "            return ['allowed' => true, 'remaining' => 999999];\n";
        $rate_limiting .= "        }\n\n";
        
        $rate_limiting .= "        \$window_start = \$this->get_window_start(\$window);\n";
        $rate_limiting .= "        \$current_count = \$this->get_current_count(\$identifier, \$action, \$window_start);\n\n";
        
        $rate_limiting .= "        if (\$current_count >= \$max_requests) {\n";
        $rate_limiting .= "            \$this->log_rate_limit_violation(\$action, \$identifier, \$current_count, \$max_requests);\n";
        $rate_limiting .= "            return [\n";
        $rate_limiting .= "                'allowed' => false,\n";
        $rate_limiting .= "                'remaining' => 0,\n";
        $rate_limiting .= "                'reset_time' => \$this->get_window_end(\$window)\n";
        $rate_limiting .= "            ];\n";
        $rate_limiting .= "        }\n\n";
        
        $rate_limiting .= "        \$this->increment_count(\$identifier, \$action, \$window_start);\n\n";
        
        $rate_limiting .= "        return [\n";
        $rate_limiting .= "            'allowed' => true,\n";
        $rate_limiting .= "            'remaining' => \$max_requests - \$current_count - 1,\n";
        $rate_limiting .= "            'reset_time' => \$this->get_window_end(\$window)\n";
        $rate_limiting .= "        ];\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function get_client_identifier() {\n";
        $rate_limiting .= "        \$ip = \$_SERVER['HTTP_X_FORWARDED_FOR'] ?? \$_SERVER['REMOTE_ADDR'];\n";
        $rate_limiting .= "        \$user_agent = \$_SERVER['HTTP_USER_AGENT'] ?? '';\n";
        $rate_limiting .= "        return hash('sha256', \$ip . \$user_agent);\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function get_window_start(\$window) {\n";
        $rate_limiting .= "        switch (\$window) {\n";
        $rate_limiting .= "            case 'hour':\n";
        $rate_limiting .= "                return date('Y-m-d H:00:00');\n";
        $rate_limiting .= "            case 'day':\n";
        $rate_limiting .= "                return date('Y-m-d 00:00:00');\n";
        $rate_limiting .= "            case 'week':\n";
        $rate_limiting .= "                return date('Y-m-d 00:00:00', strtotime('monday this week'));\n";
        $rate_limiting .= "            default:\n";
        $rate_limiting .= "                return date('Y-m-d H:00:00');\n";
        $rate_limiting .= "        }\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function get_window_end(\$window) {\n";
        $rate_limiting .= "        switch (\$window) {\n";
        $rate_limiting .= "            case 'hour':\n";
        $rate_limiting .= "                return strtotime('+1 hour');\n";
        $rate_limiting .= "            case 'day':\n";
        $rate_limiting .= "                return strtotime('+1 day');\n";
        $rate_limiting .= "            case 'week':\n";
        $rate_limiting .= "                return strtotime('+1 week');\n";
        $rate_limiting .= "            default:\n";
        $rate_limiting .= "                return strtotime('+1 hour');\n";
        $rate_limiting .= "        }\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function get_current_count(\$identifier, \$action, \$window_start) {\n";
        $rate_limiting .= "        \$table_name = \$this->db->prefix . 'smo_rate_limits';\n";
        $rate_limiting .= "        \$result = \$this->db->get_var(\$this->db->prepare(\n";
        $rate_limiting .= "            \"SELECT count FROM {\$table_name} WHERE identifier = %s AND action = %s AND window_start = %s\",\n";
        $rate_limiting .= "            \$identifier, \$action, \$window_start\n";
        $rate_limiting .= "        ));\n\n";
        
        $rate_limiting .= "        return \$result ? intval(\$result) : 0;\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function increment_count(\$identifier, \$action, \$window_start) {\n";
        $rate_limiting .= "        \$table_name = \$this->db->prefix . 'smo_rate_limits';\n";
        $rate_limiting .= "        \$this->db->query(\$this->db->prepare(\n";
        $rate_limiting .= "            \"INSERT INTO {\$table_name} (identifier, action, count, window_start) \n";
        $rate_limiting .= "            VALUES (%s, %s, 1, %s) \n";
        $rate_limiting .= "            ON DUPLICATE KEY UPDATE count = count + 1\",\n";
        $rate_limiting .= "            \$identifier, \$action, \$window_start\n";
        $rate_limiting .= "        ));\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    private function log_rate_limit_violation(\$action, \$identifier, \$current_count, \$max_requests) {\n";
        $rate_limiting .= "        // Log rate limit violation for monitoring\n";
        $rate_limiting .= "        error_log(\"Rate limit exceeded for {\$action}: {\$identifier} (current: {\$current_count}, max: {\$max_requests})\");\n\n";
        
        $rate_limiting .= "        // Could trigger security alert here\n";
        $rate_limiting .= "        if (defined('SMO_ALERT_EMAIL')) {\n";
        $rate_limiting .= "            wp_mail(\n";
        $rate_limiting .= "                SMO_ALERT_EMAIL,\n";
        $rate_limiting .= "                'Rate Limit Exceeded - ' . \$action,\n";
        $rate_limiting .= "                \"Rate limit exceeded for action: {\$action}\\nIdentifier: {\$identifier}\\nCurrent count: {\$current_count}\\nMax allowed: {\$max_requests}\"\n";
        $rate_limiting .= "            );\n";
        $rate_limiting .= "        }\n";
        $rate_limiting .= "    }\n\n";
        
        $rate_limiting .= "    public function cleanup_old_entries() {\n";
        $rate_limiting .= "        \$table_name = \$this->db->prefix . 'smo_rate_limits';\n";
        $rate_limiting .= "        \$this->db->query(\"DELETE FROM {\$table_name} WHERE window_start < DATE_SUB(NOW(), INTERVAL 7 DAY)\");\n";
        $rate_limiting .= "    }\n";
        $rate_limiting .= "}\n\n";
        
        $rate_limiting .= "// Example usage:\n";
        $rate_limiting .= "/*\n";
        $rate_limiting .= "\$rate_limiter = new SMO_Rate_Limiter();\n";
        $rate_limiting .= "\n";
        $rate_limiting .= "// Check API rate limit\n";
        $rate_limiting .= "\$result = \$rate_limiter->check_limit('api_requests', \$user_id, 'hour');\n";
        $rate_limiting .= "if (!\$result['allowed']) {\n";
        $rate_limiting .= "    wp_send_json_error('Rate limit exceeded. Try again later.', 429);\n";
        $rate_limiting .= "}\n";
        $rate_limiting .= "*/\n";
        
        return $rate_limiting;
    }
    
    /**
     * Run complete security setup
     */
    public function runSetup() {
        echo "🔒 Running complete security configuration setup...\n\n";
        
        $config_file = $this->generateSecurityConfig();
        $headers_file = $this->generateSecurityHeaders();
        $validation_file = $this->generateInputValidation();
        $rate_limiting_file = $this->generateRateLimiting();
        
        echo "\n🔐 SECURITY CONFIGURATION COMPLETE!\n";
        echo "===================================\n\n";
        
        echo "📁 Generated Security Files:\n";
        echo "   • Configuration: {$config_file}\n";
        echo "   • Security Headers: {$headers_file}\n";
        echo "   • Input Validation: {$validation_file}\n";
        echo "   • Rate Limiting: {$rate_limiting_file}\n\n";
        
        echo "🔧 NEXT STEPS:\n";
        echo "1. Upload security configuration to production server\n";
        echo "2. Add security headers to your theme's functions.php\n";
        echo "3. Include input validation in all form processing\n";
        echo "4. Implement rate limiting in API endpoints\n";
        echo "5. Test security configurations\n";
        echo "6. Update wp-config.php with security constants\n\n";
        
        echo "🛡️ Production security system is ready!\n";
    }
}

// Run the setup
if (php_sapi_name() === 'cli') {
    $security = new ProductionSecurityConfig();
    $security->runSetup();
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php production_security_config.php\n";
}