<?php
if (!defined('ABSPATH'))
    exit;

class MO_Admin_UI
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_pages']);
    }

    public function register_admin_pages()
    {

        // Main menu
        add_menu_page(
            'API Product Sync',
            'API Product Sync',
            'manage_options',
            'mo-api-sync',
            [$this, 'render_vendors_list'],
            'dashicons-randomize'
        );

        // Vendors list
        add_submenu_page(
            'mo-api-sync',
            'Vendors',
            'Vendors',
            'manage_options',
            'mo-api-sync',
            [$this, 'render_vendors_list']
        );

        // 🔥 EDIT VENDOR PAGE (المهم)
        add_submenu_page(
            null, // 👈 hidden page (مش تظهر في المينيو)
            'Edit Vendor',
            'Edit Vendor',
            'manage_options',
            'mo-api-sync-vendor-edit',
            [$this, 'render_vendor_edit_page']
        );
    }

    public function render_vendor_edit_page()
    {

        $vendor_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if (!$vendor_id) {
            echo '<div class="notice notice-error"><p>Invalid vendor ID</p></div>';
            return;
        }

        $vendor = MO_Vendor_Manager::instance()->get($vendor_id);

        if (!$vendor) {
            echo '<div class="notice notice-error"><p>Vendor not found</p></div>';
            return;
        }

        $settings = $vendor['settings'] ?? [];
        $mapping = $settings['field_mapping'] ?? [];

        echo '<div class="wrap">';
        echo '<h1>Edit Vendor</h1>';

        include MO_API_SYNC_PATH . 'admin/views/vendor-edit.php';

        echo '</div>';
    }

}

