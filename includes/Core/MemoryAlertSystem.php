<?php
/**
 * Memory Alert System for SMO Social
 *
 * Handles memory-related alerts, notifications, and alert management
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class MemoryAlertSystem {
    /**
     * @var MemoryAlertSystem|null Singleton instance
     */
    private static $instance = null;

    /**
     * @var array Active alerts
     */
    private $active_alerts = [];

    /**
     * @var array Alert history
     */
    private $alert_history = [];

    /**
     * @var array Alert thresholds
     */
    private $alert_thresholds = [];

    /**
     * @var int Max active alerts
     */
    private $max_active_alerts = 50;

    /**
     * @var int Max history entries
     */
    private $max_history_entries = 200;

    /**
     * @var int Auto-resolve hours
     */
    private $auto_resolve_hours = 24;

    /**
     * @var array Notification channels
     */
    private $notification_channels = ['admin_dashboard', 'log'];

    /**
     * MemoryAlertSystem constructor (private for singleton)
     */
    private function __construct() {
        $this->initialize_config();
        $this->setup_hooks();
    }

    /**
     * Get singleton instance
     *
     * @return MemoryAlertSystem
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize configuration
     */
    private function initialize_config() {
        $config = MemoryMonitorConfig::get_instance()->get_alert_config();

        $this->alert_thresholds = [
            'warning' => $config['warning_threshold'] ?? 70,
            'critical' => $config['critical_threshold'] ?? 90
        ];

        $this->max_active_alerts = $config['max_active_alerts'] ?? 50;
        $this->max_history_entries = $config['max_history_entries'] ?? 200;
        $this->auto_resolve_hours = $config['auto_resolve_hours'] ?? 24;
        $this->notification_channels = $config['notification_channels'] ?? ['admin_dashboard', 'log'];
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Schedule alert cleanup
        add_action('smo_memory_alert_cleanup', [$this, 'cleanup_old_alerts']);

        // Initialize cron job if not already scheduled
        if (!wp_next_scheduled('smo_memory_alert_cleanup')) {
            wp_schedule_event(time(), 'daily', 'smo_memory_alert_cleanup');
        }
    }

    /**
     * Trigger a memory alert
     *
     * @param string $alert_type Alert type identifier
     * @param string $title Alert title
     * @param string $message Alert message
     * @param string $severity Alert severity (info, warning, critical)
     * @param array $context Additional context data
     * @return bool True if alert was triggered
     */
    public function trigger_alert($alert_type, $title, $message, $severity = 'warning', $context = []) {
        try {
            // Check if alert already exists and is active
            if ($this->is_alert_active($alert_type)) {
                // Update existing alert
                $this->update_alert($alert_type, $title, $message, $severity, $context);
                return true;
            }

            // Check if we've reached the max active alerts limit
            if (count($this->active_alerts) >= $this->max_active_alerts) {
                Logger::warning('Maximum active alerts limit reached, cannot create new alert', [
                    'alert_type' => $alert_type,
                    'max_active' => $this->max_active_alerts
                ]);
                return false;
            }

            // Create new alert
            $alert = [
                'id' => uniqid('alert_', true),
                'type' => $alert_type,
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'context' => $context,
                'timestamp' => time(),
                'status' => 'active',
                'last_updated' => time()
            ];

            $this->active_alerts[$alert_type] = $alert;
            $this->alert_history[] = $alert;

            // Limit history size
            if (count($this->alert_history) > $this->max_history_entries) {
                array_shift($this->alert_history);
            }

            // Send notifications
            $this->send_notifications($alert);

            // Log alert
            $this->log_alert($alert, 'triggered');

            return true;

        } catch (\Exception $e) {
            Logger::error('Failed to trigger memory alert: ' . $e->getMessage(), [
                'alert_type' => $alert_type,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Resolve an alert
     *
     * @param string $alert_type Alert type to resolve
     * @param string $resolution_note Optional resolution note
     * @return bool True if alert was resolved
     */
    public function resolve_alert($alert_type, $resolution_note = '') {
        if (!isset($this->active_alerts[$alert_type])) {
            return false;
        }

        $alert = $this->active_alerts[$alert_type];
        $alert['status'] = 'resolved';
        $alert['resolution_note'] = $resolution_note;
        $alert['resolved_at'] = time();
        $alert['last_updated'] = time();

        // Move to history
        $this->alert_history[] = $alert;
        unset($this->active_alerts[$alert_type]);

        // Log resolution
        $this->log_alert($alert, 'resolved');

        return true;
    }

    /**
     * Update an existing alert
     *
     * @param string $alert_type Alert type to update
     * @param string $title New title
     * @param string $message New message
     * @param string $severity New severity
     * @param array $context Updated context
     * @return bool True if alert was updated
     */
    public function update_alert($alert_type, $title, $message, $severity, $context = []) {
        if (!isset($this->active_alerts[$alert_type])) {
            return false;
        }

        $alert = $this->active_alerts[$alert_type];
        $alert['title'] = $title;
        $alert['message'] = $message;
        $alert['severity'] = $severity;
        $alert['context'] = array_merge($alert['context'], $context);
        $alert['last_updated'] = time();

        $this->active_alerts[$alert_type] = $alert;

        // Log update
        $this->log_alert($alert, 'updated');

        return true;
    }

    /**
     * Check if an alert is currently active
     *
     * @param string $alert_type Alert type to check
     * @return bool True if alert is active
     */
    public function is_alert_active($alert_type) {
        return isset($this->active_alerts[$alert_type]);
    }

    /**
     * Get active alerts
     *
     * @return array Active alerts
     */
    public function get_active_alerts() {
        return array_values($this->active_alerts);
    }

    /**
     * Get alert history
     *
     * @param int $limit Number of entries to return
     * @return array Alert history
     */
    public function get_alert_history($limit = 100) {
        $history = array_slice(array_reverse($this->alert_history), 0, $limit);
        return $history;
    }

    /**
     * Get alert statistics
     *
     * @return array Alert statistics
     */
    public function get_alert_statistics() {
        $stats = [
            'active_alerts' => count($this->active_alerts),
            'total_history' => count($this->alert_history),
            'alerts_by_severity' => [
                'info' => 0,
                'warning' => 0,
                'critical' => 0
            ],
            'alerts_by_type' => [],
            'recent_alerts_24h' => 0
        ];

        $yesterday = time() - (24 * 60 * 60);

        foreach ($this->active_alerts as $alert) {
            $stats['alerts_by_severity'][$alert['severity']]++;
            $stats['alerts_by_type'][$alert['type']] = ($stats['alerts_by_type'][$alert['type']] ?? 0) + 1;
        }

        foreach ($this->alert_history as $alert) {
            if ($alert['timestamp'] >= $yesterday) {
                $stats['recent_alerts_24h']++;
            }
        }

        return $stats;
    }

    /**
     * Send notifications for an alert
     *
     * @param array $alert Alert data
     */
    private function send_notifications($alert) {
        foreach ($this->notification_channels as $channel) {
            switch ($channel) {
                case 'admin_dashboard':
                    $this->send_admin_notification($alert);
                    break;
                case 'email':
                    $this->send_email_notification($alert);
                    break;
                case 'log':
                    $this->send_log_notification($alert);
                    break;
                case 'webhook':
                    $this->send_webhook_notification($alert);
                    break;
            }
        }
    }

    /**
     * Send admin dashboard notification
     *
     * @param array $alert Alert data
     */
    private function send_admin_notification($alert) {
        // Store in transient for admin display
        $admin_alerts = get_transient('smo_memory_admin_alerts') ?: [];
        $admin_alerts[] = $alert;

        // Keep only last 10 alerts
        if (count($admin_alerts) > 10) {
            $admin_alerts = array_slice($admin_alerts, -10);
        }

        set_transient('smo_memory_admin_alerts', $admin_alerts, HOUR_IN_SECONDS);
    }

    /**
     * Send email notification
     *
     * @param array $alert Alert data
     */
    private function send_email_notification($alert) {
        $config = MemoryMonitorConfig::get_instance()->get_alert_config();

        if (!$config['email_notifications'] || empty($config['email_recipients'])) {
            return;
        }

        $subject = sprintf('[SMO Social] Memory Alert: %s', $alert['title']);
        $message = sprintf(
            "Memory Alert Details:\n\nType: %s\nSeverity: %s\nMessage: %s\nTime: %s\n\nPlease check your SMO Social memory monitoring dashboard.",
            $alert['type'],
            $alert['severity'],
            $alert['message'],
            date('Y-m-d H:i:s', $alert['timestamp'])
        );

        foreach ($config['email_recipients'] as $email) {
            wp_mail($email, $subject, $message);
        }
    }

    /**
     * Send log notification
     *
     * @param array $alert Alert data
     */
    private function send_log_notification($alert) {
        $log_message = sprintf(
            'Memory Alert - Type: %s, Severity: %s, Message: %s',
            $alert['type'],
            $alert['severity'],
            $alert['message']
        );

        Logger::warning($log_message, $alert);
    }

    /**
     * Send webhook notification
     *
     * @param array $alert Alert data
     */
    private function send_webhook_notification($alert) {
        // Implementation for webhook notifications
        // This would integrate with external monitoring systems
        Logger::info('Webhook notification for alert: ' . $alert['type'], $alert);
    }

    /**
     * Log alert activity
     *
     * @param array $alert Alert data
     * @param string $action Action performed
     */
    private function log_alert($alert, $action) {
        Logger::info("Alert $action: " . $alert['type'], [
            'alert_id' => $alert['id'],
            'alert_type' => $alert['type'],
            'severity' => $alert['severity'],
            'action' => $action
        ]);
    }

    /**
     * Cleanup old alerts
     */
    public function cleanup_old_alerts() {
        $cutoff_time = time() - ($this->auto_resolve_hours * 60 * 60);
        $resolved_count = 0;

        foreach ($this->active_alerts as $alert_type => $alert) {
            if ($alert['timestamp'] < $cutoff_time) {
                $this->resolve_alert($alert_type, 'Auto-resolved after ' . $this->auto_resolve_hours . ' hours');
                $resolved_count++;
            }
        }

        if ($resolved_count > 0) {
            Logger::info("Auto-resolved $resolved_count old alerts");
        }
    }

    /**
     * Update configuration
     *
     * @param array $new_config New configuration
     */
    public function update_config($new_config) {
        $config_instance = MemoryMonitorConfig::get_instance();

        if (isset($new_config['alert_thresholds'])) {
            $this->alert_thresholds = $new_config['alert_thresholds'];
        }

        if (isset($new_config['max_active_alerts'])) {
            $this->max_active_alerts = $new_config['max_active_alerts'];
        }

        if (isset($new_config['max_history_entries'])) {
            $this->max_history_entries = $new_config['max_history_entries'];
        }

        if (isset($new_config['auto_resolve_hours'])) {
            $this->auto_resolve_hours = $new_config['auto_resolve_hours'];
        }

        if (isset($new_config['notification_channels'])) {
            $this->notification_channels = $new_config['notification_channels'];
        }
    }

    /**
     * Get alert by type
     *
     * @param string $alert_type Alert type
     * @return array|null Alert data or null if not found
     */
    public function get_alert($alert_type) {
        return $this->active_alerts[$alert_type] ?? null;
    }

    /**
     * Clear all active alerts (for testing/emergency use)
     */
    public function clear_all_alerts() {
        $cleared_count = count($this->active_alerts);
        $this->active_alerts = [];

        Logger::warning("Cleared all $cleared_count active alerts");
        return $cleared_count;
    }
}