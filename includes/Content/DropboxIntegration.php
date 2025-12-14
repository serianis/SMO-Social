<?php
/**
 * Dropbox API Integration for SMO Social
 * Provides OAuth2 authentication and file access capabilities
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../../includes/wordpress-functions.php';

/**
 * Dropbox Integration Manager
 * Handles Dropbox API authentication, file listing, and content import
 */
class DropboxIntegration {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $access_token;
    
    public function __construct() {
        $this->client_id = get_option('smo_dropbox_client_id', '');
        $this->client_secret = get_option('smo_dropbox_client_secret', '');
        $this->redirect_uri = admin_url('admin.php?page=smo-social&dropbox_oauth=callback');
        
        // Load stored token
        $this->access_token = get_option('smo_dropbox_access_token', '');
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_smo_dropbox_auth', array($this, 'ajax_dropbox_auth'));
        add_action('wp_ajax_smo_dropbox_list_files', array($this, 'ajax_list_files'));
        add_action('wp_ajax_smo_dropbox_import_file', array($this, 'ajax_import_file'));
        add_action('wp_ajax_smo_dropbox_create_folder', array($this, 'ajax_create_folder'));
    }
    
    /**
     * Get OAuth2 authorization URL
     */
    public function get_auth_url() {
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'token_access_type' => 'offline'
        );
        
