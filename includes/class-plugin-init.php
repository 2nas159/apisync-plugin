<?php
if (!defined('ABSPATH')) {
    exit;
}

class MO_Plugin_Init
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mo_api_vendors';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(191) NOT NULL,
        api_type VARCHAR(100) NOT NULL,
        api_base_url TEXT NOT NULL,
        api_key TEXT NULL,
        settings LONGTEXT NULL,
        last_sync_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function __construct()
    {
        $this->includes();

        // Run boot late enough to avoid "too early" warnings and to ensure Woo is loaded.
        add_action('init', [$this, 'boot'], 20);
    }

    private function includes()
    {
        // Core
        require_once MO_API_SYNC_PATH . 'includes/class-api-manager.php';
        require_once MO_API_SYNC_PATH . 'includes/class-vendor-manager.php';
        require_once MO_API_SYNC_PATH . 'includes/class-product-mapper.php';
        require_once MO_API_SYNC_PATH . 'includes/class-woocommerce-sync.php';
        require_once MO_API_SYNC_PATH . 'includes/class-cron-manager.php';

        // Vendor API adapters
        require_once MO_API_SYNC_PATH . 'vendor-apis/abstract-api-adapter.php';

        // Admin
        if (is_admin()) {
            require_once MO_API_SYNC_PATH . 'admin/class-admin-menu.php';
        }
    }

    public function boot()
    {
        // Ensure WooCommerce exists (admin notice only; do not hard-crash)
        if (!class_exists('WooCommerce')) {
            if (is_admin()) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p><strong>Mini Orange API Sync</strong> requires WooCommerce.</p></div>';
                });
            }
            return;
        }

        // Initialize core singletons
        if (class_exists('MO_API_Manager'))
            MO_API_Manager::instance();
        if (class_exists('MO_Vendor_Manager'))
            MO_Vendor_Manager::instance();
        if (class_exists('MO_Product_Mapper'))
            MO_Product_Mapper::instance();
        if (class_exists('MO_WooCommerce_Sync'))
            MO_WooCommerce_Sync::instance();
        if (class_exists('MO_Cron_Manager'))
            MO_Cron_Manager::instance();

        // Admin UI
        if (is_admin() && class_exists('MO_Admin_Menu')) {
            MO_Admin_Menu::instance();
        }
    }
}
