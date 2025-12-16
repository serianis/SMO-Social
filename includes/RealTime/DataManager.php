<?php
/**
 * Real-Time Data Manager
 * Διαχειρίζεται τα data structures για real-time functionality
 */

namespace SMO_Social\RealTime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Manager
 * Handles data storage and retrieval for real-time channels and messages
 */
class DataManager {
    /** @var array */
    private $channels = [];
    /** @var array */
    private $messages = [];
    /** @var array */
    private $subscribers = [];
    /** @var array */
    private $statistics = [];

    public function __construct() {
        $this->load_data();
    }

    /**
     * Initialize the data manager
     */
    public function initialize() {
        $this->load_data();
        error_log('SMO RealTime: DataManager initialized');
    }

    /**
     * Load data from WordPress options
     */
    private function load_data() {
        $this->channels = get_option('smo_realtime_channels', []);
        if (!is_array($this->channels)) {
            $this->channels = [];
        }

        $this->messages = get_option('smo_realtime_messages', []);
        if (!is_array($this->messages)) {
            $this->messages = [];
        }

        $this->subscribers = get_option('smo_realtime_subscribers', []);
        if (!is_array($this->subscribers)) {
            $this->subscribers = [];
        }

        $this->statistics = get_option('smo_realtime_statistics', [
            'total_messages' => 0,
            'total_channels' => 0,
            'total_subscribers' => 0,
            'active_channels' => 0,
            'last_activity' => null
        ]);
        if (!is_array($this->statistics)) {
            $this->statistics = [];
        }
    }

    /**
     * Save data to WordPress options
     */
    private function save_data() {
        update_option('smo_realtime_channels', $this->channels);
        update_option('smo_realtime_messages', $this->messages);
        update_option('smo_realtime_subscribers', $this->subscribers);
        update_option('smo_realtime_statistics', $this->statistics);
    }

    /**
     * Subscribe user to channel
     */
    public function subscribe_to_channel($channel, $user_id) {
        // Validate channel name
        if (!$this->is_valid_channel($channel)) {
            return false;
        }

        // Initialize channel if it doesn't exist
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [
                'created_at' => current_time('mysql'),
                'last_activity' => current_time('mysql'),
                'message_count' => 0
            ];
        }

        // Add subscriber
        if (!isset($this->subscribers[$channel])) {
            $this->subscribers[$channel] = [];
        }

        if (!in_array($user_id, $this->subscribers[$channel])) {
            $this->subscribers[$channel][] = $user_id;
        }

        // Update channel last activity
        $this->channels[$channel]['last_activity'] = current_time('mysql');

        // Update statistics
        $this->update_statistics();

        $this->save_data();
        
