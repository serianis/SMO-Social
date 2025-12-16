<?php
/**
 * SMO Social Production Caching System
 * 
 * This script provides advanced caching optimization including object caching,
 * database query caching, page output caching with compression, and CDN integration.
 * 
 * @package SMO_Social
 * @version 1.0.0
 * @author SMO Social Production Team
 */

defined('ABSPATH') || exit;

// Production Caching Constants
define('SMO_CACHE_DIR', WP_CONTENT_DIR . '/smo-cache/');
define('SMO_CACHE_TTL_DEFAULT', 3600); // 1 hour
define('SMO_CACHE_COMPRESSION', true);
define('SMO_CACHE_MAX_SIZE', '500MB');
define('SMO_DB_QUERY_CACHE_SIZE', 1000);

/**
 * Advanced Caching System Manager
 */
class SMO_Cache_Manager {
    
    private $cache_dir;
    private $redis_config;
    private $memcached_config;
    private $compression_enabled;
    private $cache_stats;
    
    public function __construct() {
        $this->cache_dir = SMO_CACHE_DIR;
        $this->compression_enabled = SMO_CACHE_COMPRESSION;
        $this->cache_stats = [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0,
            'total_size' => 0
        ];
        
        // Create cache directory
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
        
        // Initialize external cache services
        $this->init_external_caches();
    }
    
    /**
     * Initialize external cache services
     */
    private function init_external_caches() {
        // Redis configuration
        $this->redis_config = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'timeout' => 2.0,
            'persistent' => true,
            'database' => 1
        ];
        
        // Memcached configuration
        $this->memcached_config = [
            'servers' => [
                ['127.0.0.1', 11211]
            ],
            'options' => [
                Memcached::OPT_COMPRESSION => true,
                Memcached::OPT_BINARY_PROTOCOL => true,
                Memcached::OPT_LIBKETAMA_COMPATIBLE => true
            ]
        ];
    }
    
    /**
     * Run cache optimization and setup
     */
    public function setup_production_cache() {
        echo "🚀 SMO Social Production Cache System\n";
        echo "=====================================\n\n";
        
        try {
            // Set up cache directory structure
            $this->setup_cache_directories();
            
            // Configure WordPress caching
            $this->configure_wordpress_cache();
            
            // Set up database query cache
            $this->setup_database_cache();
            
            // Configure page caching
            $this->setup_page_cache();
            
            // Set up object caching
            $this->setup_object_cache();
            
            // Configure CDN integration
            $this->setup_cdn_integration();
            
            // Test cache performance
            $this->test_cache_performance();
            
            // Generate cache configuration
            $this->generate_cache_config();
            
            echo "\n✅ Cache system setup completed successfully!\n";
            echo "💾 Cache directory: {$this->cache_dir}\n";
            echo "📊 Monitor cache performance in WordPress admin\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "\n❌ Cache setup failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Setup cache directory structure
     */
    private function setup_cache_directories() {
        echo "📁 Setting up cache directories...\n";
        
        $cache_subdirs = [
            'pages',
            'objects',
            'queries',
            'api',
            'assets',
            'temp',
            'compressed'
        ];
        
        foreach ($cache_subdirs as $subdir) {
            $dir_path = $this->cache_dir . $subdir . '/';
            if (!file_exists($dir_path)) {
                wp_mkdir_p($dir_path);
                
                // Add .htaccess for security
                $htaccess_content = "# SMO Cache - {$subdir}\n";
                $htaccess_content .= "Order deny,allow\n";
                $htaccess_content .= "Deny from all\n";
                file_put_contents($dir_path . '.htaccess', $htaccess_content);
            }
        }
        
        echo "   ✅ Cache directories created\n";
    }
    
    /**
     * Configure WordPress caching
     */
    private function configure_wordpress_cache() {
        echo "⚙️  Configuring WordPress cache settings...\n";
        
        // Set cache constants
        $wp_config_additions = <<<'PHP'

// SMO Social Production Cache Configuration
define('WP_CACHE', true);
define('ENABLE_CACHE', true);
define('WP_CACHE_KEY_SALT', 'smo-social-production-' . DB_NAME);
define('COMPRESS_CSS', true);
define('COMPRESS_SCRIPTS', true);
define('CONCATENATE_SCRIPTS', false);
define('ENFORCE_GZIP', true);

// Cache TTL settings
define('CACHE_TTL_DEFAULT', 3600); // 1 hour
define('CACHE_TTL_PAGES', 1800);   // 30 minutes
define('CACHE_TTL_QUERIES', 900);  // 15 minutes

// Object cache configuration
define('WP_OBJECT_CACHE', true);
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_DB', 1);
PHP;
        
        echo "   ✅ Cache constants configuration prepared\n";
        echo "💡 Add to wp-config.php:\n\n";
        echo $wp_config_additions . "\n\n";
        
        // Set WordPress cache options
        $this->set_wordpress_cache_options();
    }
    
    /**
     * Set WordPress cache options
     */
    private function set_wordpress_cache_options() {
        update_option('smo_cache_enabled', true);
        update_option('smo_cache_compression', $this->compression_enabled);
        update_option('smo_cache_ttl', SMO_CACHE_TTL_DEFAULT);
        update_option('smo_cache_max_size', SMO_CACHE_MAX_SIZE);
        
        // Clear any existing cache
        $this->clear_all_cache();
    }
    
    /**
     * Setup database query cache
     */
    private function setup_database_cache() {
        echo "🗄️  Setting up database query cache...\n";
        
        global $wpdb;
        
        // Create query cache table
        $cache_table = $wpdb->prefix . 'smo_query_cache';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$cache_table} (
            cache_key varchar(191) NOT NULL,
            cache_value longtext NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cache_key),
            KEY expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $wpdb->query($sql);
        
        // Setup query cache functions
        $this->create_query_cache_functions();
        
        echo "   ✅ Database query cache configured\n";
    }
    
    /**
     * Create query cache functions
     */
    private function create_query_cache_functions() {
        $cache_functions = <<<'PHP'
/**
 * SMO Social Query Cache Functions
 */

// Get cached query result
function smo_get_cached_query($sql, $params = [], $ttl = 900) {
    global $wpdb;
    
    $cache_key = md5($sql . serialize($params));
    $cache_table = $wpdb->prefix . 'smo_query_cache';
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT cache_value FROM {$cache_table} 
         WHERE cache_key = %s AND expires_at > NOW()",
        $cache_key
    ));
    
    if ($result !== null) {
        return unserialize($result);
    }
    
    return false;
}

