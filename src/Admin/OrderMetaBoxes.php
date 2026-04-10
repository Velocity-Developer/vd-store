<?php

namespace WpStore\Admin;

class OrderMetaBoxes
{
    public function register()
    {
        add_action('cmb2_admin_init', [$this, 'register_metaboxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('add_meta_boxes', [$this, 'add_proofs_box']);
        add_action('add_meta_boxes', [$this, 'add_summary_box']);
        add_action('add_meta_boxes', [$this, 'add_editable_boxes']);
        add_action('save_post_store_order', [$this, 'save_native_meta_boxes']);
    }

    public function enqueue_styles()
    {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'store_order') {
            wp_enqueue_style(
                'wp-store-admin-cmb2',
                WP_STORE_URL . 'assets/admin/css/xmb2.css',
                [],
                WP_STORE_VERSION
            );

            wp_enqueue_script(
                'wp-store-admin-js',
                WP_STORE_URL . 'assets/admin/js/store-admin.js',
                ['jquery'],
                WP_STORE_VERSION,
                true
            );
        }
    }

    public function register_metaboxes()
    {
        if (!function_exists('new_cmb2_box')) {
            return;
        }

        $status_box = new_cmb2_box([
            'id'            => 'wp_store_order_status_box',
            'title'         => 'Status Pesanan',
            'object_types'  => ['store_order'],
            'context'       => 'side',
            'priority'      => 'high',
            'show_names'    => true,
        ]);

        $status_box->add_field([
            'name'    => 'Status',
            'id'      => '_store_order_status',
            'type'    => 'select',
            'options' => [
                'pending'           => 'Pending',
                'awaiting_payment'  => 'Menunggu Pembayaran',
                'paid'              => 'Sudah Dibayar',
                'processing'        => 'Diproses',
                'shipped'           => 'Dikirim',
                'completed'         => 'Selesai',
                'cancelled'         => 'Dibatalkan',
            ],
            'default' => 'pending',
        ]);

        $status_box->add_field([
            'name' => 'No. Resi',
            'id'   => '_store_order_tracking_number',
            'type' => 'text',
        ]);

        $details = new_cmb2_box([
            'id'            => 'wp_store_order_details',
            'title'         => 'Detail Pengiriman & Pembayaran',
            'object_types'  => ['store_order'],
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true,
        ]);

        $details->add_field([
            'name' => 'Kurir',
            'id'   => '_store_order_shipping_courier',
            'type' => 'text',
        ]);

        $details->add_field([
            'name' => 'Layanan',
            'id'   => '_store_order_shipping_service',
            'type' => 'text',
        ]);

        $details->add_field([
            'name'       => 'Biaya Ongkir',
            'id'         => '_store_order_shipping_cost',
            'type'       => 'text',
            'attributes' => [
                'pattern'     => '^[0-9,.]+$',
                'inputmode'   => 'decimal',
                'placeholder' => 'Contoh: 25000',
            ],
        ]);

        $details->add_field([
            'name'       => 'Total Tagihan',
            'id'         => '_store_order_total',
            'type'       => 'text',
            'attributes' => [
                'readonly' => 'readonly',
            ],
        ]);

        $details->add_field([
            'name' => 'Catatan Admin',
            'id'   => '_store_order_admin_note',
            'type' => 'textarea_small',
        ]);
    }

    public function add_editable_boxes()
    {
        add_meta_box(
            'wp_store_order_status_native',
            'Status & Resi',
            [$this, 'render_status_box'],
            'store_order',
            'side',
            'high'
        );

        add_meta_box(
            'wp_store_order_shipping_native',
            'Pengiriman & Catatan Admin',
            [$this, 'render_shipping_box'],
            'store_order',
            'side',
            'default'
        );
    }

    public function render_status_box($post)
    {
        $order_id = isset($post->ID) ? (int) $post->ID : 0;
        if ($order_id <= 0) {
            echo '<p>Tidak ada data.</p>';
            return;
        }

        wp_nonce_field('wp_store_save_order_meta', 'wp_store_order_meta_nonce');

        $status = (string) get_post_meta($order_id, '_store_order_status', true);
        $tracking = (string) get_post_meta($order_id, '_store_order_tracking_number', true);
        $labels = $this->status_options();

        echo '<p><label for="wp-store-order-status" style="display:block; margin-bottom:6px; font-weight:600;">Status Pesanan</label>';
        echo '<select id="wp-store-order-status" name="_store_order_status" class="widefat">';
        foreach ($labels as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="wp-store-order-tracking-number" style="display:block; margin-bottom:6px; font-weight:600;">No. Resi</label>';
        echo '<input id="wp-store-order-tracking-number" type="text" name="_store_order_tracking_number" class="widefat" value="' . esc_attr($tracking) . '" placeholder="Masukkan nomor resi"></p>';
    }

    public function render_shipping_box($post)
    {
        $order_id = isset($post->ID) ? (int) $post->ID : 0;
        if ($order_id <= 0) {
            echo '<p>Tidak ada data.</p>';
            return;
        }

        $courier = (string) get_post_meta($order_id, '_store_order_shipping_courier', true);
        $service = (string) get_post_meta($order_id, '_store_order_shipping_service', true);
        $shipping_cost = (string) get_post_meta($order_id, '_store_order_shipping_cost', true);
        $admin_note = (string) get_post_meta($order_id, '_store_order_admin_note', true);

        echo '<p><label for="wp-store-order-shipping-courier" style="display:block; margin-bottom:6px; font-weight:600;">Kurir</label>';
        echo '<input id="wp-store-order-shipping-courier" type="text" name="_store_order_shipping_courier" class="widefat" value="' . esc_attr($courier) . '" placeholder="Contoh: ide"></p>';

        echo '<p><label for="wp-store-order-shipping-service" style="display:block; margin-bottom:6px; font-weight:600;">Layanan</label>';
        echo '<input id="wp-store-order-shipping-service" type="text" name="_store_order_shipping_service" class="widefat" value="' . esc_attr($service) . '" placeholder="Contoh: Idtruck"></p>';

        echo '<p><label for="wp-store-order-shipping-cost" style="display:block; margin-bottom:6px; font-weight:600;">Biaya Ongkir</label>';
        echo '<input id="wp-store-order-shipping-cost" type="text" name="_store_order_shipping_cost" class="widefat" value="' . esc_attr($shipping_cost) . '" placeholder="Contoh: 35000"></p>';

        echo '<p><label for="wp-store-order-admin-note" style="display:block; margin-bottom:6px; font-weight:600;">Catatan Admin</label>';
        echo '<textarea id="wp-store-order-admin-note" name="_store_order_admin_note" class="widefat" rows="4" placeholder="Catatan internal admin">' . esc_textarea($admin_note) . '</textarea></p>';
    }

    public function save_native_meta_boxes($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $nonce = isset($_POST['wp_store_order_meta_nonce']) ? (string) $_POST['wp_store_order_meta_nonce'] : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_store_save_order_meta')) {
            return;
        }

        $status = isset($_POST['_store_order_status']) ? sanitize_key((string) $_POST['_store_order_status']) : '';
        $tracking = isset($_POST['_store_order_tracking_number']) ? sanitize_text_field((string) $_POST['_store_order_tracking_number']) : '';
        $courier = isset($_POST['_store_order_shipping_courier']) ? sanitize_text_field((string) $_POST['_store_order_shipping_courier']) : '';
        $service = isset($_POST['_store_order_shipping_service']) ? sanitize_text_field((string) $_POST['_store_order_shipping_service']) : '';
        $shipping_cost = isset($_POST['_store_order_shipping_cost']) ? (float) preg_replace('/[^0-9.]/', '', (string) $_POST['_store_order_shipping_cost']) : 0;
        $admin_note = isset($_POST['_store_order_admin_note']) ? sanitize_textarea_field((string) $_POST['_store_order_admin_note']) : '';

        $allowed_statuses = array_keys($this->status_options());
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'pending';
        }

        update_post_meta($post_id, '_store_order_status', $status);
        update_post_meta($post_id, '_store_order_tracking_number', $tracking);
        update_post_meta($post_id, '_store_order_shipping_courier', $courier);
        update_post_meta($post_id, '_store_order_shipping_service', $service);
        update_post_meta($post_id, '_store_order_shipping_cost', max(0, $shipping_cost));
        update_post_meta($post_id, '_store_order_admin_note', $admin_note);
    }

