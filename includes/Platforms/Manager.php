<?php
namespace SMO_Social\Platforms;

class Manager {
    private $platforms = array();
    private $platform_cache = array();
    private $loaded_platforms = array();
    private $enabled_platforms = array();

    public function __construct() {
        // Only load enabled platforms configuration, not objects
        $this->enabled_platforms = $this->get_enabled_platforms_config();
    }

    /**
     * Get platform instance with lazy loading
     */
    public function get_platform($slug) {
        // Return cached platform if already loaded
        if (isset($this->loaded_platforms[$slug])) {
            return $this->loaded_platforms[$slug];
        }
        
        // Check if platform is enabled
        if (!$this->is_platform_enabled($slug)) {
            return null;
        }
        
        // Load platform on demand
        $platform = $this->load_single_platform($slug);
        
        if ($platform) {
            $this->loaded_platforms[$slug] = $platform;
        }
        
        return $platform;
    }

    /**
     * Load single platform on demand
     */
    private function load_single_platform($slug) {
        $cache_key = "platform_{$slug}";
        
        // Check memory cache first
        if (isset($this->platform_cache[$cache_key])) {
            return $this->platform_cache[$cache_key];
        }
        
        $platform_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$slug}.json";
        
        if (!file_exists($platform_file)) {
            return null;
        }
        
        // Cache file read result
        static $file_cache = array();
        if (!isset($file_cache[$platform_file])) {
            $file_cache[$platform_file] = json_decode(file_get_contents($platform_file), true);
        }
        
        $platform_data = $file_cache[$platform_file];
        
        if (!$platform_data) {
            return null;
        }
        
        // Apply platform data normalization
        $platform_data = $this->normalize_platform_config($platform_data, $slug);
        
        // Create platform instance
        $class_name = $this->get_platform_class($slug);
        
        // If specific platform class doesn't exist, use the generic Platform class
        if (!class_exists($class_name)) {
            $class_name = '\\SMO_Social\\Platforms\\Platform';
        }
        
        try {
            $platform = new $class_name($platform_data);
            
            // Cache for future use
            $this->platform_cache[$cache_key] = $platform;
            
            return $platform;
        } catch (\Exception $e) {
            error_log("SMO Social: Failed to load platform {$slug}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize platform configuration with defaults
     */
    private function normalize_platform_config($config, $slug) {
        // Ensure we have required fields
        if (!isset($config['name'])) {
            $config['name'] = ucfirst(str_replace('_', ' ', $slug));
        }
        
        // Set default values for API configuration
        if (!isset($config['api_base'])) {
            $config['api_base'] = '';
        }
        
        if (!isset($config['auth_type'])) {
            $config['auth_type'] = 'oauth';
        }
        
        if (!isset($config['max_chars'])) {
            $config['max_chars'] = isset($config['capabilities']['max_chars']) ? $config['capabilities']['max_chars'] : 280;
        }
        
        if (!isset($config['supports_images'])) {
            $config['supports_images'] = isset($config['capabilities']['image']) ? $config['capabilities']['image'] : false;
        }
        
        if (!isset($config['supports_videos'])) {
            $config['supports_videos'] = isset($config['capabilities']['video']) ? $config['capabilities']['video'] : false;
        }
        
        if (!isset($config['features'])) {
            $config['features'] = array();
            if (isset($config['capabilities'])) {
                foreach ($config['capabilities'] as $key => $value) {
                    if ($value === true) {
                        $config['features'][] = $key;
                    }
                }
            }
        }
        
        return $config;
    }

    /**
     * Get platform class name for given slug
     */
    private function get_platform_class($slug) {
        return '\\SMO_Social\\Platforms\\' . ucfirst($slug);
    }

    /**
     * Get enabled platforms configuration without instantiating objects
     */
    private function get_enabled_platforms_config() {
        $enabled = get_option('smo_social_enabled_platforms', array());
        
        if (!is_array($enabled)) {
            return array();
        }
        
        // Return only platform slugs, not objects
        return array_filter($enabled, 'is_string');
    }

    /**
     * Check if platform is enabled
     */
    private function is_platform_enabled($slug) {
        return in_array($slug, $this->enabled_platforms);
    }

    /**
     * Get all loaded platforms (legacy compatibility)
     */
    public function get_platforms() {
        return $this->loaded_platforms;
    }

    /**
     * Get all enabled platforms (loads them on demand)
     * This method has been optimized to avoid loading all platforms at once
     */
    public function get_enabled_platforms(): array {
        $result = array();
        
        // Load platforms on demand with memory optimization
        foreach ($this->enabled_platforms as $slug) {
            $platform = $this->get_platform($slug);
            if ($platform) {
                $result[$slug] = $platform;
            }
        }
        
        return $result;
    }

    /**
     * Get enabled platform slugs only (no objects loaded)
     * Memory-optimized version for scenarios where only platform identification is needed
     */
    public function get_enabled_platform_slugs(): array {
        return $this->enabled_platforms;
    }

    /**
     * Get platform configurations without loading objects
     * Returns basic platform metadata without instantiating platform classes
     */
    public function get_platform_configs(): array {
        $configs = array();
        
        foreach ($this->enabled_platforms as $slug) {
            $config = $this->get_platform_config($slug);
            if ($config) {
                $configs[$slug] = $config;
            }
        }
        
        return $configs;
    }

    /**
     * Get single platform configuration without loading object
     */
    public function get_platform_config($slug) {
        if (!$this->is_platform_enabled($slug)) {
            return null;
        }
        
        $platform_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$slug}.json";
        
        if (!file_exists($platform_file)) {
            return null;
        }
        
        // Cache file read result to avoid repeated file operations
        static $file_cache = array();
        if (!isset($file_cache[$platform_file])) {
            $content = file_get_contents($platform_file);
            $file_cache[$platform_file] = $content ? json_decode($content, true) : null;
        }
        
        $config = $file_cache[$platform_file];
        
        if (!$config) {
            return null;
        }
        
        // Apply normalization without loading platform object
        return $this->normalize_platform_config($config, $slug);
    }

    /**
     * Batch load multiple platforms efficiently
     * Loads multiple platforms in a single operation to reduce overhead
     */
    public function load_platform_batch($slugs = array()): array {
        if (empty($slugs)) {
            $slugs = $this->enabled_platforms;
        }
        
        $result = array();
        foreach ($slugs as $slug) {
            if ($this->is_platform_enabled($slug)) {
                $platform = $this->get_platform($slug);
                if ($platform) {
                    $result[$slug] = $platform;
                }
            }
        }
        
        return $result;
    }

    /**
     * Preload critical platforms for performance
     * Loads only the most commonly used platforms to optimize common use cases
     */
    public function preload_critical_platforms(): array {
        // Define critical platforms based on usage frequency
        $critical_platforms = apply_filters('smo_critical_platforms', array('twitter', 'facebook', 'linkedin', 'instagram'));
        
        return $this->load_platform_batch(array_intersect($critical_platforms, $this->enabled_platforms));
    }

    /**
     * Clear platform cache (for testing/cleanup)
     */
    public function clear_platform_cache() {
        $this->loaded_platforms = array();
        $this->platform_cache = array();
    }
}
