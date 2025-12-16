<?php
namespace SMO_Social\AI;

class ErrorHandlingTest {
    /**
     * Test error handling consistency across AI components
     */
    public static function test_error_handling_consistency() {
        $results = [
            'tests_run' => 0,
            'tests_passed' => 0,
            'tests_failed' => 0,
            'error_samples' => [],
            'inconsistencies_found' => []
        ];

        // Test 1: ContentGenerator error handling
        $results['tests_run']++;
        try {
            $container = \SMO_Social\Core\DIContainer::getInstance();
            $container->singleton('SMO_Social\AI\Models\UniversalManager', new \SMO_Social\AI\Models\UniversalManager('test'));
            $container->singleton('SMO_Social\AI\CacheManager', new CacheManager());
            $content_generator = $container->resolve('SMO_Social\AI\ContentGenerator');
            $result = $content_generator->generate_with_model('test', 'test-model');

            if (isset($result['error']) && is_array($result['error'])) {
                $results['tests_passed']++;
                $results['error_samples']['content_generator'] = 'Consistent error format found';
            } else {
                $results['tests_failed']++;
                $results['inconsistencies_found'][] = 'ContentGenerator: Inconsistent error response format';
            }
        } catch (\Exception $e) {
            $results['tests_failed']++;
            $results['inconsistencies_found'][] = 'ContentGenerator: Exception thrown instead of error response';
        }

        // Test 2: AI Manager error handling
        $results['tests_run']++;
        try {
            $ai_manager = Manager::getInstance();
            $caption_result = $ai_manager->generate_captions('test content', ['twitter']);

            if (isset($caption_result['twitter']['error']) && is_array($caption_result['twitter']['error'])) {
                $results['tests_passed']++;
                $results['error_samples']['ai_manager'] = 'Consistent error format found';
            } else {
                $results['tests_failed']++;
                $results['inconsistencies_found'][] = 'AI Manager: Inconsistent error response format';
            }
        } catch (\Exception $e) {
            $results['tests_failed']++;
            $results['inconsistencies_found'][] = 'AI Manager: Exception thrown instead of error response';
        }

        // Test 3: Error logging verification
        $results['tests_run']++;
        try {
            // This should trigger an error that gets logged
            $test_result = ErrorHandler::handle_provider_error('test-provider', 'test-operation', new \Exception('Test exception'));

            if (isset($test_result['error']['code']) && isset($test_result['error']['message'])) {
                $results['tests_passed']++;
                $results['error_samples']['error_logging'] = 'Error logging format is consistent';
            } else {
                $results['tests_failed']++;
                $results['inconsistencies_found'][] = 'Error logging: Inconsistent error structure';
            }
        } catch (\Exception $e) {
            $results['tests_failed']++;
            $results['inconsistencies_found'][] = 'Error logging: Exception thrown';
        }

        // Test 4: Fallback response consistency
        $results['tests_run']++;
        try {
            $fallback_result = ErrorHandler::create_fallback_response(
                ['test' => 'fallback_data'],
                'basic_fallback',
                'Test fallback activation'
            );

            if (isset($fallback_result['fallback']) && $fallback_result['fallback'] === true) {
                $results['tests_passed']++;
                $results['error_samples']['fallback_consistency'] = 'Fallback response format is consistent';
            } else {
                $results['tests_failed']++;
                $results['inconsistencies_found'][] = 'Fallback response: Inconsistent structure';
            }
        } catch (\Exception $e) {
            $results['tests_failed']++;
            $results['inconsistencies_found'][] = 'Fallback response: Exception thrown';
        }

        // Test 5: Success response consistency
        $results['tests_run']++;
        try {
            $success_result = ErrorHandler::create_success_response(['test' => 'data']);

            if (isset($success_result['success']) && $success_result['success'] === true) {
                $results['tests_passed']++;
                $results['error_samples']['success_consistency'] = 'Success response format is consistent';
            } else {
                $results['tests_failed']++;
                $results['inconsistencies_found'][] = 'Success response: Inconsistent structure';
            }
        } catch (\Exception $e) {
            $results['tests_failed']++;
            $results['inconsistencies_found'][] = 'Success response: Exception thrown';
        }

        // Log test results
        error_log('SMO Social Error Handling Test Results:');
        error_log('Tests Run: ' . $results['tests_run']);
        error_log('Tests Passed: ' . $results['tests_passed']);
        error_log('Tests Failed: ' . $results['tests_failed']);
        error_log('Inconsistencies Found: ' . count($results['inconsistencies_found']));

        if (!empty($results['inconsistencies_found'])) {
            error_log('Inconsistencies Details:');
            foreach ($results['inconsistencies_found'] as $inconsistency) {
                error_log('- ' . $inconsistency);
            }
        }

        return $results;
    }

