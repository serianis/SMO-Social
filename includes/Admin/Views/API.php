<?php
/**
 * API Management View
 *
 * API key management and monitoring with modern gradient design
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

/** @var \SMO_Social\Admin\Admin $this */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get API data
$api_keys = $this->get_api_keys();
$api_usage = $this->get_api_usage();

// Calculate stats
$total_keys = count($api_keys);
$active_keys = count(array_filter($api_keys, function($k) { return $k['status'] === 'active'; }));
$total_requests = array_sum(array_column($api_usage, 'requests'));
$avg_response_time = count($api_usage) > 0 ? array_sum(array_column($api_usage, 'avg_response_time')) / count($api_usage) : 0;

// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('api', __('API Management', 'smo-social'));
}
?>

<!-- Modern Gradient Header -->
<div class="smo-import-header">
    <div class="smo-header-content">
        <h1 class="smo-page-title">
            <span class="smo-icon">🔌</span>
            <?php _e('API Management', 'smo-social'); ?>
        </h1>
        <p class="smo-page-subtitle">
            <?php _e('Manage API keys and monitor usage', 'smo-social'); ?>
        </p>
    </div>
    <div class="smo-header-actions">
        <button type="button" class="smo-btn smo-btn-secondary" id="smo-view-docs">
            <span class="dashicons dashicons-book"></span>
            <?php _e('View Docs', 'smo-social'); ?>
        </button>
        <button type="button" class="smo-btn smo-btn-primary" id="smo-generate-api-key">
            <span class="dashicons dashicons-plus"></span>
            <?php _e('Generate API Key', 'smo-social'); ?>
        </button>
    </div>
</div>

