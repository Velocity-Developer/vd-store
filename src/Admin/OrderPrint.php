<?php

namespace WpStore\Admin;

class OrderPrint
{
    public function register()
    {
        add_action('admin_post_wp_store_print_invoice', [$this, 'print_invoice']);
        add_action('admin_post_wp_store_print_shipping', [$this, 'print_shipping']);
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);
    }

    public function add_row_actions($actions, $post)
    {
        if ($post->post_type !== 'store_order') {
            return $actions;
        }
        $nonce = wp_create_nonce('wp_store_print_order');
        $invoice_url = add_query_arg([
            'action' => 'wp_store_print_invoice',
            'order_id' => $post->ID,
            '_wpnonce' => $nonce,
        ], admin_url('admin-post.php'));
        $shipping_url = add_query_arg([
            'action' => 'wp_store_print_shipping',
            'order_id' => $post->ID,
            '_wpnonce' => $nonce,
        ], admin_url('admin-post.php'));

        $actions['wp_store_print_invoice'] = '<a href="' . esc_url($invoice_url) . '" target="_blank" rel="noopener">Print Invoice</a>';
        $actions['wp_store_print_shipping'] = '<a href="' . esc_url($shipping_url) . '" target="_blank" rel="noopener">Print Pengiriman</a>';
        return $actions;
    }

    private function ensure_permission()
    {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, 'wp_store_print_order')) {
            wp_die('Nonce invalid', 'Error', ['response' => 403]);
        }
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized', 'Error', ['response' => 403]);
        }
    }

    private function get_order_id()
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        if ($order_id <= 0 || get_post_type($order_id) !== 'store_order') {
            wp_die('Order tidak valid', 'Error', ['response' => 400]);
        }
        return $order_id;
    }

    private function print_header_styles($title = '')
    {
        $settings = get_option('wp_store_settings', []);
        $store_name = isset($settings['store_name']) ? (string) $settings['store_name'] : get_bloginfo('name');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html($title ?: $store_name) . '</title><style>
            * { box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#111827; margin:0; padding:24px; }
            .btn { display:inline-block; padding:8px 12px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#111827; text-decoration:none; }
            .btn-primary { border-color:#3b82f6; color:#fff; background:#3b82f6; }
            .section { margin-bottom:16px; }
            .card { border:1px solid #e5e7eb; border-radius:8px; padding:16px; }
            .title { font-size:18px; font-weight:600; margin-bottom:8px; }
            .grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
            table { width:100%; border-collapse: collapse; }
            th, td { border:1px solid #e5e7eb; padding:8px; text-align:left; font-size:14px; }
            th { background:#f9fafb; }
            .right { text-align:right; }
            .muted { color:#6b7280; font-size:12px; }
            .mb-2 { margin-bottom:8px; }
            @media print {
                .print-actions { display:none; }
                body { padding:0; }
                .card { border:none; padding:0; }
                th, td { border-color:#ddd; }
            }
        </style></head><body>';
        echo '<div class="print-actions section" style="display:flex;gap:8px;justify-content:flex-end;margin-bottom:16px;">';
        echo '<button type="button" class="btn btn-primary" onclick="window.print()">Print</button>';
        echo '<button type="button" class="btn" onclick="window.close()">Tutup</button>';
        echo '</div>';
    }

    private function render_order_summary($order_id)
    {
        $settings = get_option('wp_store_settings', []);
        $currency = isset($settings['currency_symbol']) ? (string) $settings['currency_symbol'] : 'Rp';
        $items = get_post_meta($order_id, '_store_order_items', true);
        $items = is_array($items) ? $items : [];
        $shipping_cost = get_post_meta($order_id, '_store_order_shipping_cost', true);
        $shipping_cost = is_numeric($shipping_cost) ? (float) $shipping_cost : 0;
        $total = get_post_meta($order_id, '_store_order_total', true);
        $total = is_numeric($total) ? (float) $total : 0;

        echo '<div class="section card">';
        echo '<div class="title">Ringkasan Produk</div>';
        echo '<table><thead><tr><th>Produk</th><th class="right">Qty</th><th class="right">Harga</th><th class="right">Subtotal</th></tr></thead><tbody>';
        foreach ($items as $line) {
            $title = isset($line['title']) ? (string) $line['title'] : '';
            $qty = isset($line['qty']) ? (int) $line['qty'] : 0;
            $price = isset($line['price']) ? (float) $line['price'] : 0;
            $subtotal = isset($line['subtotal']) ? (float) $line['subtotal'] : ($price * $qty);
            echo '<tr>';
            echo '<td>' . esc_html($title) . '</td>';
            echo '<td class="right">' . esc_html($qty) . '</td>';
            echo '<td class="right">' . esc_html(($currency ?: 'Rp') . ' ' . number_format($price, 0, ',', '.')) . '</td>';
            echo '<td class="right">' . esc_html(($currency ?: 'Rp') . ' ' . number_format($subtotal, 0, ',', '.')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div style="margin-top:12px">';
        echo '<div class="muted">Ongkir: ' . esc_html(($currency ?: 'Rp') . ' ' . number_format($shipping_cost, 0, ',', '.')) . '</div>';
        echo '<div><strong>Total: ' . esc_html(($currency ?: 'Rp') . ' ' . number_format($total, 0, ',', '.')) . '</strong></div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_shipping_info($order_id)
    {
        $courier = get_post_meta($order_id, '_store_order_shipping_courier', true);
        $service = get_post_meta($order_id, '_store_order_shipping_service', true);
        $tracking = get_post_meta($order_id, '_store_order_tracking_number', true);
        $address = get_post_meta($order_id, '_store_order_address', true);
        $prov = get_post_meta($order_id, '_store_order_province_name', true);
        $city = get_post_meta($order_id, '_store_order_city_name', true);
        $subd = get_post_meta($order_id, '_store_order_subdistrict_name', true);
        $postal = get_post_meta($order_id, '_store_order_postal_code', true);
        $receiver = get_the_title($order_id);

        echo '<div class="section card">';
        echo '<div class="title">Data Pengiriman</div>';
        echo '<div class="grid">';
        echo '<div class="mb-2"><div class="muted">Penerima</div><div>' . esc_html($receiver) . '</div></div>';
        echo '<div class="mb-2"><div class="muted">Kurir</div><div>' . esc_html(strtoupper($courier) . ' ' . $service) . '</div></div>';
        echo '<div class="mb-2"><div class="muted">Alamat</div><div>' . esc_html($address) . '</div></div>';
        echo '<div class="mb-2"><div class="muted">Wilayah</div><div>' . esc_html($prov . ' / ' . $city . ' / ' . $subd) . '</div></div>';
        echo '<div class="mb-2"><div class="muted">Kode Pos</div><div>' . esc_html($postal) . '</div></div>';
        echo '<div class="mb-2"><div class="muted">No. Resi</div><div>' . esc_html($tracking) . '</div></div>';
        echo '</div>';
        echo '</div>';
    }

    private function build_invoice_html($order_id)
    {
        $order = get_post($order_id);
        $settings = get_option('wp_store_settings', []);
        $store_name = isset($settings['store_name']) ? (string) $settings['store_name'] : get_bloginfo('name');
        $title = 'Invoice - ' . $store_name . ' - #' . $order_id;
        ob_start();
        $this->print_header_styles($title);
        echo '<div class="section card"><div class="title">Invoice</div>';
        echo '<div class="muted">Order #' . esc_html($order_id) . ' - ' . esc_html(get_the_date('', $order)) . '</div>';
        echo '</div>';
        $this->render_order_summary($order_id);
        $this->render_shipping_info($order_id);
        echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},150);});</script>';
        echo '</body></html>';
        return (string) ob_get_clean();
    }

    private function build_shipping_html($order_id)
    {
        $settings = get_option('wp_store_settings', []);
        $store_name = isset($settings['store_name']) ? (string) $settings['store_name'] : get_bloginfo('name');
        $title = 'Data Pengiriman - ' . $store_name . ' - #' . $order_id;
        ob_start();
        $this->print_header_styles($title);
        $this->render_shipping_info($order_id);
        echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},150);});</script>';
        echo '</body></html>';
        return (string) ob_get_clean();
    }

    public function print_invoice()
    {
        $this->ensure_permission();
        $order_id = $this->get_order_id();
        $html = $this->build_invoice_html($order_id);
        echo $html;
        exit;
    }

    public function print_shipping()
    {
        $this->ensure_permission();
        $order_id = $this->get_order_id();
        $html = $this->build_shipping_html($order_id);
        echo $html;
        exit;
    }
}
