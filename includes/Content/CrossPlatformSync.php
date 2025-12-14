<?php
namespace SMO_Social\Content;

class CrossPlatformSync {
    private $platforms;
    private $sync_rules;
    private $queue;
    
    public function __construct($platforms = array()) {
        $this->platforms = $platforms;
        $this->sync_rules = array();
        $this->queue = array();
    }
    
    public function schedule_post($content, $platforms, $schedule_time = null, $options = array()) {
        if (!$schedule_time) {
            $schedule_time = time();
        }
        
        $post_data = array(
            'content' => $content,
            'platforms' => $platforms,
            'schedule_time' => $schedule_time,
            'options' => $options,
            'status' => 'scheduled',
            'created_at' => current_time('mysql')
        );
        
        // Validate content for each platform
        $validation_results = $this->validate_content_for_platforms($content, $platforms, $options);
        
        if (!$validation_results['valid']) {
            return array(
                'success' => false,
                'error' => 'Content validation failed',
                'details' => $validation_results['errors']
            );
        }
        
        // Add to queue
        $post_id = $this->add_to_queue($post_data);
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'scheduled_platforms' => $validation_results['valid_platforms']
        );
    }
    
    public function publish_now($content, $platforms, $options = array()) {
        $results = array();
        
        foreach ($platforms as $platform_slug) {
            if (!isset($this->platforms[$platform_slug])) {
                $results[$platform_slug] = array(
                    'success' => false,
                    'error' => 'Platform not available'
                );
                continue;
            }
            
            $platform = $this->platforms[$platform_slug];
            
            // Apply platform-specific formatting
            $formatted_content = $this->format_content_for_platform($content, $platform_slug, $options);
            
            // Post to platform
            $result = $platform->post($formatted_content, $options);
            $results[$platform_slug] = $result;
            
            // Log result
            $this->log_post_result($platform_slug, $content, $result);
        }
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
    
    public function sync_existing_post($original_platform, $post_id, $target_platforms) {
        // Get original post data
        $original_post = $this->get_post_from_platform($original_platform, $post_id);
        
        if (!$original_post) {
            return array(
                'success' => false,
                'error' => 'Original post not found'
            );
        }
        
        $results = array();
        $content = $original_post['content'] ?? '';
        
        foreach ($target_platforms as $platform_slug) {
            if (!isset($this->platforms[$platform_slug])) {
                $results[$platform_slug] = array(
                    'success' => false,
                    'error' => 'Target platform not available'
                );
                continue;
            }
            
            $platform = $this->platforms[$platform_slug];
            
            // Format content for target platform
            $formatted_content = $this->format_content_for_platform($content, $platform_slug);
            
            // Post to target platform
            $result = $platform->post($formatted_content);
            $results[$platform_slug] = $result;
        }
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
    
    public function get_sync_analytics($post_id) {
        $analytics = array(
            'post_id' => $post_id,
            'platform_analytics' => array(),
            'total_engagement' => 0,
            'best_performing_platform' => ''
        );
        
        // Get queue entry
        $queue_entry = $this->get_from_queue($post_id);
        
        if (!$queue_entry) {
            return array(
                'success' => false,
                'error' => 'Post not found'
            );
        }
        
        foreach ($queue_entry['platforms'] as $platform_slug) {
            if (isset($this->platforms[$platform_slug])) {
                $platform_analytics = $this->get_platform_analytics($platform_slug, $post_id);
                $analytics['platform_analytics'][$platform_slug] = $platform_analytics;
                
                if (isset($platform_analytics['engagement'])) {
                    $analytics['total_engagement'] += $platform_analytics['engagement'];
                }
            }
        }
        
        // Determine best performing platform
        $best_engagement = 0;
        foreach ($analytics['platform_analytics'] as $platform => $data) {
            if (isset($data['engagement']) && $data['engagement'] > $best_engagement) {
                $best_engagement = $data['engagement'];
                $analytics['best_performing_platform'] = $platform;
            }
        }
        
        return $analytics;
    }
    
    public function add_sync_rule($rule_name, $rule_data) {
        $this->sync_rules[$rule_name] = $rule_data;
        
        return array(
            'success' => true,
            'rule_id' => $rule_name
        );
    }
    
    public function apply_sync_rules($content, $platforms) {
        $processed_content = $content;
        
        foreach ($this->sync_rules as $rule_name => $rule) {
            if ($rule['active'] && $this->should_apply_rule($rule, $platforms)) {
                $processed_content = $this->apply_rule($processed_content, $rule);
            }
        }
        
        return $processed_content;
    }
    
    private function validate_content_for_platforms($content, $platforms, $options) {
        $valid_platforms = array();
        $errors = array();
        
        foreach ($platforms as $platform_slug) {
            if (!isset($this->platforms[$platform_slug])) {
                $errors[$platform_slug] = 'Platform not configured';
                continue;
            }
            
            $platform = $this->platforms[$platform_slug];
            $validation = $this->validate_content_for_single_platform($content, $platform, $options);
            
            if ($validation['valid']) {
                $valid_platforms[] = $platform_slug;
            } else {
                $errors[$platform_slug] = $validation['error'];
            }
        }
        
        return array(
            'valid' => empty($errors),
            'valid_platforms' => $valid_platforms,
            'errors' => $errors
        );
    }
    
    private function validate_content_for_single_platform($content, $platform, $options) {
        // Check character limits
        $max_chars = $platform->get_max_chars();
        if (strlen($content) > $max_chars) {
            return array(
                'valid' => false,
                'error' => "Content exceeds {$max_chars} character limit"
            );
        }
        
        // Check media requirements
        if (isset($options['media']) && !empty($options['media'])) {
            foreach ($options['media'] as $media) {
                if (!$platform->supports_images() && $media['type'] === 'image') {
                    return array(
                        'valid' => false,
                        'error' => 'Platform does not support images'
                    );
                }
                
                if (!$platform->supports_videos() && $media['type'] === 'video') {
                    return array(
                        'valid' => false,
                        'error' => 'Platform does not support videos'
                    );
                }
            }
        }
        
        return array('valid' => true);
    }
    
    private function format_content_for_platform($content, $platform_slug, $options = array()) {
        // Apply platform-specific formatting
        switch ($platform_slug) {
            case 'twitter':
                return $this->format_for_twitter($content, $options);
            case 'facebook':
                return $this->format_for_facebook($content, $options);
            case 'instagram':
                return $this->format_for_instagram($content, $options);
            case 'linkedin':
                return $this->format_for_linkedin($content, $options);
            default:
                return $content;
        }
    }
    
    private function format_for_twitter($content, $options) {
        // Twitter-specific formatting
        if (strlen($content) > 280) {
            $content = substr($content, 0, 277) . '...';
        }
        
        // Add hashtags if provided
        if (isset($options['hashtags'])) {
            $content .= ' ' . implode(' ', $options['hashtags']);
        }
        
        return $content;
    }
    
    private function format_for_facebook($content, $options) {
        // Facebook-specific formatting
        return $content; // Facebook allows longer content
    }
    
    private function format_for_instagram($content, $options) {
        // Instagram-specific formatting
        if (isset($options['hashtags'])) {
            $content .= PHP_EOL . PHP_EOL . implode(' ', $options['hashtags']);
        }
        
        return $content;
    }
    
    private function format_for_linkedin($content, $options) {
        // LinkedIn-specific formatting
        return $content; // Professional formatting
    }
    
    private function add_to_queue($post_data) {
        $post_id = uniqid('smo_post_');
        $this->queue[$post_id] = $post_data;
        
        return $post_id;
    }
    
    private function get_from_queue($post_id) {
        return $this->queue[$post_id] ?? null;
    }
    
    private function log_post_result($platform_slug, $content, $result) {
        // Log the post result for analytics
        if ($result['success']) {
            // Success logging
        } else {
            // Error logging
        }
    }
    
    private function get_post_from_platform($platform_slug, $post_id) {
        // Get post data from platform
        return null; // Placeholder
    }
    
    private function get_platform_analytics($platform_slug, $post_id) {
        // Get analytics from platform
        return array(
            'engagement' => 0,
            'reach' => 0,
            'impressions' => 0
        );
    }
    
    private function should_apply_rule($rule, $platforms) {
        // Check if rule should be applied to these platforms
        return true;
    }
    
    private function apply_rule($content, $rule) {
        // Apply the rule to content
        return $content;
    }
}
