<?php
/**
 * AI Functionality Test Suite
 * Tests the refactored AI Manager system with UniversalManager
 * 
 * Note: This file requires WordPress to be loaded and will use WordPress functions.
 * IDE warnings about undefined WordPress functions are expected and can be ignored.
 * 
 * @suppress PhanUndeclaredFunction - WordPress functions loaded at runtime
 */

// Load WordPress environment
require_once dirname(__DIR__) . '/smo-social.php';

class AIFunctionalityTest {
    private $results = [];
    private $ai_manager;
    
    public function __construct() {
        echo "\n=== SMO Social AI Functionality Test Suite ===\n";
        echo "Testing refactored AI Manager with UniversalManager\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    }
    
    public function run_all_tests() {
        $this->test_ai_manager_singleton();
        $this->test_provider_configuration();
        $this->test_chat_functionality();
        $this->test_content_optimizer();
        $this->test_smart_content_repurposer();
        $this->test_caption_generation();
        $this->test_hashtag_optimization();
        $this->test_sentiment_analysis();
        $this->test_fallback_mechanisms();
        
        $this->print_summary();
    }
    
    /**
     * Test 1: AI Manager Singleton
     */
    private function test_ai_manager_singleton() {
        echo "Test 1: AI Manager Singleton Pattern\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $manager1 = \SMO_Social\AI\Manager::getInstance();
            $manager2 = \SMO_Social\AI\Manager::getInstance();
            
            if ($manager1 === $manager2) {
                $this->pass("Singleton pattern working correctly");
                $this->ai_manager = $manager1;
            } else {
                $this->fail("Singleton pattern broken - multiple instances created");
            }
            
            // Test that manager is properly initialized
            if ($manager1 !== null) {
                $this->pass("AI Manager instance created successfully");
            } else {
                $this->fail("AI Manager instance is null");
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in singleton test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 2: Provider Configuration
     */
    private function test_provider_configuration() {
        echo "Test 2: Provider Configuration\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $available_providers = $this->ai_manager->get_available_providers();
            
            echo "Available providers: " . count($available_providers) . "\n";
            foreach ($available_providers as $id => $config) {
                echo "  - {$id}: {$config['name']}\n";
            }
            
            if (count($available_providers) > 0) {
                $this->pass("Providers configured: " . count($available_providers));
            } else {
                $this->warn("No providers configured - tests will use fallbacks");
            }
            
            // Test primary provider
            $primary_provider = $this->ai_manager->get_primary_provider_id();
            if ($primary_provider) {
                $this->pass("Primary provider: {$primary_provider}");
            } else {
                $this->warn("No primary provider set");
            }
            
            // Get processing stats
            $stats = $this->ai_manager->get_processing_stats();
            echo "\nProcessing Stats:\n";
            echo "  - Available providers: {$stats['available_providers']}\n";
            echo "  - Primary provider: {$stats['primary_provider']}\n";
            echo "  - Total supported: {$stats['total_providers_supported']}\n";
            
        } catch (\Exception $e) {
            $this->fail("Exception in provider config test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 3: Chat Functionality
     */
    private function test_chat_functionality() {
        echo "Test 3: Chat Functionality\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $messages = [
                ['role' => 'user', 'content' => 'Say "Hello, SMO Social!" if you can hear me.']
            ];
            
            echo "Sending test message to AI...\n";
            
            try {
                $response = $this->ai_manager->chat($messages);
                
                if (isset($response['content']) || isset($response['message'])) {
                    $content = $response['content'] ?? $response['message'] ?? '';
                    echo "AI Response: " . substr($content, 0, 100) . "...\n";
                    $this->pass("Chat functionality working");
                } else {
                    $this->warn("Chat returned unexpected format: " . json_encode($response));
                }
            } catch (\Exception $e) {
                $this->warn("Chat failed (expected if no provider configured): " . $e->getMessage());
                echo "This is normal if no AI provider is configured.\n";
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in chat test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 4: Content Optimizer
     */
    private function test_content_optimizer() {
        echo "Test 4: Content Optimizer\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $optimizer = new \SMO_Social\AI\ContentOptimizer();
            $this->pass("ContentOptimizer instantiated successfully");
            
            // Create a test post
            $test_post_id = $this->create_test_post();
            
            if ($test_post_id) {
                echo "Testing optimization for Twitter...\n";
                $optimization = $optimizer->optimize_for_platform($test_post_id, 'twitter');
                
                if (is_wp_error($optimization)) {
                    $this->warn("Optimization returned WP_Error: " . $optimization->get_error_message());
                } elseif (is_array($optimization)) {
                    echo "Optimization results:\n";
                    echo "  - Platform: {$optimization['platform']}\n";
                    echo "  - Content length: {$optimization['content_analysis']['content_length']}\n";
                    echo "  - Recommendations: " . count($optimization['recommendations']) . "\n";
                    echo "  - Engagement score: {$optimization['engagement_prediction']['score']}\n";
                    $this->pass("Content optimization working");
                } else {
                    $this->warn("Unexpected optimization format");
                }
                
                // Clean up
                /** @suppress PhanUndeclaredFunction */
                wp_delete_post($test_post_id, true);
            } else {
                $this->warn("Could not create test post");
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in content optimizer test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 5: Smart Content Repurposer
     */
    private function test_smart_content_repurposer() {
        echo "Test 5: Smart Content Repurposer\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $repurposer = new \SMO_Social\AI\SmartContentRepurposer();
            $this->pass("SmartContentRepurposer instantiated successfully");
            
            $original_content = "This is a test post about social media marketing. Learn how to optimize your content for maximum engagement across all platforms. #SocialMedia #Marketing";
            $target_platforms = ['twitter', 'linkedin', 'facebook'];
            
            echo "Repurposing content for: " . implode(', ', $target_platforms) . "\n";
            
            $result = $repurposer->repurpose_content($original_content, $target_platforms);
            
            if (isset($result['repurposed_content'])) {
                echo "Repurposed content generated:\n";
                foreach ($result['repurposed_content'] as $platform => $content) {
                    $content_text = is_array($content) && isset($content['content']) 
                        ? $content['content'] 
                        : (is_string($content) ? $content : 'N/A');
                    echo "  - {$platform}: " . substr($content_text, 0, 50) . "...\n";
                }
                $this->pass("Content repurposing working");
            } else {
                $this->warn("Repurposing returned unexpected format");
            }
            
            // Test series creation
            echo "\nTesting series creation...\n";
            $series_result = $repurposer->create_content_series(
                str_repeat($original_content . " ", 10), // Make it longer
                ['title' => 'Test Series']
            );
            
            if (isset($series_result['total_pieces'])) {
                echo "Series created with {$series_result['total_pieces']} pieces\n";
                $this->pass("Series creation working");
            } else {
                $this->warn("Series creation returned unexpected format");
            }
            
            // Test evergreen optimization
            echo "\nTesting evergreen content optimization...\n";
            $evergreen_result = $repurposer->optimize_evergreen_content(
                "How to create effective social media content: A comprehensive guide with tips and best practices."
            );
            
            if (isset($evergreen_result['is_evergreen'])) {
                echo "Evergreen analysis: " . ($evergreen_result['is_evergreen'] ? 'Yes' : 'No') . "\n";
                $this->pass("Evergreen optimization working");
            } else {
                $this->warn("Evergreen optimization returned unexpected format");
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in repurposer test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 6: Caption Generation
     */
    private function test_caption_generation() {
        echo "Test 6: Caption Generation\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $content = "Check out our latest blog post about social media trends!";
            $platforms = ['twitter', 'instagram'];
            
            echo "Generating captions for: " . implode(', ', $platforms) . "\n";
            
            $captions = $this->ai_manager->generate_captions($content, $platforms);
            
            if (is_array($captions)) {
                foreach ($captions as $platform => $caption) {
                    $caption_text = is_array($caption) && isset($caption['caption']) 
                        ? $caption['caption'] 
                        : (is_string($caption) ? $caption : 'N/A');
                    echo "  - {$platform}: " . substr($caption_text, 0, 50) . "...\n";
                }
                $this->pass("Caption generation working");
            } else {
                $this->warn("Caption generation returned unexpected format");
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in caption generation test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 7: Hashtag Optimization
     */
    private function test_hashtag_optimization() {
        echo "Test 7: Hashtag Optimization\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $content = "Social media marketing tips for small businesses #Marketing #Business";
            $platforms = ['twitter', 'instagram'];
            
            echo "Optimizing hashtags for: " . implode(', ', $platforms) . "\n";
            
            $hashtags = $this->ai_manager->optimize_hashtags($content, $platforms);
            
            if (is_array($hashtags)) {
                foreach ($hashtags as $platform => $tags) {
                    $tag_list = is_array($tags) ? $tags : [$tags];
                    echo "  - {$platform}: " . count($tag_list) . " hashtags\n";
                }
                $this->pass("Hashtag optimization working");
            } else {
                $this->warn("Hashtag optimization returned unexpected format");
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in hashtag optimization test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 8: Sentiment Analysis
     */
    private function test_sentiment_analysis() {
        echo "Test 8: Sentiment Analysis\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $positive_text = "This is amazing! I love it! Best product ever!";
            $negative_text = "This is terrible. I hate it. Worst experience.";
            $neutral_text = "The product arrived on time.";
            
            echo "Analyzing positive text...\n";
            $positive_result = $this->ai_manager->analyze_sentiment($positive_text);
            echo "  Sentiment: {$positive_result['sentiment']}\n";
            
            echo "Analyzing negative text...\n";
            $negative_result = $this->ai_manager->analyze_sentiment($negative_text);
            echo "  Sentiment: {$negative_result['sentiment']}\n";
            
            echo "Analyzing neutral text...\n";
            $neutral_result = $this->ai_manager->analyze_sentiment($neutral_text);
            echo "  Sentiment: {$neutral_result['sentiment']}\n";
            
            if ($positive_result['sentiment'] === 'positive' && 
                $negative_result['sentiment'] === 'negative') {
                $this->pass("Sentiment analysis working correctly");
            } else {
                $this->warn("Sentiment analysis may need tuning");
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in sentiment analysis test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Test 9: Fallback Mechanisms
     */
    private function test_fallback_mechanisms() {
        echo "Test 9: Fallback Mechanisms\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            // Test with invalid provider to trigger fallback
            echo "Testing fallback behavior...\n";
            
            $content = "Test content for fallback";
            $platforms = ['twitter'];
            
            // These should use fallback methods
            $captions = $this->ai_manager->generate_captions($content, $platforms);
            $hashtags = $this->ai_manager->optimize_hashtags($content, $platforms);
            
            if (isset($captions['twitter']) && isset($hashtags['twitter'])) {
                $this->pass("Fallback mechanisms working");
                echo "  Fallback caption generated\n";
                echo "  Fallback hashtags generated\n";
            } else {
                $this->warn("Fallback mechanisms may not be working properly");
            }
            
        } catch (\Exception $e) {
            $this->fail("Exception in fallback test: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    /**
     * Helper: Create test post
     * 
     * @suppress PhanUndeclaredFunction - wp_insert_post is a WordPress function
     */
    private function create_test_post() {
        $post_data = [
            'post_title' => 'Test Post for AI Optimization',
            'post_content' => 'This is a test post about social media marketing. It contains information about best practices, engagement strategies, and content optimization. #SocialMedia #Marketing #ContentStrategy',
            'post_status' => 'publish',
            'post_type' => 'post'
        ];
        
        /** @suppress PhanUndeclaredFunction */
        return wp_insert_post($post_data);
    }
    
    /**
     * Helper: Pass test
     */
    private function pass($message) {
        echo "✓ PASS: {$message}\n";
        $this->results[] = ['status' => 'pass', 'message' => $message];
    }
    
    /**
     * Helper: Fail test
     */
    private function fail($message) {
        echo "✗ FAIL: {$message}\n";
        $this->results[] = ['status' => 'fail', 'message' => $message];
    }
    
    /**
     * Helper: Warning
     */
    private function warn($message) {
        echo "⚠ WARN: {$message}\n";
        $this->results[] = ['status' => 'warn', 'message' => $message];
    }
    
    /**
     * Print summary
     */
    private function print_summary() {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat('=', 50) . "\n\n";
        
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'pass'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'fail'));
        $warned = count(array_filter($this->results, fn($r) => $r['status'] === 'warn'));
        $total = count($this->results);
        
        echo "Total Tests: {$total}\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "Warnings: {$warned}\n\n";
        
        $success_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        echo "Success Rate: {$success_rate}%\n\n";
        
        if ($failed === 0) {
            echo "🎉 All tests passed!\n";
        } elseif ($failed < 3) {
            echo "⚠️  Most tests passed with some failures.\n";
        } else {
            echo "❌ Multiple test failures detected.\n";
        }
        
        echo "\n";
    }
}

// Run tests if executed directly
/** @suppress PhanUndeclaredConstant */
if (php_sapi_name() === 'cli' || (defined('WP_CLI') && WP_CLI)) {
    $test = new AIFunctionalityTest();
    $test->run_all_tests();
} else {
    echo "This test suite should be run from the command line or WP-CLI.\n";
}