        error_log("SMO RealTime: User {$user_id} subscribed to channel {$channel}");
        return true;
    }

    /**
     * Unsubscribe user from channel
     */
    public function unsubscribe_from_channel($channel, $user_id) {
        if (isset($this->subscribers[$channel])) {
            $key = array_search($user_id, $this->subscribers[$channel]);
            if ($key !== false) {
                unset($this->subscribers[$channel][$key]);
                
                // Reindex array
                $this->subscribers[$channel] = array_values($this->subscribers[$channel]);
                
                // Remove channel if no subscribers
                if (empty($this->subscribers[$channel])) {
                    unset($this->subscribers[$channel]);
                }
            }
        }

        $this->save_data();
        error_log("SMO RealTime: User {$user_id} unsubscribed from channel {$channel}");
        return true;
    }

    /**
     * Add message to channel
     */
    public function add_message_to_channel($channel, $message) {
        // Validate channel
        if (!$this->is_valid_channel($channel)) {
            return false;
        }

        // Initialize channel if it doesn't exist
        if (!isset($this->channels[$channel])) {
            $this->subscribe_to_channel($channel, $message['user_id']);
        }

        // Initialize messages array for channel
        if (!isset($this->messages[$channel])) {
            $this->messages[$channel] = [];
        }

        // Add message with timestamp
        $message['received_at'] = current_time('mysql');
        $this->messages[$channel][] = $message;

        // Keep only recent messages (last 100 per channel)
        if (count($this->messages[$channel]) > 100) {
            $this->messages[$channel] = array_slice($this->messages[$channel], -100);
        }

        // Update channel statistics
        if (isset($this->channels[$channel])) {
            $this->channels[$channel]['last_activity'] = current_time('mysql');
            $this->channels[$channel]['message_count']++;
        }

        // Update global statistics
        $this->statistics['total_messages']++;
        $this->statistics['last_activity'] = current_time('mysql');

        $this->save_data();
        
        error_log("SMO RealTime: Message added to channel {$channel}");
        return true;
    }

    /**
     * Get messages for channel
     */
    public function get_channel_messages($channel, $since = null) {
        if (!isset($this->messages[$channel])) {
            return [];
        }

        $messages = $this->messages[$channel];

        // Filter by timestamp if provided
        if ($since) {
            $since_timestamp = strtotime($since);
            $messages = array_filter($messages, function($msg) use ($since_timestamp) {
                return strtotime($msg['received_at']) > $since_timestamp;
            });
        }

        // Return last 50 messages
        return array_slice(array_values($messages), -50);
    }

    /**
     * Check if user is subscribed to channel
     */
    public function is_user_subscribed($channel, $user_id) {
        return isset($this->subscribers[$channel]) && 
               in_array($user_id, $this->subscribers[$channel]);
    }

    /**
     * Get subscribers for channel
     */
    public function get_channel_subscribers($channel) {
        return isset($this->subscribers[$channel]) ? $this->subscribers[$channel] : [];
    }

    /**
     * Get all channels
     */
    public function get_channels() {
        return $this->channels;
    }

    /**
     * Get active channels (with recent activity)
     */
    public function get_active_channels($within_minutes = 60) {
        $cutoff_time = time() - ($within_minutes * 60);
        $active_channels = [];

        foreach ($this->channels as $channel => $info) {
            if (strtotime($info['last_activity']) > $cutoff_time) {
                $active_channels[$channel] = $info;
            }
        }

        return $active_channels;
    }

    /**
     * Get statistics
     */
    public function get_statistics() {
        $this->update_statistics();
        
        return array_merge($this->statistics, [
            'active_channels' => count($this->get_active_channels()),
            'total_subscribers' => $this->get_total_subscriber_count(),
            'channels_with_subscribers' => count(array_filter($this->subscribers))
        ]);
    }

    /**
     * Update statistics
     */
    private function update_statistics() {
        $this->statistics['total_channels'] = count($this->channels);
        $this->statistics['total_subscribers'] = $this->get_total_subscriber_count();
    }

    /**
     * Get total subscriber count across all channels
     */
    private function get_total_subscriber_count() {
        $total = 0;
        foreach ($this->subscribers as $channel_subscribers) {
            $total += count($channel_subscribers);
        }
        return $total;
    }

    /**
     * Cleanup old data
     */
    public function cleanup_old_data($max_age = 3600) {
        $cutoff_time = time() - $max_age;
        $cleaned_messages = 0;
        $cleaned_channels = 0;

        // Clean old messages
        foreach ($this->messages as $channel => $messages) {
            $this->messages[$channel] = array_filter($messages, function($msg) use ($cutoff_time) {
                return strtotime($msg['received_at']) > $cutoff_time;
            });

            // Remove channel if no messages left
            if (empty($this->messages[$channel])) {
                unset($this->messages[$channel]);
                $cleaned_messages++;
            }
        }

        // Clean inactive channels
        foreach ($this->channels as $channel => $info) {
            if (strtotime($info['last_activity']) < $cutoff_time) {
                unset($this->channels[$channel]);
                unset($this->subscribers[$channel]);
                $cleaned_channels++;
            }
        }

        $this->save_data();
        
        error_log("SMO RealTime: Cleanup completed - removed {$cleaned_channels} channels, {$cleaned_messages} empty message arrays");
        
        return [
            'cleaned_channels' => $cleaned_channels,
            'cleaned_messages' => $cleaned_messages,
            'remaining_channels' => count($this->channels),
            'remaining_subscribers' => $this->get_total_subscriber_count()
        ];
    }

    /**
     * Validate channel name
     */
    private function is_valid_channel($channel) {
        // Channel must be string, not empty, and within reasonable length
        if (!is_string($channel) || empty($channel) || strlen($channel) > 100) {
            return false;
        }

        // Allow only alphanumeric, hyphens, underscores, and dots
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $channel)) {
            return false;
        }

        return true;
    }

    /**
     * Get channel info
     */
    public function get_channel_info($channel) {
        if (!isset($this->channels[$channel])) {
            return null;
        }

        return array_merge($this->channels[$channel], [
            'subscriber_count' => count($this->get_channel_subscribers($channel)),
            'message_count' => isset($this->messages[$channel]) ? count($this->messages[$channel]) : 0
        ]);
    }

    /**
     * Force save data (for testing)
     */
    public function force_save() {
        $this->save_data();
        return true;
    }

    /**
     * Reset all data (for testing)
     */
    public function reset_data() {
        $this->channels = [];
        $this->messages = [];
        $this->subscribers = [];
        $this->statistics = [
            'total_messages' => 0,
            'total_channels' => 0,
            'total_subscribers' => 0,
            'active_channels' => 0,
            'last_activity' => null
        ];
        
        $this->save_data();
        error_log('SMO RealTime: All data reset');
        return true;
    }
}