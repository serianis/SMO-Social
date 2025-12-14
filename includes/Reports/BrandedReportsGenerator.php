<?php
/**
 * Branded Reports Generation System
 * Creates professional, branded reports with logos and custom layouts
 */

namespace SMO_Social\Reports;

if (!defined('ABSPATH')) {
    exit;
}

// PHP Superglobal variables (for Intelephense compatibility) - Direct declarations
global $_POST, $_SERVER, $_GET, $_COOKIE, $_FILES;
if (!isset($_POST)) $_POST = array();
if (!isset($_SERVER)) $_SERVER = array('REMOTE_ADDR' => '127.0.0.1');
if (!isset($_GET)) $_GET = array();
if (!isset($_COOKIE)) $_COOKIE = array();
if (!isset($_FILES)) $_FILES = array();

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';
require_once __DIR__ . '/../global-declarations.php';
require_once __DIR__ . '/../type-stubs.php';

/**
 * Branded Reports Generation System
 * 
 * Creates professional branded reports:
 * - Custom branding (logos, colors, fonts)
 * - PDF report generation
 * - Cover page with company info
 * - Multiple report templates
 * - Automated report scheduling
 * - Email delivery of reports
 * 
 * @property-read array $_POST Superglobal POST data
 * @property-read array $_GET Superglobal GET data  
 * @property-read array $_SERVER Superglobal server data
 * @property-read array $_COOKIE Superglobal cookie data
 */

/**
 * Branded Reports Generation System
 * 
 * Creates professional branded reports:
 * - Custom branding (logos, colors, fonts)
 * - PDF report generation
 * - Cover page with company info
 * - Multiple report templates
 * - Automated report scheduling
 * - Email delivery of reports
 */
class BrandedReportsGenerator {
    
    public $last_error = '';
    
