<?php

namespace WpStore\Admin;

class OrderActions
{
    public function register()
    {
        add_action('wp_ajax_wp_store_update_order_status', [$this, 'update_order_status']);
    }

    public function update_order_status()
    {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, 'wp_store_update_order_status')) {
            wp_send_json_error(['message' => 'Nonce invalid'], 403);
        }
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
        if ($order_id <= 0 || get_post_type($order_id) !== 'store_order') {
            wp_send_json_error(['message' => 'Order invalid'], 400);
        }
        $valid = ['pending', 'awaiting_payment', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];
        if (!in_array($status, $valid, true)) {
            wp_send_json_error(['message' => 'Status invalid'], 400);
        }
        update_post_meta($order_id, '_store_order_status', $status);
        do_action('wp_store_order_status_updated', $order_id, $status, 'admin_ajax');
        wp_send_json_success(['message' => 'Status updated']);
    }
}

