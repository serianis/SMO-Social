<?php
/**
 * Post Templates Manager
 * 
 * Manages post templates for standardized social media content
 * 
 * @package SMO_Social
 */

namespace SMO_Social\Features;

if (!defined('ABSPATH')) {
    exit;
}

class PostTemplatesManager {
    
    /**
     * Get all templates for a user
     */
    public static function get_user_templates($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'smo_post_templates';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d OR is_global = 1 ORDER BY usage_count DESC, created_at DESC",
            $user_id
        ), ARRAY_A);
        
        // Parse JSON fields
        foreach ($results as &$template) {
            $template['platforms'] = json_decode($template['platforms'], true) ?: array();
            $template['variables'] = json_decode($template['variables'], true) ?: array();
            $template['metadata'] = json_decode($template['metadata'], true) ?: array();
        }
        
        return $results;
    }
    
    /**
     * Get a specific template
     */
    public static function get_template($template_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_post_templates';
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ), ARRAY_A);
        
        if ($template) {
            $template['platforms'] = json_decode($template['platforms'], true) ?: array();
            $template['variables'] = json_decode($template['variables'], true) ?: array();
            $template['metadata'] = json_decode($template['metadata'], true) ?: array();
        }
        
        return $template;
    }
    
    /**
     * Create a new template
     */
    public static function create_template($data, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Validate required fields
        if (empty($data['name']) || empty($data['content'])) {
            return new \WP_Error('missing_fields', 'Template name and content are required');
        }
        
        // Extract variables from content
        $variables = self::extract_variables($data['content']);
        
        $table_name = $wpdb->prefix . 'smo_post_templates';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'content' => wp_kses_post($data['content']),
                'platforms' => json_encode($data['platforms'] ?? array()),
                'variables' => json_encode($variables),
                'category' => sanitize_text_field($data['category'] ?? 'general'),
                'is_global' => isset($data['is_global']) && current_user_can('manage_options') ? 1 : 0,
                'metadata' => json_encode($data['metadata'] ?? array()),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to create template');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a template
     */
    public static function update_template($template_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_post_templates';
        
        // Verify ownership
        $template = self::get_template($template_id);
        if (!$template) {
            return new \WP_Error('not_found', 'Template not found');
        }
        
        if ($template['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new \WP_Error('unauthorized', 'You do not have permission to update this template');
        }
        
        $update_data = array();
        $format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
            $update_data['variables'] = json_encode(self::extract_variables($data['content']));
            $format[] = '%s';
            $format[] = '%s';
        }
        
        if (isset($data['platforms'])) {
            $update_data['platforms'] = json_encode($data['platforms']);
            $format[] = '%s';
        }
        
        if (isset($data['category'])) {
            $update_data['category'] = sanitize_text_field($data['category']);
            $format[] = '%s';
        }
        
        if (isset($data['metadata'])) {
            $update_data['metadata'] = json_encode($data['metadata']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return new \WP_Error('no_data', 'No data to update');
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $template_id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a template
     */
    public static function delete_template($template_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_post_templates';
        
        // Verify ownership
        $template = self::get_template($template_id);
        if (!$template) {
            return new \WP_Error('not_found', 'Template not found');
        }
        
        if ($template['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new \WP_Error('unauthorized', 'You do not have permission to delete this template');
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $template_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Apply template to content
     */
    public static function apply_template($template_id, $variable_values = array()) {
        $template = self::get_template($template_id);
        
        if (!$template) {
            return new \WP_Error('not_found', 'Template not found');
        }
        
        $content = $template['content'];
        
        // Replace variables
        foreach ($template['variables'] as $variable) {
            $placeholder = '{' . $variable . '}';
            $value = $variable_values[$variable] ?? '';
            $content = str_replace($placeholder, $value, $content);
        }
        
        // Increment usage count
        self::increment_usage_count($template_id);
        
        return array(
            'content' => $content,
            'platforms' => $template['platforms'],
            'metadata' => $template['metadata']
        );
    }
    
    /**
     * Extract variables from template content
     */
    private static function extract_variables($content) {
        preg_match_all('/\{([A-Z_]+)\}/', $content, $matches);
        return array_unique($matches[1]);
    }
    
    /**
     * Increment usage count
     */
    private static function increment_usage_count($template_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_post_templates';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET usage_count = usage_count + 1 WHERE id = %d",
            $template_id
        ));
    }
    
    /**
     * Get templates by category
     */
    public static function get_templates_by_category($category, $user_id = null) {
        $all_templates = self::get_user_templates($user_id);
        
        return array_filter($all_templates, function($template) use ($category) {
            return $template['category'] === $category;
        });
    }
    
    /**
     * Get template categories
     */
    public static function get_categories() {
        return array(
            'general' => __('General', 'smo-social'),
            'product_launch' => __('Product Launch', 'smo-social'),
            'promotion' => __('Promotion', 'smo-social'),
            'announcement' => __('Announcement', 'smo-social'),
            'blog_post' => __('Blog Post', 'smo-social'),
            'event' => __('Event', 'smo-social'),
            'behind_scenes' => __('Behind the Scenes', 'smo-social'),
            'testimonial' => __('Testimonial', 'smo-social'),
            'question' => __('Question/Poll', 'smo-social'),
            'tip' => __('Tip/How-to', 'smo-social'),
            'quote' => __('Quote', 'smo-social'),
            'holiday' => __('Holiday/Seasonal', 'smo-social')
        );
    }
    
    /**
     * Get default templates
     */
    public static function get_default_templates() {
        return array(
            array(
                'name' => 'Product Launch',
                'description' => 'Announce a new product or service',
                'content' => "🚀 Exciting news! We're launching {PRODUCT_NAME}!\n\n{PRODUCT_DESCRIPTION}\n\n✨ Key features:\n{KEY_FEATURES}\n\n🔗 Learn more: {LINK}\n\n#ProductLaunch #Innovation #NewProduct",
                'category' => 'product_launch',
                'platforms' => array('twitter', 'facebook', 'linkedin', 'instagram')
            ),
            array(
                'name' => 'Blog Post Promotion',
                'description' => 'Share your latest blog post',
                'content' => "📝 New blog post alert!\n\n{BLOG_TITLE}\n\n{EXCERPT}\n\n👉 Read the full article: {LINK}\n\n#Blog #Content #Learning",
                'category' => 'blog_post',
                'platforms' => array('twitter', 'facebook', 'linkedin')
            ),
            array(
                'name' => 'Event Announcement',
                'description' => 'Promote an upcoming event',
                'content' => "📅 Save the date!\n\n{EVENT_NAME}\n📍 {LOCATION}\n🕐 {DATE_TIME}\n\n{EVENT_DESCRIPTION}\n\n🎟️ Register now: {REGISTRATION_LINK}\n\n#Event #Networking #Community",
                'category' => 'event',
                'platforms' => array('twitter', 'facebook', 'linkedin', 'instagram')
            ),
            array(
                'name' => 'Behind the Scenes',
                'description' => 'Show your company culture',
                'content' => "👀 Behind the scenes at {COMPANY_NAME}!\n\n{DESCRIPTION}\n\n{TEAM_HIGHLIGHT}\n\n#BehindTheScenes #CompanyCulture #TeamWork",
                'category' => 'behind_scenes',
                'platforms' => array('instagram', 'facebook', 'twitter')
            ),
            array(
                'name' => 'Customer Testimonial',
                'description' => 'Share customer success stories',
                'content' => "💬 What our customers are saying:\n\n\"{TESTIMONIAL}\"\n\n- {CUSTOMER_NAME}, {CUSTOMER_TITLE}\n\n{CALL_TO_ACTION}\n\n#CustomerSuccess #Testimonial #Review",
                'category' => 'testimonial',
                'platforms' => array('twitter', 'facebook', 'linkedin')
            ),
            array(
                'name' => 'Quick Tip',
                'description' => 'Share helpful tips and advice',
                'content' => "💡 {TIP_CATEGORY} Tip:\n\n{TIP_CONTENT}\n\n{ADDITIONAL_INFO}\n\n#Tips #HowTo #Learning",
                'category' => 'tip',
                'platforms' => array('twitter', 'facebook', 'linkedin', 'instagram')
            ),
            array(
                'name' => 'Special Offer',
                'description' => 'Promote a sale or discount',
                'content' => "🎉 Special Offer Alert!\n\n{OFFER_DESCRIPTION}\n\n💰 {DISCOUNT_DETAILS}\n⏰ Valid until: {EXPIRY_DATE}\n\n🛒 Shop now: {LINK}\n\n#Sale #Discount #SpecialOffer",
                'category' => 'promotion',
                'platforms' => array('twitter', 'facebook', 'instagram')
            ),
            array(
                'name' => 'Motivational Quote',
                'description' => 'Share inspiring quotes',
                'content' => "✨ {DAY_OF_WEEK} Motivation\n\n\"{QUOTE}\"\n\n- {AUTHOR}\n\n{PERSONAL_REFLECTION}\n\n#Motivation #Inspiration #Quote",
                'category' => 'quote',
                'platforms' => array('twitter', 'facebook', 'linkedin', 'instagram')
            )
        );
    }
    
    /**
     * Create default templates for a user
     */
    public static function create_default_templates($user_id = null) {
        $defaults = self::get_default_templates();
        $created = array();
        
        foreach ($defaults as $default) {
            $template_id = self::create_template($default, $user_id);
            
            if (!is_wp_error($template_id)) {
                $created[] = $template_id;
            }
        }
        
        return $created;
    }
    
    /**
     * Duplicate a template
     */
    public static function duplicate_template($template_id, $user_id = null) {
        $template = self::get_template($template_id);
        
        if (!$template) {
            return new \WP_Error('not_found', 'Template not found');
        }
        
        $new_data = array(
            'name' => $template['name'] . ' (Copy)',
            'description' => $template['description'],
            'content' => $template['content'],
            'platforms' => $template['platforms'],
            'category' => $template['category'],
            'metadata' => $template['metadata']
        );
        
        return self::create_template($new_data, $user_id);
    }
}
