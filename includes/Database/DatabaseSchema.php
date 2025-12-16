<?php
/**
 * Database Schema Manager
 * 
 * Handles database table creation and schema management for SMO Social plugin
 * 
 * @package SMO_Social
 * @subpackage Database
 * @since 1.0.0
 */

namespace SMO_Social\Database;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseSchema {
    
    /**
     * Create all plugin tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Load dbDelta function - with fallback for standalone mode
        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        } else {
            // Fallback dbDelta function for standalone mode
            if (!function_exists('dbDelta')) {
                function dbDelta($sql) {
                    // Simple implementation for testing
                    return ['created' => true];
                }
            }
        }
        
        // Create posts table
        self::create_posts_table($charset_collate);
        
        // Create platforms table
        self::create_platforms_table($charset_collate);
        
        // Create queue table
        self::create_queue_table($charset_collate);
        
        // Create analytics table
        self::create_analytics_table($charset_collate);
        
        // Create content categories tables
        self::create_content_categories_tables($charset_collate);
        
        // Create RSS feeds table
        self::create_rss_feeds_table($charset_collate);
        
        // Create imported content table
        self::create_imported_content_table($charset_collate);
        
        // Create content ideas table
        self::create_content_ideas_table($charset_collate);

        // Create content sources table
        self::create_content_sources_table($charset_collate);

        // Create scheduled posts table
        self::create_scheduled_posts_table($charset_collate);

        // Create content organizer tables (from fix plan)
        self::create_content_organizer_tables($charset_collate);

        // Create approval workflows table
        self::create_approval_workflows_table($charset_collate);

        // Create channel access table
        self::create_channel_access_table($charset_collate);
        
        // Create team management tables
        self::create_team_members_table($charset_collate);
        self::create_team_assignments_table($charset_collate);
        self::create_team_permissions_table($charset_collate);
        self::create_network_groups_table($charset_collate);
        self::create_multisite_access_table($charset_collate);
    }
    
    /**
     * Create posts table
     */
    private static function create_posts_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_posts';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            content longtext NOT NULL,
            media_urls longtext,
            scheduled_time datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            platforms longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY scheduled_time (scheduled_time)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create platforms table
     */
    private static function create_platforms_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_platforms';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            account_name varchar(255),
            access_token longtext,
            refresh_token longtext,
            token_expires datetime,
            status varchar(20) NOT NULL DEFAULT 'active',
            settings longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY platform (platform),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create queue table
     */
    private static function create_queue_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_queue';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            platform_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            scheduled_time datetime NOT NULL,
            published_time datetime,
            error_message text,
            retry_count int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY platform_id (platform_id),
            KEY status (status),
            KEY scheduled_time (scheduled_time)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create analytics table
     */
    private static function create_analytics_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_analytics';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            platform_id bigint(20) NOT NULL,
            platform_post_id varchar(255),
            impressions int(11) DEFAULT 0,
            engagements int(11) DEFAULT 0,
            likes int(11) DEFAULT 0,
            shares int(11) DEFAULT 0,
            comments int(11) DEFAULT 0,
            clicks int(11) DEFAULT 0,
            reach int(11) DEFAULT 0,
            collected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY platform_id (platform_id),
            KEY collected_at (collected_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create content categories tables
     */
    public static function create_content_categories_tables($charset_collate) {
        global $wpdb;

        // Categories table - Updated to match ContentCategoriesManager requirements
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
        dbDelta($sql);

        // Post category assignments table - Updated to match ContentCategoriesManager requirements
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
        dbDelta($sql);
    }
    
    /**
     * Create RSS feeds table
     */
    private static function create_rss_feeds_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_rss_feeds';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            category varchar(100),
            auto_import tinyint(1) DEFAULT 0,
            import_frequency varchar(20) DEFAULT 'daily',
            last_imported datetime,
            status varchar(20) DEFAULT 'active',
            user_id bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create imported content table
     */
    private static function create_imported_content_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_imported_content';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feed_id bigint(20),
            source_type varchar(50) NOT NULL,
            title varchar(500) NOT NULL,
            content longtext,
            source_url varchar(500),
            image_url varchar(500),
            featured_image_id bigint(20),
            author varchar(255),
            published_date datetime,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'pending',
            user_id bigint(20) NOT NULL,
            PRIMARY KEY (id),
            KEY feed_id (feed_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY source_type (source_type)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create content ideas table
     */
    private static function create_content_ideas_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_content_ideas';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(500) NOT NULL,
            description longtext,
            content_type varchar(50) NOT NULL,
            target_platforms longtext,
            category varchar(100),
            tags longtext,
            priority varchar(20) DEFAULT 'medium',
            status varchar(20) DEFAULT 'idea',
            scheduled_date datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_date (scheduled_date)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create scheduled posts table (required by ContentIdeasManager)
     */
    private static function create_scheduled_posts_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(500) NOT NULL,
            content longtext,
            scheduled_time datetime NULL,
            status varchar(20) DEFAULT 'draft',
            priority varchar(20) DEFAULT 'medium',
            user_id bigint(20) unsigned NOT NULL,
            content_idea_id bigint(20) unsigned,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY scheduled_time (scheduled_time),
            KEY content_idea_id (content_idea_id)
        ) $charset_collate;";

        dbDelta($sql);
    }
    
