<?php
/**
 * User Permissions Modal View
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Permissions Modal Class
 */
class UserPermissionsModal {
    
    /**
     * Render the User Permissions modal
     */
    public static function render() {
        ?>
        <div id="smo-user-permissions-modal" class="smo-modal" style="display: none;" role="dialog" aria-labelledby="user-permissions-title" aria-modal="true">
            <div class="smo-modal-overlay" aria-hidden="true"></div>
            <div class="smo-modal-container smo-modal-large">
                <div class="smo-modal-header">
                    <h2 id="user-permissions-title">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php \esc_html_e('User Permissions', 'smo-social'); ?>
                    </h2>
                    <button class="smo-modal-close" aria-label="<?php \esc_attr_e('Close modal', 'smo-social'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="smo-modal-body">
                    <div class="smo-permissions-layout">
                        <!-- User Selection Sidebar -->
                        <div class="smo-permissions-sidebar">
                            <div class="smo-sidebar-header">
                                <h3><?php \esc_html_e('Users & Roles', 'smo-social'); ?></h3>
                                <button class="button button-small" id="smo-add-user-role">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                </button>
                            </div>

                            <div class="smo-search-box">
                                <input type="search" id="smo-user-search" placeholder="<?php \esc_attr_e('Search users...', 'smo-social'); ?>" aria-label="<?php \esc_attr_e('Search users', 'smo-social'); ?>">
                            </div>

                            <div class="smo-role-tabs" role="tablist">
                                <button class="smo-role-tab active" data-role="all" role="tab" aria-selected="true">
                                    <?php \esc_html_e('All Users', 'smo-social'); ?>
                                    <span class="smo-count">0</span>
                                </button>
                                <button class="smo-role-tab" data-role="admin" role="tab" aria-selected="false">
                                    <?php \esc_html_e('Administrators', 'smo-social'); ?>
                                    <span class="smo-count">0</span>
                                </button>
                                <button class="smo-role-tab" data-role="manager" role="tab" aria-selected="false">
                                    <?php \esc_html_e('Managers', 'smo-social'); ?>
                                    <span class="smo-count">0</span>
                                </button>
                                <button class="smo-role-tab" data-role="editor" role="tab" aria-selected="false">
                                    <?php \esc_html_e('Editors', 'smo-social'); ?>
                                    <span class="smo-count">0</span>
                                </button>
                                <button class="smo-role-tab" data-role="contributor" role="tab" aria-selected="false">
                                    <?php \esc_html_e('Contributors', 'smo-social'); ?>
                                    <span class="smo-count">0</span>
                                </button>
                                <button class="smo-role-tab" data-role="viewer" role="tab" aria-selected="false">
                                    <?php \esc_html_e('Viewers', 'smo-social'); ?>
                                    <span class="smo-count">0</span>
                                </button>
                            </div>

                            <div class="smo-user-list" role="list">
                                <div class="smo-loading">
                                    <span class="spinner is-active"></span>
                                    <?php \esc_html_e('Loading users...', 'smo-social'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Permissions Panel -->
                        <div class="smo-permissions-panel">
                            <div class="smo-no-selection">
                                <span class="dashicons dashicons-admin-users"></span>
                                <p><?php \esc_html_e('Select a user to manage permissions', 'smo-social'); ?></p>
                            </div>

                            <div class="smo-user-permissions" style="display: none;">
                                <!-- User Info Header -->
                                <div class="smo-user-info-header">
                                    <div class="smo-user-avatar">
                                        <img src="" alt="" id="smo-selected-user-avatar">
                                    </div>
                                    <div class="smo-user-details">
                                        <h3 id="smo-selected-user-name"></h3>
                                        <p id="smo-selected-user-email"></p>
                                        <div class="smo-user-role-selector">
                                            <label for="smo-user-role"><?php \esc_html_e('Role:', 'smo-social'); ?></label>
                                            <select id="smo-user-role" aria-label="<?php \esc_attr_e('User role', 'smo-social'); ?>">
                                                <option value="admin"><?php \esc_html_e('Administrator', 'smo-social'); ?></option>
                                                <option value="manager"><?php \esc_html_e('Manager', 'smo-social'); ?></option>
                                                <option value="editor"><?php \esc_html_e('Editor', 'smo-social'); ?></option>
                                                <option value="contributor"><?php \esc_html_e('Contributor', 'smo-social'); ?></option>
                                                <option value="viewer"><?php \esc_html_e('Viewer', 'smo-social'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Permission Categories -->
                                <div class="smo-permissions-categories">
                                    <!-- Content Permissions -->
                                    <div class="smo-permission-category">
                                        <div class="smo-category-header">
                                            <h4>
                                                <span class="dashicons dashicons-admin-post"></span>
                                                <?php \esc_html_e('Content Management', 'smo-social'); ?>
                                            </h4>
                                            <label class="smo-toggle-all">
                                                <input type="checkbox" class="smo-category-toggle" data-category="content">
                                                <span><?php \esc_html_e('Toggle All', 'smo-social'); ?></span>
                                            </label>
                                        </div>
                                        <div class="smo-permission-list">
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="create_posts">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Create Posts', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Create new social media posts', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="edit_posts">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Edit Posts', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Edit existing posts', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="delete_posts">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Delete Posts', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Delete posts permanently', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="publish_posts">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Publish Posts', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Publish posts to social platforms', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="schedule_posts">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Schedule Posts', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Schedule posts for future publishing', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Platform Permissions -->
                                    <div class="smo-permission-category">
                                        <div class="smo-category-header">
                                            <h4>
                                                <span class="dashicons dashicons-share"></span>
                                                <?php \esc_html_e('Platform Access', 'smo-social'); ?>
                                            </h4>
                                            <label class="smo-toggle-all">
                                                <input type="checkbox" class="smo-category-toggle" data-category="platforms">
                                                <span><?php \esc_html_e('Toggle All', 'smo-social'); ?></span>
                                            </label>
                                        </div>
                                        <div class="smo-permission-list">
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="manage_platforms">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Manage Platforms', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Connect and disconnect social platforms', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="view_platform_analytics">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('View Platform Analytics', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Access platform-specific analytics', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Analytics Permissions -->
                                    <div class="smo-permission-category">
                                        <div class="smo-category-header">
                                            <h4>
                                                <span class="dashicons dashicons-chart-area"></span>
                                                <?php \esc_html_e('Analytics & Reports', 'smo-social'); ?>
                                            </h4>
                                            <label class="smo-toggle-all">
                                                <input type="checkbox" class="smo-category-toggle" data-category="analytics">
                                                <span><?php \esc_html_e('Toggle All', 'smo-social'); ?></span>
                                            </label>
                                        </div>
                                        <div class="smo-permission-list">
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="view_analytics">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('View Analytics', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Access analytics dashboard', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="export_reports">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Export Reports', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Export analytics reports', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="create_custom_reports">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Create Custom Reports', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Build custom analytics reports', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Team Permissions -->
                                    <div class="smo-permission-category">
                                        <div class="smo-category-header">
                                            <h4>
                                                <span class="dashicons dashicons-groups"></span>
                                                <?php \esc_html_e('Team Management', 'smo-social'); ?>
                                            </h4>
                                            <label class="smo-toggle-all">
                                                <input type="checkbox" class="smo-category-toggle" data-category="team">
                                                <span><?php \esc_html_e('Toggle All', 'smo-social'); ?></span>
                                            </label>
                                        </div>
                                        <div class="smo-permission-list">
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="manage_users">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Manage Users', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Add, edit, and remove team members', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="manage_permissions">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Manage Permissions', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Modify user permissions and roles', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="view_team_activity">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('View Team Activity', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Monitor team member activities', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Workflow Permissions -->
                                    <div class="smo-permission-category">
                                        <div class="smo-category-header">
                                            <h4>
                                                <span class="dashicons dashicons-networking"></span>
                                                <?php \esc_html_e('Workflows & Approvals', 'smo-social'); ?>
                                            </h4>
                                            <label class="smo-toggle-all">
                                                <input type="checkbox" class="smo-category-toggle" data-category="workflow">
                                                <span><?php \esc_html_e('Toggle All', 'smo-social'); ?></span>
                                            </label>
                                        </div>
                                        <div class="smo-permission-list">
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="approve_content">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Approve Content', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Approve or reject content submissions', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="manage_workflows">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Manage Workflows', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Create and modify approval workflows', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Settings Permissions -->
                                    <div class="smo-permission-category">
                                        <div class="smo-category-header">
                                            <h4>
                                                <span class="dashicons dashicons-admin-settings"></span>
                                                <?php \esc_html_e('Settings & Configuration', 'smo-social'); ?>
                                            </h4>
                                            <label class="smo-toggle-all">
                                                <input type="checkbox" class="smo-category-toggle" data-category="settings">
                                                <span><?php \esc_html_e('Toggle All', 'smo-social'); ?></span>
                                            </label>
                                        </div>
                                        <div class="smo-permission-list">
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="manage_settings">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Manage Settings', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Modify plugin settings and configuration', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="manage_integrations">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('Manage Integrations', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Configure third-party integrations', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                            <label class="smo-permission-item">
                                                <input type="checkbox" name="permissions[]" value="view_logs">
                                                <div class="smo-permission-info">
                                                    <strong><?php \esc_html_e('View Logs', 'smo-social'); ?></strong>
                                                    <p><?php \esc_html_e('Access system logs and audit trails', 'smo-social'); ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Custom Permissions -->
                                <div class="smo-custom-permissions">
                                    <h4><?php \esc_html_e('Custom Permissions', 'smo-social'); ?></h4>
                                    <button class="button" id="smo-add-custom-permission">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <?php \esc_html_e('Add Custom Permission', 'smo-social'); ?>
                                    </button>
                                    <div class="smo-custom-permission-list"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="smo-modal-footer">
                    <div class="smo-permission-actions">
                        <button class="button" id="smo-reset-permissions">
                            <?php \esc_html_e('Reset to Default', 'smo-social'); ?>
                        </button>
                        <button class="button" id="smo-copy-permissions">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php \esc_html_e('Copy from Another User', 'smo-social'); ?>
                        </button>
                    </div>
                    <div class="smo-modal-actions">
                        <button class="button" id="smo-cancel-permissions">
                            <?php \esc_html_e('Cancel', 'smo-social'); ?>
                        </button>
                        <button class="button button-primary" id="smo-save-permissions">
                            <span class="dashicons dashicons-yes"></span>
                            <?php \esc_html_e('Save Permissions', 'smo-social'); ?>
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
        \wp_enqueue_script(
            'smo-user-permissions-modal',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/user-permissions-modal.js',
            array('jquery', 'wp-util'),
            SMO_SOCIAL_VERSION,
            true
        );

        \wp_localize_script('smo-user-permissions-modal', 'smoUserPermissions', array(
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('smo_user_permissions'),
            'strings' => array(
                'error' => \__('An error occurred', 'smo-social'),
                'success' => \__('Permissions saved successfully', 'smo-social'),
                'confirmReset' => \__('Are you sure you want to reset permissions to default?', 'smo-social'),
                'selectUser' => \__('Please select a user', 'smo-social'),
                'loading' => \__('Loading...', 'smo-social'),
            ),
        ));
    }
}
