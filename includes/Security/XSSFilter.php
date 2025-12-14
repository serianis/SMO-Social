<?php
namespace SMO_Social\Security;

class XSSFilter {
    private $allowed_html_tags;
    private $dangerous_patterns;
    private $attribute_filters;
    
    public function __construct() {
        $this->initialize_filters();
    }
    
    private function initialize_filters() {
        $this->allowed_html_tags = '<p><br><strong><em><a><img><ul><li><ol><h1><h2><h3><h4><h5><h6><blockquote><code><pre>';
        
        $this->dangerous_patterns = array(
            // JavaScript execution contexts
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/is',
            '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/is',
            '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/is',
            '/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/is',
            '/<input\b[^<>]*(?:[^<>]|)*\/>/is',
            '/<textarea\b[^<]*(?:(?!<\/textarea>)<[^<]*)*<\/textarea>/is',
            '/<select\b[^<]*(?:(?!<\/select>)<[^<]*)*<\/select>/is',
            
            // Event handlers
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/onmouseover\s*=/i',
            '/onmouseout\s*=/i',
            '/onfocus\s*=/i',
            '/onblur\s*=/i',
            '/onsubmit\s*=/i',
            '/onchange\s*=/i',
            
            // JavaScript protocols
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/data\s*:/i',
            '/livescript\s*:/i',
            
            // CSS expressions
            '/expression\s*\(/i',
            '/binding\s*\(/i',
            
            // Meta refresh and redirects
            '/<meta[^>]+http-equiv\s*=\s*["\']?refresh["\']?/i',
            
            // Base64 encoded scripts
            '/data:\s*text\/html/i',
        );
        
        $this->attribute_filters = array(
            'href' => array('http://', 'https://', 'mailto:', '/', '#'),
            'src' => array('http://', 'https://', '/', 'data:image/'),
            'action' => array('http://', 'https://', '/'),
            'cite' => array('http://', 'https://', '/')
        );
    }
    
    public function filter_html($content, $options = array()) {
        $default_options = array(
            'allow_tags' => true,
            'allow_attributes' => true,
            'remove_comments' => true,
            'max_length' => 0
        );
        
        $options = array_merge($default_options, $options);
        
        // Remove dangerous patterns
        $filtered_content = $this->remove_dangerous_patterns($content);
        
        // Clean HTML tags if allowed
        if ($options['allow_tags']) {
            $filtered_content = $this->clean_html_tags($filtered_content, $options);
        }
        
        // Clean attributes if allowed
        if ($options['allow_attributes']) {
            $filtered_content = $this->clean_attributes($filtered_content);
        }
        
        // Remove HTML comments
        if ($options['remove_comments']) {
            $filtered_content = $this->remove_comments($filtered_content);
        }
        
        // Truncate if max length specified
        if ($options['max_length'] > 0 && strlen($filtered_content) > $options['max_length']) {
            $filtered_content = substr($filtered_content, 0, $options['max_length']);
        }
        
        return $filtered_content;
    }
    
    public function remove_dangerous_patterns($content) {
        return preg_replace($this->dangerous_patterns, '', $content);
    }
    
    public function clean_html_tags($content, $options) {
        if (isset($options['allowed_tags'])) {
            return strip_tags($content, $options['allowed_tags']);
        }
        
        return strip_tags($content, $this->allowed_html_tags);
    }
    
    public function clean_attributes($content) {
        // Remove dangerous attributes
        $content = preg_replace('/\s+(on\w+)\s*=\s*["\'][^"\']*["\']/i', '', $content);
        
        // Validate and clean allowed attributes
        foreach ($this->attribute_filters as $attribute => $allowed_prefixes) {
            $pattern = '/(' . $attribute . ')\s*=\s*["\']([^"\']+)["\']/i';
            
            $content = preg_replace_callback($pattern, function($matches) use ($allowed_prefixes) {
                $attribute = $matches[1];
                $value = $matches[2];
                
                // Check if value starts with allowed prefixes
                foreach ($allowed_prefixes as $prefix) {
                    if (stripos($value, $prefix) === 0) {
                        return ' ' . $attribute . '="' . $value . '"';
                    }
                }
                
                // Remove the attribute if it doesn't start with allowed prefix
                return '';
            }, $content);
        }
        
        return $content;
    }
    
    public function remove_comments($content) {
        return preg_replace('/<!--.*?-->/', '', $content);
    }
    
    public function filter_json($json_string) {
        // Decode JSON to validate structure
        $decoded = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
        
        // Recursively filter the decoded data
        $filtered = $this->filter_array_recursive($decoded);
        
        return json_encode($filtered);
    }
    
    public function filter_url($url) {
        // Remove dangerous URL schemes
        $dangerous_schemes = array('javascript:', 'data:', 'vbscript:', 'file:', 'ftp:');
        
        foreach ($dangerous_schemes as $scheme) {
            if (stripos($url, $scheme) === 0) {
                return '';
            }
        }
        
        // Validate URL format
        $filtered_url = filter_var($url, FILTER_SANITIZE_URL);
        
        return $filtered_url;
    }
    
    public function filter_sql($sql) {
        // Remove SQL injection patterns
        $dangerous_patterns = array(
            '/(\'|"|`|;|--|\/\*|\*\/)/',
            '/(union|select|insert|update|delete|drop|create|alter|exec|execute)/i',
            '/(information_schema|mysql\.|pg_)/i'
        );
        
        $filtered_sql = preg_replace($dangerous_patterns, '', $sql);
        
        return $filtered_sql;
    }
    
    public function filter_filename($filename) {
        // Remove dangerous characters
        $dangerous_chars = array('\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0");
        $filtered_filename = str_replace($dangerous_chars, '', $filename);
        
        // Remove directory traversal attempts
        $filtered_filename = str_replace(array('../', '..\\', './', '.\\'), '', $filtered_filename);
        
        // Remove multiple dots and dashes
        $filtered_filename = preg_replace('/[\.]+/', '.', $filtered_filename);
        $filtered_filename = preg_replace('/[\-]+/', '-', $filtered_filename);
        
        return $filtered_filename;
    }
    
    public function filter_array_recursive($data) {
        if (is_array($data)) {
            $filtered = array();
            foreach ($data as $key => $value) {
                $filtered[$key] = $this->filter_array_recursive($value);
            }
            return $filtered;
        } elseif (is_string($data)) {
            return $this->filter_html($data);
        }
        
        return $data;
    }
    
    public function is_safe_content($content, $content_type = 'html') {
        $original_content = $content;
        
        switch ($content_type) {
            case 'html':
                $filtered_content = $this->filter_html($content);
                break;
            case 'json':
                $filtered_content = $this->filter_json($content);
                break;
            case 'url':
                $filtered_content = $this->filter_url($content);
                break;
            default:
                $filtered_content = $this->filter_html($content);
        }
        
        return $original_content === $filtered_content;
    }
    
    public function sanitize_for_output($content, $context = 'html') {
        switch ($context) {
            case 'html':
                return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            case 'js':
                return json_encode($content);
            case 'css':
                return preg_replace('/[^a-zA-Z0-9\s\-_#\.]/', '', $content);
            case 'url':
                return urlencode($content);
            default:
                return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Alias method for test compatibility
    public function filter($content, $options = array()) {
        return $this->filter_html($content, $options);
    }
}
