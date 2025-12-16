<?php
namespace SMO_Social\Analytics;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * AnalyticsDataStream - Memory-efficient streaming processor for analytics data
 *
 * Implements generators and iterators for streaming data processing to reduce memory usage
 * with large analytics datasets.
 */
class AnalyticsDataStream {
    private $batch_size;
    private $max_memory_usage;
    private $current_memory_usage;
    private $data_source;
    private $wpdb;
    private $logger;

    /**
     * Constructor
     */
    public function __construct($batch_size = 1000, $max_memory_usage = 50) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage; // MB
        $this->current_memory_usage = 0;
        $this->logger = new AnalyticsStreamLogger();

        // Initialize database connection
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Set data source for streaming
     */
    public function set_data_source($source) {
        $this->data_source = $source;
    }

    /**
     * Stream analytics data in batches using generators
     */
    public function stream_analytics_data($date_range = 30, $platform = 'all') {
        $this->logger->log("Starting analytics data stream for date_range: {$date_range}, platform: {$platform}");

        $table_name = $this->wpdb->prefix . 'smo_analytics';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));

        $where_clause = "WHERE post_date >= %s";
        $query_params = array($date_from);

        if ($platform !== 'all') {
            $where_clause .= " AND platform = %s";
            $query_params[] = $platform;
        }

        // Use offset-based pagination for memory-efficient streaming
        $offset = 0;
        $has_more_data = true;

        while ($has_more_data) {
            $this->check_memory_usage();

            $query = "
                SELECT
                    platform,
                    data_type,
                    metric_name,
                    SUM(metric_value) as total_value,
                    AVG(metric_value) as avg_value,
                    COUNT(*) as record_count,
                    post_date
                FROM {$table_name}
                {$where_clause}
                GROUP BY platform, data_type, metric_name, DATE(post_date)
                ORDER BY post_date DESC
                LIMIT %d OFFSET %d
            ";

            array_push($query_params, $this->batch_size, $offset);

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $query_params)
            );

            if (empty($results)) {
                $has_more_data = false;
                break;
            }

            // Yield the batch of data using generator pattern
            yield $results;

            $offset += $this->batch_size;
            $this->current_memory_usage = memory_get_usage(true) / (1024 * 1024);

            // Reset query params for next iteration
            $query_params = array($date_from);
            if ($platform !== 'all') {
                $query_params[] = $platform;
            }
        }

        $this->logger->log("Completed analytics data stream. Processed {$offset} records in total.");
    }

    /**
     * Check memory usage and clean up if needed
     */
    private function check_memory_usage() {
        $this->current_memory_usage = memory_get_usage(true) / (1024 * 1024);

        if ($this->current_memory_usage > $this->max_memory_usage) {
            $this->logger->log("Memory usage warning: {$this->current_memory_usage}MB exceeds limit of {$this->max_memory_usage}MB");
            $this->cleanup_resources();
        }
    }

    /**
     * Cleanup resources to free memory
     */
    private function cleanup_resources() {
        gc_collect_cycles();
        $this->logger->log("Garbage collection completed. Current memory usage: " . memory_get_usage(true) / (1024 * 1024) . "MB");
    }

    /**
     * Process analytics data stream with memory-efficient structures
     */
    public function process_data_stream($data_stream) {
        $processed_data = array(
            'summary' => array(
                'total_posts' => 0,
                'total_reach' => 0,
                'total_engagement' => 0,
                'average_engagement_rate' => 0
            ),
            'platforms' => array(),
            'timeline' => array(),
            'top_posts' => array()
        );

        $platform_totals = array();
        $daily_totals = array();

        foreach ($data_stream as $batch) {
            $this->check_memory_usage();

            foreach ($batch as $result) {
                $platform = $result->platform;
                $date = date('Y-m-d', strtotime($result->post_date));

                // Initialize platform if not exists
                if (!isset($platform_totals[$platform])) {
                    $platform_totals[$platform] = array(
                        'posts' => 0, 'reach' => 0, 'engagement' => 0
                    );
                }

                // Initialize date if not exists
                if (!isset($daily_totals[$date])) {
                    $daily_totals[$date] = array();
                }

                // Process metric
                $metric_name = $result->metric_name;
                $value = intval($result->total_value);

                switch ($metric_name) {
                    case 'posts':
                    case 'published':
                        $platform_totals[$platform]['posts'] += $value;
                        $processed_data['summary']['total_posts'] += $value;
                        if (!isset($daily_totals[$date]['posts'])) {
                            $daily_totals[$date]['posts'] = 0;
                        }
                        $daily_totals[$date]['posts'] += $value;
                        break;

                    case 'reach':
                    case 'impressions':
                        $platform_totals[$platform]['reach'] += $value;
                        $processed_data['summary']['total_reach'] += $value;
                        if (!isset($daily_totals[$date]['reach'])) {
                            $daily_totals[$date]['reach'] = 0;
                        }
                        $daily_totals[$date]['reach'] += $value;
                        break;

                    case 'likes':
                    case 'shares':
                    case 'comments':
                    case 'clicks':
                        $platform_totals[$platform]['engagement'] += $value;
                        $processed_data['summary']['total_engagement'] += $value;
                        if (!isset($daily_totals[$date]['engagement'])) {
                            $daily_totals[$date]['engagement'] = 0;
                        }
                        $daily_totals[$date]['engagement'] += $value;
                        break;
                }
            }
        }

        $processed_data['platforms'] = $platform_totals;
        $processed_data['timeline'] = $daily_totals;

        // Calculate engagement rate
        if ($processed_data['summary']['total_reach'] > 0) {
            $processed_data['summary']['average_engagement_rate'] =
                ($processed_data['summary']['total_engagement'] / $processed_data['summary']['total_reach']) * 100;
        }

        return $processed_data;
    }

    /**
     * Get batch processing configuration
     */
    public function get_batch_config() {
        return array(
            'batch_size' => $this->batch_size,
            'max_memory_usage' => $this->max_memory_usage,
            'current_memory_usage' => $this->current_memory_usage
        );
    }

    /**
     * Set batch processing configuration
     */
    public function set_batch_config($batch_size, $max_memory_usage) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage;
    }
}

/**
 * Simple logger for analytics streaming
 */
class AnalyticsStreamLogger {
    public function log($message) {
        if (function_exists('error_log')) {
            error_log('SMO Analytics Stream: ' . $message);
        }
    }
}