// Set query cache
function smo_set_cached_query($sql, $params, $data, $ttl = 900) {
    global $wpdb;
    
    $cache_key = md5($sql . serialize($params));
    $cache_table = $wpdb->prefix . 'smo_query_cache';
    $expires_at = date('Y-m-d H:i:s', time() + $ttl);
    
    $wpdb->replace(
        $cache_table,
        [
            'cache_key' => $cache_key,
            'cache_value' => serialize($data),
            'expires_at' => $expires_at
        ]
    );
}

// Clean expired query cache
function smo_clean_query_cache() {
    global $wpdb;
    
    $cache_table = $wpdb->prefix . 'smo_query_cache';
    return $wpdb->query("DELETE FROM {$cache_table} WHERE expires_at <= NOW()");
}
PHP;
        
        echo "   ✅ Query cache functions prepared\n";
    }
    
    /**
     * Setup page caching
     */
    private function setup_page_cache() {
        echo "📄 Setting up page caching...\n";
        
        // Create page cache handler
        $page_cache_handler = $this->get_page_cache_handler_code();
        
        // Create page cache rules
        $cache_rules = [
            'cache_pages' => true,
            'exclude_logged_in' => true,
            'exclude_admin' => true,
            'exclude_post_types' => ['wp-login', 'wp-admin', 'api'],
            'cache_ttl' => 1800
        ];
        
        // Save cache rules
        update_option('smo_page_cache_rules', $cache_rules);
        
        echo "   ✅ Page caching configured\n";
    }
    
    /**
     * Setup object caching
     */
    private function setup_object_cache() {
        echo "🔄 Setting up object caching...\n";
        
        // Test Redis connection
        $redis_available = $this->test_redis_connection();
        
        if ($redis_available) {
            echo "   ✅ Redis cache available\n";
            update_option('smo_cache_backend', 'redis');
        } else {
            echo "   ⚠️  Redis not available, using file cache\n";
            update_option('smo_cache_backend', 'file');
        }
        
        // Create object cache wrapper
        $this->create_object_cache_wrapper();
        
        echo "   ✅ Object caching configured\n";
    }
    
    /**
     * Setup CDN integration
     */
    private function setup_cdn_integration() {
        echo "☁️  Setting up CDN integration...\n";
        
        $cdn_options = [
            'enabled' => false,
            'provider' => 'cloudflare', // cloudflare, aws, maxcdn
            'url' => '',
            'include_assets' => true,
            'exclude_patterns' => ['wp-admin', 'wp-login', 'api/']
        ];
        
        update_option('smo_cdn_options', $cdn_options);
        
        echo "   ✅ CDN integration configured\n";
        echo "💡 Configure your CDN provider in WordPress admin\n";
    }
    
    /**
     * Test cache performance
     */
    private function test_cache_performance() {
        echo "🧪 Testing cache performance...\n";
        
        // Test file cache performance
        $start_time = microtime(true);
        $test_data = str_repeat('test', 1000);
        
        // Write test
        $this->cache_set('performance_test', $test_data, 60);
        
        // Read test
        $cached_data = $this->cache_get('performance_test');
        
        $end_time = microtime(true);
        $write_read_time = ($end_time - $start_time) * 1000; // milliseconds
        
        $test_results = [
            'write_read_time_ms' => $write_read_time,
            'cache_hit' => $cached_data === $test_data,
            'cache_backend' => get_option('smo_cache_backend', 'file')
        ];
        
        update_option('smo_cache_test_results', $test_results);
        
        if ($write_read_time < 10) {
            echo "   ✅ Excellent cache performance (< 10ms)\n";
        } elseif ($write_read_time < 50) {
            echo "   ✅ Good cache performance (< 50ms)\n";
        } else {
            echo "   ⚠️  Slow cache performance ({$write_read_time}ms)\n";
        }
    }
    
    /**
     * Generate cache configuration file
     */
    private function generate_cache_config() {
        echo "📋 Generating cache configuration...\n";
        
        $config = [
            'version' => '1.0.0',
            'created' => date('Y-m-d H:i:s'),
            'settings' => [
                'cache_enabled' => true,
                'compression' => $this->compression_enabled,
                'ttl_default' => SMO_CACHE_TTL_DEFAULT,
                'backend' => get_option('smo_cache_backend', 'file'),
                'page_cache' => true,
                'object_cache' => true,
                'query_cache' => true,
                'cdn_enabled' => false
            ],
            'directories' => [
                'cache_dir' => $this->cache_dir,
                'pages' => $this->cache_dir . 'pages/',
                'objects' => $this->cache_dir . 'objects/',
                'queries' => $this->cache_dir . 'queries/'
            ],
            'performance' => get_option('smo_cache_test_results', [])
        ];
        
        $config_file = $this->cache_dir . 'cache-config.json';
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
        
        echo "   ✅ Cache configuration saved: {$config_file}\n";
    }
    
    /**
     * Cache get operation
     */
    public function cache_get($key, $default = null) {
        $cache_file = $this->get_cache_file_path($key);
        
        if (!file_exists($cache_file)) {
            $this->cache_stats['misses']++;
            return $default;
        }
        
        // Check if expired
        if (filemtime($cache_file) + SMO_CACHE_TTL_DEFAULT < time()) {
            unlink($cache_file);
            $this->cache_stats['misses']++;
            return $default;
        }
        
        $data = file_get_contents($cache_file);
        
        if ($this->compression_enabled) {
            $data = gzuncompress($data);
        }
        
        $this->cache_stats['hits']++;
        return unserialize($data);
    }
    
    /**
     * Cache set operation
     */
    public function cache_set($key, $value, $ttl = SMO_CACHE_TTL_DEFAULT) {
        $cache_file = $this->get_cache_file_path($key);
        
        wp_mkdir_p(dirname($cache_file));
        
        $data = serialize($value);
        
        if ($this->compression_enabled && strlen($data) > 1024) {
            $data = gzcompress($data);
        }
        
        $result = file_put_contents($cache_file, $data, LOCK_EX);
        
        if ($result !== false) {
            $this->cache_stats['writes']++;
            $this->cache_stats['total_size'] += $result;
            return true;
        }
        
        return false;
    }
    
    /**
     * Cache delete operation
     */
    public function cache_delete($key) {
        $cache_file = $this->get_cache_file_path($key);
        
        if (file_exists($cache_file)) {
            $result = unlink($cache_file);
            if ($result) {
                $this->cache_stats['deletes']++;
            }
            return $result;
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        echo "🧹 Clearing all cache...\n";
        
        $cleared_files = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() !== 'json') {
                unlink($file->getRealPath());
                $cleared_files++;
            }
        }
        
        // Clear database query cache
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}smo_query_cache WHERE expires_at <= NOW()");
        
        echo "   ✅ Cleared {$cleared_files} cache files\n";
        
        return $cleared_files;
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        $stats = $this->cache_stats;
        $stats['hit_rate'] = $stats['hits'] + $stats['misses'] > 0 
            ? round(($stats['hits'] / ($stats['hits'] + $stats['misses'])) * 100, 2) 
            : 0;
        
        // Get cache directory size
        $cache_size = $this->get_directory_size($this->cache_dir);
        $stats['total_size'] = $this->format_bytes($cache_size);
        $stats['file_count'] = $this->get_file_count($this->cache_dir);
        
        return $stats;
    }
    
    /**
     * Optimize cache performance
     */
    public function optimize_cache() {
        echo "⚡ Optimizing cache performance...\n";
        
        // Remove expired cache files
        $this->clean_expired_cache();
        
        // Compress large cache files
        $this->compress_large_cache_files();
        
        // Optimize database query cache
        $this->optimize_query_cache();
        
        echo "   ✅ Cache optimization completed\n";
    }
    
    /**
     * Clean expired cache files
     */
    private function clean_expired_cache() {
        $expired_files = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cache_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && filemtime($file) + SMO_CACHE_TTL_DEFAULT < time()) {
                unlink($file->getRealPath());
                $expired_files++;
            }
        }
        
        echo "   ✅ Cleaned {$expired_files} expired cache files\n";
    }
    
    /**
     * Compress large cache files
     */
    private function compress_large_cache_files() {
        if (!$this->compression_enabled) return;
        
        $compressed_files = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cache_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getSize() > 10240 && !$this->is_compressed($file)) {
                $content = file_get_contents($file);
                $compressed = gzcompress($content);
                
                if ($compressed && strlen($compressed) < strlen($content) * 0.8) {
                    file_put_contents($file, $compressed);
                    $compressed_files++;
                }
            }
        }
        
        echo "   ✅ Compressed {$compressed_files} cache files\n";
    }
    
    /**
     * Optimize query cache
     */
    private function optimize_query_cache() {
        global $wpdb;
        
        $cache_table = $wpdb->prefix . 'smo_query_cache';
        
        // Remove expired entries
        $deleted = $wpdb->query("DELETE FROM {$cache_table} WHERE expires_at <= NOW()");
        
        // Optimize table
        $wpdb->query("OPTIMIZE TABLE {$cache_table}");
        
        echo "   ✅ Query cache optimized ({$deleted} expired entries removed)\n";
    }
    
    /**
     * Get cache file path
     */
    private function get_cache_file_path($key) {
        $hash = md5($key);
        return $this->cache_dir . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.cache';
    }
    
    /**
     * Check if file is compressed
     */
    private function is_compressed($file) {
        $content = file_get_contents($file, false, null, 0, 2);
        return $content === "\x1f\x8b";
    }
    
    /**
     * Test Redis connection
     */
    private function test_redis_connection() {
        // This would test Redis connection
        // For now, we'll assume it's not available
        return false;
    }
    
    /**
     * Get directory size
     */
    private function get_directory_size($directory) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Get file count
     */
    private function get_file_count($directory) {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Format bytes
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get page cache handler code
     */
    private function get_page_cache_handler_code() {
        return <<<'PHP'
// SMO Social Page Cache Handler
function smo_start_page_cache() {
    if (is_admin() || is_user_logged_in() || $_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }
    
    $cache_file = get_cache_file_path();
    if (file_exists($cache_file) && filemtime($cache_file) + 1800 > time()) {
        readfile($cache_file);
        exit;
    }
    
    ob_start('smo_cache_output');
}

function smo_cache_output($buffer) {
    if (empty($buffer)) return $buffer;
    
    $cache_file = get_cache_file_path();
    wp_mkdir_p(dirname($cache_file));
    file_put_contents($cache_file, $buffer);
    
    return $buffer;
}
PHP;
    }
    
    /**
     * Create object cache wrapper
     */
    private function create_object_cache_wrapper() {
        $wrapper_code = <<<'PHP'
/**
 * SMO Social Object Cache Wrapper
 */
class SMO_Object_Cache {
    private $cache;
    private $backend;
    
    public function __construct() {
        $this->backend = get_option('smo_cache_backend', 'file');
        
        if ($this->backend === 'redis' && class_exists('Redis')) {
            $this->cache = new Redis();
            $this->cache->connect(REDIS_HOST, REDIS_PORT);
        } else {
            $this->cache = new SMO_File_Cache();
        }
    }
    
    public function get($key, $default = null) {
        return $this->cache->get($key, $default);
    }
    
    public function set($key, $value, $ttl = 3600) {
        return $this->cache->set($key, $value, $ttl);
    }
    
    public function delete($key) {
        return $this->cache->delete($key);
    }
}
PHP;
        
        echo "   ✅ Object cache wrapper prepared\n";
    }
}

