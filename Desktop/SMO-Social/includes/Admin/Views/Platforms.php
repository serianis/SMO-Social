<?php
/**
 * SMO Social Platforms View
 * 
 * Platform management interface with modern gradient design
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get admin instance to check platform connections
$admin_instance = new \SMO_Social\Admin\Admin();
?>

<div class="smo-content-import-wrap">
    <?php
    // Use Common Layout with AppLayout helpers
    if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
        \SMO_Social\Admin\Views\Common\AppLayout::render_start('platforms', __('Platform Settings', 'smo-social'));
        
        // Render standardized gradient header using AppLayout
        \SMO_Social\Admin\Views\Common\AppLayout::render_header([
            'icon' => '🌐',
            'title' => __('Platform Configuration', 'smo-social'),
            'subtitle' => __('Connect and manage your social media platforms', 'smo-social'),
            'actions' => [
                [
                    'id' => 'smo-refresh-platforms',
                    'label' => __('Refresh', 'smo-social'),
                    'icon' => 'update',
                    'class' => 'smo-btn-secondary'
                ],
                [
                    'id' => 'smo-bulk-setup',
                    'label' => __('Bulk Setup', 'smo-social'),
                    'icon' => 'admin-tools',
                    'class' => 'smo-btn-primary'
                ]
            ]
        ]);
        
        // Render standardized stats dashboard using AppLayout
        \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
            [
                'icon' => 'admin-links',
                'value' => '-',
                'label' => __('Connected Platforms', 'smo-social'),
                'trend' => '🔗 Active',
                'id' => 'connected-platforms'
            ],
            [
                'icon' => 'grid-view',
                'value' => '-',
                'label' => __('Total Platforms', 'smo-social'),
                'trend' => '📊 Available',
                'id' => 'total-platforms'
            ],
            [
                'icon' => 'warning',
                'value' => '-',
                'label' => __('Need Setup', 'smo-social'),
                'trend' => '⚠️ Pending',
                'id' => 'platforms-needing-setup'
            ],
            [
                'icon' => 'heart',
                'value' => '-',
                'label' => __('Healthy Connections', 'smo-social'),
                'trend' => '✅ Working',
                'id' => 'healthy-platforms'
            ]
        ]);
    }
    ?>

    <div class="smo-content-body">
        <!-- Platform Categories Filter -->
        <div class="smo-platform-categories">
            <button class="smo-btn smo-btn-filter active" data-category="all">
                <span class="dashicons dashicons-grid-view"></span>
                <?php _e('All Platforms', 'smo-social'); ?>
            </button>
            <button class="smo-btn smo-btn-filter" data-category="major">
                <span class="dashicons dashicons-star-filled"></span>
                <?php _e('Major Networks', 'smo-social'); ?>
            </button>
            <button class="smo-btn smo-btn-filter" data-category="video">
                <span class="dashicons dashicons-video-alt3"></span>
                <?php _e('Video Platforms', 'smo-social'); ?>
            </button>
            <button class="smo-btn smo-btn-filter" data-category="messaging">
                <span class="dashicons dashicons-email"></span>
                <?php _e('Messaging', 'smo-social'); ?>
            </button>
            <button class="smo-btn smo-btn-filter" data-category="emerging">
                <span class="dashicons dashicons-trending-up"></span>
                <?php _e('Emerging', 'smo-social'); ?>
            </button>
            <button class="smo-btn smo-btn-filter" data-category="community">
                <span class="dashicons dashicons-groups"></span>
                <?php _e('Community', 'smo-social'); ?>
            </button>
            <button class="smo-btn smo-btn-filter" data-category="regional">
                <span class="dashicons dashicons-location-alt"></span>
                <?php _e('Regional', 'smo-social'); ?>
            </button>
        </div>

        <div class="smo-platforms-grid">
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="smo-icon">⚙️</span>
                        <?php _e('Available Platforms', 'smo-social'); ?>
                    </h2>
                </div>
                <div class="smo-card-body">
                    <div class="smo-platforms-grid-inner">
                        <?php
                        // Complete platforms configuration
                        // Complete platforms configuration
                        $platforms_base = array(
                            // Major Networks
                            'facebook' => array('name' => 'Facebook', 'description' => 'Connect with friends and customers.', 'icon' => 'facebook', 'type' => 'social_network', 'features' => array('text', 'images', 'videos'), 'category' => 'major'),
                            'instagram' => array('name' => 'Instagram', 'description' => 'Share visual content and stories.', 'icon' => 'instagram', 'type' => 'visual_social', 'features' => array('images', 'videos', 'stories'), 'category' => 'major'),
                            'twitter' => array('name' => 'Twitter/X', 'description' => 'Share updates and engage in conversations.', 'icon' => 'twitter', 'type' => 'microblogging', 'features' => array('text', 'images', 'videos'), 'category' => 'major'),
                            'linkedin' => array('name' => 'LinkedIn', 'description' => 'Professional networking and sharing.', 'icon' => 'linkedin', 'type' => 'professional', 'features' => array('text', 'images', 'articles'), 'category' => 'major'),
                            
                            // Video Platforms
                            'youtube' => array('name' => 'YouTube', 'description' => 'Upload and share video content.', 'icon' => 'youtube', 'type' => 'video_sharing', 'features' => array('videos', 'live', 'shorts'), 'category' => 'video'),
                            'tiktok' => array('name' => 'TikTok', 'description' => 'Create short-form video content.', 'icon' => 'tiktok', 'type' => 'short_video', 'features' => array('videos', 'music', 'effects'), 'category' => 'video'),
                            'snapchat' => array('name' => 'Snapchat', 'description' => 'Share moments.', 'icon' => 'snapchat', 'type' => 'visual_messaging', 'features' => array('images', 'videos', 'stories'), 'category' => 'video'),
                            
                            // Messaging
                            'discord' => array('name' => 'Discord', 'description' => 'Connect with communities.', 'icon' => 'discord', 'type' => 'messaging', 'features' => array('text', 'voice', 'webhooks'), 'category' => 'messaging'),
                            'telegram' => array('name' => 'Telegram', 'description' => 'Share through channels and groups.', 'icon' => 'telegram', 'type' => 'messaging', 'features' => array('text', 'images', 'bots'), 'category' => 'messaging'),
                            'whatsapp_business' => array('name' => 'WhatsApp Biz', 'description' => 'Business messaging.', 'icon' => 'whatsapp', 'type' => 'messaging', 'features' => array('text', 'images'), 'category' => 'messaging'),
                            'viber' => array('name' => 'Viber', 'description' => 'Connect with audience.', 'icon' => 'viber', 'type' => 'messaging', 'features' => array('text', 'images'), 'category' => 'messaging'),
                            'line' => array('name' => 'LINE', 'description' => 'Messaging and timeline.', 'icon' => 'line', 'type' => 'messaging', 'features' => array('text', 'timeline'), 'category' => 'messaging'),
                            'signal' => array('name' => 'Signal', 'description' => 'Private messaging.', 'icon' => 'signal', 'type' => 'messaging', 'features' => array('text', 'images'), 'category' => 'messaging'),
                            
                            // Emerging & Decentralized
                            'threads' => array('name' => 'Threads', 'description' => 'Text-based conversations.', 'icon' => 'threads', 'type' => 'microblogging', 'features' => array('text', 'images', 'replies'), 'category' => 'emerging'),
                            'bluesky' => array('name' => 'Bluesky', 'description' => 'Decentralized social networking.', 'icon' => 'bluesky', 'type' => 'decentralized', 'features' => array('text', 'images', 'feeds'), 'category' => 'emerging'),
                            'mastodon' => array('name' => 'Mastodon', 'description' => 'Decentralized microblogging.', 'icon' => 'mastodon', 'type' => 'decentralized', 'features' => array('text', 'images', 'boosts'), 'category' => 'emerging'),
                            'gab' => array('name' => 'Gab', 'description' => 'Free speech social network.', 'icon' => 'gab', 'type' => 'microblogging', 'features' => array('text', 'images'), 'category' => 'emerging'),
                            'parler' => array('name' => 'Parler', 'description' => 'Social platform.', 'icon' => 'parler', 'type' => 'microblogging', 'features' => array('text', 'images'), 'category' => 'emerging'),
                            'bereal' => array('name' => 'BeReal', 'description' => 'Authentic photo sharing.', 'icon' => 'bereal', 'type' => 'visual', 'features' => array('images'), 'category' => 'emerging'),
                            'clubhouse' => array('name' => 'Clubhouse', 'description' => 'Social audio.', 'icon' => 'clubhouse', 'type' => 'audio', 'features' => array('audio', 'rooms'), 'category' => 'emerging'),
                            'flipboard' => array('name' => 'Flipboard', 'description' => 'Social magazine.', 'icon' => 'flipboard', 'type' => 'curation', 'features' => array('articles', 'magazines'), 'category' => 'emerging'),
                            'spotify' => array('name' => 'Spotify', 'description' => 'Podcasts and music.', 'icon' => 'spotify', 'type' => 'audio', 'features' => array('podcasts'), 'category' => 'emerging'),
                            
                            // Community & Blogging
                            'reddit' => array('name' => 'Reddit', 'description' => 'Community discussions.', 'icon' => 'reddit', 'type' => 'discussion', 'features' => array('text', 'images', 'polls'), 'category' => 'community'),
                            'pinterest' => array('name' => 'Pinterest', 'description' => 'Visual inspiration and ideas.', 'icon' => 'pinterest', 'type' => 'visual', 'features' => array('images', 'pins', 'boards'), 'category' => 'community'),
                            'quora' => array('name' => 'Quora', 'description' => 'Question and answer.', 'icon' => 'quora', 'type' => 'discussion', 'features' => array('text', 'links'), 'category' => 'community'),
                            'tumblr' => array('name' => 'Tumblr', 'description' => 'Microblogging and social.', 'icon' => 'tumblr', 'type' => 'blogging', 'features' => array('text', 'images', 'html'), 'category' => 'community'),
                            'medium' => array('name' => 'Medium', 'description' => 'Publishing platform.', 'icon' => 'medium', 'type' => 'blogging', 'features' => array('articles'), 'category' => 'community'),
                            'discord_communities' => array('name' => 'Discord Communities', 'description' => 'Manage servers.', 'icon' => 'discord', 'type' => 'community', 'features' => array('roles', 'channels'), 'category' => 'community'),
                            'facebook_groups' => array('name' => 'Facebook Groups', 'description' => 'Community groups.', 'icon' => 'facebook', 'type' => 'community', 'features' => array('posts', 'events'), 'category' => 'community'),
                            'linkedin_groups' => array('name' => 'LinkedIn Groups', 'description' => 'Professional groups.', 'icon' => 'linkedin', 'type' => 'community', 'features' => array('discussions'), 'category' => 'community'),
                            'youtube_communities' => array('name' => 'YouTube Comm.', 'description' => 'Channel community.', 'icon' => 'youtube', 'type' => 'community', 'features' => array('posts'), 'category' => 'community'),
                            
                            // Regional
                            'vkontakte' => array('name' => 'VK', 'description' => 'Russian social network.', 'icon' => 'vk', 'type' => 'social_network', 'features' => array('text', 'images', 'videos'), 'category' => 'regional'),
                            'weibo' => array('name' => 'Weibo', 'description' => 'Chinese microblogging.', 'icon' => 'weibo', 'type' => 'microblogging', 'features' => array('text', 'images'), 'category' => 'regional'),
                            'kakaotalk' => array('name' => 'KakaoTalk', 'description' => 'Korean messaging.', 'icon' => 'kakao', 'type' => 'messaging', 'features' => array('text'), 'category' => 'regional'),
                        );

                        // Add connection status
                        $platforms_with_status = array();
                        foreach ($platforms_base as $slug => $platform) {
                            $platform['connected'] = $admin_instance->is_platform_connected($slug);
                            $platforms_with_status[$slug] = $platform;
                        }

                        foreach ($platforms_with_status as $slug => $platform):
                        ?>
                            <div class="smo-platform-card <?php echo $platform['connected'] ? 'connected' : 'disconnected'; ?> category-<?php echo esc_attr($platform['category']); ?>">
                                <div class="smo-platform-header">
                                    <h3><?php echo esc_html($platform['name']); ?></h3>
                                    <span class="smo-connection-status">
                                        <?php echo $platform['connected'] ? '✓ Connected' : '○ Not Connected'; ?>
                                    </span>
                                </div>

                                <p><?php echo esc_html($platform['description']); ?></p>

                                <div class="smo-platform-features">
                                    <strong><?php _e('Features:', 'smo-social'); ?></strong>
                                    <div class="smo-features-list">
                                        <?php foreach ($platform['features'] as $feature): ?>
                                            <span class="smo-feature-badge"><?php echo esc_html($feature); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="smo-platform-actions">
                                    <?php if ($platform['connected']): ?>
                                        <button class="smo-btn smo-btn-secondary smo-test-platform" data-platform="<?php echo esc_attr($slug); ?>">
                                            <?php _e('Test Connection', 'smo-social'); ?>
                                        </button>
                                        <button class="smo-btn smo-btn-secondary smo-configure-platform" data-platform="<?php echo esc_attr($slug); ?>">
                                            <?php _e('Configure', 'smo-social'); ?>
                                        </button>
                                        <button class="smo-btn smo-btn-secondary smo-disconnect-platform" data-platform="<?php echo esc_attr($slug); ?>">
                                            <?php _e('Disconnect', 'smo-social'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="smo-btn smo-btn-primary smo-connect-platform" data-platform="<?php echo esc_attr($slug); ?>">
                                            <?php _e('Connect', 'smo-social'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Settings Modal -->
    <div id="smo-platform-modal" class="smo-modal" style="display: none;">
        <div class="smo-modal-content">
            <span class="smo-close">&times;</span>
            <h2 id="smo-modal-title"><?php _e('Platform Settings', 'smo-social'); ?></h2>
            <div id="smo-modal-content">
                <!-- Platform-specific settings will be loaded here -->
            </div>
        </div>
    </div>

    <?php
    if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
        \SMO_Social\Admin\Views\Common\AppLayout::render_end();
    }
    ?>

    <script>
    jQuery(document).ready(function($) {
        // Category filter
        $('.smo-btn-filter').click(function() {
            var category = $(this).data('category');
            $('.smo-btn-filter').removeClass('active');
            $(this).addClass('active');
            
            if (category === 'all') {
                $('.smo-platform-card').show();
            } else {
                $('.smo-platform-card').hide();
                $('.category-' + category).show();
            }
        });

        // Connect platform
        $('.smo-connect-platform').click(function() {
            var platform = $(this).data('platform');
            $.post(ajaxurl, {
                action: 'smo_connect_platform',
                nonce: typeof smo_social_ajax !== 'undefined' ? smo_social_ajax.nonce : '',
                platform: platform
            }, function(response) {
                if (response.success && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    alert('Error: ' + (response.data || 'Connection failed'));
                }
            });
        });

        // Disconnect platform
        $('.smo-disconnect-platform').click(function() {
            if (!confirm('Are you sure you want to disconnect this platform?')) return;
            var platform = $(this).data('platform');
            $.post(ajaxurl, {
                action: 'smo_disconnect_platform',
                nonce: typeof smo_social_ajax !== 'undefined' ? smo_social_ajax.nonce : '',
                platform: platform
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        });

        // Test connection
        $('.smo-test-platform').click(function() {
            var platform = $(this).data('platform');
            var button = $(this);
            button.prop('disabled', true).text('Testing...');
            $.post(ajaxurl, {
                action: 'smo_test_platform',
                nonce: typeof smo_social_ajax !== 'undefined' ? smo_social_ajax.nonce : '',
                platform: platform
            }, function(response) {
                button.prop('disabled', false).text('Test Connection');
                alert(response.success ? '✓ Connection working!' : '✗ Connection failed: ' + response.data);
            });
        });

        // Modal handling
        $('.smo-close, .smo-modal').click(function(e) {
            if (e.target === this) {
                $('#smo-platform-modal').hide();
            }
        });
    });
    </script>
</div>