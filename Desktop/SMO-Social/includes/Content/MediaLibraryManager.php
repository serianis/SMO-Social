<?php
/**
 * Media Library Manager for SMO Social
 * 
 * Handles all media library operations including:
 * - WordPress media library integration
 * - Advanced filtering and searching
 * - Batch sharing operations
 * - Social media platform integration
 * - Image optimization for different platforms
 *
 * @package SMO_Social
 * @subpackage Content
 * @since 1.0.0
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

// Include WordPress function stubs and dependencies
require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * Media Library Manager Class
 * 
 * Manages all media library operations for social media sharing
 */
class MediaLibraryManager
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // AJAX handlers
        add_action('wp_ajax_smo_get_media_library', array($this, 'ajax_get_media_library'));
        add_action('wp_ajax_smo_share_selected_images', array($this, 'ajax_share_selected_images'));
        add_action('wp_ajax_smo_preview_share', array($this, 'ajax_preview_share'));
        add_action('wp_ajax_smo_generate_hashtags', array($this, 'ajax_generate_hashtags'));
        add_action('wp_ajax_smo_save_edited_image', array($this, 'ajax_save_edited_image'));

        // Admin menu integration
        add_action('admin_menu', array($this, 'add_media_library_menu'));
    }

    /**
     * Add Media Library menu item
     */
    public function add_media_library_menu()
    {
        // Add to main SMO Social menu
        \add_submenu_page(
            'smo-social',
            __('Media Library', 'smo-social'),
            __('Media Library', 'smo-social'),
            'edit_posts',
            'smo-social-media-library',
            array($this, 'render_media_library_page')
        );
    }

    /**
     * Render the media library page
     */
    public function render_media_library_page()
    {
        if (class_exists('\SMO_Social\Admin\Views\MediaLibrary')) {
            $mediaLibraryView = new \SMO_Social\Admin\Views\MediaLibrary();
            $mediaLibraryView->render();
        } else {
            echo '<div class="wrap"><h1>Media Library</h1><p>Loading...</p></div>';
        }
    }

    /**
     * Get media library items with filtering
     *
     * @param array $args Query arguments
     * @return array Media items and pagination info
     */
    public function get_media_library($args = array())
    {
        // Default arguments
        $defaults = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 24,
            'paged' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_mime_type' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
            's' => '', // search term
            'meta_query' => array(),
            'tax_query' => array()
        );

        $args = wp_parse_args($args, $defaults);

        // Build the query
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['posts_per_page'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'post_mime_type' => $args['post_mime_type']
        );

        // Add search
        if (!empty($args['s'])) {
            $query_args['s'] = $args['s'];
        }

        // Add meta query for additional filtering
        if (!empty($args['meta_query'])) {
            $query_args['meta_query'] = $args['meta_query'];
        }

        // Execute query
        $query = new \WP_Query($query_args);

        // Process results
        $media_items = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $item = $this->format_media_item(\get_the_ID());
                if ($item) {
                    $media_items[] = $item;
                }
            }
            \wp_reset_postdata();
        }

        // Calculate pagination
        $total_pages = $query->max_num_pages;
        $total_found = $query->found_posts;

        return array(
            'media' => $media_items,
            'total_pages' => $total_pages,
            'total_found' => $total_found,
            'current_page' => $args['paged']
        );
    }

    /**
     * Format a media item for output
     *
     * @param int $attachment_id WordPress attachment ID
     * @return array|null Formatted media item data
     */
    private function format_media_item($attachment_id)
    {
        $attachment = \get_post($attachment_id);
        if (!$attachment) {
            return null;
        }

        $metadata = \wp_get_attachment_metadata($attachment_id);
        $thumbnail_url = \wp_get_attachment_image_url($attachment_id, 'thumbnail');
        $full_url = \wp_get_attachment_url($attachment_id);

        // Calculate file size
        $file_size = '';
        if (isset($metadata['filesize'])) {
            $file_size = \size_format($metadata['filesize']);
        } elseif (\file_exists(\get_attached_file($attachment_id))) {
            $file_size = \size_format(\filesize(\get_attached_file($attachment_id)));
        }

        // Calculate dimensions
        $dimensions = '';
        if (isset($metadata['width']) && isset($metadata['height'])) {
            $dimensions = $metadata['width'] . '×' . $metadata['height'];
        }

        // Format date
        $formatted_date = \date_i18n(\get_option('date_format'), \strtotime($attachment->post_date));

        return array(
            'id' => $attachment_id,
            'title' => $attachment->post_title,
            'alt_text' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'description' => $attachment->post_content,
            'filename' => basename($attachment->guid),
            'url' => $full_url,
            'thumbnail' => $thumbnail_url,
            'size' => $file_size,
            'dimensions' => $dimensions,
            'mime_type' => $attachment->post_mime_type,
            'date' => $formatted_date,
            'uploaded_date' => $attachment->post_date,
            'width' => $metadata['width'] ?? 0,
            'height' => $metadata['height'] ?? 0
        );
    }

    /**
     * Apply filters to media library query
     *
     * @param array $filters Filter parameters
     * @return array Processed query arguments
     */
    private function apply_filters($filters)
    {
        $query_args = array();

        // Search
        if (!empty($filters['search'])) {
            $query_args['s'] = sanitize_text_field($filters['search']);
        }

        // Date range
        if (!empty($filters['date_range'])) {
            $date_query = array();
            $current_time = current_time('mysql');

            switch ($filters['date_range']) {
                case 'today':
                    $date_query = array(
                        array(
                            'after' => date('Y-m-d 00:00:00'),
                            'before' => date('Y-m-d 23:59:59'),
                            'inclusive' => true
                        )
                    );
                    break;

                case 'week':
                    $date_query = array(
                        array(
                            'after' => date('Y-m-d 00:00:00', strtotime('-7 days')),
                            'before' => $current_time
                        )
                    );
                    break;

                case 'month':
                    $date_query = array(
                        array(
                            'after' => date('Y-m-01 00:00:00'),
                            'before' => $current_time
                        )
                    );
                    break;

                case '3months':
                    $date_query = array(
                        array(
                            'after' => date('Y-m-d 00:00:00', strtotime('-3 months')),
                            'before' => $current_time
                        )
                    );
                    break;

                case 'year':
                    $date_query = array(
                        array(
                            'after' => date('Y-01-01 00:00:00'),
                            'before' => $current_time
                        )
                    );
                    break;
            }

            if (!empty($date_query)) {
                $query_args['date_query'] = $date_query;
            }
        }

        // File types
        if (!empty($filters['file_types']) && is_array($filters['file_types'])) {
            $query_args['post_mime_type'] = $filters['file_types'];
        }

        // Sort options
        if (!empty($filters['sort_by'])) {
            switch ($filters['sort_by']) {
                case 'date_asc':
                    $query_args['orderby'] = 'date';
                    $query_args['order'] = 'ASC';
                    break;
                case 'name':
                    $query_args['orderby'] = 'title';
                    $query_args['order'] = 'ASC';
                    break;
                case 'name_desc':
                    $query_args['orderby'] = 'title';
                    $query_args['order'] = 'DESC';
                    break;
                case 'size':
                    $query_args['orderby'] = 'menu_order';
                    $query_args['order'] = 'ASC';
                    break;
                case 'size_desc':
                    $query_args['orderby'] = 'menu_order';
                    $query_args['order'] = 'DESC';
                    break;
                default:
                    $query_args['orderby'] = 'date';
                    $query_args['order'] = 'DESC';
            }
        }

        // Per page
        if (!empty($filters['per_page'])) {
            $query_args['posts_per_page'] = intval($filters['per_page']);
        }

        return $query_args;
    }

    /**
     * Share selected images to social media platforms
     *
     * @param array $image_ids Array of attachment IDs
     * @param array $sharing_options Sharing configuration
     * @return array Result of sharing operation
     */
    public function share_selected_images($image_ids, $sharing_options)
    {
        try {
            $results = array(
                'success' => array(),
                'failed' => array()
            );

            // Validate input
            if (empty($image_ids) || !is_array($image_ids)) {
                throw new \Exception('No images selected for sharing');
            }

            if (empty($sharing_options['platforms'])) {
                throw new \Exception('No platforms selected for sharing');
            }

            // Process images based on sharing method
            $sharing_method = $sharing_options['sharing_method'] ?? 'individual';

            if ($sharing_method === 'carousel' && count($image_ids) > 1) {
                // Create carousel/gallery post
                $result = $this->create_carousel_post($image_ids, $sharing_options);
                if ($result['success']) {
                    $results['success'][] = $result;
                } else {
                    $results['failed'][] = $result;
                }
            } else {
                // Create individual posts for each image
                foreach ($image_ids as $image_id) {
                    $result = $this->create_individual_post($image_id, $sharing_options);
                    if ($result['success']) {
                        $results['success'][] = $result;
                    } else {
                        $results['failed'][] = $result;
                    }
                }
            }

            return $results;

        } catch (\Exception $e) {
            return array(
                'success' => array(),
                'failed' => array(),
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Create an individual post for a single image
     *
     * @param int $image_id WordPress attachment ID
     * @param array $sharing_options Sharing configuration
     * @return array Post creation result
     */
    private function create_individual_post($image_id, $sharing_options)
    {
        // Get image data
        $image_data = $this->format_media_item($image_id);
        if (!$image_data) {
            return array(
                'success' => false,
                'image_id' => $image_id,
                'error' => 'Image not found'
            );
        }

        // Prepare content
        $content = $this->prepare_post_content($sharing_options);

        // Prepare media URLs
        $media_urls = array(
            array(
                'type' => 'image',
                'url' => $image_data['url'],
                'thumbnail' => $image_data['thumbnail'],
                'alt_text' => $image_data['alt_text'] ?? $image_data['title']
            )
        );

        // Schedule or publish
        $schedule_type = $sharing_options['schedule_type'] ?? 'now';
        $scheduled_time = null;

        if ($schedule_type === 'scheduled') {
            $scheduled_time = $sharing_options['scheduled_time'] ?? null;
        } elseif ($schedule_type === 'queue') {
            $scheduled_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
        }

        // Create scheduled posts for each platform
        $platform_results = array();
        foreach ($sharing_options['platforms'] as $platform) {
            try {
                $post_result = $this->schedule_post_for_platform(
                    $platform,
                    $content,
                    $media_urls,
                    $scheduled_time,
                    $sharing_options
                );

                $platform_results[$platform] = $post_result;

            } catch (\Exception $e) {
                $platform_results[$platform] = array(
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }

        return array(
            'success' => true,
            'image_id' => $image_id,
            'platforms' => $platform_results
        );
    }

    /**
     * Create a carousel/gallery post for multiple images
     *
     * @param array $image_ids Array of attachment IDs
     * @param array $sharing_options Sharing configuration
     * @return array Post creation result
     */
    private function create_carousel_post($image_ids, $sharing_options)
    {
        $media_urls = array();

        // Prepare all images
        foreach ($image_ids as $image_id) {
            $image_data = $this->format_media_item($image_id);
            if ($image_data) {
                $media_urls[] = array(
                    'type' => 'image',
                    'url' => $image_data['url'],
                    'thumbnail' => $image_data['thumbnail'],
                    'alt_text' => $image_data['alt_text'] ?? $image_data['title']
                );
            }
        }

        if (empty($media_urls)) {
            return array(
                'success' => false,
                'error' => 'No valid images found'
            );
        }

        // Prepare content
        $content = $this->prepare_post_content($sharing_options);

        // Schedule or publish
        $schedule_type = $sharing_options['schedule_type'] ?? 'now';
        $scheduled_time = null;

        if ($schedule_type === 'scheduled') {
            $scheduled_time = $sharing_options['scheduled_time'] ?? null;
        } elseif ($schedule_type === 'queue') {
            $scheduled_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
        }

        // Create posts for each platform
        $platform_results = array();
        foreach ($sharing_options['platforms'] as $platform) {
            try {
                $post_result = $this->schedule_post_for_platform(
                    $platform,
                    $content,
                    $media_urls,
                    $scheduled_time,
                    $sharing_options
                );

                $platform_results[$platform] = $post_result;

            } catch (\Exception $e) {
                $platform_results[$platform] = array(
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }

        return array(
            'success' => true,
            'image_count' => count($media_urls),
            'platforms' => $platform_results
        );
    }

    /**
     * Schedule a post for a specific platform
     *
     * @param string $platform Platform slug
     * @param string $content Post content
     * @param array $media_urls Media URLs
     * @param string|null $scheduled_time Scheduled time
     * @param array $options Additional options
     * @return array Post creation result
     */
    private function schedule_post_for_platform($platform, $content, $media_urls, $scheduled_time, $options)
    {
        // Use the existing scheduler
        if (class_exists('\SMO_Social\Scheduling\Scheduler')) {
            $scheduler = new \SMO_Social\Scheduling\Scheduler();

            $post_options = array(
                'media' => $media_urls,
                'priority' => $options['priority'] ?? 'normal',
                'template_id' => $options['template_id'] ?? null
            );

            $post_title = $options['title'] ?? 'Social Media Post';

            if ($scheduled_time) {
                $post_id = $scheduler->schedule_post(
                    $post_title,
                    $content,
                    array($platform),
                    $scheduled_time,
                    $post_options
                );
            } else {
                // Post immediately - use a very recent time
                $post_id = $scheduler->schedule_post(
                    $post_title,
                    $content,
                    array($platform),
                    date('Y-m-d H:i:s', strtotime('+1 minute')),
                    $post_options
                );
            }

            return array(
                'success' => true,
                'post_id' => $post_id
            );

        } else {
            throw new \Exception('Scheduler not available');
        }
    }

    /**
     * Prepare post content with hashtags and template
     *
     * @param array $sharing_options Sharing options
     * @return string Prepared content
     */
    private function prepare_post_content($sharing_options)
    {
        $content = $sharing_options['content'] ?? '';

        // Add hashtags
        if (!empty($sharing_options['hashtags'])) {
            $hashtags = array_map('trim', explode(' ', $sharing_options['hashtags']));
            $valid_hashtags = array();

            foreach ($hashtags as $hashtag) {
                if (strpos($hashtag, '#') === 0) {
                    $valid_hashtags[] = $hashtag;
                } else {
                    $valid_hashtags[] = '#' . $hashtag;
                }
            }

            if (!empty($valid_hashtags)) {
                $content .= "\n\n" . implode(' ', $valid_hashtags);
            }
        }

        // Apply template if specified
        if (!empty($sharing_options['template_id'])) {
            $content = $this->apply_template($content, $sharing_options['template_id']);
        }

        return $content;
    }

    /**
     * Apply template to content
     *
     * @param string $content Post content
     * @param int $template_id Template ID
     * @return string Templated content
     */
    private function apply_template($content, $template_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_enhanced_templates';
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ));

        if ($template && !empty($template->content_template)) {
            // Simple template replacement - could be enhanced with more sophisticated templating
            $template_content = $template->content_template;

            // Replace {content} placeholder
            $template_content = str_replace('{content}', $content, $template_content);

            return $template_content;
        }

        return $content;
    }

    /**
     * Generate hashtags based on content
     *
     * @param string $content Content to analyze
     * @return array Generated hashtags
     */
    public function generate_hashtags($content)
    {
        // Simple hashtag generation - could be enhanced with AI
        $words = str_word_count(strtolower($content), 1);
        $common_words = array('the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'a', 'an');

        // Filter out common words and short words
        $filtered_words = array_filter($words, function ($word) use ($common_words) {
            return strlen($word) > 3 && !in_array($word, $common_words);
        });

        // Create hashtags
        $hashtags = array();
        foreach (array_slice($filtered_words, 0, 10) as $word) {
            $hashtags[] = '#' . $word;
        }

        // Add some default hashtags based on content length
        if (strlen($content) > 100) {
            $hashtags[] = '#socialmedia';
        }
        if (strlen($content) > 200) {
            $hashtags[] = '#marketing';
        }

        return array_slice(array_unique($hashtags), 0, 15);
    }

    /**
     * AJAX: Get media library
     */
    public function ajax_get_media_library()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $filters = array(
                'search' => sanitize_text_field($_POST['search'] ?? ''),
                'date_range' => sanitize_text_field($_POST['date_range'] ?? ''),
                'file_types' => array_map('sanitize_text_field', $_POST['file_types'] ?? array()),
                'file_size' => sanitize_text_field($_POST['file_size'] ?? ''),
                'dimensions' => array_map('sanitize_text_field', $_POST['dimensions'] ?? array()),
                'sort_by' => sanitize_text_field($_POST['sort_by'] ?? 'date'),
                'per_page' => intval($_POST['per_page'] ?? 24)
            );

            $page = intval($_POST['page'] ?? 1);

            $query_args = $this->apply_filters($filters);
            $query_args['paged'] = $page;

            $results = $this->get_media_library($query_args);

            wp_send_json_success($results);

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Share selected images
     */
    public function ajax_share_selected_images()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $image_ids = array_map('intval', $_POST['image_ids'] ?? array());
            $sharing_options = array(
                'content' => sanitize_textarea_field($_POST['content'] ?? ''),
                'hashtags' => sanitize_text_field($_POST['hashtags'] ?? ''),
                'platforms' => array_map('sanitize_text_field', $_POST['platforms'] ?? array()),
                'sharing_method' => sanitize_text_field($_POST['sharing_method'] ?? 'individual'),
                'schedule_type' => sanitize_text_field($_POST['schedule_type'] ?? 'now'),
                'scheduled_time' => sanitize_text_field($_POST['scheduled_time'] ?? ''),
                'template_id' => intval($_POST['template_id'] ?? 0) ?: null,
                'priority' => sanitize_text_field($_POST['priority'] ?? 'normal')
            );

            $results = $this->share_selected_images($image_ids, $sharing_options);

            wp_send_json_success($results);

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Preview share post
     */
    public function ajax_preview_share()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $image_url = esc_url_raw($_POST['image_url'] ?? '');
            $content = sanitize_textarea_field($_POST['content'] ?? '');
            $hashtags = sanitize_text_field($_POST['hashtags'] ?? '');
            $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());

            $preview_content = $this->generate_preview($image_url, $content, $hashtags, $platforms);

            wp_send_json_success(array('html' => $preview_content));

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Generate hashtags
     */
    public function ajax_generate_hashtags()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $content = sanitize_textarea_field($_POST['content'] ?? '');

            if (empty($content)) {
                wp_send_json_error('Content is required for hashtag generation');
            }

            $hashtags = $this->generate_hashtags($content);

            wp_send_json_success(array('hashtags' => $hashtags));

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Generate preview HTML for social media posts
     *
     * @param string $image_url Image URL
     * @param string $content Post content
     * @param string $hashtags Hashtags
     * @param array $platforms Target platforms
     * @return string Preview HTML
     */
    private function generate_preview($image_url, $content, $hashtags, $platforms)
    {
        $hashtag_array = array_map('trim', explode(' ', $hashtags));
        $hashtag_html = !empty($hashtag_array) ? '<div class="smo-preview-hashtags">' . implode(' ', array_map(function ($tag) {
            return '<span class="smo-hashtag">' . esc_html($tag) . '</span>';
        }, $hashtag_array)) . '</div>' : '';

        return '
        <div class="smo-post-preview">
            <div class="smo-preview-image">
                <img src="' . esc_url($image_url) . '" alt="Preview image" style="max-width: 100%; height: auto;">
            </div>
            <div class="smo-preview-content">
                <p>' . esc_html($content) . '</p>
                ' . $hashtag_html . '
                <div class="smo-preview-platforms">
                    <small>Preview for: ' . esc_html(implode(', ', $platforms)) . '</small>
                </div>
            </div>
        </div>';
    }

    /**
     * AJAX: Save edited image
     */
    public function ajax_save_edited_image()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $image_id = intval($_POST['image_id'] ?? 0);
            $image_data = $_POST['image_data'] ?? '';
            $filename = sanitize_file_name($_POST['filename'] ?? 'edited-image.jpg');

            if (empty($image_data)) {
                wp_send_json_error('No image data received');
            }

            // Remove data:image/jpeg;base64, prefix
            $image_data = preg_replace('/^data:image\/\w+;base64,/', '', $image_data);
            $decoded_data = base64_decode($image_data);

            if ($decoded_data === false) {
                wp_send_json_error('Failed to decode image data');
            }

            // Create a unique filename
            $upload_dir = wp_upload_dir();
            $filename_parts = pathinfo($filename);
            $name = $filename_parts['filename'];
            $ext = $filename_parts['extension'] ?? 'jpg';

            // Add timestamp to ensure uniqueness
            $unique_filename = $name . '-edited-' . time() . '.' . $ext;
            $file_path = $upload_dir['path'] . '/' . $unique_filename;

            // Save file
            if (file_put_contents($file_path, $decoded_data) === false) {
                wp_send_json_error('Failed to save file to server');
            }

            // Check file type
            $wp_filetype = wp_check_filetype($unique_filename, null);

            // Prepare attachment data
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $unique_filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Insert attachment
            $attach_id = wp_insert_attachment($attachment, $file_path);

            // Generate metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            wp_send_json_success(array(
                'id' => $attach_id,
                'url' => wp_get_attachment_url($attach_id),
                'message' => 'Image saved successfully'
            ));

        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
