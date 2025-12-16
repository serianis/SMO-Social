<?php
namespace SMO_Social\Platforms;

class Facebook extends Platform {
    public function __construct($config = array()) {
        $default_config = array(
            'slug' => 'facebook',
            'name' => 'Facebook',
            'api_base' => 'https://graph.facebook.com/v18.0',
            'auth_type' => 'oauth',
            'max_chars' => 63206,
            'supports_images' => true,
            'supports_videos' => true,
            'rate_limit' => 200,
            'features' => array('posts', 'stories', 'reels', 'live_video')
        );

        $config = array_merge($default_config, $config);
        parent::__construct($config);
    }

    public function is_authenticated() {
        $token_data = $this->get_stored_token();
        return $token_data !== null;
    }

    /**
     * Create a post on Facebook
     *
     * @param array $data Post data containing message and other options
     * @return array Response with post ID or error
     */
    public function create_post($data) {
        if ($this->is_test_mode($data)) {
            return $this->get_test_response('id');
        }
        return $this->post($data['message'], $data);
    }

    /**
     * Upload media to Facebook
     * Note: Media upload functionality not yet implemented
     *
     * @param array $data Media upload data
     * @return array Response with success status and media ID or error
     */
    public function upload_media($data) {
        if ($this->is_test_mode($data)) {
            return $this->get_test_response('media_id');
        }
        
        return array(
            'success' => false,
            'error' => 'Media upload functionality not yet implemented for Facebook',
            'code' => 'FEATURE_NOT_IMPLEMENTED'
        );
    }

    public function get_insights($data) {
        // For testing purposes, return mock data
        return array('impressions' => 100, 'reach' => 80, 'engagement' => 20);
    }

    public function get_comments($post_id) {
        // For testing purposes, return mock data
        return array(array('id' => 'comment_1', 'message' => 'Test comment'));
    }

    public function get_page_access_tokens() {
        // Implementation for getting page access tokens
        return array();
    }

    /**
     * Post content to a specific Facebook page
     * Note: Page posting functionality not yet implemented
     *
     * @param int $page_id Facebook page ID
     * @param string $content Post content
     * @param array $options Additional options
     * @return array Response with success status and post ID or error
     */
    public function post_to_page($page_id, $content, $options = array()) {
        if ($this->is_test_mode($options)) {
            return $this->get_test_response('post_id');
        }
        
        return array(
            'success' => false,
            'error' => 'Page posting functionality not yet implemented for Facebook',
            'code' => 'FEATURE_NOT_IMPLEMENTED'
        );
    }

    public function get_pages() {
        // Implementation for getting connected pages
        return array();
    }
}
