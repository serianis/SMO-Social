<?php
/**
 * SMO Social - Production Webhook Configuration Script
 * 
 * This script generates production webhook URLs and configuration for:
 * - Zapier integration
 * - IFTTT integration
 * - Custom webhooks
 * - Social media platform webhooks
 * 
 * Usage: php production_webhook_config.php
 */

class ProductionWebhookConfig {
    
    private $production_url;
    private $webhook_secret;
    private $webhook_config = [];
    
    public function __construct() {
        $this->production_url = $this->getProductionUrl();
        $this->webhook_secret = $this->generateWebhookSecret();
        echo "🔗 SMO Social Production Webhook Configuration\n";
        echo "==============================================\n\n";
    }
    
    /**
     * Get production URL
     */
    private function getProductionUrl() {
        $site_url = $_ENV['PRODUCTION_SITE_URL'] ?? 'https://your-domain.com';
        return rtrim($site_url, '/');
    }
    
    /**
     * Generate webhook secret
     */
    private function generateWebhookSecret() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Initialize webhook configuration
     */
    public function initialize() {
        $this->loadWebhookEndpoints();
        $this->displayProductionUrls();
    }
    
    /**
     * Load webhook endpoints configuration
     */
    private function loadWebhookEndpoints() {
        $this->webhook_config = [
            // Zapier Integration Webhooks
            'zapier_post_created' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/zapier/post-created',
                'method' => 'POST',
                'description' => 'Triggered when a new post is created',
                'events' => ['post.created', 'post.published'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            'zapier_post_scheduled' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/zapier/post-scheduled',
                'method' => 'POST',
                'description' => 'Triggered when a post is scheduled',
                'events' => ['post.scheduled', 'post.rescheduled'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            'zapier_analytics_update' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/zapier/analytics-update',
                'method' => 'POST',
                'description' => 'Triggered when analytics are updated',
                'events' => ['analytics.updated', 'metrics.completed'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            'zapier_content_imported' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/zapier/content-imported',
                'method' => 'POST',
                'description' => 'Triggered when content is imported',
                'events' => ['content.imported', 'media.uploaded'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            
            // IFTTT Integration Webhooks
            'ifttt_post_published' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/ifttt/post-published',
                'method' => 'POST',
                'description' => 'Triggered when a post is published',
                'events' => ['post.published'],
                'platforms' => ['facebook', 'twitter', 'linkedin', 'instagram'],
                'signature_validation' => true
            ],
            'ifttt_engagement_alert' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/ifttt/engagement-alert',
                'method' => 'POST',
                'description' => 'Triggered when engagement threshold is met',
                'events' => ['engagement.high', 'engagement.milestone'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            'ifttt_scheduled_post' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/ifttt/scheduled-post',
                'method' => 'POST',
                'description' => 'Triggered for scheduled posts',
                'events' => ['post.scheduled'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            
            // Platform-specific Webhooks
            'facebook_webhook' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/platforms/facebook',
                'method' => 'POST',
                'description' => 'Facebook platform webhooks',
                'events' => ['page.message', 'page.post', 'page.like'],
                'platforms' => ['facebook'],
                'signature_validation' => true
            ],
            'instagram_webhook' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/platforms/instagram',
                'method' => 'POST',
                'description' => 'Instagram platform webhooks',
                'events' => ['media.like', 'media.comment', 'story.view'],
                'platforms' => ['instagram'],
                'signature_validation' => true
            ],
            'twitter_webhook' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/platforms/twitter',
                'method' => 'POST',
                'description' => 'Twitter/X platform webhooks',
                'events' => ['tweet.mention', 'tweet.reply', 'tweet.like'],
                'platforms' => ['twitter'],
                'signature_validation' => true
            ],
            'linkedin_webhook' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/platforms/linkedin',
                'method' => 'POST',
                'description' => 'LinkedIn platform webhooks',
                'events' => ['post.comment', 'post.like', 'post.share'],
                'platforms' => ['linkedin'],
                'signature_validation' => true
            ],
            
            // Content Management Webhooks
            'content_draft_saved' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/content/draft-saved',
                'method' => 'POST',
                'description' => 'Triggered when content draft is saved',
                'events' => ['content.draft.created', 'content.draft.updated'],
                'platforms' => ['all'],
                'signature_validation' => false
            ],
            'media_uploaded' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/content/media-uploaded',
                'method' => 'POST',
                'description' => 'Triggered when media is uploaded',
                'events' => ['media.uploaded', 'media.processed'],
                'platforms' => ['all'],
                'signature_validation' => false
            ],
            'template_used' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/content/template-used',
                'method' => 'POST',
                'description' => 'Triggered when content template is used',
                'events' => ['template.used', 'template.cloned'],
                'platforms' => ['all'],
                'signature_validation' => false
            ],
            
