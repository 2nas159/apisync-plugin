<?php
if (!defined('ABSPATH')) {
    exit;
}

class MO_Admin_Menu
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_post_mo_sync_vendor', [$this, 'handle_sync_vendor']);
    }

    public function handle_sync_vendor()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;

        if (!$vendor_id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'mo_sync_vendor_' . $vendor_id)) {
            wp_die('Invalid request');
        }

        error_log("[MO_ADMIN] Manual sync triggered for vendor {$vendor_id}");

        $result = MO_Product_Mapper::instance()->sync_vendor($vendor_id);

        error_log('[MO_ADMIN_SYNC_RESULT] ' . json_encode($result));

        wp_redirect(
            add_query_arg(
                [
                    'page' => 'mo-api-sync',
                    'synced' => 1,
                    'vendor' => $vendor_id,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function register_admin_pages()
    {
        add_menu_page(
            'API Product Sync',
            'API Product Sync',
            'manage_options',
            'mo-api-sync',
            [$this, 'render_vendors_list'],
            'dashicons-randomize'
        );

        // Hidden edit page (opened via Edit button)
        add_submenu_page(
            null,
            'Edit Vendor',
            'Edit Vendor',
            'manage_options',
            'mo-api-sync-vendor-edit',
            [$this, 'render_vendor_edit_page']
        );
    }

    private function log($level, $msg)
    {
        error_log("[MO_API_SYNC][admin][{$level}] {$msg}");
    }

    public function render_vendors_list()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        echo '<div class="wrap">';
        echo '<h1>Vendors</h1>';

        if (!class_exists('MO_Vendor_Manager')) {
            echo '<div class="notice notice-error"><p>Vendor manager not loaded.</p></div>';
            echo '</div>';
            return;
        }

        $vendors = MO_Vendor_Manager::instance()->list();

        if (empty($vendors)) {
            echo '<p>No vendors found.</p>';
            echo '</div>';
            return;
        }

        if (isset($_GET['synced'], $_GET['vendor'])) {
            echo '<div class="notice notice-success">
        <p>Vendor #' . (int) $_GET['vendor'] . ' synced successfully.</p>
    </div>';
        }


        echo '<table class="widefat striped">';
        echo '<thead><tr>
                <th>ID</th>
                <th>Name</th>
                <th>API Type</th>
                <th>Last Sync</th>
                <th>Actions</th>
              </tr></thead><tbody>';

        foreach ($vendors as $v) {
            $edit_url = admin_url('admin.php?page=mo-api-sync-vendor-edit&id=' . (int) $v['id']);
            echo '<tr>';
            echo '<td>' . (int) $v['id'] . '</td>';
            echo '<td>' . esc_html($v['name'] ?? ('Vendor #' . (int) $v['id'])) . '</td>';
            echo '<td>' . esc_html($v['api_type'] ?? '') . '</td>';
            echo '<td>' . esc_html($v['last_sync_at'] ?? '-') . '</td>';
            $sync_url = wp_nonce_url(
                admin_url('admin-post.php?action=mo_sync_vendor&vendor_id=' . (int) $v['id']),
                'mo_sync_vendor_' . (int) $v['id']
            );

            echo '<td>
    <a class="button button-small" href="' . esc_url($edit_url) . '">Edit</a>
    <a class="button button-primary button-small" href="' . esc_url($sync_url) . '">Sync Now</a>
</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_vendor_edit_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $vendor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$vendor_id) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Invalid vendor ID.</p></div></div>';
            return;
        }

        if (!class_exists('MO_Vendor_Manager')) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Vendor manager not loaded.</p></div></div>';
            return;
        }

        $vendor = MO_Vendor_Manager::instance()->get($vendor_id);
        if (!$vendor) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Vendor not found.</p></div></div>';
            return;
        }

        $settings = $vendor['settings'] ?? [];
        if (!is_array($settings))
            $settings = [];
        $mapping = $settings['field_mapping'] ?? [];
        if (!is_array($mapping))
            $mapping = [];

        // Save Mapping
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mo_save_vendor'])) {
            check_admin_referer('mo_save_vendor_' . $vendor_id);

            $posted_mapping = $_POST['settings']['field_mapping'] ?? [];
            if (!is_array($posted_mapping))
                $posted_mapping = [];

            $sanitized = [];
            foreach ($posted_mapping as $k => $v) {
                $sanitized[sanitize_key($k)] = sanitize_text_field($v);
            }

            $settings['field_mapping'] = $sanitized;

            MO_Vendor_Manager::instance()->update($vendor_id, [
                'settings' => $settings,
            ]);

            $this->log('info', "Saved mapping for vendor {$vendor_id}");

            // Reload from DB
            $vendor = MO_Vendor_Manager::instance()->get($vendor_id);
            $settings = $vendor['settings'] ?? [];
            if (!is_array($settings))
                $settings = [];
            $mapping = $settings['field_mapping'] ?? [];
            if (!is_array($mapping))
                $mapping = [];

            echo '<div class="notice notice-success"><p>Vendor settings saved.</p></div>';
        }

        // Test Mapping
        $preview_normalized = null;
        $preview_error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mo_test_mapping'])) {
            check_admin_referer('mo_save_vendor_' . $vendor_id);

            try {
                $adapter = MO_API_Manager::instance()->get_adapter(
                    $vendor['api_type'],
                    [
                        'api_base_url' => $vendor['api_base_url'],
                        'api_key' => $vendor['api_key'],
                        'settings' => $settings, // include mapping
                    ]
                );

                $products = $adapter->fetch_products(1, 1);
                if (!empty($products[0])) {
                    $preview_normalized = $adapter->normalize_product((array) $products[0]);
                } else {
                    $preview_error = 'No products returned from API.';
                }
            } catch (Exception $e) {
                $preview_error = $e->getMessage();
            }
        }

        echo '<div class="wrap">';
        echo '<h1>Edit Vendor</h1>';

        // Simple inline view (works even if vendor-edit.php missing)
        ?>
        <form method="post">
            <?php wp_nonce_field('mo_save_vendor_' . $vendor_id); ?>

            <h2 class="title">Field Mapping</h2>
            <p class="description">Use dot notation for nested fields (example: <code>pricing.amount</code>).</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label>External ID</label></th>
                    <td><input type="text" class="regular-text" name="settings[field_mapping][external_id]"
                            value="<?php echo esc_attr($mapping['external_id'] ?? 'id'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Name</label></th>
                    <td><input type="text" class="regular-text" name="settings[field_mapping][name]"
                            value="<?php echo esc_attr($mapping['name'] ?? 'title'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Price</label></th>
                    <td><input type="text" class="regular-text" name="settings[field_mapping][price]"
                            value="<?php echo esc_attr($mapping['price'] ?? 'price'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Stock</label></th>
                    <td><input type="text" class="regular-text" name="settings[field_mapping][stock]"
                            value="<?php echo esc_attr($mapping['stock'] ?? 'qty'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label>SKU</label></th>
                    <td><input type="text" class="regular-text" name="settings[field_mapping][sku]"
                            value="<?php echo esc_attr($mapping['sku'] ?? 'sku'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Images</label></th>
                    <td><input type="text" class="regular-text" name="settings[field_mapping][images]"
                            value="<?php echo esc_attr($mapping['images'] ?? 'images'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label>Description</label></th>
                    <td><input type="text" class="regular-text" name="settings[field_mapping][description]"
                            value="<?php echo esc_attr($mapping['description'] ?? 'description'); ?>"></td>
                </tr>
            </table>

            <p>
                <button type="submit" name="mo_save_vendor" class="button button-primary">Save Mapping</button>
                <button type="submit" name="mo_test_mapping" class="button">Test Mapping</button>
            </p>
        </form>

        <?php if ($preview_error): ?>
            <div class="notice notice-error">
                <p>
                    <?php echo esc_html($preview_error); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($preview_normalized !== null): ?>
            <h2 class="title">Mapping Preview</h2>
            <pre style="background:#fff;padding:12px;border:1px solid #ccd0d4;max-width:900px;overflow:auto;"><?php
            print_r($preview_normalized);
            ?></pre>
        <?php endif; ?>

        <?php
        echo '</div>';
    }
}
