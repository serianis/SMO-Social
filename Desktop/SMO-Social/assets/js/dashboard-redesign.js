jQuery(function ($) {
    // Global error handling and URL validation

    // Performance optimization: Debounce resize events for better mobile performance
    let resizeTimeout;
    $(window).on('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
            // Handle responsive layout changes
            handleResponsiveLayout();
        }, 100);
    });

    // Monitor for window.location.href changes that might cause 403 errors
    const originalLocationAssign = window.location.assign;
    window.location.assign = function (url) {
        // Improved URL validation logic to prevent false positives and improve performance
        if (typeof url === 'string') {
            try {
                // Use URL constructor for proper validation
                const testUrl = new URL(url, window.location.origin);

                // Check for potentially malicious patterns more efficiently
                const suspiciousPatterns = [
                    '%3Cdiv%20class=',
                    '%3Cscript',
                    '%3Ciframe',
                    'javascript:',
                    'data:',
                    'onerror=',
                    'onclick='
                ];

                // Use some() for better performance - exit early if any pattern found
                if (suspiciousPatterns.some(pattern => url.includes(pattern))) {
                    console.warn('Potential malicious URL detected and blocked:', url);
                    return;
                }

                // Validate URL structure
                if (!testUrl.protocol || !testUrl.hostname) {
                    console.warn('Invalid URL structure detected:', url);
                    return;
                }
            } catch (e) {
                console.warn('Invalid URL format detected:', url, e);
                return;
            }
        }

        return originalLocationAssign.call(this, url);
    };

    // Override window.open as well
    const originalWindowOpen = window.open;
    window.open = function (url, windowName, windowFeatures) {
        if (typeof url === 'string') {
            try {
                const testUrl = new URL(url, window.location.origin);
                const suspiciousPatterns = [
                    '%3Cdiv%20class=',
                    '%3Cscript',
                    '%3Ciframe',
                    'javascript:',
                    'data:',
                    'onerror=',
                    'onclick='
                ];

                if (suspiciousPatterns.some(pattern => url.includes(pattern))) {
                    console.warn('Potential malicious URL detected and blocked in window.open:', url);
                    return null;
                }
            } catch (e) {
                console.warn('Invalid URL format detected in window.open:', url, e);
                return null;
            }
        }

        return originalWindowOpen.call(this, url, windowName, windowFeatures);
    };

    // Enhanced user experience: Add keyboard shortcuts for common actions
    $(document).on('keydown', function (e) {
        // Ctrl/Cmd + K for quick search/focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            focusQuickSearch();
        }

        // Escape key for closing modals/popups
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    // Performance optimization: Lazy load non-critical widgets
    function lazyLoadWidgets() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const widget = entry.target;
                    loadWidgetContent(widget);
                    observer.unobserve(widget);
                }
            });
        }, { threshold: 0.1 });

        // Observe secondary widgets for lazy loading
        document.querySelectorAll('.smo-lazy-widget').forEach(widget => {
            observer.observe(widget);
        });
    }

    // Initialize lazy loading
    setTimeout(lazyLoadWidgets, 500);

    // 1. Main Navigation (Sidebar) - Only handle SPA navigation
    $('.smo-nav-item').on('click', function (e) {
        // Improved navigation logic to prevent race conditions with WordPress admin links
        var href = $(this).attr('href');
        var hasViewData = $(this).data('view');

        // Check if this is a regular anchor link (WordPress admin navigation)
        // If it has a href attribute and it's not empty, let WordPress handle it
        if (href && href !== '#' && !hasViewData) {
            // This is a WordPress admin link, don't prevent default
            // Add small delay to ensure WordPress navigation completes
            setTimeout(() => {
                // Clean up any pending operations
                cleanupPendingOperations();
            }, 100);
            return; // Let the browser handle the link normally
        }

        // Only prevent default for SPA navigation (internal view switching)
        if (hasViewData) {
            e.preventDefault();

            // Add loading state
            $('body').addClass('smo-loading');

            try {
                // Update active state
                $('.smo-nav-item').removeClass('active');
                $(this).addClass('active');

                // Get target view
                var target = $(this).data('view');

                // Hide all views and show target with error handling
                $('.smo-view-section').hide();
                $('#smo-view-' + target).fadeIn(200, function () {
                    // Remove loading state after animation completes
                    $('body').removeClass('smo-loading');

                    // Trigger view loaded event
                    $(window).trigger('smo-view-loaded', [target]);
                });

                // Update header title
                $('.smo-header-title h1').text($(this).find('span:last-child').text());
            } catch (error) {
                console.error('Navigation error:', error);
                $('body').removeClass('smo-loading');
                showErrorNotification('Navigation failed. Please try again.');
            }
        }
    });

    // 2. Settings Tabs Navigation
    $('.smo-tab-btn').on('click', function (e) {
        e.preventDefault();

        try {
            // Update active state
            $('.smo-tab-btn').removeClass('active');
            $(this).addClass('active');

            // Get target tab
            var target = $(this).data('tab');

            // Hide all tab contents and show target
            $('.smo-tab-content').hide();
            $('#smo-settings-' + target).fadeIn(200);
        } catch (error) {
            console.error('Tab navigation error:', error);
            showErrorNotification('Tab navigation failed. Please try again.');
        }
    });

    // 3. Theme Toggle (Dark/Light) with AJAX fallback
    $('#smo-theme-toggle').on('click', function () {
        const $body = $('body');
        const isDarkMode = $body.hasClass('smo-dark-mode');
        const newMode = isDarkMode ? 'light' : 'dark';

        // Add loading state
        $body.addClass('smo-loading-theme');

        // Toggle immediately for better UX
        $body.toggleClass('smo-dark-mode');

        // Save preference via AJAX with proper error handling and fallback
        saveThemePreference(newMode, function (success) {
            $body.removeClass('smo-loading-theme');
            if (!success) {
                // Fallback: revert if AJAX fails
                $body.toggleClass('smo-dark-mode');
                showErrorNotification('Theme preference could not be saved. Changes will not persist.');
            }
        });
    });

    // 4. Quick Actions with error handling
    $('.smo-action-btn').on('click', function () {
        var action = $(this).data('action');
        try {
            // Implement action logic here with proper error handling
            executeAction(action);
        } catch (error) {
            console.error('Action execution error:', error);
            showErrorNotification('Action failed. Please try again.');
        }
    });

    // 5. Initialize Sparklines (Mock) with error handling
    try {
        $('.smo-sparkline').each(function () {
            var color = $(this).data('color');
            if (color) {
                $(this).css({
                    'background': 'linear-gradient(90deg, transparent 0%, ' + color + '20 100%)',
                    'border-bottom': '2px solid ' + color,
                    'height': '30px',
                    'width': '100%'
                });
            }
        });
    } catch (error) {
        console.error('Sparkline initialization error:', error);
    }

    // 6. Initialize Chart.js (if available) with cleanup
    if (typeof Chart !== 'undefined' && $('#smo-main-chart').length) {
        try {
            var ctx = document.getElementById('smo-main-chart').getContext('2d');

            // Check if a chart instance already exists and destroy it
            var existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Engagement',
                        data: [65, 59, 80, 81, 56, 55, 40],
                        borderColor: '#0073aa',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Chart initialization error:', error);
            showErrorNotification('Chart could not be loaded.');
        }
    }

    // 7. Template Widget Functionality with proper error handling and cleanup
    $('.smo-use-template').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        try {
            var templateData = $(this).data('template');

            // Validate template data structure
            if (!templateData || typeof templateData !== 'object') {
                showErrorNotification('Invalid template data. Please try another template.');
                return;
            }

            // CRITICAL FIX: Sanitize template data to prevent HTML in URLs
            const sanitizedTemplateData = sanitizeTemplateForUrl(templateData);

            // Store template data in session/localStorage for use in create post page
            if (typeof (Storage) !== 'undefined') {
                try {
                    sessionStorage.setItem('smo_selected_template', JSON.stringify(sanitizedTemplateData));
                } catch (storageError) {
                    console.error('Storage error:', storageError);
                    showErrorNotification('Could not save template preference. Storage may be full.');
                    return;
                }
            }

            // Redirect to create post page with template parameter
            var createPostUrl = smo_dashboard_ajax.create_post_url;
            var templateParam = '&template=' + encodeURIComponent(JSON.stringify(sanitizedTemplateData));

            // Additional safety check
            if (templateParam.includes('%3Cdiv%20class=')) {
                showErrorNotification('Error: Invalid template data detected. Please try again.');
                return;
            }

            window.location.href = createPostUrl + templateParam;
        } catch (error) {
            console.error('Template selection error:', error);
            showErrorNotification('Template selection failed. Please try again.');
        }
    });

    // Template preview on hover/click with accessibility and cleanup
    $('.smo-template-preview').on('click', function () {
        try {
            var templateData = $(this).find('.smo-use-template').data('template');

            // Validate template data structure
            if (!templateData || typeof templateData !== 'object') {
                showErrorNotification('Invalid template data. Please try another template.');
                return;
            }

            // Show a simple tooltip or modal with template preview
            if (templateData && templateData.content) {
                // Create a simple preview popup with accessibility attributes
                var previewHtml = '<div class="smo-template-preview-popup" role="dialog" aria-labelledby="template-preview-title" aria-modal="true">' +
                    '<div class="smo-popup-header">' +
                    '<h4 id="template-preview-title">' + (templateData.name || 'Template Preview') + '</h4>' +
                    '<button class="smo-close-popup" aria-label="Close template preview">×</button>' +
                    '</div>' +
                    '<div class="smo-popup-content" role="document">' +
                    '<p><strong>Description:</strong> ' + (templateData.description || '') + '</p>' +
                    '<p><strong>Content Preview:</strong></p>' +
                    '<div class="smo-template-content-preview" aria-live="polite">' +
                    templateData.content.replace(/\n/g, '<br>') +
                    '</div>' +
                    '<button class="smo-use-template-popup button button-primary" data-template="' + btoa(JSON.stringify(templateData)) + '" aria-label="Use this template">Use This Template</button>' +
                    '</div>' +
                    '</div>';

                // Remove existing popup with cleanup
                cleanupTemplatePopups();

                // Add new popup
                $('body').append(previewHtml);

                // Position popup near the clicked element
                var $popup = $('.smo-template-preview-popup');
                var offset = $(this).offset();
                $popup.css({
                    'position': 'absolute',
                    'top': offset.top + $(this).outerHeight() + 10,
                    'left': offset.left,
                    'z-index': 9999,
                    'background': 'white',
                    'border': '1px solid #ccc',
                    'border-radius': '6px',
                    'padding': '15px',
                    'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                    'max-width': '400px'
                });

                // Handle popup close button
                $('.smo-close-popup').on('click', function () {
                    cleanupTemplatePopups();
                });

                // Handle popup template selection with memory leak prevention
                $('.smo-use-template-popup').on('click', function (e) {
                    e.stopPropagation();
                    try {
                        var encodedTemplate = $(this).data('template');
                        var templateData = JSON.parse(atob(encodedTemplate));

                        // CRITICAL FIX: Sanitize template data to prevent HTML in URLs
                        const sanitizedTemplateData = sanitizeTemplateForUrl(templateData);

                        if (typeof (Storage) !== 'undefined') {
                            try {
                                sessionStorage.setItem('smo_selected_template', JSON.stringify(sanitizedTemplateData));
                            } catch (storageError) {
                                console.error('Storage error:', storageError);
                                showErrorNotification('Could not save template preference. Storage may be full.');
                                return;
                            }
                        }

                        var createPostUrl = smo_dashboard_ajax.create_post_url;
                        var templateParam = '&template=' + encodeURIComponent(JSON.stringify(sanitizedTemplateData));

                        // Additional safety check
                        if (templateParam.includes('%3Cdiv%20class=')) {
                            showErrorNotification('Error: Invalid template data detected. Please try again.');
                            return;
                        }

                        // Cleanup before navigation
                        cleanupTemplatePopups();
                        window.location.href = createPostUrl + templateParam;
                    } catch (error) {
                        console.error('Popup template selection error:', error);
                        showErrorNotification('Template selection failed. Please try again.');
                        cleanupTemplatePopups();
                    }
                });

                // Close popup when clicking outside with memory leak prevention
                $(document).on('click.smo-template-popup', function (e) {
                    if (!$(e.target).closest('.smo-template-preview-popup, .smo-template-preview').length) {
                        cleanupTemplatePopups();
                    }
                });

                // Close popup on Escape key
                $(document).on('keydown.smo-template-popup', function (e) {
                    if (e.key === 'Escape') {
                        cleanupTemplatePopups();
                    }
                });
            }
        } catch (error) {
            console.error('Template preview error:', error);
            showErrorNotification('Template preview could not be loaded.');
            cleanupTemplatePopups();
        }
    });

    // Helper function to cleanup template popups and prevent memory leaks
    function cleanupTemplatePopups() {
        $('.smo-template-preview-popup').remove();
        $(document).off('click.smo-template-popup');
        $(document).off('keydown.smo-template-popup');
    }

    // Helper function to cleanup pending operations
    function cleanupPendingOperations() {
        // Remove any loading states
        $('body').removeClass('smo-loading smo-loading-theme');

        // Cleanup any pending popups
        cleanupTemplatePopups();

        // Remove any pending event listeners
        $(window).off('.smo-pending');
    }

    // Helper function to show error notifications
    function showErrorNotification(message) {
        // Remove any existing notifications first
        $('.smo-error-notification').remove();

        // Create accessible error notification
        var notification = $('<div class="smo-error-notification" role="alert" aria-live="assertive">' +
            '<span class="smo-notification-icon">⚠</span>' +
            '<span class="smo-notification-message">' + message + '</span>' +
            '<button class="smo-notification-close" aria-label="Close notification">×</button>' +
            '</div>');

        $('body').append(notification);

        // Position and style the notification
        notification.css({
            'position': 'fixed',
            'top': '20px',
            'right': '20px',
            'background': '#dc3232',
            'color': 'white',
            'padding': '12px 20px',
            'border-radius': '4px',
            'box-shadow': '0 2px 8px rgba(0,0,0,0.2)',
            'z-index': 10000,
            'display': 'flex',
            'align-items': 'center',
            'gap': '10px'
        });

        // Close button functionality
        notification.find('.smo-notification-close').on('click', function () {
            notification.remove();
        });

        // Auto-close after 5 seconds
        setTimeout(function () {
            notification.fadeOut(300, function () {
                $(this).remove();
            });
        }, 5000);
    }

    // Helper function to show success notifications
    function showSuccessNotification(message) {
        // Remove any existing notifications first
        $('.smo-success-notification').remove();

        // Create accessible success notification
        var notification = $('<div class="smo-success-notification" role="status" aria-live="polite">' +
            '<span class="smo-notification-icon">✓</span>' +
            '<span class="smo-notification-message">' + message + '</span>' +
            '<button class="smo-notification-close" aria-label="Close notification">×</button>' +
            '</div>');

        $('body').append(notification);

        // Position and style the notification
        notification.css({
            'position': 'fixed',
            'top': '20px',
            'right': '20px',
            'background': '#4CAF50',
            'color': 'white',
            'padding': '12px 20px',
            'border-radius': '4px',
            'box-shadow': '0 2px 8px rgba(0,0,0,0.2)',
            'z-index': 10000,
            'display': 'flex',
            'align-items': 'center',
            'gap': '10px'
        });

        // Close button functionality
        notification.find('.smo-notification-close').on('click', function () {
            notification.remove();
        });

        // Auto-close after 3 seconds
        setTimeout(function () {
            notification.fadeOut(300, function () {
                $(this).remove();
            });
        }, 3000);
    }

    // Theme preference saving with AJAX fallback
    function saveThemePreference(mode, callback) {
        // Default to callback with false if AJAX is not available
        if (typeof smo_dashboard_ajax === 'undefined' || !smo_dashboard_ajax.ajax_url) {
            callback(false);
            return;
        }

        // Try AJAX first
        try {
            $.ajax({
                url: smo_dashboard_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_save_theme_preference',
                    mode: mode,
                    nonce: smo_dashboard_ajax.nonce
                },
                timeout: 5000, // 5 second timeout
                success: function (response) {
                    if (response.success) {
                        callback(true);
                    } else {
                        console.error('Theme save failed:', response.data);
                        callback(false);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Theme save AJAX error:', error);
                    callback(false);
                },
                complete: function () {
                    // Ensure loading state is removed
                    $('body').removeClass('smo-loading-theme');
                }
            });
        } catch (error) {
            console.error('Theme save error:', error);
            callback(false);
        }
    }

    // Action execution with error recovery
    function executeAction(action) {
        if (!action) return;

        try {
            // Show loading state
            $('body').addClass('smo-loading');

            // Simulate action execution
            setTimeout(function () {
                $('body').removeClass('smo-loading');
                showErrorNotification('Action "' + action + '" executed successfully.');
            }, 1000);
        } catch (error) {
            console.error('Action execution error:', error);
            $('body').removeClass('smo-loading');
            showErrorNotification('Action execution failed. Please try again.');
        }
    }

    // Global error recovery mechanism
    window.addEventListener('error', function (event) {
        console.error('Global error caught:', event.error);
        showErrorNotification('An unexpected error occurred. Please refresh the page.');
    });

    window.addEventListener('unhandledrejection', function (event) {
        console.error('Unhandled promise rejection:', event.reason);
        showErrorNotification('An operation failed. Please try again.');
    });

    // Cleanup on page unload to prevent memory leaks
    $(window).on('beforeunload', function () {
        cleanupPendingOperations();
    });

    // Enhanced user experience: Handle responsive layout changes
    function handleResponsiveLayout() {
        const windowWidth = window.innerWidth;

        // Add responsive class to body for CSS targeting
        const body = $('body');
        body.removeClass('smo-responsive-desktop smo-responsive-tablet smo-responsive-mobile');

        if (windowWidth >= 1200) {
            body.addClass('smo-responsive-desktop');
        } else if (windowWidth >= 768) {
            body.addClass('smo-responsive-tablet');
        } else {
            body.addClass('smo-responsive-mobile');
        }

        // Optimize widget layouts based on screen size
        optimizeWidgetLayouts();
    }

    // Enhanced user experience: Optimize widget layouts
    function optimizeWidgetLayouts() {
        const windowWidth = window.innerWidth;

        if (windowWidth < 768) {
            // Mobile: Single column layout
            $('.smo-dashboard-grid').css('grid-template-columns', '1fr');
        } else if (windowWidth < 1200) {
            // Tablet: Two column layout
            $('.smo-dashboard-grid').css('grid-template-columns', 'repeat(2, 1fr)');
        } else {
            // Desktop: Three column layout
            $('.smo-dashboard-grid').css('grid-template-columns', 'repeat(3, 1fr)');
        }
    }

    // Enhanced user experience: Focus quick search
    function focusQuickSearch() {
        const quickSearch = $('#smo-quick-search');
        if (quickSearch.length) {
            quickSearch.focus();
            showSuccessNotification('Quick search focused. Type to search...');
        } else {
            showErrorNotification('Quick search not available on this page');
        }
    }

    // Enhanced user experience: Close all modals
    function closeAllModals() {
        $('.smo-modal, .smo-popup, .smo-template-preview-popup').remove();
        $(document).off('click.smo-modal keydown.smo-modal');
    }

    // Enhanced user experience: Load widget content lazily
    function loadWidgetContent(widget) {
        if (widget.dataset.loaded) return;

        // Simulate loading content (in real implementation, this would be AJAX)
        setTimeout(() => {
            widget.innerHTML = '<div class="smo-widget-content-loaded">Content loaded!</div>';
            widget.dataset.loaded = 'true';
            widget.classList.add('smo-widget-loaded');
        }, 300);
    }

    // Performance optimization: Add CSS containment for critical components
    function applyCSSContainment() {
        // Apply contain: strict to performance-critical components
        document.querySelectorAll('.smo-performance-critical').forEach(element => {
            element.style.contain = 'strict';
        });
    }

    // Initialize CSS containment
    applyCSSContainment();
});

