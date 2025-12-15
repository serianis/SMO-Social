<?php
namespace SMO_Social\WordPress;

/**
 * DocumentationManager - Manages coding standards and validation rules
 * 
 * Provides coding standards configuration and code validation functionality
 * for WordPress plugin development best practices.
 */
class DocumentationManager {
    
    private $coding_standards;
    
    public function __construct() {
        $this->initializeCodingStandards();
    }
    
    /**
     * Initialize coding standards configuration
     */
    private function initializeCodingStandards() {
        $this->coding_standards = [
            'phpcs' => [
                'standard' => 'WordPress-Core',
                'sniffs' => [
                    'WordPress.WhiteSpace.ControlStructureSpacing',
                    'WordPress.NamingConventions.ValidFunctionName',
                    'WordPress.NamingConventions.ValidVariableName',
                    'WordPress.Security.EscapeOutput',
                    'WordPress.Security.ValidatedSanitizedInput',
                    'WordPress.VIP.SuperGlobalInputUsage',
                    'WordPress.VIP.DirectDatabaseQuery'
                ]
            ],
            'eslint' => [
                'config' => [
                    'env' => [
                        'browser' => true,
                        'es6' => true,
                        'jquery' => true
                    ],
                    'extends' => [
                        'eslint:recommended',
                        '@wordpress/eslint-plugin'
                    ],
                    'rules' => [
                        'no-console' => 'warn',
                        'no-unused-vars' => 'error',
                        'prefer-const' => 'error',
                        'no-var' => 'error'
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get coding standards configuration
     * 
     * @return array Coding standards configuration
     */
    public function getCodingStandardsConfig() {
        return $this->coding_standards;
    }
    
    /**
     * Validate code against WordPress coding standards
     * 
     * @param string $code PHP code to validate
     * @return array Array of violations found
     */
    public function validateCode($code) {
        $violations = [];
        
        // Check for deprecated WordPress functions
        $deprecated_functions = [
            'wp_get_http' => 'Use wp_remote_get() instead',
            'screen_icon' => 'Screen icons are no longer needed in WordPress 3.8+',
            'wp_tiny_mce' => 'Use wp_editor() instead',
            'register_sidebar_widget' => 'Use wp_register_sidebar_widget() instead',
            'register_widget_control' => 'Use wp_register_widget_control() instead',
            'wp_get_http' => 'Use wp_remote_get() instead',
            'get_bloginfo' => 'Consider using home_url() or site_url() for those specific values',
        ];
        
        foreach ($deprecated_functions as $function => $replacement) {
            if (preg_match('/\b' . preg_quote($function) . '\b/', $code)) {
                $violations[] = [
                    'type' => 'deprecation',
                    'severity' => 'medium',
                    'rule' => 'deprecated_function',
                    'message' => "Function '{$function}' is deprecated",
                    'suggestion' => $replacement
                ];
            }
        }
        
        // Check for direct database queries without preparation
        $direct_query_patterns = [
            '/\$wpdb->query\s*\(\s*["\'][^"\']*SELECT[^"\']*["\']\s*\)/i',
            '/\$wpdb->get_var\s*\(\s*["\'][^"\']*INSERT[^"\']*["\']\s*\)/i',
            '/\$wpdb->get_row\s*\(\s*["\'][^"\']*INSERT[^"\']*["\']\s*\)/i',
            '/\$wpdb->get_results\s*\(\s*["\'][^"\']*INSERT[^"\']*["\']\s*\)/i'
        ];
        
        foreach ($direct_query_patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                $violations[] = [
                    'type' => 'security',
                    'severity' => 'high',
                    'rule' => 'unsafe_database_query',
                    'message' => 'Direct database queries should use prepared statements',
                    'suggestion' => 'Use $wpdb->prepare(), $wpdb->insert(), $wpdb->update(), or $wpdb->delete() instead'
                ];
            }
        }
        
        // Check for missing text domain in translation functions
        $translation_functions = [
            '__\s*\(\s*["\'][^"\']+["\']\s*\)',
            '_e\s*\(\s*["\'][^"\']+["\']\s*\)',
            '_n\s*\(\s*["\'][^"\']+["\'],\s*["\'][^"\']+["\']\s*\)',
            '_nx\s*\(\s*["\'][^"\']+["\'],\s*["\'][^"\']+["\'],\s*["\'][^"\']+["\']\s*\)',
            '_x\s*\(\s*["\'][^"\']+["\'],\s*["\'][^"\']+["\']\s*\)'
        ];
        
        foreach ($translation_functions as $function) {
            if (preg_match('/' . $function . '/', $code)) {
                // Check if it doesn't have a text domain parameter
                if (!preg_match('/' . $function . ',\s*["\'][^"\']+["\']\s*\)/', $code)) {
                    $violations[] = [
                        'type' => 'internationalization',
                        'severity' => 'medium',
                        'rule' => 'missing_text_domain',
                        'message' => 'Translation functions should include text domain',
                        'suggestion' => 'Add text domain as second parameter: __("Text", "text-domain")'
                    ];
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Get coding standards documentation
     * 
     * @param string $type Type of documentation ('php' or 'js')
     * @return string Documentation text
     */
    public function getDocumentation($type = 'php') {
        if ($type === 'php') {
            return "WordPress PHP Coding Standards:\n" .
                   "- Use proper escaping functions (esc_html, esc_attr, esc_url, etc.)\n" .
                   "- Validate and sanitize all user inputs\n" .
                   "- Use prepared statements for database queries\n" .
                   "- Check user capabilities before performing actions\n" .
                   "- Include nonces in forms and AJAX requests\n" .
                   "- Use proper text domains in translation functions\n" .
                   "- Follow WordPress naming conventions\n";
        } elseif ($type === 'js') {
            return "WordPress JavaScript Coding Standards:\n" .
                   "- Use modern DOM manipulation methods\n" .
                   "- Remove console.log statements from production\n" .
                   "- Use WordPress coding standards and ESLint\n" .
                   "- Follow WordPress JavaScript best practices\n";
        }
        
        return '';
    }
}
