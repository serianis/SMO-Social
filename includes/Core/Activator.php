<?php
namespace SMO_Social\Core;

class Activator {
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create upload directories
        self::create_directories();
    }

    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create enhanced database tables using DatabaseSchema
        DatabaseSchema::create_enhanced_tables();

        // Scheduled posts table
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            media_urls text,
            platforms text NOT NULL,
            scheduled_time datetime NOT NULL,
            status enum('draft','scheduled','publishing','published','failed','cancelled') DEFAULT 'scheduled',
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            post_id varchar(255) DEFAULT NULL,
            platform_responses text,
            error_message text,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_time (scheduled_time),
            KEY created_at (created_at),
            KEY created_by (created_by)
        ) $charset_collate;";

        require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = \dbDelta($sql);
        error_log('SMO Social: dbDelta result for posts table: ' . print_r($result, true));

        // Platform tokens table (encrypted)
        $table_name = $wpdb->prefix . 'smo_platform_tokens';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform_slug varchar(50) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            access_token longtext NOT NULL,
            refresh_token longtext DEFAULT NULL,
            token_expires datetime DEFAULT NULL,
            extra_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY platform_user (platform_slug, user_id),
            KEY platform_slug (platform_slug)
        ) $charset_collate;";
        \dbDelta($sql);

        // Analytics table
        $table_name = $wpdb->prefix . 'smo_analytics';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scheduled_post_id bigint(20) unsigned NOT NULL,
            platform_slug varchar(50) NOT NULL,
            platform_post_id varchar(255) DEFAULT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value bigint(20) unsigned DEFAULT 0,
            metric_data longtext,
            fetched_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scheduled_post_id (scheduled_post_id),
            KEY platform_slug (platform_slug),
            KEY fetched_at (fetched_at)
        ) $charset_collate;";
        \dbDelta($sql);

        // Content variants table
        $table_name = $wpdb->prefix . 'smo_content_variants';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scheduled_post_id bigint(20) unsigned NOT NULL,
            variant_type varchar(50) NOT NULL,
            title varchar(255),
            content longtext NOT NULL,
            hashtags text,
            created_by enum('ai', 'user') DEFAULT 'user',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scheduled_post_id (scheduled_post_id),
            KEY variant_type (variant_type)
        ) $charset_collate;";
        \dbDelta($sql);

        // Activity logs table
        $table_name = $wpdb->prefix . 'smo_activity_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            action varchar(100) NOT NULL,
            resource_type varchar(50) DEFAULT NULL,
            resource_id bigint(20) unsigned DEFAULT NULL,
            details longtext,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        \dbDelta($sql);

        // Content templates table
        $table_name = $wpdb->prefix . 'smo_content_templates';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            template_content longtext NOT NULL,
            platforms text NOT NULL,
            tags text,
            is_evergreen tinyint(1) DEFAULT 0,
            usage_count int(11) DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY platforms (platforms),
            KEY tags (tags),
            KEY is_evergreen (is_evergreen)
        ) $charset_collate;";
        \dbDelta($sql);

        // Queue management table
        $table_name = $wpdb->prefix . 'smo_queue';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scheduled_post_id bigint(20) unsigned NOT NULL,
            platform_slug varchar(50) NOT NULL,
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            scheduled_for datetime NOT NULL,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            status enum('pending','processing','completed','failed','retry') DEFAULT 'pending',
            error_message text,
            processed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scheduled_post_id (scheduled_post_id),
            KEY platform_slug (platform_slug),
            KEY status (status),
            KEY scheduled_for (scheduled_for)
        ) $charset_collate;";
        \dbDelta($sql);

        // Post platforms table (for managing platforms per post)
        $table_name = $wpdb->prefix . 'smo_post_platforms';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            platform_slug varchar(50) NOT NULL,
            status enum('pending', 'published', 'failed') DEFAULT 'pending',
            platform_post_id varchar(255) DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY platform_slug (platform_slug),
            KEY status (status),
            UNIQUE KEY post_platform (post_id, platform_slug)
        ) $charset_collate;";
        \dbDelta($sql);
    }

    private static function set_default_options() {
        // Ensure WordPress functions are available
        if (!function_exists('add_option')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        // Default settings
        \add_option('smo_social_enabled_platforms', array('facebook', 'twitter', 'mastodon'));
        \add_option('smo_social_default_settings', array(
            'auto_schedule' => 0,
            'best_time_optimization' => 1,
            'ai_enabled' => 1,
            'queue_priority' => 'normal',
            'max_daily_posts' => 50,
            'enable_analytics' => 1,
            'cache_duration' => 3600, // 1 hour
            'gdpr_compliance' => 1
        ));

        // Version tracking
        \add_option('smo_social_db_version', '1.0.0');
        \add_option('smo_social_installed_date', \current_time('mysql'));
    }

    private static function create_directories() {
        $upload_dir = \wp_upload_dir();
        $smo_dir = $upload_dir['basedir'] . '/smo-social/';

        if (!file_exists($smo_dir)) {
            \wp_mkdir_p($smo_dir);
            
            // Create subdirectories
            $subdirs = array('media', 'cache', 'logs', 'temp');
            foreach ($subdirs as $subdir) {
                \wp_mkdir_p($smo_dir . $subdir);
                
                // Add .htaccess for security
                if ($subdir !== 'media') {
                    file_put_contents($smo_dir . $subdir . '/.htaccess', 'deny from all');
                }
            }
        }
    }
}
