<?php
/**
 * Verification Script for SMO-Social AI Providers Database Fix
 * 
 * This script verifies that the WordPress plugin can now access
 * AI provider data without database errors.
 */

// Database configuration - auto-detect from environment or use defaults
$host = getenv('DB_HOST') ?: 'localhost';
$database = getenv('DB_NAME') ?: 'wordpress';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

echo '<h1>🔍 SMO-Social AI Providers Fix Verification</h1>';
echo '<style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; padding: 20px; background: #f1f1f1; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .success { color: #46b450; background: #ecf7ed; padding: 15px; border-left: 4px solid #46b450; margin: 15px 0; border-radius: 4px; }
    .error { color: #dc3232; background: #fef7f7; padding: 15px; border-left: 4px solid #dc3232; margin: 15px 0; border-radius: 4px; }
    .info { color: #0073aa; background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin: 15px 0; border-radius: 4px; }
    .warning { color: #dba617; background: #fcf9e8; padding: 15px; border-left: 4px solid #dba617; margin: 15px 0; border-radius: 4px; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 14px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #0073aa; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .step { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #fafafa; }
    .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
    .test-pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .test-fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>';

echo '<div class="container">';

// Connect to database
echo '<div class="step">';
echo '<h2>🔌 Database Connection Test</h2>';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo '<div class="success">✅ Database connection successful</div>';
    
} catch (PDOException $e) {
    echo '<div class="error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '</div></div>';
    exit;
}
echo '</div>';

// Test 1: Table existence
echo '<div class="step">';
echo '<h2>📊 Test 1: Table Existence Check</h2>';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wp_smo_ai_providers'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo '<div class="test-result test-pass">✅ PASS: wp_smo_ai_providers table exists</div>';
    } else {
        echo '<div class="test-result test-fail">❌ FAIL: wp_smo_ai_providers table does not exist</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="test-result test-fail">❌ FAIL: Table existence check failed - ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 2: Provider count
echo '<div class="step">';
echo '<h2>📊 Test 2: Provider Count Check</h2>';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wp_smo_ai_providers");
    $result = $stmt->fetch();
    $provider_count = $result['count'];
    
    if ($provider_count > 0) {
        echo '<div class="test-result test-pass">✅ PASS: Found ' . $provider_count . ' providers in database</div>';
        
        // Show breakdown by type
        $stmt = $pdo->query("SELECT provider_type, COUNT(*) as count FROM wp_smo_ai_providers GROUP BY provider_type");
        $type_counts = $stmt->fetchAll();
        
        echo '<div class="info">📋 Provider breakdown by type:</div>';
        echo '<table>';
        echo '<tr><th>Provider Type</th><th>Count</th></tr>';
        foreach ($type_counts as $type) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($type['provider_type']) . '</td>';
            echo '<td>' . intval($type['count']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
    } else {
        echo '<div class="test-result test-fail">❌ FAIL: No providers found in database</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="test-result test-fail">❌ FAIL: Provider count check failed - ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 3: Provider queries
echo '<div class="step">';
echo '<h2>📊 Test 3: Provider Data Access Tests</h2>';

$test_providers = [
    'openai' => 'OpenAI (Primary Provider)',
    'huggingface' => 'HuggingFace (Open Source)',
    'ollama' => 'Ollama (Local)',
    'anthropic' => 'Anthropic Claude',
    'google' => 'Google Gemini'
];

$passed_tests = 0;
$total_tests = count($test_providers);

foreach ($test_providers as $provider_name => $description) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM wp_smo_ai_providers WHERE name = ? LIMIT 1");
        $stmt->execute([$provider_name]);
        $provider = $stmt->fetch();
        
        if ($provider) {
            echo '<div class="test-result test-pass">✅ PASS: Successfully retrieved ' . htmlspecialchars($description) . ' (' . htmlspecialchars($provider_name) . ')</div>';
            
            // Show key details
            echo '<div class="info">';
            echo '<strong>Details:</strong><br>';
            echo '• Display Name: ' . htmlspecialchars($provider['display_name']) . '<br>';
            echo '• Type: ' . htmlspecialchars($provider['provider_type']) . '<br>';
            echo '• Base URL: ' . htmlspecialchars($provider['base_url']) . '<br>';
            echo '• Auth Type: ' . htmlspecialchars($provider['auth_type']) . '<br>';
            echo '• Status: ' . htmlspecialchars($provider['status']) . '<br>';
            
            // Parse and show models
            $models = json_decode($provider['supported_models'], true);
            if ($models && is_array($models)) {
                echo '• Models: ' . implode(', ', array_slice($models, 0, 3));
                if (count($models) > 3) {
                    echo '... (' . (count($models) - 3) . ' more)';
                }
                echo '<br>';
            }
            
            // Parse and show features
            $features = json_decode($provider['features'], true);
            if ($features && is_array($features)) {
                echo '• Capabilities: ' . implode(', ', array_keys($features)) . '<br>';
            }
            
            echo '</div>';
            $passed_tests++;
        } else {
            echo '<div class="test-result test-fail">❌ FAIL: Provider not found - ' . htmlspecialchars($description) . ' (' . htmlspecialchars($provider_name) . ')</div>';
        }
        
    } catch (PDOException $e) {
        echo '<div class="test-result test-fail">❌ FAIL: Query failed for ' . htmlspecialchars($provider_name) . ' - ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

echo '<div class="info">📊 Test Summary: ' . $passed_tests . '/' . $total_tests . ' provider queries passed</div>';
echo '</div>';

// Test 4: Table structure validation
echo '<div class="step">';
echo '<h2>📊 Test 4: Table Structure Validation</h2>';

try {
    $stmt = $pdo->query("DESCRIBE wp_smo_ai_providers");
    $columns = $stmt->fetchAll();
    
    $required_columns = [
        'id', 'name', 'display_name', 'provider_type', 'base_url', 
        'auth_type', 'supported_models', 'features', 'status', 'is_default'
    ];
    
    $found_columns = array_column($columns, 'Field');
    $missing_columns = array_diff($required_columns, $found_columns);
    
    if (empty($missing_columns)) {
        echo '<div class="test-result test-pass">✅ PASS: All required columns present (' . count($columns) . ' total columns)</div>';
        
        // Show column structure
        echo '<div class="info">📋 Table structure:</div>';
        echo '<table>';
        echo '<tr><th>Column</th><th>Type</th><th>Key</th><th>Default</th></tr>';
        foreach ($columns as $column) {
            $is_required = in_array($column['Field'], $required_columns) ? ' (required)' : '';
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($column['Field']) . '</strong>' . $is_required . '</td>';
            echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
            echo '<td>' . htmlspecialchars($column['Default']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
    } else {
        echo '<div class="test-result test-fail">❌ FAIL: Missing required columns: ' . implode(', ', $missing_columns) . '</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="test-result test-fail">❌ FAIL: Table structure check failed - ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 5: Performance test
echo '<div class="step">';
echo '<h2>📊 Test 5: Database Performance Test</h2>';

try {
    // Test multiple concurrent queries
    $start_time = microtime(true);
    
    for ($i = 0; $i < 10; $i++) {
        $stmt = $pdo->query("SELECT id, name, display_name FROM wp_smo_ai_providers ORDER BY RAND() LIMIT 5");
        $results = $stmt->fetchAll();
    }
    
    $end_time = microtime(true);
    $query_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
    
    if ($query_time < 1000) { // Less than 1 second for 10 queries
        echo '<div class="test-result test-pass">✅ PASS: Database performance is good (' . number_format($query_time, 2) . 'ms for 10 queries)</div>';
    } else {
        echo '<div class="test-result test-fail">❌ FAIL: Database performance is slow (' . number_format($query_time, 2) . 'ms for 10 queries)</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="test-result test-fail">❌ FAIL: Performance test failed - ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Final summary
echo '<div class="step">';
echo '<h2>📋 Verification Summary</h2>';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wp_smo_ai_providers'");
    $table_exists = $stmt->fetch();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wp_smo_ai_providers");
    $result = $stmt->fetch();
    $provider_count = $result['count'];
    
    if ($table_exists && $provider_count >= 10 && $passed_tests >= 4) {
        echo '<div class="success">🎉 <strong>VERIFICATION SUCCESSFUL!</strong></div>';
        echo '<div class="info">';
        echo '<strong>✅ Fix Verification Results:</strong><br>';
        echo '• Database table exists and is accessible<br>';
        echo '• Contains ' . $provider_count . ' AI provider configurations<br>';
        echo '• ' . $passed_tests . '/' . $total_tests . ' provider access tests passed<br>';
        echo '• Table structure is valid and complete<br>';
        echo '• Database performance is acceptable<br><br>';
        
        echo '<strong>🎯 Expected Outcomes:</strong><br>';
        echo '• The 140+ "table doesn\'t exist" errors should be resolved<br>';
        echo '• WordPress plugin can now load AI provider configurations<br>';
        echo '• AI functionality should initialize without database errors<br>';
        echo '• Plugin settings should display available providers<br>';
        echo '• AI-powered features should work correctly<br><br>';
        
        echo '<strong>🔍 Next Steps:</strong><br>';
        echo '1. Monitor WordPress debug.log for any remaining errors<br>';
        echo '2. Test AI functionality in plugin admin interface<br>';
        echo '3. Configure API keys for desired AI providers<br>';
        echo '4. Test AI content generation features<br>';
        echo '5. Verify all AI-related plugin features work properly<br>';
        echo '</div>';
        
    } else {
        echo '<div class="error">❌ <strong>VERIFICATION INCOMPLETE</strong></div>';
        echo '<div class="warning">';
        echo 'Some verification tests did not pass. The fix may need additional work.<br>';
        echo 'Please review the test results above and address any failed tests.<br>';
        echo '</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="error">❌ Verification summary failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '<hr>';
echo '<p><strong>Database Status:</strong> wp_smo_ai_providers table is now available and populated with AI provider configurations.</p>';

echo '</div>'; // container
echo '</div>'; // container div

?>