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
 * - AI Chat Interface
 */

// Debug logging - only output to server logs, not browser
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('SMO Social Debug: Enhanced CreatePost.php file started loading');
    
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
    wp_enqueue_script('smo-chat-interface', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-chat-interface.js?v=' . $timestamp, ['jquery', 'jquery-validate', 'wp-api'], SMO_SOCIAL_VERSION, true);
    wp_enqueue_script('smo-realtime', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-realtime.js', [], SMO_SOCIAL_VERSION, true);
    wp_enqueue_style('smo-chat-modern', SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-chat-modern.css', [], SMO_SOCIAL_VERSION);
    wp_enqueue_style('smo-content-import-enhanced', SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-content-import-enhanced.css', [], SMO_SOCIAL_VERSION);

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
        error_log('SMO Social Debug: Starting to get available templates');
        error_log('SMO Social Debug: $this context: ' . (isset($this) ? 'IS SET' : 'IS NOT SET'));
        error_log('SMO Social Debug: $this class: ' . (isset($this) ? get_class($this) : 'N/A'));
    }

    if (isset($this) && method_exists($this, 'get_available_templates')) {
        $templates = $this->get_available_templates();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Successfully got templates via $this');
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: $this not available or method not found, using fallback');
        }
        // Fallback - check if Admin class has this method
        if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_available_templates')) {
            $admin = new \SMO_Social\Admin\Admin();
            $templates = $admin->get_available_templates();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Successfully got templates via Admin class');
            }
        } else {
            $templates = array();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Using empty templates array');
            }
        }
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social: Error getting templates: ' . $e->getMessage());
    }
    $templates = array();
}

// Get user network groups
try {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: Starting to get user network groups');
    }

    if (isset($this) && method_exists($this, 'get_user_network_groups')) {
        $network_groups = $this->get_user_network_groups();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Successfully got network groups via $this');
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: $this not available for network groups, using fallback');
        }
        // Fallback - check if Admin class has this method
        if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_user_network_groups')) {
            $admin = new \SMO_Social\Admin\Admin();
            $network_groups = $admin->get_user_network_groups();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Successfully got network groups via Admin class');
            }
        } else {
            $network_groups = array();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Using empty network groups array');
            }
        }
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social: Error getting network groups: ' . $e->getMessage());
    }
    $network_groups = array();
}

// Get URL shortener settings
try {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: Starting to get URL shortener settings');
    }

    if (isset($this) && method_exists($this, 'get_url_shortener_settings')) {
        $url_shorteners = $this->get_url_shortener_settings();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Successfully got URL shortener settings via $this');
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: $this not available for URL shortener settings, using fallback');
        }
        // Fallback - check if Admin class has this method
        if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_url_shortener_settings')) {
            $admin = new \SMO_Social\Admin\Admin();
            $url_shorteners = $admin->get_url_shortener_settings();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Successfully got URL shortener settings via Admin class');
            }
        } else {
            $url_shorteners = array();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Using empty URL shortener settings array');
            }
        }
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social: Error getting URL shortener settings: ' . $e->getMessage());
    }
    $url_shorteners = array();
}

// Get best posting times
try {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('SMO Social Debug: Starting to get best posting times');
    }

    if (isset($this) && method_exists($this, 'get_best_posting_times')) {
        $best_times = $this->get_best_posting_times();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Successfully got best posting times via $this');
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: $this not available for best posting times, using fallback');
        }
        // Fallback - check if Admin class has this method
        if (class_exists('\SMO_Social\Admin\Admin') && method_exists('\SMO_Social\Admin\Admin', 'get_best_posting_times')) {
            $admin = new \SMO_Social\Admin\Admin();
            $best_times = $admin->get_best_posting_times();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Successfully got best posting times via Admin class');
            }
        } else {
            $best_times = array();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Using empty best times array');
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
        error_log('SMO Social Debug: CSRFManager class exists: ' . (class_exists('SMO_Social\Security\CSRFManager') ? 'YES' : 'NO'));
    }

    if (class_exists('SMO_Social\Security\CSRFManager') && method_exists('SMO_Social\Security\CSRFManager', 'generateToken')) {
        $csrf_token = SMO_Social\Security\CSRFManager::generateToken('create_post');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: Successfully generated CSRF token via CSRFManager');
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO Social Debug: CSRFManager not available, trying WordPress nonce');
        }
        // Fallback to WordPress nonce
        if (function_exists('wp_create_nonce')) {
            $csrf_token = wp_create_nonce('smo_create_post');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: Successfully generated CSRF token via WordPress nonce');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SMO Social Debug: WordPress nonce function not available, using empty token');
            }
            $csrf_token = '';
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

