<?php
namespace SMO_Social\Community;

/**
 * ReputationManager - Manages reputation system for community drivers and templates
 * 
 * Tracks install counts, error rates, and assigns "Verified" badges
 * based on community contribution quality metrics.
 */
class ReputationManager {
    
    private $reputation_data;
    private $error_logs;
    
    public function __construct() {
        $this->reputation_data = get_option('smo_reputation_data', array());
        $this->error_logs = get_option('smo_error_logs', array());
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Track successful posts
        add_action('smo_post_success', array($this, 'track_successful_post'));
        
        // Track failed posts
        add_action('smo_post_failure', array($this, 'track_failed_post'));
        
        // Check reputation every hour
        add_action('wp_loaded', array($this, 'periodic_reputation_check'));
    }
    
    /**
     * Update install count for a driver/template
     * 
     * @param string $item_id Driver or template identifier
     * @param string $type 'driver' or 'template'
     */
    public function update_install_count($item_id, $type = 'template') {
        if (!isset($this->reputation_data[$item_id])) {
            $this->reputation_data[$item_id] = array(
                'type' => $type,
                'install_count' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'verified' => false,
                'rating' => 0,
                'created_date' => current_time('mysql'),
                'last_activity' => current_time('mysql')
            );
        }
        
        $this->reputation_data[$item_id]['install_count']++;
        $this->reputation_data[$item_id]['last_activity'] = current_time('mysql');
        
        $this->update_verification_status($item_id);
        $this->save_reputation_data();
    }
    
    /**
     * Track successful post execution
     * 
     * @param array $post_data Post execution data
     */
    public function track_successful_post($post_data) {
        $driver_id = $post_data['driver_id'] ?? 'unknown';
        
        if (!isset($this->reputation_data[$driver_id])) {
            $this->reputation_data[$driver_id] = array(
                'type' => 'driver',
                'install_count' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'verified' => false,
                'rating' => 0,
                'created_date' => current_time('mysql'),
                'last_activity' => current_time('mysql')
            );
        }
        
        $this->reputation_data[$driver_id]['success_count']++;
        $this->reputation_data[$driver_id]['last_activity'] = current_time('mysql');
        
        // Update success rate
        $this->update_success_rate($driver_id);
        $this->save_reputation_data();
        
        // Trigger reputation update check
        $this->update_verification_status($driver_id);
    }
    
    /**
     * Track failed post execution
     * 
     * @param array $post_data Post execution data
     */
    public function track_failed_post($post_data) {
        $driver_id = $post_data['driver_id'] ?? 'unknown';
        $error_message = $post_data['error'] ?? 'Unknown error';
        
        if (!isset($this->reputation_data[$driver_id])) {
            $this->reputation_data[$driver_id] = array(
                'type' => 'driver',
                'install_count' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'verified' => false,
                'rating' => 0,
                'created_date' => current_time('mysql'),
                'last_activity' => current_time('mysql')
            );
        }
        
        $this->reputation_data[$driver_id]['error_count']++;
        $this->reputation_data[$driver_id]['last_activity'] = current_time('mysql');
        
        // Log the error
        $this->log_error($driver_id, $error_message);
        
        // Update success rate
        $this->update_success_rate($driver_id);
        $this->save_reputation_data();
        
        // Check if verification should be revoked
        $this->update_verification_status($driver_id);
    }
    
    /**
     * Calculate and update success rate for an item
     * 
     * @param string $item_id Item identifier
     */
    private function update_success_rate($item_id) {
        if (!isset($this->reputation_data[$item_id])) {
            return;
        }
        
        $data = $this->reputation_data[$item_id];
        $total_attempts = $data['success_count'] + $data['error_count'];
        
        if ($total_attempts > 0) {
            $this->reputation_data[$item_id]['success_rate'] = ($data['success_count'] / $total_attempts) * 100;
        } else {
            $this->reputation_data[$item_id]['success_rate'] = 100;
        }
    }
    
