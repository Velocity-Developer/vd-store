<?php

namespace WpStore\Domain\Wishlist;

use WpStore\Domain\Product\ProductData;

class WishlistService
{
    private $cookie_key = 'wp_store_cart_key';

    public function get_wishlist()
    {
        return $this->format_wishlist($this->get_raw_items());
    }

    public function get_raw_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'store_wishlists';
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $row = $wpdb->get_row($wpdb->prepare("SELECT wishlist FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($row && isset($row->wishlist)) {
                $data = json_decode($row->wishlist, true);
                return is_array($data) ? $data : [];
            }
            $key = $this->get_or_set_guest_key();
            $row = $wpdb->get_row($wpdb->prepare("SELECT wishlist FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
            if ($row && isset($row->wishlist)) {
                $data = json_decode($row->wishlist, true);
                if (is_array($data)) {
                    $this->write_raw_items($data);
                }
                return is_array($data) ? $data : [];
            }
            return [];
        }

        $key = $this->get_or_set_guest_key();
        $row = $wpdb->get_row($wpdb->prepare("SELECT wishlist FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
        if ($row && isset($row->wishlist)) {
            $data = json_decode($row->wishlist, true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    public function write_raw_items($wishlist)
    {
        global $wpdb;
        $wishlist = is_array($wishlist) ? array_values($wishlist) : [];
        $table = $wpdb->prefix . 'store_wishlists';
        $json = wp_json_encode($wishlist);

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($exists) {
                $wpdb->update($table, ['wishlist' => $json], ['user_id' => $user_id], ['%s'], ['%d']);
            } else {
                $wpdb->insert($table, ['user_id' => $user_id, 'wishlist' => $json], ['%d', '%s']);
            }
            return;
        }

        $key = $this->get_or_set_guest_key();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
        if ($exists) {
            $wpdb->update($table, ['wishlist' => $json], ['guest_key' => $key], ['%s'], ['%s']);
        } else {
            $wpdb->insert($table, ['guest_key' => $key, 'wishlist' => $json], ['%s', '%s']);
        }
    }

    public function add_item($product_id, $options = [])
    {
        $product_id = (int) $product_id;
        $options = $this->normalize_options(is_array($options) ? $options : []);
        $wishlist = $this->get_raw_items();
        foreach ($wishlist as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $row_opts = isset($row['opts']) && is_array($row['opts']) ? $this->normalize_options($row['opts']) : [];
            if ($id === $product_id && $this->options_equal($row_opts, $options)) {
                return $wishlist;
            }
        }
        $wishlist[] = ['id' => $product_id, 'opts' => $options];
        $this->write_raw_items($wishlist);

        return $wishlist;
    }

    public function remove_item($product_id, $options = [])
    {
        $product_id = (int) $product_id;
        $options = $this->normalize_options(is_array($options) ? $options : []);
        $wishlist = [];
        foreach ($this->get_raw_items() as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $row_opts = isset($row['opts']) && is_array($row['opts']) ? $this->normalize_options($row['opts']) : [];
            if ($id === $product_id && $this->options_equal($row_opts, $options)) {
                continue;
            }
            if ($id > 0) {
                $wishlist[] = ['id' => $id, 'opts' => $row_opts];
            }
        }

        $this->write_raw_items($wishlist);

        return $wishlist;
    }

    public function clear()
    {
        $this->write_raw_items([]);
    }

    public function format_wishlist($wishlist)
    {
        $items = [];
        foreach ((array) $wishlist as $row) {
            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            $opts = isset($row['opts']) && is_array($row['opts']) ? $row['opts'] : [];
            if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
                continue;
            }
            $price = ProductData::resolve_price_with_options($product_id, $opts);
            $items[] = [
                'id' => $product_id,
                'title' => get_the_title($product_id),
                'price' => $price,
                'image' => get_the_post_thumbnail_url($product_id, 'thumbnail') ?: null,
                'link' => get_permalink($product_id),
                'options' => $opts,
            ];
        }

        return [
            'items' => $items,
            'count' => count($items),
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
}
