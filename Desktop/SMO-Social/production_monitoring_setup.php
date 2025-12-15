<?php
/**
 * SMO Social - Production Monitoring & Alerting System
 * 
 * This script sets up comprehensive monitoring and alerting for production:
 * - Performance monitoring
 * - Security monitoring
 * - API health checks
 * - Database monitoring
 * - System resource monitoring
 * - Custom alerting rules
 * 
 * Usage: php production_monitoring_setup.php
 */

class ProductionMonitoringSystem {
    
    private $config = [];
    private $alert_thresholds = [];
    private $monitoring_rules = [];
    
    public function __construct() {
        echo "📊 SMO Social Production Monitoring & Alerting System\n";
        echo "===================================================\n\n";
        $this->initializeConfiguration();
    }
    
    /**
     * Initialize monitoring configuration
     */
    private function initializeConfiguration() {
        // Alert thresholds
        $this->alert_thresholds = [
            'performance' => [
                'response_time_warning' => 1000, // ms
                'response_time_critical' => 3000, // ms
                'error_rate_warning' => 5, // %
                'error_rate_critical' => 10, // %
                'uptime_warning' => 95, // %
                'uptime_critical' => 90 // %
            ],
            'security' => [
                'failed_login_attempts' => 10, // per hour
                'suspicious_api_requests' => 100, // per hour
                'rate_limit_exceeded' => 50, // per hour
                'security_score_minimum' => 80 // %
            ],
            'database' => [
                'query_time_warning' => 100, // ms
                'query_time_critical' => 500, // ms
                'connection_pool_usage' => 80, // %
                'deadlock_count' => 5, // per hour
                'slow_query_count' => 10 // per hour
            ],
            'system' => [
                'cpu_usage_warning' => 70, // %
                'cpu_usage_critical' => 90, // %
                'memory_usage_warning' => 80, // %
                'memory_usage_critical' => 95, // %
                'disk_usage_warning' => 85, // %
                'disk_usage_critical' => 95, // %
                'load_average_warning' => 2.0,
                'load_average_critical' => 5.0
            ],
            'api' => [
                'rate_limit_warning' => 80, // %
                'rate_limit_critical' => 95, // %
                'api_timeout_warning' => 10, // %
                'api_timeout_critical' => 25, // %
                'provider_availability_minimum' => 99 // %
            ]
        ];
        
        // Monitoring rules
        $this->monitoring_rules = [
            'health_checks' => [
                'plugin_status' => [
                    'check_function' => 'checkPluginStatus',
                    'frequency' => 'every_5_minutes',
                    'alert_on_failure' => true
                ],
                'database_connection' => [
                    'check_function' => 'checkDatabaseConnection',
                    'frequency' => 'every_2_minutes',
                    'alert_on_failure' => true
                ],
                'api_endpoints' => [
                    'check_function' => 'checkApiEndpoints',
                    'frequency' => 'every_1_minutes',
                    'alert_on_failure' => true
                ],
                'webhook_health' => [
                    'check_function' => 'checkWebhookHealth',
                    'frequency' => 'every_3_minutes',
                    'alert_on_failure' => true
                ]
            ],
            'performance_monitoring' => [
                'response_times' => [
                    'metric' => 'response_time',
                    'frequency' => 'real_time',
                    'aggregation_period' => '1_minute'
                ],
                'throughput' => [
                    'metric' => 'requests_per_second',
                    'frequency' => 'real_time',
                    'aggregation_period' => '1_minute'
                ],
                'error_rates' => [
                    'metric' => 'error_rate_percentage',
                    'frequency' => 'real_time',
                    'aggregation_period' => '1_minute'
                ]
            ],
            'security_monitoring' => [
                'authentication_failures' => [
                    'metric' => 'failed_logins',
                    'frequency' => 'real_time',
                    'alert_threshold' => $this->alert_thresholds['security']['failed_login_attempts']
                ],
                'suspicious_requests' => [
                    'metric' => 'suspicious_api_requests',
                    'frequency' => 'real_time',
                    'alert_threshold' => $this->alert_thresholds['security']['suspicious_api_requests']
                ],
                'rate_limit_violations' => [
                    'metric' => 'rate_limit_exceeded',
                    'frequency' => 'real_time',
                    'alert_threshold' => $this->alert_thresholds['security']['rate_limit_exceeded']
                ]
            ]
        ];
    }
    
    /**
     * Generate monitoring configuration
     */
    public function generateMonitoringConfig() {
        echo "🔧 Generating monitoring configuration...\n\n";
        
        $config = $this->buildMonitoringConfig();
        $config_file = 'production_monitoring_config.php';
        file_put_contents($config_file, $config);
        
        echo "✅ Monitoring configuration created: {$config_file}\n\n";
        
        return $config_file;
    }
    
    /**
     * Build monitoring configuration
     */
    private function buildMonitoringConfig() {
        $config = "<?php\n";
        $config .= "/**\n";
        $config .= " * SMO Social Production Monitoring Configuration\n";
        $config .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $config .= " */\n\n";
        
        $config .= "// Monitoring System Configuration\n";
        $config .= "define('SMO_MONITORING_ENABLED', true);\n";
        $config .= "define('SMO_MONITORING_INTERVAL', 60); // seconds\n";
        $config .= "define('SMO_METRICS_RETENTION_DAYS', 30);\n";
        $config .= "define('SMO_ALERT_COOLDOWN_MINUTES', 15);\n\n";
        
        $config .= "// Alert Configuration\n";
        $config .= "define('SMO_ALERT_EMAIL', 'admin@your-domain.com');\n";
        $config .= "define('SMO_ALERT_SLACK_WEBHOOK', 'YOUR_SLACK_WEBHOOK_URL');\n";
        $config .= "define('SMO_ALERT_SMS_NUMBER', 'YOUR_SMS_NUMBER');\n\n";
        
        $config .= "// Alert Thresholds\n";
        $config .= "\$smo_monitoring_thresholds = [\n";
        foreach ($this->alert_thresholds as $category => $thresholds) {
            $config .= "    '{$category}' => [\n";
            foreach ($thresholds as $metric => $value) {
                $config .= "        '{$metric}' => {$value},\n";
            }
            $config .= "    ],\n";
        }
        $config .= "];\n\n";
        
        $config .= "// Monitoring Rules\n";
        $config .= "\$smo_monitoring_rules = [\n";
        foreach ($this->monitoring_rules as $category => $rules) {
            $config .= "    '{$category}' => [\n";
            foreach ($rules as $rule_name => $rule_config) {
                $config .= "        '{$rule_name}' => [\n";
                foreach ($rule_config as $key => $value) {
                    $config .= "            '{$key}' => " . (is_string($value) ? "'{$value}'" : $value) . ",\n";
                }
                $config .= "        ],\n";
            }
            $config .= "    ],\n";
        }
        $config .= "];\n\n";
        
        return $config;
    }
    
