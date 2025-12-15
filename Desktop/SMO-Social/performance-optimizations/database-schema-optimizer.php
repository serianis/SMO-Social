<?php
/**
 * Database Schema Optimizer
 *
 * Adds missing indexes to improve performance on large datasets
 * for chat message tables and AI provider tables
 */

namespace SMO_Social\Performance;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseSchemaOptimizer {

    /**
     * Add all missing database indexes
     */
    public static function add_missing_indexes() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Get table names with prefix
        $tables = self::get_table_names();

        // Add indexes to chat messages table
        self::add_chat_message_indexes($tables['messages']);

        // Add indexes to AI providers table
        self::add_ai_provider_indexes($tables['providers']);

        // Add indexes to AI models table
        self::add_ai_model_indexes($tables['models']);

        // Add indexes to chat sessions table
        self::add_chat_session_indexes($tables['sessions']);

        // Add indexes to rate limiting table
        self::add_rate_limit_indexes($tables['rate_limits']);

        // Add indexes to moderation table
        self::add_moderation_indexes($tables['moderation']);

        return true;
    }

    /**
     * Get all table names with proper prefix
     */
    private static function get_table_names() {
        global $wpdb;

        return [
            'sessions' => $wpdb->prefix . 'smo_chat_sessions',
            'messages' => $wpdb->prefix . 'smo_chat_messages',
            'providers' => $wpdb->prefix . 'smo_ai_providers',
            'models' => $wpdb->prefix . 'smo_ai_models',
            'templates' => $wpdb->prefix . 'smo_chat_templates',
            'audit' => $wpdb->prefix . 'smo_chat_audit',
            'moderation' => $wpdb->prefix . 'smo_chat_moderation',
            'rate_limits' => $wpdb->prefix . 'smo_chat_rate_limits'
        ];
    }

    /**
     * Add indexes to chat messages table
     */
    private static function add_chat_message_indexes($table_name) {
        global $wpdb;

        // Check if indexes already exist
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `$table_name`");

        $indexes_to_add = [
            'idx_session_created' => "ADD INDEX `idx_session_created` (`session_id`, `created_at`)",
            'idx_content_type' => "ADD INDEX `idx_content_type` (`content_type`)",
            'idx_model_used' => "ADD INDEX `idx_model_used` (`model_used`)",
            'idx_flagged_moderation' => "ADD INDEX `idx_flagged_moderation` (`flagged`, `moderation_score`)"
        ];

        foreach ($indexes_to_add as $index_name => $index_sql) {
            $index_exists = false;
            foreach ($existing_indexes as $index) {
                if ($index->Key_name === $index_name) {
                    $index_exists = true;
                    break;
                }
            }

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE `$table_name` $index_sql");
                error_log("SMO_PERF: Added index $index_name to $table_name");
            }
        }
    }

    /**
     * Add indexes to AI providers table
     */
    private static function add_ai_provider_indexes($table_name) {
        global $wpdb;

        // Check if indexes already exist
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `$table_name`");

        $indexes_to_add = [
            'idx_provider_type' => "ADD INDEX `idx_provider_type` (`provider_type`)",
            'idx_is_default' => "ADD INDEX `idx_is_default` (`is_default`)",
            'idx_status_type' => "ADD INDEX `idx_status_type` (`status`, `provider_type`)",
            'idx_base_url' => "ADD INDEX `idx_base_url` (`base_url`(255))"
        ];

        foreach ($indexes_to_add as $index_name => $index_sql) {
            $index_exists = false;
            foreach ($existing_indexes as $index) {
                if ($index->Key_name === $index_name) {
                    $index_exists = true;
                    break;
                }
            }

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE `$table_name` $index_sql");
                error_log("SMO_PERF: Added index $index_name to $table_name");
            }
        }
    }

    /**
     * Add indexes to AI models table
     */
    private static function add_ai_model_indexes($table_name) {
        global $wpdb;

        // Check if indexes already exist
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `$table_name`");

        $indexes_to_add = [
            'idx_provider_model' => "ADD INDEX `idx_provider_model` (`provider_id`, `model_name`)",
            'idx_model_status' => "ADD INDEX `idx_model_status` (`status`)"
        ];

        foreach ($indexes_to_add as $index_name => $index_sql) {
            $index_exists = false;
            foreach ($existing_indexes as $index) {
                if ($index->Key_name === $index_name) {
                    $index_exists = true;
                    break;
                }
            }

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE `$table_name` $index_sql");
                error_log("SMO_PERF: Added index $index_name to $table_name");
            }
        }
    }

    /**
     * Add indexes to chat sessions table
     */
    private static function add_chat_session_indexes($table_name) {
        global $wpdb;

        // Check if indexes already exist
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `$table_name`");

        $indexes_to_add = [
            'idx_user_status' => "ADD INDEX `idx_user_status` (`user_id`, `status`)",
            'idx_session_provider' => "ADD INDEX `idx_session_provider` (`provider_id`)",
            'idx_last_activity' => "ADD INDEX `idx_last_activity` (`last_activity`)"
        ];

        foreach ($indexes_to_add as $index_name => $index_sql) {
            $index_exists = false;
            foreach ($existing_indexes as $index) {
                if ($index->Key_name === $index_name) {
                    $index_exists = true;
                    break;
                }
            }

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE `$table_name` $index_sql");
                error_log("SMO_PERF: Added index $index_name to $table_name");
            }
        }
    }

    /**
     * Add indexes to rate limiting table
     */
    private static function add_rate_limit_indexes($table_name) {
        global $wpdb;

        // Check if indexes already exist
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `$table_name`");

        $indexes_to_add = [
            'idx_rate_limit_composite' => "ADD INDEX `idx_rate_limit_composite` (`user_id`, `provider_id`, `rate_limit_key`, `window_start`)"
        ];

        foreach ($indexes_to_add as $index_name => $index_sql) {
            $index_exists = false;
            foreach ($existing_indexes as $index) {
                if ($index->Key_name === $index_name) {
                    $index_exists = true;
                    break;
                }
            }

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE `$table_name` $index_sql");
                error_log("SMO_PERF: Added index $index_name to $table_name");
            }
        }
    }

    /**
     * Add indexes to moderation table
     */
    private static function add_moderation_indexes($table_name) {
        global $wpdb;

        // Check if indexes already exist
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `$table_name`");

        $indexes_to_add = [
            'idx_moderation_workflow' => "ADD INDEX `idx_moderation_workflow` (`status`, `reviewed_by`, `reviewed_at`)",
            'idx_content_hash' => "ADD INDEX `idx_content_hash` (`content_hash`)"
        ];

        foreach ($indexes_to_add as $index_name => $index_sql) {
            $index_exists = false;
            foreach ($existing_indexes as $index) {
                if ($index->Key_name === $index_name) {
                    $index_exists = true;
                    break;
                }
            }

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE `$table_name` $index_sql");
                error_log("SMO_PERF: Added index $index_name to $table_name");
            }
        }
    }

    /**
     * Check if indexes exist and log results
     */
    public static function check_indexes() {
        global $wpdb;

        $tables = self::get_table_names();
        $results = [];

        foreach ($tables as $name => $table) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM `$table`");
            $results[$name] = [
                'table' => $table,
                'index_count' => count($indexes),
                'indexes' => $indexes
            ];
        }

        return $results;
    }
}