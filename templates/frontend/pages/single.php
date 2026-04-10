<div class="wps-p-4 single-product">
    <div class="wps-flex wps-gap-4 wps-items-start wps-mb-4 product-detail">
        <div class="wps-w-full" style="flex: 1;">
            <?php $image_src = (!empty($image) ? $image : (WP_STORE_URL . 'assets/frontend/img/noimg.webp')); ?>
            <?php
            $gallery_raw = get_post_meta((int) $id, '_store_gallery_ids', true);
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
            <?php echo \WpStore\Frontend\Template::render('components/product-gallery', [
                'id' => (int) $id,
                'title' => (string) $title,
                'image_src' => (string) $image_src,
                'items' => $items,
            ]); ?>
        </div>
        <div style="flex: 1;">
            <h1 class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-2"><?php echo esc_html($title); ?></h1>
            <?php echo \WpStore\Frontend\Template::render('components/breadcrumb', ['post_id' => $id]); ?>
            <?php echo wps_product_price_html((int) $id, ['wrapper_class' => 'wps-mb-4']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php
            $single_sold_count = (int) get_post_meta((int) $id, '_store_sold_count', true);
            $single_review_count = (int) get_post_meta((int) $id, '_store_review_count', true);
            $single_rating_average = (float) get_post_meta((int) $id, '_store_rating_average', true);
            ?>
            <?php if ($single_sold_count > 0 || $single_review_count > 0) : ?>
                <div class="wps-flex wps-flex-wrap wps-gap-2 wps-items-center wps-mb-4">
                    <?php if ($single_review_count > 0) : ?>
                        <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;background:#f8fafc;border:1px solid #e5e7eb;color:#475569;font-size:12px;line-height:1;">
                            <?php echo \WpStore\Domain\Review\RatingRenderer::summary_html($single_rating_average, $single_review_count, ['show_count' => false, 'class' => 'wps-gap-1']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <span><?php echo esc_html(sprintf(_n('%d ulasan', '%d ulasan', $single_review_count, 'wp-store'), $single_review_count)); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($single_sold_count > 0) : ?>
                        <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;background:#f8fafc;border:1px solid #e5e7eb;color:#475569;font-size:12px;line-height:1;">
                            <?php echo wps_icon(['name' => 'cart', 'size' => 14, 'stroke_color' => '#2563eb']); ?>
                            <strong style="color:#111827;font-weight:600;"><?php echo esc_html((string) $single_sold_count); ?></strong>
                            <span><?php echo esc_html(_n('terjual', 'terjual', $single_sold_count, 'wp-store')); ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="wps-mb-4">
                <?php
                $sku = get_post_meta($id, '_store_sku', true);
                $min_order = get_post_meta($id, '_store_min_order', true);
                $weight_kg = get_post_meta($id, '_store_weight_kg', true);
                $ptype = get_post_meta($id, '_store_product_type', true);
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
                        <?php if (!empty($cats)) : ?>
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 8px; color:#6b7280; font-size:12px;">Kategori</td>
                                <td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900"><?php echo esc_html(implode(', ', $cats)); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
            ob_start();
            do_action('wp_store_single_after_summary', (int) $id, [
                'id' => (int) $id,
                'title' => (string) $title,
                'price' => $price,
                'stock' => $stock,
                'image' => $image_src,
                'currency' => $currency ?? 'Rp',
            ]);
            $single_after_summary = trim((string) ob_get_clean());
            if ($single_after_summary !== '') :
            ?>
                <div class="wps-mb-4">
                    <?php echo $single_after_summary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
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
                    <a href="<?php echo esc_url($x_url); ?>" target="_blank" rel="noopener" class="wps-btn wps-btn-secondary wps-btn-sm"><?php echo wps_icon(['name' => 'x-logo', 'size' => 18]); ?></a>
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
    <?php
    $product_reviews_html = do_shortcode('[wp_store_product_reviews id="' . esc_attr((string) $id) . '" limit="20"]');
    if (trim((string) $product_reviews_html) !== '') :
    ?>
        <div class="wps-mt-8 product-reviews">
            <?php echo $product_reviews_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>
    <?php
    $related_html = do_shortcode('[wp_store_related id="' . esc_attr((string) $id) . '" per_page="4"]');
    if (trim((string) $related_html) !== '') :
    ?>
        <div class="wps-mt-8 product-related">
            <h2 class="wps-text-lg wps-font-bold wps-text-gray-900 wps-mb-4">Produk Terkait</h2>
            <?php echo $related_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>
</div>