    /**
     * Update verification status based on metrics
     * 
     * Verification criteria:
     * - Install count >= 100 AND Error rate <= 1%
     * 
     * @param string $item_id Item identifier
     */
    private function update_verification_status($item_id) {
        if (!isset($this->reputation_data[$item_id])) {
            return;
        }
        
        $data = $this->reputation_data[$item_id];
        $install_count = $data['install_count'] ?? 0;
        $error_rate = $this->calculate_error_rate($item_id);
        
        // Verification criteria
        $should_be_verified = ($install_count >= 100) && ($error_rate <= 1.0);
        
        // Update verification status
        if ($should_be_verified && !$data['verified']) {
            $this->reputation_data[$item_id]['verified'] = true;
            $this->reputation_data[$item_id]['verified_date'] = current_time('mysql');
            
            // Log verification event
            $this->log_verification_event($item_id, 'verified');
            
        } elseif (!$should_be_verified && $data['verified']) {
            $this->reputation_data[$item_id]['verified'] = false;
            $this->reputation_data[$item_id]['unverified_date'] = current_time('mysql');
            
            // Log unverification event
            $this->log_verification_event($item_id, 'unverified');
        }
    }
    
    /**
     * Calculate error rate for an item
     * 
     * @param string $item_id Item identifier
     * @return float Error rate percentage
     */
    private function calculate_error_rate($item_id) {
        if (!isset($this->reputation_data[$item_id])) {
            return 0;
        }
        
        $data = $this->reputation_data[$item_id];
        $total_attempts = $data['success_count'] + $data['error_count'];
        
        if ($total_attempts === 0) {
            return 0;
        }
        
        return ($data['error_count'] / $total_attempts) * 100;
    }
    
    /**
     * Log error for tracking and analysis
     * 
     * @param string $item_id Item identifier
     * @param string $error_message Error message
     */
    private function log_error($item_id, $error_message) {
        $error_entry = array(
            'item_id' => $item_id,
            'timestamp' => current_time('mysql'),
            'error' => $error_message,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $this->get_client_ip()
        );
        
        $this->error_logs[] = $error_entry;
        
        // Keep only last 1000 error logs
        if (count($this->error_logs) > 1000) {
            $this->error_logs = array_slice($this->error_logs, -1000);
        }
        
        update_option('smo_error_logs', $this->error_logs);
    }
    
    /**
     * Log verification event
     * 
     * @param string $item_id Item identifier
     * @param string $event 'verified' or 'unverified'
     */
    private function log_verification_event($item_id, $event) {
        $verification_log = get_option('smo_verification_log', array());
        $verification_log[] = array(
            'item_id' => $item_id,
            'event' => $event,
            'timestamp' => current_time('mysql'),
            'metrics' => $this->reputation_data[$item_id]
        );
        
        // Keep only last 100 verification events
        if (count($verification_log) > 100) {
            $verification_log = array_slice($verification_log, -100);
        }
        
        update_option('smo_verification_log', $verification_log);
    }
    
    /**
     * Get client IP address for logging
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Get reputation data for an item
     * 
     * @param string $item_id Item identifier
     * @return array|null Reputation data or null if not found
     */
    public function get_reputation_data($item_id) {
        return $this->reputation_data[$item_id] ?? null;
    }
    
    /**
     * Get all reputation data
     * 
     * @return array All reputation data
     */
    public function get_all_reputation_data() {
        return $this->reputation_data;
    }
    
    /**
     * Get verified items
     * 
     * @param string $type Filter by type ('driver', 'template', or null for all)
     * @return array Verified items
     */
    public function get_verified_items($type = null) {
        $verified_items = array();
        
        foreach ($this->reputation_data as $item_id => $data) {
            if ($data['verified'] && ($type === null || $data['type'] === $type)) {
                $verified_items[$item_id] = $data;
            }
        }
        
        return $verified_items;
    }
    
