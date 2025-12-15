<?php
/**
 * Network Groupings Manager
 * 
 * Manages user-defined network groups for organizing social media accounts
 * 
 * @package SMO_Social
 */

namespace SMO_Social\Features;

if (!defined('ABSPATH')) {
    exit;
}

class NetworkGroupingsManager {
    
    /**
     * Get all network groups for a user
     */
    public static function get_user_groups($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'smo_network_groupings';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY group_name ASC",
            $user_id
        ), ARRAY_A);
        
        // Parse JSON platforms
        foreach ($results as &$group) {
            $group['platforms'] = json_decode($group['platforms'], true) ?: array();
        }
        
        return $results;
    }
    
    /**
     * Get a specific network group
     */
    public static function get_group($group_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_network_groupings';
        
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $group_id
        ), ARRAY_A);
        
        if ($group) {
            $group['platforms'] = json_decode($group['platforms'], true) ?: array();
        }
        
        return $group;
    }
    
    /**
     * Create a new network group
     */
    public static function create_group($group_name, $platforms, $description = '', $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Validate platforms
        if (!is_array($platforms) || empty($platforms)) {
            return new \WP_Error('invalid_platforms', 'Platforms must be a non-empty array');
        }
        
        $table_name = $wpdb->prefix . 'smo_network_groupings';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'group_name' => sanitize_text_field($group_name),
                'description' => sanitize_textarea_field($description),
                'platforms' => json_encode($platforms),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to create network group');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a network group
     */
    public static function update_group($group_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_network_groupings';
        
        // Verify ownership
        $group = self::get_group($group_id);
        if (!$group || $group['user_id'] != get_current_user_id()) {
            return new \WP_Error('unauthorized', 'You do not have permission to update this group');
        }
        
        $update_data = array();
        $format = array();
        
        if (isset($data['group_name'])) {
            $update_data['group_name'] = sanitize_text_field($data['group_name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['platforms'])) {
            if (!is_array($data['platforms'])) {
                return new \WP_Error('invalid_platforms', 'Platforms must be an array');
            }
            $update_data['platforms'] = json_encode($data['platforms']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return new \WP_Error('no_data', 'No data to update');
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $group_id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a network group
     */
    public static function delete_group($group_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_network_groupings';
        
        // Verify ownership
        $group = self::get_group($group_id);
        if (!$group || $group['user_id'] != get_current_user_id()) {
            return new \WP_Error('unauthorized', 'You do not have permission to delete this group');
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $group_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get platforms in a group
     */
    public static function get_group_platforms($group_id) {
        $group = self::get_group($group_id);
        
        if (!$group) {
            return array();
        }
        
        return $group['platforms'];
    }
    
    /**
     * Add platform to group
     */
    public static function add_platform_to_group($group_id, $platform_slug, $account_id = null) {
        $group = self::get_group($group_id);
        
        if (!$group) {
            return new \WP_Error('group_not_found', 'Network group not found');
        }
        
        $platforms = $group['platforms'];
        
        // Check if platform already exists
        foreach ($platforms as $platform) {
            if ($platform['slug'] === $platform_slug && 
                (!$account_id || $platform['account_id'] === $account_id)) {
                return true; // Already exists
            }
        }
        
        // Add new platform
        $platforms[] = array(
            'slug' => $platform_slug,
            'account_id' => $account_id,
            'added_at' => current_time('mysql')
        );
        
        return self::update_group($group_id, array('platforms' => $platforms));
    }
    
    /**
     * Remove platform from group
     */
    public static function remove_platform_from_group($group_id, $platform_slug, $account_id = null) {
        $group = self::get_group($group_id);
        
        if (!$group) {
            return new \WP_Error('group_not_found', 'Network group not found');
        }
        
        $platforms = $group['platforms'];
        $updated_platforms = array();
        
        foreach ($platforms as $platform) {
            if ($platform['slug'] !== $platform_slug || 
                ($account_id && $platform['account_id'] !== $account_id)) {
                $updated_platforms[] = $platform;
            }
        }
        
        return self::update_group($group_id, array('platforms' => $updated_platforms));
    }
    
    /**
     * Get groups containing a specific platform
     */
    public static function get_groups_by_platform($platform_slug, $user_id = null) {
        $all_groups = self::get_user_groups($user_id);
        $matching_groups = array();
        
        foreach ($all_groups as $group) {
            foreach ($group['platforms'] as $platform) {
                if ($platform['slug'] === $platform_slug) {
                    $matching_groups[] = $group;
                    break;
                }
            }
        }
        
        return $matching_groups;
    }
    
    /**
     * Get default network groups (suggestions)
     */
    public static function get_default_groups() {
        return array(
            array(
                'name' => 'Professional Networks',
                'description' => 'LinkedIn and other professional platforms',
                'platforms' => array('linkedin', 'xing'),
                'icon' => '💼'
            ),
            array(
                'name' => 'Visual Content',
                'description' => 'Image and video-focused platforms',
                'platforms' => array('instagram', 'pinterest', 'tiktok'),
                'icon' => '📸'
            ),
            array(
                'name' => 'News & Updates',
                'description' => 'Quick updates and news sharing',
                'platforms' => array('twitter', 'facebook'),
                'icon' => '📰'
            ),
            array(
                'name' => 'Video Platforms',
                'description' => 'Long-form video content',
                'platforms' => array('youtube', 'vimeo'),
                'icon' => '🎥'
            ),
            array(
                'name' => 'Community Engagement',
                'description' => 'Community and discussion platforms',
                'platforms' => array('reddit', 'discord', 'facebook'),
                'icon' => '👥'
            )
        );
    }
    
    /**
     * Create default groups for a user
     */
    public static function create_default_groups($user_id = null) {
        $defaults = self::get_default_groups();
        $created = array();
        
        foreach ($defaults as $default) {
            $platforms = array();
            foreach ($default['platforms'] as $platform_slug) {
                $platforms[] = array(
                    'slug' => $platform_slug,
                    'account_id' => null,
                    'added_at' => current_time('mysql')
                );
            }
            
            $group_id = self::create_group(
                $default['name'],
                $platforms,
                $default['description'],
                $user_id
            );
            
            if (!is_wp_error($group_id)) {
                $created[] = $group_id;
            }
        }
        
        return $created;
    }
    
    /**
     * Get statistics for a group
     */
    public static function get_group_stats($group_id) {
        global $wpdb;
        
        $group = self::get_group($group_id);
        
        if (!$group) {
            return null;
        }
        
        $platform_slugs = array_column($group['platforms'], 'slug');
        
        if (empty($platform_slugs)) {
            return array(
                'total_posts' => 0,
                'total_reach' => 0,
                'avg_engagement' => 0
            );
        }
        
        $placeholders = implode(',', array_fill(0, count($platform_slugs), '%s'));
        $analytics_table = $wpdb->prefix . 'smo_post_analytics';
        
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_posts,
                SUM(reach) as total_reach,
                AVG(engagement_rate) as avg_engagement
            FROM $analytics_table
            WHERE platform_slug IN ($placeholders)
            AND user_id = %d",
            array_merge($platform_slugs, array($group['user_id']))
        );
        
        $stats = $wpdb->get_row($query, ARRAY_A);
        
        return array(
            'total_posts' => intval($stats['total_posts'] ?? 0),
            'total_reach' => intval($stats['total_reach'] ?? 0),
            'avg_engagement' => floatval($stats['avg_engagement'] ?? 0)
        );
    }
}
