<?php

namespace WpStore\Domain\Cart;

use WpStore\Domain\Product\ProductData;

class CartService
{
    private $cookie_key = 'wp_store_cart_key';

    public function get_cart()
    {
        return $this->format_cart($this->get_raw_items());
    }

    public function get_raw_items()
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
                    $this->write_raw_items($data);
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

    public function write_raw_items($cart)
    {
        global $wpdb;
        $cart = is_array($cart) ? array_values($cart) : [];
        $table = $wpdb->prefix . 'store_carts';
        $json = wp_json_encode($cart);

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($exists) {
                $wpdb->update($table, ['cart' => $json, 'shipping_data' => '', 'marketplace_snapshot' => ''], ['user_id' => $user_id], ['%s', '%s', '%s'], ['%d']);
            } else {
                $wpdb->insert($table, ['user_id' => $user_id, 'cart' => $json], ['%d', '%s']);
            }
            return;
        }

        $key = $this->get_or_set_guest_key();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
        if ($exists) {
            $wpdb->update($table, ['cart' => $json, 'shipping_data' => '', 'marketplace_snapshot' => ''], ['guest_key' => $key], ['%s', '%s', '%s'], ['%s']);
        } else {
            $wpdb->insert($table, ['guest_key' => $key, 'cart' => $json], ['%s', '%s']);
        }
    }

    public function get_marketplace_snapshot()
    {
        $row = $this->get_cart_row();
        if (!$row || !isset($row->marketplace_snapshot)) {
            return [];
        }

        $data = json_decode((string) $row->marketplace_snapshot, true);
        return is_array($data) ? $data : [];
    }

    public function write_marketplace_snapshot($snapshot)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'store_carts';
        $json = wp_json_encode(is_array($snapshot) ? $snapshot : []);

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($exists) {
                $wpdb->update($table, ['marketplace_snapshot' => $json], ['user_id' => $user_id], ['%s'], ['%d']);
            } else {
                $wpdb->insert($table, ['user_id' => $user_id, 'cart' => wp_json_encode([]), 'marketplace_snapshot' => $json], ['%d', '%s', '%s']);
            }
            return;
        }

        $key = $this->get_or_set_guest_key();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
        if ($exists) {
            $wpdb->update($table, ['marketplace_snapshot' => $json], ['guest_key' => $key], ['%s'], ['%s']);
        } else {
            $wpdb->insert($table, ['guest_key' => $key, 'cart' => wp_json_encode([]), 'marketplace_snapshot' => $json], ['%s', '%s', '%s']);
        }
    }

    public function clear_marketplace_snapshot()
    {
        $this->write_marketplace_snapshot([]);
    }

    public function raw_cart_hash($cart = null)
    {
        if ($cart === null) {
            $cart = $this->get_raw_items();
        }

        return md5(wp_json_encode(array_values(is_array($cart) ? $cart : [])));
    }

    public function upsert_item($product_id, $qty, $options = [], $cart_key = '', $add_qty = null)
    {
        $product_id = (int) $product_id;
        $qty = (int) $qty;
        $options = $this->normalize_options(is_array($options) ? $options : []);
        $cart_key = is_string($cart_key) ? trim($cart_key) : '';

        $cart = $this->get_raw_items();
        if ($add_qty !== null) {
            $add_qty = max(0, (int) $add_qty);
            $qty = max(0, $this->find_current_qty($cart, $product_id, $options) + $add_qty);
        }

        $cart = $this->apply_upsert($cart, $product_id, $qty, $options, $cart_key);
        $this->write_raw_items($cart);

        return $cart;
    }

    public function clear()
    {
        $this->write_raw_items([]);
    }

    public function format_cart($cart)
    {
        $items = [];
        $total = 0.0;

        foreach ((array) $cart as $row) {
            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $opts = isset($row['opts']) && is_array($row['opts']) ? $row['opts'] : [];

            if ($product_id <= 0 || $qty <= 0 || get_post_type($product_id) !== 'store_product') {
                continue;
            }

            $price = $this->resolve_price_with_options($product_id, $opts);
            $subtotal = $price * $qty;
            $total += $subtotal;
            $is_digital = ProductData::is_digital($product_id);

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

    public function normalize_options($options)
    {
        $normalized = [];
        foreach ((array) $options as $k => $v) {
            $key = trim(sanitize_text_field($k));
            if ($key === '') {
                continue;
            }
            if (is_array($v)) {
                $normalized[$key] = array_map(static function ($x) {
                    return trim(sanitize_text_field($x));
                }, $v);
            } else {
                $normalized[$key] = trim(sanitize_text_field((string) $v));
            }
        }
        ksort($normalized);

        return $normalized;
    }

    public function options_equal($a, $b)
    {
        $a = $this->normalize_options(is_array($a) ? $a : []);
        $b = $this->normalize_options(is_array($b) ? $b : []);

        return wp_json_encode($a) === wp_json_encode($b);
    }

    public function get_actor_key()
    {
        if (is_user_logged_in()) {
            return 'u_' . get_current_user_id();
        }

        return 'g_' . $this->get_or_set_guest_key();
    }

    private function apply_upsert($cart, $product_id, $qty, $options = [], $cart_key = '')
    {
        $cart = is_array($cart) ? $cart : [];
        $next = [];
        $found = false;

        foreach ($cart as $index => $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $row_qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            $row_opts = isset($row['opts']) && is_array($row['opts']) ? $this->normalize_options($row['opts']) : [];
            $row_key = md5((string) $id . '|' . wp_json_encode($row_opts));

            if ($id <= 0 || $row_qty <= 0) {
                continue;
            }

            if ($id === (int) $product_id && $this->options_equal($row_opts, $options) || ($cart_key !== '' && $cart_key === $row_key)) {
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

        return array_values($next);
    }

    private function find_current_qty($cart, $product_id, $options = [])
    {
        foreach ((array) $cart as $row) {
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

    private function get_or_set_guest_key()
    {
        if (isset($_COOKIE[$this->cookie_key]) && is_string($_COOKIE[$this->cookie_key]) && $_COOKIE[$this->cookie_key] !== '') {
            return sanitize_key($_COOKIE[$this->cookie_key]);
        }

        $key = sanitize_key(wp_generate_uuid4());
        setcookie($this->cookie_key, $key, time() + (DAY_IN_SECONDS * 30), '/', '', is_ssl(), true);
        $_COOKIE[$this->cookie_key] = $key;

        return $key;
    }

    private function resolve_price_with_options($product_id, $opts)
    {
        return ProductData::resolve_price_with_options((int) $product_id, is_array($opts) ? $opts : []);
    }

    private function get_cart_row()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'store_carts';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($row) {
                return $row;
            }

            $key = $this->get_or_set_guest_key();
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
        }

        $key = $this->get_or_set_guest_key();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
    }
}
