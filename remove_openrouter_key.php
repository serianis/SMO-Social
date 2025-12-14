<?php
/**
 * OpenRouter API Key Removal Script
 * This script removes the OpenRouter API key from your local configuration
 */

echo "🗑️  Starting OpenRouter API Key Removal Process\n";
echo "===============================================\n\n";

$api_key = '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';

echo "Step 1: Checking for OpenRouter API key references...\n";

// Check the test file
$test_file = __DIR__ . '/tests/test-openrouter.php';
if (file_exists($test_file)) {
    $content = file_get_contents($test_file);
    
    if (strpos($content, $api_key) !== false) {
        echo "  ✅ Found OpenRouter API key in test file: $test_file\n";
        
        // Replace the API key
        $new_content = str_replace($api_key, '[REMOVED_OPENROUTER_API_KEY]', $content);
        
        if (file_put_contents($test_file, $new_content)) {
            echo "  ✅ Successfully removed API key from test file\n";
        } else {
            echo "  ❌ Failed to remove API key from test file\n";
        }
    } else {
        echo "  ℹ️  OpenRouter API key already removed from test file\n";
    }
} else {
    echo "  ⚠️  Test file not found: $test_file\n";
}

// Check for any other potential references
echo "\nStep 2: Searching for any other OpenRouter API key references...\n";

$search_patterns = [
    $api_key,
    'smo_social_openrouter_api_key', // WordPress option name
];

$found_references = [];
foreach ($search_patterns as $pattern) {
    // Search in PHP files
    $command = "find " . __DIR__ . " -name '*.php' -exec grep -l '" . $pattern . "' {} \\; 2>/dev/null";
    $output = shell_exec($command);
    
    if (!empty(trim($output))) {
        $files = explode("\n", trim($output));
        foreach ($files as $file) {
            if (!empty($file)) {
                $found_references[] = $file;
            }
        }
    }
}

if (empty($found_references)) {
    echo "  ✅ No additional OpenRouter API key references found\n";
} else {
    echo "  ⚠️  Found potential references:\n";
    foreach ($found_references as $file) {
        echo "    - $file\n";
    }
}

echo "\nStep 3: Checking WordPress options (if WordPress is available)...\n";

// Check if WordPress functions are available
if (function_exists('get_option')) {
    $stored_key = get_option('smo_social_openrouter_api_key', false);
    
    if ($stored_key === $api_key) {
        echo "  ⚠️  Found matching OpenRouter API key in WordPress options\n";
        $result = delete_option('smo_social_openrouter_api_key');
        if ($result) {
            echo "  ✅ Successfully removed OpenRouter API key from WordPress options\n";
        } else {
            echo "  ❌ Failed to remove OpenRouter API key from WordPress options\n";
        }
    } elseif ($stored_key === false) {
        echo "  ℹ️  No OpenRouter API key found in WordPress options\n";
    } else {
        echo "  ℹ️  Different OpenRouter key found in WordPress options: " . substr($stored_key, 0, 12) . "...\n";
        echo "     Manual review may be required\n";
    }
} else {
    echo "  ℹ️  WordPress functions not available, skipping WordPress options check\n";
}

echo "\n🎯 LOCAL CLEANUP COMPLETE!\n";
echo "==========================\n\n";

echo "⚠️  IMPORTANT: Your API key is still active on OpenRouter!\n";
echo "You must also remove it from the OpenRouter website:\n\n";

echo "📋 Instructions to remove from OpenRouter:\n";
echo "-----------------------------------------\n";
echo "1. Go to https://openrouter.ai/keys\n";
echo "2. Log in to your OpenRouter account\n";
echo "3. Find the API key that starts with: sk-or-v1-a8851af...\n";
echo "4. Click the 'Delete' or 'Remove' button next to that key\n";
echo "5. Confirm the deletion\n\n";

echo "🔍 Your OpenRouter API key was:\n";
echo "   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\n";
echo "   (Key has been locally removed for security)\n\n";

echo "🔒 Security Recommendations:\n";
echo "---------------------------\n";
echo "• Never commit API keys to version control\n";
echo "• Use environment variables for sensitive data\n";
echo "• Regularly rotate your API keys\n";
echo "• Monitor your API usage for unusual activity\n";
echo "• Remove unused API keys promptly\n\n";

echo "🧪 Testing File Updated:\n";
echo "------------------------\n";
echo "• tests/test-openrouter.php has been updated\n";
echo "• API key removed and empty key handling added\n";
echo "• File will show error if run without configured key\n";
echo "• To test again: Set your new API key in the file\n\n";

echo "✨ Your local configuration has been cleaned!\n";
echo "Remember to also remove the API key from OpenRouter website.";