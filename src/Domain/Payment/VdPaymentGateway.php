<?php

namespace WpStore\Domain\Payment;

/**
 * Jembatan ke plugin VD Payment Gateway (DOKU, dst.). Duitku tetap lewat DuitkuGateway
 * (kelas Velocity_Addons_Duitku, baik asli addons maupun alias dari vd-payment-gateway).
 */
class VdPaymentGateway
{
    const SOURCE = 'vd-store';

    /** Metode pembayaran vd-store yang ditangani langsung oleh vd-payment-gateway. */
    public static function methods()
    {
        return ['doku' => 'DOKU'];
    }

    public static function handles($method)
    {
        return isset(self::methods()[sanitize_key((string) $method)]);
    }

    public static function is_available($method)
    {
        return self::handles($method) && function_exists('vd_pg_is_available') && vd_pg_is_available($method);
    }

    public function register()
    {
        add_action('vd_pg_payment_status_changed', [$this, 'handle_status_changed'], 10, 3);
    }

    public static function create_payment($method, $order_id, array $data, $total)
    {
        if (!self::is_available($method)) {
            return new \WP_Error('vd_pg_not_available', sprintf(__('Gateway %s tidak tersedia.', 'vd-store'), self::methods()[$method] ?? $method));
        }

        $invoice = sanitize_text_field((string) ($data['order_number'] ?? get_post_meta($order_id, '_store_order_number', true)));
        if ($invoice === '') {
            $invoice = 'WPS-' . gmdate('Ymd') . '-' . (int) $order_id;
        }

        $items = [];
        foreach ((array) ($data['items'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }
            $items[] = [
                'name' => (string) ($line['title'] ?? 'Produk'),
                'price' => (int) round((float) ($line['price'] ?? 0)),
                'qty' => max(1, (int) ($line['qty'] ?? 1)),
            ];
        }

        $return_url = trim((string) ($data['return_url'] ?? ''));
        if ($return_url === '') {
            $return_url = self::thanks_url($order_id, $invoice);
        }

        return vd_pg_create_payment($method, [
            'invoice' => $invoice,
            'amount' => (int) round((float) $total),
            'source' => self::SOURCE,
            'ref_id' => (string) (int) $order_id,
            'return_url' => $return_url,
            'description' => 'Pembayaran pesanan ' . $invoice,
            'customer' => [
                'id' => (string) ($data['user_id'] ?? ''),
                'name' => (string) ($data['name'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
                'phone' => (string) ($data['phone'] ?? ''),
                'address' => (string) ($data['address'] ?? ''),
                'city' => (string) ($data['city_name'] ?? ''),
                'postcode' => (string) ($data['postal_code'] ?? ''),
            ],
            'items' => $items,
        ]);
    }

    /**
     * Link bayar untuk halaman terima kasih / lacak pesanan. Link yang sudah kedaluwarsa
     * dibuat ulang (invoice sama) selama pesanan belum dibayar.
     *
     * @return string|\WP_Error
     */
    public static function payment_link($order_id)
    {
        $order_id = (int) $order_id;
        $method = (string) get_post_meta($order_id, '_store_order_payment_method', true);
        $url = (string) get_post_meta($order_id, '_store_order_payment_url', true);
        $expires_at = (int) get_post_meta($order_id, '_store_order_payment_expires_at', true);

        if ($url !== '' && ($expires_at === 0 || $expires_at > time() + 60)) {
            return $url;
        }
        if (!in_array((string) get_post_meta($order_id, '_store_order_status', true), ['pending', 'awaiting_payment'], true)) {
            return $url;
        }

        // Pesanan dari plugin lain (mis. vd-marketplace: tagihan hanya bagian non-COD) tidak boleh
        // dibuat ulang dengan _store_order_total; plugin itu mengurus link bayarnya sendiri.
        $can_regenerate = apply_filters('wp_store_payment_link_can_regenerate', !metadata_exists('post', $order_id, 'vmp_pay_now_total'), $order_id, $method);
        if (!$can_regenerate) {
            return new \WP_Error('wp_store_pay_link_expired', __('Link pembayaran sudah kedaluwarsa.', 'vd-store'));
        }

        // Halaman ini bisa dibuka siapa saja yang tahu nomor pesanan: batasi buat ulang 1x per 5 menit.
        $lock = 'wp_store_pay_regen_' . $order_id;
        if (get_transient($lock)) {
            return new \WP_Error('wp_store_pay_regen_wait', __('Link pembayaran sedang dibuat ulang, coba beberapa menit lagi.', 'vd-store'));
        }
        set_transient($lock, 1, 5 * MINUTE_IN_SECONDS);

        $title = (string) get_the_title($order_id);
        $data = [
            'order_number' => (string) get_post_meta($order_id, '_store_order_number', true),
            'name' => strpos($title, ' - ') !== false ? substr($title, 0, strrpos($title, ' - ')) : $title,
            'email' => (string) get_post_meta($order_id, '_store_order_email', true),
            'phone' => (string) get_post_meta($order_id, '_store_order_phone', true),
            'user_id' => (string) get_post_meta($order_id, '_store_order_user_id', true),
            'address' => (string) get_post_meta($order_id, '_store_order_address', true),
            'city_name' => (string) get_post_meta($order_id, '_store_order_city_name', true),
            'postal_code' => (string) get_post_meta($order_id, '_store_order_postal_code', true),
            'items' => (array) get_post_meta($order_id, '_store_order_items', true),
        ];
        $result = self::create_payment($method, $order_id, $data, (float) get_post_meta($order_id, '_store_order_total', true));
        if (is_wp_error($result)) {
            return $result;
        }

        $extra = get_post_meta($order_id, '_store_order_payment_extra', true);
        $extra = is_array($extra) ? $extra : [];
        $extra['reference'] = sanitize_text_field((string) ($result['reference'] ?? ''));
        $extra['transaction_id'] = (int) ($result['transaction_id'] ?? 0);
        (new \WpStore\Domain\Order\OrderService())->update_payment_data($order_id, [
            'payment_url' => (string) $result['payment_url'],
            'payment_token' => sanitize_text_field((string) ($result['token'] ?? '')),
            'expires_at' => (int) ($result['expires_at'] ?? 0),
            'extra' => $extra,
        ]);

        return (string) $result['payment_url'];
    }

    /** Potongan HTML tombol bayar untuk templates/frontend/pages/{thanks,tracking}.php. */
    public static function render_pay_box($order_id, $method)
    {
        $label = self::methods()[$method] ?? $method;
        $link = self::payment_link($order_id);
        ?>
        <div class="wps-mt-3 wps-p-4" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; text-align:center;">
            <div class="wps-text-sm wps-text-gray-900 wps-font-medium" style="margin-bottom:12px;"><?php echo esc_html(sprintf('Pembayaran via %s', $label)); ?></div>
            <?php if (is_wp_error($link) || $link === '') : ?>
                <div class="wps-text-sm wps-text-gray-700">Link pembayaran belum tersedia. Silakan hubungi admin toko.</div>
            <?php else : ?>
                <a href="<?php echo esc_url($link); ?>" class="wps-btn wps-btn-primary wps-w-full">
                    <?php echo function_exists('wps_icon') ? wps_icon(['name' => 'credit-card', 'size' => 18, 'class' => 'wps-mr-2']) : ''; ?>
                    Bayar Sekarang
                </a>
                <div class="wps-text-xs wps-text-gray-500 wps-mt-3"><?php echo esc_html(sprintf('Anda akan diarahkan ke halaman pembayaran aman %s (Virtual Account, QRIS, e-wallet, kartu).', $label)); ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function thanks_url($order_id, $invoice)
    {
        $settings = get_option('wp_store_settings', []);
        $page_id = isset($settings['page_thanks']) ? absint($settings['page_thanks']) : 0;
        $url = $page_id ? get_permalink($page_id) : '';
        if (!$url) {
            $url = site_url('/thanks/');
        }

        return add_query_arg(['order' => $invoice], $url);
    }

    /** Status dari vd-payment-gateway (notifikasi atau cek status) diteruskan ke pesanan. */
    public function handle_status_changed($transaction, $status, $payload)
    {
        if (!is_object($transaction) || $transaction->source !== self::SOURCE) {
            return;
        }

        $order_id = (int) $transaction->ref_id;
        if ($order_id <= 0 || get_post_type($order_id) !== 'store_order') {
            return;
        }

        // Refund bukan gagal bayar: status pesanan dibiarkan, cukup kabari lewat action sendiri.
        if ($status === 'refunded') {
            do_action('wp_store_payment_refunded', $order_id, (string) $transaction->gateway, $transaction, is_array($payload) ? $payload : []);
            return;
        }

        $normalized = [
            'gateway' => (string) $transaction->gateway,
            'invoice' => (string) $transaction->invoice,
            'reference' => (string) $transaction->reference,
            'payment_code' => (string) $transaction->channel,
            'amount' => (float) $transaction->amount,
            'result_code' => (string) $status,
            'gateway_status' => $status === 'paid' ? 'paid' : (string) $status,
            'callback_at' => current_time('mysql'),
        ];

        (new PaymentService())->apply_gateway_result($order_id, $normalized, is_array($payload) ? $payload : []);
    }
}