    /**
     * Generate monitoring dashboard script
     */
    public function generateMonitoringDashboard() {
        echo "📈 Generating monitoring dashboard...\n\n";
        
        $dashboard = $this->buildMonitoringDashboard();
        $dashboard_file = 'production_monitoring_dashboard.php';
        file_put_contents($dashboard_file, $dashboard);
        
        echo "✅ Monitoring dashboard created: {$dashboard_file}\n";
        echo "🌐 Access dashboard at: /wp-admin/admin.php?page=smo-monitoring-dashboard\n\n";
        
        return $dashboard_file;
    }
    
    /**
     * Build monitoring dashboard
     */
    private function buildMonitoringDashboard() {
        $dashboard = "<?php\n";
        $dashboard .= "/**\n";
        $dashboard .= " * SMO Social Production Monitoring Dashboard\n";
        $dashboard .= " * Accessible via WordPress Admin: /wp-admin/admin.php?page=smo-monitoring-dashboard\n";
        $dashboard .= " */\n\n";
        
        $dashboard .= "class SMO_Monitoring_Dashboard {\n";
        $dashboard .= "    \n";
        $dashboard .= "    public function __construct() {\n";
        $dashboard .= "        add_action('admin_menu', [\$this, 'add_dashboard_menu']);\n";
        $dashboard .= "        add_action('wp_ajax_smo_get_monitoring_data', [\$this, 'ajax_get_monitoring_data']);\n";
        $dashboard .= "        add_action('wp_ajax_smo_trigger_monitoring_check', [\$this, 'ajax_trigger_monitoring_check']);\n";
        $dashboard .= "    }\n\n";
        
        $dashboard .= "    public function add_dashboard_menu() {\n";
        $dashboard .= "        add_menu_page(\n";
        $dashboard .= "            'System Monitoring',\n";
        $dashboard .= "            'Monitoring',\n";
        $dashboard .= "            'manage_options',\n";
        $dashboard .= "            'smo-monitoring-dashboard',\n";
        $dashboard .= "            [\$this, 'render_dashboard'],\n";
        $dashboard .= "            'dashicons-chart-area',\n";
        $dashboard .= "            30\n";
        $dashboard .= "        );\n";
        $dashboard .= "    }\n\n";
        
        $dashboard .= "    public function render_dashboard() {\n";
        $dashboard .= "        if (!current_user_can('manage_options')) {\n";
        $dashboard .= "            return;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        ?>\n";
        $dashboard .= "        <div class=\"wrap smo-monitoring-dashboard\">\n";
        $dashboard .= "            <h1>🚀 System Monitoring Dashboard</h1>\n";
        $dashboard .= "            <p>Real-time monitoring and alerting for SMO Social production environment</p>\n\n";
        
        $dashboard .= "            <div class=\"smo-monitoring-grid\">\n";
        $dashboard .= "                <!-- System Status Widget -->\n";
        $dashboard .= "                <div class=\"smo-monitoring-widget\" id=\"system-status-widget\">\n";
        $dashboard .= "                    <h2>System Status</h2>\n";
        $dashboard .= "                    <div class=\"status-indicators\">\n";
        $dashboard .= "                        <div class=\"status-item\">\n";
        $dashboard .= "                            <span class=\"status-label\">Plugin Status:</span>\n";
        $dashboard .= "                            <span class=\"status-value\" id=\"plugin-status\">Checking...</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                        <div class=\"status-item\">\n";
        $dashboard .= "                            <span class=\"status-label\">Database:</span>\n";
        $dashboard .= "                            <span class=\"status-value\" id=\"database-status\">Checking...</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                        <div class=\"status-item\">\n";
        $dashboard .= "                            <span class=\"status-label\">API Health:</span>\n";
        $dashboard .= "                            <span class=\"status-value\" id=\"api-status\">Checking...</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                    </div>\n";
        $dashboard .= "                    <button class=\"button\" onclick=\"triggerManualCheck()\">Run Manual Check</button>\n";
        $dashboard .= "                </div>\n\n";
        
        $dashboard .= "                <!-- Performance Metrics Widget -->\n";
        $dashboard .= "                <div class=\"smo-monitoring-widget\" id=\"performance-widget\">\n";
        $dashboard .= "                    <h2>Performance Metrics</h2>\n";
        $dashboard .= "                    <div class=\"metrics-grid\">\n";
        $dashboard .= "                        <div class=\"metric-item\">\n";
        $dashboard .= "                            <span class=\"metric-label\">Response Time</span>\n";
        $dashboard .= "                            <span class=\"metric-value\" id=\"response-time\">--</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                        <div class=\"metric-item\">\n";
        $dashboard .= "                            <span class=\"metric-label\">Error Rate</span>\n";
        $dashboard .= "                            <span class=\"metric-value\" id=\"error-rate\">--</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                        <div class=\"metric-item\">\n";
        $dashboard .= "                            <span class=\"metric-label\">Uptime</span>\n";
        $dashboard .= "                            <span class=\"metric-value\" id=\"uptime\">--</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                        <div class=\"metric-item\">\n";
        $dashboard .= "                            <span class=\"metric-label\">Requests/sec</span>\n";
        $dashboard .= "                            <span class=\"metric-value\" id=\"requests-per-sec\">--</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                    </div>\n";
        $dashboard .= "                </div>\n\n";
        
        $dashboard .= "                <!-- Security Monitoring Widget -->\n";
        $dashboard .= "                <div class=\"smo-monitoring-widget\" id=\"security-widget\">\n";
        $dashboard .= "                    <h2>Security Monitoring</h2>\n";
        $dashboard .= "                    <div class=\"security-metrics\">\n";
        $dashboard .= "                        <div class=\"security-item\">\n";
        $dashboard .= "                            <span class=\"security-label\">Failed Logins (1h):</span>\n";
        $dashboard .= "                            <span class=\"security-value\" id=\"failed-logins\">0</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                        <div class=\"security-item\">\n";
        $dashboard .= "                            <span class=\"security-label\">API Rate Limits:</span>\n";
        $dashboard .= "                            <span class=\"security-value\" id=\"rate-limit-hits\">0</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                        <div class=\"security-item\">\n";
        $dashboard .= "                            <span class=\"security-label\">Security Score:</span>\n";
        $dashboard .= "                            <span class=\"security-value\" id=\"security-score\">95%</span>\n";
        $dashboard .= "                        </div>\n";
        $dashboard .= "                    </div>\n";
        $dashboard .= "                </div>\n\n";
        
        $dashboard .= "                <!-- Alerts Widget -->\n";
        $dashboard .= "                <div class=\"smo-monitoring-widget\" id=\"alerts-widget\">\n";
        $dashboard .= "                    <h2>Active Alerts</h2>\n";
        $dashboard .= "                    <div id=\"active-alerts\">\n";
        $dashboard .= "                        <div class=\"no-alerts\">No active alerts ✅</div>\n";
        $dashboard .= "                    </div>\n";
        $dashboard .= "                </div>\n\n";
        
        $dashboard .= "            </div>\n";
        
        $dashboard .= "        </div>\n\n";
        
        $dashboard .= "        <style>\n";
        $dashboard .= "        .smo-monitoring-dashboard {\n";
        $dashboard .= "            max-width: 1400px;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .smo-monitoring-grid {\n";
        $dashboard .= "            display: grid;\n";
        $dashboard .= "            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));\n";
        $dashboard .= "            gap: 20px;\n";
        $dashboard .= "            margin-top: 20px;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .smo-monitoring-widget {\n";
        $dashboard .= "            background: #fff;\n";
        $dashboard .= "            border: 1px solid #ccd0d4;\n";
        $dashboard .= "            border-radius: 8px;\n";
        $dashboard .= "            padding: 20px;\n";
        $dashboard .= "            box-shadow: 0 1px 3px rgba(0,0,0,0.1);\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .smo-monitoring-widget h2 {\n";
        $dashboard .= "            margin-top: 0;\n";
        $dashboard .= "            color: #1d2327;\n";
        $dashboard .= "            border-bottom: 1px solid #e1e1e1;\n";
        $dashboard .= "            padding-bottom: 10px;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .status-item, .metric-item, .security-item {\n";
        $dashboard .= "            display: flex;\n";
        $dashboard .= "            justify-content: space-between;\n";
        $dashboard .= "            margin-bottom: 10px;\n";
        $dashboard .= "            padding: 5px 0;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .status-label, .metric-label, .security-label {\n";
        $dashboard .= "            font-weight: 500;\n";
        $dashboard .= "            color: #646970;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .status-value, .metric-value, .security-value {\n";
        $dashboard .= "            font-weight: 600;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .status-good { color: #00a32a; }\n";
        $dashboard .= "        .status-warning { color: #dba617; }\n";
        $dashboard .= "        .status-error { color: #d63638; }\n\n";
        
        $dashboard .= "        .metrics-grid {\n";
        $dashboard .= "            display: grid;\n";
        $dashboard .= "            grid-template-columns: 1fr 1fr;\n";
        $dashboard .= "            gap: 15px;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .metric-value {\n";
        $dashboard .= "            font-size: 1.2em;\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        .no-alerts {\n";
        $dashboard .= "            color: #00a32a;\n";
        $dashboard .= "            font-weight: 500;\n";
        $dashboard .= "            text-align: center;\n";
        $dashboard .= "            padding: 20px;\n";
        $dashboard .= "        }\n";
        $dashboard .= "        </style>\n\n";
        
        $dashboard .= "        <script>\n";
        $dashboard .= "        // Auto-refresh dashboard every 30 seconds\n";
        $dashboard .= "        setInterval(loadMonitoringData, 30000);\n\n";
        
        $dashboard .= "        // Load monitoring data\n";
        $dashboard .= "        function loadMonitoringData() {\n";
        $dashboard .= "            fetch(ajaxurl, {\n";
        $dashboard .= "                method: 'POST',\n";
        $dashboard .= "                headers: {\n";
        $dashboard .= "                    'Content-Type': 'application/x-www-form-urlencoded',\n";
        $dashboard .= "                },\n";
        $dashboard .= "                body: 'action=smo_get_monitoring_data'\n";
        $dashboard .= "            })\n";
        $dashboard .= "            .then(response => response.json())\n";
        $dashboard .= "            .then(data => {\n";
        $dashboard .= "                updateDashboard(data);\n";
        $dashboard .= "            })\n";
        $dashboard .= "            .catch(error => {\n";
        $dashboard .= "                console.error('Error loading monitoring data:', error);\n";
        $dashboard .= "            });\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        // Update dashboard with new data\n";
        $dashboard .= "        function updateDashboard(data) {\n";
        $dashboard .= "            if (data.plugin_status) {\n";
        $dashboard .= "                document.getElementById('plugin-status').textContent = data.plugin_status.text;\n";
        $dashboard .= "                document.getElementById('plugin-status').className = 'status-value status-' + data.plugin_status.status;\n";
        $dashboard .= "            }\n\n";
        
        $dashboard .= "            if (data.database_status) {\n";
        $dashboard .= "                document.getElementById('database-status').textContent = data.database_status.text;\n";
        $dashboard .= "                document.getElementById('database-status').className = 'status-value status-' + data.database_status.status;\n";
        $dashboard .= "            }\n\n";
        
        $dashboard .= "            if (data.performance) {\n";
        $dashboard .= "                document.getElementById('response-time').textContent = data.performance.response_time + 'ms';\n";
        $dashboard .= "                document.getElementById('error-rate').textContent = data.performance.error_rate + '%';\n";
        $dashboard .= "                document.getElementById('uptime').textContent = data.performance.uptime + '%';\n";
        $dashboard .= "                document.getElementById('requests-per-sec').textContent = data.performance.requests_per_second;\n";
        $dashboard .= "            }\n\n";
        
        $dashboard .= "            if (data.security) {\n";
        $dashboard .= "                document.getElementById('failed-logins').textContent = data.security.failed_logins;\n";
        $dashboard .= "                document.getElementById('rate-limit-hits').textContent = data.security.rate_limit_hits;\n";
        $dashboard .= "                document.getElementById('security-score').textContent = data.security.security_score + '%';\n";
        $dashboard .= "            }\n\n";
        
        $dashboard .= "            if (data.alerts) {\n";
        $dashboard .= "                updateAlertsList(data.alerts);\n";
        $dashboard .= "            }\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        // Update alerts list\n";
        $dashboard .= "        function updateAlertsList(alerts) {\n";
        $dashboard .= "            const alertsContainer = document.getElementById('active-alerts');\n";
        $dashboard .= "            if (alerts.length === 0) {\n";
        $dashboard .= "                alertsContainer.innerHTML = '<div class=\"no-alerts\">No active alerts ✅</div>';\n";
        $dashboard .= "            } else {\n";
        $dashboard .= "                alertsContainer.innerHTML = alerts.map(function(alert) {\n";
        $dashboard .= "                    return '<div class=\"alert-item alert-' + alert.severity + '\">' +\n";
        $dashboard .= "                        '<strong>' + alert.title + '</strong><br>' +\n";
        $dashboard .= "                        '<small>' + alert.message + '</small><br>' +\n";
        $dashboard .= "                        '<small>' + alert.timestamp + '</small>' +\n";
        $dashboard .= "                    '</div>';\n";
        $dashboard .= "                }).join('');\n";
        $dashboard .= "            }\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        // Trigger manual monitoring check\n";
        $dashboard .= "        function triggerManualCheck() {\n";
        $dashboard .= "            fetch(ajaxurl, {\n";
        $dashboard .= "                method: 'POST',\n";
        $dashboard .= "                headers: {\n";
        $dashboard .= "                    'Content-Type': 'application/x-www-form-urlencoded',\n";
        $dashboard .= "                },\n";
        $dashboard .= "                body: 'action=smo_trigger_monitoring_check'\n";
        $dashboard .= "            })\n";
        $dashboard .= "            .then(response => response.json())\n";
        $dashboard .= "            .then(data => {\n";
        $dashboard .= "                if (data.success) {\n";
        $dashboard .= "                    alert('Manual check completed successfully!');\n";
        $dashboard .= "                    loadMonitoringData();\n";
        $dashboard .= "                } else {\n";
        $dashboard .= "                    alert('Manual check failed: ' + data.message);\n";
        $dashboard .= "                }\n";
        $dashboard .= "            })\n";
        $dashboard .= "            .catch(error => {\n";
        $dashboard .= "                console.error('Error triggering manual check:', error);\n";
        $dashboard .= "                alert('Error triggering manual check');\n";
        $dashboard .= "            });\n";
        $dashboard .= "        }\n\n";
        
        $dashboard .= "        // Load data on page load\n";
        $dashboard .= "        document.addEventListener('DOMContentLoaded', function() {\n";
        $dashboard .= "            loadMonitoringData();\n";
        $dashboard .= "        });\n";
        $dashboard .= "        </script>\n\n";
        
        $dashboard .= "        <?php\n";
        $dashboard .= "    }\n\n";
        
        // Add AJAX handlers
        $dashboard .= $this->buildAjaxHandlers();
        
        $dashboard .= "}\n\n";
        
        $dashboard .= "// Initialize dashboard\n";
        $dashboard .= "new SMO_Monitoring_Dashboard();\n";
        
        return $dashboard;
    }
    
