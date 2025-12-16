<?php
namespace SMO_Social\Core;

class Deactivator {
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Cancel scheduled events
        wp_clear_scheduled_hook('smo_social_process_queue');
    }
}