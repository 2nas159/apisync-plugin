<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class MO_Abstract_API_Adapter
{

    protected $settings = [];

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->authenticate();
    }

    /**
     * Authenticate with vendor API
     */
    abstract protected function authenticate();

    /**
     * Fetch paginated products
     */
    abstract public function fetch_products($page = 1, $limit = 50);

    /**
     * Fetch stock for a single product (optional override)
     */
    public function fetch_stock($external_id)
    {
        return null;
    }

    /**
     * Normalize vendor product into WooCommerce-ready array
     */
    public function normalize_product(array $raw_product)
    {
        $mapping = $this->settings['field_mapping'] ?? [];

        $external_id = $this->map_field($raw_product, $mapping['external_id'] ?? '');
        $name = $this->map_field($raw_product, $mapping['name'] ?? '');
        $price = $this->map_field($raw_product, $mapping['price'] ?? 0);
        $stock = $this->map_field($raw_product, $mapping['stock'] ?? 0);
        $sku = $this->map_field($raw_product, $mapping['sku'] ?? '');
        $images = $this->map_field($raw_product, $mapping['images'] ?? []);
        $description = $this->map_field($raw_product, $mapping['description'] ?? '');

        if (empty($external_id) || empty($name)) {
            return null;
        }

        return [
            'external_id' => (string) $external_id,
            'name' => (string) $name,
            'price' => (float) $price,
            'stock' => (int) $stock,
            'sku' => (string) $sku,
            'images' => is_array($images) ? $images : [],
            'description' => (string) $description,
        ];
    }

    protected function map_field(array $raw, string $path, $default = null)
    {
        if (!$path)
            return $default;

        $segments = explode('.', $path);
        $value = $raw;

        foreach ($segments as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }

        return $value;
    }



    /**
     * Optional connection test
     */
    public function test_connection()
    {
        return true;
    }

    /**
     * HTTP request with retry + exponential backoff
     *
     * @param callable $callback function that performs the request
     * @param int $max_retries
     * @param int $base_delay_ms
     * @return mixed
     * @throws Exception
     */

    protected function with_retry(callable $callback, int $max_retries = 3, int $base_delay_ms = 500)
    {

        $attempt = 0;
        $last_exception = null;

        while ($attempt <= $max_retries) {
            try {
                return $callback();
            } catch (Exception $e) {
                $last_exception = $e;
                $attempt++;

                if ($attempt > $max_retries) {
                    break;
                }

                // exponential backoff: 0.5s, 1s, 2s, 4s...
                $delay_ms = $base_delay_ms * (2 ** ($attempt - 1));

                usleep($delay_ms * 1000);
            }
        }

        throw $last_exception ?: new Exception('Unknown API error');
    }


}
