<?php
/**
 * SMO Social - Production API Keys Configuration Script
 * 
 * This script helps configure production API keys for all integrated services.
 * Run this script after deploying to production environment.
 * 
 * Usage: php production_api_keys_setup.php
 */

class ProductionAPIKeysSetup {
    
    private $production_credentials = [];
    private $config_file = 'production_wp_config.php';
    private $validation_results = [];
    
    public function __construct() {
        echo "🚀 SMO Social Production API Keys Setup\n";
        echo "=======================================\n\n";
    }
    
    /**
     * Initialize production configuration
     */
    public function initialize() {
        $this->loadProductionCredentials();
        $this->displayInstructions();
    }
    
    /**
     * Load production credential template
     */
    private function loadProductionCredentials() {
        $this->production_credentials = [
            // Social Media Platforms
            'FACEBOOK_APP_ID' => [
                'label' => 'Facebook App ID',
                'required' => true,
                'description' => 'Your Facebook App ID for posting to Facebook pages'
            ],
            'FACEBOOK_APP_SECRET' => [
                'label' => 'Facebook App Secret',
                'required' => true,
                'description' => 'Your Facebook App Secret for authentication'
            ],
            'FACEBOOK_PAGE_ACCESS_TOKEN' => [
                'label' => 'Facebook Page Access Token',
                'required' => true,
                'description' => 'Long-lived access token for Facebook pages'
            ],
            
            // Instagram API
            'INSTAGRAM_APP_ID' => [
                'label' => 'Instagram App ID',
                'required' => true,
                'description' => 'Your Instagram App ID for API access'
            ],
            'INSTAGRAM_APP_SECRET' => [
                'label' => 'Instagram App Secret',
                'required' => true,
                'description' => 'Your Instagram App Secret'
            ],
            'INSTAGRAM_ACCESS_TOKEN' => [
                'label' => 'Instagram Access Token',
                'required' => true,
                'description' => 'Your Instagram API access token'
            ],
            
            // Twitter/X API
            'TWITTER_API_KEY' => [
                'label' => 'Twitter API Key',
                'required' => true,
                'description' => 'Your Twitter API Key (v2)'
            ],
            'TWITTER_API_SECRET' => [
                'label' => 'Twitter API Secret',
                'required' => true,
                'description' => 'Your Twitter API Secret'
            ],
            'TWITTER_ACCESS_TOKEN' => [
                'label' => 'Twitter Access Token',
                'required' => true,
                'description' => 'Your Twitter Access Token'
            ],
            'TWITTER_ACCESS_TOKEN_SECRET' => [
                'label' => 'Twitter Access Token Secret',
                'required' => true,
                'description' => 'Your Twitter Access Token Secret'
            ],
            
            // LinkedIn API
            'LINKEDIN_CLIENT_ID' => [
                'label' => 'LinkedIn Client ID',
                'required' => true,
                'description' => 'Your LinkedIn API Client ID'
            ],
            'LINKEDIN_CLIENT_SECRET' => [
                'label' => 'LinkedIn Client Secret',
                'required' => true,
                'description' => 'Your LinkedIn API Client Secret'
            ],
            
            // YouTube API
            'YOUTUBE_CLIENT_ID' => [
                'label' => 'YouTube Client ID',
                'required' => true,
                'description' => 'Your YouTube API Client ID'
            ],
            'YOUTUBE_CLIENT_SECRET' => [
                'label' => 'YouTube Client Secret',
                'required' => true,
                'description' => 'Your YouTube API Client Secret'
            ],
            
            // Pinterest API
            'PINTEREST_APP_ID' => [
                'label' => 'Pinterest App ID',
                'required' => true,
                'description' => 'Your Pinterest App ID'
            ],
            'PINTEREST_APP_SECRET' => [
                'label' => 'Pinterest App Secret',
                'required' => true,
                'description' => 'Your Pinterest App Secret'
            ],
            
            // TikTok API
            'TIKTOK_CLIENT_KEY' => [
                'label' => 'TikTok Client Key',
                'required' => true,
                'description' => 'Your TikTok API Client Key'
            ],
            'TIKTOK_CLIENT_SECRET' => [
                'label' => 'TikTok Client Secret',
                'required' => true,
                'description' => 'Your TikTok API Client Secret'
            ],
            
            // Content Services (No OAuth required)
            'UNSPLASH_ACCESS_KEY' => [
                'label' => 'Unsplash Access Key',
                'required' => true,
                'description' => 'Your Unsplash API Access Key for stock photos'
            ],
            'PIXABAY_API_KEY' => [
                'label' => 'Pixabay API Key',
                'required' => true,
                'description' => 'Your Pixabay API Key for stock photos and videos'
            ],
            
            // Cloud Storage & Content Import
            'GOOGLE_DRIVE_CLIENT_ID' => [
                'label' => 'Google Drive Client ID',
                'required' => true,
                'description' => 'Your Google Drive Client ID for file import'
            ],
            'GOOGLE_DRIVE_CLIENT_SECRET' => [
                'label' => 'Google Drive Client Secret',
                'required' => true,
                'description' => 'Your Google Drive Client Secret'
            ],
            'DROPBOX_APP_KEY' => [
                'label' => 'Dropbox App Key',
                'required' => true,
                'description' => 'Your Dropbox App Key for file import'
            ],
            'DROPBOX_APP_SECRET' => [
                'label' => 'Dropbox App Secret',
                'required' => true,
                'description' => 'Your Dropbox App Secret'
            ],
            'CANVA_CLIENT_ID' => [
                'label' => 'Canva Client ID',
                'required' => true,
                'description' => 'Your Canva API Client ID for design import'
            ],
            'CANVA_CLIENT_SECRET' => [
                'label' => 'Canva Client Secret',
                'required' => true,
                'description' => 'Your Canva API Client Secret'
            ]
        ];
    }
    
