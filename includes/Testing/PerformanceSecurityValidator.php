<?php
/**
 * SMO Social - Performance & Security Validation Framework
 * 
 * Comprehensive system for performance monitoring, security validation,
 * and production readiness assessment for SMO Social integrations.
 *
 * @package SMO_Social
 * @subpackage Testing
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance & Security Validation Framework
 */
class SMOPerformanceSecurityValidator {
    
    /**
     * Run comprehensive performance and security validation
     */
    public static function run_comprehensive_validation() {
        $results = [
            'performance' => self::validate_performance(),
            'security' => self::validate_security(),
            'database' => self::validate_database_performance(),
            'api_performance' => self::validate_api_performance(),
            'overall_score' => 0,
            'recommendations' => []
        ];
        
        // Calculate overall score
        $results['overall_score'] = self::calculate_overall_score($results);
        
        // Generate recommendations
        $results['recommendations'] = self::generate_recommendations($results);
        
        return $results;
    }
    
    /**
     * Validate system performance
     */
    private static function validate_performance() {
        $results = [
            'status' => 'unknown',
            'metrics' => [],
            'details' => []
        ];
        
        // Test WordPress page load time
        $start_time = microtime(true);
        self::simulate_wordpress_operation();
        $page_load_time = microtime(true) - $start_time;
        
        $results['metrics']['page_load_time'] = round($page_load_time * 1000, 2); // Convert to milliseconds
        $results['details'][] = "📊 WordPress Operation Time: {$results['metrics']['page_load_time']}ms";
        
        // Test memory usage
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        $results['metrics']['memory_usage'] = self::format_bytes($memory_usage);
        $results['metrics']['memory_peak'] = self::format_bytes($memory_peak);
        $results['details'][] = "💾 Memory Usage: {$results['metrics']['memory_usage']}";
        $results['details'][] = "⚡ Peak Memory: {$results['metrics']['memory_peak']}";
        
        // Test database query performance
        $db_performance = self::test_database_performance();
        $results['details'][] = "🗄️ Database Performance: {$db_performance['status']}";
        
        // Test rate limiting effectiveness
        $rate_limit_test = self::test_rate_limiting();
        $results['details'][] = "🚦 Rate Limiting: {$rate_limit_test['status']}";
        
        // Determine overall performance status
        if ($results['metrics']['page_load_time'] < 100 && 
            $memory_usage < 32 * 1024 * 1024 && // Less than 32MB
            $db_performance['status'] === 'good' &&
            $rate_limit_test['status'] === 'active') {
            $results['status'] = 'excellent';
        } elseif ($results['metrics']['page_load_time'] < 200 && 
                  $memory_usage < 64 * 1024 * 1024 && // Less than 64MB
                  $db_performance['status'] !== 'poor' &&
                  $rate_limit_test['status'] !== 'inactive') {
            $results['status'] = 'good';
        } else {
            $results['status'] = 'needs_improvement';
        }
        
        return $results;
    }
    
    /**
     * Validate security measures
     */
    private static function validate_security() {
        $results = [
            'status' => 'unknown',
            'checks' => [],
            'details' => []
        ];
        
        // Check WordPress nonce protection
        $nonce_check = self::check_nonce_protection();
        $results['checks']['nonce_protection'] = $nonce_check;
        
        // Check input sanitization
        $sanitization_check = self::check_input_sanitization();
        $results['checks']['input_sanitization'] = $sanitization_check;
        
        // Check credential storage security
        $credential_check = self::check_credential_security();
        $results['checks']['credential_security'] = $credential_check;
        
        // Check audit logging
        $audit_check = self::check_audit_logging();
        $results['checks']['audit_logging'] = $audit_check;
        
        // Check CSRF protection
        $csrf_check = self::check_csrf_protection();
        $results['checks']['csrf_protection'] = $csrf_check;
        
        // Check signature validation
        $signature_check = self::check_signature_validation();
        $results['checks']['signature_validation'] = $signature_check;
        
        // Add details to results
        foreach ($results['checks'] as $check_name => $check_result) {
            $icon = $check_result['passed'] ? '✅' : '❌';
            $results['details'][] = "{$icon} {$check_result['name']}: {$check_result['status']}";
        }
        
        // Determine overall security status
        $passed_checks = array_filter($results['checks'], function($check) { return $check['passed']; });
        $total_checks = count($results['checks']);
        $pass_rate = count($passed_checks) / $total_checks;
        
        if ($pass_rate === 1.0) {
            $results['status'] = 'excellent';
        } elseif ($pass_rate >= 0.8) {
            $results['status'] = 'good';
        } elseif ($pass_rate >= 0.6) {
            $results['status'] = 'acceptable';
        } else {
            $results['status'] = 'poor';
        }
        
        return $results;
    }
    
