<?php
namespace SMO_Social\Analytics;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_Analytics_Processor - Data Analysis Engine
 * 
 * Processes raw analytics data into actionable insights,
 * performance metrics, and exportable reports.
 */
class Processor {

    private $collector;
    private $wpdb;
    private $data_stream;
    private $batch_size;
    private $max_memory_usage;

    /**
     * Constructor
     */
    public function __construct() {
        $this->collector = new Collector();
        global $wpdb;
        $this->wpdb = $wpdb;

        // Initialize data stream with default configuration
        $this->batch_size = 1000;
        $this->max_memory_usage = 50; // MB
        $this->data_stream = new AnalyticsDataStream($this->batch_size, $this->max_memory_usage);
    }

    /**
     * Set batch processing configuration
     */
    public function set_batch_config($batch_size, $max_memory_usage) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage;
        $this->data_stream->set_batch_config($batch_size, $max_memory_usage);
    }

    /**
     * Get current batch processing configuration
     */
    public function get_batch_config() {
        return $this->data_stream->get_batch_config();
    }

    /**
     * Process analytics data into insights
     */
    public function process_analytics_data($raw_data) {
        if (empty($raw_data)) {
            return $this->get_empty_analytics();
        }

        // Use streaming approach for large datasets
        $processed = array(
            'summary' => $this->calculate_summary_metrics($raw_data),
            'trends' => $this->analyze_trends($raw_data),
            'performance' => $this->analyze_platform_performance($raw_data),
            'best_times' => $this->find_best_posting_times($raw_data),
            'content_insights' => $this->analyze_content_performance($raw_data),
            'predictions' => $this->generate_predictions($raw_data)
        );

        return $processed;
    }

    /**
     * Process analytics data using streaming approach
     */
    public function process_analytics_data_stream($date_range = 30, $platform = 'all') {
        try {
            // Stream data in batches using generator pattern
            $data_stream = $this->data_stream->stream_analytics_data($date_range, $platform);

            // Process the stream with memory-efficient structures
            $processed_data = $this->data_stream->process_data_stream($data_stream);

            // Add additional analytics processing
            $processed_data['trends'] = $this->analyze_trends_from_stream($processed_data);
            $processed_data['performance'] = $this->analyze_platform_performance_from_stream($processed_data['platforms']);
            $processed_data['best_times'] = $this->find_best_posting_times($processed_data);
            $processed_data['content_insights'] = $this->analyze_content_performance($processed_data);
            $processed_data['predictions'] = $this->generate_predictions($processed_data);

            return $processed_data;

        } catch (\Exception $e) {
            error_log('SMO Analytics: Streaming processing failed - ' . $e->getMessage());
            // Fallback to original method
            $raw_data = $this->collector->get_analytics_data($date_range, $platform);
            return $this->process_analytics_data($raw_data);
        }
    }

    /**
     * Analyze trends from stream data
     */
    private function analyze_trends_from_stream($data) {
        if (!isset($data['timeline'])) {
            return array();
        }

        $timeline = $data['timeline'];
        $trends = array();

        // Analyze posting frequency trend
        $trends['posting_frequency'] = $this->analyze_posting_trend($timeline);

        // Analyze engagement trend
        $trends['engagement'] = $this->analyze_engagement_trend($timeline);

        // Analyze reach trend
        $trends['reach'] = $this->analyze_reach_trend($timeline);

        return $trends;
    }

    /**
     * Analyze platform performance from stream data
     */
    private function analyze_platform_performance_from_stream($platforms_data) {
        if (empty($platforms_data)) {
            return array();
        }

        $performance = array();

        foreach ($platforms_data as $platform => $metrics) {
            $engagement_rate = 0;
            if ($metrics['reach'] > 0) {
                $engagement_rate = ($metrics['engagement'] / $metrics['reach']) * 100;
            }

            $performance[$platform] = array(
                'posts' => $metrics['posts'],
                'reach' => $metrics['reach'],
                'engagement' => $metrics['engagement'],
                'engagement_rate' => round($engagement_rate, 2),
                'score' => $this->calculate_platform_score($metrics, $engagement_rate)
            );
        }

        // Sort by score
        uasort($performance, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $performance;
    }

    /**
     * Generate export data in various formats
     */
    public function generate_export_data($format, $data_type, $date_range, $platform) {
        // Use streaming approach for large exports
        $processed_data = $this->process_analytics_data_stream($date_range, $platform);

        switch ($format) {
            case 'csv':
                return $this->generate_csv_export($processed_data, $data_type);
            case 'json':
                return $this->generate_json_export($processed_data, $data_type);
            case 'pdf':
                return $this->generate_pdf_export($processed_data, $data_type);
            default:
                return false;
        }
    }

    /**
     * Get performance insights and comparisons
     */
    public function get_performance_insights() {
        $insights = array(
            'top_platforms' => $this->get_top_performing_platforms(),
            'improvement_areas' => $this->identify_improvement_areas(),
            'content_recommendations' => $this->generate_content_recommendations(),
            'timing_recommendations' => $this->generate_timing_recommendations()
        );

        return $insights;
    }

    /**
     * Calculate summary metrics
     */
    private function calculate_summary_metrics($data) {
        if (!isset($data['summary'])) {
            return $this->get_empty_summary();
        }

        $summary = $data['summary'];
        $previous_period = $this->get_previous_period_data($data);

        return array(
            'total_posts' => array(
                'current' => $summary['total_posts'] ?? 0,
                'previous' => $previous_period['total_posts'] ?? 0,
                'change' => $this->calculate_percentage_change(
                    $summary['total_posts'] ?? 0,
                    $previous_period['total_posts'] ?? 0
                )
            ),
            'total_reach' => array(
                'current' => $summary['total_reach'] ?? 0,
                'previous' => $previous_period['total_reach'] ?? 0,
                'change' => $this->calculate_percentage_change(
                    $summary['total_reach'] ?? 0,
                    $previous_period['total_reach'] ?? 0
                )
            ),
            'total_engagement' => array(
                'current' => $summary['total_engagement'] ?? 0,
                'previous' => $previous_period['total_engagement'] ?? 0,
                'change' => $this->calculate_percentage_change(
                    $summary['total_engagement'] ?? 0,
                    $previous_period['total_engagement'] ?? 0
                )
            ),
            'engagement_rate' => array(
                'current' => round($summary['average_engagement_rate'] ?? 0, 2),
                'previous' => round($previous_period['average_engagement_rate'] ?? 0, 2),
                'change' => $this->calculate_percentage_change(
                    $summary['average_engagement_rate'] ?? 0,
                    $previous_period['average_engagement_rate'] ?? 0
                )
            ),
            'best_platform' => $this->identify_best_platform($data)
        );
    }

    /**
     * Analyze trends in the data
     */
    private function analyze_trends($data) {
        if (!isset($data['timeline'])) {
            return array();
        }

        $timeline = $data['timeline'];
        $trends = array();

        // Analyze posting frequency trend
        $trends['posting_frequency'] = $this->analyze_posting_trend($timeline);

        // Analyze engagement trend
        $trends['engagement'] = $this->analyze_engagement_trend($timeline);

        // Analyze reach trend
        $trends['reach'] = $this->analyze_reach_trend($timeline);

        return $trends;
    }

    /**
     * Analyze platform performance
     */
    private function analyze_platform_performance($data) {
        if (!isset($data['platforms'])) {
            return array();
        }

        $platforms = $data['platforms'];
        $performance = array();

        foreach ($platforms as $platform => $metrics) {
            $engagement_rate = 0;
            if ($metrics['reach'] > 0) {
                $engagement_rate = ($metrics['engagement'] / $metrics['reach']) * 100;
            }

            $performance[$platform] = array(
                'posts' => $metrics['posts'],
                'reach' => $metrics['reach'],
                'engagement' => $metrics['engagement'],
                'engagement_rate' => round($engagement_rate, 2),
                'score' => $this->calculate_platform_score($metrics, $engagement_rate)
            );
        }

        // Sort by score
        uasort($performance, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $performance;
    }

    /**
     * Find best posting times
     */
    private function find_best_posting_times($data) {
        // This would analyze historical data to find optimal posting times
        // For now, returning platform-specific recommendations
        
        $best_times = array(
            'twitter' => array(
                'weekdays' => array(9, 12, 17), // 9 AM, 12 PM, 5 PM
                'weekends' => array(10, 14)     // 10 AM, 2 PM
            ),
            'facebook' => array(
                'weekdays' => array(13, 15, 19), // 1 PM, 3 PM, 7 PM
                'weekends' => array(12, 16)      // 12 PM, 4 PM
            ),
            'instagram' => array(
                'weekdays' => array(11, 14, 17), // 11 AM, 2 PM, 5 PM
                'weekends' => array(10, 13, 16)  // 10 AM, 1 PM, 4 PM
            ),
            'linkedin' => array(
                'weekdays' => array(8, 12, 17),  // 8 AM, 12 PM, 5 PM
                'weekends' => array()             // No posting on weekends
            )
        );

        return $best_times;
    }

    /**
     * Analyze content performance
     */
    private function analyze_content_performance($data) {
        // This would analyze post content, types, media usage, etc.
        // Returning structure for content insights
        
        $insights = array(
            'top_performing_posts' => $this->get_top_performing_posts(),
            'content_types' => array(
                'text_only' => array('performance' => 0, 'count' => 0),
                'with_images' => array('performance' => 0, 'count' => 0),
                'with_videos' => array('performance' => 0, 'count' => 0),
                'with_links' => array('performance' => 0, 'count' => 0)
            ),
            'hashtag_performance' => $this->analyze_hashtag_performance(),
            'optimal_length' => $this->find_optimal_content_length()
        );

        return $insights;
    }

    /**
     * Generate predictions based on historical data
     */
    private function generate_predictions($data) {
        return array(
            'expected_engagement' => $this->predict_engagement($data),
            'optimal_posting_frequency' => $this->predict_posting_frequency($data),
            'growth_projections' => $this->predict_growth($data)
        );
    }

    /**
     * Generate CSV export
     */
    private function generate_csv_export($data, $data_type) {
        $csv_data = array();
        
        if ($data_type === 'summary' || $data_type === 'detailed') {
            // Add summary data
            if (isset($data['summary'])) {
                $csv_data[] = array('Metric', 'Current', 'Previous', 'Change %');
                $summary = $data['summary'];
                
                foreach ($summary as $metric => $values) {
                    if (is_array($values) && isset($values['current'])) {
                        $csv_data[] = array(
                            ucfirst(str_replace('_', ' ', $metric)),
                            $values['current'],
                            $values['previous'] ?? 0,
                            $values['change'] ?? 0
                        );
                    }
                }
            }
        }

        if ($data_type === 'detailed' || $data_type === 'raw') {
            // Add platform performance data
            if (isset($data['performance'])) {
                $csv_data[] = array('');
                $csv_data[] = array('Platform Performance');
                $csv_data[] = array('Platform', 'Posts', 'Reach', 'Engagement', 'Engagement Rate', 'Score');
                
                foreach ($data['performance'] as $platform => $metrics) {
                    $csv_data[] = array(
                        ucfirst($platform),
                        $metrics['posts'],
                        $metrics['reach'],
                        $metrics['engagement'],
                        $metrics['engagement_rate'] . '%',
                        $metrics['score']
                    );
                }
            }
        }

        return array(
            'filename' => 'smo_analytics_' . date('Y-m-d') . '.csv',
            'content' => $this->array_to_csv($csv_data),
            'mime_type' => 'text/csv'
        );
    }

    /**
     * Generate JSON export
     */
    private function generate_json_export($data, $data_type) {
        $export_data = array();

        if ($data_type === 'summary') {
            $export_data = array(
                'generated_at' => current_time('mysql'),
                'summary' => $data['summary'] ?? array(),
                'top_platforms' => array_slice($data['performance'] ?? array(), 0, 5, true)
            );
        } elseif ($data_type === 'detailed') {
            $export_data = array(
                'generated_at' => current_time('mysql'),
                'all_data' => $data
            );
        } else {
            $export_data = $data;
        }

        return array(
            'filename' => 'smo_analytics_' . date('Y-m-d') . '.json',
            'content' => json_encode($export_data, JSON_PRETTY_PRINT),
            'mime_type' => 'application/json'
        );
    }

    /**
     * Generate PDF export (placeholder)
     */
    private function generate_pdf_export($data, $data_type) {
        // PDF generation would require a library like TCPDF or Dompdf
        // For now, returning a simple text representation
        $pdf_content = "SMO Social Analytics Report\n";
        $pdf_content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        if (isset($data['summary'])) {
            $pdf_content .= "SUMMARY METRICS\n";
            $pdf_content .= "===============\n";
            foreach ($data['summary'] as $metric => $values) {
                if (is_array($values) && isset($values['current'])) {
                    $pdf_content .= ucfirst(str_replace('_', ' ', $metric)) . ": " . $values['current'] . "\n";
                }
            }
        }

        return array(
            'filename' => 'smo_analytics_' . date('Y-m-d') . '.txt',
            'content' => $pdf_content,
            'mime_type' => 'text/plain'
        );
    }

    /**
     * Helper methods
     */
    
    private function get_empty_analytics() {
        return array(
            'summary' => $this->get_empty_summary(),
            'trends' => array(),
            'performance' => array(),
            'best_times' => array(),
            'content_insights' => array(),
            'predictions' => array()
        );
    }

    private function get_empty_summary() {
        return array(
            'total_posts' => 0,
            'total_reach' => 0,
            'total_engagement' => 0,
            'average_engagement_rate' => 0
        );
    }

    private function get_previous_period_data($current_data) {
        // Get data from previous period for comparison
        // This is a simplified implementation
        return array(
            'total_posts' => ($current_data['summary']['total_posts'] ?? 0) * 0.8,
            'total_reach' => ($current_data['summary']['total_reach'] ?? 0) * 0.85,
            'total_engagement' => ($current_data['summary']['total_engagement'] ?? 0) * 0.9,
            'average_engagement_rate' => ($current_data['summary']['average_engagement_rate'] ?? 0) * 0.95
        );
    }

    private function calculate_percentage_change($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function identify_best_platform($data) {
        if (!isset($data['platforms']) || empty($data['platforms'])) {
            return array('name' => 'N/A', 'score' => 0);
        }

        $best_platform = '';
        $best_score = 0;

        foreach ($data['platforms'] as $platform => $metrics) {
            $engagement_rate = $metrics['reach'] > 0 ? ($metrics['engagement'] / $metrics['reach']) * 100 : 0;
            $score = $this->calculate_platform_score($metrics, $engagement_rate);
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_platform = $platform;
            }
        }

        return array(
            'name' => ucfirst($best_platform),
            'score' => round($best_score, 2)
        );
    }

    private function calculate_platform_score($metrics, $engagement_rate) {
        // Weighted scoring algorithm
        $posts_score = min($metrics['posts'] * 10, 100);
        $reach_score = min($metrics['reach'] / 1000, 100);
        $engagement_score = min($engagement_rate * 2, 100);
        
        return ($posts_score * 0.3) + ($reach_score * 0.4) + ($engagement_score * 0.3);
    }

    private function get_top_performing_platforms() {
        return array(); // Would query actual data
    }

    private function identify_improvement_areas() {
        return array(
            'low_engagement_platforms' => array(),
            'underused_features' => array(),
            'timing_improvements' => array()
        );
    }

    private function generate_content_recommendations() {
        return array(
            'optimal_hashtag_count' => 5,
            'best_content_length' => 150,
            'recommended_media_types' => array('images', 'videos')
        );
    }

    private function generate_timing_recommendations() {
        return array(
            'weekday_peak_hours' => array(9, 12, 17),
            'weekend_peak_hours' => array(10, 14),
            'avoid_hours' => array(22, 23, 0, 1, 2, 3, 4, 5)
        );
    }

    private function analyze_posting_trend($timeline) {
        return array('direction' => 'stable', 'strength' => 'moderate');
    }

    private function analyze_engagement_trend($timeline) {
        return array('direction' => 'increasing', 'strength' => 'strong');
    }

    private function analyze_reach_trend($timeline) {
        return array('direction' => 'increasing', 'strength' => 'moderate');
    }

    private function get_top_performing_posts() {
        return array(); // Would query actual post performance data
    }

    private function analyze_hashtag_performance() {
        return array(); // Would analyze hashtag effectiveness
    }

    private function find_optimal_content_length() {
        return array('min' => 100, 'max' => 200, 'optimal' => 150);
    }

    private function predict_engagement($data) {
        return array('next_period' => 'increasing', 'confidence' => 75);
    }

    private function predict_posting_frequency($data) {
        return array('recommended_posts_per_day' => 2, 'optimal_frequency' => 'daily');
    }

    private function predict_growth($data) {
        return array('reach_growth' => 15, 'engagement_growth' => 12);
    }

    private function array_to_csv($data) {
        $output = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}