        return 'https://www.dropbox.com/oauth2/authorize?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        if (!isset($_GET['dropbox_oauth']) || $_GET['dropbox_oauth'] !== 'callback') {
            return;
        }
        
        if (!isset($_GET['code'])) {
            wp_die(__('Authorization code not received', 'smo-social'));
        }
        
        $code = sanitize_text_field($_GET['code']);
        
        try {
            $tokens = $this->exchange_code_for_tokens($code);
            
            // Store token
            update_option('smo_dropbox_access_token', $tokens['access_token']);
            
            // Redirect back to settings
            wp_redirect(admin_url('admin.php?page=smo-social&dropbox=connected'));
            exit;
            
        } catch (\Exception $e) {
            error_log('SMO Social Dropbox OAuth Error: ' . $e->getMessage());
            wp_die(__('Dropbox authentication failed', 'smo-social'));
        }
    }
    
    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code_for_tokens($code) {
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', array(
            'body' => array(
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('Token exchange failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['access_token'])) {
            throw new \Exception('Invalid response from Dropbox OAuth server');
        }
        
        return $data;
    }
    
    /**
     * Make authenticated API request to Dropbox
     */
    private function make_api_request($endpoint, $method = 'POST', $body = null, $content_type = 'application/json') {
        if (!$this->access_token) {
            throw new \Exception('Dropbox access token not available');
        }
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => $content_type
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers
        );
        
        if ($body) {
            if ($content_type === 'application/json') {
                $args['body'] = json_encode($body);
            } else {
                $args['body'] = $body;
            }
        }
        
        $response = wp_remote_post('https://api.dropboxapi.com/2/' . $endpoint, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['error_description']) ? $error_data['error']['error_description'] : 'Unknown API error';
            throw new \Exception('Dropbox API error: ' . $error_message);
        }
        
        if ($response_code === 204) {
            return array(); // No content response
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * List files in Dropbox folder
     */
    public function list_files($path = '') {
        $body = array(
            'path' => $path,
            'recursive' => false,
            'include_media_info' => false,
            'include_deleted' => false,
            'include_has_explicit_shared_members' => false
        );
        
        try {
            return $this->make_api_request('files/list_folder', 'POST', $body);
        } catch (\Exception $e) {
            error_log('SMO Social Dropbox list files error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Download file from Dropbox
     */
    public function download_file($path) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Dropbox-API-Arg' => json_encode(array('path' => $path))
        );
        
        $response = wp_remote_post('https://content.dropboxapi.com/2/files/download', array(
            'headers' => $headers
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('File download failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code >= 400) {
            throw new \Exception('Dropbox download error: HTTP ' . $response_code);
        }
        
        $content = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        // Get file metadata from response headers
        $metadata_header = wp_remote_retrieve_header($response, 'dropbox-api-result');
        $metadata = json_decode($metadata_header, true);
        
        return array(
            'metadata' => $metadata,
            'content' => $content,
            'content_type' => $content_type
        );
    }
    
    /**
     * Search files in Dropbox
     */
    public function search_files($query, $path = '') {
        $body = array(
            'query' => $query,
            'options' => array(
                'path' => $path,
                'max_results' => 20,
                'file_status' => 'active',
                'filename_only' => false
            )
        );
        
        try {
            return $this->make_api_request('files/search_v2', 'POST', $body);
        } catch (\Exception $e) {
            error_log('SMO Social Dropbox search error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get account information
     */
    public function get_account_info() {
        try {
            return $this->make_api_request('users/get_current_account', 'POST', array());
        } catch (\Exception $e) {
            error_log('SMO Social Dropbox account info error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if connected to Dropbox
     */
    public function is_connected() {
        return !empty($this->access_token);
    }
    
    /**
     * Disconnect from Dropbox
     */
    public function disconnect() {
        delete_option('smo_dropbox_access_token');
        $this->access_token = '';
    }
    
    /**
     * AJAX: Start Dropbox OAuth
     */
    public function ajax_dropbox_auth() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (empty($this->client_id) || empty($this->client_secret)) {
            wp_send_json_error(__('Dropbox API credentials not configured'));
        }
        
        $auth_url = $this->get_auth_url();
        wp_send_json_success(array('auth_url' => $auth_url));
    }
    
    /**
     * AJAX: List Dropbox files
     */
    public function ajax_list_files() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Dropbox'));
        }
        
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : null;
        
        try {
            if ($search) {
                $result = $this->search_files($search, $path);
            } else {
                $result = $this->list_files($path);
            }
            
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Import file from Dropbox
     */
    public function ajax_import_file() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Dropbox'));
        }
        
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        
        if (empty($path)) {
            wp_send_json_error(__('File path not provided'));
        }
        
        try {
            $file_data = $this->download_file($path);
            
            // Process file based on type
            $imported_content = $this->process_file_content($file_data);
            
            wp_send_json_success($imported_content);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Create folder in Dropbox
     */
    public function ajax_create_folder() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Dropbox'));
        }
        
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        
        if (empty($path)) {
            wp_send_json_error(__('Folder path not provided'));
        }
        
        try {
            $result = $this->make_api_request('files/create_folder_v2', 'POST', array(
                'path' => $path,
                'autorename' => false
            ));
            
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Process file content based on type
     */
    private function process_file_content($file_data) {
        $metadata = $file_data['metadata'];
        $content = $file_data['content'];
        $mime_type = $this->get_mime_type_from_extension($metadata['name']);
        
        // Handle different file types
        switch ($mime_type) {
            case 'text/plain':
                return $this->import_text_content($metadata['name'], $content);
                
            case 'application/json':
                return $this->import_json_content($metadata['name'], $content);
                
            case 'text/csv':
                return $this->import_csv_content($metadata['name'], $content);
                
            case 'image/jpeg':
            case 'image/png':
            case 'image/gif':
            case 'image/webp':
                return $this->import_image_content($metadata['name'], $content, $mime_type);
                
            default:
                return array(
                    'status' => 'unsupported',
                    'message' => 'File type not supported: ' . $mime_type,
                    'metadata' => $metadata
                );
        }
    }
    
    /**
     * Get MIME type from file extension
     */
    private function get_mime_type_from_extension($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mime_types = array(
            'txt' => 'text/plain',
            'json' => 'application/json',
            'csv' => 'text/csv',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        
        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }
    
    /**
     * Import text content
     */
    private function import_text_content($filename, $content) {
        return array(
            'status' => 'imported',
            'type' => 'text',
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'content' => $content,
            'source' => 'dropbox',
            'filename' => $filename
        );
    }
    
    /**
     * Import JSON content
     */
    private function import_json_content($filename, $content) {
        $json_data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON content');
        }
        
        return array(
            'status' => 'imported',
            'type' => 'json',
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'content' => $content,
            'parsed_data' => $json_data,
            'source' => 'dropbox',
            'filename' => $filename
        );
    }
    
    /**
     * Import CSV content
     */
    private function import_csv_content($filename, $content) {
        $lines = explode("\n", $content);
        $csv_data = array();
        
        if (count($lines) > 0) {
            $headers = str_getcsv($lines[0]);
            
            for ($i = 1; $i < count($lines); $i++) {
                if (!empty(trim($lines[$i]))) {
                    $csv_data[] = array_combine($headers, str_getcsv($lines[$i]));
                }
            }
        }
        
        return array(
            'status' => 'imported',
            'type' => 'csv',
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'content' => $content,
            'parsed_data' => $csv_data,
            'headers' => isset($headers) ? $headers : array(),
            'source' => 'dropbox',
            'filename' => $filename
        );
    }
    
    /**
     * Import image content
     */
    private function import_image_content($filename, $content, $mime_type) {
        // Upload image to WordPress media library
        $upload_dir = wp_upload_dir();
        $image_data = $content;
        
        $filename = sanitize_file_name($filename);
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        if (file_put_contents($file_path, $image_data)) {
            $attachment = array(
                'post_mime_type' => $mime_type,
                'post_title' => pathinfo($filename, PATHINFO_FILENAME),
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
                    'type' => 'image',
                    'title' => pathinfo($filename, PATHINFO_FILENAME),
                    'attachment_id' => $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'source' => 'dropbox',
                    'filename' => $filename
                );
            }
        }
        
        throw new \Exception('Failed to import image to media library');
    }
}
