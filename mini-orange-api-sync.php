<?php
/**
 * Plugin Name: Mini Orange API Sync
 * Description: Sync external vendor products (price, stock, images) into WooCommerce for multi-vendor stores.
 * Version: 0.1.0
 * Author: Your Name
 * Text Domain: mini-orange-api-sync
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MO_API_SYNC_VERSION', '0.1.0');
define('MO_API_SYNC_PATH', plugin_dir_path(__FILE__));
define('MO_API_SYNC_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, ['MO_Plugin_Init', 'activate']);

require_once MO_API_SYNC_PATH . 'includes/class-plugin-init.php';

function mo_api_sync_boot() {
    if (class_exists('MO_Plugin_Init')) {
        MO_Plugin_Init::instance();
    }
}
add_action('plugins_loaded', 'mo_api_sync_boot', 5);
