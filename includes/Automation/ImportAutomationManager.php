<?php
/**
 * Import Automation Manager
 * 
 * Handles advanced automation rules, content transformation, and auto-sharing
 * for imported content from various sources
 *
 * @package SMO_Social
 * @subpackage Automation
 * @since 1.0.0
 */

namespace SMO_Social\Automation;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * Import Automation Manager
 * 
 * Manages automation rules, content transformations, and auto-sharing
 * for imported social media content
 */
class ImportAutomationManager {
    
    private $table_names;
    private $last_error = '';
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'imported_content' => $wpdb->prefix . 'smo_imported_content',
            'content_sources' => $wpdb->prefix . 'smo_content_sources',
            'automation_rules' => $wpdb->prefix . 'smo_import_automation_rules',
            'auto_share_config' => $wpdb->prefix . 'smo_import_auto_share_config',
            'transformation_templates' => $wpdb->prefix . 'smo_content_transformation_templates',
            'automation_logs' => $wpdb->prefix . 'smo_import_automation_logs'
        );
        
        // Initialize WordPress hooks
        add_action('wp_ajax_smo_run_automation_batch', array($this, 'ajax_run_automation_batch'));
        add_action('wp_ajax_smo_get_automation_analytics', array($this, 'ajax_get_automation_analytics'));
        add_action('wp_ajax_smo_test_transformation_rule', array($this, 'ajax_test_transformation_rule'));
        add_action('wp_ajax_smo_bulk_update_automation_rules', array($this, 'ajax_bulk_update_automation_rules'));
    }
    
    /**
     * Apply transformation to imported content
     */
    public function apply_transformation_to_content($imported_content_id, $template_id) {
        global $wpdb;
        
        // Get content
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['imported_content']} WHERE id = %d",
            $imported_content_id
        ), ARRAY_A);
        
        if (!$content) {
            throw new \Exception('Content not found');
        }
        
        // Get template
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['transformation_templates']} WHERE id = %d",
            $template_id
        ), ARRAY_A);
        
        if (!$template) {
            throw new \Exception('Transformation template not found');
        }
        
        // Apply transformation
        $rules = json_decode($template['transformation_rules'] ?? '{}', true);
        $transformed_content = $this->execute_transformation_rules($content, $rules);
        
        // Update content in database
        $result = $wpdb->update(
            $this->table_names['imported_content'],
            array(
                'content' => $transformed_content['content'],
                'metadata' => json_encode(array_merge(
                    json_decode($content['metadata'] ?? '{}', true),
                    $transformed_content['metadata']
                )),
                'status' => 'transformed'
            ),
            array('id' => $imported_content_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to update transformed content');
        }
        
        return $transformed_content;
    }
    
    /**
     * Execute transformation rules on content
     */
    private function execute_transformation_rules($content, $rules) {
        $result = array(
            'content' => $content['content'],
            'title' => $content['title'],
            'metadata' => array()
        );
        
        foreach ($rules as $rule_type => $rule_config) {
            switch ($rule_type) {
                case 'title_enhancement':
                    $result['title'] = $this->enhance_title($result['title'], $rule_config);
                    break;
                    
                case 'content_cleanup':
                    $result['content'] = $this->cleanup_content($result['content'], $rule_config);
                    break;
                    
                case 'add_formatting':
                    $result['content'] = $this->add_formatting($result['content'], $rule_config);
                    break;
                    
                case 'hashtag_optimization':
                    $hashtags = $this->optimize_hashtags($result['content'], $rule_config);
                    $result['content'] .= ' ' . $hashtags;
                    $result['metadata']['optimized_hashtags'] = $hashtags;
                    break;
                    
                case 'link_processing':
                    $result = $this->process_links($result, $rule_config);
                    break;
                    
                case 'content_rewrite':
                    $result = $this->rewrite_content($result, $rule_config);
                    break;
                    
                case 'template_application':
                    $result = $this->apply_template($result, $rule_config);
                    break;
            }
        }
        
        return $result;
    }
    
    /**
     * Enhance content title
     */
    private function enhance_title($title, $config) {
        if (isset($config['add_prefix']) && !empty($config['add_prefix'])) {
            $title = $config['add_prefix'] . ' ' . $title;
        }
        
        if (isset($config['add_suffix']) && !empty($config['add_suffix'])) {
            $title .= ' ' . $config['add_suffix'];
        }
        
        if (isset($config['capitalize']) && $config['capitalize']) {
            $title = ucwords($title);
        }
        
        if (isset($config['add_emoji']) && !empty($config['add_emoji'])) {
            $emoji = is_array($config['add_emoji']) 
                ? $config['add_emoji'][array_rand($config['add_emoji'])]
                : $config['add_emoji'];
            $title = $emoji . ' ' . $title . ' ' . $emoji;
        }
        
        return $title;
    }
    
    /**
     * Clean up content
     */
    private function cleanup_content($content, $config) {
        $content = wp_strip_all_tags($content);
        
        if (isset($config['remove_urls']) && $config['remove_urls']) {
            $content = preg_replace('/\bhttps?:\/\/[^\s]+\b/', '', $content);
        }
        
        if (isset($config['remove_mentions']) && $config['remove_mentions']) {
            $content = preg_replace('/@[a-zA-Z0-9_]+/', '', $content);
        }
        
        if (isset($config['normalize_whitespace']) && $config['normalize_whitespace']) {
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);
        }
        
        if (isset($config['limit_length']) && $config['limit_length'] > 0) {
            if (strlen($content) > $config['limit_length']) {
                $content = substr($content, 0, $config['limit_length'] - 3) . '...';
            }
        }
        
        return $content;
    }
    
    /**
     * Add formatting to content
     */
    private function add_formatting($content, $config) {
        if (isset($config['add_line_breaks']) && $config['add_line_breaks']) {
            $sentences = preg_split('/([.!?]+)\s+/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
            $formatted = '';
            
            for ($i = 0; $i < count($sentences); $i += 2) {
                $sentence = $sentences[$i];
                $punctuation = isset($sentences[$i + 1]) ? $sentences[$i + 1] : '';
                $formatted .= $sentence . $punctuation . "\n\n";
            }
            
            $content = trim($formatted);
        }
        
        if (isset($config['add_bullets']) && $config['add_bullets']) {
            $lines = explode("\n", $content);
            $bulleted = array();
            
            foreach ($lines as $line) {
                if (trim($line)) {
                    $bulleted[] = '• ' . trim($line);
                } else {
                    $bulleted[] = '';
                }
            }
            
            $content = implode("\n", $bulleted);
        }
        
        return $content;
    }
    
    /**
     * Optimize hashtags
     */
    private function optimize_hashtags($content, $config) {
        $existing_hashtags = array();
        preg_match_all('/#[\w]+/', $content, $matches);
        
        foreach ($matches[0] as $hashtag) {
            $existing_hashtags[] = strtolower($hashtag);
        }
        
        $new_hashtags = array();
        
        if (isset($config['auto_generate']) && $config['auto_generate']) {
            $words = str_word_count(strtolower(strip_tags($content)), 1);
            $stop_words = array('the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use');
            
            foreach ($words as $word) {
                if (strlen($word) > 3 && !in_array($word, $stop_words) && !in_array('#' . $word, $existing_hashtags)) {
                    $new_hashtags[] = '#' . $word;
                }
            }
        }
        
        if (isset($config['add_tags']) && !empty($config['add_tags'])) {
            foreach ($config['add_tags'] as $tag) {
                $hashtag = strpos($tag, '#') === 0 ? $tag : '#' . $tag;
                if (!in_array(strtolower($hashtag), array_map('strtolower', $existing_hashtags))) {
                    $new_hashtags[] = $hashtag;
                }
            }
        }
        
        $max_hashtags = isset($config['max_count']) ? intval($config['max_count']) : 10;
        $all_hashtags = array_merge($existing_hashtags, $new_hashtags);
        
        return implode(' ', array_slice($all_hashtags, 0, $max_hashtags));
    }
    
    /**
     * Process links in content
     */
    private function process_links($content_data, $config) {
        $content = $content_data['content'];
        
        if (isset($config['extract_urls']) && $config['extract_urls']) {
            preg_match_all('/\bhttps?:\/\/[^\s]+/', $content, $url_matches);
            $content_data['metadata']['extracted_urls'] = $url_matches[0];
        }
        
        if (isset($config['add_utm_parameters']) && !empty($config['add_utm_parameters'])) {
            $utm_params = http_build_query($config['add_utm_parameters']);
            
            $content = preg_replace_callback(
                '/\bhttps?:\/\/[^\s]+/',
                function($matches) use ($utm_params) {
                    $url = $matches[0];
                    $separator = strpos($url, '?') !== false ? '&' : '?';
                    return $url . $separator . $utm_params;
                },
                $content
            );
            
            $content_data['content'] = $content;
        }
        
        if (isset($config['add_call_to_action']) && !empty($config['add_call_to_action'])) {
            $cta = $config['add_call_to_action'];
            if (strpos($cta, '{url}') !== false) {
                // Find first URL and replace placeholder
                preg_match('/\bhttps?:\/\/[^\s]+/', $content, $url_match);
                if (!empty($url_match)) {
                    $cta = str_replace('{url}', $url_match[0], $cta);
                }
            }
            $content_data['content'] .= "\n\n" . $cta;
        }
        
        return $content_data;
    }
    
    /**
     * Rewrite content
     */
    private function rewrite_content($content_data, $config) {
        $content = $content_data['content'];
        $title = $content_data['title'];
        
        if (isset($config['tone']) && $config['tone'] !== 'original') {
            switch ($config['tone']) {
                case 'professional':
                    $content = $this->make_professional($content);
                    $title = $this->make_professional($title);
                    break;
                    
                case 'casual':
                    $content = $this->make_casual($content);
                    $title = $this->make_casual($title);
                    break;
                    
                case 'enthusiastic':
                    $content = $this->make_enthusiastic($content);
                    $title = $this->make_enthusiastic($title);
                    break;
            }
        }
        
        if (isset($config['simplify']) && $config['simplify']) {
            $content = $this->simplify_content($content);
        }
        
        if (isset($config['expand']) && $config['expand']) {
            $content = $this->expand_content($content);
        }
        
        $content_data['content'] = $content;
        $content_data['title'] = $title;
        
        return $content_data;
    }
    
    /**
     * Apply template to content
     */
    private function apply_template($content_data, $config) {
        if (empty($config['template'])) {
            return $content_data;
        }
        
        $template = $config['template'];
        $content = $content_data['content'];
        $title = $content_data['title'];
        
        // Replace placeholders in template
        $replacements = array(
            '{title}' => $title,
            '{content}' => $content,
            '{excerpt}' => wp_trim_words(strip_tags($content), 20),
            '{hashtags}' => $this->optimize_hashtags($content, array('max_count' => 5)),
            '{date}' => current_time('M j, Y'),
            '{author}' => wp_get_current_user()->display_name
        );
        
        $final_content = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        $content_data['content'] = $final_content;
        $content_data['metadata']['template_applied'] = true;
        
        return $content_data;
    }
    
    /**
     * Make content professional
     */
    private function make_professional($content) {
        $replacements = array(
            'awesome' => 'excellent',
            'great' => 'outstanding',
            'amazing' => 'remarkable',
            'cool' => 'professional',
            'nice' => 'appropriate',
            'super' => 'highly',
            'really' => 'significantly',
            'very' => 'substantially'
        );
        
        foreach ($replacements as $informal => $formal) {
            $content = str_ireplace($informal, $formal, $content);
        }
        
        return $content;
    }
    
    /**
     * Make content casual
     */
    private function make_casual($content) {
        $replacements = array(
            'excellent' => 'awesome',
            'outstanding' => 'great',
            'remarkable' => 'amazing',
            'professional' => 'cool',
            'appropriate' => 'nice',
            'highly' => 'really',
            'significantly' => 'really',
            'substantially' => 'really'
        );
        
        foreach ($replacements as $formal => $informal) {
            $content = str_ireplace($formal, $informal, $content);
        }
        
        return $content;
    }
    
    /**
     * Make content enthusiastic
     */
    private function make_enthusiastic($content) {
        $content = str_replace('.', '!', $content);
        $content = str_ireplace('good', 'fantastic', $content);
        $content = str_ireplace('great', 'incredible', $content);
        $content = str_ireplace('nice', 'wonderful', $content);
        
        return $content;
    }
    
    /**
     * Simplify content
     */
    private function simplify_content($content) {
        // Remove complex words and long sentences
        $sentences = preg_split('/[.!?]+/', $content);
        $simplified = array();
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 100 && str_word_count($sentence) < 15) {
                $simplified[] = $sentence;
            }
        }
        
        return implode('. ', $simplified) . '.';
    }
    
    /**
     * Expand content
     */
    private function expand_content($content) {
        $expansions = array(
            'is' => 'is definitely',
            'are' => 'are certainly',
            'can' => 'can definitely',
            'will' => 'will certainly',
            'should' => 'should definitely'
        );
        
        foreach ($expansions as $base => $expanded) {
            $content = str_ireplace($base, $expanded, $content);
        }
        
        return $content;
    }
    
    /**
     * Get automation analytics
     */
    public function get_automation_analytics($days = 30) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get automation execution stats
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_executions,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(execution_time_ms) as avg_execution_time,
                action_type,
                DATE(created_at) as execution_date
             FROM {$this->table_names['automation_logs']} 
             WHERE user_id = %d AND created_at >= %s
             GROUP BY action_type, DATE(created_at)
             ORDER BY execution_date DESC",
            $user_id,
            $date_from
        ), ARRAY_A);
        
        // Get rule performance
        $rule_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                rule_name,
                rule_type,
                execution_count,
                success_count,
                failure_count,
                (success_count / execution_count * 100) as success_rate,
                last_executed
             FROM {$this->table_names['automation_rules']} 
             WHERE user_id = %d AND execution_count > 0
             ORDER BY success_rate DESC, execution_count DESC",
            $user_id
        ), ARRAY_A);
        
        return array(
            'stats' => $stats,
            'rule_performance' => $rule_performance,
            'summary' => array(
                'total_rules' => count($rule_performance),
                'active_rules' => count(array_filter($rule_performance, function($rule) {
                    return strtotime($rule['last_executed']) > strtotime('-7 days');
                })),
                'avg_success_rate' => !empty($rule_performance) 
                    ? array_sum(array_column($rule_performance, 'success_rate')) / count($rule_performance)
                    : 0
            )
        );
    }
    
    /**
     * Run automation batch
     */
    public function run_automation_batch($rule_ids = null) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $start_time = microtime(true);
        
        // Get rules to execute
        if ($rule_ids === null) {
            $rule_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$this->table_names['automation_rules']} 
                 WHERE user_id = %d AND is_active = 1",
                $user_id
            ));
        }
        
        $results = array();
        $processed_count = 0;
        
        foreach ($rule_ids as $rule_id) {
            try {
                $rule = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$this->table_names['automation_rules']} WHERE id = %d",
                    $rule_id
                ), ARRAY_A);
                
                if (!$rule) {
                    continue;
                }
                
                // Get pending content for this rule
                $source_ids = json_decode($rule['source_ids'] ?? '[]', true);
                $content_items = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$this->table_names['imported_content']} 
                     WHERE source_id IN (%s) AND status = 'pending'
                     LIMIT 50",
                    implode(',', array_map('intval', $source_ids))
                ), ARRAY_A);
                
                $rule_processed = 0;
                
                foreach ($content_items as $content) {
                    $this->execute_rule_for_content($rule, $content);
                    $rule_processed++;
                }
                
                $processed_count += $rule_processed;
                $results[] = array(
                    'rule_id' => $rule_id,
                    'rule_name' => $rule['rule_name'],
                    'processed' => $rule_processed,
                    'status' => 'success'
                );
                
            } catch (\Exception $e) {
                $results[] = array(
                    'rule_id' => $rule_id,
                    'rule_name' => $rule['rule_name'] ?? 'Unknown',
                    'processed' => 0,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                );
            }
        }
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        
        return array(
            'results' => $results,
            'total_processed' => $processed_count,
            'execution_time_ms' => round($execution_time),
            'success' => true
        );
    }
    
    /**
     * Execute rule for specific content
     */
    private function execute_rule_for_content($rule, $content) {
        global $wpdb;
        
        $start_time = microtime(true);
        $status = 'success';
        $error_message = '';
        
        try {
            // Auto-share if enabled
            if ($rule['auto_share_enabled']) {
                $this->process_auto_share($rule, $content);
            }
            
            // Auto-process if enabled
            if ($rule['auto_process_enabled']) {
                $this->process_auto_transformation($rule, $content);
            }
            
        } catch (\Exception $e) {
            $status = 'failed';
            $error_message = $e->getMessage();
        }
        
        // Log execution
        $execution_time = (microtime(true) - $start_time) * 1000;
        $this->log_execution($rule['id'], $content['id'], $rule['rule_type'], $status, $execution_time, $error_message);
    }
    
    /**
     * Process auto-share for rule and content
     */
    private function process_auto_share($rule, $content) {
        global $wpdb;
        
        $platform_targets = json_decode($rule['platform_targets'] ?? '[]', true);
        
        foreach ($platform_targets as $platform) {
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
                    'imported_content_id' => $content['id'],
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
     * Process auto-transformation for rule and content
     */
    private function process_auto_transformation($rule, $content) {
        if (!$rule['transformation_template_id']) {
            return;
        }
        
        $this->apply_transformation_to_content(
            $content['id'],
            $rule['transformation_template_id']
        );
    }
    
    /**
     * Log rule execution
     */
    private function log_execution($rule_id, $content_id, $action_type, $status, $execution_time_ms, $error_message = '') {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_names['automation_logs'],
            array(
                'user_id' => get_current_user_id(),
                'rule_id' => $rule_id,
                'imported_content_id' => $content_id,
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
     * AJAX: Run automation batch
     */
    public function ajax_run_automation_batch() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $rule_ids = isset($_POST['rule_ids']) ? array_map('intval', $_POST['rule_ids']) : null;
        
        $result = $this->run_automation_batch($rule_ids);
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get automation analytics
     */
    public function ajax_get_automation_analytics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $days = intval($_POST['days'] ?? 30);
        $analytics = $this->get_automation_analytics($days);
        wp_send_json_success($analytics);
    }
    
    /**
     * AJAX: Test transformation rule
     */
    public function ajax_test_transformation_rule() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $rules = $_POST['rules'] ?? array();
        
        $test_content = array(
            'content' => $content,
            'title' => 'Test Title',
            'metadata' => array()
        );
        
        $result = $this->execute_transformation_rules($test_content, $rules);
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Bulk update automation rules
     */
    public function ajax_bulk_update_automation_rules() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $rule_ids = array_map('intval', $_POST['rule_ids'] ?? array());
        $action = sanitize_text_field($_POST['action'] ?? '');
        $value = $_POST['value'] ?? '';
        
        $results = array();
        global $wpdb;
        
        foreach ($rule_ids as $rule_id) {
            $update_data = array();
            
            switch ($action) {
                case 'activate':
                    $update_data['is_active'] = 1;
                    break;
                case 'deactivate':
                    $update_data['is_active'] = 0;
                    break;
                case 'set_priority':
                    $update_data['priority'] = intval($value);
                    break;
                case 'delete':
                    $result = $wpdb->delete(
                        $this->table_names['automation_rules'],
                        array('id' => $rule_id, 'user_id' => get_current_user_id()),
                        array('%d', '%d')
                    );
                    $results[] = array('rule_id' => $rule_id, 'success' => $result !== false);
                    continue 2;
            }
            
            if (!empty($update_data)) {
                $update_data['updated_at'] = current_time('mysql');
                
                $result = $wpdb->update(
                    $this->table_names['automation_rules'],
                    $update_data,
                    array('id' => $rule_id, 'user_id' => get_current_user_id()),
                    array('%s'),
                    array('%d', '%d')
                );
                
                $results[] = array('rule_id' => $rule_id, 'success' => $result !== false);
            }
        }
        
        wp_send_json_success($results);
    }
}