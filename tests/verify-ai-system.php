<?php
/**
 * Quick AI System Verification
 * Verifies the refactored AI system structure without requiring WordPress
 */

echo "\n=== SMO Social AI System Verification ===\n";
echo "Verifying refactored AI Manager architecture\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Set up basic path
$base_dir = dirname(__DIR__);
$includes_dir = $base_dir . '/includes';

// Track results
$checks = [];
$passed = 0;
$failed = 0;

/**
 * Check if file exists and has correct structure
 */
function check_file($path, $description) {
    global $checks, $passed, $failed;
    
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        // Check for PHP opening tag
        if (strpos($content, '<?php') === 0) {
            echo "✓ PASS: {$description}\n";
            $checks[] = ['status' => 'pass', 'message' => $description];
            $passed++;
            return true;
        } else {
            echo "✗ FAIL: {$description} - Missing <?php tag\n";
            $checks[] = ['status' => 'fail', 'message' => $description];
            $failed++;
            return false;
        }
    } else {
        echo "✗ FAIL: {$description} - File not found\n";
        $checks[] = ['status' => 'fail', 'message' => $description];
        $failed++;
        return false;
    }
}

/**
 * Check if class exists in file
 */
function check_class_in_file($path, $class_name, $description) {
    global $checks, $passed, $failed;
    
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        if (preg_match('/class\s+' . preg_quote($class_name, '/') . '\s*\{/', $content)) {
            echo "✓ PASS: {$description}\n";
            $checks[] = ['status' => 'pass', 'message' => $description];
            $passed++;
            return true;
        } else {
            echo "✗ FAIL: {$description} - Class not found\n";
            $checks[] = ['status' => 'fail', 'message' => $description];
            $failed++;
            return false;
        }
    } else {
        echo "✗ FAIL: {$description} - File not found\n";
        $checks[] = ['status' => 'fail', 'message' => $description];
        $failed++;
        return false;
    }
}

/**
 * Check if method exists in file
 */
function check_method_in_file($path, $method_name, $description) {
    global $checks, $passed, $failed;
    
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        if (preg_match('/function\s+' . preg_quote($method_name, '/') . '\s*\(/', $content)) {
            echo "✓ PASS: {$description}\n";
            $checks[] = ['status' => 'pass', 'message' => $description];
            $passed++;
            return true;
        } else {
            echo "✗ FAIL: {$description} - Method not found\n";
            $checks[] = ['status' => 'fail', 'message' => $description];
            $failed++;
            return false;
        }
    } else {
        echo "✗ FAIL: {$description} - File not found\n";
        $checks[] = ['status' => 'fail', 'message' => $description];
        $failed++;
        return false;
    }
}

/**
 * Check if old file is deleted
 */
function check_file_deleted($path, $description) {
    global $checks, $passed, $failed;
    
    if (!file_exists($path)) {
        echo "✓ PASS: {$description}\n";
        $checks[] = ['status' => 'pass', 'message' => $description];
        $passed++;
        return true;
    } else {
        echo "✗ FAIL: {$description} - File still exists\n";
        $checks[] = ['status' => 'fail', 'message' => $description];
        $failed++;
        return false;
    }
}

echo "1. Checking Core AI Files\n";
echo str_repeat('-', 50) . "\n";
check_file($includes_dir . '/AI/Manager.php', 'AI/Manager.php exists with PHP tag');
check_file($includes_dir . '/AI/ContentOptimizer.php', 'AI/ContentOptimizer.php exists with PHP tag');
check_file($includes_dir . '/AI/SmartContentRepurposer.php', 'AI/SmartContentRepurposer.php exists with PHP tag');
check_file($includes_dir . '/AI/PlatformAdvisor.php', 'AI/PlatformAdvisor.php exists with PHP tag');
echo "\n";

echo "2. Checking Class Definitions\n";
echo str_repeat('-', 50) . "\n";
check_class_in_file($includes_dir . '/AI/Manager.php', 'Manager', 'Manager class defined');
check_class_in_file($includes_dir . '/AI/ContentOptimizer.php', 'ContentOptimizer', 'ContentOptimizer class defined');
check_class_in_file($includes_dir . '/AI/SmartContentRepurposer.php', 'SmartContentRepurposer', 'SmartContentRepurposer class defined');
check_class_in_file($includes_dir . '/AI/PlatformAdvisor.php', 'PlatformAdvisor', 'PlatformAdvisor class defined');
echo "\n";

echo "3. Checking Manager Singleton Pattern\n";
echo str_repeat('-', 50) . "\n";
check_method_in_file($includes_dir . '/AI/Manager.php', 'getInstance', 'getInstance() method exists');
check_method_in_file($includes_dir . '/AI/Manager.php', '__construct', '__construct() method exists');
check_method_in_file($includes_dir . '/AI/Manager.php', '__clone', '__clone() method exists (prevents cloning)');
check_method_in_file($includes_dir . '/AI/Manager.php', '__wakeup', '__wakeup() method exists (prevents unserialization)');
echo "\n";

