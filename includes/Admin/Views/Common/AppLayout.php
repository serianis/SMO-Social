<?php
/**
 * SMO Social - Unified App Layout
 * 
 * Provides a consistent wrapper and styling structure for all admin views.
 * Ensures visual uniformity across all menu tabs with:
 * - Modern gradient-based UI
 * - Responsive design
 * - Smooth animations
 * - Theme toggle (dark/light mode)
 * 
 * @package SMO_Social
 * @subpackage Admin/Views/Common
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views\Common;

class AppLayout
{
    /**
     * CSS classes for different page types
     */
    private static $page_classes = [
        'dashboard' => 'smo-page-dashboard',
        'posts' => 'smo-page-posts',
        'calendar' => 'smo-page-calendar',
        'analytics' => 'smo-page-analytics',
        'platforms' => 'smo-page-platforms',
        'settings' => 'smo-page-settings',
        'content-import' => 'smo-page-content-import',
        'content-organizer' => 'smo-page-content-organizer',
        'reports' => 'smo-page-reports',
        'users' => 'smo-page-users',
        'create' => 'smo-page-create',
        'tools' => 'smo-page-tools',
        'templates' => 'smo-page-templates',
        'media' => 'smo-page-media',
        'integrations' => 'smo-page-integrations',
        'notifications' => 'smo-page-notifications',
        'maintenance' => 'smo-page-maintenance',
    ];

    /**
     * Render the start of the unified layout
     * 
     * @param string $page_slug Unique identifier for the page
     * @param string $page_title Display title for the page
     * @param array $options Additional rendering options
     */
    public static function render_start($page_slug = 'dashboard', $page_title = 'Dashboard', $options = [])
    {
        // Get page-specific class
        $page_class = self::$page_classes[$page_slug] ?? 'smo-page-default';
        
        // Merge default options
        $defaults = [
            'show_header' => false, // Pages typically render their own gradient header
            'show_theme_toggle' => true,
            'show_create_button' => ($page_slug !== 'create'),
            'wrapper_class' => '',
        ];
        $options = array_merge($defaults, $options);
        
        $wrapper_classes = [
            'wrap',
            'smo-content-import-wrap',
            'smo-unified-layout',
            $page_class,
            $options['wrapper_class']
        ];
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_filter($wrapper_classes))); ?>" data-page="<?php echo esc_attr($page_slug); ?>">
            
            <?php if ($options['show_header']): ?>
            <!-- Optional Layout Header (most pages use their own gradient header) -->
            <header class="smo-layout-header">
                <div class="smo-header-title">
                    <h1><?php echo esc_html($page_title); ?></h1>
                </div>
                <div class="smo-header-controls">
                    <?php if ($options['show_theme_toggle']): ?>
                    <button class="smo-btn smo-btn-icon" id="smo-theme-toggle" title="<?php \esc_attr_e('Toggle Dark Mode', 'smo-social'); ?>">
                        <span class="dashicons dashicons-admin-appearance"></span>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($options['show_create_button']): ?>
                    <a href="<?php echo admin_url('admin.php?page=smo-social-create'); ?>" class="smo-btn smo-btn-primary">
                        <span class="dashicons dashicons-plus"></span>
                        <?php \esc_html_e('New Post', 'smo-social'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </header>
            <?php endif; ?>

            <div class="smo-main-content">
                <?php
    }

    /**
     * Render the end of the unified layout
     */
    public static function render_end()
    {
        ?>
            </div><!-- .smo-main-content -->
        </div><!-- .smo-unified-layout -->
        
        <script>
        (function($) {
            'use strict';
            
            // SMO Social Unified Layout JavaScript
            $(document).ready(function() {
                
                // =============================================
                // Theme Toggle Functionality
                // =============================================
                function initThemeToggle() {
                    const savedTheme = localStorage.getItem('smo-theme') || 'light';
                    $('html').attr('data-theme', savedTheme);
                    updateThemeIcon(savedTheme);
                    
                    $(document).on('click', '#smo-theme-toggle', function() {
                        const currentTheme = $('html').attr('data-theme') || 'light';
                        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                        $('html').attr('data-theme', newTheme);
                        localStorage.setItem('smo-theme', newTheme);
                        updateThemeIcon(newTheme);
                    });
                }
                
                function updateThemeIcon(theme) {
                    const $icon = $('#smo-theme-toggle .dashicons');
                    if (theme === 'dark') {
                        $icon.removeClass('dashicons-admin-appearance').addClass('dashicons-lightbulb');
                    } else {
                        $icon.removeClass('dashicons-lightbulb').addClass('dashicons-admin-appearance');
                    }
                }
                
                // =============================================
                // Tab Navigation (if present)
                // =============================================
                function initTabNavigation() {
                    $('.smo-tabs-nav .smo-tab-link').on('click', function(e) {
                        e.preventDefault();
                        
                        const $this = $(this);
                        const tabId = $this.data('tab');
                        
                        // Update active tab
                        $this.closest('.smo-tabs-nav').find('.smo-tab-link').removeClass('active');
                        $this.addClass('active');
                        
                        // Show corresponding panel
                        const $wrapper = $this.closest('.smo-tabs-wrapper, .smo-card');
                        $wrapper.find('.smo-tab-panel').removeClass('active');
                        $wrapper.find('#' + tabId + '-panel').addClass('active');
                        
                        // Store in URL hash for persistence
                        if (history.pushState) {
                            history.pushState(null, null, '#' + tabId);
                        }
                    });
                    
                    // Restore tab from URL hash
                    const hash = window.location.hash.substring(1);
                    if (hash) {
                        const $tab = $('.smo-tab-link[data-tab="' + hash + '"]');
                        if ($tab.length) {
                            $tab.trigger('click');
                        }
                    }
                }
                
                // =============================================
                // Smooth Scroll Animations
                // =============================================
                function initScrollAnimations() {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('smo-animate-visible');
                            }
                        });
                    }, { threshold: 0.1 });
                    
                    document.querySelectorAll('.smo-card, .smo-stat-card').forEach(el => {
                        observer.observe(el);
                    });
                }
                
                // =============================================
                // Notification System
                // =============================================
                window.smoShowNotification = function(message, type = 'info', duration = 3000) {
                    const $notification = $('<div class="smo-notification smo-notification-' + type + '">' + message + '</div>');
                    $('body').append($notification);
                    
                    setTimeout(() => {
                        $notification.addClass('smo-notification-show');
                    }, 10);
                    
                    setTimeout(() => {
                        $notification.removeClass('smo-notification-show');
                        setTimeout(() => $notification.remove(), 300);
                    }, duration);
                };
                
                // =============================================
                // Initialize All Components
                // =============================================
                initThemeToggle();
                initTabNavigation();
                
                // Only init scroll animations if IntersectionObserver is supported
                if ('IntersectionObserver' in window) {
                    initScrollAnimations();
                }
                
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Render a standardized gradient header section
     * 
     * @param array $config Header configuration
     */
    public static function render_header($config = [])
    {
        $defaults = [
            'icon' => '📋',
            'title' => 'Page Title',
            'subtitle' => '',
            'actions' => [] // Array of action buttons
        ];
        $config = array_merge($defaults, $config);
        ?>
        <div class="smo-import-header">
            <div class="smo-header-content">
                <h1 class="smo-page-title">
                    <span class="smo-icon"><?php echo $config['icon']; ?></span>
                    <?php echo esc_html($config['title']); ?>
                </h1>
                <?php if (!empty($config['subtitle'])): ?>
                <p class="smo-page-subtitle">
                    <?php echo esc_html($config['subtitle']); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php if (!empty($config['actions'])): ?>
            <div class="smo-header-actions">
                <?php foreach ($config['actions'] as $action): ?>
                    <?php if (isset($action['href'])): ?>
                    <a href="<?php echo esc_url($action['href']); ?>" 
                       class="smo-btn <?php echo esc_attr($action['class'] ?? 'smo-btn-secondary'); ?>"
                       <?php echo isset($action['id']) ? 'id="' . esc_attr($action['id']) . '"' : ''; ?>>
                        <?php if (isset($action['icon'])): ?>
                        <span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>"></span>
                        <?php endif; ?>
                        <?php echo esc_html($action['label']); ?>
                    </a>
                    <?php else: ?>
                    <button type="button" 
                            class="smo-btn <?php echo esc_attr($action['class'] ?? 'smo-btn-secondary'); ?>"
                            <?php echo isset($action['id']) ? 'id="' . esc_attr($action['id']) . '"' : ''; ?>>
                        <?php if (isset($action['icon'])): ?>
                        <span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>"></span>
                        <?php endif; ?>
                        <?php echo esc_html($action['label']); ?>
                    </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render a standardized stats dashboard section
     * 
     * @param array $stats Array of stat configurations
     */
    public static function render_stats_dashboard($stats = [])
    {
        if (empty($stats)) {
            return;
        }
        ?>
        <div class="smo-import-dashboard">
            <div class="smo-stats-grid">
                <?php 
                $gradient_index = 1;
                foreach ($stats as $stat): 
                    $gradient_class = 'smo-stat-gradient-' . (($gradient_index - 1) % 4 + 1);
                ?>
                <div class="smo-stat-card <?php echo esc_attr($gradient_class); ?>">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-<?php echo esc_attr($stat['icon'] ?? 'chart-bar'); ?>"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number" <?php echo isset($stat['id']) ? 'id="' . esc_attr($stat['id']) . '"' : ''; ?>>
                            <?php echo esc_html($stat['value'] ?? '-'); ?>
                        </h3>
                        <p class="smo-stat-label"><?php echo esc_html($stat['label'] ?? 'Stat'); ?></p>
                        <?php if (isset($stat['trend'])): ?>
                        <span class="smo-stat-trend"><?php echo esc_html($stat['trend']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                    $gradient_index++;
                endforeach; 
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render a standardized quick actions bar
     * 
     * @param array $actions Array of quick action configurations
     */
    public static function render_quick_actions($actions = [])
    {
        if (empty($actions)) {
            return;
        }
        ?>
        <div class="smo-quick-actions">
            <?php foreach ($actions as $action): ?>
            <button type="button" 
                    class="smo-quick-action-btn"
                    <?php echo isset($action['id']) ? 'id="' . esc_attr($action['id']) . '"' : ''; ?>
                    <?php echo isset($action['onclick']) ? 'onclick="' . esc_attr($action['onclick']) . '"' : ''; ?>>
                <span class="dashicons dashicons-<?php echo esc_attr($action['icon'] ?? 'admin-generic'); ?>"></span>
                <span><?php echo esc_html($action['label']); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
