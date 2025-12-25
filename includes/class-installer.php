<?php
if (!defined('ABSPATH')) exit;

class MO_Installer {

    const DB_VERSION = '1.0';

    public static function activate() {
        self::create_tables();
        self::set_db_version();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private static function set_db_version() {
        update_option('mo_api_sync_db_version', self::DB_VERSION);
    }

    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'mo_vendor_apis';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            vendor_name VARCHAR(190) NOT NULL,
            api_type VARCHAR(100) NOT NULL,
            api_base_url VARCHAR(255) NULL,
            api_key LONGTEXT NULL,
            settings LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_sync_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY wp_user_id (wp_user_id),
            KEY api_type (api_type),
            KEY is_active (is_active)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
