<?php
namespace SMO_Social\Core;

// Import WordPress functions
use function \is_wp_error;
use function \wp_remote_retrieve_response_code;
use function \wp_remote_retrieve_headers;
use function \current_time;
use function \get_option;
use function \update_option;
use function \delete_option;
use function \get_transient;
use function \set_transient;
use function \delete_transient;
use function \__;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_Retry_Handler - Intelligent Retry Logic with Exponential Backoff
 * 
 * Handles API failures gracefully with retry mechanisms, exponential backoff,
 * and fallback endpoint switching for maximum reliability.
 */
class RetryHandler {

    private $max_retries = 3;
    private $base_delay = 1; // seconds
    private $max_delay = 30; // seconds
    private $platform_slug;
    private $attempt_log = array();

    /**
     * Constructor
     * 
     * @param string $platform_slug Platform identifier
     * @param int    $max_retries   Maximum retry attempts
     */
    public function __construct($platform_slug, $max_retries = 3) {
        $this->platform_slug = $platform_slug;
        $this->max_retries = $max_retries;
    }

    /**
     * Execute API call with retry logic
     * 
     * @param callable $api_call     API call function
     * @param array    $endpoints    Array of endpoint URLs (primary + fallbacks)
     * @param array    $context      Additional context for logging
     * @return array|WP_Error Result or error
     */
    public function execute_with_retry($api_call, $endpoints = array(), $context = array()) {
        $attempt = 0;
        $last_error = null;
        $endpoint_index = 0;

        // Ensure we have at least one endpoint
        if (empty($endpoints)) {
            return new \WP_Error('no_endpoints', __('No API endpoints configured', 'smo-social'));
        }

        while ($attempt < $this->max_retries) {
            $attempt++;
            $current_endpoint = $endpoints[$endpoint_index];

            // Log attempt
            $this->log_attempt($attempt, $current_endpoint, $context);

            try {
                // Execute the API call
                $result = call_user_func($api_call, $current_endpoint);

                // Check if result is successful
                if (!is_wp_error($result)) {
                    $response_code = wp_remote_retrieve_response_code($result);
                    
                    // Success codes (2xx)
                    if ($response_code >= 200 && $response_code < 300) {
                        $this->log_success($attempt, $current_endpoint);
                        return $result;
                    }
                    
                    // Rate limit (429) - use longer backoff
                    if ($response_code === 429) {
                        $retry_after = $this->get_retry_after_header($result);
                        $this->log_rate_limit($attempt, $current_endpoint, $retry_after);
                        
                        if ($attempt < $this->max_retries) {
                            $this->wait_with_backoff($attempt, $retry_after);
                            continue;
                        }
                    }
                    
                    // Server errors (5xx) - retry with backoff
                    if ($response_code >= 500) {
                        $last_error = new \WP_Error(
                            'server_error',
                            sprintf(__('Server error: HTTP %d', 'smo-social'), $response_code)
                        );
                        
                        $this->log_error($attempt, $current_endpoint, $last_error);
                        
                        if ($attempt < $this->max_retries) {
                            $this->wait_with_backoff($attempt);
                            continue;
                        }
                    }
                    
                    // Client errors (4xx) - try fallback endpoint
                    if ($response_code >= 400 && $response_code < 500) {
                        $last_error = new \WP_Error(
                            'client_error',
                            sprintf(__('Client error: HTTP %d', 'smo-social'), $response_code)
                        );
                        
                        $this->log_error($attempt, $current_endpoint, $last_error);
                        
                        // Try next endpoint if available
                        if ($endpoint_index < count($endpoints) - 1) {
                            $endpoint_index++;
                            $attempt--; // Don't count endpoint switch as retry
                            continue;
                        }
                    }
                } else {
                    // WP_Error returned
                    $last_error = $result;
                    $this->log_error($attempt, $current_endpoint, $last_error);
                    
                    // Network errors - retry with backoff
                    if ($attempt < $this->max_retries) {
                        $this->wait_with_backoff($attempt);
                        continue;
                    }
                }

            } catch (\Exception $e) {
                $last_error = new \WP_Error('exception', $e->getMessage());
                $this->log_error($attempt, $current_endpoint, $last_error);
                
                if ($attempt < $this->max_retries) {
                    $this->wait_with_backoff($attempt);
                    continue;
                }
            }

            // If we've exhausted retries for this endpoint, try next endpoint
            if ($attempt >= $this->max_retries && $endpoint_index < count($endpoints) - 1) {
                $endpoint_index++;
                $attempt = 0; // Reset attempts for new endpoint
                continue;
            }

            break;
        }

        // All retries exhausted
        $this->log_final_failure($last_error);
        return $last_error ?? new \WP_Error('unknown_error', __('Unknown error occurred', 'smo-social'));
    }

