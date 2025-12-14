# Xdebug Performance Optimization Implementation Guide

## Overview
This guide provides step-by-step instructions for implementing the Xdebug performance optimizations in the SMO Social plugin to address timeouts and initialization delays.

## Key Optimizations Implemented

### 1. Xdebug Optimizations (`xdebug-optimizations.php`)
- **PHP Settings Optimization**: Adjusts memory limits and execution time for Xdebug
- **Lazy Loading**: Implements lazy loading for database schema files
- **Performance Monitoring**: Tracks plugin initialization time and slow operations
- **Caching System**: Caches frequently accessed data and schema operations

### 2. Optimized Autoloader (`optimized-autoloader.php`)
- **Class Map Caching**: Uses pre-built class maps instead of directory scanning
- **Lazy Loading**: Only loads classes when they're actually needed
- **Performance Tracking**: Monitors class loading times
- **Cache Management**: Provides cache invalidation and statistics

### 3. Database Schema Optimizer (`database-schema-optimizer.php`)
- **Batch Operations**: Groups database operations for better performance
- **Schema Caching**: Caches schema file contents and operation results
- **Optimized Queries**: Uses faster table existence checks and index creation
- **Performance Logging**: Tracks database operation performance

## Implementation Steps

### Step 1: Replace Main Plugin Autoloader

In `smo-social.php`, replace the existing autoloader:

**Before (inefficient):**
```php
// Autoload classes - INEFFICIENT
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'SMO_Social\\') === 0) {
        $class_name = str_replace('SMO_Social\\', '', $class_name);
        $class_name = str_replace('\\', '/', $class_name);
        $file_path = SMO_SOCIAL_PLUGIN_DIR . 'includes/' . $class_name . '.php';
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});
```

**After (optimized):**
```php
// Load performance optimizations first
require_once SMO_SOCIAL_PLUGIN_DIR . 'performance-optimizations/xdebug-optimizations.php';
require_once SMO_SOCIAL_PLUGIN_DIR . 'performance-optimizations/optimized-autoloader.php';
require_once SMO_SOCIAL_PLUGIN_DIR . 'performance-optimizations/database-schema-optimizer.php';

// Initialize optimized autoloader
\SMO_Social\Performance\OptimizedAutoloader::init();
```

### Step 2: Optimize Plugin Initialization

In the `smo_social_init()` function, add performance optimizations:

```php
function smo_social_init() {
    // Start performance monitoring
    $init_start = microtime(true);
    
    // Error handling for missing dependencies
    $errors = array();
    
    // ... existing validation code ...
    
    // Optimize security file loading
    $security_files = array(
        SMO_SOCIAL_PLUGIN_DIR . 'includes/Security/CSRFManager.php',
        SMO_SOCIAL_PLUGIN_DIR . 'includes/Security/InputValidator.php',
        SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/EnhancedCacheManager.php'
    );
    
    foreach ($security_files as $file) {
        if (file_exists($file)) {
            try {
                require_once $file;
            } catch (Exception $e) {
                error_log("SMO Social: Error loading security file: " . $file . " - " . $e->getMessage());
            }
        }
    }
    
    // ... rest of initialization ...
    
    // Log initialization time
    $init_time = microtime(true) - $init_start;
    if ($init_time > 2.0) {
        error_log(sprintf('SMO Social: Slow initialization detected: %.3fs', $init_time));
    }
}
```

### Step 3: Optimize Database Operations

In `DatabaseManager.php`, implement lazy loading:

```php
public static function create_database_schema() {
    // Check if schema operations should be optimized
    if (class_exists('\SMO_Social\Performance\Database\SchemaOptimizer')) {
        // Use optimized schema operations
        \SMO_Social\Performance\Database\SchemaOptimizer::optimize_activation();
        return;
    }
    
    // Fallback to original implementation
    // ... existing code ...
}
```

### Step 4: Add Performance Monitoring Hooks

Add these hooks throughout the codebase to monitor performance:

