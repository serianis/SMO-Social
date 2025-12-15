<?php
/**
 * Centralized AJAX security validation helper
 * 
 * Replaces 233+ instances of duplicated check_ajax_referer() calls
 * Provides consistent nonce validation, permission checking, and CSRF protection
 * 
 * @package SMO_Social
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AjaxSecurityHelper {
    
    /**
     * Default nonce action for SMO Social
     */
    const DEFAULT_NONCE_ACTION = 'smo_social_nonce';
    
    /**
     * Default nonce field name
     */
    const DEFAULT_NONCE_FIELD = 'nonce';
    
    /**
     * Validate AJAX request with nonce and permissions
     * 
     * @param string $nonce_action Nonce action (defaults to smo_social_nonce)
     * @param string $nonce_field Nonce field name (defaults to nonce)
     * @param string $capability Required capability (defaults to edit_posts)
     * @param bool $die Whether to die on failure (defaults to true)
     * @return bool True if valid
     * @throws \Exception If validation fails and $die is false
     */
    public static function validateAjaxRequest(
        $nonce_action = self::DEFAULT_NONCE_ACTION,
        $nonce_field = self::DEFAULT_NONCE_FIELD,
        $capability = 'edit_posts',
        $die = true
    ) {
        // Check nonce first
        if (!self::validateNonce($nonce_action, $nonce_field, $die)) {
            return false;
        }
        
        // Check permissions
        return self::checkPermission($capability, $die);
    }
    
    /**
     * Validate nonce only
     * 
     * @param string $action Nonce action
     * @param string $query_arg Nonce field name
     * @param bool $die Whether to die on failure
     * @return bool
     */
    public static function validateNonce($action = self::DEFAULT_NONCE_ACTION, $query_arg = self::DEFAULT_NONCE_FIELD, $die = true) {
        // Use WordPress function if available
        if (function_exists('check_ajax_referer')) {
            return check_ajax_referer($action, $query_arg, $die) !== false;
        }
        
        // Fallback for non-WordPress environments
        $nonce = self::getNonceFromRequest($query_arg);
        if (empty($nonce)) {
            if ($die) {
                wp_die('Security check failed - nonce missing', 403);
            }
            throw new \Exception('Security check failed - nonce missing');
        }
        
        // Basic validation (in real implementation, you'd verify against stored nonces)
        if (!self::isValidNonceFormat($nonce)) {
            if ($die) {
                wp_die('Security check failed - invalid nonce format', 403);
            }
            throw new \Exception('Security check failed - invalid nonce format');
        }
        
        return true;
    }
    
    /**
     * Check user permissions
     * 
     * @param string $capability Required capability
     * @param bool $die Whether to die on failure
     * @return bool
     */
    public static function checkPermission($capability = 'edit_posts', $die = true) {
        // Use WordPress function if available
        if (function_exists('current_user_can')) {
            if (!current_user_can($capability)) {
                if ($die) {
                    wp_die('Insufficient permissions', 403);
                }
                return false;
            }
            return true;
        }
        
        // Fallback for non-WordPress environments
        // In a real implementation, you'd have your own permission system
        if ($die) {
            wp_die('Permission check failed', 403);
        }
        return false;
    }
    
    /**
     * Validate specific SMO Social AJAX actions
     * 
     * @param string $action The AJAX action being performed
     * @param array $options Validation options
     * @return bool
     */
    public static function validateSmoAction($action, $options = []) {
        $defaults = [
            'nonce_action' => self::DEFAULT_NONCE_ACTION,
            'capability' => 'edit_posts',
            'required_params' => [],
            'validate_params' => []
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Validate the main request
        if (!self::validateAjaxRequest(
            $options['nonce_action'],
            self::DEFAULT_NONCE_FIELD,
            $options['capability'],
            false
        )) {
            return false;
        }
        
        // Check for required parameters
        foreach ($options['required_params'] as $param) {
            if (!isset($_POST[$param]) && !isset($_GET[$param])) {
                if ($options['capability'] === 'manage_options') {
                    wp_send_json_error('Missing required parameter: ' . $param, 400);
                }
                return false;
            }
        }
        
        // Validate specific parameters
        foreach ($options['validate_params'] as $param => $validator) {
            $value = $_POST[$param] ?? $_GET[$param] ?? null;
            if ($value !== null && !$validator($value)) {
                if ($options['capability'] === 'manage_options') {
                    wp_send_json_error('Invalid parameter: ' . $param, 400);
                }
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send success JSON response with consistent format
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $status_code HTTP status code
     */
    public static function sendSuccess($data = null, $message = 'Success', $status_code = 200) {
        if (function_exists('wp_send_json_success')) {
            wp_send_json_success($data, $status_code);
        } else {
            self::sendJsonResponse(['success' => true, 'data' => $data, 'message' => $message], $status_code);
        }
    }
    
    /**
     * Send error JSON response with consistent format
     * 
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @param mixed $data Additional error data
     */
    public static function sendError($message = 'An error occurred', $status_code = 400, $data = null) {
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error($data ?: $message, $status_code);
        } else {
            self::sendJsonResponse(['success' => false, 'data' => $data ?: $message, 'message' => $message], $status_code);
        }
    }
    
    /**
     * Validate multiple AJAX actions at once
     * 
     * @param array $actions Array of action names to validate
     * @param array $options Validation options for each action
     * @return bool|string True if all valid, or first failed action name
     */
    public static function validateMultipleActions($actions, $options = []) {
        foreach ($actions as $action) {
            if (!self::validateSmoAction($action, $options[$action] ?? [])) {
                return $action;
            }
        }
        return true;
    }
    
    /**
     * Generate CSRF token for forms
     * 
     * @param string $action Action name for the token
     * @return string CSRF token
     */
    public static function generateCSRFToken($action = self::DEFAULT_NONCE_ACTION) {
        if (function_exists('wp_create_nonce')) {
            return wp_create_nonce($action);
        }
        
        // Fallback token generation
        return hash('sha256', $action . wp_salt('nonce') . time());
    }
    
    /**
     * Verify CSRF token
     * 
     * @param string $token Token to verify
     * @param string $action Expected action
     * @return bool
     */
    public static function verifyCSRFToken($token, $action = self::DEFAULT_NONCE_ACTION) {
        if (function_exists('wp_verify_nonce')) {
            return wp_verify_nonce($token, $action) !== false;
        }
        
        // Basic token validation (in real implementation, you'd verify against stored tokens)
        return !empty($token) && strlen($token) > 10;
    }
    
    /**
     * Get nonce from request
     * 
     * @param string $field Field name to look for
     * @return string|null
     */
    private static function getNonceFromRequest($field = self::DEFAULT_NONCE_FIELD) {
        return $_POST[$field] ?? $_GET[$field] ?? null;
    }
    
    /**
     * Check if nonce format is valid
     * 
     * @param string $nonce Nonce to validate
     * @return bool
     */
    private static function isValidNonceFormat($nonce) {
        // Basic format validation - adjust based on your nonce implementation
        return !empty($nonce) && strlen($nonce) >= 8 && strlen($nonce) <= 64;
    }
    
    /**
     * Send JSON response (fallback for non-WordPress environments)
     * 
     * @param array $data Response data
     * @param int $status_code HTTP status code
     */
    private static function sendJsonResponse($data, $status_code = 200) {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}