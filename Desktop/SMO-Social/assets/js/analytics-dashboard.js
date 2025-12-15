/**
 * SMO Social Analytics Dashboard JavaScript
 * Handles dashboard interactions, AJAX calls, and chart rendering
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize dashboard
    initAnalyticsDashboard();

    function initAnalyticsDashboard() {
        // Bind event handlers
        bindEvents();

        // Load initial data
        loadAnalyticsData();

        // Start real-time updates
        startRealtimeUpdates();
    }

    function bindEvents() {
        // Date range change
        $('#date-range').on('change', function() {
            loadAnalyticsData();
        });

        // Platform filter change
        $('#platform-filter').on('change', function() {
            loadAnalyticsData();
        });

        // Refresh button
        $('#refresh-analytics').on('click', function(e) {
            e.preventDefault();
            loadAnalyticsData();
        });

        // Export button
        $('#export-analytics').on('click', function(e) {
            e.preventDefault();
            showExportModal();
        });

        // Export modal events
        $('#start-export').on('click', function(e) {
            e.preventDefault();
            startExport();
        });

        $('#cancel-export, .smo-modal-close').on('click', function(e) {
            e.preventDefault();
            hideExportModal();
        });
    }

    function loadAnalyticsData() {
        const dateRange = $('#date-range').val();
        const platform = $('#platform-filter').val();

        // Show loading
        showLoading();

        $.ajax({
            url: smoAnalytics.ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_get_analytics_data',
                nonce: smoAnalytics.nonce,
                date_range: dateRange,
                platform: platform
            },
            success: function(response) {
                if (response.success) {
                    updateDashboard(response.data);
                } else {
                    showError('Failed to load analytics data: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                showError('AJAX Error: ' + error);
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    function updateDashboard(data) {
        // Update summary metrics
        if (data.summary) {
            updateSummaryMetrics(data.summary);
        }

        // Update charts
        if (data.trends && data.trends.timeline) {
            updateTimelineChart(data.trends.timeline);
        }

        if (data.performance) {
            updatePlatformChart(data.performance);
            updateEngagementChart(data.performance);
        }

        // Update best times
        if (data.best_times) {
            updateBestTimes(data.best_times);
        }

        // Update performance table
        if (data.content_insights && data.content_insights.top_performing_posts) {
            updatePerformanceTable(data.content_insights.top_performing_posts);
        }
    }

    function updateSummaryMetrics(summary) {
        $('#total-posts').text(summary.total_posts ? summary.total_posts.current : 0);
        $('#total-posts-change').text(formatChange(summary.total_posts ? summary.total_posts.change : 0));

        $('#total-reach').text(summary.total_reach ? formatNumber(summary.total_reach.current) : 0);
        $('#total-reach-change').text(formatChange(summary.total_reach ? summary.total_reach.change : 0));

        $('#engagement-rate').text(summary.engagement_rate ? summary.engagement_rate.current + '%' : '0%');
        $('#engagement-rate-change').text(formatChange(summary.engagement_rate ? summary.engagement_rate.change : 0));

        $('#best-platform').text(summary.best_platform ? summary.best_platform.name : 'N/A');
        $('#best-platform-score').text(summary.best_platform ? summary.best_platform.score : 0);
    }

    function updateTimelineChart(timeline) {
        const ctx = document.getElementById('posts-timeline-chart');
        if (!ctx) return;

        const labels = Object.keys(timeline);
        const postsData = labels.map(date => timeline[date].posts || 0);
        const reachData = labels.map(date => timeline[date].reach || 0);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Posts',
                    data: postsData,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }, {
                    label: 'Reach',
                    data: reachData,
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
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

    function updatePlatformChart(performance) {
        const ctx = document.getElementById('platform-performance-chart');
        if (!ctx) return;

        const labels = Object.keys(performance);
        const postsData = labels.map(platform => performance[platform].posts || 0);
        const reachData = labels.map(platform => performance[platform].reach || 0);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Posts',
                    data: postsData,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1
                }, {
                    label: 'Reach',
                    data: reachData,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgb(255, 99, 132)',
                    borderWidth: 1
                }]
            },
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

    function updateEngagementChart(performance) {
        const ctx = document.getElementById('engagement-chart');
        if (!ctx) return;

        const labels = Object.keys(performance);
        const engagementData = labels.map(platform => performance[platform].engagement || 0);

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: engagementData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
    }

    function updateBestTimes(bestTimes) {
        const grid = $('#best-times-grid');
        grid.empty();

        // Simple implementation - show recommended times
        Object.keys(bestTimes).forEach(platform => {
            const times = bestTimes[platform];
            const div = $('<div class="smo-best-time-item">').html(`
                <h4>${platform.charAt(0).toUpperCase() + platform.slice(1)}</h4>
                <p>Weekdays: ${times.weekdays ? times.weekdays.join(', ') : 'N/A'}</p>
                <p>Weekends: ${times.weekends ? times.weekends.join(', ') : 'N/A'}</p>
            `);
            grid.append(div);
        });
    }

    function updatePerformanceTable(posts) {
        const tbody = $('#performance-table-body');
        tbody.empty();

        if (!posts || posts.length === 0) {
            tbody.append('<tr><td colspan="6">No data available</td></tr>');
            return;
        }

        posts.forEach(post => {
            const row = $('<tr>').html(`
                <td>${post.title || 'N/A'}</td>
                <td>${post.platform || 'N/A'}</td>
                <td>${post.date || 'N/A'}</td>
                <td>${formatNumber(post.reach || 0)}</td>
                <td>${formatNumber(post.engagement || 0)}</td>
                <td>${post.engagement_rate ? post.engagement_rate + '%' : '0%'}</td>
            `);
            tbody.append(row);
        });
    }

    function startRealtimeUpdates() {
        // Update every 5 minutes
        setInterval(function() {
            loadRealtimeStats();
        }, 300000);

        // Initial load
        loadRealtimeStats();
    }

    function loadRealtimeStats() {
        $.ajax({
            url: smoAnalytics.ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_get_realtime_stats',
                nonce: smoAnalytics.realTimeNonce
            },
            success: function(response) {
                if (response.success) {
                    updateRealtimeStats(response.data);
                }
            },
            error: function(xhr, status, error) {
                // Realtime stats error
            }
        });
    }

    function updateRealtimeStats(data) {
        const logs = $('#realtime-logs');
        logs.empty();

        if (data.recent_activity) {
            data.recent_activity.forEach(activity => {
                const log = $('<div class="smo-realtime-log">').html(`
                    <span class="smo-log-time">${activity.post_date || 'N/A'}</span>
                    <span class="smo-log-platform">${activity.platform || 'N/A'}</span>
                    <span class="smo-log-message">New activity recorded</span>
                `);
                logs.append(log);
            });
        }
    }

    function showExportModal() {
        $('#export-modal').show();
    }

    function hideExportModal() {
        $('#export-modal').hide();
    }

    function startExport() {
        const format = $('#export-format').val();
        const dataType = $('#export-data').val();
        const dateRange = $('#date-range').val();
        const platform = $('#platform-filter').val();

        $('#export-progress').show();

        $.ajax({
            url: smoAnalytics.ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_export_analytics',
                nonce: smoAnalytics.exportNonce,
                format: format,
                data_type: dataType,
                date_range: dateRange,
                platform: platform
            },
            success: function(response) {
                if (response.success) {
                    // Trigger download
                    const link = document.createElement('a');
                    link.href = 'data:' + response.data.mime_type + ';base64,' + btoa(response.data.content);
                    link.download = response.data.filename;
                    link.click();

                    hideExportModal();
                } else {
                    showError('Export failed: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                showError('Export error: ' + error);
            },
            complete: function() {
                $('#export-progress').hide();
            }
        });
    }

    function showLoading() {
        // Add loading indicators
        $('.smo-metric-value').text('Loading...');
    }

    function hideLoading() {
        // Loading complete
    }

    function showError(message) {
        // Could show a notice to user
        alert('Error: ' + message);
    }

    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    function formatChange(change) {
        if (change > 0) {
            return '+' + change + '%';
        } else if (change < 0) {
            return change + '%';
        }
        return '0%';
    }
});