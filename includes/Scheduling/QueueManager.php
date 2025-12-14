<?php
namespace SMO_Social\Scheduling;

class QueueManager {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smo_queue';
    }

    /**
     * Get pending items from the queue
     */
    public function get_pending_items($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'pending' 
             AND scheduled_for <= %s
             ORDER BY priority DESC, created_at ASC 
             LIMIT %d",
            current_time('mysql'),
            $limit
        ));
    }

    /**
     * Add item to queue
     */
    public function add_to_queue($scheduled_post_id, $platform_slug, $priority = 'normal', $scheduled_for = null) {
        global $wpdb;
        
        if ($scheduled_for === null) {
            $scheduled_for = current_time('mysql');
        }
        
        return $wpdb->insert(
            $this->table_name,
            array(
                'scheduled_post_id' => $scheduled_post_id,
                'platform_slug' => $platform_slug,
                'priority' => $priority,
                'scheduled_for' => $scheduled_for,
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => 3,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
    }

    /**
     * Update queue item status
     */
    public function update_status($queue_id, $status, $error_message = null, $processed_at = null) {
        global $wpdb;
        
        $data = array('status' => $status);
        $format = array('%s');
        
        if ($error_message !== null) {
            $data['error_message'] = $error_message;
            $format[] = '%s';
        }
        
        if ($processed_at !== null) {
            $data['processed_at'] = $processed_at;
            $format[] = '%s';
        }
        
        $data['updated_at'] = current_time('mysql');
        $format[] = '%s';
        
        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $queue_id),
            $format,
            array('%d')
        );
    }

    /**
     * Increment attempts for a queue item
     */
    public function increment_attempts($queue_id) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET attempts = attempts + 1 
             WHERE id = %d",
            $queue_id
        ));
    }

    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$this->table_name} 
             GROUP BY status",
            ARRAY_A
        );
        
        $result = array();
        foreach ($stats as $stat) {
            $result[$stat['status']] = (int) $stat['count'];
        }
        
        return array(
            'pending' => $result['pending'] ?? 0,
            'processing' => $result['processing'] ?? 0,
            'completed' => $result['completed'] ?? 0,
            'failed' => $result['failed'] ?? 0,
            'retry' => $result['retry'] ?? 0
        );
    }

    /**
     * Get queue items by status
     */
    public function get_items_by_status($status, $limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, sp.title, sp.content, sp.scheduled_time as post_scheduled_time
             FROM {$this->table_name} q
             LEFT JOIN {$wpdb->prefix}smo_scheduled_posts sp ON q.scheduled_post_id = sp.id
             WHERE q.status = %s
             ORDER BY q.priority DESC, q.scheduled_for ASC
             LIMIT %d OFFSET %d",
            $status,
            $limit,
            $offset
        ));
    }

    /**
     * Get failed items for retry
     */
    public function get_failed_items_for_retry() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT q.*, sp.title, sp.content
             FROM {$this->table_name} q
             LEFT JOIN {$wpdb->prefix}smo_scheduled_posts sp ON q.scheduled_post_id = sp.id
             WHERE q.status = 'retry' 
             AND q.scheduled_for <= NOW()
             ORDER BY q.priority DESC, q.scheduled_for ASC"
        );
    }

    /**
     * Clear completed items older than specified days
     */
    public function cleanup_completed_items($days = 7) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE status IN ('completed', 'failed') 
             AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    /**
     * Get queue processing time statistics
     */
    public function get_processing_stats($days = 30) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_items,
                AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) as avg_processing_time
             FROM {$this->table_name} 
             WHERE status = 'completed' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            $days
        ));
    }

    /**
     * Get platform queue statistics
     */
    public function get_platform_stats($days = 30) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                platform_slug,
                status,
                COUNT(*) as count
             FROM {$this->table_name}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY platform_slug, status
             ORDER BY platform_slug, status",
            $days
        ));
    }

    /**
     * Reschedule failed items
     */
    public function reschedule_failed_items($new_scheduled_for) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = 'pending', 
                 scheduled_for = %s,
                 attempts = 0,
                 error_message = NULL
             WHERE status = 'failed'",
            $new_scheduled_for
        ));
    }

    /**
     * Cancel queue items for a scheduled post
     */
    public function cancel_post_queue_items($scheduled_post_id) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            array('status' => 'cancelled'),
            array('scheduled_post_id' => $scheduled_post_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Get next item in queue (for single processing)
     */
    public function get_next_item() {
        global $wpdb;
        
        return $wpdb->get_row(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'pending' 
             AND scheduled_for <= NOW()
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1"
        );
    }

    /**
     * Mark queue item as processing (with locking to prevent double processing)
     */
    public function lock_and_process($queue_id, $lock_timeout = 300) {
        global $wpdb;
        
        // Try to lock the item for processing
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = 'processing',
                 processed_at = %s
             WHERE id = %d 
             AND status = 'pending' 
             AND (processed_at IS NULL OR processed_at < DATE_SUB(NOW(), INTERVAL %d SECOND))",
            current_time('mysql'),
            $queue_id,
            $lock_timeout
        ));
        
        return $updated > 0;
    }

    /**
     * Get queue health metrics
     */
    public function get_health_metrics() {
        global $wpdb;
        
        $metrics = array();
        
        // Total items in queue
        $metrics['total_items'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status IN ('pending', 'processing')"
        );
        
        // Oldest pending item
        $metrics['oldest_pending'] = $wpdb->get_var(
            "SELECT MIN(TIMESTAMPDIFF(MINUTE, created_at, NOW())) 
             FROM {$this->table_name} 
             WHERE status = 'pending'"
        );
        
        // Failed items count
        $metrics['failed_items'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'"
        );
        
        // Average processing time (last 24 hours)
        $metrics['avg_processing_time'] = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) 
             FROM {$this->table_name} 
             WHERE status = 'completed' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        
        return $metrics;
    }
}