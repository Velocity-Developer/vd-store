<?php

namespace WpStore\Api;

use WpStore\Domain\Cart\CartService;
use WpStore\Domain\Product\ProductData;

use WP_REST_Request;
use WP_REST_Response;

class CartController
{
    private function service()
    {
        return new CartService();
    }

    public function register_routes()
    {
        register_rest_route('wp-store/v1', '/cart', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_cart'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'upsert_item'],
                'permission_callback' => [$this, 'require_rest_nonce'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'clear_cart'],
                'permission_callback' => [$this, 'require_rest_nonce'],
            ],
        ]);

        register_rest_route('wp-store/v1', '/debug', [
            'methods' => 'GET',
            'callback' => [$this, 'debug_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function debug_status()
    {
        $service = $this->service();
        global $wpdb;
        $table = $wpdb->prefix . 'store_carts';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        // Try to force create if not exists
        if (!$exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                guest_key VARCHAR(64) NULL DEFAULT NULL,
                cart LONGTEXT NOT NULL,
                shipping_data LONGTEXT NULL DEFAULT NULL,
                marketplace_snapshot LONGTEXT NULL DEFAULT NULL,
                total_price DECIMAL(10,2) NULL DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user (user_id),
                UNIQUE KEY uniq_guest (guest_key)
            ) {$charset_collate};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        }

        return new WP_REST_Response([
            'table_name' => $table,
            'table_exists' => $exists,
            'last_db_error' => $wpdb->last_error,
            'cookie_sent' => $_COOKIE,
            'cookie_key_name' => 'wp_store_cart_key',
            'guest_key_resolved' => $service->get_actor_key(),
            'is_user_logged_in' => is_user_logged_in(),
            'current_user_id' => get_current_user_id(),
            'rows' => $exists ? $wpdb->get_results("SELECT * FROM $table LIMIT 10") : [],
        ], 200);
    }

    public function require_rest_nonce(WP_REST_Request $request)
    {
        $nonce = $request->get_header('x_wp_nonce');
        if (!$nonce) {
            $nonce = $request->get_header('x-wp-nonce');
        }

        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    public function get_cart(WP_REST_Request $request)
    {
        return new WP_REST_Response($this->service()->get_cart(), 200);
    }

    public function upsert_item(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = [];
        }

        $product_id = isset($data['id']) ? (int) $data['id'] : 0;
        $qty = isset($data['qty']) ? (int) $data['qty'] : 1;
        $options = isset($data['options']) && is_array($data['options']) ? $this->normalize_options($data['options']) : [];
        $add_qty = isset($data['add_qty']) ? (int) $data['add_qty'] : null;

        if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
            return new WP_REST_Response(['message' => 'Produk tidak valid'], 400);
        }

        if ($qty < 0) {
            $qty = 0;
        }

        $actor_key = $this->get_actor_key();
        $op_fingerprint = md5(wp_json_encode([
            'id' => $product_id,
            'qty' => $qty,
            'add_qty' => $add_qty,
            'options' => $options,
        ]));
        $op_lock_key = 'wp_store_cart_op_' . md5($actor_key);
        $existing_op = get_transient($op_lock_key);
        if (is_string($existing_op) && $existing_op === $op_fingerprint) {
            $cart = $this->read_cart();
            return new WP_REST_Response($this->format_cart($cart), 200);
        }
        set_transient($op_lock_key, $op_fingerprint, 3);

        $lock_key = 'wp_store_cart_lock_' . $actor_key;
        if (get_transient($lock_key)) {
            $cart = $this->read_cart();
            return new WP_REST_Response($this->format_cart($cart), 200);
        }
        set_transient($lock_key, 1, 1);

        $service = $this->service();
        $cart = $service->get_raw_items();
        if ($add_qty !== null) {
            if ($add_qty < 0) {
                $add_qty = 0;
            }
            $current = $this->find_current_qty($cart, $product_id, $options);
            $qty = max(0, $current + $add_qty);
        }
        $cart = $service->upsert_item($product_id, $qty, $options);
        delete_transient($lock_key);

        return new WP_REST_Response($service->get_cart(), 200);
    }

    public function clear_cart(WP_REST_Request $request)
    {
        $this->service()->clear();
        return new WP_REST_Response($this->service()->get_cart(), 200);
    }

    private function apply_upsert($cart, $product_id, $qty, $options = [])
    {
        $cart = is_array($cart) ? $cart : [];

        $next = [];
        $found = false;

        foreach ($cart as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $row_qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $row_opts = isset($row['opts']) && is_array($row['opts']) ? $this->normalize_options($row['opts']) : [];

            if ($id <= 0 || $row_qty <= 0) {
                continue;
            }

            if ($id === (int) $product_id && $this->options_equal($row_opts, $options)) {
                $found = true;
                if ($qty > 0) {
                    $next[] = ['id' => (int) $product_id, 'qty' => (int) $qty, 'opts' => $options];
                }
                continue;
            }

            $next[] = ['id' => $id, 'qty' => $row_qty, 'opts' => $row_opts];
        }

        if (!$found && $qty > 0) {
            $next[] = ['id' => (int) $product_id, 'qty' => (int) $qty, 'opts' => $options];
        }

        return $next;
    }

    private function find_current_qty($cart, $product_id, $options = [])
    {
        $cart = is_array($cart) ? $cart : [];
        $options = $this->normalize_options(is_array($options) ? $options : []);
        foreach ($cart as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $row_qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $row_opts = isset($row['opts']) && is_array($row['opts']) ? $this->normalize_options($row['opts']) : [];
            if ($id <= 0 || $row_qty <= 0) {
                continue;
            }
            if ($id === (int) $product_id && $this->options_equal($row_opts, $options)) {
                return $row_qty;
            }
        }
        return 0;
    }

    private function get_actor_key()
    {
        return $this->service()->get_actor_key();
    }

    private function normalize_options($options)
    {
        return $this->service()->normalize_options($options);
    }

    private function options_equal($a, $b)
    {
        return $this->service()->options_equal($a, $b);
    }

    private function resolve_price_with_options($product_id, $opts)
    {
        return ProductData::resolve_price_with_options((int) $product_id, is_array($opts) ? $opts : []);
    }
}
