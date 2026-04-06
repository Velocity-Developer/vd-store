<?php

namespace WpStore\Domain\Order;

use WpStore\Domain\Payment\PaymentService;
use WpStore\Domain\Product\ProductData;

class OrderService
{
    public function build_lines($items, $trust_price = false)
    {
        $lines = [];

        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $product_id = isset($item['product_id']) ? (int) $item['product_id'] : (isset($item['id']) ? (int) $item['id'] : 0);
            $qty = isset($item['qty']) ? (int) $item['qty'] : 1;
            $opts = [];
            if (isset($item['options']) && is_array($item['options'])) {
                $opts = $item['options'];
            } elseif (isset($item['opts']) && is_array($item['opts'])) {
                $opts = $item['opts'];
            }

            if ($product_id <= 0 || $qty <= 0 || get_post_type($product_id) !== 'store_product') {
                continue;
            }

            $opts = $this->normalize_options($opts);
            $price = $trust_price && isset($item['price']) && is_numeric($item['price'])
                ? (float) $item['price']
                : $this->resolve_price_with_options($product_id, $opts);
            $subtotal = $price * $qty;

            $line = is_array($item) ? $item : [];
            $line['product_id'] = $product_id;
            $line['title'] = isset($item['title']) ? sanitize_text_field((string) $item['title']) : get_the_title($product_id);
            $line['qty'] = $qty;
            $line['price'] = $price;
            $line['subtotal'] = $subtotal;
            $line['options'] = $opts;

            $lines[] = $line;
        }