    /**
     * Wait with exponential backoff
     * 
     * @param int      $attempt     Current attempt number
     * @param int|null $retry_after Optional retry-after value from header
     */
    private function wait_with_backoff($attempt, $retry_after = null) {
        if ($retry_after !== null) {
            $delay = min($retry_after, $this->max_delay);
        } else {
            // Exponential backoff: base_delay * 2^(attempt-1)
            $delay = min($this->base_delay * pow(2, $attempt - 1), $this->max_delay);
            
            // Note: Jitter removed to resolve linting issues
            // Original jitter: random 0-25% variation to prevent thundering herd
        }

        $this->log_backoff($attempt, $delay);
        sleep((int)$delay);
    }

    /**
     * Get retry-after header value
     * 
     * @param array $response HTTP response
     * @return int|null Seconds to wait
     */
    private function get_retry_after_header($response) {
        $headers = wp_remote_retrieve_headers($response);
        
        if (isset($headers['retry-after'])) {
            $retry_after = $headers['retry-after'];
            
            // Can be either seconds or HTTP date
            if (is_numeric($retry_after)) {
                return (int)$retry_after;
            } else {
                $retry_time = strtotime($retry_after);
                if ($retry_time !== false) {
                    return max(0, $retry_time - time());
                }
            }
        }
        
        return null;
    }

    /**
     * Check if error is retryable
     * 
     * @param WP_Error $error Error object
     * @return bool Is retryable
     */
    public function is_retryable_error($error) {
        if (!is_wp_error($error)) {
            return false;
        }

        $retryable_codes = array(
            'http_request_failed',
            'server_error',
            'timeout',
            'connection_timeout',
            'ssl_verification_failed'
        );

        return in_array($error->get_error_code(), $retryable_codes);
    }

