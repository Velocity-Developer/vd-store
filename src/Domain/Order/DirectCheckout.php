<?php

namespace WpStore\Domain\Order;

use WpStore\Domain\Cart\CartService;
use WpStore\Domain\Product\ProductData;

/** A short-lived checkout source that never reads or writes the shopping cart. */
class DirectCheckout
{
    public static function token($request): string
    {
        $token = $request->get_param('direct_checkout');
        return $token === null ? '' : (is_string($token) && $token !== '' ? $token : 'invalid');
    }

    private static function error($message, $status = 400)
    {
        return new \WP_Error('direct_checkout_error', $message, ['status' => $status]);
    }

    public static function validate_item($item)
    {
        if (!is_array($item) || !is_scalar($item['id'] ?? null) || !is_scalar($item['qty'] ?? null)) {
            return self::error('Produk atau jumlah tidak valid.');
        }
        $id = (int) $item['id'];
        $qty = filter_var($item['qty'], FILTER_VALIDATE_INT);
        if (!$qty || $qty < 1 || get_post_status($id) !== 'publish' || !ProductData::is_purchasable($id)) {
            return self::error('Produk tidak tersedia atau jumlah tidak valid.');
        }
        $product = ProductData::map_post($id);
        if (!$product || $qty < max(1, (int) $product['min_order'])) {
            return self::error('Jumlah belum memenuhi minimal pembelian.');
        }
        if ($product['stock'] !== null && $qty > (int) $product['stock']) {
            return self::error('Stok produk tidak mencukupi.');
        }
        $submitted = $item['options'] ?? $item['opts'] ?? [];
        if (!is_array($submitted)) {
            return self::error('Pilihan produk tidak valid.');
        }
        $options = [];
        foreach (['variant', 'price_adjustment'] as $kind) {
            $name = (string) ($product[$kind . '_name'] ?? '');
            $rows = $product[$kind . '_options'] ?? [];
            if ($name === '' || !$rows) {
                continue;
            }
            $allowed = array_map(static function ($row) {
                return is_array($row) ? (string) ($row['label'] ?? '') : (string) $row;
            }, $rows);
            $value = $submitted[$name] ?? null;
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                return self::error('Pilih ' . $name . ' yang tersedia.');
            }
            $options[$name] = $value;
        }
        if (array_diff_key($submitted, $options)) {
            return self::error('Pilihan produk tidak dikenal.');
        }
        return ['id' => $id, 'qty' => (int) $qty, 'opts' => $options];
    }

    public static function create($item)
    {
        $row = self::validate_item($item);
        if (is_wp_error($row)) {
            return $row;
        }
        $token = bin2hex(random_bytes(24));
        $session = ['actor' => (new CartService())->get_actor_key(), 'item' => $row, 'expires' => time() + HOUR_IN_SECONDS];
        if (!set_transient('wps_direct_' . $token, $session, HOUR_IN_SECONDS)) {
            return self::error('Sesi checkout gagal disimpan. Coba lagi.', 500);
        }
        return ['token' => $token, 'expires_in' => HOUR_IN_SECONDS];
    }

    public static function read($token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/D', $token)) {
            return self::error('Sesi Beli Sekarang tidak valid.', 400);
        }
        $session = get_transient('wps_direct_' . $token);
        if (!is_array($session) || $session['expires'] <= time()) {
            return self::error('Sesi Beli Sekarang berakhir. Buka produk dan klik Beli Sekarang lagi.', 410);
        }
        if (!hash_equals($session['actor'], (new CartService())->get_actor_key())) {
            return self::error('Sesi Beli Sekarang bukan milik pembeli ini. Buka produk dan mulai lagi.', 403);
        }
        if (!empty($session['order_id'])) {
            return self::error('Sesi ini sudah menghasilkan pesanan. Periksa pesanan Anda sebelum membeli lagi.', 409);
        }
        $row = self::validate_item($session['item']);
        return is_wp_error($row) ? $row : [$row];
    }

    /** Mark consumed before payment initialization; retries must not create a second order. */
    public static function complete($token, $order_id): void
    {
        $session = get_transient('wps_direct_' . $token);
        if (is_array($session)) {
            $session['order_id'] = (int) $order_id;
            unset($session['item']);
            set_transient('wps_direct_' . $token, $session, max(1, $session['expires'] - time()));
        }
        update_post_meta($order_id, '_store_order_checkout_source', 'direct');
    }

    /** Serialize submits across both checkout endpoints, leaving cart checkout untouched. */
    public static function submit($request, callable $callback)
    {
        $token = self::token($request);
        if ($token === '') {
            return $callback($request);
        }
        $rows = self::read($token);
        if (is_wp_error($rows)) {
            return $rows;
        }
        $key = 'wps_direct_lock_' . $token;
        if (!add_option($key, time(), '', false)) {
            return self::error('Pesanan sedang diproses. Tunggu sebentar.', 409);
        }
        $released = false;
        register_shutdown_function(static function () use ($key, &$released) {
            if (!$released) {
                delete_option($key);
            }
        });
        try {
            $rows = self::read($token);
            return is_wp_error($rows) ? $rows : $callback($request);
        } finally {
            delete_option($key);
            $released = true;
        }
    }
}
