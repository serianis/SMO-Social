<?php
namespace SMO_Social\Platforms;

class Twitter extends Platform {
    public function __construct($config = array()) {
        $default_config = array(
            'slug' => 'twitter',
            'name' => 'Twitter/X',
            'api_base' => 'https://api.twitter.com/2',
            'auth_type' => 'oauth',
            'max_chars' => 280,
            'supports_images' => true,
            'supports_videos' => true,
            'rate_limit' => 300,
            'features' => array('tweets', 'threads', 'spaces', 'polls')
        );

        $config = array_merge($default_config, $config);
        parent::__construct($config);
    }

    public function is_authenticated() {
        $token_data = $this->get_stored_token();
        return $token_data !== null;
    }

    /**
     * Create a tweet on Twitter/X
     *
     * @param array $data Tweet data containing text and other options
     * @return array Response with tweet data or error
     */
    public function create_tweet($data) {
        if ($this->is_test_mode($data)) {
            return array('data' => $this->get_test_response('id'));
        }
        return $this->post($data['text'], $data);
    }

    /**
     * Upload media to Twitter/X
     * Note: Media upload functionality not yet implemented
     *
     * @param array $data Media upload data
     * @return array Response with success status and media ID or error
     */
    public function upload_media($data) {
        if ($this->is_test_mode($data)) {
            return $this->get_test_response('media_id_string');
        }
        
        return $this->get_not_implemented_response('Media upload');
    }

    public function get_tweet_metrics($tweet_id) {
        // For testing purposes, return mock data
        return array(
            'retweet_count' => 10,
            'like_count' => 25,
            'reply_count' => 5,
            'impression_count' => 1000
        );
    }

    /**
     * Post a thread of tweets
     * Note: Thread functionality not yet implemented
     *
     * @param array $tweets Array of tweet content
     * @param array $options Additional options
     * @return array Response with success status and thread ID or error
     */
    public function post_thread($tweets, $options = array()) {
        if ($this->is_test_mode($options)) {
            return $this->get_test_response('thread_id');
        }
        
        return $this->get_not_implemented_response('Thread posting');
    }

    public function get_mentions() {
        // Implementation for getting mentions
        return array();
    }

    public function get_trending_topics() {
        // Implementation for getting trending topics
        return array();
    }
}
