<?php
namespace SMO_Social\Community;

/**
 * ValidationPipeline - Automated validation system for drivers and templates
 * 
 * Runs automated tests including JSON validation, endpoint reachability,
 * and rate limit compliance checking.
 */
class ValidationPipeline {
    
    private $validation_results;
    private $test_timeout = 30; // seconds
    
    public function __construct() {
        $this->validation_results = array();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_run_validation', array($this, 'ajax_run_validation'));
        add_action('wp_ajax_smo_get_validation_results', array($this, 'ajax_get_validation_results'));
    }
    
    /**
     * Run complete validation suite on a driver or template
     * 
     * @param string $file_path Path to JSON file
     * @param string $type 'driver' or 'template'
     * @return array Validation results
     */
    public function run_validation($file_path, $type) {
        $results = array(
            'file_path' => $file_path,
            'type' => $type,
            'timestamp' => current_time('mysql'),
            'overall_status' => 'pending',
            'tests' => array(),
            'errors' => array(),
            'warnings' => array(),
            'score' => 0
        );
        
        try {
            // Test 1: JSON Structure Validation
            $json_result = $this->validate_json_structure($file_path);
            $results['tests']['json_structure'] = $json_result;
            
            // Test 2: Schema Compliance
            $schema_result = $this->validate_schema_compliance($file_path, $type);
            $results['tests']['schema_compliance'] = $schema_result;
            
            // Test 3: Endpoint Reachability (for drivers only)
            if ($type === 'driver') {
                $endpoint_result = $this->validate_endpoint_reachability($file_path);
                $results['tests']['endpoint_reachability'] = $endpoint_result;
            }
            
            // Test 4: Rate Limit Compliance
            $rate_limit_result = $this->validate_rate_limit_compliance($file_path);
            $results['tests']['rate_limit_compliance'] = $rate_limit_result;
            
            // Test 5: Security Validation
            $security_result = $this->validate_security($file_path);
            $results['tests']['security'] = $security_result;
            
            // Test 6: Platform Compatibility
            $compatibility_result = $this->validate_platform_compatibility($file_path, $type);
            $results['tests']['platform_compatibility'] = $compatibility_result;
            
            // Calculate overall score
            $results['score'] = $this->calculate_validation_score($results['tests']);
            
            // Determine overall status
            $results['overall_status'] = $this->determine_overall_status($results);
            
            // Store results
            $this->store_validation_results($file_path, $results);
            
        } catch (\Exception $e) {
            $results['overall_status'] = 'failed';
            $results['errors'][] = 'Validation pipeline error: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Validate JSON structure and syntax
     * 
     * @param string $file_path Path to JSON file
     * @return array Validation result
     */
    private function validate_json_structure($file_path) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        try {
            // Check if file exists
            if (!file_exists($file_path)) {
                $result['status'] = 'failed';
                $result['message'] = 'File not found';
                return $result;
            }
            
            // Read file content
            $content = file_get_contents($file_path);
            if ($content === false) {
                $result['status'] = 'failed';
                $result['message'] = 'Unable to read file';
                return $result;
            }
            
            // Parse JSON
            $json_data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['status'] = 'failed';
                $result['message'] = 'Invalid JSON syntax: ' . json_last_error_msg();
                return $result;
            }
            
            // Validate JSON is an object/array, not null
            if ($json_data === null) {
                $result['status'] = 'failed';
                $result['message'] = 'JSON parses to null';
                return $result;
            }
            
            $result['status'] = 'passed';
            $result['message'] = 'JSON structure is valid';
            $result['details'] = array(
                'json_size' => strlen($content),
                'parsed_size' => count($json_data, COUNT_RECURSIVE)
            );
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = 'JSON validation error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate schema compliance based on type
     * 
     * @param string $file_path Path to JSON file
     * @param string $type 'driver' or 'template'
     * @return array Validation result
     */
    private function validate_schema_compliance($file_path, $type) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        try {
            $content = file_get_contents($file_path);
            $data = json_decode($content, true);
            
            if ($type === 'driver') {
                return $this->validate_driver_schema($data);
            } elseif ($type === 'template') {
                return $this->validate_template_schema($data);
            }
            
            $result['status'] = 'failed';
            $result['message'] = 'Unknown type: ' . $type;
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = 'Schema validation error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate driver schema compliance
     * 
     * @param array $data Driver JSON data
     * @return array Validation result
     */
    private function validate_driver_schema($data) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        $required_fields = array('driver_id', 'name', 'type', 'api_interaction');
        $missing_fields = array();
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $result['status'] = 'failed';
            $result['message'] = 'Missing required fields: ' . implode(', ', $missing_fields);
            return $result;
        }
        
        // Validate driver_id format
        if (!preg_match('/^[a-z0-9_]+$/', $data['driver_id'])) {
            $result['status'] = 'failed';
            $result['message'] = 'Invalid driver_id format (must be lowercase alphanumeric with underscores)';
            return $result;
        }
        
        // Validate API interaction structure
        if (isset($data['api_interaction'])) {
            $api_validation = $this->validate_api_interaction($data['api_interaction']);
            if ($api_validation['status'] === 'failed') {
                $result['status'] = 'failed';
                $result['message'] = 'Invalid API interaction structure: ' . $api_validation['message'];
                return $result;
            }
        }
        
        $result['status'] = 'passed';
        $result['message'] = 'Driver schema is compliant';
        $result['details'] = array(
            'driver_id' => $data['driver_id'],
            'name' => $data['name'],
            'type' => $data['type']
        );
        
        return $result;
    }
    
    /**
     * Validate template schema compliance
     * 
     * @param array $data Template JSON data
     * @return array Validation result
     */
    private function validate_template_schema($data) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        $required_fields = array('template_id', 'name', 'version', 'description', 'author', 'posts');
        $missing_fields = array();
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $result['status'] = 'failed';
            $result['message'] = 'Missing required fields: ' . implode(', ', $missing_fields);
            return $result;
        }
        
