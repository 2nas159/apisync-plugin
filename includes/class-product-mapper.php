<?php
if (!defined('ABSPATH'))
    exit;

class MO_Product_Mapper
{

    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null)
            self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
    }

    public function sync_all(array $args = [])
    {
        $vendors = MO_Vendor_Manager::instance()->list(true);
        $results = [];

        foreach ($vendors as $v) {
            $results[] = $this->sync_vendor((int) $v['id'], $args);
        }

        return $results;
    }

    public function sync_vendor(int $vendor_id, array $args = [])
    {
        error_log('[MO_DEBUG_ARGS] ' . json_encode($args));

        $args = array_merge([
            'limit_per_page' => 50,
            'max_pages' => 50,
            'lock_ttl' => 900,
        ], $args);

        // dry_run must be explicitly true
        $dry_run = isset($args['dry_run']) && $args['dry_run'] === true;

        if (!$this->acquire_lock($vendor_id, (int) $args['lock_ttl'])) {
            $this->log('info', "Skipped sync: vendor {$vendor_id} is locked.");
            return ['vendor_id' => $vendor_id, 'status' => 'skipped', 'reason' => 'locked'];
        }

        $vendor = MO_Vendor_Manager::instance()->get_for_sync($vendor_id);
        if (!$vendor) {
            $this->release_lock($vendor_id);
            return ['vendor_id' => $vendor_id, 'status' => 'error', 'reason' => 'vendor_not_found'];
        }

        $seen_external_ids = [];

        try {

            $adapter = MO_API_Manager::instance()->get_adapter(
                $vendor['api_type'],
                [
                    'api_base_url' => $vendor['api_base_url'],
                    'api_key' => $vendor['api_key'],
                    'settings' => $vendor['settings'],
                ]
            );

            $page = 1;
            $created = $updated = $skipped = $errors = 0;

            $had_any_products = false;

            while ($page <= (int) $args['max_pages']) {

                try {
                    $raw_products = $adapter->fetch_products($page, (int) $args['limit_per_page']);
                } catch (Exception $e) {
                    $this->log('error', "Vendor {$vendor_id} page {$page} failed: " . $e->getMessage());
                    break;
                }

                if (!is_array($raw_products) || empty($raw_products)) {
                    break;
                }

                $had_any_products = true;

                foreach ($raw_products as $raw) {

                    $normalized = $adapter->normalize_product((array) $raw);
                    if (!$normalized) {
                        $skipped++;
                        continue;
                    }

                    $seen_external_ids[] = (string) $normalized['external_id'];

                    if ($dry_run) {
                        $this->log('info', 'Dry-run enabled, skipping upsert.');
                        $skipped++;
                        continue;
                    }

                    $this->log('info', 'Upserting ' . $normalized['external_id']);

                    $res = MO_WooCommerce_Sync::instance()
                        ->upsert_product($normalized, (int) $vendor['wp_user_id']);

                    if (($res['status'] ?? '') === 'created')
                        $created++;
                    elseif (($res['status'] ?? '') === 'updated')
                        $updated++;
                    elseif (($res['status'] ?? '') === 'skipped')
                        $skipped++;
                    else
                        $errors++;
                }

                $page++;
            }

            if ($had_any_products) {
                $this->handle_missing_products(
                    $vendor_id,
                    (int) $vendor['wp_user_id'],
                    $seen_external_ids,
                    $vendor['settings']['deletion_strategy'] ?? 'none'
                );
            }

            MO_Vendor_Manager::instance()->update($vendor_id, [
                'last_sync_at' => current_time('mysql'),
            ]);

            return [
                'vendor_id' => $vendor_id,
                'status' => 'ok',
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'pages' => $page - 1,
            ];

        } finally {
            $this->release_lock($vendor_id);
        }
    }

    /**
     * Handle products missing from API
     */
    private function handle_missing_products(
        int $vendor_id,
        int $vendor_user_id,
        array $seen_external_ids,
        string $strategy
    ) {

        if ($strategy === 'none' || empty($seen_external_ids))
            return;

        global $wpdb;

        $product_ids = $wpdb->get_col($wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id
            INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id
            WHERE p.post_type = 'product'
              AND m1.meta_key = '_mo_vendor_id'
              AND m1.meta_value = %d
              AND m2.meta_key = '_mo_external_id'
        ", $vendor_user_id));

        foreach ($product_ids as $product_id) {

            $external_id = get_post_meta($product_id, '_mo_external_id', true);

            if (in_array((string) $external_id, $seen_external_ids, true))
                continue;

            switch ($strategy) {
                case 'draft':
                    wp_update_post(['ID' => $product_id, 'post_status' => 'draft']);
                    break;

                case 'out_of_stock':
                    $wc = wc_get_product($product_id);
                    if ($wc) {
                        $wc->set_stock_quantity(0);
                        $wc->set_stock_status('outofstock');
                        $wc->save();
                    }
                    break;

                case 'trash':
                    wp_trash_post($product_id);
                    break;
            }

            $this->log('info', "Product {$product_id} missing from API → {$strategy}");
        }
    }

    /* =======================
       LOCKING
       ======================= */

    private function lock_key(int $vendor_id): string
    {
        return 'mo_api_sync_lock_vendor_' . $vendor_id;
    }

    private function acquire_lock(int $vendor_id, int $ttl): bool
    {
        if (get_transient($this->lock_key($vendor_id)))
            return false;
        set_transient($this->lock_key($vendor_id), 1, $ttl);
        return true;
    }

    private function release_lock(int $vendor_id): void
    {
        delete_transient($this->lock_key($vendor_id));
    }

    private function log(string $level, string $message)
    {
        error_log("[MO_API_SYNC][{$level}] {$message}");
    }
}
