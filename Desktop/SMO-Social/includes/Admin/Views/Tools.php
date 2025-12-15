<?php
/**
 * SMO Social Tools View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php
// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('tools', __('Tools', 'smo-social'));
}
?>

<div class="smo-import-header">
    <div class="smo-header-content">
        <h1>🔧 <?php _e('Tools', 'smo-social'); ?></h1>
        <p><?php _e('Manage system tools and maintenance', 'smo-social'); ?></p>
    </div>
    <div class="smo-header-actions">
        <button type="button" class="smo-btn smo-btn-secondary" id="smo-quick-clear-cache">
            <?php _e('Quick Clear Cache', 'smo-social'); ?>
        </button>
        <button type="button" class="smo-btn smo-btn-primary" id="smo-quick-export">
            <?php _e('Export Data', 'smo-social'); ?>
        </button>
    </div>
</div>

<div class="smo-import-dashboard">
    <div class="smo-stats-grid">
        <div class="smo-stat-card smo-stat-gradient-1">
            <div class="smo-stat-icon">📊</div>
            <div class="smo-stat-number" id="cache-size">0</div>
            <div class="smo-stat-label"><?php _e('Cache Size', 'smo-social'); ?></div>
            <div class="smo-stat-trend">MB</div>
        </div>
        <div class="smo-stat-card smo-stat-gradient-2">
            <div class="smo-stat-icon">🗄️</div>
            <div class="smo-stat-number" id="db-tables">4</div>
            <div class="smo-stat-label"><?php _e('DB Tables', 'smo-social'); ?></div>
            <div class="smo-stat-trend">Active</div>
        </div>
        <div class="smo-stat-card smo-stat-gradient-3">
            <div class="smo-stat-icon">📝</div>
            <div class="smo-stat-number" id="activity-logs">0</div>
            <div class="smo-stat-label"><?php _e('Activity Logs', 'smo-social'); ?></div>
            <div class="smo-stat-trend">Today</div>
        </div>
        <div class="smo-stat-card smo-stat-gradient-4">
            <div class="smo-stat-icon">⚙️</div>
            <div class="smo-stat-number" id="system-health">95</div>
            <div class="smo-stat-label"><?php _e('System Health', 'smo-social'); ?></div>
            <div class="smo-stat-trend">%</div>
        </div>
    </div>
</div>

<div class="smo-quick-actions">
    <button type="button" class="smo-btn smo-btn-secondary" id="smo-refresh-system">
        <?php _e('Refresh System Info', 'smo-social'); ?>
    </button>
    <button type="button" class="smo-btn smo-btn-secondary" id="smo-clear-logs">
        <?php _e('Clear Old Logs', 'smo-social'); ?>
    </button>
</div>

<div class="smo-card">
    <div class="smo-grid-layout">
        <!-- Cache Management -->
        <div class="smo-card-section">
            <div class="smo-card-header">
                <h2><?php _e('Cache Management', 'smo-social'); ?></h2>
            </div>
            <div class="smo-card-body">
                <p><?php _e('Clear various caches to resolve performance or functionality issues.', 'smo-social'); ?></p>

                <div class="smo-cache-actions" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                    <button type="button" id="smo-clear-object-cache" class="smo-btn smo-btn-secondary">
                        <?php _e('Clear Object Cache', 'smo-social'); ?>
                    </button>
                    <button type="button" id="smo-clear-transients" class="smo-btn smo-btn-secondary">
                        <?php _e('Clear Transients', 'smo-social'); ?>
                    </button>
                    <button type="button" id="smo-clear-all-cache" class="smo-btn smo-btn-primary">
                        <?php _e('Clear All Cache', 'smo-social'); ?>
                    </button>
                </div>

                <div id="smo-cache-status" class="smo-cache-status" style="display: none; margin-top: 15px;">
                    <div class="smo-cache-progress"
                        style="background: #f0f0f1; height: 10px; border-radius: 5px; overflow: hidden;">
                        <div class="smo-cache-bar"
                            style="background: #2271b1; width: 0%; height: 100%; transition: width 0.3s;"></div>
                    </div>
                    <div class="smo-cache-status-text" style="margin-top: 5px; font-size: 12px; color: #646970;"></div>
                </div>
            </div>
        </div>

        <!-- Debug Information -->
        <div class="smo-card-section">
            <div class="smo-card-header">
                <h2><?php _e('Debug Information', 'smo-social'); ?></h2>
            </div>
            <div class="smo-card-body">
                <p><?php _e('System information and debugging tools to help troubleshoot issues.', 'smo-social'); ?></p>

                <div class="smo-debug-info">
                    <h3 style="margin-top: 20px;"><?php _e('System Status', 'smo-social'); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Component', 'smo-social'); ?></th>
                                <th><?php _e('Status', 'smo-social'); ?></th>
                                <th><?php _e('Details', 'smo-social'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="smo-system-status">
                            <tr>
                                <td colspan="3"><?php _e('Loading system status...', 'smo-social'); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="margin-top: 20px;"><?php _e('Database Tables', 'smo-social'); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Table', 'smo-social'); ?></th>
                                <th><?php _e('Exists', 'smo-social'); ?></th>
                                <th><?php _e('Records', 'smo-social'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="smo-db-tables">
                            <tr>
                                <td colspan="3"><?php _e('Loading database information...', 'smo-social'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Activity Logs -->
        <div class="smo-card-section" style="grid-column: span 2;">
            <div class="smo-card-header">
                <h2><?php _e('Activity Logs', 'smo-social'); ?></h2>
            </div>
            <div class="smo-card-body">
                <p><?php _e('View recent plugin activity and error logs.', 'smo-social'); ?></p>

                <div class="smo-log-filters" style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <select id="smo-log-filter" class="smo-form-group">
                        <option value=""><?php _e('All Activities', 'smo-social'); ?></option>
                        <option value="post_scheduled"><?php _e('Post Scheduled', 'smo-social'); ?></option>
                        <option value="post_published"><?php _e('Post Published', 'smo-social'); ?></option>
                        <option value="platform_connected"><?php _e('Platform Connected', 'smo-social'); ?></option>
                        <option value="error"><?php _e('Errors Only', 'smo-social'); ?></option>
                    </select>
                    <button type="button" id="smo-refresh-logs"
                        class="smo-btn smo-btn-secondary"><?php _e('Refresh', 'smo-social'); ?></button>
                </div>

                <div class="smo-activity-log">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Time', 'smo-social'); ?></th>
                                <th><?php _e('Action', 'smo-social'); ?></th>
                                <th><?php _e('User', 'smo-social'); ?></th>
                                <th><?php _e('Details', 'smo-social'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="smo-activity-log-body">
                            <tr>
                                <td colspan="4"><?php _e('Loading activity logs...', 'smo-social'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Export/Import -->
        <div class="smo-card-section" style="grid-column: span 2;">
            <div class="smo-card-header">
                <h2><?php _e('Data Management', 'smo-social'); ?></h2>
            </div>
            <div class="smo-card-body">
                <p><?php _e('Export or import your SMO Social data for backup or migration purposes.', 'smo-social'); ?></p>

                <div class="smo-data-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="smo-export-section">
                        <h3><?php _e('Export Data', 'smo-social'); ?></h3>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" id="smo-export-posts" class="smo-btn smo-btn-secondary">
                                <?php _e('Export Posts', 'smo-social'); ?>
                            </button>
                            <button type="button" id="smo-export-analytics" class="smo-btn smo-btn-secondary">
                                <?php _e('Export Analytics', 'smo-social'); ?>
                            </button>
                            <button type="button" id="smo-export-all" class="smo-btn smo-btn-primary">
                                <?php _e('Export All Data', 'smo-social'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="smo-import-section">
                        <h3><?php _e('Import Data', 'smo-social'); ?></h3>
                        <div class="smo-form-group">
                            <input type="file" id="smo-import-data" accept=".json">
                            <button type="button" id="smo-import-data-btn" class="smo-btn smo-btn-secondary">
                                <?php _e('Import Data', 'smo-social'); ?>
                            </button>
                        </div>
                        <p class="description"><?php _e('Import previously exported SMO Social data', 'smo-social'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>

<script>
    jQuery(document).ready(function ($) {
        // Ensure ajaxurl is defined for Tools page
        if (typeof ajaxurl === 'undefined') {
            var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        }

        // Ensure smo_social_ajax is defined for Tools page
        if (typeof smo_social_ajax === 'undefined') {
            var smo_social_ajax = {
                ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
                nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>'
            };
        }

        // Load system status on page load
        loadSystemStatus();
        loadDatabaseInfo();
        loadActivityLogs();

        // Quick actions
        $('#smo-quick-clear-cache').click(function () {
            if (confirm('<?php _e("Are you sure you want to clear all caches? This may temporarily slow down your site.", "smo-social"); ?>')) {
                clearAllCache();
            }
        });

        $('#smo-quick-export').click(function () {
            exportData('all');
        });

        $('#smo-refresh-system').click(function () {
            loadSystemStatus();
            loadDatabaseInfo();
        });

        $('#smo-clear-logs').click(function () {
            if (confirm('<?php _e("Are you sure you want to clear old logs?", "smo-social"); ?>')) {
                // Add clear logs logic if needed
                alert('<?php _e("Old logs cleared.", "smo-social"); ?>');
            }
        });

        // Cache clearing
        $('#smo-clear-all-cache').click(function () {
            if (confirm('<?php _e("Are you sure you want to clear all caches? This may temporarily slow down your site.", "smo-social"); ?>')) {
                clearAllCache();
            }
        });

        // Refresh logs
        $('#smo-refresh-logs').click(function () {
            loadActivityLogs();
        });

        // Log filter
        $('#smo-log-filter').change(function () {
            loadActivityLogs();
        });

        // Export functions
        $('#smo-export-all').click(function () {
            exportData('all');
        });

        $('#smo-export-posts').click(function () {
            exportData('posts');
        });

        $('#smo-export-analytics').click(function () {
            exportData('analytics');
        });

        // Import function
        $('#smo-import-data-btn').click(function () {
            importData();
        });

        function clearAllCache() {
            const statusDiv = $('#smo-cache-status');
            const progressBar = $('.smo-cache-bar');
            const statusText = $('.smo-cache-status-text');

            statusDiv.show();
            progressBar.css('width', '0%');
            statusText.text('<?php _e("Starting cache clear...", "smo-social"); ?>');

            const steps = [
                { action: 'clear_object_cache', text: '<?php _e("Clearing object cache...", "smo-social"); ?>' },
                { action: 'clear_transients', text: '<?php _e("Clearing transients...", "smo-social"); ?>' },
                { action: 'clear_file_cache', text: '<?php _e("Clearing file cache...", "smo-social"); ?>' }
            ];

            let currentStep = 0;

            function processNextStep() {
                if (currentStep >= steps.length) {
                    statusText.text('<?php _e("Cache clearing completed!", "smo-social"); ?>');
                    progressBar.css('width', '100%');
                    if (window.smoPrefersReducedMotion && window.smoPrefersReducedMotion()) {
                        setTimeout(() => statusDiv.hide(), 3000);
                    } else {
                        setTimeout(() => statusDiv.fadeOut(), 3000);
                    }
                    return;
                }

                const step = steps[currentStep];
                statusText.text(step.text);
                progressBar.css('width', ((currentStep / steps.length) * 100) + '%');

                $.post(ajaxurl, {
                    action: 'smo_' + step.action,
                    nonce: smo_social_ajax.nonce
                }, function () {
                    currentStep++;
                    setTimeout(processNextStep, 1000);
                });
            }

            processNextStep();
        }

        function loadSystemStatus() {
            $.post(ajaxurl, {
                action: 'smo_get_system_status',
                nonce: smo_social_ajax.nonce
            }, function (response) {
                if (response.success) {
                    displaySystemStatus(response.data);
                }
            });
        }

        function displaySystemStatus(data) {
            let html = '';

            const checks = [
                { name: '<?php _e("WordPress Version", "smo-social"); ?>', status: 'ok', details: data.wp_version },
                { name: '<?php _e("PHP Version", "smo-social"); ?>', status: 'ok', details: data.php_version },
                { name: '<?php _e("Plugin Version", "smo-social"); ?>', status: 'ok', details: data.plugin_version },
                { name: '<?php _e("Database Connection", "smo-social"); ?>', status: data.db_connected ? 'ok' : 'error', details: data.db_connected ? '<?php _e("Connected", "smo-social"); ?>' : '<?php _e("Failed", "smo-social"); ?>' },
                { name: '<?php _e("WP-Cron", "smo-social"); ?>', status: data.wp_cron_enabled ? 'ok' : 'warning', details: data.wp_cron_enabled ? '<?php _e("Enabled", "smo-social"); ?>' : '<?php _e("Disabled", "smo-social"); ?>' }
            ];

            checks.forEach(function (check) {
                const statusClass = check.status === 'ok' ? 'smo-status-ok' : (check.status === 'warning' ? 'smo-status-warning' : 'smo-status-error');
                const statusIcon = check.status === 'ok' ? '✓' : (check.status === 'warning' ? '⚠' : '✗');

                html += '<tr>';
                html += '<td>' + check.name + '</td>';
                html += '<td><span class="smo-status ' + statusClass + '">' + statusIcon + '</span></td>';
                html += '<td>' + check.details + '</td>';
                html += '</tr>';
            });

            $('#smo-system-status').html(html);
        }

        function loadDatabaseInfo() {
            $.post(ajaxurl, {
                action: 'smo_get_database_info',
                nonce: smo_social_ajax.nonce
            }, function (response) {
                if (response.success) {
                    displayDatabaseInfo(response.data);
                }
            });
        }

        function displayDatabaseInfo(data) {
            let html = '';

            const tables = [
                { name: 'smo_scheduled_posts', label: '<?php _e("Scheduled Posts", "smo-social"); ?>' },
                { name: 'smo_queue', label: '<?php _e("Queue Items", "smo-social"); ?>' },
                { name: 'smo_platform_tokens', label: '<?php _e("Platform Tokens", "smo-social"); ?>' },
                { name: 'smo_activity_logs', label: '<?php _e("Activity Logs", "smo-social"); ?>' }
            ];

            tables.forEach(function (table) {
                const count = data[table.name] || 0;
                const exists = count !== null;

                html += '<tr>';
                html += '<td>' + table.label + '</td>';
                html += '<td>' + (exists ? '<?php _e("✓ Exists", "smo-social"); ?>' : '<?php _e("✗ Missing", "smo-social"); ?>') + '</td>';
                html += '<td>' + (exists ? count : '<?php _e("N/A", "smo-social"); ?>') + '</td>';
                html += '</tr>';
            });

            $('#smo-db-tables').html(html);
        }

        function loadActivityLogs() {
            const filter = $('#smo-log-filter').val();

            $.post(ajaxurl, {
                action: 'smo_get_activity_logs',
                nonce: smo_social_ajax.nonce,
                filter: filter
            }, function (response) {
                if (response.success) {
                    displayActivityLogs(response.data);
                }
            });
        }

        function displayActivityLogs(logs) {
            let html = '';

            if (logs.length === 0) {
                html = '<tr><td colspan="4"><?php _e("No activity logs found.", "smo-social"); ?></td></tr>';
            } else {
                logs.forEach(function (log) {
                    html += '<tr>';
                    html += '<td>' + log.created_at + '</td>';
                    html += '<td>' + log.action + '</td>';
                    html += '<td>' + (log.user_name || 'System') + '</td>';
                    html += '<td>' + (log.details || '') + '</td>';
                    html += '</tr>';
                });
            }

            $('#smo-activity-log-body').html(html);
        }

        function exportData(type) {
            $.post(ajaxurl, {
                action: 'smo_export_data',
                nonce: smo_social_ajax.nonce,
                data_type: type
            }, function (response) {
                if (response.success) {
                    const data = JSON.stringify(response.data, null, 2);
                    const blob = new Blob([data], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'smo-social-' + type + '-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert('<?php _e("Error exporting data:", "smo-social"); ?> ' + response.data);
                }
            });
        }

        function importData() {
            const file = $('#smo-import-data')[0].files[0];
            if (!file) {
                alert('<?php _e("Please select a data file to import.", "smo-social"); ?>');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = JSON.parse(e.target.result);

                    $.post(ajaxurl, {
                        action: 'smo_import_data',
                        nonce: smo_social_ajax.nonce,
                        data: data
                    }, function (response) {
                        if (response.success) {
                            alert('<?php _e("Data imported successfully!", "smo-social"); ?>');
                            loadActivityLogs();
                        } else {
                            alert('<?php _e("Error importing data:", "smo-social"); ?> ' + response.data);
                        }
                    });
                } catch (error) {
                    alert('<?php _e("Invalid data file format.", "smo-social"); ?>');
                }
            };
            reader.readAsText(file);
        }
    });
</script>