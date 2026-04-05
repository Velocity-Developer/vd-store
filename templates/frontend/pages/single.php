<div class="wps-p-4 single-product">
    <div class="wps-flex wps-gap-4 wps-items-start wps-mb-4 product-detail">
        <div class="wps-w-full" style="flex: 1;">
            <?php $image_src = (!empty($image) ? $image : (WP_STORE_URL . 'assets/frontend/img/noimg.webp')); ?>
            <?php
            $gallery_raw = get_post_meta((int) $id, '_store_gallery_ids', true);
            $settings_theme = get_option('wp_store_settings', []);
            $primary_color = isset($settings_theme['theme_primary']) ? sanitize_hex_color($settings_theme['theme_primary']) : '#2563eb';
            $items = [];
            $featured_thumb = get_the_post_thumbnail_url((int) $id, 'thumbnail');
            $featured_thumb = $featured_thumb ? $featured_thumb : $image_src;
            $items[] = [
                'full' => $image_src,
                'thumb' => $featured_thumb
            ];
            if (is_array($gallery_raw) && !empty($gallery_raw)) {
                foreach ($gallery_raw as $k => $v) {
                    $aid = is_numeric($k) ? (int) $k : 0;
                    $full = $aid ? (wp_get_attachment_image_url($aid, 'large') ?: (is_string($v) ? $v : '')) : (is_string($v) ? $v : '');
                    $thumb = $aid ? (wp_get_attachment_image_url($aid, 'thumbnail') ?: $full) : $full;
                    if ($full) {
                        $items[] = ['full' => $full, 'thumb' => $thumb];
                    }
                }
            }
            ?>
            <?php if (count($items) > 1) : ?>
                <div class="wps-position-relative wps-w-full wps-products-carousel" data-wps-carousel data-cell-align="center" data-contain="true" data-wrap-around="true" data-page-dots="true" data-prev-next-buttons="true" data-draggable="true">
                    <div class="main-carousel carousel-main" id="wps-main-carousel-<?php echo esc_attr($id); ?>">
                        <?php foreach ($items as $idx => $gi) : ?>
                            <div class="carousel-cell wps-mx-0">
                                <img class="wps-w-full wps-rounded wps-img-320" src="<?php echo esc_url($gi['full']); ?>" alt="<?php echo esc_attr($title); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    $ptype_single = get_post_meta((int) $id, '_store_product_type', true);
                    $is_digital_single = ($ptype_single === 'digital') || (bool) get_post_meta((int) $id, '_store_is_digital', true);
                    if ($is_digital_single) {
                        echo '<span class="wps-digital-badge wps-text-xs wps-text-white">'
                            . wps_icon(['name' => 'cloud-download', 'size' => 12, 'stroke_color' => '#ffffff'])
                            . '<span class="txt wps-text-white wps-text-xs">Digital</span>'
                            . '</span>';
                    }
                    $lbl_single = get_post_meta((int) $id, '_store_label', true);
                    if (is_string($lbl_single) && $lbl_single !== '') {
                        $txt = $lbl_single === 'label-best' ? 'Best Seller' : ($lbl_single === 'label-limited' ? 'Limited' : ($lbl_single === 'label-new' ? 'New' : ''));
                        if ($txt !== '') {
                            echo '<span class="wps-label-badge ' . esc_attr($lbl_single) . ' wps-text-xs">'
                                . wps_icon(['name' => 'heart', 'size' => 10, 'stroke_color' => '#ffffff'])
                                . '<span class="txt wps-text-white wps-text-xs">' . esc_html($txt) . '</span>'
                                . '</span>';
                        }
                    }
                    ?>
                </div>
                <div class="wps-mt-2 wps-products-carousel" data-wps-carousel data-as-nav-for="#wps-main-carousel-<?php echo esc_attr($id); ?>" data-cell-align="left" data-contain="true" data-wrap-around="false" data-page-dots="false" data-prev-next-buttons="false" data-draggable="true">
                    <div class="main-carousel carousel-nav">
                        <?php foreach ($items as $idx => $gi) : ?>
                            <div class="carousel-cell wps-mr-2" style="width:64px;">
                                <img class="wps-img-60 wps-rounded" src="<?php echo esc_url($gi['thumb']); ?>" alt="">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <div style="position:relative;display:block;">
                    <img class="wps-w-full wps-rounded wps-img-320" src="<?php echo esc_url($image_src); ?>" alt="<?php echo esc_attr($title); ?>">
                    <?php
                    $ptype_single = get_post_meta((int) $id, '_store_product_type', true);
                    $is_digital_single = ($ptype_single === 'digital') || (bool) get_post_meta((int) $id, '_store_is_digital', true);
                    if ($is_digital_single) {
                        echo '<span class="wps-text-xs wps-text-white" style="position:absolute;top:8px;left:8px;display:flex;align-items:center;background:#111827cc;color:#fff;border-radius:9999px;padding:2px 6px;backdrop-filter:saturate(180%) blur(4px);">'
                            . wps_icon(['name' => 'cloud-download', 'size' => 12, 'stroke_color' => '#ffffff'])
                            . '<span style="color:#fff;font-size:10px;margin-left:4px;">Digital</span>'
                            . '</span>';
                    }
                    $lbl_single = get_post_meta((int) $id, '_store_label', true);
                    if (is_string($lbl_single) && $lbl_single !== '') {
                        $txt = $lbl_single === 'label-best' ? 'Best Seller' : ($lbl_single === 'label-limited' ? 'Limited' : ($lbl_single === 'label-new' ? 'New' : ''));
                        $bg  = $lbl_single === 'label-best' ? '#f59e0b' : ($lbl_single === 'label-limited' ? '#ef4444' : ($lbl_single === 'label-new' ? '#10b981' : '#374151'));
                        if ($txt !== '') {
                            echo '<span class="wps-text-xs" style="position:absolute;top:8px;right:8px;display:inline-flex;align-items:center;background:' . esc_attr($bg) . ';color:#fff;border-radius:9999px;padding:2px 6px;">'
                                . wps_icon(['name' => 'heart', 'size' => 10, 'stroke_color' => '#ffffff'])
                                . '<span style="color:#fff;font-size:10px;margin-left:4px;">' . esc_html($txt) . '</span>'
                                . '</span>';
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <div style="flex: 1;">
            <h1 class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-2"><?php echo esc_html($title); ?></h1>
            <?php echo \WpStore\Frontend\Template::render('components/breadcrumb', ['post_id' => $id]); ?>
            <div class="wps-text-sm wps-text-gray-900 wps-mb-4">
                <?php if ($price !== null) : ?>
                    <?php echo esc_html(($currency ?? 'Rp') . ' ' . number_format_i18n((float) $price, 0)); ?>
                <?php endif; ?>
            </div>
            <div class="wps-mb-4">
                <?php
                $sku = get_post_meta($id, '_store_sku', true);
                $min_order = get_post_meta($id, '_store_min_order', true);
                $weight_kg = get_post_meta($id, '_store_weight_kg', true);
                $ptype = get_post_meta($id, '_store_product_type', true);
                $sale_price = get_post_meta($id, '_store_sale_price', true);
                $terms = get_the_terms($id, 'store_product_cat');
                $cats = [];
                if (is_array($terms)) {
                    foreach ($terms as $t) {
                        $cats[] = $t->name;
                    }
                }
                ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <tbody>
                        <?php if (is_string($sku) && $sku !== '') : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Kode Produk</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900"><?php echo esc_html($sku); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($weight_kg !== '' && $weight_kg !== null) : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Berat</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900"><?php echo esc_html(number_format_i18n((float) $weight_kg, 2)); ?> kg</td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($min_order !== '' && $min_order !== null) : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Minimal Order</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900"><?php echo esc_html((int) $min_order); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($stock !== null) : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Stok</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900"><?php echo esc_html((int) $stock); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (is_string($ptype) && $ptype !== '') : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Tipe</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900">
                                    <?php echo esc_html($ptype === 'digital' ? 'Produk Digital' : 'Produk Fisik'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php
                        $wps_settings_tmp = get_option('wp_store_settings', []);
                        $wps_hide_sale_for_guest = !empty($wps_settings_tmp['members_only_discount']) && !is_user_logged_in();
                        ?>
                        <?php if ($sale_price !== '' && $sale_price !== null && !$wps_hide_sale_for_guest) : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Harga Promo</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900">
                                    <?php echo esc_html(($currency ?? 'Rp') . ' ' . number_format_i18n((float) $sale_price, 0)); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($cats)) : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Kategori</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900"><?php echo esc_html(implode(', ', $cats)); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="wps-flex wps-gap-2 wps-items-center wps-mb-2">
                <div><?php echo do_shortcode('[wp_store_add_to_cart id="' . esc_attr($id) . '"]'); ?></div>
                <div><?php echo do_shortcode('[wp_store_add_to_wishlist id="' . esc_attr($id) . '"]'); ?></div>
            </div>
            <div class="wps-mb-4">
                <?php
                $share_link = get_permalink($id);
                $share_title = $title;
                $enc_url = rawurlencode($share_link);
                $enc_title = rawurlencode($share_title);
                $wa_url = 'https://api.whatsapp.com/send?text=' . rawurlencode($share_title . ' ' . $share_link);
                $x_url = 'https://twitter.com/intent/tweet?text=' . $enc_title . '&url=' . $enc_url;
                $fb_url = 'https://www.facebook.com/sharer/sharer.php?u=' . $enc_url;
                $mail_url = 'mailto:?subject=' . rawurlencode($share_title) . '&body=' . rawurlencode($share_link);
                ?>
                <div class="wps-flex wps-gap-2 wps-items-center">
                    <a href="<?php echo esc_url($wa_url); ?>" target="_blank" rel="noopener" class="wps-btn wps-btn-secondary wps-btn-sm"><?php echo wps_icon(['name' => 'whatsapp', 'size' => 18]); ?></a>
                    <a href="<?php echo esc_url($x_url); ?>" target="_blank" rel="noopener" class="wps-btn wps-btn-secondary wps-btn-sm"><?php echo wps_icon(['name' => 'x', 'size' => 18]); ?></a>
                    <a href="<?php echo esc_url($fb_url); ?>" target="_blank" rel="noopener" class="wps-btn wps-btn-secondary wps-btn-sm"><?php echo wps_icon(['name' => 'facebook', 'size' => 18]); ?></a>
                    <a href="<?php echo esc_url($mail_url); ?>" class="wps-btn wps-btn-secondary wps-btn-sm"><?php echo wps_icon(['name' => 'email', 'size' => 18]); ?></a>
                </div>
            </div>
        </div>
    </div>
    <div class="wps-mb-4 product-description">
        <h2 class="wps-text-lg wps-font-bold wps-text-gray-900">Deskripsi Produk</h2>
        <div class="wps-text-sm wps-text-gray-500">
            <?php echo $content; ?>
        </div>
    </div>
</div>