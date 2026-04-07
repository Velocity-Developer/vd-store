<?php

namespace WpStore\Api;

use WpStore\Domain\Product\ProductData;

use WP_REST_Request;
use WP_REST_Response;

class CouponController
{
    public function register_routes()
    {
        register_rest_route('wp-store/v1', '/coupons/validate', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'validate_coupon'],
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

    public function validate_coupon(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $code = isset($params['code']) ? sanitize_text_field($params['code']) : '';
        $items = isset($params['items']) && is_array($params['items']) ? $params['items'] : [];
        if ($code === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kode kupon wajib diisi.',
            ], 400);
        }

        $coupon = $this->find_coupon_by_code($code);
        if (!$coupon) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kupon tidak ditemukan atau tidak aktif.',
            ], 404);
        }

        $total_products = 0;
        foreach ($items as $it) {
            $product_id = isset($it['id']) ? (int) $it['id'] : 0;
            $qty = isset($it['qty']) ? (int) $it['qty'] : 0;
            $opts = isset($it['options']) && is_array($it['options']) ? $it['options'] : [];
            if ($product_id <= 0 || $qty <= 0 || get_post_type($product_id) !== 'store_product') {
                continue;
            }
            $price = $this->resolve_price_with_options($product_id, $this->normalize_options($opts));
            $total_products += ($price * $qty);
        }

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
        if ($starts_ts > 0 && $starts_ts > $now_ts) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kupon belum mulai berlaku.',
            ], 400);
        }
        if ($expires_ts > 0 && $expires_ts <= $now_ts) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kupon sudah kadaluarsa.',
            ], 400);
        }
        if ($min_purchase > 0 && $total_products < $min_purchase) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Minimal belanja untuk kupon ini belum terpenuhi.',
            ], 400);
        }
        if ($usage_limit > 0 && $usage_count >= $usage_limit) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kupon sudah mencapai batas penggunaan.',
            ], 400);
        }
        if ($scope === 'shipping') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Kupon diskon ongkir hanya bisa dipakai setelah ongkir dipilih.',
            ], 400);
        }

        $discount = 0;
        if ($type === 'percent') {
            $pct = max(0, min(100, $value));
            $discount = round(($total_products * $pct) / 100);
        } else {
            $discount = max(0, $value);
        }
        $discount = min($discount, $total_products);

        return new WP_REST_Response([
            'success' => true,
            'code' => get_post_meta($coupon->ID, '_store_coupon_code', true) ?: $code,
            'scope' => $scope,
            'type' => $type,
            'value' => $value,
            'min_purchase' => $min_purchase,
            'discount' => $discount,
            'message' => 'Kupon valid.',
        ], 200);
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

    private function resolve_price_with_options($product_id, $opts)
    {
        return ProductData::resolve_price_with_options((int) $product_id, is_array($opts) ? $opts : []);
    }

    private function normalize_coupon_type($type)
    {
        return trim((string) $type) === 'percent' ? 'percent' : 'nominal';
    }
}

