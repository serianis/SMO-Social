<?php
/**
 * Media Processing and Image Handling Optimizations for SMO Social Plugin
 *
 * This file contains optimized media processing functions and strategies
 * to improve the performance of image handling, compression, and media operations
 * by implementing lazy loading, intelligent caching, and efficient processing.
 *
 * @package SMO_Social
 * @subpackage Performance_Optimizations
 * @since 1.0.0
 */

// Include WordPress functions and database stubs for Intelephense support
// These must be loaded before the namespace declaration
require_once __DIR__ . '/../../includes/wordpress-functions.php';
require_once __DIR__ . '/../../includes/global-declarations.php';
require_once __DIR__ . '/../../includes/type-stubs.php';

// Ensure direct file access is blocked
if (!defined('ABSPATH') && !defined('SMO_SOCIAL_STANDALONE')) {
    wp_die(__('Access denied', 'smo-social'));
}

namespace SMO_Social\Performance\Media;

/**
 * Media Processing Optimizations Class
 *
 * Contains optimized media processing functions for improved performance
 */
class MediaOptimizations {

    /**
     * Optimized image processing with intelligent compression and resizing
     *
     * @param string $image_path Path to the image file
     * @param array $sizes Array of sizes to generate
     * @param array $options Processing options
     * @return array Processing results
     */
    public static function process_image_optimized($image_path, $sizes = array(), $options = array()) {
        if (!file_exists($image_path)) {
            return array('error' => 'Image file not found');
        }

        $defaults = array(
            'quality' => 85,
            'compression' => true,
            'lazy_loading' => true,
            'webp_conversion' => true,
            'progressive_jpeg' => true
        );

        $options = array_merge($defaults, $options);

        $results = array(
            'original_size' => filesize($image_path),
            'processed_sizes' => array(),
            'processing_time' => 0,
            'compression_ratio' => 0
        );

        $start_time = microtime(true);

        // Get image info
        $image_info = getimagesize($image_path);
        if (!$image_info) {
            return array('error' => 'Invalid image file');
        }

        $mime_type = $image_info['mime'];
        $original_width = $image_info[0];
        $original_height = $image_info[1];

        // Default sizes if none provided
        if (empty($sizes)) {
            $sizes = array(
                'thumbnail' => array('width' => 150, 'height' => 150, 'crop' => true),
                'medium' => array('width' => 300, 'height' => 300, 'crop' => false),
                'large' => array('width' => 1024, 'height' => 1024, 'crop' => false)
            );
        }

        // Process each size
        foreach ($sizes as $size_name => $size_config) {
            $processed_image = self::process_single_size(
                $image_path,
                $size_name,
                $size_config,
                $options,
                $mime_type,
                $original_width,
                $original_height
            );

            if ($processed_image) {
                $results['processed_sizes'][$size_name] = $processed_image;
            }
        }

        // Generate WebP versions if enabled
        if ($options['webp_conversion'] && function_exists('imagewebp')) {
            $webp_results = self::generate_webp_versions($image_path, $sizes, $options);
            $results['webp_versions'] = $webp_results;
        }

        $results['processing_time'] = microtime(true) - $start_time;
        $total_processed_size = array_sum(array_column($results['processed_sizes'], 'size'));
        $results['compression_ratio'] = $results['original_size'] > 0 ?
            round(($results['original_size'] - $total_processed_size) / $results['original_size'] * 100, 2) : 0;

        return $results;
    }

    /**
     * Process a single image size with optimizations
     *
     * @param string $image_path Original image path
     * @param string $size_name Size name
     * @param array $size_config Size configuration
     * @param array $options Processing options
     * @param string $mime_type Image MIME type
     * @param int $original_width Original width
     * @param int $original_height Original height
     * @return array|null Processing result
     */
    private static function process_single_size($image_path, $size_name, $size_config, $options, $mime_type, $original_width, $original_height) {
        $cache_key = 'smo_image_' . md5($image_path . $size_name . serialize($size_config));
        $cache_ttl = 24 * 60 * 60; // 24 hours

        // Check cache
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // Calculate dimensions
        $target_width = $size_config['width'];
        $target_height = $size_config['height'];
        $crop = $size_config['crop'] ?? false;

        // Calculate aspect ratios
        $original_ratio = $original_width / $original_height;
        $target_ratio = $target_width / $target_height;

        if ($crop) {
            // Crop to exact dimensions
            $new_width = $target_width;
            $new_height = $target_height;
        } else {
            // Resize maintaining aspect ratio
            if ($original_ratio > $target_ratio) {
                $new_width = $target_width;
                $new_height = $target_width / $original_ratio;
            } else {
                $new_height = $target_height;
                $new_width = $target_height * $original_ratio;
            }
        }

        // Create resized image
        $resized_image = self::resize_image_gd(
            $image_path,
            $mime_type,
            $new_width,
            $new_height,
            $target_width,
            $target_height,
            $crop,
            $options
        );

        if (!$resized_image) {
            return null;
        }

        $result = array(
            'size_name' => $size_name,
            'width' => $target_width,
            'height' => $target_height,
            'file_path' => $resized_image['path'],
            'size' => $resized_image['size'],
            'mime_type' => $mime_type,
            'processed_at' => time()
        );

        // Cache result
        set_transient($cache_key, $result, $cache_ttl);

        return $result;
    }

