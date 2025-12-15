<?php
/**
 * User Management View
 *
 * Team collaboration and user management features with modern gradient design
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get users with SMO Social capabilities
$users = $this->get_smo_users();
$roles = $this->get_smo_roles();
$current_user_id = get_current_user_id();

// Get platforms for channel access
$platforms = array();
if (isset($this->platform_manager) && method_exists($this->platform_manager, 'get_connected_platforms')) {
    $platforms = $this->platform_manager->get_connected_platforms();
}
if (empty($platforms)) {
    // Fallback: get from database directly
    global $wpdb;
    $platforms_table = $wpdb->prefix . 'smo_platforms';
    $platforms_result = $wpdb->get_results("SELECT * FROM $platforms_table WHERE status = 'active'", ARRAY_A);
    if ($platforms_result) {
        $platforms = $platforms_result;
    }
}

// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('users', __('User Management', 'smo-social'));
}
?>

    <!-- Modern Gradient Header -->
    <div class="smo-import-header">
        <div class="smo-header-content">
            <h1 class="smo-page-title">
                <span class="smo-icon">👥</span>
                <?php _e('User Management', 'smo-social'); ?>
            </h1>
            <p class="smo-page-subtitle">
                <?php _e('Manage team members, permissions, and approval workflows', 'smo-social'); ?>
            </p>
        </div>
        <div class="smo-header-actions">
            <button type="button" class="smo-btn smo-btn-primary" id="smo-invite-user">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('Invite User', 'smo-social'); ?>
            </button>
        </div>
    </div>

    <!-- Dashboard Stats Overview -->
    <div class="smo-import-dashboard">
        <div class="smo-stats-grid">
            <div class="smo-stat-card smo-stat-gradient-1">
                <div class="smo-stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="smo-stat-content">
                    <h3 class="smo-stat-number"><?php echo count($users); ?></h3>
                    <p class="smo-stat-label"><?php _e('Active Users', 'smo-social'); ?></p>
                    <span class="smo-stat-trend">👥 Team</span>
                </div>
            </div>

            <div class="smo-stat-card smo-stat-gradient-2">
                <div class="smo-stat-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="smo-stat-content">
                    <h3 class="smo-stat-number"><?php echo count(array_filter($users, function ($u) {
                        return $u['role'] === 'editor'; })); ?></h3>
                    <p class="smo-stat-label"><?php _e('Editors', 'smo-social'); ?></p>
                    <span class="smo-stat-trend">✏️ Active</span>
                </div>
            </div>

            <div class="smo-stat-card smo-stat-gradient-3">
                <div class="smo-stat-icon">
                    <span class="dashicons dashicons-edit"></span>
                </div>
                <div class="smo-stat-content">
                    <h3 class="smo-stat-number"><?php echo count(array_filter($users, function ($u) {
                        return $u['role'] === 'author'; })); ?></h3>
                    <p class="smo-stat-label"><?php _e('Authors', 'smo-social'); ?></p>
                    <span class="smo-stat-trend">📝 Writing</span>
                </div>
            </div>

            <div class="smo-stat-card smo-stat-gradient-4">
                <div class="smo-stat-icon">
                    <span class="dashicons dashicons-admin-network"></span>
                </div>
                <div class="smo-stat-content">
                    <h3 class="smo-stat-number"><?php echo count($platforms); ?></h3>
                    <p class="smo-stat-label"><?php _e('Platforms', 'smo-social'); ?></p>
                    <span class="smo-stat-trend">🔗 Connected</span>
                </div>
            </div>
        </div>
    </div>

    <div class="smo-tabs-nav">
        <button class="smo-tab-btn active" data-tab="team-members">
            <span class="dashicons dashicons-groups"></span>
            <?php _e('Team Members', 'smo-social'); ?>
        </button>
        <button class="smo-tab-btn" data-tab="channel-access">
            <span class="dashicons dashicons-admin-network"></span>
            <?php _e('Channel Access', 'smo-social'); ?>
        </button>
        <button class="smo-tab-btn" data-tab="approval-workflows">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php _e('Approval Workflows', 'smo-social'); ?>
        </button>
    </div>

    <div class="smo-tab-content active" id="tab-team-members">
        <div class="smo-grid">
            <!-- Users Table -->
            <div class="smo-card smo-span-2">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e('Team Members', 'smo-social'); ?>
                    </h2>
                </div>
                <div class="smo-card-body">
                    <div class="smo-users-table-container">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('User', 'smo-social'); ?></th>
                                    <th><?php _e('Role', 'smo-social'); ?></th>
                                    <th><?php _e('Last Active', 'smo-social'); ?></th>
                                    <th><?php _e('Actions', 'smo-social'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr data-user-id="<?php echo esc_attr($user['ID']); ?>">
                                        <td>
                                            <div class="smo-user-info">
                                                <img src="<?php echo esc_url($user['avatar']); ?>" alt="" class="smo-user-avatar-enhanced">
                                                <div class="smo-user-details">
                                                    <strong><?php echo esc_html($user['display_name']); ?></strong>
                                                    <br><small><?php echo esc_html($user['user_email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <select class="smo-user-role" data-user-id="<?php echo esc_attr($user['ID']); ?>">
                                                <?php foreach ($roles as $role_key => $role_name): ?>
                                                    <option value="<?php echo esc_attr($role_key); ?>" <?php selected($user['role'], $role_key); ?>>
                                                        <?php echo esc_html($role_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><?php echo esc_html($user['last_active']); ?></td>
                                        <td>
                                            <div class="smo-user-actions">
                                                <?php if ($user['ID'] !== $current_user_id): ?>
                                                    <button type="button" class="button button-small button-link-delete smo-remove-user" data-user-id="<?php echo esc_attr($user['ID']); ?>">
                                                        <?php _e('Remove', 'smo-social'); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="smo-tab-content" id="tab-channel-access">
        <div class="smo-card">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php _e('Channel Access Permissions', 'smo-social'); ?>
                </h2>
                <p class="smo-card-description"><?php _e('Manage which channels each team member can access and their permission level.', 'smo-social'); ?></p>
            </div>
            <div class="smo-card-body">
                <?php if (empty($platforms)): ?>
                    <div class="smo-empty-state">
                        <p><?php _e('No platforms connected. Connect platforms first to manage access.', 'smo-social'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=smo-social-platforms'); ?>" class="smo-btn smo-btn-primary"><?php _e('Connect Platforms', 'smo-social'); ?></a>
                    </div>
                <?php else: ?>
                    <div class="smo-channel-access-container">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('User', 'smo-social'); ?></th>
                                    <?php foreach ($platforms as $platform): ?>
                                        <th class="smo-text-center">
                                            <span class="dashicons dashicons-<?php echo esc_attr(strtolower($platform['platform'] ?? 'admin-site')); ?>"></span>
                                            <?php echo esc_html($platform['account_name'] ?? $platform['platform'] ?? 'Platform'); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr data-user-id="<?php echo esc_attr($user['ID']); ?>">
                                        <td>
                                            <strong><?php echo esc_html($user['display_name']); ?></strong>
                                        </td>
                                        <?php foreach ($platforms as $platform): ?>
                                            <td class="smo-text-center">
                                                <select class="smo-channel-permission" 
                                                        data-user-id="<?php echo esc_attr($user['ID']); ?>" 
                                                        data-platform-id="<?php echo esc_attr($platform['id'] ?? 0); ?>">
                                                    <option value="none"><?php _e('No Access', 'smo-social'); ?></option>
                                                    <option value="view"><?php _e('View Only', 'smo-social'); ?></option>
                                                    <option value="draft"><?php _e('Draft', 'smo-social'); ?></option>
                                                    <option value="publish"><?php _e('Publish', 'smo-social'); ?></option>
                                                </select>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="smo-actions-footer">
                            <button type="button" class="smo-btn smo-btn-primary" id="smo-save-channel-access">
                                <?php _e('Save Changes', 'smo-social'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="smo-tab-content" id="tab-approval-workflows">
        <div class="smo-card">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Approval Workflows', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <button type="button" class="smo-btn smo-btn-primary" id="smo-create-workflow">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Create Workflow', 'smo-social'); ?>
                    </button>
                </div>
            </div>
            <div class="smo-card-body">
                <div id="smo-workflows-list">
                    <!-- Workflows will be loaded here via AJAX -->
                    <div class="smo-loading-spinner"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invite User Modal -->
    <div id="smo-invite-modal" class="smo-modal">
        <div class="smo-modal-content">
            <div class="smo-modal-header">
                <h3><?php _e('Invite Team Member', 'smo-social'); ?></h3>
                <button type="button" class="smo-modal-close">&times;</button>
            </div>
            <div class="smo-modal-body">
                <form id="smo-invite-form">
                    <div class="smo-form-group">
                        <label class="smo-form-label" for="invite_email"><?php _e('Email Address:', 'smo-social'); ?></label>
                        <input type="email" id="invite_email" name="email" required class="smo-form-input">
                    </div>
                    <div class="smo-form-group">
                        <label class="smo-form-label" for="invite_role"><?php _e('Role:', 'smo-social'); ?></label>
                        <select id="invite_role" name="role" class="smo-form-select">
                            <?php foreach ($roles as $role_key => $role_name): ?>
                                <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="smo-form-group">
                        <button type="submit" class="smo-btn smo-btn-primary"><?php _e('Send Invitation', 'smo-social'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Workflow Modal -->
    <div id="smo-workflow-modal" class="smo-modal">
        <div class="smo-modal-content">
            <div class="smo-modal-header">
                <h3><?php _e('Create Approval Workflow', 'smo-social'); ?></h3>
                <button type="button" class="smo-modal-close">&times;</button>
            </div>
            <div class="smo-modal-body">
                <form id="smo-workflow-form">
                    <input type="hidden" name="workflow_id" id="workflow_id" value="">
                    <div class="smo-form-group">
                        <label class="smo-form-label" for="workflow_name"><?php _e('Workflow Name:', 'smo-social'); ?></label>
                        <input type="text" id="workflow_name" name="name" required class="smo-form-input" placeholder="e.g., Standard Content Approval">
                    </div>
                    <div class="smo-form-group">
                        <label class="smo-form-label" for="workflow_description"><?php _e('Description:', 'smo-social'); ?></label>
                        <textarea id="workflow_description" name="description" rows="3" class="smo-form-control"></textarea>
                    </div>
                    <div class="smo-form-group">
                        <label class="smo-form-label" for="required_approvals"><?php _e('Required Approvals:', 'smo-social'); ?></label>
                        <input type="number" id="required_approvals" name="required_approvals" min="1" value="1" class="smo-form-input smo-w-100">
                    </div>
                    <div class="smo-form-group">
                        <label class="smo-form-label"><?php _e('Select Approvers:', 'smo-social'); ?></label>
                        <div class="smo-approvers-list">
                            <?php foreach ($users as $user): ?>
                                <label class="smo-label-flex">
                                    <input type="checkbox" name="approvers[]" value="<?php echo esc_attr($user['ID']); ?>">
                                    <?php echo esc_html($user['display_name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="smo-form-group">
                        <button type="submit" class="smo-btn smo-btn-primary"><?php _e('Save Workflow', 'smo-social'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>


<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.smo-tab-btn').on('click', function() {
        $('.smo-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.smo-tab-content').hide();
        $('#tab-' + $(this).data('tab')).show();
        
        if ($(this).data('tab') === 'approval-workflows') {
            loadWorkflows();
        } else if ($(this).data('tab') === 'channel-access') {
            loadChannelAccess();
        }
    });

    // Invite User Modal
    $('#smo-invite-user').on('click', function() {
        $('#smo-invite-modal').show();
    });

    // Create Workflow Modal
    $('#smo-create-workflow').on('click', function() {
        $('#smo-workflow-form')[0].reset();
        $('#workflow_id').val('');
        $('#smo-workflow-modal').show();
    });

    $('.smo-modal-close').on('click', function() {
        $('.smo-modal').hide();
    });

    // Save Channel Access
    $('#smo-save-channel-access').on('click', function() {
        const permissions = [];
        $('.smo-channel-permission').each(function() {
            permissions.push({
                user_id: $(this).data('user-id'),
                platform_id: $(this).data('platform-id'),
                access_level: $(this).val()
            });
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_save_channel_access',
                permissions: permissions,
                nonce: '<?php echo wp_create_nonce("smo_users_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e("Permissions saved successfully!", "smo-social"); ?>');
                }
            }
        });
    });

    // Save Workflow
    $('#smo-workflow-form').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=smo_save_workflow&nonce=<?php echo wp_create_nonce("smo_users_nonce"); ?>',
            success: function(response) {
                if (response.success) {
                    $('#smo-workflow-modal').hide();
                    loadWorkflows();
                }
            }
        });
    });

    // Load Workflows
    function loadWorkflows() {
        $('#smo-workflows-list').html('<div class="smo-loading-spinner"></div>');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_get_workflows',
                nonce: '<?php echo wp_create_nonce("smo_users_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    renderWorkflows(response.data.workflows);
                }
            }
        });
    }

    function renderWorkflows(workflows) {
        if (workflows.length === 0) {
            $('#smo-workflows-list').html('<div class="smo-empty-state"><p><?php _e("No workflows found.", "smo-social"); ?></p></div>');
            return;
        }

        let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php _e("Name", "smo-social"); ?></th><th><?php _e("Approvers", "smo-social"); ?></th><th><?php _e("Required", "smo-social"); ?></th><th><?php _e("Actions", "smo-social"); ?></th></tr></thead><tbody>';
        
        workflows.forEach(function(workflow) {
            html += `<tr>
                <td><strong>${workflow.name}</strong><br><small>${workflow.description || ''}</small></td>
                <td>${workflow.approvers_count} <?php _e("Approvers", "smo-social"); ?></td>
                <td>${workflow.required_approvals}</td>
                <td>
                    <button class="button button-small smo-edit-workflow" data-id="${workflow.id}"><?php _e("Edit", "smo-social"); ?></button>
                    <button class="button button-small button-link-delete smo-delete-workflow" data-id="${workflow.id}"><?php _e("Delete", "smo-social"); ?></button>
                </td>
            </tr>`;
        });
        
        html += '</tbody></table>';
        $('#smo-workflows-list').html(html);
    }

    // Load Channel Access
    function loadChannelAccess() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_get_channel_access',
                nonce: '<?php echo wp_create_nonce("smo_users_nonce"); ?>'
            },
            success: function(response) {
                if (response.success && response.data.permissions) {
                    response.data.permissions.forEach(function(perm) {
                        $(`.smo-channel-permission[data-user-id="${perm.user_id}"][data-platform-id="${perm.platform_id}"]`).val(perm.access_level);
                    });
                }
            }
        });
    }
    
    // Delete Workflow
    $(document).on('click', '.smo-delete-workflow', function() {
        if(!confirm('<?php _e("Are you sure?", "smo-social"); ?>')) return;
        
        const id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_delete_workflow',
                id: id,
                nonce: '<?php echo wp_create_nonce("smo_users_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) loadWorkflows();
            }
        });
    });
});
</script>