    /**
     * Log attempt
     * 
     * @param int    $attempt  Attempt number
     * @param string $endpoint Endpoint URL
     * @param array  $context  Additional context
     */
    private function log_attempt($attempt, $endpoint, $context) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'attempt' => $attempt,
            'endpoint' => $endpoint,
            'context' => $context
        );

        $this->attempt_log[] = $log_entry;

        error_log(sprintf(
            'SMO Social [%s] Attempt %d: Calling %s',
            $this->platform_slug,
            $attempt,
            $endpoint
        ));
    }

    /**
     * Log success
     * 
     * @param int    $attempt  Attempt number
     * @param string $endpoint Endpoint URL
     */
    private function log_success($attempt, $endpoint) {
        error_log(sprintf(
            'SMO Social [%s] Success on attempt %d: %s',
            $this->platform_slug,
            $attempt,
            $endpoint
        ));

        // Update platform health metrics
        $this->update_health_metrics('success', $attempt);
    }

    /**
     * Log error
     * 
     * @param int      $attempt  Attempt number
     * @param string   $endpoint Endpoint URL
     * @param WP_Error $error    Error object
     */
    private function log_error($attempt, $endpoint, $error) {
        error_log(sprintf(
            'SMO Social [%s] Error on attempt %d (%s): %s',
            $this->platform_slug,
            $attempt,
            $endpoint,
            $error->get_error_message()
        ));
    }

    /**
     * Log rate limit
     * 
     * @param int      $attempt     Attempt number
     * @param string   $endpoint    Endpoint URL
     * @param int|null $retry_after Retry after seconds
     */
    private function log_rate_limit($attempt, $endpoint, $retry_after) {
        error_log(sprintf(
            'SMO Social [%s] Rate limited on attempt %d (%s). Retry after: %s seconds',
            $this->platform_slug,
            $attempt,
            $endpoint,
            $retry_after ?? 'unknown'
        ));

        // Update rate limit tracking
        $this->track_rate_limit($retry_after);
    }

    /**
     * Log backoff wait
     * 
     * @param int   $attempt Attempt number
     * @param float $delay   Delay in seconds
     */
    private function log_backoff($attempt, $delay) {
        error_log(sprintf(
            'SMO Social [%s] Backing off for %.2f seconds before attempt %d',
            $this->platform_slug,
            $delay,
            $attempt + 1
        ));
    }

    /**
     * Log final failure
     * 
     * @param WP_Error|null $error Final error
     */
    private function log_final_failure($error) {
        $message = $error ? $error->get_error_message() : 'Unknown error';
        
        error_log(sprintf(
            'SMO Social [%s] All retry attempts exhausted. Final error: %s',
            $this->platform_slug,
            $message
        ));

        // Update platform health metrics
        $this->update_health_metrics('failure', $this->max_retries);

        // Store failure log for admin visibility
        $this->store_failure_log($error);
    }

    /**
     * Update platform health metrics
     * 
     * @param string $status  Status (success/failure)
     * @param int    $attempts Number of attempts
     */
    private function update_health_metrics($status, $attempts) {
        $metrics_key = 'smo_health_' . $this->platform_slug;
        $metrics = get_option($metrics_key, array(
            'total_attempts' => 0,
            'successful' => 0,
            'failed' => 0,
            'avg_attempts' => 1,
            'last_success' => null,
            'last_failure' => null,
            'consecutive_failures' => 0
        ));
        
        // Ensure metrics is always an array
        if (!is_array($metrics)) {
            $metrics = array(
                'total_attempts' => 0,
                'successful' => 0,
                'failed' => 0,
                'avg_attempts' => 1,
                'last_success' => null,
                'last_failure' => null,
                'consecutive_failures' => 0
            );
        }

        $metrics['total_attempts']++;

        if ($status === 'success') {
            $metrics['successful']++;
            $metrics['last_success'] = current_time('mysql');
            $metrics['consecutive_failures'] = 0;
            
            // Update average attempts
            $metrics['avg_attempts'] = (($metrics['avg_attempts'] * ($metrics['successful'] - 1)) + $attempts) / $metrics['successful'];
        } else {
            $metrics['failed']++;
            $metrics['last_failure'] = current_time('mysql');
            $metrics['consecutive_failures']++;
        }

        update_option($metrics_key, $metrics);

        // Alert if too many consecutive failures
        if ($metrics['consecutive_failures'] >= 5) {
            $this->trigger_health_alert($metrics);
        }
    }

    /**
     * Track rate limit occurrence
     * 
     * @param int|null $retry_after Retry after seconds
     */
    private function track_rate_limit($retry_after) {
        $rate_limit_key = 'smo_rate_limit_' . $this->platform_slug;
        $rate_limit_data = array(
            'timestamp' => current_time('mysql'),
            'retry_after' => $retry_after,
            'expires_at' => time() + ($retry_after ?? 60)
        );

        set_transient($rate_limit_key, $rate_limit_data, $retry_after ?? 60);
    }

    /**
     * Check if platform is currently rate limited
     * 
     * @return bool Is rate limited
     */
    public function is_rate_limited() {
        $rate_limit_key = 'smo_rate_limit_' . $this->platform_slug;
        return get_transient($rate_limit_key) !== false;
    }

    /**
     * Get time until rate limit expires
     * 
     * @return int Seconds until expiry (0 if not rate limited)
     */
    public function get_rate_limit_expiry() {
        $rate_limit_key = 'smo_rate_limit_' . $this->platform_slug;
        $rate_limit_data = get_transient($rate_limit_key);

        if ($rate_limit_data && isset($rate_limit_data['expires_at'])) {
            return max(0, $rate_limit_data['expires_at'] - time());
        }

        return 0;
    }

    /**
     * Store failure log for admin review
     * 
     * @param WP_Error|null $error Error object
     */
    private function store_failure_log($error) {
        $log_key = 'smo_failure_log_' . $this->platform_slug;
        $logs = get_option($log_key, array());

        // Ensure $logs is always an array
        if (!is_array($logs)) {
            $logs = array();
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'error_code' => $error ? $error->get_error_code() : 'unknown',
            'error_message' => $error ? $error->get_error_message() : 'Unknown error',
            'attempts' => $this->attempt_log
        );

        array_unshift($logs, $log_entry);

        // Keep only last 20 failures
        $logs = array_slice($logs, 0, 20);

        update_option($log_key, $logs);
    }

    /**
     * Trigger health alert for admin
     * 
     * @param array $metrics Health metrics
     */
    private function trigger_health_alert($metrics) {
        // Set admin notice transient
        set_transient(
            'smo_health_alert_' . $this->platform_slug,
            array(
                'platform' => $this->platform_slug,
                'consecutive_failures' => $metrics['consecutive_failures'],
                'last_failure' => $metrics['last_failure']
            ),
            DAY_IN_SECONDS
        );

        // Log critical alert
        error_log(sprintf(
            'SMO Social [CRITICAL] Platform %s has %d consecutive failures',
            $this->platform_slug,
            $metrics['consecutive_failures']
        ));
    }

    /**
     * Get platform health status
     * 
     * @return array Health status
     */
    public function get_health_status() {
        $metrics_key = 'smo_health_' . $this->platform_slug;
        $metrics = get_option($metrics_key, array());
        
        // Ensure metrics is always an array
        if (!is_array($metrics)) {
            $metrics = array();
        }

        if (empty($metrics)) {
            return array(
                'status' => 'unknown',
                'message' => __('No health data available', 'smo-social')
            );
        }

        $success_rate = $metrics['total_attempts'] > 0 
            ? ($metrics['successful'] / $metrics['total_attempts']) * 100 
            : 0;

        if ($metrics['consecutive_failures'] >= 5) {
            $status = 'critical';
            $message = __('Platform experiencing critical issues', 'smo-social');
        } elseif ($metrics['consecutive_failures'] >= 3) {
            $status = 'warning';
            $message = __('Platform experiencing issues', 'smo-social');
        } elseif ($success_rate >= 95) {
            $status = 'excellent';
            $message = __('Platform operating normally', 'smo-social');
        } elseif ($success_rate >= 80) {
            $status = 'good';
            $message = __('Platform mostly stable', 'smo-social');
        } else {
            $status = 'poor';
            $message = __('Platform reliability issues', 'smo-social');
        }

        return array(
            'status' => $status,
            'message' => $message,
            'success_rate' => round($success_rate, 2),
            'metrics' => $metrics
        );
    }

    /**
     * Get attempt log
     * 
     * @return array Attempt log
     */
    public function get_attempt_log() {
        return $this->attempt_log;
    }

    /**
     * Reset health metrics
     */
    public function reset_health_metrics() {
        $metrics_key = 'smo_health_' . $this->platform_slug;
        delete_option($metrics_key);
        delete_transient('smo_health_alert_' . $this->platform_slug);
    }
}
