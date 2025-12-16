<?php
namespace SMO_Social\AI;

class ComprehensiveErrorHandler {
    /**
     * Comprehensive error handling system with graceful degradation
     */

    /**
     * Handle errors with comprehensive context and graceful degradation
     *
     * @param string $component
     * @param string $operation
     * @param \Exception $exception
     * @param array $context
     * @param callable $fallback_function
     * @param string $fallback_type
     * @return array
     */
    public static function handle_error_with_fallback($component, $operation, \Exception $exception, $context = [], $fallback_function = null, $fallback_type = 'basic') {
        $error_code = ErrorHandler::ERROR_CODE_PROCESSING_FAILED;
        $error_message = "{$component} Error: Failed to {$operation}";

        // Log the error with comprehensive context
        self::log_comprehensive_error($component, $operation, $exception, $context);

        // Determine if we should use fallback
        $should_use_fallback = self::should_use_fallback($exception, $context);

        if ($should_use_fallback && $fallback_function && is_callable($fallback_function)) {
            try {
                $fallback_data = call_user_func($fallback_function);

                // Log fallback activation
                error_log("SMO Social {$component}: Activated {$fallback_type} fallback for {$operation}");

                return ErrorHandler::create_fallback_response(
                    $fallback_data,
                    $fallback_type,
                    "Original operation failed: " . $exception->getMessage()
                );
            } catch (\Exception $fallback_exception) {
                // Fallback also failed - log and return error
                error_log("SMO Social {$component}: Fallback failed for {$operation}: " . $fallback_exception->getMessage());

                return ErrorHandler::create_error_response(
                    $error_code,
                    "{$operation} failed and fallback also failed",
                    array_merge($context, [
                        'original_error' => $exception->getMessage(),
                        'fallback_error' => $fallback_exception->getMessage()
                    ])
                );
            }
        }

        // No fallback available or fallback not appropriate
        return ErrorHandler::create_error_response(
            $error_code,
            $error_message,
            $context
        );
    }

    /**
     * Log comprehensive error information for debugging
     *
     * @param string $component
     * @param string $operation
     * @param \Exception $exception
     * @param array $context
     */
    public static function log_comprehensive_error($component, $operation, \Exception $exception, $context = []) {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'component' => $component,
            'operation' => $operation,
            'exception' => [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => self::format_trace($exception->getTrace())
            ],
            'context' => $context,
            'system_info' => self::get_system_info()
        ];

        // Log as JSON for easy parsing
        error_log('SMO_SOCIAL_COMPREHENSIVE_ERROR: ' . json_encode($log_data));

        // Also log human-readable version
        $human_readable = "SMO Social Error | {$component} | {$operation} | " . $exception->getMessage();
        error_log($human_readable);
    }

    /**
     * Format trace for logging
     *
     * @param array $trace
     * @return string
     */
    private static function format_trace($trace) {
        $formatted = [];

        foreach ($trace as $i => $step) {
            $file = $step['file'] ?? 'unknown';
            $line = $step['line'] ?? 'unknown';
            $function = $step['function'] ?? 'unknown';
            $class = $step['class'] ?? '';

            $formatted[] = "#{$i} {$file}:{$line} - {$class}{$function}()";
        }

        return implode(' | ', array_slice($formatted, 0, 5)); // Limit to 5 steps
    }

    /**
     * Get system information for error context
     *
     * @return array
     */
    private static function get_system_info() {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => time(),
            'timezone' => date_default_timezone_get()
        ];
    }

    /**
     * Determine if fallback should be used based on error type and context
     *
     * @param \Exception $exception
     * @param array $context
     * @return bool
     */
    private static function should_use_fallback(\Exception $exception, $context = []) {
        // Don't use fallback for critical errors
        $critical_errors = [
            'Out of memory',
            'Allowed memory size exhausted',
            'Maximum execution time exceeded',
            'Database connection failed'
        ];

        foreach ($critical_errors as $critical_error) {
            if (stripos($exception->getMessage(), $critical_error) !== false) {
                return false;
            }
        }

        // Don't use fallback if explicitly disabled in context
        if (isset($context['disable_fallback']) && $context['disable_fallback'] === true) {
            return false;
        }

        // Use fallback for most other cases
        return true;
    }
}