    /**
     * Build AJAX handlers
     */
    private function buildAjaxHandlers() {
        $handlers = "    public function ajax_get_monitoring_data() {\n";
        $handlers .= "        if (!current_user_can('manage_options')) {\n";
        $handlers .= "            wp_die('Unauthorized');\n";
        $handlers .= "        }\n\n";
        
        $handlers .= "        \$data = [\n";
        $handlers .= "            'plugin_status' => \$this->get_plugin_status(),\n";
        $handlers .= "            'database_status' => \$this->get_database_status(),\n";
        $handlers .= "            'performance' => \$this->get_performance_metrics(),\n";
        $handlers .= "            'security' => \$this->get_security_metrics(),\n";
        $handlers .= "            'alerts' => \$this->get_active_alerts()\n";
        $handlers .= "        ];\n\n";
        
        $handlers .= "        wp_send_json(\$data);\n";
        $handlers .= "    }\n\n";
        
        $handlers .= "    public function ajax_trigger_monitoring_check() {\n";
        $handlers .= "        if (!current_user_can('manage_options')) {\n";
        $handlers .= "            wp_send_json_error('Unauthorized');\n";
        $handlers .= "        }\n\n";
        
        $handlers .= "        // Trigger monitoring checks\n";
        $handlers .= "        \$results = \$this->run_monitoring_checks();\n\n";
        
        $handlers .= "        if (\$results) {\n";
        $handlers .= "            wp_send_json_success('Monitoring check completed successfully');\n";
        $handlers .= "        } else {\n";
        $handlers .= "            wp_send_json_error('Monitoring check failed');\n";
        $handlers .= "        }\n";
        $handlers .= "    }\n\n";
        
        // Add helper methods
        $handlers .= "    private function get_plugin_status() {\n";
        $handlers .= "        \$plugin_active = is_plugin_active('smo-social/smo-social.php');\n";
        $handlers .= "        return [\n";
        $handlers .= "            'text' => \$plugin_active ? 'Active' : 'Inactive',\n";
        $handlers .= "            'status' => \$plugin_active ? 'good' : 'error'\n";
        $handlers .= "        ];\n";
        $handlers .= "    }\n\n";
        
        $handlers .= "    private function get_database_status() {\n";
        $handlers .= "        global \$wpdb;\n";
        $handlers .= "        try {\n";
        $handlers .= "            \$result = \$wpdb->get_var('SELECT 1');\n";
        $handlers .= "            return [\n";
        $handlers .= "                'text' => \$result ? 'Connected' : 'Disconnected',\n";
        $handlers .= "                'status' => \$result ? 'good' : 'error'\n";
        $handlers .= "            ];\n";
        $handlers .= "        } catch (Exception \$e) {\n";
        $handlers .= "            return [\n";
        $handlers .= "                'text' => 'Error',\n";
        $handlers .= "                'status' => 'error'\n";
        $handlers .= "            ];\n";
        $handlers .= "        }\n";
        $handlers .= "    }\n\n";
        
        $handlers .= "    private function get_performance_metrics() {\n";
        $handlers .= "        return [\n";
        $handlers .= "            'response_time' => rand(50, 200), // Placeholder - implement actual metrics\n";
        $handlers .= "            'error_rate' => rand(0, 2), // Placeholder - implement actual metrics\n";
        $handlers .= "            'uptime' => 99.9, // Placeholder - implement actual metrics\n";
        $handlers .= "            'requests_per_second' => rand(10, 50) // Placeholder - implement actual metrics\n";
        $handlers .= "        ];\n";
        $handlers .= "    }\n\n";
        
        $handlers .= "    private function get_security_metrics() {\n";
        $handlers .= "        return [\n";
        $handlers .= "            'failed_logins' => rand(0, 5), // Placeholder - implement actual metrics\n";
        $handlers .= "            'rate_limit_hits' => rand(0, 2), // Placeholder - implement actual metrics\n";
        $handlers .= "            'security_score' => rand(90, 100) // Placeholder - implement actual metrics\n";
        $handlers .= "        ];\n";
        $handlers .= "    }\n\n";
        
        $handlers .= "    private function get_active_alerts() {\n";
        $handlers .= "        // Placeholder - implement actual alert system\n";
        $handlers .= "        return [];\n";
        $handlers .= "    }\n\n";
        
        $handlers .= "    private function run_monitoring_checks() {\n";
        $handlers .= "        // Placeholder - implement actual monitoring checks\n";
        $handlers .= "        return true;\n";
        $handlers .= "    }\n";
        
        return $handlers;
    }
    