    /**
     * Get top performing items
     * 
     * @param string $type Filter by type ('driver', 'template', or null for all)
     * @param int $limit Number of items to return
     * @return array Top performing items
     */
    public function get_top_performing_items($type = null, $limit = 10) {
        $items = array();
        
        foreach ($this->reputation_data as $item_id => $data) {
            if ($type === null || $data['type'] === $type) {
                $items[$item_id] = $data;
            }
        }
        
        // Sort by install count
        uasort($items, function($a, $b) {
            return ($b['install_count'] ?? 0) - ($a['install_count'] ?? 0);
        });
        
        return array_slice($items, 0, $limit, true);
    }
    
    /**
     * Calculate trust score for an item
     * 
     * @param string $item_id Item identifier
     * @return float Trust score (0-100)
     */
    public function calculate_trust_score($item_id) {
        if (!isset($this->reputation_data[$item_id])) {
            return 0;
        }
        
        $data = $this->reputation_data[$item_id];
        $score = 0;
        
        // Install count factor (0-40 points)
        $install_count = $data['install_count'] ?? 0;
        $score += min(40, $install_count * 0.4);
        
        // Success rate factor (0-40 points)
        $success_rate = $data['success_rate'] ?? 100;
        $score += ($success_rate / 100) * 40;
        
        // Verification factor (0-20 points)
        if ($data['verified']) {
            $score += 20;
        }
        
        return min(100, round($score, 2));
    }
    
    /**
     * Periodic reputation check (runs every hour)
     */
    public function periodic_reputation_check() {
        // Check if it's time for a reputation update (every hour)
        $last_check = get_option('smo_last_reputation_check', 0);
        if (time() - $last_check < 3600) { // 1 hour
            return;
        }
        
        // Update all verification statuses
        foreach ($this->reputation_data as $item_id => $data) {
            $this->update_verification_status($item_id);
        }
        
        $this->save_reputation_data();
        update_option('smo_last_reputation_check', time());
    }
    
    /**
     * Save reputation data to database
     */
    private function save_reputation_data() {
        update_option('smo_reputation_data', $this->reputation_data);
    }
    
    /**
     * Get reputation statistics
     * 
     * @return array Reputation statistics
     */
    public function get_reputation_statistics() {
        $stats = array(
            'total_items' => count($this->reputation_data),
            'verified_items' => 0,
            'total_installs' => 0,
            'total_errors' => 0,
            'average_success_rate' => 0
        );
        
        $total_success_rate = 0;
        $items_with_ratings = 0;
        
        foreach ($this->reputation_data as $data) {
            if ($data['verified']) {
                $stats['verified_items']++;
            }
            
            $stats['total_installs'] += $data['install_count'] ?? 0;
            $stats['total_errors'] += $data['error_count'] ?? 0;
            
            if (isset($data['success_rate'])) {
                $total_success_rate += $data['success_rate'];
                $items_with_ratings++;
            }
        }
        
        if ($items_with_ratings > 0) {
            $stats['average_success_rate'] = round($total_success_rate / $items_with_ratings, 2);
        }
        
        return $stats;
    }
    
    /**
     * Export reputation data for external use
     * 
     * @param string $format Export format ('json' or 'csv')
     * @return string Exported data
     */
    public function export_reputation_data($format = 'json') {
        $data = array(
            'export_date' => current_time('mysql'),
            'statistics' => $this->get_reputation_statistics(),
            'items' => $this->reputation_data
        );
        
        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            $csv = "Item ID,Type,Install Count,Success Count,Error Count,Success Rate,Verified,Rating\n";
            
            foreach ($this->reputation_data as $item_id => $item_data) {
                $csv .= sprintf(
                    "%s,%s,%d,%d,%d,%.2f,%s,%.2f\n",
                    $item_id,
                    $item_data['type'],
                    $item_data['install_count'],
                    $item_data['success_count'],
                    $item_data['error_count'],
                    $item_data['success_rate'],
                    $item_data['verified'] ? 'Yes' : 'No',
                    $item_data['rating']
                );
            }
            
            return $csv;
        }
        
        return '';
    }
}