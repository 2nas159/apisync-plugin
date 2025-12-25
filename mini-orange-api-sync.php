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

/**
 * Plugin Constants
 */
define('MO_API_SYNC_VERSION', '0.1.0');
define('MO_API_SYNC_PATH', plugin_dir_path(__FILE__));
define('MO_API_SYNC_URL', plugin_dir_url(__FILE__));
define('MO_API_SYNC_BASENAME', plugin_basename(__FILE__));

/**
 * Load Core
 */
require_once MO_API_SYNC_PATH . 'includes/class-plugin-init.php';
require_once MO_API_SYNC_PATH . 'includes/class-installer.php';

register_activation_hook(__FILE__, ['MO_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['MO_Installer', 'deactivate']);

/**
 * Init Plugin
 */
function mo_api_sync_init() {
    return MO_Plugin_Init::instance();
}
mo_api_sync_init();
