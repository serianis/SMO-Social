<?php
/**
 * Integrations Dashboard View
 *
 * Modern UI for managing third-party integrations
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integrations View Class
 */
class Integrations {

    /**
     * Render the integrations page
     */
    public static function render() {
        ?>

<?php
// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('integrations', __('Integrations', 'smo-social'));
}
?>

<!-- Modern Gradient Header -->
<div class="smo-import-header">
    <div class="smo-header-content">
        <h1 class="smo-page-title">
            <span class="smo-icon">🔗</span>
            <?php _e('Integrations', 'smo-social'); ?>
        </h1>
        <p class="smo-page-subtitle">
            <?php _e('Connect SMO Social with your favorite tools and services', 'smo-social'); ?>
        </p>
    </div>
    <div class="smo-header-actions">
        <button type="button" class="smo-btn smo-btn-secondary" id="smo-refresh-integrations">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh', 'smo-social'); ?>
        </button>
    </div>
</div>

<!-- Dashboard Stats Overview -->
<div class="smo-import-dashboard">
    <div class="smo-stats-grid">
        <div class="smo-stat-card smo-stat-gradient-1">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-admin-plugins"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number">12</h3>
                <p class="smo-stat-label"><?php _e('Total Integrations', 'smo-social'); ?></p>
                <span class="smo-stat-trend">🔗 Available</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-2">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-yes"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number">8</h3>
                <p class="smo-stat-label"><?php _e('Connected', 'smo-social'); ?></p>
                <span class="smo-stat-trend">✅ Active</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-3">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-no"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number">4</h3>
                <p class="smo-stat-label"><?php _e('Disconnected', 'smo-social'); ?></p>
                <span class="smo-stat-trend">⚠️ Needs Setup</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-4">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-calendar"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number">2</h3>
                <p class="smo-stat-label"><?php _e('Recently Added', 'smo-social'); ?></p>
                <span class="smo-stat-trend">🆕 This Week</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="smo-quick-actions">
    <button type="button" class="smo-quick-action-btn" id="smo-quick-connect">
        <span class="dashicons dashicons-admin-plugins"></span>
        <span><?php _e('Connect New', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-test">
        <span class="dashicons dashicons-admin-tools"></span>
        <span><?php _e('Test Connections', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-settings">
        <span class="dashicons dashicons-admin-settings"></span>
        <span><?php _e('Settings', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-logs">
        <span class="dashicons dashicons-list-view"></span>
        <span><?php _e('View Logs', 'smo-social'); ?></span>
    </button>
</div>

<!-- Main Content -->
<div class="smo-card">
    <div class="smo-card-header">
        <h2 class="smo-card-title">
            <span class="smo-icon">🔗</span>
            <?php _e('Available Integrations', 'smo-social'); ?>
        </h2>
        <div class="smo-card-actions">
            <button type="button" class="smo-btn smo-btn-sm smo-btn-secondary" id="smo-filter-integrations">
                <span class="dashicons dashicons-filter"></span>
                <?php _e('Filter', 'smo-social'); ?>
            </button>
        </div>
    </div>
    <div class="smo-card-body">

            <!-- Integration Categories -->
            <div class="smo-integration-categories">
                <button class="smo-category-btn active" data-category="all">
                    <?php esc_html_e('All Integrations', 'smo-social'); ?>
                </button>
                <button class="smo-category-btn" data-category="content">
                    📝 <?php esc_html_e('Content', 'smo-social'); ?>
                </button>
                <button class="smo-category-btn" data-category="storage">
                    💾 <?php esc_html_e('Storage', 'smo-social'); ?>
                </button>
                <button class="smo-category-btn" data-category="automation">
                    ⚡ <?php esc_html_e('Automation', 'smo-social'); ?>
                </button>
                <button class="smo-category-btn" data-category="media">
                    🖼️ <?php esc_html_e('Media', 'smo-social'); ?>
                </button>
            </div>
            
            <!-- Integrations Grid -->
            <div class="smo-integrations-grid" id="smo-integrations-grid">
                <div class="smo-loading">
                    <div class="smo-spinner"></div>
                    <p><?php esc_html_e('Loading integrations...', 'smo-social'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Integration Connection Modal -->
        <div id="smo-integration-modal" class="smo-modal" style="display: none;">
            <div class="smo-modal-overlay"></div>
            <div class="smo-modal-container">
                <div class="smo-modal-header">
                    <h2 id="smo-modal-title"><?php esc_html_e('Connect Integration', 'smo-social'); ?></h2>
                    <button class="smo-modal-close" aria-label="<?php esc_attr_e('Close', 'smo-social'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="smo-modal-body" id="smo-modal-body">
                    <!-- Dynamic content loaded here -->
                </div>
                <div class="smo-modal-footer">
                    <button class="smo-btn smo-btn-outline" id="smo-modal-cancel">
                        <?php esc_html_e('Cancel', 'smo-social'); ?>
                    </button>
                    <button class="smo-btn smo-btn-primary" id="smo-modal-connect">
                        <?php esc_html_e('Connect', 'smo-social'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Integration Browser Modal (for selecting files/content) -->
        <div id="smo-integration-browser-modal" class="smo-modal smo-modal-large" style="display: none;">
            <div class="smo-modal-overlay"></div>
            <div class="smo-modal-container">
                <div class="smo-modal-header">
                    <h2 id="smo-browser-title"><?php esc_html_e('Browse Content', 'smo-social'); ?></h2>
                    <button class="smo-modal-close" aria-label="<?php esc_attr_e('Close', 'smo-social'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="smo-modal-body">
                    <div class="smo-browser-toolbar">
                        <input type="search" id="smo-browser-search" placeholder="<?php esc_attr_e('Search...', 'smo-social'); ?>">
                        <select id="smo-browser-filter">
                            <option value=""><?php esc_html_e('All Types', 'smo-social'); ?></option>
                            <option value="image"><?php esc_html_e('Images', 'smo-social'); ?></option>
                            <option value="video"><?php esc_html_e('Videos', 'smo-social'); ?></option>
                            <option value="document"><?php esc_html_e('Documents', 'smo-social'); ?></option>
                        </select>
                    </div>
                    <div class="smo-browser-content" id="smo-browser-content">
                        <!-- Dynamic content loaded here -->
                    </div>
                    <div class="smo-browser-pagination">
                        <button class="smo-btn smo-btn-outline" id="smo-browser-prev" disabled>
                            <?php esc_html_e('Previous', 'smo-social'); ?>
                        </button>
                        <span id="smo-browser-page-info">Page 1</span>
                        <button class="smo-btn smo-btn-outline" id="smo-browser-next">
                            <?php esc_html_e('Next', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                <div class="smo-modal-footer">
                    <button class="smo-btn smo-btn-outline" id="smo-browser-cancel">
                        <?php esc_html_e('Cancel', 'smo-social'); ?>
                    </button>
                    <button class="smo-btn smo-btn-primary" id="smo-browser-import" disabled>
                        <?php esc_html_e('Import Selected', 'smo-social'); ?>
                    </button>
                </div>
            </div>
        </div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>

        <?php
    }
}