    /**
     * Display setup instructions
     */
    private function displayInstructions() {
        echo "📋 SETUP INSTRUCTIONS\n";
        echo "====================\n\n";
        echo "1. This script will help you configure production API keys\n";
        echo "2. You'll need to obtain API credentials from each service:\n";
        echo "   - Facebook/Meta for Facebook and Instagram\n";
        echo "   - Twitter/X for Twitter API v2\n";
        echo "   - LinkedIn for LinkedIn API\n";
        echo "   - Google Cloud Console for YouTube and Google Drive\n";
        echo "   - Pinterest Developers for Pinterest API\n";
        echo "   - TikTok for Developers for TikTok API\n";
        echo "   - Unsplash and Pixabay for stock content\n";
        echo "   - Dropbox, Google Drive, and Canva for content import\n\n";
        echo "3. Production URLs you need to register:\n";
        echo "   - Site URL: " . $this->getProductionSiteUrl() . "\n";
        echo "   - Redirect URIs: " . $this->getProductionRedirectUris() . "\n\n";
    }
    
    /**
     * Get production site URL
     */
    private function getProductionSiteUrl() {
        // In production, this would be the actual site URL
        $site_url = $_ENV['SITE_URL'] ?? 'https://your-domain.com';
        return $site_url;
    }
    
    /**
     * Get production redirect URIs
     */
    private function getProductionRedirectUris() {
        $site_url = $this->getProductionSiteUrl();
        return [
            $site_url . '/wp-admin/admin.php?page=smo-oauth-callback',
            $site_url . '/wp-json/smo-social/v1/oauth/callback'
        ];
    }
    
    /**
     * Generate production wp-config template
     */
    public function generateProductionConfig() {
        echo "🔧 Generating production wp-config template...\n\n";
        
        $config_template = $this->buildConfigTemplate();
        
        // Save the template
        file_put_contents($this->config_file, $config_template);
        
        echo "✅ Production configuration template created: {$this->config_file}\n";
        echo "📝 Edit this file with your actual production API keys\n\n";
        
        return $this->config_file;
    }
    
