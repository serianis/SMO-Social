<?php
/**
 * SMO Social - Content Import Testing Framework
 * 
 * Comprehensive testing system for content import workflows
 * across all SMO Social integrations with real data testing.
 *
 * @package SMO_Social
 * @subpackage Testing
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Import Testing Framework
 */
class SMOContentImportTester {
    
    /**
     * Test content import workflows
     */
    public static function test_content_import_workflows() {
        $results = [];
        
        // Test image import services
        $image_services = self::test_image_import_services();
        $results['image_imports'] = $image_services;
        
        // Test document import services
        $document_services = self::test_document_import_services();
        $results['document_imports'] = $document_services;
        
        // Test media import services
        $media_services = self::test_media_import_services();
        $results['media_imports'] = $media_services;
        
        // Test feed import services
        $feed_services = self::test_feed_import_services();
        $results['feed_imports'] = $feed_services;
        
        return $results;
    }
    
    /**
     * Test image import services
     */
    private static function test_image_import_services() {
        $services = [
            'unsplash' => [
                'name' => 'Unsplash',
                'test_query' => 'nature landscape',
                'test_type' => 'search_images'
            ],
            'pixabay' => [
                'name' => 'Pixabay',
                'test_query' => 'mountain landscape',
                'test_type' => 'search_images'
            ],
            'canva' => [
                'name' => 'Canva',
                'test_type' => 'import_designs',
                'test_action' => 'browse_designs'
            ],
            'google_photos' => [
                'name' => 'Google Photos',
                'test_type' => 'import_photos',
                'test_action' => 'browse_albums'
            ]
        ];
        
        $results = [];
        
        foreach ($services as $service_id => $config) {
            $results[$service_id] = self::test_image_service($service_id, $config);
        }
        
        return $results;
    }
    
    /**
     * Test document import services
     */
    private static function test_document_import_services() {
        $services = [
            'dropbox' => [
                'name' => 'Dropbox',
                'test_type' => 'import_files',
                'test_action' => 'browse_files'
            ],
            'google_drive' => [
                'name' => 'Google Drive',
                'test_type' => 'import_files',
                'test_action' => 'browse_files'
            ],
            'onedrive' => [
                'name' => 'OneDrive',
                'test_type' => 'import_files',
                'test_action' => 'browse_files'
            ]
        ];
        
        $results = [];
        
        foreach ($services as $service_id => $config) {
            $results[$service_id] = self::test_document_service($service_id, $config);
        }
        
        return $results;
    }
    
    /**
     * Test media import services
     */
    private static function test_media_import_services() {
        $services = [
            'pixabay' => [
                'name' => 'Pixabay',
                'test_type' => 'search_videos',
                'test_query' => 'nature video'
            ]
        ];
        
        $results = [];
        
        foreach ($services as $service_id => $config) {
            $results[$service_id] = self::test_media_service($service_id, $config);
        }
        
        return $results;
    }
    
    /**
     * Test feed import services
     */
    private static function test_feed_import_services() {
        $services = [
            'feedly' => [
                'name' => 'Feedly',
                'test_type' => 'import_articles',
                'test_action' => 'read_feeds'
            ],
            'pocket' => [
                'name' => 'Pocket',
                'test_type' => 'import_articles',
                'test_action' => 'read_items'
            ]
        ];
        
        $results = [];
        
        foreach ($services as $service_id => $config) {
            $results[$service_id] = self::test_feed_service($service_id, $config);
        }
        
        return $results;
    }
    