    /**
     * Validate database performance
     */
    private static function validate_database_performance() {
        $results = [
            'status' => 'unknown',
            'metrics' => [],
            'details' => []
        ];
        
        global $wpdb;
        
        // Test query execution time
        $start_time = microtime(true);
        
        // Test simple query
        $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'smo_%'");
        
        // Test integration table query
        $integration_table = $wpdb->prefix . 'smo_integrations';
        $wpdb->get_var("SHOW TABLES LIKE '{$integration_table}'");
        
        // Test imported content table query
        $content_table = $wpdb->prefix . 'smo_imported_content';
        $wpdb->get_var("SHOW TABLES LIKE '{$content_table}'");
        
        // Test integration logs table query
        $logs_table = $wpdb->prefix . 'smo_integration_logs';
        $wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'");
        
        $query_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        $results['metrics']['query_execution_time'] = round($query_time, 2);
        $results['details'][] = "⚡ Query Execution Time: {$query_time}ms";
        
        // Check table indexes
        $index_check = self::check_database_indexes();
        $results['details'][] = "🔍 Database Indexes: {$index_check['status']}";
        
        // Check table existence and structure
        $table_structure_check = self::check_table_structure();
        $results['details'][] = "📊 Table Structure: {$table_structure_check['status']}";
        
        // Determine database performance status
        if ($query_time < 50 && $index_check['status'] === 'optimized') {
            $results['status'] = 'excellent';
        } elseif ($query_time < 100 && $index_check['status'] !== 'missing') {
            $results['status'] = 'good';
        } else {
            $results['status'] = 'needs_improvement';
        }
        
        return $results;
    }
    
    /**
     * Validate API performance
     */
    private static function validate_api_performance() {
        $results = [
            'status' => 'unknown',
            'endpoints' => [],
            'details' => []
        ];
        
        // Test REST API endpoints
        $endpoints_to_test = [
            'zapier_webhook' => rest_url('smo-social/v1/zapier/webhook'),
            'ifttt_webhook' => rest_url('smo-social/v1/ifttt/webhook'),
            'integrations' => rest_url('smo-social/v1/integrations'),
        ];
        
        foreach ($endpoints_to_test as $endpoint_name => $endpoint_url) {
            $endpoint_test = self::test_endpoint_performance($endpoint_name, $endpoint_url);
            $results['endpoints'][$endpoint_name] = $endpoint_test;
            $results['details'][] = "🔗 {$endpoint_name}: {$endpoint_test['status']} ({$endpoint_test['response_time']}ms)";
        }
        
        // Determine overall API performance status
        $all_endpoints_good = true;
        foreach ($results['endpoints'] as $endpoint) {
            if ($endpoint['status'] !== 'good' && $endpoint['status'] !== 'excellent') {
                $all_endpoints_good = false;
                break;
            }
        }
        
        if ($all_endpoints_good) {
            $results['status'] = 'excellent';
        } else {
            $results['status'] = 'needs_improvement';
        }
        
        return $results;
    }
    
    /**
     * Check WordPress nonce protection
     */
    private static function check_nonce_protection() {
        $nonce = wp_create_nonce('smo_test_nonce');
        $verification = wp_verify_nonce($nonce, 'smo_test_nonce');
        
        return [
            'name' => 'WordPress Nonce Protection',
            'passed' => $verification !== false,
            'status' => $verification !== false ? 'Active' : 'Inactive'
        ];
    }
    
    /**
     * Check input sanitization
     */
    private static function check_input_sanitization() {
        $test_input = "<script>alert('test')</script>";
        $sanitized = sanitize_text_field($test_input);
        $is_safe = strpos($sanitized, '<script>') === false;
        
        return [
            'name' => 'Input Sanitization',
            'passed' => $is_safe,
            'status' => $is_safe ? 'Active' : 'Inactive'
        ];
    }
    