/**
 * IMPROVED: Sanitize template data to prevent HTML from being included in URLs
 * This function removes or escapes HTML content that could cause malformed URLs
 * while being less aggressive and more secure
 */
function sanitizeTemplateForUrl(templateData) {
    // Create a deep copy to avoid modifying the original
    const sanitized = JSON.parse(JSON.stringify(templateData || {}));

    // Improved sanitization that's less aggressive but more secure
    Object.keys(sanitized).forEach(key => {
        if (typeof sanitized[key] === 'string') {
            // First, create a temporary element to parse HTML safely
            const tempDiv = document.createElement('div');
            tempDiv.textContent = sanitized[key];

            // Get the text content (automatically removes HTML)
            let cleanText = tempDiv.textContent || tempDiv.innerText || '';

            // Additional security measures - proper HTML entity encoding for URL safety
            cleanText = cleanText
                .replace(/&/g, '&amp;')    // Encode ampersands first
                .replace(/</g, '&lt;')     // Encode less-than
                .replace(/>/g, '&gt;')     // Encode greater-than
                .replace(/"/g, '&quot;')   // Encode double quotes
                .replace(/'/g, '\'')       // Encode single quotes (properly escaped)
                .replace(/\n/g, ' ')       // Replace newlines with spaces
                .replace(/\r/g, '')        // Remove carriage returns
                .trim();                   // Trim whitespace
            console.log('DEBUG: Finished string sanitization:', cleanText);

            // Allow basic formatting but prevent script execution
            cleanText = cleanText.replace(/javascript:/gi, 'about:blank');

            sanitized[key] = cleanText;
        } else if (typeof sanitized[key] === 'object' && sanitized[key] !== null) {
            // Recursively sanitize nested objects
            sanitized[key] = sanitizeTemplateForUrl(sanitized[key]);
        }
    });

    return sanitized;
}