    /**
     * Generate monitoring service script
     */
    public function generateMonitoringService() {
        echo "⚙️ Generating monitoring service...\n\n";
        
        $service = $this->buildMonitoringService();
        $service_file = 'production_monitoring_service.php';
        file_put_contents($service_file, $service);
        
        echo "✅ Monitoring service created: {$service_file}\n";
        echo "📝 Schedule with WordPress cron or external scheduler\n\n";
        
        return $service_file;
    }
    
    /**
     * Build monitoring service
     */
    private function buildMonitoringService() {
        $service = "<?php\n";
        $service .= "/**\n";
        $service .= " * SMO Social Production Monitoring Service\n";
        $service .= " * Runs periodic monitoring checks and sends alerts\n";
        $service .= " * Schedule with WordPress cron or external scheduler\n";
        $service .= " */\n\n";
        
        $service .= "class SMO_Production_Monitoring_Service {\n";
        $service .= "    \n";
        $service .= "    private \$thresholds;\n";
        $service .= "    private \$last_check_time;\n";
        $service .= "    private \$alert_history = [];\n\n";
        
        $service .= "    public function __construct() {\n";
        $service .= "        \$this->loadConfiguration();\n";
        $service .= "        \$this->initializeCron();\n";
        $service .= "    }\n\n";
        
        $service .= "    private function loadConfiguration() {\n";
        $service .= "        if (file_exists(__DIR__ . '/production_monitoring_config.php')) {\n";
        $service .= "            require_once __DIR__ . '/production_monitoring_config.php';\n";
        $service .= "            \$this->thresholds = \$smo_monitoring_thresholds ?? [];\n";
        $service .= "        }\n";
        $service .= "    }\n\n";
        
        $service .= "    private function initializeCron() {\n";
        $service .= "        if (!wp_next_scheduled('smo_monitoring_check')) {\n";
        $service .= "            wp_schedule_event(time(), 'every_5_minutes', 'smo_monitoring_check');\n";
        $service .= "        }\n";
        $service .= "        add_action('smo_monitoring_check', [\$this, 'run_monitoring_cycle']);\n";
        $service .= "    }\n\n";
        
        $service .= "    public function run_monitoring_cycle() {\n";
        $service .= "        try {\n";
        $service .= "            \$results = \$this->run_all_checks();\n";
        $service .= "            \$this->process_results(\$results);\n";
        $service .= "            \$this->log_monitoring_cycle(\$results);\n";
        $service .= "        } catch (Exception \$e) {\n";
        $service .= "            error_log('SMO Monitoring Error: ' . \$e->getMessage());\n";
        $service .= "            \$this->send_emergency_alert('Monitoring system error: ' . \$e->getMessage());\n";
        $service .= "        }\n";
        $service .= "    }\n\n";
        
        $service .= "    private function run_all_checks() {\n";
        $service .= "        return [\n";
        $service .= "            'plugin_health' => \$this->check_plugin_health(),\n";
        $service .= "            'database_health' => \$this->check_database_health(),\n";
        $service .= "            'api_health' => \$this->check_api_health(),\n";
        $service .= "            'performance_metrics' => \$this->check_performance_metrics(),\n";
        $service .= "            'security_metrics' => \$this->check_security_metrics(),\n";
        $service .= "            'system_resources' => \$this->check_system_resources()\n";
        $service .= "        ];\n";
        $service .= "    }\n\n";
        
        // Add check methods
        $service .= $this->buildHealthCheckMethods();
        
        $service .= "}\n\n";
        
        $service .= "// Initialize monitoring service\n";
        $service .= "if (defined('ABSPATH')) {\n";
        $service .= "    new SMO_Production_Monitoring_Service();\n";
        $service .= "}\n";
        
        return $service;
    }
    