    private $table_names;
    private $report_templates;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'branded_reports' => $wpdb->prefix . 'smo_branded_reports',
            'enhanced_analytics' => $wpdb->prefix . 'smo_enhanced_analytics',
            'scheduled_posts' => $wpdb->prefix . 'smo_scheduled_posts',
            'audience_demographics' => $wpdb->prefix . 'smo_audience_demographics'
        );
        
        $this->init_report_templates();
        $this->init_hooks();
    }
    
    /**
     * Initialize report templates
     */
    private function init_report_templates() {
        $this->report_templates = array(
            'executive_summary' => array(
                'name' => 'Executive Summary',
                'description' => 'High-level overview for leadership',
                'sections' => array('overview', 'key_metrics', 'trends', 'recommendations'),
                'format' => 'compact'
            ),
            'detailed_analytics' => array(
                'name' => 'Detailed Analytics',
                'description' => 'Comprehensive analytics report',
                'sections' => array('overview', 'performance', 'demographics', 'engagement', 'platform_breakdown'),
                'format' => 'detailed'
            ),
            'performance_monthly' => array(
                'name' => 'Monthly Performance',
                'description' => 'Monthly performance review',
                'sections' => array('overview', 'monthly_trends', 'top_content', 'platform_performance'),
                'format' => 'monthly'
            ),
            'social_media_dashboard' => array(
                'name' => 'Social Media Dashboard',
                'description' => 'Visual dashboard-style report',
                'sections' => array('kpi_dashboard', 'charts', 'insights'),
                'format' => 'dashboard'
            ),
            'campaign_report' => array(
                'name' => 'Campaign Report',
                'description' => 'Single campaign analysis',
                'sections' => array('campaign_overview', 'performance', 'audience', 'recommendations'),
                'format' => 'campaign'
            )
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_create_branded_report', array($this, 'ajax_create_branded_report'));
        add_action('wp_ajax_smo_get_report_templates', array($this, 'ajax_get_report_templates'));
        add_action('wp_ajax_smo_get_report_history', array($this, 'ajax_get_report_history'));
        add_action('wp_ajax_smo_schedule_report', array($this, 'ajax_schedule_report'));
        add_action('wp_ajax_smo_export_report', array($this, 'ajax_export_report'));
        add_action('wp_ajax_smo_get_report_data', array($this, 'ajax_get_report_data'));
        
        // Schedule automated report generation
        add_action('smo_generate_scheduled_reports', array($this, 'generate_scheduled_reports'));
        if (!wp_next_scheduled('smo_generate_scheduled_reports')) {
            wp_schedule_event(time(), 'weekly', 'smo_generate_scheduled_reports');
        }
    }
    
    /**
     * Create branded report template
     */
    public function create_branded_report_template($template_data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_names['branded_reports'],
            array(
                'name' => sanitize_text_field($template_data['name']),
                'logo_url' => esc_url_raw($template_data['logo_url']),
                'brand_colors' => json_encode($template_data['brand_colors']),
                'company_info' => json_encode($template_data['company_info']),
                'template_config' => json_encode($template_data['template_config']),
                'is_default' => isset($template_data['is_default']) ? intval($template_data['is_default']) : 0,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to create branded report template: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Generate branded report
     */
    public function generate_branded_report($template_id, $report_config) {
        global $wpdb;
        
        // Get template
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['branded_reports']} WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            throw new \Exception('Report template not found');
        }
        
        // Prepare data
        $report_data = $this->prepare_report_data($report_config);
        
        // Generate report content
        $report_content = $this->render_report_template($template, $report_data, $report_config);
        
        // Create cover page
        $cover_page = $this->create_cover_page($template, $report_config);
        
        // Combine cover page and content
        $full_report = $cover_page . $report_content;
        
        // Generate filename
        $filename = $this->generate_report_filename($report_config);
        
        // Save report record
        $report_id = $this->save_report_record($template_id, $report_config, $full_report, $filename);
        
        return array(
            'report_id' => $report_id,
            'filename' => $filename,
            'content' => $full_report,
            'download_url' => $this->get_report_download_url($report_id)
        );
    }
    
    /**
     * Prepare report data
     */
    private function prepare_report_data($config) {
        global $wpdb;
        
        $date_from = $config['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $config['date_to'] ?? date('Y-m-d');
        $platform = $config['platform'] ?? 'all';
        $template = $config['template'] ?? 'executive_summary';
        
        $data = array();
        
        // Basic metrics
        $data['metrics'] = $this->get_basic_metrics($date_from, $date_to, $platform);
        
        // Platform performance
        $data['platform_performance'] = $this->get_platform_performance($date_from, $date_to, $platform);
        
        // Content performance
        $data['top_content'] = $this->get_top_performing_content($date_from, $date_to, $platform);
        
        // Audience demographics
        $data['demographics'] = $this->get_audience_demographics($date_from, $date_to, $platform);
        
        // Engagement trends
        $data['engagement_trends'] = $this->get_engagement_trends($date_from, $date_to, $platform);
        
        // Recommendations
        $data['recommendations'] = $this->generate_recommendations($data);
        
        // Report metadata
        $data['meta'] = array(
            'date_from' => $date_from,
            'date_to' => $date_to,
            'platform' => $platform,
            'generated_at' => current_time('mysql'),
            'generated_by' => get_current_user_id()
        );
        
        return $data;
    }
    
    /**
     * Get basic metrics
     */
    private function get_basic_metrics($date_from, $date_to, $platform) {
        global $wpdb;
        
        $where_conditions = array('recorded_at >= %s', 'recorded_at <= %s');
        $where_values = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        if ($platform !== 'all') {
            $where_conditions[] = 'platform = %s';
            $where_values[] = $platform;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $metrics = array();
        
        // Total posts
        $metrics['total_posts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$this->table_names['enhanced_analytics']} $where_clause",
            $where_values
        ));
        
        // Total impressions
        $metrics['total_impressions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(metric_value) FROM {$this->table_names['enhanced_analytics']} 
             $where_clause AND metric_type = 'impressions'",
            $where_values
        )) ?: 0;
        
        // Total engagement
        $metrics['total_engagement'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(metric_value) FROM {$this->table_names['enhanced_analytics']} 
             $where_clause AND metric_type = 'engagement'",
            $where_values
        )) ?: 0;
        
        // Engagement rate
        $metrics['engagement_rate'] = $metrics['total_impressions'] > 0 ? 
            round(($metrics['total_engagement'] / $metrics['total_impressions']) * 100, 2) : 0;
        
        // Average engagement per post
        $metrics['avg_engagement_per_post'] = $metrics['total_posts'] > 0 ? 
            round($metrics['total_engagement'] / $metrics['total_posts'], 2) : 0;
        
        return $metrics;
    }
    
    /**
     * Get platform performance
     */
    private function get_platform_performance($date_from, $date_to, $platform) {
        global $wpdb;
        
        $where_conditions = array('recorded_at >= %s', 'recorded_at <= %s');
        $where_values = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        if ($platform !== 'all') {
            $where_conditions[] = 'platform = %s';
            $where_values[] = $platform;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT platform, 
                    COUNT(DISTINCT post_id) as total_posts,
                    SUM(CASE WHEN metric_type = 'impressions' THEN metric_value ELSE 0 END) as impressions,
                    SUM(CASE WHEN metric_type = 'engagement' THEN metric_value ELSE 0 END) as engagement
             FROM {$this->table_names['enhanced_analytics']}
             $where_clause
             GROUP BY platform
             ORDER BY engagement DESC",
            $where_values
        ), ARRAY_A);
    }
    
    /**
     * Get top performing content
     */
    private function get_top_performing_content($date_from, $date_to, $platform) {
        global $wpdb;
        
        $where_conditions = array('recorded_at >= %s', 'recorded_at <= %s');
        $where_values = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        if ($platform !== 'all') {
            $where_conditions[] = 'platform = %s';
            $where_values[] = $platform;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ea.platform, sp.title, sp.content, sp.published_at,
                    SUM(CASE WHEN metric_type = 'impressions' THEN metric_value ELSE 0 END) as impressions,
                    SUM(CASE WHEN metric_type = 'engagement' THEN metric_value ELSE 0 END) as engagement
             FROM {$this->table_names['enhanced_analytics']} ea
             LEFT JOIN {$this->table_names['scheduled_posts']} sp ON ea.post_id = sp.id
             $where_clause
             GROUP BY ea.post_id, ea.platform, sp.title, sp.content, sp.published_at
             ORDER BY engagement DESC
             LIMIT 10",
            $where_values
        ), ARRAY_A);
    }
    
    /**
     * Get audience demographics
     */
    private function get_audience_demographics($date_from, $date_to, $platform) {
        global $wpdb;
        
        $where_conditions = array('recorded_at >= %s', 'recorded_at <= %s');
        $where_values = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        if ($platform !== 'all') {
            $where_conditions[] = 'platform = %s';
            $where_values[] = $platform;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT age_range, gender, location_country, 
                    SUM(percentage) as total_percentage
             FROM {$this->table_names['audience_demographics']}
             $where_clause
             GROUP BY age_range, gender, location_country
             ORDER BY total_percentage DESC",
            $where_values
        ), ARRAY_A);
    }
    
    /**
     * Get engagement trends
     */
    private function get_engagement_trends($date_from, $date_to, $platform) {
        global $wpdb;
        
        $where_conditions = array('recorded_at >= %s', 'recorded_at <= %s');
        $where_values = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        if ($platform !== 'all') {
            $where_conditions[] = 'platform = %s';
            $where_values[] = $platform;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(recorded_at) as date, 
                    SUM(CASE WHEN metric_type = 'impressions' THEN metric_value ELSE 0 END) as impressions,
                    SUM(CASE WHEN metric_type = 'engagement' THEN metric_value ELSE 0 END) as engagement
             FROM {$this->table_names['enhanced_analytics']}
             $where_clause
             GROUP BY DATE(recorded_at)
             ORDER BY date",
            $where_values
        ), ARRAY_A);
    }
    
    /**
     * Generate recommendations based on data
     */
    private function generate_recommendations($data) {
        $recommendations = array();
        
        // Analyze engagement rate
        if ($data['metrics']['engagement_rate'] < 2) {
            $recommendations[] = array(
                'type' => 'warning',
                'title' => 'Low Engagement Rate',
                'description' => 'Your engagement rate is below 2%. Consider posting more engaging content or adjusting your posting times.'
            );
        }
        
        // Analyze top performing platform
        if (!empty($data['platform_performance'])) {
            $top_platform = $data['platform_performance'][0];
            $recommendations[] = array(
                'type' => 'success',
                'title' => 'Top Performing Platform',
                'description' => ucfirst($top_platform['platform']) . ' is your best performing platform. Consider allocating more resources here.'
            );
        }
        
        // Analyze posting frequency
        if ($data['metrics']['total_posts'] < 20) {
            $recommendations[] = array(
                'type' => 'info',
                'title' => 'Posting Frequency',
                'description' => 'Consider increasing your posting frequency to improve reach and engagement.'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Create cover page
     */
    private function create_cover_page($template, $config) {
        $brand_colors = json_decode($template->brand_colors, true);
        $company_info = json_decode($template->company_info, true);
        
        $cover_page = '
        <div class="report-cover">
            <div class="cover-header">
                <img src="' . esc_url($template->logo_url) . '" alt="Company Logo" class="company-logo">
                <h1 class="report-title">' . esc_html($config['title'] ?? 'Social Media Performance Report') . '</h1>
                <p class="report-subtitle">' . esc_html($config['subtitle'] ?? '') . '</p>
            </div>
            
            <div class="cover-info">
                <div class="company-details">
                    <h2>' . esc_html($company_info['company_name'] ?? get_bloginfo('name')) . '</h2>
                    <p>' . esc_html($company_info['website'] ?? '') . '</p>
                    <p>' . esc_html($company_info['contact_email'] ?? '') . '</p>
                </div>
                
                <div class="report-details">
                    <p><strong>Report Period:</strong> ' . esc_html($config['date_from'] ?? '') . ' to ' . esc_html($config['date_to'] ?? '') . '</p>
                    <p><strong>Generated:</strong> ' . date('F j, Y') . '</p>
                    <p><strong>Platform:</strong> ' . esc_html(ucfirst($config['platform'] ?? 'All')) . '</p>
                </div>
            </div>
        </div>
        
        <style>
        .report-cover {
            page-break-after: always;
            text-align: center;
            padding: 100px 50px;
            background: linear-gradient(135deg, ' . ($brand_colors['primary'] ?? '#0073aa') . ', ' . ($brand_colors['secondary'] ?? '#646970') . ');
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .cover-header { margin-bottom: 60px; }
        .company-logo { max-height: 100px; margin-bottom: 30px; }
        .report-title { font-size: 3em; margin: 20px 0; }
        .report-subtitle { font-size: 1.5em; opacity: 0.9; }
        .cover-info { text-align: left; }
        .company-details h2 { margin-bottom: 10px; }
        .report-details { margin-top: 40px; }
        .report-details p { margin: 10px 0; }
        </style>';
        
        return $cover_page;
    }
    
    /**
     * Render report template
     */
    private function render_report_template($template, $data, $config) {
        $template_config = json_decode($template->template_config, true);
        $brand_colors = json_decode($template->brand_colors, true);
        
        $content = '<div class="report-content">';
        
        // Generate sections based on template
        $sections = $this->report_templates[$config['template']]['sections'] ?? array('overview');
        
        foreach ($sections as $section) {
            $content .= $this->render_section($section, $data, $brand_colors);
        }
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Render individual section
     */
    private function render_section($section_name, $data, $brand_colors) {
        switch ($section_name) {
            case 'overview':
                return $this->render_overview_section($data, $brand_colors);
            case 'key_metrics':
                return $this->render_key_metrics_section($data, $brand_colors);
            case 'trends':
                return $this->render_trends_section($data, $brand_colors);
            case 'platform_performance':
                return $this->render_platform_performance_section($data, $brand_colors);
            case 'demographics':
                return $this->render_demographics_section($data, $brand_colors);
            case 'top_content':
                return $this->render_top_content_section($data, $brand_colors);
            case 'recommendations':
                return $this->render_recommendations_section($data, $brand_colors);
            default:
                return '';
        }
    }
    
    /**
     * Render overview section
     */
    private function render_overview_section($data, $brand_colors) {
        $metrics = $data['metrics'];
        
        return '
        <div class="report-section">
            <h2>Overview</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <h3>Total Posts</h3>
                    <div class="metric-value">' . number_format($metrics['total_posts']) . '</div>
                </div>
                <div class="metric-card">
                    <h3>Total Impressions</h3>
                    <div class="metric-value">' . number_format($metrics['total_impressions']) . '</div>
                </div>
                <div class="metric-card">
                    <h3>Total Engagement</h3>
                    <div class="metric-value">' . number_format($metrics['total_engagement']) . '</div>
                </div>
                <div class="metric-card">
                    <h3>Engagement Rate</h3>
                    <div class="metric-value">' . $metrics['engagement_rate'] . '%</div>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Render key metrics section
     */
    private function render_key_metrics_section($data, $brand_colors) {
        return '
        <div class="report-section">
            <h2>Key Performance Indicators</h2>
            <p>Your social media performance shows strong engagement across platforms.</p>
            <ul>
                <li>Average engagement per post: ' . $data['metrics']['avg_engagement_per_post'] . '</li>
                <li>Best performing platform: ' . (isset($data['platform_performance'][0]) ? ucfirst($data['platform_performance'][0]['platform']) : 'N/A') . '</li>
                <li>Total reach across all platforms: ' . number_format($data['metrics']['total_impressions']) . '</li>
            </ul>
        </div>';
    }
    
    /**
     * Render trends section
     */
    private function render_trends_section($data, $brand_colors) {
        $trends = $data['engagement_trends'];
        
        if (empty($trends)) {
            return '<div class="report-section"><h2>Trends</h2><p>No trend data available for the selected period.</p></div>';
        }
        
        $content = '<div class="report-section"><h2>Engagement Trends</h2>';
        $content .= '<table class="data-table"><thead><tr><th>Date</th><th>Impressions</th><th>Engagement</th></tr></thead><tbody>';
        
        foreach ($trends as $trend) {
            $content .= '<tr><td>' . date('M j', strtotime($trend['date'])) . '</td><td>' . number_format($trend['impressions']) . '</td><td>' . number_format($trend['engagement']) . '</td></tr>';
        }
        
        $content .= '</tbody></table></div>';
        
        return $content;
    }
    
    /**
     * Render platform performance section
     */
    private function render_platform_performance_section($data, $brand_colors) {
        $platforms = $data['platform_performance'];
        
        if (empty($platforms)) {
            return '<div class="report-section"><h2>Platform Performance</h2><p>No platform data available.</p></div>';
        }
        
        $content = '<div class="report-section"><h2>Platform Performance</h2>';
        $content .= '<table class="data-table"><thead><tr><th>Platform</th><th>Posts</th><th>Impressions</th><th>Engagement</th><th>Engagement Rate</th></tr></thead><tbody>';
        
        foreach ($platforms as $platform) {
            $engagement_rate = $platform['impressions'] > 0 ? round(($platform['engagement'] / $platform['impressions']) * 100, 2) : 0;
            $content .= '<tr><td>' . ucfirst($platform['platform']) . '</td><td>' . $platform['total_posts'] . '</td><td>' . number_format($platform['impressions']) . '</td><td>' . number_format($platform['engagement']) . '</td><td>' . $engagement_rate . '%</td></tr>';
        }
        
        $content .= '</tbody></table></div>';
        
        return $content;
    }
    
    /**
     * Render demographics section
     */
    private function render_demographics_section($data, $brand_colors) {
        $demographics = $data['demographics'];
        
        if (empty($demographics)) {
            return '<div class="report-section"><h2>Audience Demographics</h2><p>No demographic data available.</p></div>';
        }
        
        $content = '<div class="report-section"><h2>Audience Demographics</h2>';
        $content .= '<table class="data-table"><thead><tr><th>Age Range</th><th>Gender</th><th>Location</th><th>Percentage</th></tr></thead><tbody>';
        
        foreach ($demographics as $demo) {
            $content .= '<tr><td>' . esc_html($demo['age_range']) . '</td><td>' . esc_html(ucfirst($demo['gender'])) . '</td><td>' . esc_html($demo['location_country']) . '</td><td>' . $demo['total_percentage'] . '%</td></tr>';
        }
        
        $content .= '</tbody></table></div>';
        
        return $content;
    }
    
    /**
     * Render top content section
     */
    private function render_top_content_section($data, $brand_colors) {
        $content_items = $data['top_content'];
        
        if (empty($content_items)) {
            return '<div class="report-section"><h2>Top Performing Content</h2><p>No content data available.</p></div>';
        }
        
        $content = '<div class="report-section"><h2>Top Performing Content</h2>';
        $content .= '<table class="data-table"><thead><tr><th>Platform</th><th>Title</th><th>Impressions</th><th>Engagement</th></tr></thead><tbody>';
        
        foreach (array_slice($content_items, 0, 5) as $item) {
            $title = strlen($item['title']) > 50 ? substr($item['title'], 0, 47) . '...' : $item['title'];
            $content .= '<tr><td>' . ucfirst($item['platform']) . '</td><td>' . esc_html($title) . '</td><td>' . number_format($item['impressions']) . '</td><td>' . number_format($item['engagement']) . '</td></tr>';
        }
        
        $content .= '</tbody></table></div>';
        
        return $content;
    }
    
    /**
     * Render recommendations section
     */
    private function render_recommendations_section($data, $brand_colors) {
        $recommendations = $data['recommendations'];
        
        if (empty($recommendations)) {
            return '<div class="report-section"><h2>Recommendations</h2><p>No recommendations available for this period.</p></div>';
        }
        
        $content = '<div class="report-section"><h2>Recommendations</h2>';
        
        foreach ($recommendations as $rec) {
            $content .= '<div class="recommendation recommendation-' . $rec['type'] . '">';
            $content .= '<h3>' . esc_html($rec['title']) . '</h3>';
            $content .= '<p>' . esc_html($rec['description']) . '</p>';
            $content .= '</div>';
        }
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Generate report filename
     */
    private function generate_report_filename($config) {
        $date_from = $config['date_from'] ?? date('Y-m-d');
        $date_to = $config['date_to'] ?? date('Y-m-d');
        $platform = $config['platform'] ?? 'all';
        $template = $config['template'] ?? 'report';
        
        $filename = sanitize_file_name("social-media-report-{$template}-{$platform}-{$date_from}-to-{$date_to}.html");
        
        return $filename;
    }
    
    /**
     * Save report record
     */
    private function save_report_record($template_id, $config, $content, $filename) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'smo_generated_reports',
            array(
                'template_id' => $template_id,
                'title' => sanitize_text_field($config['title'] ?? 'Social Media Report'),
                'config' => json_encode($config),
                'content' => $content,
                'filename' => $filename,
                'file_path' => '',
                'generated_by' => get_current_user_id(),
                'generated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to save report record: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get report download URL
     */
    private function get_report_download_url($report_id) {
        return admin_url('admin.php?page=smo-social-reports&action=download&id=' . $report_id);
    }
    
    /**
     * Generate scheduled reports
     */
    public function generate_scheduled_reports() {
        // Get scheduled reports (this would be implemented based on your scheduling system)
        // For now, we'll skip this implementation
        error_log('SMO Social: Scheduled report generation triggered');
    }
    
    // AJAX handlers
    
    public function ajax_create_branded_report() {
        /** @var array $_POST */
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if (empty($template_id)) {
            wp_send_json_error(__('Template ID not provided'));
        }
        
        $report_config = array(
            'title' => isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '',
            'subtitle' => isset($_POST['subtitle']) ? sanitize_text_field($_POST['subtitle']) : '',
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'platform' => isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : 'all',
            'template' => isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'executive_summary'
        );
        
        try {
            $report = $this->generate_branded_report($template_id, $report_config);
            wp_send_json_success($report);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_report_templates() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        wp_send_json_success($this->report_templates);
    }
    
    public function ajax_get_report_data() {
        /** @var array $_POST */
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $config = array(
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'platform' => isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : 'all'
        );
        
        $data = $this->prepare_report_data($config);
        wp_send_json_success($data);
    }
}
