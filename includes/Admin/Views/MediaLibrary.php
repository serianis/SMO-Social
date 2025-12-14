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
        define('SMO_SOCIAL_VERSION', '1.0.1');
    }
    
    // Add timestamp for cache busting
    $timestamp = time();
    
    // Enqueue media library specific scripts
    wp_enqueue_script('smo-media-library', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-media-library.js?v=' . $timestamp, ['jquery', 'jquery-ui-sortable'], SMO_SOCIAL_VERSION, true);
    wp_enqueue_style('smo-media-library', SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-media-library.css?v=' . $timestamp, [], SMO_SOCIAL_VERSION);
    
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

<style>
/* Media Library Styles */
.smo-media-library {
    max-width: none;
}

.smo-media-library-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e1e1e1;
}

.smo-media-actions {
    display: flex;
    gap: 10px;
}

.smo-media-library-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
}

.smo-media-sidebar {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    height: fit-content;
}

.smo-filter-section {
    margin-bottom: 25px;
}

.smo-filter-section:last-child {
    margin-bottom: 0;
}

.smo-filter-section h3 {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

.smo-search-box {
    display: flex;
    gap: 5px;
}

.smo-search-box input {
    flex: 1;
}

.smo-file-types label,
.smo-dimension-filters label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    cursor: pointer;
}

.smo-file-types input,
.smo-dimension-filters input {
    margin-right: 8px;
}

.smo-media-main {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    overflow: hidden;
}

.smo-view-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.smo-view-options {
    display: flex;
    gap: 5px;
}

.smo-view-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
}

.smo-view-btn.active {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.smo-sort-options {
    flex: 1;
    margin: 0 15px;
}

.smo-pagination-info {
    font-size: 13px;
    color: #646970;
}

.smo-media-container {
    min-height: 400px;
    padding: 20px;
}

/* Grid View */
.smo-media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.smo-media-item {
    position: relative;
    border: 2px solid #e1e1e1;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.smo-media-item:hover {
    border-color: #0073aa;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 115, 170, 0.15);
}

.smo-media-item.selected {
    border-color: #0073aa;
    background: #e3f2fd;
}

.smo-media-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}

.smo-media-item .smo-media-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.smo-media-item:hover .smo-media-overlay {
    opacity: 1;
}

.smo-media-item .smo-checkbox {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 20px;
    height: 20px;
    background: white;
    border: 2px solid #ccc;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.smo-media-item.selected .smo-checkbox {
    background: #0073aa;
    border-color: #0073aa;
}

.smo-media-item .smo-checkbox::after {
    content: '✓';
    color: white;
    font-weight: bold;
    display: none;
}

.smo-media-item.selected .smo-checkbox::after {
    display: block;
}

.smo-media-item .smo-media-actions {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.8);
    padding: 8px;
    display: flex;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.smo-media-item:hover .smo-media-actions {
    opacity: 1;
}

.smo-media-item .smo-media-actions button {
    flex: 1;
    padding: 4px 8px;
    font-size: 11px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 3px;
    cursor: pointer;
}

.smo-media-item .smo-media-info {
    padding: 10px;
    background: white;
}

.smo-media-item .smo-media-title {
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.smo-media-item .smo-media-meta {
    font-size: 11px;
    color: #646970;
}

/* List View */
.smo-media-list {
    display: none;
}

.smo-media-list-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.smo-media-list-item:hover {
    background: #f8f9fa;
}

.smo-media-list-item.selected {
    background: #e3f2fd;
}

.smo-media-list-thumb {
    width: 60px;
    height: 60px;
    margin-right: 15px;
    border-radius: 4px;
    overflow: hidden;
}

.smo-media-list-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.smo-media-list-details {
    flex: 1;
}

.smo-media-list-title {
    font-weight: 500;
    margin-bottom: 4px;
}

.smo-media-list-meta {
    font-size: 12px;
    color: #646970;
}

/* Loading States */
.smo-loading-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #646970;
}

.smo-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Pagination */
.smo-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

.smo-pagination-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.smo-per-page select {
    width: auto;
}

/* Selection Bar */
.smo-selection-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #0073aa;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 999;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
}

.smo-selection-bar .button {
    color: #0073aa;
    background: white;
    border: none;
}

.smo-selection-bar .button-primary {
    background: #00a32a;
    color: white;
}

/* Modal Styles */
.smo-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.smo-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 800px;
    width: 90%;
    max-height: 90%;
    overflow-y: auto;
    position: relative;
}

.smo-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.smo-modal-header h3 {
    margin: 0;
}

.smo-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
}

.smo-modal-body {
    padding: 20px;
}

.smo-form-section {
    margin-bottom: 25px;
}

.smo-form-section h4 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
}

.smo-form-field {
    margin-bottom: 15px;
}

.smo-form-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.smo-sharing-methods label,
.smo-schedule-options label {
    display: block;
    margin-bottom: 8px;
}

.smo-platform-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

.smo-platform-option {
    padding: 10px;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.smo-platform-option:hover {
    border-color: #0073aa;
    background: #f8f9fa;
}

.smo-platform-option input:checked + .smo-platform-name {
    color: #0073aa;
    font-weight: 600;
}

.smo-form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

/* Selected Preview */
.smo-selected-preview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.smo-selected-preview img {
    width: 100%;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
    border: 2px solid #0073aa;
}

/* Character Counter */
.smo-character-counter {
    text-align: right;
    font-size: 12px;
    color: #646970;
    margin-top: 5px;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .smo-media-library-layout {
        grid-template-columns: 1fr;
    }
    
    .smo-media-sidebar {
        order: 2;
    }
    
    .smo-media-main {
        order: 1;
    }
}

@media (max-width: 768px) {
    .smo-media-library-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .smo-media-actions {
        justify-content: center;
    }
    
    .smo-view-controls {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .smo-media-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
    
    .smo-pagination {
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
    }
    
    .smo-selection-bar {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .smo-modal-content {
        width: 95%;
        margin: 20px;
    }
}
</style>