    /**
     * Build the wp-config template
     */
    private function buildConfigTemplate() {
        $template = "<?php\n";
        $template .= "/**\n";
        $template .= " * SMO Social Production Configuration\n";
        $template .= " * \n";
        $template .= " * This file contains all production API credentials.\n";
        $template .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
        $template .= " */\n\n";
        
        $template .= "// Production Environment Settings\n";
        $template .= "define('SMO_ENVIRONMENT', 'production');\n";
        $template .= "define('SMO_DEBUG_MODE', false);\n";
        $template .= "define('SMO_API_TIMEOUT', 30);\n";
        $template .= "define('SMO_MAX_RETRIES', 3);\n\n";
        
        $template .= "// ======================================\n";
        $template .= "// SOCIAL MEDIA PLATFORM API CREDENTIALS\n";
        $template .= "// ======================================\n\n";
        
        foreach ($this->production_credentials as $key => $config) {
            $template .= "// {$config['label']}\n";
            $template .= "// {$config['description']}\n";
            if ($config['required']) {
                $template .= "// 🔴 REQUIRED - Replace 'YOUR_{$key}_HERE' with actual credentials\n";
            }
            $template .= "define('{$key}', 'YOUR_{$key}_HERE');\n\n";
        }
        
        $template .= "// ======================================\n";
        $template .= "// PRODUCTION SECURITY SETTINGS\n";
        $template .= "// ======================================\n\n";
        
        $template .= "// API Security\n";
        $template .= "define('SMO_API_ENCRYPTION_KEY', '" . $this->generateEncryptionKey() . "');\n";
        $template .= "define('SMO_WEBHOOK_SECRET', '" . $this->generateWebhookSecret() . "');\n\n";
        
        $template .= "// Rate Limiting (requests per hour per user)\n";
        $template .= "define('SMO_RATE_LIMIT_API', 1000);\n";
        $template .= "define('SMO_RATE_LIMIT_OAUTH', 50);\n";
        $template .= "define('SMO_RATE_LIMIT_WEBHOOK', 500);\n\n";
        
        $template .= "// Cache Settings\n";
        $template .= "define('SMO_CACHE_ENABLED', true);\n";
        $template .= "define('SMO_CACHE_DURATION', 3600);\n\n";
        
        $template .= "// Database Optimization\n";
        $template .= "define('SMO_DB_OPTIMIZATION', true);\n";
        $template .= "define('SMO_QUERY_CACHE', true);\n\n";
        
        $template .= "// Monitoring & Alerts\n";
        $template .= "define('SMO_MONITORING_ENABLED', true);\n";
        $template .= "define('SMO_ALERT_EMAIL', 'admin@your-domain.com');\n";
        $template .= "define('SMO_ERROR_REPORTING', true);\n\n";
        
        $template .= "// Backup Settings\n";
        $template .= "define('SMO_AUTO_BACKUP', true);\n";
        $template .= "define('SMO_BACKUP_FREQUENCY', 'daily');\n";
        $template .= "define('SMO_BACKUP_RETENTION', 30);\n\n";
        
        return $template;
    }
    
    /**
     * Generate encryption key
     */
    private function generateEncryptionKey() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generate webhook secret
     */
    private function generateWebhookSecret() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Validate configuration
     */
    public function validateConfiguration() {
        echo "🔍 Validating production configuration...\n\n";
        
        $this->validation_results = [];
        
        // Check if config file exists
        if (!file_exists($this->config_file)) {
            $this->addValidationError("Configuration file not found: {$this->config_file}");
            return false;
        }
        
        // Validate each required credential
        $config_content = file_get_contents($this->config_file);
        
        foreach ($this->production_credentials as $key => $config) {
            if ($config['required']) {
                $pattern = "/define\('{$key}', 'YOUR_{$key}_HERE'\)/";
                if (preg_match($pattern, $config_content)) {
                    $this->addValidationWarning("{$config['label']} still has placeholder value");
                } else {
                    $this->addValidationSuccess("{$config['label']} is configured");
                }
            }
        }
        
        $this->displayValidationResults();
        return true;
    }
    
    /**
     * Add validation error
     */
    private function addValidationError($message) {
        $this->validation_results[] = ['type' => 'error', 'message' => $message];
    }
    
