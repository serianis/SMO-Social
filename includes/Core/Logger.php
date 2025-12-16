<?php
namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Logger Class
 * 
 * Provides a centralized way to log messages with context and consistent formatting.
 * Only logs when WP_DEBUG is enabled to avoid production clutter.
 * 
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */
class Logger {
    /**
     * Log a debug message
     * 
     * @param string $message The message to log
     * @param array $context Optional context data to log as JSON
     */
    public static function debug($message, $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        // Don't duplicate the prefix if already present
        if (strpos($message, '[SMO Social') !== 0) {
            $formatted = '[SMO Social DEBUG] ' . $message;
        } else {
            $formatted = $message;
        }
        
        if (!empty($context)) {
            $formatted .= ' | Context: ' . json_encode($context);
        }
        
        error_log($formatted);
    }
    
    /**
     * Log an error message
     * Errors should always be logged, regardless of WP_DEBUG unless explicitly suppressed
     * 
     * @param string $message The error message
     * @param array $context Optional context data
     */
    public static function error($message, $context = []) {
        if (strpos($message, '[SMO Social') !== 0) {
            $formatted = '[SMO Social ERROR] ' . $message;
        } else {
            $formatted = $message;
        }

        if (!empty($context)) {
            $formatted .= ' | Context: ' . json_encode($context);
        }
        
        error_log($formatted);
    }
    
    /**
     * Log an info message
     * 
     * @param string $message The info message
     * @param array $context Optional context data
     */
    public static function info($message, $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        if (strpos($message, '[SMO Social') !== 0) {
            $formatted = '[SMO Social INFO] ' . $message;
        } else {
            $formatted = $message;
        }

        if (!empty($context)) {
            $formatted .= ' | ' . json_encode($context);
        }
        
        error_log($formatted);
    }

    /**
     * Log a warning message
     * 
     * @param string $message The warning message
     * @param array $context Optional context data
     */
    public static function warning($message, $context = []) {
        if (strpos($message, '[SMO Social') !== 0) {
            $formatted = '[SMO Social WARNING] ' . $message;
        } else {
            $formatted = $message;
        }

        if (!empty($context)) {
            $formatted .= ' | Context: ' . json_encode($context);
        }
        
        error_log($formatted);
    }
}
