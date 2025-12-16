<?php
namespace SMO_Social\WordPress;

/**
 * WordPress Code Validator
 * Validates code against WordPress coding standards and deprecated functions
 */
class CodeValidator {
    
    private $documentation_manager;
    private $linting_config;
    
    public function __construct() {
        $this->documentation_manager = new DocumentationManager();
        $this->linting_config = $this->documentation_manager->getCodingStandardsConfig();
    }
    
    /**
     * Validate code for WordPress best practices
     */
    public function validateCode($code, $file_type = 'php') {
        $violations = [];
        
        // Check for deprecated functions
        $violations = array_merge($violations, $this->documentation_manager->validateCode($code));
        
        // Validate PHP code
        if ($file_type === 'php') {
            $violations = array_merge($violations, $this->validatePHPCode($code));
        }
        
        // Validate JavaScript code
        elseif ($file_type === 'js') {
            $violations = array_merge($violations, $this->validateJSCode($code));
        }
        
        return $violations;
    }
    
    /**
     * Validate PHP code against WordPress standards
     */
    private function validatePHPCode($code) {
        $violations = [];
        
        // Check for proper escaping
        $this->checkEscaping($code, $violations);
        
        // Check for proper capability checks
        $this->checkCapabilities($code, $violations);
        
        // Check for proper nonce usage
        $this->checkNonces($code, $violations);
        
        // Check for proper database queries
        $this->checkDatabaseQueries($code, $violations);
        
        // Check for proper text domain usage
        $this->checkTextDomain($code, $violations);
        
        return $violations;
    }
    
