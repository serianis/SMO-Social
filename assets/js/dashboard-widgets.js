/**
 * SMO Social Dashboard Widgets JavaScript
 *
 * Handles drag-and-drop functionality, widget management, and AJAX interactions
 */

(function($) {
    'use strict';

    // Dashboard Widgets Manager
    var SMODashboardWidgets = {
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.loadWidgetLibrary();
        },

        bindEvents: function() {
            var self = this;

            // Add widget button
            $(document).on('click', '#smo-add-widget-btn, #smo-add-first-widget-btn', function(e) {
                e.preventDefault();
                self.showWidgetLibrary();
            });

            // Dashboard settings
            $(document).on('click', '#smo-dashboard-settings-btn', function(e) {
                e.preventDefault();
                self.showDashboardSettings();
            });

            // Save layout
            $(document).on('click', '#smo-save-layout-btn', function(e) {
                e.preventDefault();
                self.saveLayout();
            });

            // Reset dashboard
            $(document).on('click', '#smo-reset-dashboard-btn', function(e) {
                e.preventDefault();
                if (confirm(smo_dashboard.strings.confirm_reset)) {
                    self.resetDashboard();
                }
            });

            // Widget removal
            $(document).on('click', '.smo-widget-remove-btn', function(e) {
                e.preventDefault();
                var widgetId = $(this).data('widget-id');
                if (confirm(smo_dashboard.strings.confirm_remove)) {
                    self.removeWidget(widgetId);
                }
            });

            // Widget settings
            $(document).on('click', '.smo-widget-settings-btn', function(e) {
                e.preventDefault();
                var widgetId = $(this).data('widget-id');
                self.showWidgetSettings(widgetId);
            });

            // Drill-down toggle
            $(document).on('click', '.smo-drilldown-toggle', function(e) {
                e.preventDefault();
                var widgetId = $(this).data('widget-id');
                self.toggleDrilldown(widgetId);
            });

            // Timeframe and metric changes
            $(document).on('change', '.smo-timeframe-select, .smo-metric-select', function() {
                var widgetId = $(this).data('widget-id');
                self.updateWidgetData(widgetId);
            });

            // Modal close
            $(document).on('click', '.smo-modal-close, .smo-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Dashboard settings form
            $(document).on('submit', '#smo-dashboard-settings-form', function(e) {
                e.preventDefault();
                self.saveDashboardSettings($(this));
            });
        },

        initSortable: function() {
            var self = this;

            $('#smo-dashboard-grid').sortable({
                handle: '.smo-widget-drag-handle',
                placeholder: 'smo-widget-placeholder',
                tolerance: 'pointer',
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                },
                update: function(event, ui) {
                    // Layout will be saved when save button is clicked
                }
            });

            $('.smo-dashboard-row').sortable({
                connectWith: '.smo-dashboard-row',
                handle: '.smo-widget-drag-handle',
                placeholder: 'smo-widget-placeholder',
                tolerance: 'pointer',
                start: function(event, ui) {
                    ui.placeholder.height(ui.item.height());
                }
            });
        },

        showWidgetLibrary: function() {
            var self = this;

            $.ajax({
                url: smo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_get_widget_library',
                    nonce: smo_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderWidgetLibrary(response.data);
                        $('#smo-widget-library-modal').show();
                    } else {
                        alert('Error loading widget library');
                    }
                },
                error: function() {
                    alert('Error loading widget library');
                }
            });
        },

        renderWidgetLibrary: function(data) {
            var html = '';

            $.each(data, function(categorySlug, category) {
                html += '<div class="smo-widget-category" data-category="' + categorySlug + '">';
                html += '<h4>' + category.name + '</h4>';
                html += '<div class="smo-widget-grid">';

                $.each(category.widgets, function(index, widget) {
                    html += '<div class="smo-widget-item" data-widget-id="' + widget.id + '">';
                    html += '<div class="smo-widget-icon">' + widget.icon + '</div>';
                    html += '<div class="smo-widget-info">';
                    html += '<h5>' + widget.name + '</h5>';
                    html += '<p>' + widget.description + '</p>';
                    html += '</div>';
                    html += '<button class="button button-small smo-add-widget-to-dashboard" data-widget-id="' + widget.id + '">Add</button>';
                    html += '</div>';
                });

                html += '</div>';
                html += '</div>';
            });

            $('#smo-widget-library').html(html);

            // Bind add widget events
            $('.smo-add-widget-to-dashboard').on('click', function() {
                var widgetId = $(this).data('widget-id');
                SMODashboardWidgets.addWidgetToDashboard(widgetId);
            });
        },

        addWidgetToDashboard: function(widgetId) {
            var self = this;

            $.ajax({
                url: smo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_add_widget_to_dashboard',
                    widget_id: widgetId,
                    nonce: smo_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Add widget to the first row
                        var firstRow = $('.smo-dashboard-row').first();
                        if (firstRow.length === 0) {
                            $('#smo-dashboard-grid').append('<div class="smo-dashboard-row"></div>');
                            firstRow = $('.smo-dashboard-row').first();
                        }

                        firstRow.append(response.data.html);
                        self.closeModal();
                        self.showNotification(response.data.message, 'success');

                        // Reinitialize sortable
                        self.initSortable();
                    } else {
                        alert(response.data.message || 'Error adding widget');
                    }
                },
                error: function() {
                    alert('Error adding widget');
                }
            });
        },

        removeWidget: function(widgetId) {
            var self = this;

            $.ajax({
                url: smo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_remove_widget_from_dashboard',
                    widget_id: widgetId,
                    nonce: smo_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('[data-widget-id="' + widgetId + '"]').closest('.smo-dashboard-widget-wrapper').remove();
                        self.showNotification('Widget removed successfully', 'success');
                    } else {
                        alert(response.data.message || 'Error removing widget');
                    }
                },
                error: function() {
                    alert('Error removing widget');
                }
            });
        },

        saveLayout: function() {
            var self = this;
            var layout = [];

            $('.smo-dashboard-row').each(function(rowIndex) {
                layout[rowIndex] = [];
                $(this).find('.smo-dashboard-widget-wrapper').each(function() {
                    var widgetId = $(this).data('widget-id');
                    var size = $(this).find('.smo-widget-size-medium').length ? 'medium' :
                               $(this).find('.smo-widget-size-large').length ? 'large' : 'small';
                    layout[rowIndex].push({
                        id: widgetId,
                        size: size
                    });
                });
            });

            $('#smo-save-layout-btn').prop('disabled', true).text(smo_dashboard.strings.saving);

            $.ajax({
                url: smo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_save_dashboard_layout',
                    layout: JSON.stringify(layout),
                    nonce: smo_dashboard.nonce
                },
                success: function(response) {
                    $('#smo-save-layout-btn').prop('disabled', false).text(smo_dashboard.strings.save_layout);
                    if (response.success) {
                        self.showNotification(smo_dashboard.strings.saved, 'success');
                    } else {
                        self.showNotification(smo_dashboard.strings.error, 'error');
                    }
                },
                error: function() {
                    $('#smo-save-layout-btn').prop('disabled', false).text(smo_dashboard.strings.save_layout);
                    self.showNotification(smo_dashboard.strings.error, 'error');
                }
            });
        },

        updateWidgetData: function(widgetId) {
            var self = this;
            var widget = $('[data-widget-id="' + widgetId + '"]');
            var timeframe = widget.find('.smo-timeframe-select').val();
            var metric = widget.find('.smo-metric-select').val();

            var settings = {
                timeframe: timeframe,
                metric: metric
            };

            $.ajax({
                url: smo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_get_widget_data',
                    widget_id: widgetId,
                    settings: JSON.stringify(settings),
                    nonce: smo_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update chart data
                        if (response.data.chart) {
                            var chartCanvas = widget.find('canvas');
                            if (chartCanvas.length && window.Chart) {
                                var chart = Chart.getChart(chartCanvas[0]);
                                if (chart) {
                                    chart.data = response.data.chart;
                                    chart.update();
                                }
                            }
                        }

                        // Update drilldown data
                        if (response.data.drilldown) {
                            var drilldownContent = widget.find('.smo-drilldown-content');
                            if (drilldownContent.length) {
                                drilldownContent.html(self.renderDrilldownTable(response.data.drilldown));
                            }
                        }

                        // Update summary stats
                        if (response.data.summary) {
                            var summaryContainer = widget.find('.smo-chart-summary');
                            if (summaryContainer.length) {
                                summaryContainer.html(self.renderSummaryStats(response.data.summary));
                            }
                        }
                    }
                }
            });
        },

        toggleDrilldown: function(widgetId) {
            var widget = $('[data-widget-id="' + widgetId + '"]');
            var drilldown = widget.find('.smo-drilldown-details');

            if (drilldown.is(':visible')) {
                drilldown.hide();
            } else {
                drilldown.show();
            }
        },

        renderDrilldownTable: function(data) {
            var html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th>Date</th><th>Posts</th><th>Engagement</th><th>Reach</th><th>Platforms</th>';
            html += '</tr></thead><tbody>';

            $.each(data, function(index, item) {
                html += '<tr>';
                html += '<td>' + item.date + '</td>';
                html += '<td>' + item.posts + '</td>';
                html += '<td>' + item.engagement + '</td>';
                html += '<td>' + item.reach + '</td>';
                html += '<td>' + item.platforms.join(', ') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        },

        renderSummaryStats: function(summary) {
            var html = '';
            html += '<div class="smo-summary-stat"><span class="smo-summary-label">Total</span><span class="smo-summary-value">' + summary.total + '</span></div>';
            html += '<div class="smo-summary-stat"><span class="smo-summary-label">Average</span><span class="smo-summary-value">' + summary.average + '</span></div>';
            html += '<div class="smo-summary-stat"><span class="smo-summary-label">Peak</span><span class="smo-summary-value">' + summary.peak + '</span></div>';
            return html;
        },

        showDashboardSettings: function() {
            $('#smo-dashboard-settings-modal').show();
        },

        saveDashboardSettings: function(form) {
            var self = this;
            var formData = form.serialize();

            $.ajax({
                url: smo_dashboard.ajax_url,
                type: 'POST',
                data: formData + '&action=smo_save_dashboard_settings&nonce=' + smo_dashboard.nonce,
                success: function(response) {
                    if (response.success) {
                        self.closeModal();
                        self.showNotification('Dashboard settings saved', 'success');
                        location.reload(); // Reload to apply layout changes
                    } else {
                        alert('Error saving settings');
                    }
                },
                error: function() {
                    alert('Error saving settings');
                }
            });
        },

        resetDashboard: function() {
            var self = this;

            $.ajax({
                url: smo_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_reset_widget_config',
                    nonce: smo_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error resetting dashboard');
                    }
                },
                error: function() {
                    alert('Error resetting dashboard');
                }
            });
        },

        loadWidgetLibrary: function() {
            // Widget library is loaded on demand when modal is opened
        },

        closeModal: function() {
            $('.smo-modal').hide();
        },

        showNotification: function(message, type) {
            var notification = $('<div class="smo-notification smo-notification-' + type + '">' + message + '</div>');
            $('body').append(notification);

            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SMODashboardWidgets.init();
    });

})(jQuery);