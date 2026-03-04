<?php
$order_id = isset($order_id) ? (int) $order_id : 0;
$currency = isset($currency) ? (string) $currency : 'Rp';
$order_exists = ($order_id > 0 && get_post_type($order_id) === 'store_order');
$total = $order_exists ? (float) get_post_meta($order_id, '_store_order_total', true) : 0;
$items = $order_exists ? get_post_meta($order_id, '_store_order_items', true) : [];
$items = is_array($items) ? $items : [];
$shipping_courier = $order_exists ? get_post_meta($order_id, '_store_order_shipping_courier', true) : '';
$shipping_service = $order_exists ? get_post_meta($order_id, '_store_order_shipping_service', true) : '';
$shipping_cost = $order_exists ? (float) get_post_meta($order_id, '_store_order_shipping_cost', true) : 0;
$address = $order_exists ? get_post_meta($order_id, '_store_order_address', true) : '';
$province_name = $order_exists ? get_post_meta($order_id, '_store_order_province_name', true) : '';
$city_name = $order_exists ? get_post_meta($order_id, '_store_order_city_name', true) : '';
$subdistrict_name = $order_exists ? get_post_meta($order_id, '_store_order_subdistrict_name', true) : '';
$postal_code = $order_exists ? get_post_meta($order_id, '_store_order_postal_code', true) : '';
$payment_method = $order_exists ? get_post_meta($order_id, '_store_order_payment_method', true) : '';
$shop_archive = function_exists('get_post_type_archive_link') ? get_post_type_archive_link('store_product') : '';
$shop_url = $shop_archive ?: site_url('/produk/');
$settings = get_option('wp_store_settings', []);
$order_number = $order_exists ? get_post_meta($order_id, '_store_order_number', true) : '';
if (!$order_number) {
    $order_number = $order_id;
}
?>
<div class="wps-container">
    <div class="wps-card wps-p-6">
        <div class="wps-text-center">
            <div class="wps-text-2xl wps-font-extrabold wps-text-gray-900">Terima Kasih</div>
            <div class="wps-text-sm wps-text-gray-600 wps-mt-1">Pesanan Anda sudah kami terima.</div>
            <?php if ($order_exists) : ?>
                <div class="wps-mt-2 wps-text-sm wps-text-gray-700">Nomor Pesanan: <span class="wps-font-medium">#<?php echo esc_html($order_number); ?></span></div>
            <?php endif; ?>
        </div>
        <?php if ($order_exists) : ?>
            <div class="wps-divider wps-mt-6 wps-mb-4"></div>
            <?php
            $bank_accounts = [];
            if (isset($settings['store_bank_accounts']) && is_array($settings['store_bank_accounts'])) {
                $bank_accounts = $settings['store_bank_accounts'];
            } else {
                $legacy_bank = [
                    'bank_name' => isset($settings['bank_name']) ? (string) $settings['bank_name'] : '',
                    'bank_account' => isset($settings['bank_account']) ? (string) $settings['bank_account'] : '',
                    'bank_holder' => isset($settings['bank_holder']) ? (string) $settings['bank_holder'] : '',
                ];
                if ($legacy_bank['bank_name'] !== '' || $legacy_bank['bank_account'] !== '' || $legacy_bank['bank_holder'] !== '') {
                    $bank_accounts[] = $legacy_bank;
                }
            }
            ?>
            <div class="wps-grid wps-two-col" style="display:grid; gap: 1rem; grid-template-columns: 1.2fr 0.8fr;">
                <div>
                    <div class="wps-text-lg wps-font-medium wps-text-gray-900">Ringkasan Pesanan</div>
                    <div class="wps-mt-2">
                        <?php if (empty($items)) : ?>
                            <div class="wps-text-sm wps-text-gray-500">Tidak ada item.</div>
                        <?php else : ?>
                            <table class="wps-text-sm wps-table wps-table-striped">
                                <thead class="wps-table-head">
                                    <tr>
                                        <th class="wps-text-left wps-th">Produk</th>
                                        <th class="wps-text-right wps-th">Harga</th>
                                        <th class="wps-text-right wps-th">Qty</th>
                                        <th class="wps-text-right wps-th">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $i => $it) :
                                        $title = isset($it['title']) ? (string) $it['title'] : '';
                                        $qty = isset($it['qty']) ? (int) $it['qty'] : 0;
                                        $price = isset($it['price']) ? (float) $it['price'] : 0;
                                        $subtotal = isset($it['subtotal']) ? (float) $it['subtotal'] : ($price * $qty);
                                    ?>
                                        <tr>
                                            <td class="wps-text-gray-900 wps-td"><?php echo esc_html($title); ?></td>
                                            <td class="wps-text-gray-700 wps-text-right wps-td"><?php echo esc_html(($currency ?: 'Rp') . ' ' . number_format($price, 0, ',', '.')); ?></td>
                                            <td class="wps-text-gray-700 wps-text-right wps-td"><?php echo esc_html($qty); ?></td>
                                            <td class="wps-text-gray-900 wps-text-right wps-td"><?php echo esc_html(($currency ?: 'Rp') . ' ' . number_format($subtotal, 0, ',', '.')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="wps-mt-4 wps-p-4" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                <?php
                                $product_total = 0;
                                foreach ($items as $it) {
                                    $product_total += isset($it['subtotal']) ? (float) $it['subtotal'] : 0;
                                }
                                $coupon_code_applied = (string) get_post_meta($order_id, '_store_order_coupon_code', true);
                                $discount_amount = (float) get_post_meta($order_id, '_store_order_discount_amount', true);
                                $discount_type = (string) get_post_meta($order_id, '_store_order_discount_type', true);
                                $discount_value = (float) get_post_meta($order_id, '_store_order_discount_value', true);
                                if ($discount_amount <= 0) {
                                    $calc = ($product_total + $shipping_cost) - $total;
                                    if ($calc > 0) {
                                        $discount_amount = $calc;
                                    }
                                }
                                ?>
                                <div class="wps-flex wps-justify-between wps-items-center">
                                    <div class="wps-text-sm wps-text-gray-500">Total Produk</div>
                                    <div class="wps-text-sm wps-text-gray-900"><?php echo esc_html(($currency ?: 'Rp') . ' ' . number_format($product_total, 0, ',', '.')); ?></div>
                                </div>
                                <?php if ($discount_amount > 0) : ?>
                                    <div class="wps-flex wps-justify-between wps-items-center wps-mt-2">
                                        <div class="wps-text-sm wps-text-gray-500">
                                            <?php
                                            $label = 'Diskon Kupon';
                                            if ($discount_type === 'percent' && $discount_value > 0) {
                                                $label = 'Diskon Kupon (' . number_format_i18n($discount_value, 0) . '%)';
                                            }
                                            if ($coupon_code_applied !== '') {
                                                $label .= ' [' . esc_html($coupon_code_applied) . ']';
                                            }
                                            echo esc_html($label);
                                            ?>
                                        </div>
                                        <div class="wps-text-sm wps-text-green-700">-<?php echo esc_html(($currency ?: 'Rp') . ' ' . number_format($discount_amount, 0, ',', '.')); ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="wps-flex wps-justify-between wps-items-center wps-mt-2">
                                    <?php
                                    $courier_labels = [
                                        'jne' => 'JNE',
                                        'sicepat' => 'SiCepat',
                                        'ide' => 'IDExpress',
                                        'sap' => 'SAP Express',
                                        'ninja' => 'Ninja',
                                        'jnt' => 'J&T Express',
                                        'tiki' => 'TIKI',
                                        'wahana' => 'Wahana Express',
                                        'pos' => 'POS Indonesia',
                                        'sentral' => 'Sentral Cargo',
                                        'lion' => 'Lion Parcel',
                                        'rex' => 'Royal Express Asia',
                                    ];
                                    $courier_label = isset($courier_labels[$shipping_courier]) ? $courier_labels[$shipping_courier] : strtoupper((string)$shipping_courier);
                                    ?>
                                    <div class="wps-text-sm wps-text-gray-500">Ongkir (<?php echo esc_html($courier_label . ' ' . $shipping_service); ?>)</div>
                                    <div class="wps-text-sm wps-text-gray-900"><?php echo esc_html(($currency ?: 'Rp') . ' ' . number_format($shipping_cost, 0, ',', '.')); ?></div>
                                </div>
                                <div class="wps-flex wps-justify-between wps-items-center wps-mt-2" style="border-top:1px dashed #e5e7eb; padding-top:12px;">
                                    <div class="wps-text-sm wps-text-gray-900 wps-font-medium">Total Tagihan</div>
                                    <div class="wps-text-sm wps-text-gray-900 wps-font-medium"><?php echo esc_html(($currency ?: 'Rp') . ' ' . number_format($total, 0, ',', '.')); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mt-4">Alamat Pengiriman</div>
                    <div class="wps-mt-2 wps-text-sm wps-text-gray-700">
                        <div><?php echo esc_html($address); ?></div>
                        <div><?php echo esc_html($subdistrict_name); ?>, <?php echo esc_html($city_name); ?>, <?php echo esc_html($province_name); ?> <?php echo esc_html($postal_code); ?></div>
                    </div>
                </div>
                <div>
                    <div class="wps-text-lg wps-font-medium wps-text-gray-900">Informasi Pembayaran</div>
                    <div class="wps-text-sm wps-text-gray-700 wps-mt-1">Gunakan nomor pesanan <span class="wps-font-medium">#<?php echo esc_html($order_number); ?></span> sebagai berita.</div>
                    <div class="wps-mt-3">
                        <div class="wps-flex wps-justify-between wps-items-center">
                            <div class="wps-text-sm wps-text-gray-500">Total yang harus dibayar</div>
                            <div class="wps-text-sm wps-text-gray-900 wps-font-medium"><?php echo esc_html(($currency ?: 'Rp') . ' ' . number_format($total, 0, ',', '.')); ?></div>
                        </div>
                    </div>
                    <?php if ($payment_method === 'qris') : ?>
                        <?php
                        $qris_id = isset($settings['qris_image_id']) ? absint($settings['qris_image_id']) : 0;
                        $qris_src = $qris_id ? wp_get_attachment_image_url($qris_id, 'medium') : '';
                        $qris_label = isset($settings['qris_label']) ? (string) $settings['qris_label'] : 'QRIS';
                        ?>
                        <div class="wps-mt-3 wps-p-4" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; text-align:center;">
                            <div class="wps-text-sm wps-text-gray-900 wps-font-medium" style="margin-bottom:8px;"><?php echo esc_html($qris_label); ?></div>
                            <div class="wps-mt-2">
                                <img src="<?php echo esc_url($qris_src ?: WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>" alt="QRIS" style="width:180px;height:180px; object-fit:contain;">
                            </div>
                            <div class="wps-text-xs wps-text-gray-500 wps-mt-2">Scan untuk membayar via QRIS.</div>
                        </div>
                    <?php else : ?>
                        <?php if (!empty($bank_accounts)) : ?>
                            <div class="wps-mt-3">
                                <?php foreach ($bank_accounts as $acc) : ?>
                                    <div class="wps-card wps-p-4 wps-mb-2">
                                        <div class="wps-text-sm wps-text-gray-900 wps-font-medium" style="margin-bottom:6px;"><?php echo esc_html($acc['bank_name'] ?? ''); ?></div>
                                        <div class="wps-text-sm wps-text-gray-700">
                                            <div>No. Rekening: <span class="wps-font-medium"><?php echo esc_html($acc['bank_account'] ?? ''); ?></span></div>
                                            <div>Atas Nama: <span class="wps-font-medium"><?php echo esc_html($acc['bank_holder'] ?? ''); ?></span></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="wps-text-xs wps-text-gray-500 wps-mt-4">Setelah pembayaran, kirim bukti transfer melalui kontak yang tersedia atau tunggu konfirmasi dari kami.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php
                    $tracking_id = isset($settings['page_tracking']) ? absint($settings['page_tracking']) : 0;
                    $tracking_url = $tracking_id ? get_permalink($tracking_id) : site_url('/tracking-order/');
                    if ($tracking_url) {
                        $tracking_target = add_query_arg(['order' => $order_number], $tracking_url);
                        $qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . rawurlencode($tracking_target);
                    }
                    ?>
                    <?php if (!empty($tracking_url)) : ?>
                        <div class="wps-mt-4 wps-p-4" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; text-align:center;">
                            <div class="wps-mt-2">
                                <a href="<?php echo esc_url($tracking_target); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo esc_url($qr_src); ?>" alt="QR Tracking" style="width:160px;height:160px;">
                                </a>
                            </div>
                            <div class="wps-text-sm wps-text-gray-900 wps-font-medium">Scan untuk Lacak Pesanan</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="wps-text-center wps-mt-6">
            <a class="wps-btn wps-btn-primary" href="<?php echo esc_url($shop_url); ?>">
                <?php echo wps_icon(['name' => 'cart', 'size' => 18, 'class' => 'wps-mr-2']); ?>
                Kembali Belanja
            </a>
        </div>
    </div>
</div>