    /**
     * Check for proper escaping
     */
    private function checkEscaping($code, &$violations) {
        $unescaped_patterns = [
            '/echo\s+\$[a-zA-Z_][a-zA-Z0-9_]*/',
            '/print\s+\$[a-zA-Z_][a-zA-Z0-9_]*/',
            '/printf\s+\$[a-zA-Z_][a-zA-Z0-9_]*/',
            '/sprintf\s+\$[a-zA-Z_][a-zA-Z0-9_]*/'
        ];
        
        $escape_functions = [
            'esc_html', 'esc_attr', 'esc_url', 'esc_textarea',
            'wp_kses', 'wp_kses_post', 'wp_kses_data', 'sanitize_text_field',
            'sanitize_title', 'sanitize_email', 'sanitize_file_name'
        ];
        
        foreach ($unescaped_patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                // Check if it's already escaped
                $escaped = false;
                foreach ($escape_functions as $function) {
                    if (preg_match('/' . preg_quote($function) . '\s*\(/', $code)) {
                        $escaped = true;
                        break;
                    }
                }
                
                if (!$escaped) {
                    $violations[] = [
                        'type' => 'security',
                        'severity' => 'high',
                        'rule' => 'missing_escape',
                        'message' => 'Output should be properly escaped to prevent XSS vulnerabilities',
                        'suggestion' => 'Use esc_html(), esc_attr(), esc_url(), or other appropriate escaping functions'
                    ];
                }
            }
        }
    }
    
    /**
     * Check for proper capability checks
     */
    private function checkCapabilities($code, &$violations) {
        $admin_patterns = [
            '/add_menu_page\s*\(/',
            '/add_submenu_page\s*\(/',
            '/admin_menu\s/',
            '/admin_init\s/',
            '/current_user_can\s*\(/'
        ];
        
        $has_capability_check = false;
        foreach ($admin_patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                if (preg_match('/current_user_can\s*\(/', $code)) {
                    $has_capability_check = true;
                    break;
                }
            }
        }
        
        if (!$has_capability_check && (preg_match('/add_menu_page|add_submenu_page/', $code))) {
            $violations[] = [
                'type' => 'security',
                'severity' => 'medium',
                'rule' => 'missing_capability_check',
                'message' => 'Admin pages should check user capabilities',
                'suggestion' => 'Use current_user_can() to verify user permissions before adding admin menus'
            ];
        }
    }
    
    /**
     * Check for proper nonce usage
     */
    private function checkNonces($code, &$violations) {
        $form_patterns = [
            '/<form/',
            '/wp_insert_post\s*\(/',
            '/update_option\s*\(/',
            '/delete_option\s*\(/',
            '/wp_update_user\s*\(/'
        ];
        
        $nonce_functions = [
            'wp_nonce_field', 'wp_nonce_url', 'wp_verify_nonce',
            'check_admin_referer', 'wp_create_nonce'
        ];
        
        $has_nonce = false;
        foreach ($nonce_functions as $function) {
            if (preg_match('/' . preg_quote($function) . '\s*\(/', $code)) {
                $has_nonce = true;
                break;
            }
        }
        
        foreach ($form_patterns as $pattern) {
            if (preg_match($pattern, $code) && !$has_nonce) {
                $violations[] = [
                    'type' => 'security',
                    'severity' => 'high',
                    'rule' => 'missing_nonce',
                    'message' => 'Forms and data modifications should include nonces for security',
                    'suggestion' => 'Use wp_nonce_field() in forms and wp_verify_nonce() to validate requests'
                ];
                break;
            }
        }
    }
    
    /**
     * Check for proper database queries
     */
    private function checkDatabaseQueries($code, &$violations) {
        $direct_query_patterns = [
            '/\$wpdb->query\s*\([^)]*SELECT/is',
            '/\$wpdb->get_var\s*\([^)]*INSERT|UPDATE|DELETE/is',
            '/\$wpdb->get_row\s*\([^)]*INSERT|UPDATE|DELETE/is',
            '/\$wpdb->get_results\s*\([^)]*INSERT|UPDATE|DELETE/is'
        ];
        
        $prepared_patterns = [
            '/\$wpdb->prepare\s*\(/',
            '/\$wpdb->insert\s*\(/',
            '/\$wpdb->update\s*\(/',
            '/\$wpdb->delete\s*\(/'
        ];
        
        foreach ($direct_query_patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                // Check if it's prepared
                $is_prepared = false;
                foreach ($prepared_patterns as $prepared) {
                    if (preg_match($prepared, $code)) {
                        $is_prepared = true;
                        break;
                    }
                }
                
                if (!$is_prepared) {
                    $violations[] = [
                        'type' => 'security',
                        'severity' => 'high',
                        'rule' => 'unsafe_database_query',
                        'message' => 'Database queries should use prepared statements to prevent SQL injection',
                        'suggestion' => 'Use \$wpdb->prepare(), \$wpdb->insert(), \$wpdb->update(), or \$wpdb->delete() instead of direct queries'
                    ];
                }
            }
        }
    }
    
    /**
     * Check for proper text domain usage
     */
    private function checkTextDomain($code, &$violations) {
        $i18n_functions = [
            '__\s*\(',
            '_e\s*\(',
            '_n\s*\(',
            '_nx\s*\(',
            '_x\s*\('
        ];
        
        foreach ($i18n_functions as $function) {
            if (preg_match('/' . $function . '/', $code)) {
                // Check if text domain is specified
                if (!preg_match('/' . $function . '[^)]*,\s*__[a-zA-Z_-]/', $code)) {
                    $violations[] = [
                        'type' => 'internationalization',
                        'severity' => 'medium',
                        'rule' => 'missing_text_domain',
                        'message' => 'Translation functions should include text domain',
                        'suggestion' => 'Add text domain as second parameter: __("\'Text\'", "text-domain")'
                    ];
                }
            }
        }
    }
    
    /**
     * Validate JavaScript code against WordPress standards
     */
    private function validateJSCode($code) {
        $violations = [];
        
        // Check for console.log usage
        if (preg_match('/console\.(log|warn|error)/', $code)) {
            $violations[] = [
                'type' => 'development',
                'severity' => 'low',
                'rule' => 'console_usage',
                'message' => 'Console statements should be removed from production code',
                'suggestion' => 'Remove console.log statements or use proper debugging methods'
            ];
        }
        
        // Check for jQuery usage
        if (preg_match('/\$\(document\)\.ready/', $code)) {
            $violations[] = [
                'type' => 'best_practice',
                'severity' => 'medium',
                'rule' => 'jquery_ready',
                'message' => 'Use modern DOM ready methods instead of jQuery',
                'suggestion' => 'Use document.addEventListener("DOMContentLoaded", function() {}) instead'
            ];
        }
        
        return $violations;
    }
    
    /**
     * Run PHPCS validation
     */
    public function runPHPCS($file_path) {
        if (!file_exists($file_path)) {
            return ['error' => 'File not found'];
        }
        
        $config = $this->linting_config['phpcs'];
        $command = "phpcs --standard={$config['standard']} --report=json {$file_path}";
        
        $output = shell_exec($command);
        
        if ($output === null) {
            return ['error' => 'PHPCS execution failed'];
        }
        
        return json_decode($output, true) ?: ['error' => 'Invalid JSON output'];
    }
    
    /**
     * Run ESLint validation
     */
    public function runESLint($file_path) {
        if (!file_exists($file_path)) {
            return ['error' => 'File not found'];
        }
        
        $config = $this->linting_config['eslint']['config'];
        $config_file = sys_get_temp_dir() . '/eslint-config-' . uniqid() . '.json';
        
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
        
        $command = "eslint --config {$config_file} --format json {$file_path}";
        $output = shell_exec($command);
        
        // Clean up temp file
        unlink($config_file);
        
        if ($output === null) {
            return ['error' => 'ESLint execution failed'];
        }
        
        return json_decode($output, true) ?: ['error' => 'Invalid JSON output'];
    }
}
