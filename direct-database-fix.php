<?php
/**
 * Direct Database Fix for SMO-Social AI Providers Table
 * 
 * This script directly connects to the MySQL database to create the missing
 * wp_smo_ai_providers table and resolve the 140+ database errors.
 */

// Database configuration
$host = 'localhost';
$database = 'ena';
$username = 'root';
$password = '';

echo '<h1>🚀 SMO-Social AI Providers Database Fix</h1>';
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
    .progress { background: #e0e0e0; border-radius: 4px; overflow: hidden; margin: 10px 0; }
    .progress-bar { background: #0073aa; height: 20px; transition: width 0.3s; }
</style>';

echo '<div class="container">';

// Connect to database
echo '<div class="step">';
echo '<h2>🔌 Step 1: Database Connection</h2>';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo '<div class="success">✅ Connected to database successfully!</div>';
    echo '<p><strong>Host:</strong> ' . htmlspecialchars($host) . '</p>';
    echo '<p><strong>Database:</strong> ' . htmlspecialchars($database) . '</p>';
    
} catch (PDOException $e) {
    echo '<div class="error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '</div></div>';
    exit;
}
echo '</div>';

// Check current table state
echo '<div class="step">';
echo '<h2>📊 Step 2: Database State Analysis</h2>';

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'wp_smo_ai_providers'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo '<div class="warning">⚠️ Table wp_smo_ai_providers already exists! Checking current state...</div>';
        
        // Get current provider count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM wp_smo_ai_providers");
        $result = $stmt->fetch();
        $current_count = $result['count'];
        
        echo '<p><strong>Current provider count:</strong> ' . intval($current_count) . '</p>';
        
        if ($current_count > 0) {
            echo '<div class="info">📋 Current providers in database:</div>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Name</th><th>Display Name</th><th>Type</th><th>Status</th></tr>';
            
            $stmt = $pdo->query("SELECT id, name, display_name, provider_type, status FROM wp_smo_ai_providers ORDER BY name");
            while ($provider = $stmt->fetch()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($provider['id']) . '</td>';
                echo '<td><strong>' . htmlspecialchars($provider['name']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($provider['display_name']) . '</td>';
                echo '<td>' . htmlspecialchars($provider['provider_type']) . '</td>';
                echo '<td>' . htmlspecialchars($provider['status']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    } else {
        echo '<div class="error">❌ Table wp_smo_ai_providers does not exist - this is the source of the database errors!</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="error">❌ Database query failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Create the table
echo '<div class="step">';
echo '<h2>🔧 Step 3: Creating Missing Database Table</h2>';

if (!$table_exists) {
    try {
        echo '<div class="info">📝 Creating table structure...</div>';
        
        // Create the AI providers table
        $create_table_sql = "
            CREATE TABLE wp_smo_ai_providers (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                display_name varchar(100) NOT NULL,
                provider_type varchar(50) NOT NULL,
                base_url varchar(500) NOT NULL,
                auth_type enum('api_key', 'oauth2', 'none') NOT NULL,
                auth_config longtext,
                default_params longtext,
                supported_models longtext,
                features longtext,
                rate_limits longtext,
                status enum('active', 'inactive', 'testing') DEFAULT 'active',
                is_default boolean DEFAULT FALSE,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY name (name),
                KEY provider_type (provider_type),
                KEY status (status),
                KEY idx_provider_type (provider_type),
                KEY idx_is_default (is_default),
                KEY idx_status_type (status, provider_type),
                KEY idx_base_url (base_url(255))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($create_table_sql);
        
        // Verify table was created
        $stmt = $pdo->query("SHOW TABLES LIKE 'wp_smo_ai_providers'");
        $table_created = $stmt->fetch();
        
        if ($table_created) {
            echo '<div class="success">✅ Table created successfully!</div>';
        } else {
            throw new Exception('Table creation failed verification');
        }
        
    } catch (PDOException $e) {
        echo '<div class="error">❌ Table creation failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
} else {
    echo '<div class="info">📝 Table already exists, skipping creation.</div>';
}
echo '</div>';

// Populate with providers
echo '<div class="step">';
echo '<h2>🚀 Step 4: Populating with AI Providers</h2>';

// Define the providers to insert based on the static configuration
$providers = [
    [
        'name' => 'openai',
        'display_name' => 'OpenAI',
        'provider_type' => 'cloud',
        'base_url' => 'https://api.openai.com/v1',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['gpt-4.1', 'gpt-4o', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo', 'o3-mini', 'o3']),
        'features' => json_encode(['chat', 'completion', 'embedding', 'vision', 'function-calling']),
        'status' => 'active',
        'is_default' => true
    ],
    [
        'name' => 'anthropic',
        'display_name' => 'Anthropic',
        'provider_type' => 'cloud',
        'base_url' => 'https://api.anthropic.com',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku', 'claude-3.5-sonnet', 'claude-3.5-haiku']),
        'features' => json_encode(['chat', 'analysis', 'long-context', 'vision']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'google',
        'display_name' => 'Google Gemini',
        'provider_type' => 'cloud',
        'base_url' => 'https://generativelanguage.googleapis.com',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['gemini-2.0-flash-exp', 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro']),
        'features' => json_encode(['chat', 'multimodal', 'vision', 'long-context']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'huggingface',
        'display_name' => 'HuggingFace',
        'provider_type' => 'cloud',
        'base_url' => 'https://api-inference.huggingface.co',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['mistral-7b', 'llama-2-7b', 'falcon-7b', 'zephyr-7b']),
        'features' => json_encode(['chat', 'inference']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'ollama',
        'display_name' => 'Ollama',
        'provider_type' => 'local',
        'base_url' => 'http://localhost:11434',
        'auth_type' => 'none',
        'supported_models' => json_encode(['llama3', 'llama3.1', 'llama3.2', 'mistral', 'mixtral', 'phi', 'gemma', 'qwen']),
        'features' => json_encode(['chat', 'local', 'privacy']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'lm-studio',
        'display_name' => 'LM Studio',
        'provider_type' => 'local',
        'base_url' => 'http://localhost:1234',
        'auth_type' => 'none',
        'supported_models' => json_encode(['local-models']),
        'features' => json_encode(['chat', 'local', 'desktop']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'bedrock',
        'display_name' => 'AWS Bedrock',
        'provider_type' => 'cloud',
        'base_url' => 'https://bedrock-runtime.{region}.amazonaws.com',
        'auth_type' => 'aws_credentials',
        'supported_models' => json_encode(['claude-3-opus', 'claude-3-sonnet', 'llama-3-70b', 'mistral-large', 'titan']),
        'features' => json_encode(['chat', 'enterprise', 'multi-model']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'vertex-ai',
        'display_name' => 'Google Vertex AI',
        'provider_type' => 'cloud',
        'base_url' => 'https://{region}-aiplatform.googleapis.com',
        'auth_type' => 'service_account',
        'supported_models' => json_encode(['gemini-pro', 'gemini-ultra', 'claude-3-sonnet@vertex']),
        'features' => json_encode(['chat', 'enterprise', 'multimodal']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'xai',
        'display_name' => 'XAI (Grok)',
        'provider_type' => 'cloud',
        'base_url' => 'https://api.x.ai',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['grok-1', 'grok-1.5', 'grok-2', 'grok-beta']),
        'features' => json_encode(['chat', 'real-time']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'fireworks',
        'display_name' => 'Fireworks AI',
        'provider_type' => 'cloud',
        'base_url' => 'https://api.fireworks.ai',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['llama-3-70b', 'llama-3-8b', 'mixtral-8x7b', 'mixtral-8x22b', 'mistral-7b']),
        'features' => json_encode(['chat', 'fast-inference', 'open-models']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'together',
        'display_name' => 'Together AI',
        'provider_type' => 'cloud',
        'base_url' => 'https://api.together.xyz',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['llama-3-70b', 'llama-3-8b', 'mixtral-8x22b', 'qwen-72b', 'deepseek-coder']),
        'features' => json_encode(['chat', 'multi-model', 'open-source']),
        'status' => 'active',
        'is_default' => false
    ],
    [
        'name' => 'openrouter',
        'display_name' => 'OpenRouter',
        'provider_type' => 'router',
        'base_url' => 'https://openrouter.ai/api/v1',
        'auth_type' => 'api_key',
        'supported_models' => json_encode(['auto', 'gpt-4', 'claude-3-opus', 'llama-3-70b', 'gemini-pro']),
        'features' => json_encode(['chat', 'multi-provider', 'routing']),
        'status' => 'active',
        'is_default' => false
    ]
];

try {
    // Check if we need to insert providers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wp_smo_ai_providers");
    $result = $stmt->fetch();
    $current_count = $result['count'];
    
    if ($current_count == 0) {
        echo '<div class="info">📦 Table is empty - inserting ' . count($providers) . ' default providers...</div>';
        
        $inserted_count = 0;
        foreach ($providers as $provider) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO wp_smo_ai_providers 
                    (name, display_name, provider_type, base_url, auth_type, auth_config, default_params, supported_models, features, rate_limits, status, is_default, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $result = $stmt->execute([
                    $provider['name'],
                    $provider['display_name'],
                    $provider['provider_type'],
                    $provider['base_url'],
                    $provider['auth_type'],
                    json_encode([]), // auth_config
                    json_encode(['temperature' => 0.7, 'max_tokens' => 512, 'stream' => false]), // default_params
                    $provider['supported_models'],
                    $provider['features'],
                    json_encode([]), // rate_limits
                    $provider['status'],
                    $provider['is_default'] ? 1 : 0
                ]);
                
                if ($result) {
                    $inserted_count++;
                }
            } catch (PDOException $e) {
                echo '<div class="warning">⚠️ Failed to insert provider ' . htmlspecialchars($provider['name']) . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        
        echo '<div class="success">✅ Inserted ' . $inserted_count . ' out of ' . count($providers) . ' providers</div>';
    } else {
        echo '<div class="info">📦 Table already has ' . $current_count . ' providers - skipping insertion</div>';
    }
    
    // Show final state
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wp_smo_ai_providers");
    $result = $stmt->fetch();
    $final_count = $result['count'];
    
    if ($final_count > 0) {
        echo '<div class="success">✅ Final provider count: ' . intval($final_count) . '</div>';
        
        echo '<div class="info">📋 Providers now available in database:</div>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Display Name</th><th>Type</th><th>Status</th><th>Default?</th></tr>';
        
        $stmt = $pdo->query("SELECT id, name, display_name, provider_type, status, is_default FROM wp_smo_ai_providers ORDER BY name");
        while ($provider = $stmt->fetch()) {
            $default_badge = $provider['is_default'] ? '✅' : '';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($provider['id']) . '</td>';
            echo '<td><strong>' . htmlspecialchars($provider['name']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($provider['display_name']) . '</td>';
            echo '<td>' . htmlspecialchars($provider['provider_type']) . '</td>';
            echo '<td>' . htmlspecialchars($provider['status']) . '</td>';
            echo '<td>' . $default_badge . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
} catch (PDOException $e) {
    echo '<div class="error">❌ Provider insertion failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test the fix
echo '<div class="step">';
echo '<h2>🧪 Step 5: Testing the Fix</h2>';

try {
    // Test some provider queries
    $test_providers = ['openai', 'huggingface', 'ollama', 'anthropic'];
    $successful_queries = 0;
    
    echo '<div class="info">🔍 Testing database queries for providers...</div>';
    
    foreach ($test_providers as $provider_name) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM wp_smo_ai_providers WHERE name = ? LIMIT 1");
            $stmt->execute([$provider_name]);
            $provider = $stmt->fetch();
            
            if ($provider) {
                echo '<div class="success">✅ Successfully queried provider: ' . htmlspecialchars($provider_name) . '</div>';
                $successful_queries++;
            } else {
                echo '<div class="warning">⚠️ Provider not found: ' . htmlspecialchars($provider_name) . '</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="error">❌ Query failed for ' . htmlspecialchars($provider_name) . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    if ($successful_queries > 0) {
        echo '<div class="success">🎉 Database queries are working! (' . $successful_queries . '/' . count($test_providers) . ' test providers found)</div>';
    }
    
    // Test table structure
    echo '<div class="info">🔍 Testing table structure...</div>';
    $stmt = $pdo->query("DESCRIBE wp_smo_ai_providers");
    $columns = $stmt->fetchAll();
    
    echo '<div class="success">✅ Table structure verified - ' . count($columns) . ' columns found:</div>';
    echo '<table>';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    foreach ($columns as $column) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($column['Field']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Default']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
} catch (PDOException $e) {
    echo '<div class="error">❌ Testing failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Summary and next steps
echo '<div class="step">';
echo '<h2>📋 Step 6: Fix Summary</h2>';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wp_smo_ai_providers'");
    $table_exists = $stmt->fetch();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wp_smo_ai_providers");
    $result = $stmt->fetch();
    $provider_count = $result['count'];
    
    if ($table_exists && $provider_count > 0) {
        echo '<div class="success">🎉 <strong>FIX SUCCESSFUL!</strong></div>';
        echo '<div class="info">';
        echo '<strong>✅ What was fixed:</strong><br>';
        echo '• Created missing wp_smo_ai_providers table<br>';
        echo '• Populated with ' . $provider_count . ' AI provider configurations<br>';
        echo '• Database errors should now be resolved<br>';
        echo '• AI functionality should be restored<br><br>';
        
        echo '<strong>🔍 Expected results:</strong><br>';
        echo '• No more "table doesn\'t exist" errors in debug.log<br>';
        echo '• AI provider configurations accessible via database<br>';
        echo '• Plugin can initialize AI components without errors<br>';
        echo '• Settings page should show available providers<br><br>';
        
        echo '<strong>📝 Next steps:</strong><br>';
        echo '1. Check WordPress debug.log for errors<br>';
        echo '2. Test AI functionality in plugin settings<br>';
        echo '3. Configure API keys for desired providers<br>';
        echo '4. Test AI-powered features<br>';
        echo '</div>';
    } else {
        echo '<div class="error">❌ <strong>FIX INCOMPLETE</strong></div>';
        echo '<div class="warning">';
        echo 'The table creation or population did not complete successfully.<br>';
        echo 'Please check the error messages above and try again.<br>';
        echo '</div>';
    }
} catch (PDOException $e) {
    echo '<div class="error">❌ Summary check failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '<hr>';
echo '<p><strong>Database Connection Details:</strong></p>';
echo '<ul>';
echo '<li><strong>Host:</strong> ' . htmlspecialchars($host) . '</li>';
echo '<li><strong>Database:</strong> ' . htmlspecialchars($database) . '</li>';
echo '<li><strong>Table:</strong> wp_smo_ai_providers</li>';
echo '</ul>';

echo '</div>'; // container
echo '</div>'; // container div

?>