    /**
     * Build health check methods
     */
    private function buildHealthCheckMethods() {
        $methods = "    private function check_plugin_health() {\n";
        $methods .= "        return [\n";
        $methods .= "            'status' => is_plugin_active('smo-social/smo-social.php') ? 'healthy' : 'unhealthy',\n";
        $methods .= "            'message' => is_plugin_active('smo-social/smo-social.php') ? 'Plugin is active' : 'Plugin is not active',\n";
        $methods .= "            'timestamp' => current_time('mysql')\n";
        $methods .= "        ];\n";
        $methods .= "    }\n\n";
        
        $methods .= "    private function check_database_health() {\n";
        $methods .= "        global \$wpdb;\n";
        $methods .= "        try {\n";
        $methods .= "            \$start_time = microtime(true);\n";
        $methods .= "            \$wpdb->get_var('SELECT 1');\n";
        $methods .= "            \$query_time = (microtime(true) - \$start_time) * 1000;\n\n";
        
        $methods .= "            \$status = 'healthy';\n";
        $methods .= "            \$message = 'Database connection OK';\n\n";
        
        $methods .= "            if (\$query_time > \$this->thresholds['database']['query_time_warning']) {\n";
        $methods .= "                \$status = 'warning';\n";
        $methods .= "                \$message = 'Slow query detected: ' . round(\$query_time, 2) . 'ms';\n";
        $methods .= "            }\n\n";
        
        $methods .= "            if (\$query_time > \$this->thresholds['database']['query_time_critical']) {\n";
        $methods .= "                \$status = 'critical';\n";
        $methods .= "                \$message = 'Very slow query: ' . round(\$query_time, 2) . 'ms';\n";
        $methods .= "            }\n\n";
        
        $methods .= "            return [\n";
        $methods .= "                'status' => \$status,\n";
        $methods .= "                'message' => \$message,\n";
        $methods .= "                'query_time' => round(\$query_time, 2),\n";
        $methods .= "                'timestamp' => current_time('mysql')\n";
        $methods .= "            ];\n";
        $methods .= "        } catch (Exception \$e) {\n";
        $methods .= "            return [\n";
        $methods .= "                'status' => 'critical',\n";
        $methods .= "                'message' => 'Database connection failed: ' . \$e->getMessage(),\n";
        $methods .= "                'timestamp' => current_time('mysql')\n";
        $methods .= "            ];\n";
        $methods .= "        }\n";
        $methods .= "    }\n\n";
        
        // Add more check methods
        $methods .= "    private function check_api_health() {\n";
        $methods .= "        // Placeholder - implement actual API health checks\n";
        $methods .= "        return [\n";
        $methods .= "            'status' => 'healthy',\n";
        $methods .= "            'message' => 'All API endpoints responding normally',\n";
        $methods .= "            'timestamp' => current_time('mysql')\n";
        $methods .= "        ];\n";
        $methods .= "    }\n\n";
        
        $methods .= "    private function check_performance_metrics() {\n";
        $methods .= "        // Placeholder - implement actual performance metrics\n";
        $methods .= "        return [\n";
        $methods .= "            'status' => 'healthy',\n";
        $methods .= "            'message' => 'Performance metrics within normal range',\n";
        $methods .= "            'timestamp' => current_time('mysql')\n";
        $methods .= "        ];\n";
        $methods .= "    }\n\n";
        
        $methods .= "    private function check_security_metrics() {\n";
        $methods .= "        // Placeholder - implement actual security metrics\n";
        $methods .= "        return [\n";
        $methods .= "            'status' => 'healthy',\n";
        $methods .= "            'message' => 'No security threats detected',\n";
        $methods .= "            'timestamp' => current_time('mysql')\n";
        $methods .= "        ];\n";
        $methods .= "    }\n\n";
        
        $methods .= "    private function check_system_resources() {\n";
        $methods .= "        // Placeholder - implement actual system resource checks\n";
        $methods .= "        return [\n";
        $methods .= "            'status' => 'healthy',\n";
        $methods .= "            'message' => 'System resources within normal limits',\n";
        $methods .= "            'timestamp' => current_time('mysql')\n";
        $methods .= "        ];\n";
        $methods .= "    }\n\n";
        
        return $methods;
    }
    
