<?php
/**
 * XAMPP WebSocket Auto-Setup Script
 * Ρυθμίζει αυτόματα το XAMPP για WebSocket server
 */

echo "=== XAMPP WebSocket Auto-Setup ===\n";
echo "Προετοιμασία XAMPP environment για WebSocket...\n\n";

// Βήμα 1: Εύρεση XAMPP directory (χρήση user-provided path)
$xampp_dir = 'C:\\xampp'; // Χρησιμοποιούμε το path που δόθηκε από τον χρήστη

// Έλεγχος αν το XAMPP directory υπάρχει
if (!is_dir($xampp_dir)) {
    die("❌ XAMPP directory not found: {$xampp_dir}\n" .
        "Παρακαλώ ελέγξτε ότι το XAMPP είναι εγκατεστημένο στη σωστή θέση.\n");
}

// Έλεγχος αν τα βασικά XAMPP files υπάρχουν
if (!is_file($xampp_dir . '\\php\\php.exe')) {
    die("❌ PHP executable not found: {$xampp_dir}\\php\\php.exe\n" .
        "Παρακαλώ ελέγξτε ότι το XAMPP είναι σωστά εγκατεστημένο.\n");
}

if (!is_dir($xampp_dir . '\\htdocs')) {
    die("❌ htdocs directory not found: {$xampp_dir}\\htdocs\n");
}

echo "✅ XAMPP directory: {$xampp_dir}\n";

$php_ini = $xampp_dir . '\\php\\php.ini';
$htdocs = $xampp_dir . '\\htdocs';

// Βήμα 2: Έλεγχος και ρύθμιση php.ini
echo "\n🔧 Ρύθμιση PHP Sockets Extension...\n";

if (!file_exists($php_ini)) {
    die("❌ php.ini not found: {$php_ini}\n");
}

$php_ini_content = file_get_contents($php_ini);

// Έλεγχος αν sockets extension είναι enabled
$sockets_enabled = false;
if (preg_match('/^extension\s*=\s*sockets/sm', $php_ini_content)) {
    $sockets_enabled = true;
    echo "✅ Sockets extension already enabled\n";
} elseif (preg_match('/^;extension\s*=\s*sockets/sm', $php_ini_content)) {
    // Ενεργοποίηση sockets
    $php_ini_content = preg_replace('/^;extension\s*=\s*sockets/sm', 'extension=sockets', $php_ini_content);
    file_put_contents($php_ini, $php_ini_content);
    $sockets_enabled = true;
    echo "✅ Sockets extension enabled\n";
} else {
    // Προσθήκη sockets extension
    $php_ini_content .= "\n; SMO Social WebSocket Extension\n";
    $php_ini_content .= "extension=sockets\n";
    file_put_contents($php_ini, $php_ini_content);
    $sockets_enabled = true;
    echo "✅ Sockets extension added\n";
}

// Βήμα 3: Αντιγραφή WebSocket server files
echo "\n📁 Προετοιμασία WebSocket files...\n";

$plugin_webroot = dirname(__DIR__);
$target_websocket_file = $htdocs . '\\smo-websocket-server.php';
$target_batch_file = $htdocs . '\\start-websocket-server.bat';

