<?php
/**
 * Canva API Integration for SMO Social
 * Provides design content import capabilities
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../../includes/wordpress-functions.php';

/**
 * Canva Integration Manager
 * Handles Canva API integration for importing design content
 */
class CanvaIntegration {
    
    private $api_key;
    private $base_url = 'https://api.canva.com/rest/v1';
    
    public function __construct() {
        $this->api_key = get_option('smo_canva_api_key', '');
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_canva_list_designs', array($this, 'ajax_list_designs'));
        add_action('wp_ajax_smo_canva_import_design', array($this, 'ajax_import_design'));
        add_action('wp_ajax_smo_canva_search_templates', array($this, 'ajax_search_templates'));
        add_action('wp_ajax_smo_canva_test_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Make authenticated API request to Canva
     */
    private function make_api_request($endpoint, $method = 'GET', $body = null) {
        if (empty($this->api_key)) {
            throw new \Exception('Canva API key not configured');
        }
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        );
        
        if ($body) {
            $args['body'] = json_encode($body);
        }
        
        $url = $this->base_url . $endpoint;
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            throw new \Exception('Canva API error: ' . $error_message);
        }
        
        if (empty($response_body)) {
            return array();
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * List user designs from Canva
     */
    public function list_designs($limit = 20, $offset = 0) {
        $params = array(
            'limit' => $limit,
            'offset' => $offset
        );
        
        $query_string = http_build_query($params);
        
        try {
            return $this->make_api_request('/designs?' . $query_string);
        } catch (\Exception $e) {
            error_log('SMO Social Canva list designs error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get design details
     */
    public function get_design($design_id) {
        try {
            return $this->make_api_request('/designs/' . $design_id);
        } catch (\Exception $e) {
            error_log('SMO Social Canva get design error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Export design as image/PDF
     */
    public function export_design($design_id, $format = 'png') {
        $body = array(
            'format' => $format,
            'pages' => 'all'
        );
        
        try {
            return $this->make_api_request('/designs/' . $design_id . '/export', 'POST', $body);
        } catch (\Exception $e) {
            error_log('SMO Social Canva export design error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get export job status
     */
    public function get_export_status($job_id) {
        try {
            return $this->make_api_request('/exports/' . $job_id);
        } catch (\Exception $e) {
            error_log('SMO Social Canva export status error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Download exported file
     */
    public function download_export($job_id) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key
        );
        
        $response = wp_remote_get($this->base_url . '/exports/' . $job_id . '/download', array(
            'headers' => $headers,
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('Download failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code >= 400) {
            throw new \Exception('Export download error: HTTP ' . $response_code);
        }
        
        $content = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        return array(
            'content' => $content,
            'content_type' => $content_type
        );
    }
    
    /**
     * Search Canva templates
     */
    public function search_templates($query, $limit = 20) {
        $params = array(
            'query' => $query,
            'limit' => $limit
        );
        
        $query_string = http_build_query($params);
        
        try {
            return $this->make_api_request('/templates/search?' . $query_string);
        } catch (\Exception $e) {
            error_log('SMO Social Canva search templates error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create design from template
     */
    public function create_design_from_template($template_id, $title = null) {
        $body = array(
            'template_id' => $template_id
        );
        
        if ($title) {
            $body['title'] = $title;
        }
        
        try {
            return $this->make_api_request('/designs', 'POST', $body);
        } catch (\Exception $e) {
            error_log('SMO Social Canva create design error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if connected to Canva
     */
    public function is_connected() {
        return !empty($this->api_key);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        try {
            $result = $this->make_api_request('/user');
            return array(
                'status' => 'success',
                'message' => 'Connected successfully',
                'user' => isset($result['user']) ? $result['user'] : null
            );
        } catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * AJAX: List Canva designs
     */
    public function ajax_list_designs() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Canva'));
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        try {
            $result = $this->list_designs($limit, $offset);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Import design from Canva
     */
    public function ajax_import_design() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Canva'));
        }
        
        $design_id = sanitize_text_field($_POST['design_id']);
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'png';
        
        try {
            // Start export job
            $export_result = $this->export_design($design_id, $format);
            
            if (!isset($export_result['job']['id'])) {
                throw new \Exception('Export job not started');
            }
            
            $job_id = $export_result['job']['id'];
            
            // Poll for completion (simplified - in production would use proper polling)
            $max_attempts = 10;
            $attempt = 0;
            
            while ($attempt < $max_attempts) {
                $status = $this->get_export_status($job_id);
                
                if ($status['job']['status'] === 'success') {
                    // Download the file
                    $file_data = $this->download_export($job_id);
                    
                    // Import to WordPress media library
                    $imported_content = $this->import_design_content($file_data, $design_id, $format);
                    
                    wp_send_json_success($imported_content);
                } elseif ($status['job']['status'] === 'error') {
                    throw new \Exception('Export failed: ' . ($status['job']['error']['message'] ?? 'Unknown error'));
                }
                
                $attempt++;
                sleep(2); // Wait 2 seconds before next check
            }
            
            throw new \Exception('Export timeout - please try again');
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Search Canva templates
     */
    public function ajax_search_templates() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Canva'));
        }
        
        $query = sanitize_text_field($_POST['query']);
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        
        try {
            $result = $this->search_templates($query, $limit);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Test Canva connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        $result = $this->test_connection();
        
        if ($result['status'] === 'success') {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Import design content to WordPress
     */
    private function import_design_content($file_data, $design_id, $format) {
        $content = $file_data['content'];
        $content_type = $file_data['content_type'];
        
        // Generate filename
        $extension = $this->get_extension_from_format($format);
        $filename = 'canva-design-' . $design_id . '.' . $extension;
        
        // Upload to WordPress media library
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        if (file_put_contents($file_path, $content)) {
            $attachment = array(
                'post_mime_type' => $content_type,
                'post_title' => 'Canva Design ' . $design_id,
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $file_path);
            
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                return array(
                    'status' => 'imported',
                    'type' => 'design',
                    'title' => 'Canva Design ' . $design_id,
                    'attachment_id' => $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'source' => 'canva',
                    'design_id' => $design_id,
                    'format' => $format
                );
            }
        }
        
        throw new \Exception('Failed to import design to media library');
    }
    
    /**
     * Get file extension from format
     */
    private function get_extension_from_format($format) {
        $extensions = array(
            'png' => 'png',
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'pdf' => 'pdf',
            'svg' => 'svg'
        );
        
        return isset($extensions[$format]) ? $extensions[$format] : 'png';
    }
}
