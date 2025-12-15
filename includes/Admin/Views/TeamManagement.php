<?php
/**
 * Team Management View
 *
 * Comprehensive team management interface with assignments, permissions,
 * and team calendar functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get team data
$team_members = $this->get_team_members();
$user_assignments = $this->get_user_network_assignments();
$team_permissions = $this->get_team_permissions();
$team_calendar_data = $this->get_team_calendar_data();
?>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('team-management', __('Team Management', 'smo-social'));
}
?>

<!-- Modern Gradient Header -->
<div class="smo-header">
    <div class="smo-header-content">
        <h1 class="smo-page-title">
            <span class="smo-icon">👥</span>
            <?php _e('Team Management', 'smo-social'); ?>
        </h1>
        <p class="smo-page-subtitle">
            <?php _e('Manage your team members, assignments, and permissions', 'smo-social'); ?>
        </p>
    </div>
    <div class="smo-header-actions">
        <button type="button" class="smo-btn smo-btn-primary" id="smo-add-team-member">
            <span class="dashicons dashicons-plus"></span>
            <?php _e('Add Team Member', 'smo-social'); ?>
        </button>
    </div>
</div>

<!-- Dashboard Stats Overview -->
<div class="smo-stats-dashboard">
    <div class="smo-stat-card smo-stat-gradient-1">
        <div class="smo-stat-icon">
            <span class="dashicons dashicons-admin-users"></span>
        </div>
        <div class="smo-stat-content">
            <h3 class="smo-stat-number"><?php echo esc_html(count($team_members)); ?></h3>
            <p class="smo-stat-label"><?php _e('Team Members', 'smo-social'); ?></p>
            <span class="smo-stat-trend">👥 Active</span>
        </div>
    </div>

    <div class="smo-stat-card smo-stat-gradient-2">
        <div class="smo-stat-icon">
            <span class="dashicons dashicons-networking"></span>
        </div>
        <div class="smo-stat-content">
            <h3 class="smo-stat-number"><?php echo esc_html(count($user_assignments)); ?></h3>
            <p class="smo-stat-label"><?php _e('Active Assignments', 'smo-social'); ?></p>
            <span class="smo-stat-trend">🔗 Connected</span>
        </div>
    </div>

    <div class="smo-stat-card smo-stat-gradient-3">
        <div class="smo-stat-icon">
            <span class="dashicons dashicons-calendar-alt"></span>
        </div>
        <div class="smo-stat-content">
            <h3 class="smo-stat-number"><?php echo esc_html($team_calendar_data['total_scheduled']); ?></h3>
            <p class="smo-stat-label"><?php _e('Scheduled Posts', 'smo-social'); ?></p>
            <span class="smo-stat-trend">📅 Planned</span>
        </div>
    </div>

    <div class="smo-stat-card smo-stat-gradient-4">
        <div class="smo-stat-icon">
            <span class="dashicons dashicons-yes"></span>
        </div>
        <div class="smo-stat-content">
            <h3 class="smo-stat-number"><?php echo esc_html($team_calendar_data['published_today']); ?></h3>
            <p class="smo-stat-label"><?php _e('Published Today', 'smo-social'); ?></p>
            <span class="smo-stat-trend">✅ Done</span>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="smo-quick-actions">
    <button type="button" class="smo-quick-action-btn" id="smo-quick-create-assignment">
        <span class="dashicons dashicons-plus-alt"></span>
        <span><?php _e('Create Assignment', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-manage-permissions">
        <span class="dashicons dashicons-admin-settings"></span>
        <span><?php _e('Manage Permissions', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-quick-view-calendar">
        <span class="dashicons dashicons-calendar"></span>
        <span><?php _e('View Calendar', 'smo-social'); ?></span>
    </button>
</div>

<div class="smo-card">


    <!-- Team Management Tabs -->
    <div class="smo-tabs-nav">
        <a href="#members" class="smo-tab-link active" data-tab="members">
            <span class="dashicons dashicons-admin-users"></span>
            <?php _e('Team Members', 'smo-social'); ?>
        </a>
        <a href="#assignments" class="smo-tab-link" data-tab="assignments">
            <span class="dashicons dashicons-networking"></span>
            <?php _e('Assignments', 'smo-social'); ?>
        </a>
        <a href="#permissions" class="smo-tab-link" data-tab="permissions">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('Permissions', 'smo-social'); ?>
        </a>
        <a href="#calendar" class="smo-tab-link" data-tab="calendar">
            <span class="dashicons dashicons-calendar"></span>
            <?php _e('Team Calendar', 'smo-social'); ?>
        </a>
        <a href="#networks" class="smo-tab-link" data-tab="networks">
            <span class="dashicons dashicons-groups"></span>
            <?php _e('Network Groups', 'smo-social'); ?>
        </a>
    </div>

        <!-- Team Members Tab -->
        <div id="smo-tab-members" class="smo-tab-panel active">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="smo-icon">👥</span>
                    <?php _e('Team Members', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <button type="button" class="smo-btn smo-btn-primary" id="smo-add-team-member">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Add Team Member', 'smo-social'); ?>
                    </button>
                </div>
            </div>

            <div class="smo-team-members-grid">
                <?php foreach ($team_members as $member): ?>
                <div class="smo-team-member-card" data-member-id="<?php echo esc_attr($member['id']); ?>">
                    <div class="smo-member-header">
                        <div class="smo-member-avatar">
                            <?php echo esc_html(strtoupper(substr($member['name'], 0, 1))); ?>
                        </div>
                        <div class="smo-member-info">
                            <h3><?php echo esc_html($member['name']); ?></h3>
                            <p><?php echo esc_html($member['email']); ?></p>
                            <span class="smo-member-role"><?php echo esc_html($member['role']); ?></span>
                        </div>
                    </div>
                    
                    <div class="smo-member-stats">
                        <div class="smo-member-stat">
                            <span class="smo-stat-label"><?php _e('Posts Scheduled', 'smo-social'); ?></span>
                            <span class="smo-stat-value"><?php echo $member['scheduled_posts']; ?></span>
                        </div>
                        <div class="smo-member-stat">
                            <span class="smo-stat-label"><?php _e('Published', 'smo-social'); ?></span>
                            <span class="smo-stat-value"><?php echo $member['published_posts']; ?></span>
                        </div>
                    </div>
                    
                    <div class="smo-member-actions">
                        <button type="button" class="smo-btn smo-btn-secondary smo-edit-member"><?php _e('Edit', 'smo-social'); ?></button>
                        <button type="button" class="smo-btn smo-btn-secondary smo-assign-networks"><?php _e('Assign Networks', 'smo-social'); ?></button>
                        <button type="button" class="smo-btn smo-btn-secondary smo-view-calendar"><?php _e('View Calendar', 'smo-social'); ?></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Network Assignments Tab -->
        <div id="smo-tab-assignments" class="smo-tab-panel">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="smo-icon">🔗</span>
                    <?php _e('Network Assignments', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <button type="button" class="smo-btn smo-btn-primary" id="smo-create-assignment">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Create Assignment', 'smo-social'); ?>
                    </button>
                </div>
            </div>

            <div class="smo-assignments-grid">
                <?php foreach ($user_assignments as $assignment): ?>
                <div class="smo-assignment-card">
                    <div class="smo-assignment-header">
                        <h3><?php echo esc_html($assignment['assignment_name']); ?></h3>
                        <span class="smo-assignment-type"><?php echo esc_html($assignment['type']); ?></span>
                    </div>
                    
                    <div class="smo-assignment-details">
                        <div class="smo-assignment-member">
                            <strong><?php _e('Member:', 'smo-social'); ?></strong>
                            <?php echo esc_html($assignment['member_name']); ?>
                        </div>
                        
                        <div class="smo-assignment-platforms">
                            <strong><?php _e('Platforms:', 'smo-social'); ?></strong>
                            <div class="smo-platform-list">
                                <?php foreach ($assignment['platforms'] as $platform): ?>
                                <span class="smo-platform-badge"><?php echo esc_html($platform); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="smo-assignment-url-tracking">
                            <strong><?php _e('URL Tracking:', 'smo-social'); ?></strong>
                            <?php echo $assignment['url_tracking'] ? __('Enabled', 'smo-social') : __('Disabled', 'smo-social'); ?>
                        </div>
                    </div>
                    
                    <div class="smo-assignment-actions">
                        <button type="button" class="smo-btn smo-btn-secondary smo-edit-assignment"><?php _e('Edit', 'smo-social'); ?></button>
                        <button type="button" class="smo-btn smo-btn-danger smo-delete-assignment"><?php _e('Delete', 'smo-social'); ?></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Permissions Tab -->
        <div id="smo-tab-permissions" class="smo-tab-panel">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="smo-icon">🔐</span>
                    <?php _e('Team Permissions', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <button type="button" class="smo-btn smo-btn-primary" id="smo-manage-permissions">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Manage Permissions', 'smo-social'); ?>
                    </button>
                </div>
            </div>

            <div class="smo-permissions-matrix">
                <table class="smo-permissions-table">
                    <thead>
                        <tr>
                            <th><?php _e('Member', 'smo-social'); ?></th>
                            <th><?php _e('Create Posts', 'smo-social'); ?></th>
                            <th><?php _e('Edit Posts', 'smo-social'); ?></th>
                            <th><?php _e('Delete Posts', 'smo-social'); ?></th>
                            <th><?php _e('Manage Networks', 'smo-social'); ?></th>
                            <th><?php _e('View Analytics', 'smo-social'); ?></th>
                            <th><?php _e('Team Settings', 'smo-social'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_members as $member): ?>
                        <tr>
                            <td><?php echo esc_html($member['name']); ?></td>
                            <td><?php echo $this->get_permission_checkbox($member['id'], 'create_posts'); ?></td>
                            <td><?php echo $this->get_permission_checkbox($member['id'], 'edit_posts'); ?></td>
                            <td><?php echo $this->get_permission_checkbox($member['id'], 'delete_posts'); ?></td>
                            <td><?php echo $this->get_permission_checkbox($member['id'], 'manage_networks'); ?></td>
                            <td><?php echo $this->get_permission_checkbox($member['id'], 'view_analytics'); ?></td>
                            <td><?php echo $this->get_permission_checkbox($member['id'], 'team_settings'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Team Calendar Tab -->
        <div id="smo-tab-calendar" class="smo-tab-panel">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="smo-icon">📅</span>
                    <?php _e('Team Calendar View', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <select id="smo-calendar-member-filter" class="smo-form-control">
                        <option value="all"><?php _e('All Members', 'smo-social'); ?></option>
                        <?php foreach ($team_members as $member): ?>
                        <option value="<?php echo esc_attr($member['id']); ?>"><?php echo esc_html($member['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="smo-btn smo-btn-secondary" id="smo-refresh-team-calendar">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'smo-social'); ?>
                    </button>
                </div>
            </div>

            <div class="smo-team-calendar-container">
                <?php echo $this->render_team_calendar($team_calendar_data); ?>
            </div>
        </div>

        <!-- Network Groups Tab -->
        <div id="smo-tab-networks" class="smo-tab-panel">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="smo-icon">🌐</span>
                    <?php _e('Network Groupings', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <button type="button" class="smo-btn smo-btn-primary" id="smo-create-network-group">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Create Group', 'smo-social'); ?>
                    </button>
                </div>
            </div>

            <div class="smo-network-groups-list">
                <?php
                $network_groups = $this->get_network_groups();
                foreach ($network_groups as $group): ?>
                <div class="smo-network-group-card" data-group-id="<?php echo esc_attr($group['id']); ?>">
                    <div class="smo-group-header">
                        <h3><?php echo esc_html($group['name']); ?></h3>
                        <div class="smo-group-stats">
                            <span class="smo-member-count"><?php printf(__('%d members', 'smo-social'), count($group['members'])); ?></span>
                            <span class="smo-platform-count"><?php printf(__('%d platforms', 'smo-social'), count($group['platforms'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="smo-group-platforms">
                        <?php foreach ($group['platforms'] as $platform): ?>
                        <span class="smo-platform-badge"><?php echo esc_html($platform); ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="smo-group-members">
                        <strong><?php _e('Members:', 'smo-social'); ?></strong>
                        <?php echo implode(', ', array_column($group['members'], 'name')); ?>
                    </div>
                    
                    <div class="smo-group-actions">
                        <button type="button" class="smo-btn smo-btn-secondary smo-edit-group"><?php _e('Edit', 'smo-social'); ?></button>
                        <button type="button" class="smo-btn smo-btn-secondary smo-assign-members"><?php _e('Assign Members', 'smo-social'); ?></button>
                        <button type="button" class="smo-btn smo-btn-danger smo-delete-group"><?php _e('Delete', 'smo-social'); ?></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="smo-member-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <div class="smo-modal-header">
            <h3><?php _e('Add Team Member', 'smo-social'); ?></h3>
            <button type="button" class="smo-modal-close">&times;</button>
        </div>
        <div class="smo-modal-body">
            <form id="smo-add-member-form" class="smo-form">
                <div class="smo-form-grid">
                    <div class="smo-form-field required">
                        <label class="smo-form-label" for="member_name"><?php _e('Name', 'smo-social'); ?></label>
                        <input type="text" id="member_name" name="name" class="smo-input" required 
                               aria-describedby="member_name-help">
                        <p id="member_name-help" class="smo-form-help">
                            <span class="icon">👤</span>
                            <?php _e('Full name of the team member', 'smo-social'); ?>
                        </p>
                    </div>
                    
                    <div class="smo-form-field required">
                        <label class="smo-form-label" for="member_email"><?php _e('Email', 'smo-social'); ?></label>
                        <input type="email" id="member_email" name="email" class="smo-input" required 
                               aria-describedby="member_email-help">
                        <p id="member_email-help" class="smo-form-help">
                            <span class="icon">📧</span>
                            <?php _e('Email address for invitations and notifications', 'smo-social'); ?>
                        </p>
                    </div>
                    
                    <div class="smo-form-field">
                        <label class="smo-form-label" for="member_role"><?php _e('Role', 'smo-social'); ?></label>
                        <select id="member_role" name="role" class="smo-select" aria-describedby="member_role-help">
                            <option value="member"><?php _e('Member', 'smo-social'); ?></option>
                            <option value="admin"><?php _e('Admin', 'smo-social'); ?></option>
                            <option value="manager"><?php _e('Manager', 'smo-social'); ?></option>
                        </select>
                        <p id="member_role-help" class="smo-form-help">
                            <span class="icon">🔐</span>
                            <?php _e('Determine what level of access this member will have', 'smo-social'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="smo-button-group right">
                    <button type="button" class="smo-btn smo-btn-secondary" onclick="jQuery('#smo-member-modal').hide();">
                        <?php _e('Cancel', 'smo-social'); ?>
                    </button>
                    <button type="submit" class="smo-btn smo-btn-primary">
                        <?php _e('Add Member', 'smo-social'); ?>
                    </button>
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

<script>
/* DIAGNOSTIC: Log jQuery state before initialization */
console.log('SMO Social Debug: jQuery state before initialization');
console.log('jQuery available:', typeof jQuery !== 'undefined');
console.log('jQuery version:', typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'N/A');
console.log('$ available:', typeof $ !== 'undefined');
console.log('$ is function:', typeof $ === 'function');

