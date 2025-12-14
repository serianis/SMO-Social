<?php
namespace SMO_Social\Platforms;

/**
 * Advanced Rate Limit Handler with Platform-Specific Intelligence
 * Manages rate limiting across all platforms with smart queuing and optimization
 */
class RateLimitHandler {
    private $platform_slug;
    private $rate_limits;
    private $request_history;
    private $current_window_start;
    private $requests_in_window;
    private $backoff_strategy;

    public function __construct($platform_slug) {
        $this->platform_slug = $platform_slug;
        $this->rate_limits = $this->load_platform_rate_limits();
        $this->request_history = $this->load_request_history();
        $this->current_window_start = time();
        $this->requests_in_window = 0;
        $this->backoff_strategy = $this->initialize_backoff_strategy();
    }

    /**
     * Load platform-specific rate limits and configuration
     */
    private function load_platform_rate_limits() {
        $platform_configs = [
            'twitter' => [
                'requests_per_window' => 300,
                'window_size' => 900, // 15 minutes
                'tier' => 'standard',
                'burst_allowance' => 10,
                'priority_weights' => [
                    'posting' => 3,
                    'analytics' => 1,
                    'media_upload' => 5
                ]
            ],
            'facebook' => [
                'requests_per_window' => 200,
                'window_size' => 3600, // 1 hour
                'tier' => 'standard',
                'burst_allowance' => 5,
                'priority_weights' => [
                    'posting' => 2,
                    'analytics' => 1,
                    'media_upload' => 3
                ]
            ],
            'instagram' => [
                'requests_per_window' => 200,
                'window_size' => 3600,
                'tier' => 'standard',
                'burst_allowance' => 5,
                'priority_weights' => [
                    'posting' => 3,
                    'analytics' => 1,
                    'media_upload' => 4
                ]
            ],
            'linkedin' => [
                'requests_per_window' => 100,
                'window_size' => 3600,
                'tier' => 'professional',
                'burst_allowance' => 3,
                'priority_weights' => [
                    'posting' => 2,
                    'analytics' => 1,
                    'media_upload' => 2
                ]
            ],
            'reddit' => [
                'requests_per_window' => 60,
                'window_size' => 3600,
                'tier' => 'community',
                'burst_allowance' => 2,
                'priority_weights' => [
                    'posting' => 1,
                    'analytics' => 2,
                    'commenting' => 1
                ]
            ],
            'mastodon' => [
                'requests_per_window' => 300,
                'window_size' => 300, // 5 minutes
                'tier' => 'open',
                'burst_allowance' => 10,
                'priority_weights' => [
                    'posting' => 1,
                    'analytics' => 1,
                    'media_upload' => 2
                ]
            ],
            'tiktok' => [
                'requests_per_window' => 100,
                'window_size' => 3600,
                'tier' => 'strict',
                'burst_allowance' => 3,
                'priority_weights' => [
                    'posting' => 3,
                    'analytics' => 1,
                    'media_upload' => 4
                ]
            ]
        ];

        // Default configuration for unknown platforms
        $default_config = [
            'requests_per_window' => 100,
            'window_size' => 3600,
            'tier' => 'unknown',
            'burst_allowance' => 3,
            'priority_weights' => [
                'posting' => 2,
                'analytics' => 1,
                'media_upload' => 2
            ]
        ];

        return $platform_configs[$this->platform_slug] ?? $default_config;
    }

    /**
     * Load request history from storage
     */
    private function load_request_history() {
        return get_option("smo_social_{$this->platform_slug}_request_history", []);
    }

    /**
     * Initialize intelligent backoff strategy
     */
    private function initialize_backoff_strategy() {
        return [
            'exponential_base' => 2,
            'max_backoff' => 3600, // 1 hour
            'jitter_factor' => 0.1,
            'recovery_multiplier' => 0.5
        ];
    }

    /**
     * Check if request can be made within rate limits
     */
    public function can_make_request($operation_type = 'default', $priority = 'normal') {
        $this->update_window_state();
        
        $current_requests = $this->get_current_request_count();
        $max_requests = $this->calculate_max_requests($operation_type, $priority);
        
        if ($current_requests >= $max_requests) {
            $wait_time = $this->calculate_wait_time();
            return [
                'allowed' => false,
                'wait_time' => $wait_time,
                'reason' => 'rate_limit_exceeded',
                'current_requests' => $current_requests,
                'max_requests' => $max_requests
            ];
        }

        return [
            'allowed' => true,
            'current_requests' => $current_requests,
            'remaining_requests' => $max_requests - $current_requests
        ];
    }

    /**
     * Record successful request
     */
    public function record_success($operation_type = 'default') {
        $request_record = [
            'timestamp' => time(),
            'type' => $operation_type,
            'success' => true,
            'response_time' => 0 // Would be measured in production
        ];

        $this->add_request_to_history($request_record);
        $this->clear_error_state();
        
        // Update platform health score
        $this->update_platform_health_score(1.0);
    }

