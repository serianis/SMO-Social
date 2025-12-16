<?php
namespace SMO_Social\Platforms;

class Instagram extends Platform {
    public function __construct($config = array()) {
        $default_config = array(
            'slug' => 'instagram',
            'name' => 'Instagram',
            'api_base' => 'https://graph.instagram.com/v18.0',
            'auth_type' => 'oauth',
            'max_chars' => 2200,
            'supports_images' => true,
            'supports_videos' => true,
            'rate_limit' => 200,
            'features' => array('posts', 'stories', 'reels', 'igtv')
        );

        $config = array_merge($default_config, $config);
        parent::__construct($config);
    }

    public function is_authenticated() {
        $token_data = $this->get_stored_token();
        return $token_data !== null;
    }

    /**
     * Create media post on Instagram
     *
     * @param array $data Media data containing caption and other options
     * @return array Response with media ID or error
     */
    public function create_media($data) {
        if ($this->is_test_mode($data)) {
            return $this->get_test_response('id');
        }
        return $this->post($data['caption'], $data);
    }

    public function get_insights($data) {
        // For testing purposes, return mock data
        return array(
            'impressions' => array('value' => 100),
            'reach' => array('value' => 80),
            'engagement' => array('value' => 20)
        );
    }

    public function get_media_insights($media_id) {
        // Implementation for getting media insights
        return array();
    }

    /**
     * Post a reel on Instagram
     * Note: Reel posting functionality not yet implemented
     *
     * @param string $content Reel content
     * @param array $options Additional options
     * @return array Response with success status and reel ID or error
     */
    public function post_reel($content, $options = array()) {
        if ($this->is_test_mode($options)) {
            return $this->get_test_response('reel_id');
        }
        
        return $this->get_not_implemented_response('Reel posting');
    }

    public function get_user_insights() {
        // Implementation for getting user insights
        return array();
    }
}
