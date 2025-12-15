<?php
/**
 * Temporary script to set HuggingFace API key
 * This script will set the HuggingFace API key in the WordPress options
 */

// Check if we're in WordPress context or standalone mode
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        'wp-load.php',
        '../wp-load.php',
        '../../wp-load.php',
        '../../../wp-load.php'
    ];
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            break;
        }
    }
    
    // If still not loaded, try standalone mode with the plugin
    if (!defined('ABSPATH')) {
        // Load the plugin in standalone mode
        require_once('smo-social.php');
        echo "🔧 Loaded SMO Social plugin in standalone mode\n";
    }
}

// Initialize database if in standalone mode and not already initialized
if (!defined('SMO_SOCIAL_STANDALONE') && defined('ABSPATH')) {
    // Try to load the database manager for standalone mode
    if (file_exists('includes/Core/DatabaseManager.php')) {
        require_once('includes/Core/DatabaseManager.php');
        if (class_exists('SMO_Social\\Core\\DatabaseManager')) {
            // Database should be initialized through the plugin activation
            echo "🔧 DatabaseManager loaded\n";
        }
    }
}

// The API key was removed for security
// $huggingface_api_key = '[REMOVED_API_KEY]';

// Set the API key in WordPress options
$result = update_option('smo_social_huggingface_api_key', $huggingface_api_key);

if ($result) {
    echo "✅ Successfully set HuggingFace API key!\n";
    echo "API Key: " . substr($huggingface_api_key, 0, 8) . "..." . substr($huggingface_api_key, -8) . "\n";
    
    // Verify it was saved correctly
    $saved_key = get_option('smo_social_huggingface_api_key', '');
    if ($saved_key === $huggingface_api_key) {
        echo "✅ Verification: API key saved correctly\n";
    } else {
        echo "❌ Verification: API key may not have been saved correctly\n";
    }
    
    // Test if the AI system can access it
    echo "\n🔍 Testing AI System Integration:\n";
    
    // Check if AI Manager exists
    if (class_exists('SMO_Social\AI\Manager')) {
        echo "✅ AI Manager class found\n";
        
        // Try to get the AI manager instance
        try {
            $ai_manager = \SMO_Social\AI\Manager::getInstance();
            echo "✅ AI Manager instance created successfully\n";
            
            // Check if HuggingFace manager is available
            $reflection = new ReflectionClass($ai_manager);
            $huggingface_property = $reflection->getProperty('huggingface_manager');
            $huggingface_property->setAccessible(true);
            $hf_manager = $huggingface_property->getValue($ai_manager);
            
            if ($hf_manager) {
                echo "✅ HuggingFace manager is available\n";
                
                // Try to get the API settings
                $settings_property = $reflection->getProperty('api_settings');
                $settings_property->setAccessible(true);
                $api_settings = $settings_property->getValue($ai_manager);
                
                if (isset($api_settings['api_keys']['huggingface_api_key'])) {
                    $stored_key = $api_settings['api_keys']['huggingface_api_key'];
                    if ($stored_key === $huggingface_api_key) {
                        echo "✅ HuggingFace API key is properly configured in AI Manager\n";
                    } else {
                        echo "⚠️  HuggingFace API key in AI Manager doesn't match: " . substr($stored_key, 0, 8) . "...\n";
                    }
                } else {
                    echo "⚠️  HuggingFace API key not found in AI Manager settings\n";
                }
            } else {
                echo "❌ HuggingFace manager is not available\n";
            }
        } catch (Exception $e) {
            echo "❌ Error creating AI Manager instance: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ AI Manager class not found\n";
    }
    
    echo "\n🚀 The HuggingFace API key has been successfully configured!\n";
    echo "You can now use the AI chat functionality.\n";
    
} else {
    echo "❌ Failed to set HuggingFace API key\n";
    echo "This might be due to insufficient permissions or database issues.\n";
}

echo "\n📋 Next Steps:\n";
echo "1. Visit your WordPress admin panel\n";
echo "2. Go to SMO Social → Settings → AI Providers\n";
echo "3. Verify that HuggingFace shows as 'Connected'\n";
echo "4. Test the AI chat functionality\n";