    /**
     * Record failed request with intelligent backoff
     */
    public function record_failure($operation_type = 'default', $error_type = 'unknown') {
        $request_record = [
            'timestamp' => time(),
            'type' => $operation_type,
            'success' => false,
            'error_type' => $error_type
        ];

        $this->add_request_to_history($request_record);
        
        // Determine if this is a rate limit error
        $is_rate_limit_error = in_array($error_type, [
            'rate_limit', 'too_many_requests', 'quota_exceeded'
        ]);

        if ($is_rate_limit_error) {
            $this->handle_rate_limit_error();
        } else {
            $this->handle_general_error($error_type);
        }
    }

    /**
     * Handle rate limit specific errors
     */
    private function handle_rate_limit_error() {
        $current_backoff = get_option("smo_social_{$this->platform_slug}_backoff_time", 0);
        
        // Exponential backoff with jitter
        $new_backoff = $this->calculate_exponential_backoff($current_backoff);
        
        update_option("smo_social_{$this->platform_slug}_backoff_time", $new_backoff, false);
        update_option("smo_social_{$this->platform_slug}_last_rate_limit", time(), false);
        
        // Log the rate limit hit
        error_log("SMO Social - Rate limit hit for {$this->platform_slug}, backoff: {$new_backoff}s");
    }

    /**
     * Handle general API errors
     */
    private function handle_general_error($error_type) {
        $error_scores = [
            'unauthorized' => 0.8,
            'forbidden' => 0.9,
            'server_error' => 0.7,
            'timeout' => 0.6,
            'network_error' => 0.5
        ];
        
        $health_impact = $error_scores[$error_type] ?? 0.8;
        $this->update_platform_health_score($health_impact);
        
        // Short backoff for non-rate-limit errors
        $short_backoff = min(60, $this->backoff_strategy['max_backoff'] / 10);
        update_option("smo_social_{$this->platform_slug}_backoff_time", $short_backoff, false);
    }

    /**
     * Calculate exponential backoff time
     */
    private function calculate_exponential_backoff($current_backoff) {
        if ($current_backoff === 0) {
            return 60; // Start with 1 minute
        }
        
        $base_backoff = $current_backoff * $this->backoff_strategy['exponential_base'];
        $max_backoff = $this->backoff_strategy['max_backoff'];
        
        $backoff = min($base_backoff, $max_backoff);
        
        // Add jitter to prevent thundering herd
        $jitter = $backoff * $this->backoff_strategy['jitter_factor'] * (rand(1, 100) / 100);
        
        return intval($backoff + $jitter);
    }

    /**
     * Update platform health score based on recent performance
     */
    private function update_platform_health_score($performance_factor) {
        $current_score = get_option("smo_social_{$this->platform_slug}_health_score", 100);
        $new_score = intval($current_score * $performance_factor);
        
        // Gradually recover score over time
        $recovery_rate = $this->backoff_strategy['recovery_multiplier'];
        $time_since_last_request = time() - $this->get_last_request_time();
        
        if ($time_since_last_request > 300) { // 5 minutes
            $new_score = min(100, $new_score + ($recovery_rate * ($time_since_last_request / 60)));
        }
        
        update_option("smo_social_{$this->platform_slug}_health_score", $new_score, false);
    }

    /**
     * Get current request count in time window
     */
    private function get_current_request_count() {
        $window_start = time() - $this->rate_limits['window_size'];
        
        $recent_requests = array_filter($this->request_history, function($request) use ($window_start) {
            return $request['timestamp'] >= $window_start;
        });
        
        return count($recent_requests);
    }

    /**
     * Calculate maximum allowed requests considering priority and operation type
     */
    private function calculate_max_requests($operation_type, $priority) {
        $base_limit = $this->rate_limits['requests_per_window'];
        $burst_allowance = $this->rate_limits['burst_allowance'];
        
        // Apply priority adjustments
        $priority_multipliers = [
            'critical' => 2.0,
            'high' => 1.5,
            'normal' => 1.0,
            'low' => 0.7
        ];
        
        $priority_multiplier = $priority_multipliers[$priority] ?? 1.0;
        
        // Apply operation type weights
        $operation_weight = $this->rate_limits['priority_weights'][$operation_type] ?? 1.0;
        $adjusted_limit = intval($base_limit * $priority_multiplier / $operation_weight);
        
        // Add burst allowance for high-priority operations
        if (in_array($priority, ['critical', 'high'])) {
            $adjusted_limit += $burst_allowance;
        }
        
        return min($adjusted_limit, $base_limit + $burst_allowance);
    }

