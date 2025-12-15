<?php
namespace SMO_Social\AI;

/**
 * AI Cache Helper
 * Handles caching operations for AI providers and settings
 */
class CacheHelper {
    
    /**
     * Clear provider-specific cache
     * 
     * @param string|null $provider_id Provider ID or null for all
     */
    public static function clear_provider_cache($provider_id = null) {
        if ($provider_id) {
            $provider = ProvidersConfig::get_provider($provider_id);
            if ($provider && isset($provider['key_option'])) {
                wp_cache_delete($provider['key_option'], 'options');
            }
        } else {
            // Clear all provider caches
            $providers = ProvidersConfig::get_all_providers();
            foreach ($providers as $p) {
                if (isset($p['key_option'])) {
                    wp_cache_delete($p['key_option'], 'options');
                }
            }
        }
        
        // Clear general AI transients
        delete_transient('smo_ai_provider_config');
        delete_transient('smo_ai_settings');
        
        // Flush object cache group if available
        if (function_exists('wp_cache_flush_group')) {
            \wp_cache_flush_group('smo_social_ai');
        }
    }
    
    /**
     * Clear all AI-related caches
     */
    public static function clear_all() {
        self::clear_provider_cache();
        
        // Clear any other AI related transients
        delete_transient('smo_ai_models_cache');
        delete_transient('smo_ai_status_cache');
    }
}