    /**
     * Create content sources table
     */
    private static function create_content_sources_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_content_sources';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            url varchar(500),
            api_key varchar(255),
            settings longtext,
            status varchar(20) DEFAULT 'active',
            user_id bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    /**
     * Create approval workflows table
     */
    private static function create_approval_workflows_table($charset_collate) {
        global $wpdb;
        
        // Workflows table
        $workflows_table = $wpdb->prefix . 'smo_approval_workflows';
        $sql = "CREATE TABLE IF NOT EXISTS $workflows_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            required_approvals int(11) DEFAULT 1,
            approvers longtext, -- JSON array of user IDs
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by)
        ) $charset_collate;";
        dbDelta($sql);

        // Post approvals table
        $approvals_table = $wpdb->prefix . 'smo_post_approvals';
        $sql = "CREATE TABLE IF NOT EXISTS $approvals_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            workflow_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'pending', -- pending, approved, rejected
            current_step int(11) DEFAULT 1,
            history longtext, -- JSON log of actions
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY workflow_id (workflow_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
    }

    /**
     * Create channel access table
     */
    private static function create_channel_access_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_channel_access';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            platform_id bigint(20) NOT NULL,
            access_level varchar(20) DEFAULT 'view', -- view, draft, publish
            granted_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY platform_id (platform_id),
            KEY access_level (access_level)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create team members table
     */
    private static function create_team_members_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_members';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            role varchar(50) DEFAULT 'member',
            display_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            avatar_url varchar(500),
            status varchar(20) DEFAULT 'active',
            invited_by bigint(20),
            joined_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_active datetime,
            settings longtext,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY role (role),
            KEY status (status),
            KEY invited_by (invited_by)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create team assignments table
     */
    private static function create_team_assignments_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_assignments';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assignment_name varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            assignment_type varchar(50) NOT NULL, -- 'platform', 'network_group', 'url_tracking'
            resource_id bigint(20), -- platform_id or network_group_id
            platforms longtext, -- JSON array of platform names
            url_tracking_params longtext, -- JSON object of UTM parameters
            access_level varchar(20) DEFAULT 'view', -- view, edit, publish
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY assignment_type (assignment_type),
            KEY resource_id (resource_id),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create team permissions table
     */
    private static function create_team_permissions_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_permissions';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            permission_key varchar(100) NOT NULL,
            permission_value tinyint(1) DEFAULT 0,
            granted_by bigint(20),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_permission (user_id, permission_key),
            KEY permission_key (permission_key),
            KEY granted_by (granted_by)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create network groups table
     */
    private static function create_network_groups_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_network_groups';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            platforms longtext, -- JSON array of platform names
            members longtext, -- JSON array of user IDs
            settings longtext, -- JSON object for group settings
            color varchar(7) DEFAULT '#3b82f6',
            icon varchar(50) DEFAULT 'users',
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY name (name)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create multisite access table
     */
    private static function create_multisite_access_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_multisite_access';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            site_id bigint(20) NOT NULL,
            access_level varchar(20) DEFAULT 'view', -- view, edit, publish, admin
            permissions longtext, -- JSON object of specific permissions
            granted_by bigint(20),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_site (user_id, site_id),
            KEY user_id (user_id),
            KEY site_id (site_id),
            KEY access_level (access_level),
            KEY granted_by (granted_by)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    /**
     * Create content organizer tables (from fix plan)
     * Note: These tables are kept for backward compatibility but should be migrated to the main tables
     */
    /**
     * Create content organizer tables (from fix plan)
     * Note: These tables are kept for backward compatibility but should be migrated to the main tables
     * @deprecated Legacy tables (smo_categories, smo_ideas) are replaced by smo_content_categories and smo_content_ideas.
     * Creation disabled to prevent duplicate schema definitions.
     */
    private static function create_content_organizer_tables($charset_collate) {
        // Disabled to prevent creation of duplicate/legacy tables. 
        // Use create_content_categories_tables() and create_content_ideas_table() instead.
        return;
    }

    /**
     * Drop all plugin tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'smo_posts',
            $wpdb->prefix . 'smo_platforms',
            $wpdb->prefix . 'smo_queue',
            $wpdb->prefix . 'smo_analytics',
            $wpdb->prefix . 'smo_content_categories',
            $wpdb->prefix . 'smo_post_category_assignments',
            $wpdb->prefix . 'smo_content_category_relationships',
            $wpdb->prefix . 'smo_scheduled_posts',
            $wpdb->prefix . 'smo_rss_feeds',
            $wpdb->prefix . 'smo_imported_content',
            $wpdb->prefix . 'smo_content_ideas',
            $wpdb->prefix . 'smo_content_sources',
            $wpdb->prefix . 'smo_approval_workflows',
            $wpdb->prefix . 'smo_post_approvals',
            $wpdb->prefix . 'smo_channel_access',
            $wpdb->prefix . 'smo_team_members',
            $wpdb->prefix . 'smo_team_assignments',
            $wpdb->prefix . 'smo_team_permissions',
            $wpdb->prefix . 'smo_network_groups',
            $wpdb->prefix . 'smo_multisite_access',
            // Content organizer tables (from fix plan) - kept for backward compatibility
            $wpdb->prefix . 'smo_categories',
            $wpdb->prefix . 'smo_ideas'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
