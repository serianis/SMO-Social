<?php
/**
 * Content Import Manager
 * Handles importing content from various sources (Google Drive, Dropbox, Canva, RSS feeds, etc.)
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';
require_once __DIR__ . '/GoogleDriveIntegration.php';
require_once __DIR__ . '/DropboxIntegration.php';
require_once __DIR__ . '/CanvaIntegration.php';

/**
 * Content Import Manager
 * 
 * Manages content import from multiple sources:
 * - Google Drive integration
 * - Dropbox integration  
 * - Canva integration
 * - RSS feed parsing
 * - File uploads
 * - URL content extraction
 */
class ContentImportManager {
    
    public $last_error = '';
    private $current_source_id = null;
    private $table_names;
    private $automation_manager = null;
    private $content_stream;
    private $batch_size;
    private $max_memory_usage;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'content_sources' => $wpdb->prefix . 'smo_content_sources',
            'imported_content' => $wpdb->prefix . 'smo_imported_content',
            'content_ideas' => $wpdb->prefix . 'smo_content_ideas',
            'automation_rules' => $wpdb->prefix . 'smo_import_automation_rules',
            'auto_share_config' => $wpdb->prefix . 'smo_import_auto_share_config',
            'transformation_templates' => $wpdb->prefix . 'smo_content_transformation_templates',
            'automation_logs' => $wpdb->prefix . 'smo_import_automation_logs'
        );
        $this->init_hooks();
        $this->init_automation_manager();
        $this->create_tables();

        // Initialize content stream with default configuration
        $this->batch_size = 500;
        $this->max_memory_usage = 40; // MB
        $this->content_stream = new ContentImportStream($this->batch_size, $this->max_memory_usage);
    }
    
    /**
     * Initialize WordPress hooks
     */
    /**
     * Set batch processing configuration
     */
    public function set_batch_config($batch_size, $max_memory_usage) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage;
        $this->content_stream->set_batch_config($batch_size, $max_memory_usage);
    }

    /**
     * Get current batch processing configuration
     */
    public function get_batch_config() {
        return $this->content_stream->get_batch_config();
    }

    private function init_hooks() {
        add_action('wp_ajax_smo_add_content_source', array($this, 'ajax_add_content_source'));
        add_action('wp_ajax_smo_sync_content_source', array($this, 'ajax_sync_content_source'));
        add_action('wp_ajax_smo_import_content_item', array($this, 'ajax_import_content_item'));
        add_action('wp_ajax_smo_get_content_sources', array($this, 'ajax_get_content_sources'));
        add_action('wp_ajax_smo_delete_content_source', array($this, 'ajax_delete_content_source'));
        add_action('wp_ajax_smo_process_rss_feed', array($this, 'ajax_process_rss_feed'));
        
        // Automation hooks
        add_action('wp_ajax_smo_create_automation_rule', array($this, 'ajax_create_automation_rule'));
        add_action('wp_ajax_smo_get_automation_rules', array($this, 'ajax_get_automation_rules'));
        add_action('wp_ajax_smo_update_automation_rule', array($this, 'ajax_update_automation_rule'));
        add_action('wp_ajax_smo_delete_automation_rule', array($this, 'ajax_delete_automation_rule'));
        add_action('wp_ajax_smo_execute_automation_rule', array($this, 'ajax_execute_automation_rule'));
        add_action('wp_ajax_smo_get_auto_share_config', array($this, 'ajax_get_auto_share_config'));
        add_action('wp_ajax_smo_update_auto_share_config', array($this, 'ajax_update_auto_share_config'));
        
        // Content transformation hooks
        add_action('wp_ajax_smo_create_transformation_template', array($this, 'ajax_create_transformation_template'));
        add_action('wp_ajax_smo_get_transformation_templates', array($this, 'ajax_get_transformation_templates'));
        add_action('wp_ajax_smo_apply_transformation', array($this, 'ajax_apply_transformation'));
    }
    
    /**
     * Initialize automation manager
     */
    private function init_automation_manager() {
        // Load automation manager when needed
        if (class_exists('\\SMO_Social\\Automation\\ImportAutomationManager')) {
            $this->automation_manager = new \SMO_Social\Automation\ImportAutomationManager();
        }
    }
    
    /**
     * Add a new content source
     */
    public function add_content_source($name, $type, $config) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_names['content_sources'],
            array(
                'name' => $name,
                'type' => $type,
                'config' => json_encode($config),
                'status' => 'active',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            $this->last_error = $wpdb->last_error;
            error_log('SMO Social: Failed to add content source - ' . $wpdb->last_error);
            throw new \Exception('Failed to add content source: ' . $wpdb->last_error);
        }
        
        error_log('SMO Social: Content source added successfully with ID ' . $wpdb->insert_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get all content sources
     */
    public function get_content_sources() {
        global $wpdb;
        
        $sources = $wpdb->get_results(
            "SELECT * FROM {$this->table_names['content_sources']} ORDER BY created_at DESC",
            ARRAY_A
        );
        
        // Decode config JSON for each source
        foreach ($sources as &$source) {
            $source['config'] = json_decode($source['config'], true);
        }
        
        return $sources;
    }
    
    /**
     * Sync content from a source
     */
    public function sync_content_source($source_id) {
        global $wpdb;

        // Set current source ID for use in processing methods
        $this->current_source_id = $source_id;

        // Get source details
        $source = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['content_sources']} WHERE id = %d",
            $source_id
        ), ARRAY_A);

        if (!$source) {
            throw new \Exception('Content source not found');
        }

        $config = json_decode($source['config'], true);
        $imported_count = 0;

        // Use streaming approach for large content imports
        try {
            switch ($source['type']) {
                case 'rss':
                    $imported_count = $this->stream_rss_feed($config);
                    break;
                case 'url':
                    $imported_count = $this->stream_url_content($config);
                    break;
                case 'upload':
                    $imported_count = $this->process_uploads($config);
                    break;
                case 'google_drive':
                    $imported_count = $this->stream_google_drive($config);
                    break;
                case 'dropbox':
                    $imported_count = $this->stream_dropbox($config);
                    break;
                case 'canva':
                    $imported_count = $this->stream_canva($config);
                    break;
            }
        } catch (\Exception $e) {
            error_log('SMO Content Import: Streaming failed, falling back to original method - ' . $e->getMessage());

            // Fallback to original methods
            switch ($source['type']) {
                case 'rss':
                    $imported_count = $this->process_rss_feed($config);
                    break;
                case 'url':
                    $imported_count = $this->process_url_content($config);
                    break;
                case 'upload':
                    $imported_count = $this->process_uploads($config);
                    break;
                case 'google_drive':
                    $imported_count = $this->process_google_drive($config);
                    break;
                case 'dropbox':
                    $imported_count = $this->process_dropbox($config);
                    break;
                case 'canva':
                    $imported_count = $this->process_canva($config);
                    break;
            }
        }

        // Update last sync time
        $wpdb->update(
            $this->table_names['content_sources'],
            array('last_sync' => current_time('mysql')),
            array('id' => $source_id),
            array('%s'),
            array('%d')
        );

        // Reset current source ID
        $this->current_source_id = null;

        return $imported_count;
    }
    
    /**
     * Process RSS feed content
     */
    private function process_rss_feed($config) {
        if (empty($config['url'])) {
            return 0;
        }
        
        // Simple RSS parsing (in production, use proper RSS parser like SimplePie)
        $rss_content = wp_remote_get($config['url']);
        
        if (is_wp_error($rss_content)) {
            error_log('SMO Social: RSS feed error - ' . $rss_content->get_error_message());
            return 0;
        }
        
        $body = wp_remote_retrieve_body($rss_content);
        
        // Basic RSS parsing (simplified)
        preg_match_all('/<item[^>]*>(.*?)<\/item>/s', $body, $items);
        
        $imported_count = 0;
        foreach ($items[1] as $item) {
            // Extract title
            preg_match('/<title[^>]*><!\[CDATA\[(.*?)\]\]><\/title>|<title[^>]*>(.*?)<\/title>/s', $item, $title_match);
            $title = !empty($title_match[1]) ? $title_match[1] : (!empty($title_match[2]) ? $title_match[2] : 'Untitled');
            
            // Extract description/content
            preg_match('/<description[^>]*><!\[CDATA\[(.*?)\]\]><\/description>|<description[^>]*>(.*?)<\/description>/s', $item, $desc_match);
            $content = !empty($desc_match[1]) ? $desc_match[1] : (!empty($desc_match[2]) ? $desc_match[2] : '');
            
            // Extract link
            preg_match('/<link[^>]*>(.*?)<\/link>/s', $item, $link_match);
            $link = !empty($link_match[1]) ? trim($link_match[1]) : '';
            
            if (!empty($title) || !empty($content)) {
                $this->save_imported_content($this->get_current_source_id(), $title, $content, $link);
                $imported_count++;
            }
        }
        
        return $imported_count;
    }
    
    /**
     * Process URL content extraction
     */
    private function process_url_content($config) {
        if (empty($config['urls']) || !is_array($config['urls'])) {
            return 0;
        }
        
        $imported_count = 0;
        foreach ($config['urls'] as $url) {
            $response = wp_remote_get($url);
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                
                // Simple title extraction
                preg_match('/<title[^>]*>(.*?)<\/title>/s', $body, $title_match);
                $title = !empty($title_match[1]) ? trim($title_match[1]) : 'Imported Content';
                
                // Simple content extraction (first paragraph or meta description)
                preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/s', $body, $desc_match);
                $content = !empty($desc_match[1]) ? trim($desc_match[1]) : wp_trim_words(strip_tags($body), 50);
                
                if (!empty($title) || !empty($content)) {
                    $this->save_imported_content($this->get_current_source_id(), $title, $content, $url);
                    $imported_count++;
                }
            }
        }
        
        return $imported_count;
    }
    
    /**
     * Process uploaded files
     */
    private function process_uploads($config) {
        // This would handle file uploads from the media library
        // For now, return 0 as it's handled by WordPress media system
        return 0;
    }
    
    /**
     * Process Google Drive content
     */
    private function process_google_drive($config) {
        try {
            $google_drive = new GoogleDriveIntegration();
            
            if (!$google_drive->is_connected()) {
                error_log('SMO Social: Google Drive not connected');
                return 0;
            }
            
            $folder_id = $config['folder_id'] ?? null;
            $imported_count = 0;
            
            // List files in the specified folder
            $result = $google_drive->list_files($folder_id);
            
            if (isset($result['files'])) {
                foreach ($result['files'] as $file) {
                    // Skip folders
                    if ($file['mimeType'] === 'application/vnd.google-apps.folder') {
                        continue;
                    }
                    
                    // Get file content
                    $file_data = $google_drive->get_file_content($file['id']);
                    
                    // Process and save the content
                    $this->save_imported_content($this->get_current_source_id(), $file['name'], $file_data['content'], $file['webViewLink']);
                    $imported_count++;
                }
            }
            
            return $imported_count;
        } catch (\Exception $e) {
            error_log('SMO Social: Google Drive sync error - ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Process Dropbox content
     */
    private function process_dropbox($config) {
        try {
            $dropbox = new DropboxIntegration();
            
            if (!$dropbox->is_connected()) {
                error_log('SMO Social: Dropbox not connected');
                return 0;
            }
            
            $path = $config['path'] ?? '';
            $imported_count = 0;
            
            // List files in the specified folder
            $result = $dropbox->list_files($path);
            
            if (isset($result['entries'])) {
                foreach ($result['entries'] as $file) {
                    // Skip folders
                    if ($file['.tag'] === 'folder') {
                        continue;
                    }
                    
                    // Download file
                    $file_data = $dropbox->download_file($file['path_lower']);
                    
                    // Process and save the content
                    $this->save_imported_content($this->get_current_source_id(), $file['name'], $file_data['content'], '');
                    $imported_count++;
                }
            }
            
            return $imported_count;
        } catch (\Exception $e) {
            error_log('SMO Social: Dropbox sync error - ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Process Canva content
     */
    private function process_canva($config) {
        try {
            $canva = new CanvaIntegration();
            
            if (!$canva->is_connected()) {
                error_log('SMO Social: Canva not connected');
                return 0;
            }
            
            $imported_count = 0;
            
            // List user designs
            $result = $canva->list_designs(10);
            
            if (isset($result['items'])) {
                foreach ($result['items'] as $design) {
                    try {
                        // Export design as PNG
                        $export_result = $canva->export_design($design['id'], 'png');
                        
                        if (isset($export_result['job']['id'])) {
                            $job_id = $export_result['job']['id'];
                            
                            // Wait for export to complete (simplified)
                            sleep(3);
                            
                            // Download the exported file
                            $file_data = $canva->download_export($job_id);
                            
                            // Process and save the content
                            $this->save_imported_content($this->get_current_source_id(), $design['title'] . '.png', $file_data['content'], '');
                            $imported_count++;
                        }
                    } catch (\Exception $e) {
                        error_log('SMO Social: Failed to process Canva design ' . $design['id'] . ': ' . $e->getMessage());
                        continue;
                    }
                }
            }
            
            return $imported_count;
        } catch (\Exception $e) {
            error_log('SMO Social: Canva sync error - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Stream RSS feed content with memory-efficient processing
     */
    private function stream_rss_feed($config) {
        $content_processor = function($title, $content, $link) {
            $this->save_imported_content($this->get_current_source_id(), $title, $content, $link);
        };

        return $this->content_stream->stream_external_content(
            array('type' => 'rss', 'url' => $config['url']),
            $content_processor
        );
    }

    /**
     * Stream URL content with memory-efficient processing
     */
    private function stream_url_content($config) {
        if (empty($config['urls']) || !is_array($config['urls'])) {
            return 0;
        }

        $imported_count = 0;
        $content_processor = function($title, $content, $url) {
            $this->save_imported_content($this->get_current_source_id(), $title, $content, $url);
        };

        foreach ($config['urls'] as $url) {
            $response = wp_remote_get($url);

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);

                // Simple title extraction
                preg_match('/<title[^>]*>(.*?)<\/title>/s', $body, $title_match);
                $title = !empty($title_match[1]) ? trim($title_match[1]) : 'Imported Content';

                // Simple content extraction (first paragraph or meta description)
                preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/s', $body, $desc_match);
                $content = !empty($desc_match[1]) ? trim($desc_match[1]) : wp_trim_words(strip_tags($body), 50);

                if (!empty($title) || !empty($content)) {
                    $content_processor($title, $content, $url);
                    $imported_count++;
                }
            }

            // Check memory usage periodically
            if ($imported_count % 50 === 0) {
                $this->content_stream->check_memory_usage();
            }
        }

        return $imported_count;
    }

    /**
     * Stream Google Drive content with memory-efficient processing
     */
    private function stream_google_drive($config) {
        $content_processor = function($title, $content, $url) {
            $this->save_imported_content($this->get_current_source_id(), $title, $content, $url);
        };

        return $this->content_stream->stream_external_content(
            array('type' => 'google_drive', 'folder_id' => $config['folder_id'] ?? null),
            $content_processor
        );
    }

    /**
     * Stream Dropbox content with memory-efficient processing
     */
    private function stream_dropbox($config) {
        $content_processor = function($title, $content, $url) {
            $this->save_imported_content($this->get_current_source_id(), $title, $content, $url);
        };

        return $this->content_stream->stream_external_content(
            array('type' => 'dropbox', 'path' => $config['path'] ?? ''),
            $content_processor
        );
    }

    /**
     * Stream Canva content with memory-efficient processing
     */
    private function stream_canva($config) {
        $content_processor = function($title, $content, $url) {
            $this->save_imported_content($this->get_current_source_id(), $title, $content, $url);
        };

        return $this->content_stream->stream_external_content(
            array('type' => 'canva'),
            $content_processor
        );
    }
    
    /**
     * Save imported content to database
     */
    private function save_imported_content($source_id, $title, $content, $url = '') {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_names['imported_content'],
            array(
                'user_id' => get_current_user_id(),
                'source_id' => $source_id,
                'title' => sanitize_text_field($title),
                'content' => wp_kses_post($content),
                'metadata' => json_encode(array('url' => $url)),
                'status' => 'pending',
                'imported_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        $imported_content_id = $wpdb->insert_id;
        
        // Trigger automation processing
        if ($imported_content_id) {
            $this->process_import_automation($imported_content_id);
        }
        
        return $imported_content_id;
    }
    
    /**
     * Process automation rules for imported content
     */
    private function process_import_automation($imported_content_id) {
        global $wpdb;
        
        // Get imported content details
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT ic.*, cs.type as source_type 
             FROM {$this->table_names['imported_content']} ic
             LEFT JOIN {$this->table_names['content_sources']} cs ON ic.source_id = cs.id
             WHERE ic.id = %d",
            $imported_content_id
        ), ARRAY_A);
        
        if (!$content) {
            return;
        }
        
        // Get applicable automation rules
        $rules = $this->get_applicable_automation_rules($content['source_id'], $content['source_type']);
        
        foreach ($rules as $rule) {
            $this->execute_automation_rule($rule, $imported_content_id);
        }
    }
    
    /**
     * Get applicable automation rules for content
     */
    private function get_applicable_automation_rules($source_id, $source_type) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Get all active automation rules for the user
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_names['automation_rules']} 
             WHERE user_id = %d AND is_active = 1
             ORDER BY priority ASC",
            $user_id
        ), ARRAY_A);
        
        $applicable_rules = array();
        
        foreach ($rules as $rule) {
            $rule_sources = json_decode($rule['source_ids'] ?? '[]', true);
            
            // Check if rule applies to this source
            if (empty($rule_sources) || in_array($source_id, $rule_sources) || in_array('all', $rule_sources)) {
                $applicable_rules[] = $rule;
            }
        }
        
        return $applicable_rules;
    }
    
    /**
     * Execute automation rule
     */
    private function execute_automation_rule($rule, $imported_content_id) {
        global $wpdb;
        
        $start_time = microtime(true);
        $status = 'success';
        $error_message = '';
        
        try {
            $rule_data = json_decode($rule['metadata'] ?? '{}', true);
            $scheduling_config = json_decode($rule['scheduling_config'] ?? '{}', true);
            
            // Update rule execution count
            $wpdb->update(
                $this->table_names['automation_rules'],
                array(
                    'execution_count' => $rule['execution_count'] + 1,
                    'last_executed' => current_time('mysql')
                ),
                array('id' => $rule['id']),
                array('%d', '%s'),
                array('%d')
            );
            
            // Auto-share if enabled
            if ($rule['auto_share_enabled']) {
                $this->process_auto_share($rule, $imported_content_id);
            }
            
            // Auto-process if enabled
            if ($rule['auto_process_enabled']) {
                $this->process_auto_transformation($rule, $imported_content_id);
            }
            
        } catch (\Exception $e) {
            $status = 'failed';
            $error_message = $e->getMessage();
            
            // Update rule failure count
            $wpdb->update(
                $this->table_names['automation_rules'],
                array('failure_count' => $rule['failure_count'] + 1),
                array('id' => $rule['id']),
                array('%d'),
                array('%d')
            );
        }
        
        // Log the execution
        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        $this->log_automation_execution(
            $rule['id'],
            $imported_content_id,
            $rule['rule_type'],
            $status,
            $execution_time,
            $error_message
        );
    }
    
    /**
     * Process auto-share for imported content
     */
    private function process_auto_share($rule, $imported_content_id) {
        global $wpdb;
        
        $platform_targets = json_decode($rule['platform_targets'] ?? '[]', true);
        
        foreach ($platform_targets as $platform) {
            // Check if auto-share config exists
            $config = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_names['auto_share_config']} 
                 WHERE rule_id = %d AND platform = %s",
                $rule['id'],
                $platform
            ), ARRAY_A);
            
            if (!$config || !$config['auto_share_enabled']) {
                continue;
            }
            
            // Create auto-share job
            $wpdb->insert(
                $this->table_names['auto_share_config'],
                array(
                    'user_id' => $rule['user_id'],
                    'rule_id' => $rule['id'],
                    'imported_content_id' => $imported_content_id,
                    'platform' => $platform,
                    'auto_share_enabled' => 1,
                    'share_immediately' => $config['share_immediately'] ?? 0,
                    'schedule_delay_minutes' => $config['schedule_delay_minutes'] ?? 0,
                    'custom_message_template' => $config['custom_message_template'] ?? '',
                    'hashtag_strategy' => $config['hashtag_strategy'] ?? 'auto',
                    'custom_hashtags' => $config['custom_hashtags'] ?? '',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Process auto-transformation for imported content
     */
    private function process_auto_transformation($rule, $imported_content_id) {
        if (!$rule['transformation_template_id']) {
            return;
        }
        
        // Apply transformation using automation manager
        if ($this->automation_manager) {
            $this->automation_manager->apply_transformation_to_content(
                $imported_content_id,
                $rule['transformation_template_id']
            );
        }
    }
    
    /**
     * Log automation execution
     */
    private function log_automation_execution($rule_id, $imported_content_id, $action_type, $status, $execution_time_ms, $error_message = '') {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_names['automation_logs'],
            array(
                'user_id' => get_current_user_id(),
                'rule_id' => $rule_id,
                'imported_content_id' => $imported_content_id,
                'action_type' => $action_type,
                'status' => $status,
                'error_message' => $error_message,
                'execution_time_ms' => round($execution_time_ms),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Get imported content items
     */
    public function get_imported_content($limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ic.*, cs.name as source_name 
             FROM {$this->table_names['imported_content']} ic
             LEFT JOIN {$this->table_names['content_sources']} cs ON ic.source_id = cs.id
             ORDER BY ic.imported_at DESC
             LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A);
    }
    
    /**
     * Convert imported content to a post idea
     */
    public function content_to_idea($imported_content_id, $user_id) {
        global $wpdb;
        
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['imported_content']} WHERE id = %d",
            $imported_content_id
        ), ARRAY_A);
        
        if (!$content) {
            throw new \Exception('Imported content not found');
        }
        
        $idea_id = $wpdb->insert(
            $this->table_names['content_ideas'],
            array(
                'user_id' => $user_id,
                'title' => $content['title'],
                'description' => $content['content'],
                'content_type' => 'post',
                'status' => 'idea',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        // Mark content as processed
        $wpdb->update(
            $this->table_names['imported_content'],
            array('status' => 'processed'),
            array('id' => $imported_content_id),
            array('%s'),
            array('%d')
        );
        
        return $idea_id;
    }
    
    /**
     * Get current source ID (helper method)
     */
    private function get_current_source_id() {
        // This would be set by the sync method
        return isset($this->current_source_id) ? $this->current_source_id : 0;
    }
    
    /**
     * AJAX: Add content source
     */
    public function ajax_add_content_source() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $config = $_POST['config'] ?? array();
        
        if (empty($name) || empty($type)) {
            wp_send_json_error(__('Name and type are required'));
        }
        
        try {
            $source_id = $this->add_content_source($name, $type, $config);
            wp_send_json_success(array('source_id' => $source_id));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Sync content source
     */
    public function ajax_sync_content_source() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $source_id = intval($_POST['source_id'] ?? 0);
        
        try {
            $imported_count = $this->sync_content_source($source_id);
            wp_send_json_success(array(
                'imported_count' => $imported_count,
                'message' => sprintf(__('Imported %d items', 'smo-social'), $imported_count)
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Import content item as idea
     */
    public function ajax_import_content_item() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $imported_content_id = intval($_POST['imported_content_id'] ?? 0);
        
        try {
            $idea_id = $this->content_to_idea($imported_content_id, get_current_user_id());
            wp_send_json_success(array('idea_id' => $idea_id));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Get content sources
     */
    public function ajax_get_content_sources() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $sources = $this->get_content_sources();
        wp_send_json_success($sources);
    }
    
    /**
     * AJAX: Delete content source
     */
    public function ajax_delete_content_source() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $source_id = intval($_POST['source_id'] ?? 0);

        global $wpdb;
        $result = $wpdb->delete(
            $this->table_names['content_sources'],
            array('id' => $source_id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success(__('Content source deleted'));
        } else {
            wp_send_json_error(__('Failed to delete content source'));
        }
    }
    
    /**
     * AJAX: Process RSS feed
     */
    public function ajax_process_rss_feed() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $url = esc_url_raw($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(__('Valid URL is required'));
        }
        
        $config = array('url' => $url);
        $imported_count = $this->process_rss_feed($config);
        
        wp_send_json_success(array(
            'imported_count' => $imported_count,
            'message' => sprintf(__('Imported %d items from RSS feed', 'smo-social'), $imported_count)
        ));
    }
    
    /**
     * AJAX: Create automation rule
     */
    public function ajax_create_automation_rule() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $source_ids = array_map('intval', $_POST['source_ids'] ?? array());
        $auto_share = (bool) ($_POST['auto_share'] ?? false);
        $auto_process = (bool) ($_POST['auto_process'] ?? false);
        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());
        $scheduling_config = $_POST['scheduling_config'] ?? array();
        
        $result = $this->create_automation_rule($name, $type, $source_ids, $auto_share, $auto_process, $platforms, $scheduling_config);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Get automation rules
     */
    public function ajax_get_automation_rules() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $rules = $this->get_automation_rules();
        wp_send_json_success($rules);
    }
    
    /**
     * AJAX: Update automation rule
     */
    public function ajax_update_automation_rule() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $rule_id = intval($_POST['rule_id'] ?? 0);
        $updates = $_POST['updates'] ?? array();
        
        $result = $this->update_automation_rule($rule_id, $updates);
        
        if ($result) {
            wp_send_json_success(__('Automation rule updated', 'smo-social'));
        } else {
            wp_send_json_error(__('Failed to update automation rule', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Delete automation rule
     */
    public function ajax_delete_automation_rule() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $rule_id = intval($_POST['rule_id'] ?? 0);
        
        $result = $this->delete_automation_rule($rule_id);
        
        if ($result) {
            wp_send_json_success(__('Automation rule deleted', 'smo-social'));
        } else {
            wp_send_json_error(__('Failed to delete automation rule', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Execute automation rule
     */
    public function ajax_execute_automation_rule() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $rule_id = intval($_POST['rule_id'] ?? 0);
        
        $result = $this->execute_manual_automation_rule($rule_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Get auto-share configuration
     */
    public function ajax_get_auto_share_config() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $config = $this->get_auto_share_config($platform);
        wp_send_json_success($config);
    }
    
    /**
     * AJAX: Update auto-share configuration
     */
    public function ajax_update_auto_share_config() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $config = $_POST['config'] ?? array();
        
        $result = $this->update_auto_share_config($platform, $config);
        
        if ($result) {
            wp_send_json_success(__('Auto-share configuration updated', 'smo-social'));
        } else {
            wp_send_json_error(__('Failed to update auto-share configuration', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Create transformation template
     */
    public function ajax_create_transformation_template() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $rules = $_POST['rules'] ?? array();
        
        $result = $this->create_transformation_template($name, $type, $description, $rules);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Get transformation templates
     */
    public function ajax_get_transformation_templates() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $templates = $this->get_transformation_templates();
        wp_send_json_success($templates);
    }
    
    /**
     * AJAX: Apply transformation
     */
    public function ajax_apply_transformation() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $content_id = intval($_POST['content_id'] ?? 0);
        $template_id = intval($_POST['template_id'] ?? 0);
        
        $result = $this->apply_transformation($content_id, $template_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Create automation rule
     */
    public function create_automation_rule($name, $type, $source_ids, $auto_share, $auto_process, $platforms, $scheduling_config) {
        global $wpdb;
        
        if (empty($name) || empty($type)) {
            return array(
                'success' => false,
                'message' => __('Name and type are required', 'smo-social')
            );
        }
        
        $result = $wpdb->insert(
            $this->table_names['automation_rules'],
            array(
                'user_id' => get_current_user_id(),
                'rule_name' => sanitize_text_field($name),
                'rule_type' => sanitize_text_field($type),
                'source_ids' => json_encode($source_ids),
                'auto_share_enabled' => $auto_share ? 1 : 0,
                'auto_process_enabled' => $auto_process ? 1 : 0,
                'platform_targets' => json_encode($platforms),
                'scheduling_config' => json_encode($scheduling_config),
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => $wpdb->last_error
            );
        }
        
        return array(
            'success' => true,
            'rule_id' => $wpdb->insert_id,
            'message' => __('Automation rule created successfully', 'smo-social')
        );
    }
    
    /**
     * Get automation rules
     */
    public function get_automation_rules() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_names['automation_rules']} 
             WHERE user_id = %d 
             ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Update automation rule
     */
    public function update_automation_rule($rule_id, $updates) {
        global $wpdb;
        
        // Sanitize and prepare updates
        $allowed_updates = array();
        
        if (isset($updates['rule_name'])) {
            $allowed_updates['rule_name'] = sanitize_text_field($updates['rule_name']);
        }
        
        if (isset($updates['is_active'])) {
            $allowed_updates['is_active'] = $updates['is_active'] ? 1 : 0;
        }
        
        if (isset($updates['auto_share_enabled'])) {
            $allowed_updates['auto_share_enabled'] = $updates['auto_share_enabled'] ? 1 : 0;
        }
        
        if (isset($updates['auto_process_enabled'])) {
            $allowed_updates['auto_process_enabled'] = $updates['auto_process_enabled'] ? 1 : 0;
        }
        
        if (isset($updates['priority'])) {
            $allowed_updates['priority'] = intval($updates['priority']);
        }
        
        $allowed_updates['updated_at'] = current_time('mysql');
        
        if (empty($allowed_updates)) {
            return false;
        }
        
        return $wpdb->update(
            $this->table_names['automation_rules'],
            $allowed_updates,
            array('id' => $rule_id, 'user_id' => get_current_user_id()),
            array('%s'),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Delete automation rule
     */
    public function delete_automation_rule($rule_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_names['automation_rules'],
            array('id' => $rule_id, 'user_id' => get_current_user_id()),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Execute manual automation rule
     */
    public function execute_manual_automation_rule($rule_id) {
        global $wpdb;
        
        // Get the rule
        $rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['automation_rules']} 
             WHERE id = %d AND user_id = %d",
            $rule_id,
            get_current_user_id()
        ), ARRAY_A);
        
        if (!$rule) {
            return array(
                'success' => false,
                'message' => __('Automation rule not found', 'smo-social')
            );
        }
        
        // Get pending imported content for this rule's sources
        $rule_sources = json_decode($rule['source_ids'] ?? '[]', true);
        
        $content_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_names['imported_content']} 
             WHERE source_id IN (%s) AND status = 'pending'
             LIMIT 10",
            implode(',', array_map('intval', $rule_sources))
        ), ARRAY_A);
        
        $processed_count = 0;
        
        foreach ($content_items as $content) {
            $this->execute_automation_rule($rule, $content['id']);
            $processed_count++;
        }
        
        return array(
            'success' => true,
            'processed_count' => $processed_count,
            'message' => sprintf(__('Processed %d items', 'smo-social'), $processed_count)
        );
    }
    
    /**
     * Get auto-share configuration
     */
    public function get_auto_share_config($platform) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['auto_share_config']} 
             WHERE user_id = %d AND platform = %s",
            $user_id,
            $platform
        ), ARRAY_A);
    }
    
    /**
     * Update auto-share configuration
     */
    public function update_auto_share_config($platform, $config) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Check if config exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_names['auto_share_config']} 
             WHERE user_id = %d AND platform = %s",
            $user_id,
            $platform
        ));
        
        $data = array(
            'user_id' => $user_id,
            'platform' => $platform,
            'auto_share_enabled' => $config['auto_share_enabled'] ? 1 : 0,
            'share_immediately' => $config['share_immediately'] ? 1 : 0,
            'schedule_delay_minutes' => intval($config['schedule_delay_minutes'] ?? 0),
            'custom_message_template' => sanitize_textarea_field($config['custom_message_template'] ?? ''),
            'hashtag_strategy' => sanitize_text_field($config['hashtag_strategy'] ?? 'auto'),
            'custom_hashtags' => sanitize_text_field($config['custom_hashtags'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        if ($existing) {
            return $wpdb->update(
                $this->table_names['auto_share_config'],
                $data,
                array('id' => $existing),
                array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            ) !== false;
        } else {
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert(
                $this->table_names['auto_share_config'],
                $data,
                array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
            ) !== false;
        }
    }
    
    /**
     * Create transformation template
     */
    public function create_transformation_template($name, $type, $description, $rules) {
        global $wpdb;
        
        if (empty($name) || empty($type)) {
            return array(
                'success' => false,
                'message' => __('Name and type are required', 'smo-social')
            );
        }
        
        $result = $wpdb->insert(
            $this->table_names['transformation_templates'],
            array(
                'user_id' => get_current_user_id(),
                'template_name' => sanitize_text_field($name),
                'template_type' => sanitize_text_field($type),
                'description' => sanitize_textarea_field($description),
                'transformation_rules' => json_encode($rules),
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => $wpdb->last_error
            );
        }
        
        return array(
            'success' => true,
            'template_id' => $wpdb->insert_id,
            'message' => __('Transformation template created successfully', 'smo-social')
        );
    }
    
    /**
     * Get transformation templates
     */
    public function get_transformation_templates() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_names['transformation_templates']} 
             WHERE user_id = %d AND is_active = 1
             ORDER BY usage_count DESC, created_at DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Apply transformation to content
     */
    public function apply_transformation($content_id, $template_id) {
        global $wpdb;
        
        // Get content
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['imported_content']} WHERE id = %d",
            $content_id
        ), ARRAY_A);
        
        if (!$content) {
            return array(
                'success' => false,
                'message' => __('Content not found', 'smo-social')
            );
        }
        
        // Get template
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['transformation_templates']} 
             WHERE id = %d AND user_id = %d",
            $template_id,
            get_current_user_id()
        ), ARRAY_A);
        
        if (!$template) {
            return array(
                'success' => false,
                'message' => __('Template not found', 'smo-social')
            );
        }
        
        // Apply transformation rules
        $rules = json_decode($template['transformation_rules'] ?? '{}', true);
        $transformed_content = $this->apply_transformation_rules($content, $rules);
        
        // Update content
        $result = $wpdb->update(
            $this->table_names['imported_content'],
            array(
                'content' => $transformed_content['content'],
                'metadata' => json_encode(array_merge(
                    json_decode($content['metadata'] ?? '{}', true),
                    $transformed_content['metadata']
                )),
                'status' => 'transformed',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $content_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        // Update template usage count
        $wpdb->update(
            $this->table_names['transformation_templates'],
            array('usage_count' => $template['usage_count'] + 1),
            array('id' => $template_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            return array(
                'success' => true,
                'transformed_content' => $transformed_content,
                'message' => __('Transformation applied successfully', 'smo-social')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to apply transformation', 'smo-social')
        );
    }
    
    /**
     * Apply transformation rules to content
     */
    private function apply_transformation_rules($content, $rules) {
        $result = array(
            'content' => $content['content'],
            'metadata' => array()
        );
        
        foreach ($rules as $rule_type => $rule_config) {
            switch ($rule_type) {
                case 'add_prefix':
                    if (isset($rule_config['text'])) {
                        $result['content'] = $rule_config['text'] . ' ' . $result['content'];
                    }
                    break;
                    
                case 'add_suffix':
                    if (isset($rule_config['text'])) {
                        $result['content'] = $result['content'] . ' ' . $rule_config['text'];
                    }
                    break;
                    
                case 'replace_text':
                    if (isset($rule_config['search']) && isset($rule_config['replace'])) {
                        $result['content'] = str_replace(
                            $rule_config['search'],
                            $rule_config['replace'],
                            $result['content']
                        );
                    }
                    break;
                    
                case 'hashtag_processing':
                    if (isset($rule_config['auto_hashtags'])) {
                        // Auto-generate hashtags (simplified implementation)
                        $words = explode(' ', strtolower(strip_tags($result['content'])));
                        $hashtags = array();
                        
                        foreach ($words as $word) {
                            if (strlen($word) > 3 && !in_array($word, array('the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use'))) {
                                $hashtags[] = '#' . $word;
                            }
                        }
                        
                        if (!empty($hashtags)) {
                            $result['content'] .= ' ' . implode(' ', array_slice($hashtags, 0, 5));
                            $result['metadata']['auto_hashtags'] = array_slice($hashtags, 0, 5);
                        }
                    }
                    break;
            }
        }
        
        return $result;
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Content Sources table
        $sources_table = $wpdb->prefix . 'smo_content_sources';
        $sql = "CREATE TABLE IF NOT EXISTS $sources_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            url varchar(500) NOT NULL,
            settings longtext,
            status varchar(50) DEFAULT 'active',
            last_import datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_type (type),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // Imported Content table
        $imported_table = $wpdb->prefix . 'smo_imported_content';
        $sql .= "CREATE TABLE IF NOT EXISTS $imported_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            title varchar(500) NOT NULL,
            content longtext NOT NULL,
            metadata longtext,
            status varchar(50) DEFAULT 'pending',
            imported_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_source_id (source_id),
            KEY idx_status (status),
            KEY idx_imported_at (imported_at)
        ) $charset_collate;";
        
        // Automation Rules table
        $rules_table = $wpdb->prefix . 'smo_import_automation_rules';
        $sql .= "CREATE TABLE IF NOT EXISTS $rules_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            rule_name varchar(255) NOT NULL,
            rule_type varchar(50) NOT NULL,
            source_ids longtext,
            auto_share_enabled boolean DEFAULT 0,
            auto_process_enabled boolean DEFAULT 0,
            platform_targets longtext,
            scheduling_config longtext,
            metadata longtext,
            is_active boolean DEFAULT 1,
            priority int(11) DEFAULT 0,
            execution_count int(11) DEFAULT 0,
            failure_count int(11) DEFAULT 0,
            last_executed datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_is_active (is_active),
            KEY idx_last_executed (last_executed)
        ) $charset_collate;";
        
        // Auto Share Config table
        $share_table = $wpdb->prefix . 'smo_import_auto_share_config';
        $sql .= "CREATE TABLE IF NOT EXISTS $share_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            rule_id bigint(20) unsigned NOT NULL,
            imported_content_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            auto_share_enabled boolean DEFAULT 0,
            share_immediately boolean DEFAULT 0,
            schedule_delay_minutes int(11) DEFAULT 0,
            custom_message_template text,
            hashtag_strategy varchar(50) DEFAULT 'auto',
            custom_hashtags text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_rule_id (rule_id),
            KEY idx_imported_content_id (imported_content_id),
            KEY idx_platform (platform)
        ) $charset_collate;";
        
        // Transformation Templates table
        $templates_table = $wpdb->prefix . 'smo_content_transformation_templates';
        $sql .= "CREATE TABLE IF NOT EXISTS $templates_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            template_name varchar(255) NOT NULL,
            template_type varchar(50) NOT NULL,
            description text,
            transformation_rules longtext,
            is_active boolean DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_is_active (is_active),
            KEY idx_usage_count (usage_count)
        ) $charset_collate;";
        
        // Automation Logs table
        $logs_table = $wpdb->prefix . 'smo_import_automation_logs';
        $sql .= "CREATE TABLE IF NOT EXISTS $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            rule_id bigint(20) unsigned NOT NULL,
            imported_content_id bigint(20) unsigned NOT NULL,
            action_type varchar(50) NOT NULL,
            status varchar(50) NOT NULL,
            error_message text,
            execution_time_ms int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_rule_id (rule_id),
            KEY idx_imported_content_id (imported_content_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        \dbDelta($sql);
    }
}