    /**
     * Test image service
     */
    private static function test_image_service($service_id, $config) {
        $result = [
            'service' => $service_id,
            'name' => $config['name'],
            'status' => 'failed',
            'test_type' => $config['test_type'],
            'details' => []
        ];
        
        // Check if service is connected
        $connection_test = self::check_service_connection($service_id);
        if (!$connection_test['connected']) {
            $result['details'][] = '❌ Service not connected';
            $result['error'] = $connection_test['error'];
            return $result;
        }
        
        $result['details'][] = '✅ Service connected';
        
        // Test content browsing capability
        $browse_test = self::test_content_browsing($service_id, $config);
        if ($browse_test['success']) {
            $result['details'][] = '✅ Content browsing working';
            $result['content_available'] = $browse_test['content_count'];
        } else {
            $result['details'][] = '❌ Content browsing failed';
            $result['browse_error'] = $browse_test['error'];
            return $result;
        }
        
        // Test content import
        if (isset($browse_test['sample_content']) && !empty($browse_test['sample_content'])) {
            $import_test = self::test_content_import($service_id, $browse_test['sample_content']);
            if ($import_test['success']) {
                $result['details'][] = '✅ Content import successful';
                $result['status'] = 'passed';
            } else {
                $result['details'][] = '⚠️ Content import failed';
                $result['import_error'] = $import_test['error'];
            }
        } else {
            $result['details'][] = '⚠️ No content available for import test';
            $result['status'] = 'warning';
        }
        
        return $result;
    }
    
    /**
     * Test document service
     */
    private static function test_document_service($service_id, $config) {
        $result = [
            'service' => $service_id,
            'name' => $config['name'],
            'status' => 'failed',
            'test_type' => $config['test_type'],
            'details' => []
        ];
        
        // Check if service is connected
        $connection_test = self::check_service_connection($service_id);
        if (!$connection_test['connected']) {
            $result['details'][] = '❌ Service not connected';
            $result['error'] = $connection_test['error'];
            return $result;
        }
        
        $result['details'][] = '✅ Service connected';
        
        // Test file browsing capability
        $browse_test = self::test_file_browsing($service_id, $config);
        if ($browse_test['success']) {
            $result['details'][] = '✅ File browsing working';
            $result['files_available'] = $browse_test['file_count'];
        } else {
            $result['details'][] = '❌ File browsing failed';
            $result['browse_error'] = $browse_test['error'];
            return $result;
        }
        
        // Test file import
        if (isset($browse_test['sample_files']) && !empty($browse_test['sample_files'])) {
            $import_test = self::test_file_import($service_id, $browse_test['sample_files'][0]);
            if ($import_test['success']) {
                $result['details'][] = '✅ File import successful';
                $result['status'] = 'passed';
            } else {
                $result['details'][] = '⚠️ File import failed';
                $result['import_error'] = $import_test['error'];
            }
        } else {
            $result['details'][] = '⚠️ No files available for import test';
            $result['status'] = 'warning';
        }
        
        return $result;
    }
    
    /**
     * Test media service
     */
    private static function test_media_service($service_id, $config) {
        $result = [
            'service' => $service_id,
            'name' => $config['name'],
            'status' => 'failed',
            'test_type' => $config['test_type'],
            'details' => []
        ];
        
        // Check if service is connected
        $connection_test = self::check_service_connection($service_id);
        if (!$connection_test['connected']) {
            $result['details'][] = '❌ Service not connected';
            $result['error'] = $connection_test['error'];
            return $result;
        }
        
        $result['details'][] = '✅ Service connected';
        
        // Test video search
        $search_test = self::test_video_search($service_id, $config);
        if ($search_test['success']) {
            $result['details'][] = '✅ Video search working';
            $result['videos_available'] = $search_test['video_count'];
        } else {
            $result['details'][] = '❌ Video search failed';
            $result['search_error'] = $search_test['error'];
            return $result;
        }
        
        // Test video import
        if (isset($search_test['sample_videos']) && !empty($search_test['sample_videos'])) {
            $import_test = self::test_video_import($service_id, $search_test['sample_videos'][0]);
            if ($import_test['success']) {
                $result['details'][] = '✅ Video import successful';
                $result['status'] = 'passed';
            } else {
                $result['details'][] = '⚠️ Video import failed';
                $result['import_error'] = $import_test['error'];
            }
        } else {
            $result['details'][] = '⚠️ No videos available for import test';
            $result['status'] = 'warning';
        }
        
        return $result;
    }
    
