<?php
/**
 * Branded Reports Modal View
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Branded Reports Modal Class
 */
class BrandedReportsModal {
    
    /**
     * Render the Branded Reports modal
     */
    public static function render() {
        ?>
        <div id="smo-branded-reports-modal" class="smo-modal" style="display: none;" role="dialog" aria-labelledby="branded-reports-title" aria-modal="true">
            <div class="smo-modal-overlay" aria-hidden="true"></div>
            <div class="smo-modal-container smo-modal-large">
                <div class="smo-modal-header">
                    <h2 id="branded-reports-title">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('Create Branded Report', 'smo-social'); ?>
                    </h2>
                    <button class="smo-modal-close" aria-label="<?php esc_attr_e('Close modal', 'smo-social'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="smo-modal-body">
                    <div class="smo-report-wizard">
                        <!-- Step Indicator -->
                        <div class="smo-wizard-steps" role="tablist" aria-label="<?php esc_attr_e('Report creation steps', 'smo-social'); ?>">
                            <div class="smo-wizard-step active" data-step="1" role="tab" aria-selected="true" aria-controls="step-1-panel">
                                <span class="smo-step-number">1</span>
                                <span class="smo-step-label"><?php esc_html_e('Report Type', 'smo-social'); ?></span>
                            </div>
                            <div class="smo-wizard-step" data-step="2" role="tab" aria-selected="false" aria-controls="step-2-panel">
                                <span class="smo-step-number">2</span>
                                <span class="smo-step-label"><?php esc_html_e('Data Selection', 'smo-social'); ?></span>
                            </div>
                            <div class="smo-wizard-step" data-step="3" role="tab" aria-selected="false" aria-controls="step-3-panel">
                                <span class="smo-step-number">3</span>
                                <span class="smo-step-label"><?php esc_html_e('Branding', 'smo-social'); ?></span>
                            </div>
                            <div class="smo-wizard-step" data-step="4" role="tab" aria-selected="false" aria-controls="step-4-panel">
                                <span class="smo-step-number">4</span>
                                <span class="smo-step-label"><?php esc_html_e('Preview & Export', 'smo-social'); ?></span>
                            </div>
                        </div>

                        <!-- Step 1: Report Type -->
                        <div class="smo-wizard-panel active" id="step-1-panel" data-step="1" role="tabpanel">
                            <h3><?php esc_html_e('Select Report Type', 'smo-social'); ?></h3>
                            <div class="smo-report-types">
                                <div class="smo-report-type-card" data-type="performance" tabindex="0" role="button">
                                    <div class="smo-type-icon">
                                        <span class="dashicons dashicons-chart-line"></span>
                                    </div>
                                    <h4><?php esc_html_e('Performance Report', 'smo-social'); ?></h4>
                                    <p><?php esc_html_e('Comprehensive analytics and metrics', 'smo-social'); ?></p>
                                </div>

                                <div class="smo-report-type-card" data-type="engagement" tabindex="0" role="button">
                                    <div class="smo-type-icon">
                                        <span class="dashicons dashicons-heart"></span>
                                    </div>
                                    <h4><?php esc_html_e('Engagement Report', 'smo-social'); ?></h4>
                                    <p><?php esc_html_e('Likes, comments, shares analysis', 'smo-social'); ?></p>
                                </div>

                                <div class="smo-report-type-card" data-type="audience" tabindex="0" role="button">
                                    <div class="smo-type-icon">
                                        <span class="dashicons dashicons-groups"></span>
                                    </div>
                                    <h4><?php esc_html_e('Audience Report', 'smo-social'); ?></h4>
                                    <p><?php esc_html_e('Demographics and growth insights', 'smo-social'); ?></p>
                                </div>

                                <div class="smo-report-type-card" data-type="content" tabindex="0" role="button">
                                    <div class="smo-type-icon">
                                        <span class="dashicons dashicons-admin-post"></span>
                                    </div>
                                    <h4><?php esc_html_e('Content Report', 'smo-social'); ?></h4>
                                    <p><?php esc_html_e('Top performing posts and content', 'smo-social'); ?></p>
                                </div>

                                <div class="smo-report-type-card" data-type="competitive" tabindex="0" role="button">
                                    <div class="smo-type-icon">
                                        <span class="dashicons dashicons-awards"></span>
                                    </div>
                                    <h4><?php esc_html_e('Competitive Analysis', 'smo-social'); ?></h4>
                                    <p><?php esc_html_e('Compare with competitors', 'smo-social'); ?></p>
                                </div>

                                <div class="smo-report-type-card" data-type="custom" tabindex="0" role="button">
                                    <div class="smo-type-icon">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                    </div>
                                    <h4><?php esc_html_e('Custom Report', 'smo-social'); ?></h4>
                                    <p><?php esc_html_e('Build your own report', 'smo-social'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Data Selection -->
                        <div class="smo-wizard-panel" id="step-2-panel" data-step="2" role="tabpanel" style="display: none;">
                            <h3><?php esc_html_e('Configure Report Data', 'smo-social'); ?></h3>
                            
                            <div class="smo-form-section">
                                <label for="smo-report-name"><?php esc_html_e('Report Name', 'smo-social'); ?></label>
                                <input type="text" id="smo-report-name" class="widefat" placeholder="<?php esc_attr_e('e.g., Monthly Performance Report', 'smo-social'); ?>">
                            </div>

                            <div class="smo-form-section">
                                <label><?php esc_html_e('Date Range', 'smo-social'); ?></label>
                                <div class="smo-date-range-selector">
                                    <select id="smo-date-range-preset" aria-label="<?php esc_attr_e('Date range preset', 'smo-social'); ?>">
                                        <option value="last-7-days"><?php esc_html_e('Last 7 Days', 'smo-social'); ?></option>
                                        <option value="last-30-days"><?php esc_html_e('Last 30 Days', 'smo-social'); ?></option>
                                        <option value="last-90-days"><?php esc_html_e('Last 90 Days', 'smo-social'); ?></option>
                                        <option value="this-month"><?php esc_html_e('This Month', 'smo-social'); ?></option>
                                        <option value="last-month"><?php esc_html_e('Last Month', 'smo-social'); ?></option>
                                        <option value="this-quarter"><?php esc_html_e('This Quarter', 'smo-social'); ?></option>
                                        <option value="this-year"><?php esc_html_e('This Year', 'smo-social'); ?></option>
                                        <option value="custom"><?php esc_html_e('Custom Range', 'smo-social'); ?></option>
                                    </select>
                                    <div class="smo-custom-date-range" style="display: none;">
                                        <input type="date" id="smo-start-date" aria-label="<?php esc_attr_e('Start date', 'smo-social'); ?>">
                                        <span>to</span>
                                        <input type="date" id="smo-end-date" aria-label="<?php esc_attr_e('End date', 'smo-social'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="smo-form-section">
                                <label><?php esc_html_e('Platforms', 'smo-social'); ?></label>
                                <div class="smo-platform-checkboxes">
                                    <label><input type="checkbox" name="platforms[]" value="facebook" checked> Facebook</label>
                                    <label><input type="checkbox" name="platforms[]" value="twitter" checked> Twitter</label>
                                    <label><input type="checkbox" name="platforms[]" value="instagram" checked> Instagram</label>
                                    <label><input type="checkbox" name="platforms[]" value="linkedin" checked> LinkedIn</label>
                                    <label><input type="checkbox" name="platforms[]" value="youtube"> YouTube</label>
                                    <label><input type="checkbox" name="platforms[]" value="tiktok"> TikTok</label>
                                    <label><input type="checkbox" name="platforms[]" value="pinterest"> Pinterest</label>
                                </div>
                            </div>

                            <div class="smo-form-section">
                                <label><?php esc_html_e('Metrics to Include', 'smo-social'); ?></label>
                                <div class="smo-metrics-grid">
                                    <label><input type="checkbox" name="metrics[]" value="impressions" checked> <?php esc_html_e('Impressions', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="reach" checked> <?php esc_html_e('Reach', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="engagement" checked> <?php esc_html_e('Engagement', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="clicks" checked> <?php esc_html_e('Clicks', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="likes"> <?php esc_html_e('Likes', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="comments"> <?php esc_html_e('Comments', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="shares"> <?php esc_html_e('Shares', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="followers"> <?php esc_html_e('Follower Growth', 'smo-social'); ?></label>
                                    <label><input type="checkbox" name="metrics[]" value="conversions"> <?php esc_html_e('Conversions', 'smo-social'); ?></label>
                                </div>
                            </div>

                            <div class="smo-form-section">
                                <label><input type="checkbox" id="smo-include-comparison"> <?php esc_html_e('Include comparison with previous period', 'smo-social'); ?></label>
                            </div>
                        </div>

                        <!-- Step 3: Branding -->
                        <div class="smo-wizard-panel" id="step-3-panel" data-step="3" role="tabpanel" style="display: none;">
                            <h3><?php esc_html_e('Customize Report Branding', 'smo-social'); ?></h3>
                            
                            <div class="smo-branding-grid">
                                <div class="smo-branding-section">
                                    <h4><?php esc_html_e('Logo & Colors', 'smo-social'); ?></h4>
                                    
                                    <div class="smo-form-field">
                                        <label for="smo-report-logo"><?php esc_html_e('Company Logo', 'smo-social'); ?></label>
                                        <div class="smo-logo-upload">
                                            <div class="smo-logo-preview">
                                                <img id="smo-logo-preview-img" src="" alt="" style="display: none;">
                                                <span class="smo-logo-placeholder"><?php esc_html_e('No logo selected', 'smo-social'); ?></span>
                                            </div>
                                            <button type="button" class="button" id="smo-upload-logo">
                                                <?php esc_html_e('Upload Logo', 'smo-social'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="smo-form-field">
                                        <label for="smo-primary-color"><?php esc_html_e('Primary Color', 'smo-social'); ?></label>
                                        <input type="color" id="smo-primary-color" value="#0073aa">
                                    </div>

                                    <div class="smo-form-field">
                                        <label for="smo-secondary-color"><?php esc_html_e('Secondary Color', 'smo-social'); ?></label>
                                        <input type="color" id="smo-secondary-color" value="#23282d">
                                    </div>

                                    <div class="smo-form-field">
                                        <label for="smo-accent-color"><?php esc_html_e('Accent Color', 'smo-social'); ?></label>
                                        <input type="color" id="smo-accent-color" value="#00a0d2">
                                    </div>
                                </div>

                                <div class="smo-branding-section">
                                    <h4><?php esc_html_e('Company Information', 'smo-social'); ?></h4>
                                    
                                    <div class="smo-form-field">
                                        <label for="smo-company-name"><?php esc_html_e('Company Name', 'smo-social'); ?></label>
                                        <input type="text" id="smo-company-name" class="widefat">
                                    </div>

                                    <div class="smo-form-field">
                                        <label for="smo-company-website"><?php esc_html_e('Website', 'smo-social'); ?></label>
                                        <input type="url" id="smo-company-website" class="widefat" placeholder="https://">
                                    </div>

                                    <div class="smo-form-field">
                                        <label for="smo-company-email"><?php esc_html_e('Contact Email', 'smo-social'); ?></label>
                                        <input type="email" id="smo-company-email" class="widefat">
                                    </div>

                                    <div class="smo-form-field">
                                        <label for="smo-report-footer"><?php esc_html_e('Footer Text', 'smo-social'); ?></label>
                                        <textarea id="smo-report-footer" class="widefat" rows="3" placeholder="<?php esc_attr_e('Optional footer text for the report', 'smo-social'); ?>"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="smo-form-section">
                                <h4><?php esc_html_e('Report Layout', 'smo-social'); ?></h4>
                                <div class="smo-layout-options">
                                    <label class="smo-layout-option">
                                        <input type="radio" name="layout" value="modern" checked>
                                        <div class="smo-layout-preview">
                                            <span class="dashicons dashicons-layout"></span>
                                            <span><?php esc_html_e('Modern', 'smo-social'); ?></span>
                                        </div>
                                    </label>
                                    <label class="smo-layout-option">
                                        <input type="radio" name="layout" value="classic">
                                        <div class="smo-layout-preview">
                                            <span class="dashicons dashicons-text-page"></span>
                                            <span><?php esc_html_e('Classic', 'smo-social'); ?></span>
                                        </div>
                                    </label>
                                    <label class="smo-layout-option">
                                        <input type="radio" name="layout" value="minimal">
                                        <div class="smo-layout-preview">
                                            <span class="dashicons dashicons-align-center"></span>
                                            <span><?php esc_html_e('Minimal', 'smo-social'); ?></span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Preview & Export -->
                        <div class="smo-wizard-panel" id="step-4-panel" data-step="4" role="tabpanel" style="display: none;">
                            <h3><?php esc_html_e('Preview & Export', 'smo-social'); ?></h3>
                            
                            <div class="smo-report-preview-container">
                                <div class="smo-preview-toolbar">
                                    <button class="button" id="smo-refresh-preview">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php esc_html_e('Refresh Preview', 'smo-social'); ?>
                                    </button>
                                    <div class="smo-preview-zoom">
                                        <button class="button" id="smo-zoom-out" aria-label="<?php esc_attr_e('Zoom out', 'smo-social'); ?>">
                                            <span class="dashicons dashicons-minus"></span>
                                        </button>
                                        <span id="smo-zoom-level">100%</span>
                                        <button class="button" id="smo-zoom-in" aria-label="<?php esc_attr_e('Zoom in', 'smo-social'); ?>">
                                            <span class="dashicons dashicons-plus"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="smo-report-preview" id="smo-report-preview">
                                    <div class="smo-loading">
                                        <span class="spinner is-active"></span>
                                        <?php esc_html_e('Generating preview...', 'smo-social'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="smo-export-options">
                                <h4><?php esc_html_e('Export Options', 'smo-social'); ?></h4>
                                <div class="smo-export-formats">
                                    <label><input type="checkbox" name="export_formats[]" value="pdf" checked> PDF</label>
                                    <label><input type="checkbox" name="export_formats[]" value="excel"> Excel</label>
                                    <label><input type="checkbox" name="export_formats[]" value="powerpoint"> PowerPoint</label>
                                    <label><input type="checkbox" name="export_formats[]" value="csv"> CSV</label>
                                </div>
                            </div>

                            <div class="smo-form-section">
                                <label><input type="checkbox" id="smo-save-template"> <?php esc_html_e('Save as template for future use', 'smo-social'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="smo-modal-footer">
                    <div class="smo-wizard-navigation">
                        <button class="button" id="smo-wizard-prev" style="display: none;">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php esc_html_e('Previous', 'smo-social'); ?>
                        </button>
                        <button class="button button-primary" id="smo-wizard-next">
                            <?php esc_html_e('Next', 'smo-social'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                        <button class="button button-primary" id="smo-generate-report" style="display: none;">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Generate Report', 'smo-social'); ?>
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
        \wp_enqueue_media();

        \wp_enqueue_script(
            'smo-branded-reports-modal',
            \SMO_SOCIAL_PLUGIN_URL . 'assets/js/branded-reports-modal.js',
            array('jquery', 'wp-util'),
            \SMO_SOCIAL_VERSION,
            true
        );

        wp_localize_script('smo-branded-reports-modal', 'smoBrandedReports', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_branded_reports'),
            'strings' => array(
                'error' => __('An error occurred', 'smo-social'),
                'success' => __('Report generated successfully', 'smo-social'),
                'generating' => __('Generating report...', 'smo-social'),
                'selectType' => __('Please select a report type', 'smo-social'),
                'enterName' => __('Please enter a report name', 'smo-social'),
                'selectPlatform' => __('Please select at least one platform', 'smo-social'),
                'selectMetric' => __('Please select at least one metric', 'smo-social'),
            ),
        ));
    }
}