jQuery(document).ready(function($) {
    // DIAGNOSTIC: Log jQuery state inside ready callback
    console.log('SMO Social Debug: Inside jQuery ready callback');
    console.log('jQuery available inside ready:', typeof jQuery !== 'undefined');
    console.log('$ available inside ready:', typeof $ !== 'undefined');
    console.log('$ is function inside ready:', typeof $ === 'function');

    // Initialize team management functionality
    SMOTeamManagement.init();
});

const SMOTeamManagement = {
    init: function() {
        // DIAGNOSTIC: Log initialization
        console.log('SMO Social Debug: SMOTeamManagement.init() called');

        this.bindEvents();
        this.initializeTabs();
    },

    bindEvents: function() {
        // DIAGNOSTIC: Log bindEvents
        console.log('SMO Social Debug: bindEvents() called');

        // Use jQuery directly to ensure it's available
        const $ = jQuery;

        // Check if jQuery is available
        if (typeof $ === 'undefined' || typeof $.fn === 'undefined') {
            console.error('SMO Social Error: jQuery not available in bindEvents');
            return;
        }

        // Tab switching
        $('.smo-tab-link').on('click', function(e) {
            e.preventDefault();
            const targetTab = $(this).data('tab');
            SMOTeamManagement.switchTab(targetTab);
        });

        // Member management
        $('#smo-add-team-member').on('click', function() {
            $('#smo-member-modal').show();
        });

        $(document).on('click', '.smo-modal-close', function() {
            $(this).closest('.smo-modal').hide();
        });

        $(document).on('click', '.smo-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // Form submissions
        $('#smo-add-member-form').on('submit', this.handleAddMember);

        // Quick actions
        $('#smo-quick-create-assignment').on('click', function() { $('#smo-create-assignment').click(); });
        $('#smo-quick-manage-permissions').on('click', function() { SMOTeamManagement.switchTab('permissions'); });
        $('#smo-quick-view-calendar').on('click', function() { SMOTeamManagement.switchTab('calendar'); });

        // Calendar filtering
        $('#smo-calendar-member-filter').on('change', this.filterCalendarByMember);
        $('#smo-refresh-team-calendar').on('click', this.refreshTeamCalendar);
    },

    initializeTabs: function() {
        // DIAGNOSTIC: Log initializeTabs
        console.log('SMO Social Debug: initializeTabs() called');

        // Use jQuery directly to ensure it's available
        const $ = jQuery;

        // Check if jQuery is available
        if (typeof $ === 'undefined' || typeof $.fn === 'undefined') {
            console.error('SMO Social Error: jQuery not available in initializeTabs');
            return;
        }

        // Initialize first tab as active
        $('.smo-tab-panel').removeClass('active');
        $('#smo-tab-members').addClass('active');
        $('.smo-tab-link').removeClass('active');
        $('.smo-tab-link[data-tab="members"]').addClass('active');

        console.log('SMO Social Debug: Tabs initialized successfully');
    },

    switchTab: function(targetTab) {
        // DIAGNOSTIC: Log switchTab
        console.log('SMO Social Debug: switchTab() called with:', targetTab);

        // Use jQuery directly to ensure it's available
        const $ = jQuery;

        // Check if jQuery is available
        if (typeof $ === 'undefined' || typeof $.fn === 'undefined') {
            console.error('SMO Social Error: jQuery not available in switchTab');
            return;
        }

        // Update tab buttons
        $('.smo-tab-link').removeClass('active');
        $(`.smo-tab-link[data-tab="${targetTab}"]`).addClass('active');

        // Update tab content
        $('.smo-tab-panel').removeClass('active');
        $(`#smo-tab-${targetTab}`).addClass('active');
    },

    handleAddMember: function(e) {
        e.preventDefault();

        // DIAGNOSTIC: Log handleAddMember
        console.log('SMO Social Debug: handleAddMember() called');

        const formData = {
            action: 'smo_add_team_member',
            name: $('#member_name').val(),
            email: $('#member_email').val(),
            role: $('#member_role').val(),
            nonce: '<?php echo wp_create_nonce("smo_team_nonce"); ?>'
        };

        jQuery.post(ajaxurl, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error adding team member: ' + response.data);
            }
        });
    },

    filterCalendarByMember: function() {
        // DIAGNOSTIC: Log filterCalendarByMember
        console.log('SMO Social Debug: filterCalendarByMember() called');

        const memberId = jQuery('#smo-calendar-member-filter').val();
        const posts = jQuery('.smo-calendar-post');

        posts.each(function() {
            const createdBy = jQuery(this).data('created-by');
            if (memberId === 'all' || createdBy === memberId) {
                jQuery(this).show();
            } else {
                jQuery(this).hide();
            }
        });
    },

    refreshTeamCalendar: function() {
        // DIAGNOSTIC: Log refreshTeamCalendar
        console.log('SMO Social Debug: refreshTeamCalendar() called');

        jQuery.post(ajaxurl, {
            action: 'smo_refresh_team_calendar',
            nonce: '<?php echo wp_create_nonce("smo_team_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    }
};
</script>