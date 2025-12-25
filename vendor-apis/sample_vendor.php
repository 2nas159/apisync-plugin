<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sample vendor adapter (for Mock API testing)
 */
class MO_Sample_vendor_API extends MO_Abstract_API_Adapter
{
    /**
     * Fetch products from mock API
     */
    public function fetch_products(int $page = 1, int $limit = 50): array
    {
        $response = $this->request('GET', '/products', [
            'page'  => $page,
            'limit' => $limit,
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        error_log('[MO_DEBUG_API_BODY] ' . json_encode($body));

        if (!isset($body['products']) || !is_array($body['products'])) {
            return [];
        }

        return $body['products'];
    }

    /**
     * Normalize raw product into internal schema
     */
    public function normalize_product(array $raw_product): ?array
    {
        error_log('[MO_DEBUG_RAW] ' . json_encode($raw_product));

        $mapping = $this->settings['field_mapping'] ?? [];

        $external_id = $this->get_field($raw_product, $mapping['external_id'] ?? 'id');
        if (!$external_id) {
            return null;
        }

        return [
            'external_id' => (string) $external_id,
            'name'        => (string) ($this->get_field($raw_product, $mapping['name'] ?? 'title') ?? ''),
            'price'       => (float)  ($this->get_field($raw_product, $mapping['price'] ?? 'price') ?? 0),
            'stock'       => (int)    ($this->get_field($raw_product, $mapping['stock'] ?? 'qty') ?? 0),
            'sku'         => (string) ($this->get_field($raw_product, $mapping['sku'] ?? 'sku') ?? ''),
            'images'      => (array)  ($this->get_field($raw_product, $mapping['images'] ?? 'images') ?? []),
            'description' => (string) ($this->get_field($raw_product, $mapping['description'] ?? 'description') ?? ''),
        ];
    }
}
