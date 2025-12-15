<?php
namespace SMO_Social\Admin\Views;

class EnhancedDashboard
{
    /**
     * Get count of video posts
     * Uses prepared statements for security and transient caching for performance
     */
    public static function get_video_posts_count()
    {
        // Check cache first
        $cache_key = 'smo_video_posts_count';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (int) $cached;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return 0;
        }

        // Use prepared statement for security
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}smo_scheduled_posts WHERE post_type = %s AND status IN (%s, %s)",
            'video',
            'published',
            'scheduled'
        ));

        $result = $count ? (int) $count : 0;
        
        // Cache for 5 minutes
        set_transient($cache_key, $result, 300); // Cache for 5 minutes
        
        return $result;
    }

    /**
     * Get recent video views (mock data for now)
     */
    public static function get_recent_video_views()
    {
        // Mock data - in real implementation, this would come from analytics
        return rand(50, 200);
    }

    /**
     * Get best posting times
     */
    public static function get_best_posting_times()
    {
        // Mock data - in real implementation, this would use AI predictions
        return array(
            'twitter' => '9:00 AM',
            'facebook' => '1:00 PM',
            'linkedin' => '8:00 AM',
            'instagram' => '6:00 PM',
            'youtube' => '2:00 PM',
            'tiktok' => '7:00 PM'
        );
    }

    /**
     * Get popular templates
     */
    public static function get_popular_templates()
    {
        // Mock data - in real implementation, this would come from database
        return array(
            array(
                'id' => 1,
                'name' => 'Product Launch',
                'description' => 'Announce new products',
                'icon' => '🚀',
                'content' => 'Exciting news! We\'re launching...'
            ),
            array(
                'id' => 2,
                'name' => 'Blog Promotion',
                'description' => 'Share blog content',
                'icon' => '📝',
                'content' => 'Check out our latest blog post...'
            ),
            array(
                'id' => 3,
                'name' => 'Behind the Scenes',
                'description' => 'Show company culture',
                'icon' => '👥',
                'content' => 'Take a look behind the scenes...'
            )
        );
    }

    /**
     * Get templates count
     */
    public static function get_templates_count()
    {
        // Mock data - in real implementation, this would count from database
        return 15;
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }

        wp_enqueue_style(
            'smo-content-import-enhanced',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-content-import-enhanced.css',
            array('smo-social-admin'),
            SMO_SOCIAL_VERSION
        );
    }

    public static function render($data = array())
    {
        // Default values
        $stats = array(
            'total_reach' => '0',
            'engagement' => '0',
            'scheduled' => 0,
            'response_time' => '-'
        );

        // Map real data if available
        if (!empty($data)) {
            if (isset($data['scheduled_posts'])) {
                $stats['scheduled'] = $data['scheduled_posts'];
            }
            // Add other mappings as needed
        }

        // Load feature managers
        if (file_exists(dirname(__DIR__, 2) . '/Features/BestTimeManager.php')) {
            require_once dirname(__DIR__, 2) . '/Features/BestTimeManager.php';
        }
        if (file_exists(dirname(__DIR__, 2) . '/Features/URLShortener.php')) {
            require_once dirname(__DIR__, 2) . '/Features/URLShortener.php';
        }
        if (file_exists(dirname(__DIR__, 2) . '/Features/NetworkGroupingsManager.php')) {
            require_once dirname(__DIR__, 2) . '/Features/NetworkGroupingsManager.php';
        }
        if (file_exists(dirname(__DIR__, 2) . '/Features/PostTemplatesManager.php')) {
            require_once dirname(__DIR__, 2) . '/Features/PostTemplatesManager.php';
        }
        if (file_exists(dirname(__DIR__, 2) . '/WhiteLabel/BrandingManager.php')) {
            require_once dirname(__DIR__, 2) . '/WhiteLabel/BrandingManager.php';
        }
        if (file_exists(dirname(__DIR__, 2) . '/Collaboration/UserManager.php')) {
            require_once dirname(__DIR__, 2) . '/Collaboration/UserManager.php';
        }
        if (file_exists(dirname(__DIR__, 2) . '/Collaboration/InternalNotesManager.php')) {
            require_once dirname(__DIR__, 2) . '/Collaboration/InternalNotesManager.php';
        }

        // Use Common Layout
        // Use Common Layout with fallback
        $layout_available = class_exists('\SMO_Social\Admin\Views\Common\AppLayout');
        if ($layout_available) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('dashboard', __('Dashboard', 'smo-social'));
        } else {
            echo '<div class="wrap smo-dashboard">';
        }
        ?>

        <!-- Modern Gradient Header -->
        <div class="smo-import-header">
            <div class="smo-header-content">
                <h1 class="smo-page-title">
                    <span class="smo-icon">📊</span>
                    Enhanced Dashboard
                </h1>
                <p class="smo-page-subtitle">Comprehensive overview and management</p>
            </div>
            <div class="smo-header-actions">
                <button class="smo-btn smo-btn-primary" id="smo-refresh-dashboard">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Refresh', 'smo-social'); ?>
                </button>
                <button class="smo-btn smo-btn-secondary" id="smo-export-report">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export', 'smo-social'); ?>
                </button>
            </div>
        </div>

        <!-- Dashboard Stats Overview -->
        <div class="smo-import-dashboard">
            <div class="smo-stats-grid">
                <div class="smo-stat-card smo-stat-gradient-1">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($stats['total_reach']); ?></h3>
                        <p class="smo-stat-label"><?php esc_html_e('Total Reach', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">📈 +12.5%</span>
                    </div>
                </div>
                
                <div class="smo-stat-card smo-stat-gradient-2">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-heart"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($stats['engagement']); ?></h3>
                        <p class="smo-stat-label"><?php esc_html_e('Engagement', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">❤️ +5.2%</span>
                    </div>
                </div>
                
                <div class="smo-stat-card smo-stat-gradient-3">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($stats['scheduled']); ?></h3>
                        <p class="smo-stat-label"><?php esc_html_e('Scheduled Posts', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">📅 Ready to post</span>
                    </div>
                </div>
                
                <div class="smo-stat-card smo-stat-gradient-4">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($stats['response_time']); ?></h3>
                        <p class="smo-stat-label"><?php esc_html_e('Avg Response', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">⚡ Fast response</span>
                    </div>
                </div>
            </div>
        </div>

        <br>

        <!-- Feature Widgets Row -->
        <?php
        $show_video_widget = get_option('smo_social_show_video_widget', 1);
        $show_best_time_widget = get_option('smo_social_show_best_time_widget', 1);
        $show_templates_widget = get_option('smo_social_show_templates_widget', 1);

        if ($show_video_widget || $show_best_time_widget || $show_templates_widget) {
        ?>
        <div class="smo-features-row">
            <?php if ($show_video_widget) { ?>
            <!-- Video Posting Widget -->
            <div class="smo-feature-widget">
                <div class="smo-card">
                    <div class="smo-card-header">
                        <h3><?php \esc_html_e('Video Posts', 'smo-social'); ?></h3>
                        <a href="<?php echo admin_url('admin.php?page=smo-social-create'); ?>" class="smo-btn smo-btn-secondary">
                            <?php \esc_html_e('Create Video', 'smo-social'); ?>
                        </a>
                    </div>
                    <div class="smo-card-body">
                        <div class="smo-video-stats">
                            <div class="smo-stat-item">
                                <span class="smo-stat-number"><?php echo esc_html(self::get_video_posts_count()); ?></span>
                                <span class="smo-stat-label"><?php \esc_html_e('Video Posts', 'smo-social'); ?></span>
                            </div>
                            <div class="smo-stat-item">
                                <span class="smo-stat-number"><?php echo esc_html(self::get_recent_video_views()); ?>K</span>
                                <span class="smo-stat-label"><?php \esc_html_e('Views', 'smo-social'); ?></span>
                            </div>
                        </div>
                        <div class="smo-video-platforms">
                            <span class="smo-platform-badge youtube">YouTube</span>
                            <span class="smo-platform-badge vimeo">Vimeo</span>
                            <span class="smo-platform-badge tiktok">TikTok</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

            <?php if ($show_best_time_widget) { ?>
            <!-- Best Time Manager Widget -->
            <div class="smo-feature-widget">
                <div class="smo-card">
                    <div class="smo-card-header">
                        <h3><?php \esc_html_e('Best Posting Times', 'smo-social'); ?></h3>
                        <a href="<?php echo admin_url('admin.php?page=smo-social-calendar'); ?>" class="smo-btn smo-btn-secondary">
                            <?php \esc_html_e('View Calendar', 'smo-social'); ?>
                        </a>
                    </div>
                    <div class="smo-card-body">
                        <div class="smo-best-times-list">
                            <?php
                            $best_times = self::get_best_posting_times();
                            foreach ($best_times as $platform => $time) {
                                echo '<div class="smo-best-time-item">';
                                echo '<span class="smo-platform-name">' . esc_html(ucfirst($platform)) . '</span>';
                                echo '<span class="smo-optimal-time">' . esc_html($time) . '</span>';
                                echo '<span class="smo-engagement-indicator high">High</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <div class="smo-time-insights">
                            <small><?php \esc_html_e('AI-powered recommendations for maximum engagement', 'smo-social'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

            <?php if ($show_templates_widget) { ?>
            <!-- Post Templates Widget -->
            <div class="smo-feature-widget">
                <div class="smo-card">
                    <div class="smo-card-header">
                        <h3><?php \esc_html_e('Content Templates', 'smo-social'); ?></h3>
                        <a href="<?php echo admin_url('admin.php?page=smo-social-templates'); ?>" class="smo-btn smo-btn-secondary">
                            <?php \esc_html_e('Browse All', 'smo-social'); ?>
                        </a>
                    </div>
                    <div class="smo-card-body">
                        <div class="smo-template-previews">
                            <?php
                            $templates = self::get_popular_templates();
                            foreach ($templates as $template) {
                                echo '<div class="smo-template-preview" data-template-id="' . esc_attr($template['id']) . '">';
                                echo '<div class="smo-template-icon">' . esc_html($template['icon']) . '</div>';
                                echo '<div class="smo-template-info">';
                                echo '<h4>' . esc_html($template['name']) . '</h4>';
                                echo '<p>' . esc_html($template['description']) . '</p>';
                                echo '</div>';
                                echo '<button class="smo-use-template smo-btn smo-btn-secondary" data-template="' . esc_attr(json_encode($template)) . '">';
                                echo esc_html(__('Use', 'smo-social'));
                                echo '</button>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <div class="smo-template-stats">
                            <span><?php printf(esc_html(__('%d templates available', 'smo-social')), self::get_templates_count()); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <br>

        <!-- New Features Grid -->
        <div class="smo-features-grid">
            <!-- Best Time Manager Widget -->
            <div class="smo-feature-card">
                <div class="smo-feature-icon purple">⏰</div>
                <h3><?php _e('Best Posting Times', 'smo-social'); ?></h3>
                <p><?php _e('AI-powered recommendations for optimal engagement', 'smo-social'); ?></p>
                
                <div class="smo-best-time-list">
                    <?php
                    if (class_exists('\SMO_Social\Features\BestTimeManager')) {
                        $best_times = \SMO_Social\Features\BestTimeManager::get_best_times();
                        $count = 0;
                        foreach ($best_times as $platform => $time_data) {
                            if ($count >= 4) break; // Show top 4
                            $platform_name = is_array($time_data) ? ($time_data['platform_slug'] ?? $platform) : $platform;
                            $time_slot = is_array($time_data) ? ($time_data['time_slot'] ?? 'N/A') : '09:00-11:00';
                            $engagement = is_array($time_data) ? ($time_data['engagement_rate'] ?? 'High') : 'High';
                            ?>
                            <div class="smo-best-time-item">
                                <div class="smo-time-platform">
                                    <span class="smo-platform-name"><?php echo esc_html($platform_name); ?></span>
                                </div>
                                <div class="smo-time-details">
                                    <span class="smo-time-badge"><?php echo esc_html($time_slot); ?></span>
                                    <span class="smo-engagement-badge high"><?php echo esc_html($engagement); ?></span>
                                </div>
                            </div>
                            <?php
                            $count++;
                        }
                    } else {
                        echo '<p class="smo-time-coming-soon">' . __('Best time analysis coming soon...', 'smo-social') . '</p>';
                    }
                    ?>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=smo-social-calendar'); ?>" class="smo-btn smo-btn-primary" style="width: 100%; margin-top: 16px; text-align: center;">
                    <?php _e('View Calendar', 'smo-social'); ?>
                </a>
            </div>

            <!-- Post Templates Widget -->
            <div class="smo-feature-card">
                <div class="smo-feature-icon blue">📝</div>
                <h3><?php _e('Content Templates', 'smo-social'); ?></h3>
                <p><?php _e('Pre-designed templates for consistent branding', 'smo-social'); ?></p>
                
                <div class="smo-feature-stats">
                    <?php
                    if (class_exists('\SMO_Social\Features\PostTemplatesManager')) {
                        $templates = \SMO_Social\Features\PostTemplatesManager::get_user_templates();
                        $template_count = count($templates);
                    } else {
                        $template_count = 15;
                    }
                    ?>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value"><?php echo esc_html($template_count); ?></span>
                        <span class="smo-stat-mini-label"><?php _e('Templates', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value">8</span>
                        <span class="smo-stat-mini-label"><?php _e('Categories', 'smo-social'); ?></span>
                    </div>
                </div>
                
                <div class="smo-template-scroll">
                    <?php
                    if (class_exists('\SMO_Social\Features\PostTemplatesManager')) {
                        $templates = \SMO_Social\Features\PostTemplatesManager::get_user_templates();
                        $shown = 0;
                        foreach ($templates as $template) {
                            if ($shown >= 3) break;
                            ?>
                            <div class="smo-template-preview-mini" onclick="window.location.href='<?php echo admin_url('admin.php?page=smo-social-create&template_id=' . $template['id']); ?>'">
                                <h4><?php echo esc_html($template['name']); ?></h4>
                                <p><?php echo esc_html($template['description']); ?></p>
                            </div>
                            <?php
                            $shown++;
                        }
                    }
                    ?>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=smo-social-templates'); ?>" class="smo-btn smo-btn-primary" style="width: 100%; margin-top: 16px; text-align: center;">
                    <?php _e('Browse Templates', 'smo-social'); ?>
                </a>
            </div>

            <!-- URL Shortener Widget -->
            <div class="smo-feature-card">
                <div class="smo-feature-icon green">🔗</div>
                <h3><?php _e('URL Shortener', 'smo-social'); ?></h3>
                <p><?php _e('Track link performance across platforms', 'smo-social'); ?></p>
                
                <div class="smo-url-stats-grid">
                    <?php
                    if (class_exists('\SMO_Social\Features\URLShortener')) {
                        $url_stats = \SMO_Social\Features\URLShortener::get_click_stats();
                        $total_urls = is_array($url_stats) ? count($url_stats) : 0;
                        $total_clicks = 0;
                        if (is_array($url_stats)) {
                            foreach ($url_stats as $stat) {
                                $total_clicks += intval($stat['clicks'] ?? 0);
                            }
                        }
                    } else {
                        $total_urls = 0;
                        $total_clicks = 0;
                    }
                    ?>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value"><?php echo esc_html($total_urls); ?></span>
                        <span class="smo-stat-mini-label"><?php _e('URLs', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value"><?php echo esc_html($total_clicks); ?></span>
                        <span class="smo-stat-mini-label"><?php _e('Clicks', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value">4</span>
                        <span class="smo-stat-mini-label"><?php _e('Providers', 'smo-social'); ?></span>
                    </div>
                </div>
                
                <div class="smo-url-providers">
                    <p class="smo-providers-label">
                        <?php _e('Supported providers:', 'smo-social'); ?>
                    </p>
                    <div class="smo-providers-badges">
                        <span class="smo-provider-badge bitly">Bitly</span>
                        <span class="smo-provider-badge rebrandly">Rebrandly</span>
                        <span class="smo-provider-badge sniply">Sniply</span>
                        <span class="smo-provider-badge tinyurl">TinyURL</span>
                    </div>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=smo-social-settings&tab=url-shortener'); ?>" class="smo-btn smo-btn-primary" style="width: 100%; margin-top: 16px; text-align: center;">
                    <?php _e('Configure', 'smo-social'); ?>
                </a>
            </div>

            <!-- Network Groupings Widget -->
            <div class="smo-feature-card">
                <div class="smo-feature-icon orange">👥</div>
                <h3><?php _e('Network Groups', 'smo-social'); ?></h3>
                <p><?php _e('Organize platforms for targeted posting', 'smo-social'); ?></p>
                
                <div class="smo-feature-stats">
                    <?php
                    if (class_exists('\SMO_Social\Features\NetworkGroupingsManager')) {
                        $groups = \SMO_Social\Features\NetworkGroupingsManager::get_user_groups();
                        $group_count = count($groups);
                        $total_platforms = 0;
                        foreach ($groups as $group) {
                            $total_platforms += count($group['platforms'] ?? array());
                        }
                    } else {
                        $group_count = 0;
                        $total_platforms = 0;
                    }
                    ?>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value"><?php echo esc_html($group_count); ?></span>
                        <span class="smo-stat-mini-label"><?php _e('Groups', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value"><?php echo esc_html($total_platforms); ?></span>
                        <span class="smo-stat-mini-label"><?php _e('Accounts', 'smo-social'); ?></span>
                    </div>
                </div>
                
                <div class="smo-groups-scroll">
                    <?php
                    if (class_exists('\SMO_Social\Features\NetworkGroupingsManager')) {
                        $groups = \SMO_Social\Features\NetworkGroupingsManager::get_user_groups();
                        if (!empty($groups)) {
                            foreach (array_slice($groups, 0, 3) as $group) {
                                ?>
                                <div class="smo-group-badge">
                                    <span class="smo-group-badge-icon">📁</span>
                                    <span class="smo-group-name"><?php echo esc_html($group['group_name']); ?></span>
                                    <span class="smo-group-count">
                                        <?php echo count($group['platforms'] ?? array()); ?>
                                    </span>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<p class="smo-no-groups">' . __('No groups created yet', 'smo-social') . '</p>';
                        }
                    }
                    ?>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=smo-social-settings&tab=network-groups'); ?>" class="smo-btn smo-btn-primary" style="width: 100%; margin-top: 16px; text-align: center;">
                    <?php _e('Manage Groups', 'smo-social'); ?>
                </a>
            </div>

            <!-- Branded Reports Widget -->
            <div class="smo-feature-card">
                <div class="smo-feature-icon purple">🎨</div>
                <h3><?php _e('Branded Reports', 'smo-social'); ?></h3>
                <p><?php _e('Customize your reports with your logo and branding', 'smo-social'); ?></p>
                
                <?php
                $logo_url = '';
                $primary_color = '#667eea';
                if (class_exists('\SMO_Social\WhiteLabel\BrandingManager')) {
                    $branding_manager = new \SMO_Social\WhiteLabel\BrandingManager();
                    $logo_url = $branding_manager->get_setting('logo_url');
                    $primary_color = $branding_manager->get_setting('primary_color', '#667eea');
                }
                ?>
                
                <div class="smo-branding-preview-card" style="--smo-primary-color: <?php echo esc_attr($primary_color); ?>">
                    <?php if ($logo_url) { ?>
                        <img src="<?php echo esc_url($logo_url); ?>" style="max-height: 50px; max-width: 100%; object-fit: contain;" alt="Brand Logo">
                    <?php } else { ?>
                        <div style="color: var(--smo-text-muted); display: flex; flex-direction: column; align-items: center;">
                            <span class="dashicons dashicons-format-image" style="font-size: 32px; height: 32px; width: 32px; margin-bottom: 8px;"></span>
                            <span style="font-size: 12px;"><?php _e('No logo uploaded', 'smo-social'); ?></span>
                        </div>
                    <?php } ?>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=smo-branding'); ?>" class="smo-btn smo-btn-primary" style="width: 100%; text-align: center;">
                    <?php _e('Configure Branding', 'smo-social'); ?>
                </a>
            </div>

            <!-- Included Users Widget -->
            <div class="smo-feature-card">
                <div class="smo-feature-icon blue">👥</div>
                <h3><?php _e('Team Members', 'smo-social'); ?></h3>
                <p><?php _e('Manage users who can access and contribute', 'smo-social'); ?></p>
                
                <div class="smo-feature-stats">
                    <?php
                    $total_users = 0;
                    $active_users = 0;
                    $team_members = array();
                    
                    if (class_exists('\SMO_Social\Collaboration\UserManager')) {
                        $user_manager = new \SMO_Social\Collaboration\UserManager();
                        $team_members = $user_manager->get_team_members();
                        $total_users = count($team_members);
                        $active_users = count(array_filter($team_members, function($m) { return $m->status === 'active'; }));
                    }
                    ?>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value"><?php echo esc_html($total_users); ?></span>
                        <span class="smo-stat-mini-label"><?php _e('Total', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-stat-mini">
                        <span class="smo-stat-mini-value"><?php echo esc_html($active_users); ?></span>
                        <span class="smo-stat-mini-label"><?php _e('Active', 'smo-social'); ?></span>
                    </div>
                </div>
                
                <div class="smo-avatar-group">
                    <?php
                    $shown_avatars = 0;
                    foreach ($team_members as $member) {
                        if ($shown_avatars >= 5) break;
                        if ($member->status !== 'active') continue;
                        
                        $avatar_url = get_avatar_url($member->user_email, array('size' => 72));
                        echo '<img src="' . esc_url($avatar_url) . '" class="smo-avatar" title="' . esc_attr($member->display_name) . '" alt="' . esc_attr($member->display_name) . '">';
                        $shown_avatars++;
                    }
                    
                    if ($active_users > 5) {
                        echo '<div class="smo-avatar-more">+' . ($active_users - 5) . '</div>';
                    }

                    if ($active_users === 0) {
                        echo '<p class="smo-no-members">' . __('No active team members', 'smo-social') . '</p>';
                    }
                    ?>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=smo-social-users'); ?>" class="smo-btn smo-btn-primary" style="width: 100%; margin-top: 16px; text-align: center;">
                    <?php _e('Manage Team', 'smo-social'); ?>
                </a>
            </div>

            <!-- Notes Widget -->
            <div class="smo-feature-card">
                <div class="smo-feature-icon orange">📝</div>
                <h3><?php _e('Internal Notes', 'smo-social'); ?></h3>
                <p><?php _e('Recent internal comments and collaboration', 'smo-social'); ?></p>
                
                <div class="smo-notes-list">
                    <?php
                    if (class_exists('\SMO_Social\Collaboration\InternalNotesManager')) {
                        $notes_manager = new \SMO_Social\Collaboration\InternalNotesManager();
                        $recent_notes = $notes_manager->get_note_history(null, null, 3);
                        
                        if (!empty($recent_notes)) {
                            foreach ($recent_notes as $note) {
                                $user_email = isset($note['user_email']) ? $note['user_email'] : '';
                                if (empty($user_email) && isset($note['user_id'])) {
                                    $user = get_userdata($note['user_id']);
                                    if ($user) $user_email = $user->user_email;
                                }
                                $avatar_url = get_avatar_url($user_email, array('size' => 64));
                                ?>
                                <div class="smo-note-item">
                                    <img src="<?php echo esc_url($avatar_url); ?>" class="smo-note-avatar" alt="<?php echo esc_attr($note['user_name']); ?>">
                                    <div class="smo-note-content">
                                        <div class="smo-note-header">
                                            <span class="smo-note-author"><?php echo esc_html($note['user_name']); ?></span>
                                            <span class="smo-note-time"><?php echo human_time_diff(strtotime($note['created_at']), current_time('timestamp')) . ' ago'; ?></span>
                                        </div>
                                        <p class="smo-note-text"><?php echo wp_trim_words(esc_html($note['content']), 10); ?></p>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="smo-no-notes">';
                            echo '<span class="dashicons dashicons-edit smo-no-notes-icon"></span>';
                            echo '<p class="smo-no-notes-text">' . __('No recent notes', 'smo-social') . '</p>';
                            echo '</div>';
                        }
                    } else {
                         echo '<p style="color: var(--smo-text-secondary); font-size: 12px;">' . __('Notes module not loaded', 'smo-social') . '</p>';
                    }
                    ?>
                </div>
                
                <a href="<?php echo admin_url('admin.php?page=smo-social-posts'); ?>" class="smo-btn smo-btn-primary" style="width: 100%; text-align: center;">
                    <?php _e('View All Notes', 'smo-social'); ?>
                </a>
            </div>
        </div>

        <br>

        <!-- Main Grid -->
        <div class="smo-grid-layout">
            <!-- Main Chart -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2><?php \esc_html_e('Engagement Overview', 'smo-social'); ?></h2>
                    <select>
                        <option><?php \esc_html_e('Last 7 Days', 'smo-social'); ?></option>
                        <option><?php \esc_html_e('Last 30 Days', 'smo-social'); ?></option>
                    </select>
                </div>
                <div class="smo-card-body">
                    <div class="smo-chart-container">
                        <canvas id="smo-main-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2><?php \esc_html_e('Recent Activity', 'smo-social'); ?></h2>
                </div>
                <ul class="smo-activity-list">
                    <li class="smo-activity-item">
                        <div class="smo-activity-icon"><span class="dashicons dashicons-twitter"></span></div>
                        <div class="smo-activity-content">
                            <p><strong><?php \esc_html_e('Post published', 'smo-social'); ?></strong>
                                <?php \esc_html_e('on Twitter', 'smo-social'); ?></p>
                            <span class="smo-activity-time"><?php \esc_html_e('Just now', 'smo-social'); ?></span>
                        </div>
                    </li>
                    <li class="smo-activity-item">
                        <div class="smo-activity-icon"><span class="dashicons dashicons-warning"></span></div>
                        <div class="smo-activity-content">
                            <p><strong><?php \esc_html_e('Connection lost', 'smo-social'); ?></strong>
                                <?php \esc_html_e('on LinkedIn', 'smo-social'); ?></p>
                            <span class="smo-activity-time"><?php \esc_html_e('25 mins ago', 'smo-social'); ?></span>
                        </div>
                    </li>
                    <li class="smo-activity-item">
                        <div class="smo-activity-icon"><span class="dashicons dashicons-admin-comments"></span></div>
                        <div class="smo-activity-content">
                            <p><strong><?php \esc_html_e('New comment', 'smo-social'); ?></strong>
                                <?php \esc_html_e('from Sarah', 'smo-social'); ?></p>
                            <span class="smo-activity-time"><?php \esc_html_e('1 hour ago', 'smo-social'); ?></span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <?php
        // Check if layout was available at start to close it properly
        $layout_available = class_exists('\SMO_Social\Admin\Views\Common\AppLayout');
        if ($layout_available) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        } else {
            echo '</div>';
        }
    }
}
