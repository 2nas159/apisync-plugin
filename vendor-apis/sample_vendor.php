<?php
if (!defined('ABSPATH'))
    exit;

class MO_Sample_vendor_API extends MO_Abstract_API_Adapter
{

    protected $token = null;

    protected function authenticate()
    {
        // For this sample: api_key IS the bearer token.
        $this->token = $this->settings['api_key'] ?? null;
    }

    public function test_connection()
    {
        // You can change this endpoint according to vendor
        $res = $this->request('GET', '/ping');
        return !is_wp_error($res) && isset($res['ok']) && $res['ok'] === true;
    }

    /**
     * Fetch products paginated
     *
     * Expected vendor response shape (example):
     * {
     *   "data": [ ...products ],
     *   "meta": { "page": 1, "has_more": true }
     * }
     */
    public function fetch_products($page = 1, $limit = 50)
    {

        $query = [
            'page' => (int) $page,
            'limit' => (int) $limit,
        ];

        $res = $this->with_retry(function () use ($query) {

            $response = $this->request('GET', '/products', $query);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            return $response;

        }, 3, 500);

        // Vendor might return directly array or wrapped in data
        if (isset($res['data']) && is_array($res['data'])) {
            return $res['data'];
        }

        if (is_array($res))
            return $res;

        return [];
    }

    /**
     * Normalize vendor product into our strict contract
     */
    public function normalize_product(array $raw_product)
    {

        error_log('[MO_DEBUG_RAW] ' . json_encode($raw_product));

        $external_id = $raw_product['id'] ?? null;
        $name = $raw_product['title'] ?? null;
        $price = $raw_product['price'] ?? null;
        $stock = $raw_product['qty'] ?? 0;

        if (empty($external_id) || empty($name)) {
            error_log('[MO_DEBUG_SKIP] missing id or name');
            return null;
        }

        if (!is_numeric($price) || $price <= 0) {
            error_log('[MO_DEBUG_SKIP] invalid price: ' . var_export($price, true));
            return null;
        }

        return [
            'external_id' => (string) $external_id,
            'name' => (string) $name,
            'price' => (float) $price,
            'stock' => (int) $stock,
            'sku' => $raw_product['sku'] ?? '',
            'images' => $raw_product['images'] ?? [],
        ];
    }


    /**
     * -----------------------------
     * HTTP Request Helper
     * -----------------------------
     */
    private function request(string $method, string $path, array $query = [], $body = null)
    {

        $base = rtrim((string) ($this->settings['api_base_url'] ?? ''), '/');
        if (empty($base)) {
            return new WP_Error('mo_no_base_url', 'Missing api_base_url');
        }

        $url = $base . '/' . ltrim($path, '/');

        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $headers = [
            'Accept' => 'application/json',
        ];

        if (!empty($this->token)) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $args = [
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => $headers,
        ];

        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);

        $json = json_decode($raw, true);

        if ($code >= 500 || $code == 429) {
            $msg = "Retryable HTTP {$code}";
            throw new Exception($msg);
        }

        if ($code >= 400) {
            $msg = is_array($json) && isset($json['message'])
                ? $json['message']
                : "HTTP {$code}";
            return new WP_Error('mo_http_error', $msg, ['code' => $code]);
        }


        return is_array($json) ? $json : [];
    }
}
