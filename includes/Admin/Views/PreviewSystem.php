<?php
namespace SMO_Social\Admin\Views;

class PreviewSystem {
    private $platform_manager;

    public function __construct() {
        $this->platform_manager = new \SMO_Social\Platforms\Manager();
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add preview JavaScript
        \add_action('admin_footer', array($this, 'add_preview_javascript'));
        
        // AJAX handlers for preview
        \add_action('wp_ajax_smo_get_platform_preview', array($this, 'ajax_get_platform_preview'));
        \add_action('wp_ajax_smo_validate_media', array($this, 'ajax_validate_media'));
    }

    /**
     * Get preview data for all enabled platforms
     * Optimized to only load requested platforms
     */
    public function get_previews($content, $media = array(), $platforms = array()) {
        // Memory optimization: If no specific platforms requested, use efficient batch loading
        if (empty($platforms)) {
            $platform_objects = $this->platform_manager->load_platform_batch();
        } else {
            // Load only the requested platforms
            $platform_objects = array();
            foreach ($platforms as $platform_slug) {
                $platform = $this->platform_manager->get_platform($platform_slug);
                if ($platform) {
                    $platform_objects[$platform_slug] = $platform;
                }
            }
        }

        $previews = array();
        
        foreach ($platform_objects as $platform_slug => $platform) {
            $previews[$platform_slug] = array(
                'platform' => $platform_slug,
                'name' => $platform->get_name(),
                'preview' => $this->generate_platform_preview($platform, $content, $media),
                'validation' => $this->validate_platform_content($platform, $content, $media),
                'character_limit' => $platform->get_max_chars(),
                'features' => $platform->get_features()
            );
        }

        return $previews;
    }

    /**
     * Generate platform-specific preview
     */
    private function generate_platform_preview($platform, $content, $media = array()) {
        $platform_slug = $platform->get_slug();
        $preview = array();

        switch ($platform_slug) {
            case 'twitter':
                $preview = $this->generate_twitter_preview($content, $media);
                break;
            case 'facebook':
                $preview = $this->generate_facebook_preview($content, $media);
                break;
            case 'instagram':
                $preview = $this->generate_instagram_preview($content, $media);
                break;
            case 'linkedin':
                $preview = $this->generate_linkedin_preview($content, $media);
                break;
            case 'mastodon':
                $preview = $this->generate_mastodon_preview($content, $media);
                break;
            case 'tiktok':
                $preview = $this->generate_tiktok_preview($content, $media);
                break;
            case 'reddit':
                $preview = $this->generate_reddit_preview($content, $media);
                break;
            default:
                $preview = $this->generate_generic_preview($content, $media);
        }

        return $preview;
    }

