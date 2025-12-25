<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base abstract API adapter
 * All vendor adapters MUST extend this class
 */
abstract class MO_Abstract_API_Adapter
{
    protected string $api_base_url;
    protected ?string $api_key;
    protected array $settings = [];

    public function __construct(array $config = [])
    {
        $this->api_base_url = rtrim($config['api_base_url'] ?? '', '/');
        $this->api_key      = $config['api_key'] ?? null;
        $this->settings     = is_array($config['settings'] ?? null)
            ? $config['settings']
            : [];
    }

    /**
     * Fetch raw products from API (pagination supported)
     */
    abstract public function fetch_products(int $page = 1, int $limit = 50): array;

    /**
     * Normalize ONE raw product into internal format
     */
    abstract public function normalize_product(array $raw_product): ?array;

    /**
     * Perform HTTP request with retry support
     */
    protected function request(
        string $method,
        string $endpoint,
        array $query = [],
        int $timeout = 20
    ) {
        $url = $this->api_base_url . $endpoint;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $args = [
            'method'  => $method,
            'timeout' => $timeout,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($this->api_key)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->api_key;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);

        if ($status === 429) {
            throw new Exception('Retryable HTTP 429');
        }

        if ($status < 200 || $status >= 300) {
            throw new Exception("HTTP {$status}");
        }

        return $response;
    }

    /**
     * Helper: read nested value using dot notation
     * Example: pricing.amount
     */
    protected function get_field(array $data, string $path)
    {
        $parts = explode('.', $path);
        $value = $data;

        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return null;
            }
            $value = $value[$p];
        }

        return $value;
    }
}
