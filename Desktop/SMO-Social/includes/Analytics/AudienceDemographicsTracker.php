<?php
/**
 * Audience Demographics Tracking System
 * Tracks and analyzes audience demographics across all platforms
 */

namespace SMO_Social\Analytics;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * Audience Demographics Tracking System
 * 
 * Manages audience demographic data:
 * - Age, gender, and location insights
 * - Platform-specific demographic tracking
 * - Real-time demographic updates
 * - Demographic trend analysis
 * - Custom demographic segments
 */
class AudienceDemographicsTracker {
    
    private $table_names;
    private $supported_metrics;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'audience_demographics' => $wpdb->prefix . 'smo_audience_demographics',
            'demographic_trends' => $wpdb->prefix . 'smo_demographic_trends',
            'scheduled_posts' => $wpdb->prefix . 'smo_scheduled_posts'
        );
        
        $this->supported_metrics = array('age_range', 'gender', 'location_country', 'location_city');
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_get_demographics', array($this, 'ajax_get_demographics'));
        add_action('wp_ajax_smo_update_demographics', array($this, 'ajax_update_demographics'));
        add_action('wp_ajax_smo_sync_demographics', array($this, 'ajax_sync_demographics'));
        add_action('wp_ajax_smo_get_demographic_trends', array($this, 'ajax_get_demographic_trends'));
        add_action('wp_ajax_smo_get_demographic_insights', array($this, 'ajax_get_demographic_insights'));
        add_action('wp_ajax_smo_export_demographics', array($this, 'ajax_export_demographics'));
        
        // Schedule demographic synchronization
        add_action('smo_sync_demographics', array($this, 'sync_all_demographics'));
        if (!wp_next_scheduled('smo_sync_demographics')) {
            wp_schedule_event(time(), 'daily', 'smo_sync_demographics');
        }
    }
    
    /**
     * Sync demographics from all platforms
     */
    public function sync_all_demographics() {
        $platforms = $this->get_active_platforms();
        
        foreach ($platforms as $platform) {
            try {
                $this->sync_platform_demographics($platform);
            } catch (\Exception $e) {
                error_log("SMO Social: Failed to sync demographics for {$platform}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Sync demographics from specific platform
     */
    public function sync_platform_demographics($platform) {
        // Get platform API credentials
        $credentials = $this->get_platform_credentials($platform);
        if (!$credentials) {
            return;
        }
        
        // Fetch demographics data from platform API
        $demographics_data = $this->fetch_platform_demographics($platform, $credentials);
        
        if ($demographics_data) {
            $this->save_demographics_data($platform, $demographics_data);
        }
    }
    
    /**
     * Fetch demographics data from platform API
     */
    private function fetch_platform_demographics($platform, $credentials) {
        // This would make actual API calls to platform-specific endpoints
        // For now, returning mock data based on platform
        
        switch ($platform) {
            case 'facebook':
                return $this->get_facebook_demographics($credentials);
            case 'instagram':
                return $this->get_instagram_demographics($credentials);
            case 'linkedin':
                return $this->get_linkedin_demographics($credentials);
            case 'twitter':
                return $this->get_twitter_demographics($credentials);
            default:
                return $this->get_generic_demographics($platform);
        }
    }
    
    /**
     * Get Facebook demographics
     */
    private function get_facebook_demographics($credentials) {
        // Mock data - in production, this would call Facebook Graph API
        return array(
            array('age_range' => '18-24', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'New York', 'percentage' => 15.2),
            array('age_range' => '18-24', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'New York', 'percentage' => 18.1),
            array('age_range' => '25-34', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'New York', 'percentage' => 22.3),
            array('age_range' => '25-34', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'New York', 'percentage' => 25.7),
            array('age_range' => '35-44', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'New York', 'percentage' => 12.8),
            array('age_range' => '35-44', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'New York', 'percentage' => 5.9)
        );
    }
    
    /**
     * Get Instagram demographics
     */
    private function get_instagram_demographics($credentials) {
        // Mock data - in production, this would call Instagram Basic Display API
        return array(
            array('age_range' => '18-24', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'Los Angeles', 'percentage' => 28.4),
            array('age_range' => '18-24', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'Los Angeles', 'percentage' => 19.7),
            array('age_range' => '25-34', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'Los Angeles', 'percentage' => 24.1),
            array('age_range' => '25-34', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'Los Angeles', 'percentage' => 18.3),
            array('age_range' => '35-44', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'Los Angeles', 'percentage' => 6.2),
            array('age_range' => '35-44', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'Los Angeles', 'percentage' => 3.3)
        );
    }
    
    /**
     * Get LinkedIn demographics
     */
    private function get_linkedin_demographics($credentials) {
        // Mock data - in production, this would call LinkedIn API
        return array(
            array('age_range' => '25-34', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'San Francisco', 'percentage' => 35.2),
            array('age_range' => '25-34', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'San Francisco', 'percentage' => 31.8),
            array('age_range' => '35-44', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'San Francisco', 'percentage' => 18.9),
            array('age_range' => '35-44', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'San Francisco', 'percentage' => 12.4),
            array('age_range' => '45-54', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'San Francisco', 'percentage' => 1.7)
        );
    }
    
    /**
     * Get Twitter demographics
     */
    private function get_twitter_demographics($credentials) {
        // Mock data - in production, this would call Twitter API
        return array(
            array('age_range' => '18-24', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'Seattle', 'percentage' => 22.1),
            array('age_range' => '18-24', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'Seattle', 'percentage' => 19.8),
            array('age_range' => '25-34', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'Seattle', 'percentage' => 27.3),
            array('age_range' => '25-34', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'Seattle', 'percentage' => 21.6),
            array('age_range' => '35-44', 'gender' => 'male', 'location_country' => 'United States', 'location_city' => 'Seattle', 'percentage' => 6.7),
            array('age_range' => '35-44', 'gender' => 'female', 'location_country' => 'United States', 'location_city' => 'Seattle', 'percentage' => 2.5)
        );
    }
    
    /**
     * Get generic demographics
     */
    private function get_generic_demographics($platform) {
        // Default demographics for platforms without specific implementation
        return array(
            array('age_range' => '25-34', 'gender' => 'unknown', 'location_country' => 'Unknown', 'location_city' => 'Unknown', 'percentage' => 100.0)
        );
    }
    
    /**
     * Save demographics data
     */
    private function save_demographics_data($platform, $demographics_data) {
        global $wpdb;
        
        foreach ($demographics_data as $data) {
            // Check if record already exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$this->table_names['audience_demographics']} 
                 WHERE platform = %s AND age_range = %s AND gender = %s AND location_country = %s AND location_city = %s",
                $platform, $data['age_range'], $data['gender'], $data['location_country'], $data['location_city']
            ));
            
            if ($existing) {
                // Update existing record
                $wpdb->update(
                    $this->table_names['audience_demographics'],
                    array(
                        'percentage' => $data['percentage'],
                        'recorded_at' => current_time('mysql')
                    ),
                    array('id' => $existing->id),
                    array('%f', '%s'),
                    array('%d')
                );
            } else {
                // Insert new record
                $wpdb->insert(
                    $this->table_names['audience_demographics'],
                    array(
                        'platform' => $platform,
                        'age_range' => $data['age_range'],
                        'gender' => $data['gender'],
                        'location_country' => $data['location_country'],
                        'location_city' => $data['location_city'],
                        'percentage' => $data['percentage'],
                        'recorded_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%f', '%s')
                );
            }
        }
    }
    
    /**
     * Get demographics data with filtering
     */
    public function get_demographics($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['platform'])) {
            $where_conditions[] = "platform = %s";
            $where_values[] = $filters['platform'];
        }
        
        if (!empty($filters['age_range'])) {
            $where_conditions[] = "age_range = %s";
            $where_values[] = $filters['age_range'];
        }
        
        if (!empty($filters['gender'])) {
            $where_conditions[] = "gender = %s";
            $where_values[] = $filters['gender'];
        }
        
        if (!empty($filters['location_country'])) {
            $where_conditions[] = "location_country = %s";
            $where_values[] = $filters['location_country'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "recorded_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "recorded_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT platform, age_range, gender, location_country, location_city, 
                         SUM(percentage) as total_percentage, 
                         MAX(recorded_at) as last_updated
                  FROM {$this->table_names['audience_demographics']}
                  $where_clause
                  GROUP BY platform, age_range, gender, location_country, location_city
                  ORDER BY total_percentage DESC";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get demographic trends over time
     */
    public function get_demographic_trends($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['platform'])) {
            $where_conditions[] = "platform = %s";
            $where_values[] = $filters['platform'];
        }
        
        if (!empty($filters['metric'])) {
            $where_conditions[] = "metric_type = %s";
            $where_values[] = $filters['metric'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "recorded_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "recorded_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT DATE(recorded_at) as date, 
                         metric_type, 
                         SUM(metric_value) as total_value
                  FROM {$this->table_names['demographic_trends']}
                  $where_clause
                  GROUP BY DATE(recorded_at), metric_type
                  ORDER BY date ASC";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get demographic insights
     */
    public function get_demographic_insights($filters = array()) {
        $demographics = $this->get_demographics($filters);
        $insights = array();
        
        // Analyze age distribution
        $age_distribution = array();
        foreach ($demographics as $demo) {
            if (!isset($age_distribution[$demo['age_range']])) {
                $age_distribution[$demo['age_range']] = 0;
            }
            $age_distribution[$demo['age_range']] += $demo['total_percentage'];
        }
        
        arsort($age_distribution);
        $primary_age_group = array_key_first($age_distribution);
        
        if ($primary_age_group) {
            $insights[] = array(
                'type' => 'demographic',
                'title' => 'Primary Age Group',
                'description' => "Your primary audience is {$primary_age_group} years old ({$age_distribution[$primary_age_group]}% of total audience)",
                'priority' => 'high'
            );
        }
        
        // Analyze gender distribution
        $gender_distribution = array();
        foreach ($demographics as $demo) {
            $gender = $demo['gender'] === 'unknown' ? 'Not Specified' : ucfirst($demo['gender']);
            if (!isset($gender_distribution[$gender])) {
                $gender_distribution[$gender] = 0;
            }
            $gender_distribution[$gender] += $demo['total_percentage'];
        }
        
        $top_gender = array_keys($gender_distribution)[0] ?? 'Unknown';
        $top_gender_percentage = reset($gender_distribution);
        
        if ($top_gender_percentage > 60) {
            $insights[] = array(
                'type' => 'demographic',
                'title' => 'Gender Skew',
                'description' => "Your audience is heavily skewed toward {$top_gender} users ({$top_gender_percentage}%)",
                'priority' => 'medium'
            );
        }
        
        // Analyze geographic distribution
        $location_distribution = array();
        foreach ($demographics as $demo) {
            $country = $demo['location_country'] === 'Unknown' ? 'Not Specified' : $demo['location_country'];
            if (!isset($location_distribution[$country])) {
                $location_distribution[$country] = 0;
            }
            $location_distribution[$country] += $demo['total_percentage'];
        }
        
        arsort($location_distribution);
        $top_country = array_key_first($location_distribution);
        $top_country_percentage = reset($location_distribution);
        
        if ($top_country_percentage > 50) {
            $insights[] = array(
                'type' => 'geographic',
                'title' => 'Geographic Concentration',
                'description' => "Your audience is heavily concentrated in {$top_country} ({$top_country_percentage}%)",
                'priority' => 'medium'
            );
        }
        
        // Platform-specific insights
        $platform_distribution = array();
        foreach ($demographics as $demo) {
            if (!isset($platform_distribution[$demo['platform']])) {
                $platform_distribution[$demo['platform']] = 0;
            }
            $platform_distribution[$demo['platform']] += $demo['total_percentage'];
        }
        
        $dominant_platform = array_keys($platform_distribution)[0] ?? 'unknown';
        
        $insights[] = array(
            'type' => 'platform',
            'title' => 'Most Demographically Diverse Platform',
            'description' => ucfirst($dominant_platform) . " shows the most diverse demographic distribution",
            'priority' => 'low'
        );
        
        return $insights;
    }
    
    /**
     * Export demographics data
     */
    public function export_demographics($filters = array(), $format = 'csv') {
        $demographics = $this->get_demographics($filters);
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($demographics);
            case 'json':
                return json_encode($demographics, JSON_PRETTY_PRINT);
            default:
                throw new \Exception('Unsupported export format');
        }
    }
    
    /**
     * Export demographics to CSV
     */
    private function export_to_csv($demographics) {
        $csv = "Platform,Age Range,Gender,Country,City,Percentage,Last Updated\n";
        
        foreach ($demographics as $demo) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%.2f,%s\n",
                $demo['platform'],
                $demo['age_range'],
                $demo['gender'],
                $demo['location_country'],
                $demo['location_city'],
                $demo['total_percentage'],
                $demo['last_updated']
            );
        }
        
        return $csv;
    }
    
    /**
     * Get active platforms
     */
    private function get_active_platforms() {
        $platforms = array();
        $driver_dir = SMO_SOCIAL_PLUGIN_DIR . 'drivers/';
        
        if (is_dir($driver_dir)) {
            $files = glob($driver_dir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['slug'])) {
                    $platforms[] = $data['slug'];
                }
            }
        }
        
        return $platforms;
    }
    
    /**
     * Get platform credentials
     */
    private function get_platform_credentials($platform) {
        $option_key = "smo_{$platform}_credentials";
        $credentials = get_option($option_key);
        
        return $credentials ? json_decode($credentials, true) : null;
    }
    
    /**
     * Get demographic summary statistics
     */
    public function get_demographic_summary($filters = array()) {
        $demographics = $this->get_demographics($filters);
        
        $summary = array(
            'total_segments' => count($demographics),
            'unique_countries' => count(array_unique(array_column($demographics, 'location_country'))),
            'unique_age_groups' => count(array_unique(array_column($demographics, 'age_range'))),
            'gender_distribution' => array(),
            'top_countries' => array(),
            'age_distribution' => array()
        );
        
        // Gender distribution
        foreach ($demographics as $demo) {
            $gender = $demo['gender'] === 'unknown' ? 'Not Specified' : ucfirst($demo['gender']);
            if (!isset($summary['gender_distribution'][$gender])) {
                $summary['gender_distribution'][$gender] = 0;
            }
            $summary['gender_distribution'][$gender] += $demo['total_percentage'];
        }
        
        // Top countries
        $countries = array();
        foreach ($demographics as $demo) {
            $country = $demo['location_country'] === 'Unknown' ? 'Not Specified' : $demo['location_country'];
            if (!isset($countries[$country])) {
                $countries[$country] = 0;
            }
            $countries[$country] += $demo['total_percentage'];
        }
        
        arsort($countries);
        $summary['top_countries'] = array_slice($countries, 0, 5, true);
        
        // Age distribution
        $ages = array();
        foreach ($demographics as $demo) {
            if (!isset($ages[$demo['age_range']])) {
                $ages[$demo['age_range']] = 0;
            }
            $ages[$demo['age_range']] += $demo['total_percentage'];
        }
        
        $summary['age_distribution'] = $ages;
        
        return $summary;
    }
    
        /**
     * Get demographics summary for dashboard widgets
     */
    public function get_demographics_summary($filters = array()) {
        $demographics = $this->get_demographics($filters);
        $age_distribution = [];
        $gender_distribution = [];
        $location_counts = [];

        foreach ($demographics as $demo) {
            $age = $demo['age_range'];
            $gender = $demo['gender'];
            $country = $demo['location_country'];
            $city = $demo['location_city'];
            $perc = $demo['total_percentage'];

            $age_distribution[$age] = ($age_distribution[$age] ?? 0) + $perc;
            $gender_distribution[$gender] = ($gender_distribution[$gender] ?? 0) + $perc;
            $locKey = $country . ' - ' . $city;
            $location_counts[$locKey] = ($location_counts[$locKey] ?? 0) + $perc;
        }

        arsort($age_distribution);
        arsort($gender_distribution);
        arsort($location_counts);

        $top_locations = [];
        foreach (array_slice($location_counts, 0, 5, true) as $key => $perc) {
            [$country, $city] = explode(' - ', $key);
            $top_locations[] = [
                'name' => $city,
                'country' => $country,
                'percentage' => round($perc, 1)
            ];
        }

        return [
            'age_distribution' => $age_distribution,
            'gender_distribution' => $gender_distribution,
            'top_locations' => $top_locations,
        ];
    }
    // AJAX handlers
    
    public function ajax_get_demographics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'platform' => sanitize_text_field($_POST['platform'] ?? ''),
            'age_range' => sanitize_text_field($_POST['age_range'] ?? ''),
            'gender' => sanitize_text_field($_POST['gender'] ?? ''),
            'location_country' => sanitize_text_field($_POST['location_country'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );
        
        $demographics = $this->get_demographics($filters);
        wp_send_json_success($demographics);
    }
    
    public function ajax_sync_demographics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        try {
            if ($platform) {
                $this->sync_platform_demographics($platform);
                wp_send_json_success(__("Demographics synchronized for {$platform}"));
            } else {
                $this->sync_all_demographics();
                wp_send_json_success(__('All platform demographics synchronized'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_demographic_trends() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'platform' => sanitize_text_field($_POST['platform'] ?? ''),
            'metric' => sanitize_text_field($_POST['metric'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );
        
        $trends = $this->get_demographic_trends($filters);
        wp_send_json_success($trends);
    }
    
    public function ajax_get_demographic_insights() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'platform' => sanitize_text_field($_POST['platform'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );
        
        $insights = $this->get_demographic_insights($filters);
        wp_send_json_success($insights);
    }
    
    public function ajax_export_demographics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'platform' => sanitize_text_field($_POST['platform'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        try {
            $export_data = $this->export_demographics($filters, $format);
            wp_send_json_success(array('data' => $export_data));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}