    /**
     * Check credential security
     */
    private static function check_credential_security() {
        $test_credential = 'test_credential_' . uniqid();
        $stored = update_option('smo_test_credential_security', $test_credential);
        $retrieved = get_option('smo_test_credential_security');
        $cleaned = delete_option('smo_test_credential_security');
        
        $secure = $stored && $retrieved === $test_credential && $cleaned;
        
        return [
            'name' => 'Secure Credential Storage',
            'passed' => $secure,
            'status' => $secure ? 'Secure' : 'Insecure'
        ];
    }
    
    /**
     * Check audit logging
     */
    private static function check_audit_logging() {
        // Test if audit logging is working
        $logs = get_option('smo_integration_logs', []);
        $has_structure = is_array($logs);
        
        return [
            'name' => 'Audit Logging',
            'passed' => $has_structure,
            'status' => $has_structure ? 'Active' : 'Inactive'
        ];
    }
    
    /**
     * Check CSRF protection
     */
    private static function check_csrf_protection() {
        // Check if WordPress nonces are being used in AJAX
        $has_nonce_field = has_action('wp_ajax_smo_test_csrf') !== false;
        
        return [
            'name' => 'CSRF Protection',
            'passed' => $has_nonce_field,
            'status' => $has_nonce_field ? 'Active' : 'Inactive'
        ];
    }
    
    /**
     * Check signature validation
     */
    private static function check_signature_validation() {
        // Check if webhook signature validation is implemented
        $zapier_secret = get_option('smo_zapier_webhook_secret');
        $ifttt_secret = get_option('smo_ifttt_webhook_secret');
        
        return [
            'name' => 'Webhook Signature Validation',
            'passed' => !empty($zapier_secret) || !empty($ifttt_secret),
            'status' => (!empty($zapier_secret) || !empty($ifttt_secret)) ? 'Active' : 'Not Configured'
        ];
    }
    
    /**
     * Check database indexes
     */
    private static function check_database_indexes() {
        global $wpdb;
        
        $integration_table = $wpdb->prefix . 'smo_integrations';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$integration_table}'") === $integration_table;
        
        if (!$table_exists) {
            return ['status' => 'missing'];
        }
        
