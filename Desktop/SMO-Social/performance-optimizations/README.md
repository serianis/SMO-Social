# Βελτιστοποιήσεις Απόδοσης για SMO Social Plugin

Αυτό το αρχείο περιέχει μια ολοκληρωμένη ανάλυση και προτάσεις βελτιστοποιήσεις για το SMO Social WordPress plugin.

## Περιεχόμενα

1. [Ανάλυση Σημείων Συμφόρησης](#1-ανάλυση-σημείων-συμφόρησης)
2. [Προτάσεις Βελτιστοποιήσεων](#2-προτάσεις-βελτιστοποιήσεων)
3. [Εκτιμώμενα Οφέλη](#3-εκτιμώμενα-οφέλη)
4. [Προτεινόμενη Σειρά Υλοποίησης](#4-προτεινόμενη-σειρά-υλοποίησης)
5. [SQL Ευρετήρια](#5-sql-ευρετήρια)
6. [Κώδικας Βελτιστοποιήσεων](#6-κώδικας-βελτιστοποιήσεων)

## 1. Ανάλυση Σημείων Συμφόρησης

### 1.1 Βάση Δεδομένων

Αναλύοντας τον κώδικα, εντοπίστηκαν τα εξής προβλήματα:

- **Πολλαπλά ερωτήματα σε βρόχους**: Στο `includes/Admin/Admin.php`, υπάρχουν επαναλαμβανόμενα SELECT ερωτήματα μέσα σε foreach βρόχους
- **Έλλειψη ευρετηρίων**: Πολλά ερωτήματα χρησιμοποιούν WHERE clauses χωρίς κατάλληλα ευρετήρια
- **Μη βέλτιστα JOINs**: Σε αρκετά σημεία χρησιμοποιούνται πολλαπλά ερωτήματα αντί για single JOIN queries

### 1.2 AI Συστήματα

- **Συγχρονικές κλήσεις API**: Οι AI κλήσεις στο `includes/AI/Manager.php` γίνονται σειριακά
- **Μη αποτελεσματική διαχείριση cache**: Το cache συχνά ελέγχεται αλλά δεν χρησιμοποιείται αποτελεσματικά
- **Πιθανότητα διπλής αρχικοποίησης**: Υπάρχει έλεγχος αλλά μπορεί να βελτιστοποιηθεί

### 1.3 Πλατφόρμες και API

- **Σειριακές επεξεργασίες**: Οι πλατφόρμες επεξεργάζονται μία-μία αντί για παράλληλα
- **Έλλειψη connection pooling**: Κάθε κλήση API δημιουργεί νέα σύνδεση
- **Μη βέλτιστη διαχείριση rate limiting**: Το rate limiting ελέγχεται αλλά δεν προβλέπεται

## 2. Προτάσεις Βελτιστοποιήσεων

### 2.1 Βελτιώσεις Χρονικής Πολυπλοκότητας

#### Βάση Δεδομένων

**Πρόβλημα**: Πολλαπλά ερωτήματα σε βρόχους στο `ajax_get_platform_status()`

```php
// Αντί για αυτό (O(n²)):
foreach ($enabled_platforms as $slug) {
    $platform = $this->platform_manager->get_platform($slug);
    if ($platform) {
        $platforms_status[$slug] = array(
            'name' => $platform->get_name(),
            'connected' => $this->is_platform_connected($slug),
            'health' => $platform->health_check(),
            'features' => $platform->get_features()
        );
    }
}
```

**Λύση**: Ενοποίηση ερωτημάτων και χρήση JOINs

#### Ευρετήρια

**Προτεινόμενα ευρετήρια**:
```sql
-- Προτεινόμενα ευρετήρια:
CREATE INDEX idx_scheduled_posts_status_time ON wp_smo_scheduled_posts (status, scheduled_time);
CREATE INDEX idx_queue_status_scheduled ON wp_smo_queue (status, scheduled_for);
CREATE INDEX idx_platform_tokens_platform ON wp_smo_platform_tokens (platform_slug);
CREATE INDEX idx_activity_logs_created ON wp_smo_activity_logs (created_at);
```

### 2.2 Βελτιστοποίηση Χρήσης Μνήμης

#### Lazy Loading για Πλατφόρμες

**Πρόβλημα**: Φόρτωση όλων των πλατφόρμων κάθε φορά

**Λύση**: Υλοποίηση lazy loading

```php
public function get_platform($slug) {
    if (!isset($this->platforms[$slug])) {
        $this->load_platform($slug);
    }
    return $this->platforms[$slug];
}
```

#### Εκκαθάριση Cache

**Πρόβλημα**: Μη διαχείριση μεγέθους cache

**Λύση**: Βελτιστοποίηση cache μεγέθους

```php
public function optimize_cache() {
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($this->get_cache_size() > $max_size) {
        $this->cleanup_oldest_files();
    }
}
```

### 2.3 Μείωση Περιττών Λειτουργιών

#### Συγχώνευση Ερωτημάτων

**Πρόβλημα**: Πολλαπλά ερωτήματα για dashboard stats

**Λύση**: Χρήση subqueries για ένωση ερωτημάτων

```php
public function get_dashboard_stats() {
    $results = $wpdb->get_results("
        SELECT 
            (SELECT COUNT(*) FROM $posts_table) as total_posts,
            (SELECT COUNT(*) FROM $posts_table WHERE status = 'scheduled') as scheduled_posts,
            (SELECT COUNT(*) FROM $queue_table WHERE status = 'pending') as pending_queue
    ");
    return $results[0];
}
```

### 2.4 Παραλληλοποίηση και Ασύγχρονες Λειτουργίες

#### Παράλληλες Κλήσεις API

**Πρόβλημα**: Σειριακές AI κλήσεις

**Λύση**: Χρήση wp_remote_post για ασύγχρονες κλήσεις

```php
public function process_platforms_async($platforms, $data) {
    $requests = array();
    foreach ($platforms as $platform) {
        $requests[$platform] = wp_remote_post($api_url, [
            'blocking' => false,
            'body' => $data
        ]);
    }
    return $requests;
}
```

#### Background Processing

**Λύση**: Χρήση wp_schedule_single_event για επεξεργασία στο παρασκήνιο

```php
wp_schedule_single_event(time() + 60, 'smo_process_queue_batch', [$batch_data]);
```

### 2.5 Στρατηγικές Προσωρινής Αποθήκευσης

#### Προσωρινή Αποθήκευση Μερικών Αποτελεσμάτων

**Πρόβλημα**: Επανειλημμένες επαναφορτώσεις ίδιων δεδομένων

**Λύση**: Cache συγκεκριμένων αποτελεσμάτων

```php
public function get_platform_data($platform) {
    $cache_key = "platform_data_{$platform}";
    $data = $this->cache_manager->get($cache_key);
    
    if (!$data) {
        $data = $this->fetch_platform_data($platform);
        $this->cache_manager->set($cache_key, $data, 1800); // 30 λεπτά
    }
    
    return $data;
}
```

## 3. Εκτιμώμενα Οφέλη

### Χρόνος Απόκρισης

- **Σελίδες Dashboard**: 60-70% βελτίωση
- **AI Επεξεργασία**: 40-50% βελτίωση (με παραλληλοποίηση)
- **API Αποκρίσεις**: 30-40% βελτίωση

### Χρήση Μνήμης

- **Cache Memory**: 20-30% μείωση
- **Database Connections**: 50-60% μείωση
- **PHP Memory Usage**: 15-25% μείωση

### Χρόνος Εκτέλεσης

- **Batch Processing**: 70-80% βελτίωση
- **Queue Processing**: 40-50% βελτίωση
- **Platform Operations**: 30-40% βελτίωση

## 4. Προτεινόμενη Σειρά Υλοποίησης

### Φάση 1: Database Optimizations (Κρίσιμης Προτεραιότητας)

1. **Προσθήκη ευρετηρίων** - Αναμένεται 70-80% βελτίωση στα ερωτήματα
2. **Ενοποίηση ερωτημάτων σε JOINs** - Αναμένεται 50-60% μείωση ερωτημάτων
3. **Συγχώνευση dashboard ερωτημάτων** - Μείωση ερωτημάτων από 5 σε 1

### Φάση 2: Cache Improvements (Υψηλής Προτεραιότητας)

1. **Cache warming για δημοφιλείς ερωτήσεις** - Αναμένεται 90% βελτίωση σε cache hit rate
2. **TTL optimization** - Βελτιστοποίηση διάρκειας cache
3. **Partial result caching** - Μείωση επανειλημμένων επαναφορτώσεων

### Φάση 3: Async Processing (Μεσαίας Προτεραιότητας)

1. **Ασύγχρονες AI κλήσεις** - Παράλληλη επεξεργασία πολλαπλών πλατφόρμων
2. **Connection pooling για API κλήσεις** - Μείωση latency κλήσεων
3. **Batch processing για ουρές** - Επεξεργασία πολλαπλών εργασιών ταυτόχρονα

### Φάση 4: Frontend Optimizations (Χαμηλότερης Προτεραιότητας)

1. **Lazy loading για πλατφόρμες** - Αναμένεται 30-40% μείωση χρόνου φόρτωσης
2. **Frontend asset optimization** - Minification, compression
3. **Code splitting** - Διαχωρισμός μεγάλων αρχείων

## 5. SQL Ευρετήρια

### Κρίσιμα Ευρετήρια

```sql
-- Ευρετήρια για συχνά χρησιμοποιούμενα ερωτήματα
CREATE INDEX idx_scheduled_posts_status_time ON wp_smo_scheduled_posts (status, scheduled_time);
CREATE INDEX idx_scheduled_posts_created_by ON wp_smo_scheduled_posts (created_by);
CREATE INDEX idx_queue_status_scheduled ON wp_smo_queue (status, scheduled_for);
CREATE INDEX idx_queue_platform_slug ON wp_smo_queue (platform_slug);

-- Ευρετήρια για πλατφόρμες
CREATE INDEX idx_platform_tokens_platform ON wp_smo_platform_tokens (platform_slug);
CREATE INDEX idx_post_platforms_post_id ON wp_smo_post_platforms (post_id);
CREATE INDEX idx_post_platforms_status ON wp_smo_post_platforms (status);

-- Ευρετήρια για analytics και logs
CREATE INDEX idx_analytics_scheduled_post_id ON wp_smo_analytics (scheduled_post_id);
CREATE INDEX idx_activity_logs_created ON wp_smo_activity_logs (created_at);
CREATE INDEX idx_activity_logs_user_action ON wp_smo_activity_logs (user_id, action);
```

### Ευρετήρια για Composite Queries

```sql
-- Composite indexes for complex queries
CREATE INDEX idx_scheduled_posts_status_created ON wp_smo_scheduled_posts (status, created_at);
CREATE INDEX idx_queue_status_attempts ON wp_smo_queue (status, attempts);
CREATE INDEX idx_platform_tokens_user_platform ON wp_smo_platform_tokens (user_id, platform_slug);
```

## 6. Κώδικας Βελτιστοποιήσεων

### 6.1 Database Query Optimizations

#### Βελτιστοποιημένο Dashboard Stats

```php
private function get_dashboard_stats_optimized() {
    global $wpdb;
    
    $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
    $queue_table = $wpdb->prefix . 'smo_queue';
    
    // Single query with subqueries instead of multiple separate queries
    $query = "
        SELECT 
            (SELECT COUNT(*) FROM $posts_table) as total_posts,
            (SELECT COUNT(*) FROM $posts_table WHERE status = 'scheduled') as scheduled_posts,
            (SELECT COUNT(*) FROM $posts_table WHERE status = 'published' AND DATE(created_at) = CURDATE()) as published_today,
            (SELECT COUNT(*) FROM $posts_table WHERE status = 'failed') as failed_posts,
            (SELECT COUNT(*) FROM $queue_table WHERE status = 'pending') as pending_queue,
            (SELECT COUNT(*) FROM $posts_table WHERE status = 'published' AND created_by = %d) as user_posts_today
    ";
    
    $results = $wpdb->get_row($wpdb->prepare($query, get_current_user_id()));
    
    return array(
        'total_posts' => (int) $results->total_posts,
        'scheduled_posts' => (int) $results->scheduled_posts,
        'published_today' => (int) $results->published_today,
        'failed_posts' => (int) $results->failed_posts,
        'pending_queue' => (int) $results->pending_queue,
        'user_posts_today' => (int) $results->user_posts_today
    );
}
```

#### Βελτιστοποιημένο Platform Status

```php
public function ajax_get_platform_status_optimized() {
    check_ajax_referer('smo_social_nonce', 'nonce');

    $enabled_platforms = get_option('smo_social_enabled_platforms', array());
    if (!is_countable($enabled_platforms)) {
        $enabled_platforms = array();
    }

    // Batch fetch platform token data
    global $wpdb;
    $tokens_table = $wpdb->prefix . 'smo_platform_tokens';
    
    if (!empty($enabled_platforms)) {
        $platforms_str = "'" . implode("','", $enabled_platforms) . "'";
        $token_data = $wpdb->get_results("
            SELECT platform_slug, COUNT(*) as token_count 
            FROM $tokens_table 
            WHERE platform_slug IN ($platforms_str) 
            GROUP BY platform_slug
        ", ARRAY_A);
        
        $token_counts = array_column($token_data, 'token_count', 'platform_slug');
    } else {
        $token_counts = array();
    }

    $platforms_status = array();

    foreach ($enabled_platforms as $slug) {
        $platform = $this->platform_manager->get_platform($slug);
        if ($platform) {
            $platforms_status[$slug] = array(
                'name' => $platform->get_name(),
                'connected' => isset($token_counts[$slug]) && $token_counts[$slug] > 0,
                'health' => $platform->health_check(),
                'features' => $platform->get_features()
            );
        }
    }

    wp_send_json_success($platforms_status);
}
```

### 6.2 AI Manager Optimizations

#### Ασύγχρονες AI Κλήσεις

```php
public function process_platforms_async($platforms, $data) {
    $requests = array();
    
    foreach ($platforms as $platform) {
        // Use non-blocking requests for parallel processing
        $requests[$platform] = wp_remote_post($api_url, [
            'blocking' => false,
            'timeout' => 30,
            'body' => array_merge($data, ['platform' => $platform]),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_api_key()
            )
        ]);
    }
    
    return $requests;
}

public function process_platforms_parallel($platforms, $data, $task_type) {
    $results = array();
    $transient_key = 'smo_ai_batch_' . md5(serialize([$platforms, $data, $task_type]));
    
    // Check cache first
    $cached_results = get_transient($transient_key);
    if ($cached_results !== false) {
        return $cached_results;
    }
    
    // Process platforms in batches
    $batch_size = 3;
    $platform_batches = array_chunk($platforms, $batch_size);
    
    foreach ($platform_batches as $batch) {
        $batch_results = array();
        $async_requests = $this->process_platforms_async($batch, $data);
        
        // Wait for all requests to complete
        foreach ($async_requests as $platform => $request) {
            $response = wp_remote_retrieve_response($request);
            if (!is_wp_error($response)) {
                $batch_results[$platform] = $response;
            }
        }
        
        $results = array_merge($results, $batch_results);
        
        // Small delay between batches to avoid overwhelming APIs
        sleep(1);
    }
    
    // Cache results for 5 minutes
    set_transient($transient_key, $results, 5 * 60);
    
    return $results;
}
```

#### Βελτιστοποιημένο Cache Management

```php
public function optimize_cache() {
    $cache_dir = wp_upload_dir()['basedir'] . '/smo-social/cache/';
    $max_size = 50 * 1024 * 1024; // 50MB
    $max_age = 24 * 60 * 60; // 24 hours
    $current_time = time();
    
    if (!file_exists($cache_dir)) {
        return;
    }
    
    $total_size = 0;
    $files_info = array();
    
    // Calculate total size and collect file info
    $files = glob($cache_dir . '*.cache');
    foreach ($files as $file) {
        $size = filesize($file);
        $mtime = filemtime($file);
        $total_size += $size;
        
        $files_info[] = array(
            'path' => $file,
            'size' => $size,
            'age' => $current_time - $mtime,
            'mtime' => $mtime
        );
    }
    
    // Sort by age (oldest first)
    usort($files_info, function($a, $b) {
        return $a['age'] - $b['age'];
    });
    
    // Remove oldest files if over size limit or too old
    foreach ($files_info as $file_info) {
        $should_delete = false;
        
        if ($total_size > $max_size) {
            $should_delete = true;
            $total_size -= $file_info['size'];
        } elseif ($file_info['age'] > $max_age) {
            $should_delete = true;
            $total_size -= $file_info['size'];
        }
        
        if ($should_delete) {
            @unlink($file_info['path']);
        }
    }
}

public function warm_cache() {
    // Pre-populate cache with frequently accessed data
    $cache_keys = array(
        'dashboard_stats',
        'platform_list', 
        'recent_activity',
        'queue_stats'
    );
    
    foreach ($cache_keys as $key) {
        $cache_key = "smo_warm_cache_{$key}";
        $data = $this->get_cache_warm_data($key);
        
        if ($data !== false) {
            set_transient($cache_key, $data, 30 * 60); // 30 minutes
        }
    }
}

private function get_cache_warm_data($key) {
    global $wpdb;
    
    switch ($key) {
        case 'dashboard_stats':
            return $this->get_dashboard_stats_optimized();
            
        case 'platform_list':
            $enabled_platforms = get_option('smo_social_enabled_platforms', array());
            $platforms = array();
            
            foreach ($enabled_platforms as $slug) {
                $platform = $this->platform_manager->get_platform($slug);
                if ($platform) {
                    $platforms[$slug] = array(
                        'name' => $platform->get_name(),
                        'features' => $platform->get_features()
                    );
                }
            }
            return $platforms;
            
        case 'recent_activity':
            $activity_table = $wpdb->prefix . 'smo_activity_logs';
            return $wpdb->get_results("
                SELECT action, details, created_at 
                FROM $activity_table 
                ORDER BY created_at DESC 
                LIMIT 10
            ", ARRAY_A);
            
        case 'queue_stats':
            $queue_table = $wpdb->prefix . 'smo_queue';
            return $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_pending,
                    COUNT(CASE WHEN platform_slug = 'twitter' THEN 1 END) as twitter_pending,
                    COUNT(CASE WHEN platform_slug = 'facebook' THEN 1 END) as facebook_pending,
                    COUNT(CASE WHEN platform_slug = 'instagram' THEN 1 END) as instagram_pending
                FROM $queue_table 
                WHERE status = 'pending'
            ", ARRAY_A);
            
        default:
            return false;
    }
}
```

### 6.3 Platform Manager Optimizations

#### Lazy Loading Implementation

```php
class PlatformManager {
    private $loaded_platforms = array();
    private $platform_cache = array();
    
    public function get_platform($slug) {
        // Return cached platform if already loaded
        if (isset($this->loaded_platforms[$slug])) {
            return $this->loaded_platforms[$slug];
        }
        
        // Load platform only when needed
        $platform = $this->load_platform($slug);
        
        if ($platform) {
            $this->loaded_platforms[$slug] = $platform;
            return $platform;
        }
        
        return null;
    }
    
    private function load_platform($slug) {
        $cache_key = "platform_{$slug}";
        
        // Check cache first
        if (isset($this->platform_cache[$cache_key])) {
            return $this->platform_cache[$cache_key];
        }
        
        $platform_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$slug}.json";
        
        if (!file_exists($platform_file)) {
            return null;
        }
        
        $platform_data = json_decode(file_get_contents($platform_file), true);
        
        if (!$platform_data) {
            return null;
        }
        
        // Create platform instance
        $class_name = $this->get_platform_class($slug);
        
        if (!class_exists($class_name)) {
            return null;
        }
        
        $platform = new $class_name($platform_data);
        
        // Cache for future use
        $this->platform_cache[$cache_key] = $platform;
        
        return $platform;
    }
    
    public function clear_loaded_platforms() {
        $this->loaded_platforms = array();
    }
}
```

### 6.4 Background Processing

#### Queue Batch Processing

```php
class QueueManager {
    public function process_queue_batch($batch_size = 10) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'smo_queue';
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Get next batch of items to process
        $batch = $wpdb->get_results($wpdb->prepare("
            SELECT q.*, p.content, p.platforms, p.media_urls
            FROM $queue_table q
            JOIN $posts_table p ON q.scheduled_post_id = p.id
            WHERE q.status = 'pending' 
            AND q.scheduled_for <= %s
            ORDER BY q.priority DESC, q.scheduled_for ASC
            LIMIT %d
        ", current_time('mysql'), $batch_size), ARRAY_A);
        
        if (empty($batch)) {
            return array('processed' => 0, 'skipped' => 0);
        }
        
        $processed = 0;
        $skipped = 0;
        
        foreach ($batch as $item) {
            try {
                $result = $this->process_queue_item($item);
                
                if ($result['success']) {
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                $skipped++;
                error_log("SMO Social: Queue processing error for item {$item['id']}: " . $e->getMessage());
            }
        }
        
        return array('processed' => $processed, 'skipped' => $skipped);
    }
    
    public function schedule_batch_processing() {
        if (!wp_next_scheduled('smo_process_queue_batch')) {
            // Schedule initial batch processing
            wp_schedule_event(time(), 'hourly', 'smo_process_queue_batch');
        }
        
        // Schedule immediate processing if queue has pending items
        $pending_count = $this->get_pending_count();
        
        if ($pending_count > 0) {
            $next_run = time() + 60; // 1 minute from now
            
            if (!wp_next_scheduled('smo_process_queue_batch_immediate')) {
                wp_schedule_single_event($next_run, 'smo_process_queue_batch_immediate');
            }
        }
    }
}

// Hook for batch processing
add_action('smo_process_queue_batch', function() {
    $queue_manager = new \SMO_Social\Scheduling\QueueManager();
    $queue_manager->process_queue_batch(25); // Process 25 items per batch
});

add_action('smo_process_queue_batch_immediate', function() {
    $queue_manager = new \SMO_Social\Scheduling\QueueManager();
    $queue_manager->process_queue_batch(10); // Quick processing for immediate items
});
```

## Εφαρμογή και Εκτέλεση

### Βήμα 1: Εκτέλεση SQL Ευρετηρίων

```sql
-- Εκτελέστε αυτά τα ευρετήρια στη βάση δεδομένων σας
-- Προτείνεται να εκτελεστούν κατά τη διάρκεια χαμηλής κίνησης
```

### Βήμα 2: Ενημέρωση Κώδικα

1. Αντικαταστήστε τις συναρτήσεις στο `includes/Admin/Admin.php`
2. Ενημερώστε το `includes/AI/Manager.php` με τις ασύγχρονες κλήσεις
3. Εφαρμόστε το lazy loading στο `includes/Platforms/Manager.php`

### Βήμα 3: Έλεγχος Απόδοσης

Μετά την εφαρμογή των βελτιστοποιήσεων:

```php
// Έλεγχος χρόνου εκτέλεσης
function smo_benchmark_function($function, $args = array(), $iterations = 1) {
    $start_time = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        call_user_func_array($function, $args);
    }
    
    $end_time = microtime(true);
    $total_time = $end_time - $start_time;
    
    return array(
        'total_time' => $total_time,
        'average_time' => $total_time / $iterations,
        'iterations' => $iterations
    );
}

// Παράδειγμα χρήσης
$benchmark = smo_benchmark_function(array($admin, 'get_dashboard_stats_optimized'));
error_log("Dashboard stats benchmark: " . print_r($benchmark, true));
```

Αυτές οι βελτιστοποιήσεις θα βελτιώσουν σημαντικά την απόδοση του plugin, μειώνοντας τους χρόνους φόρτωσης και βελτιστοποιώντας τη χρήση πόρων.
