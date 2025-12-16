<?php
namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * ContentImportStream - Memory-efficient streaming processor for content imports
 *
 * Implements generators and iterators for streaming content processing to reduce memory usage
 * with large content imports from various sources.
 */
class ContentImportStream {
    private $batch_size;
    private $max_memory_usage;
    private $current_memory_usage;
    private $wpdb;
    private $table_names;
    private $logger;

    /**
     * Constructor
     */
    public function __construct($batch_size = 500, $max_memory_usage = 40) {
        $this->batch_size = $batch_size;
        $this->max_memory_usage = $max_memory_usage; // MB
        $this->current_memory_usage = 0;
        $this->logger = new ContentStreamLogger();

        // Initialize database connection
        global $wpdb;
        $this->wpdb = $wpdb;

        // Set default table names with fallback for testing
        $prefix = 'wp_'; // Default prefix
        if (isset($wpdb->prefix)) {
            $prefix = $wpdb->prefix;
        }

        $this->table_names = array(
            'content_sources' => $prefix . 'smo_content_sources',
            'imported_content' => $prefix . 'smo_imported_content',
            'content_ideas' => $prefix . 'smo_content_ideas'
        );
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
     * Stream content from database in batches
     */
    public function stream_content_from_database($source_id, $status = 'pending') {
        $this->logger->log("Starting content stream from database for source_id: {$source_id}");

        $offset = 0;
        $has_more_data = true;

        while ($has_more_data) {
            $this->check_memory_usage();

            // Get content in batches
            $results = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->table_names['imported_content']}
                 WHERE source_id = %d AND status = %s
                 ORDER BY imported_at DESC
                 LIMIT %d OFFSET %d",
                $source_id, $status, $this->batch_size, $offset
            ));

            if (empty($results)) {
                $has_more_data = false;
                break;
            }

            // Yield the batch of content using generator pattern
            yield $results;

