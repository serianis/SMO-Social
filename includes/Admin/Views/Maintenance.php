<?php
/**
 * Maintenance Tools View
 *
 * System maintenance and optimization tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get system info
$system_info = $this->get_system_info();
$maintenance_tasks = $this->get_maintenance_tasks();

// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('maintenance', __('Maintenance', 'smo-social'));
}
?>
<div class="smo-import-header">
    <div class="smo-header-content">
        <h1><span class="smo-header-icon">🛠️</span> <?php _e('Maintenance', 'smo-social'); ?></h1>
        <p><?php _e('Keep your system running smoothly with automated maintenance tools', 'smo-social'); ?></p>
    </div>
    <div class="smo-header-actions">
        <button class="smo-btn smo-btn-primary" id="smo-run-all-tasks"><?php _e('Run All Tasks', 'smo-social'); ?></button>
    </div>
</div>

<div class="smo-import-dashboard">
    <div class="smo-stats-grid">
        <div class="smo-stat-card smo-stat-gradient-1">
            <div class="smo-stat-icon">⚙️</div>
            <div class="smo-stat-content">
                <div class="smo-stat-number"><?php echo esc_html($system_info['php_version']); ?></div>
                <div class="smo-stat-label"><?php _e('PHP Version', 'smo-social'); ?></div>
                <div class="smo-stat-trend">✓ <?php _e('Compatible', 'smo-social'); ?></div>
            </div>
        </div>
        <div class="smo-stat-card smo-stat-gradient-2">
            <div class="smo-stat-icon">🗄️</div>
            <div class="smo-stat-content">
                <div class="smo-stat-number"><?php echo esc_html($system_info['db_size']); ?></div>
                <div class="smo-stat-label"><?php _e('Database Size', 'smo-social'); ?></div>
                <div class="smo-stat-trend">📈 <?php _e('Growing', 'smo-social'); ?></div>
            </div>
        </div>
        <div class="smo-stat-card smo-stat-gradient-3">
            <div class="smo-stat-icon">💾</div>
            <div class="smo-stat-content">
                <div class="smo-stat-number"><?php echo esc_html($system_info['cache_size']); ?></div>
                <div class="smo-stat-label"><?php _e('Cache Size', 'smo-social'); ?></div>
                <div class="smo-stat-trend">🔄 <?php _e('Optimized', 'smo-social'); ?></div>
            </div>
        </div>
        <div class="smo-stat-card smo-stat-gradient-4">
            <div class="smo-stat-icon">⏱️</div>
            <div class="smo-stat-content">
                <div class="smo-stat-number"><?php echo esc_html($system_info['uptime']); ?></div>
                <div class="smo-stat-label"><?php _e('System Uptime', 'smo-social'); ?></div>
                <div class="smo-stat-trend">🟢 <?php _e('Running', 'smo-social'); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="smo-grid-layout">
    <!-- System Information -->
    <div class="smo-card" style="grid-column: span 2;">
        <div class="smo-card-header">
            <h2><?php _e('System Information', 'smo-social'); ?></h2>
        </div>
        <div class="smo-card-body">
            <div class="smo-system-info-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div class="smo-info-item"
                    style="display: flex; justify-content: space-between; padding: 10px; background: var(--smo-bg); border-radius: 4px;">
                    <strong><?php _e('PHP Version:', 'smo-social'); ?></strong>
                    <span><?php echo esc_html($system_info['php_version']); ?></span>
                </div>
                <div class="smo-info-item"
                    style="display: flex; justify-content: space-between; padding: 10px; background: var(--smo-bg); border-radius: 4px;">
                    <strong><?php _e('WordPress Version:', 'smo-social'); ?></strong>
                    <span><?php echo esc_html($system_info['wp_version']); ?></span>
                </div>
                <div class="smo-info-item"
                    style="display: flex; justify-content: space-between; padding: 10px; background: var(--smo-bg); border-radius: 4px;">
                    <strong><?php _e('Database Size:', 'smo-social'); ?></strong>
                    <span><?php echo esc_html($system_info['db_size']); ?></span>
                </div>
                <div class="smo-info-item"
                    style="display: flex; justify-content: space-between; padding: 10px; background: var(--smo-bg); border-radius: 4px;">
                    <strong><?php _e('Cache Size:', 'smo-social'); ?></strong>
                    <span><?php echo esc_html($system_info['cache_size']); ?></span>
                </div>
                <div class="smo-info-item"
                    style="display: flex; justify-content: space-between; padding: 10px; background: var(--smo-bg); border-radius: 4px;">
                    <strong><?php _e('Log Files:', 'smo-social'); ?></strong>
                    <span><?php echo esc_html($system_info['log_files']); ?> files</span>
                </div>
                <div class="smo-info-item"
                    style="display: flex; justify-content: space-between; padding: 10px; background: var(--smo-bg); border-radius: 4px;">
                    <strong><?php _e('Uptime:', 'smo-social'); ?></strong>
                    <span><?php echo esc_html($system_info['uptime']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Tasks -->
    <div class="smo-card" style="grid-column: span 2;">
        <div class="smo-card-header">
            <h2><?php _e('Maintenance Tasks', 'smo-social'); ?></h2>
        </div>
        <div class="smo-card-body">
            <div class="smo-maintenance-tasks" style="display: grid; gap: 15px;">
                <?php
                $sanitizer = '\SMO_Social\Admin\Helpers\ViewDataSanitizer';
                foreach ($maintenance_tasks as $task):
                    $task_title = $sanitizer::safe_get($task, 'title', __('Untitled Task', 'smo-social'));
                    $task_status = $sanitizer::safe_get($task, 'status', 'pending');
                    $task_description = $sanitizer::safe_get($task, 'description', '');
                    $task_id = $sanitizer::safe_get($task, 'id', '');
                    $task_last_run = $sanitizer::safe_get($task, 'last_run', '');

                    $bg_color = $task_status === 'completed' ? '#d1e7dd' : ($task_status === 'pending' ? '#fff3cd' : ($task_status === 'running' ? '#cff4fc' : '#f8d7da'));
                    $text_color = $task_status === 'completed' ? '#0f5132' : ($task_status === 'pending' ? '#664d03' : ($task_status === 'running' ? '#055160' : '#721c24'));
                ?>
                    <div class="smo-maintenance-task" style="padding: 15px; border: 1px solid var(--smo-border); border-radius: 4px;">
                        <div class="smo-task-header"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h4 style="margin: 0;"><?php echo esc_html($task_title); ?></h4>
                            <span class="smo-task-status <?php echo esc_attr($task_status); ?>"
                                style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; background: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>;">
                                <?php echo esc_html(ucfirst($task_status)); ?>
                            </span>
                        </div>
                        <p><?php echo esc_html($task_description); ?></p>
                        <div class="smo-task-actions"
                            style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                            <button type="button" class="smo-btn smo-btn-secondary smo-run-task"
                                data-task="<?php echo esc_attr($task_id); ?>">
                                <?php _e('Run Now', 'smo-social'); ?>
                            </button>
                            <?php if (!empty($task_last_run)): ?>
                                <span class="smo-last-run"
                                    style="font-size: 12px; color: var(--smo-text-secondary);"><?php printf(__('Last run: %s', 'smo-social'), esc_html($task_last_run)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Database Optimization -->
    <div class="smo-card">
        <div class="smo-card-header">
            <h2><?php _e('Database Optimization', 'smo-social'); ?></h2>
        </div>
        <div class="smo-card-body">
            <div class="smo-database-tools" style="display: grid; gap: 15px;">
                <div class="smo-db-tool" style="padding: 15px; border: 1px solid var(--smo-border); border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;"><?php _e('Clean Old Logs', 'smo-social'); ?></h4>
                    <p style="margin: 0 0 15px 0; color: var(--smo-text-secondary);">
                        <?php _e('Remove log entries older than 30 days', 'smo-social'); ?></p>
                    <button type="button" class="smo-btn smo-btn-secondary smo-clean-logs">
                        <?php _e('Clean Logs', 'smo-social'); ?>
                    </button>
                </div>

                <div class="smo-db-tool" style="padding: 15px; border: 1px solid var(--smo-border); border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;"><?php _e('Optimize Tables', 'smo-social'); ?></h4>
                    <p style="margin: 0 0 15px 0; color: var(--smo-text-secondary);">
                        <?php _e('Optimize database tables for better performance', 'smo-social'); ?></p>
                    <button type="button" class="smo-btn smo-btn-secondary smo-optimize-tables">
                        <?php _e('Optimize', 'smo-social'); ?>
                    </button>
                </div>

                <div class="smo-db-tool" style="padding: 15px; border: 1px solid var(--smo-border); border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;"><?php _e('Clear Cache', 'smo-social'); ?></h4>
                    <p style="margin: 0 0 15px 0; color: var(--smo-text-secondary);"><?php _e('Clear all cached data', 'smo-social'); ?>
                    </p>
                    <button type="button" class="smo-btn smo-btn-secondary smo-clear-cache">
                        <?php _e('Clear Cache', 'smo-social'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup & Restore -->
    <div class="smo-card">
        <div class="smo-card-header">
            <h2><?php _e('Backup & Restore', 'smo-social'); ?></h2>
        </div>
        <div class="smo-card-body">
            <div class="smo-backup-tools" style="display: grid; gap: 15px;">
                <div class="smo-backup-tool" style="padding: 15px; border: 1px solid var(--smo-border); border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;"><?php _e('Create Backup', 'smo-social'); ?></h4>
                    <p style="margin: 0 0 15px 0; color: var(--smo-text-secondary);">
                        <?php _e('Create a backup of all SMO Social data', 'smo-social'); ?></p>
                    <button type="button" class="smo-btn smo-btn-primary smo-create-backup">
                        <?php _e('Create Backup', 'smo-social'); ?>
                    </button>
                </div>

                <div class="smo-backup-tool" style="padding: 15px; border: 1px solid var(--smo-border); border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;"><?php _e('Restore from Backup', 'smo-social'); ?></h4>
                    <p style="margin: 0 0 15px 0; color: var(--smo-text-secondary);">
                        <?php _e('Restore data from a previous backup', 'smo-social'); ?></p>
                    <div class="smo-form-group">
                        <label for="smo-backup-file"><?php _e('Select Backup File', 'smo-social'); ?></label>
                        <input type="file" id="smo-backup-file" accept=".zip,.sql">
                    </div>
                    <button type="button" class="smo-btn smo-btn-secondary smo-restore-backup">
                        <?php _e('Restore', 'smo-social'); ?>
                    </button>
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
        // Run all tasks
        $('#smo-run-all-tasks').on('click', function () {
            const button = $(this);
            button.prop('disabled', true).text('<?php _e("Running All Tasks...", "smo-social"); ?>');

            // Trigger all run task buttons
            $('.smo-run-task').each(function() {
                $(this).trigger('click');
            });

            // Re-enable after a delay or when all are done
            setTimeout(function() {
                button.prop('disabled', false).text('<?php _e("Run All Tasks", "smo-social"); ?>');
            }, 1000);
        });

        // Run maintenance task
        $('.smo-run-task').on('click', function () {
            const taskId = $(this).data('task');
            const button = $(this);

            button.prop('disabled', true).text('<?php _e("Running...", "smo-social"); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smo_run_maintenance_task',
                    task_id: taskId,
                    nonce: '<?php echo wp_create_nonce("smo_maintenance_nonce"); ?>'
                },
                success: function (response) {
                    button.prop('disabled', false).text('<?php _e("Run Now", "smo-social"); ?>');

                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php _e("Task failed to run", "smo-social"); ?>');
                    }
                }
            });
        });

        // Database tools
        $('.smo-clean-logs').on('click', function () {
            if (confirm('<?php _e("Are you sure you want to clean old logs?", "smo-social"); ?>')) {
                runDbTask('clean_logs');
            }
        });

        $('.smo-optimize-tables').on('click', function () {
            runDbTask('optimize_tables');
        });

        $('.smo-clear-cache').on('click', function () {
            runDbTask('clear_cache');
        });

        // Backup tools
        $('.smo-create-backup').on('click', function () {
            runBackupTask('create_backup');
        });

        $('.smo-restore-backup').on('click', function () {
            const fileInput = $('#smo-backup-file')[0];
            if (!fileInput.files[0]) {
                alert('<?php _e("Please select a backup file", "smo-social"); ?>');
                return;
            }

            if (confirm('<?php _e("Are you sure you want to restore from this backup? This will overwrite existing data.", "smo-social"); ?>')) {
                // Handle file upload and restore
                alert('<?php _e("Backup restore functionality would be implemented here", "smo-social"); ?>');
            }
        });

        function runDbTask(task) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smo_run_db_task',
                    task: task,
                    nonce: '<?php echo wp_create_nonce("smo_maintenance_nonce"); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert('<?php _e("Task failed", "smo-social"); ?>');
                    }
                }
            });
        }

        function runBackupTask(task) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smo_run_backup_task',
                    task: task,
                    nonce: '<?php echo wp_create_nonce("smo_maintenance_nonce"); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        if (task === 'create_backup' && response.data.download_url) {
                            // Trigger download
                            const link = document.createElement('a');
                            link.href = response.data.download_url;
                            link.download = response.data.filename;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                        alert(response.data.message);
                    } else {
                        alert('<?php _e("Task failed", "smo-social"); ?>');
                    }
                }
            });
        }
    });
</script>