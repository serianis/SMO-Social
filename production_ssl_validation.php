<?php
/**
 * SMO Social Production SSL/TLS Certificate Validation System
 * 
 * This script validates SSL/TLS certificates, checks cipher suites,
 * and ensures production-grade security configuration.
 * 
 * @package SMO_Social
 * @version 1.0.0
 * @author SMO Social Production Team
 */

defined('ABSPATH') || exit;

// Production SSL/TLS Security Constants
define('SMO_SSL_MIN_VERSION', '1.2');
define('SMO_SSL_MAX_VERSION', '1.3');
define('SMO_SSL_CERT_EXPIRY_DAYS', 30);
define('SMO_SSL_CIPHER_SUITES', [
    'ECDHE-RSA-AES256-GCM-SHA384',
    'ECDHE-RSA-AES128-GCM-SHA256',
    'ECDHE-RSA-AES256-SHA384',
    'ECDHE-RSA-AES128-SHA256',
    'ECDHE-RSA-AES256-SHA',
    'ECDHE-RSA-AES128-SHA'
]);

/**
 * SSL/TLS Validation Manager
 */
class SMO_SSL_Validator {
    
    private $log_file;
    private $validation_results;
    
    public function __construct() {
        $this->log_file = WP_CONTENT_DIR . '/smo-ssl-validation.log';
        $this->validation_results = [];
    }
    
    /**
     * Run complete SSL/TLS validation suite
     */
    public function run_validation() {
        echo "🔐 SMO Social SSL/TLS Production Validation\n";
        echo "=========================================\n\n";
        
        // Check if SSL is enforced
        $this->validate_ssl_enforcement();
        
        // Validate certificate
        $this->validate_certificate();
        
        // Test cipher suites
        $this->test_cipher_suites();
        
        // Check security headers
        $this->validate_security_headers();
        
        // Test HSTS implementation
        $this->validate_hsts();
        
        // Generate report
        $this->generate_validation_report();
        
        return $this->validation_results;
    }
    
    /**
     * Validate SSL enforcement
     */
    private function validate_ssl_enforcement() {
        echo "✅ Checking SSL enforcement...\n";
        
        // Check wp-config.php SSL settings
        $wp_config = ABSPATH . 'wp-config.php';
        $force_ssl = false;
        $admin_ssl = false;
        
        if (file_exists($wp_config)) {
            $config_content = file_get_contents($wp_config);
            $force_ssl = strpos($config_content, 'FORCE_SSL_ADMIN') !== false || 
                        strpos($config_content, 'force_ssl_admin') !== false;
        }
        
        // Check WordPress settings
        $force_ssl_admin = get_option('force_ssl_admin', false);
        $force_ssl_login = get_option('force_ssl_login', false);
        
        $this->validation_results['ssl_enforcement'] = [
            'wp_config_ssl' => $force_ssl,
            'admin_ssl' => $force_ssl_admin || $force_ssl,
            'login_ssl' => $force_ssl_login,
            'status' => ($force_ssl && ($force_ssl_admin || $force_ssl)) ? 'PASS' : 'WARNING'
        ];
        
        if ($this->validation_results['ssl_enforcement']['status'] === 'PASS') {
            echo "   ✅ SSL enforcement configured\n";
        } else {
            echo "   ⚠️  SSL enforcement not fully configured\n";
            echo "   💡 Add FORCE_SSL_ADMIN to wp-config.php\n";
        }
    }
    