    /**
     * Add validation warning
     */
    private function addValidationWarning($message) {
        $this->validation_results[] = ['type' => 'warning', 'message' => $message];
    }
    
    /**
     * Add validation success
     */
    private function addValidationSuccess($message) {
        $this->validation_results[] = ['type' => 'success', 'message' => $message];
    }
    
    /**
     * Display validation results
     */
    private function displayValidationResults() {
        echo "📊 VALIDATION RESULTS\n";
        echo "====================\n\n";
        
        $errors = 0;
        $warnings = 0;
        $successes = 0;
        
        foreach ($this->validation_results as $result) {
            switch ($result['type']) {
                case 'error':
                    $errors++;
                    echo "❌ ERROR: {$result['message']}\n";
                    break;
                case 'warning':
                    $warnings++;
                    echo "⚠️  WARNING: {$result['message']}\n";
                    break;
                case 'success':
                    $successes++;
                    echo "✅ SUCCESS: {$result['message']}\n";
                    break;
            }
        }
        
        echo "\n📈 SUMMARY\n";
        echo "==========\n";
        echo "Errors: {$errors}\n";
        echo "Warnings: {$warnings}\n";
        echo "Successes: {$successes}\n";
        echo "Total Checks: " . count($this->validation_results) . "\n\n";
        
        if ($errors === 0) {
            echo "🎉 Configuration validation completed!\n";
            if ($warnings > 0) {
                echo "⚠️  Please address the warnings above.\n";
            } else {
                echo "✅ All required configurations are properly set!\n";
            }
        }
    }
    
    /**
     * Generate API registration guide
     */
    public function generateAPIRegistrationGuide() {
        echo "📚 Generating API registration guide...\n\n";
        
        $guide = $this->buildAPIRegistrationGuide();
        
        $guide_file = 'PRODUCTION_API_REGISTRATION_GUIDE.md';
        file_put_contents($guide_file, $guide);
        
        echo "✅ API registration guide created: {$guide_file}\n\n";
        
        return $guide_file;
    }
    
    /**
     * Build API registration guide
     */
    private function buildAPIRegistrationGuide() {
        $guide = "# SMO Social Production API Registration Guide\n\n";
        $guide .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $guide .= "## 📋 Overview\n\n";
        $guide .= "This guide will walk you through registering your application with each service to obtain production API credentials.\n\n";
        
        $guide .= "## 🌐 Production URLs\n\n";
        $guide .= "Before you start, make sure you have these URLs ready:\n\n";
        $guide .= "- **Site URL**: `{$this->getProductionSiteUrl()}`\n";
        $guide .= "- **Redirect URIs**:\n";
        foreach ($this->getProductionRedirectUris() as $uri) {
            $guide .= "  - `{$uri}`\n";
        }
        $guide .= "\n";
        
        // Add specific instructions for each service
        $guide .= $this->buildServiceRegistrationInstructions();
        
        return $guide;
    }
    
