<?php

namespace WpStore\Admin;

class OrderColumns
{
    public function register()
    {
        add_filter('manage_store_order_posts_columns', [$this, 'add_columns']);
        add_action('manage_store_order_posts_custom_column', [$this, 'render_columns'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_columns($columns)
    {
        $new = [];
        foreach ($columns as $key => $label) {
            if ($key === 'date') {
                continue;
            }
            // Insert Status right after Title
            $new[$key] = $label;
            if ($key === 'title') {
                $new['order_customer'] = 'Pembeli';
                $new['order_payment'] = 'Pembayaran';
                $new['order_total'] = 'Harga';
                $new['order_proofs'] = 'Bukti Transfer';
                $new['order_shipping'] = 'Kurir';
                $new['order_tracking'] = 'Tracking';
                $new['order_status'] = 'Status';
            }
        }
        return $new;
    }

    public function render_columns($column, $post_id)
    {
        if ($column === 'order_customer') {
            $author_id = (int) get_post_field('post_author', $post_id);
            if ($author_id > 0) {
                $u = get_userdata($author_id);
                $name = $u ? ($u->display_name ?: $u->user_login) : '';
                echo esc_html($name ? ('Login (' . $name . ')') : 'Login');
            } else {
                echo 'Guest';
            }
            return;
        }
        if ($column === 'order_status') {
            $status = get_post_meta($post_id, '_store_order_status', true);
            $labels = [
                'pending' => 'Pending',
                'awaiting_payment' => 'Menunggu Pembayaran',
                'paid' => 'Sudah Dibayar',
                'processing' => 'Sedang Diproses',
                'shipped' => 'Dikirim',
                'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan',
            ];
            $nonce = wp_create_nonce('wp_store_update_order_status');
            echo '<select class="wps-order-status-select" data-order-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr($nonce) . '">';
            foreach ($labels as $key => $label) {
                $selected = ($key === $status) ? ' selected' : '';
                echo '<option value="' . esc_attr($key) . '"' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            return;
        }
        if ($column === 'order_tracking') {
            $settings = get_option('wp_store_settings', []);
            $tracking_id = isset($settings['page_tracking']) ? (int) $settings['page_tracking'] : 0;
            $tracking_url = $tracking_id ? get_permalink($tracking_id) : site_url('/tracking-order/');
            if ($tracking_url) {
                $order_number = get_post_meta($post_id, '_store_order_number', true);
                if (!$order_number) {
                    $order_number = $post_id;
                }
                $url = add_query_arg(['order' => $order_number], $tracking_url);
                echo '<a class="button button-small" href="' . esc_url($url) . '" target="_blank" rel="noopener">Tracking</a>';
            } else {
                echo '-';
            }
            return;
        }
        if ($column === 'order_payment') {
            $method = get_post_meta($post_id, '_store_order_payment_method', true);
            $label = ($method === 'qris') ? 'QRIS' : 'Transfer Bank';
            echo esc_html($label);
            return;
        }
        if ($column === 'order_total') {
            $settings = get_option('wp_store_settings', []);
            $currency = isset($settings['currency_symbol']) ? (string) $settings['currency_symbol'] : 'Rp';
            $total = get_post_meta($post_id, '_store_order_total', true);
            $total = is_numeric($total) ? (float) $total : 0;
            echo esc_html(($currency ?: 'Rp') . ' ' . number_format($total, 0, ',', '.'));
            return;
        }
        if ($column === 'order_proofs') {
            $proofs = get_post_meta($post_id, '_store_order_payment_proofs', true);
            $proofs = is_array($proofs) ? $proofs : [];
            $count = count($proofs);
            if ($count === 0) {
                echo '';
                return;
            }
            $first = (int) $proofs[0];
            $mime = $first ? get_post_mime_type($first) : '';
            $url = $first ? wp_get_attachment_url($first) : '';
            if ($mime && strpos($mime, 'image/') === 0) {
                $thumb = wp_get_attachment_image_url($first, 'thumbnail');
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener"><img src="' . esc_url($thumb ?: $url) . '" alt="Bukti" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"></a>';
            } else {
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="button button-small">Dokumen</a>';
            }
            if ($count > 1) {
                echo '<span style="margin-left:6px; font-size:11px; color:#6b7280;">+' . esc_html($count - 1) . '</span>';
            }
            return;
        }
        if ($column === 'order_shipping') {
            $shipping = get_post_meta($post_id, '_store_order_shipping_courier', true) ?? '-';
            $shipping = strtoupper($shipping);
            echo esc_html($shipping);
            $tracking = get_post_meta($post_id, '_store_order_tracking_number', true);
            if ($tracking) {
                echo '<br><small style="font-size:11px; color:#6b7280;">Resi: ' . esc_html($tracking) . '</small>';
            }
            return;
        }
    }

    public function enqueue_scripts()
    {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'store_order' && $screen->base === 'edit') {
            wp_enqueue_style(
                'wp-store-order-columns',
                WP_STORE_URL . 'assets/admin/css/order-columns.css',
                [],
                WP_STORE_VERSION
            );
            wp_enqueue_script(
                'wp-store-order-columns',
                WP_STORE_URL . 'assets/admin/js/order-columns.js',
                ['jquery'],
                WP_STORE_VERSION,
                true
            );
            wp_localize_script('wp-store-order-columns', 'wpStoreOrderColumns', [
                'ajaxUrl' => admin_url('admin-ajax.php')
            ]);
        }
    }
}