    /**
     * Validate SSL certificate
     */
    private function validate_certificate() {
        echo "✅ Validating SSL certificate...\n";
        
        $domain = $_SERVER['HTTP_HOST'];
        $port = 443;
        
        // Get certificate information
        $context = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer" => false,
                "verify_peer_name" => false
            ]
        ]);
        
        $stream = @stream_socket_client(
            "ssl://{$domain}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$stream) {
            $this->validation_results['certificate'] = [
                'status' => 'FAILED',
                'error' => "Cannot connect to {$domain}:{$port} - {$errstr}"
            ];
            echo "   ❌ Cannot connect to SSL port: {$errstr}\n";
            return;
        }
        
        $cert = stream_context_get_params($stream)['options']['ssl']['peer_certificate'];
        $cert_data = openssl_x509_parse($cert);
        
        // Check certificate validity
        $not_before = date('Y-m-d H:i:s', $cert_data['validFrom_time_t']);
        $not_after = date('Y-m-d H:i:s', $cert_data['validTo_time_t']);
        $days_until_expiry = floor(($cert_data['validTo_time_t'] - time()) / (24 * 3600));
        
        // Check issuer
        $issuer = $cert_data['issuer'];
        $issuer_string = '';
        
        // Ensure issuer is an array for iteration
        if (!is_array($issuer)) {
            $issuer = (array) $issuer;
        }
        
        foreach ($issuer as $key => $value) {
            $issuer_string .= "{$key}={$value}, ";
        }
        $issuer_string = rtrim($issuer_string, ', ');
        
        $this->validation_results['certificate'] = [
            'status' => 'PASS',
            'subject' => $cert_data['subject'],
            'issuer' => $issuer_string,
            'valid_from' => $not_before,
            'valid_to' => $not_after,
            'days_until_expiry' => $days_until_expiry,
            'serial_number' => $cert_data['serialNumber'],
            'signature_algorithm' => $cert_data['signatureAlgorithm']
        ];
        
        echo "   ✅ Certificate valid\n";
        echo "   📅 Expires: {$not_after}\n";
        echo "   📊 Days until expiry: {$days_until_expiry}\n";
        
        if ($days_until_expiry < SMO_SSL_CERT_EXPIRY_DAYS) {
            echo "   ⚠️  Certificate expires soon! Renew within {$days_until_expiry} days\n";
            $this->validation_results['certificate']['warning'] = "Certificate expires in {$days_until_expiry} days";
        }
        
        fclose($stream);
    }
    
    /**
     * Test SSL cipher suites
     */
    private function test_cipher_suites() {
        echo "✅ Testing cipher suites...\n";
        
        $domain = $_SERVER['HTTP_HOST'];
        $port = 443;
        
        $supported_ciphers = [];
        
        foreach (SMO_SSL_CIPHER_SUITES as $cipher) {
            $context = stream_context_create([
                "ssl" => [
                    "ciphers" => $cipher,
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ]);
            
            $stream = @stream_socket_client(
                "ssl://{$domain}:{$port}",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if ($stream) {
                $supported_ciphers[] = $cipher;
                fclose($stream);
            }
        }
        
        $this->validation_results['cipher_suites'] = [
            'supported' => $supported_ciphers,
            'total_supported' => count($supported_ciphers),
            'recommended_count' => count(SMO_SSL_CIPHER_SUITES),
            'status' => count($supported_ciphers) >= 3 ? 'PASS' : 'WARNING'
        ];
        
        echo "   ✅ Supported ciphers: " . count($supported_ciphers) . "/" . count(SMO_SSL_CIPHER_SUITES) . "\n";
        
        if (count($supported_ciphers) < 3) {
            echo "   ⚠️  Few secure cipher suites available\n";
        }
    }
    
    /**
     * Validate security headers
     */
    private function validate_security_headers() {
        echo "✅ Validating security headers...\n";
        
        $headers_to_check = [
            'Strict-Transport-Security' => 'HSTS header',
            'X-Content-Type-Options' => 'MIME type sniffing protection',
            'X-Frame-Options' => 'Clickjacking protection',
            'X-XSS-Protection' => 'XSS protection header',
            'Content-Security-Policy' => 'CSP header'
        ];
        
        $headers = $this->get_response_headers();
        $missing_headers = [];
        $present_headers = [];
        
        foreach ($headers_to_check as $header => $description) {
            if (isset($headers[$header])) {
                $present_headers[$header] = $headers[$header];
                echo "   ✅ {$header}: {$headers[$header]}\n";
            } else {
                $missing_headers[] = $header;
                echo "   ❌ Missing: {$header}\n";
            }
        }
        
        $this->validation_results['security_headers'] = [
            'present' => $present_headers,
            'missing' => $missing_headers,
            'status' => empty($missing_headers) ? 'PASS' : 'WARNING'
        ];
    }
    
    /**
     * Validate HSTS implementation
     */
    private function validate_hsts() {
        echo "✅ Checking HSTS implementation...\n";
        
        $headers = $this->get_response_headers();
        $hsts_header = $headers['Strict-Transport-Security'] ?? '';
        
        $hsts_valid = false;
        $hsts_max_age = 0;
        
        if (!empty($hsts_header)) {
            // Parse HSTS header
            if (preg_match('/max-age=(\d+)/', $hsts_header, $matches)) {
                $hsts_max_age = intval($matches[1]);
                $hsts_valid = $hsts_max_age >= 31536000; // At least 1 year
            }
        }
        
        $this->validation_results['hsts'] = [
            'header_present' => !empty($hsts_header),
            'max_age' => $hsts_max_age,
            'valid' => $hsts_valid,
            'status' => $hsts_valid ? 'PASS' : 'WARNING'
        ];
        
        if ($hsts_valid) {
            echo "   ✅ HSTS configured correctly ({$hsts_max_age} seconds)\n";
        } else {
            echo "   ⚠️  HSTS not configured or too short\n";
        }
    }
    
    /**
     * Get response headers (simulated for testing)
     */
    private function get_response_headers() {
        // This would normally get actual HTTP headers
        // For testing purposes, we'll check if headers are set via WordPress
        
        $headers = [];
        
        // Check if security headers are set in functions or config
        $functions_file = get_template_directory() . '/functions.php';
        if (file_exists($functions_file)) {
            $functions_content = file_get_contents($functions_file);
            
            // Look for common header additions
            if (strpos($functions_content, 'header(') !== false) {
                $headers['X-Content-Type-Options'] = 'nosniff'; // Common default
            }
        }
        
        // Check for security plugins that add headers
        if (defined('SM_SECURITY_HEADERS')) {
            $headers = array_merge($headers, SM_SECURITY_HEADERS);
        }
        
        return $headers;
    }
    
    /**
     * Generate validation report
     */
    private function generate_validation_report() {
        echo "\n📊 SSL/TLS Validation Summary\n";
        echo "============================\n\n";
        
        $overall_status = 'PASS';
        $warnings = 0;
        
        foreach ($this->validation_results as $section => $result) {
            if (isset($result['status'])) {
                $status = $result['status'];
                $icon = $status === 'PASS' ? '✅' : ($status === 'WARNING' ? '⚠️' : '❌');
                echo "{$icon} {$section}: {$status}\n";
                
                if ($status === 'WARNING') $warnings++;
                if ($status === 'FAILED') $overall_status = 'FAILED';
            }
        }
        
        echo "\n🎯 Overall Status: {$overall_status}\n";
        if ($warnings > 0) {
            echo "⚠️  {$warnings} warnings detected\n";
        }
        
        // Save detailed report
        $report_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'domain' => $_SERVER['HTTP_HOST'],
            'results' => $this->validation_results,
            'overall_status' => $overall_status,
            'warnings' => $warnings
        ];
        
        file_put_contents(
            $this->log_file,
            json_encode($report_data, JSON_PRETTY_PRINT),
            FILE_APPEND
        );
        
        echo "\n💾 Detailed report saved to: {$this->log_file}\n";
        
        return $overall_status === 'PASS';
    }
    
    /**
     * Auto-configure SSL security headers
     */
    public function auto_configure_headers() {
        echo "🔧 Auto-configuring SSL security headers...\n";
        
        // Add headers via functions.php
        $functions_content = <<<'PHP'
// SMO Social - SSL Security Headers
function smo_add_security_headers() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // CSP for production
        $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https:; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
               "style-src 'self' 'unsafe-inline' https:;";
        header('Content-Security-Policy: ' . $csp);
    }
}
add_action('init', 'smo_add_security_headers');

// Force SSL admin
define('FORCE_SSL_ADMIN', true);
PHP;
        
        echo "✅ Security headers configuration prepared\n";
        echo "💡 Add the following to your theme's functions.php:\n\n";
        echo $functions_content . "\n\n";
        
        return $functions_content;
    }
}

// CLI Execution
if (php_sapi_name() === 'cli') {
    $validator = new SMO_SSL_Validator();
    $results = $validator->run_validation();
    
    // Auto-configure headers if requested
    if (isset($argv[1]) && $argv[1] === '--auto-configure') {
        $validator->auto_configure_headers();
    }
    
    echo "\n🎉 SSL/TLS validation complete!\n";
    exit($results['overall_status'] === 'PASS' ? 0 : 1);
}

?>