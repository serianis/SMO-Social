<?php
namespace SMO_Social\AI;

use SMO_Social\Chat\DatabaseSchema;

/**
 * Database Provider Migrator
 * Migrates static provider configurations to database
 */
class DatabaseProviderMigrator {

    /**
     * Migrate all static providers to database
     */
    public static function migrate_static_providers_to_database() {
        global $wpdb;

        $providers_table = $wpdb->prefix . 'smo_ai_providers';

        // Check if migration is needed
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");
        if ($existing_count > 0) {
            error_log("DatabaseProviderMigrator: Database already has providers, skipping migration");
            return;
        }

        error_log("DatabaseProviderMigrator: Starting migration of static providers to database");

        // Get all static providers
        $static_providers = ProvidersConfig::get_all_providers();

        foreach ($static_providers as $provider_id => $config) {
            self::migrate_single_provider($provider_id, $config);
        }

        error_log("DatabaseProviderMigrator: Migration completed. Migrated " . count($static_providers) . " providers");
    }

    /**
     * Migrate single provider to database
     */
    private static function migrate_single_provider($provider_id, $config) {
        global $wpdb;

        $providers_table = $wpdb->prefix . 'smo_ai_providers';

        // Check if provider already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $providers_table WHERE name = %s LIMIT 1",
            $provider_id
        ));

        if ($existing) {
            error_log("DatabaseProviderMigrator: Provider {$provider_id} already exists, skipping");
            return;
        }

        // Transform static config to database format
        $db_config = [
            'name' => $provider_id,
            'display_name' => $config['name'],
            'provider_type' => $config['type'],
            'base_url' => $config['base_url'],
            'auth_type' => $config['auth_type'],
            'auth_config' => json_encode([]),
            'default_params' => json_encode([
                'temperature' => 0.7,
                'max_tokens' => 512,
                'stream' => false
            ]),
            'supported_models' => json_encode($config['models']),
            'features' => json_encode($config['capabilities']),
            'rate_limits' => json_encode([]),
            'status' => 'active',
            'is_default' => $provider_id === 'openai' // Set OpenAI as default
        ];

        // Insert provider
        $result = $wpdb->insert(
            $providers_table,
            $db_config,
            [
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%d'
            ]
        );

        if ($result) {
            error_log("DatabaseProviderMigrator: Successfully migrated provider {$provider_id}");
        } else {
            error_log("DatabaseProviderMigrator: Failed to migrate provider {$provider_id}");
        }
    }

    /**
     * Ensure database tables exist and migrate if needed
     */
    public static function ensure_database_ready() {
        // Ensure tables exist
        \SMO_Social\Chat\DatabaseSchema::create_tables();

        // Migrate static providers if database is empty
        self::migrate_static_providers_to_database();
    }
}