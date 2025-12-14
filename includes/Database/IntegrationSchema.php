<?php
/**
 * Integration Database Schema
 * 
 * Manages database tables for the integration system
 *
 * @package SMO_Social
 * @subpackage Database
 * @since 1.0.0
 */

namespace SMO_Social\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration Schema Class
 */
class IntegrationSchema {
    
    /**
     * Create integration-related database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create integrations table
        $integrations_table = $wpdb->prefix . 'smo_integrations';
        $integrations_sql = "CREATE TABLE $integrations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id varchar(100) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'disconnected',
            credentials longtext,
            access_token longtext,
            refresh_token longtext,
            expires_at datetime,
            last_used datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_integration (user_id, integration_id),
            KEY idx_integration_id (integration_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // Create imported content table
        $imported_content_table = $wpdb->prefix . 'smo_imported_content';
        $imported_content_sql = "CREATE TABLE $imported_content_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id varchar(100) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            content_id varchar(255) NOT NULL,
            title text,
            description longtext,
            content_url text,
            content_type varchar(50),
            file_path text,
            file_type varchar(50),
            file_size bigint(20),
            thumbnail_url text,
            metadata longtext,
            attachment_id bigint(20) unsigned,
            post_id bigint(20) unsigned,
            imported_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_content (integration_id, content_id, user_id),
            KEY idx_integration_id (integration_id),
            KEY idx_user_id (user_id),
            KEY idx_content_type (content_type),
            KEY idx_imported_at (imported_at)
        ) $charset_collate;";
        
        // Create integration logs table
        $integration_logs_table = $wpdb->prefix . 'smo_integration_logs';
        $integration_logs_sql = "CREATE TABLE $integration_logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            integration_id varchar(100) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(100) NOT NULL,
            details text,
            status varchar(20) NOT NULL DEFAULT 'success',
            error_message text,
            ip_address varchar(45),
            user_agent text,
            execution_time decimal(8,3),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_integration_id (integration_id),
            KEY idx_user_id (user_id),
            KEY idx_action (action),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($integrations_sql);
        dbDelta($imported_content_sql);
        dbDelta($integration_logs_sql);
        
        // Update version
        update_option('smo_integrations_db_version', '1.0.0');
    }
    
    /**
     * Drop integration-related database tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'smo_integrations',
            $wpdb->prefix . 'smo_imported_content',
            $wpdb->prefix . 'smo_integration_logs'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('smo_integrations_db_version');
    }
    
    /**
     * Check if tables need to be created or updated
     */
    public static function maybe_create_tables() {
        $current_version = get_option('smo_integrations_db_version', '0.0.0');
        $required_version = '1.0.0';
        
        if (version_compare($current_version, $required_version, '<')) {
            self::create_tables();
        }
    }
    
    /**
     * Add indexes for better performance
     */
    public static function add_indexes() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'smo_imported_content',
            $wpdb->prefix . 'smo_integration_logs'
        ];
        
        // Additional indexes for performance
        $indexes = [
            'smo_imported_content' => [
                'idx_integration_user' => '(integration_id, user_id)',
                'idx_content_date' => '(content_type, imported_at)'
            ],
            'smo_integration_logs' => [
                'idx_integration_action' => '(integration_id, action)',
                'idx_date_status' => '(created_at, status)'
            ]
        ];
        
        foreach ($indexes as $table_name => $table_indexes) {
            foreach ($table_indexes as $index_name => $columns) {
                $full_index_name = $table_name . '_' . $index_name;
                $wpdb->query("CREATE INDEX $full_index_name ON $table_name $columns");
            }
        }
    }
}