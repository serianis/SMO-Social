<?php
/**
 * Enhanced SMO Social Create Post View
 * 
 * Comprehensive post creation interface with all advanced features:
 * - Link posts with preview generation
 * - Video posts from links
 * - Image posts with collections
 * - Post templates
 * - URL shortener integration
 * - Image editor
 * - Best time suggestions
 * - Reshare options
 * - AI-powered optimization
 */

// Debug logging - only output to server logs, not browser
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('SMO Social Debug: EnhancedCreatePost.php file started loading');
    
    // Start of file diagnostic
    error_log('SMO Social Debug: ABSPATH defined: ' . (defined('ABSPATH') ? 'YES' : 'NO'));
    error_log('SMO Social Debug: Current working directory: ' . getcwd());
    error_log('SMO Social Debug: File being included from: ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)[1]['file'] ?? 'unknown'));
}

if (!defined('ABSPATH')) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: ABSPATH not defined, exiting');
    }
    exit;
}

// Include WordPress function stubs for Intelephense compatibility
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('SMO Social Debug: Including WordPress function stubs');
}
require_once __DIR__ . '/../../wordpress-functions.php';

// Check if we're in a WordPress environment and safely enqueue scripts
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('SMO Social Debug: wp_enqueue_media exists: ' . (function_exists('wp_enqueue_media') ? 'YES' : 'NO'));
    error_log('SMO Social Debug: wp_enqueue_script exists: ' . (function_exists('wp_enqueue_script') ? 'YES' : 'NO'));
    error_log('SMO Social Debug: wp_enqueue_media function: ' . (string) function_exists('wp_enqueue_media'));
    error_log('SMO Social Debug: wp_enqueue_script function: ' . (string) function_exists('wp_enqueue_script'));
}

