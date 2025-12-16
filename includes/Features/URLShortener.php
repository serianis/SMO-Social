<?php
/**
 * URL Shortener Integration
 * 
 * Integrates with Bitly, Rebrandly, and Sniply for URL shortening and tracking
 * 
 * @package SMO_Social
 */

namespace SMO_Social\Features;

if (!defined('ABSPATH')) {
    exit;
}

class URLShortener {
    
    private static $providers = array(
        'bitly' => 'Bitly',
        'rebrandly' => 'Rebrandly',
        'sniply' => 'Sniply',
        'tinyurl' => 'TinyURL'
    );
    
    /**
     * Get available providers
     */
    public static function get_providers() {
        return self::$providers;
    }
    
    /**
     * Shorten URL using specified provider
     */
    public static function shorten_url($url, $provider = 'bitly', $options = array()) {
        $method = "shorten_with_{$provider}";
        
        if (!method_exists(__CLASS__, $method)) {
            return new \WP_Error('invalid_provider', 'Invalid URL shortener provider');
        }
        
        $result = self::$method($url, $options);
        
        // Save to database
        if (!is_wp_error($result)) {
            self::save_shortened_url($url, $result['short_url'], $provider, $result);
        }
        
        return $result;
    }
    
    /**
     * Shorten with Bitly
     */
    private static function shorten_with_bitly($url, $options = array()) {
        $api_key = get_option('smo_social_bitly_api_key');
        
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'Bitly API key not configured');
        }
        
        $response = wp_remote_post('https://api-ssl.bitly.com/v4/shorten', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'long_url' => $url,
                'domain' => $options['domain'] ?? 'bit.ly'
            )),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['link'])) {
            return array(
                'short_url' => $body['link'],
                'provider' => 'bitly',
                'id' => $body['id'] ?? '',
                'created_at' => $body['created_at'] ?? current_time('mysql')
            );
        }
        
        return new \WP_Error('bitly_error', $body['message'] ?? 'Failed to shorten URL');
    }
    
    /**
     * Shorten with Rebrandly
     */
    private static function shorten_with_rebrandly($url, $options = array()) {
        $api_key = get_option('smo_social_rebrandly_api_key');
        
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'Rebrandly API key not configured');
        }
        
        $post_data = array(
            'destination' => $url,
            'domain' => array('fullName' => $options['domain'] ?? 'rebrand.ly')
        );
        
        if (!empty($options['slashtag'])) {
            $post_data['slashtag'] = $options['slashtag'];
        }
        
        $response = wp_remote_post('https://api.rebrandly.com/v1/links', array(
            'headers' => array(
                'apikey' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($post_data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['shortUrl'])) {
            return array(
                'short_url' => 'https://' . $body['shortUrl'],
                'provider' => 'rebrandly',
                'id' => $body['id'] ?? '',
                'created_at' => $body['createdAt'] ?? current_time('mysql')
            );
        }
        
        return new \WP_Error('rebrandly_error', $body['message'] ?? 'Failed to shorten URL');
    }
    
    /**
     * Shorten with Sniply
     */
    private static function shorten_with_sniply($url, $options = array()) {
        $api_key = get_option('smo_social_sniply_api_key');
        
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'Sniply API key not configured');
        }
        
        $post_data = array(
            'url' => $url,
            'cta_id' => $options['cta_id'] ?? null
        );
        
        $response = wp_remote_post('https://snip.ly/api/v1/links', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($post_data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['short_url'])) {
            return array(
                'short_url' => $body['short_url'],
                'provider' => 'sniply',
                'id' => $body['id'] ?? '',
                'created_at' => current_time('mysql')
            );
        }
        
        return new \WP_Error('sniply_error', $body['message'] ?? 'Failed to shorten URL');
    }
    
    /**
     * Shorten with TinyURL (no API key required)
     */
    private static function shorten_with_tinyurl($url, $options = array()) {
        $response = wp_remote_get('https://tinyurl.com/api-create.php?url=' . urlencode($url), array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $short_url = wp_remote_retrieve_body($response);
        
        if (!empty($short_url) && filter_var($short_url, FILTER_VALIDATE_URL)) {
            return array(
                'short_url' => $short_url,
                'provider' => 'tinyurl',
                'id' => '',
                'created_at' => current_time('mysql')
            );
        }
        
        return new \WP_Error('tinyurl_error', 'Failed to shorten URL');
    }
    
    /**
     * Save shortened URL to database
     */
    private static function save_shortened_url($original_url, $short_url, $provider, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_url_shorteners';
        
        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'original_url' => $original_url,
                'short_url' => $short_url,
                'provider' => $provider,
                'provider_id' => $data['id'] ?? '',
                'clicks' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Get shortened URL from database
     */
    public static function get_shortened_url($original_url, $provider = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_url_shorteners';
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE original_url = %s",
            $original_url
        );
        
        if ($provider) {
            $query .= $wpdb->prepare(" AND provider = %s", $provider);
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 1";
        
        return $wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Get click statistics
     */
    public static function get_click_stats($short_url = null, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'smo_url_shorteners';
        
        if ($short_url) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE short_url = %s AND user_id = %d",
                $short_url,
                $user_id
            ), ARRAY_A);
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Update click count
     */
    public static function update_click_count($short_url, $clicks) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_url_shorteners';
        
        return $wpdb->update(
            $table_name,
            array('clicks' => $clicks),
            array('short_url' => $short_url),
            array('%d'),
            array('%s')
        );
    }
    
    /**
     * Sync click statistics from provider
     */
    public static function sync_stats($provider = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_url_shorteners';
        $user_id = get_current_user_id();
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        );
        
        if ($provider) {
            $query .= $wpdb->prepare(" AND provider = %s", $provider);
        }
        
        $urls = $wpdb->get_results($query, ARRAY_A);
        
        $synced = 0;
        
        foreach ($urls as $url_data) {
            $method = "get_{$url_data['provider']}_stats";
            
            if (method_exists(__CLASS__, $method)) {
                $stats = self::$method($url_data['provider_id']);
                
                if (!is_wp_error($stats) && isset($stats['clicks'])) {
                    self::update_click_count($url_data['short_url'], $stats['clicks']);
                    $synced++;
                }
            }
        }
        
        return $synced;
    }
    
    /**
     * Get Bitly stats
     */
    private static function get_bitly_stats($link_id) {
        $api_key = get_option('smo_social_bitly_api_key');
        
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'Bitly API key not configured');
        }
        
        $response = wp_remote_get("https://api-ssl.bitly.com/v4/bitlinks/{$link_id}/clicks/summary", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'clicks' => $body['total_clicks'] ?? 0
        );
    }
    
    /**
     * Get Rebrandly stats
     */
    private static function get_rebrandly_stats($link_id) {
        $api_key = get_option('smo_social_rebrandly_api_key');
        
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'Rebrandly API key not configured');
        }
        
        $response = wp_remote_get("https://api.rebrandly.com/v1/links/{$link_id}", array(
            'headers' => array(
                'apikey' => $api_key
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'clicks' => $body['clicks'] ?? 0
        );
    }
}