try {
    // Αντιγραφή PHP WebSocket server
    $websocket_source = __DIR__ . '\\xampp-websocket-server.php';
    if (file_exists($websocket_source)) {
        if (copy($websocket_source, $target_websocket_file)) {
            echo "✅ WebSocket server copied to htdocs\n";
        } else {
            throw new Exception("Δεν μπορώ να αντιγράψω το WebSocket server");
        }
    } else {
        throw new Exception("WebSocket server file not found: {$websocket_source}");
    }

    // Αντιγραφή batch file
    $batch_source = __DIR__ . '\\start-websocket-server.bat';
    if (file_exists($batch_source)) {
        if (copy($batch_source, $target_batch_file)) {
            echo "✅ Batch launcher copied to htdocs\n";
        } else {
            throw new Exception("Δεν μπορώ να αντιγράψω το batch file");
        }
    } else {
        throw new Exception("Batch file not found: {$batch_source}");
    }

} catch (Exception $e) {
    echo "⚠️  Warning: " . $e->getMessage() . "\n";
    echo "   Θα χρησιμοποιήσω το plugin directory ως fallback\n";
    
    $target_websocket_file = $plugin_webroot . '\\smo-websocket-server.php';
    $target_batch_file = $plugin_webroot . '\\start-websocket-server.bat';
    
    copy(__DIR__ . '\\xampp-websocket-server.php', $target_websocket_file);
    copy(__DIR__ . '\\start-websocket-server.bat', $target_batch_file);
    
    echo "✅ Files copied to plugin directory\n";
}

// Βήμα 4: Δημιουργία startup script
echo "\n🚀 Δημιουργία startup script...\n";

$startup_script = $htdocs . '\\start-smo-websocket.bat';
$startup_content = "@echo off\necho === SMO WebSocket Server Starter ===\necho.\ncd /d \"{$htdocs}\"\nphp smo-websocket-server.php\npause\n";

file_put_contents($startup_script, $startup_content);
echo "✅ Startup script created: {$startup_script}\n";

// Βήμα 5: Έλεγχος PHP version και extensions
echo "\n🔍 Έλεγχος PHP Environment...\n";

$php_executable = $xampp_dir . '\\php\\php.exe';
exec("\"{$php_executable}\" --version 2>&1", $php_version_output, $return_code);

if ($return_code === 0) {
    echo "✅ PHP found: " . implode("\n", $php_version_output);
} else {
    echo "❌ PHP not working properly\n";
}

exec("\"{$php_executable}\" -m 2>&1", $extensions_output, $return_code);
$extensions_list = implode("\n", $extensions_output);

if (strpos($extensions_list, 'sockets') !== false) {
    echo "✅ Sockets extension: AVAILABLE\n";
} else {
    echo "❌ Sockets extension: NOT FOUND\n";
    echo "   Παρακαλώ επανεκκινήστε τον Apache στο XAMPP\n";
}

// Βήμα 6: Δημιουργία test page
echo "\n📄 Δημιουργία test page...\n";