    /**
     * Resize image using GD with optimizations
     *
     * @param string $image_path Image path
     * @param string $mime_type MIME type
     * @param int $new_width New width
     * @param int $new_height New height
     * @param int $target_width Target width
     * @param int $target_height Target height
     * @param bool $crop Whether to crop
     * @param array $options Processing options
     * @return array|null Resized image data
     */
    private static function resize_image_gd($image_path, $mime_type, $new_width, $new_height, $target_width, $target_height, $crop, $options) {
        // Create image resource based on type
        $image = null;
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($image_path);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($image_path);
                }
                break;
        }

        if (!$image) {
            return null;
        }

        // Create new image
        $new_image = imagecreatetruecolor($target_width, $target_height);

        // Preserve transparency for PNG
        if ($mime_type === 'image/png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefill($new_image, 0, 0, $transparent);
        }

        // Resize
        imagecopyresampled(
            $new_image, $image,
            0, 0, 0, 0,
            $target_width, $target_height,
            $new_width, $new_height
        );

        // Generate output path
        $output_dir = wp_upload_dir()['path'];
        $filename = pathinfo($image_path, PATHINFO_FILENAME);
        $extension = ($mime_type === 'image/jpeg') ? 'jpg' : substr($mime_type, 6);
        $output_path = $output_dir . '/' . $filename . '_' . $target_width . 'x' . $target_height . '.' . $extension;

        // Save image with optimizations
        $quality = $options['quality'];
        $saved = false;

        switch ($mime_type) {
            case 'image/jpeg':
                if ($options['progressive_jpeg']) {
                    imageinterlace($new_image, 1); // Enable progressive JPEG
                }
                $saved = imagejpeg($new_image, $output_path, $quality);
                break;
            case 'image/png':
                $quality = 9 - round($quality / 11.111); // Convert 0-100 to 0-9 for PNG
                $saved = imagepng($new_image, $output_path, $quality);
                break;
            case 'image/gif':
                $saved = imagegif($new_image, $output_path);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    $saved = imagewebp($new_image, $output_path, $quality);
                }
                break;
        }

        // Clean up memory
        imagedestroy($image);
        imagedestroy($new_image);

        if (!$saved) {
            return null;
        }

        return array(
            'path' => $output_path,
            'size' => filesize($output_path)
        );
    }

    /**
     * Generate WebP versions of images
     *
     * @param string $image_path Original image path
     * @param array $sizes Size configurations
     * @param array $options Processing options
     * @return array WebP generation results
     */
    private static function generate_webp_versions($image_path, $sizes, $options) {
        if (!function_exists('imagewebp')) {
            return array('error' => 'WebP support not available');
        }

        $results = array();
        $filename = pathinfo($image_path, PATHINFO_FILENAME);
        $output_dir = wp_upload_dir()['path'];

        foreach ($sizes as $size_name => $size_config) {
            $webp_path = $output_dir . '/' . $filename . '_' . $size_config['width'] . 'x' . $size_config['height'] . '.webp';

            // Create WebP version
            $image = null;
            $mime_type = getimagesize($image_path)['mime'];

            switch ($mime_type) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($image_path);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($image_path);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($image_path);
                    break;
            }

            if ($image) {
                // Resize if needed
                $resized_image = imagecreatetruecolor($size_config['width'], $size_config['height']);
                imagecopyresampled(
                    $resized_image, $image,
                    0, 0, 0, 0,
                    $size_config['width'], $size_config['height'],
                    imagesx($image), imagesy($image)
                );

                $saved = imagewebp($resized_image, $webp_path, $options['quality']);

                if ($saved) {
                    $results[$size_name] = array(
                        'path' => $webp_path,
                        'size' => filesize($webp_path),
                        'mime_type' => 'image/webp'
                    );
                }

                imagedestroy($image);
                imagedestroy($resized_image);
            }
        }

        return $results;
    }

    /**
     * Lazy load images with intersection observer
     *
     * @param string $image_html Original image HTML
     * @param array $options Lazy loading options
     * @return string Modified HTML with lazy loading
     */
    public static function lazy_load_image($image_html, $options = array()) {
        $defaults = array(
            'placeholder' => 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PC9zdmc+',
            'threshold' => '0px',
            'root_margin' => '50px'
        );

        $options = array_merge($defaults, $options);

        // Parse image HTML
        preg_match('/<img[^>]+>/i', $image_html, $matches);
        if (empty($matches)) {
            return $image_html;
        }

        $img_tag = $matches[0];

        // Extract src attribute
        preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_matches);
        if (empty($src_matches)) {
            return $image_html;
        }

        $src = $src_matches[1];

        // Add lazy loading attributes
        $lazy_img = str_replace(
            'src="' . $src . '"',
            'src="' . $options['placeholder'] . '" data-src="' . $src . '" loading="lazy"',
            $img_tag
        );

        // Add classes for intersection observer
        if (strpos($lazy_img, 'class="') !== false) {
            $lazy_img = str_replace('class="', 'class="lazy-image ', $lazy_img);
        } else {
            $lazy_img = str_replace('<img', '<img class="lazy-image"', $lazy_img);
        }

        return str_replace($img_tag, $lazy_img, $image_html);
    }

    /**
     * Optimize media library queries with caching
     *
     * @param array $args Query arguments
     * @return array Optimized media results
     */
    public static function get_media_optimized($args = array()) {
        $defaults = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 20,
            'paged' => 1
        );

        $args = array_merge($defaults, $args);

        $cache_key = 'smo_media_query_' . md5(serialize($args));
        $cache_ttl = 15 * 60; // 15 minutes

        // Check cache
        $cached_results = get_transient($cache_key);
        if ($cached_results !== false) {
            return $cached_results;
        }

        // Perform optimized query
        /** @var \WP_Query $query */
        $query = new \WP_Query($args);
        $results = array(
            'posts' => array(),
            'total' => $query->found_posts ?? 0,
            'max_pages' => $query->max_num_pages ?? 0,
            'current_page' => $args['paged'] ?? 1
        );

        // Process posts with optimized metadata loading
        foreach ($query->posts as $post) {
            $attachment_data = wp_get_attachment_metadata($post->ID);
            $results['posts'][] = array(
                'ID' => $post->ID,
                'title' => $post->post_title,
                'filename' => basename($post->guid),
                'url' => wp_get_attachment_url($post->ID),
                'mime_type' => $post->post_mime_type,
                'size' => $attachment_data['filesize'] ?? 0,
                'dimensions' => array(
                    'width' => $attachment_data['width'] ?? 0,
                    'height' => $attachment_data['height'] ?? 0
                ),
                'sizes' => $attachment_data['sizes'] ?? array()
            );
        }

        // Cache results
        set_transient($cache_key, $results, $cache_ttl);

        return $results;
    }

    /**
     * Batch process media files with progress tracking
     *
     * @param array $media_ids Array of media IDs to process
     * @param array $operations Operations to perform
     * @return array Processing results
     */
    public static function batch_process_media($media_ids, $operations = array()) {
        if (empty($media_ids)) {
            return array('error' => 'No media IDs provided');
        }

        $results = array(
            'processed' => 0,
            'failed' => 0,
            'errors' => array(),
            'processing_time' => 0
        );

        $start_time = microtime(true);
        $batch_size = 5; // Process 5 files at a time
        $batches = array_chunk($media_ids, $batch_size);

        foreach ($batches as $batch) {
            foreach ($batch as $media_id) {
                $result = self::process_single_media($media_id, $operations);

                if ($result['success']) {
                    $results['processed']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = array(
                        'media_id' => $media_id,
                        'error' => $result['error']
                    );
                }
            }

            // Small delay between batches to prevent overwhelming
            usleep(100000); // 0.1 seconds
        }

        $results['processing_time'] = microtime(true) - $start_time;

        return $results;
    }

    /**
     * Process a single media file
     *
     * @param int $media_id Media ID
     * @param array $operations Operations to perform
     * @return array Processing result
     */
    private static function process_single_media($media_id, $operations) {
        try {
            $file_path = get_attached_file($media_id);

            if (!$file_path || !file_exists($file_path)) {
                return array('success' => false, 'error' => 'File not found');
            }

            $results = array();

            foreach ($operations as $operation => $config) {
                switch ($operation) {
                    case 'resize':
                        $results['resize'] = self::process_image_optimized($file_path, $config['sizes'] ?? array(), $config['options'] ?? array());
                        break;

                    case 'compress':
                        $results['compress'] = self::compress_image($file_path, $config);
                        break;

                    case 'convert':
                        $results['convert'] = self::convert_image_format($file_path, $config);
                        break;
                }
            }

            return array('success' => true, 'results' => $results);

        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Compress image using various algorithms
     *
     * @param string $image_path Image path
     * @param array $options Compression options
     * @return array Compression result
     */
    private static function compress_image($image_path, $options = array()) {
        $defaults = array(
            'quality' => 85,
            'method' => 'gd' // gd, imagick, or webp
        );

        $options = array_merge($defaults, $options);
        $original_size = filesize($image_path);

        // For now, use GD compression (can be extended with ImageMagick)
        $image_info = getimagesize($image_path);
        if (!$image_info) {
            return array('error' => 'Invalid image');
        }

        $mime_type = $image_info['mime'];
        $compressed_path = $image_path . '.compressed';

        // Create compressed version
        $image = null;
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                imagejpeg($image, $compressed_path, $options['quality']);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                $quality = 9 - round($options['quality'] / 11.111);
                imagepng($image, $compressed_path, $quality);
                break;
        }

        if ($image) {
            imagedestroy($image);
        }

        $compressed_size = filesize($compressed_path);
        $compression_ratio = round(($original_size - $compressed_size) / $original_size * 100, 2);

        return array(
            'original_size' => $original_size,
            'compressed_size' => $compressed_size,
            'compression_ratio' => $compression_ratio,
            'path' => $compressed_path
        );
    }

    /**
     * Convert image to different format
     *
     * @param string $image_path Image path
     * @param array $options Conversion options
     * @return array Conversion result
     */
    private static function convert_image_format($image_path, $options = array()) {
        $defaults = array(
            'format' => 'webp',
            'quality' => 85
        );

        $options = array_merge($defaults, $options);

        if (!function_exists('imagewebp')) {
            return array('error' => 'WebP conversion not supported');
        }

        $image_info = getimagesize($image_path);
        if (!$image_info) {
            return array('error' => 'Invalid image');
        }

        $mime_type = $image_info['mime'];
        $output_path = $image_path . '.' . $options['format'];

        $image = null;
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($image_path);
                break;
        }

        if (!$image) {
            return array('error' => 'Could not load image');
        }

        $success = false;
        switch ($options['format']) {
            case 'webp':
                $success = imagewebp($image, $output_path, $options['quality']);
                break;
        }

        imagedestroy($image);

        if ($success) {
            return array(
                'original_format' => $mime_type,
                'new_format' => 'image/' . $options['format'],
                'path' => $output_path,
                'size' => filesize($output_path)
            );
        }

        return array('error' => 'Conversion failed');
    }

    /**
     * Clean up old cached media files
     *
     * @param int $max_age Maximum age in hours
     * @return array Cleanup results
     */
    public static function cleanup_cached_media($max_age = 24) {
        $upload_dir = wp_upload_dir()['basedir'];
        $cache_dirs = array(
            $upload_dir . '/smo-social/cache',
            $upload_dir . '/smo-social/optimized'
        );

        $results = array(
            'files_removed' => 0,
            'space_freed' => 0,
            'errors' => array()
        );

        $cutoff_time = time() - ($max_age * 3600);

        foreach ($cache_dirs as $cache_dir) {
            if (!file_exists($cache_dir)) {
                continue;
            }

            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff_time) {
                    $size = filesize($file);
                    if (@unlink($file)) {
                        $results['files_removed']++;
                        $results['space_freed'] += $size;
                    } else {
                        $results['errors'][] = 'Could not delete: ' . $file;
                    }
                }
            }
        }

        return $results;
    }
}
