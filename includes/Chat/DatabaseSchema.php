<?php
namespace SMO_Social\Chat;

use SMO_Social\Core\Activator;

/**
 * Database schema for chat functionality
 * Creates tables for sessions, messages, providers, and audit logs
 */
class DatabaseSchema {
    
    /**
     * Create all chat-related database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Chat sessions table
        $sessions_table = $wpdb->prefix . 'smo_chat_sessions';
        $sessions_sql = "CREATE TABLE $sessions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            session_name varchar(255) DEFAULT '',
            provider_id bigint(20) DEFAULT NULL,
            model_name varchar(255) NOT NULL,
            system_prompt longtext,
            conversation_context longtext,
            status enum('active', 'archived', 'deleted') DEFAULT 'active',
            message_count int(11) DEFAULT 0,
            token_usage int(11) DEFAULT 0,
            cost_estimate decimal(10,6) DEFAULT 0.000000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY provider_id (provider_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY idx_user_status (user_id, status),
            KEY idx_session_provider (provider_id),
            KEY idx_last_activity (last_activity)
        ) $charset_collate;";
        
        // Chat messages table
        $messages_table = $wpdb->prefix . 'smo_chat_messages';
        $messages_sql = "CREATE TABLE $messages_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            role enum('user', 'assistant', 'system', 'tool') NOT NULL,
            content longtext NOT NULL,
            content_type enum('text', 'json', 'markdown', 'code') DEFAULT 'text',
            metadata longtext,
            tokens_used int(11) DEFAULT 0,
            processing_time_ms int(11) DEFAULT 0,
            model_used varchar(255),
            provider_response longtext,
            flagged boolean DEFAULT FALSE,
            moderation_score decimal(3,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY role (role),
            KEY created_at (created_at),
            KEY flagged (flagged),
            KEY idx_session_created (session_id, created_at),
            KEY idx_content_type (content_type),
            KEY idx_model_used (model_used),
            KEY idx_flagged_moderation (flagged, moderation_score),
            FOREIGN KEY (session_id) REFERENCES {$wpdb->prefix}smo_chat_sessions(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // AI Providers table
        $providers_table = $wpdb->prefix . 'smo_ai_providers';
        $providers_sql = "CREATE TABLE $providers_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            display_name varchar(100) NOT NULL,
            provider_type varchar(50) NOT NULL,
            base_url varchar(500) NOT NULL,
            auth_type enum('api_key', 'oauth2', 'none') NOT NULL,
            auth_config longtext,
            default_params longtext,
            supported_models longtext,
            features longtext,
            rate_limits longtext,
            status enum('active', 'inactive', 'testing') DEFAULT 'active',
            is_default boolean DEFAULT FALSE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY provider_type (provider_type),
            KEY status (status),
            KEY idx_provider_type (provider_type),
            KEY idx_is_default (is_default),
            KEY idx_status_type (status, provider_type),
            KEY idx_base_url (base_url(255))
        ) $charset_collate;";
        
        // Provider models table
        $models_table = $wpdb->prefix . 'smo_ai_models';
        $models_sql = "CREATE TABLE $models_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) NOT NULL,
            model_name varchar(255) NOT NULL,
            display_name varchar(255) NOT NULL,
            description text,
            max_tokens int(11) DEFAULT NULL,
            cost_per_token decimal(10,8) DEFAULT 0.00000000,
            capabilities longtext,
            parameters longtext,
            status enum('active', 'inactive', 'deprecated') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY provider_id (provider_id),
            KEY model_name (model_name),
            KEY status (status),
            KEY idx_provider_model (provider_id, model_name),
            KEY idx_model_status (status),
            FOREIGN KEY (provider_id) REFERENCES {$wpdb->prefix}smo_ai_providers(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Chat templates table
        $templates_table = $wpdb->prefix . 'smo_chat_templates';
        $templates_sql = "CREATE TABLE $templates_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            system_prompt longtext NOT NULL,
            template_type enum('general', 'caption', 'thread', 'ad', 'blog', 'custom') DEFAULT 'general',
            parameters longtext,
            is_public boolean DEFAULT FALSE,
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY template_type (template_type),
            KEY is_public (is_public)
        ) $charset_collate;";
        
        // Chat audit logs table
        $audit_table = $wpdb->prefix . 'smo_chat_audit';
        $audit_sql = "CREATE TABLE $audit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            session_id bigint(20) DEFAULT NULL,
            action varchar(100) NOT NULL,
            resource_type varchar(50) DEFAULT NULL,
            resource_id bigint(20) DEFAULT NULL,
            details longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Moderation queue table
        $moderation_table = $wpdb->prefix . 'smo_chat_moderation';
        $moderation_sql = "CREATE TABLE $moderation_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_id bigint(20) NOT NULL,
            session_id bigint(20) NOT NULL,
            content_hash varchar(64) NOT NULL,
            moderation_result longtext,
            confidence_score decimal(3,2) DEFAULT 0.00,
            flagged_categories longtext,
            status enum('pending', 'approved', 'rejected', 'reviewed') DEFAULT 'pending',
            reviewed_by bigint(20) DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            review_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY message_id (message_id),
            KEY session_id (session_id),
            KEY content_hash (content_hash),
            KEY status (status),
            KEY reviewed_by (reviewed_by),
            KEY idx_moderation_workflow (status, reviewed_by, reviewed_at),
            KEY idx_content_hash (content_hash),
            FOREIGN KEY (message_id) REFERENCES {$wpdb->prefix}smo_chat_messages(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Rate limiting table
        $rate_limit_table = $wpdb->prefix . 'smo_chat_rate_limits';
        $rate_limit_sql = "CREATE TABLE $rate_limit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            provider_id bigint(20) DEFAULT NULL,
            rate_limit_key varchar(100) NOT NULL,
            window_start datetime NOT NULL,
            request_count int(11) DEFAULT 0,
            tokens_used int(11) DEFAULT 0,
            cost_accumulated decimal(10,6) DEFAULT 0.000000,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY provider_id (provider_id),
            KEY rate_limit_key (rate_limit_key),
            KEY window_start (window_start),
            KEY (user_id, provider_id, window_start),
            KEY idx_rate_limit_composite (user_id, provider_id, rate_limit_key, window_start)
        ) $charset_collate;";
        
        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sessions_sql);
        dbDelta($messages_sql);
        dbDelta($providers_sql);
        dbDelta($models_sql);
        dbDelta($templates_sql);
        dbDelta($audit_sql);
        dbDelta($moderation_sql);
        dbDelta($rate_limit_sql);
        
        // Insert default providers
        self::insert_default_providers();
    }
    
    /**
     * Insert default AI providers
     */
    private static function insert_default_providers() {
        global $wpdb;
        
        $providers_table = $wpdb->prefix . 'smo_ai_providers';
        $models_table = $wpdb->prefix . 'smo_ai_models';
        
        // Check if providers already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");
        if ($existing > 0) {
            return;
        }
        
        // HuggingFace provider
        $hf_provider_id = $wpdb->insert(
            $providers_table,
            [
                'name' => 'huggingface',
                'display_name' => 'HuggingFace',
                'provider_type' => 'huggingface',
                'base_url' => 'https://api-inference.huggingface.co/models',
                'auth_type' => 'api_key',
                'default_params' => json_encode([
                    'temperature' => 0.7,
                    'max_tokens' => 512,
                    'stream' => false
                ]),
                'supported_models' => json_encode([
                    'microsoft/DialoGPT-medium',
                    'facebook/blenderbot-400M-distill',
                    'microsoft/DialoGPT-large'
                ]),
                'features' => json_encode([
                    'chat' => true,
                    'completion' => true,
                    'embeddings' => false,
                    'streaming' => false
                ]),
                'status' => 'active',
                'is_default' => true
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        // Add HuggingFace models
        if ($hf_provider_id) {
            $hf_models = [
                [
                    'model_name' => 'microsoft/DialoGPT-medium',
                    'display_name' => 'DialoGPT Medium',
                    'description' => 'Medium-sized conversational AI model',
                    'max_tokens' => 1024,
                    'capabilities' => json_encode(['chat', 'conversation']),
                    'parameters' => json_encode(['temperature' => 0.7, 'max_tokens' => 512])
                ],
                [
                    'model_name' => 'facebook/blenderbot-400M-distill',
                    'display_name' => 'BlenderBot 400M',
                    'description' => 'BlenderBot conversational model',
                    'max_tokens' => 512,
                    'capabilities' => json_encode(['chat', 'conversation']),
                    'parameters' => json_encode(['temperature' => 0.7, 'max_tokens' => 256])
                ]
            ];
            
            foreach ($hf_models as $model) {
                $wpdb->insert(
                    $models_table,
                    array_merge($model, ['provider_id' => $hf_provider_id]),
                    ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d']
                );
            }
        }
        
        // Localhost provider (for Ollama, etc.)
        $localhost_provider_id = $wpdb->insert(
            $providers_table,
            [
                'name' => 'localhost',
                'display_name' => 'Localhost (Ollama/LM Studio)',
                'provider_type' => 'localhost',
                'base_url' => 'http://localhost:11434',
                'auth_type' => 'none',
                'default_params' => json_encode([
                    'temperature' => 0.7,
                    'max_tokens' => 512,
                    'stream' => true
                ]),
                'supported_models' => json_encode([
                    'llama2',
                    'codellama',
                    'mistral',
                    'neural-chat'
                ]),
                'features' => json_encode([
                    'chat' => true,
                    'completion' => true,
                    'embeddings' => true,
                    'streaming' => true
                ]),
                'status' => 'active',
                'is_default' => false
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        // Custom API provider (for OpenAI, Anthropic, etc.)
        $custom_provider_id = $wpdb->insert(
            $providers_table,
            [
                'name' => 'custom_api',
                'display_name' => 'Custom API Provider',
                'provider_type' => 'custom',
                'base_url' => '',
                'auth_type' => 'api_key',
                'default_params' => json_encode([
                    'temperature' => 0.7,
                    'max_tokens' => 512,
                    'stream' => true
                ]),
                'supported_models' => json_encode([
                    'gpt-3.5-turbo',
                    'gpt-4',
                    'claude-3-sonnet',
                    'claude-3-opus'
                ]),
                'features' => json_encode([
                    'chat' => true,
                    'completion' => true,
                    'embeddings' => true,
                    'streaming' => true
                ]),
                'status' => 'inactive',
                'is_default' => false
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Drop all chat-related tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'smo_chat_rate_limits',
            $wpdb->prefix . 'smo_chat_moderation',
            $wpdb->prefix . 'smo_chat_audit',
            $wpdb->prefix . 'smo_chat_templates',
            $wpdb->prefix . 'smo_ai_models',
            $wpdb->prefix . 'smo_ai_providers',
            $wpdb->prefix . 'smo_chat_messages',
            $wpdb->prefix . 'smo_chat_sessions'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Get table names
     */
    public static function get_table_names() {
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
}