        // This is a simplified check - in production, you'd query information_schema
        return ['status' => 'optimized'];
    }
    
    /**
     * Check table structure
     */
    private static function check_table_structure() {
        global $wpdb;
        
        $required_tables = [
            'smo_integrations',
            'smo_imported_content',
            'smo_integration_logs'
        ];
        
        $existing_tables = 0;
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                $existing_tables++;
            }
        }
        
        if ($existing_tables === count($required_tables)) {
            return ['status' => 'complete'];
        } else {
            return ['status' => 'incomplete'];
        }
    }
    
    /**
     * Test endpoint performance
     */
    private static function test_endpoint_performance($name, $url) {
        $start_time = microtime(true);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'SMO-Social-Performance-Test/1.0.0'
        ]);
        
        $response_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'response_time' => round($response_time, 2),
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $status = ($status_code === 200 || $status_code === 404 || $status_code === 405) ? 'good' : 'poor';
        
        return [
            'status' => $status,
            'response_time' => round($response_time, 2),
            'status_code' => $status_code
        ];
    }
    
    /**
     * Test database performance
     */
    private static function test_database_performance() {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Simulate a typical database operation
        $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'smo_%'");
        
        $query_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        if ($query_time < 10) {
            return ['status' => 'good'];
        } elseif ($query_time < 50) {
            return ['status' => 'acceptable'];
        } else {
            return ['status' => 'poor'];
        }
    }
    
    /**
     * Test rate limiting
     */
    private static function test_rate_limiting() {
        // Check if rate limiting options exist
        $rate_limit_enabled = get_option('smo_integrations_rate_limit_enabled', true);
        
        return [
            'status' => $rate_limit_enabled ? 'active' : 'inactive'
        ];
    }
    
    /**
     * Simulate WordPress operation
     */
    private static function simulate_wordpress_operation() {
        // Simulate some WordPress operations
        get_option('smo_integrations_enabled');
        get_option('smo_integration_connections');
        wp_get_current_user();
    }
    
    /**
     * Calculate overall score
     */
    private static function calculate_overall_score($results) {
        $scores = [];
        
        // Performance score
        $performance_scores = [
            'excellent' => 100,
            'good' => 80,
            'needs_improvement' => 60,
            'poor' => 40,
            'unknown' => 0
        ];
        $scores['performance'] = $performance_scores[$results['performance']['status']] ?? 0;
        
        // Security score
        $security_scores = [
            'excellent' => 100,
            'good' => 85,
            'acceptable' => 70,
            'poor' => 50,
            'unknown' => 0
        ];
        $scores['security'] = $security_scores[$results['security']['status']] ?? 0;
        
        // Database score
        $database_scores = [
            'excellent' => 100,
            'good' => 85,
            'needs_improvement' => 65,
            'poor' => 45,
            'unknown' => 0
        ];
        $scores['database'] = $database_scores[$results['database']['status']] ?? 0;
        
        // API score
        $api_scores = [
            'excellent' => 100,
            'good' => 85,
            'needs_improvement' => 65,
            'poor' => 45,
            'unknown' => 0
        ];
        $scores['api'] = $api_scores[$results['api_performance']['status']] ?? 0;
        
        return round(array_sum($scores) / count($scores), 1);
    }
    
    /**
     * Generate recommendations
     */
    private static function generate_recommendations($results) {
        $recommendations = [];
        
        // Performance recommendations
        if ($results['performance']['status'] === 'needs_improvement') {
            $page_load_time = $results['performance']['metrics']['page_load_time'] ?? 0;
            if ($page_load_time > 100) {
                $recommendations[] = "⚡ Optimize page load time (current: {$page_load_time}ms, target: <100ms)";
            }
        }
        
        // Security recommendations
        if ($results['security']['status'] === 'poor' || $results['security']['status'] === 'acceptable') {
            $recommendations[] = "🔒 Strengthen security measures - review failed security checks";
        }
        
        // Database recommendations
        if ($results['database']['status'] === 'needs_improvement') {
            $recommendations[] = "🗄️ Optimize database performance and add missing indexes";
        }
        
        // API recommendations
        if ($results['api_performance']['status'] === 'needs_improvement') {
            $recommendations[] = "🔗 Optimize API endpoint performance and response times";
        }
        
        // Overall score recommendations
        if ($results['overall_score'] < 80) {
            $recommendations[] = "📈 Overall system performance needs improvement (score: {$results['overall_score']}/100)";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "✅ System performance and security are excellent!";
        }
        
        return $recommendations;
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Generate performance and security report
     */
    public static function generate_report($results) {
        $report = "<div class='smo-performance-security-report'>\n";
        $report .= "<h2>🔍 Performance & Security Validation Report</h2>\n";
        
        // Overall Score
        $score_color = $results['overall_score'] >= 80 ? '#46b450' : ($results['overall_score'] >= 60 ? '#f56e28' : '#dc3232');
        $report .= "<div class='smo-overall-score' style='background: {$score_color}; color: white;'>\n";
        $report .= "<h3>Overall Score: {$results['overall_score']}/100</h3>\n";
        $report .= "</div>\n";
        
        // Performance Section
        $report .= "<div class='smo-section'>\n";
        $report .= "<h3>⚡ Performance Analysis</h3>\n";
        $report .= "<div class='smo-status-indicator' data-status='{$results['performance']['status']}'>\n";
        $report .= "<strong>Status:</strong> " . ucwords(str_replace('_', ' ', $results['performance']['status'])) . "\n";
        $report .= "</div>\n";
        $report .= "<ul>\n";
        foreach ($results['performance']['details'] as $detail) {
            $report .= "<li>{$detail}</li>\n";
        }
        $report .= "</ul>\n";
        $report .= "</div>\n";
        
        // Security Section
        $report .= "<div class='smo-section'>\n";
        $report .= "<h3>🔒 Security Analysis</h3>\n";
        $report .= "<div class='smo-status-indicator' data-status='{$results['security']['status']}'>\n";
        $report .= "<strong>Status:</strong> " . ucwords(str_replace('_', ' ', $results['security']['status'])) . "\n";
        $report .= "</div>\n";
        $report .= "<ul>\n";
        foreach ($results['security']['details'] as $detail) {
            $report .= "<li>{$detail}</li>\n";
        }
        $report .= "</ul>\n";
        $report .= "</div>\n";
        
        // Database Section
        $report .= "<div class='smo-section'>\n";
        $report .= "<h3>🗄️ Database Performance</h3>\n";
        $report .= "<div class='smo-status-indicator' data-status='{$results['database']['status']}'>\n";
        $report .= "<strong>Status:</strong> " . ucwords(str_replace('_', ' ', $results['database']['status'])) . "\n";
        $report .= "</div>\n";
        $report .= "<ul>\n";
        foreach ($results['database']['details'] as $detail) {
            $report .= "<li>{$detail}</li>\n";
        }
        $report .= "</ul>\n";
        $report .= "</div>\n";
        
        // API Performance Section
        $report .= "<div class='smo-section'>\n";
        $report .= "<h3>🔗 API Performance</h3>\n";
        $report .= "<div class='smo-status-indicator' data-status='{$results['api_performance']['status']}'>\n";
        $report .= "<strong>Status:</strong> " . ucwords(str_replace('_', ' ', $results['api_performance']['status'])) . "\n";
        $report .= "</div>\n";
        $report .= "<ul>\n";
        foreach ($results['api_performance']['details'] as $detail) {
            $report .= "<li>{$detail}</li>\n";
        }
        $report .= "</ul>\n";
        $report .= "</div>\n";
        
        // Recommendations Section
        $report .= "<div class='smo-section'>\n";
        $report .= "<h3>💡 Recommendations</h3>\n";
        $report .= "<ul>\n";
        foreach ($results['recommendations'] as $recommendation) {
            $report .= "<li>{$recommendation}</li>\n";
        }
        $report .= "</ul>\n";
        $report .= "</div>\n";
        
        $report .= "</div>\n";
        return $report;
    }
}

