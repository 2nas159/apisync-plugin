<form method="post">

  <?php wp_nonce_field('mo_save_vendor_' . $vendor_id); ?>

  <h2>Field Mapping</h2>

  <table class="form-table">
    <tr>
      <th>External ID</th>
      <td><input type="text" name="settings[field_mapping][external_id]"
          value="<?= esc_attr($mapping['external_id'] ?? '') ?>"></td>
    </tr>
    <tr>
      <th>Name</th>
      <td><input type="text" name="settings[field_mapping][name]" value="<?= esc_attr($mapping['name'] ?? '') ?>"></td>
    </tr>
    <tr>
      <th>Price</th>
      <td><input type="text" name="settings[field_mapping][price]" value="<?= esc_attr($mapping['price'] ?? '') ?>">
      </td>
    </tr>
    <tr>
      <th>Stock</th>
      <td><input type="text" name="settings[field_mapping][stock]" value="<?= esc_attr($mapping['stock'] ?? '') ?>">
      </td>
    </tr>
    <tr>
      <th>SKU</th>
      <td><input type="text" name="settings[field_mapping][sku]" value="<?= esc_attr($mapping['sku'] ?? '') ?>"></td>
    </tr>
    <tr>
      <th>Images</th>
      <td><input type="text" name="settings[field_mapping][images]" value="<?= esc_attr($mapping['images'] ?? '') ?>">
      </td>
    </tr>
    <tr>
      <th>Description</th>
      <td><input type="text" name="settings[field_mapping][description]"
          value="<?= esc_attr($mapping['description'] ?? '') ?>"></td>
    </tr>
  </table>

  <p>
    <button class="button button-primary" name="mo_save_vendor">
      Save Mapping
    </button>
    <button class="button" name="mo_test_mapping">
      Test Mapping
    </button>
  </p>

</form>