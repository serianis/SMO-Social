<?php
/**
 * Safe Array Accessor
 * 
 * Provides safe array access with null checks, type casting, and dot-notation support
 * to prevent undefined index warnings and improve error handling.
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH') && !defined('SMO_SOCIAL_STANDALONE')) {
    exit;
}

/**
 * Safe Array Accessor Trait
 * 
 * Can be used as a trait in classes or standalone via static methods
 */
trait SafeArrayAccessor {
    
    /**
     * Get value from array with dot notation support
     *
     * @param array $array The array to access
     * @param string $key The key (supports dot notation like "user.profile.name")
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The value or default
     */
    protected function array_get($array, $key, $default = null) {
        return self::safe_array_get($array, $key, $default);
    }
    
    /**
     * Get string value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param string $default Default value
     * @return string
     */
    protected function array_get_string($array, $key, $default = '') {
        return self::safe_array_get_string($array, $key, $default);
    }
    
    /**
     * Get boolean value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param bool $default Default value
     * @return bool
     */
    protected function array_get_bool($array, $key, $default = false) {
        return self::safe_array_get_bool($array, $key, $default);
    }
    
    /**
     * Get integer value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param int $default Default value
     * @return int
     */
    protected function array_get_int($array, $key, $default = 0) {
        return self::safe_array_get_int($array, $key, $default);
    }
    
    /**
     * Get array value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param array $default Default value
     * @return array
     */
    protected function array_get_array($array, $key, $default = array()) {
        return self::safe_array_get_array($array, $key, $default);
    }
    
    /**
     * Safely decode JSON with null check
     *
     * @param mixed $json JSON string or null
     * @param bool $assoc Return associative array
     * @param mixed $default Default value if decode fails
     * @return mixed Decoded value or default
     */
    protected function safe_json_decode($json, $assoc = true, $default = null) {
        return self::safe_decode_json($json, $assoc, $default);
    }
    
    /**
     * Static: Get value from array with dot notation support
     *
     * @param array $array The array to access
     * @param string $key The key (supports dot notation)
     * @param mixed $default Default value
     * @return mixed
     */
    public static function safe_array_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        
        // Handle dot notation
        if (strpos($key, '.') === false) {
            return $default;
        }
        
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
    
    /**
     * Static: Get string value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param string $default Default value
     * @return string
     */
    public static function safe_array_get_string($array, $key, $default = '') {
        $value = self::safe_array_get($array, $key, $default);
        
        if (is_string($value)) {
            return $value;
        }
        
        if (is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }
        
        return $default;
    }
    
    /**
     * Static: Get boolean value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param bool $default Default value
     * @return bool
     */
    public static function safe_array_get_bool($array, $key, $default = false) {
        $value = self::safe_array_get($array, $key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return $value != 0;
        }
        
        if (is_string($value)) {
            return in_array(strtolower($value), array('true', '1', 'yes', 'on'), true);
        }
        
        return $default;
    }
    
    /**
     * Static: Get integer value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param int $default Default value
     * @return int
     */
    public static function safe_array_get_int($array, $key, $default = 0) {
        $value = self::safe_array_get($array, $key, $default);
        
        if (is_int($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        return $default;
    }
    
    /**
     * Static: Get array value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param array $default Default value
     * @return array
     */
    public static function safe_array_get_array($array, $key, $default = array()) {
        $value = self::safe_array_get($array, $key, $default);
        
        if (is_array($value)) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Static: Safely decode JSON with null check
     *
     * @param mixed $json JSON string or null
     * @param bool $assoc Return associative array
     * @param mixed $default Default value if decode fails
     * @return mixed
     */
    public static function safe_decode_json($json, $assoc = true, $default = null) {
        if ($json === null || $json === '') {
            return $default !== null ? $default : ($assoc ? array() : null);
        }
        
        if (is_array($json) || is_object($json)) {
            return $json;
        }
        
        $decoded = json_decode($json, $assoc);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default !== null ? $default : ($assoc ? array() : null);
        }
        
        return $decoded;
    }
}

/**
 * Standalone Safe Array Accessor Class
 * 
 * Can be used without trait inclusion via static methods
 */
class SafeArray {
    use SafeArrayAccessor;
    
    /**
     * Get value from array with dot notation support
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get($array, $key, $default = null) {
        return self::safe_array_get($array, $key, $default);
    }
    
    /**
     * Get string value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param string $default Default value
     * @return string
     */
    public static function get_string($array, $key, $default = '') {
        return self::safe_array_get_string($array, $key, $default);
    }
    
    /**
     * Get boolean value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param bool $default Default value
     * @return bool
     */
    public static function get_bool($array, $key, $default = false) {
        return self::safe_array_get_bool($array, $key, $default);
    }
    
    /**
     * Get integer value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param int $default Default value
     * @return int
     */
    public static function get_int($array, $key, $default = 0) {
        return self::safe_array_get_int($array, $key, $default);
    }
    
    /**
     * Get array value from array
     *
     * @param array $array The array to access
     * @param string $key The key
     * @param array $default Default value
     * @return array
     */
    public static function get_array($array, $key, $default = array()) {
        return self::safe_array_get_array($array, $key, $default);
    }
    
    /**
     * Safely decode JSON
     *
     * @param mixed $json JSON string or null
     * @param bool $assoc Return associative array
     * @param mixed $default Default value if decode fails
     * @return mixed
     */
    public static function json_decode($json, $assoc = true, $default = null) {
        return self::safe_decode_json($json, $assoc, $default);
    }
}