    /**
     * Twitter/X preview
     */
    private function generate_twitter_preview($content, $media) {
        return array(
            'type' => 'timeline',
            'header' => array(
                'avatar' => 'https://via.placeholder.com/48x48',
                'username' => '@yourusername',
                'display_name' => 'Your Display Name',
                'verified' => true,
                'timestamp' => 'Now'
            ),
            'content' => array(
                'text' => $this->format_twitter_content($content),
                'media' => $this->process_media_for_twitter($media),
                'stats' => array(
                    'likes' => 0,
                    'retweets' => 0,
                    'replies' => 0
                )
            ),
            'actions' => array('reply', 'retweet', 'like'),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '550px',
                'background' => '#ffffff',
                'text_color' => '#0f1419',
                'border' => '#eff3f4'
            )
        );
    }

    /**
     * Facebook preview
     */
    private function generate_facebook_preview($content, $media) {
        return array(
            'type' => 'feed_post',
            'header' => array(
                'avatar' => 'https://via.placeholder.com/40x40',
                'username' => 'Your Page',
                'timestamp' => 'Just now',
                'privacy' => 'Public'
            ),
            'content' => array(
                'text' => nl2br($content),
                'media' => $this->process_media_for_facebook($media)
            ),
            'actions' => array('like', 'comment', 'share'),
            'stats' => array(
                'likes' => 0,
                'shares' => 0,
                'comments' => 0
            ),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '500px',
                'background' => '#ffffff',
                'text_color' => '#1c1e21'
            )
        );
    }

    /**
     * Instagram preview
     */
    private function generate_instagram_preview($content, $media) {
        $primary_image = !empty($media) ? $media[0]['url'] : 'https://via.placeholder.com/400x400';
        
        return array(
            'type' => 'instagram_post',
            'header' => array(
                'avatar' => 'https://via.placeholder.com/32x32',
                'username' => 'yourusername',
                'verified' => false,
                'timestamp' => 'Just now'
            ),
            'content' => array(
                'image' => $primary_image,
                'carousel' => count($media) > 1 ? array_slice($media, 0, 4) : null,
                'caption' => $content,
                'hashtags' => $this->extract_hashtags($content)
            ),
            'actions' => array('like', 'comment', 'share', 'save'),
            'stats' => array(
                'likes' => 0,
                'comments' => 0
            ),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '375px',
                'background' => '#ffffff',
                'text_color' => '#262626'
            )
        );
    }

    /**
     * LinkedIn preview
     */
    private function generate_linkedin_preview($content, $media) {
        return array(
            'type' => 'linkedin_post',
            'header' => array(
                'avatar' => 'https://via.placeholder.com/48x48',
                'username' => 'Your Name',
                'title' => 'Your Professional Title',
                'timestamp' => 'Just now',
                'visibility' => 'Public'
            ),
            'content' => array(
                'text' => nl2br($content),
                'media' => $this->process_media_for_linkedin($media),
                'engagement' => array(
                    'reactions' => 0,
                    'comments' => 0,
                    'shares' => 0
                )
            ),
            'actions' => array('like', 'comment', 'share', 'send'),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '552px',
                'background' => '#ffffff',
                'text_color' => '#000000'
            )
        );
    }

    /**
     * Mastodon preview
     */
    private function generate_mastodon_preview($content, $media) {
        return array(
            'type' => 'mastodon_toot',
            'header' => array(
                'avatar' => 'https://via.placeholder.com/48x48',
                'username' => '@yourusername',
                'display_name' => 'Your Display Name',
                'instance' => '@social.example',
                'timestamp' => 'Just now',
                'boosted' => false
            ),
            'content' => array(
                'text' => $this->format_mastodon_content($content),
                'media' => $this->process_media_for_mastodon($media)
            ),
            'actions' => array('reply', 'boost', 'favourite', 'share'),
            'stats' => array(
                'replies' => 0,
                'boosts' => 0,
                'favourites' => 0
            ),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '600px',
                'background' => '#282c37',
                'text_color' => '#d9d9d9',
                'card_background' => '#1c2023'
            )
        );
    }

    /**
     * TikTok preview
     */
    private function generate_tiktok_preview($content, $media) {
        return array(
            'type' => 'tiktok_video',
            'header' => array(
                'username' => '@yourusername',
                'display_name' => 'Your Display Name',
                'verified' => false
            ),
            'content' => array(
                'video_placeholder' => !empty($media) ? $media[0]['url'] : 'https://via.placeholder.com/300x400',
                'caption' => $content,
                'hashtags' => $this->extract_hashtags($content),
                'sounds' => array('Original Sound')
            ),
            'actions' => array('like', 'comment', 'share', 'save'),
            'stats' => array(
                'likes' => 0,
                'comments' => 0,
                'shares' => 0
            ),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '325px',
                'background' => '#000000',
                'text_color' => '#ffffff'
            )
        );
    }

    /**
     * Reddit preview
     */
    private function generate_reddit_preview($content, $media) {
        return array(
            'type' => 'reddit_post',
            'header' => array(
                'subreddit' => 'r/yoursubreddit',
                'username' => 'u/yourusername',
                'timestamp' => 'Just now',
                'score' => 0
            ),
            'content' => array(
                'title' => substr($content, 0, 300),
                'text' => strlen($content) > 300 ? substr($content, 300) : '',
                'media' => $this->process_media_for_reddit($media)
            ),
            'actions' => array('upvote', 'downvote', 'comment', 'share', 'save'),
            'comments_preview' => array(
                'count' => 0,
                'sample' => array()
            ),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '728px',
                'background' => '#ffffff',
                'text_color' => '#1a1a1b'
            )
        );
    }

    /**
     * Generic preview fallback
     */
    private function generate_generic_preview($content, $media) {
        return array(
            'type' => 'generic_post',
            'content' => array(
                'text' => $content,
                'media' => $media
            ),
            'style' => array(
                'font_family' => 'system-ui',
                'max_width' => '400px',
                'background' => '#ffffff',
                'text_color' => '#000000'
            )
        );
    }

    /**
     * Validate content for platform
     */
    private function validate_platform_content($platform, $content, $media = array()) {
        $validation = array(
            'valid' => true,
            'warnings' => array(),
            'errors' => array(),
            'suggestions' => array()
        );

        $platform_slug = $platform->get_slug();
        $max_chars = $platform->get_max_chars();
        $char_count = strlen($content);

        // Character limit check
        if ($char_count > $max_chars) {
            $validation['errors'][] = "Content exceeds character limit of {$max_chars} characters";
            $validation['valid'] = false;
        } elseif ($char_count > $max_chars * 0.9) {
            $validation['warnings'][] = "Content is close to character limit ({$char_count}/{$max_chars})";
        }

        // Platform-specific validations
        switch ($platform_slug) {
            case 'twitter':
                $validation = $this->validate_twitter_content($content, $media, $validation);
                break;
            case 'instagram':
                $validation = $this->validate_instagram_content($content, $media, $validation);
                break;
            case 'linkedin':
                $validation = $this->validate_linkedin_content($content, $media, $validation);
                break;
            case 'mastodon':
                $validation = $this->validate_mastodon_content($content, $media, $validation);
                break;
        }

        // Media validation
        if (!empty($media)) {
            $validation = $this->validate_media_for_platform($platform, $media, $validation);
        }

        return $validation;
    }

    /**
     * Twitter-specific content validation
     */
    private function validate_twitter_content($content, $media, $validation) {
        $char_count = strlen($content);
        
        // URL counting (Twitter counts URLs as 23 characters)
        $url_pattern = '/https?:\/\/[^\s]+/';
        $urls = preg_match_all($url_pattern, $content, $matches);
        if ($urls > 0) {
            $original_url_length = strlen(implode(' ', $matches[0]));
            $twitter_url_length = $urls * 23;
            $adjusted_count = $char_count - $original_url_length + $twitter_url_length;
            
            if ($adjusted_count > 280) {
                $validation['errors'][] = "Content exceeds Twitter character limit when URLs are counted (estimated {$adjusted_count}/280 characters)";
            }
        }

        // Check for excessive hashtags
        $hashtag_count = substr_count($content, '#');
        if ($hashtag_count > 6) {
            $validation['warnings'][] = "Twitter posts with more than 6 hashtags may have reduced engagement";
        }

        // Check for mentions and ensure they're valid
        $mentions = preg_match_all('/@\w+/', $content, $matches);
        if ($mentions > 0) {
            foreach ($matches[0] as $mention) {
                if (!preg_match('/^@[a-zA-Z0-9_]{1,15}$/', $mention)) {
                    $validation['errors'][] = "Invalid Twitter username: {$mention}";
                }
            }
        }

        return $validation;
    }

    /**
     * Instagram-specific content validation
     */
    private function validate_instagram_content($content, $media, $validation) {
        $char_count = strlen($content);
        
        if ($char_count > 2200) {
            $validation['errors'][] = "Instagram captions cannot exceed 2,200 characters";
        }

        // Hashtag validation
        $hashtags = $this->extract_hashtags($content);
        if (count($hashtags) > 30) {
            $validation['warnings'][] = "Instagram allows up to 30 hashtags, but 5-10 are optimal";
        }

        // Check if content appears to need an image
        if (empty($media) && (stripos($content, 'photo') !== false || stripos($content, 'picture') !== false)) {
            $validation['suggestions'][] = "Consider adding an image to your post";
        }

        return $validation;
    }

    /**
     * LinkedIn-specific content validation
     */
    private function validate_linkedin_content($content, $media, $validation) {
        $word_count = str_word_count($content);
        $char_count = strlen($content);
        
        if ($char_count > 3000) {
            $validation['errors'][] = "LinkedIn posts cannot exceed 3,000 characters";
        }

        if ($word_count < 25) {
            $validation['warnings'][] = "LinkedIn posts with 25+ words tend to perform better";
        }

        // Check for professional tone indicators
        $casual_words = array('lol', 'omg', 'wtf', 'kinda', 'gonna');
        foreach ($casual_words as $word) {
            if (stripos($content, $word) !== false) {
                $validation['warnings'][] = "Consider using more professional language for LinkedIn";
                break;
            }
        }

        return $validation;
    }

    /**
     * Mastodon-specific content validation
     */
    private function validate_mastodon_content($content, $media, $validation) {
        $char_count = strlen($content);
        
        if ($char_count > 500) {
            $validation['errors'][] = "Mastodon posts cannot exceed 500 characters";
        }

        // Check for federated content warnings
        $warning_words = array('nsfw', 'cw:', 'content warning');
        foreach ($warning_words as $word) {
            if (stripos($content, $word) !== false) {
                $validation['suggestions'][] = "Consider adding content warnings for sensitive material";
                break;
            }
        }

        return $validation;
    }

    /**
     * Media validation for platform
     */
    private function validate_media_for_platform($platform, $media, $validation) {
        $platform_slug = $platform->get_slug();
        $issues = array();

        foreach ($media as $index => $item) {
            if ($item['type'] === 'image') {
                $image_validation = $this->validate_image_for_platform($platform_slug, $item['url']);
                if (!$image_validation['valid']) {
                    $issues = array_merge($issues, $image_validation['issues']);
                }
            } elseif ($item['type'] === 'video') {
                $video_validation = $this->validate_video_for_platform($platform_slug, $item['url']);
                if (!$video_validation['valid']) {
                    $issues = array_merge($issues, $video_validation['issues']);
                }
            }
        }

        if (!empty($issues)) {
            $validation['errors'] = array_merge($validation['errors'], $issues);
            $validation['valid'] = false;
        }

        return $validation;
    }

    /**
     * Validate image for specific platform
     */
    private function validate_image_for_platform($platform, $image_url) {
        $validation = array('valid' => true, 'issues' => array());
        
        // Get image dimensions (simplified)
        $image_info = @getimagesize($image_url);
        
        if ($image_info === false) {
            $validation['issues'][] = "Cannot read image dimensions";
            $validation['valid'] = false;
            return $validation;
        }

        list($width, $height, $type) = $image_info;
        $aspect_ratio = $width / $height;

        switch ($platform) {
            case 'instagram':
                // Instagram prefers square or tall images
                if ($width < 1080 || $height < 1080) {
                    $validation['issues'][] = "Instagram images should be at least 1080x1080px";
                }
                if ($aspect_ratio > 1.91) {
                    $validation['warnings'][] = "Instagram landscape images work best with 1.91:1 aspect ratio";
                }
                break;
                
            case 'twitter':
                // Twitter image requirements
                if ($width < 1200 || $height < 675) {
                    $validation['warnings'][] = "Twitter images work best at 1200x675px (16:9 ratio)";
                }
                break;
                
            case 'linkedin':
                // LinkedIn image recommendations
                if ($width < 1200 || $height < 627) {
                    $validation['warnings'][] = "LinkedIn images work best at 1200x627px (1.91:1 ratio)";
                }
                break;
        }

        if (!empty($validation['issues'])) {
            $validation['valid'] = false;
        }

        return $validation;
    }

    /**
     * Validate video for specific platform
     */
    private function validate_video_for_platform($platform, $video_url) {
        $validation = array('valid' => true, 'issues' => array());
        
        // This would typically involve checking video metadata
        // For now, just basic platform-specific checks
        
        switch ($platform) {
            case 'instagram':
                $validation['issues'][] = "Instagram video posts require a square format";
                $validation['valid'] = false;
                break;
                
            case 'twitter':
                $validation['suggestions'][] = "Twitter videos work best when under 2 minutes";
                break;
        }

        return $validation;
    }

    /**
     * Format content for Twitter (URLs, mentions, hashtags)
     */
    private function format_twitter_content($content) {
        // Convert URLs to blue links
        $content = preg_replace('/\b(https?:\/\/[^\s]+)/', '<span class="twitter-link">$1</span>', $content);
        
        // Convert mentions to blue links
        $content = preg_replace('/@(\w+)/', '<span class="twitter-mention">@$1</span>', $content);
        
        // Convert hashtags to blue links
        $content = preg_replace('/#(\w+)/', '<span class="twitter-hashtag">#$1</span>', $content);
        
        return $content;
    }

    /**
     * Format content for Mastodon
     */
    private function format_mastodon_content($content) {
        // Mastodon uses ActivityPub, so mentions and hashtags work differently
        $content = preg_replace('/@(\w+)@([a-zA-Z0-9.-]+)/', '<span class="mastodon-mention">@$1@$2</span>', $content);
        $content = preg_replace('/@(\w+)/', '<span class="mastodon-mention">@$1</span>', $content);
        $content = preg_replace('/#(\w+)/', '<span class="mastodon-hashtag">#$1</span>', $content);
        
        return $content;
    }

    /**
     * Process media for specific platforms
     */
    private function process_media_for_twitter($media) {
        $processed = array();
        
        foreach ($media as $item) {
            if ($item['type'] === 'image') {
                $processed[] = array(
                    'type' => 'image',
                    'url' => $item['url'],
                    'alt' => $item['alt'] ?? ''
                );
            }
        }
        
        return $processed;
    }

    private function process_media_for_facebook($media) {
        return $media;
    }

    private function process_media_for_instagram($media) {
        return array_slice($media, 0, 10); // Instagram allows up to 10 media items
    }

    private function process_media_for_linkedin($media) {
        return array_slice($media, 0, 9); // LinkedIn allows up to 9 media items
    }

    private function process_media_for_mastodon($media) {
        return array_slice($media, 0, 4); // Mastodon allows up to 4 media items
    }

    private function process_media_for_reddit($media) {
        return $media;
    }

    /**
     * Extract hashtags from content
     */
    private function extract_hashtags($content) {
        preg_match_all('/#(\w+)/', $content, $matches);
        return $matches[1];
    }

    /**
     * Enhanced preview engine with caching and real-time updates
     */
    private $preview_cache = array();
    private $preview_cache_duration = 300; // 5 minutes

    /**
     * Get cached preview data
     */
    private function get_cached_preview($cache_key) {
        if (isset($this->preview_cache[$cache_key])) {
            return $this->preview_cache[$cache_key];
        }
        return false;
    }

    /**
     * Set cached preview data
     */
    private function set_cached_preview($cache_key, $data) {
        $this->preview_cache[$cache_key] = array(
            'data' => $data,
            'timestamp' => time()
        );
        
        // Clean old cache entries
        $this->clean_preview_cache();
    }

    /**
     * Clean expired cache entries
     */
    private function clean_preview_cache() {
        $current_time = time();
        foreach ($this->preview_cache as $key => $cache_entry) {
            if ($current_time - $cache_entry['timestamp'] > $this->preview_cache_duration) {
                unset($this->preview_cache[$key]);
            }
        }
    }

    /**
     * Generate link preview data
     */
    public function generate_link_preview($url) {
        // Simulate link preview generation (in real implementation, would fetch URL metadata)
        $link_data = array(
            'url' => $url,
            'title' => 'Link Preview Title',
            'description' => 'Link preview description would be extracted from the webpage.',
            'image' => 'https://via.placeholder.com/400x200',
            'domain' => parse_url($url, PHP_URL_HOST)
        );
        
        return $link_data;
    }

    /**
     * Process content for hashtag highlighting
     */
    private function highlight_hashtags($content) {
        // Highlight hashtags with platform-specific styling
        $content = preg_replace('/#(\w+)/', '<span class="smo-hashtag" data-tag="$1">#$1</span>', $content);
        return $content;
    }

    /**
     * Get character count with platform-specific calculations
     */
    public function get_character_count($content, $platform) {
        switch ($platform) {
            case 'twitter':
                // Twitter counts URLs as 23 characters
                $url_pattern = '/https?:\/\/[^\s]+/';
                $url_matches = array();
                preg_match_all($url_pattern, $content, $url_matches);
                
                $char_count = strlen($content);
                foreach ($url_matches[0] as $url) {
                    $char_count -= strlen($url);
                    $char_count += 23; // Twitter URL length
                }
                return $char_count;
                
            default:
                return strlen($content);
        }
    }

    /**
     * Add preview JavaScript with enhanced features
     */
    public function add_preview_javascript() {
        if (!\is_admin() || !isset($_GET['page']) || strpos($_GET['page'], 'smo-social') === false) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            window.SMOPreviewSystem = {
                // Preview configuration
                currentView: 'desktop', // 'desktop' or 'mobile'
                previewMode: 'platform', // 'platform' or 'timeline'
                autoUpdate: true,
                
                // Platform configurations
                platformConfigs: {
                    twitter: { charLimit: 280, style: 'light' },
                    facebook: { charLimit: 2200, style: 'light' },
                    instagram: { charLimit: 2200, style: 'light' },
                    linkedin: { charLimit: 3000, style: 'light' },
                    mastodon: { charLimit: 500, style: 'dark' }
                },

                // Initialize preview system
                init: function() {
                    this.bindEvents();
                    this.loadInitialPreviews();
                },

                // Bind event handlers
                bindEvents: function() {
                    // Content changes
                    $(document).on('input', '#smo-post-content', this.debounce(this.updatePreviews.bind(this), 500));
                    $(document).on('change', '.smo-platform-checkbox input', this.updatePreviews.bind(this));
                    
                    // View mode toggles
                    $(document).on('click', '.smo-view-toggle[data-view="desktop"]', this.setViewMode.bind(this, 'desktop'));
                    $(document).on('click', '.smo-view-toggle[data-view="mobile"]', this.setViewMode.bind(this, 'mobile'));
                    
                    // Media upload
                    $(document).on('change', '.smo-media-upload', this.handleMediaUpload.bind(this));
                    
                    // Preview refresh
                    $(document).on('click', '.smo-refresh-preview', this.updatePreviews.bind(this));
                },

                // Debounce function for performance
                debounce: function(func, wait) {
                    let timeout;
                    return function executedFunction(...args) {
                        const later = () => {
                            clearTimeout(timeout);
                            func(...args);
                        };
                        clearTimeout(timeout);
                        timeout = setTimeout(later, wait);
                    };
                },

                // Load initial previews
                loadInitialPreviews: function() {
                    this.updatePreviews();
                },

                // Set view mode (desktop/mobile)
                setViewMode: function(mode) {
                    this.currentView = mode;
                    $('.smo-preview-container').removeClass('desktop mobile').addClass(mode);
                    $('.smo-view-toggle').removeClass('active').filter(`[data-view="${mode}"]`).addClass('active');
                },

                // Handle media upload
                handleMediaUpload: function(event) {
                    const files = event.target.files;
                    const mediaArray = [];
                    
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        const reader = new FileReader();
                        
                        reader.onload = (e) => {
                            mediaArray.push({
                                url: e.target.result,
                                type: file.type.startsWith('image/') ? 'image' : 'video',
                                alt: file.name
                            });
                            
                            if (mediaArray.length === files.length) {
                                window.SMOPreviewSystem.updatePreviews(mediaArray);
                            }
                        };
                        
                        reader.readAsDataURL(file);
                    }
                },

                // Update all previews
                updatePreviews: function(media = null) {
                    if (!this.autoUpdate) return;
                    
                    const content = $('#smo-post-content').val();
                    const platforms = [];
                    
                    $('.smo-platform-checkbox input:checked').each(function() {
                        platforms.push($(this).val());
                    });
                    
                    if (content && platforms.length > 0) {
                        const mediaData = media || this.getCurrentMedia();
                        this.generatePreviews(content, mediaData, platforms);
                    }
                },

                // Get current media data
                getCurrentMedia: function() {
                    const media = [];
                    $('.smo-media-item').each(function() {
                        media.push({
                            url: $(this).find('img').attr('src'),
                            type: $(this).data('type'),
                            alt: $(this).find('img').attr('alt')
                        });
                    });
                    return media;
                },

                // Generate previews via AJAX
                generatePreviews: function(content, media, platforms) {
                    const cacheKey = this.generateCacheKey(content, media, platforms);
                    
                    // Check cache first
                    const cached = this.getFromCache(cacheKey);
                    if (cached) {
                        this.renderPreviews(cached);
                        return;
                    }
                    
                    $.post(ajaxurl, {
                        action: 'smo_get_platform_preview',
                        content: content,
                        media: media,
                        platforms: platforms,
                        view_mode: this.currentView,
                        nonce: '<?php echo \wp_create_nonce("smo_social_nonce"); ?>'
                    }, (response) => {
                        if (response.success) {
                            this.saveToCache(cacheKey, response.data);
                            this.renderPreviews(response.data);
                        }
                    });
                },

                // Generate cache key
                generateCacheKey: function(content, media, platforms) {
                    return btoa(content + JSON.stringify(media) + JSON.stringify(platforms) + this.currentView);
                },

                // Cache management
                getFromCache: function(key) {
                    const cached = sessionStorage.getItem('smo_preview_' + key);
                    if (cached) {
                        const data = JSON.parse(cached);
                        if (Date.now() - data.timestamp < 300000) { // 5 minutes
                            return data.content;
                        }
                    }
                    return null;
                },

                saveToCache: function(key, content) {
                    sessionStorage.setItem('smo_preview_' + key, JSON.stringify({
                        content: content,
                        timestamp: Date.now()
                    }));
                },

                // Render previews
                renderPreviews: function(previews) {
                    $.each(previews, (platform, data) => {
                        this.renderPlatformPreview(platform, data);
                    });
                },

                // Render individual platform preview
                renderPlatformPreview: function(platform, data) {
                    const $container = $(`.smo-preview-content[data-platform="${platform}"]`);
                    if ($container.length === 0) return;

                    $container.html(this.generatePreviewHTML(data));
                    this.updateCharacterCount(platform, data);
                    this.updateValidationStatus(platform, data.validation);
                    this.processLinkPreviews(platform, data);
                    this.highlightHashtags(platform, data);
                },

                // Generate platform-specific preview HTML
                generatePreviewHTML: function(data) {
                    switch (data.platform) {
                        case 'twitter':
                            return this.generateTwitterHTML(data.preview);
                        case 'facebook':
                            return this.generateFacebookHTML(data.preview);
                        case 'instagram':
                            return this.generateInstagramHTML(data.preview);
                        case 'linkedin':
                            return this.generateLinkedInHTML(data.preview);
                        case 'mastodon':
                            return this.generateMastodonHTML(data.preview);
                        default:
                            return this.generateGenericHTML(data.preview);
                    }
                },

                // Enhanced Twitter preview
                generateTwitterHTML: function(preview) {
                    return `
                        <div class="smo-twitter-preview ${this.currentView}" style="${this.generateStyleString(preview.style)}">
                            <div class="smo-twitter-header">
                                <img src="${preview.header.avatar}" class="smo-twitter-avatar" alt="Avatar">
                                <div class="smo-twitter-user-info">
                                    <div class="smo-twitter-display-name">${preview.header.display_name}</div>
                                    <div class="smo-twitter-username">${preview.header.username}</div>
                                </div>
                                ${preview.header.verified ? '<span class="smo-verified">✓</span>' : ''}
                            </div>
                            <div class="smo-twitter-content">
                                <div class="smo-twitter-text">${this.formatTwitterContent(preview.content.text)}</div>
                                ${this.generateMediaHTML(preview.content.media)}
                                ${this.generateLinkPreviewHTML(preview.content.links)}
                            </div>
                            <div class="smo-twitter-stats">
                                <span class="smo-stat">${preview.content.stats.replies} replies</span>
                                <span class="smo-stat">${preview.content.stats.retweets} retweets</span>
                                <span class="smo-stat">${preview.content.stats.likes} likes</span>
                            </div>
                        </div>
                    `;
                },

                // Format Twitter content with proper styling
                formatTwitterContent: function(text) {
                    return text
                        .replace(/\b(https?:\/\/[^\s]+)/g, '<span class="smo-link">$1</span>')
                        .replace(/@(\w+)/g, '<span class="smo-mention">@$1</span>')
                        .replace(/#(\w+)/g, '<span class="smo-hashtag" data-tag="$1">#$1</span>');
                },

                // Generate link preview HTML
                generateLinkPreviewHTML: function(links) {
                    if (!links || links.length === 0) return '';
                    
                    let html = '<div class="smo-link-preview">';
                    links.forEach(link => {
                        html += `
                            <div class="smo-link-card">
                                <div class="smo-link-image">
                                    <img src="${link.image}" alt="">
                                </div>
                                <div class="smo-link-content">
                                    <div class="smo-link-title">${link.title}</div>
                                    <div class="smo-link-description">${link.description}</div>
                                    <div class="smo-link-domain">${link.domain}</div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    return html;
                },

                // Enhanced Facebook preview
                generateFacebookHTML: function(preview) {
                    return `
                        <div class="smo-facebook-preview ${this.currentView}" style="${this.generateStyleString(preview.style)}">
                            <div class="smo-facebook-header">
                                <img src="${preview.header.avatar}" class="smo-facebook-avatar" alt="Avatar">
                                <div class="smo-facebook-user-info">
                                    <div class="smo-facebook-username">${preview.header.username}</div>
                                    <div class="smo-facebook-timestamp">${preview.header.timestamp}</div>
                                </div>
                            </div>
                            <div class="smo-facebook-content">
                                <div class="smo-facebook-text">${preview.content.text}</div>
                                ${this.generateMediaHTML(preview.content.media)}
                                ${this.generateLinkPreviewHTML(preview.content.links)}
                            </div>
                            <div class="smo-facebook-stats">
                                <span>${preview.content.stats.likes} likes</span>
                                <span>${preview.content.stats.shares} shares</span>
                                <span>${preview.content.stats.comments} comments</span>
                            </div>
                        </div>
                    `;
                },

                // Enhanced Instagram preview
                generateInstagramHTML: function(preview) {
                    return `
                        <div class="smo-instagram-preview ${this.currentView}" style="${this.generateStyleString(preview.style)}">
                            <div class="smo-instagram-header">
                                <img src="${preview.header.avatar}" class="smo-instagram-avatar" alt="Avatar">
                                <div class="smo-instagram-user-info">
                                    <div class="smo-instagram-username">${preview.header.username}</div>
                                    ${preview.header.verified ? '<span class="smo-verified">✓</span>' : ''}
                                </div>
                            </div>
                            <div class="smo-instagram-media">
                                <img src="${preview.content.image}" alt="">
                                ${preview.content.carousel ? this.generateCarouselHTML(preview.content.carousel) : ''}
                            </div>
                            <div class="smo-instagram-stats">
                                <span>❤️ ${preview.content.stats.likes}</span>
                                <span>💬 ${preview.content.stats.comments}</span>
                            </div>
                            <div class="smo-instagram-caption">
                                <strong>${preview.header.username}</strong> ${preview.content.caption}
                            </div>
                        </div>
                    `;
                },

                // Generate carousel for Instagram
                generateCarouselHTML: function(carousel) {
                    let html = '<div class="smo-carousel">';
                    carousel.forEach((item, index) => {
                        html += `<img src="${item.url}" alt="${item.alt || ''}" class="${index === 0 ? 'active' : ''}">`;
                    });
                    html += '</div>';
                    return html;
                },

                // Generate style string
                generateStyleString: function(style) {
                    const styles = [];
                    if (style.max_width) styles.push(`max-width: ${style.max_width}`);
                    if (style.background) styles.push(`background: ${style.background}`);
                    if (style.text_color) styles.push(`color: ${style.text_color}`);
                    if (style.border) styles.push(`border: 1px solid ${style.border}`);
                    
                    // Add responsive styles
                    if (this.currentView === 'mobile') {
                        styles.push('max-width: 375px');
                        styles.push('margin: 0 auto');
                    }
                    
                    return styles.join('; ');
                },

                // Generate media HTML
                generateMediaHTML: function(media) {
                    if (!media || media.length === 0) return '';
                    
                    let html = '<div class="smo-preview-media">';
                    media.forEach(item => {
                        if (item.type === 'image') {
                            html += `<img src="${item.url}" alt="${item.alt || ''}" class="smo-preview-image">`;
                        } else if (item.type === 'video') {
                            html += `<video src="${item.url}" class="smo-preview-video" controls></video>`;
                        }
                    });
                    html += '</div>';
                    return html;
                },

                // Update character count display
                updateCharacterCount: function(platform, data) {
                    const charCount = data.character_count;
                    const charLimit = data.character_limit;
                    const percentage = (charCount / charLimit) * 100;
                    
                    const $countDisplay = $(`.smo-char-count[data-platform="${platform}"]`);
                    $countDisplay.find('.count').text(`${charCount}/${charLimit}`);
                    $countDisplay.find('.bar').css('width', `${Math.min(percentage, 100)}%`);
                    $countDisplay.removeClass('warning error').addClass(percentage > 90 ? 'error' : percentage > 80 ? 'warning' : '');
                },

                // Process link previews
                processLinkPreviews: function(platform, data) {
                    // Extract URLs from content and generate preview data
                    const urlRegex = /\bhttps?:\/\/[^\s]+/g;
                    const content = data.preview.content.text || '';
                    const matches = content.match(urlRegex);
                    
                    if (matches) {
                        data.preview.content.links = matches.map(url => this.generateLinkPreviewData(url));
                    }
                },

                // Generate link preview data (simulated)
                generateLinkPreviewData: function(url) {
                    return {
                        url: url,
                        title: 'Preview Title',
                        description: 'Preview description would be extracted from the webpage.',
                        image: 'https://via.placeholder.com/400x200',
                        domain: new URL(url).hostname
                    };
                },

                // Highlight hashtags
                highlightHashtags: function(platform, data) {
                    const $container = $(`.smo-preview-content[data-platform="${platform}"]`);
                    $container.find('.smo-hashtag').on('click', function() {
                        const tag = $(this).data('tag');
                        // Could open hashtag research or filter content
                        console.log('Hashtag clicked:', tag);
                    });
                },

                // Update validation status
                updateValidationStatus: function(platform, validation) {
                    const $container = $(`.smo-preview-content[data-platform="${platform}"]`);
                    
                    $container.find('.smo-validation-message').remove();
                    
                    if (validation.errors && validation.errors.length > 0) {
                        validation.errors.forEach(error => {
                            $container.append(`<div class="smo-validation-message smo-error">${error}</div>`);
                        });
                    }
                    
                    if (validation.warnings && validation.warnings.length > 0) {
                        validation.warnings.forEach(warning => {
                            $container.append(`<div class="smo-validation-message smo-warning">${warning}</div>`);
                        });
                    }
                }
            };

            // Initialize the preview system
            window.SMOPreviewSystem.init();
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for getting platform previews
     */
    public function ajax_get_platform_preview() {
        \check_ajax_referer('smo_social_nonce', 'nonce');
        
        // Safely get POST data with fallback values
        $content = isset($_POST['content']) ? \sanitize_textarea_field($_POST['content']) : '';
        $media = isset($_POST['media']) ? array_map('\esc_url_raw', $_POST['media']) : array();
        $platforms = isset($_POST['platforms']) ? array_map('\sanitize_text_field', $_POST['platforms']) : array();
        
        $previews = $this->get_previews($content, $media, $platforms);
        
        \wp_send_json_success($previews);
    }

    /**
     * AJAX handler for media validation
     */
    public function ajax_validate_media() {
        \check_ajax_referer('smo_social_nonce', 'nonce');
        
        // Safely get POST data with fallback values
        $platform = isset($_POST['platform']) ? \sanitize_text_field($_POST['platform']) : '';
        $media_url = isset($_POST['media_url']) ? \esc_url_raw($_POST['media_url']) : '';
        
        // Validate required parameters
        if (empty($platform)) {
            \wp_send_json_error('Platform parameter is required');
        }
        
        if (empty($media_url)) {
            \wp_send_json_error('Media URL parameter is required');
        }
        
        $platform_obj = $this->platform_manager->get_platform($platform);
        if (!$platform_obj) {
            \wp_send_json_error('Platform not found');
        }
        
        $validation = array('valid' => true, 'issues' => array());
        
        if (pathinfo($media_url, PATHINFO_EXTENSION) === 'mp4' || strpos($media_url, 'video') !== false) {
            $validation = $this->validate_video_for_platform($platform, $media_url);
        } else {
            $validation = $this->validate_image_for_platform($platform, $media_url);
        }
        
        \wp_send_json_success($validation);
    }
}