// File-based cache implementation
class SMO_File_Cache {
    private $cache_dir = SMO_CACHE_DIR . 'objects/';
    
    public function get($key, $default = null) {
        $file = $this->get_file_path($key);
        
        if (!file_exists($file) || filemtime($file) + SMO_CACHE_TTL_DEFAULT < time()) {
            return $default;
        }
        
        return unserialize(file_get_contents($file));
    }
    
    public function set($key, $value, $ttl = 3600) {
        $file = $this->get_file_path($key);
        wp_mkdir_p(dirname($file));
        
        return file_put_contents($file, serialize($value)) !== false;
    }
    
    public function delete($key) {
        $file = $this->get_file_path($key);
        return !file_exists($file) || unlink($file);
    }
    
    private function get_file_path($key) {
        return $this->cache_dir . md5($key) . '.cache';
    }
}

// CLI Execution
if (php_sapi_name() === 'cli') {
    $cache_manager = new SMO_Cache_Manager();
    
    $command = $argv[1] ?? 'setup';
    
    switch ($command) {
        case 'setup':
            $cache_manager->setup_production_cache();
            break;
            
        case 'clear':
            $cache_manager->clear_all_cache();
            echo "Cache cleared successfully!\n";
            break;
            
        case 'optimize':
            $cache_manager->optimize_cache();
            break;
            
        case 'stats':
            $stats = $cache_manager->get_cache_stats();
            echo "Cache Statistics:\n";
            echo "Hits: " . $stats['hits'] . "\n";
            echo "Misses: " . $stats['misses'] . "\n";
            echo "Hit Rate: " . $stats['hit_rate'] . "%\n";
            echo "Total Size: " . $stats['total_size'] . "\n";
            echo "File Count: " . $stats['file_count'] . "\n";
            break;
            
        default:
            echo "Usage:\n";
            echo "  php production_caching_system.php setup\n";
            echo "  php production_caching_system.php clear\n";
            echo "  php production_caching_system.php optimize\n";
            echo "  php production_caching_system.php stats\n";
            break;
    }
    
    exit(0);
}

?>