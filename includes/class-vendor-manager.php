<?php
if (!defined('ABSPATH')) exit;

class MO_Vendor_Manager {

    private static $instance = null;
    private $table;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mo_vendor_apis';
    }

    /**
     * Create vendor API config
     */
    public function create(array $data) {
        global $wpdb;

        $defaults = [
            'wp_user_id'   => 0,
            'vendor_name'  => '',
            'api_type'     => '',
            'api_base_url' => null,
            'api_key'      => null,
            'settings'     => [],
            'is_active'    => 1,
        ];
        $data = array_merge($defaults, $data);

        $wpdb->insert($this->table, [
            'wp_user_id'   => (int) $data['wp_user_id'],
            'vendor_name'  => sanitize_text_field($data['vendor_name']),
            'api_type'     => sanitize_key($data['api_type']),
            'api_base_url' => $data['api_base_url'] ? esc_url_raw($data['api_base_url']) : null,
            'api_key'      => $data['api_key'] ? $this->encrypt_secret($data['api_key']) : null,
            'settings'     => wp_json_encode(is_array($data['settings']) ? $data['settings'] : []),
            'is_active'    => (int) $data['is_active'],
        ], ['%d','%s','%s','%s','%s','%s','%d']);

        return (int) $wpdb->insert_id;
    }

    /**
     * Update vendor API config
     */
    public function update($id, array $data) {
        global $wpdb;

        $update = [];
        $formats = [];

        if (isset($data['wp_user_id'])) { $update['wp_user_id'] = (int)$data['wp_user_id']; $formats[] = '%d'; }
        if (isset($data['vendor_name'])) { $update['vendor_name'] = sanitize_text_field($data['vendor_name']); $formats[] = '%s'; }
        if (isset($data['api_type'])) { $update['api_type'] = sanitize_key($data['api_type']); $formats[] = '%s'; }
        if (isset($data['api_base_url'])) { $update['api_base_url'] = $data['api_base_url'] ? esc_url_raw($data['api_base_url']) : null; $formats[] = '%s'; }

        if (array_key_exists('api_key', $data)) {
            $update['api_key'] = $data['api_key'] ? $this->encrypt_secret($data['api_key']) : null;
            $formats[] = '%s';
        }

        if (isset($data['settings'])) { $update['settings'] = wp_json_encode(is_array($data['settings']) ? $data['settings'] : []); $formats[] = '%s'; }
        if (isset($data['is_active'])) { $update['is_active'] = (int)$data['is_active']; $formats[] = '%d'; }
        if (isset($data['last_sync_at'])) { $update['last_sync_at'] = $data['last_sync_at']; $formats[] = '%s'; }

        if (empty($update)) return false;

        return (bool) $wpdb->update($this->table, $update, ['id' => (int)$id], $formats, ['%d']);
    }

    /**
     * Get vendor by id (optionally decrypt api_key)
     */
    public function get($id, $with_secrets = false) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", (int)$id), ARRAY_A);
        if (!$row) return null;

        $row['settings'] = $row['settings'] ? json_decode($row['settings'], true) : [];
        if ($with_secrets && !empty($row['api_key'])) {
            $row['api_key'] = $this->decrypt_secret($row['api_key']);
        } else {
            unset($row['api_key']); // don’t leak secrets by default
        }
        return $row;
    }

    /**
     * List active vendors
     */
    public function list($only_active = true) {
        global $wpdb;
        $where = $only_active ? "WHERE is_active = 1" : "";
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} {$where} ORDER BY id DESC", ARRAY_A);

        foreach ($rows as &$r) {
            $r['settings'] = $r['settings'] ? json_decode($r['settings'], true) : [];
            unset($r['api_key']);
        }
        return $rows;
    }

    /**
     * Delete vendor
     */
    public function delete($id) {
        global $wpdb;
        return (bool) $wpdb->delete($this->table, ['id' => (int)$id], ['%d']);
    }

    /**
     * Fetch vendor config for syncing (includes decrypted api_key)
     */
    public function get_for_sync($id) {
        $row = $this->get($id, true);
        if (!$row) return null;

        // return adapter-ready settings
        return [
            'id'           => (int) $row['id'],
            'wp_user_id'   => (int) $row['wp_user_id'],
            'vendor_name'  => (string) $row['vendor_name'],
            'api_type'     => (string) $row['api_type'],
            'api_base_url' => $row['api_base_url'],
            'api_key'      => $row['api_key'] ?? null,
            'settings'     => is_array($row['settings']) ? $row['settings'] : [],
        ];
    }

    /**
     * --- Secret helpers ---
     * Uses openssl when available. If not, falls back to base64 (not secure).
     */
    private function encrypt_secret($plain) {
        $plain = (string) $plain;

        if (function_exists('openssl_encrypt')) {
            $key = hash('sha256', wp_salt('auth'), true);
            $iv  = random_bytes(16);
            $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return base64_encode($iv . $cipher);
        }

        // fallback (not secure)
        return base64_encode($plain);
    }

    private function decrypt_secret($encrypted) {
        $encrypted = (string) $encrypted;
        $raw = base64_decode($encrypted, true);
        if ($raw === false) return null;

        if (function_exists('openssl_decrypt') && strlen($raw) > 16) {
            $key = hash('sha256', wp_salt('auth'), true);
            $iv = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $plain === false ? null : $plain;
        }

        // fallback (base64)
        return $raw;
    }
}
