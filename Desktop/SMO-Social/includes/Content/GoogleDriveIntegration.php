<?php
/**
 * Google Drive API Integration for SMO Social
 * Provides OAuth2 authentication and file access capabilities
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';

/**
 * Google Drive Integration Manager
 * Handles Google Drive API authentication, file listing, and content import
 */
class GoogleDriveIntegration {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $access_token;
    private $refresh_token;
    
    public function __construct() {
        $this->client_id = get_option('smo_google_drive_client_id', '');
        $this->client_secret = get_option('smo_google_drive_client_secret', '');
        $this->redirect_uri = admin_url('admin.php?page=smo-social&google_drive_oauth=callback');
        
        // Load stored tokens
        $this->access_token = get_option('smo_google_drive_access_token', '');
        $this->refresh_token = get_option('smo_google_drive_refresh_token', '');
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_smo_google_drive_auth', array($this, 'ajax_google_drive_auth'));
        add_action('wp_ajax_smo_google_drive_list_files', array($this, 'ajax_list_files'));
        add_action('wp_ajax_smo_google_drive_import_file', array($this, 'ajax_import_file'));
        add_action('wp_ajax_smo_google_drive_refresh_token', array($this, 'ajax_refresh_token'));
    }
    
    /**
     * Get OAuth2 authorization URL
     */
    public function get_auth_url() {
        $scopes = array(
            'https://www.googleapis.com/auth/drive.readonly',
            'https://www.googleapis.com/auth/drive.file'
        );
        
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => implode(' ', $scopes),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        );
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        if (!isset($_GET['google_drive_oauth']) || $_GET['google_drive_oauth'] !== 'callback') {
            return;
        }
        
        if (!isset($_GET['code'])) {
            wp_die(__('Authorization code not received', 'smo-social'));
        }
        
        $code = sanitize_text_field($_GET['code']);
        
