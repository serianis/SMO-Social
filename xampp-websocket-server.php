<?php
/**
 * XAMPP WebSocket Server
 * Αυτόνομος WebSocket server για XAMPP environment
 * Τρέξτε: php xampp-websocket-server.php
 */

echo "=== XAMPP WebSocket Server ===\n";
echo "Starting WebSocket server on XAMPP...\n";

// Έλεγχος αν υπάρχει sockets extension
if (!extension_loaded('sockets')) {
    die("❌ ERROR: Sockets extension not loaded!\n" .
        "Enable it in C:\\xampp\\php\\php.ini (remove ';' from extension=sockets)\n");
}

echo "✓ Sockets extension loaded successfully\n";

// Ρυθμίσεις server
$host = '127.0.0.1';  // localhost για XAMPP
$port = 8080;
$max_connections = 100;

// Έλεγχος διαθεσιμότητας θύρας
function check_port_availability($host, $port, $max_attempts = 5) {
    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            continue;
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);
        
        $connected = @socket_connect($socket, $host, $port);
        socket_close($socket);
        
        if ($connected) {
            // Θύρα κατειλημμένη, δοκιμάζουμε επόμενη
            $port++;
            $prevPort = $port - 1;
            echo "⚠️  Port {$prevPort} is busy, trying port {$port}...\n";
        } else {
            // Θύρα διαθέσιμη
            return $port;
        }
    }
    
    // Όλες οι θύρες κατειλημμένες
    return false;
}

// Έλεγχος διαθεσιμότητας θύρας
$available_port = check_port_availability($host, $port);
if ($available_port === false) {
    die("❌ ERROR: No available ports in range {$port}-" . ($port + 4) . "\n" .
        "Please check if other WebSocket servers are running.\n");
}

if ($available_port !== $port) {
    $port = $available_port;
    echo "✓ Using alternative port: {$port}\n";
}

echo "✓ Creating WebSocket server on {$host}:{$port}\n";

// Δημιουργία socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$socket) {
    die("❌ ERROR: Cannot create socket\n");
}

echo "✓ Socket created successfully\n";

// Ρύθμιση socket options
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_set_nonblock($socket);

// Bind socket
if (!socket_bind($socket, $host, $port)) {
    $error = socket_strerror(socket_last_error($socket));
    die("❌ ERROR: Cannot bind to {$host}:{$port} - {$error}\n");
}

echo "✓ Socket bound to {$host}:{$port}\n";

// Listen για connections
if (!socket_listen($socket, $max_connections)) {
    die("❌ ERROR: Cannot listen on socket\n");
}

echo "✓ Server listening for connections\n";
echo "🚀 WebSocket Server Ready!\n";
echo "URL: ws://{$host}:{$port}\n";
echo "Press Ctrl+C to stop the server\n\n";

// Διαχείριση connections
$connections = [];
$channels = [];

// Κύρια loop του server
while (true) {
    // Έλεγχος για νέες συνδέσεις
    $read = [$socket];
    foreach ($connections as $conn) {
        $read[] = $conn['socket'];
    }
    
    $write = null;
    $except = null;
    $timeout = 0;
    $microseconds = 100000; // 0.1 seconds
    
    if (socket_select($read, $write, $except, $timeout, $microseconds) > 0) {
        // Νέα σύνδεση
        if (in_array($socket, $read)) {
            $new_socket = socket_accept($socket);
            if ($new_socket) {
                socket_set_nonblock($new_socket);
                $conn_id = uniqid('conn_', true);
                $connections[$conn_id] = [
                    'socket' => $new_socket,
                    'connected_at' => time(),
                    'channels' => [],
                    'authenticated' => false,
                    'user_id' => null
                ];
                echo "📱 New connection: {$conn_id}\n";
            }
        }
        
        // Επεξεργασία υπαρχόντων connections
        foreach ($connections as $conn_id => $connection) {
            if (in_array($connection['socket'], $read)) {
                $data = @socket_read($connection['socket'], 2048);
                
                if ($data === false || $data === '') {
                    // Αποσύνδεση
                    echo "📴 Connection closed: {$conn_id}\n";
                    socket_close($connection['socket']);
                    unset($connections[$conn_id]);
                    continue;
                }
                
                // Επεξεργασία WebSocket message
                handle_message($connection, $data, $conn_id, $channels);
            }
        }
    }
    
    // Cleanup παλιών connections
    foreach ($connections as $conn_id => $connection) {
        if (time() - $connection['connected_at'] > 300) { // 5 minutes timeout
            echo "⏰ Connection timeout: {$conn_id}\n";
            socket_close($connection['socket']);
            unset($connections[$conn_id]);
        }
    }
}

socket_close($socket);

/**
 * Επεξεργασία WebSocket μηνυμάτων
 */