if (function_exists('wp_enqueue_media') && function_exists('wp_enqueue_script')) {
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-validate', 'https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js', ['jquery'], '1.19.5', true);
    // Define plugin URL constant if not available
    if (!defined('SMO_SOCIAL_PLUGIN_URL')) {
        define('SMO_SOCIAL_PLUGIN_URL', plugin_dir_url(__FILE__) . '../../..//');
    }

    // Define plugin version constant if not available
    if (!defined('SMO_SOCIAL_VERSION')) {
        define('SMO_SOCIAL_VERSION', '1.0.1-debug');
    }

    // Add timestamp for cache busting
    $timestamp = time();
    wp_enqueue_script('wp-api'); // Ensure WordPress REST API nonce is available
    
    // Enqueue enhanced create post scripts and styles
    wp_enqueue_script('smo-enhanced-create-post', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-enhanced-create-post.js?v=' . $timestamp, ['jquery', 'jquery-validate', 'wp-api'], SMO_SOCIAL_VERSION, true);
    wp_enqueue_style('smo-enhanced-create-post', SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-enhanced-create-post.css?v=' . $timestamp, [], SMO_SOCIAL_VERSION);
    
    // Enqueue chat and realtime features
    wp_enqueue_script('smo-chat-interface', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-chat-interface.js?v=' . $timestamp, ['jquery', 'jquery-validate', 'wp-api'], SMO_SOCIAL_VERSION, true);
    wp_enqueue_script('smo-realtime', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-realtime.js', [], SMO_SOCIAL_VERSION, true);
    wp_enqueue_style('smo-chat-modern', SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-chat-modern.css', [], SMO_SOCIAL_VERSION);

    // Localize WebSocket script safely
    if (function_exists('wp_localize_script') && function_exists('admin_url') && function_exists('wp_create_nonce') && function_exists('get_current_user_id')) {
        wp_localize_script('smo-realtime', 'smo_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_social_nonce'),
            'websocket_token' => wp_create_nonce('websocket_auth_' . get_current_user_id())
        ]);

        // Generate nonce for API authentication - prioritize wp_rest nonce
        $rest_nonce = wp_create_nonce('wp_rest');
        $chat_nonce = wp_create_nonce('smo_social_nonce');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Generated REST nonce: ' . substr($rest_nonce, 0, 20) . '...');
            error_log('SMO Social Debug: Generated chat nonce: ' . substr($chat_nonce, 0, 20) . '...');
            error_log('SMO Social Debug: Current user ID: ' . get_current_user_id());
            error_log('SMO Social Debug: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        }

        // Localize script with API settings - prioritize wp_rest nonce
        wp_localize_script('smo-chat-interface', 'smoChatConfig', [
            'root' => esc_url_raw(rest_url('smo-social/v1/')),
            'nonce' => $chat_nonce, // Keep for backward compatibility
            'restNonce' => $rest_nonce, // Primary nonce for REST API
            'ajaxurl' => admin_url('admin-ajax.php'),
            'userId' => get_current_user_id(),
            'theme' => 'light',
            'autoScroll' => true,
            'showTyping' => true,
            'streamingEnabled' => true,
            'debug' => true
        ]);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Successfully localized smo-chat-interface script with restNonce');
            error_log('SMO Social Debug: Localized config: ' . json_encode([
                'root' => esc_url_raw(rest_url('smo-social/v1/')),
                'nonce' => substr($chat_nonce, 0, 8) . '...',
                'restNonce' => substr($rest_nonce, 0, 8) . '...',
                'userId' => get_current_user_id()
            ]));
        }
    }
}

// Get available templates, network groups, and settings with fallbacks
$templates = [];
$network_groups = [];
$url_shorteners = [];
$best_times = [];

// Safe method calls with error handling
try {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: Starting to get user network groups');

        if (isset($this) && method_exists($this, 'get_user_network_groups')) {
            $network_groups = $this->get_user_network_groups();
            error_log('SMO Social Debug: Successfully got network groups via $this');
        } else {
            error_log('SMO Social Debug: $this not available for network groups, using fallback');
            // Fallback - check if Admin class has this method
            if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_user_network_groups')) {
                $admin = new \SMO_Social\Admin\Admin();
                $network_groups = $admin->get_user_network_groups();
                error_log('SMO Social Debug: Successfully got network groups via Admin class');
            } else {
                $network_groups = array();
                error_log('SMO Social Debug: Using empty network groups array');
            }
        }
    } else {
        if (isset($this) && method_exists($this, 'get_user_network_groups')) {
            $network_groups = $this->get_user_network_groups();
        } else {
            // Fallback - check if Admin class has this method
            if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_user_network_groups')) {
                $admin = new \SMO_Social\Admin\Admin();
                $network_groups = $admin->get_user_network_groups();
            } else {
                $network_groups = array();
            }
        }
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social: Error getting network groups: ' . $e->getMessage());
    }
    $network_groups = array();
}

try {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: Starting to get URL shortener settings');

        if (isset($this) && method_exists($this, 'get_url_shortener_settings')) {
            $url_shorteners = $this->get_url_shortener_settings();
            error_log('SMO Social Debug: Successfully got URL shortener settings via $this');
        } else {
            error_log('SMO Social Debug: $this not available for URL shortener settings, using fallback');
            // Fallback - check if Admin class has this method
            if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_url_shortener_settings')) {
                $admin = new \SMO_Social\Admin\Admin();
                $url_shorteners = $admin->get_url_shortener_settings();
                error_log('SMO Social Debug: Successfully got URL shortener settings via Admin class');
            } else {
                $url_shorteners = array();
                error_log('SMO Social Debug: Using empty URL shortener settings array');
            }
        }
    } else {
        if (isset($this) && method_exists($this, 'get_url_shortener_settings')) {
            $url_shorteners = $this->get_url_shortener_settings();
        } else {
            // Fallback - check if Admin class has this method
            if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_url_shortener_settings')) {
                $admin = new \SMO_Social\Admin\Admin();
                $url_shorteners = $admin->get_url_shortener_settings();
            } else {
                $url_shorteners = array();
            }
        }
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social: Error getting URL shortener settings: ' . $e->getMessage());
    }
    $url_shorteners = array();
}

