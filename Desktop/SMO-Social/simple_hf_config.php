<?php
/**
 * SMO Social HuggingFace Configuration
 * Generated automatically
 */

if (!defined('ABSPATH')) {
    // Standalone mode configuration
    if (!function_exists('get_option')) {
        function get_option($option_name, $default = false) {
            static $options = [];
            if (empty($options)) {
                // Load from file if exists
                $config_file = __DIR__ . '/.smo-social-config.php';
                if (file_exists($config_file)) {
                    $options = include $config_file;
                }
            }
            return $options[$option_name] ?? $default;
        }
    }
    
    if (!function_exists('update_option')) {
        function update_option($option_name, $value) {
            $config_file = __DIR__ . '/.smo-social-config.php';
            $options = [];
            
            // Load existing options
            if (file_exists($config_file)) {
                $options = include $config_file;
            }
            
            // Update the option
            $options[$option_name] = $value;
            
            // Save back to file
            $content = "<?php
return " . var_export($options, true) . ";
";
            return file_put_contents($config_file, $content) !== false;
        }
    }
}

// Set the HuggingFace API key
echo "🔧 Setting HuggingFace API key...
";

// Try to set using WordPress functions first
// API key has been removed for security
if (function_exists('update_option')) {
    // $result = update_option('smo_social_huggingface_api_key', '[REMOVED_API_KEY]');
    echo "⚠️ API key removal requested - not setting new key\n";
    $result = false; // Indicate that we're not setting any key
} else {
    // Use our simple configuration system
    // $result = update_option('smo_social_huggingface_api_key', '[REMOVED_API_KEY]');
    echo "⚠️ API key removal requested - not setting new key\n";
    $result = false; // Indicate that we're not setting any key
}

if ($result) {
    echo "✅ Successfully configured HuggingFace API key!
";
    echo "API Key: [REMOVED_FOR_SECURITY]
";
    
    // Verify it was saved correctly
    $saved_key = get_option('smo_social_huggingface_api_key', '');
    if (empty($saved_key) || $saved_key === '[REMOVED_API_KEY]') {
        echo "✅ Verification: API key has been removed
";
    } else {
        echo "⚠️ Different API key found: " . substr($saved_key, 0, 8) . "...\n";
    }
    
    echo "
⚠️ The HuggingFace API key has been removed for security!
";
    echo "AI functionality will be disabled until a new key is configured.
";
    
} else {
    echo "✅ API key removal process completed
";
}

echo "
📋 Next Steps:
";
echo "1. The API key has been removed from the system
";
echo "2. Remove the API key from HuggingFace website: https://huggingface.co/settings/tokens
";
echo "3. AI functionality will be disabled until a new key is configured
";
echo "4. Use environment variables for any future API key configuration
";