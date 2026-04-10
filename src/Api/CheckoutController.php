<?php

namespace WpStore\Api;

use WpStore\Domain\Payment\PaymentMethodRegistry;
use WpStore\Domain\Order\OrderService;
use WpStore\Domain\Product\ProductData;

use WP_REST_Request;
use WP_REST_Response;

class CheckoutController
{
    private function service()
    {
        return new OrderService();
    }

    public function register_routes()
    {
        register_rest_route('wp-store/v1', '/checkout', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_order'],
                'permission_callback' => [$this, 'require_rest_nonce'],
            ],
        ]);
    }

    public function require_rest_nonce(WP_REST_Request $request)
    {
        $nonce = $request->get_header('x_wp_nonce');
        if (!$nonce) {
            $nonce = $request->get_header('x-wp-nonce');
        }

        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    public function create_order(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        $data = apply_filters('wp_store_before_create_order', $data, $request);
        $settings = get_option('wp_store_settings', []);
        $disable_shipping_for_digital = !empty($settings['disable_shipping_for_digital']);

        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        $email = isset($data['email']) ? sanitize_email($data['email']) : '';
        $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

        if ($name === '' || empty($items)) {
            return new WP_REST_Response(['message' => 'Data tidak lengkap'], 400);
        }
        if (!is_email($email)) {
            return new WP_REST_RESPONSE(['message' => 'Email tidak valid'], 400);
        }
        $address_required = isset($data['address']) ? sanitize_textarea_field($data['address']) : '';
        if ($phone === '') {
            return new WP_REST_RESPONSE(['message' => 'Telepon wajib diisi'], 400);
        }
        $shipping_courier_req = isset($data['shipping_courier']) ? sanitize_text_field($data['shipping_courier']) : '';
        $shipping_service_req = isset($data['shipping_service']) ? sanitize_text_field($data['shipping_service']) : '';
        $shipping_cost_req = isset($data['shipping_cost']) ? floatval($data['shipping_cost']) : 0;
        $all_digital = true;
        foreach ($items as $it) {
            $pid = isset($it['id']) ? (int) $it['id'] : 0;
            if ($pid > 0 && get_post_type($pid) === 'store_product') {
                $is_digital = ProductData::is_digital($pid);
                if (!$is_digital) {
                    $all_digital = false;
                    break;
                }
            }
        }
        if (!$disable_shipping_for_digital || !$all_digital) {
            if ($address_required === '') {
                return new WP_REST_RESPONSE(['message' => 'Alamat wajib diisi'], 400);
            }
            if ($shipping_courier_req === '' || $shipping_service_req === '' || $shipping_cost_req <= 0) {
                return new WP_REST_RESPONSE(['message' => 'Ongkir belum dipilih atau tidak valid'], 400);
            }
        } else {
            $shipping_courier_req = '';
            $shipping_service_req = '';
            $shipping_cost_req = 0;
        }

        $actor_key = is_user_logged_in() ? ('user:' . get_current_user_id()) : ('guest:' . (isset($_COOKIE['wp_store_cart_key']) ? sanitize_key($_COOKIE['wp_store_cart_key']) : ''));
        $fingerprint = md5(wp_json_encode($items) . '|' . ($data['coupon_code'] ?? '') . '|' . $shipping_courier_req . '|' . $shipping_service_req . '|' . (string) $shipping_cost_req);
        if ($actor_key !== '') {
            $lock_key = 'wp_store_checkout_lock_' . md5($actor_key);
            $existing_lock = get_transient($lock_key);
            if (is_string($existing_lock) && $existing_lock === $fingerprint) {
                return new WP_REST_Response(['message' => 'Order sedang diproses, coba lagi beberapa detik.'], 429);
            }
            set_transient($lock_key, $fingerprint, 10);
        }

        $request_id = isset($data['request_id']) ? sanitize_text_field($data['request_id']) : '';
        if ($request_id !== '') {
            $rid_lock_key = 'wp_store_rid_lock_' . md5($request_id);
            if (get_transient($rid_lock_key)) {
                return new WP_REST_Response(['message' => 'Duplikasi submit terdeteksi'], 409);
            }
            set_transient($rid_lock_key, 1, 20);
            $existing = get_posts([
                'post_type' => 'store_order',
                'post_status' => 'any',
                'meta_key' => '_store_order_request_id',
                'meta_value' => $request_id,
                'posts_per_page' => 1,
                'fields' => 'ids',
            ]);
            if (!empty($existing)) {
                $order_id = (int) $existing[0];
                $order_number = get_post_meta($order_id, '_store_order_number', true) ?: (string) $order_id;
                $order_total = floatval(get_post_meta($order_id, '_store_order_total', true));
                $resp = [
                    'id' => $order_id,
                    'order_number' => $order_number,
                    'total' => $order_total,
                    'message' => 'Pesanan berhasil dibuat',
                ];
                $resp = apply_filters('wp_store_payment_response', $resp, $order_id, null, $data);
                delete_transient($rid_lock_key);
                return new WP_REST_Response($resp, 200);
            }
        }

        $actor_key = is_user_logged_in() ? ('user:' . get_current_user_id()) : ('guest:' . (isset($_COOKIE['wp_store_cart_key']) ? sanitize_key($_COOKIE['wp_store_cart_key']) : ''));
        if ($actor_key !== '') {
            // Jika request_id tersedia, gunakan mekanisme one-time request_id lock saja
            if ($request_id === '') {
                $actor_lock_key = 'wp_store_checkout_actor_lock_' . md5($actor_key);
                if (get_transient($actor_lock_key)) {
                    return new WP_REST_Response(['message' => 'Order sedang diproses'], 429);
                }
                set_transient($actor_lock_key, 1, 10);
            }
        }

        $service = $this->service();
        $total = 0;
        $coupon_code = isset($data['coupon_code']) ? sanitize_text_field($data['coupon_code']) : '';
        $discount_amount = 0;
        $discount_type = '';
        $discount_value = 0;
        $scope = 'product';
        $lines = $service->build_lines($items);
        foreach ($lines as $line) {
            $total += isset($line['subtotal']) ? (float) $line['subtotal'] : 0;
        }

        $lines = apply_filters('wp_store_checkout_lines', $lines, $data);
        $lines = $service->dedupe_lines($lines);
        if (empty($lines)) {
            return new WP_REST_Response(['message' => 'Keranjang kosong'], 400);
        }

        $coupon_id = 0;
        if ($coupon_code !== '') {
            $coupon = $this->find_coupon_by_code($coupon_code);
            if ($coupon) {
                $coupon_id = (int) $coupon->ID;
                $type = $this->normalize_coupon_type((string) get_post_meta($coupon->ID, '_store_coupon_type', true));
                $value_raw = get_post_meta($coupon->ID, '_store_coupon_value', true);
                $value = is_numeric($value_raw) ? floatval($value_raw) : 0;
                $scope = (string) get_post_meta($coupon->ID, '_store_coupon_scope', true) === 'shipping' ? 'shipping' : 'product';
                $min_purchase = max(0, (float) get_post_meta($coupon->ID, '_store_coupon_min_purchase', true));
                $usage_limit = max(0, (int) get_post_meta($coupon->ID, '_store_coupon_usage_limit', true));
                $usage_count = max(0, (int) get_post_meta($coupon->ID, '_store_coupon_usage_count', true));
                $starts_at_raw = (string) get_post_meta($coupon->ID, '_store_coupon_starts_at', true);
                $starts_ts = $starts_at_raw ? strtotime($starts_at_raw) : 0;
                $expires_at_raw = (string) get_post_meta($coupon->ID, '_store_coupon_expires_at', true);
                $expires_ts = $expires_at_raw ? strtotime($expires_at_raw) : 0;
                $now_ts = current_time('timestamp');
                $is_started = !($starts_ts > 0 && $starts_ts > $now_ts);
                $is_not_expired = !($expires_ts > 0 && $expires_ts <= $now_ts);
                $is_min_purchase_met = !($min_purchase > 0 && $total < $min_purchase);
                $is_usage_available = !($usage_limit > 0 && $usage_count >= $usage_limit);
                if ($is_started && $is_not_expired && $is_min_purchase_met && $is_usage_available) {
                    if ($scope === 'shipping') {
                        if ($shipping_cost_req <= 0) {
                            $coupon_id = 0;
                            $coupon_code = '';
                        } else {
                            if ($type === 'percent') {
                                $pct = max(0, min(100, $value));
                                $discount_amount = round(($shipping_cost_req * $pct) / 100);
                                $discount_type = 'percent';
                                $discount_value = $pct;
                            } else {
                                $discount_amount = max(0, $value);
                                $discount_type = 'nominal';
                                $discount_value = $discount_amount;
                            }
                            $discount_amount = min($discount_amount, $shipping_cost_req);
                        }
                    } else {
                        if ($type === 'percent') {
                            $pct = max(0, min(100, $value));
                            $discount_amount = round(($total * $pct) / 100);
                            $discount_type = 'percent';
                            $discount_value = $pct;
                        } else {
                            $discount_amount = max(0, $value);
                            $discount_type = 'nominal';
                            $discount_value = $discount_amount;
                        }
                        $discount_amount = min($discount_amount, $total);
                    }
                } else {
                    $coupon_id = 0;
                    $coupon_code = '';
                }
            }
        }

        $payment_method = isset($data['payment_method']) ? sanitize_key($data['payment_method']) : 'bank_transfer';
        $allowed_methods = apply_filters('wp_store_allowed_payment_methods', (new PaymentMethodRegistry())->available_methods(), $data);
        if (!in_array($payment_method, $allowed_methods, true)) {
            $payment_method = 'bank_transfer';
        }

        $order_result = $service->create_order([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
            'address' => isset($data['address']) ? sanitize_textarea_field($data['address']) : '',
            'province_id' => isset($data['province_id']) ? sanitize_text_field($data['province_id']) : '',
            'province_name' => isset($data['province_name']) ? sanitize_text_field($data['province_name']) : '',
            'city_id' => isset($data['city_id']) ? sanitize_text_field($data['city_id']) : '',
            'city_name' => isset($data['city_name']) ? sanitize_text_field($data['city_name']) : '',
            'subdistrict_id' => isset($data['subdistrict_id']) ? sanitize_text_field($data['subdistrict_id']) : '',
            'subdistrict_name' => isset($data['subdistrict_name']) ? sanitize_text_field($data['subdistrict_name']) : '',
            'postal_code' => isset($data['postal_code']) ? sanitize_text_field($data['postal_code']) : '',
            'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
            'items' => $lines,
            'payment_method' => $payment_method,
            'status' => '',
            'shipping_courier' => $shipping_courier_req,
            'shipping_service' => $shipping_service_req,
            'shipping_cost' => $shipping_cost_req,
            'coupon_code' => $coupon_code,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'discount_amount' => $discount_amount,
            'order_total' => $scope === 'shipping'
                ? max(0, $total) + max(0, $shipping_cost_req - $discount_amount)
                : max(0, $total - $discount_amount) + max(0, $shipping_cost_req),
            'request_id' => $request_id,
        ]);

        if (is_wp_error($order_result)) {
            if ($request_id !== '') {
                delete_transient('wp_store_rid_lock_' . md5($request_id));
            }
            if (isset($actor_lock_key)) {
                delete_transient($actor_lock_key);
            }
            return new WP_REST_Response(['message' => 'Gagal membuat pesanan'], 500);
        }

        $order_id = (int) $order_result['order_id'];
        $order_number = (string) ($order_result['order_number'] ?? $order_id);
        $shipping_courier = $shipping_courier_req;
        $shipping_service = $shipping_service_req;
        $shipping_cost = $shipping_cost_req;
        $order_total = (float) ($order_result['order_total'] ?? (max(0, $total - $discount_amount) + max(0, $shipping_cost)));
        $payment_info = isset($order_result['payment_info']) && is_array($order_result['payment_info']) ? $order_result['payment_info'] : [];
        if ($request_id !== '') {
            delete_transient('wp_store_rid_lock_' . md5($request_id));
        }
        if (isset($actor_lock_key)) {
            delete_transient($actor_lock_key);
        }
        if ($coupon_id > 0) {
            $usage_count = (int) get_post_meta($coupon_id, '_store_coupon_usage_count', true);
            update_post_meta($coupon_id, '_store_coupon_usage_count', $usage_count + 1);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'store_carts';
        $snapshot_items = array_map(function ($l) {
            return [
                'id' => isset($l['product_id']) ? (int) $l['product_id'] : 0,
                'qty' => isset($l['qty']) ? (int) $l['qty'] : 0,
                'price_at_purchase' => isset($l['price']) ? (float) $l['price'] : 0,
                'subtotal' => isset($l['subtotal']) ? (float) $l['subtotal'] : 0,
                'options' => isset($l['options']) && is_array($l['options']) ? $l['options'] : new \stdClass(),
            ];
        }, $lines);
        $shipping_snapshot = [
            'courier' => $shipping_courier,
            'service' => $shipping_service,
            'cost' => $shipping_cost,
            'items' => $snapshot_items,
            'total_products' => $total,
            'discount' => [
                'code' => $coupon_code,
                'type' => $discount_type,
                'value' => $discount_value,
                'amount' => $discount_amount,
            ],
            'grand_total' => $order_total,
            'destination' => [
                'province_id' => sanitize_text_field((string) ($data['province_id'] ?? '')),
                'province_name' => sanitize_text_field((string) ($data['province_name'] ?? '')),
                'city_id' => sanitize_text_field((string) ($data['city_id'] ?? '')),
                'city_name' => sanitize_text_field((string) ($data['city_name'] ?? '')),
                'subdistrict_id' => sanitize_text_field((string) ($data['subdistrict_id'] ?? '')),
                'subdistrict_name' => sanitize_text_field((string) ($data['subdistrict_name'] ?? '')),
                'postal_code' => sanitize_text_field((string) ($data['postal_code'] ?? '')),
                'address' => sanitize_textarea_field((string) ($data['address'] ?? '')),
            ],
        ];
        $shipping_snapshot = apply_filters('wp_store_shipping_snapshot', $shipping_snapshot, $order_id, $data);
        $shipping_json = wp_json_encode($shipping_snapshot);
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($exists) {
                $wpdb->update($table, ['shipping_data' => $shipping_json, 'total_price' => $order_total], ['user_id' => $user_id], ['%s', '%f'], ['%d']);
            } else {
                $wpdb->insert($table, ['user_id' => $user_id, 'cart' => wp_json_encode([]), 'shipping_data' => $shipping_json, 'total_price' => $order_total], ['%d', '%s', '%s', '%f']);
            }
        } else {
            $cookie_key = 'wp_store_cart_key';
            $key = isset($_COOKIE[$cookie_key]) && is_string($_COOKIE[$cookie_key]) && $_COOKIE[$cookie_key] !== '' ? sanitize_key($_COOKIE[$cookie_key]) : '';
            if ($key !== '') {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
                if ($exists) {
                    $wpdb->update($table, ['shipping_data' => $shipping_json, 'total_price' => $order_total], ['guest_key' => $key], ['%s', '%f'], ['%s']);
                } else {
                    $wpdb->insert($table, ['guest_key' => $key, 'cart' => wp_json_encode([]), 'shipping_data' => $shipping_json, 'total_price' => $order_total], ['%s', '%s', '%s', '%f']);
                }
            }
        }

        do_action('wp_store_order_created', $order_id, $data, $lines, $order_total);
        do_action('wp_store_after_create_order', $order_id, $data, $lines, $order_total);
        $resp = [
            'id' => $order_id,
            'order_number' => isset($order_number) ? $order_number : $order_id,
            'total' => $order_total,
            'message' => 'Pesanan berhasil dibuat',
        ];
        if (isset($payment_info) && is_array($payment_info)) {
            if (!empty($payment_info['payment_url'])) {
                $resp['payment_url'] = (string) $payment_info['payment_url'];
            }
            if (!empty($payment_info['payment_token'])) {
                $resp['payment_token'] = (string) $payment_info['payment_token'];
            }
        }
        $resp = apply_filters('wp_store_payment_response', $resp, $order_id, isset($payment_info) ? $payment_info : null, $data);
        return new WP_REST_Response($resp, 201);
    }

    private function find_coupon_by_code($code)
    {
        $q = new \WP_Query([
            'post_type' => 'store_coupon',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_store_coupon_code',
                    'value' => $code,
                    'compare' => '=',
                ],
            ],
            'fields' => 'all',
        ]);
        if ($q->have_posts()) {
            $q->the_post();
            $post = get_post();
            wp_reset_postdata();
            return $post;
        }
        return null;
    }

    private function normalize_coupon_type($type)
    {
        return trim((string) $type) === 'percent' ? 'percent' : 'nominal';
    }
}