    /**
     * Test graceful degradation across AI components
     */
    public static function test_graceful_degradation() {
        $results = [
            'components_tested' => 0,
            'fallbacks_working' => 0,
            'fallbacks_failing' => 0,
            'fallback_samples' => []
        ];

        // Test AI Manager fallbacks
        $results['components_tested']++;
        try {
            $ai_manager = Manager::getInstance();

            // Test with invalid provider to trigger fallback
            $original_provider = $ai_manager->get_primary_provider_id();

            // Force a scenario that should trigger fallback
            $test_result = $ai_manager->generate_captions('test content', ['twitter']);

            if (isset($test_result['twitter']['fallback'])) {
                $results['fallbacks_working']++;
                $results['fallback_samples']['ai_manager'] = 'Fallback mechanism working';
            } else {
                $results['fallbacks_failing']++;
                $results['fallback_samples']['ai_manager'] = 'Fallback mechanism not triggered or missing';
            }
        } catch (\Exception $e) {
            $results['fallbacks_failing']++;
            $results['fallback_samples']['ai_manager'] = 'Exception during fallback test: ' . $e->getMessage();
        }

        // Test ContentGenerator fallbacks
        $results['components_tested']++;
        try {
            $container = \SMO_Social\Core\DIContainer::getInstance();
            $container->singleton('SMO_Social\AI\Models\UniversalManager', new \SMO_Social\AI\Models\UniversalManager('test'));
            $container->singleton('SMO_Social\AI\CacheManager', new CacheManager());
            $content_generator = $container->resolve('SMO_Social\AI\ContentGenerator');
            $result = $content_generator->generate_with_model('test', 'non-existent-model');

            if (isset($result['fallback'])) {
                $results['fallbacks_working']++;
                $results['fallback_samples']['content_generator'] = 'Fallback mechanism working';
            } else {
                $results['fallbacks_failing']++;
                $results['fallback_samples']['content_generator'] = 'Fallback mechanism not triggered or missing';
            }
        } catch (\Exception $e) {
            $results['fallbacks_failing']++;
            $results['fallback_samples']['content_generator'] = 'Exception during fallback test: ' . $e->getMessage();
        }

        // Log graceful degradation test results
        error_log('SMO Social Graceful Degradation Test Results:');
        error_log('Components Tested: ' . $results['components_tested']);
        error_log('Fallbacks Working: ' . $results['fallbacks_working']);
        error_log('Fallbacks Failing: ' . $results['fallbacks_failing']);

        return $results;
    }

    /**
     * Run comprehensive error handling validation
     */
    public static function run_comprehensive_validation() {
        error_log('SMO Social: Starting comprehensive error handling validation');

        $consistency_results = self::test_error_handling_consistency();
        $degradation_results = self::test_graceful_degradation();

        $summary = [
            'error_handling' => $consistency_results,
            'graceful_degradation' => $degradation_results,
            'overall_status' => 'partial',
            'recommendations' => []
        ];

        // Generate recommendations based on test results
        if ($consistency_results['tests_failed'] > 0) {
            $summary['recommendations'][] = 'Fix error handling inconsistencies in identified components';
            $summary['overall_status'] = 'needs_attention';
        }

        if ($degradation_results['fallbacks_failing'] > 0) {
            $summary['recommendations'][] = 'Improve fallback mechanisms in failing components';
            $summary['overall_status'] = 'needs_attention';
        }

        if ($consistency_results['tests_failed'] === 0 && $degradation_results['fallbacks_failing'] === 0) {
            $summary['overall_status'] = 'healthy';
            $summary['recommendations'][] = 'Error handling system is working consistently';
        }

        error_log('SMO Social Error Handling Validation Summary:');
        error_log('Overall Status: ' . strtoupper($summary['overall_status']));
        error_log('Recommendations: ' . count($summary['recommendations']));

        foreach ($summary['recommendations'] as $recommendation) {
            error_log('- ' . $recommendation);
        }

        return $summary;
    }
}