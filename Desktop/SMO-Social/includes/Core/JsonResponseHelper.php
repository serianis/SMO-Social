<?php
/**
 * Centralized JSON response helper
 * 
 * Eliminates 200+ instances of duplicated wp_send_json_success/wp_send_json_error calls
 * Provides consistent JSON response formatting, error handling, and status codes
 * 
 * @package SMO_Social
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class JsonResponseHelper {
    
    /**
     * Default success status code
     */
    const DEFAULT_SUCCESS_CODE = 200;
    
    /**
     * Default error status code
     */
    const DEFAULT_ERROR_CODE = 400;
    
    /**
     * Send success response with data
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $status_code HTTP status code
     * @param array $meta Additional metadata
     */
    public static function success($data = null, $message = 'Success', $status_code = self::DEFAULT_SUCCESS_CODE, $meta = []) {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        self::sendResponse($response, $status_code);
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @param mixed $data Additional error data
     * @param string $error_code Application-specific error code
     */
    public static function error($message = 'An error occurred', $status_code = self::DEFAULT_ERROR_CODE, $data = null, $error_code = null) {
        $response = [
            'success' => false,
            'data' => $data,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ];
        
        if ($error_code) {
            $response['error_code'] = $error_code;
        }
        
        self::sendResponse($response, $status_code);
    }
    
    /**
     * Send validation error response
     * 
     * @param array $errors Validation errors (field => error_message)
     * @param string $message General validation message
     * @param int $status_code HTTP status code (defaults to 422)
     */
    public static function validationError($errors, $message = 'Validation failed', $status_code = 422) {
        $response = [
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => current_time('mysql')
        ];
        
        self::sendResponse($response, $status_code);
    }
    
    /**
     * Send unauthorized response
     * 
     * @param string $message Error message
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     * 
     * @param string $message Error message
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }
    
    /**
     * Send not found response
     * 
     * @param string $message Error message
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send server error response
     * 
     * @param string $message Error message
     * @param mixed $data Additional error data
     */
    public static function serverError($message = 'Internal server error', $data = null) {
        self::error($message, 500, $data);
    }
    
    /**
     * Send created response (201)
     * 
     * @param mixed $data Created resource data
     * @param string $message Success message
     * @param array $meta Additional metadata
     */
    public static function created($data = null, $message = 'Resource created successfully', $meta = []) {
        self::success($data, $message, 201, $meta);
    }
    
    /**
     * Send no content response (204)
     */
    public static function noContent() {
        if (function_exists('wp_send_json_success')) {
            wp_send_json_success(null, 204);
        } else {
            http_response_code(204);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'No Content']);
            exit;
        }
    }
    
    /**
     * Send paginated response
     * 
     * @param array $items Items for current page
     * @param int $total_items Total number of items
     * @param int $current_page Current page number
     * @param int $per_page Items per page
     * @param string $message Success message
     */
    public static function paginated($items, $total_items, $current_page, $per_page, $message = 'Data retrieved successfully') {
        $total_pages = ceil($total_items / $per_page);
        $has_next = $current_page < $total_pages;
        $has_previous = $current_page > 1;
        
        $meta = [
            'pagination' => [
                'current_page' => (int) $current_page,
                'per_page' => (int) $per_page,
                'total_items' => (int) $total_items,
                'total_pages' => (int) $total_pages,
                'has_next' => $has_next,
                'has_previous' => $has_previous,
                'next_page' => $has_next ? $current_page + 1 : null,
                'previous_page' => $has_previous ? $current_page - 1 : null
            ]
        ];
        
        self::success($items, $message, 200, $meta);
    }
    
    /**
     * Send collection response with metadata
     * 
     * @param array $items Collection items
     * @param array $meta Additional metadata
     * @param string $message Success message
     */
    public static function collection($items, $meta = [], $message = 'Collection retrieved successfully') {
        $response_meta = array_merge($meta, [
            'count' => count($items),
            'type' => 'collection'
        ]);
        
        self::success($items, $message, 200, $response_meta);
    }
    
    /**
     * Send single resource response
     * 
     * @param mixed $item Single resource
     * @param string $message Success message
     */
    public static function item($item, $message = 'Resource retrieved successfully') {
        self::success($item, $message, 200, ['type' => 'item']);
    }
    
    /**
     * Send bulk operation response
     * 
     * @param int $success_count Number of successful operations
     * @param int $failure_count Number of failed operations
     * @param array $errors Array of error messages (optional)
     * @param string $message Success message
     */
    public static function bulkOperation($success_count, $failure_count, $errors = [], $message = 'Bulk operation completed') {
        $data = [
            'success_count' => $success_count,
            'failure_count' => $failure_count,
            'total_operations' => $success_count + $failure_count
        ];
        
        if (!empty($errors)) {
            $data['errors'] = $errors;
        }
        
        $meta = [
            'type' => 'bulk_operation',
            'success_rate' => $success_count + $failure_count > 0 
                ? round(($success_count / ($success_count + $failure_count)) * 100, 2) 
                : 0
        ];
        
        self::success($data, $message, 200, $meta);
    }
    
    /**
     * Send file download response
     * 
     * @param string $file_path Path to file
     * @param string $filename Download filename
     * @param string $mime_type MIME type
     */
    public static function download($file_path, $filename = null, $mime_type = 'application/octet-stream') {
        if (!file_exists($file_path)) {
            self::notFound('File not found');
            return;
        }
        
        $filename = $filename ?: basename($file_path);
        
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        readfile($file_path);
        exit;
    }
    
    /**
     * Send JSONP response
     * 
     * @param mixed $data Response data
     * @param string $callback Callback function name
     * @param string $message Success message
     */
    public static function jsonp($data, $callback = 'callback', $message = 'Success') {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ];
        
        header('Content-Type: application/javascript');
        echo $callback . '(' . json_encode($response) . ')';
        exit;
    }
    
    /**
     * Send custom response with specific structure
     * 
     * @param array $response Response array
     * @param int $status_code HTTP status code
     */
    public static function custom($response, $status_code = 200) {
        // Ensure required fields
        if (!isset($response['success'])) {
            $response['success'] = $status_code < 400;
        }
        
        if (!isset($response['timestamp'])) {
            $response['timestamp'] = current_time('mysql');
        }
        
        self::sendResponse($response, $status_code);
    }
    
    /**
     * Send response based on operation result
     * 
     * @param mixed $result Operation result
     * @param string $success_message Success message
     * @param string $error_message Error message
     * @param callable $data_transformer Optional data transformation function
     */
    public static function result($result, $success_message = 'Operation completed successfully', $error_message = 'Operation failed', $data_transformer = null) {
        if ($result) {
            $data = $data_transformer ? $data_transformer($result) : $result;
            self::success($data, $success_message);
        } else {
            self::error($error_message);
        }
    }
    
    /**
     * Send rate limit response
     * 
     * @param int $retry_after Seconds to wait before retry
     * @param string $message Error message
     */
    public static function rateLimited($retry_after = 60, $message = 'Rate limit exceeded') {
        header('Retry-After: ' . $retry_after);
        self::error($message, 429);
    }
    
    /**
     * Get standard HTTP status messages
     * 
     * @param int $status_code HTTP status code
     * @return string
     */
    public static function getStatusMessage($status_code) {
        $messages = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error'
        ];
        
        return $messages[$status_code] ?? 'Unknown Status';
    }
    
    /**
     * Check if request wants JSON response
     * 
     * @return bool
     */
    public static function isJsonRequested() {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        
        return strpos($accept, 'application/json') !== false 
            || strpos($content_type, 'application/json') !== false
            || (isset($_REQUEST['format']) && $_REQUEST['format'] === 'json');
    }
    
    /**
     * Send response with proper headers and exit
     * 
     * @param array $response Response data
     * @param int $status_code HTTP status code
     */
    private static function sendResponse($response, $status_code = 200) {
        // Use WordPress function if available
        if (function_exists('wp_send_json_success') && function_exists('wp_send_json_error')) {
            if ($response['success']) {
                wp_send_json_success($response['data'], $status_code);
            } else {
                wp_send_json_error($response['data'] ?: $response['message'], $status_code);
            }
        } else {
            // Fallback for non-WordPress environments
            http_response_code($status_code);
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                exit;
            }
            
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    /**
     * Log response for debugging
     * 
     * @param array $response Response that was sent
     * @param int $status_code Status code used
     */
    private static function logResponse($response, $status_code) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_entry = sprintf(
                '[JSON Response] Status: %d | Success: %s | Message: %s',
                $status_code,
                $response['success'] ? 'true' : 'false',
                $response['message']
            );
            
            if (function_exists('error_log')) {
                error_log($log_entry);
            }
        }
    }
}