try {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: Starting to get best posting times');

        if (isset($this) && method_exists($this, 'get_best_posting_times')) {
            $best_times = $this->get_best_posting_times();
            error_log('SMO Social Debug: Successfully got best posting times via $this');
        } else {
            error_log('SMO Social Debug: $this not available for best posting times, using fallback');
            // Fallback - check if Admin class has this method
            if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_best_posting_times')) {
                $admin = new \SMO_Social\Admin\Admin();
                $best_times = $admin->get_best_posting_times();
                error_log('SMO Social Debug: Successfully got best posting times via Admin class');
            } else {
                $best_times = array();
                error_log('SMO Social Debug: Using empty best times array');
            }
        }
    } else {
        if (isset($this) && method_exists($this, 'get_best_posting_times')) {
            $best_times = $this->get_best_posting_times();
        } else {
            // Fallback - check if Admin class has this method
            if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_best_posting_times')) {
                $admin = new \SMO_Social\Admin\Admin();
                $best_times = $admin->get_best_posting_times();
            } else {
                $best_times = array();
            }
        }
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social: Error getting best posting times: ' . $e->getMessage());
    }
    $best_times = array();
}

// Generate CSRF token safely
$csrf_token = '';
try {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: Starting to generate CSRF token');
        error_log('SMO Social Debug: CSRFManager class exists: ' . (class_exists('SMO_Social\\Security\\CSRFManager') ? 'YES' : 'NO'));

        if (class_exists('SMO_Social\\Security\\CSRFManager') && method_exists('SMO_Social\\Security\\CSRFManager', 'generateToken')) {
            $csrf_token = SMO_Social\Security\CSRFManager::generateToken('create_post');
            error_log('SMO Social Debug: Successfully generated CSRF token via CSRFManager');
        } else {
            error_log('SMO Social Debug: CSRFManager not available, trying WordPress nonce');
            // Fallback to WordPress nonce
            if (function_exists('wp_create_nonce')) {
                $csrf_token = wp_create_nonce('smo_create_post');
                error_log('SMO Social Debug: Successfully generated CSRF token via WordPress nonce');
            } else {
                error_log('SMO Social Debug: WordPress nonce function not available, using empty token');
                $csrf_token = '';
            }
        }
    } else {
        if (class_exists('SMO_Social\\Security\\CSRFManager') && method_exists('SMO_Social\\Security\\CSRFManager', 'generateToken')) {
            $csrf_token = SMO_Social\Security\CSRFManager::generateToken('create_post');
        } else {
            // Fallback to WordPress nonce
            if (function_exists('wp_create_nonce')) {
                $csrf_token = wp_create_nonce('smo_create_post');
            } else {
                $csrf_token = '';
            }
        }
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social: Error generating CSRF token: ' . $e->getMessage());
    }
    if (function_exists('wp_create_nonce')) {
        $csrf_token = wp_create_nonce('smo_create_post');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Generated CSRF token via WordPress nonce after error');
        }
    } else {
        $csrf_token = '';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Unable to generate CSRF token, using empty string');
        }
    }
}

?>