    /**
     * Calculate wait time until next request is allowed
     */
    private function calculate_wait_time() {
        $window_start = time() - $this->rate_limits['window_size'];
        
        // Find oldest request in current window
        $recent_requests = array_filter($this->request_history, function($request) use ($window_start) {
            return $request['timestamp'] >= $window_start;
        });
        
        if (empty($recent_requests)) {
            return 0;
        }
        
        // Sort by timestamp (oldest first)
        usort($recent_requests, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        $oldest_request = $recent_requests[0];
        $window_end = $oldest_request['timestamp'] + $this->rate_limits['window_size'];
        
        return max(0, $window_end - time());
    }

    /**
     * Get intelligent queue position for delayed operations
     */
    public function get_queue_position($operation_type, $priority = 'normal') {
        $can_proceed = $this->can_make_request($operation_type, $priority);
        
        if ($can_proceed['allowed']) {
            return [
                'position' => 0,
                'wait_time' => 0,
                'can_proceed' => true
            ];
        }
        
        // Estimate queue position based on historical patterns
        $estimated_queue_size = $this->estimate_queue_size($operation_type, $priority);
        $avg_processing_time = $this->get_average_processing_time($operation_type);
        
        return [
            'position' => $estimated_queue_size,
            'wait_time' => $can_proceed['wait_time'],
            'estimated_total_wait' => $estimated_queue_size * $avg_processing_time + $can_proceed['wait_time'],
            'can_proceed' => false
        ];
    }

    /**
     * Estimate current queue size for operation type
     */
    private function estimate_queue_size($operation_type, $priority) {
        // This would integrate with the actual queue system
        // For now, return a basic estimation based on recent activity
        $recent_activity = $this->get_recent_activity_count($operation_type);
        
        $priority_adjustments = [
            'critical' => 0.8,
            'high' => 0.9,
            'normal' => 1.0,
            'low' => 1.2
        ];
        
        $priority_multiplier = $priority_adjustments[$priority] ?? 1.0;
        
        return intval($recent_activity * $priority_multiplier);
    }

    /**
     * Get average processing time for operation type
     */
    private function get_average_processing_time($operation_type) {
        $operation_times = [
            'posting' => 2,
            'analytics' => 5,
            'media_upload' => 10,
            'commenting' => 1,
            'default' => 3
        ];
        
        return $operation_times[$operation_type] ?? $operation_times['default'];
    }

    /**
     * Get recent activity count for operation type
     */
    private function get_recent_activity_count($operation_type, $minutes = 10) {
        $window_start = time() - ($minutes * 60);
        
        $recent_requests = array_filter($this->request_history, function($request) use ($window_start, $operation_type) {
            return $request['timestamp'] >= $window_start && 
                   ($request['type'] === $operation_type || $operation_type === 'all');
        });
        
        return count($recent_requests);
    }

    /**
     * Update time window state
     */
    private function update_window_state() {
        $current_time = time();
        
        if ($current_time - $this->current_window_start >= $this->rate_limits['window_size']) {
            $this->current_window_start = $current_time;
            $this->requests_in_window = 0;
            $this->cleanup_old_requests();
        }
    }

    /**
     * Add request to history
     */
    private function add_request_to_history($request_record) {
        $this->request_history[] = $request_record;
        
        // Keep only recent requests (last 24 hours)
        $cutoff_time = time() - 86400;
        $this->request_history = array_filter($this->request_history, function($request) use ($cutoff_time) {
            return $request['timestamp'] >= $cutoff_time;
        });
        
        // Save to database
        update_option("smo_social_{$this->platform_slug}_request_history", $this->request_history, false);
    }

    /**
     * Clean up old requests from history
     */
    private function cleanup_old_requests() {
        $cutoff_time = time() - ($this->rate_limits['window_size'] * 2);
        
        $this->request_history = array_filter($this->request_history, function($request) use ($cutoff_time) {
            return $request['timestamp'] >= $cutoff_time;
        });
    }

    /**
     * Clear error state after successful request
     */
    private function clear_error_state() {
        delete_option("smo_social_{$this->platform_slug}_backoff_time");
    }

    /**
     * Get last request timestamp
     */
    private function get_last_request_time() {
        if (empty($this->request_history)) {
            return 0;
        }
        
        $last_request = end($this->request_history);
        return $last_request['timestamp'];
    }

    /**
     * Get comprehensive rate limit status
     */
    public function get_rate_limit_status() {
        $this->update_window_state();
        
        return [
            'platform' => $this->platform_slug,
            'current_requests' => $this->get_current_request_count(),
            'max_requests' => $this->rate_limits['requests_per_window'],
            'window_size' => $this->rate_limits['window_size'],
            'window_remaining' => $this->rate_limits['window_size'] - (time() - $this->current_window_start),
            'burst_allowance' => $this->rate_limits['burst_allowance'],
            'tier' => $this->rate_limits['tier'],
            'backoff_time' => get_option("smo_social_{$this->platform_slug}_backoff_time", 0),
            'health_score' => get_option("smo_social_{$this->platform_slug}_health_score", 100),
            'last_rate_limit' => get_option("smo_social_{$this->platform_slug}_last_rate_limit", 0)
        ];
    }

    /**
     * Reset rate limit state (for testing or manual recovery)
     */
    public function reset_rate_limits() {
        $this->request_history = [];
        $this->current_window_start = time();
        $this->requests_in_window = 0;
        
        delete_option("smo_social_{$this->platform_slug}_backoff_time");
        delete_option("smo_social_{$this->platform_slug}_last_rate_limit");
        delete_option("smo_social_{$this->platform_slug}_health_score");
        
        update_option("smo_social_{$this->platform_slug}_request_history", [], false);
    }
}