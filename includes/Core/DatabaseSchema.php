<?php
/**
 * SMO Social Enhanced Database Schema
 *
 * Creates comprehensive database tables for all advanced social media management features
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Database Schema Manager
 *
 * Manages creation and maintenance of all database tables for advanced features
 */
class DatabaseSchema {

    /**
     * Create entity platforms table for normalized platform relationships
     */
    private static function create_entity_platforms_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_entity_platforms';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            platform_slug varchar(50) NOT NULL,
            platform_config longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_platform (platform_slug),
            UNIQUE KEY unique_entity_platform (entity_type, entity_id, platform_slug)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }

    /**
     * Create post media table for normalized media attachments
     */
    private static function create_post_media_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_post_media';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            media_url varchar(1000) NOT NULL,
            media_type varchar(50) NOT NULL,
            media_order int(11) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_media_order (post_id, media_order)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }

    /**
     * Create transformation rules table for normalized content transformation
     */
    private static function create_transformation_rules_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_transformation_rules';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            rule_type varchar(50) NOT NULL,
            rule_config longtext NOT NULL,
            rule_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_template_id (template_id),
            KEY idx_rule_order (template_id, rule_order)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }

    /**
     * Create transformation variables table for normalized content transformation
     */
    private static function create_transformation_variables_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_transformation_variables';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            variable_name varchar(100) NOT NULL,
            variable_type varchar(50) NOT NULL,
            default_value text,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_template_id (template_id),
            UNIQUE KEY unique_template_variable (template_id, variable_name)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }

    /**
     * Create all enhanced database tables
     */
    public static function create_enhanced_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create normalized tables first
        self::create_entity_platforms_table($charset_collate);
        self::create_post_media_table($charset_collate);
        self::create_transformation_rules_table($charset_collate);
        self::create_transformation_variables_table($charset_collate);

        // Posts per day analytics table
        self::create_posts_per_day_table($charset_collate);

        // Auto-publish WordPress content table
        self::create_auto_publish_table($charset_collate);

        // Enhanced templates table
        self::create_enhanced_templates_table($charset_collate);

        // Team management tables
        self::create_team_management_tables($charset_collate);

        // Network groupings table
        self::create_network_groupings_table($charset_collate);

        // URL shortener table
        self::create_url_shortener_table($charset_collate);

        // Best time predictions table
        self::create_best_time_predictions_table($charset_collate);

        // Image editor data table
        self::create_image_editor_table($charset_collate);

        // Reshare queue table
        self::create_reshare_queue_table($charset_collate);

        // Enhanced content calendar table
        self::create_enhanced_calendar_table($charset_collate);

        // Team calendar table
        self::create_team_calendar_table($charset_collate);

        // WordPress multisite support
        self::create_multisite_support_table($charset_collate);

        // Import automation and sharing tables
        self::create_import_automation_tables($charset_collate);

        // Enhanced media sharing tables
        self::create_media_sharing_tables($charset_collate);

        // Advanced calendar analytics tables
        self::create_advanced_calendar_analytics_tables($charset_collate);

        // Content categories tables
        self::create_content_categories_tables($charset_collate);

        // Content tags table for custom analytics
        self::create_content_tags_table($charset_collate);

        // Post individual analytics table
        self::create_post_individual_analytics_table($charset_collate);

        // Platform management tables
        self::create_platform_tables($charset_collate);

        // Advanced memory monitoring tables
        self::create_memory_monitoring_tables($charset_collate);
    }
    
    /**
     * Posts per day analytics table
     */
    private static function create_posts_per_day_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_posts_per_day_analytics';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            date DATE NOT NULL,
            platform varchar(50) NOT NULL,
            post_count int(11) NOT NULL DEFAULT 0,
            published_count int(11) NOT NULL DEFAULT 0,
            failed_count int(11) NOT NULL DEFAULT 0,
            total_engagement bigint(20) DEFAULT 0,
            total_reach bigint(20) DEFAULT 0,
            avg_post_length int(11) DEFAULT 0,
            hashtags_used int(11) DEFAULT 0,
            links_added int(11) DEFAULT 0,
            images_attached int(11) DEFAULT 0,
            videos_attached int(11) DEFAULT 0,
            best_performing_post_id bigint(20) DEFAULT NULL,
            engagement_rate decimal(5,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_date_platform (user_id, date, platform),
            KEY idx_date (date),
            KEY idx_user_date (user_id, date),
            KEY idx_platform (platform)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Auto-publish WordPress content table
     */
    private static function create_auto_publish_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            site_id bigint(20) unsigned DEFAULT 1,
            title text NOT NULL,
            content longtext NOT NULL,
            excerpt text,
            featured_image_id bigint(20) DEFAULT NULL,
            categories text,
            tags text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            publish_date datetime NOT NULL,
            actual_publish_date datetime DEFAULT NULL,
            platforms text NOT NULL,
            auto_hashtags boolean DEFAULT 0,
            auto_optimize boolean DEFAULT 1,
            custom_message text,
            priority varchar(10) NOT NULL DEFAULT 'normal',
            retry_count int(11) NOT NULL DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_user_id (user_id),
            KEY idx_site_id (site_id),
            KEY idx_status (status),
            KEY idx_publish_date (publish_date),
            KEY idx_priority (priority)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Enhanced templates table
     */
    private static function create_enhanced_templates_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_enhanced_templates';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100) DEFAULT 'general',
            content_template longtext NOT NULL,
            design_config longtext,
            image_template_url varchar(500) DEFAULT NULL,
            video_template_url varchar(500) DEFAULT NULL,
            platforms text NOT NULL,
            variables text,
            usage_count int(11) NOT NULL DEFAULT 0,
            rating decimal(2,1) DEFAULT 0.0,
            is_public boolean DEFAULT 0,
            is_featured boolean DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_category (category),
            KEY idx_usage_count (usage_count),
            KEY idx_rating (rating),
            KEY idx_is_public (is_public),
            KEY idx_is_featured (is_featured)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Team management tables
     */
    private static function create_team_management_tables($charset_collate) {
        global $wpdb;
        
        // Team members table
        $members_table = $wpdb->prefix . 'smo_team_members';
        $sql = "CREATE TABLE $members_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            team_name varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            role varchar(50) NOT NULL DEFAULT 'member',
            permissions text,
            assigned_platforms text,
            assigned_network_groups text,
            assigned_url_tracking text,
            is_active boolean DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_team (user_id, team_name),
            KEY idx_team_name (team_name),
            KEY idx_role (role),
            KEY idx_is_active (is_active)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
        
        // Team assignments table
        $assignments_table = $wpdb->prefix . 'smo_team_assignments';
        $sql = "CREATE TABLE $assignments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            team_name varchar(255) NOT NULL,
            assigned_user_id bigint(20) unsigned NOT NULL,
            assigning_user_id bigint(20) unsigned NOT NULL,
            assignment_type varchar(50) NOT NULL,
            assignment_data text NOT NULL,
            is_active boolean DEFAULT 1,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_team_name (team_name),
            KEY idx_assigned_user (assigned_user_id),
            KEY idx_assignment_type (assignment_type),
            KEY idx_is_active (is_active)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Network groupings table
     */
    private static function create_network_groupings_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_network_groupings';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            group_name varchar(255) NOT NULL,
            group_description text,
            platforms text NOT NULL,
            usage_frequency varchar(20) DEFAULT 'medium',
            best_times text,
            is_default boolean DEFAULT 0,
            usage_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_group_name (group_name),
            KEY idx_usage_frequency (usage_frequency),
            KEY idx_is_default (is_default)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * URL shortener table
     */
    private static function create_url_shortener_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_url_shorteners';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            provider varchar(50) NOT NULL,
            original_url varchar(2000) NOT NULL,
            shortened_url varchar(500) NOT NULL,
            short_code varchar(100) NOT NULL,
            tracking_id varchar(100) DEFAULT NULL,
            campaign_name varchar(255) DEFAULT NULL,
            click_count int(11) NOT NULL DEFAULT 0,
            engagement_data longtext,
            expires_at datetime DEFAULT NULL,
            is_active boolean DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_provider (provider),
            KEY idx_short_code (short_code),
            KEY idx_tracking_id (tracking_id),
            KEY idx_is_active (is_active),
            UNIQUE KEY original_url_user (original_url, user_id)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Best time predictions table
     */
    private static function create_best_time_predictions_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_best_time_predictions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            timezone varchar(100) NOT NULL,
            day_of_week int(11) NOT NULL,
            hour_of_day int(11) NOT NULL,
            predicted_engagement_rate decimal(5,2) NOT NULL,
            confidence_score decimal(3,2) NOT NULL,
            historical_data text,
            audience_data text,
            seasonal_factors text,
            algorithm_version varchar(20) NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_platform_time (user_id, platform, day_of_week, hour_of_day),
            KEY idx_platform (platform),
            KEY idx_predicted_engagement (predicted_engagement_rate),
            KEY idx_confidence_score (confidence_score)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Image editor data table
     */
    private static function create_image_editor_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_image_editor_data';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            original_image_id bigint(20) unsigned NOT NULL,
            edited_image_url varchar(500) NOT NULL,
            edit_operations longtext NOT NULL,
            crop_data text,
            rotation_data text,
            filters_applied text,
            text_overlays text,
            watermark_data text,
            file_size int(11) NOT NULL,
            dimensions varchar(50) NOT NULL,
            format varchar(10) NOT NULL DEFAULT 'jpg',
            quality int(11) DEFAULT 90,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_original_image_id (original_image_id),
            KEY idx_file_size (file_size),
            KEY idx_format (format)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Reshare queue table
     */
    private static function create_reshare_queue_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_reshare_queue';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            original_post_id bigint(20) unsigned NOT NULL,
            queue_name varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            reshare_count int(11) NOT NULL DEFAULT 0,
            max_reshares int(11) NOT NULL DEFAULT 200,
            interval_hours int(11) NOT NULL DEFAULT 24,
            next_reshare_date datetime NOT NULL,
            platforms text NOT NULL,
            reshare_message text,
            hashtags text,
            custom_timing text,
            is_active boolean DEFAULT 1,
            last_reshare_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_original_post_id (original_post_id),
            KEY idx_queue_name (queue_name),
            KEY idx_status (status),
            KEY idx_next_reshare_date (next_reshare_date),
            KEY idx_is_active (is_active)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Enhanced content calendar table
     */
    private static function create_enhanced_calendar_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_enhanced_calendar';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            calendar_date date NOT NULL,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            post_type varchar(50) NOT NULL DEFAULT 'text',
            content text NOT NULL,
            media_attachments text,
            platforms text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'scheduled',
            priority varchar(10) NOT NULL DEFAULT 'normal',
            estimated_reach int(11) DEFAULT 0,
            actual_reach int(11) DEFAULT 0,
            drag_drop_position varchar(50) DEFAULT NULL,
            color_code varchar(7) DEFAULT '#007cba',
            notes text,
            approval_status varchar(20) DEFAULT 'pending',
            approved_by bigint(20) DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            is_template boolean DEFAULT 0,
            template_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_calendar_date (calendar_date),
            KEY idx_status (status),
            KEY idx_priority (priority),
            KEY idx_approval_status (approval_status),
            KEY idx_is_template (is_template)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Team calendar table
     */
    private static function create_team_calendar_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_team_calendar';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            team_name varchar(255) NOT NULL,
            member_user_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            calendar_date date NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'scheduled',
            platforms text NOT NULL,
            team_member_name varchar(255) NOT NULL,
            is_visible_to_team boolean DEFAULT 1,
            collaboration_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_team_name (team_name),
            KEY idx_member_user_id (member_user_id),
            KEY idx_calendar_date (calendar_date),
            KEY idx_status (status),
            KEY idx_is_visible_to_team (is_visible_to_team)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * WordPress multisite support table
     */
    private static function create_multisite_support_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_multisite_networks';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            network_id bigint(20) unsigned NOT NULL,
            site_id bigint(20) unsigned NOT NULL,
            is_network_active boolean DEFAULT 1,
            shared_platforms text,
            network_settings longtext,
            user_mapping text,
            cross_site_permissions boolean DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY network_site (network_id, site_id),
            KEY idx_network_id (network_id),
            KEY idx_site_id (site_id),
            KEY idx_is_network_active (is_network_active)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Import automation and sharing tables
     */
    private static function create_import_automation_tables($charset_collate) {
        // Automation rules table
        self::create_import_automation_rules_table($charset_collate);
        
        // Auto-share configuration table  
        self::create_import_auto_share_config_table($charset_collate);
        
        // Content transformation templates table
        self::create_content_transformation_templates_table($charset_collate);
        
        // Automation logs table
        self::create_import_automation_logs_table($charset_collate);
    }
    
    /**
     * Import automation rules table
     */
    private static function create_import_automation_rules_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_import_automation_rules';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            rule_name varchar(255) NOT NULL,
            rule_type varchar(50) NOT NULL,
            source_ids text,
            auto_share_enabled boolean DEFAULT 0,
            auto_process_enabled boolean DEFAULT 0,
            platform_targets text,
            transformation_template_id bigint(20) DEFAULT NULL,
            scheduling_config longtext,
            content_filters longtext,
            metadata longtext,
            is_active boolean DEFAULT 1,
            priority int(11) NOT NULL DEFAULT 5,
            execution_count int(11) NOT NULL DEFAULT 0,
            success_count int(11) NOT NULL DEFAULT 0,
            failure_count int(11) NOT NULL DEFAULT 0,
            last_executed datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_rule_type (rule_type),
            KEY idx_is_active (is_active),
            KEY idx_priority (priority),
            KEY idx_last_executed (last_executed),
            KEY idx_transformation_template_id (transformation_template_id),
            UNIQUE KEY user_rule_name (user_id, rule_name)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Import auto-share configuration table
     */
    private static function create_import_auto_share_config_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_import_auto_share_config';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            rule_id bigint(20) unsigned DEFAULT NULL,
            imported_content_id bigint(20) unsigned DEFAULT NULL,
            platform varchar(50) NOT NULL,
            auto_share_enabled boolean DEFAULT 0,
            share_immediately boolean DEFAULT 0,
            schedule_delay_minutes int(11) DEFAULT 0,
            scheduled_time datetime DEFAULT NULL,
            custom_message_template text,
            hashtag_strategy varchar(20) DEFAULT 'auto',
            custom_hashtags text,
            mention_strategy varchar(20) DEFAULT 'none',
            content_transformation_id bigint(20) DEFAULT NULL,
            publish_status varchar(20) DEFAULT 'auto',
            retry_attempts int(11) DEFAULT 3,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_rule_id (rule_id),
            KEY idx_imported_content_id (imported_content_id),
            KEY idx_platform (platform),
            KEY idx_auto_share_enabled (auto_share_enabled),
            KEY idx_scheduled_time (scheduled_time),
            KEY idx_content_transformation_id (content_transformation_id)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Content transformation templates table
     */
    private static function create_content_transformation_templates_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_content_transformation_templates';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            template_name varchar(255) NOT NULL,
            template_type varchar(50) NOT NULL,
            description text,
            source_platform varchar(50) DEFAULT NULL,
            target_platforms text,
            preview_data text,
            is_active boolean DEFAULT 1,
            usage_count int(11) NOT NULL DEFAULT 0,
            success_rate decimal(5,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_template_type (template_type),
            KEY idx_source_platform (source_platform),
            KEY idx_is_active (is_active),
            KEY idx_usage_count (usage_count),
            KEY idx_success_rate (success_rate),
            UNIQUE KEY user_template_name (user_id, template_name)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Import automation logs table
     */
    private static function create_import_automation_logs_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_import_automation_logs';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            rule_id bigint(20) unsigned DEFAULT NULL,
            imported_content_id bigint(20) unsigned DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            platform varchar(50) DEFAULT NULL,
            target_content text,
            source_content longtext,
            transformation_applied text,
            error_message text,
            execution_time_ms int(11) DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_rule_id (rule_id),
            KEY idx_imported_content_id (imported_content_id),
            KEY idx_action_type (action_type),
            KEY idx_status (status),
            KEY idx_platform (platform),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Create media sharing tables
     */
    private static function create_media_sharing_tables($charset_collate) {
        self::create_shared_media_library_table($charset_collate);
        self::create_media_sharing_rules_table($charset_collate);
        self::create_cross_platform_media_sync_table($charset_collate);
    }
    
    /**
     * Shared media library table
     */
    private static function create_shared_media_library_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_shared_media_library';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            original_media_id bigint(20) unsigned NOT NULL,
            media_url varchar(1000) NOT NULL,
            media_type varchar(50) NOT NULL,
            platform_source varchar(50) DEFAULT NULL,
            shared_platforms text,
            share_count int(11) NOT NULL DEFAULT 0,
            usage_frequency varchar(20) DEFAULT 'medium',
            last_shared_date datetime DEFAULT NULL,
            media_metadata longtext,
            tags text,
            is_favorite boolean DEFAULT 0,
            performance_metrics longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_original_media_id (original_media_id),
            KEY idx_media_type (media_type),
            KEY idx_platform_source (platform_source),
            KEY idx_usage_frequency (usage_frequency),
            KEY idx_is_favorite (is_favorite),
            KEY idx_share_count (share_count)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Media sharing rules table
     */
    private static function create_media_sharing_rules_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_media_sharing_rules';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            rule_name varchar(255) NOT NULL,
            rule_type varchar(50) NOT NULL,
            media_types text,
            platform_targets text,
            auto_share_enabled boolean DEFAULT 0,
            share_timing_config longtext,
            content_modification_rules longtext,
            performance_threshold decimal(5,2) DEFAULT NULL,
            usage_limitations text,
            is_active boolean DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_rule_type (rule_type),
            KEY idx_is_active (is_active),
            UNIQUE KEY user_rule_name (user_id, rule_name)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Cross-platform media sync table
     */
    private static function create_cross_platform_media_sync_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_cross_platform_media_sync';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            media_id bigint(20) unsigned NOT NULL,
            source_platform varchar(50) NOT NULL,
            target_platform varchar(50) NOT NULL,
            sync_status varchar(20) NOT NULL DEFAULT 'pending',
            optimization_applied longtext,
            sync_attempts int(11) NOT NULL DEFAULT 0,
            last_sync_attempt datetime DEFAULT NULL,
            success_metrics longtext,
            error_details text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_media_id (media_id),
            KEY idx_source_platform (source_platform),
            KEY idx_target_platform (target_platform),
            KEY idx_sync_status (sync_status),
            KEY idx_last_sync_attempt (last_sync_attempt)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Create advanced calendar analytics tables
     */
    private static function create_advanced_calendar_analytics_tables($charset_collate) {
        self::create_calendar_performance_metrics_table($charset_collate);
        self::create_calendar_insights_table($charset_collate);
        self::create_calendar_forecasting_table($charset_collate);
    }
    
    /**
     * Calendar performance metrics table
     */
    private static function create_calendar_performance_metrics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_calendar_performance_metrics';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            calendar_date date NOT NULL,
            scheduled_time time DEFAULT NULL,
            platform varchar(50) NOT NULL,
            engagement_score decimal(5,2) DEFAULT 0,
            reach_score decimal(8,2) DEFAULT 0,
            conversion_rate decimal(5,2) DEFAULT 0,
            audience_growth int(11) DEFAULT 0,
            time_slot_performance decimal(5,2) DEFAULT 0,
            day_of_week_performance decimal(5,2) DEFAULT 0,
            content_type_performance decimal(5,2) DEFAULT 0,
            competitor_analysis text,
            trend_data longtext,
            prediction_accuracy decimal(3,2) DEFAULT 0,
            optimization_suggestions text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_post_id (post_id),
            KEY idx_calendar_date (calendar_date),
            KEY idx_platform (platform),
            KEY idx_engagement_score (engagement_score),
            KEY idx_time_slot_performance (time_slot_performance),
            KEY idx_day_of_week_performance (day_of_week_performance)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Calendar insights table
     */
    private static function create_calendar_insights_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_calendar_insights';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            insight_type varchar(50) NOT NULL,
            insight_title varchar(255) NOT NULL,
            insight_description text,
            affected_dates text,
            impact_level varchar(20) DEFAULT 'medium',
            confidence_score decimal(3,2) DEFAULT 0,
            supporting_data longtext,
            actionable_recommendations text,
            implementation_steps text,
            expected_improvement varchar(100),
            is_implemented boolean DEFAULT 0,
            implementation_date datetime DEFAULT NULL,
            results_achieved longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_insight_type (insight_type),
            KEY idx_impact_level (impact_level),
            KEY idx_confidence_score (confidence_score),
            KEY idx_is_implemented (is_implemented)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Calendar forecasting table
     */
    private static function create_calendar_forecasting_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_calendar_forecasting';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            forecast_period_start date NOT NULL,
            forecast_period_end date NOT NULL,
            forecast_type varchar(50) NOT NULL,
            predicted_metrics longtext NOT NULL,
            confidence_intervals longtext,
            seasonal_factors text,
            trend_analysis longtext,
            algorithm_version varchar(20) NOT NULL,
            model_accuracy decimal(3,2) DEFAULT 0,
            data_points_analyzed int(11) DEFAULT 0,
            forecast_horizon_days int(11) DEFAULT 30,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_forecast_period_start (forecast_period_start),
            KEY idx_forecast_period_end (forecast_period_end),
            KEY idx_forecast_type (forecast_type),
            KEY idx_model_accuracy (model_accuracy)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Create indexes for performance optimization
     */
    public static function create_performance_indexes() {
        global $wpdb;

        $indexes = array();

        // Check if tables and columns exist before creating indexes
        $table_info = self::get_table_columns_info();

        // Enhanced analytics indexes
        if (isset($table_info['smo_analytics'])) {
            $analytics_columns = $table_info['smo_analytics'];

            if (in_array('user_id', $analytics_columns) && in_array('date_created', $analytics_columns)) {
                $indexes[] = "CREATE INDEX idx_smo_analytics_user_date ON {$wpdb->prefix}smo_analytics(user_id, date_created)";
            }

            if (in_array('platform', $analytics_columns)) {
                $indexes[] = "CREATE INDEX idx_smo_analytics_platform ON {$wpdb->prefix}smo_analytics(platform)";
            }

            if (in_array('engagement_rate', $analytics_columns) && in_array('reach', $analytics_columns)) {
                $indexes[] = "CREATE INDEX idx_smo_analytics_performance ON {$wpdb->prefix}smo_analytics(engagement_rate DESC, reach DESC)";
            }
        }

        // Posts scheduling indexes
        if (isset($table_info['smo_scheduled_posts'])) {
            $scheduled_columns = $table_info['smo_scheduled_posts'];

            if (in_array('created_by', $scheduled_columns) && in_array('status', $scheduled_columns)) {
                $indexes[] = "CREATE INDEX idx_smo_scheduled_posts_user_status ON {$wpdb->prefix}smo_scheduled_posts(created_by, status)";
            }

            if (in_array('scheduled_time', $scheduled_columns) && in_array('status', $scheduled_columns)) {
                $indexes[] = "CREATE INDEX idx_smo_scheduled_posts_date_status ON {$wpdb->prefix}smo_scheduled_posts(scheduled_time, status)";
            }
        }

        // Queue processing indexes
        if (isset($table_info['smo_queue'])) {
            $queue_columns = $table_info['smo_queue'];

            if (in_array('status', $queue_columns) && in_array('priority', $queue_columns)) {
                $indexes[] = "CREATE INDEX idx_smo_queue_status_priority ON {$wpdb->prefix}smo_queue(status, priority DESC)";
            }

            if (in_array('next_attempt_at', $queue_columns)) {
                $indexes[] = "CREATE INDEX idx_smo_queue_next_attempt ON {$wpdb->prefix}smo_queue(next_attempt_at)";
            }
        }

        // Normalized tables indexes
        $indexes[] = "CREATE INDEX idx_smo_entity_platforms_entity ON {$wpdb->prefix}smo_entity_platforms(entity_type, entity_id)";
        $indexes[] = "CREATE INDEX idx_smo_entity_platforms_platform ON {$wpdb->prefix}smo_entity_platforms(platform_slug)";
        $indexes[] = "CREATE INDEX idx_smo_post_media_post ON {$wpdb->prefix}smo_post_media(post_id)";
        $indexes[] = "CREATE INDEX idx_smo_post_media_order ON {$wpdb->prefix}smo_post_media(post_id, media_order)";
        $indexes[] = "CREATE INDEX idx_smo_transformation_rules_template ON {$wpdb->prefix}smo_transformation_rules(template_id)";
        $indexes[] = "CREATE INDEX idx_smo_transformation_rules_order ON {$wpdb->prefix}smo_transformation_rules(template_id, rule_order)";
        $indexes[] = "CREATE INDEX idx_smo_transformation_variables_template ON {$wpdb->prefix}smo_transformation_variables(template_id)";

        // Execute index creation queries with error handling
        foreach ($indexes as $sql) {
            try {
                // Check if index already exists before creating
                $index_name = self::extract_index_name($sql);
                $table_name = self::extract_table_name($sql);

                $existing_index = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE table_schema = %s AND table_name = %s AND index_name = %s",
                    \DB_NAME,
                    $table_name,
                    $index_name
                ));

                if (!$existing_index) {
                    $result = $wpdb->query($sql);
                    if ($result === false) {
                        error_log("SMO Social: Failed to create index: " . $sql);
                    }
                }
            } catch (\Exception $e) {
                error_log("SMO Social: Error creating index: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get table columns information
     */
    private static function get_table_columns_info() {
        global $wpdb;
        
        $tables = array(
            'smo_analytics',
            'smo_scheduled_posts', 
            'smo_queue'
        );
        
        $table_info = array();
        
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if ($table_exists) {
                $columns = $wpdb->get_col("SHOW COLUMNS FROM $full_table_name");
                $table_info[$table] = $columns;
            }
        }
        
        return $table_info;
    }
    
    /**
     * Extract index name from CREATE INDEX statement
     */
    private static function extract_index_name($sql) {
        preg_match('/CREATE INDEX\s+(\w+)/i', $sql, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }
    
    /**
     * Extract table name from CREATE INDEX statement
     */
    private static function extract_table_name($sql) {
        preg_match('/ON\s+(\w+)/i', $sql, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }
    
    /**
     * Create content categories tables
     */
    private static function create_content_categories_tables($charset_collate) {
        global $wpdb;
        
        // Content categories table
        $categories_table = $wpdb->prefix . 'smo_content_categories';
        $sql = "CREATE TABLE IF NOT EXISTS $categories_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            color_code varchar(7) DEFAULT '#007cba',
            icon varchar(50) DEFAULT 'dashicons-category',
            parent_id bigint(20) unsigned DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            post_count int(11) DEFAULT 0,
            is_default boolean DEFAULT 0,
            is_active boolean DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_parent_id (parent_id),
            KEY idx_is_active (is_active),
            KEY idx_sort_order (sort_order)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
        
        // Post category assignments table
        $assignments_table = $wpdb->prefix . 'smo_post_category_assignments';
        $sql = "CREATE TABLE IF NOT EXISTS $assignments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_category (post_id, category_id),
            KEY idx_post_id (post_id),
            KEY idx_category_id (category_id),
            KEY idx_user_id (user_id)
        ) $charset_collate;";
        
        \dbDelta($sql);
        
        // Content ideas table for Content Organizer Kanban board
        $ideas_table = $wpdb->prefix . 'smo_content_ideas';
        $sql = "CREATE TABLE IF NOT EXISTS $ideas_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(500) NOT NULL,
            content longtext,
            category_id bigint(20) unsigned DEFAULT NULL,
            priority varchar(20) DEFAULT 'medium',
            status varchar(20) DEFAULT 'idea',
            scheduled_date datetime DEFAULT NULL,
            tags text,
            metadata longtext,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_category_id (category_id),
            KEY idx_status (status),
            KEY idx_priority (priority),
            KEY idx_scheduled_date (scheduled_date),
            KEY idx_sort_order (sort_order)
        ) $charset_collate;";
        
        \dbDelta($sql);
    }
    
    /**
     * Create content tags table for custom analytics
     */
    private static function create_content_tags_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_content_tags';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tag_name varchar(100) NOT NULL,
            tag_slug varchar(100) NOT NULL,
            description text,
            color_code varchar(7) DEFAULT '#667eea',
            user_id bigint(20) unsigned NOT NULL,
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tag_slug_user (tag_slug, user_id),
            KEY idx_user_id (user_id),
            KEY idx_usage_count (usage_count)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
        
        // Post tags assignments table
        $assignments_table = $wpdb->prefix . 'smo_post_tags';
        $sql = "CREATE TABLE IF NOT EXISTS $assignments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            tag_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            tagged_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_tag (post_id, tag_id),
            KEY idx_post_id (post_id),
            KEY idx_tag_id (tag_id),
            KEY idx_user_id (user_id)
        ) $charset_collate;";
        
        \dbDelta($sql);
    }
    
    /**
     * Create post individual analytics table
     */
    private static function create_post_individual_analytics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_post_individual_analytics';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            platform_post_id varchar(255) DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL,
            
            -- Engagement metrics
            likes_count int(11) DEFAULT 0,
            comments_count int(11) DEFAULT 0,
            shares_count int(11) DEFAULT 0,
            saves_count int(11) DEFAULT 0,
            total_engagements int(11) DEFAULT 0,
            
            -- Reach metrics
            impressions int(11) DEFAULT 0,
            reach int(11) DEFAULT 0,
            unique_views int(11) DEFAULT 0,
            
            -- Performance metrics
            engagement_rate decimal(5,2) DEFAULT 0.00,
            click_through_rate decimal(5,2) DEFAULT 0.00,
            conversion_rate decimal(5,2) DEFAULT 0.00,
            
            -- Time-based metrics
            avg_watch_time int(11) DEFAULT 0,
            completion_rate decimal(5,2) DEFAULT 0.00,
            
            -- Audience metrics
            follower_growth int(11) DEFAULT 0,
            profile_visits int(11) DEFAULT 0,
            
            -- Content performance
            best_performing_time time DEFAULT NULL,
            peak_engagement_hour int(11) DEFAULT NULL,
            
            -- Metadata
            last_synced_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY post_platform (post_id, platform),
            KEY idx_post_id (post_id),
            KEY idx_platform (platform),
            KEY idx_user_id (user_id),
            KEY idx_engagement_rate (engagement_rate),
            KEY idx_total_engagements (total_engagements),
            KEY idx_impressions (impressions)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
    
    /**
     * Create platform management tables
     */
    private static function create_platform_tables($charset_collate) {
        global $wpdb;

        // Platform tokens table for OAuth credentials
        $tokens_table = $wpdb->prefix . 'smo_platform_tokens';
        $sql = "CREATE TABLE $tokens_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform_slug varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            access_token text NOT NULL,
            refresh_token text DEFAULT NULL,
            token_expires datetime DEFAULT NULL,
            extra_data longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY platform_user (platform_slug, user_id),
            KEY idx_platform_slug (platform_slug),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_token_expires (token_expires)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);

        // Platform settings table for configuration
        $settings_table = $wpdb->prefix . 'smo_platform_settings';
        $sql = "CREATE TABLE $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform_slug varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            is_encrypted boolean DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY platform_user_key (platform_slug, user_id, setting_key),
            KEY idx_platform_slug (platform_slug),
            KEY idx_user_id (user_id),
            KEY idx_setting_key (setting_key)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }

    /**
     * Create advanced memory monitoring tables
     */
    private static function create_memory_monitoring_tables($charset_collate) {
        global $wpdb;

        // Enhanced historical memory metrics table
        $metrics_table = $wpdb->prefix . 'smo_memory_metrics_history';
        $sql = "CREATE TABLE $metrics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            memory_usage bigint(20) NOT NULL,
            usage_percentage decimal(5,2) NOT NULL,
            system_memory bigint(20) NOT NULL,
            peak_memory bigint(20) NOT NULL,
            pool_memory bigint(20) NOT NULL,
            cache_memory bigint(20) NOT NULL,
            config_memory bigint(20) NOT NULL,
            cleanup_memory bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'normal',
            efficiency_score decimal(5,2) NOT NULL DEFAULT 0.00,
            memory_limit bigint(20) NOT NULL,
            available_memory bigint(20) NOT NULL,
            fragmentation_ratio decimal(5,2) DEFAULT 0.00,
            garbage_collection_runs int(11) DEFAULT 0,
            object_pool_hit_rate decimal(5,2) DEFAULT 0.00,
            cache_hit_rate decimal(5,2) DEFAULT 0.00,
            connection_pool_utilization decimal(5,2) DEFAULT 0.00,
            websocket_pool_utilization decimal(5,2) DEFAULT 0.00,
            memory_pressure_level varchar(20) DEFAULT 'low',
            trend_direction varchar(20) DEFAULT 'stable',
            trend_magnitude decimal(5,2) DEFAULT 0.00,
            anomaly_score decimal(5,2) DEFAULT 0.00,
            component_breakdown longtext,
            system_info longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_timestamp (timestamp),
            KEY idx_status (status),
            KEY idx_efficiency_score (efficiency_score),
            KEY idx_usage_percentage (usage_percentage),
            KEY idx_memory_pressure (memory_pressure_level),
            KEY idx_trend_direction (trend_direction),
            KEY idx_anomaly_score (anomaly_score DESC)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);

        // Memory trends analysis table
        $trends_table = $wpdb->prefix . 'smo_memory_trends';
        $sql = "CREATE TABLE $trends_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            analysis_period_start datetime NOT NULL,
            analysis_period_end datetime NOT NULL,
            trend_type varchar(50) NOT NULL,
            trend_direction varchar(20) NOT NULL,
            trend_slope decimal(10,6) NOT NULL,
            trend_strength decimal(5,2) NOT NULL,
            confidence_level decimal(5,2) NOT NULL,
            data_points_analyzed int(11) NOT NULL,
            average_usage decimal(5,2) NOT NULL,
            peak_usage decimal(5,2) NOT NULL,
            volatility_index decimal(5,2) NOT NULL,
            seasonality_detected boolean DEFAULT 0,
            seasonal_pattern longtext,
            predictive_accuracy decimal(5,2) DEFAULT 0.00,
            next_predicted_value decimal(5,2) DEFAULT 0.00,
            prediction_confidence decimal(5,2) DEFAULT 0.00,
            anomaly_detected boolean DEFAULT 0,
            anomaly_details longtext,
            recommendations longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_period_start (analysis_period_start),
            KEY idx_period_end (analysis_period_end),
            KEY idx_trend_type (trend_type),
            KEY idx_trend_direction (trend_direction),
            KEY idx_confidence_level (confidence_level DESC),
            KEY idx_anomaly_detected (anomaly_detected)
        ) $charset_collate;";

        \dbDelta($sql);

        // Memory leak detection patterns table
        $leaks_table = $wpdb->prefix . 'smo_memory_leak_patterns';
        $sql = "CREATE TABLE $leaks_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            detection_timestamp datetime NOT NULL,
            leak_type varchar(50) NOT NULL,
            severity_level varchar(20) NOT NULL,
            memory_growth_rate decimal(10,6) NOT NULL,
            leak_duration_hours int(11) NOT NULL,
            affected_components longtext,
            suspected_causes longtext,
            evidence_data longtext,
            confidence_score decimal(5,2) NOT NULL,
            false_positive_probability decimal(5,2) DEFAULT 0.00,
            mitigation_suggestions longtext,
            status varchar(20) NOT NULL DEFAULT 'active',
            resolved_at datetime DEFAULT NULL,
            resolution_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_detection_timestamp (detection_timestamp),
            KEY idx_leak_type (leak_type),
            KEY idx_severity_level (severity_level),
            KEY idx_confidence_score (confidence_score DESC),
            KEY idx_status (status)
        ) $charset_collate;";

        \dbDelta($sql);

        // Memory usage patterns table
        $patterns_table = $wpdb->prefix . 'smo_memory_usage_patterns';
        $sql = "CREATE TABLE $patterns_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pattern_name varchar(100) NOT NULL,
            pattern_type varchar(50) NOT NULL,
            time_period varchar(20) NOT NULL,
            pattern_data longtext NOT NULL,
            frequency_distribution longtext,
            peak_usage_times longtext,
            low_usage_times longtext,
            correlation_factors longtext,
            predictive_power decimal(5,2) NOT NULL,
            confidence_score decimal(5,2) NOT NULL,
            last_analyzed datetime NOT NULL,
            next_analysis_due datetime NOT NULL,
            is_active boolean DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pattern_type (pattern_type),
            KEY idx_time_period (time_period),
            KEY idx_predictive_power (predictive_power DESC),
            KEY idx_confidence_score (confidence_score DESC),
            KEY idx_is_active (is_active),
            KEY idx_next_analysis (next_analysis_due)
        ) $charset_collate;";

        \dbDelta($sql);

        // Memory optimization recommendations table
        $recommendations_table = $wpdb->prefix . 'smo_memory_optimization_recommendations';
        $sql = "CREATE TABLE $recommendations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            recommendation_type varchar(50) NOT NULL,
            priority_level varchar(20) NOT NULL,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            technical_details longtext,
            implementation_steps longtext,
            expected_benefit_mb bigint(20) DEFAULT 0,
            expected_benefit_percentage decimal(5,2) DEFAULT 0.00,
            implementation_complexity varchar(20) NOT NULL,
            risk_level varchar(20) NOT NULL,
            prerequisites longtext,
            affected_components longtext,
            status varchar(20) NOT NULL DEFAULT 'pending',
            implemented_at datetime DEFAULT NULL,
            implementation_result longtext,
            success_score decimal(5,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_recommendation_type (recommendation_type),
            KEY idx_priority_level (priority_level),
            KEY idx_status (status),
            KEY idx_expected_benefit (expected_benefit_mb DESC),
            KEY idx_implementation_complexity (implementation_complexity),
            KEY idx_risk_level (risk_level)
        ) $charset_collate;";

        \dbDelta($sql);

        // Memory predictive models table
        $predictive_table = $wpdb->prefix . 'smo_memory_predictive_models';
        $sql = "CREATE TABLE $predictive_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            model_name varchar(100) NOT NULL,
            model_type varchar(50) NOT NULL,
            algorithm_used varchar(50) NOT NULL,
            training_period_start datetime NOT NULL,
            training_period_end datetime NOT NULL,
            model_parameters longtext NOT NULL,
            feature_importance longtext,
            accuracy_metrics longtext NOT NULL,
            prediction_horizon_hours int(11) NOT NULL,
            confidence_interval decimal(5,2) NOT NULL,
            last_trained datetime NOT NULL,
            next_retraining_due datetime NOT NULL,
            is_active boolean DEFAULT 1,
            performance_score decimal(5,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_model_type (model_type),
            KEY idx_algorithm_used (algorithm_used),
            KEY idx_is_active (is_active),
            KEY idx_performance_score (performance_score DESC),
            KEY idx_next_retraining (next_retraining_due)
        ) $charset_collate;";

        \dbDelta($sql);

        // Memory forecasts table
        $forecasts_table = $wpdb->prefix . 'smo_memory_forecasts';
        $sql = "CREATE TABLE $forecasts_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            model_id bigint(20) unsigned NOT NULL,
            forecast_timestamp datetime NOT NULL,
            forecast_period_start datetime NOT NULL,
            forecast_period_end datetime NOT NULL,
            predicted_usage decimal(5,2) NOT NULL,
            upper_bound decimal(5,2) NOT NULL,
            lower_bound decimal(5,2) NOT NULL,
            confidence_level decimal(5,2) NOT NULL,
            forecast_accuracy decimal(5,2) DEFAULT NULL,
            actual_usage decimal(5,2) DEFAULT NULL,
            accuracy_calculated_at datetime DEFAULT NULL,
            forecast_factors longtext,
            risk_assessment varchar(20) NOT NULL,
            recommendations longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_model_id (model_id),
            KEY idx_forecast_timestamp (forecast_timestamp),
            KEY idx_forecast_period_start (forecast_period_start),
            KEY idx_confidence_level (confidence_level DESC),
            KEY idx_risk_assessment (risk_assessment)
        ) $charset_collate;";

        \dbDelta($sql);
    }
}