function handle_message($connection, $data, $conn_id, &$channels) {
    global $connections;
    
    // WebSocket handshake
    if (strpos($data, 'Sec-WebSocket-Key') !== false) {
        perform_handshake($connection['socket'], $data);
        echo "🤝 WebSocket handshake completed for {$conn_id}\n";
        return;
    }
    
    // Αποκωδικοποίηση WebSocket frame
    $message = decode_websocket_frame($data);
    if (!$message) {
        return;
    }
    
    echo "📨 Message from {$conn_id}: " . substr($message, 0, 100) . "...\n";
    
    // Επεξεργασία JSON message
    try {
        $msg_data = json_decode($message, true);
        if (!$msg_data) {
            return;
        }
        
        switch ($msg_data['type'] ?? '') {
            case 'authenticate':
                handle_authentication($conn_id, $msg_data, $connection, $channels);
                break;
                
            case 'subscribe':
                handle_subscribe($conn_id, $msg_data, $connection, $channels);
                break;
                
            case 'publish':
                handle_publish($conn_id, $msg_data, $connection, $channels);
                break;
                
            case 'ping':
                send_message($connection['socket'], ['type' => 'pong']);
                break;
        }
        
    } catch (Exception $e) {
        echo "❌ Error processing message: " . $e->getMessage() . "\n";
    }
}

/**
 * WebSocket handshake
 */
function perform_handshake($socket, $data) {
    $lines = explode("\r\n", $data);
    $key = '';
    
    foreach ($lines as $line) {
        if (strpos($line, 'Sec-WebSocket-Key:') !== false) {
            $key = trim(substr($line, 19));
            break;
        }
    }
    
    if (!$key) {
        return false;
    }
    
    $accept_key = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    
    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$accept_key}\r\n\r\n";
    
    socket_write($socket, $response, strlen($response));
    return true;
}

/**
 * Αποκωδικοποίηση WebSocket frame
 */
function decode_websocket_frame($data) {
    if (strlen($data) < 2) {
        return false;
    }
    
    $firstByte = ord($data[0]);
    $secondByte = ord($data[1]);
    
    $payloadLength = $secondByte & 0x7F;
    $masked = ($secondByte & 0x80) === 0x80;
    
    $offset = 2;
    
    if ($payloadLength === 126) {
        $payloadLength = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($payloadLength === 127) {
        $payloadLength = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }
    
    $maskingKey = '';
    if ($masked) {
        $maskingKey = substr($data, $offset, 4);
        $offset += 4;
    }
    
    $payload = substr($data, $offset, $payloadLength);
    
    if ($masked) {
        for ($i = 0; $i < strlen($payload); $i++) {
            $payload[$i] = chr(ord($payload[$i]) ^ ord($maskingKey[$i % 4]));
        }
    }
    
    return $payload;
}

/**
 * Κωδικοποίηση WebSocket frame
 */
function encode_websocket_frame($payload) {
    $payloadLength = strlen($payload);
    $frame = chr(0x81); // Text frame with no mask
    
    if ($payloadLength <= 125) {
        $frame .= chr($payloadLength);
    } elseif ($payloadLength <= 65535) {
        $frame .= chr(126) . pack('n', $payloadLength);
    } else {
        $frame .= chr(127) . pack('J', $payloadLength);
    }
    
    $frame .= $payload;
    
    return $frame;
}

/**
 * Αποστολή μηνύματος σε connection
 */
function send_message($socket, $message) {
    $payload = json_encode($message);
    $frame = encode_websocket_frame($payload);
    return socket_write($socket, $frame, strlen($frame));
}

/**
 * Χειρισμός authentication
 */
function handle_authentication($conn_id, $msg_data, $connection, &$channels) {
    global $connections;
    
    $token = $msg_data['token'] ?? '';
    
    // Απλό validation - στην πραγματικότητα θα πρέπει να ελέγξετε το token
    if (!empty($token)) {
        $connections[$conn_id]['authenticated'] = true;
        $connections[$conn_id]['user_id'] = 'user_' . substr($token, 0, 8);
        
        send_message($connection['socket'], [
            'type' => 'authenticated',
            'user_id' => $connections[$conn_id]['user_id']
        ]);
        
        echo "✅ User authenticated: {$conn_id}\n";
    }
}

/**
 * Χειρισμός subscription
 */
function handle_subscribe($conn_id, $msg_data, $connection, &$channels) {
    global $connections;
    
    $channel = $msg_data['channel'] ?? '';
    
    if (!empty($channel)) {
        if (!isset($channels[$channel])) {
            $channels[$channel] = [];
        }
        
        $channels[$channel][$conn_id] = true;
        $connections[$conn_id]['channels'][] = $channel;
        
        send_message($connection['socket'], [
            'type' => 'subscribed',
            'channel' => $channel
        ]);
        
        echo "📋 Subscribed to channel: {$channel}\n";
    }
}

/**
 * Χειρισμός publish
 */
function handle_publish($conn_id, $msg_data, $connection, &$channels) {
    global $connections;
    
    $channel = $msg_data['channel'] ?? '';
    $data = $msg_data['data'] ?? [];
    
    if (!empty($channel) && isset($channels[$channel])) {
        $message = [
            'type' => 'message',
            'channel' => $channel,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to all subscribers
        foreach ($channels[$channel] as $sub_conn_id => $active) {
            if ($sub_conn_id !== $conn_id && isset($connections[$sub_conn_id])) {
                send_message($connections[$sub_conn_id]['socket'], $message);
            }
        }
        
        echo "📤 Published to channel: {$channel}\n";
    }
}
?>