        // Validate template_id format
        if (!preg_match('/^[a-z0-9_]+$/', $data['template_id'])) {
            $result['status'] = 'failed';
            $result['message'] = 'Invalid template_id format (must be lowercase alphanumeric with underscores)';
            return $result;
        }
        
        // Validate posts structure
        if (!is_array($data['posts']) || empty($data['posts'])) {
            $result['status'] = 'failed';
            $result['message'] = 'Posts must be a non-empty array';
            return $result;
        }
        
        // Validate each post
        foreach ($data['posts'] as $index => $post) {
            $post_validation = $this->validate_post_structure($post, $index);
            if ($post_validation['status'] === 'failed') {
                $result['status'] = 'failed';
                $result['message'] = "Invalid post at index {$index}: " . $post_validation['message'];
                return $result;
            }
        }
        
        $result['status'] = 'passed';
        $result['message'] = 'Template schema is compliant';
        $result['details'] = array(
            'template_id' => $data['template_id'],
            'name' => $data['name'],
            'posts_count' => count($data['posts'])
        );
        
        return $result;
    }
    
    /**
     * Validate post structure within templates
     * 
     * @param array $post Post data
     * @param int $index Post index
     * @return array Validation result
     */
    private function validate_post_structure($post, $index) {
        $required_fields = array('day', 'platforms', 'content_template');
        $missing_fields = array();
        
        foreach ($required_fields as $field) {
            if (!isset($post[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            return array(
                'status' => 'failed',
                'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
            );
        }
        
        // Validate platforms array
        if (!is_array($post['platforms']) || empty($post['platforms'])) {
            return array(
                'status' => 'failed',
                'message' => 'Platforms must be a non-empty array'
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => 'Post structure is valid'
        );
    }
    
    /**
     * Validate API interaction structure
     * 
     * @param array $api_interaction API interaction data
     * @return array Validation result
     */
    private function validate_api_interaction($api_interaction) {
        if (!isset($api_interaction['post_endpoint'])) {
            return array(
                'status' => 'failed',
                'message' => 'Missing post_endpoint in API interaction'
            );
        }
        
        $post_endpoint = $api_interaction['post_endpoint'];
        if (!isset($post_endpoint['url']) || !isset($post_endpoint['method'])) {
            return array(
                'status' => 'failed',
                'message' => 'Post endpoint must have url and method'
            );
        }
        
        // Validate URL format
        if (!filter_var($post_endpoint['url'], FILTER_VALIDATE_URL)) {
            return array(
                'status' => 'failed',
                'message' => 'Invalid URL format in post endpoint'
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => 'API interaction structure is valid'
        );
    }
    
    /**
     * Validate endpoint reachability
     * 
     * @param string $file_path Path to driver JSON file
     * @return array Validation result
     */
    private function validate_endpoint_reachability($file_path) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        try {
            $content = file_get_contents($file_path);
            $data = json_decode($content, true);
            
            if (!isset($data['api_interaction']['post_endpoint']['url'])) {
                $result['status'] = 'skipped';
                $result['message'] = 'No endpoint URL found';
                return $result;
            }
            
            $endpoint_url = $data['api_interaction']['post_endpoint']['url'];
            
            // Test endpoint with a simple OPTIONS or HEAD request
            $response = wp_remote_request($endpoint_url, array(
                'method' => 'OPTIONS',
                'timeout' => $this->test_timeout,
                'headers' => array(
                    'User-Agent' => 'SMO-Social-Validator/1.0'
                )
            ));
            
            if (is_wp_error($response)) {
                $result['status'] = 'warning';
                $result['message'] = 'Endpoint not reachable: ' . $response->get_error_message();
                $result['details']['error'] = $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                
                // 405 Method Not Allowed is OK (means server is running)
                // 401/403 are OK (means authentication required)
                // 404 might be OK (endpoint doesn't exist, but server is running)
                if (in_array($status_code, array(200, 201, 202, 400, 401, 403, 404, 405, 406, 429))) {
                    $result['status'] = 'passed';
                    $result['message'] = 'Endpoint is reachable (HTTP ' . $status_code . ')';
                } else {
                    $result['status'] = 'warning';
                    $result['message'] = 'Unexpected response code: ' . $status_code;
                }
                
                $result['details']['status_code'] = $status_code;
            }
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = 'Endpoint validation error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate rate limit compliance
     * 
     * @param string $file_path Path to JSON file
     * @return array Validation result
     */
    private function validate_rate_limit_compliance($file_path) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        try {
            $content = file_get_contents($file_path);
            $data = json_decode($content, true);
            
            // Check for rate limit information in driver
            if (isset($data['rate_limits'])) {
                $rate_limits = $data['rate_limits'];
                $warnings = array();
                
                // Check if rate limits are reasonable
                if (isset($rate_limits['posts_per_hour']) && $rate_limits['posts_per_hour'] > 1000) {
                    $warnings[] = 'Extremely high rate limit detected (posts_per_hour > 1000)';
                }
                
                if (isset($rate_limits['posts_per_day']) && $rate_limits['posts_per_day'] > 10000) {
                    $warnings[] = 'Extremely high rate limit detected (posts_per_day > 10000)';
                }
                
                $result['status'] = 'passed';
                $result['message'] = empty($warnings) ? 'Rate limits are reasonable' : 'Rate limits need review';
                $result['details']['rate_limits'] = $rate_limits;
                
                if (!empty($warnings)) {
                    $result['details']['warnings'] = $warnings;
                }
            } else {
                $result['status'] = 'warning';
                $result['message'] = 'No rate limit information found';
            }
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = 'Rate limit validation error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate security aspects
     * 
     * @param string $file_path Path to JSON file
     * @return array Validation result
     */
    private function validate_security($file_path) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        try {
            $content = file_get_contents($file_path);
            $data = json_decode($content, true);
            
            $warnings = array();
            
            // Check for hardcoded credentials (basic pattern matching)
            $json_string = json_encode($data);
            
            // Look for potential credential patterns
            $credential_patterns = array(
                '/"[^"]*key[^"]*"\s*:\s*"[^"]*[Aa]pi[Kk]ey[^"]*"/',
                '/"[^"]*token[^"]*"\s*:\s*"[^"]*[Tt]oken[^"]*"/',
                '/"[^"]*password[^"]*"\s*:\s*"[^"]*[^"]*"/',
                '/"[^"]*secret[^"]*"\s*:\s*"[^"]*[^"]*"/'
            );
            
            foreach ($credential_patterns as $pattern) {
                if (preg_match($pattern, $json_string)) {
                    $warnings[] = 'Potential hardcoded credential detected';
                    break;
                }
            }
            
            // Check for HTTP URLs (should use HTTPS)
            $http_urls = array();
            if (preg_match_all('/http:\/\/[^\s"\'<>]+/', $json_string, $matches)) {
                $http_urls = $matches[0];
            }
            
            if (!empty($http_urls)) {
                $warnings[] = 'HTTP URLs found (should use HTTPS): ' . implode(', ', $http_urls);
            }
            
            $result['status'] = empty($warnings) ? 'passed' : 'warning';
            $result['message'] = empty($warnings) ? 'No security issues detected' : 'Security warnings found';
            
            if (!empty($warnings)) {
                $result['details']['warnings'] = $warnings;
            }
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = 'Security validation error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate platform compatibility
     * 
     * @param string $file_path Path to JSON file
     * @param string $type 'driver' or 'template'
     * @return array Validation result
     */
    private function validate_platform_compatibility($file_path, $type) {
        $result = array(
            'status' => 'pending',
            'message' => '',
            'details' => array()
        );
        
        try {
            $content = file_get_contents($file_path);
            $data = json_decode($content, true);
            
            $warnings = array();
            
            if ($type === 'driver') {
                // Check for required driver fields
                if (!isset($data['capabilities'])) {
                    $warnings[] = 'No capabilities defined for driver';
                }
                
                // Check if driver refers to existing platform
                $platform_name = $data['name'] ?? 'unknown';
                if ($this->is_known_platform($platform_name)) {
                    $result['message'] = "Driver for known platform: {$platform_name}";
                } else {
                    $warnings[] = "Unknown platform: {$platform_name}";
                }
                
            } elseif ($type === 'template') {
                // Check if template platforms have corresponding drivers
                if (isset($data['posts'])) {
                    foreach ($data['posts'] as $index => $post) {
                        if (isset($post['platforms'])) {
                            foreach ($post['platforms'] as $platform) {
                                $driver_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$platform}.json";
                                if (!file_exists($driver_file)) {
                                    $warnings[] = "Platform driver not found for template: {$platform}";
                                }
                            }
                        }
                    }
                }
            }
            
            $result['status'] = empty($warnings) ? 'passed' : 'warning';
            $result['message'] = empty($warnings) ? 'Platform compatibility OK' : 'Platform compatibility issues found';
            
            if (!empty($warnings)) {
                $result['details']['warnings'] = $warnings;
            }
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = 'Platform compatibility validation error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check if platform is a known/supported platform
     * 
     * @param string $platform_name Platform name
     * @return bool True if known platform
     */
    private function is_known_platform($platform_name) {
        $known_platforms = array(
            'Twitter', 'Facebook', 'Instagram', 'LinkedIn', 'TikTok',
            'YouTube', 'Pinterest', 'Snapchat', 'Discord', 'Telegram',
            'Reddit', 'Medium', 'Mastodon', 'Bluesky'
        );
        
        return in_array($platform_name, $known_platforms);
    }
    
    /**
     * Calculate validation score
     * 
     * @param array $tests Test results
     * @return int Score (0-100)
     */
    private function calculate_validation_score($tests) {
        $total_tests = count($tests);
        if ($total_tests === 0) {
            return 0;
        }
        
        $passed_tests = 0;
        foreach ($tests as $test) {
            if ($test['status'] === 'passed') {
                $passed_tests++;
            }
        }
        
        return round(($passed_tests / $total_tests) * 100);
    }
    
    /**
     * Determine overall validation status
     * 
     * @param array $results Validation results
     * @return string Overall status
     */
    private function determine_overall_status($results) {
        $critical_tests = array('json_structure', 'schema_compliance');
        $critical_failed = false;
        
        foreach ($critical_tests as $test) {
            if (isset($results['tests'][$test]) && $results['tests'][$test]['status'] === 'failed') {
                $critical_failed = true;
                break;
            }
        }
        
        if ($critical_failed) {
            return 'failed';
        }
        
        $failed_tests = 0;
        $warning_tests = 0;
        
        foreach ($results['tests'] as $test) {
            if ($test['status'] === 'failed') {
                $failed_tests++;
            } elseif ($test['status'] === 'warning') {
                $warning_tests++;
            }
        }
        
        if ($failed_tests > 0) {
            return 'failed';
        } elseif ($warning_tests > 0) {
            return 'warning';
        } else {
            return 'passed';
        }
    }
    
    /**
     * Store validation results
     * 
     * @param string $file_path File path
     * @param array $results Validation results
     */
    private function store_validation_results($file_path, $results) {
        $all_results = get_option('smo_validation_results', array());
        $file_hash = md5($file_path);
        
        $all_results[$file_hash] = $results;
        
        // Keep only last 50 validation results
        if (count($all_results) > 50) {
            $all_results = array_slice($all_results, -50, true);
        }
        
        update_option('smo_validation_results', $all_results);
    }
    
    /**
     * Get validation results for a file
     * 
     * @param string $file_path File path
     * @return array|null Validation results or null
     */
    public function get_validation_results($file_path) {
        $all_results = get_option('smo_validation_results', array());
        $file_hash = md5($file_path);
        
        return $all_results[$file_hash] ?? null;
    }
    
    /**
     * Get all validation results
     * 
     * @return array All validation results
     */
    public function get_all_validation_results() {
        return get_option('smo_validation_results', array());
    }
    
    /**
     * Run validation via AJAX
     */
    public function ajax_run_validation() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $file_path = sanitize_text_field($_POST['file_path']);
        $type = sanitize_text_field($_POST['type']);
        
        $results = $this->run_validation($file_path, $type);
        wp_send_json($results);
    }
    
    /**
     * Get validation results via AJAX
     */
    public function ajax_get_validation_results() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $file_path = sanitize_text_field($_POST['file_path']);
        $results = $this->get_validation_results($file_path);
        
        if ($results) {
            wp_send_json_success($results);
        } else {
            wp_send_json_error('No validation results found');
        }
    }
}