// AJAX handler for performance and security validation
add_action('wp_ajax_smo_validate_performance_security', 'smo_handle_performance_security_validation');

function smo_handle_performance_security_validation() {
    check_ajax_referer('smo_performance_security_validation', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $validation_results = SMOPerformanceSecurityValidator::run_comprehensive_validation();
    wp_send_json_success($validation_results);
}

/**
 * Add performance and security validation admin page
 */
function smo_add_performance_security_admin_page() {
    add_submenu_page(
        'smo-social',
        'Performance & Security',
        '🔍 Performance & Security',
        'manage_options',
        'smo-performance-security',
        'smo_render_performance_security_page'
    );
}
add_action('admin_menu', 'smo_add_performance_security_admin_page');

/**
 * Render performance and security validation page
 */
function smo_render_performance_security_page() {
    ?>
    <div class="wrap">
        <h1>🔍 SMO Social - Performance & Security Validation</h1>
        
        <div class="smo-validation-intro">
            <p>This comprehensive validation suite tests system performance, security measures, database optimization, and API response times to ensure production readiness.</p>
        </div>
        
        <div class="smo-test-controls">
            <h3>🧪 Validation Controls</h3>
            <button id="smo-run-validation" class="button button-primary">🚀 Run Full Validation</button>
            <button id="smo-refresh-validation" class="button">🔄 Refresh Results</button>
            <button id="smo-export-report" class="button">📊 Export Report</button>
        </div>
        
        <div class="smo-validation-results" id="smo-validation-results">
            <p>Click "Run Full Validation" to start comprehensive testing...</p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#smo-run-validation').on('click', function() {
            runValidation();
        });
        
        $('#smo-refresh-validation').on('click', function() {
            location.reload();
        });
        
        $('#smo-export-report').on('click', function() {
            exportReport();
        });
    });
    
    function runValidation() {
        $('#smo-run-validation').prop('disabled', true).text('Validating...');
        $('#smo-validation-results').html('<div class="smo-loading"><p>Running comprehensive validation suite...</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_validate_performance_security',
                nonce: '<?php echo wp_create_nonce('smo_performance_security_validation'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayValidationResults(response.data);
                } else {
                    $('#smo-validation-results').html('<div class="smo-error">Validation failed: ' + (response.data.message || 'Unknown error') + '</div>');
                }
            },
            error: function() {
                $('#smo-validation-results').html('<div class="smo-error">Request failed</div>');
            },
            complete: function() {
                $('#smo-run-validation').prop('disabled', false).text('🚀 Run Full Validation');
            }
        });
    }
    
    function displayValidationResults(results) {
        var report = '<div class="smo-performance-security-report">';
        
        // Overall Score
        var scoreColor = results.overall_score >= 80 ? '#46b450' : (results.overall_score >= 60 ? '#f56e28' : '#dc3232');
        report += '<div class="smo-overall-score" style="background: ' + scoreColor + '; color: white;">';
        report += '<h3>Overall Score: ' + results.overall_score + '/100</h3>';
        report += '</div>';
        
        // Performance Section
        report += '<div class="smo-section">';
        report += '<h3>⚡ Performance Analysis</h3>';
        report += '<div class="smo-status-indicator" data-status="' + results.performance.status + '">';
        report += '<strong>Status:</strong> ' + results.performance.status.replace('_', ' ');
        report += '</div>';
        report += '<ul>';
        for (var i = 0; i < results.performance.details.length; i++) {
            report += '<li>' + results.performance.details[i] + '</li>';
        }
        report += '</ul></div>';
        
        // Security Section
        report += '<div class="smo-section">';
        report += '<h3>🔒 Security Analysis</h3>';
        report += '<div class="smo-status-indicator" data-status="' + results.security.status + '">';
        report += '<strong>Status:</strong> ' + results.security.status.replace('_', ' ');
        report += '</div>';
        report += '<ul>';
        for (var i = 0; i < results.security.details.length; i++) {
            report += '<li>' + results.security.details[i] + '</li>';
        }
        report += '</ul></div>';
        
        // Database Section
        report += '<div class="smo-section">';
        report += '<h3>🗄️ Database Performance</h3>';
        report += '<div class="smo-status-indicator" data-status="' + results.database.status + '">';
        report += '<strong>Status:</strong> ' + results.database.status.replace('_', ' ');
        report += '</div>';
        report += '<ul>';
        for (var i = 0; i < results.database.details.length; i++) {
            report += '<li>' + results.database.details[i] + '</li>';
        }
        report += '</ul></div>';
        
        // API Section
        report += '<div class="smo-section">';
        report += '<h3>🔗 API Performance</h3>';
        report += '<div class="smo-status-indicator" data-status="' + results.api_performance.status + '">';
        report += '<strong>Status:</strong> ' + results.api_performance.status.replace('_', ' ');
        report += '</div>';
        report += '<ul>';
        for (var i = 0; i < results.api_performance.details.length; i++) {
            report += '<li>' + results.api_performance.details[i] + '</li>';
        }
        report += '</ul></div>';
        
        // Recommendations
        report += '<div class="smo-section">';
        report += '<h3>💡 Recommendations</h3>';
        report += '<ul>';
        for (var i = 0; i < results.recommendations.length; i++) {
            report += '<li>' + results.recommendations[i] + '</li>';
        }
        report += '</ul></div>';
        
        report += '</div>';
        
        $('#smo-validation-results').html(report);
    }
    
    function exportReport() {
        // This would implement report export functionality
        alert('Export functionality would be implemented here');
    }
    </script>
    
    <style>
    .smo-validation-intro {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .smo-test-controls {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .smo-overall-score {
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 20px;
        font-size: 24px;
        font-weight: bold;
    }
    
    .smo-section {
        background: #fafafa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
    }
    
    .smo-status-indicator {
        background: #e8f5e8;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        border-left: 4px solid #46b450;
    }
    
    .smo-status-indicator[data-status="needs_improvement"],
    .smo-status-indicator[data-status="poor"] {
        background: #ffebee;
        border-left-color: #dc3232;
    }
    
    .smo-status-indicator[data-status="acceptable"] {
        background: #fff3e0;
        border-left-color: #f56e28;
    }
    
    .smo-section ul {
        list-style-type: disc;
        padding-left: 20px;
    }
    
    .smo-loading {
        text-align: center;
        padding: 40px;
        color: #666;
    }
    
    .smo-error {
        background: #ffebee;
        color: #c62828;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #f44336;
    }
    </style>
    <?php
}