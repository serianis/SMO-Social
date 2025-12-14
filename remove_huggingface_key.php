<?php
/**
 * HuggingFace API Key Removal Script
 * This script removes the HuggingFace API key from your local configuration
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
            echo "🔧 Loaded WordPress environment\n";
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

echo "🗑️  Starting HuggingFace API Key Removal Process\n";
echo "===============================================\n\n";

$api_key = 'hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

// Step 1: Remove from WordPress options (if available)
if (function_exists('get_option')) {
    echo "Step 1: Removing API key from WordPress options...\n";
    
    // Check if the key exists
    $existing_key = get_option('smo_social_huggingface_api_key', false);
    
    if ($existing_key === $api_key) {
        $result = delete_option('smo_social_huggingface_api_key');
        if ($result) {
            echo "✅ Successfully removed API key from WordPress options\n";
        } else {
            echo "❌ Failed to remove API key from WordPress options\n";
        }
    } elseif ($existing_key === false) {
        echo "ℹ️  API key was not found in WordPress options\n";
    } else {
        echo "⚠️  Different API key found in WordPress options: " . substr($existing_key, 0, 8) . "...\n";
        echo "   Manual removal may be required\n";
    }
} else {
    echo "⚠️  WordPress functions not available, skipping WordPress options removal\n";
}

// Step 2: Clean up configuration file
echo "\nStep 2: Cleaning up configuration file...\n";
$config_file = __DIR__ . '/.smo-social-config.php';

if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    
    // Check if the API key is referenced in the config
    if (strpos($config_content, $api_key) !== false || strpos($config_file, 'SMO_SOCIAL_HF_API_KEY') !== false) {
        // Update the config to use empty environment variable
        $new_config = "<?php
/**
 * SMO Social Configuration
 * 
 * SECURITY NOTICE: Never commit API keys to version control!
 * Use environment variables or WordPress options instead.
 */

// Secure configuration using environment variables
return array (
  'smo_social_huggingface_api_key' => getenv('SMO_SOCIAL_HF_API_KEY') ?: '',
  'smo_social_environment' => getenv('SMO_SOCIAL_ENV') ?: 'production',
  
  // Additional secure configuration options
  'smo_social_debug_mode' => getenv('SMO_SOCIAL_DEBUG') === 'true' ? true : false,
  'smo_social_log_level' => getenv('SMO_SOCIAL_LOG_LEVEL') ?: 'error',
  
  // TODO: Migrate other sensitive configurations to environment variables
  // 'smo_social_database_encryption_key' => getenv('SMO_SOCIAL_DB_ENCRYPTION_KEY'),
  // 'smo_social_oauth_client_secret' => getenv('SMO_SOCIAL_OAUTH_CLIENT_SECRET'),
);";
        
        if (file_put_contents($config_file, $new_config)) {
            echo "✅ Configuration file updated to remove API key reference\n";
        } else {
            echo "❌ Failed to update configuration file\n";
        }
    } else {
        echo "ℹ️  Configuration file already clean\n";
    }
} else {
    echo "ℹ️  Configuration file not found\n";
}

// Step 3: Clean up hardcoded references
echo "\nStep 3: Cleaning up hardcoded API key references...\n";

$files_to_clean = [
    'simple_hf_config.php',
    'set_huggingface_key.php', 
    'huggingface_setup_complete.php'
];

foreach ($files_to_clean as $file) {
    if (file_exists($file)) {
        echo "  Cleaning $file...\n";
        
        $content = file_get_contents($file);
        $original_content = $content;
        
        // Remove the specific API key
        $content = str_replace($api_key, '[REMOVED_API_KEY]', $content);
        
        // Update display references to show [REMOVED]
        $content = str_replace(
            "substr('" . $api_key . "', 0, 8) . '...' . substr('" . $api_key . "', -8)",
            "'[REMOVED_API_KEY]'",
            $content
        );
        
        // Update comparison references
        $content = str_replace(
            "=== '" . $api_key . "'",
            "=== '[REMOVED_API_KEY]'",
            $content
        );
        
        // Update echo statements
        $content = str_replace(
            "Expected: '" . $api_key . "'",
            "Expected: '[REMOVED_API_KEY]'",
            $content
        );
        
        // Remove the variable assignment
        $content = preg_replace(
            "/\$huggingface_api_key\s*=\s*['\"]" . preg_quote($api_key, '/') . "['\"];?/",
            "// \$huggingface_api_key = '[REMOVED_API_KEY]'; // Removed for security",
            $content
        );
        
        if ($content !== $original_content) {
            if (file_put_contents($file, $content)) {
                echo "    ✅ Successfully cleaned $file\n";
            } else {
                echo "    ❌ Failed to clean $file\n";
            }
        } else {
            echo "    ℹ️  No changes needed for $file\n";
        }
    } else {
        echo "  ⚠️  File not found: $file\n";
    }
}

echo "\n🎯 LOCAL CLEANUP COMPLETE!\n";
echo "==========================\n\n";

echo "⚠️  IMPORTANT: Your API key is still active on HuggingFace!\n";
echo "You must also remove it from the HuggingFace website:\n\n";

echo "📋 Instructions to remove from HuggingFace:\n";
echo "-----------------------------------------\n";
echo "1. Go to https://huggingface.co/settings/tokens\n";
echo "2. Log in to your HuggingFace account\n";
echo "3. Find the API token that starts with: " . substr($api_key, 0, 12) . "...\n";
echo "4. Click the 'Delete' button next to that token\n";
echo "5. Confirm the deletion\n\n";

echo "🔒 Security Recommendations:\n";
echo "---------------------------\n";
echo "• Never commit API keys to version control\n";
echo "• Use environment variables for sensitive data\n";
echo "• Regularly rotate your API keys\n";
echo "• Monitor your API usage for unusual activity\n";
echo "• Remove unused API keys promptly\n\n";

echo "✨ Your local configuration has been cleaned!\n";
echo "Remember to also remove the API key from HuggingFace website.";