        return $lines;
    }

    public function dedupe_lines($lines)
    {
        $out = [];
        $map = [];

        foreach ((array) $lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $pid = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $qty = isset($line['qty']) ? (int) $line['qty'] : 0;
            $price = isset($line['price']) ? (float) $line['price'] : 0;
            $opts = isset($line['options']) && is_array($line['options']) ? $this->normalize_options($line['options']) : [];
            $seller_id = isset($line['seller_id']) ? (int) $line['seller_id'] : 0;

            if ($pid <= 0 || $qty <= 0) {
                continue;
            }

            $key = $pid . '|' . $seller_id . '|' . wp_json_encode($opts);
            if (!isset($map[$key])) {
                $map[$key] = count($out);
                $line['product_id'] = $pid;
                $line['title'] = isset($line['title']) ? (string) $line['title'] : get_the_title($pid);
                $line['qty'] = $qty;
                $line['price'] = $price;
                $line['subtotal'] = $price * $qty;
                $line['options'] = $opts;
                $out[] = $line;
                continue;
            }

            $idx = (int) $map[$key];
            $out[$idx]['qty'] += $qty;
            $out[$idx]['subtotal'] = (float) $out[$idx]['price'] * (int) $out[$idx]['qty'];
        }

        return array_values($out);
    }

    public function create_order($data)
    {
        $data = is_array($data) ? $data : [];
        $lines = isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : [];
        if (empty($lines)) {
            return new \WP_Error('empty_order_items', 'Item pesanan kosong.');
        }

        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new \WP_Error('missing_customer_name', 'Nama pembeli wajib diisi.');
        }

        $post_args = [
            'post_type' => 'store_order',
            'post_status' => 'publish',
            'post_title' => sanitize_text_field((string) ($data['post_title'] ?? ($name . ' - ' . current_time('mysql')))),
        ];
        $post_args = apply_filters('wp_store_order_post_args', $post_args, $data);
        $order_id = wp_insert_post($post_args);

        if (is_wp_error($order_id) || !$order_id) {
            return new \WP_Error('order_create_failed', 'Gagal membuat pesanan.');
        }

        $order_number = sanitize_text_field((string) ($data['order_number'] ?? ''));
        if ($order_number === '') {
            $order_number = $this->generate_order_number($order_id);
        }

        $payment_method = $this->normalize_payment_method((string) ($data['payment_method'] ?? 'bank_transfer'));
        $payment_method = apply_filters('wp_store_payment_method', $payment_method, $data, $order_id);
        $status_seed = (string) ($data['status'] ?? '');
        if ($status_seed === '') {
            $status_seed = (string) apply_filters('wp_store_default_order_status', 'awaiting_payment', $order_id, $data);
        }
        $status = $this->map_external_status($status_seed);
        $user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $discount_amount = max(0, (float) ($data['discount_amount'] ?? 0));
        $shipping_cost = max(0, (float) ($data['shipping_cost'] ?? 0));
        $total = isset($data['order_total']) ? max(0, (float) $data['order_total']) : 0;
        if ($total <= 0) {
            $product_total = 0;
            foreach ($lines as $line) {
                $product_total += isset($line['subtotal']) ? (float) $line['subtotal'] : 0;
            }
            $total = max(0, $product_total - $discount_amount) + $shipping_cost;
        }

        update_post_meta($order_id, '_store_order_number', $order_number);
        update_post_meta($order_id, '_store_order_email', sanitize_email((string) ($data['email'] ?? '')));
        if ($user_id > 0) {
            update_post_meta($order_id, '_store_order_user_id', $user_id);
        }
        update_post_meta($order_id, '_store_order_phone', sanitize_text_field((string) ($data['phone'] ?? '')));
        update_post_meta($order_id, '_store_order_total', $total);
        update_post_meta($order_id, '_store_order_items', $lines);
        update_post_meta($order_id, '_store_order_payment_method', $payment_method);
        update_post_meta($order_id, '_store_order_status', $status);
        update_post_meta($order_id, '_store_order_address', sanitize_textarea_field((string) ($data['address'] ?? '')));
        update_post_meta($order_id, '_store_order_province_id', sanitize_text_field((string) ($data['province_id'] ?? '')));
        update_post_meta($order_id, '_store_order_province_name', sanitize_text_field((string) ($data['province_name'] ?? '')));
        update_post_meta($order_id, '_store_order_city_id', sanitize_text_field((string) ($data['city_id'] ?? '')));
        update_post_meta($order_id, '_store_order_city_name', sanitize_text_field((string) ($data['city_name'] ?? '')));
        update_post_meta($order_id, '_store_order_subdistrict_id', sanitize_text_field((string) ($data['subdistrict_id'] ?? '')));
        update_post_meta($order_id, '_store_order_subdistrict_name', sanitize_text_field((string) ($data['subdistrict_name'] ?? '')));
        update_post_meta($order_id, '_store_order_postal_code', sanitize_text_field((string) ($data['postal_code'] ?? '')));
        update_post_meta($order_id, '_store_order_notes', sanitize_textarea_field((string) ($data['notes'] ?? '')));
        update_post_meta($order_id, '_store_order_shipping_courier', sanitize_text_field((string) ($data['shipping_courier'] ?? '')));
        update_post_meta($order_id, '_store_order_shipping_service', sanitize_text_field((string) ($data['shipping_service'] ?? '')));
        update_post_meta($order_id, '_store_order_shipping_cost', $shipping_cost);

        $coupon_code = sanitize_text_field((string) ($data['coupon_code'] ?? ''));
        if ($coupon_code !== '' && $discount_amount > 0) {
            update_post_meta($order_id, '_store_order_coupon_code', $coupon_code);
            update_post_meta($order_id, '_store_order_discount_type', sanitize_text_field((string) ($data['discount_type'] ?? 'nominal')));
            update_post_meta($order_id, '_store_order_discount_value', (float) ($data['discount_value'] ?? $discount_amount));
            update_post_meta($order_id, '_store_order_discount_amount', $discount_amount);
        } else {
            delete_post_meta($order_id, '_store_order_coupon_code');
            delete_post_meta($order_id, '_store_order_discount_type');
            delete_post_meta($order_id, '_store_order_discount_value');
            delete_post_meta($order_id, '_store_order_discount_amount');
        }

        $request_id = sanitize_text_field((string) ($data['request_id'] ?? ''));
        if ($request_id !== '') {
            update_post_meta($order_id, '_store_order_request_id', $request_id);
        }

        $payment_info = [
            'payment_url' => '',
            'payment_token' => '',
            'expires_at' => 0,
            'extra' => new \stdClass(),
        ];

        if (!isset($data['init_payment']) || $data['init_payment']) {
            $payment_service = new PaymentService();
            $payment_info = $payment_service->initialize_order_payment($order_id, $payment_method, $data, $total);
            if (is_wp_error($payment_info)) {
                wp_delete_post($order_id, true);
                return $payment_info;
            }
            if (is_array($payment_info)) {
                $this->update_payment_data($order_id, $payment_info);
                do_action('wp_store_payment_initialized', $order_id, $payment_info);
            }
        } elseif (!empty($data['payment_info']) && is_array($data['payment_info'])) {
            $payment_info = $data['payment_info'];
            $this->update_payment_data($order_id, $payment_info);
        }

        return [
            'order_id' => (int) $order_id,
            'order_number' => $order_number,
            'order_total' => (float) $total,
            'payment_method' => $payment_method,
            'status' => $status,
            'payment_info' => is_array($payment_info) ? $payment_info : [],
        ];
    }

    public function update_status($order_id, $status)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return;
        }

        update_post_meta($order_id, '_store_order_status', $this->map_external_status($status));
    }

    public function update_payment_data($order_id, $payment_info)
    {
        $order_id = (int) $order_id;
        $payment_info = is_array($payment_info) ? $payment_info : [];
        if ($order_id <= 0) {
            return;
        }

        $payment_url = isset($payment_info['payment_url']) ? (string) $payment_info['payment_url'] : '';
        $payment_token = isset($payment_info['payment_token']) ? (string) $payment_info['payment_token'] : '';
        $expires_at = isset($payment_info['expires_at']) ? (int) $payment_info['expires_at'] : 0;
        $extra = isset($payment_info['extra']) ? $payment_info['extra'] : new \stdClass();
        if (!is_array($extra)) {
            $extra = new \stdClass();
        }

        update_post_meta($order_id, '_store_order_payment_url', esc_url_raw($payment_url));
        update_post_meta($order_id, '_store_order_payment_token', sanitize_text_field($payment_token));
        update_post_meta($order_id, '_store_order_payment_expires_at', $expires_at);
        update_post_meta($order_id, '_store_order_payment_extra', $extra);
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

    public function normalize_payment_method($method)
    {
        $method = sanitize_key((string) $method);
        $map = [
            'bank' => 'bank_transfer',
        ];

        return isset($map[$method]) ? $map[$method] : ($method !== '' ? $method : 'bank_transfer');
    }

    public function map_external_status($status)
    {
        $status = sanitize_key((string) $status);
        $map = [
            'pending_payment' => 'awaiting_payment',
            'pending_verification' => 'awaiting_payment',
            'refunded' => 'cancelled',
            'paid' => 'paid',
            'processing' => 'processing',
            'shipped' => 'shipped',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'awaiting_payment' => 'awaiting_payment',
            'pending' => 'pending',
        ];

        return isset($map[$status]) ? $map[$status] : 'pending';
    }

    private function generate_order_number($order_id)
    {
        $rand_suffix = str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT);
        return date('Ymd') . (int) $order_id . $rand_suffix;
    }

    private function resolve_price_with_options($product_id, $opts)
    {
        return ProductData::resolve_price_with_options((int) $product_id, is_array($opts) ? $opts : []);
    }
}
