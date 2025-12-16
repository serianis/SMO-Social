<?php
/**
 * Audience Demographics Modal Component
 * Interactive charts and demographic analysis
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../../Analytics/AudienceDemographicsTracker.php';

/**
 * Audience Demographics Modal View
 */
class AudienceDemographicsModal {
    
    private $demographics_tracker;
    
    public function __construct() {
        $this->demographics_tracker = new \SMO_Social\Analytics\AudienceDemographicsTracker();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_get_demographics_modal', array($this, 'ajax_get_demographics_data'));
        add_action('wp_ajax_smo_get_demographic_insights_modal', array($this, 'ajax_get_demographic_insights'));
        add_action('wp_ajax_smo_export_demographics_modal', array($this, 'ajax_export_demographics'));
        add_action('wp_ajax_smo_sync_demographics_modal', array($this, 'ajax_sync_demographics'));
    }
    
    /**
     * Get audience demographics modal HTML
     */
    public function get_modal_html() {
        ob_start();
        ?>
        <div id="smo-modal-audience-demographics" class="smo-modal">
            <div class="smo-modal-content extra-large-modal">
                <span class="smo-modal-close" role="button" tabindex="0" aria-label="<?php _e('Close modal', 'smo-social'); ?>">&times;</span>
                
                <div class="smo-modal-header">
                    <h3><?php _e('Audience Demographics Analytics', 'smo-social'); ?></h3>
                    <div class="smo-modal-actions">
                        <button type="button" class="button" id="smo-sync-demographics-btn">
                            <?php _e('Sync Data', 'smo-social'); ?>
                        </button>
                        <button type="button" class="button" id="smo-export-demographics-btn">
                            <?php _e('Export Report', 'smo-social'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="smo-generate-insights-btn">
                            <?php _e('Generate Insights', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="smo-modal-body">
                    <!-- Tabs Navigation -->
                    <div class="smo-tabs-navigation">
                        <button class="smo-tab-btn active" data-tab="overview" aria-controls="tab-overview" role="tab">
                            <?php _e('Overview', 'smo-social'); ?>
                        </button>
                        <button class="smo-tab-btn" data-tab="age" aria-controls="tab-age" role="tab">
                            <?php _e('Age Analysis', 'smo-social'); ?>
                        </button>
                        <button class="smo-tab-btn" data-tab="gender" aria-controls="tab-gender" role="tab">
                            <?php _e('Gender Distribution', 'smo-social'); ?>
                        </button>
                        <button class="smo-tab-btn" data-tab="location" aria-controls="tab-location" role="tab">
                            <?php _e('Geographic Data', 'smo-social'); ?>
                        </button>
                        <button class="smo-tab-btn" data-tab="trends" aria-controls="tab-trends" role="tab">
                            <?php _e('Trends', 'smo-social'); ?>
                        </button>
                        <button class="smo-tab-btn" data-tab="insights" aria-controls="tab-insights" role="tab">
                            <?php _e('AI Insights', 'smo-social'); ?>
                            <span class="smo-tab-badge smo-new-badge">AI</span>
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="smo-demographics-filters">
                        <div class="smo-filters-row">
                            <div class="smo-filter-group">
                                <label for="smo-filter-platform-demo" class="smo-filter-label"><?php _e('Platform:', 'smo-social'); ?></label>
                                <select id="smo-filter-platform-demo" class="smo-filter-select">
                                    <option value=""><?php _e('All Platforms', 'smo-social'); ?></option>
                                    <option value="facebook"><?php _e('Facebook', 'smo-social'); ?></option>
                                    <option value="instagram"><?php _e('Instagram', 'smo-social'); ?></option>
                                    <option value="twitter"><?php _e('Twitter/X', 'smo-social'); ?></option>
                                    <option value="linkedin"><?php _e('LinkedIn', 'smo-social'); ?></option>
                                </select>
                            </div>
                            
                            <div class="smo-filter-group">
                                <label for="smo-filter-date-from" class="smo-filter-label"><?php _e('Date From:', 'smo-social'); ?></label>
                                <input type="date" id="smo-filter-date-from" class="smo-filter-input">
                            </div>
                            
                            <div class="smo-filter-group">
                                <label for="smo-filter-date-to" class="smo-filter-label"><?php _e('Date To:', 'smo-social'); ?></label>
                                <input type="date" id="smo-filter-date-to" class="smo-filter-input">
                            </div>
                            
                            <div class="smo-filter-group">
                                <button type="button" class="button button-small" id="smo-apply-filters">
                                    <?php _e('Apply Filters', 'smo-social'); ?>
                                </button>
                                <button type="button" class="button button-small" id="smo-reset-filters">
                                    <?php _e('Reset', 'smo-social'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Content -->
                    <div class="smo-tab-content">
                        <!-- Overview Tab -->
                        <div id="tab-overview" class="smo-tab-pane active" role="tabpanel">
                            <div class="smo-overview-section">
                                <div class="smo-summary-cards">
                                    <div class="smo-summary-card">
                                        <h4><?php _e('Total Audience', 'smo-social'); ?></h4>
                                        <div class="smo-summary-value" id="smo-total-audience">-</div>
                                        <span class="smo-summary-change" id="smo-audience-change"></span>
                                    </div>
                                    <div class="smo-summary-card">
                                        <h4><?php _e('Primary Age Group', 'smo-social'); ?></h4>
                                        <div class="smo-summary-value" id="smo-primary-age">-</div>
                                        <span class="smo-summary-subtitle" id="smo-age-percentage"></span>
                                    </div>
                                    <div class="smo-summary-card">
                                        <h4><?php _e('Top Location', 'smo-social'); ?></h4>
                                        <div class="smo-summary-value" id="smo-top-location">-</div>
                                        <span class="smo-summary-subtitle" id="smo-location-percentage"></span>
                                    </div>
                                    <div class="smo-summary-card">
                                        <h4><?php _e('Platform Coverage', 'smo-social'); ?></h4>
                                        <div class="smo-summary-value" id="smo-platform-count">-</div>
                                        <span class="smo-summary-subtitle"><?php _e('Active Platforms', 'smo-social'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="smo-overview-charts">
                                    <div class="smo-chart-row">
                                        <div class="smo-chart-container">
                                            <h4><?php _e('Audience Distribution by Platform', 'smo-social'); ?></h4>
                                            <canvas id="smo-platform-distribution-chart" width="400" height="300"></canvas>
                                        </div>
                                        <div class="smo-chart-container">
                                            <h4><?php _e('Demographic Overview', 'smo-social'); ?></h4>
                                            <canvas id="smo-demographic-overview-chart" width="400" height="300"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Age Analysis Tab -->
                        <div id="tab-age" class="smo-tab-pane" role="tabpanel">
                            <div class="smo-age-section">
                                <div class="smo-age-controls">
                                    <div class="smo-age-filters">
                                        <button type="button" class="smo-age-filter-btn active" data-age-group="all">
                                            <?php _e('All Ages', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-age-filter-btn" data-age-group="18-24">
                                            <?php _e('18-24', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-age-filter-btn" data-age-group="25-34">
                                            <?php _e('25-34', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-age-filter-btn" data-age-group="35-44">
                                            <?php _e('35-44', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-age-filter-btn" data-age-group="45-54">
                                            <?php _e('45-54', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-age-filter-btn" data-age-group="55+">
                                            <?php _e('55+', 'smo-social'); ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="smo-age-charts">
                                    <div class="smo-chart-row">
                                        <div class="smo-chart-container">
                                            <h4><?php _e('Age Distribution', 'smo-social'); ?></h4>
                                            <canvas id="smo-age-distribution-chart" width="400" height="300"></canvas>
                                        </div>
                                        <div class="smo-chart-container">
                                            <h4><?php _e('Age by Platform', 'smo-social'); ?></h4>
                                            <canvas id="smo-age-platform-chart" width="400" height="300"></canvas>
                                        </div>
                                    </div>
                                    
                                    <div class="smo-age-insights" id="smo-age-insights">
                                        <!-- Age-specific insights will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Gender Distribution Tab -->
                        <div id="tab-gender" class="smo-tab-pane" role="tabpanel">
                            <div class="smo-gender-section">
                                <div class="smo-gender-overview">
                                    <div class="smo-gender-stats">
                                        <div class="smo-gender-stat">
                                            <h4><?php _e('Male Audience', 'smo-social'); ?></h4>
                                            <div class="smo-gender-percentage" id="smo-male-percentage">-</div>
                                            <div class="smo-gender-count" id="smo-male-count">-</div>
                                        </div>
                                        <div class="smo-gender-stat">
                                            <h4><?php _e('Female Audience', 'smo-social'); ?></h4>
                                            <div class="smo-gender-percentage" id="smo-female-percentage">-</div>
                                            <div class="smo-gender-count" id="smo-female-count">-</div>
                                        </div>
                                        <div class="smo-gender-stat">
                                            <h4><?php _e('Other/Unknown', 'smo-social'); ?></h4>
                                            <div class="smo-gender-percentage" id="smo-other-percentage">-</div>
                                            <div class="smo-gender-count" id="smo-other-count">-</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="smo-gender-charts">
                                    <div class="smo-chart-row">
                                        <div class="smo-chart-container">
                                            <h4><?php _e('Gender Distribution', 'smo-social'); ?></h4>
                                            <canvas id="smo-gender-distribution-chart" width="400" height="300"></canvas>
                                        </div>
                                        <div class="smo-chart-container">
                                            <h4><?php _e('Gender by Platform', 'smo-social'); ?></h4>
                                            <canvas id="smo-gender-platform-chart" width="400" height="300"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Geographic Data Tab -->
                        <div id="tab-location" class="smo-tab-pane" role="tabpanel">
                            <div class="smo-location-section">
                                <div class="smo-location-filters">
                                    <div class="smo-location-type-toggle">
                                        <button type="button" class="smo-location-toggle-btn active" data-location-type="countries">
                                            <?php _e('Countries', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-location-toggle-btn" data-location-type="cities">
                                            <?php _e('Cities', 'smo-social'); ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="smo-location-content">
                                    <div class="smo-location-list" id="smo-location-list">
                                        <!-- Top locations will be displayed here -->
                                    </div>
                                    
                                    <div class="smo-location-chart">
                                        <canvas id="smo-location-chart" width="600" height="400"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Trends Tab -->
                        <div id="tab-trends" class="smo-tab-pane" role="tabpanel">
                            <div class="smo-trends-section">
                                <div class="smo-trend-filters">
                                    <div class="smo-trend-period-selector">
                                        <button type="button" class="smo-trend-period-btn active" data-period="7d">
                                            <?php _e('7 Days', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-trend-period-btn" data-period="30d">
                                            <?php _e('30 Days', 'smo-social'); ?>
                                        </button>
                                        <button type="button" class="smo-trend-period-btn" data-period="90d">
                                            <?php _e('90 Days', 'smo-social'); ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="smo-trends-content">
                                    <div class="smo-trend-chart">
                                        <h4><?php _e('Audience Growth Trends', 'smo-social'); ?></h4>
                                        <canvas id="smo-trend-chart" width="800" height="400"></canvas>
                                    </div>
                                    
                                    <div class="smo-trend-insights" id="smo-trend-insights">
                                        <!-- Trend insights will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Insights Tab -->
                        <div id="tab-insights" class="smo-tab-pane" role="tabpanel">
                            <div class="smo-insights-section">
                                <div class="smo-insights-header">
                                    <h4><?php _e('AI-Powered Demographic Insights', 'smo-social'); ?></h4>
                                    <p><?php _e('Get intelligent analysis and recommendations based on your audience data', 'smo-social'); ?></p>
                                </div>
                                
                                <div class="smo-insights-content" id="smo-insights-container">
                                    <div class="smo-insights-loading">
                                        <div class="smo-spinner"></div>
                                        <p><?php _e('Analyzing audience data...', 'smo-social'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="smo-insights-actions">
                                    <button type="button" class="button button-primary" id="smo-refresh-insights">
                                        <?php _e('Refresh Insights', 'smo-social'); ?>
                                    </button>
                                    <button type="button" class="button" id="smo-save-insights">
                                        <?php _e('Save Insights', 'smo-social'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let currentFilters = {};
            let currentAgeFilter = 'all';
            let currentLocationType = 'countries';
            let currentTrendPeriod = '30d';
            
            // Initialize modal
            initializeDemographicsModal();
            
            // Tab navigation
            $('.smo-tab-btn').on('click', function() {
                const tab = $(this).data('tab');
                switchTab(tab);
            });
            
            // Filter handlers
            $('#smo-apply-filters').on('click', function() {
                applyFilters();
            });
            
            $('#smo-reset-filters').on('click', function() {
                resetFilters();
            });
            
            // Age filter buttons
            $('.smo-age-filter-btn').on('click', function() {
                $('.smo-age-filter-btn').removeClass('active');
                $(this).addClass('active');
                currentAgeFilter = $(this).data('age-group');
                loadAgeData();
            });
            
            // Location type toggle
            $('.smo-location-toggle-btn').on('click', function() {
                $('.smo-location-toggle-btn').removeClass('active');
                $(this).addClass('active');
                currentLocationType = $(this).data('location-type');
                loadLocationData();
            });
            
            // Trend period selector
            $('.smo-trend-period-btn').on('click', function() {
                $('.smo-trend-period-btn').removeClass('active');
                $(this).addClass('active');
                currentTrendPeriod = $(this).data('period');
                loadTrendData();
            });
            
            // Action buttons
            $('#smo-sync-demographics-btn').on('click', function() {
                syncDemographics();
            });
            
            $('#smo-export-demographics-btn').on('click', function() {
                exportDemographics();
            });
            
            $('#smo-generate-insights-btn').on('click', function() {
                generateInsights();
            });
            
            $('#smo-refresh-insights').on('click', function() {
                generateInsights();
            });
            
            $('#smo-save-insights').on('click', function() {
                saveInsights();
            });
            
            function initializeDemographicsModal() {
                loadOverviewData();
                initializeCharts();
            }
            
            function loadOverviewData() {
                const filters = getCurrentFilters();
                
                $.post(ajaxurl, {
                    action: 'smo_get_demographics_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: filters
                }, function(response) {
                    if (response.success) {
                        updateOverviewData(response.data);
                        updateOverviewCharts(response.data);
                    } else {
                        console.error('Failed to load demographics:', response.data);
                    }
                });
            }
            
            function updateOverviewData(data) {
                $('#smo-total-audience').text(data.total_audience || '-');
                $('#smo-primary-age').text(data.primary_age_group || '-');
                $('#smo-age-percentage').text(data.age_percentage ? data.age_percentage + '%' : '');
                $('#smo-top-location').text(data.top_location || '-');
                $('#smo-location-percentage').text(data.location_percentage ? data.location_percentage + '%' : '');
                $('#smo-platform-count').text(data.platform_count || '-');
                $('#smo-audience-change').text(data.audience_change || '');
            }
            
            function updateOverviewCharts(data) {
                // Platform distribution chart
                if (data.platform_distribution) {
                    createPieChart('smo-platform-distribution-chart', data.platform_distribution);
                }
                
                // Demographic overview chart
                if (data.demographic_overview) {
                    createBarChart('smo-demographic-overview-chart', data.demographic_overview);
                }
            }
            
            function loadAgeData() {
                const filters = getCurrentFilters();
                filters.age_group = currentAgeFilter;
                
                $.post(ajaxurl, {
                    action: 'smo_get_demographics_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: filters,
                    view: 'age'
                }, function(response) {
                    if (response.success) {
                        updateAgeCharts(response.data);
                        updateAgeInsights(response.data);
                    }
                });
            }
            
            function updateAgeCharts(data) {
                if (data.age_distribution) {
                    createPieChart('smo-age-distribution-chart', data.age_distribution);
                }
                
                if (data.age_by_platform) {
                    createStackedBarChart('smo-age-platform-chart', data.age_by_platform);
                }
            }
            
            function updateAgeInsights(data) {
                const container = $('#smo-age-insights');
                let html = '';
                
                if (data.insights && data.insights.length > 0) {
                    data.insights.forEach(function(insight) {
                        html += `
                            <div class="smo-insight-card ${insight.type}">
                                <div class="smo-insight-title">${escapeHtml(insight.title)}</div>
                                <div class="smo-insight-description">${escapeHtml(insight.description)}</div>
                            </div>
                        `;
                    });
                } else {
                    html = '<p>No age-specific insights available for the selected filters.</p>';
                }
                
                container.html(html);
            }
            
            function loadLocationData() {
                const filters = getCurrentFilters();
                filters.location_type = currentLocationType;
                
                $.post(ajaxurl, {
                    action: 'smo_get_demographics_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: filters,
                    view: 'location'
                }, function(response) {
                    if (response.success) {
                        updateLocationData(response.data);
                    }
                });
            }
            
            function updateLocationData(data) {
                // Update location list
                const listContainer = $('#smo-location-list');
                let html = '';
                
                if (data.top_locations && data.top_locations.length > 0) {
                    data.top_locations.forEach(function(location) {
                        html += `
                            <div class="smo-location-item">
                                <span class="smo-location-name">${escapeHtml(location.name)}</span>
                                <span class="smo-location-percentage">${location.percentage}%</span>
                            </div>
                        `;
                    });
                }
                
                listContainer.html(html || '<p>No location data available.</p>');
                
                // Update location chart
                if (data.location_distribution) {
                    createHorizontalBarChart('smo-location-chart', data.location_distribution);
                }
            }
            
            function loadTrendData() {
                const filters = getCurrentFilters();
                filters.period = currentTrendPeriod;
                
                $.post(ajaxurl, {
                    action: 'smo_get_demographics_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: filters,
                    view: 'trends'
                }, function(response) {
                    if (response.success) {
                        updateTrendChart(response.data);
                        updateTrendInsights(response.data);
                    }
                });
            }
            
            function updateTrendChart(data) {
                if (data.trend_data) {
                    createLineChart('smo-trend-chart', data.trend_data);
                }
            }
            
            function updateTrendInsights(data) {
                const container = $('#smo-trend-insights');
                let html = '';
                
                if (data.trend_insights && data.trend_insights.length > 0) {
                    data.trend_insights.forEach(function(insight) {
                        html += `
                            <div class="smo-insight-card ${insight.type}">
                                <div class="smo-insight-title">${escapeHtml(insight.title)}</div>
                                <div class="smo-insight-description">${escapeHtml(insight.description)}</div>
                            </div>
                        `;
                    });
                } else {
                    html = '<p>No trend insights available for the selected period.</p>';
                }
                
                container.html(html);
            }
            
            function generateInsights() {
                const filters = getCurrentFilters();
                
                $.post(ajaxurl, {
                    action: 'smo_get_demographic_insights_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: filters
                }, function(response) {
                    if (response.success) {
                        renderInsights(response.data);
                    } else {
                        console.error('Failed to generate insights:', response.data);
                    }
                });
            }
            
            function renderInsights(insights) {
                const container = $('#smo-insights-container');
                let html = '';
                
                if (insights && insights.length > 0) {
                    insights.forEach(function(insight) {
                        html += `
                            <div class="smo-insight-card ${insight.priority}">
                                <div class="smo-insight-title">${escapeHtml(insight.title)}</div>
                                <div class="smo-insight-description">${escapeHtml(insight.description)}</div>
                            </div>
                        `;
                    });
                } else {
                    html = '<p>No insights available for the current filters.</p>';
                }
                
                container.html(html);
            }
            
            function initializeCharts() {
                // Initialize Chart.js charts
                if (typeof Chart !== 'undefined') {
                    // Charts will be created dynamically
                } else {
                    console.warn('Chart.js not loaded');
                }
            }
            
            function createPieChart(canvasId, data) {
                const ctx = document.getElementById(canvasId);
                if (!ctx || typeof Chart === 'undefined') return;
                
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.values,
                            backgroundColor: [
                                '#0073aa', '#ff6900', '#00a32a', '#d63638', 
                                '#8c8f94', '#50575e', '#2271b1', '#f56e28'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            function createBarChart(canvasId, data) {
                const ctx = document.getElementById(canvasId);
                if (!ctx || typeof Chart === 'undefined') return;
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Count',
                            data: data.values,
                            backgroundColor: '#0073aa'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function createStackedBarChart(canvasId, data) {
                const ctx = document.getElementById(canvasId);
                if (!ctx || typeof Chart === 'undefined') return;
                
                new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: {
                        responsive: true,
                        scales: {
                            x: {
                                stacked: true
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function createHorizontalBarChart(canvasId, data) {
                const ctx = document.getElementById(canvasId);
                if (!ctx || typeof Chart === 'undefined') return;
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Percentage',
                            data: data.values,
                            backgroundColor: '#0073aa'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
            
            function createLineChart(canvasId, data) {
                const ctx = document.getElementById(canvasId);
                if (!ctx || typeof Chart === 'undefined') return;
                
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function getCurrentFilters() {
                return {
                    platform: $('#smo-filter-platform-demo').val(),
                    date_from: $('#smo-filter-date-from').val(),
                    date_to: $('#smo-filter-date-to').val()
                };
            }
            
            function applyFilters() {
                currentFilters = getCurrentFilters();
                loadOverviewData();
                switchTab('overview');
            }
            
            function resetFilters() {
                $('#smo-filter-platform-demo').val('');
                $('#smo-filter-date-from').val('');
                $('#smo-filter-date-to').val('');
                currentFilters = {};
                loadOverviewData();
            }
            
            function switchTab(tab) {
                $('.smo-tab-btn').removeClass('active');
                $('.smo-tab-pane').removeClass('active');
                
                $(`.smo-tab-btn[data-tab="${tab}"]`).addClass('active');
                $(`#tab-${tab}`).addClass('active');
                
                // Load tab-specific data
                switch(tab) {
                    case 'age':
                        loadAgeData();
                        break;
                    case 'gender':
                        loadGenderData();
                        break;
                    case 'location':
                        loadLocationData();
                        break;
                    case 'trends':
                        loadTrendData();
                        break;
                    case 'insights':
                        // Insights loaded on demand
                        break;
                }
            }
            
            function loadGenderData() {
                const filters = getCurrentFilters();
                
                $.post(ajaxurl, {
                    action: 'smo_get_demographics_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: filters,
                    view: 'gender'
                }, function(response) {
                    if (response.success) {
                        updateGenderData(response.data);
                    }
                });
            }
            
            function updateGenderData(data) {
                if (data.gender_distribution) {
                    $('#smo-male-percentage').text(data.gender_distribution.male.percentage + '%');
                    $('#smo-male-count').text(data.gender_distribution.male.count.toLocaleString());
                    
                    $('#smo-female-percentage').text(data.gender_distribution.female.percentage + '%');
                    $('#smo-female-count').text(data.gender_distribution.female.count.toLocaleString());
                    
                    $('#smo-other-percentage').text(data.gender_distribution.other.percentage + '%');
                    $('#smo-other-count').text(data.gender_distribution.other.count.toLocaleString());
                    
                    createPieChart('smo-gender-distribution-chart', data.gender_distribution.chart_data);
                    createStackedBarChart('smo-gender-platform-chart', data.gender_platform_chart);
                }
            }
            
            function syncDemographics() {
                $.post(ajaxurl, {
                    action: 'smo_sync_demographics_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        loadOverviewData();
                        showNotification('Demographics synchronized successfully', 'success');
                    } else {
                        showNotification('Sync failed: ' + response.data, 'error');
                    }
                });
            }
            
            function exportDemographics() {
                const filters = getCurrentFilters();
                
                $.post(ajaxurl, {
                    action: 'smo_export_demographics_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: filters
                }, function(response) {
                    if (response.success) {
                        downloadFile(response.data.content, response.data.filename, 'text/csv');
                        showNotification('Demographics exported successfully', 'success');
                    } else {
                        showNotification('Export failed: ' + response.data, 'error');
                    }
                });
            }
            
            function saveInsights() {
                const insights = $('#smo-insights-container').html();
                showNotification('Insights saved successfully', 'success');
            }
            
            function downloadFile(content, filename, mimeType) {
                const blob = new Blob([content], { type: mimeType });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function showNotification(message, type) {
                const notification = $(`<div class="smo-notification smo-notification-${type}">${message}</div>`);
                $('body').append(notification);

                if (window.smoPrefersReducedMotion && window.smoPrefersReducedMotion()) {
                    setTimeout(function() {
                        notification.remove();
                    }, 3000);
                } else {
                    setTimeout(function() {
                        notification.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            }
        });
        </script>
        
        <!-- Include Chart.js for interactive charts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get demographics data for modal
     */
    public function ajax_get_demographics_data() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        $filters = $_POST['filters'] ?? array();
        $view = $_POST['view'] ?? 'overview';
        
        try {
            switch ($view) {
                case 'age':
                    $data = $this->get_age_data($filters);
                    break;
                case 'gender':
                    $data = $this->get_gender_data($filters);
                    break;
                case 'location':
                    $data = $this->get_location_data($filters);
                    break;
                case 'trends':
                    $data = $this->get_trends_data($filters);
                    break;
                default:
                    $data = $this->get_overview_data($filters);
            }
            
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get overview data
     */
    private function get_overview_data($filters) {
        $demographics = $this->demographics_tracker->get_demographics($filters);
        $summary = $this->demographics_tracker->get_demographic_summary($filters);
        
        return array(
            'total_audience' => array_sum($summary['gender_distribution']),
            'primary_age_group' => array_key_first($summary['age_distribution']) ?: 'N/A',
            'age_percentage' => reset($summary['age_distribution']) ?: 0,
            'top_location' => array_key_first($summary['top_countries']) ?: 'N/A',
            'location_percentage' => reset($summary['top_countries']) ?: 0,
            'platform_count' => count(array_unique(array_column($demographics, 'platform'))),
            'platform_distribution' => $this->format_platform_distribution($demographics),
            'demographic_overview' => $this->format_demographic_overview($demographics),
            'summary' => $summary
        );
    }
    
    /**
     * Get age data
     */
    private function get_age_data($filters) {
        $demographics = $this->demographics_tracker->get_demographics($filters);
        
        return array(
            'age_distribution' => $this->format_age_distribution($demographics),
            'age_by_platform' => $this->format_age_by_platform($demographics),
            'insights' => $this->generate_age_insights($demographics)
        );
    }
    
    /**
     * Get gender data
     */
    private function get_gender_data($filters) {
        $demographics = $this->demographics_tracker->get_demographics($filters);
        
        return array(
            'gender_distribution' => $this->format_gender_distribution($demographics)
        );
    }
    
    /**
     * Get location data
     */
    private function get_location_data($filters) {
        $demographics = $this->demographics_tracker->get_demographics($filters);
        
        return array(
            'top_locations' => $this->get_top_locations($demographics, $filters['location_type'] ?? 'countries'),
            'location_distribution' => $this->format_location_distribution($demographics, $filters['location_type'] ?? 'countries')
        );
    }
    
    /**
     * Get trends data
     */
    private function get_trends_data($filters) {
        $period = $filters['period'] ?? '30d';
        $date_from = date('Y-m-d', strtotime("-{$period}"));
        
        $filters['date_from'] = $date_from;
        $trends = $this->demographics_tracker->get_demographic_trends($filters);
        
        return array(
            'trend_data' => $this->format_trend_data($trends),
            'trend_insights' => $this->generate_trend_insights($trends)
        );
    }
    
    /**
     * AJAX: Get demographic insights
     */
    public function ajax_get_demographic_insights() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        $filters = $_POST['filters'] ?? array();
        
        try {
            $insights = $this->demographics_tracker->get_demographic_insights($filters);
            wp_send_json_success($insights);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Export demographics
     */
    public function ajax_export_demographics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        $filters = $_POST['filters'] ?? array();
        
        try {
            $export_data = $this->demographics_tracker->export_demographics($filters, 'csv');
            $filename = 'demographics-export-' . date('Y-m-d-H-i-s') . '.csv';
            
            wp_send_json_success(array(
                'content' => $export_data,
                'filename' => $filename
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Sync demographics
     */
    public function ajax_sync_demographics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        try {
            $platform = $_POST['filters']['platform'] ?? '';

            if ($platform) {
                error_log("SMO Social Debug: Attempting to sync demographics for platform: {$platform}");
                $this->demographics_tracker->sync_platform_demographics($platform);
                wp_send_json_success(array('message' => sprintf(__('Demographics synchronized for %s', 'smo-social'), $platform)));
            } else {
                error_log("SMO Social Debug: Attempting to sync all demographics");
                $this->demographics_tracker->sync_all_demographics();
                wp_send_json_success(array('message' => __('All platform demographics synchronized', 'smo-social')));
            }
        } catch (\Exception $e) {
            error_log("SMO Social Debug: Demographics sync failed: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    // Helper methods for formatting data
    
    private function format_platform_distribution($demographics) {
        $platforms = array();
        foreach ($demographics as $demo) {
            if (!isset($platforms[$demo['platform']])) {
                $platforms[$demo['platform']] = 0;
            }
            $platforms[$demo['platform']] += $demo['total_percentage'];
        }
        
        return array(
            'labels' => array_keys($platforms),
            'values' => array_values($platforms)
        );
    }
    
    private function format_demographic_overview($demographics) {
        return array(
            'labels' => array('Age Groups', 'Gender', 'Location'),
            'values' => array(25, 35, 40) // Placeholder values
        );
    }
    
    private function format_age_distribution($demographics) {
        $ages = array();
        foreach ($demographics as $demo) {
            if (!isset($ages[$demo['age_range']])) {
                $ages[$demo['age_range']] = 0;
            }
            $ages[$demo['age_range']] += $demo['total_percentage'];
        }
        
        return array(
            'labels' => array_keys($ages),
            'values' => array_values($ages)
        );
    }
    
    private function format_age_by_platform($demographics) {
        // Format data for stacked bar chart
        $platforms = array_unique(array_column($demographics, 'platform'));
        $age_ranges = array_unique(array_column($demographics, 'age_range'));
        
        $datasets = array();
        foreach ($platforms as $platform) {
            $data = array();
            foreach ($age_ranges as $age_range) {
                $total = 0;
                foreach ($demographics as $demo) {
                    if ($demo['platform'] === $platform && $demo['age_range'] === $age_range) {
                        $total += $demo['total_percentage'];
                    }
                }
                $data[] = $total;
            }
            
            $datasets[] = array(
                'label' => ucfirst($platform),
                'data' => $data,
                'backgroundColor' => $this->get_platform_color($platform)
            );
        }
        
        return array(
            'labels' => $age_ranges,
            'datasets' => $datasets
        );
    }
    
    private function format_gender_distribution($demographics) {
        $genders = array('male' => 0, 'female' => 0, 'other' => 0);
        $total = 0;
        
        foreach ($demographics as $demo) {
            $gender = strtolower($demo['gender']);
            if (isset($genders[$gender])) {
                $genders[$gender] += $demo['total_percentage'];
            } else {
                $genders['other'] += $demo['total_percentage'];
            }
            $total += $demo['total_percentage'];
        }
        
        $formatted = array();
        foreach ($genders as $gender => $count) {
            $formatted[$gender] = array(
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0
            );
        }
        
        $formatted['chart_data'] = array(
            'labels' => array('Male', 'Female', 'Other/Unknown'),
            'values' => array($genders['male'], $genders['female'], $genders['other'])
        );
        
        return $formatted;
    }
    
    private function get_top_locations($demographics, $type) {
        $locations = array();
        
        foreach ($demographics as $demo) {
            $location = $type === 'cities' ? $demo['location_city'] : $demo['location_country'];
            if (!isset($locations[$location])) {
                $locations[$location] = 0;
            }
            $locations[$location] += $demo['total_percentage'];
        }
        
        arsort($locations);
        
        $top_locations = array();
        $count = 0;
        foreach ($locations as $location => $percentage) {
            if ($count >= 10) break;
            $top_locations[] = array(
                'name' => $location,
                'percentage' => round($percentage, 1)
            );
            $count++;
        }
        
        return $top_locations;
    }
    
    private function format_location_distribution($demographics, $type) {
        $locations = array();
        foreach ($demographics as $demo) {
            $location = $type === 'cities' ? $demo['location_city'] : $demo['location_country'];
            if (!isset($locations[$location])) {
                $locations[$location] = 0;
            }
            $locations[$location] += $demo['total_percentage'];
        }
        
        arsort($locations);
        
        return array(
            'labels' => array_slice(array_keys($locations), 0, 10),
            'values' => array_slice(array_values($locations), 0, 10)
        );
    }
    
    private function format_trend_data($trends) {
        // Format trends data for Chart.js line chart
        $labels = array();
        $datasets = array();
        
        $platforms = array_unique(array_column($trends, 'platform'));
        foreach ($platforms as $platform) {
            $platform_data = array();
            foreach ($trends as $trend) {
                if ($trend['platform'] === $platform) {
                    $platform_data[] = $trend['total_value'];
                }
            }
            $datasets[] = array(
                'label' => ucfirst($platform),
                'data' => $platform_data,
                'borderColor' => $this->get_platform_color($platform),
                'backgroundColor' => $this->get_platform_color($platform, 0.1)
            );
        }
        
        return array(
            'labels' => array_unique(array_column($trends, 'date')),
            'datasets' => $datasets
        );
    }
    
    private function generate_age_insights($demographics) {
        $insights = array();
        
        // Analyze age distribution
        $age_dist = array();
        foreach ($demographics as $demo) {
            if (!isset($age_dist[$demo['age_range']])) {
                $age_dist[$demo['age_range']] = 0;
            }
            $age_dist[$demo['age_range']] += $demo['total_percentage'];
        }
        
        arsort($age_dist);
        $dominant_age = array_key_first($age_dist);
        
        if ($dominant_age) {
            $insights[] = array(
                'type' => 'info',
                'title' => 'Dominant Age Group',
                'description' => "Your primary audience is {$dominant_age} years old, representing " . 
                               round($age_dist[$dominant_age], 1) . "% of your total audience."
            );
        }
        
        return $insights;
    }
    
    private function generate_trend_insights($trends) {
        $insights = array();
        
        // Simple trend analysis
        if (count($trends) > 1) {
            $recent_avg = array_slice($trends, 0, 7);
            $older_avg = array_slice($trends, 7, 7);
            
            $recent_total = array_sum(array_column($recent_avg, 'total_value'));
            $older_total = array_sum(array_column($older_avg, 'total_value'));
            
            if ($recent_total > $older_total) {
                $insights[] = array(
                    'type' => 'success',
                    'title' => 'Growth Trend',
                    'description' => 'Your audience is growing. Recent activity shows a ' . 
                                   round((($recent_total - $older_total) / $older_total) * 100, 1) . '% increase.'
                );
            }
        }
        
        return $insights;
    }
    
    private function get_platform_color($platform, $alpha = 1) {
        $colors = array(
            'facebook' => 'rgba(59, 89, 152, ' . $alpha . ')',
            'instagram' => 'rgba(225, 48, 108, ' . $alpha . ')',
            'twitter' => 'rgba(29, 161, 242, ' . $alpha . ')',
            'linkedin' => 'rgba(0, 119, 181, ' . $alpha . ')',
            'tiktok' => array(0, 242, 234)
        );
        
        if (isset($colors[$platform])) {
            $color = $colors[$platform];
            if (is_array($color)) {
                return 'rgba(' . implode(', ', $color) . ', ' . $alpha . ')';
            }
            return $color;
        }
        
        return 'rgba(100, 100, 100, ' . $alpha . ')';
    }
}