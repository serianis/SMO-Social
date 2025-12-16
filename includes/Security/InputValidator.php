<?php
namespace SMO_Social\Security;

/**
 * Enhanced Input Validator
 * 
 * Provides comprehensive input validation with security-focused rules.
 * 
 * @since 2.1.0
 */
class InputValidator
{
    private const MAX_STRING_LENGTH = 65535;
    private const MAX_EMAIL_LENGTH = 254;
    private const MAX_URL_LENGTH = 2048;
    private const MAX_FILENAME_LENGTH = 255;
    
    /**
     * Validate platform name
     */
    public static function validatePlatform(string $platform): bool
    {
        $valid_platforms = [
            'facebook', 'twitter', 'instagram', 'linkedin', 'tiktok', 'youtube',
            'snapchat', 'reddit', 'telegram', 'discord', 'whatsapp', 'pinterest',
            'medium', 'quora', 'vimeo', 'tumblr', 'myspace', 'vkontakte',
            'weibo', 'wechat', 'line', 'kakaotalk', 'snapchat', 'threads',
            'bereal', 'mastodon', 'bluesky', 'gab', 'parler', 'flipboard',
            'spotify', 'soundcloud'
        ];
        
        return in_array(strtolower(trim($platform)), $valid_platforms);
    }
    
    /**
     * Validate date range
     */
    public static function validateDateRange(int $days): bool
    {
        return $days >= 1 && $days <= 365;
    }
    
    /**
     * Validate export format
     */
    public static function validateExportFormat(string $format): bool
    {
        $valid_formats = ['csv', 'json', 'pdf', 'xml'];
        return in_array(strtolower(trim($format)), $valid_formats);
    }
    
    /**
     * Validate data type for exports
     */
    public static function validateDataType(string $type): bool
    {
        $valid_types = ['summary', 'detailed', 'raw', 'analytics'];
        return in_array(strtolower(trim($type)), $valid_types);
    }
    
    /**
     * Validate post ID
     */
    public static function validatePostId(mixed $post_id): bool
    {
        return is_numeric($post_id) && $post_id > 0 && $post_id <= PHP_INT_MAX;
    }
    
    /**
     * Validate user ID
     */
    public static function validateUserId(mixed $user_id): bool
    {
        return is_numeric($user_id) && $user_id >= 0 && $user_id <= PHP_INT_MAX;
    }
    
    /**
     * Validate pagination parameters
     */
    public static function validatePagination(int $page, int $per_page): bool
    {
        return $page >= 1 && $per_page >= 1 && $per_page <= 1000;
    }
    
    /**
     * Sanitize and validate content for social media
     */
    public static function sanitizeContent(string $content, int $max_length = 280): string
    {
        // Remove control characters except allowed ones
        $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);
        
