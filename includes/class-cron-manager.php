<?php
if (!defined('ABSPATH')) exit;

class MO_Cron_Manager {

    private static $instance = null;

    const CRON_HOURLY_STOCK = 'mo_api_sync_hourly_stock';
    const CRON_DAILY_FULL  = 'mo_api_sync_daily_full';

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register_schedules();
        $this->register_events();
        $this->register_handlers();
    }

    /**
     * -----------------------------
     * Register custom schedules
     * -----------------------------
     */
    private function register_schedules() {
        add_filter('cron_schedules', function ($schedules) {

            if (!isset($schedules['mo_hourly'])) {
                $schedules['mo_hourly'] = [
                    'interval' => HOUR_IN_SECONDS,
                    'display'  => __('MO Hourly Sync', 'mini-orange-api-sync'),
                ];
            }

            if (!isset($schedules['mo_daily'])) {
                $schedules['mo_daily'] = [
                    'interval' => DAY_IN_SECONDS,
                    'display'  => __('MO Daily Sync', 'mini-orange-api-sync'),
                ];
            }

            return $schedules;
        });
    }

    /**
     * -----------------------------
     * Schedule events (if missing)
     * -----------------------------
     */
    private function register_events() {

        if (!wp_next_scheduled(self::CRON_HOURLY_STOCK)) {
            wp_schedule_event(time() + 300, 'mo_hourly', self::CRON_HOURLY_STOCK);
        }

        if (!wp_next_scheduled(self::CRON_DAILY_FULL)) {
            wp_schedule_event(time() + 600, 'mo_daily', self::CRON_DAILY_FULL);
        }
    }

    /**
     * -----------------------------
     * Attach handlers
     * -----------------------------
     */
    private function register_handlers() {

        // Hourly: stock + price refresh
        add_action(self::CRON_HOURLY_STOCK, [$this, 'run_hourly_stock_sync']);

        // Daily: full catalog sync
        add_action(self::CRON_DAILY_FULL, [$this, 'run_daily_full_sync']);

        // Manual triggers (admin / CLI / AJAX)
        add_action('mo_api_sync_run_all', [$this, 'manual_sync_all']);
        add_action('mo_api_sync_run_vendor', [$this, 'manual_sync_vendor'], 10, 2);
    }

    /**
     * -----------------------------
     * HOURLY STOCK SYNC
     * -----------------------------
     */
    public function run_hourly_stock_sync() {

        // Limit pages aggressively for safety
        $args = [
            'limit_per_page' => 50,
            'max_pages'      => 5,
        ];

        $vendors = MO_Vendor_Manager::instance()->list(true);

        foreach ($vendors as $v) {
            MO_Product_Mapper::instance()->sync_vendor(
                (int)$v['id'],
                $args
            );
        }
    }

    /**
     * -----------------------------
     * DAILY FULL SYNC
     * -----------------------------
     */
    public function run_daily_full_sync() {

        MO_Product_Mapper::instance()->sync_all([
            'limit_per_page' => 50,
            'max_pages'      => 50,
        ]);
    }

    /**
     * -----------------------------
     * MANUAL: Sync all vendors
     * -----------------------------
     */
    public function manual_sync_all() {
        return MO_Product_Mapper::instance()->sync_all();
    }

    /**
     * -----------------------------
     * MANUAL: Sync single vendor
     * -----------------------------
     */
    public function manual_sync_vendor($vendor_id, $args = []) {
        return MO_Product_Mapper::instance()->sync_vendor((int)$vendor_id, (array)$args);
    }
}