<?php
// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('create-post', __('Create Post', 'smo-social'));
}
?>

<!-- Modern Gradient Header -->
<div class="smo-import-header">
    <div class="smo-header-content">
        <h1 class="smo-page-title">
            <span class="smo-icon">📝</span>
            <?php _e('Create New Post', 'smo-social'); ?>
        </h1>
        <p class="smo-page-subtitle">
            <?php _e('Design and publish content across platforms', 'smo-social'); ?>
        </p>
    </div>
    <div class="smo-header-actions">
        <button type="button" class="smo-btn smo-btn-secondary" id="smo-save-template">
            <span class="dashicons dashicons-admin-page"></span>
            <?php _e('Save as Template', 'smo-social'); ?>
        </button>
        <button type="button" class="smo-btn smo-btn-secondary" id="smo-preview-post">
            <span class="dashicons dashicons-visibility"></span>
            <?php _e('Preview', 'smo-social'); ?>
        </button>
        <button type="submit" name="submit" id="submit" class="smo-btn smo-btn-primary">
            <span class="dashicons dashicons-calendar-alt"></span>
            <?php _e('Schedule Post', 'smo-social'); ?>
        </button>
    </div>
</div>

<!-- Dashboard Stats Overview -->
<div class="smo-import-dashboard">
    <div class="smo-stats-grid">
        <div class="smo-stat-card smo-stat-gradient-1">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-admin-page"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html(count($templates)); ?></h3>
                <p class="smo-stat-label"><?php _e('Templates Available', 'smo-social'); ?></p>
                <span class="smo-stat-trend">📋 Ready</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-2">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-calendar"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html(count($network_groups)); ?></h3>
                <p class="smo-stat-label"><?php _e('Network Groups', 'smo-social'); ?></p>
                <span class="smo-stat-trend">👥 Connected</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-3">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-admin-post"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number">0</h3>
                <p class="smo-stat-label"><?php _e('Posts Today', 'smo-social'); ?></p>
                <span class="smo-stat-trend">📈 Today</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-4">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number">0</h3>
                <p class="smo-stat-label"><?php _e('Scheduled Today', 'smo-social'); ?></p>
                <span class="smo-stat-trend">⏰ Upcoming</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="smo-quick-actions">
    <button type="button" class="smo-quick-action-btn" id="smo-quick-load-template">
        <span class="dashicons dashicons-admin-page"></span>
        <span><?php _e('Load Template', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-ai-optimize">
        <span class="dashicons dashicons-admin-tools"></span>
        <span><?php _e('AI Optimize', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-preview">
        <span class="dashicons dashicons-visibility"></span>
        <span><?php _e('Preview', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-save-draft">
        <span class="dashicons dashicons-edit"></span>
        <span><?php _e('Save Draft', 'smo-social'); ?></span>
    </button>
</div>

<form id="smo-enhanced-create-post-form" method="post">
        <?php
        // Enhanced CSRF protection
        echo '<input type="hidden" name="csrf_token" value="' . esc_attr($csrf_token) . '">';
        ?>

        <div class="smo-content-body">
            <div class="smo-create-post-layout">
                <!-- Main Content Area -->
                <div class="smo-main-content">
                    <div class="smo-card">
                        <div class="smo-card-header">
                            <h2 class="smo-card-title">
                                <span class="smo-icon">📝</span>
                                <?php _e('Post Configuration', 'smo-social'); ?>
                            </h2>
                            <div class="smo-card-actions">
                                <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary" id="smo-ai-optimize">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <?php _e('AI Optimize', 'smo-social'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="smo-card-body">
                            <div class="smo-post-type-selector">
                                <label class="smo-field-label"><?php _e('Post Type', 'smo-social'); ?></label>
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
                    <select id="post_template" name="post_template">
                        <option value=""><?php _e('Select a template...', 'smo-social'); ?></option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo esc_attr($template['id']); ?>">
                                <?php echo esc_html($template['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="smo-btn smo-btn-secondary"
                        id="smo-load-template"><?php _e('Load Template', 'smo-social'); ?></button>
                </div>

                <!-- Content Editor -->
                <div class="smo-content-editor">
                    <h3><?php _e('Content', 'smo-social'); ?></h3>

                    <!-- Title -->
                    <div class="smo-form-group">
                        <label for="post_title"><?php _e('Title (Optional)', 'smo-social'); ?></label>
                        <input type="text" id="post_title" name="post_title" class="large-text"
                            placeholder="<?php _e('Internal title for your reference', 'smo-social'); ?>">
                    </div>

                    <!-- Main Content -->
                    <div class="smo-form-group">
                        <label for="post_content"><?php _e('Post Content', 'smo-social'); ?></label>
                        <textarea id="post_content" name="post_content" class="large-text" rows="8" required
                            placeholder="<?php _e('Write your engaging post content here...', 'smo-social'); ?>"></textarea>
                        <div class="smo-character-counter">
                            <span id="char-count">0</span> <?php _e('characters', 'smo-social'); ?>
                        </div>

                        <!-- Content Tools -->
                        <div class="smo-content-tools">
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-add-emoji"><?php _e('Add Emoji', 'smo-social'); ?></button>
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-ai-optimize"><?php _e('AI Optimize', 'smo-social'); ?></button>
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-best-time"><?php _e('Best Time', 'smo-social'); ?></button>
                        </div>
                    </div>

                    <!-- Link-specific fields -->
                    <div class="smo-link-fields" style="display: none;">
                        <div class="smo-form-group">
                            <label for="post_links"><?php _e('Links', 'smo-social'); ?></label>
                            <div class="smo-links-container">
                                <div id="smo-links-list">
                                    <div class="smo-link-item">
                                        <input type="url" name="post_links[]" class="smo-link-input regular-text"
                                            placeholder="<?php _e('https://example.com', 'smo-social'); ?>">
                                        <button type="button" class="smo-btn smo-btn-secondary smo-remove-link"
                                            style="display: none;">&times;</button>
                                        <button type="button"
                                            class="smo-btn smo-btn-secondary smo-preview-link"><?php _e('Preview', 'smo-social'); ?></button>
                                    </div>
                                </div>
                                <button type="button" class="smo-btn smo-btn-secondary"
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
                        <div class="smo-form-group">
                            <label for="video_url"><?php _e('Video URL', 'smo-social'); ?></label>
                            <input type="url" id="video_url" name="video_url" class="large-text"
                                placeholder="<?php _e('https://youtube.com/watch?v=...', 'smo-social'); ?>">
                            <p class="description">
                                <?php _e('Supports YouTube, Vimeo, and other video platforms', 'smo-social'); ?></p>
                        </div>
                    </div>

                    <!-- Gallery-specific fields -->
                    <div class="smo-gallery-fields" style="display: none;">
                        <div class="smo-form-group">
                            <label><?php _e('Image Gallery (Up to 10 images)', 'smo-social'); ?></label>
                            <div id="smo-gallery-upload">
                                <button type="button" class="smo-btn smo-btn-secondary"
                                    id="smo-add-gallery-images"><?php _e('Add Images', 'smo-social'); ?></button>
                                <div id="smo-gallery-list" class="smo-gallery-grid"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Reshare-specific fields -->
                    <div class="smo-reshare-fields" style="display: none;">
                        <div class="smo-form-group">
                            <label for="original_post_url"><?php _e('Original Post URL', 'smo-social'); ?></label>
                            <input type="url" id="original_post_url" name="original_post_url" class="large-text"
                                placeholder="<?php _e('URL of the post to reshare', 'smo-social'); ?>">
                        </div>
                        <div class="smo-form-group">
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
                        <div class="smo-form-group">
                            <label for="post_hashtags"><?php _e('Hashtags', 'smo-social'); ?></label>
                            <input type="text" id="post_hashtags" name="post_hashtags" class="regular-text"
                                placeholder="<?php _e('#hashtag1 #hashtag2', 'smo-social'); ?>">
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-generate-hashtags"><?php _e('Generate Hashtags', 'smo-social'); ?></button>
                        </div>

                        <div class="smo-form-group">
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
                        <button type="button" class="smo-btn smo-btn-secondary"
                            id="smo-add-media"><?php _e('Add Media', 'smo-social'); ?></button>
                        <div id="smo-media-list" class="smo-media-grid"></div>
                    </div>

                    <!-- Image Editor -->
                    <div class="smo-image-editor" style="display: none;">
                        <h4><?php _e('Image Editor', 'smo-social'); ?></h4>
                        <div class="smo-editor-controls">
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-crop-image"><?php _e('Crop', 'smo-social'); ?></button>
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-rotate-image"><?php _e('Rotate', 'smo-social'); ?></button>
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-flip-image"><?php _e('Flip', 'smo-social'); ?></button>
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-filters"><?php _e('Filters', 'smo-social'); ?></button>
                            <button type="button" class="smo-btn smo-btn-secondary"
                                id="smo-text-overlay"><?php _e('Add Text', 'smo-social'); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Categories and Tags -->
                <div class="smo-categories-section">
                    <h3><?php _e('Categories & Organization', 'smo-social'); ?></h3>
                    <div class="smo-form-group">
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
                <!-- Network Groups Card -->
                <div class="smo-card">
                    <div class="smo-card-header">
                        <h3 class="smo-card-title">
                            <span class="smo-icon">👥</span>
                            <?php _e('Network Groups', 'smo-social'); ?>
                        </h3>
                    </div>
                    <div class="smo-card-body">
                        <div class="smo-network-groups">
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
                                class="smo-btn smo-btn-sm smo-btn-secondary"><?php _e('Create Groups', 'smo-social'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                        </div>
                    </div>
                </div>
                
                <!-- Individual Platforms Card -->
                <div class="smo-card">
                    <div class="smo-card-header">
                        <h3 class="smo-card-title">
                            <span class="smo-icon">🌐</span>
                            <?php _e('Platforms', 'smo-social'); ?>
                        </h3>
                    </div>
                    <div class="smo-card-body">
                        <div class="smo-platforms">
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
                        <div class="smo-form-group">
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
                        <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary"
                            id="smo-ai-generate"><?php _e('Generate Content', 'smo-social'); ?></button>
                        <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary"
                            id="smo-ai-improve"><?php _e('Improve Content', 'smo-social'); ?></button>
                        <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary"
                            id="smo-ai-translate"><?php _e('Translate', 'smo-social'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="smo-form-actions">
            <input type="submit" name="submit" id="submit" class="smo-btn smo-btn-primary"
                value="<?php _e('Schedule Post', 'smo-social'); ?>">
            <input type="submit" name="save_draft" id="save_draft" class="smo-btn smo-btn-secondary"
                value="<?php _e('Save as Draft', 'smo-social'); ?>">
            <button type="button" class="smo-btn smo-btn-secondary" id="smo-reset-form"><?php _e('Reset Form', 'smo-social'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=smo-social-posts'); ?>"
                class="smo-btn smo-btn-secondary"><?php _e('Cancel', 'smo-social'); ?></a>
        </div>
    </form>

<!-- Preview Modal -->
<div id="smo-preview-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <span class="smo-close">&times;</span>
        <h2><?php _e('Post Preview', 'smo-social'); ?></h2>
        <div id="smo-preview-content"></div>
    </div>
</div>

<!-- Link Preview Modal -->
<div id="smo-link-preview-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <span class="smo-close">&times;</span>
        <h2><?php _e('Link Preview', 'smo-social'); ?></h2>
        <div id="smo-link-preview-content"></div>
    </div>
</div>

<!-- Image Editor Modal -->
<div id="smo-image-editor-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <span class="smo-close">&times;</span>
        <h2><?php _e('Image Editor', 'smo-social'); ?></h2>
        <div id="smo-image-editor-container">
            <canvas id="smo-editor-canvas" width="800" height="600"></canvas>
        </div>
        <div class="smo-editor-toolbar">
            <button type="button" class="smo-btn smo-btn-secondary" id="smo-editor-crop"><?php _e('Crop', 'smo-social'); ?></button>
            <button type="button" class="smo-btn smo-btn-secondary"
                id="smo-editor-rotate-left"><?php _e('Rotate Left', 'smo-social'); ?></button>
            <button type="button" class="smo-btn smo-btn-secondary"
                id="smo-editor-rotate-right"><?php _e('Rotate Right', 'smo-social'); ?></button>
            <button type="button" class="smo-btn smo-btn-secondary"
                id="smo-editor-flip-h"><?php _e('Flip Horizontal', 'smo-social'); ?></button>
            <button type="button" class="smo-btn smo-btn-secondary"
                id="smo-editor-flip-v"><?php _e('Flip Vertical', 'smo-social'); ?></button>
            <button type="button" class="smo-btn smo-btn-primary"
                id="smo-editor-apply"><?php _e('Apply Changes', 'smo-social'); ?></button>
        </div>
    </div>
</div>


<script>
    jQuery(document).ready(function ($) {
        // Initialize the enhanced create post functionality
        SMOEnhancedCreatePost.init();
    });

    const SMOEnhancedCreatePost = {
        init: function () {
            this.bindEvents();
            this.initializeComponents();
            this.setupValidation();
        },

        bindEvents: function () {
            const $ = jQuery;

            // Quick actions
            $('#smo-quick-load-template').on('click', function() { $('#smo-load-template').click(); });
            $('#smo-quick-ai-optimize').on('click', function() { $('#smo-ai-optimize').click(); });
            $('#smo-quick-preview').on('click', function() { $('#smo-preview-post').click(); });
            $('#smo-quick-save-draft').on('click', function() { $('#save_draft').click(); });

            // Post type selection
            $('input[name="post_type"]').on('change', this.handlePostTypeChange);

            // Content editing
            $('#post_content').on('input', this.updateCharacterCount);
            $('#smo-add-emoji').on('click', this.showEmojiPicker);

            // Link management
            $('#smo-add-link').on('click', this.addLinkField);
            $(document).on('click', '.smo-remove-link', this.removeLinkField);
            $(document).on('input', '.smo-link-input', this.handleLinkInput);
            $(document).on('click', '.smo-preview-link', this.previewLink);

            // Media management
            $('#smo-add-media').on('click', this.openMediaLibrary);
            $(document).on('click', '.smo-remove-media', this.removeMedia);
            $(document).on('click', '.smo-edit-media', this.editMedia);

            // Gallery management
            $('#smo-add-gallery-images').on('click', this.openGalleryLibrary);

            // AI features
            $('#smo-ai-optimize').on('click', this.optimizeContent);
            $('#smo-generate-hashtags').on('click', this.generateHashtags);
            $('#smo-best-time').on('click', this.getBestTimes);

            // Scheduling
            $('input[name="schedule_type"]').on('change', this.handleScheduleTypeChange);

            // Templates
            $('#smo-load-template').on('click', this.loadTemplate);
            $('#smo-save-template').on('click', this.saveTemplate);

            // Form actions
            $('#smo-preview-post').on('click', this.previewPost);
            $('#smo-reset-form').on('click', this.resetForm);
            $('#smo-enhanced-create-post-form').on('submit', this.handleFormSubmit);

            // Modal close
            $('.smo-modal').on('click', function (e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
            $('.smo-close').on('click', function () {
                $(this).closest('.smo-modal').hide();
            });
        },

        initializeComponents: function () {
            // Initialize character counter
            this.updateCharacterCount();

            // Initialize post type
            this.handlePostTypeChange();

            // Initialize scheduling
            this.handleScheduleTypeChange();

            // Check for template data from dashboard
            this.loadTemplateFromDashboard();
        },

        setupValidation: function () {
            // Debug: Check if jQuery validate is available
            console.log('SMO Debug: jQuery validate available:', typeof jQuery.fn.validate);
            console.log('SMO Debug: jQuery version:', jQuery.fn.jquery);

            // Set up form validation rules
            const rules = {
                post_content: {
                    required: true,
                    minLength: 10
                },
                'post_platforms[]': {
                    required: true,
                    minLength: 1
                }
            };

            // Add validation to form
            try {
                jQuery('#smo-enhanced-create-post-form').validate({
                    rules: rules,
                    messages: {
                        'post_platforms[]': {
                            required: 'Please select at least one platform'
                        }
                    }
                });
                console.log('SMO Debug: Form validation setup successful');
            } catch (error) {
                console.error('SMO Debug: Form validation setup failed:', error);
            }
        },

        handlePostTypeChange: function () {
            const postType = jQuery('input[name="post_type"]:checked').val();

            // Hide all specific fields
            jQuery('.smo-link-fields, .smo-video-fields, .smo-gallery-fields, .smo-reshare-fields, .smo-template-selector').hide();

            // Show relevant fields
            switch (postType) {
                case 'link':
                    jQuery('.smo-link-fields').show();
                    break;
                case 'video':
                    jQuery('.smo-video-fields').show();
                    break;
                case 'gallery':
                    jQuery('.smo-gallery-fields').show();
                    break;
                case 'reshare':
                    jQuery('.smo-reshare-fields').show();
                    break;
            }

            jQuery('.smo-template-selector').show();
        },

        updateCharacterCount: function () {
            const content = jQuery('#post_content').val();
            const length = content.length;

            jQuery('#char-count').text(length);

            // Show warnings for platform limits
            const selectedPlatforms = jQuery('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            let warnings = [];

            selectedPlatforms.forEach(function (platform) {
                let limit = 0;
                switch (platform) {
                    case 'twitter':
                        limit = 280;
                        break;
                    case 'instagram':
                        limit = 2200;
                        break;
                    case 'linkedin':
                        limit = 3000;
                        break;
                }

                if (limit > 0 && length > limit) {
                    warnings.push(`${platform}: ${length}/${limit} characters`);
                }
            });

            if (warnings.length > 0) {
                jQuery('#char-count').css('color', 'red').text(warnings.join(', '));
            } else {
                jQuery('#char-count').css('color', '').text(length + ' characters');
            }
        },

        showEmojiPicker: function () {
            // Simple emoji picker implementation
            const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩'];

            let html = '<div class="smo-emoji-picker">';
            emojis.forEach(emoji => {
                html += `<span class="smo-emoji" data-emoji="${emoji}">${emoji}</span>`;
            });
            html += '</div>';

            jQuery('#post_content').after(html);

            jQuery('.smo-emoji').on('click', function () {
                const emoji = jQuery(this).data('emoji');
                const textarea = jQuery('#post_content')[0];
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const text = textarea.value;
                textarea.value = text.slice(0, start) + emoji + text.slice(end);
                textarea.focus();
                textarea.setSelectionRange(start + emoji.length, start + emoji.length);
                jQuery('.smo-emoji-picker').remove();
            });
        },

        addLinkField: function () {
            const $ = jQuery;
            const linkCount = $('.smo-link-item').length;

            if (linkCount >= 5) {
                alert('Maximum 5 links allowed per post.');
                return;
            }

            const newLink = $(`
            <div class="smo-link-item">
                <input type="url" name="post_links[]" class="smo-link-input regular-text" placeholder="https://example.com">
                <button type="button" class="smo-btn smo-btn-secondary smo-remove-link">&times;</button>
                <button type="button" class="smo-btn smo-btn-secondary smo-preview-link">Preview</button>
            </div>
        `);

            $('#smo-links-list').append(newLink);
            this.updateRemoveButtons();
        },

        removeLinkField: function () {
            jQuery(this).closest('.smo-link-item').remove();
            SMOEnhancedCreatePost.updateRemoveButtons();
        },

        updateRemoveButtons: function () {
            const linkCount = jQuery('.smo-link-item').length;
            jQuery('.smo-remove-link').toggle(linkCount > 1);
        },

        handleLinkInput: function () {
            const url = jQuery(this).val();
            const linkItem = jQuery(this).closest('.smo-link-item');

            // Remove existing validation
            linkItem.find('.smo-link-validation').remove();

            if (url && SMOEnhancedCreatePost.isValidUrl(url)) {
                linkItem.find('.smo-preview-link').show();
            } else {
                linkItem.find('.smo-preview-link').hide();
            }
        },

        isValidUrl: function (string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        },

        previewLink: function () {
            const url = jQuery(this).siblings('.smo-link-input').val();
            if (!url) return;

            jQuery.post(ajaxurl, {
                action: 'smo_get_link_preview',
                nonce: smoChatConfig.nonce,
                url: url
            }, function (response) {
                if (response.success) {
                    const preview = response.data;
                    let html = `
                    <div class="smo-link-preview">
                        <h4>${preview.title || 'No title'}</h4>
                        <p>${preview.description || 'No description'}</p>
                        <small>${preview.domain || ''}</small>
                    </div>
                `;
                    jQuery('#smo-link-preview-content').html(html);
                    jQuery('#smo-link-preview-modal').show();
                }
            });
        },

        openMediaLibrary: function () {
            const mediaUploader = wp.media({
                title: 'Select Media',
                button: {
                    text: 'Add Media'
                },
                multiple: true
            });

            mediaUploader.on('select', function () {
                const attachments = mediaUploader.state().get('selection').toJSON();
                attachments.forEach(function (attachment) {
                    SMOEnhancedCreatePost.addMediaItem(attachment);
                });
            });

            mediaUploader.open();
        },

        addMediaItem: function (attachment) {
            const html = `
            <div class="smo-media-item" data-media-id="${attachment.id}">
                <img src="${attachment.url}" alt="${attachment.alt || ''}">
                <div class="smo-media-actions">
                    <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary smo-edit-media">Edit</button>
                    <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary smo-remove-media">Remove</button>
                </div>
                <input type="hidden" name="media_ids[]" value="${attachment.id}">
            </div>
        `;
            jQuery('#smo-media-list').append(html);
        },

        removeMedia: function () {
            jQuery(this).closest('.smo-media-item').remove();
        },

        editMedia: function () {
            const mediaId = jQuery(this).closest('.smo-media-item').data('media-id');
            // Open image editor
            jQuery('#smo-image-editor-modal').show();
        },

        openGalleryLibrary: function () {
            const mediaUploader = wp.media({
                title: 'Select Images for Gallery',
                button: {
                    text: 'Add Images'
                },
                multiple: true
            });

            mediaUploader.on('select', function () {
                const attachments = mediaUploader.state().get('selection').toJSON();
                attachments.forEach(function (attachment, index) {
                    SMOEnhancedCreatePost.addGalleryItem(attachment, index);
                });
            });

            mediaUploader.open();
        },

        addGalleryItem: function (attachment, index) {
            const html = `
            <div class="smo-media-item" data-media-id="${attachment.id}" data-gallery-index="${index}">
                <img src="${attachment.url}" alt="${attachment.alt || ''}">
                <div class="smo-gallery-controls">
                    <input type="number" name="gallery_order[]" value="${index + 1}" min="1" max="10" style="width: 40px;">
                    <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary smo-remove-media">Remove</button>
                </div>
                <input type="hidden" name="gallery_media_ids[]" value="${attachment.id}">
            </div>
        `;
            jQuery('#smo-gallery-list').append(html);
        },

        optimizeContent: function () {
            const content = jQuery('#post_content').val();
            const platforms = jQuery('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (!content) {
                alert('Please enter some content first.');
                return;
            }

            jQuery.post(ajaxurl, {
                action: 'smo_ai_optimize_content',
                nonce: smoChatConfig.nonce,
                content: content,
                platforms: platforms
            }, function (response) {
                if (response.success) {
                    jQuery('#post_content').val(response.data.optimized_content);
                    SMOEnhancedCreatePost.updateCharacterCount();
                }
            });
        },

        generateHashtags: function () {
            const content = jQuery('#post_content').val();

            if (!content) {
                alert('Please enter some content first.');
                return;
            }

            jQuery.post(ajaxurl, {
                action: 'smo_ai_generate_hashtags',
                nonce: smoChatConfig.nonce,
                content: content
            }, function (response) {
                if (response.success) {
                    jQuery('#post_hashtags').val(response.data.hashtags.join(' '));
                }
            });
        },

        getBestTimes: function () {
            const platforms = jQuery('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (platforms.length === 0) {
                alert('Please select platforms first.');
                return;
            }

            jQuery.post(ajaxurl, {
                action: 'smo_get_best_posting_times',
                nonce: smoChatConfig.nonce,
                platforms: platforms
            }, function (response) {
                if (response.success) {
                    let html = '<div class="smo-time-suggestions">';
                    response.data.forEach(function (suggestion) {
                        html += `<div class="smo-time-suggestion" data-time="${suggestion.time}">
                        ${suggestion.platform}: ${suggestion.time} (${suggestion.engagement}%)
                    </div>`;
                    });
                    html += '</div>';
                    jQuery('#smo-time-suggestions').html(html);

                    jQuery('.smo-time-suggestion').on('click', function () {
                        const time = jQuery(this).data('time');
                        const dateTime = new Date();
                        const [hours, minutes] = time.split(':');
                        dateTime.setHours(parseInt(hours), parseInt(minutes));
                        jQuery('#scheduled_time').val(dateTime.toISOString().slice(0, 16));
                        jQuery('input[name="schedule_type"][value="scheduled"]').prop('checked', true);
                        SMOEnhancedCreatePost.handleScheduleTypeChange();
                    });
                }
            });
        },

        handleScheduleTypeChange: function () {
            const scheduleType = jQuery('input[name="schedule_type"]:checked').val();
            jQuery('.smo-schedule-fields').toggle(scheduleType !== 'now');
        },

        loadTemplateFromDashboard: function () {
            // Check URL parameters first
            const urlParams = new URLSearchParams(window.location.search);
            const templateParam = urlParams.get('template');

            if (templateParam) {
                try {
                    const templateData = JSON.parse(decodeURIComponent(templateParam));
                    this.applyTemplateData(templateData);
                    return;
                } catch (e) {
                    console.error('Error parsing template data from URL:', e);
                }
            }

            // Check sessionStorage for template data from dashboard
            if (typeof(Storage) !== 'undefined') {
                const storedTemplate = sessionStorage.getItem('smo_selected_template');
                if (storedTemplate) {
                    try {
                        const templateData = JSON.parse(storedTemplate);
                        this.applyTemplateData(templateData);
                        // Clear the stored template after use
                        sessionStorage.removeItem('smo_selected_template');
                        return;
                    } catch (e) {
                        console.error('Error parsing stored template data:', e);
                    }
                }
            }
        },

        applyTemplateData: function (templateData) {
            if (templateData.name) {
                jQuery('#post_title').val(templateData.name);
            }

            if (templateData.content) {
                jQuery('#post_content').val(templateData.content);
                this.updateCharacterCount();
            }

            // Set post type if specified
            if (templateData.post_type) {
                jQuery(`input[name="post_type"][value="${templateData.post_type}"]`).prop('checked', true);
                this.handlePostTypeChange();
            }

            // Set platforms if specified
            if (templateData.platforms && Array.isArray(templateData.platforms)) {
                templateData.platforms.forEach(platform => {
                    jQuery(`input[name="post_platforms[]"][value="${platform}"]`).prop('checked', true);
                });
            }

            console.log('Template data applied:', templateData);
        },

        loadTemplate: function () {
            const templateId = jQuery('#post_template').val();
            if (!templateId) return;

            jQuery.post(ajaxurl, {
                action: 'smo_load_template',
                nonce: smoChatConfig.nonce,
                template_id: templateId
            }, function (response) {
                if (response.success) {
                    const template = response.data;
                    jQuery('#post_title').val(template.name);
                    jQuery('#post_content').val(template.content_template);
                    SMOEnhancedCreatePost.updateCharacterCount();
                }
            });
        },

        saveTemplate: function () {
            const title = jQuery('#post_title').val();
            const content = jQuery('#post_content').val();
            const platforms = jQuery('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (!title || !content) {
                alert('Please enter a title and content for the template.');
                return;
            }

            const templateName = prompt('Template Name:', title);
            if (!templateName) return;

            jQuery.post(ajaxurl, {
                action: 'smo_save_template',
                nonce: smoChatConfig.nonce,
                name: templateName,
                content: content,
                platforms: platforms
            }, function (response) {
                if (response.success) {
                    alert('Template saved successfully!');
                }
            });
        },

        previewPost: function () {
            const formData = SMOEnhancedCreatePost.collectFormData();

            jQuery.post(ajaxurl, {
                action: 'smo_preview_post',
                nonce: smoChatConfig.nonce,
                data: formData
            }, function (response) {
                if (response.success) {
                    jQuery('#smo-preview-content').html(response.data.html);
                    jQuery('#smo-preview-modal').show();
                }
            });
        },

        collectFormData: function () {
            const formData = {
                title: jQuery('#post_title').val(),
                content: jQuery('#post_content').val(),
                post_type: jQuery('input[name="post_type"]:checked').val(),
                platforms: jQuery('input[name="post_platforms[]"]:checked').map(function () {
                    return this.value;
                }).get(),
                hashtags: jQuery('#post_hashtags').val(),
                mentions: jQuery('#post_mentions').val(),
                schedule_type: jQuery('input[name="schedule_type"]:checked').val(),
                scheduled_time: jQuery('#scheduled_time').val(),
                priority: jQuery('#post_priority').val(),
                links: jQuery('input[name="post_links[]"]').map(function () {
                    return this.value;
                }).get(),
                video_url: jQuery('#video_url').val(),
                media_ids: jQuery('input[name="media_ids[]"]').map(function () {
                    return this.value;
                }).get(),
                gallery_ids: jQuery('input[name="gallery_media_ids[]"]').map(function () {
                    return this.value;
                }).get()
            };

            return formData;
        },

        resetForm: function () {
            if (confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) {
                jQuery('#smo-enhanced-create-post-form')[0].reset();
                jQuery('#smo-media-list, #smo-gallery-list').empty();
                SMOEnhancedCreatePost.updateCharacterCount();
                SMOEnhancedCreatePost.handlePostTypeChange();
                SMOEnhancedCreatePost.handleScheduleTypeChange();
            }
        },

        handleFormSubmit: function (e) {
            // Validate form before submission
            const formData = SMOEnhancedCreatePost.collectFormData();

            if (!formData.content) {
                alert('Please enter post content.');
                e.preventDefault();
                return false;
            }

            if (formData.platforms.length === 0) {
                alert('Please select at least one platform.');
                e.preventDefault();
                return false;
            }

            // Add loading state
            jQuery('#submit').prop('disabled', true).val('Scheduling...');

            // Continue with normal form submission
            return true;
        }
    };

    // Check if debug mode is enabled (safe for both PHP and JavaScript contexts)
    if (typeof window !== 'undefined' && window.smoChatConfig && window.smoChatConfig.debug) {
        console.log('SMO Social Debug: Enhanced CreatePost.php file loaded successfully');
    }
</script>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>