        // Remove dangerous HTML/script content
        $dangerous_patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:/i',
            '/on\w+\s*=/i'
        ];
        
        $content = preg_replace($dangerous_patterns, '', $content);
        
        // Remove HTML tags but preserve basic formatting
        $content = strip_tags($content, '<br><p><strong><em>');
        
        // Limit length
        if (strlen($content) > $max_length) {
            $content = substr($content, 0, $max_length);
        }
        
        // Trim whitespace
        return trim($content);
    }
    
    /**
     * Validate and sanitize hashtag
     */
    public static function validateHashtag(string $hashtag): string
    {
        // Remove # if present
        $hashtag = ltrim($hashtag, '#');
        
        // Remove invalid characters
        $hashtag = preg_replace('/[^a-zA-Z0-9_\p{L}]/u', '', $hashtag);
        
        // Limit length
        if (strlen($hashtag) > 50) {
            $hashtag = substr($hashtag, 0, 50);
        }
        
        // Must not be empty and should start with letter
        if (empty($hashtag) || !preg_match('/^[a-zA-Z]/', $hashtag)) {
            return '';
        }
        
        return $hashtag;
    }
    
    /**
     * Validate and sanitize URL with strict rules
     */
    public static function validateUrl(string $url, array $options = []): string
    {
        $defaults = [
            'allowed_protocols' => ['http', 'https'],
            'require_tld' => true,
            'max_length' => self::MAX_URL_LENGTH
        ];
        
        $options = array_merge($defaults, $options);
        
        // Limit length
        if (strlen($url) > $options['max_length']) {
            return '';
        }
        
        // Basic URL sanitization
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (empty($url)) {
            return '';
        }
        
        // Parse URL
        $parsed = parse_url($url);
        if (!$parsed) {
            return '';
        }
        
        // Validate protocol
        if (isset($parsed['scheme']) && !in_array($parsed['scheme'], $options['allowed_protocols'])) {
            return '';
        }
        
        // Validate host
        if (isset($parsed['host'])) {
            $host = $parsed['host'];
            
            // Check for IP addresses (usually not allowed for security)
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return '';
            }
            
            // Check TLD requirement
            if ($options['require_tld'] && !preg_match('/\.[a-zA-Z]{2,}$/', $host)) {
                return '';
            }
            
            // Check for suspicious patterns
            if (preg_match('/\.(exe|bat|cmd|scr|zip|rar)$/', $host)) {
                return '';
            }
        }
        
        return $url;
    }
    
    /**
     * Validate and sanitize email with strict rules
     */
    public static function validateEmail(string $email): string
    {
        // Limit length
        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            return '';
        }
        
        // Remove leading/trailing whitespace
        $email = trim($email);
        
        // Basic email sanitization
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if (empty($email)) {
            return '';
        }
        
        // Strict email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        
        // Additional security checks
        $parsed = parse_url('mailto:' . $email);
        if (!$parsed || !isset($parsed['host'])) {
            return '';
        }
        
        $domain = $parsed['host'];
        
        // Check for suspicious domains
        $suspicious_patterns = [
            '/tempmail\./i',
            '/10minutemail\./i',
            '/throwaway\./i',
            '/disposable\./i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return '';
            }
        }
        
        return $email;
    }
    
    /**
     * Validate and sanitize filename with security checks
     */
    public static function validateFilename(string $filename, array $options = []): string
    {
        $defaults = [
            'max_length' => self::MAX_FILENAME_LENGTH,
            'allowed_extensions' => [],
            'prevent_path_traversal' => true,
            'prevent_hidden_files' => true
        ];
        
        $options = array_merge($defaults, $options);
        
        // Prevent path traversal
        if ($options['prevent_path_traversal']) {
            $filename = basename($filename);
        }
        
        // Remove dangerous characters
        $dangerous_chars = ['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0"];
        $filename = str_replace($dangerous_chars, '', $filename);
        
        // Remove control characters
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
        
        // Prevent hidden files
        if ($options['prevent_hidden_files']) {
            $filename = ltrim($filename, '.');
        }
        
        // Limit length
        if (strlen($filename) > $options['max_length']) {
            $filename = substr($filename, 0, $options['max_length']);
        }
        
        // Validate extension if specified
        if (!empty($options['allowed_extensions'])) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array(strtolower($extension), array_map('strtolower', $options['allowed_extensions']))) {
                return '';
            }
        }
        
        // Must not be empty
        if (empty(trim($filename))) {
            return '';
        }
        
        return $filename;
    }
    
    /**
     * Validate and sanitize username with strict rules
     */
    public static function validateUsername(string $username, array $options = []): string
    {
        $defaults = [
            'min_length' => 3,
            'max_length' => 32,
            'allow_special_chars' => false,
            'allow_unicode' => false
        ];
        
        $options = array_merge($defaults, $options);
        
        // Remove control characters
        $username = preg_replace('/[\x00-\x1F\x7F]/', '', $username);
        
        // Handle Unicode usernames
        if (!$options['allow_unicode']) {
            // ASCII only
            if ($options['allow_special_chars']) {
                $username = preg_replace('/[^a-zA-Z0-9_\-.@]/', '', $username);
            } else {
                $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            }
        } else {
            // Allow Unicode letters and numbers
            if ($options['allow_special_chars']) {
                $username = preg_replace('/[^\p{L}\p{N}_\-.@]/u', '', $username);
            } else {
                $username = preg_replace('/[^\p{L}\p{N}_]/u', '', $username);
            }
        }
        
        // Check length constraints
        $length = strlen($username);
        if ($length < $options['min_length'] || $length > $options['max_length']) {
            return '';
        }
        
        // Remove leading/trailing whitespace
        $username = trim($username);
        
        // Check for reserved usernames
        $reserved = ['admin', 'root', 'system', 'www', 'api', 'test', 'guest', 'null', 'undefined'];
        if (in_array(strtolower($username), $reserved)) {
            return '';
        }
        
        return $username;
    }
    
    /**
     * Validate JSON data
     */
    public static function validateJson(string $json): array
    {
        if (strlen($json) > self::MAX_STRING_LENGTH) {
            return [];
        }
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Validate IP address with security checks
     */
    public static function validateIp(string $ip): string
    {
        // Remove whitespace
        $ip = trim($ip);
        
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }
        
        // Check for private/local IPs (may not be suitable for all use cases)
        $private_ranges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16'
        ];
        
        foreach ($private_ranges as $range) {
            if (self::ipInRange($ip, $range)) {
                return ''; // Could be modified based on requirements
            }
        }
        
        return $ip;
    }
    
    /**
     * Check if IP is in range
     */
    private static function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $bits) = explode('/', $range);
        $ip_decimal = ip2long($ip);
        $subnet_decimal = ip2long($subnet);
        $mask_decimal = -1 << (32 - $bits);
        
        return ($ip_decimal & $mask_decimal) == ($subnet_decimal & $mask_decimal);
    }
    
    /**
     * Comprehensive form validation
     */
    public static function validateForm(array $data, array $rules): array
    {
        $errors = [];
        $sanitized_data = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                if ($rule['required'] ?? false) {
                    $errors[$field] = 'This field is required';
                }
                continue;
            }
            
            $value = $data[$field];
            $field_errors = [];
            
            // Skip validation for empty values unless required
            if (empty($value) && !($rule['required'] ?? false)) {
                $sanitized_data[$field] = $value;
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                $sanitized_value = self::validateByType($value, $rule['type'], $rule['options'] ?? []);
                if ($sanitized_value === false) {
                    $field_errors[] = 'Invalid format for ' . $field;
                } else {
                    $value = $sanitized_value;
                }
            }
            
            // Length validation
            if (isset($rule['min_length']) && strlen((string)$value) < $rule['min_length']) {
                $field_errors[] = 'Minimum length for ' . $field . ' is ' . $rule['min_length'];
            }
            
            if (isset($rule['max_length']) && strlen((string)$value) > $rule['max_length']) {
                $field_errors[] = 'Maximum length for ' . $field . ' is ' . $rule['max_length'];
            }
            
            // Custom validation callback
            if (isset($rule['callback']) && is_callable($rule['callback'])) {
                $callback_result = call_user_func($rule['callback'], $value, $data);
                if ($callback_result !== true) {
                    $field_errors[] = is_string($callback_result) ? $callback_result : 'Invalid value for ' . $field;
                }
            }
            
            if (!empty($field_errors)) {
                $errors[$field] = implode(', ', $field_errors);
            } else {
                $sanitized_data[$field] = $value;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_data' => $sanitized_data
        ];
    }
    
    /**
     * Validate by type
     */
    private static function validateByType(mixed $value, string $type, array $options = []): mixed
    {
        switch ($type) {
            case 'email':
                return self::validateEmail((string)$value);
                
            case 'url':
                return self::validateUrl((string)$value, $options);
                
            case 'filename':
                return self::validateFilename((string)$value, $options);
                
            case 'username':
                return self::validateUsername((string)$value, $options);
                
            case 'platform':
                return self::validatePlatform((string)$value) ? $value : false;
                
            case 'date_range':
                return self::validateDateRange((int)$value) ? $value : false;
                
            case 'content':
                return self::sanitizeContent((string)$value, $options['max_length'] ?? 280);
                
            case 'hashtag':
                return self::validateHashtag((string)$value);
                
            default:
                return $value; // No validation
        }
    }
}
