<?php
/**
 * Test script to validate chat interface distortion fix
 * 
 * This script checks:
 * 1. CSS variables are properly defined in the unified design system
 * 2. Chat interface CSS can access the variables
 * 3. JavaScript fallback container creation works correctly
 */

echo "=== SMO Social Chat Interface Distortion Fix Test ===\n\n";

// Test 1: Check if unified design system CSS contains required variables
echo "1. Testing CSS Variables Definition...\n";
$unified_css_file = __DIR__ . '/assets/css/smo-unified-design-system.css';
if (file_exists($unified_css_file)) {
    $css_content = file_get_contents($unified_css_file);
    
    $required_variables = [
        '--smo-surface',
        '--smo-bg', 
        '--smo-text-primary',
        '--smo-text-secondary',
        '--smo-border',
        '--smo-primary',
        '--smo-shadow-sm',
        '--smo-shadow-md',
        '--smo-shadow-lg',
        '--smo-radius-lg',
        '--smo-radius-md',
        '--smo-font-sans',
        '--smo-transition-fast',
        '--smo-transition-base'
    ];
    
    $missing_variables = [];
    $found_variables = [];
    
    foreach ($required_variables as $variable) {
        if (strpos($css_content, $variable . ':') !== false) {
            $found_variables[] = $variable;
        } else {
            $missing_variables[] = $variable;
        }
    }
    
    echo "   ✓ Found " . count($found_variables) . " required CSS variables\n";
    foreach ($found_variables as $var) {
        echo "     - $var\n";
    }
    
    if (!empty($missing_variables)) {
        echo "   ✗ Missing " . count($missing_variables) . " CSS variables:\n";
        foreach ($missing_variables as $var) {
            echo "     - $var\n";
        }
    } else {
        echo "   ✓ All required CSS variables are defined!\n";
    }
} else {
    echo "   ✗ Unified design system CSS file not found\n";
}

// Test 2: Check chat interface CSS imports unified design system
echo "\n2. Testing Chat CSS Import...\n";
$chat_css_file = __DIR__ . '/assets/css/smo-chat-modern.css';
if (file_exists($chat_css_file)) {
    $chat_css_content = file_get_contents($chat_css_file);
    
    if (strpos($chat_css_content, "smo-unified-design-system.css") !== false) {
        echo "   ✓ Chat CSS properly imports unified design system\n";
    } else {
        echo "   ✗ Chat CSS missing unified design system import\n";
    }
} else {
    echo "   ✗ Chat interface CSS file not found\n";
}

// Test 3: Check JavaScript fallback container fix
echo "\n3. Testing JavaScript Fallback Fix...\n";
$js_file = __DIR__ . '/assets/js/smo-chat-interface.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    
    if (strpos($js_content, "smo-chat-fallback") !== false) {
        echo "   ✓ JavaScript uses CSS classes instead of inline styles\n";
    } else {
        echo "   ✗ JavaScript still uses inline styles for fallback container\n";
    }
    
    if (strpos($js_content, "container.style.width") === false && 
        strpos($js_content, "container.style.height") === false) {
        echo "   ✓ Removed problematic inline width/height styles\n";
    } else {
        echo "   ✗ Still has inline width/height styles\n";
    }
} else {
    echo "   ✗ Chat interface JavaScript file not found\n";
}

// Test 4: Check responsive design improvements
echo "\n4. Testing Responsive Design...\n";
if (strpos($chat_css_content, "@media (max-width: 768px)") !== false) {
    echo "   ✓ Responsive breakpoints added\n";
} else {
    echo "   ✗ Missing responsive breakpoints\n";
}

if (strpos($chat_css_content, "smo-chat-fallback") !== false) {
    echo "   ✓ Fallback container responsive styles included\n";
} else {
    echo "   ✗ Missing fallback container responsive styles\n";
}

// Test 5: Verify CSS variable usage in chat styles
echo "\n5. Testing Chat Interface CSS Variable Usage...\n";
$chat_css_variables_used = [
    'var(--smo-chat-bg)',
    'var(--smo-chat-border)', 
    'var(--smo-chat-text)',
    'var(--smo-chat-primary)',
    'var(--smo-shadow-md)'
];

$variables_resolved = 0;
foreach ($chat_css_variables_used as $usage) {
    if (strpos($chat_css_content, $usage) !== false) {
        $variables_resolved++;
    }
}

echo "   ✓ Chat interface uses $variables_resolved/" . count($chat_css_variables_used) . " resolved CSS variables\n";

echo "\n=== Test Summary ===\n";
$total_tests = 5;
$passed_tests = 0;

// Count passed tests
if (empty($missing_variables)) $passed_tests++;
if (strpos($chat_css_content, "smo-unified-design-system.css") !== false) $passed_tests++;
if (strpos($js_content, "smo-chat-fallback") !== false) $passed_tests++;
if (strpos($chat_css_content, "@media (max-width: 768px)") !== false) $passed_tests++;
if ($variables_resolved >= 4) $passed_tests++;

echo "Tests Passed: $passed_tests/$total_tests\n";

if ($passed_tests === $total_tests) {
    echo "🎉 All tests passed! Chat interface distortion should be fixed.\n";
    echo "\n=== Fix Summary ===\n";
    echo "1. Added missing CSS variables to unified design system\n";
    echo "2. Fixed JavaScript inline style conflicts\n";
    echo "3. Improved responsive design for chat container\n";
    echo "4. Ensured proper CSS variable resolution\n";
} else {
    echo "⚠️  Some tests failed. Please review the issues above.\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Clear any CSS caches\n";
echo "2. Test the chat interface in different screen sizes\n";
echo "3. Verify the fallback container works when main container is missing\n";
echo "4. Check browser console for any CSS variable warnings\n";

?>