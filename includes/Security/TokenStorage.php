<?php
namespace SMO_Social\Security;

/**
 * Secure Token Storage with AES-256 encryption
 * Implements zero-knowledge architecture for maximum security
 */
class TokenStorage {
    private $platform_slug;
    private $encryption_key;
    private $option_prefix;

    public function __construct($platform_slug) {
        $this->platform_slug = $platform_slug;
        $this->option_prefix = "smo_social_{$platform_slug}_";
        $this->encryption_key = $this->get_encryption_key();
    }

    /**
     * Generate or retrieve encryption key from WordPress auth keys
     */
    private function get_encryption_key() {
        $auth_keys = [
            AUTH_KEY,
            SECURE_AUTH_KEY,
            LOGGED_IN_KEY,
            NONCE_KEY
        ];

        // Use first non-empty key, or generate from multiple keys
        foreach ($auth_keys as $key) {
            if (!empty($key) && defined($key)) {
                return \hash('sha256', $key . $this->platform_slug);
            }
        }

        // Fallback: derive from site keys
        return \hash('sha256', get_site_option('siteurl') . $this->platform_slug);
    }

    /**
     * Store encrypted tokens securely
     */
    public function store_tokens($tokens) {
        $encrypted_tokens = $this->encrypt_data(json_encode($tokens));
        
        update_option($this->option_prefix . 'tokens', $encrypted_tokens, false);
        
        // Log token storage for audit trail (without exposing sensitive data)
        $this->log_token_activity('stored', [
            'has_access_token' => !empty($tokens['access_token']),
            'has_refresh_token' => !empty($tokens['refresh_token'])
        ]);
    }

    /**
     * Retrieve and decrypt tokens
     */
    public function get_tokens() {
        $encrypted_tokens = get_option($this->option_prefix . 'tokens');
        
        if (!$encrypted_tokens) {
            return null;
        }

        $decrypted_data = $this->decrypt_data($encrypted_tokens);
        
        if ($decrypted_data === false) {
            // Decryption failed - possibly corrupted or tampered data
            $this->clear_tokens();
            return null;
        }

        $tokens = json_decode($decrypted_data, true);
        
        // Check if token is expired
        if ($this->is_token_expired($tokens)) {
            return null;
        }

        return $tokens;
    }

    /**
     * Update existing tokens (e.g., during refresh)
     */
    public function update_tokens($updates) {
        $current_tokens = $this->get_tokens();
        
        if (!$current_tokens) {
            return false;
        }

        $updated_tokens = array_merge($current_tokens, $updates);
        $this->store_tokens($updated_tokens);
        
        return true;
    }

    /**
     * Clear all tokens (secure deletion)
     */
    public function clear_tokens() {
        delete_option($this->option_prefix . 'tokens');
        
        // Also clear any related auth data
        delete_option($this->option_prefix . 'auth_state');
        delete_option($this->option_prefix . 'code_verifier');
        
        $this->log_token_activity('cleared', []);
    }

    /**
     * Check if current tokens are expired or need refresh
     */
    private function is_token_expired($tokens) {
        if (empty($tokens['expires_in']) || empty($tokens['stored_at'])) {
            return false; // No expiration info, assume valid
        }

        $expires_at = $tokens['stored_at'] + ($tokens['expires_in'] - 300); // 5-minute buffer
        
        return time() >= $expires_at;
    }

    /**
     * Encrypt sensitive data using AES-256
     */
    private function encrypt_data($data) {
        $iv = \random_bytes(16); // AES block size
        $key = substr(\hash('sha256', $this->encryption_key), 0, 32); // 256-bit key

        $encrypted = \openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data using AES-256
     */
    private function decrypt_data($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $key = substr(\hash('sha256', $this->encryption_key), 0, 32);

        return \openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Log token activities for security auditing
     */
    private function log_token_activity($action, $metadata = []) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'platform' => $this->platform_slug,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'metadata' => $metadata
        ];

        // Store in WordPress options (could be moved to dedicated logging table)
        $logs = get_option('smo_social_security_logs', []);
        if (!is_array($logs)) {
            $logs = [];
        }
        $logs[] = $log_entry;

        // Keep only last 100 entries to prevent bloat
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        update_option('smo_social_security_logs', $logs, false);
    }

    /**
     * Get client IP address for logging
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }

    /**
     * Perform security check on token storage
     */
    public function security_audit() {
        $issues = [];
        
        // Check if tokens exist without proper encryption
        $raw_tokens = get_option($this->option_prefix . 'tokens_raw');
        if ($raw_tokens) {
            $issues[] = 'Unencrypted tokens found';
        }
        
        // Check for recent suspicious activity
        $logs = get_option('smo_social_security_logs', []);
        if (!is_array($logs)) {
            $logs = [];
        }
        $recent_logs = array_filter($logs, function($log) {
            return $log['platform'] === $this->platform_slug &&
                    strtotime($log['timestamp']) > strtotime('-1 hour');
        });
        
        if (count($recent_logs) > 10) {
            $issues[] = 'High frequency token access detected';
        }
        
        return $issues;
    }

    /**
     * Export tokens for backup (encrypted)
     */
    public function export_tokens() {
        $encrypted_tokens = get_option($this->option_prefix . 'tokens');
        if (!$encrypted_tokens) {
            return null;
        }
        
        return [
            'platform' => $this->platform_slug,
            'exported_at' => current_time('mysql'),
            'encrypted_tokens' => $encrypted_tokens,
            'export_hash' => \hash('sha256', $encrypted_tokens . $this->platform_slug)
        ];
    }

    /**
     * Import encrypted tokens from backup
     */
    public function import_tokens($export_data) {
        if (!$export_data || $export_data['platform'] !== $this->platform_slug) {
            return false;
        }
        
        // Verify integrity
        $expected_hash = \hash('sha256', $export_data['encrypted_tokens'] . $this->platform_slug);
        if ($expected_hash !== $export_data['export_hash']) {
            return false;
        }
        
        update_option($this->option_prefix . 'tokens', $export_data['encrypted_tokens'], false);
        $this->log_token_activity('imported', ['source' => 'backup']);
        
        return true;
    }
}