<div class="wrap smo-enhanced-create-post">
    <div class="smo-create-post-header">
        <h1><?php _e('Create Enhanced Social Media Post', 'smo-social'); ?></h1>
        <div class="smo-post-actions">
            <button type="button" class="button"
                id="smo-save-template"><?php _e('Save as Template', 'smo-social'); ?></button>
            <button type="button" class="button" id="smo-preview-post"><?php _e('Preview', 'smo-social'); ?></button>
            <input type="submit" name="submit" id="submit" class="smo-btn smo-btn-primary"
                value="<?php _e('Schedule Post', 'smo-social'); ?>">
        </div>
    </div>

    <form id="smo-enhanced-create-post-form" method="post" class="smo-form">
        <?php
        // Enhanced CSRF protection
        echo '<input type="hidden" name="csrf_token" value="' . esc_attr($csrf_token) . '">';
        ?>

        <div class="smo-create-post-layout">
            <!-- Main Content Area -->
            <div class="smo-main-content">

                <!-- Post Type Selection -->
                <div class="smo-post-type-selector">
                    <h3><?php _e('Post Type', 'smo-social'); ?></h3>
                    <div class="smo-post-types">
                        <label class="smo-post-type">
                            <input type="radio" name="post_type" value="text" checked>
                            <span class="smo-type-icon">📝</span>
                            <span class="smo-type-label"><?php _e('Text Post', 'smo-social'); ?></span>
                        </label>
                        <label class="smo-post-type">
                            <input type="radio" name="post_type" value="link">
                            <span class="smo-type-icon">🔗</span>
                            <span class="smo-type-label"><?php _e('Link Post', 'smo-social'); ?></span>
                        </label>
                        <label class="smo-post-type">
                            <input type="radio" name="post_type" value="video">
                            <span class="smo-type-icon">🎥</span>
                            <span class="smo-type-label"><?php _e('Video Post', 'smo-social'); ?></span>
                        </label>
                        <label class="smo-post-type">
                            <input type="radio" name="post_type" value="image">
                            <span class="smo-type-icon">🖼️</span>
                            <span class="smo-type-label"><?php _e('Image Post', 'smo-social'); ?></span>
                        </label>
                        <label class="smo-post-type">
                            <input type="radio" name="post_type" value="gallery">
                            <span class="smo-type-icon">🖼️</span>
                            <span class="smo-type-label"><?php _e('Image Gallery', 'smo-social'); ?></span>
                        </label>
                        <label class="smo-post-type">
                            <input type="radio" name="post_type" value="reshare">
                            <span class="smo-type-icon">🔄</span>
                            <span class="smo-type-label"><?php _e('Reshare', 'smo-social'); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Template Selection -->
                <div class="smo-template-selector" style="display: none;">
                    <h3><?php _e('Post Template', 'smo-social'); ?></h3>
                    <select id="post_template" name="post_template" class="smo-select">
                        <option value=""><?php _e('Select a template...', 'smo-social'); ?></option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo esc_attr($template['id']); ?>">
                                <?php echo esc_html($template['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button"
                        id="smo-load-template"><?php _e('Load Template', 'smo-social'); ?></button>
                </div>

                <!-- Content Editor -->
                <div class="smo-content-editor">
                    <h3><?php _e('Content', 'smo-social'); ?></h3>

                    <!-- Title -->
                    <div class="smo-form-field">
                        <label for="post_title" class="smo-form-label"><?php _e('Title (Optional)', 'smo-social'); ?></label>
                        <input type="text" id="post_title" name="post_title" class="smo-input"
                            placeholder="<?php _e('Internal title for your reference', 'smo-social'); ?>"
                            aria-describedby="post_title-help">
                        <p id="post_title-help" class="smo-form-help">
                            <span class="icon">📝</span>
                            <?php _e('Internal title for your reference - not published', 'smo-social'); ?>
                        </p>
                    </div>

                    <!-- Main Content -->
                    <div class="smo-form-field required">
                        <label for="post_content" class="smo-form-label"><?php _e('Post Content', 'smo-social'); ?></label>
                        <textarea id="post_content" name="post_content" class="smo-textarea" rows="8" required
                            placeholder="<?php _e('Write your engaging post content here...', 'smo-social'); ?>"
                            data-maxlength="500"
                            aria-describedby="post_content-help char-count-display"></textarea>
                        <div class="smo-character-counter" id="char-count-display">
                            <span id="char-count">0</span> <?php _e('characters', 'smo-social'); ?>
                        </div>
                        <p id="post_content-help" class="smo-form-help">
                            <span class="icon">✍️</span>
                            <?php _e('Write engaging content for your audience', 'smo-social'); ?>
                        </p>

                        <!-- Content Tools -->
                        <div class="smo-button-group">
                            <button type="button" class="button"
                                id="smo-add-emoji"><?php _e('Add Emoji', 'smo-social'); ?></button>
                            <button type="button" class="button"
                                id="smo-ai-optimize"><?php _e('AI Optimize', 'smo-social'); ?></button>
                            <button type="button" class="button"
                                id="smo-best-time"><?php _e('Best Time', 'smo-social'); ?></button>
                        </div>
                    </div>

                    <!-- Link-specific fields -->
                    <div class="smo-link-fields" style="display: none;">
                        <div class="smo-form-field">
                            <label for="post_links"><?php _e('Links', 'smo-social'); ?></label>
                            <div class="smo-links-container">
                                <div id="smo-links-list">
                                    <div class="smo-link-item">
                                        <input type="url" name="post_links[]" class="smo-link-input regular-text"
                                            placeholder="<?php _e('https://example.com', 'smo-social'); ?>">
                                        <button type="button" class="button smo-remove-link"
                                            style="display: none;">&times;</button>
                                        <button type="button"
                                            class="button smo-preview-link"><?php _e('Preview', 'smo-social'); ?></button>
                                    </div>
                                </div>
                                <button type="button" class="button"
                                    id="smo-add-link"><?php _e('Add Another Link', 'smo-social'); ?></button>
                            </div>

                            <!-- URL Shortener Options -->
                            <div class="smo-url-shortener-options">
                                <label>
                                    <input type="checkbox" name="auto_shorten_urls" value="1">
                                    <?php _e('Auto-shorten URLs', 'smo-social'); ?>
                                </label>
                                <select name="url_shortener_provider" id="url_shortener_provider">
                                    <option value="bitly"><?php _e('Bitly', 'smo-social'); ?></option>
                                    <option value="rebrandly"><?php _e('Rebrandly', 'smo-social'); ?></option>
                                    <option value="sniply"><?php _e('Sniply', 'smo-social'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Video-specific fields -->
                    <div class="smo-video-fields" style="display: none;">
                        <div class="smo-form-field">
                            <label for="video_url"><?php _e('Video URL', 'smo-social'); ?></label>
                            <div style="display: flex; gap: 10px;">
                                <input type="url" id="video_url" name="video_url" class="large-text"
                                    placeholder="<?php _e('https://youtube.com/watch?v=...', 'smo-social'); ?>">
                                <button type="button" class="button" id="smo-preview-video" style="display: none;">
                                    <?php _e('Preview', 'smo-social'); ?>
                                </button>
                            </div>
                            <p class="description">
                                <?php _e('Supports YouTube, Vimeo, and other video platforms', 'smo-social'); ?></p>
                        </div>
                    </div>

                    <!-- Gallery-specific fields -->
                    <div class="smo-gallery-fields" style="display: none;">
                        <div class="smo-form-field">
                            <label><?php _e('Image Gallery (Up to 10 images)', 'smo-social'); ?></label>
                            <div id="smo-gallery-upload">
                                <button type="button" class="button"
                                    id="smo-add-gallery-images"><?php _e('Add Images', 'smo-social'); ?></button>
                                <div id="smo-gallery-list" class="smo-gallery-grid"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Reshare-specific fields -->
                    <div class="smo-reshare-fields" style="display: none;">
                        <div class="smo-form-field">
                            <label for="original_post_url"><?php _e('Original Post URL', 'smo-social'); ?></label>
                            <div style="display: flex; gap: 10px;">
                                <input type="url" id="original_post_url" name="original_post_url" class="large-text"
                                    placeholder="<?php _e('URL of the post to reshare', 'smo-social'); ?>">
                                <button type="button" class="button" id="smo-preview-reshare" style="display: none;">
                                    <?php _e('Preview', 'smo-social'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="smo-form-field">
                            <label><?php _e('Reshare Settings', 'smo-social'); ?></label>
                            <div class="smo-reshare-options">
                                <label>
                                    <input type="checkbox" name="add_to_queue" value="1" checked>
                                    <?php _e('Add to reshare queue', 'smo-social'); ?>
                                </label>
                                <div class="smo-queue-settings">
                                    <label><?php _e('Max reshares:', 'smo-social'); ?></label>
                                    <input type="number" name="max_reshares" value="10" min="1" max="200"
                                        style="width: 80px;">
                                    <label><?php _e('Interval (hours):', 'smo-social'); ?></label>
                                    <input type="number" name="reshare_interval" value="24" min="1" max="168"
                                        style="width: 80px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hashtags and Mentions -->
                    <div class="smo-social-elements">
                        <div class="smo-form-field">
                            <label for="post_hashtags"><?php _e('Hashtags', 'smo-social'); ?></label>
                            <input type="text" id="post_hashtags" name="post_hashtags" class="regular-text"
                                placeholder="<?php _e('#hashtag1 #hashtag2', 'smo-social'); ?>">
                            <button type="button" class="button"
                                id="smo-generate-hashtags"><?php _e('Generate Hashtags', 'smo-social'); ?></button>
                        </div>

                        <div class="smo-form-field">
                            <label for="post_mentions"><?php _e('Mentions', 'smo-social'); ?></label>
                            <input type="text" id="post_mentions" name="post_mentions" class="regular-text"
                                placeholder="<?php _e('@username1 @username2', 'smo-social'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Media Attachment -->
                <div class="smo-media-section">
                    <h3><?php _e('Media & Attachments', 'smo-social'); ?></h3>
                    <div id="smo-media-upload">
                        <button type="button" class="button"
                            id="smo-add-media"><?php _e('Add Media', 'smo-social'); ?></button>
                        <div id="smo-media-list" class="smo-media-grid"></div>
                    </div>

                    <!-- Image Editor -->
                    <div class="smo-image-editor" style="display: none;">
                        <h4><?php _e('Image Editor', 'smo-social'); ?></h4>
                        <div class="smo-editor-controls">
                            <button type="button" class="button"
                                id="smo-crop-image"><?php _e('Crop', 'smo-social'); ?></button>
                            <button type="button" class="button"
                                id="smo-rotate-image"><?php _e('Rotate', 'smo-social'); ?></button>
                            <button type="button" class="button"
                                id="smo-flip-image"><?php _e('Flip', 'smo-social'); ?></button>
                            <button type="button" class="button"
                                id="smo-filters"><?php _e('Filters', 'smo-social'); ?></button>
                            <button type="button" class="button"
                                id="smo-text-overlay"><?php _e('Add Text', 'smo-social'); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Categories and Tags -->
                <div class="smo-categories-section">
                    <h3><?php _e('Categories & Organization', 'smo-social'); ?></h3>
                    <div class="smo-form-field">
                        <label for="post_categories"><?php _e('Categories/Tags', 'smo-social'); ?></label>
                        <select id="post_categories" name="post_categories[]" multiple class="regular-text">
                            <option value="news"><?php _e('News', 'smo-social'); ?></option>
                            <option value="promotion"><?php _e('Promotion', 'smo-social'); ?></option>
                            <option value="education"><?php _e('Education', 'smo-social'); ?></option>
                            <option value="entertainment"><?php _e('Entertainment', 'smo-social'); ?></option>
                            <option value="lifestyle"><?php _e('Lifestyle', 'smo-social'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="smo-post-sidebar">

                <!-- Network Groups -->
                <div class="smo-network-groups">
                    <h3><?php _e('Network Groups', 'smo-social'); ?></h3>
                    <div class="smo-group-selector">
                        <?php if (!empty($network_groups)): ?>
                            <?php foreach ($network_groups as $group): ?>
                                <label class="smo-network-group">
                                    <input type="checkbox" name="network_groups[]"
                                        value="<?php echo esc_attr($group['id']); ?>">
                                    <span class="smo-group-name"><?php echo esc_html($group['name']); ?></span>
                                    <span class="smo-group-platforms"><?php echo esc_html($group['platforms']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="smo-no-groups"><?php _e('No network groups created yet.', 'smo-social'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=smo-social-settings'); ?>"
                                class="button"><?php _e('Create Groups', 'smo-social'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Individual Platforms -->
                <div class="smo-platforms">
                    <h3><?php _e('Platforms', 'smo-social'); ?></h3>
                    <fieldset>
                        <?php
                        $platforms = array(
                            'twitter' => 'Twitter',
                            'facebook' => 'Facebook',
                            'linkedin' => 'LinkedIn',
                            'instagram' => 'Instagram',
                            'youtube' => 'YouTube',
                            'tiktok' => 'TikTok'
                        );

                        foreach ($platforms as $slug => $name) {
                            ?>
                            <label class="smo-platform-option">
                                <input type="checkbox" name="post_platforms[]" value="<?php echo esc_attr($slug); ?>">
                                <span class="smo-platform-name"><?php echo esc_html($name); ?></span>
                                <?php if (isset($best_times[$slug])): ?>
                                    <span
                                        class="smo-best-time"><?php printf(__('Best: %s', 'smo-social'), $best_times[$slug]); ?></span>
                                <?php endif; ?>
                            </label>
                            <?php
                        }
                        ?>
                    </fieldset>
                </div>

                <!-- Scheduling -->
                <div class="smo-scheduling">
                    <h3><?php _e('Scheduling', 'smo-social'); ?></h3>
                    <div class="smo-schedule-options">
                        <label>
                            <input type="radio" name="schedule_type" value="now" checked>
                            <?php _e('Post Now', 'smo-social'); ?>
                        </label>
                        <label>
                            <input type="radio" name="schedule_type" value="scheduled">
                            <?php _e('Schedule for Later', 'smo-social'); ?>
                        </label>
                        <label>
                            <input type="radio" name="schedule_type" value="best_time">
                            <?php _e('Best Time', 'smo-social'); ?>
                        </label>
                    </div>

                    <div class="smo-schedule-fields" style="display: none;">
                        <div class="smo-form-field">
                            <label for="scheduled_time"><?php _e('Schedule Time', 'smo-social'); ?></label>
                            <input type="datetime-local" id="scheduled_time" name="scheduled_time">
                        </div>

                        <div class="smo-best-time-suggestions">
                            <h4><?php _e('AI Suggestions', 'smo-social'); ?></h4>
                            <div id="smo-time-suggestions"></div>
                        </div>
                    </div>
                </div>

                <!-- Priority -->
                <div class="smo-priority">
                    <h3><?php _e('Priority', 'smo-social'); ?></h3>
                    <select id="post_priority" name="post_priority">
                        <option value="low"><?php _e('Low', 'smo-social'); ?></option>
                        <option value="normal" selected><?php _e('Normal', 'smo-social'); ?></option>
                        <option value="high"><?php _e('High', 'smo-social'); ?></option>
                        <option value="urgent"><?php _e('Urgent', 'smo-social'); ?></option>
                    </select>
                </div>

                <!-- Auto-publish Options -->
                <div class="smo-auto-publish">
                    <h3><?php _e('Auto-Publish Options', 'smo-social'); ?></h3>
                    <label>
                        <input type="checkbox" name="enable_auto_publish" value="1">
                        <?php _e('Enable auto-publish for future posts', 'smo-social'); ?>
                    </label>
                </div>

                <!-- AI Assistant -->
                <div class="smo-ai-assistant">
                    <h3><?php _e('AI Assistant', 'smo-social'); ?></h3>
                    <div id="smo-chat-container"></div>
                    <div class="smo-ai-tools">
                        <button type="button" class="button"
                            id="smo-ai-generate"><?php _e('Generate Content', 'smo-social'); ?></button>
                        <button type="button" class="button"
                            id="smo-ai-improve"><?php _e('Improve Content', 'smo-social'); ?></button>
                        <button type="button" class="button"
                            id="smo-ai-translate"><?php _e('Translate', 'smo-social'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="smo-form-actions">
            <input type="submit" name="submit" id="submit" class="smo-btn smo-btn-primary"
                value="<?php _e('Schedule Post', 'smo-social'); ?>">
            <input type="submit" name="save_draft" id="save_draft" class="button"
                value="<?php _e('Save as Draft', 'smo-social'); ?>">
            <button type="button" class="button" id="smo-reset-form"><?php _e('Reset Form', 'smo-social'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=smo-social-posts'); ?>"
                class="button"><?php _e('Cancel', 'smo-social'); ?></a>
        </div>
    </form>
</div>

<style>
    /* Enhanced Create Post Styles */
    .smo-enhanced-create-post {
        max-width: none;
    }

    .smo-create-post-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e1e1e1;
    }

    .smo-create-post-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
        margin: 20px 0;
    }

    .smo-main-content {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 20px;
    }

    .smo-post-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .smo-post-sidebar>div {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 20px;
    }

    /* Post Type Selector */
    .smo-post-type-selector {
        margin-bottom: 30px;
    }

    .smo-post-types {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .smo-post-type {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 15px;
        border: 2px solid #e1e1e1;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .smo-post-type:hover {
        border-color: #0073aa;
        background: #f8f9fa;
    }

    .smo-post-type input:checked+.smo-type-icon {
        background: #0073aa;
        color: white;
    }

    .smo-type-icon {
        font-size: 24px;
        padding: 10px;
        border-radius: 50%;
        background: #f0f0f0;
        margin-bottom: 8px;
    }

    .smo-type-label {
        font-size: 12px;
        font-weight: 500;
        text-align: center;
    }

    /* Form Fields */
    .smo-form-field {
        margin-bottom: 20px;
    }

    .smo-form-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #1d2327;
    }

    .smo-character-counter {
        text-align: right;
        font-size: 12px;
        color: #646970;
        margin-top: 5px;
    }

    .smo-content-tools {
        margin-top: 10px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* Media Grid */
    .smo-media-grid,
    .smo-gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .smo-media-item {
        position: relative;
        border: 2px solid #e1e1e1;
        border-radius: 8px;
        overflow: hidden;
        background: #f8f9fa;
    }

    .smo-media-item img {
        width: 100%;
        height: 120px;
        object-fit: cover;
    }

    .smo-media-item .smo-media-actions {
        display: flex;
        gap: 5px;
        padding: 8px;
        background: rgba(0, 0, 0, 0.8);
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
    }

    .smo-media-item .smo-media-actions button {
        flex: 1;
        padding: 4px 8px;
        font-size: 11px;
    }

    /* Network Groups */
    .smo-network-group {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        border: 1px solid #e1e1e1;
        border-radius: 6px;
        margin-bottom: 8px;
    }

    .smo-group-platforms {
        font-size: 11px;
        color: #646970;
    }

    /* Platform Options */
    .smo-platform-option {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .smo-best-time {
        font-size: 11px;
        color: #00a32a;
        font-weight: 500;
    }

    /* Scheduling */
    .smo-schedule-options {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 15px;
    }

    .smo-best-time-suggestions {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e1e1e1;
    }

    .smo-time-suggestion {
        padding: 8px;
        margin-bottom: 8px;
        background: #f8f9fa;
        border-radius: 4px;
        cursor: pointer;
    }

    .smo-time-suggestion:hover {
        background: #e9ecef;
    }

    /* Modal Styles */
    .smo-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .smo-modal-content {
        background: white;
        border-radius: 8px;
        padding: 20px;
        max-width: 90%;
        max-height: 90%;
        overflow: auto;
        position: relative;
    }

    .smo-close {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        cursor: pointer;
        color: #646970;
    }

    /* Editor Styles */
    .smo-editor-controls {
        display: flex;
        gap: 10px;
        margin: 15px 0;
        flex-wrap: wrap;
    }

    #smo-editor-canvas {
        border: 1px solid #e1e1e1;
        border-radius: 4px;
        max-width: 100%;
    }

    .smo-editor-toolbar {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e1e1e1;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .smo-create-post-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .smo-post-types {
            grid-template-columns: repeat(2, 1fr);
        }

        .smo-media-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        }
    }
</style>

<script>
    // The SMOEnhancedPost object is defined in smo-enhanced-create-post.js
    // It will automatically initialize when the document is ready
    console.log('SMO Social Debug: EnhancedCreatePost.php file loaded successfully');
</script>