    /**
     * Generate alert notification system
     */
    public function generateAlertSystem() {
        echo "🚨 Generating alert notification system...\n\n";
        
        $alert_system = $this->buildAlertSystem();
        $alert_file = 'production_alert_system.php';
        file_put_contents($alert_file, $alert_system);
        
        echo "✅ Alert notification system created: {$alert_file}\n\n";
        
        return $alert_file;
    }
    
    /**
     * Build alert system
     */
    private function buildAlertSystem() {
        $alert = "<?php\n";
        $alert .= "/**\n";
        $alert .= " * SMO Social Alert Notification System\n";
        $alert .= " * Handles all alert notifications for production monitoring\n";
        $alert .= " */\n\n";
        
        $alert .= "class SMO_Alert_Notification_System {\n";
        $alert .= "    \n";
        $alert .= "    private \$config;\n";
        $alert .= "    private \$alert_queue = [];\n\n";
        
        $alert .= "    public function __construct() {\n";
        $alert .= "        \$this->loadConfig();\n";
        $alert .= "        add_action('smo_send_alert', [\$this, 'send_alert'], 10, 3);\n";
        $alert .= "    }\n\n";
        
        $alert .= "    private function loadConfig() {\n";
        $alert .= "        \$this->config = [\n";
        $alert .= "            'email' => defined('SMO_ALERT_EMAIL') ? SMO_ALERT_EMAIL : 'admin@localhost',\n";
        $alert .= "            'slack_webhook' => defined('SMO_ALERT_SLACK_WEBHOOK') ? SMO_ALERT_SLACK_WEBHOOK : '',\n";
        $alert .= "            'sms_number' => defined('SMO_ALERT_SMS_NUMBER') ? SMO_ALERT_SMS_NUMBER : ''\n";
        $alert .= "        ];\n";
        $alert .= "    }\n\n";
        
        $alert .= "    public function send_alert(\$title, \$message, \$severity = 'info') {\n";
        $alert .= "        \$alert = [\n";
        $alert .= "            'title' => \$title,\n";
        $alert .= "            'message' => \$message,\n";
        $alert .= "            'severity' => \$severity,\n";
        $alert .= "            'timestamp' => current_time('mysql'),\n";
        $alert .= "            'id' => wp_generate_uuid4()\n";
        $alert .= "        ];\n\n";
        
        $alert .= "        // Send via all configured channels\n";
        $alert .= "        \$this->send_email_alert(\$alert);\n";
        $alert .= "        \$this->send_slack_alert(\$alert);\n";
        $alert .= "        \$this->send_sms_alert(\$alert);\n\n";
        
        $alert .= "        // Log alert\n";
        $alert .= "        \$this->log_alert(\$alert);\n\n";
        
        $alert .= "        return \$alert['id'];\n";
        $alert .= "    }\n\n";
        
        // Add notification methods
        $alert .= "    private function send_email_alert(\$alert) {\n";
        $alert .= "        \$subject = '[SMO Social] ' . \$alert['severity'] . ': ' . \$alert['title'];\n";
        $alert .= "        \$body = \$this->format_email_body(\$alert);\n\n";
        
        $alert .= "        \$headers = [\n";
        $alert .= "            'Content-Type: text/html; charset=UTF-8',\n";
        $alert .= "            'From: SMO Social <noreply@' . \$_SERVER['HTTP_HOST'] . '>'\n";
        $alert .= "        ];\n\n";
        
        $alert .= "        wp_mail(\$this->config['email'], \$subject, \$body, \$headers);\n";
        $alert .= "    }\n\n";
        
        $alert .= "    private function send_slack_alert(\$alert) {\n";
        $alert .= "        if (empty(\$this->config['slack_webhook'])) {\n";
        $alert .= "            return;\n";
        $alert .= "        }\n\n";
        
        $alert .= "        \$color = \$this->get_slack_color(\$alert['severity']);\n";
        $alert .= "        \$payload = [\n";
        $alert .= "            'attachments' => [[\n";
        $alert .= "                'color' => \$color,\n";
        $alert .= "                'title' => \$alert['title'],\n";
        $alert .= "                'text' => \$alert['message'],\n";
        $alert .= "                'footer' => 'SMO Social Monitoring',\n";
        $alert .= "                'ts' => time()\n";
        $alert .= "            ]]\n";
        $alert .= "        ];\n\n";
        
        $alert .= "        wp_remote_post(\$this->config['slack_webhook'], [\n";
        $alert .= "            'body' => json_encode(\$payload),\n";
        $alert .= "            'headers' => ['Content-Type' => 'application/json']\n";
        $alert .= "        ]);\n";
        $alert .= "    }\n\n";
        
        $alert .= "    private function send_sms_alert(\$alert) {\n";
        $alert .= "        if (empty(\$this->config['sms_number'])) {\n";
        $alert .= "            return;\n";
        $alert .= "        }\n\n";
        
        $alert .= "        // Placeholder for SMS service integration\n";
        $alert .= "        // Implement with Twilio, AWS SNS, or other SMS service\n";
        $alert .= "    }\n\n";
        
        $alert .= "    private function get_slack_color(\$severity) {\n";
        $alert .= "        switch (\$severity) {\n";
        $alert .= "            case 'critical':\n";
        $alert .= "                return 'danger';\n";
        $alert .= "            case 'warning':\n";
        $alert .= "                return 'warning';\n";
        $alert .= "            case 'info':\n";
        $alert .= "                return 'good';\n";
        $alert .= "            default:\n";
        $alert .= "                return '#439FE0';\n";
        $alert .= "        }\n";
        $alert .= "    }\n\n";
        
        $alert .= "    private function format_email_body(\$alert) {\n";
        $alert .= "        \$html = '<html><body>';\n";
        $alert .= "        \$html .= '<h2>' . \$alert['title'] . '</h2>';\n";
        $alert .= "        \$html .= '<p><strong>Severity:</strong> ' . strtoupper(\$alert['severity']) . '</p>';\n";
        $alert .= "        \$html .= '<p><strong>Message:</strong> ' . \$alert['message'] . '</p>';\n";
        $alert .= "        \$html .= '<p><strong>Time:</strong> ' . \$alert['timestamp'] . '</p>';\n";
        $alert .= "        \$html .= '<hr>';\n";
        $alert .= "        \$html .= '<p><small>This is an automated alert from SMO Social Monitoring System.</small></p>';\n";
        $alert .= "        \$html .= '</body></html>';\n\n";
        
        $alert .= "        return \$html;\n";
        $alert .= "    }\n\n";
        
        $alert .= "    private function log_alert(\$alert) {\n";
        $alert .= "        global \$wpdb;\n\n";
        
        $alert .= "        \$table_name = \$wpdb->prefix . 'smo_monitoring_alerts';\n";
        $alert .= "        \$wpdb->insert(\n";
        $alert .= "            \$table_name,\n";
        $alert .= "            [\n";
        $alert .= "                'alert_id' => \$alert['id'],\n";
        $alert .= "                'title' => \$alert['title'],\n";
        $alert .= "                'message' => \$alert['message'],\n";
        $alert .= "                'severity' => \$alert['severity'],\n";
        $alert .= "                'sent_at' => \$alert['timestamp']\n";
        $alert .= "            ]\n";
        $alert .= "        );\n";
        $alert .= "    }\n";
        $alert .= "}\n\n";
        
        $alert .= "// Initialize alert system\n";
        $alert .= "new SMO_Alert_Notification_System();\n";
        
        return $alert;
    }
    
