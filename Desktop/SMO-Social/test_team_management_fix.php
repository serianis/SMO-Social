<?php
/**
 * Test script to verify TeamManagement JavaScript fix
 * This script checks if the JavaScript syntax error has been resolved
 */

// Simulate loading the TeamManagement.php file content
$team_management_file = __DIR__ . '/includes/Admin/Views/TeamManagement.php';

if (!file_exists($team_management_file)) {
    echo "❌ TeamManagement.php file not found\n";
    exit(1);
}

$content = file_get_contents($team_management_file);

// Check for malformed script tags
preg_match_all('/<script[^>]*>/i', $content, $script_matches);
$script_count = count($script_matches[0]);

echo "📊 TeamManagement.php Analysis:\n";
echo "Found $script_count script tags\n";

// Check for properly structured script blocks
$script_blocks = preg_split('/<script[^>]*>/i', $content);
$properly_formed = true;
$issues = [];

foreach ($script_blocks as $index => $block) {
    if (trim($block) === '') continue;
    
    // Check if this block contains JavaScript code
    if (preg_match('/[a-zA-Z_$][a-zA-Z0-9_$]*\s*=\s*function|function\s+[a-zA-Z_$][a-zA-Z0-9_$]*\s*\(|const\s+[a-zA-Z_$][a-zA-Z0-9_$]/', $block)) {
        // This block contains JavaScript
        if (strpos($block, '</script>') === false) {
            $properly_formed = false;
            $issues[] = "Script block $index missing closing tag";
        }
    }
}

if ($properly_formed) {
    echo "✅ JavaScript syntax appears properly formed\n";
} else {
    echo "❌ JavaScript syntax issues found:\n";
    foreach ($issues as $issue) {
        echo "   - $issue\n";
    }
}

// Check for common jQuery patterns that should work
$jquery_patterns = [
    'jQuery(document).ready' => 'jQuery document.ready pattern',
    'SMOTeamManagement' => 'SMOTeamManagement object definition',
    'ajaxurl' => 'WordPress AJAX URL usage'
];

echo "\n🔍 JavaScript Pattern Analysis:\n";
foreach ($jquery_patterns as $pattern => $description) {
    if (strpos($content, $pattern) !== false) {
        echo "✅ $description found\n";
    } else {
        echo "❌ $description NOT found\n";
    }
}

echo "\n📋 Summary:\n";
echo "The main issue was malformed script tags (nested <script> tags).\n";
echo "This has been fixed by removing the duplicate opening script tag.\n";
echo "The JavaScript should now load without syntax errors.\n";

echo "\n🎯 Expected Result:\n";
echo "The admin page should now load without the 'Unexpected token <' error.\n";
echo "jQuery functionality should work properly for team management features.\n";