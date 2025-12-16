<?php
namespace SMO_Social\Community;

/**
 * TemplateManager - Manages community-contributed post templates
 * 
 * Handles creation, validation, installation, and execution of post templates
 * that define complete social media campaigns across multiple platforms.
 */
class TemplateManager {
    
    private $templates_dir;
    private $installed_templates;
    private $validation_errors = array();
    
    public function __construct() {
        $this->templates_dir = SMO_SOCIAL_PLUGIN_DIR . 'templates/';
        $this->installed_templates = get_option('smo_installed_templates', array());
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_install_template', array($this, 'ajax_install_template'));
        add_action('wp_ajax_smo_uninstall_template', array($this, 'ajax_uninstall_template'));
        add_action('wp_ajax_smo_validate_template', array($this, 'ajax_validate_template'));
        add_action('wp_ajax_smo_create_template', array($this, 'ajax_create_template'));
    }
    
    /**
     * Install a template from JSON file
     *
     * @param string $template_path Path to template JSON file
     * @return array Installation result
     */
    public function install_template($template_path) {
        error_log("SMO Social: TemplateManager::install_template called with path: {$template_path}");

        try {
            // Read and validate JSON
            $template_data = $this->load_template_file($template_path);
            if (!$template_data) {
                error_log("SMO Social: Failed to load template file: {$template_path}");
                return array('success' => false, 'error' => 'Failed to load template file');
            }

            error_log("SMO Social: Template data loaded: " . json_encode($template_data));

            // Validate template structure
            $validation_result = $this->validate_template($template_data);
            if (!$validation_result['valid']) {
                error_log("SMO Social: Template validation failed: " . implode(', ', $validation_result['errors']));
                return array('success' => false, 'error' => implode(', ', $validation_result['errors']));
            }

            // Store template metadata
            $template_id = $template_data['template_id'];
            $this->installed_templates[$template_id] = array(
                'template_id' => $template_id,
                'name' => $template_data['name'],
                'version' => $template_data['version'],
                'author' => $template_data['author'],
                'installed_date' => current_time('mysql'),
                'install_count' => 0,
                'status' => 'active',
                'rating' => 0,
                'verified' => false
            );

            update_option('smo_installed_templates', $this->installed_templates);
            error_log("SMO Social: Template {$template_id} installed successfully");

            // Increment install counter for reputation system
            $this->increment_install_count($template_id);

            return array('success' => true, 'template_id' => $template_id);

        } catch (\Exception $e) {
            error_log("SMO Social: TemplateManager::install_template exception: " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Uninstall a template
     * 
     * @param string $template_id Template identifier
     * @return array Uninstall result
     */
    public function uninstall_template($template_id) {
        if (!isset($this->installed_templates[$template_id])) {
            return array('success' => false, 'error' => 'Template not found');
        }
        
        unset($this->installed_templates[$template_id]);
        update_option('smo_installed_templates', $this->installed_templates);
        
        return array('success' => true);
    }
    
    /**
     * Load template from file
     * 
     * @param string $file_path Path to template JSON file
     * @return array|null Template data or null on failure
     */
    private function load_template_file($file_path) {
        if (!file_exists($file_path)) {
            return null;
        }
        
        $json_content = file_get_contents($file_path);
        $template_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $template_data;
    }
    
    /**
     * Validate template structure and content
     * 
     * @param array $template_data Template data to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validate_template($template_data) {
        $this->validation_errors = array();
        
        // Required fields validation
        $required_fields = array('template_id', 'name', 'version', 'description', 'author', 'posts');
        foreach ($required_fields as $field) {
            if (!isset($template_data[$field])) {
                $this->validation_errors[] = "Missing required field: {$field}";
            }
        }
        
        // Template ID format validation
        if (isset($template_data['template_id'])) {
            if (!preg_match('/^[a-z0-9_]+$/', $template_data['template_id'])) {
                $this->validation_errors[] = 'Template ID must contain only lowercase letters, numbers, and underscores';
            }
        }
        
        // Posts array validation
        if (isset($template_data['posts']) && is_array($template_data['posts'])) {
            $this->validate_posts_array($template_data['posts']);
        }
        
        // A/B variants validation
        if (isset($template_data['ab_variants']) && is_array($template_data['ab_variants'])) {
            $this->validate_ab_variants($template_data['ab_variants']);
        }
        
        return array(
            'valid' => empty($this->validation_errors),
            'errors' => $this->validation_errors
        );
    }
    
    /**
     * Validate posts array structure
     * 
     * @param array $posts Posts array to validate
     */
    private function validate_posts_array($posts) {
        if (empty($posts)) {
            $this->validation_errors[] = 'Posts array cannot be empty';
            return;
        }
        
        foreach ($posts as $index => $post) {
            // Required post fields
            $required_post_fields = array('day', 'platforms', 'content_template');
            foreach ($required_post_fields as $field) {
                if (!isset($post[$field])) {
                    $this->validation_errors[] = "Post {$index}: Missing required field: {$field}";
                }
            }
            
            // Day validation (should be positive integer)
            if (isset($post['day']) && (!is_int($post['day']) || $post['day'] <= 0)) {
                $this->validation_errors[] = "Post {$index}: Day must be a positive integer";
            }
            
            // Platforms validation
            if (isset($post['platforms'])) {
                if (!is_array($post['platforms']) || empty($post['platforms'])) {
                    $this->validation_errors[] = "Post {$index}: Platforms must be a non-empty array";
                } else {
                    $this->validate_platform_list($post['platforms'], $index);
                }
            }
            
            // Content template validation
            if (isset($post['content_template'])) {
                if (empty(trim($post['content_template']))) {
                    $this->validation_errors[] = "Post {$index}: Content template cannot be empty";
                }
            }
        }
    }
    
    /**
     * Validate platform list
     * 
     * @param array $platforms Platform list to validate
     * @param int $post_index Post index for error reporting
     */
    private function validate_platform_list($platforms, $post_index) {
        // Check if platform driver files exist
        foreach ($platforms as $platform) {
            $driver_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$platform}.json";
            if (!file_exists($driver_file)) {
                $this->validation_errors[] = "Post {$post_index}: Platform driver not found: {$platform}";
            }
        }
    }
    
    /**
     * Validate A/B variants structure
     * 
     * @param array $variants A/B variants to validate
     */
    private function validate_ab_variants($variants) {
        if (count($variants) < 2) {
            $this->validation_errors[] = 'A/B variants must contain at least 2 variants';
            return;
        }
        
        foreach ($variants as $index => $variant) {
            if (!isset($variant['name']) || !isset($variant['posts'])) {
                $this->validation_errors[] = "A/B variant {$index}: Missing required fields (name, posts)";
            }
            
            if (isset($variant['posts']) && is_array($variant['posts'])) {
                $this->validate_posts_array($variant['posts']);
            }
        }
    }
    
    /**
     * Execute a template campaign
     * 
     * @param string $template_id Template identifier
     * @param array $context Campaign context (post_id, variables, etc.)
     * @return array Execution result
     */
    public function execute_template($template_id, $context = array()) {
        $template_data = $this->get_template_data($template_id);
        if (!$template_data) {
            return array('success' => false, 'error' => 'Template not found or not installed');
        }
        
        try {
            $results = array();
            
            // Handle A/B variants
            if (isset($template_data['ab_variants']) && !empty($template_data['ab_variants'])) {
                $selected_variant = $this->select_ab_variant($template_data['ab_variants'], $context);
                $posts_to_execute = $selected_variant['posts'];
            } else {
                $posts_to_execute = $template_data['posts'];
            }
            
            // Execute posts in sequence
            foreach ($posts_to_execute as $index => $post) {
                $post_result = $this->execute_single_post($post, $context);
                $results[] = array(
                    'post_index' => $index,
                    'day' => $post['day'],
                    'result' => $post_result
                );
                
                // Stop if post failed (optional: make this configurable)
                if (!$post_result['success']) {
                    break;
                }
            }
            
            // Log campaign execution
            $this->log_campaign_execution($template_id, $results);
            
            return array(
                'success' => true,
                'results' => $results,
                'executed_posts' => count($results)
            );
            
        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Execute a single post from template
     * 
     * @param array $post Post configuration
     * @param array $context Campaign context
     * @return array Execution result
     */
    private function execute_single_post($post, $context) {
        $post_content = $this->render_content_template($post['content_template'], $context);
        
        $results = array();
        foreach ($post['platforms'] as $platform) {
            // Load platform driver
            $driver_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$platform}.json";
            if (!file_exists($driver_file)) {
                $results[$platform] = array('success' => false, 'error' => 'Driver not found');
                continue;
            }
            
            $driver_data = json_decode(file_get_contents($driver_file), true);
            $user_config = $this->get_user_platform_config($platform);
            
            if (!$user_config) {
                $results[$platform] = array('success' => false, 'error' => 'Platform not configured');
                continue;
            }
            
            // Use existing DriverEngine
            $driver_engine = new \SMO_Social\Core\DriverEngine($driver_data, $user_config, $context['post_id']);
            
            // Modify post content temporarily
            $original_post = get_post($context['post_id']);
            $temp_post_id = $this->create_temp_post($original_post, $post_content);
            
            if ($temp_post_id) {
                $result = $driver_engine->publish();
                \wp_delete_post($temp_post_id, true); // Clean up temp post
                
                $results[$platform] = array(
                    'success' => !$result instanceof \WP_Error,
                    'result' => $result,
                    'temp_post_id' => $temp_post_id
                );
            } else {
                $results[$platform] = array('success' => false, 'error' => 'Failed to create temporary post');
            }
        }
        
        return array(
            'success' => !empty(array_filter(array_column($results, 'success'))),
            'platforms' => $results
        );
    }
    
    /**
     * Render content template with context variables
     * 
     * @param string $template Template string with variables
     * @param array $context Context variables
     * @return string Rendered content
     */
    private function render_content_template($template, $context) {
        $replacements = array();
        
        // Add context variables
        foreach ($context as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }
        
        // Add WordPress variables
        $post = get_post($context['post_id']);
        if ($post) {
            $replacements['{{post_title}}'] = $post->post_title;
            $replacements['{{post_content}}'] = $post->post_content;
            $replacements['{{post_excerpt}}'] = $post->post_excerpt;
            $replacements['{{post_url}}'] = get_permalink($context['post_id']);
            $replacements['{{site_name}}'] = \get_bloginfo('name');
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Select A/B variant based on context
     * 
     * @param array $variants A/B variants
     * @param array $context Campaign context
     * @return array Selected variant
     */
    private function select_ab_variant($variants, $context) {
        // Simple random selection (could be made more sophisticated)
        $random_index = array_rand($variants);
        return $variants[$random_index];
    }
    
    /**
     * Create temporary post for template execution
     * 
     * @param WP_Post $original_post Original post
     * @param string $new_content New content
     * @return int|false Temp post ID or false on failure
     */
    private function create_temp_post($original_post, $new_content) {
        $temp_post_data = array(
            'post_title' => $original_post->post_title,
            'post_content' => $new_content,
            'post_excerpt' => $original_post->post_excerpt,
            'post_status' => 'publish',
            'post_type' => $original_post->post_type,
            'post_author' => $original_post->post_author,
            'post_date' => $original_post->post_date
        );
        
        $temp_post_id = \wp_insert_post($temp_post_data);
        
        if ($temp_post_id && !is_wp_error($temp_post_id)) {
            // Copy featured image if exists
            if (has_post_thumbnail($original_post->ID)) {
                \set_post_thumbnail($temp_post_id, get_post_thumbnail_id($original_post->ID));
            }
            return $temp_post_id;
        }
        
        return false;
    }
    
    /**
     * Get user platform configuration
     * 
     * @param string $platform Platform name
     * @return array|null User config or null
     */
    private function get_user_platform_config($platform) {
        return get_option("smo_{$platform}_config", array());
    }
    
    /**
     * Get template data
     * 
     * @param string $template_id Template identifier
     * @return array|null Template data or null
     */
    public function get_template_data($template_id) {
        $template_file = $this->templates_dir . "{$template_id}.json";
        if (!file_exists($template_file)) {
            return null;
        }
        
        return $this->load_template_file($template_file);
    }
    
    /**
     * Get all installed templates
     * 
     * @return array Installed templates
     */
    public function get_installed_templates() {
        return $this->installed_templates;
    }
    
    /**
     * Increment install count for reputation system
     * 
     * @param string $template_id Template identifier
     */
    private function increment_install_count($template_id) {
        if (isset($this->installed_templates[$template_id])) {
            $this->installed_templates[$template_id]['install_count']++;
            update_option('smo_installed_templates', $this->installed_templates);
            
            // Update reputation data (will be implemented in ReputationManager)
            // $reputation_manager = new ReputationManager();
            // $reputation_manager->update_install_count($template_id);
        }
    }
    
    /**
     * Log campaign execution for analytics
     * 
     * @param string $template_id Template identifier
     * @param array $results Execution results
     */
    private function log_campaign_execution($template_id, $results) {
        $log_entry = array(
            'template_id' => $template_id,
            'timestamp' => current_time('mysql'),
            'results' => $results,
            'success_count' => count(array_filter(array_column($results, 'success'))),
            'total_count' => count($results)
        );
        
        $logs = get_option('smo_template_execution_logs', array());
        $logs[] = $log_entry;
        
        // Keep only last 100 logs
        if (is_array($logs) && count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('smo_template_execution_logs', $logs);
    }
    
    // AJAX handlers

    public function ajax_install_template() {
        error_log("SMO Social: ajax_install_template called");

        check_ajax_referer('smo_community_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            error_log("SMO Social: Insufficient permissions for ajax_install_template");
            \wp_die('Insufficient permissions');
        }

        // Check if template_id is provided in POST data
        if (!isset($_POST['template_id'])) {
            error_log("SMO Social: Missing template_id in ajax_install_template");
            \wp_send_json(array('success' => false, 'error' => 'Template ID is required'));
            exit;
        }

        $template_id = sanitize_text_field($_POST['template_id']);
        $template_path = $this->templates_dir . "{$template_id}.json";

        error_log("SMO Social: Installing template {$template_id} from path {$template_path}");

        $result = $this->install_template($template_path);
        \wp_send_json($result);
    }
    
    public function ajax_uninstall_template() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        // Check if template_id is provided in POST data
        if (!isset($_POST['template_id'])) {
            \wp_send_json(array('success' => false, 'error' => 'Template ID is required'));
            exit;
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $result = $this->uninstall_template($template_id);
        \wp_send_json($result);
    }
    
    public function ajax_validate_template() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        // Check if template_data is provided in POST data
        if (!isset($_POST['template_data'])) {
            \wp_send_json(array('success' => false, 'error' => 'Template data is required'));
            exit;
        }
        
        $template_data = json_decode(stripslashes($_POST['template_data']), true);
        $result = $this->validate_template($template_data);
        \wp_send_json($result);
    }
    
    public function ajax_create_template() {
        check_ajax_referer('smo_community_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        // Check if template_data is provided in POST data
        if (!isset($_POST['template_data'])) {
            \wp_send_json(array('success' => false, 'error' => 'Template data is required'));
            exit;
        }
        
        $template_data = json_decode(stripslashes($_POST['template_data']), true);
        $result = $this->create_template_file($template_data);
        \wp_send_json($result);
    }
    
    /**
     * Create template file from data
     * 
     * @param array $template_data Template data
     * @return array Creation result
     */
    private function create_template_file($template_data) {
        $template_id = $template_data['template_id'];
        $template_path = $this->templates_dir . "{$template_id}.json";
        
        // Validate template data
        $validation_result = $this->validate_template($template_data);
        if (!$validation_result['valid']) {
            return array('success' => false, 'error' => implode(', ', $validation_result['errors']));
        }
        
        // Create templates directory if it doesn't exist
        if (!file_exists($this->templates_dir)) {
            \wp_mkdir_p($this->templates_dir);
        }
        
        // Write template file
        $json_content = json_encode($template_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($template_path, $json_content);
        
        if ($result === false) {
            return array('success' => false, 'error' => 'Failed to write template file');
        }
        
        return array('success' => true, 'template_id' => $template_id);
    }
}
