<?php
namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * CacheMetadataStream - Memory-efficient streaming processor for cache metadata
 *
 * Implements generators and iterators for streaming cache file processing to reduce memory usage
 * with large cache datasets and metadata operations.
 */
class CacheMetadataStream {
    private $batch_size;
    private $max_memory_usage;
    private $current_memory_usage;
    private $cache_dir;
    private $logger;

    /**
     * Constructor
     */
    public function __construct($batch_size = 1000, $max_memory_usage = 30) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage; // MB
        $this->current_memory_usage = 0;
        $this->logger = new CacheStreamLogger();

        // Initialize cache directory
        $this->initialize_cache_directory();
    }

    /**
     * Initialize cache directory
     */
    private function initialize_cache_directory() {
        // Handle both WordPress and non-WordPress contexts
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $this->cache_dir = $upload_dir['basedir'] . '/smo-social/cache/';
        } else {
            // Non-WordPress context - use current directory
            $this->cache_dir = dirname(__FILE__) . '/../../cache/smo-social/cache/';
        }

        // Ensure cache directory exists
        if (!file_exists($this->cache_dir)) {
            $this->create_directory($this->cache_dir);
        }
    }

    /**
     * Create directory recursively
     */
    private function create_directory($dir) {
        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }
        return true;
    }

    /**
     * Set batch processing configuration
     */
    public function set_batch_config($batch_size, $max_memory_usage) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage;
    }

    /**
     * Get current batch processing configuration
     */
    public function get_batch_config() {
        return array(
            'batch_size' => $this->batch_size,
            'max_memory_usage' => $this->max_memory_usage,
            'current_memory_usage' => $this->current_memory_usage
        );
    }

    /**
     * Check memory usage and clean up if needed
     */
    public function check_memory_usage() {
        $this->current_memory_usage = memory_get_usage(true) / (1024 * 1024);

        if ($this->current_memory_usage > $this->max_memory_usage) {
            $this->logger->log("Memory usage warning: {$this->current_memory_usage}MB exceeds limit of {$this->max_memory_usage}MB");
            $this->cleanup_resources();
        }
    }

    /**
     * Cleanup resources to free memory
     */
    private function cleanup_resources() {
        gc_collect_cycles();
        $this->logger->log("Garbage collection completed. Current memory usage: " . memory_get_usage(true) / (1024 * 1024) . "MB");
    }

    /**
     * Stream cache files in batches
     */
    public function stream_cache_files($namespace = null) {
        $this->logger->log("Starting cache file stream for namespace: " . ($namespace ?? 'all'));

        $files = array();
        $processed_count = 0;

        // Find all cache files
        $all_files = glob($this->cache_dir . '**/*');

        foreach ($all_files as $file) {
            if (is_file($file)) {
                $files[] = $file;

                // Process in batches
                if (count($files) >= $this->batch_size) {
                    yield $this->process_cache_file_batch($files, $namespace);
                    $files = array();
                    $this->check_memory_usage();
                }
            }
        }

        // Process remaining files
        if (!empty($files)) {
            yield $this->process_cache_file_batch($files, $namespace);
        }

        $this->logger->log("Completed cache file stream. Processed {$processed_count} files in total.");
    }

    /**
     * Process a batch of cache files
     */
    private function process_cache_file_batch($files, $namespace) {
        $batch_results = array();

        foreach ($files as $file) {
            $cache_data = $this->read_cache_file($file);

            if ($cache_data !== false) {
                // Filter by namespace if specified
                if ($namespace === null || ($cache_data['namespace'] ?? 'default') === $namespace) {
                    $batch_results[] = array(
                        'file_path' => $file,
                        'cache_key' => $cache_data['key'] ?? basename($file, '.cache'),
                        'namespace' => $cache_data['namespace'] ?? 'default',
                        'size' => filesize($file),
                        'created' => $cache_data['created'] ?? filemtime($file),
                        'expires' => $cache_data['expires'] ?? 0,
                        'is_valid' => isset($cache_data['expires']) && $cache_data['expires'] >= time(),
                        'data_size' => strlen($cache_data['data'] ?? '')
                    );
                }
            }
        }

        return $batch_results;
    }

    /**
     * Stream cache metadata with memory-efficient structures
     */
    public function stream_cache_metadata($namespace = null) {
        $metadata_stream = $this->stream_cache_files($namespace);

        $metadata_summary = array(
            'total_files' => 0,
            'total_size' => 0,
            'valid_files' => 0,
            'expired_files' => 0,
            'namespaces' => array(),
            'size_by_namespace' => array()
        );

        foreach ($metadata_stream as $batch) {
            $this->check_memory_usage();

            foreach ($batch as $cache_file) {
                $metadata_summary['total_files']++;
                $metadata_summary['total_size'] += $cache_file['size'];

                if ($cache_file['is_valid']) {
                    $metadata_summary['valid_files']++;
                } else {
                    $metadata_summary['expired_files']++;
                }

                // Track namespace statistics
                $namespace = $cache_file['namespace'];
                if (!isset($metadata_summary['namespaces'][$namespace])) {
                    $metadata_summary['namespaces'][$namespace] = 0;
                    $metadata_summary['size_by_namespace'][$namespace] = 0;
                }

                $metadata_summary['namespaces'][$namespace]++;
                $metadata_summary['size_by_namespace'][$namespace] += $cache_file['size'];
            }
        }

        return $metadata_summary;
    }

    /**
     * Process cache files in chunks for cleanup operations
     */
    public function process_cache_files_in_chunks($operation, $namespace = null, $chunk_size = 500) {
        $this->logger->log("Starting chunked cache processing for operation: {$operation}");

        $total_processed = 0;
        $total_errors = 0;

        $files = glob($this->cache_dir . '**/*');
        $cache_files = array();

        // Filter cache files
        foreach ($files as $file) {
            if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
                $cache_files[] = $file;
            }
        }

        // Process in chunks
        for ($i = 0; $i < count($cache_files); $i += $chunk_size) {
            $this->check_memory_usage();

            $chunk = array_slice($cache_files, $i, $chunk_size);
            $results = array();

            foreach ($chunk as $file) {
                try {
                    $result = false;

                    switch ($operation) {
                        case 'cleanup_expired':
                            $result = $this->cleanup_expired_cache_file($file);
                            break;
                        case 'validate':
                            $result = $this->validate_cache_file($file);
                            break;
                        case 'optimize':
                            $result = $this->optimize_cache_file($file);
                            break;
                        case 'delete':
                            $result = $this->delete_cache_file($file);
                            break;
                    }

                    if ($result) {
                        $total_processed++;
                    } else {
                        $total_errors++;
                    }
                } catch (\Exception $e) {
                    $this->logger->log("Error processing cache file {$file}: " . $e->getMessage());
                    $total_errors++;
                }
            }

            // Force cleanup after each chunk
            $this->cleanup_resources();
        }

        $this->logger->log("Completed chunked cache processing. Operation: {$operation}, Processed: {$total_processed}, Errors: {$total_errors}");

        return array(
            'operation' => $operation,
            'processed' => $total_processed,
            'errors' => $total_errors,
            'total' => $total_processed + $total_errors
        );
    }

    /**
     * Cleanup expired cache file
     */
    private function cleanup_expired_cache_file($file_path) {
        $cache_data = $this->read_cache_file($file_path);

        if ($cache_data !== false && isset($cache_data['expires']) && $cache_data['expires'] < time()) {
            // Cache is expired, delete it
            return unlink($file_path);
        }

        return false;
    }

    /**
     * Validate cache file
     */
    private function validate_cache_file($file_path) {
        $cache_data = $this->read_cache_file($file_path);
        return $cache_data !== false;
    }

    /**
     * Optimize cache file (placeholder for future optimization)
     */
    private function optimize_cache_file($file_path) {
        // Future optimization: compress, reorganize, etc.
        return true;
    }

    /**
     * Delete cache file
     */
    private function delete_cache_file($file_path) {
        return unlink($file_path);
    }

    /**
     * Read and unserialize cache file
     */
    public function read_cache_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $content = file_get_contents($file_path);

        if ($content === false) {
            return false;
        }

        $cache_data = unserialize($content);

        if ($cache_data === false || !isset($cache_data['data'], $cache_data['expires'])) {
            // Invalid cache file, delete it
            unlink($file_path);
            return false;
        }

        return $cache_data;
    }

    /**
     * Get memory-efficient cache statistics
     */
    public function get_cache_statistics() {
        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'expired_files' => 0,
            'valid_files' => 0,
            'hit_rate' => 0,
            'miss_rate' => 0,
            'memory_usage' => memory_get_usage(true) / (1024 * 1024)
        );

        $files = glob($this->cache_dir . '**/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $stats['total_files']++;
                $stats['total_size'] += filesize($file);

                $cache_data = $this->read_cache_file($file);
                if ($cache_data === false) {
                    $stats['expired_files']++;
                } else {
                    $stats['valid_files']++;
                }
            }
        }

        // Calculate hit/miss rates (would be populated from actual usage)
        $total_requests = $stats['valid_files'] + $stats['expired_files'];
        if ($total_requests > 0) {
            $stats['hit_rate'] = $stats['valid_files'] / $total_requests;
            $stats['miss_rate'] = $stats['expired_files'] / $total_requests;
        }

        return $stats;
    }

    /**
     * Get cache size in bytes with memory efficiency
     */
    public function get_cache_size() {
        $size = 0;
        $files = glob($this->cache_dir . '**/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);

                // Check memory usage periodically
                if ($size > 10000000) { // 10MB
                    $this->check_memory_usage();
                }
            }
        }

        return $size;
    }
}

/**
 * Simple logger for cache streaming
 */
class CacheStreamLogger {
    public function log($message) {
        if (function_exists('error_log')) {
            error_log('SMO Cache Stream: ' . $message);
        }
    }
}