<!-- Dashboard Stats Overview -->
<div class="smo-import-dashboard">
    <div class="smo-stats-grid">
        <div class="smo-stat-card smo-stat-gradient-1">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-admin-network"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html($total_keys); ?></h3>
                <p class="smo-stat-label"><?php _e('Total API Keys', 'smo-social'); ?></p>
                <span class="smo-stat-trend">🔑 All Time</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-2">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html($active_keys); ?></h3>
                <p class="smo-stat-label"><?php _e('Active Keys', 'smo-social'); ?></p>
                <span class="smo-stat-trend">✅ Current</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-3">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo number_format($total_requests); ?></h3>
                <p class="smo-stat-label"><?php _e('Total Requests', 'smo-social'); ?></p>
                <span class="smo-stat-trend">📊 All Time</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-4">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-performance"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo number_format($avg_response_time, 0); ?>ms</h3>
                <p class="smo-stat-label"><?php _e('Avg Response', 'smo-social'); ?></p>
                <span class="smo-stat-trend">⚡ Speed</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="smo-quick-actions">
    <button class="smo-quick-action-btn" id="smo-filter-active">
        <span class="dashicons dashicons-yes-alt"></span>
        <span><?php _e('Active Keys', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-filter-inactive">
        <span class="dashicons dashicons-dismiss"></span>
        <span><?php _e('Inactive Keys', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-view-usage">
        <span class="dashicons dashicons-chart-line"></span>
        <span><?php _e('View Usage', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-refresh-stats">
        <span class="dashicons dashicons-update"></span>
        <span><?php _e('Refresh', 'smo-social'); ?></span>
    </button>
</div>

<!-- API Keys Table -->
<div class="smo-card">
    <div class="smo-card-header">
        <h2 class="smo-card-title">
            <span class="dashicons dashicons-admin-network"></span>
            <?php _e('API Keys', 'smo-social'); ?>
        </h2>
    </div>
    <div class="smo-card-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'smo-social'); ?></th>
                    <th><?php _e('Key', 'smo-social'); ?></th>
                    <th><?php _e('Permissions', 'smo-social'); ?></th>
                    <th><?php _e('Created', 'smo-social'); ?></th>
                    <th><?php _e('Last Used', 'smo-social'); ?></th>
                    <th><?php _e('Actions', 'smo-social'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($api_keys)): ?>
                    <?php foreach ($api_keys as $key): ?>
                        <tr>
                            <td><strong><?php echo esc_html($key['name']); ?></strong></td>
                            <td>
                                <code style="background: var(--smo-bg); padding: 4px 8px; border-radius: 4px;">
                                    <?php echo esc_html(substr($key['key'], 0, 20) . '...'); ?>
                                </code>
                            </td>
                            <td><?php echo esc_html(implode(', ', $key['permissions'])); ?></td>
                            <td><?php echo esc_html($key['created']); ?></td>
                            <td><?php echo esc_html($key['last_used'] ?? __('Never', 'smo-social')); ?></td>
                            <td>
                                <button type="button" class="button button-small smo-copy-key"
                                    data-key="<?php echo esc_attr($key['key']); ?>">
                                    <?php _e('Copy', 'smo-social'); ?>
                                </button>
                                <button type="button" class="button button-small button-link-delete smo-revoke-key"
                                    data-key-id="<?php echo esc_attr($key['id']); ?>">
                                    <?php _e('Revoke', 'smo-social'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="smo-empty-state">
                            <p><?php _e('No API keys found. Generate your first API key to get started!', 'smo-social'); ?></p>
                            <button class="smo-btn smo-btn-primary" id="smo-generate-first-key">
                                <span class="dashicons dashicons-plus"></span>
                                <?php _e('Generate API Key', 'smo-social'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- API Usage -->
<div class="smo-card">
    <div class="smo-card-header">
        <h2 class="smo-card-title">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php _e('API Usage Statistics', 'smo-social'); ?>
        </h2>
    </div>
    <div class="smo-card-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Endpoint', 'smo-social'); ?></th>
                    <th><?php _e('Requests', 'smo-social'); ?></th>
                    <th><?php _e('Success Rate', 'smo-social'); ?></th>
                    <th><?php _e('Avg Response Time', 'smo-social'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($api_usage)): ?>
                    <?php foreach ($api_usage as $endpoint => $usage): ?>
                        <tr>
                            <td><code><?php echo esc_html($endpoint); ?></code></td>
                            <td><?php echo number_format($usage['requests']); ?></td>
                            <td>
                                <span class="smo-status-badge <?php echo $usage['success_rate'] >= 95 ? 'smo-status-published' : 'smo-status-warning'; ?>">
                                    <?php echo number_format($usage['success_rate'], 1); ?>%
                                </span>
                            </td>
                            <td><?php echo number_format($usage['avg_response_time'], 2); ?>ms</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="smo-empty-state">
                            <p><?php _e('No API usage data available yet.', 'smo-social'); ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- API Documentation -->
<div class="smo-card">
    <div class="smo-card-header">
        <h2 class="smo-card-title">
            <span class="dashicons dashicons-book"></span>
            <?php _e('API Documentation', 'smo-social'); ?>
        </h2>
    </div>
    <div class="smo-card-body">
        <div class="smo-api-endpoints" style="display: grid; gap: 20px;">
            <div class="smo-api-endpoint">
                <h4>POST /wp-json/smo-social/v1/posts</h4>
                <p><?php _e('Create a new social media post', 'smo-social'); ?></p>
                <pre><code>{
  "content": "Post content",
  "platforms": ["twitter", "facebook"],
  "scheduled_time": "2024-01-01 12:00:00"
}</code></pre>
            </div>

            <div class="smo-api-endpoint">
                <h4>GET /wp-json/smo-social/v1/posts</h4>
                <p><?php _e('Retrieve scheduled posts', 'smo-social'); ?></p>
            </div>

            <div class="smo-api-endpoint">
                <h4>GET /wp-json/smo-social/v1/analytics</h4>
                <p><?php _e('Get analytics data', 'smo-social'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>

<style>
.smo-api-endpoint {
    border: 1px solid var(--smo-border);
    border-radius: var(--smo-radius-md);
    padding: 20px;
    background: white;
}

.smo-api-endpoint h4 {
    margin: 0 0 10px 0;
    color: var(--smo-primary);
    font-family: monospace;
    font-size: 14px;
}

.smo-api-endpoint p {
    margin: 0 0 15px 0;
    color: var(--smo-text-secondary);
}

.smo-api-endpoint pre {
    background: var(--smo-bg);
    padding: 15px;
    border-radius: var(--smo-radius-sm);
    overflow-x: auto;
    margin: 0;
}

.smo-api-endpoint code {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
}

.smo-status-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--smo-warning);
}
</style>

<script>
jQuery(document).ready(function($) {
    // Generate new API key
    $('#smo-generate-api-key, #smo-generate-first-key').on('click', function() {
        const keyName = prompt('<?php _e("Enter a name for the API key:", "smo-social"); ?>');
        if (keyName) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smo_generate_api_key',
                    name: keyName,
                    nonce: '<?php echo wp_create_nonce("smo_api_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e("API key generated successfully!", "smo-social"); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e("Error generating API key", "smo-social"); ?>');
                    }
                }
            });
        }
    });

    // Copy API key
    $('.smo-copy-key').on('click', function() {
        const key = $(this).data('key');
        navigator.clipboard.writeText(key).then(function() {
            alert('<?php _e("API key copied to clipboard!", "smo-social"); ?>');
        });
    });

    // Revoke API key
    $('.smo-revoke-key').on('click', function() {
        if (confirm('<?php _e("Are you sure you want to revoke this API key?", "smo-social"); ?>')) {
            const keyId = $(this).data('key-id');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smo_revoke_api_key',
                    key_id: keyId,
                    nonce: '<?php echo wp_create_nonce("smo_api_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
    });

    // View documentation
    $('#smo-view-docs').on('click', function() {
        $('html, body').animate({
            scrollTop: $('.smo-api-endpoints').offset().top - 100
        }, 500);
    });

    // Refresh stats
    $('#smo-refresh-stats').on('click', function() {
        location.reload();
    });
});
</script>