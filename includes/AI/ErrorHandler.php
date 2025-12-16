<?php
namespace SMO_Social\AI;

class ErrorHandler {
    const ERROR_CODE_AI_PROVIDER_UNAVAILABLE = 1001;
    const ERROR_CODE_API_CONNECTION_FAILED = 1002;
    const ERROR_CODE_INVALID_RESPONSE_FORMAT = 1003;
    const ERROR_CODE_CONFIGURATION_MISSING = 1004;
    const ERROR_CODE_RATE_LIMIT_EXCEEDED = 1005;
    const ERROR_CODE_INVALID_INPUT = 1006;
    const ERROR_CODE_PROCESSING_FAILED = 1007;
    const ERROR_CODE_FALLBACK_ACTIVATED = 1008;

    /**
     * Standard error response format
     *
     * @param int $error_code
     * @param string $error_message
     * @param array $context
     * @param mixed $fallback_data
     * @return array
     */
    public static function create_error_response($error_code, $error_message, $context = [], $fallback_data = null) {
        $response = [
            'success' => false,
            'error' => [
                'code' => $error_code,
                'message' => $error_message,
                'type' => self::get_error_type($error_code),
                'severity' => self::get_error_severity($error_code),
                'timestamp' => current_time('mysql'),
                'context' => $context
            ]
        ];

        if ($fallback_data !== null) {
            $response['fallback'] = $fallback_data;
            $response['error']['fallback_used'] = true;
        }

        return $response;
    }