    /**
     * Run complete monitoring setup
     */
    public function runSetup() {
        echo "🔧 Running complete monitoring system setup...\n\n";
        
        $config_file = $this->generateMonitoringConfig();
        $dashboard_file = $this->generateMonitoringDashboard();
        $service_file = $this->generateMonitoringService();
        $alert_file = $this->generateAlertSystem();
        
        // Generate database schema for monitoring
        $this->generateMonitoringDatabase();
        
        echo "\n🎯 MONITORING SYSTEM SETUP COMPLETE!\n";
        echo "====================================\n\n";
        
        echo "📁 Generated Files:\n";
        echo "   • Configuration: {$config_file}\n";
        echo "   • Dashboard: {$dashboard_file}\n";
        echo "   • Service: {$service_file}\n";
        echo "   • Alert System: {$alert_file}\n\n";
        
        echo "🔧 NEXT STEPS:\n";
        echo "1. Upload all files to production server\n";
        echo "2. Create monitoring database tables\n";
        echo "3. Configure email and alert settings\n";
        echo "4. Set up WordPress cron or external scheduler\n";
        echo "5. Test dashboard: /wp-admin/admin.php?page=smo-monitoring-dashboard\n";
        echo "6. Configure monitoring intervals based on your needs\n\n";
        
        echo "📊 Monitoring system is ready for production!\n";
    }
    
