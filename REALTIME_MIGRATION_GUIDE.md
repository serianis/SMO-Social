# Real-Time System Migration Guide

## Εισαγωγή

Αυτό το έγγραφο περιγράφει τη μετάβαση από το παλιό WebSocket-based real-time system στο νέο WordPress-native REST API polling system.

## Τι Άλλαξε

### Παλιό Σύστημα (WebSocket)
- Απαιτούσε εξωτερικό WebSocket server
- Χρειαζόταν XAMPP setup και sockets extension
- Δεν λειτουργούσε σε shared hosting
- Περίπλοκη διαχείριση συνδέσεων
- Απαιτούσε ειδική διαμόρφωση firewall

### Νέο Σύστημα (REST API Polling)
- Χρησιμοποιεί καθαρό HTTP και WordPress REST API
- Δεν απαιτεί εξωτερικούς servers
- Λειτουργεί σε όλα τα WordPress hosting environments
- Απλούστερη και αξιόπιστη υλοποίηση
- Καλύτερο error handling

## Αρχιτεκτονική

### Νέα Components

1. **RealTimeManager** (`includes/RealTime/RealTimeManager.php`)
   - Κύριος orchestrator του συστήματος
   - Διαχειρίζεται όλα τα υπόλοιπα components

2. **RESTAPIManager** (`includes/RealTime/RESTAPIManager.php`)
   - Διαχείριση REST API endpoints
   - Επεξεργασία αιτημάτων και απαντήσεων

3. **DataManager** (`includes/RealTime/DataManager.php`)
   - Αποθήκευση και ανάκτηση δεδομένων
   - Διαχείριση channels, messages, και subscribers

4. **PollingManager** (`includes/RealTime/PollingManager.php`)
   - Διαχείριση polling sessions
   - Παρακολούθηση ενεργών συνδέσεων

5. **MigrationManager** (`includes/RealTime/MigrationManager.php`)
   - Εργαλεία για μετάβαση από WebSocket
   - Backup και rollback δυνατότητες

### JavaScript Client

- **smo-polling-client.js** (`assets/js/smo-polling-client.js`)
  - Αντικαθιστά το παλιό smo-realtime.js
  - Χρησιμοποιεί HTTP polling αντί WebSocket
  - Συμβατό με όλα τα browsers

## Εγκατάσταση

### Αυτόματη Εγκατάσταση

1. Ενεργοποιήστε το plugin
2. Το νέο σύστημα ενεργοποιείται αυτόματα
3. Επισκεφτείτε τις ρυθμίσεις: `SMO Social → Real-Time`

### Χειροκίνητη Μετάβαση

1. Επισκεφτείτε: `SMO Social → Real-Time Settings`
2. Πατήστε "Run Migration"
3. Ακολουθήστε τα βήματα μετάβασης
4. Ελέγξτε τα αποτελέσματα

## Ρυθμίσεις

### Βασικές Ρυθμίσεις

- **Enable real-time features**: Ενεργοποίηση/Απενεργοποίηση του συστήματος
- **Polling Interval**: Χρόνος μεταξύ polling requests (2-30 δευτερόλεπτα)
- **Max Channels per User**: Μέγιστος αριθμός channels ανά χρήστη
- **Debug Mode**: Ενεργοποίηση λεπτομερούς logging

### Προηγμένες Ρυθμίσεις

Για προηγμένους χρήστες, οι ρυθμίσεις μπορούν να τροποποιηθούν μέσω:

```php
// Παράδειγμα ρύθμισης polling interval
add_filter('smo_realtime_config', function($config) {
    $config['default_poll_interval'] = 10; // 10 δευτερόλεπτα
    return $config;
});
```

## API Endpoints

### REST API Endpoints

#### Subscribe to Channel
```
POST /wp-json/smo-social/v1/realtime/subscribe
```
Parameters:
- `channel` (string, required): Channel name
- `token` (string, optional): Authentication token

#### Unsubscribe from Channel
```
POST /wp-json/smo-social/v1/realtime/unsubscribe
```
Parameters:
- `channel` (string, required): Channel name

#### Get Messages
```
GET /wp-json/smo-social/v1/realtime/messages
```
Parameters:
- `channel` (string, required): Channel name
- `since` (string, optional): Timestamp to get messages after

#### Publish Message
```
POST /wp-json/smo-social/v1/realtime/publish
```
Parameters:
- `channel` (string, required): Channel name
- `data` (object, required): Message data
- `type` (string, optional): Message type

#### Get System Status
```
GET /wp-json/smo-social/v1/realtime/status
```

## JavaScript Client Usage

### Βασική Χρήση

```javascript
// Αρχικοποίηση
const client = new SMOPollingClient();

// Σύνδεση
await client.connect(token);

// Εγγραφή σε channel
await client.subscribe('comments_post_123');

// Αποσυνδεση από channel
await client.unsubscribe('comments_post_123');

// Αποστολή μηνύματος
await client.publish('comments_post_123', {
    type: 'new_comment',
    data: { comment: 'Hello!' }
});
```

### Event Handlers

```javascript
// Εγγραφή σε events
client.onComment((channel, data) => {
    console.log('New comment:', data);
});

client.onCollaboration((channel, data) => {
    console.log('Collaboration update:', data);
});

client.onActivity((channel, data) => {
    console.log('Activity update:', data);
});
```

