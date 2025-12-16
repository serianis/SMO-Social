<?php
/**
 * Content Import Modal View
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Import Modal Class
 */
class ContentImportModal {
    
    /**
     * Render the Content Import modal
     */
    public static function render() {
        ?>
        <div id="smo-content-import-modal" class="smo-modal" style="display: none;" role="dialog" aria-labelledby="content-import-title" aria-modal="true">
            <div class="smo-modal-overlay" aria-hidden="true"></div>
            <div class="smo-modal-container">
                <div class="smo-modal-header">
                    <h2 id="content-import-title">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Import Content', 'smo-social'); ?>
                    </h2>
                    <button class="smo-modal-close" aria-label="<?php esc_attr_e('Close modal', 'smo-social'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="smo-modal-body">
                    <!-- Source Selection -->
                    <div class="smo-import-section">
                        <h3><?php esc_html_e('Select Import Source', 'smo-social'); ?></h3>
                        <div class="smo-source-grid">
                            <div class="smo-source-card" data-source="google-drive" tabindex="0" role="button" aria-pressed="false">
                                <div class="smo-source-icon">
                                    <img src="<?php echo esc_url(SMO_SOCIAL_PLUGIN_URL . 'assets/images/google-drive-icon.svg'); ?>" alt="">
                                </div>
                                <h4><?php esc_html_e('Google Drive', 'smo-social'); ?></h4>
                                <p><?php esc_html_e('Import from Google Drive', 'smo-social'); ?></p>
                                <span class="smo-source-status" data-status="disconnected">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php esc_html_e('Not Connected', 'smo-social'); ?>
                                </span>
                            </div>

                            <div class="smo-source-card" data-source="dropbox" tabindex="0" role="button" aria-pressed="false">
                                <div class="smo-source-icon">
                                    <img src="<?php echo esc_url(SMO_SOCIAL_PLUGIN_URL . 'assets/images/dropbox-icon.svg'); ?>" alt="">
                                </div>
                                <h4><?php esc_html_e('Dropbox', 'smo-social'); ?></h4>
                                <p><?php esc_html_e('Import from Dropbox', 'smo-social'); ?></p>
                                <span class="smo-source-status" data-status="disconnected">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php esc_html_e('Not Connected', 'smo-social'); ?>
                                </span>
                            </div>

                            <div class="smo-source-card" data-source="canva" tabindex="0" role="button" aria-pressed="false">
                                <div class="smo-source-icon">
                                    <img src="<?php echo esc_url(SMO_SOCIAL_PLUGIN_URL . 'assets/images/canva-icon.svg'); ?>" alt="">
                                </div>
                                <h4><?php esc_html_e('Canva', 'smo-social'); ?></h4>
                                <p><?php esc_html_e('Import from Canva', 'smo-social'); ?></p>
                                <span class="smo-source-status" data-status="disconnected">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php esc_html_e('Not Connected', 'smo-social'); ?>
                                </span>
                            </div>

                            <div class="smo-source-card" data-source="local" tabindex="0" role="button" aria-pressed="false">
                                <div class="smo-source-icon">
                                    <span class="dashicons dashicons-upload"></span>
                                </div>
                                <h4><?php esc_html_e('Local Upload', 'smo-social'); ?></h4>
                                <p><?php esc_html_e('Upload from computer', 'smo-social'); ?></p>
                                <span class="smo-source-status" data-status="connected">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Ready', 'smo-social'); ?>
                                </span>
                            </div>

                            <div class="smo-source-card" data-source="url" tabindex="0" role="button" aria-pressed="false">
                                <div class="smo-source-icon">
                                    <span class="dashicons dashicons-admin-links"></span>
                                </div>
                                <h4><?php esc_html_e('URL Import', 'smo-social'); ?></h4>
                                <p><?php esc_html_e('Import from URL', 'smo-social'); ?></p>
                                <span class="smo-source-status" data-status="connected">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Ready', 'smo-social'); ?>
                                </span>
                            </div>

                            <div class="smo-source-card" data-source="media-library" tabindex="0" role="button" aria-pressed="false">
                                <div class="smo-source-icon">
                                    <span class="dashicons dashicons-admin-media"></span>
                                </div>
                                <h4><?php esc_html_e('Media Library', 'smo-social'); ?></h4>
                                <p><?php esc_html_e('WordPress Media', 'smo-social'); ?></p>
                                <span class="smo-source-status" data-status="connected">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Ready', 'smo-social'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Import Configuration -->
                    <div class="smo-import-config" style="display: none;">
                        <div class="smo-import-back">
                            <button class="button" id="smo-import-back-btn">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                                <?php esc_html_e('Back to Sources', 'smo-social'); ?>
                            </button>
                        </div>

                        <!-- Google Drive Browser -->
                        <div class="smo-source-browser" data-source="google-drive" style="display: none;">
                            <div class="smo-browser-toolbar">
                                <div class="smo-breadcrumb" role="navigation" aria-label="<?php esc_attr_e('Folder navigation', 'smo-social'); ?>">
                                    <span class="smo-breadcrumb-item active"><?php esc_html_e('My Drive', 'smo-social'); ?></span>
                                </div>
                                <div class="smo-browser-actions">
                                    <input type="search" class="smo-search-input" placeholder="<?php esc_attr_e('Search files...', 'smo-social'); ?>" aria-label="<?php esc_attr_e('Search files', 'smo-social'); ?>">
                                    <button class="button" id="smo-refresh-drive">
                                        <span class="dashicons dashicons-update"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="smo-file-list" role="list">
                                <div class="smo-loading">
                                    <span class="spinner is-active"></span>
                                    <?php esc_html_e('Loading files...', 'smo-social'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Dropbox Browser -->
                        <div class="smo-source-browser" data-source="dropbox" style="display: none;">
                            <div class="smo-browser-toolbar">
                                <div class="smo-breadcrumb" role="navigation" aria-label="<?php esc_attr_e('Folder navigation', 'smo-social'); ?>">
                                    <span class="smo-breadcrumb-item active"><?php esc_html_e('Dropbox', 'smo-social'); ?></span>
                                </div>
                                <div class="smo-browser-actions">
                                    <input type="search" class="smo-search-input" placeholder="<?php esc_attr_e('Search files...', 'smo-social'); ?>" aria-label="<?php esc_attr_e('Search files', 'smo-social'); ?>">
                                    <button class="button" id="smo-refresh-dropbox">
                                        <span class="dashicons dashicons-update"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="smo-file-list" role="list">
                                <div class="smo-loading">
                                    <span class="spinner is-active"></span>
                                    <?php esc_html_e('Loading files...', 'smo-social'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Canva Browser -->
                        <div class="smo-source-browser" data-source="canva" style="display: none;">
                            <div class="smo-browser-toolbar">
                                <div class="smo-breadcrumb" role="navigation" aria-label="<?php esc_attr_e('Design navigation', 'smo-social'); ?>">
                                    <span class="smo-breadcrumb-item active"><?php esc_html_e('My Designs', 'smo-social'); ?></span>
                                </div>
                                <div class="smo-browser-actions">
                                    <input type="search" class="smo-search-input" placeholder="<?php esc_attr_e('Search designs...', 'smo-social'); ?>" aria-label="<?php esc_attr_e('Search designs', 'smo-social'); ?>">
                                    <button class="button" id="smo-refresh-canva">
                                        <span class="dashicons dashicons-update"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="smo-file-list smo-design-grid" role="list">
                                <div class="smo-loading">
                                    <span class="spinner is-active"></span>
                                    <?php esc_html_e('Loading designs...', 'smo-social'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Local Upload -->
                        <div class="smo-source-browser" data-source="local" style="display: none;">
                            <div class="smo-upload-area" id="smo-upload-dropzone">
                                <div class="smo-upload-icon">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                </div>
                                <h3><?php esc_html_e('Drop files here or click to upload', 'smo-social'); ?></h3>
                                <p><?php esc_html_e('Supports: Images, Videos, Documents', 'smo-social'); ?></p>
                                <input type="file" id="smo-file-input" multiple accept="image/*,video/*,.pdf,.doc,.docx" style="display: none;">
                                <button class="button button-primary" id="smo-browse-files">
                                    <?php esc_html_e('Browse Files', 'smo-social'); ?>
                                </button>
                            </div>
                            <div class="smo-upload-queue" style="display: none;">
                                <h4><?php esc_html_e('Upload Queue', 'smo-social'); ?></h4>
                                <div class="smo-queue-list"></div>
                            </div>
                        </div>

                        <!-- URL Import -->
                        <div class="smo-source-browser" data-source="url" style="display: none;">
                            <div class="smo-url-import-form">
                                <label for="smo-import-url">
                                    <?php esc_html_e('Enter URL to import', 'smo-social'); ?>
                                </label>
                                <input type="url" id="smo-import-url" class="widefat" placeholder="https://example.com/image.jpg">
                                <p class="description">
                                    <?php esc_html_e('Enter a direct URL to an image, video, or document', 'smo-social'); ?>
                                </p>
                                <button class="button button-primary" id="smo-import-from-url">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Import from URL', 'smo-social'); ?>
                                </button>
                            </div>
                            <div class="smo-url-preview" style="display: none;">
                                <h4><?php esc_html_e('Preview', 'smo-social'); ?></h4>
                                <div class="smo-preview-content"></div>
                            </div>
                        </div>

                        <!-- Media Library Browser -->
                        <div class="smo-source-browser" data-source="media-library" style="display: none;">
                            <div class="smo-browser-toolbar">
                                <div class="smo-browser-actions">
                                    <input type="search" class="smo-search-input" placeholder="<?php esc_attr_e('Search media...', 'smo-social'); ?>" aria-label="<?php esc_attr_e('Search media', 'smo-social'); ?>">
                                    <select id="smo-media-filter" aria-label="<?php esc_attr_e('Filter media type', 'smo-social'); ?>">
                                        <option value="all"><?php esc_html_e('All Media', 'smo-social'); ?></option>
                                        <option value="image"><?php esc_html_e('Images', 'smo-social'); ?></option>
                                        <option value="video"><?php esc_html_e('Videos', 'smo-social'); ?></option>
                                        <option value="document"><?php esc_html_e('Documents', 'smo-social'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="smo-file-list smo-media-grid" role="list">
                                <div class="smo-loading">
                                    <span class="spinner is-active"></span>
                                    <?php esc_html_e('Loading media...', 'smo-social'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Files -->
                    <div class="smo-selected-files" style="display: none;">
                        <h3><?php esc_html_e('Selected Files', 'smo-social'); ?></h3>
                        <div class="smo-selected-list"></div>
                    </div>
                </div>

                <div class="smo-modal-footer">
                    <div class="smo-import-stats">
                        <span class="smo-selected-count">0 <?php esc_html_e('files selected', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-modal-actions">
                        <button class="button" id="smo-cancel-import">
                            <?php esc_html_e('Cancel', 'smo-social'); ?>
                        </button>
                        <button class="button button-primary" id="smo-confirm-import" disabled>
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Import Selected', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue modal scripts and styles
     */
    public static function enqueue_assets() {
        wp_enqueue_script(
            'smo-content-import-modal',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/content-import-modal.js',
            array('jquery', 'wp-util'),
            SMO_SOCIAL_VERSION,
            true
        );

        wp_localize_script('smo-content-import-modal', 'smoContentImport', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_content_import'),
            'strings' => array(
                'error' => __('An error occurred', 'smo-social'),
                'success' => __('Import successful', 'smo-social'),
                'connecting' => __('Connecting...', 'smo-social'),
                'loading' => __('Loading...', 'smo-social'),
                'uploading' => __('Uploading...', 'smo-social'),
                'confirmDelete' => __('Are you sure you want to remove this file?', 'smo-social'),
            ),
        ));
    }
}