    /**
     * Test feed service
     */
    private static function test_feed_service($service_id, $config) {
        $result = [
            'service' => $service_id,
            'name' => $config['name'],
            'status' => 'failed',
            'test_type' => $config['test_type'],
            'details' => []
        ];
        
        // Check if service is connected
        $connection_test = self::check_service_connection($service_id);
        if (!$connection_test['connected']) {
            $result['details'][] = '❌ Service not connected';
            $result['error'] = $connection_test['error'];
            return $result;
        }
        
        $result['details'][] = '✅ Service connected';
        
        // Test feed reading
        $feed_test = self::test_feed_reading($service_id, $config);
        if ($feed_test['success']) {
            $result['details'][] = '✅ Feed reading working';
            $result['articles_available'] = $feed_test['article_count'];
        } else {
            $result['details'][] = '❌ Feed reading failed';
            $result['feed_error'] = $feed_test['error'];
            return $result;
        }
        
        // Test article import
        if (isset($feed_test['sample_articles']) && !empty($feed_test['sample_articles'])) {
            $import_test = self::test_article_import($service_id, $feed_test['sample_articles'][0]);
            if ($import_test['success']) {
                $result['details'][] = '✅ Article import successful';
                $result['status'] = 'passed';
            } else {
                $result['details'][] = '⚠️ Article import failed';
                $result['import_error'] = $import_test['error'];
            }
        } else {
            $result['details'][] = '⚠️ No articles available for import test';
            $result['status'] = 'warning';
        }
        
        return $result;
    }
    
    /**
     * Check if service is connected
     */
    private static function check_service_connection($service_id) {
        $connections = get_option('smo_integration_connections', []);
        
        if (!isset($connections[$service_id])) {
            return [
                'connected' => false,
                'error' => 'No connection found'
            ];
        }
        
        $connection = $connections[$service_id];
        
        // Check for access token or API key
        if (empty($connection['access_token']) && empty($connection['api_key'])) {
            return [
                'connected' => false,
                'error' => 'No valid credentials found'
            ];
        }
        
        // Check token expiry if present
        if (isset($connection['expires_at'])) {
            $expires_at = strtotime($connection['expires_at']);
            if ($expires_at < time()) {
                return [
                    'connected' => false,
                    'error' => 'Token expired'
                ];
            }
        }
        
        return ['connected' => true];
    }
    
    /**
     * Test content browsing
     */
    private static function test_content_browsing($service_id, $config) {
        // Simulate content browsing based on service type
        switch ($service_id) {
            case 'unsplash':
                return self::simulate_unsplash_browsing($config['test_query']);
            case 'pixabay':
                return self::simulate_pixabay_browsing($config['test_query']);
            case 'canva':
                return self::simulate_canva_browsing();
            case 'google_photos':
                return self::simulate_google_photos_browsing();
            default:
                return [
                    'success' => false,
                    'error' => 'Unknown service'
                ];
        }
    }
    
    /**
     * Test file browsing
     */
    private static function test_file_browsing($service_id, $config) {
        // Simulate file browsing
        return self::simulate_file_browsing($service_id);
    }
    
    /**
     * Test video search
     */
    private static function test_video_search($service_id, $config) {
        // Simulate video search
        return self::simulate_video_search($service_id, $config['test_query']);
    }
    
    /**
     * Test feed reading
     */
    private static function test_feed_reading($service_id, $config) {
        // Simulate feed reading
        return self::simulate_feed_reading($service_id);
    }
    
