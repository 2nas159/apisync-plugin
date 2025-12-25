<?php
if (!defined('ABSPATH')) {
    exit;
}

class MO_Vendor_Manager
{
    private static $instance = null;
    private $table;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mo_api_vendors';
    }

    /**
     * List vendors
     */
    public function list($for_sync = false)
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY id ASC",
            ARRAY_A
        );

        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            $row = $this->normalize_row($row);
        }

        return $rows;
    }

    /**
     * Get vendor by ID
     */
    public function get(int $id)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->normalize_row($row);
    }

    /**
     * Get vendor for sync
     */
    public function get_for_sync(int $id)
    {
        return $this->get($id);
    }

    /**
     * Update vendor
     */
    public function update(int $id, array $data)
    {
        global $wpdb;

        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = wp_json_encode($data['settings']);
        }

        $data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );
    }

    /**
     * Normalize DB row
     */
    private function normalize_row(array $row)
    {
        // Decode settings safely
        if (!empty($row['settings'])) {
            if (is_string($row['settings'])) {
                $decoded = json_decode($row['settings'], true);
                $row['settings'] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($row['settings'])) {
                $row['settings'] = [];
            }
        } else {
            $row['settings'] = [];
        }

        // Always ensure field_mapping exists
        if (!isset($row['settings']['field_mapping']) || !is_array($row['settings']['field_mapping'])) {
            $row['settings']['field_mapping'] = [];
        }

        return $row;
    }
}
