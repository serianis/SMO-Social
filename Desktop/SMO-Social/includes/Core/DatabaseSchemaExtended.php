<?php
/**
 * Extended Database Schema for SMO Social Plugin
 * Handles all new features including content import, analytics, team management, etc.
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extended Database Schema Manager
 * 
 * Manages database operations for new features:
 * - Content Import & Management
 * - Social Engagement & Comments
 * - Advanced Analytics & Reporting
 * - Team Management & Permissions
 * - Approval Workflows
 */
class DatabaseSchemaExtended {
    
    /**
     * Create all extended database tables
     */
    public static function create_extended_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Content Import Sources
        $table_name = $wpdb->prefix . 'smo_content_sources';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            type enum('google_drive','dropbox','canva','rss','upload','url') NOT NULL,
            config longtext,
            status enum('active','inactive','error') DEFAULT 'active',
            last_sync datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // Imported Content Items
        $table_name = $wpdb->prefix . 'smo_imported_content';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            source_id mediumint(9),
            title text,
            content longtext,
            media_urls text,
            metadata longtext,
            tags text,
            category varchar(100),
            status enum('pending','processed','published','archived') DEFAULT 'pending',
            imported_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY source_id (source_id),
            KEY status (status),
            KEY category (category),
            KEY tags (tags(100))
        ) $charset_collate;";
        
        // Content Ideas
        $table_name = $wpdb->prefix . 'smo_content_ideas';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description longtext,
            content_type enum('post','story','video','campaign') DEFAULT 'post',
            target_platforms text,
            tags text,
            category varchar(100),
            priority enum('low','medium','high') DEFAULT 'medium',
            status enum('idea','draft','scheduled','published','archived') DEFAULT 'idea',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            scheduled_date datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY priority (priority),
            KEY content_type (content_type)
        ) $charset_collate;";
        
        // Social Comments & Engagement
        $table_name = $wpdb->prefix . 'smo_social_comments';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9),
            platform varchar(50) NOT NULL,
            platform_post_id varchar(255),
            comment_id varchar(255),
            author_name varchar(255),
            author_profile_url varchar(500),
            content longtext,
            sentiment enum('positive','neutral','negative') NULL,
            is_replied tinyint(1) DEFAULT 0,
            reply_content longtext NULL,
            replied_at datetime NULL,
            replied_by bigint(20) unsigned NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY platform (platform),
            KEY is_replied (is_replied),
            KEY sentiment (sentiment),
            FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}smo_scheduled_posts(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Enhanced Analytics
        $table_name = $wpdb->prefix . 'smo_enhanced_analytics';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9),
            platform varchar(50) NOT NULL,
            metric_type enum('impressions','engagement','clicks','shares','likes','comments') NOT NULL,
            metric_value int(11) DEFAULT 0,
            metric_data longtext,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY platform (platform),
            KEY metric_type (metric_type),
            KEY recorded_at (recorded_at),
            FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}smo_scheduled_posts(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Content Tagging for Custom Analytics
        $table_name = $wpdb->prefix . 'smo_content_tags';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9),
            tag_name varchar(100) NOT NULL,
            tag_category varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY tag_name (tag_name),
            KEY tag_category (tag_category),
            FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}smo_scheduled_posts(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Audience Demographics
        $table_name = $wpdb->prefix . 'smo_audience_demographics';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            platform varchar(50) NOT NULL,
            age_range varchar(20),
            gender enum('male','female','other','unknown') DEFAULT 'unknown',
            location_country varchar(100),
            location_city varchar(100),
            percentage decimal(5,2) DEFAULT 0.00,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY platform (platform),
            KEY age_range (age_range),
            KEY gender (gender),
            KEY location_country (location_country)
        ) $charset_collate;";
        
        // Internal Notes System
        $table_name = $wpdb->prefix . 'smo_internal_notes';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9),
            user_id bigint(20) unsigned,
            content longtext NOT NULL,
            note_type enum('general','feedback','revision','approval') DEFAULT 'general',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY note_type (note_type),
            FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}smo_scheduled_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Approval Workflows
        $table_name = $wpdb->prefix . 'smo_approval_workflows';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9),
            assigned_to bigint(20) unsigned,
            status enum('pending','approved','rejected','revision_requested') DEFAULT 'pending',
            comments text,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime NULL,
            reviewed_by bigint(20) unsigned NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY assigned_to (assigned_to),
            KEY status (status),
            KEY reviewed_by (reviewed_by),
            FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}smo_scheduled_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL
        ) $charset_collate;";
        
        // User Permissions by Platform
        $table_name = $wpdb->prefix . 'smo_user_platform_permissions';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            platform varchar(50) NOT NULL,
            permissions text,
            granted_by bigint(20) unsigned,
            granted_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY platform (platform),
            KEY expires_at (expires_at),
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
            FOREIGN KEY (granted_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL
        ) $charset_collate;";
        
        // Comment Scores & Habits Tracking
        $table_name = $wpdb->prefix . 'smo_comment_scores';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            platform varchar(50) NOT NULL,
            date date NOT NULL,
            comments_sent int(11) DEFAULT 0,
            avg_response_time int(11) DEFAULT 0, -- in minutes
            consistency_score decimal(5,2) DEFAULT 0.00,
            speed_score decimal(5,2) DEFAULT 0.00,
            habits_score decimal(5,2) DEFAULT 0.00,
            total_score decimal(5,2) DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY platform (platform),
            KEY date (date),
            UNIQUE KEY user_platform_date (user_id, platform, date),
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Branded Reports Configuration
        $table_name = $wpdb->prefix . 'smo_branded_reports';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            logo_url varchar(500),
            brand_colors text,
            company_info longtext,
            template_config longtext,
            is_default tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY is_default (is_default)
        ) $charset_collate;";
        
        // Execute all table creation queries
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert default data
        self::insert_default_data();
    }
    
    /**
     * Insert default data for new features
     */
    private static function insert_default_data() {
        global $wpdb;
        
        // Insert default content sources
        $default_sources = array(
            array(
                'name' => 'Sample RSS Feed',
                'type' => 'rss',
                'config' => json_encode(array(
                    'url' => 'https://example.com/feed',
                    'update_interval' => 3600,
                    'auto_import' => false
                ))
            ),
            array(
                'name' => 'Google Drive Import',
                'type' => 'google_drive',
                'config' => json_encode(array(
                    'folder_id' => '',
                    'file_types' => array('image', 'video', 'document'),
                    'auto_import' => false
                ))
            )
        );
        
        foreach ($default_sources as $source) {
            $wpdb->insert(
                $wpdb->prefix . 'smo_content_sources',
                $source
            );
        }
        
        // Insert default branded report template
        $wpdb->insert(
            $wpdb->prefix . 'smo_branded_reports',
            array(
                'name' => 'Default Report Template',
                'brand_colors' => json_encode(array(
                    'primary' => '#0073aa',
                    'secondary' => '#646970',
                    'accent' => '#00a32a'
                )),
                'company_info' => json_encode(array(
                    'company_name' => get_bloginfo('name'),
                    'website' => home_url(),
                    'contact_email' => get_option('admin_email')
                )),
                'is_default' => 1
            )
        );
    }
    
    /**
     * Add extended columns to existing tables if needed
     */
    public static function update_existing_tables() {
        global $wpdb;
        
        // Add columns to existing posts table
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Check if columns exist and add them if they don't
        $columns_to_add = array(
            'duplicate_of' => 'mediumint(9) NULL',
            'import_source_id' => 'mediumint(9) NULL',
            'content_idea_id' => 'mediumint(9) NULL',
            'is_template' => 'tinyint(1) DEFAULT 0',
            'approval_status' => 'enum("draft","pending_approval","approved","rejected") DEFAULT "draft"',
            'last_comment_check' => 'datetime NULL'
        );
        
        foreach ($columns_to_add as $column => $definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                $column
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN {$column} {$definition}");
            }
        }
        
        // Add foreign key constraints
        $wpdb->query("ALTER TABLE {$table_name} ADD FOREIGN KEY (duplicate_of) REFERENCES {$table_name}(id) ON DELETE SET NULL");
        $wpdb->query("ALTER TABLE {$table_name} ADD FOREIGN KEY (import_source_id) REFERENCES {$wpdb->prefix}smo_content_sources(id) ON DELETE SET NULL");
        $wpdb->query("ALTER TABLE {$table_name} ADD FOREIGN KEY (content_idea_id) REFERENCES {$wpdb->prefix}smo_content_ideas(id) ON DELETE SET NULL");
    }
    
    /**
     * Drop extended tables (for uninstall)
     */
    public static function drop_extended_tables() {
        global $wpdb;
        
        $tables = array(
            'smo_content_sources',
            'smo_imported_content',
            'smo_content_ideas',
            'smo_social_comments',
            'smo_enhanced_analytics',
            'smo_content_tags',
            'smo_audience_demographics',
            'smo_internal_notes',
            'smo_approval_workflows',
            'smo_user_platform_permissions',
            'smo_comment_scores',
            'smo_branded_reports'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
    }
    
    /**
     * Get table names for reference
     */
    public static function get_table_names() {
        global $wpdb;
        
        return array(
            'content_sources' => $wpdb->prefix . 'smo_content_sources',
            'imported_content' => $wpdb->prefix . 'smo_imported_content',
            'content_ideas' => $wpdb->prefix . 'smo_content_ideas',
            'social_comments' => $wpdb->prefix . 'smo_social_comments',
            'enhanced_analytics' => $wpdb->prefix . 'smo_enhanced_analytics',
            'content_tags' => $wpdb->prefix . 'smo_content_tags',
            'audience_demographics' => $wpdb->prefix . 'smo_audience_demographics',
            'internal_notes' => $wpdb->prefix . 'smo_internal_notes',
            'approval_workflows' => $wpdb->prefix . 'smo_approval_workflows',
            'user_platform_permissions' => $wpdb->prefix . 'smo_user_platform_permissions',
            'comment_scores' => $wpdb->prefix . 'smo_comment_scores',
            'branded_reports' => $wpdb->prefix . 'smo_branded_reports'
        );
    }
}