$test_page = $htdocs . '\\smo-websocket-test.html';
$test_content = '<!DOCTYPE html>
<html>
<head>
    <title>SMO WebSocket Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        #messages { margin-top: 20px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <h1>🧪 SMO WebSocket Test Page</h1>
    
    <div id="status" class="status info">Ετοιμασία...</div>
    <div id="messages"></div>
    
    <script>
        const statusDiv = document.getElementById(\'status\');
        const messagesDiv = document.getElementById(\'messages\');
        
        function addMessage(message, type = \'info\') {
            const div = document.createElement(\'div\');
            div.innerHTML = `<strong>${new Date().toLocaleTimeString()}:</strong> ${message}`;
            div.style.color = type === \'success\' ? \'green\' : type === \'error\' ? \'red\' : \'black\';
            messagesDiv.appendChild(div);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
        function updateStatus(message, type = \'info\') {
            statusDiv.textContent = message;
            statusDiv.className = `status ${type}`;
        }
        
        // Test WebSocket connection
        function testWebSocket() {
            try {
                updateStatus(\'Σύνδεση σε WebSocket server...\', \'info\');
                addMessage(\'Προσπάθεια σύνδεσης σε ws://127.0.0.1:8080\');
                
                const ws = new WebSocket(\'ws://127.0.0.1:8080\');
                
                ws.onopen = function(event) {
                    updateStatus(\'✅ WebSocket συνδεδεμένο!\', \'success\');
                    addMessage(\'🎉 Επιτυχής σύνδεση στον WebSocket server\', \'success\');
                    
                    // Send test message
                    ws.send(JSON.stringify({
                        type: \'authenticate\',
                        token: \'test_token_123\'
                    }));
                    addMessage(\'📤 Αποστολή test authentication\');
                    
                    setTimeout(() => {
                        ws.send(JSON.stringify({
                            type: \'subscribe\',
                            channel: \'test_channel\'
                        }));
                        addMessage(\'📋 Subscription στο test channel\');
                    }, 1000);
                };
                
                ws.onmessage = function(event) {
                    try {
                        const message = JSON.parse(event.data);
                        addMessage(`📨 Μήνυμα: ${JSON.stringify(message, null, 2)}`);
                        
                        if (message.type === \'authenticated\') {
                            updateStatus(\'✅ Authentication επιτυχής!\', \'success\');
                        }
                    } catch (e) {
                        addMessage(`📨 Raw data: ${event.data}`);
                    }
                };
                
                ws.onerror = function(error) {
                    updateStatus(\'❌ WebSocket Error\', \'error\');
                    addMessage(`❌ Connection error: ${error}`, \'error\');
                    console.error(\'WebSocket error:\', error);
                };
                
                ws.onclose = function(event) {
                    updateStatus(\'🔌 WebSocket αποσυνδεδεμένο\', \'info\');
                    addMessage(`🔌 Connection closed: ${event.code} ${event.reason}`, \'info\');
                };
                
            } catch (error) {
                updateStatus(\'❌ JavaScript Error\', \'error\');
                addMessage(`❌ Error: ${error.message}`, \'error\');
            }
        }
        
        // Start test on page load
        window.onload = function() {
            addMessage(\'🚀 Εκκίνηση WebSocket test...\');
            testWebSocket();
            
            // Auto-retry every 10 seconds if failed
            setInterval(() => {
                const currentStatus = statusDiv.textContent;
                if (currentStatus.includes(\'Error\') || currentStatus.includes(\'αποσυνδεδεμένο\')) {
                    addMessage(\'🔄 Αυτόματη επανάπειρα...\');
                    testWebSocket();
                }
            }, 10000);
        };
    </script>
</body>
</html>';

file_put_contents($test_page, $test_content);
echo "✅ Test page created: {$test_page}\n";

// Βήμα 7: Οδηγίες χρήσης
echo "\n📋 ΟΔΗΓΙΕΣ ΧΡΗΣΗΣ:\n";
echo "=====================\n\n";

echo "1. 🚀 ΕΚΚΙΝΗΣΗ WebSocket Server:\n";
echo "   • Κάντε διπλό κλικ: {$startup_script}\n";
echo "   • Ή από command line: php {$target_websocket_file}\n\n";

echo "2. 🧪 TEST WebSocket:\n";
echo "   • Ανοίξτε σε browser: http://localhost/smo-websocket-test.html\n\n";

echo "3. 🔧 WordPress Plugin:\n";
echo "   • Το plugin θα εντοπίσει αυτόματα τον local server\n";
echo "   • Θα χρησιμοποιήσει: ws://127.0.0.1:8080\n\n";

echo "4. 📁 Files τοποθετημένα στο:\n";
echo "   • WebSocket Server: {$target_websocket_file}\n";
echo "   • Test Page: {$test_page}\n";
echo "   • Startup Script: {$startup_script}\n\n";

echo "⚠️  ΣΗΜΑΝΤΙΚΟ:\n";
echo "• Επανεκκινήστε τον Apache στο XAMPP για να ενεργοποιηθούν τα sockets\n";
echo "• Βεβαιωθείτε ότι το Windows Firewall επιτρέπει την PHP\n";
echo "• Χρησιμοποιήστε τη θύρα 8080 (προεπιλογή)\n\n";

echo "🎉 SETUP ΟΛΟΚΛΗΡΩΘΗΚΕ!\n";
echo "Ο WebSocket server είναι έτοιμος να τρέξει στο XAMPP!\n";
?>