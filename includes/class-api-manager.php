<?php

if (!defined('ABSPATH')) {
    exit;
}

class MO_API_Manager {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get API adapter instance
     */
    public function get_adapter($api_type, $settings) {

        $adapter_file = MO_API_SYNC_PATH . "vendor-apis/{$api_type}.php";

        if (!file_exists($adapter_file)) {
            throw new Exception("API adapter not found: {$api_type}");
        }

        require_once $adapter_file;

        $class_name = 'MO_' . ucfirst($api_type) . '_API';

        if (!class_exists($class_name)) {
            throw new Exception("API adapter class missing: {$class_name}");
        }

        return new $class_name($settings);
    }
}
