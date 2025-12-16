<?php
/**
 * Media Library View for SMO Social
 * 
 * Professional media library interface for browsing, selecting, and sharing images
 * from WordPress media library with advanced features for social media sharing.
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include WordPress function stubs for compatibility
require_once __DIR__ . '/../../wordpress-functions.php';

if (function_exists('wp_enqueue_media') && function_exists('wp_enqueue_script')) {
    // Enqueue media library scripts and styles
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-sortable');
    
    // Define plugin URL constant if not available
    if (!defined('SMO_SOCIAL_PLUGIN_URL')) {
        define('SMO_SOCIAL_PLUGIN_URL', plugin_dir_url(__FILE__) . '../../..//');
    }
    
    // Define plugin version constant if not available
    if (!defined('SMO_SOCIAL_VERSION')) {
        define('SMO_SOCIAL_VERSION', '2.0.0');
    }
    
    // Add timestamp for cache busting
    $timestamp = time();
    
    // Enqueue media library specific scripts
    // CSS and JS enqueuing moved to AssetManager for centralized management
    
    // Localize script with AJAX settings
    if (function_exists('wp_localize_script') && function_exists('admin_url') && function_exists('wp_create_nonce')) {
        wp_localize_script('smo-media-library', 'smoMediaLibrary', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_social_nonce'),
            'strings' => [
                'select_images' => __('Select Images', 'smo-social'),
                'share_selected' => __('Share Selected', 'smo-social'),
                'add_to_queue' => __('Add to Queue', 'smo-social'),
                'schedule_posts' => __('Schedule Posts', 'smo-social'),
                'bulk_schedule' => __('Bulk Schedule', 'smo-social'),
                'preview' => __('Preview', 'smo-social'),
                'edit_image' => __('Edit Image', 'smo-social'),
                'delete_image' => __('Delete', 'smo-social'),
                'loading' => __('Loading...', 'smo-social'),
                'no_images_found' => __('No images found', 'smo-social'),
                'select_at_least_one' => __('Please select at least one image', 'smo-social'),
                'processing' => __('Processing...', 'smo-social'),
                'success' => __('Success!', 'smo-social'),
                'error' => __('Error occurred', 'smo-social')
            ]
        ]);
    }
}

// Get available platforms for sharing
$available_platforms = [
    'twitter' => 'Twitter',
    'facebook' => 'Facebook', 
    'linkedin' => 'LinkedIn',
    'instagram' => 'Instagram',
    'youtube' => 'YouTube',
    'tiktok' => 'TikTok',
    'pinterest' => 'Pinterest',
    'snapchat' => 'Snapchat'
];

// Get user's saved templates
$templates = [];
try {
    if (class_exists('\SMO_Social\Admin\Admin')) {
        $admin = new \SMO_Social\Admin\Admin();
        if (method_exists($admin, 'get_available_templates')) {
            $templates = $admin->get_available_templates();
        }
    }
} catch (\Exception $e) {
    error_log('SMO Social: Error getting templates: ' . $e->getMessage());
    $templates = [];
}
?>

<div class="wrap smo-media-library">
    <div class="smo-media-library-header">
        <h1><?php _e('Media Library for Social Sharing', 'smo-social'); ?></h1>
        <div class="smo-media-actions">
            <button type="button" class="button" id="smo-upload-images">
                <?php _e('Upload Images', 'smo-social'); ?>
            </button>
            <button type="button" class="button button-primary" id="smo-share-selected" disabled>
                <?php _e('Share Selected Images', 'smo-social'); ?>
            </button>
            <button type="button" class="button" id="smo-bulk-schedule" disabled>
                <?php _e('Bulk Schedule', 'smo-social'); ?>
            </button>
        </div>
    </div>

    <div class="smo-media-library-layout">
        <!-- Sidebar Filters -->
        <div class="smo-media-sidebar">
            <div class="smo-filter-section">
                <h3><?php _e('Search & Filter', 'smo-social'); ?></h3>
                <div class="smo-search-box">
                    <input type="text" id="smo-media-search" placeholder="<?php _e('Search images...', 'smo-social'); ?>" class="widefat">
                    <button type="button" id="smo-clear-search" class="button">×</button>
                </div>
            </div>

            <div class="smo-filter-section">
                <h3><?php _e('Date Range', 'smo-social'); ?></h3>
                <select id="smo-date-filter" class="widefat">
                    <option value=""><?php _e('All dates', 'smo-social'); ?></option>
                    <option value="today"><?php _e('Today', 'smo-social'); ?></option>
                    <option value="week"><?php _e('This week', 'smo-social'); ?></option>
                    <option value="month"><?php _e('This month', 'smo-social'); ?></option>
                    <option value="3months"><?php _e('Last 3 months', 'smo-social'); ?></option>
                    <option value="year"><?php _e('This year', 'smo-social'); ?></option>
                </select>
            </div>

            <div class="smo-filter-section">
                <h3><?php _e('File Type', 'smo-social'); ?></h3>
                <div class="smo-file-types">
                    <label><input type="checkbox" value="image/jpeg" checked> JPEG</label>
                    <label><input type="checkbox" value="image/png" checked> PNG</label>
                    <label><input type="checkbox" value="image/gif" checked> GIF</label>
                    <label><input type="checkbox" value="image/webp" checked> WebP</label>
                </div>
            </div>

            <div class="smo-filter-section">
                <h3><?php _e('File Size', 'smo-social'); ?></h3>
                <select id="smo-size-filter" class="widefat">
                    <option value=""><?php _e('All sizes', 'smo-social'); ?></option>
                    <option value="small">< 1MB</option>
                    <option value="medium">1MB - 5MB</option>
                    <option value="large">5MB - 10MB</option>
                    <option value="xlarge">> 10MB</option>
                </select>
            </div>

            <div class="smo-filter-section">
                <h3><?php _e('Dimensions', 'smo-social'); ?></h3>
                <div class="smo-dimension-filters">
                    <label><input type="checkbox" value="square"> <?php _e('Square (1:1)', 'smo-social'); ?></label>
                    <label><input type="checkbox" value="landscape"> <?php _e('Landscape', 'smo-social'); ?></label>
                    <label><input type="checkbox" value="portrait"> <?php _e('Portrait', 'smo-social'); ?></label>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="smo-media-main">
            <!-- View Controls -->
            <div class="smo-view-controls">
                <div class="smo-view-options">
                    <button type="button" class="button smo-view-btn active" data-view="grid">
                        <span class="dashicons dashicons-grid-view"></span> <?php _e('Grid', 'smo-social'); ?>
                    </button>
                    <button type="button" class="button smo-view-btn" data-view="list">
                        <span class="dashicons dashicons-list-view"></span> <?php _e('List', 'smo-social'); ?>
                    </button>
                </div>
                
                <div class="smo-sort-options">
                    <select id="smo-sort-by" class="widefat">
                        <option value="date"><?php _e('Date (newest first)', 'smo-social'); ?></option>
                        <option value="date_asc"><?php _e('Date (oldest first)', 'smo-social'); ?></option>
                        <option value="name"><?php _e('Name (A-Z)', 'smo-social'); ?></option>
                        <option value="name_desc"><?php _e('Name (Z-A)', 'smo-social'); ?></option>
                        <option value="size"><?php _e('File size', 'smo-social'); ?></option>
                        <option value="size_desc"><?php _e('File size (largest)', 'smo-social'); ?></option>
                    </select>
                </div>
                
                <div class="smo-pagination-info">
                    <span id="smo-results-count">0 <?php _e('images found', 'smo-social'); ?></span>
                </div>
            </div>

            <!-- Media Grid/List -->
            <div class="smo-media-container">
                <div id="smo-media-grid" class="smo-media-grid">
                    <!-- Media items will be loaded here via AJAX -->
                    <div class="smo-loading-state">
                        <div class="smo-spinner"></div>
                        <p><?php _e('Loading media library...', 'smo-social'); ?></p>
                    </div>
                </div>

                <div id="smo-media-list" class="smo-media-list" style="display: none;">
                    <!-- List view will be rendered here -->
                </div>
            </div>

            <!-- Pagination -->
            <div class="smo-pagination">
                <div class="smo-pagination-controls">
                    <button type="button" id="smo-prev-page" class="button" disabled>
                        <?php _e('Previous', 'smo-social'); ?>
                    </button>
                    <span id="smo-page-info" class="smo-page-info">
                        <?php _e('Page 1 of 1', 'smo-social'); ?>
                    </span>
                    <button type="button" id="smo-next-page" class="button" disabled>
                        <?php _e('Next', 'smo-social'); ?>
                    </button>
                </div>
                <div class="smo-per-page">
                    <select id="smo-per-page">
                        <option value="12">12 <?php _e('per page', 'smo-social'); ?></option>
                        <option value="24" selected>24 <?php _e('per page', 'smo-social'); ?></option>
                        <option value="48">48 <?php _e('per page', 'smo-social'); ?></option>
                        <option value="96">96 <?php _e('per page', 'smo-social'); ?></option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Selection Info Bar -->
<div id="smo-selection-bar" class="smo-selection-bar" style="display: none;">
    <div class="smo-selection-info">
        <span id="smo-selected-count">0 <?php _e('images selected', 'smo-social'); ?></span>
    </div>
    <div class="smo-selection-actions">
        <button type="button" class="button" id="smo-clear-selection">
            <?php _e('Clear Selection', 'smo-social'); ?>
        </button>
        <button type="button" class="button button-primary" id="smo-share-from-bar">
            <?php _e('Share Selected', 'smo-social'); ?>
        </button>
    </div>
</div>

<!-- Share Modal -->
<div id="smo-share-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <div class="smo-modal-header">
            <h3><?php _e('Share Images to Social Media', 'smo-social'); ?></h3>
            <button type="button" class="smo-modal-close">&times;</button>
        </div>
        <div class="smo-modal-body">
            <form id="smo-share-form">
                <div class="smo-form-section">
                    <h4><?php _e('Selected Images', 'smo-social'); ?></h4>
                    <div id="smo-selected-images-preview" class="smo-selected-preview">
                        <!-- Selected images will be shown here -->
                    </div>
                </div>

                <div class="smo-form-section">
                    <h4><?php _e('Sharing Options', 'smo-social'); ?></h4>
                    
                    <div class="smo-form-field">
                        <label><?php _e('Sharing Method', 'smo-social'); ?></label>
                        <div class="smo-sharing-methods">
                            <label>
                                <input type="radio" name="sharing_method" value="individual" checked>
                                <?php _e('Create individual posts for each image', 'smo-social'); ?>
                            </label>
                            <label>
                                <input type="radio" name="sharing_method" value="carousel">
                                <?php _e('Create carousel/gallery posts', 'smo-social'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="smo-form-field">
                        <label for="smo-post-content"><?php _e('Post Content (optional)', 'smo-social'); ?></label>
                        <textarea id="smo-post-content" rows="4" placeholder="<?php _e('Add your caption or description...', 'smo-social'); ?>"></textarea>
                        <div class="smo-character-counter">
                            <span id="smo-char-count">0</span> <?php _e('characters', 'smo-social'); ?>
                        </div>
                    </div>

                    <div class="smo-form-field">
                        <label><?php _e('Hashtags', 'smo-social'); ?></label>
                        <input type="text" id="smo-hashtags" placeholder="<?php _e('#hashtag1 #hashtag2', 'smo-social'); ?>">
                        <button type="button" class="button" id="smo-generate-hashtags">
                            <?php _e('Generate Hashtags', 'smo-social'); ?>
                        </button>
                    </div>

                    <div class="smo-form-field">
                        <label for="smo-platforms"><?php _e('Target Platforms', 'smo-social'); ?></label>
                        <div class="smo-platform-selector">
                            <?php foreach ($available_platforms as $slug => $name): ?>
                                <label class="smo-platform-option">
                                    <input type="checkbox" name="platforms[]" value="<?php echo esc_attr($slug); ?>">
                                    <span class="smo-platform-name"><?php echo esc_html($name); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="smo-form-field">
                        <label><?php _e('Scheduling', 'smo-social'); ?></label>
                        <div class="smo-schedule-options">
                            <label>
                                <input type="radio" name="schedule_type" value="now" checked>
                                <?php _e('Post immediately', 'smo-social'); ?>
                            </label>
                            <label>
                                <input type="radio" name="schedule_type" value="queue">
                                <?php _e('Add to queue', 'smo-social'); ?>
                            </label>
                            <label>
                                <input type="radio" name="schedule_type" value="scheduled">
                                <?php _e('Schedule for specific time', 'smo-social'); ?>
                            </label>
                        </div>
                        
                        <div id="smo-schedule-datetime" class="smo-schedule-datetime" style="display: none;">
                            <input type="datetime-local" id="smo-scheduled-time">
                        </div>
                    </div>

                    <?php if (!empty($templates)): ?>
                    <div class="smo-form-field">
                        <label><?php _e('Use Template', 'smo-social'); ?></label>
                        <select id="smo-post-template">
                            <option value=""><?php _e('No template', 'smo-social'); ?></option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template['id']); ?>">
                                    <?php echo esc_html($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="smo-form-actions">
                    <button type="button" class="button" id="smo-preview-share">
                        <?php _e('Preview', 'smo-social'); ?>
                    </button>
                    <button type="submit" class="button button-primary" id="smo-submit-share">
                        <?php _e('Share Now', 'smo-social'); ?>
                    </button>
                    <button type="button" class="button" id="smo-cancel-share">
                        <?php _e('Cancel', 'smo-social'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div id="smo-preview-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <div class="smo-modal-header">
            <h3><?php _e('Post Preview', 'smo-social'); ?></h3>
            <button type="button" class="smo-modal-close">&times;</button>
        </div>
        <div class="smo-modal-body">
            <div id="smo-preview-content">
                <!-- Preview content will be generated here -->
            </div>
        </div>
    </div>
</div>

