<?php

namespace WpStore\Api;

use WpStore\Domain\Wishlist\WishlistService;

use WP_REST_Request;
use WP_REST_Response;

class WishlistController
{
    private function service()
    {
        return new WishlistService();
    }

    public function register_routes()
    {
        register_rest_route('wp-store/v1', '/wishlist', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_wishlist'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_item'],
                'permission_callback' => [$this, 'require_rest_nonce'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_item_or_clear'],
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

    public function get_wishlist(WP_REST_Request $request)
    {
        return new WP_REST_Response($this->service()->get_wishlist(), 200);
    }

    public function add_item(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = [];
        }
        $product_id = isset($data['id']) ? (int) $data['id'] : 0;
        $options = isset($data['options']) && is_array($data['options']) ? $this->normalize_options($data['options']) : [];

        if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
            return new WP_REST_Response(['message' => 'Produk tidak valid'], 400);
        }

        $wishlist = $this->service()->add_item($product_id, $options);

        return new WP_REST_Response($this->service()->format_wishlist($wishlist), 200);
    }

    public function remove_item_or_clear(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = [];
        }
        $product_id = isset($data['id']) ? (int) $data['id'] : 0;
        $options = isset($data['options']) && is_array($data['options']) ? $this->normalize_options($data['options']) : [];

        if ($product_id > 0) {
            $wishlist = $this->service()->remove_item($product_id, $options);
        } else {
            $this->service()->clear();
            $wishlist = [];
        }
        return new WP_REST_Response($this->service()->format_wishlist($wishlist), 200);
    }
}

