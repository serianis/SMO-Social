<?php
namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_Driver_Engine - The core engine that processes JSON drivers
 * 
 * This engine interprets platform-specific JSON configurations and executes
 * social media publishing operations. It's platform-agnostic and relies entirely
 * on JSON driver definitions.
 */
class DriverEngine {

    private $driver_schema;    // The JSON driver configuration
    private $user_config;      // User's API credentials
    private $post_id;          // WordPress post ID
    private $session_token;    // Platform session token
    private $validation_errors = array();
    private $warnings = array();

    /**
     * Constructor
     * 
     * @param array $driver_json   Decoded JSON driver configuration
     * @param array $user_config   User's saved credentials
     * @param int   $post_id       WordPress post ID to publish
     */
    public function __construct($driver_json, $user_config, $post_id) {
        $this->driver_schema = $driver_json;
        $this->user_config = $user_config;
        $this->post_id = $post_id;
    }

    /**
     * Main publishing function
     * 
     * @return array|WP_Error Results with success/error information
     */
    public function publish() {
        try {
            // Step 1: Pre-validation
            $validation_result = $this->pre_validate();
            if (!$validation_result['valid']) {
                return new \WP_Error('validation_failed', implode(', ', $validation_result['errors']));
            }

            // Step 2: Handle authentication
            $auth_result = $this->handle_authentication();
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }
            $this->session_token = $auth_result;

            // Step 3: Prepare and execute the API call
            $result = $this->execute_api_call();

            // Step 4: Process response
            return $this->process_response($result);

        } catch (Exception $e) {
            return new \WP_Error('execution_error', $e->getMessage());
        }
    }

    /**
     * Pre-validate the setup before API execution
     * 
     * @return array Validation results
     */
    private function pre_validate() {
        $errors = array();
        $valid = true;

        // Check if driver schema is valid
        if (!isset($this->driver_schema['api_interaction'])) {
            $errors[] = 'Driver schema is incomplete - missing api_interaction';
            $valid = false;
        }

        // Check required user config fields
        if (isset($this->driver_schema['config_ui'])) {
            foreach ($this->driver_schema['config_ui'] as $field) {
                if (isset($field['required']) && $field['required']) {
                    $config_key = $field['key'];
                    if (!isset($this->user_config[$config_key]) || empty($this->user_config[$config_key])) {
                        $errors[] = "Required configuration missing: {$config_key}";
                        $valid = false;
                    }
                }
            }
        }

        // Check post exists
        $post = get_post($this->post_id);
        if (!$post || $post->post_status !== 'publish') {
            $errors[] = 'Post does not exist or is not published';
            $valid = false;
        }

        return array('valid' => $valid, 'errors' => $errors);
    }

    /**
     * Handle authentication based on driver configuration
     * 
     * @return string|WP_Error Session token or error
     */
    private function handle_authentication() {
        $auth_config = $this->driver_schema['api_interaction'];
        $auth_method = $auth_config['auth_method'] ?? 'none';

        switch ($auth_method) {
            case 'api_key':
                return $this->handle_api_key_auth();

            case 'bearer_token':
                if (isset($auth_config['auth_endpoint'])) {
                    return $this->handle_bearer_token_auth($auth_config['auth_endpoint']);
                }
                // Fall through to none if no endpoint specified

            case 'oauth':
                return $this->handle_oauth_auth();

            case 'none':
            default:
                return ''; // No authentication needed
        }
    }

    /**
     * Handle simple API key authentication
     * 
     * @return string API key
     */
    private function handle_api_key_auth() {
        // API key should be in user config
        $api_key_field = null;
        foreach ($this->driver_schema['config_ui'] as $field) {
            if ($field['type'] === 'password' || strpos($field['key'], 'api_key') !== false) {
                $api_key_field = $field['key'];
                break;
            }
        }

        if ($api_key_field && isset($this->user_config[$api_key_field])) {
            return $this->user_config[$api_key_field];
        }

        return new \WP_Error('auth_failed', 'API key not configured');
    }

    /**
     * Handle bearer token authentication with session creation
     * 
     * @param array $auth_endpoint Auth configuration
     * @return string|WP_Error Session token
     */
    private function handle_bearer_token_auth($auth_endpoint) {
        // Check for cached token first
        $cached_token = get_transient('smo_token_' . $this->driver_schema['slug']);
        if ($cached_token) {
            return $cached_token;
        }

        // Prepare auth payload
        $payload = $this->hydrate_payload($auth_endpoint['payload']);

        // Make auth request
        $response = wp_remote_request($auth_endpoint['url'], array(
            'method' => $auth_endpoint['method'] ?? 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $token_path = $auth_endpoint['token_path'] ?? 'access_token';

        // Extract token from response using dot notation
        $token = $this->get_nested_value($body, $token_path);
        
        if (!$token) {
            return new \WP_Error('auth_failed', 'Failed to obtain session token');
        }

        // Cache token for 1 hour
        set_transient('smo_token_' . $this->driver_schema['slug'], $token, HOUR_IN_SECONDS);

        return $token;
    }

    /**
     * Handle OAuth authentication (placeholder for future implementation)
     * 
     * @return string|WP_Error OAuth token
     */
    private function handle_oauth_auth() {
        // OAuth implementation would go here
        // For now, return error to indicate not implemented
        return new \WP_Error('oauth_not_implemented', 'OAuth authentication not yet implemented');
    }

    /**
     * Execute the main API call using hydrated payload
     * 
     * @return array|WP_Error API response
     */
    private function execute_api_call() {
        $api_config = $this->driver_schema['api_interaction']['post_endpoint'];
        
        // Handle media upload if needed
        if ($this->needs_media_upload()) {
            $media_result = $this->handle_media_upload();
            if (is_wp_error($media_result)) {
                return $media_result;
            }
            
            // Inject media data into payload
            $this->inject_media_data($media_result);
        }

        // Prepare final payload
        $final_payload = $this->hydrate_payload($api_config['payload'] ?? array());

        // Prepare headers
        $headers = array();
        if (isset($api_config['headers'])) {
            $headers = $this->hydrate_payload($api_config['headers']);
        }

        // Set authorization header if we have a session token
        if ($this->session_token) {
            $auth_header = $this->driver_schema['api_interaction']['auth_header'] ?? 'Authorization';
            $headers[$auth_header] = str_replace('{{session_token}}', $this->session_token, $headers[$auth_header] ?? 'Bearer {{session_token}}');
        }

        // Execute API call
        $response = wp_remote_request($api_config['url'], array(
            'method' => $api_config['method'] ?? 'POST',
            'headers' => $headers,
            'body' => wp_json_encode($final_payload),
            'timeout' => 45,
            'blocking' => true
        ));

        return $response;
    }

    /**
     * Process API response and handle success/failure
     * 
     * @param array|WP_Error $response API response
     * @return array Results array
     */
    private function process_response($response) {
        if (is_wp_error($response)) {
            // Log the error
            $this->log_activity('error', $response->get_error_message());
            
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'code' => $response->get_error_code()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            // Success
            $this->log_activity('success', 'Post published successfully');
            
            // Extract post ID from response if available
            $post_id = $this->extract_post_id($response_data);
            
            return array(
                'success' => true,
                'post_id' => $post_id,
                'response' => $response_data,
                'status_code' => $status_code
            );
        } else {
            // Error response
            $error_message = $this->extract_error_message($response_data) ?: "HTTP {$status_code}";
            $this->log_activity('error', $error_message);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'code' => $status_code,
                'response' => $response_data
            );
        }
    }

    /**
     * Recursive function to hydrate payload with actual data
     * 
     * @param mixed $data    Data to hydrate (string, array, or object)
     * @param string $token  Session token (optional)
     * @return mixed Hydrated data
     */
    private function hydrate_payload($data, $token = '') {
        if (is_string($data)) {
            return $this->replace_variables($data, $token);
        }

        if (is_array($data)) {
            $new_data = array();
            foreach ($data as $key => $value) {
                // Handle conditional logic
                if ($key === 'if') {
                    $condition = $this->replace_variables($value, $token);
                    if (empty($condition) || $condition === 'false' || $condition === '0') {
                        return null; // Skip this entire branch
                    }
                    continue; // Don't include the 'if' key itself
                }

                $processed_value = $this->hydrate_payload($value, $token);
                
                // Skip null values from failed conditions
                if ($processed_value !== null) {
                    $new_data[$key] = $processed_value;
                }
            }
            return $new_data;
        }

        return $data;
    }

    /**
     * Replace variables in strings with actual data
     * 
     * @param string $string String with variables to replace
     * @param string $token  Session token
     * @return string String with variables replaced
     */
    private function replace_variables($string, $token = '') {
        $post = get_post($this->post_id);
        
        // Basic WordPress post variables
        $replacements = array(
            '{{post_title}}' => $post->post_title,
            '{{post_content}}' => $this->get_clean_content($post),
            '{{post_excerpt}}' => $post->post_excerpt,
            '{{post_url}}' => get_permalink($this->post_id),
            '{{post_date}}' => get_the_date('c', $post),
            '{{post_modified}}' => get_the_modified_date('c', $post),
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url(),
            '{{current_timestamp}}' => time(),
            '{{current_timestamp_iso}}' => date('c'),
            '{{session_token}}' => $token
        );

        // Add user config variables
        foreach ($this->user_config as $key => $value) {
            $replacements['{{config.' . $key . '}}'] = $value;
        }

        // Add computed variables
        $replacements['{{has_images}}'] = has_post_thumbnail($this->post_id) ? 'true' : 'false';
        $replacements['{{has_excerpt}}'] = !empty($post->post_excerpt) ? 'true' : 'false';
        $replacements['{{content_length}}'] = strlen(strip_tags($post->post_content));

        // Get hashtags from post tags
        $tags = get_the_tags($this->post_id);
        if ($tags) {
            $hashtags = array_map(function($tag) {
                return '#' . $tag->name;
            }, $tags);
            $replacements['{{post_hashtags}}'] = implode(' ', $hashtags);
        }

        // Replace all variables
        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }

    /**
     * Get clean content suitable for social media
     * 
     * @param WP_Post $post WordPress post object
     * @return string Cleaned content
     */
    private function get_clean_content($post) {
        $content = strip_shortcodes($post->post_content);
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content); // Normalize whitespace
        
        // Truncate to platform limit if specified
        $max_chars = $this->driver_schema['capabilities']['max_chars'] ?? 280;
        if (strlen($content) > $max_chars) {
            $content = mb_substr($content, 0, $max_chars - 3) . '...';
        }

        return trim($content);
    }

    /**
     * Check if media upload is required
     * 
     * @return bool Whether media upload is needed
     */
    private function needs_media_upload() {
        // Check if post has featured image and platform supports images
        if (!has_post_thumbnail($this->post_id)) {
            return false;
        }

        $capabilities = $this->driver_schema['capabilities'] ?? array();
        return isset($capabilities['image']) && $capabilities['image'] === true;
    }

    /**
     * Handle media upload to platform
     * 
     * @return array|WP_Error Upload result
     */
    private function handle_media_upload() {
        // This would handle media upload based on platform-specific requirements
        // Implementation depends on platform API requirements
        
        $featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($this->post_id), 'full');
        
        if (!$featured_image) {
            return new \WP_Error('no_featured_image', 'No featured image found');
        }

        // Platform-specific media upload logic would go here
        // For now, return mock result
        return array(
            'media_id' => 'mock_media_id_' . time(),
            'media_url' => $featured_image[0]
        );
    }

    /**
     * Inject media data into payload
     * 
     * @param array $media_result Media upload result
     */
    private function inject_media_data($media_result) {
        // This would modify the payload to include media data
        // Implementation depends on platform-specific requirements
        
        // Placeholder for media injection logic
    }

    /**
     * Extract post ID from API response
     * 
     * @param array $response_data API response data
     * @return string|null Post ID if found
     */
    private function extract_post_id($response_data) {
        // Try common field names
        $possible_fields = array('id', 'post_id', 'message_id', 'data.id');
        
        foreach ($possible_fields as $field) {
            $value = $this->get_nested_value($response_data, $field);
            if ($value) {
                return $value;
            }
        }
        
        return null;
    }

    /**
     * Extract error message from API response
     * 
     * @param array $response_data API response data
     * @return string Error message
     */
    private function extract_error_message($response_data) {
        // Try common error field names
        $possible_fields = array('error', 'message', 'error.message', 'errors.0.message');
        
        foreach ($possible_fields as $field) {
            $value = $this->get_nested_value($response_data, $field);
            if ($value) {
                return $value;
            }
        }
        
        return null;
    }

    /**
     * Get nested value from array using dot notation
     * 
     * @param array  $array   Array to search
     * @param string $path    Dot-separated path
     * @return mixed Value or null
     */
    private function get_nested_value($array, $path) {
        $keys = explode('.', $path);
        $value = $array;
        
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }

    /**
     * Log activity for debugging and user visibility
     * 
     * @param string $type    Log type (success, error, info)
     * @param string $message Log message
     */
    private function log_activity($type, $message) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'platform' => $this->driver_schema['slug'],
            'post_id' => $this->post_id,
            'type' => $type,
            'message' => $message
        );
        
        // Store in post meta for user visibility
        $logs = get_post_meta($this->post_id, '_smo_activity_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }
        
        $logs[] = $log_entry;
        
        // Keep only last 50 logs per post
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_post_meta($this->post_id, '_smo_activity_logs', $logs);
        
        // Also log to error log for debugging
        error_log("SMO Social [{$type}]: " . $message);
    }

    /**
     * Get validation warnings for user feedback
     * 
     * @return array Validation warnings
     */
    public function get_warnings() {
        $warnings = array();
        
        // Check content length
        $post = get_post($this->post_id);
        $max_chars = $this->driver_schema['capabilities']['max_chars'] ?? 280;
        $content_length = strlen(strip_tags($post->post_content));
        
        if ($content_length > $max_chars) {
            $warnings[] = "Content length ({$content_length}) exceeds platform limit ({$max_chars})";
        }
        
        // Check if media is optimal for platform
        if (has_post_thumbnail($this->post_id)) {
            $image = wp_get_attachment_metadata(get_post_thumbnail_id($this->post_id));
            if ($image && isset($image['width']) && isset($image['height'])) {
                $aspect_ratio = $image['width'] / $image['height'];
                $optimal_ratio = $this->driver_schema['capabilities']['best_image_ratio'] ?? '16:9';
                
                // Simple ratio check (this would be more sophisticated in real implementation)
                if (abs($aspect_ratio - 1.77) > 0.3) {
                    $warnings[] = "Image aspect ratio ({$aspect_ratio}) may not be optimal for this platform";
                }
            }
        }
        
        return $warnings;
    }
}