            // Analytics & Reporting Webhooks
            'report_generated' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/analytics/report-generated',
                'method' => 'POST',
                'description' => 'Triggered when analytics report is generated',
                'events' => ['report.generated', 'report.scheduled'],
                'platforms' => ['all'],
                'signature_validation' => false
            ],
            'insights_updated' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/analytics/insights-updated',
                'method' => 'POST',
                'description' => 'Triggered when insights are updated',
                'events' => ['insights.updated', 'metrics.cached'],
                'platforms' => ['all'],
                'signature_validation' => false
            ],
            
            // System & Security Webhooks
            'system_backup' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/system/backup',
                'method' => 'POST',
                'description' => 'Triggered for system backup events',
                'events' => ['backup.started', 'backup.completed', 'backup.failed'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            'security_alert' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/security/alert',
                'method' => 'POST',
                'description' => 'Triggered for security events',
                'events' => ['security.threat', 'security.breach', 'security.login_failed'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            'system_error' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/system/error',
                'method' => 'POST',
                'description' => 'Triggered for system errors',
                'events' => ['system.error', 'api.failure', 'database.error'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            
            // User & Team Webhooks
            'user_registered' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/user/registered',
                'method' => 'POST',
                'description' => 'Triggered when user registers',
                'events' => ['user.registered', 'user.activated'],
                'platforms' => ['all'],
                'signature_validation' => true
            ],
            'team_activity' => [
                'url' => $this->production_url . '/wp-json/smo-social/v1/webhooks/team/activity',
                'method' => 'POST',
                'description' => 'Triggered for team member activities',
                'events' => ['team.invited', 'team.permission_changed', 'team.activity'],
                'platforms' => ['all'],
                'signature_validation' => true
            ]
        ];
    }
    
    /**
     * Display production URLs and configuration
     */
    private function displayProductionUrls() {
        echo "🌐 PRODUCTION CONFIGURATION\n";
        echo "============================\n\n";
        echo "Production Site URL: {$this->production_url}\n";
        echo "Webhook Secret: {$this->webhook_secret}\n";
        echo "Total Webhook Endpoints: " . count($this->webhook_config) . "\n\n";
    }
    
    /**
     * Generate webhook configuration documentation
     */
    public function generateWebhookDocumentation() {
        echo "📚 Generating webhook configuration documentation...\n\n";
        
        $documentation = $this->buildWebhookDocumentation();
        $doc_file = 'PRODUCTION_WEBHOOK_DOCUMENTATION.md';
        file_put_contents($doc_file, $documentation);
        
        echo "✅ Webhook documentation created: {$doc_file}\n\n";
        
        return $doc_file;
    }
    
    /**
     * Build webhook documentation
     */
    private function buildWebhookDocumentation() {
        $doc = "# SMO Social Production Webhook Documentation\n\n";
        $doc .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $doc .= "Production URL: {$this->production_url}\n";
        $doc .= "Webhook Secret: {$this->webhook_secret}\n\n";
        
        $doc .= "## 🔗 Webhook Overview\n\n";
        $doc .= "SMO Social provides webhook endpoints for real-time integrations with third-party services.\n";
        $doc .= "All webhooks support both JSON and form-encoded payload formats.\n\n";
        
        $doc .= "## 🔐 Authentication & Security\n\n";
        $doc .= "### Signature Validation\n";
        $doc .= "Webhooks marked with `signature_validation: true` include HMAC-SHA256 signatures.\n";
        $doc .= "Signature header: `X-SMO-Signature`\n";
        $doc .= "Signature secret: `{$this->webhook_secret}`\n\n";
        
        $doc .= "### Validating Webhook Signatures (Example - PHP)\n";
        $doc .= "```php\n";
        $doc .= "\$payload = file_get_contents('php://input');\n";
        $doc .= "\$signature = \$_SERVER['HTTP_X_SMO_SIGNATURE'];\n";
        $doc .= "\$expected_signature = hash_hmac('sha256', \$payload, '{$this->webhook_secret}');\n";
        $doc .= "if (hash_equals(\$expected_signature, \$signature)) {\n";
        $doc .= "    // Valid webhook\n";
        $doc .= "}\n";
        $doc .= "```\n\n";
        
        // Add detailed endpoint documentation
        $doc .= $this->buildEndpointDocumentation();
        
        // Add integration guides
        $doc .= $this->buildIntegrationGuides();
        
        return $doc;
    }
    
    /**
     * Build endpoint documentation
     */
    private function buildEndpointDocumentation() {
        $doc = "## 📡 Webhook Endpoints\n\n";
        
        // Group webhooks by category
        $grouped_endpoints = $this->groupEndpointsByCategory();
        
        foreach ($grouped_endpoints as $category => $endpoints) {
            $doc .= "### {$category}\n\n";
            
            foreach ($endpoints as $key => $config) {
                $doc .= "#### {$config['description']}\n";
                $doc .= "- **URL**: `{$config['url']}`\n";
                $doc .= "- **Method**: {$config['method']}\n";
                $doc .= "- **Events**: " . implode(', ', $config['events']) . "\n";
                $doc .= "- **Platforms**: " . implode(', ', $config['platforms']) . "\n";
                $doc .= "- **Signature Validation**: " . ($config['signature_validation'] ? 'Required' : 'Optional') . "\n";
                
                // Add example payload
                $doc .= "- **Example Payload**:\n";
                $doc .= "```json\n";
                $doc .= json_encode($this->generateExamplePayload($key), JSON_PRETTY_PRINT) . "\n";
                $doc .= "```\n\n";
            }
        }
        
        return $doc;
    }
    
    /**
     * Group endpoints by category
     */
    private function groupEndpointsByCategory() {
        $grouped = [
            'Zapier Integration' => [],
            'IFTTT Integration' => [],
            'Platform Webhooks' => [],
            'Content Management' => [],
            'Analytics & Reporting' => [],
            'System & Security' => [],
            'User & Team Management' => []
        ];
        
        foreach ($this->webhook_config as $key => $config) {
            if (strpos($key, 'zapier_') === 0) {
                $grouped['Zapier Integration'][$key] = $config;
            } elseif (strpos($key, 'ifttt_') === 0) {
                $grouped['IFTTT Integration'][$key] = $config;
            } elseif (strpos($key, 'platform_') === 0 || strpos($key, '_webhook') !== false) {
                $grouped['Platform Webhooks'][$key] = $config;
            } elseif (strpos($key, 'content_') === 0 || strpos($key, 'media_') === 0 || strpos($key, 'template_') === 0) {
                $grouped['Content Management'][$key] = $config;
            } elseif (strpos($key, 'analytics_') === 0 || strpos($key, 'report_') === 0 || strpos($key, 'insights_') === 0) {
                $grouped['Analytics & Reporting'][$key] = $config;
            } elseif (strpos($key, 'system_') === 0 || strpos($key, 'security_') === 0) {
                $grouped['System & Security'][$key] = $config;
            } elseif (strpos($key, 'user_') === 0 || strpos($key, 'team_') === 0) {
                $grouped['User & Team Management'][$key] = $config;
            }
        }
        
        // Remove empty categories
        return array_filter($grouped, function($category) {
            return !empty($category);
        });
    }
    
    /**
     * Generate example payload
     */
    private function generateExamplePayload($endpoint_key) {
        $base_payload = [
            'timestamp' => date('c'),
            'event_type' => $endpoint_key,
            'source' => 'smo-social',
            'version' => '2.0'
        ];
        
        // Add specific payload data based on endpoint
        switch ($endpoint_key) {
            case 'zapier_post_created':
                $base_payload['data'] = [
                    'post_id' => 12345,
                    'title' => 'Example Post Title',
                    'content' => 'Post content here...',
                    'platforms' => ['facebook', 'twitter'],
                    'scheduled_time' => null,
                    'author' => 'John Doe'
                ];
                break;
                
            case 'ifttt_engagement_alert':
                $base_payload['data'] = [
                    'post_id' => 12345,
                    'platform' => 'facebook',
                    'metric_type' => 'likes',
                    'threshold' => 100,
                    'current_value' => 105,
                    'post_url' => 'https://facebook.com/post/12345'
                ];
                break;
                
            case 'security_alert':
                $base_payload['data'] = [
                    'alert_type' => 'multiple_failed_logins',
                    'severity' => 'high',
                    'ip_address' => '192.168.1.100',
                    'user_attempted' => 'admin',
                    'attempts' => 5,
                    'timestamp' => date('c')
                ];
                break;
                
            default:
                $base_payload['data'] = [
                    'message' => 'Example webhook data',
                    'additional_info' => 'This would contain specific data for the event'
                ];
        }
        
        return $base_payload;
    }
    
    /**
     * Build integration guides
     */
    private function buildIntegrationGuides() {
        $doc = "## 🔧 Integration Guides\n\n";
        
        $doc .= "### Zapier Integration\n\n";
        $doc .= "1. Create a new Zap in Zapier\n";
        $doc .= "2. Choose 'Webhooks by Zapier' as the trigger\n";
        $doc .= "3. Select 'Catch Hook'\n";
        $doc .= "4. Use the Zapier webhook URLs provided above\n";
        $doc .= "5. Configure actions based on webhook events\n\n";
        
        $doc .= "### IFTTT Integration\n\n";
        $doc .= "1. Create a new Applet in IFTTT\n";
        $doc .= "2. Choose 'Webhooks' as the trigger service\n";
        $doc .= "3. Select 'Receive a web request'\n";
        $doc .= "4. Use the IFTTT webhook URLs provided above\n";
        $doc .= "5. Configure the action service for each event\n\n";
        
        $doc .= "### Custom Integration Example\n\n";
        $doc .= "```javascript\n";
        $doc .= "// JavaScript example for consuming webhooks\n";
        $doc .= "fetch('{$this->webhook_config['zapier_post_created']['url']}', {\n";
        $doc .= "    method: 'POST',\n";
        $doc .= "    headers: {\n";
        $doc .= "        'Content-Type': 'application/json',\n";
        $doc .= "        'X-SMO-Signature': 'your-computed-signature'\n";
        $doc .= "    },\n";
        $doc .= "    body: JSON.stringify({\n";
        $doc .= "        event: 'post.created',\n";
        $doc .= "        data: { /* your data */ }\n";
        $doc .= "    })\n";
        $doc .= "});\n";
        $doc .= "```\n\n";
        
        return $doc;
    }
    
    /**
     * Generate webhook testing script
     */
    public function generateWebhookTestingScript() {
        echo "🧪 Generating webhook testing script...\n\n";
        
        $test_script = $this->buildWebhookTestingScript();
        $test_file = 'webhook_testing_production.php';
        file_put_contents($test_file, $test_script);
        
        echo "✅ Webhook testing script created: {$test_file}\n";
        echo "📝 Run tests with: php {$test_file}\n\n";
        
        return $test_file;
    }
    
    /**
     * Build webhook testing script
     */
    private function buildWebhookTestingScript() {
        $script = "<?php\n";
        $script .= "/**\n";
        $script .= " * SMO Social Production Webhook Testing Script\n";
        $script .= " */\n\n";
        
        $script .= "class WebhookTester {\n";
        $script .= "    private \$webhook_secret;\n";
        $script .= "    private \$base_url;\n\n";
        
        $script .= "    public function __construct() {\n";
        $script .= "        \$this->webhook_secret = '{$this->webhook_secret}';\n";
        $script .= "        \$this->base_url = '{$this->production_url}';\n";
        $script .= "    }\n\n";
        
        // Add test methods for each webhook
        foreach ($this->webhook_config as $key => $config) {
            $script .= $this->buildTestMethod($key, $config);
        }
        
        $script .= "    public function runAllTests() {\n";
        $script .= "        echo \"Testing all webhook endpoints...\\n\";\n";
        
        foreach ($this->webhook_config as $key => $config) {
            $method_name = str_replace('_', '', ucwords($key, '_')) . 'Test';
            $script .= "        \$this->{$method_name}();\n";
        }
        
        $script .= "    }\n";
        $script .= "}\n\n";
        
        $script .= "// Run tests\n";
        $script .= "if (php_sapi_name() === 'cli') {\n";
        $script .= "    \$tester = new WebhookTester();\n";
        $script .= "    \$tester->runAllTests();\n";
        $script .= "}\n";
        
        return $script;
    }
    
    /**
     * Build test method for webhook
     */
    private function buildTestMethod($key, $config) {
        $method_name = str_replace('_', '', ucwords($key, '_')) . 'Test';
        
        $method = "    public function {$method_name}() {\n";
        $method .= "        \$url = '{$config['url']}';\n";
        $method .= "        \$payload = " . var_export($this->generateExamplePayload($key), true) . ";\n\n";
        
        if ($config['signature_validation']) {
            $method .= "        // Add signature for security\n";
            $method .= "        \$payload['signature'] = hash_hmac('sha256', json_encode(\$payload), \$this->webhook_secret);\n\n";
        }
        
        $method .= "        \$ch = curl_init();\n";
        $method .= "        curl_setopt(\$ch, CURLOPT_URL, \$url);\n";
        $method .= "        curl_setopt(\$ch, CURLOPT_POST, true);\n";
        $method .= "        curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$payload));\n";
        $method .= "        curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\n";
        $method .= "        curl_setopt(\$ch, CURLOPT_HTTPHEADER, [\n";
        $method .= "            'Content-Type: application/json',\n";
        if ($config['signature_validation']) {
            $method .= "            'X-SMO-Signature: ' . hash_hmac('sha256', json_encode(\$payload), \$this->webhook_secret)\n";
        }
        $method .= "        ]);\n\n";
        
        $method .= "        \$response = curl_exec(\$ch);\n";
        $method .= "        \$http_code = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);\n";
        $method .= "        curl_close(\$ch);\n\n";
        
        $method .= "        if (\$http_code >= 200 && \$http_code < 300) {\n";
        $method .= "            echo \"✅ {$key}: Success (HTTP {\$http_code})\\n\";\n";
        $method .= "        } else {\n";
        $method .= "            echo \"❌ {$key}: Failed (HTTP {\$http_code})\\n\";\n";
        $method .= "            echo \"Response: {\$response}\\n\";\n";
        $method .= "        }\n";
        $method .= "    }\n\n";
        
        return $method;
    }
    
    /**
     * Generate Zapier configuration file
     */
    public function generateZapierConfig() {
        echo "⚡ Generating Zapier configuration file...\n\n";
        
        $zapier_config = $this->buildZapierConfig();
        $config_file = 'zapier_webhook_config.json';
        file_put_contents($config_file, $zapier_config);
        
        echo "✅ Zapier configuration created: {$config_file}\n\n";
        
        return $config_file;
    }
    
    /**
     * Build Zapier configuration
     */
    private function buildZapierConfig() {
        $zapier_config = [
            'webhook_urls' => [
                'post_created' => $this->webhook_config['zapier_post_created']['url'],
                'post_scheduled' => $this->webhook_config['zapier_post_scheduled']['url'],
                'analytics_update' => $this->webhook_config['zapier_analytics_update']['url'],
                'content_imported' => $this->webhook_config['zapier_content_imported']['url']
            ],
            'webhook_secret' => $this->webhook_secret,
            'supported_events' => [
                'post.created',
                'post.published', 
                'post.scheduled',
                'post.rescheduled',
                'analytics.updated',
                'metrics.completed',
                'content.imported',
                'media.uploaded'
            ],
            'sample_payloads' => [
                'post_created' => $this->generateExamplePayload('zapier_post_created'),
                'analytics_update' => $this->generateExamplePayload('zapier_analytics_update')
            ]
        ];
        
        return json_encode($zapier_config, JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate IFTTT configuration file
     */
    public function generateIFTTTConfig() {
        echo "🎯 Generating IFTTT configuration file...\n\n";
        
        $ifttt_config = $this->buildIFTTTConfig();
        $config_file = 'ifttt_webhook_config.json';
        file_put_contents($config_file, $ifttt_config);
        
        echo "✅ IFTTT configuration created: {$config_file}\n\n";
        
        return $config_file;
    }
    
    /**
     * Build IFTTT configuration
     */
    private function buildIFTTTConfig() {
        $ifttt_config = [
            'webhook_urls' => [
                'post_published' => $this->webhook_config['ifttt_post_published']['url'],
                'engagement_alert' => $this->webhook_config['ifttt_engagement_alert']['url'],
                'scheduled_post' => $this->webhook_config['ifttt_scheduled_post']['url']
            ],
            'webhook_secret' => $this->webhook_secret,
            'event_names' => [
                'post_published' => 'smo_post_published',
                'engagement_alert' => 'smo_engagement_alert',
                'scheduled_post' => 'smo_scheduled_post'
            ],
            'sample_webhook_data' => [
                'post_published' => [
                    'value1' => 'Post Published',
                    'value2' => 'Facebook',
                    'value3' => 'https://facebook.com/post/12345'
                ],
                'engagement_alert' => [
                    'value1' => 'High Engagement',
                    'value2' => '105 likes',
                    'value3' => 'Post exceeded threshold'
                ]
            ]
        ];
        
        return json_encode($ifttt_config, JSON_PRETTY_PRINT);
    }
    
    /**
     * Run complete webhook setup
     */
    public function runSetup() {
        $this->initialize();
        $doc_file = $this->generateWebhookDocumentation();
        $test_file = $this->generateWebhookTestingScript();
        $zapier_file = $this->generateZapierConfig();
        $ifttt_file = $this->generateIFTTTConfig();
        
        echo "\n🎯 WEBHOOK SETUP COMPLETE!\n";
        echo "==========================\n\n";
        echo "📚 Documentation: {$doc_file}\n";
        echo "🧪 Testing Script: {$test_file}\n";
        echo "⚡ Zapier Config: {$zapier_file}\n";
        echo "🎯 IFTTT Config: {$ifttt_file}\n\n";
        
        echo "🔧 NEXT STEPS:\n";
        echo "1. Review documentation: {$doc_file}\n";
        echo "2. Configure Zapier with URLs from: {$zapier_file}\n";
        echo "3. Configure IFTTT with event names from: {$ifttt_file}\n";
        echo "4. Test webhooks with: php {$test_file}\n";
        echo "5. Update third-party services with production URLs\n";
        echo "6. Monitor webhook delivery in production logs\n\n";
        
        echo "🔗 Production webhook endpoints are ready for use!\n";
    }
}

// Run the setup
if (php_sapi_name() === 'cli') {
    $config = new ProductionWebhookConfig();
    $config->runSetup();
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php production_webhook_config.php\n";
}