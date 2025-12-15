<?php
/**
 * Data Validator
 * 
 * Provides centralized validation and default value merging for content and collaboration data.
 *
 * @package SMO_Social
 * @subpackage Content
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

class DataValidator {

    /**
     * Validate and normalize content idea data
     *
     * @param array $data Raw input data
     * @return array Validated data with defaults applied
     */
    public static function validate_content_idea(array $data): array {
        $defaults = [
            'title' => '',
            'description' => '',
            'content_type' => 'text',
            'target_platforms' => [],
            'tags' => [],
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'idea',
            'scheduled_date' => null,
            'user_id' => get_current_user_id(),
        ];

        $data = wp_parse_args($data, $defaults);

        // Sanitize and cast
        $data['title'] = sanitize_text_field($data['title']);
        $data['description'] = wp_kses_post($data['description']);
        $data['content_type'] = sanitize_text_field($data['content_type']);
        
        // Handle array fields
        if (!is_array($data['target_platforms'])) {
            $data['target_platforms'] = $data['target_platforms'] ? explode(',', (string)$data['target_platforms']) : [];
        }
        $data['target_platforms'] = array_map('sanitize_text_field', $data['target_platforms']);

        if (!is_array($data['tags'])) {
            $data['tags'] = $data['tags'] ? explode(',', (string)$data['tags']) : [];
        }
        $data['tags'] = array_map('sanitize_text_field', $data['tags']);

        $data['category'] = sanitize_text_field($data['category']);
        $data['priority'] = sanitize_text_field($data['priority']);
        $data['status'] = sanitize_text_field($data['status']);
        
        if (!empty($data['scheduled_date'])) {
            $data['scheduled_date'] = sanitize_text_field($data['scheduled_date']);
        } else {
            $data['scheduled_date'] = null;
        }

        $data['user_id'] = absint($data['user_id']);

        return $data;
    }

    /**
     * Validate and normalize content category data
     *
     * @param array $data Raw input data
     * @return array Validated data with defaults applied
     */
    public static function validate_content_category(array $data): array {
        $defaults = [
            'name' => '',
            'description' => '',
            'color_code' => '#007cba',
            'icon' => 'dashicons-category',
            'parent_id' => null,
            'sort_order' => 0,
            'is_default' => false,
            'is_active' => true,
        ];

        $data = wp_parse_args($data, $defaults);

        $data['name'] = sanitize_text_field($data['name']);
        
        // Ensure name is not empty
        if (empty($data['name'])) {
            throw new \InvalidArgumentException(__('Category name is required', 'smo-social'));
        }

        $data['description'] = sanitize_textarea_field($data['description']);
        
        $color = sanitize_hex_color($data['color_code']);
        $data['color_code'] = $color ? $color : '#007cba';
        
        $data['icon'] = sanitize_text_field($data['icon']);
        
        $data['parent_id'] = !empty($data['parent_id']) ? absint($data['parent_id']) : null;
        $data['sort_order'] = intval($data['sort_order']);
        $data['is_default'] = (bool)$data['is_default'];
        $data['is_active'] = (bool)$data['is_active'];

        return $data;
    }

    /**
     * Validate and normalize collaboration submission data
     *
     * @param array $data Raw input data
     * @return array Validated data with defaults applied
     */
    public static function validate_collaboration_submission(array $data): array {
        $defaults = [
            'post_id' => 0,
            'content' => '',
            'media' => [],
            'platforms' => [],
            'scheduled_time' => null,
            'hashtags' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        $data['post_id'] = absint($data['post_id']);
        if (empty($data['post_id'])) {
            throw new \InvalidArgumentException(__('Post ID is required', 'smo-social'));
        }

        $data['content'] = sanitize_textarea_field($data['content']);
        
        if (!is_array($data['media'])) {
            $data['media'] = [];
        }
        $data['media'] = array_map('esc_url_raw', $data['media']);
        
        if (!is_array($data['platforms'])) {
            $data['platforms'] = [];
        }
        $data['platforms'] = array_map('sanitize_text_field', $data['platforms']);
        
        $data['scheduled_time'] = !empty($data['scheduled_time']) ? sanitize_text_field($data['scheduled_time']) : null;

        if (!is_array($data['hashtags'])) {
            $data['hashtags'] = [];
        }
        $data['hashtags'] = array_map('sanitize_text_field', $data['hashtags']);

        return $data;
    }
}