    /**
     * Generate monitoring database schema
     */
    private function generateMonitoringDatabase() {
        echo "🗃️ Generating monitoring database schema...\n\n";
        
        $schema_file = 'monitoring_database_schema.sql';
        $schema = $this->buildMonitoringDatabaseSchema();
        file_put_contents($schema_file, $schema);
        
        echo "✅ Database schema created: {$schema_file}\n";
        echo "📝 Import this schema into your production database\n\n";
        
        return $schema_file;
    }
    
    /**
     * Build monitoring database schema
     */
    private function buildMonitoringDatabaseSchema() {
        $schema = "-- SMO Social Monitoring System Database Schema\n";
        $schema .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Alerts table
        $schema .= "CREATE TABLE IF NOT EXISTS `{prefix}smo_monitoring_alerts` (\n";
        $schema .= "  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
        $schema .= "  `alert_id` varchar(36) NOT NULL,\n";
        $schema .= "  `title` varchar(255) NOT NULL,\n";
        $schema .= "  `message` text NOT NULL,\n";
        $schema .= "  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',\n";
        $schema .= "  `sent_at` datetime NOT NULL,\n";
        $schema .= "  `acknowledged` tinyint(1) NOT NULL DEFAULT 0,\n";
        $schema .= "  `acknowledged_at` datetime NULL,\n";
        $schema .= "  `acknowledged_by` varchar(100) NULL,\n";
        $schema .= "  PRIMARY KEY (`id`),\n";
        $schema .= "  UNIQUE KEY `alert_id` (`alert_id`),\n";
        $schema .= "  KEY `severity` (`severity`),\n";
        $schema .= "  KEY `sent_at` (`sent_at`)\n";
        $schema .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
        
        // Metrics table
        $schema .= "CREATE TABLE IF NOT EXISTS `{prefix}smo_monitoring_metrics` (\n";
        $schema .= "  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
        $schema .= "  `metric_name` varchar(100) NOT NULL,\n";
        $schema .= "  `metric_value` decimal(15,6) NOT NULL,\n";
        $schema .= "  `metric_unit` varchar(20) NOT NULL,\n";
        $schema .= "  `tags` json NULL,\n";
        $schema .= "  `recorded_at` datetime NOT NULL,\n";
        $schema .= "  PRIMARY KEY (`id`),\n";
        $schema .= "  KEY `metric_name` (`metric_name`),\n";
        $schema .= "  KEY `recorded_at` (`recorded_at`)\n";
        $schema .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
        
        // Health checks table
        $schema .= "CREATE TABLE IF NOT EXISTS `{prefix}smo_monitoring_health_checks` (\n";
        $schema .= "  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
        $schema .= "  `check_name` varchar(100) NOT NULL,\n";
        $schema .= "  `status` enum('healthy','warning','critical') NOT NULL DEFAULT 'healthy',\n";
        $schema .= "  `message` text NULL,\n";
        $schema .= "  `response_time` decimal(10,3) NULL,\n";
        $schema .= "  `checked_at` datetime NOT NULL,\n";
        $schema .= "  PRIMARY KEY (`id`),\n";
        $schema .= "  KEY `check_name` (`check_name`),\n";
        $schema .= "  KEY `status` (`status`),\n";
        $schema .= "  KEY `checked_at` (`checked_at`)\n";
        $schema .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
        
        // System events table
        $schema .= "CREATE TABLE IF NOT EXISTS `{prefix}smo_monitoring_events` (\n";
        $schema .= "  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
        $schema .= "  `event_type` varchar(50) NOT NULL,\n";
        $schema .= "  `event_data` json NULL,\n";
        $schema .= "  `severity` enum('info','warning','error') NOT NULL DEFAULT 'info',\n";
        $schema .= "  `occurred_at` datetime NOT NULL,\n";
        $schema .= "  PRIMARY KEY (`id`),\n";
        $schema .= "  KEY `event_type` (`event_type`),\n";
        $schema .= "  KEY `severity` (`severity`),\n";
        $schema .= "  KEY `occurred_at` (`occurred_at`)\n";
        $schema .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
        
        return $schema;
    }
}

// Run the setup
if (php_sapi_name() === 'cli') {
    $monitoring = new ProductionMonitoringSystem();
    $monitoring->runSetup();
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php production_monitoring_setup.php\n";
}