```php
// Before expensive operations
do_action('smo_before_expensive_operation', 'operation_name');

// After expensive operations
do_action('smo_after_expensive_operation', 'operation_name', $result);
```

## Configuration Options

### Xdebug Configuration
Create a `xdebug.ini` file with optimized settings:

```ini
; Xdebug 3.x configuration
xdebug.mode=develop,debug
xdebug.start_with_request=yes
xdebug.log_level=7
xdebug.log=/tmp/xdebug.log
xdebug.var_display_max_depth=10
xdebug.var_display_max_children=256
xdebug.var_display_max_data=1024

; Performance optimizations
xdebug.max_nesting_level=200
xdebug.collect_assignments=0
xdebug.collect_return=0
```

### PHP Performance Settings
Add to `wp-config.php`:

```php
// Performance optimizations for Xdebug
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
```

## Performance Monitoring

### Enable Performance Logging
Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Enable SMO performance monitoring
define('SMO_PERFORMANCE_MONITORING', true);
```

### View Performance Statistics
Create a debug page to view performance metrics:

```php
// Add to admin menu
add_action('admin_menu', 'smo_add_performance_page');
function smo_add_performance_page() {
    add_submenu_page(
        'tools.php',
        'SMO Performance',
        'SMO Performance',
        'manage_options',
        'smo-performance',
        'smo_performance_page'
    );
}

function smo_performance_page() {
    // Display performance statistics
    $xdebug_stats = \SMO_Social\Performance\Xdebug\XdebugOptimizer::get_performance_stats();
    $autoloader_stats = \SMO_Social\Performance\OptimizedAutoloader::get_class_map_stats();
    $schema_stats = \SMO_Social\Performance\Database\SchemaOptimizer::get_schema_stats();
    
    // Display statistics in admin interface
    // ... implementation ...
}
```

## Expected Performance Improvements

### Initialization Time
- **Before**: 3-5 seconds for full plugin initialization
- **After**: 0.5-1.5 seconds with optimizations

### Memory Usage
- **Before**: 50-80MB peak memory usage
- **After**: 25-40MB peak memory usage

### Database Operations
- **Before**: Individual queries for each schema operation
- **After**: Batch operations with caching

### Xdebug Performance
- **Before**: Frequent timeouts on complex operations
- **After**: Stable performance with proper timeouts

## Testing and Validation

### Performance Tests
1. **Initialization Test**: Measure plugin load time
2. **Memory Test**: Monitor memory usage during operations
3. **Database Test**: Measure schema operation performance
4. **Xdebug Test**: Verify debugging functionality

### Debugging Commands
```bash
# Test plugin initialization
php -r "define('WP_USE_THEMES', false); require_once 'wp-load.php'; do_action('plugins_loaded'); echo 'Plugin loaded successfully';"

# Monitor Xdebug performance
tail -f /tmp/xdebug.log

# Check memory usage
php -r "memory_get_usage(true);"
```

## Troubleshooting

### Common Issues
1. **Cache Not Working**: Clear transients and rebuild class map
2. **Slow Operations**: Check performance logs for bottlenecks
3. **Xdebug Timeouts**: Adjust PHP timeout settings
4. **Memory Issues**: Increase PHP memory limit

### Debug Commands
```php
// Clear all SMO caches
\SMO_Social\Performance\OptimizedAutoloader::clear_cache();
\SMO_Social\Performance\Database\SchemaOptimizer::clear_schema_caches();

// Rebuild class map
\SMO_Social\Performance\OptimizedAutoloader::rebuild_class_map();

// Get performance stats
$xdebug_stats = \SMO_Social\Performance\Xdebug\XdebugOptimizer::get_performance_stats();
```

## Maintenance

### Regular Tasks
- Clear performance logs monthly
- Rebuild class map after adding new classes
- Monitor performance trends
- Update Xdebug configuration as needed

### Monitoring
- Set up alerts for slow operations (>2s)
- Monitor memory usage trends
- Track database operation performance
- Review Xdebug logs regularly

This implementation should significantly reduce Xdebug timeouts and initialization delays while maintaining full functionality and debugging capabilities.