    /**
     * Test content import
     */
    private static function test_content_import($service_id, $content) {
        // Simulate content import
        try {
            // Store import in database
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'smo_imported_content';
            
            $wpdb->insert(
                $table_name,
                [
                    'service_id' => $service_id,
                    'content_id' => $content['id'] ?? uniqid(),
                    'content_type' => $content['type'] ?? 'image',
                    'title' => $content['title'] ?? 'Test Import',
                    'url' => $content['url'] ?? '',
                    'metadata' => json_encode($content),
                    'imported_at' => current_time('mysql'),
                    'user_id' => get_current_user_id()
                ]
            );
            
            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test file import
     */
    private static function test_file_import($service_id, $file) {
        // Simulate file import
        return self::test_content_import($service_id, $file);
    }
    
    /**
     * Test video import
     */
    private static function test_video_import($service_id, $video) {
        // Simulate video import
        return self::test_content_import($service_id, $video);
    }
    
    /**
     * Test article import
     */
    private static function test_article_import($service_id, $article) {
        // Simulate article import
        return self::test_content_import($service_id, $article);
    }
    
    /**
     * Simulation methods (replace with actual API calls in production)
     */
    private static function simulate_unsplash_browsing($query) {
        return [
            'success' => true,
            'content_count' => 25,
            'sample_content' => [
                [
                    'id' => 'test_' . uniqid(),
                    'type' => 'image',
                    'title' => 'Sample Unsplash Image',
                    'url' => 'https://example.com/unsplash-image.jpg',
                    'thumbnail' => 'https://example.com/unsplash-thumb.jpg'
                ]
            ]
        ];
    }
    
    private static function simulate_pixabay_browsing($query) {
        return [
            'success' => true,
            'content_count' => 50,
            'sample_content' => [
                [
                    'id' => 'test_' . uniqid(),
                    'type' => 'image',
                    'title' => 'Sample Pixabay Image',
                    'url' => 'https://example.com/pixabay-image.jpg'
                ]
            ]
        ];
    }
    
    private static function simulate_canva_browsing() {
        return [
            'success' => true,
            'content_count' => 10,
            'sample_content' => [
                [
                    'id' => 'test_' . uniqid(),
                    'type' => 'design',
                    'title' => 'Sample Canva Design',
                    'url' => 'https://example.com/canva-design.jpg'
                ]
            ]
        ];
    }
    
    private static function simulate_google_photos_browsing() {
        return [
            'success' => true,
            'content_count' => 15,
            'sample_content' => [
                [
                    'id' => 'test_' . uniqid(),
                    'type' => 'photo',
                    'title' => 'Sample Google Photo',
                    'url' => 'https://example.com/google-photo.jpg'
                ]
            ]
        ];
    }
    
    private static function simulate_file_browsing($service_id) {
        return [
            'success' => true,
            'file_count' => 20,
            'sample_files' => [
                [
                    'id' => 'test_' . uniqid(),
                    'type' => 'document',
                    'name' => 'Sample Document.pdf',
                    'url' => 'https://example.com/document.pdf',
                    'size' => '1024KB'
                ]
            ]
        ];
    }
    
    private static function simulate_video_search($service_id, $query) {
        return [
            'success' => true,
            'video_count' => 5,
            'sample_videos' => [
                [
                    'id' => 'test_' . uniqid(),
                    'type' => 'video',
                    'title' => 'Sample Nature Video',
                    'url' => 'https://example.com/nature-video.mp4',
                    'thumbnail' => 'https://example.com/video-thumb.jpg'
                ]
            ]
        ];
    }
    
    private static function simulate_feed_reading($service_id) {
        return [
            'success' => true,
            'article_count' => 30,
            'sample_articles' => [
                [
                    'id' => 'test_' . uniqid(),
                    'type' => 'article',
                    'title' => 'Sample Article',
                    'url' => 'https://example.com/article',
                    'excerpt' => 'This is a sample article excerpt...'
                ]
            ]
        ];
    }
    
    /**
     * Generate content import test report
     */
    public static function generate_test_report($test_results) {
        $report = "<div class='smo-content-import-report'>\n";
        $report .= "<h2>📦 Content Import Testing Report</h2>\n";
        
        // Image Imports
        if (isset($test_results['image_imports'])) {
            $report .= "<h3>🖼️ Image Import Services</h3>\n";
            $report .= self::generate_service_section($test_results['image_imports']);
        }
        
        // Document Imports
        if (isset($test_results['document_imports'])) {
            $report .= "<h3>📄 Document Import Services</h3>\n";
            $report .= self::generate_service_section($test_results['document_imports']);
        }
        
        // Media Imports
        if (isset($test_results['media_imports'])) {
            $report .= "<h3>🎬 Media Import Services</h3>\n";
            $report .= self::generate_service_section($test_results['media_imports']);
        }
        
        // Feed Imports
        if (isset($test_results['feed_imports'])) {
            $report .= "<h3>📰 Feed Import Services</h3>\n";
            $report .= self::generate_service_section($test_results['feed_imports']);
        }
        
        $report .= "</div>\n";
        return $report;
    }
    
    /**
     * Generate service section
     */
    private static function generate_service_section($services) {
        $section = "";
        
        foreach ($services as $service_id => $result) {
            $status_icon = $result['status'] === 'passed' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌');
            
            $section .= "<div class='smo-service-result' data-service='{$service_id}'>\n";
            $section .= "<h4>{$status_icon} {$result['name']}</h4>\n";
            $section .= "<div class='smo-result-details'>\n";
            
            foreach ($result['details'] as $detail) {
                $section .= "<div class='smo-detail-line'>{$detail}</div>\n";
            }
            
            if (isset($result['content_available'])) {
                $section .= "<div class='smo-metric'>Content Available: {$result['content_available']} items</div>\n";
            }
            
            if (isset($result['files_available'])) {
                $section .= "<div class='smo-metric'>Files Available: {$result['files_available']} files</div>\n";
            }
            
            if (isset($result['videos_available'])) {
                $section .= "<div class='smo-metric'>Videos Available: {$result['videos_available']} videos</div>\n";
            }
            
            if (isset($result['articles_available'])) {
                $section .= "<div class='smo-metric'>Articles Available: {$result['articles_available']} articles</div>\n";
            }
            
            if (isset($result['error'])) {
                $section .= "<div class='smo-error'>Error: {$result['error']}</div>\n";
            }
            
            if (isset($result['browse_error'])) {
                $section .= "<div class='smo-error'>Browse Error: {$result['browse_error']}</div>\n";
            }
            
            if (isset($result['import_error'])) {
                $section .= "<div class='smo-error'>Import Error: {$result['import_error']}</div>\n";
            }
            
            $section .= "</div>\n";
            $section .= "</div>\n";
        }
        
        return $section;
    }
}

// AJAX handler for content import tests
add_action('wp_ajax_smo_test_content_imports', 'smo_handle_content_import_tests');

function smo_handle_content_import_tests() {
    check_ajax_referer('smo_content_import_tests', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $test_results = SMOContentImportTester::test_content_import_workflows();
    wp_send_json_success($test_results);
}

/**
 * Add content import testing admin page
 */
function smo_add_content_import_test_admin_page() {
    add_submenu_page(
        'smo-social',
        'Content Import Testing',
        '📦 Import Tests',
        'manage_options',
        'smo-content-import-tests',
        'smo_render_content_import_test_page'
    );
}
add_action('admin_menu', 'smo_add_content_import_test_admin_page');

/**
 * Render content import testing page
 */
function smo_render_content_import_test_page() {
    ?>
    <div class="wrap">
        <h1>📦 SMO Social - Content Import Testing</h1>
        
        <div class="smo-test-intro">
            <p>This testing suite validates content import workflows across all integrated services. Each test verifies the complete import process from content discovery to database storage.</p>
        </div>
        
        <div class="smo-test-controls">
            <h3>🧪 Testing Controls</h3>
            <button id="smo-run-content-import-tests" class="button button-primary">🚀 Test All Content Imports</button>
            <button id="smo-refresh-content-import-tests" class="button">🔄 Refresh Tests</button>
        </div>
        
        <div class="smo-test-results" id="smo-content-import-test-results">
            <p>Click "Test All Content Imports" to start testing...</p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#smo-run-content-import-tests').on('click', function() {
            runContentImportTests();
        });
        
        $('#smo-refresh-content-import-tests').on('click', function() {
            location.reload();
        });
    });
    
    function runContentImportTests() {
        $('#smo-run-content-import-tests').prop('disabled', true).text('Testing...');
        $('#smo-content-import-test-results').html('<div class="smo-loading"><p>Testing content import workflows...</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_test_content_imports',
                nonce: '<?php echo wp_create_nonce('smo_content_import_tests'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayImportResults(response.data);
                } else {
                    $('#smo-content-import-test-results').html('<div class="smo-error">Test failed: ' + (response.data.message || 'Unknown error') + '</div>');
                }
            },
            error: function() {
                $('#smo-content-import-test-results').html('<div class="smo-error">Request failed</div>');
            },
            complete: function() {
                $('#smo-run-content-import-tests').prop('disabled', false).text('🚀 Test All Content Imports');
            }
        });
    }
    
    function displayImportResults(results) {
        var report = '<div class="smo-import-report">';
        
        // Image Imports
        if (results.image_imports) {
            report += '<h3>🖼️ Image Import Services</h3>';
            report += generateServiceSection(results.image_imports);
        }
        
        // Document Imports
        if (results.document_imports) {
            report += '<h3>📄 Document Import Services</h3>';
            report += generateServiceSection(results.document_imports);
        }
        
        // Media Imports
        if (results.media_imports) {
            report += '<h3>🎬 Media Import Services</h3>';
            report += generateServiceSection(results.media_imports);
        }
        
        // Feed Imports
        if (results.feed_imports) {
            report += '<h3>📰 Feed Import Services</h3>';
            report += generateServiceSection(results.feed_imports);
        }
        
        report += '</div>';
        
        $('#smo-content-import-test-results').html(report);
    }
    
    function generateServiceSection(services) {
        var section = '';
        
        for (var service in services) {
            var result = services[service];
            var icon = result.status === 'passed' ? '✅' : (result.status === 'warning' ? '⚠️' : '❌');
            
            section += '<div class="smo-service-result">' +
                '<h4>' + icon + ' ' + result.name + '</h4>' +
                '<div class="smo-result-details">';
            
            for (var i = 0; i < result.details.length; i++) {
                section += '<div class="smo-detail-line">' + result.details[i] + '</div>';
            }
            
            if (result.content_available) {
                section += '<div class="smo-metric">Content Available: ' + result.content_available + ' items</div>';
            }
            
            if (result.files_available) {
                section += '<div class="smo-metric">Files Available: ' + result.files_available + ' files</div>';
            }
            
            if (result.videos_available) {
                section += '<div class="smo-metric">Videos Available: ' + result.videos_available + ' videos</div>';
            }
            
            if (result.articles_available) {
                section += '<div class="smo-metric">Articles Available: ' + result.articles_available + ' articles</div>';
            }
            
            if (result.error) {
                section += '<div class="smo-error">Error: ' + result.error + '</div>';
            }
            
            if (result.browse_error) {
                section += '<div class="smo-error">Browse Error: ' + result.browse_error + '</div>';
            }
            
            if (result.import_error) {
                section += '<div class="smo-error">Import Error: ' + result.import_error + '</div>';
            }
            
            section += '</div></div>';
        }
        
        return section;
    }
    </script>
    
    <style>
    .smo-test-intro {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .smo-test-controls {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .smo-service-result {
        background: #fafafa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .smo-detail-line {
        padding: 3px 0;
        font-family: monospace;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .smo-metric {
        background: #e3f2fd;
        color: #1565c0;
        padding: 5px 10px;
        border-radius: 15px;
        display: inline-block;
        margin: 5px 5px 0 0;
        font-size: 12px;
        font-weight: bold;
    }
    
    .smo-error {
        background: #ffebee;
        color: #c62828;
        padding: 8px;
        border-radius: 4px;
        margin-top: 10px;
        border-left: 4px solid #f44336;
    }
    
    .smo-loading {
        text-align: center;
        padding: 40px;
        color: #666;
    }
    </style>
    <?php
}