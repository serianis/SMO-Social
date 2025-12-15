<?php
namespace SMO_Social\AI;

use SMO_Social\Chat\DatabaseSchema;
use SMO_Social\Core\SafeArray;

/**
 * Database Provider Loader
 * Loads AI provider configurations from the database instead of static config
 */
class DatabaseProviderLoader {

    /**
     * Get provider configuration from database
     *
     * @param string $provider_id Provider ID or name
     * @return array|null Provider configuration or null if not found
     */
    public static function get_provider_from_database($provider_id) {
        global $wpdb;

        error_log("SMO_AI_DB: Attempting to load provider {$provider_id} from database");

        // Check if WordPress database is available
        if (!function_exists('get_option') || !isset($wpdb)) {
            error_log("SMO_AI_DB_ERROR: WordPress database not available for provider {$provider_id}");
            return null;
        }

        $providers_table = $wpdb->prefix . 'smo_ai_providers';
        error_log("SMO_AI_DB: Querying table {$providers_table} for provider {$provider_id}");

        $provider = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $providers_table WHERE name = %s OR id = %d LIMIT 1",
            $provider_id, $provider_id
        ), ARRAY_A);

        if (!$provider) {
            error_log("SMO_AI_DB: Provider {$provider_id} not found in database");
            return null;
        }

        error_log("SMO_AI_DB: Provider {$provider_id} found in database, transforming...");
        $transformed = self::transform_database_provider($provider);
        error_log("SMO_AI_DB: Transformed provider config: " . print_r($transformed, true));

        return $transformed;
    }

    /**
     * Get all providers from database
     *
     * @return array Array of provider configurations
     */
    public static function get_all_providers_from_database() {
        global $wpdb;

        $providers_table = $wpdb->prefix . 'smo_ai_providers';
        $providers = $wpdb->get_results("SELECT * FROM $providers_table", ARRAY_A);

        $result = [];
        foreach ($providers as $provider) {
            $result[$provider['name']] = self::transform_database_provider($provider);
        }

        return $result;
    }

    /**
     * Transform database provider format to UniversalManager expected format
     *
     * @param array $db_provider Database provider record
     * @return array Transformed provider configuration
     */
    private static function transform_database_provider($db_provider) {
        if (!is_array($db_provider)) {
            error_log("SMO_AI_DB_ERROR: Invalid provider data - not an array");
            return array();
        }
        
        // Safely get provider name
        $provider_name = SafeArray::get_string($db_provider, 'name', 'unknown');
        
        if ($provider_name === 'unknown') {
            error_log("SMO_AI_DB_ERROR: Provider name missing in database record");
        }
        
        // Parse JSON fields with safe decoding
        $auth_config = SafeArray::json_decode(
            SafeArray::get($db_provider, 'auth_config'),
            true,
            array()
        );
        $default_params = SafeArray::json_decode(
            SafeArray::get($db_provider, 'default_params'),
            true,
            array()
        );
        $supported_models = SafeArray::json_decode(
            SafeArray::get($db_provider, 'supported_models'),
            true,
            array()
        );
        $features = SafeArray::json_decode(
            SafeArray::get($db_provider, 'features'),
            true,
            array()
        );
        $rate_limits = SafeArray::json_decode(
            SafeArray::get($db_provider, 'rate_limits'),
            true,
            array()
        );

        // Get static provider config to maintain consistent option names
        $static_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();
        $static_provider = SafeArray::get_array($static_providers, $provider_name, array());

        // Determine consistent option names
        $key_option = 'smo_social_' . $provider_name . '_api_key';
        $url_option = 'smo_social_' . $provider_name . '_base_url';

        // If static provider exists, use its option names for consistency
        if (!empty($static_provider)) {
            $key_option = SafeArray::get_string($static_provider, 'key_option', $key_option);
            $url_option = SafeArray::get_string($static_provider, 'url_option', $url_option);
        }
        
        // Get display name and provider type with defaults
        $display_name = SafeArray::get_string($db_provider, 'display_name', ucfirst($provider_name));
        $provider_type = SafeArray::get_string($db_provider, 'provider_type', 'custom');
        $auth_type = SafeArray::get_string($db_provider, 'auth_type', 'api_key');
        $base_url = SafeArray::get_string($db_provider, 'base_url', '');
        $status = SafeArray::get_string($db_provider, 'status', 'active');
        $is_default = SafeArray::get_bool($db_provider, 'is_default', false);

        // Map database fields to UniversalManager expected format
        $transformed = array(
            'id' => $provider_name,
            'name' => $display_name,
            'type' => $provider_type,
            'auth_type' => $auth_type,
            'base_url' => $base_url,
            'models' => $supported_models,
            'capabilities' => $features,
            'requires_key' => self::determine_requires_key($auth_type),
            'key_option' => $key_option,
            'url_option' => $url_option,
            'docs_url' => SafeArray::get_string($static_provider, 'docs_url', ''),
            'icon' => SafeArray::get_string($static_provider, 'icon', $provider_type . '.svg'),
            'description' => SafeArray::get_string(
                $static_provider,
                'description',
                $display_name . ' AI provider'
            ),
            'default_params' => $default_params,
            'rate_limits' => $rate_limits,
            'auth_config' => $auth_config,
            'status' => $status,
            'is_default' => $is_default
        );

        return $transformed;
    }

    /**
     * Determine if provider requires API key based on auth type
     *
     * @param string $auth_type Authentication type
     * @return bool True if requires key
     */
    private static function determine_requires_key($auth_type) {
        return !in_array($auth_type, ['none', '']);
    }

    /**
     * Get API key using consistent naming convention
     *
     * @param string $provider_id Provider ID
     * @return string|null API key or null if not found
     */
    public static function get_api_key_consistently($provider_id) {
        error_log("SMO_AI_DB: Getting API key consistently for provider {$provider_id}");

        // Get provider configuration
        $provider = self::get_provider_from_database($provider_id);
        if (!$provider) {
            error_log("SMO_AI_DB: Provider {$provider_id} not found in database");
            return null;
        }

        // Try to get static provider to determine correct option name
        $static_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();
        $static_provider = SafeArray::get_array($static_providers, $provider_id, array());

        $key_option = '';
        if (!empty($static_provider)) {
            $key_option = SafeArray::get_string($static_provider, 'key_option');
            if (!empty($key_option)) {
                error_log("SMO_AI_DB: Using static provider key_option: {$key_option}");
            }
        }
        
        if (empty($key_option)) {
            // Fallback to database provider's key_option
            $key_option = SafeArray::get_string(
                $provider,
                'key_option',
                'smo_social_' . $provider_id . '_api_key'
            );
            error_log("SMO_AI_DB: Using database provider key_option: {$key_option}");
        }

        // Get the API key
        $api_key = get_option($key_option);
        error_log("SMO_AI_DB: Retrieved API key for {$key_option}: " . (!empty($api_key) ? 'present' : 'empty'));

        return $api_key;
    }

    /**
     * Check if provider is configured (has required credentials)
     *
     * @param string $provider_id Provider ID
     * @return bool True if configured
     */
    public static function is_provider_configured($provider_id) {
        error_log("SMO_AI_DB: Checking if provider {$provider_id} is configured");
        $provider = self::get_provider_from_database($provider_id);
        if (!$provider) {
            error_log("SMO_AI_DB: Provider {$provider_id} not found in database");
            return false;
        }

        error_log("SMO_AI_DB: Provider {$provider_id} found, checking configuration");
        error_log("SMO_AI_DB: Provider config: " . print_r($provider, true));

        // Check if provider requires a key and has one configured
        $requires_key = SafeArray::get_bool($provider, 'requires_key', false);
        if ($requires_key) {
            // Use consistent API key retrieval
            $key = self::get_api_key_consistently($provider_id);
            if (empty($key)) {
                error_log("SMO_AI_DB_ERROR: Provider {$provider_id} requires key but none configured");
                return false;
            }
        }

        // Check if provider requires a URL and has one configured
        $url_option = SafeArray::get_string($provider, 'url_option');
        if (!empty($url_option)) {
            error_log("SMO_AI_DB: Provider requires URL, checking option: " . $url_option);
            $url = get_option($url_option);
            error_log("SMO_AI_DB: Retrieved URL for " . $url_option . ": " . (!empty($url) ? $url : 'empty'));

            if (empty($url)) {
                error_log("SMO_AI_DB_ERROR: Provider {$provider_id} requires URL but none configured");
                return false;
            }
        }

        error_log("SMO_AI_DB: Provider {$provider_id} is fully configured");
        return true;
    }

    /**
     * Get all configured providers from database
     *
     * @return array Array of configured providers
     */
    public static function get_configured_providers() {
        $all_providers = self::get_all_providers_from_database();
        $configured = [];

        foreach ($all_providers as $id => $provider) {
            if (self::is_provider_configured($id)) {
                $configured[$id] = $provider;
            }
        }

        return $configured;
    }

    /**
     * Save provider configuration to database
     *
     * @param string $provider_id Provider ID
     * @param array $config Configuration to save
     * @return bool|int False on failure, insert ID on success
     */
    public static function save_provider_to_database($provider_id, $config) {
        global $wpdb;

        $providers_table = $wpdb->prefix . 'smo_ai_providers';

        // Transform config to database format
        $db_config = self::transform_to_database_format($provider_id, $config);

        // Check if provider already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $providers_table WHERE name = %s LIMIT 1",
            $provider_id
        ));

        if ($existing) {
            // Update existing provider
            $db_config['updated_at'] = current_time('mysql');
            $result = $wpdb->update(
                $providers_table,
                $db_config,
                ['id' => $existing],
                self::get_database_format_types()
            );
            return $result !== false;
        } else {
            // Insert new provider
            $db_config['created_at'] = current_time('mysql');
            $db_config['updated_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $providers_table,
                $db_config,
                self::get_database_format_types()
            );
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Transform UniversalManager format to database format
     *
     * @param string $provider_id Provider ID
     * @param array $config Provider configuration
     * @return array Database format configuration
     */
    private static function transform_to_database_format($provider_id, $config) {
        $db_config = [
            'name' => $provider_id,
            'display_name' => $config['name'] ?? $provider_id,
            'provider_type' => $config['type'] ?? 'custom',
            'base_url' => $config['base_url'] ?? '',
            'auth_type' => $config['auth_type'] ?? 'api_key',
            'auth_config' => json_encode($config['auth_config'] ?? []),
            'default_params' => json_encode($config['default_params'] ?? []),
            'supported_models' => json_encode($config['models'] ?? []),
            'features' => json_encode($config['capabilities'] ?? []),
            'rate_limits' => json_encode($config['rate_limits'] ?? []),
            'status' => $config['status'] ?? 'active',
            'is_default' => $config['is_default'] ?? false
        ];

        return $db_config;
    }

    /**
     * Get format types for database operations
     *
     * @return array Format types
     */
    private static function get_database_format_types() {
        return [
            '%s', // name
            '%s', // display_name
            '%s', // provider_type
            '%s', // base_url
            '%s', // auth_type
            '%s', // auth_config
            '%s', // default_params
            '%s', // supported_models
            '%s', // features
            '%s', // rate_limits
            '%s', // status
            '%d'  // is_default
        ];
    }

    /**
     * Ensure database tables exist
     */
    public static function ensure_tables_exist() {
        DatabaseSchema::create_tables();
    }
}