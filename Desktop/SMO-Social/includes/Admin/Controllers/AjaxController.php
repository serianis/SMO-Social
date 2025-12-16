<?php
namespace SMO_Social\Admin\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajax Controller
 * 
 * Manages registration of all AJAX handlers in the plugin.
 */
class AjaxController {
    
    /**
     * Registered handler instances
     * @var array
     */
    private $handlers = [];

    /**
     * Initialize AJAX handlers
     */
    public function init() {
        // Only load if doing AJAX
        if (!\wp_doing_ajax()) {
            return;
        }

        $this->register_handlers();
    }

    /**
     * Register individual handler classes
     */
    private function register_handlers() {
        $handlers = [
            \SMO_Social\Admin\Ajax\DashboardAjax::class,
            \SMO_Social\Admin\Ajax\PlatformAjax::class,
            \SMO_Social\Admin\Ajax\AiProviderAjax::class,
            \SMO_Social\Admin\Ajax\ContentOrganizerAjax::class,
            \SMO_Social\Admin\Ajax\WorkflowAjax::class,
            // Add more handlers here as we create them
        ];

        foreach ($handlers as $handler_class) {
            if (class_exists($handler_class)) {
                $handler = new $handler_class();
                if (method_exists($handler, 'register')) {
                    $handler->register();
                    $this->handlers[] = $handler;
                }
            }
        }
    }
}