## Migration Steps

### 1. Ανάλυση Τρέχουσας Κατάστασης
- Έλεγχος ύπαρξης WebSocket files
- Έλεγχος WebSocket configuration
- Έλεγχος ενεργού WebSocket server

### 2. Backup Δεδομένων
- Αποθήκευση WebSocket configuration
- Αποθήκευση authentication tokens
- Αποθήκευση οποιωνδήποτε υπαρχόντων real-time δεδομένων

### 3. Απενεργοποίηση WebSocket
- Απενεργοποίηση WebSocket server
- Ενημέρωση ρυθμίσεων

### 4. Ενεργοποίηση REST API System
- Ενεργοποίηση νέου συστήματος
- Δημιουργία απαραίτητων database tables
- Έλεγχος λειτουργικότητας

### 5. Ενημέρωση Client Code
- Αντικατάσταση WebSocket client με polling client
- Ενημέρωση API calls
- Έλεγχος συμβατότητας

### 6. Cleanup
- Αφαίρεση WebSocket files
- Αφαίρεση εξωτερικών dependencies
- Ενημέρωση documentation

## Testing

### Βασικά Τεστ

1. **REST API Endpoints**
   ```bash
   # Test status endpoint
   curl http://yoursite.com/wp-json/smo-social/v1/realtime/status
   
   # Test subscription
   curl -X POST http://yoursite.com/wp-json/smo-social/v1/realtime/subscribe \
        -H "Content-Type: application/json" \
        -d '{"channel":"test_channel"}'
   ```

2. **JavaScript Client**
   ```javascript
   // Test in browser console
   const client = new SMOPollingClient();
   client.connect().then(success => {
       console.log('Connected:', success);
   });
   ```

3. **Real-time Functionality**
   - Εγγραφή σε channel
   - Αποστολή μηνύματος
   - Λήψη μηνύματος
   - Έλεγχος χρόνου απόκρισης

### Προηγμένα Τεστ

1. **Load Testing**
   - Πολλαπλά concurrent connections
   - Μεγάλος αριθμός messages
   - Μεγάλος αριθμός channels

2. **Error Handling**
   - Network failures
   - Server errors
   - Invalid requests
   - Timeout scenarios

3. **Performance Testing**
   - Response times
   - Memory usage
   - CPU usage
   - Database performance

## Troubleshooting

### Κοινά Προβλήματα

#### 1. REST API Endpoints Not Working
**Σύμπτωμα**: 404 errors στα REST API endpoints
**Λύση**:
- Ελέγξτε ότι το REST API είναι ενεργοποιημένο
- Ελέγξτε τις ρυθμίσεις του plugin
- Ελέγξτε τα .htaccess rules

#### 2. Polling Not Working
**Σύμπτωμα**: Τα μηνύματα δεν φτάνουν
**Λύση**:
- Ελέγξτε το polling interval
- Ελέγξτε τη σύνδεση στο internet
- Ελέγξτε τα browser console logs

#### 3. Authentication Issues
**Σύμπτωμα**: Μη εξουσιοδοτημένα αιτήματα
**Λύση**:
- Ελέγξτε τα authentication tokens
- Ελέγξτε τα WordPress user permissions
- Ελέγξτε τα nonce values

### Debug Mode

Ενεργοποιήστε το debug mode στις ρυθμίσεις για λεπτομερή logging:

```php
// Ενεργοποίηση debug mode
update_option('smo_realtime_config', [
    'debug_mode' => true
]);
```

### Logs

Τα logs αποθηκεύονται στο WordPress debug log:
- `wp-content/debug.log`
- `wp-admin/options-general.php?page=debug-log`

## Performance Optimization

### Database Optimization
- Regular cleanup of old messages
- Index optimization for real-time tables
- Cache optimization

### Polling Optimization
- Adjust polling interval based on usage
- Use exponential backoff for failures
- Implement smart polling (poll less when idle)

### Memory Management
- Cleanup old sessions
- Limit concurrent connections
- Monitor memory usage

## Security

### Authentication
- All API endpoints require authentication
- Use secure tokens
- Implement rate limiting

### Data Validation
- Validate all input data
- Sanitize output data
- Prevent SQL injection

### HTTPS
- Use HTTPS for all API calls
- Secure WebSocket connections
- Implement HSTS headers

## Backward Compatibility

### API Compatibility
Το νέο σύστημα παρέχει compatibility endpoints για το παλιό WebSocket client:

- `smo_get_websocket_config` - Επιστρέφει REST API URL αντί WebSocket URL
- `smo_websocket_status` - Επιστρέφει REST API status

### Client Compatibility
- Το παλιό JavaScript client θα λαμβάνει σωστό URL για REST API
- Σταδιακή μετάβαση χωρίς διακοπές

## Support

Για περισσότερη βοήθεια:
- Επισκεφτείτε το admin panel: `SMO Social → Real-Time Settings`
- Ελέγξτε τα debug logs
- Επικοινωνήστε με την ομάδα υποστήριξης

## Changelog

### v2.0.0
- Εισαγωγή REST API polling system
- Αφαίρεση WebSocket dependencies
- Βελτίωση performance και αξιοπιστίας
- Νέα admin interface
- Εργαλεία μετάβασης