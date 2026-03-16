<?php

namespace WpStore\Api;

use WP_REST_Request;
use WP_REST_Response;

class CartController
{
    private $cookie_key = 'wp_store_cart_key';

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
            'cookie_key_name' => $this->cookie_key,
            'guest_key_resolved' => $this->get_or_set_guest_key(),
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
        $cart = $this->read_cart();
        return new WP_REST_Response($this->format_cart($cart), 200);
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

        $cart = $this->read_cart();
        if ($add_qty !== null) {
            if ($add_qty < 0) {
                $add_qty = 0;
            }
            $current = $this->find_current_qty($cart, $product_id, $options);
            $qty = max(0, $current + $add_qty);
        }
        $cart = $this->apply_upsert($cart, $product_id, $qty, $options);
        $this->write_cart($cart);
        delete_transient($lock_key);

        return new WP_REST_Response($this->format_cart($cart), 200);
    }

    public function clear_cart(WP_REST_Request $request)
    {
        $this->write_cart([]);
        return new WP_REST_Response($this->format_cart([]), 200);
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

    private function read_cart()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'store_carts';
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $row = $wpdb->get_row($wpdb->prepare("SELECT cart FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($row && isset($row->cart)) {
                $data = json_decode($row->cart, true);
                return is_array($data) ? $data : [];
            }
            $key = $this->get_or_set_guest_key();
            $row = $wpdb->get_row($wpdb->prepare("SELECT cart FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
            if ($row && isset($row->cart)) {
                $data = json_decode($row->cart, true);
                if (is_array($data)) {
                    $this->write_cart($data);
                }
                return is_array($data) ? $data : [];
            }
            return [];
        }
        $key = $this->get_or_set_guest_key();
        $row = $wpdb->get_row($wpdb->prepare("SELECT cart FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
        if ($row && isset($row->cart)) {
            $data = json_decode($row->cart, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function write_cart($cart)
    {
        global $wpdb;
        $cart = is_array($cart) ? $cart : [];
        $table = $wpdb->prefix . 'store_carts';
        $json = wp_json_encode($cart);
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($exists) {
                $wpdb->update($table, ['cart' => $json], ['user_id' => $user_id], ['%s'], ['%d']);
            } else {
                $wpdb->insert($table, ['user_id' => $user_id, 'cart' => $json], ['%d', '%s']);
            }
            return;
        }
        $key = $this->get_or_set_guest_key();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
        if ($exists) {
            $wpdb->update($table, ['cart' => $json], ['guest_key' => $key], ['%s'], ['%s']);
        } else {
            $wpdb->insert($table, ['guest_key' => $key, 'cart' => $json], ['%s', '%s']);
        }
    }

    private function get_or_set_guest_key()
    {
        if (isset($_COOKIE[$this->cookie_key]) && is_string($_COOKIE[$this->cookie_key]) && $_COOKIE[$this->cookie_key] !== '') {
            $key = sanitize_key($_COOKIE[$this->cookie_key]);
            return $key;
        }

        $key = sanitize_key(wp_generate_uuid4());

        $secure = is_ssl();
        $path = '/'; // Force root path for testing
        $domain = ''; // Let browser handle domain

        setcookie($this->cookie_key, $key, time() + (DAY_IN_SECONDS * 30), $path, $domain, $secure, true);
        $_COOKIE[$this->cookie_key] = $key;

        return $key;
    }

    private function get_actor_key()
    {
        if (is_user_logged_in()) {
            return 'u_' . get_current_user_id();
        }
        return 'g_' . $this->get_or_set_guest_key();
    }

    private function format_cart($cart)
    {
        $items = [];
        $total = 0;

        foreach ($cart as $row) {
            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $opts = isset($row['opts']) && is_array($row['opts']) ? $row['opts'] : [];

            if ($product_id <= 0 || $qty <= 0 || get_post_type($product_id) !== 'store_product') {
                continue;
            }

            $price = $this->resolve_price_with_options($product_id, $opts);
            $subtotal = $price * $qty;
            $total += $subtotal;
            $ptype = get_post_meta($product_id, '_store_product_type', true);
            $is_digital = ($ptype === 'digital') || (bool) get_post_meta($product_id, '_store_is_digital', true);

            $items[] = [
                'id' => $product_id,
                'title' => get_the_title($product_id),
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $subtotal,
                'image' => get_the_post_thumbnail_url($product_id, 'thumbnail') ?: null,
                'link' => get_permalink($product_id),
                'options' => $opts,
                'is_digital' => $is_digital,
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    private function normalize_options($options)
    {
        $normalized = [];
        foreach ($options as $k => $v) {
            $key = trim(sanitize_text_field($k));
            if (is_array($v)) {
                $normalized[$key] = array_map(function ($x) {
                    return trim(sanitize_text_field($x));
                }, $v);
            } else {
                $normalized[$key] = trim(sanitize_text_field((string) $v));
            }
        }
        ksort($normalized);
        return $normalized;
    }

    private function options_equal($a, $b)
    {
        $a = $this->normalize_options(is_array($a) ? $a : []);
        $b = $this->normalize_options(is_array($b) ? $b : []);
        return wp_json_encode($a) === wp_json_encode($b);
    }

    private function resolve_price_with_options($product_id, $opts)
    {
        $base = (float) get_post_meta($product_id, '_store_price', true);
        $adv_name = get_post_meta($product_id, '_store_option2_name', true);
        $adv = get_post_meta($product_id, '_store_advanced_options', true);
        if (is_array($adv) && $adv_name && isset($opts[$adv_name])) {
            $label = (string) $opts[$adv_name];
            foreach ($adv as $row) {
                $rlabel = isset($row['label']) ? (string) $row['label'] : '';
                $rprice = isset($row['price']) ? (float) $row['price'] : 0;
                if ($rlabel !== '' && $rlabel === $label && $rprice > 0) {
                    return $rprice;
                }
            }
        }
        return $base;
    }
}
