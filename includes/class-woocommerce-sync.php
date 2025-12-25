<?php
if (!defined('ABSPATH'))
    exit;

class MO_WooCommerce_Sync
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

    /**
     * MAIN ENTRY POINT
     */
    public function upsert_product(array $product, int $vendor_user_id)
    {

        if (!$this->is_valid_product($product)) {
            return ['status' => 'skipped', 'reason' => 'invalid_product'];
        }

        $product_id = $this->find_product_by_external_id($product['external_id']);

        if ($product_id) {
            return $this->update_product($product_id, $product, $vendor_user_id);
        }

        return $this->create_product($product, $vendor_user_id);
    }

    /**
     * CREATE PRODUCT
     */
    private function create_product(array $p, int $vendor_user_id)
    {
        error_log('[MO_DEBUG_WC] create_product START ' . json_encode($p));

        if (!class_exists('WC_Product_Simple')) {
            error_log('[MO_DEBUG_WC] WC_Product_Simple not found');
            return ['status' => 'error', 'reason' => 'wc_not_loaded'];
        }

        $wc_product = new WC_Product_Simple();

        $wc_product->set_name($p['name']);
        $wc_product->set_status('publish');
        $wc_product->set_catalog_visibility('visible');
        $wc_product->set_regular_price($p['price']);
        $wc_product->set_price($p['price']);
        $wc_product->set_manage_stock(true);
        $wc_product->set_stock_quantity((int) $p['stock']);
        $wc_product->set_stock_status($p['stock'] > 0 ? 'instock' : 'outofstock');

        if (!empty($p['sku'])) {
            $wc_product->set_sku($p['sku']);
        }

        if (!empty($p['description'])) {
            $wc_product->set_description($p['description']);
        }

        $product_id = $wc_product->save();

        error_log('[MO_DEBUG_WC] save() returned ID = ' . var_export($product_id, true));

        if (!$product_id || is_wp_error($product_id)) {
            error_log('[MO_DEBUG_WC] save failed');
            return ['status' => 'error', 'reason' => 'save_failed'];
        }

        update_post_meta($product_id, '_mo_external_id', $p['external_id']);
        update_post_meta($product_id, '_mo_vendor_id', $vendor_user_id);

        if ($vendor_user_id && get_user_by('id', $vendor_user_id)) {
            wp_update_post([
                'ID' => $product_id,
                'post_author' => $vendor_user_id,
            ]);
        }

        error_log('[MO_DEBUG_WC] create_product DONE ' . $product_id);

        return [
            'status' => 'created',
            'product_id' => $product_id,
        ];
    }


    /**
     * UPDATE PRODUCT
     */
    private function update_product(int $product_id, array $p, int $vendor_user_id)
    {

        $wc_product = wc_get_product($product_id);
        if (!$wc_product) {
            return ['status' => 'error', 'reason' => 'product_missing'];
        }

        if ($p['price'] > 0) {
            $wc_product->set_regular_price($p['price']);
            $wc_product->set_price($p['price']);
        }

        $stock = max(0, (int) $p['stock']);
        $wc_product->set_manage_stock(true);
        $wc_product->set_stock_quantity($stock);
        $wc_product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');

        if (!empty($p['sku'])) {
            $wc_product->set_sku($p['sku']);
        }

        if (!empty($p['name'])) {
            $wc_product->set_name($p['name']);
        }

        if (!empty($p['description'])) {
            $wc_product->set_description(wp_kses_post($p['description']));
        }

        $wc_product->save();

        update_post_meta($product_id, '_mo_vendor_id', (int) $vendor_user_id);

        $this->sync_images($product_id, $p['images'] ?? []);

        return [
            'status' => 'updated',
            'product_id' => $product_id,
        ];
    }

    /**
     * IMAGE SYNC
     */
    private function sync_images(int $product_id, array $image_urls)
    {

        if (empty($image_urls))
            return;

        delete_post_meta($product_id, '_product_image_gallery');

        $attachment_ids = [];

        foreach ($image_urls as $url) {
            $id = $this->sideload_image($url, $product_id);
            if ($id)
                $attachment_ids[] = $id;
        }

        if (empty($attachment_ids))
            return;

        set_post_thumbnail($product_id, $attachment_ids[0]);

        if (count($attachment_ids) > 1) {
            update_post_meta(
                $product_id,
                '_product_image_gallery',
                implode(',', array_slice($attachment_ids, 1))
            );
        }
    }

    private function sideload_image(string $url, int $product_id)
    {

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $existing = $this->find_attachment_by_url($url);
        if ($existing)
            return $existing;

        $tmp = download_url($url, 30);
        if (is_wp_error($tmp))
            return null;

        $file = [
            'name' => basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ];

        $id = media_handle_sideload($file, $product_id);

        if (is_wp_error($id)) {
            @unlink($tmp);
            return null;
        }

        return (int) $id;
    }

    private function find_product_by_external_id($external_id)
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id
            WHERE pm.meta_key = '_mo_external_id'
              AND pm.meta_value = %s
              AND p.post_type = 'product'
              AND p.post_status NOT IN ('trash', 'auto-draft')
            LIMIT 1
            ",
                $external_id
            )
        );
    }


    private function find_attachment_by_url($url)
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE guid = %s
                 AND post_type = 'attachment'
                 LIMIT 1",
                $url
            )
        );
    }

    private function is_valid_product(array $p)
    {
        return !empty($p['external_id'])
            && !empty($p['name'])
            && isset($p['price']) && $p['price'] > 0
            && isset($p['stock'])
            && is_array($p['images']);
    }
}