        try {
            $tokens = $this->exchange_code_for_tokens($code);
            
            // Store tokens
            update_option('smo_google_drive_access_token', $tokens['access_token']);
            update_option('smo_google_drive_refresh_token', $tokens['refresh_token']);
            update_option('smo_google_drive_token_expires', time() + $tokens['expires_in']);
            
            // Redirect back to settings
            wp_redirect(admin_url('admin.php?page=smo-social&google_drive=connected'));
            exit;
            
        } catch (\Exception $e) {
            error_log('SMO Social Google Drive OAuth Error: ' . $e->getMessage());
            wp_die(__('Google Drive authentication failed', 'smo-social'));
        }
    }
    
    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code_for_tokens($code) {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
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
            throw new \Exception('Invalid response from Google OAuth server');
        }
        
        return $data;
    }
    
    /**
     * Refresh access token
     */
    private function refresh_access_token() {
        if (!$this->refresh_token) {
            throw new \Exception('No refresh token available');
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('Token refresh failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['access_token'])) {
            throw new \Exception('Invalid response from Google OAuth server');
        }
        
        // Update stored token
        update_option('smo_google_drive_access_token', $data['access_token']);
        update_option('smo_google_drive_token_expires', time() + $data['expires_in']);
        
        $this->access_token = $data['access_token'];
        
        return $data;
    }
    
    /**
     * Ensure valid access token
     */
    private function ensure_valid_token() {
        $expires = get_option('smo_google_drive_token_expires', 0);
        
        // Refresh token if expired or about to expire (within 5 minutes)
        if (time() >= ($expires - 300)) {
            $this->refresh_access_token();
        }
    }
    
    /**
     * Make authenticated API request to Google Drive
     */
    private function make_api_request($endpoint, $method = 'GET', $body = null) {
        $this->ensure_valid_token();
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers
        );
        
        if ($body) {
            $args['body'] = json_encode($body);
        }
        
        $response = wp_remote_request('https://www.googleapis.com/drive/v3/' . $endpoint, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            throw new \Exception('Google Drive API error: ' . $error_message);
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * List files in Google Drive
     */
    public function list_files($folder_id = null, $page_token = null) {
        $query = "trashed = false";
        if ($folder_id) {
            $query .= " and '" . $folder_id . "' in parents";
        }
        
        $params = array(
            'q' => $query,
            'fields' => 'nextPageToken, files(id, name, mimeType, modifiedTime, size, webViewLink)',
            'pageSize' => 50
        );
        
        if ($page_token) {
            $params['pageToken'] = $page_token;
        }
        
        $endpoint = 'files?' . http_build_query($params);
        
        try {
            return $this->make_api_request($endpoint);
        } catch (\Exception $e) {
            error_log('SMO Social Google Drive list files error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get file content
     */
    public function get_file_content($file_id) {
        // First get file metadata
        $metadata = $this->make_api_request('files/' . $file_id . '?fields=id,name,mimeType,size');
        
        // Download file content
        $download_url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token
        );
        
        $response = wp_remote_get($download_url, array('headers' => $headers));
        
        if (is_wp_error($response)) {
            throw new \Exception('File download failed: ' . $response->get_error_message());
        }
        
        $content = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        return array(
            'metadata' => $metadata,
            'content' => $content,
            'content_type' => $content_type
        );
    }
    
    /**
     * Search files by query
     */
    public function search_files($query, $folder_id = null) {
        $search_query = "fullText contains '" . $this->escape_query($query) . "' and trashed = false";
        if ($folder_id) {
            $search_query .= " and '" . $folder_id . "' in parents";
        }
        
        $params = array(
            'q' => $search_query,
            'fields' => 'files(id, name, mimeType, modifiedTime, size, webViewLink)',
            'pageSize' => 20
        );
        
        $endpoint = 'files?' . http_build_query($params);
        
        try {
            return $this->make_api_request($endpoint);
        } catch (\Exception $e) {
            error_log('SMO Social Google Drive search error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Escape search query
     */
    private function escape_query($query) {
        return str_replace(array("'", '"', '\\'), array("\\'", '\\"', '\\\\'), $query);
    }
    
    /**
     * Check if connected to Google Drive
     */
    public function is_connected() {
        return !empty($this->access_token) && !empty($this->refresh_token);
    }
    
    /**
     * Disconnect from Google Drive
     */
    public function disconnect() {
        delete_option('smo_google_drive_access_token');
        delete_option('smo_google_drive_refresh_token');
        delete_option('smo_google_drive_token_expires');
        
        $this->access_token = '';
        $this->refresh_token = '';
    }
    
    /**
     * AJAX: Start Google Drive OAuth
     */
    public function ajax_google_drive_auth() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (empty($this->client_id) || empty($this->client_secret)) {
            wp_send_json_error(__('Google Drive API credentials not configured'));
        }
        
        $auth_url = $this->get_auth_url();
        wp_send_json_success(array('auth_url' => $auth_url));
    }
    
    /**
     * AJAX: List Google Drive files
     */
    public function ajax_list_files() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Google Drive'));
        }
        
        $folder_id = isset($_POST['folder_id']) ? sanitize_text_field($_POST['folder_id']) : null;
        $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : null;
        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : null;
        
        try {
            if ($search_query) {
                $result = $this->search_files($search_query, $folder_id);
            } else {
                $result = $this->list_files($folder_id, $page_token);
            }
            
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Import file from Google Drive
     */
    public function ajax_import_file() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        if (!$this->is_connected()) {
            wp_send_json_error(__('Not connected to Google Drive'));
        }
        
        if (!isset($_POST['file_id'])) {
            wp_send_json_error(__('File ID not provided'));
        }
        
        $file_id = sanitize_text_field($_POST['file_id']);
        
        if (empty($file_id)) {
            wp_send_json_error(__('Invalid file ID provided'));
        }
        
        try {
            $file_data = $this->get_file_content($file_id);
            
            // Process file based on mime type
            $imported_content = $this->process_file_content($file_data);
            
            wp_send_json_success($imported_content);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Refresh access token
     */
    public function ajax_refresh_token() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        try {
            $this->refresh_access_token();
            wp_send_json_success(__('Token refreshed successfully'));
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
        $mime_type = $metadata['mimeType'];
        
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
     * Import text content
     */
    private function import_text_content($filename, $content) {
        return array(
            'status' => 'imported',
            'type' => 'text',
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'content' => $content,
            'source' => 'google_drive',
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
            'source' => 'google_drive',
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
            'source' => 'google_drive',
            'filename' => $filename
        );
    }
    
    /**
     * Import image content
     */
    private function import_image_content($filename, $content, $mime_type) {
        // Upload image to WordPress media library
        $upload_dir = wp_upload_dir();
        $image_data = base64_decode(base64_encode($content)); // Ensure proper encoding
        
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
                    'source' => 'google_drive',
                    'filename' => $filename
                );
            }
        }
        
        throw new \Exception('Failed to import image to media library');
    }
}