echo "4. Checking AI Manager Methods\n";
echo str_repeat('-', 50) . "\n";
check_method_in_file($includes_dir . '/AI/Manager.php', 'chat', 'chat() method exists');
check_method_in_file($includes_dir . '/AI/Manager.php', 'get_primary_provider_id', 'get_primary_provider_id() method exists');
check_method_in_file($includes_dir . '/AI/Manager.php', 'get_provider_manager', 'get_provider_manager() method exists');
check_method_in_file($includes_dir . '/AI/Manager.php', 'generate_captions', 'generate_captions() method exists');
check_method_in_file($includes_dir . '/AI/Manager.php', 'optimize_hashtags', 'optimize_hashtags() method exists');
check_method_in_file($includes_dir . '/AI/Manager.php', 'analyze_sentiment', 'analyze_sentiment() method exists');
echo "\n";

echo "5. Checking UniversalManager Integration\n";
echo str_repeat('-', 50) . "\n";
$manager_content = file_get_contents($includes_dir . '/AI/Manager.php');
if (strpos($manager_content, 'use SMO_Social\\AI\\Models\\UniversalManager') !== false) {
    echo "✓ PASS: Manager imports UniversalManager\n";
    $passed++;
} else {
    echo "✗ FAIL: Manager doesn't import UniversalManager\n";
    $failed++;
}

if (strpos($manager_content, 'new UniversalManager') !== false) {
    echo "✓ PASS: Manager instantiates UniversalManager\n";
    $passed++;
} else {
    echo "✗ FAIL: Manager doesn't instantiate UniversalManager\n";
    $failed++;
}
echo "\n";

echo "6. Checking SmartContentRepurposer Refactoring\n";
echo str_repeat('-', 50) . "\n";
$repurposer_content = file_get_contents($includes_dir . '/AI/SmartContentRepurposer.php');

// Check it uses Manager instead of internal classes
if (strpos($repurposer_content, 'Manager::getInstance()') !== false) {
    echo "✓ PASS: SmartContentRepurposer uses Manager::getInstance()\n";
    $passed++;
} else {
    echo "✗ FAIL: SmartContentRepurposer doesn't use Manager::getInstance()\n";
    $failed++;
}

// Check it doesn't use old internal classes
$old_classes = ['ContentAnalyzer', 'TransformationEngine', 'PlatformOptimizer', 'QualityAssurance'];
$uses_old_classes = false;
foreach ($old_classes as $old_class) {
    if (preg_match('/new\s+' . $old_class . '\s*\(/', $repurposer_content)) {
        echo "✗ FAIL: SmartContentRepurposer still uses {$old_class}\n";
        $failed++;
        $uses_old_classes = true;
    }
}
if (!$uses_old_classes) {
    echo "✓ PASS: SmartContentRepurposer doesn't use old internal classes\n";
    $passed++;
}
echo "\n";

echo "7. Checking ContentOptimizer Integration\n";
echo str_repeat('-', 50) . "\n";
$optimizer_content = file_get_contents($includes_dir . '/AI/ContentOptimizer.php');

if (strpos($optimizer_content, 'Manager::getInstance()') !== false) {
    echo "✓ PASS: ContentOptimizer uses Manager::getInstance()\n";
    $passed++;
} else {
    echo "✗ FAIL: ContentOptimizer doesn't use Manager::getInstance()\n";
    $failed++;
}
echo "\n";

echo "8. Verifying Old Manager Files Deleted\n";
echo str_repeat('-', 50) . "\n";
check_file_deleted($includes_dir . '/AI/HuggingFaceManager.php', 'HuggingFaceManager.php deleted');
check_file_deleted($includes_dir . '/AI/LocalhostManager.php', 'LocalhostManager.php deleted');
check_file_deleted($includes_dir . '/AI/CustomAPIManager.php', 'CustomAPIManager.php deleted');
echo "\n";

echo "9. Checking Namespace Declarations\n";
echo str_repeat('-', 50) . "\n";
$files_to_check = [
    'AI/Manager.php' => 'SMO_Social\\AI',
    'AI/ContentOptimizer.php' => 'SMO_Social\\AI',
    'AI/SmartContentRepurposer.php' => 'SMO_Social\\AI',
    'AI/PlatformAdvisor.php' => 'SMO_Social\\AI'
];

foreach ($files_to_check as $file => $expected_namespace) {
    $content = file_get_contents($includes_dir . '/' . $file);
    if (preg_match('/namespace\s+' . preg_quote($expected_namespace, '/') . '\s*;/', $content)) {
        echo "✓ PASS: {$file} has correct namespace\n";
        $passed++;
    } else {
        echo "✗ FAIL: {$file} has incorrect namespace\n";
        $failed++;
    }
}
echo "\n";

// Print summary
echo str_repeat('=', 50) . "\n";
echo "VERIFICATION SUMMARY\n";
echo str_repeat('=', 50) . "\n\n";

$total = $passed + $failed;
echo "Total Checks: {$total}\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n\n";

$success_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
echo "Success Rate: {$success_rate}%\n\n";

if ($failed === 0) {
    echo "🎉 All verification checks passed!\n";
    echo "✓ AI Manager refactoring is complete\n";
    echo "✓ All files use UniversalManager architecture\n";
    echo "✓ Old manager files have been removed\n";
    echo "✓ System is ready for testing\n";
} elseif ($failed < 3) {
    echo "⚠️  Most checks passed with some issues.\n";
    echo "Review the failed checks above.\n";
} else {
    echo "❌ Multiple verification failures detected.\n";
    echo "Please review the failed checks above.\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);