    private function status_options()
    {
        return [
            'pending' => 'Pending',
            'awaiting_payment' => 'Menunggu Pembayaran',
            'paid' => 'Sudah Dibayar',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];
    }

    public function add_proofs_box()
    {
        add_meta_box(
            'wp_store_order_proofs',
            'Bukti Transfer',
            [$this, 'render_proofs_box'],
            'store_order',
            'normal',
            'default'
        );
    }

    public function render_proofs_box($post)
    {
        $order_id = isset($post->ID) ? (int) $post->ID : 0;
        if ($order_id <= 0) {
            echo '<p>Tidak ada data.</p>';
            return;
        }
        $proofs = get_post_meta($order_id, '_store_order_payment_proofs', true);
        $proofs = is_array($proofs) ? $proofs : [];
        if (empty($proofs)) {
            echo '<p class="description">Belum ada bukti transfer.</p>';
            return;
        }
        echo '<div style="display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:12px;">';
        foreach ($proofs as $pid) {
            $url = wp_get_attachment_url($pid);
            $mime = get_post_mime_type($pid);
            echo '<div class="wps-card" style="border:1px solid #e5e7eb; border-radius:6px; padding:8px;">';
            if ($mime && strpos($mime, 'image/') === 0) {
                $thumb = wp_get_attachment_image_url($pid, 'medium');
                $thumb = $thumb ?: $url;
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">';
                echo '<img src="' . esc_url($thumb) . '" alt="Bukti Transfer" style="width:100%; height:140px; object-fit:cover;">';
                echo '</a>';
            } else {
                $title = get_the_title($pid);
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="button button-small">Lihat Dokumen</a>';
                echo '<div style="margin-top:6px; font-size:12px; color:#374151;">' . esc_html($title) . '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    public function add_summary_box()
    {
        add_meta_box(
            'wp_store_order_summary',
            'Ringkasan Order',
            [$this, 'render_summary_box'],
            'store_order',
            'normal',
            'high'
        );
    }

    public function render_summary_box($post)
    {
        $order_id = isset($post->ID) ? (int) $post->ID : 0;
        if ($order_id <= 0) {
            echo '<p>Tidak ada data.</p>';
            return;
        }
        $title = get_the_title($order_id);
        $customer_name = $title;
        if (strpos($title, ' - ') !== false) {
            $parts = explode(' - ', $title);
            $customer_name = $parts[0];
        }
        $email = (string) get_post_meta($order_id, '_store_order_email', true);
        $phone = (string) get_post_meta($order_id, '_store_order_phone', true);
        $address = (string) get_post_meta($order_id, '_store_order_address', true);
        $province = (string) get_post_meta($order_id, '_store_order_province_name', true);
        $city = (string) get_post_meta($order_id, '_store_order_city_name', true);
        $subdistrict = (string) get_post_meta($order_id, '_store_order_subdistrict_name', true);
        $postal = (string) get_post_meta($order_id, '_store_order_postal_code', true);
        $courier = (string) get_post_meta($order_id, '_store_order_shipping_courier', true);
        $service = (string) get_post_meta($order_id, '_store_order_shipping_service', true);
        $shipping_cost = (float) get_post_meta($order_id, '_store_order_shipping_cost', true);
        $grand_total = (float) get_post_meta($order_id, '_store_order_total', true);
        $payment_method = (string) get_post_meta($order_id, '_store_order_payment_method', true);
        $payment_url = (string) get_post_meta($order_id, '_store_order_payment_url', true);
        $items = get_post_meta($order_id, '_store_order_items', true);
        $items = is_array($items) ? $items : [];
        $product_total = 0;
        foreach ($items as $row) {
            $product_total += isset($row['subtotal']) ? (float) $row['subtotal'] : 0;
        }
        $coupon_code_applied = (string) get_post_meta($order_id, '_store_order_coupon_code', true);
        $discount_amount = (float) get_post_meta($order_id, '_store_order_discount_amount', true);
        $discount_type = (string) get_post_meta($order_id, '_store_order_discount_type', true);
        $discount_value = (float) get_post_meta($order_id, '_store_order_discount_value', true);
        echo '<div class="wps-card" style="border:1px solid #e5e7eb; border-radius:6px; padding:12px;">';
        echo '<h2 style="margin:0 0 10px; font-size:16px;">Pelanggan</h2>';
        echo '<table class="widefat striped" style="margin-bottom:12px;"><tbody>';
        echo '<tr><td style="width:200px;">Nama</td><td>' . esc_html($customer_name) . '</td></tr>';
        echo '<tr><td>Email</td><td>' . esc_html($email) . '</td></tr>';
        echo '<tr><td>Telepon</td><td>' . esc_html($phone) . '</td></tr>';
        echo '</tbody></table>';
        echo '<h2 style="margin:20px 0 10px; font-size:16px;">Alamat Pengiriman</h2>';
        echo '<table class="widefat striped" style="margin-bottom:12px;"><tbody>';
        echo '<tr><td style="width:200px;">Alamat</td><td>' . nl2br(esc_html($address)) . '</td></tr>';
        echo '<tr><td>Provinsi</td><td>' . esc_html($province) . '</td></tr>';
        echo '<tr><td>Kota/Kabupaten</td><td>' . esc_html($city) . '</td></tr>';
        echo '<tr><td>Kecamatan</td><td>' . esc_html($subdistrict) . '</td></tr>';
        echo '<tr><td>Kode Pos</td><td>' . esc_html($postal) . '</td></tr>';
        echo '</tbody></table>';
        echo '<h2 style="margin:20px 0 10px; font-size:16px;">Pengiriman</h2>';
        echo '<table class="widefat striped" style="margin-bottom:12px;"><tbody>';
        echo '<tr><td style="width:200px;">Kurir</td><td>' . esc_html($courier) . '</td></tr>';
        echo '<tr><td>Layanan</td><td>' . esc_html($service) . '</td></tr>';
        echo '<tr><td>Biaya Ongkir</td><td>Rp ' . esc_html(number_format_i18n($shipping_cost, 0)) . '</td></tr>';
        echo '</tbody></table>';
        echo '<h2 style="margin:20px 0 10px; font-size:16px;">Pembayaran</h2>';
        echo '<table class="widefat striped" style="margin-bottom:12px;"><tbody>';
        echo '<tr><td style="width:200px;">Metode</td><td>' . esc_html($payment_method ?: 'bank_transfer') . '</td></tr>';
        if ($payment_url) {
            echo '<tr><td>Link Pembayaran</td><td><a href="' . esc_url($payment_url) . '" target="_blank" rel="noopener">Buka</a></td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2 style="margin:20px 0 10px; font-size:16px;">Item Pesanan</h2>';
        echo '<table class="widefat striped" style="margin-bottom:12px;"><thead>';
        echo '<tr>';
        echo '<th style="width:40%;">Produk</th>';
        echo '<th style="width:20%;">Opsi</th>';
        echo '<th style="width:10%;">Qty</th>';
        echo '<th style="width:15%;">Harga</th>';
        echo '<th style="width:15%;">Subtotal</th>';
        echo '</tr>';
        echo '</thead><tbody>';
        if (empty($items)) {
            echo '<tr><td colspan="5">Tidak ada item.</td></tr>';
        } else {
            foreach ($items as $row) {
                $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
                $price = isset($row['price']) ? (float) $row['price'] : 0;
                $subtotal = isset($row['subtotal']) ? (float) $row['subtotal'] : 0;
                $opts = isset($row['options']) && is_array($row['options']) ? $row['options'] : [];
                $pname = $pid > 0 ? get_the_title($pid) : '';
                $plink = $pid > 0 ? get_edit_post_link($pid) : '';
                $opt_texts = [];
                foreach ($opts as $k => $v) {
                    $opt_texts[] = esc_html($k) . ': ' . esc_html($v);
                }
                $opt_html = implode(', ', $opt_texts);
                echo '<tr>';
                echo '<td>' . ($plink ? '<a href="' . esc_url($plink) . '">' . esc_html($pname) . '</a>' : esc_html($pname)) . '</td>';
                echo '<td>' . ($opt_html !== '' ? $opt_html : '-') . '</td>';
                echo '<td>' . esc_html($qty) . '</td>';
                echo '<td>Rp ' . esc_html(number_format_i18n($price, 0)) . '</td>';
                echo '<td>Rp ' . esc_html(number_format_i18n($subtotal, 0)) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<h2 style="margin:20px 0 10px; font-size:16px;">Ringkasan Total</h2>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><td style="width:200px;">Total Produk</td><td>Rp ' . esc_html(number_format_i18n($product_total, 0)) . '</td></tr>';
        if ($discount_amount > 0) {
            $label = 'Diskon Kupon';
            if ($discount_type === 'percent' && $discount_value > 0) {
                $label = 'Diskon Kupon (' . esc_html(number_format_i18n($discount_value, 0)) . '%)';
            }
            if ($coupon_code_applied !== '') {
                $label .= ' [' . esc_html($coupon_code_applied) . ']';
            }
            echo '<tr><td>' . $label . '</td><td>- Rp ' . esc_html(number_format_i18n($discount_amount, 0)) . '</td></tr>';
        }
        echo '<tr><td>Biaya Ongkir</td><td>Rp ' . esc_html(number_format_i18n($shipping_cost, 0)) . '</td></tr>';
        echo '<tr><td><strong>Total Tagihan</strong></td><td><strong>Rp ' . esc_html(number_format_i18n($grand_total, 0)) . '</strong></td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    }
}