    /**
     * Build service registration instructions
     */
    private function buildServiceRegistrationInstructions() {
        $instructions = "## 🔧 Service Registration Instructions\n\n";
        
        $instructions .= "### Facebook & Instagram (Meta)\n";
        $instructions .= "1. Go to [Facebook Developers](https://developers.facebook.com/)\n";
        $instructions .= "2. Create a new app or use existing one\n";
        $instructions .= "3. Add Products: Facebook Login, Instagram Basic Display\n";
        $instructions .= "4. Configure Valid OAuth Redirect URIs with the production URLs above\n";
        $instructions .= "5. Get App ID, App Secret, and generate Page Access Token\n";
        $instructions .= "6. Required permissions: pages_manage_posts, pages_read_engagement, instagram_basic\n\n";
        
        $instructions .= "### Twitter/X API v2\n";
        $instructions .= "1. Go to [Twitter Developer Portal](https://developer.twitter.com/)\n";
        $instructions .= "2. Apply for developer access\n";
        $instructions .= "3. Create a new app\n";
        $instructions .= "4. Go to Keys and Tokens tab\n";
        $instructions .= "5. Generate API Key, API Secret, Access Token, Access Token Secret\n";
        $instructions .= "6. Set app permissions to Read/Write\n\n";
        
        $instructions .= "### LinkedIn API\n";
        $instructions .= "1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)\n";
        $instructions .= "2. Create a new app\n";
        $instructions .= "3. Add products: Marketing Developer Platform\n";
        $instructions .= "4. Configure OAuth 2.0 redirect URLs with production URLs\n";
        $instructions .= "5. Request access to required products\n";
        $instructions .= "6. Get Client ID and Client Secret\n\n";
        
        $instructions .= "### YouTube & Google Drive APIs\n";
        $instructions .= "1. Go to [Google Cloud Console](https://console.cloud.google.com/)\n";
        $instructions .= "2. Create a new project or select existing\n";
        $instructions .= "3. Enable YouTube Data API v3 and Google Drive API\n";
        $instructions .= "4. Create OAuth 2.0 credentials\n";
        $instructions .= "5. Configure authorized redirect URIs with production URLs\n";
        $instructions .= "6. Get Client ID and Client Secret\n\n";
        
        $instructions .= "### Pinterest API\n";
        $instructions .= "1. Go to [Pinterest Developers](https://developers.pinterest.com/)\n";
        $instructions .= "2. Create a new app\n";
        $instructions .= "3. Configure app details and permissions\n";
        $instructions .= "4. Set redirect URI to production site URL\n";
        $instructions .= "5. Get App ID and App Secret\n\n";
        
        $instructions .= "### TikTok API\n";
        $instructions .= "1. Go to [TikTok for Developers](https://developers.tiktok.com/)\n";
        $instructions .= "2. Create a new app\n";
        $instructions .= "3. Apply for API access\n";
        $instructions .= "4. Configure redirect URIs\n";
        $instructions .= "5. Get Client Key and Client Secret\n\n";
        
        $instructions .= "### Content Services\n\n";
        $instructions .= "#### Unsplash\n";
        $instructions .= "1. Go to [Unsplash Developers](https://unsplash.com/developers)\n";
        $instructions .= "2. Create a new app\n";
        $instructions .= "3. Get Access Key from app settings\n\n";
        
        $instructions .= "#### Pixabay\n";
        $instructions .= "1. Go to [Pixabay API](https://pixabay.com/api/docs/)\n";
        $instructions .= "2. Get free API key or register for production key\n\n";
        
        $instructions .= "### Cloud Storage Services\n\n";
        $instructions .= "#### Dropbox\n";
        $instructions .= "1. Go to [Dropbox App Console](https://www.dropbox.com/developers/apps)\n";
        $instructions .= "2. Create a new app\n";
        $instructions .= "3. Choose appropriate access type\n";
        $instructions .= "4. Configure redirect URI\n";
        $instructions .= "5. Get App Key and App Secret\n\n";
        
        $instructions .= "#### Canva\n";
        $instructions .= "1. Go to [Canva Developers](https://www.canva.com/developers/)\n";
        $instructions .= "2. Create a new app\n";
        $instructions .= "3. Configure OAuth settings\n";
        $instructions .= "4. Get Client ID and Client Secret\n\n";
        
        return $instructions;
    }
    
    /**
     * Run the complete setup process
     */
    public function runSetup() {
        $this->initialize();
        $config_file = $this->generateProductionConfig();
        $this->validateConfiguration();
        $guide_file = $this->generateAPIRegistrationGuide();
        
        echo "\n🎯 NEXT STEPS\n";
        echo "=============\n\n";
        echo "1. Review and edit: {$config_file}\n";
        echo "2. Follow the guide: {$guide_file}\n";
        echo "3. Register your app with each service\n";
        echo "4. Replace placeholder values in the config file\n";
        echo "5. Run validation again to confirm setup\n";
        echo "6. Upload config file to production server\n\n";
        
        echo "🚀 Production API Keys Setup Complete!\n";
    }
}

// Run the setup
if (php_sapi_name() === 'cli') {
    $setup = new ProductionAPIKeysSetup();
    $setup->runSetup();
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php production_api_keys_setup.php\n";
}