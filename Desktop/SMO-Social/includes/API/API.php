<?php
namespace SMO_Social\API;

class API {
    public function __construct() {
        // API endpoints initialization
        error_log('SMO Debug: API constructor called');

        // Initialize Chat API Controller
        if (class_exists('\SMO_Social\Chat\ChatAPIController')) {
            new \SMO_Social\Chat\ChatAPIController();
            error_log('SMO Debug: ChatAPIController instantiated');
        } else {
            error_log('SMO Debug: ChatAPIController class not found');
        }
    }
}