<?php
/**
 * SMO Social Memory Monitoring Dashboard View
 *
 * Real-time memory monitoring dashboard with charts, alerts, and configuration management
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

class MemoryMonitoring {
    private $plugin;
    private $memory_monitor;
    private $alert_system;
    private $config;

    public function __construct($plugin = null) {
        $this->plugin = $plugin;
        $this->memory_monitor = \SMO_Social\Core\MemoryMonitor::get_instance();
        $this->config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        // Initialize alert system if available
        if (class_exists('\SMO_Social\Core\MemoryAlertSystem')) {
            $this->alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();
        }
    }

    public function render() {
        $data = $this->get_dashboard_data();
        ob_start();

        // Use Common Layout
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('memory-monitoring', __('Memory Monitoring', 'smo-social'));

            // Render standardized gradient header
            \SMO_Social\Admin\Views\Common\AppLayout::render_header([
                'icon' => '💾',
                'title' => __('Memory Monitoring Dashboard', 'smo-social'),
                'subtitle' => __('Monitor system memory usage, performance, and alerts in real-time', 'smo-social'),
                'actions' => [
                    [
                        'id' => 'smo-refresh-memory',
                        'label' => __('Refresh', 'smo-social'),
                        'icon' => 'update',
                        'class' => 'smo-btn-secondary'
                    ],
                    [
                        'id' => 'smo-force-monitor',
                        'label' => __('Force Monitor', 'smo-social'),
                        'icon' => 'performance',
                        'class' => 'smo-btn-primary'
                    ]
                ]
            ]);

            // Render standardized stats dashboard
            \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
                [
                    'icon' => 'memory',
                    'value' => $data['current_stats']['total_usage_formatted'] ?? 'N/A',
                    'label' => __('Current Usage', 'smo-social'),
                    'trend' => $this->get_status_badge($data['current_stats']['status'] ?? 'normal')
                ],
                [
                    'icon' => 'chart-line',
                    'value' => number_format($data['current_stats']['usage_percentage'] ?? 0, 1) . '%',
                    'label' => __('Usage %', 'smo-social'),
                    'trend' => $this->get_usage_trend($data['current_stats']['usage_percentage'] ?? 0)
                ],
                [
                    'icon' => 'star-filled',
                    'value' => number_format($data['efficiency_analysis']['average_efficiency'] ?? 0, 1) . '%',
                    'label' => __('Efficiency Score', 'smo-social'),
                    'trend' => $this->get_efficiency_badge($data['efficiency_analysis']['average_efficiency'] ?? 0)
                ],
                [
                    'icon' => 'warning',
                    'value' => count($data['active_alerts'] ?? []),
                    'label' => __('Active Alerts', 'smo-social'),
                    'trend' => count($data['active_alerts'] ?? []) > 0 ? '⚠️ Active' : '✅ None'
                ]
            ]);
        }
        ?>

        <!-- Main Content Grid -->
        <div class="smo-grid">
            <!-- Current Status Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-dashboard"></span>
                        <?php _e('Current Memory Status', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <span class="smo-status-indicator status-<?php echo esc_attr($data['current_stats']['status'] ?? 'normal'); ?>">
                            <?php echo $this->get_status_icon($data['current_stats']['status'] ?? 'normal'); ?>
                        </span>
                        <span class="smo-last-update"><?php _e('Updated:', 'smo-social'); ?> <span id="last-update-time"><?php echo date('H:i:s'); ?></span></span>
                    </div>
                </div>
                <div class="smo-card-body">
                    <div class="smo-memory-overview">
                        <div class="smo-memory-gauge">
                            <div class="smo-gauge-container">
                                <svg class="smo-memory-gauge-svg" viewBox="0 0 120 120">
                                    <circle cx="60" cy="60" r="50" fill="none" stroke="#e1e5e9" stroke-width="8"/>
                                    <circle cx="60" cy="60" r="50" fill="none" stroke="#007cba" stroke-width="8"
                                            stroke-dasharray="<?php echo 2 * M_PI * 50; ?>"
                                            stroke-dashoffset="<?php echo 2 * M_PI * 50 * (1 - ($data['current_stats']['usage_percentage'] ?? 0) / 100); ?>"
                                            transform="rotate(-90 60 60)"/>
                                </svg>
                                <div class="smo-gauge-text">
                                    <div class="smo-gauge-value"><?php echo number_format($data['current_stats']['usage_percentage'] ?? 0, 1); ?>%</div>
                                    <div class="smo-gauge-label"><?php _e('Used', 'smo-social'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="smo-memory-details">
                            <div class="smo-memory-metric">
                                <span class="smo-metric-label"><?php _e('Current Usage:', 'smo-social'); ?></span>
                                <span class="smo-metric-value"><?php echo esc_html($data['current_stats']['total_usage_formatted'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="smo-memory-metric">
                                <span class="smo-metric-label"><?php _e('Peak Usage:', 'smo-social'); ?></span>
                                <span class="smo-metric-value"><?php echo esc_html($data['current_stats']['system']['peak_usage_formatted'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="smo-memory-metric">
                                <span class="smo-metric-label"><?php _e('Memory Limit:', 'smo-social'); ?></span>
                                <span class="smo-metric-value"><?php echo esc_html($data['current_stats']['system']['memory_limit_formatted'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="smo-memory-metric">
                                <span class="smo-metric-label"><?php _e('Efficiency Score:', 'smo-social'); ?></span>
                                <span class="smo-metric-value"><?php echo number_format($data['current_stats']['efficiency_score'] ?? 0, 1); ?>%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Memory Usage by Component -->
                    <div class="smo-component-breakdown">
                        <h4><?php _e('Memory Usage by Component', 'smo-social'); ?></h4>
                        <div class="smo-component-list">
                            <?php foreach ($data['component_usage'] as $component => $usage): ?>
                                <div class="smo-component-item">
                                    <div class="smo-component-name"><?php echo esc_html($usage['name']); ?></div>
                                    <div class="smo-component-bar">
                                        <div class="smo-component-fill" data-width="<?php echo min(100, $usage['percentage']); ?>%"></div>
                                    </div>
                                    <div class="smo-component-value"><?php echo esc_html($usage['usage_formatted']); ?> (<?php echo number_format($usage['percentage'], 1); ?>%)</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Active Alerts', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <span class="smo-card-count"><?php echo count($data['active_alerts'] ?? []); ?> <?php _e('alerts', 'smo-social'); ?></span>
                    </div>
                </div>
                <div class="smo-card-body">
                    <?php if (!empty($data['active_alerts'])): ?>
                        <div class="smo-alerts-list">
                            <?php foreach ($data['active_alerts'] as $alert): ?>
                                <div class="smo-alert-item alert-<?php echo esc_attr($alert['severity'] ?? 'warning'); ?>">
                                    <div class="smo-alert-icon">
                                        <?php echo $this->get_alert_icon($alert['severity'] ?? 'warning'); ?>
                                    </div>
                                    <div class="smo-alert-content">
                                        <h4><?php echo esc_html($alert['title'] ?? 'Alert'); ?></h4>
                                        <p><?php echo esc_html($alert['message'] ?? ''); ?></p>
                                        <small><?php echo esc_html($alert['timestamp'] ?? ''); ?></small>
                                    </div>
                                    <div class="smo-alert-actions">
                                        <button class="button button-small smo-dismiss-alert" data-alert-id="<?php echo esc_attr($alert['id'] ?? ''); ?>">
                                            <?php _e('Dismiss', 'smo-social'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="smo-empty-state">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <p><?php _e('No active alerts. Memory usage is within normal parameters.', 'smo-social'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Configuration Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Configuration', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <button class="button button-small" id="smo-configure-memory">
                            <?php _e('Configure', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                <div class="smo-card-body">
                    <div class="smo-config-overview">
                        <div class="smo-config-item">
                            <span class="smo-config-label"><?php _e('Monitoring:', 'smo-social'); ?></span>
                            <span class="smo-config-value"><?php echo $data['config']['monitoring_enabled'] ? __('Enabled', 'smo-social') : __('Disabled', 'smo-social'); ?></span>
                        </div>
                        <div class="smo-config-item">
                            <span class="smo-config-label"><?php _e('Real-time:', 'smo-social'); ?></span>
                            <span class="smo-config-value"><?php echo $data['config']['enable_real_time_monitoring'] ? __('Enabled', 'smo-social') : __('Disabled', 'smo-social'); ?></span>
                        </div>
                        <div class="smo-config-item">
                            <span class="smo-config-label"><?php _e('Interval:', 'smo-social'); ?></span>
                            <span class="smo-config-value"><?php echo $data['config']['monitoring_interval']; ?> <?php _e('seconds', 'smo-social'); ?></span>
                        </div>
                        <div class="smo-config-item">
                            <span class="smo-config-label"><?php _e('Warning Threshold:', 'smo-social'); ?></span>
                            <span class="smo-config-value"><?php echo $data['config']['warning_threshold']; ?>%</span>
                        </div>
                        <div class="smo-config-item">
                            <span class="smo-config-label"><?php _e('Critical Threshold:', 'smo-social'); ?></span>
                            <span class="smo-config-value"><?php echo $data['config']['critical_threshold']; ?>%</span>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="smo-config-actions">
                        <button class="button" id="smo-apply-preset-development">
                            <?php _e('Development Preset', 'smo-social'); ?>
                        </button>
                        <button class="button" id="smo-apply-preset-production">
                            <?php _e('Production Preset', 'smo-social'); ?>
                        </button>
                        <button class="button button-secondary" id="smo-reset-config">
                            <?php _e('Reset to Defaults', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historical Trends Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-chart-line"></span>
                        <?php _e('Historical Trends', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <select id="smo-chart-period">
                            <option value="1"><?php _e('Last Hour', 'smo-social'); ?></option>
                            <option value="6"><?php _e('Last 6 Hours', 'smo-social'); ?></option>
                            <option value="24" selected><?php _e('Last 24 Hours', 'smo-social'); ?></option>
                            <option value="168"><?php _e('Last 7 Days', 'smo-social'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="smo-card-body">
                    <div class="smo-chart-container">
                        <canvas id="memory-usage-chart" width="400" height="200"></canvas>
                    </div>
                    <div class="smo-chart-legend">
                        <div class="smo-legend-item">
                            <span class="smo-legend-color smo-bg-blue"></span>
                            <span><?php _e('Memory Usage %', 'smo-social'); ?></span>
                        </div>
                        <div class="smo-legend-item">
                            <span class="smo-legend-color smo-bg-green"></span>
                            <span><?php _e('Efficiency Score', 'smo-social'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Memory Leak Detection Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Memory Leak Detection', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <span class="smo-card-count"><?php echo count($data['memory_leaks'] ?? []); ?> <?php _e('leaks detected', 'smo-social'); ?></span>
                    </div>
                </div>
                <div class="smo-card-body">
                    <?php if (!empty($data['memory_leaks'])): ?>
                        <div class="smo-leaks-list">
                            <?php foreach ($data['memory_leaks'] as $leak): ?>
                                <div class="smo-leak-item leak-<?php echo esc_attr($leak['severity_level'] ?? 'low'); ?>">
                                    <div class="smo-leak-header">
                                        <h4><?php echo esc_html($leak['leak_type'] ?? 'Unknown Leak'); ?></h4>
                                        <span class="smo-leak-severity"><?php echo esc_html($leak['severity_level'] ?? 'low'); ?></span>
                                    </div>
                                    <div class="smo-leak-details">
                                        <p><strong><?php _e('Growth Rate:', 'smo-social'); ?></strong> <?php echo number_format($leak['memory_growth_rate'] ?? 0, 4); ?>% per interval</p>
                                        <p><strong><?php _e('Duration:', 'smo-social'); ?></strong> <?php echo intval(($leak['leak_duration_hours'] ?? 0) / 24); ?> days</p>
                                        <p><strong><?php _e('Confidence:', 'smo-social'); ?></strong> <?php echo number_format(($leak['confidence_score'] ?? 0) * 100, 1); ?>%</p>
                                        <?php if (!empty($leak['affected_components'])): ?>
                                            <p><strong><?php _e('Affected Components:', 'smo-social'); ?></strong> <?php echo esc_html(implode(', ', $leak['affected_components'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="smo-empty-state">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <p><?php _e('No memory leaks detected. System memory usage appears stable.', 'smo-social'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Usage Patterns Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php _e('Usage Patterns', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <select id="smo-pattern-type">
                            <option value="hourly"><?php _e('Hourly', 'smo-social'); ?></option>
                            <option value="daily"><?php _e('Daily', 'smo-social'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="smo-card-body">
                    <div class="smo-patterns-grid">
                        <?php foreach ($data['usage_patterns'] as $pattern): ?>
                            <div class="smo-pattern-card">
                                <h4><?php echo esc_html($pattern['pattern_name'] ?? 'Unknown Pattern'); ?></h4>
                                <div class="smo-pattern-metrics">
                                    <div class="smo-pattern-metric">
                                        <span class="smo-metric-label"><?php _e('Avg Usage:', 'smo-social'); ?></span>
                                        <span class="smo-metric-value"><?php echo number_format($pattern['pattern_data']['total_usage'] / $pattern['pattern_data']['count'], 1); ?>%</span>
                                    </div>
                                    <div class="smo-pattern-metric">
                                        <span class="smo-metric-label"><?php _e('Peak Usage:', 'smo-social'); ?></span>
                                        <span class="smo-metric-value"><?php echo number_format($pattern['pattern_data']['peak_usage'], 1); ?>%</span>
                                    </div>
                                    <div class="smo-pattern-metric">
                                        <span class="smo-metric-label"><?php _e('Confidence:', 'smo-social'); ?></span>
                                        <span class="smo-metric-value"><?php echo number_format($pattern['confidence_score'] * 100, 1); ?>%</span>
                                    </div>
                                    <div class="smo-pattern-metric">
                                        <span class="smo-metric-label"><?php _e('Predictive Power:', 'smo-social'); ?></span>
                                        <span class="smo-metric-value"><?php echo number_format($pattern['predictive_power'] * 100, 1); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Predictive Analytics Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php _e('Predictive Analytics', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <button class="button button-small" id="smo-generate-forecast">
                            <?php _e('Generate Forecast', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                <div class="smo-card-body">
                    <div id="forecast-results">
                        <?php if (!empty($data['forecast'])): ?>
                            <div class="smo-forecast-summary">
                                <div class="smo-forecast-metric">
                                    <span class="smo-metric-label"><?php _e('Risk Assessment:', 'smo-social'); ?></span>
                                    <span class="smo-metric-value risk-<?php echo esc_attr($data['forecast']['risk_assessment']); ?>">
                                        <?php echo esc_html(ucfirst($data['forecast']['risk_assessment'])); ?>
                                    </span>
                                </div>
                                <div class="smo-forecast-metric">
                                    <span class="smo-metric-label"><?php _e('Peak Predicted Usage:', 'smo-social'); ?></span>
                                    <span class="smo-metric-value">
                                        <?php
                                        $max_predicted = 0;
                                        foreach ($data['forecast']['predictions'] as $pred) {
                                            $max_predicted = max($max_predicted, $pred['predicted_usage_percentage']);
                                        }
                                        echo number_format($max_predicted, 1) . '%';
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="smo-forecast-chart">
                                <canvas id="forecast-chart" width="400" height="150"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="smo-empty-state">
                                <span class="dashicons dashicons-chart-area"></span>
                                <p><?php _e('Click "Generate Forecast" to analyze future memory usage patterns.', 'smo-social'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Optimization Recommendations Section -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php _e('Optimization Recommendations', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <button class="button button-small" id="smo-generate-recommendations">
                            <?php _e('Refresh Recommendations', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                <div class="smo-card-body">
                    <?php if (!empty($data['recommendations'])): ?>
                        <div class="smo-recommendations-list">
                            <?php foreach ($data['recommendations'] as $rec): ?>
                                <div class="smo-recommendation-item priority-<?php echo esc_attr($rec['priority'] ?? 'medium'); ?>">
                                    <div class="smo-recommendation-header">
                                        <h4><?php echo esc_html($rec['title'] ?? 'Recommendation'); ?></h4>
                                        <span class="smo-recommendation-priority"><?php echo esc_html($rec['priority'] ?? 'medium'); ?></span>
                                    </div>
                                    <p><?php echo esc_html($rec['description'] ?? ''); ?></p>
                                    <div class="smo-recommendation-meta">
                                        <span class="smo-meta-item">
                                            <strong><?php _e('Complexity:', 'smo-social'); ?></strong> <?php echo esc_html($rec['implementation_complexity'] ?? 'medium'); ?>
                                        </span>
                                        <span class="smo-meta-item">
                                            <strong><?php _e('Risk:', 'smo-social'); ?></strong> <?php echo esc_html($rec['risk_level'] ?? 'medium'); ?>
                                        </span>
                                        <?php if (isset($rec['expected_benefit_percentage']) && $rec['expected_benefit_percentage'] > 0): ?>
                                            <span class="smo-meta-item">
                                                <strong><?php _e('Expected Benefit:', 'smo-social'); ?></strong> <?php echo number_format($rec['expected_benefit_percentage'], 1); ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="smo-empty-state">
                            <span class="dashicons dashicons-lightbulb"></span>
                            <p><?php _e('No optimization recommendations available. Memory usage appears optimal.', 'smo-social'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Configuration Modal -->
        <div id="smo-memory-config-modal" class="smo-modal">
            <div class="smo-modal-content">
                <div class="smo-modal-header">
                    <h3><?php _e('Memory Monitoring Configuration', 'smo-social'); ?></h3>
                    <span class="smo-modal-close">&times;</span>
                </div>
                <div class="smo-modal-body">
                    <form id="smo-memory-config-form">
                        <?php
                        $sections = $this->config->get_admin_settings_sections();
                        foreach ($sections as $section_key => $section):
                        ?>
                            <div class="smo-config-section">
                                <h4><?php echo esc_html($section['title']); ?></h4>
                                <p><?php echo esc_html($section['description']); ?></p>

                                <?php foreach ($section['fields'] as $field_key => $field): ?>
                                    <div class="smo-config-field">
                                        <label for="<?php echo esc_attr($field_key); ?>">
                                            <?php echo esc_html($field['label']); ?>
                                        </label>

                                        <?php if ($field['type'] === 'checkbox'): ?>
                                            <input type="checkbox"
                                                   id="<?php echo esc_attr($field_key); ?>"
                                                   name="<?php echo esc_attr($field_key); ?>"
                                                   value="1"
                                                   <?php checked($data['config'][$field_key] ?? false); ?> />
                                            <span class="description"><?php echo esc_html($field['description']); ?></span>

                                        <?php elseif ($field['type'] === 'number'): ?>
                                            <input type="number"
                                                   id="<?php echo esc_attr($field_key); ?>"
                                                   name="<?php echo esc_attr($field_key); ?>"
                                                   value="<?php echo esc_attr($data['config'][$field_key] ?? $field['default']); ?>"
                                                   min="<?php echo esc_attr($field['min'] ?? ''); ?>"
                                                   max="<?php echo esc_attr($field['max'] ?? ''); ?>" />
                                            <span class="description"><?php echo esc_html($field['description']); ?></span>

                                        <?php elseif ($field['type'] === 'select'): ?>
                                            <select id="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>">
                                                <?php foreach ($field['options'] as $option_value => $option_label): ?>
                                                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($data['config'][$field_key] ?? $field['default'], $option_value); ?>>
                                                        <?php echo esc_html($option_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="description"><?php echo esc_html($field['description']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="smo-modal-actions">
                            <button type="submit" class="button button-primary"><?php _e('Save Configuration', 'smo-social'); ?></button>
                            <button type="button" class="button button-secondary" id="smo-cancel-config"><?php _e('Cancel', 'smo-social'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        jQuery(document).ready(function($) {
            let memoryChart;
            let updateInterval;

            // Initialize chart
            function initializeChart() {
                const ctx = document.getElementById('memory-usage-chart').getContext('2d');
                const chartData = <?php echo json_encode($this->get_chart_data()); ?>;

                memoryChart = new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Percentage (%)'
                                }
                            },
                            efficiency: {
                                beginAtZero: true,
                                max: 100,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Efficiency Score'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Time'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        }
                    }
                });
            }

            // Update memory data
            function updateMemoryData() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_get_memory_stats',
                        nonce: '<?php echo wp_create_nonce("smo_memory_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            updateDashboardDisplay(response.data);
                            $('#last-update-time').text(new Date().toLocaleTimeString());
                        }
                    }
                });
            }

            // Update dashboard display with new data
            function updateDashboardDisplay(data) {
                // Update gauge
                const percentage = data.usage_percentage || 0;
                $('.smo-gauge-value').text(percentage.toFixed(1) + '%');

                // Update SVG gauge
                const circumference = 2 * Math.PI * 50;
                const offset = circumference * (1 - percentage / 100);
                $('.smo-memory-gauge-svg circle:last-child').attr('stroke-dashoffset', offset);

                // Update component fills with dynamic width
                $('.smo-component-fill').each(function() {
                    const width = $(this).data('width');
                    if (width) {
                        $(this).css('width', width);
                    }
                });

                // Update metrics
                $('.smo-memory-metric').each(function() {
                    const label = $(this).find('.smo-metric-label').text();
                    if (label.includes('Current Usage:')) {
                        $(this).find('.smo-metric-value').text(data.total_usage_formatted || 'N/A');
                    } else if (label.includes('Peak Usage:')) {
                        $(this).find('.smo-metric-value').text(data.system.peak_usage_formatted || 'N/A');
                    } else if (label.includes('Efficiency Score:')) {
                        $(this).find('.smo-metric-value').text((data.efficiency_score || 0).toFixed(1) + '%');
                    }
                });

                // Update status indicator
                const status = data.status || 'normal';
                $('.smo-status-indicator').removeClass('status-normal status-warning status-critical')
                    .addClass('status-' + status)
                    .html(getStatusIcon(status));
            }

            // Get status icon
            function getStatusIcon(status) {
                const icons = {
                    'normal': '✅',
                    'warning': '⚠️',
                    'critical': '❌'
                };
                return icons[status] || '❓';
            }

            // Update chart data
            function updateChartData() {
                const hours = $('#smo-chart-period').val();
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_get_memory_history',
                        hours: hours,
                        nonce: '<?php echo wp_create_nonce("smo_memory_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success && memoryChart) {
                            const history = response.data;
                            const labels = [];
                            const usageData = [];
                            const efficiencyData = [];

                            history.forEach(function(entry) {
                                labels.push(new Date(entry.timestamp).toLocaleTimeString());
                                usageData.push(entry.usage_percentage);
                                efficiencyData.push(entry.efficiency_score);
                            });

                            memoryChart.data.labels = labels;
                            memoryChart.data.datasets[0].data = usageData;
                            memoryChart.data.datasets[1].data = efficiencyData;
                            memoryChart.update();
                        }
                    }
                });
            }

            // Event handlers
            $('#smo-refresh-memory').on('click', function() {
                updateMemoryData();
                updateChartData();
            });

            $('#smo-force-monitor').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_force_memory_monitoring',
                        nonce: '<?php echo wp_create_nonce("smo_memory_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            updateMemoryData();
                            updateChartData();
                        }
                    }
                });
            });

            $('#smo-configure-memory').on('click', function() {
                $('#smo-memory-config-modal').show();
            });

            $('.smo-modal-close, #smo-cancel-config').on('click', function() {
                $('#smo-memory-config-modal').hide();
            });

            $('#smo-memory-config-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });

            $('#smo-memory-config-form').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'smo_update_memory_config');
                formData.append('nonce', '<?php echo wp_create_nonce("smo_memory_config_nonce"); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#smo-memory-config-modal').hide();
                            location.reload(); // Reload to show updated config
                        } else {
                            alert('Error updating configuration: ' + (response.data || 'Unknown error'));
                        }
                    }
                });
            });

            $('#smo-apply-preset-development').on('click', function() {
                applyPreset('development');
            });

            $('#smo-apply-preset-production').on('click', function() {
                applyPreset('production');
            });

            $('#smo-reset-config').on('click', function() {
                if (confirm('<?php _e("Are you sure you want to reset configuration to defaults?", "smo-social"); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'smo_reset_memory_config',
                            nonce: '<?php echo wp_create_nonce("smo_memory_config_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        }
                    });
                }
            });

            $('#smo-chart-period').on('change', function() {
                updateChartData();
            });

            $('.smo-dismiss-alert').on('click', function() {
                const alertId = $(this).data('alert-id');
                const $item = $(this).closest('.smo-alert-item');
                if (window.smoPrefersReducedMotion && window.smoPrefersReducedMotion()) {
                    $item.remove();
                } else {
                    $item.fadeOut();
                }
            });

            // Apply configuration preset
            function applyPreset(preset) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_apply_memory_preset',
                        preset: preset,
                        nonce: '<?php echo wp_create_nonce("smo_memory_config_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error applying preset: ' + (response.data || 'Unknown error'));
                        }
                    }
                });
            }

            // Initialize
            initializeChart();

            // Auto-update every 30 seconds
            updateInterval = setInterval(function() {
                updateMemoryData();
            }, 30000);

            // Cleanup on page unload
            $(window).on('beforeunload', function() {
                if (updateInterval) {
                    clearInterval(updateInterval);
                }
            });
        });
        </script>

        <?php
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        }

        return ob_get_clean();
    }

    public function get_dashboard_data() {
        $data = [
            'current_stats' => [],
            'component_usage' => [],
            'active_alerts' => [],
            'efficiency_analysis' => [],
            'config' => [],
            'chart_data' => [],
            'memory_leaks' => [],
            'usage_patterns' => [],
            'forecast' => [],
            'recommendations' => []
        ];

        try {
            // Get current memory stats
            $data['current_stats'] = $this->memory_monitor->get_current_stats();

            // Get component usage
            $data['component_usage'] = $this->memory_monitor->get_memory_usage_by_component();

            // Get efficiency analysis
            $data['efficiency_analysis'] = $this->memory_monitor->get_memory_efficiency_analysis();

            // Get active alerts
            if ($this->alert_system && method_exists($this->alert_system, 'get_active_alerts')) {
                $data['active_alerts'] = $this->alert_system->get_active_alerts();
            }

            // Get configuration
            $data['config'] = $this->config->get_config();

            // Get chart data
            $data['chart_data'] = $this->get_chart_data();

            // Get memory leak patterns
            $data['memory_leaks'] = $this->memory_monitor->get_memory_leak_patterns(24);

            // Get usage patterns
            $data['usage_patterns'] = $this->memory_monitor->get_usage_patterns('all');

            // Get optimization recommendations
            $data['recommendations'] = $this->memory_monitor->generate_optimization_recommendations();

        } catch (\Exception $e) {
            // Log error and return empty data
            if (class_exists('\SMO_Social\Core\Logger')) {
                \SMO_Social\Core\Logger::error('Failed to get memory monitoring dashboard data: ' . $e->getMessage());
            }
        }

        return $data;
    }

    private function get_status_badge($status) {
        $badges = [
            'normal' => '🟢 Normal',
            'warning' => '🟡 Warning',
            'critical' => '🔴 Critical'
        ];
        return $badges[$status] ?? '⚪ Unknown';
    }

    private function get_usage_trend($percentage) {
        if ($percentage >= 90) return '🔴 High';
        if ($percentage >= 70) return '🟡 Moderate';
        return '🟢 Low';
    }

    private function get_efficiency_badge($score) {
        if ($score >= 80) return '🟢 Excellent';
        if ($score >= 60) return '🟡 Good';
        return '🔴 Needs Attention';
    }

    private function get_status_icon($status) {
        $icons = [
            'normal' => '✅',
            'warning' => '⚠️',
            'critical' => '❌'
        ];
        return $icons[$status] ?? '❓';
    }

    private function get_alert_icon($severity) {
        $icons = [
            'critical' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️'
        ];
        return $icons[$severity] ?? '⚠️';
    }

    private function get_chart_data() {
        $history = $this->memory_monitor->get_database_memory_history(50);

        $chart_data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => __('Memory Usage %', 'smo-social'),
                    'data' => [],
                    'borderColor' => '#007cba',
                    'backgroundColor' => 'rgba(0, 124, 186, 0.1)',
                    'fill' => false,
                    'tension' => 0.1
                ],
                [
                    'label' => __('Efficiency Score', 'smo-social'),
                    'data' => [],
                    'borderColor' => '#28a745',
                    'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                    'fill' => false,
                    'tension' => 0.1,
                    'yAxisID' => 'efficiency'
                ]
            ]
        ];

        foreach ($history as $entry) {
            $chart_data['labels'][] = date('H:i', strtotime($entry['timestamp']));
            $chart_data['datasets'][0]['data'][] = $entry['usage_percentage'];
            $chart_data['datasets'][1]['data'][] = $entry['efficiency_score'];
        }

        return $chart_data;
    }
}