            $offset += $this->batch_size;
            $this->current_memory_usage = memory_get_usage(true) / (1024 * 1024);
        }

        $this->logger->log("Completed content stream from database. Processed {$offset} records in total.");
    }

    /**
     * Process content stream with memory-efficient structures
     */
    public function process_content_stream($content_stream, $processing_callback) {
        $processed_count = 0;
        $error_count = 0;

        foreach ($content_stream as $batch) {
            $this->check_memory_usage();

            foreach ($batch as $content_item) {
                try {
                    // Process each content item with the provided callback
                    $result = $processing_callback($content_item);

                    if ($result) {
                        $processed_count++;
                    } else {
                        $error_count++;
                    }
                } catch (\Exception $e) {
                    $this->logger->log("Error processing content item {$content_item->id}: " . $e->getMessage());
                    $error_count++;
                }
            }
        }

        return array(
            'processed_count' => $processed_count,
            'error_count' => $error_count,
            'total_processed' => $processed_count + $error_count
        );
    }

    /**
     * Stream content from external sources with chunked processing
     */
    public function stream_external_content($source_config, $content_processor) {
        $this->logger->log("Starting external content stream for source type: {$source_config['type']}");

        $content_batch = array();
        $processed_count = 0;

        switch ($source_config['type']) {
            case 'rss':
                $processed_count = $this->stream_rss_content($source_config, $content_processor);
                break;
            case 'google_drive':
                $processed_count = $this->stream_google_drive_content($source_config, $content_processor);
                break;
            case 'dropbox':
                $processed_count = $this->stream_dropbox_content($source_config, $content_processor);
                break;
            case 'canva':
                $processed_count = $this->stream_canva_content($source_config, $content_processor);
                break;
            default:
                $this->logger->log("Unsupported source type: {$source_config['type']}");
                return 0;
        }

        $this->logger->log("Completed external content stream. Processed {$processed_count} items.");
        return $processed_count;
    }

    /**
     * Stream RSS content with chunked processing
     */
    private function stream_rss_content($config, $content_processor) {
        if (empty($config['url'])) {
            return 0;
        }

        $imported_count = 0;
        $rss_content = wp_remote_get($config['url']);

        if (is_wp_error($rss_content)) {
            $this->logger->log('RSS feed error - ' . $rss_content->get_error_message());
            return 0;
        }

        $body = wp_remote_retrieve_body($rss_content);

        // Basic RSS parsing (simplified)
        preg_match_all('/<item[^>]*>(.*?)<\/item>/s', $body, $items);

        foreach ($items[1] as $item) {
            $this->check_memory_usage();

            // Extract title
            preg_match('/<title[^>]*><!\[CDATA\[(.*?)\]\]><\/title>|<title[^>]*>(.*?)<\/title>/s', $item, $title_match);
            $title = !empty($title_match[1]) ? $title_match[1] : (!empty($title_match[2]) ? $title_match[2] : 'Untitled');

            // Extract description/content
            preg_match('/<description[^>]*><!\[CDATA\[(.*?)\]\]><\/description>|<description[^>]*>(.*?)<\/description>/s', $item, $desc_match);
            $content = !empty($desc_match[1]) ? $desc_match[1] : (!empty($desc_match[2]) ? $desc_match[2] : '');

            // Extract link
            preg_match('/<link[^>]*>(.*?)<\/link>/s', $item, $link_match);
            $link = !empty($link_match[1]) ? trim($link_match[1]) : '';

            if (!empty($title) || !empty($content)) {
                $content_processor($title, $content, $link);
                $imported_count++;
            }

            // Check memory usage periodically
            if ($imported_count % 100 === 0) {
                $this->check_memory_usage();
            }
        }

        return $imported_count;
    }

    /**
     * Stream Google Drive content with chunked processing
     */
    private function stream_google_drive_content($config, $content_processor) {
        try {
            require_once __DIR__ . '/GoogleDriveIntegration.php';
            $google_drive = new GoogleDriveIntegration();

            if (!$google_drive->is_connected()) {
                $this->logger->log('Google Drive not connected');
                return 0;
            }

            $folder_id = $config['folder_id'] ?? null;
            $imported_count = 0;

            // List files in the specified folder
            $result = $google_drive->list_files($folder_id);

            if (isset($result['files'])) {
                foreach ($result['files'] as $file) {
                    $this->check_memory_usage();

                    // Skip folders
                    if ($file['mimeType'] === 'application/vnd.google-apps.folder') {
                        continue;
                    }

                    // Get file content
                    $file_data = $google_drive->get_file_content($file['id']);

                    // Process and save the content
                    $content_processor($file['name'], $file_data['content'], $file['webViewLink']);
                    $imported_count++;

                    // Check memory usage periodically
                    if ($imported_count % 50 === 0) {
                        $this->check_memory_usage();
                    }
                }
            }

            return $imported_count;
        } catch (\Exception $e) {
            $this->logger->log('Google Drive sync error - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Stream Dropbox content with chunked processing
     */
    private function stream_dropbox_content($config, $content_processor) {
        try {
            require_once __DIR__ . '/DropboxIntegration.php';
            $dropbox = new DropboxIntegration();

            if (!$dropbox->is_connected()) {
                $this->logger->log('Dropbox not connected');
                return 0;
            }

            $path = $config['path'] ?? '';
            $imported_count = 0;

            // List files in the specified folder
            $result = $dropbox->list_files($path);

            if (isset($result['entries'])) {
                foreach ($result['entries'] as $file) {
                    $this->check_memory_usage();

                    // Skip folders
                    if ($file['.tag'] === 'folder') {
                        continue;
                    }

                    // Download file
                    $file_data = $dropbox->download_file($file['path_lower']);

                    // Process and save the content
                    $content_processor($file['name'], $file_data['content'], '');
                    $imported_count++;

                    // Check memory usage periodically
                    if ($imported_count % 50 === 0) {
                        $this->check_memory_usage();
                    }
                }
            }

            return $imported_count;
        } catch (\Exception $e) {
            $this->logger->log('Dropbox sync error - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Stream Canva content with chunked processing
     */
    private function stream_canva_content($config, $content_processor) {
        try {
            require_once __DIR__ . '/CanvaIntegration.php';
            $canva = new CanvaIntegration();

            if (!$canva->is_connected()) {
                $this->logger->log('Canva not connected');
                return 0;
            }

            $imported_count = 0;

            // List user designs
            $result = $canva->list_designs(10);

            if (isset($result['items'])) {
                foreach ($result['items'] as $design) {
                    try {
                        $this->check_memory_usage();

                        // Export design as PNG
                        $export_result = $canva->export_design($design['id'], 'png');

                        if (isset($export_result['job']['id'])) {
                            $job_id = $export_result['job']['id'];

                            // Wait for export to complete (simplified)
                            sleep(3);

                            // Download the exported file
                            $file_data = $canva->download_export($job_id);

                            // Process and save the content
                            $content_processor($design['title'] . '.png', $file_data['content'], '');
                            $imported_count++;

                            // Check memory usage periodically
                            if ($imported_count % 25 === 0) {
                                $this->check_memory_usage();
                            }
                        }
                    } catch (\Exception $e) {
                        $this->logger->log('Failed to process Canva design ' . $design['id'] . ': ' . $e->getMessage());
                        continue;
                    }
                }
            }

            return $imported_count;
        } catch (\Exception $e) {
            $this->logger->log('Canva sync error - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Process content in chunks to avoid memory overload
     */
    public function process_content_in_chunks($processor_callback, $content_items, $chunk_size = 100) {
        $this->logger->log("Starting chunked content processing for " . count($content_items) . " items");

        $total_processed = 0;
        $total_errors = 0;

        // Process content in chunks
        for ($i = 0; $i < count($content_items); $i += $chunk_size) {
            $this->check_memory_usage();

            $chunk = array_slice($content_items, $i, $chunk_size);

            foreach ($chunk as $content_item) {
                try {
                    $result = $processor_callback($content_item);
                    if ($result) {
                        $total_processed++;
                    } else {
                        $total_errors++;
                    }
                } catch (\Exception $e) {
                    $this->logger->log("Error processing content item: " . $e->getMessage());
                    $total_errors++;
                }
            }

            // Force cleanup after each chunk
            $this->cleanup_resources();
        }

        $this->logger->log("Completed chunked content processing. Processed: {$total_processed}, Errors: {$total_errors}");

        return array(
            'processed' => $total_processed,
            'errors' => $total_errors,
            'total' => $total_processed + $total_errors
        );
    }
}

/**
 * Simple logger for content streaming
 */
class ContentStreamLogger {
    public function log($message) {
        if (function_exists('error_log')) {
            error_log('SMO Content Stream: ' . $message);
        }
    }
}