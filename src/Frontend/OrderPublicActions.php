<?php

namespace WpStore\Frontend;

class OrderPublicActions
{
    public function register()
    {
        add_action('wp_ajax_nopriv_wp_store_upload_payment_proof', [$this, 'upload_payment_proof']);
        add_action('wp_ajax_wp_store_upload_payment_proof', [$this, 'upload_payment_proof']);
    }

    public function upload_payment_proof()
    {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, 'wp_store_upload_payment_proof')) {
            wp_send_json_error(['message' => 'Nonce invalid'], 403);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if ($order_id <= 0 || get_post_type($order_id) !== 'store_order') {
            wp_send_json_error(['message' => 'Order invalid'], 400);
        }

        $status = get_post_meta($order_id, '_store_order_status', true);
        $allowed_statuses = apply_filters('wp_store_upload_allowed_statuses', ['pending', 'awaiting_payment'], $order_id);
        if (!in_array($status ?: 'pending', $allowed_statuses, true)) {
            wp_send_json_error(['message' => 'Order status not allowed'], 400);
        }

        if (!is_user_logged_in()) {
            $captcha = Captcha::validate($_POST);
            if (empty($captcha['success'])) {
                wp_send_json_error(['message' => isset($captcha['message']) ? $captcha['message'] : 'Captcha invalid'], 400);
            }
        }

        if (!isset($_FILES['proof'])) {
            wp_send_json_error(['message' => 'File not found'], 400);
        }

        $file = $_FILES['proof'];
        $file = apply_filters('wp_store_before_upload_proof', $file, $order_id);
        if (!isset($file['name']) || !isset($file['tmp_name'])) {
            wp_send_json_error(['message' => 'Invalid file'], 400);
        }

        $allowed = apply_filters('wp_store_upload_proof_allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ], $order_id);
        $type = isset($file['type']) ? $file['type'] : '';
        if ($type && !in_array($type, $allowed, true)) {
            wp_send_json_error(['message' => 'File type not allowed'], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded = wp_handle_upload($file, apply_filters('wp_store_upload_overrides', ['test_form' => false], $order_id));
        if (!isset($uploaded['file']) || isset($uploaded['error'])) {
            wp_send_json_error(['message' => isset($uploaded['error']) ? $uploaded['error'] : 'Upload failed'], 500);
        }

        $filetype = wp_check_filetype(basename($uploaded['file']), null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded['file'])),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $uploaded['file']);
        if (is_wp_error($attach_id) || !$attach_id) {
            wp_send_json_error(['message' => 'Insert attachment failed'], 500);
        }

        $meta = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
        if ($meta) {
            wp_update_attachment_metadata($attach_id, $meta);
        }

        $proofs = get_post_meta($order_id, '_store_order_payment_proofs', true);
        $proofs = is_array($proofs) ? $proofs : [];
        $proofs[] = (int) $attach_id;
        update_post_meta($order_id, '_store_order_payment_proofs', $proofs);

        $url = wp_get_attachment_url($attach_id);

        do_action('wp_store_payment_proof_uploaded', $order_id, (int) $attach_id, (string) $url);
        do_action('wp_store_after_upload_proof', $order_id, (int) $attach_id, (string) $url);

        wp_send_json_success([
            'message' => 'Bukti transfer diunggah',
            'attachment_id' => $attach_id,
            'url' => $url,
        ]);
    }
}
