<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>API Product Sync</h1>

    <?php if (!empty($_GET['added'])): ?>
        <div class="notice notice-success"><p>Vendor added successfully.</p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['synced'])): ?>
        <div class="notice notice-info"><p>Sync triggered.</p></div>
    <?php endif; ?>

    <h2>Add Vendor</h2>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('mo_add_vendor'); ?>
        <input type="hidden" name="action" value="mo_add_vendor">

        <table class="form-table">
            <tr>
                <th>Vendor Name</th>
                <td><input type="text" name="vendor_name" required></td>
            </tr>
            <tr>
                <th>WP Vendor User ID</th>
                <td><input type="number" name="wp_user_id" required></td>
            </tr>
            <tr>
                <th>API Type</th>
                <td>
                    <input type="text" name="api_type" placeholder="sample_vendor" required>
                    <p class="description">Must match adapter filename.</p>
                </td>
            </tr>
            <tr>
                <th>API Base URL</th>
                <td><input type="url" name="api_base_url" required></td>
            </tr>
            <tr>
                <th>API Key / Token</th>
                <td><input type="text" name="api_key"></td>
            </tr>
            <tr>
                <th>Active</th>
                <td><input type="checkbox" name="is_active" checked></td>
            </tr>
        </table>

        <p>
            <button type="submit" class="button button-primary">Add Vendor</button>
        </p>
    </form>

    <hr>

    <h2>Vendors</h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>API Type</th>
                <th>WP User</th>
                <th>Last Sync</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($vendors)): ?>
            <tr><td colspan="7">No vendors added yet.</td></tr>
        <?php else: foreach ($vendors as $v): ?>
            <tr>
                <td><?php echo (int)$v['id']; ?></td>
                <td><?php echo esc_html($v['vendor_name']); ?></td>
                <td><?php echo esc_html($v['api_type']); ?></td>
                <td><?php echo (int)$v['wp_user_id']; ?></td>
                <td><?php echo esc_html($v['last_sync_at'] ?: '—'); ?></td>
                <td><?php echo $v['is_active'] ? 'Active' : 'Disabled'; ?></td>
                <td>
                    <?php if ($v['is_active']): ?>
                        <a class="button"
                           href="<?php echo wp_nonce_url(
                               admin_url('admin-post.php?action=mo_sync_vendor&vendor_id=' . (int)$v['id']),
                               'mo_sync_vendor'
                           ); ?>">
                           Sync Now
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