    /**
     * Standard success response format
     *
     * @param mixed $data
     * @param array $meta
     * @return array
     */
    public static function create_success_response($data, $meta = []) {
        return [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => current_time('mysql'),
                'source' => 'ai_processing'
            ], $meta)
        ];
    }

    /**
     * Log error with consistent formatting
     *
     * @param string $component
     * @param string $message
     * @param array $context
     * @param int $error_code
     */
    public static function log_error($component, $message, $context = [], $error_code = null) {
        $log_message = "[$component] ERROR: $message";

        if ($error_code) {
            $log_message .= " (Code: $error_code)";
        }

        if (!empty($context)) {
            $log_message .= " | Context: " . json_encode($context);
        }

        error_log($log_message);
    }

    /**
     * Handle AI provider errors consistently
     *
     * @param string $provider_id
     * @param string $operation
     * @param \Exception $exception
     * @return array
     */
    public static function handle_provider_error($provider_id, $operation, \Exception $exception) {
        $error_code = self::ERROR_CODE_AI_PROVIDER_UNAVAILABLE;
        $error_message = "AI Provider Error: Failed to $operation with provider $provider_id";

        self::log_error(
            'AI_Provider',
            $error_message,
            [
                'provider' => $provider_id,
                'operation' => $operation,
                'exception_message' => $exception->getMessage()
            ],
            $error_code
        );

        return self::create_error_response(
            $error_code,
            $error_message,
            [
                'provider' => $provider_id,
                'operation' => $operation,
                'details' => $exception->getMessage()
            ]
        );
    }

    /**
     * Handle API connection errors consistently
     *
     * @param string $endpoint
     * @param string $method
     * @param \Exception $exception
     * @return array
     */
    public static function handle_api_error($endpoint, $method, \Exception $exception) {
        $error_code = self::ERROR_CODE_API_CONNECTION_FAILED;
        $error_message = "API Connection Error: Failed to $method to $endpoint";

        self::log_error(
            'API_Connection',
            $error_message,
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'exception_message' => $exception->getMessage()
            ],
            $error_code
        );

        return self::create_error_response(
            $error_code,
            $error_message,
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'details' => $exception->getMessage()
            ]
        );
    }

    /**
     * Handle configuration errors consistently
     *
     * @param string $component
     * @param string $missing_config
     * @param string $context
     * @return array
     */
    public static function handle_config_error($component, $missing_config, $context = '') {
        $error_code = self::ERROR_CODE_CONFIGURATION_MISSING;
        $error_message = "Configuration Error: $component requires $missing_config";

        if ($context) {
            $error_message .= " for $context";
        }

        self::log_error(
            'Configuration',
            $error_message,
            [
                'component' => $component,
                'missing_config' => $missing_config,
                'context' => $context
            ],
            $error_code
        );

        return self::create_error_response(
            $error_code,
            $error_message,
            [
                'component' => $component,
                'missing_config' => $missing_config,
                'context' => $context
            ]
        );
    }

    /**
     * Get error type from error code
     *
     * @param int $error_code
     * @return string
     */
    private static function get_error_type($error_code) {
        $error_types = [
            self::ERROR_CODE_AI_PROVIDER_UNAVAILABLE => 'provider_error',
            self::ERROR_CODE_API_CONNECTION_FAILED => 'connection_error',
            self::ERROR_CODE_INVALID_RESPONSE_FORMAT => 'format_error',
            self::ERROR_CODE_CONFIGURATION_MISSING => 'configuration_error',
            self::ERROR_CODE_RATE_LIMIT_EXCEEDED => 'rate_limit_error',
            self::ERROR_CODE_INVALID_INPUT => 'input_error',
            self::ERROR_CODE_PROCESSING_FAILED => 'processing_error',
            self::ERROR_CODE_FALLBACK_ACTIVATED => 'fallback_warning'
        ];

        return $error_types[$error_code] ?? 'unknown_error';
    }

    /**
     * Get error severity from error code
     *
     * @param int $error_code
     * @return string
     */
    private static function get_error_severity($error_code) {
        $severities = [
            self::ERROR_CODE_AI_PROVIDER_UNAVAILABLE => 'high',
            self::ERROR_CODE_API_CONNECTION_FAILED => 'high',
            self::ERROR_CODE_INVALID_RESPONSE_FORMAT => 'medium',
            self::ERROR_CODE_CONFIGURATION_MISSING => 'high',
            self::ERROR_CODE_RATE_LIMIT_EXCEEDED => 'medium',
            self::ERROR_CODE_INVALID_INPUT => 'low',
            self::ERROR_CODE_PROCESSING_FAILED => 'medium',
            self::ERROR_CODE_FALLBACK_ACTIVATED => 'low'
        ];

        return $severities[$error_code] ?? 'medium';
    }

    /**
     * Create fallback response with consistent structure
     *
     * @param mixed $fallback_data
     * @param string $fallback_type
     * @param string $reason
     * @return array
     */
    public static function create_fallback_response($fallback_data, $fallback_type, $reason = '') {
        return [
            'success' => true,
            'fallback' => true,
            'fallback_type' => $fallback_type,
            'fallback_reason' => $reason,
            'data' => $fallback_data,
            'meta' => [
                'timestamp' => current_time('mysql'),
                'source' => 'fallback_processing'
            ]
        ];
    }

    /**
     * Handle rate limit errors
     *
     * @param string $provider
     * @param int $limit
     * @param int $remaining
     * @param int $reset_time
     * @return array
     */
    public static function handle_rate_limit_error($provider, $limit, $remaining, $reset_time) {
        $error_code = self::ERROR_CODE_RATE_LIMIT_EXCEEDED;
        $error_message = "Rate Limit Exceeded: $provider API rate limit reached";

        self::log_error(
            'Rate_Limit',
            $error_message,
            [
                'provider' => $provider,
                'limit' => $limit,
                'remaining' => $remaining,
                'reset_time' => $reset_time
            ],
            $error_code
        );

        return self::create_error_response(
            $error_code,
            $error_message,
            [
                'provider' => $provider,
                'limit' => $limit,
                'remaining' => $remaining,
                'reset_time' => $reset_time,
                'suggestion' => "Please wait $reset_time seconds before trying again"
            ]
        );
    }

    /**
     * Handle invalid input errors
     *
     * @param string $function
     * @param string $parameter
     * @param string $expected_type
     * @param mixed $actual_value
     * @return array
     */
    public static function handle_invalid_input($function, $parameter, $expected_type, $actual_value) {
        $error_code = self::ERROR_CODE_INVALID_INPUT;
        $error_message = "Invalid Input: $function expected $expected_type for parameter $parameter";

        self::log_error(
            'Input_Validation',
            $error_message,
            [
                'function' => $function,
                'parameter' => $parameter,
                'expected_type' => $expected_type,
                'actual_value' => is_scalar($actual_value) ? $actual_value : gettype($actual_value)
            ],
            $error_code
        );

        return self::create_error_response(
            $error_code,
            $error_message,
            [
                'function' => $function,
                'parameter' => $parameter,
                'expected' => $expected_type,
                'actual' => is_scalar($actual_value) ? $actual_value : gettype($actual_value)
            ]
        );
    }
}