<?php

namespace WpStore\Domain\Product;

use WpStore\Domain\Review\RatingRenderer;
use WpStore\Domain\Review\ProductReviewRepository;
use WpStore\Frontend\Template;

class ProductRenderer
{
    public static function thumbnail_size(): array
    {
        $settings = get_option('wp_store_settings', []);
        $width = isset($settings['product_thumbnail_width']) ? (int) $settings['product_thumbnail_width'] : 200;
        $height = isset($settings['product_thumbnail_height']) ? (int) $settings['product_thumbnail_height'] : 300;

        return [
            $width > 0 ? $width : 200,
            $height > 0 ? $height : 300,
        ];
    }

    public static function card_item(int $product_id, $image_size = 'medium'): ?array
    {
        $product = ProductData::map_post($product_id);
        if ($product === null) {
            return null;
        }

        $image = get_the_post_thumbnail_url($product_id, $image_size);
        if ($image) {
            $product['image'] = $image;
        }

        return [
            'id' => (int) $product['id'],
            'title' => (string) $product['title'],
            'link' => (string) $product['link'],
            'image' => (string) ($product['image'] ?? ''),
            'price' => $product['price'] ?? null,
            'regular_price' => $product['regular_price'] ?? null,
            'sale_price' => $product['sale_price'] ?? null,
            'stock' => $product['stock'] ?? null,
            'sold_count' => (int) ($product['sold_count'] ?? 0),
            'rating_average' => (float) ($product['rating_average'] ?? 0),
        ];
    }

    public static function render_card(int $product_id, array $args = []): string
    {
        if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
            return '';
        }

        $thumbnail_size = self::thumbnail_size();
        $args = wp_parse_args($args, [
            'context' => 'default',
            'variant' => 'default',
            'image_size' => $thumbnail_size,
            'thumbnail_width' => $thumbnail_size[0],
            'thumbnail_height' => $thumbnail_size[1],
            'thumbnail_crop' => 'true',
            'currency' => get_option('wp_store_settings', [])['currency_symbol'] ?? 'Rp',
            'template' => 'components/product-card',
        ]);

        $item = self::card_item($product_id, $args['image_size']);
        if ($item === null) {
            return '';
        }

        $data = [
            'item' => $item,
            'currency' => (string) $args['currency'],
            'context' => sanitize_key((string) $args['context']),
            'variant' => sanitize_key((string) $args['variant']),
            'card_class' => isset($args['card_class']) ? (string) $args['card_class'] : '',
            'extra_html' => isset($args['extra_html']) ? (string) $args['extra_html'] : '',
            'actions_html' => isset($args['actions_html']) ? (string) $args['actions_html'] : '',
            'thumbnail_width' => max(1, (int) $args['thumbnail_width']),
            'thumbnail_height' => max(1, (int) $args['thumbnail_height']),
            'thumbnail_crop' => $args['thumbnail_crop'],
        ];

        $html = Template::render((string) $args['template'], $data);
        return (string) apply_filters('wp_store_render_product_card', $html, $product_id, $data, $args);
    }

    public static function render_component(string $component, int $product_id, array $args = []): string
    {
        if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
            return '';
        }

        $component = sanitize_key($component);
        $custom = apply_filters('wp_store_render_product_component', null, $component, $product_id, $args);
        if (is_string($custom)) {
            return $custom;
        }

        switch ($component) {
            case 'gallery':
                return do_shortcode('[wp_store_gallery id="' . esc_attr((string) $product_id) . '"]');
            case 'title':
                return '<h1 class="fs-3 fw-bold wps-text-gray-900 wps-mb-2">' . esc_html(get_the_title($product_id)) . '</h1>';
            case 'breadcrumb':
                return Template::render('components/breadcrumb', ['post_id' => $product_id]);
            case 'price':
                return wps_product_price_html($product_id, $args);
            case 'rating':
                return self::rating_summary($product_id, $args);
            case 'info':
            case 'product_info':
            case 'meta':
                return self::product_info($product_id, $args);
            case 'after_summary':
                return self::after_summary($product_id);
            case 'actions':
            case 'buy':
            case 'buy_button':
                return self::actions($product_id);
            case 'share':
                return self::share_links($product_id);
            case 'description':
                $content = isset($args['content']) ? (string) $args['content'] : apply_filters('the_content', get_post_field('post_content', $product_id));
                return '<div class="wps-mb-4 product-description"><h2 class="wps-text-lg wps-font-bold wps-text-gray-900">Deskripsi Produk</h2><div class="wps-text-sm wps-text-gray-500">' . $content . '</div></div>';
            case 'reviews':
                $html = do_shortcode('[wp_store_product_reviews id="' . esc_attr((string) $product_id) . '" limit="' . esc_attr((string) ($args['limit'] ?? 20)) . '"]');
                return trim((string) $html) !== '' ? '<div class="wps-mt-8 product-reviews py-4">' . $html . '</div>' : '';
            case 'related':
                $html = do_shortcode('[wp_store_related id="' . esc_attr((string) $product_id) . '" per_page="' . esc_attr((string) ($args['per_page'] ?? 4)) . '"]');
                return trim((string) $html) !== '' ? '<div class="wps-mt-8 product-related"><h2 class="wps-text-lg wps-font-bold wps-text-gray-900 wps-mb-4">Produk Terkait</h2>' . $html . '</div>' : '';
        }

        $template_html = Template::render('components/product-' . $component, [
            'product_id' => $product_id,
            'args' => $args,
        ]);

        return $template_html;
    }

    public static function render_single(int $product_id, array $args = []): string
    {
        $product = ProductData::map_post($product_id);
        if ($product === null) {
            return '';
        }

        $sections = self::single_sections($product_id, $args);
        $content = isset($args['content']) ? (string) $args['content'] : apply_filters('the_content', get_post_field('post_content', $product_id));

        return Template::render('pages/single-flex', [
            'id' => $product_id,
            'product' => $product,
            'sections' => $sections,
            'content' => $content,
            'currency' => get_option('wp_store_settings', [])['currency_symbol'] ?? 'Rp',
        ]);
    }

    public static function single_sections(int $product_id, array $args = []): array
    {
        $sections = [
            'gallery' => true,
            'title' => true,
            'breadcrumb' => true,
            'price' => true,
            'rating' => true,
            'info' => true,
            'after_summary' => true,
            'actions' => true,
            'share' => true,
            'description' => true,
            'reviews' => true,
            'related' => true,
        ];

        $sections = apply_filters('wp_store_single_product_sections', $sections, $product_id, $args);
        if (!is_array($sections)) {
            $sections = [];
        }

        $hide = self::key_list($args['hide'] ?? '');
        foreach ($hide as $key) {
            unset($sections[$key]);
        }

        return array_filter($sections);
    }

    private static function rating_summary(int $product_id, array $args = []): string
    {
        $summary = (new ProductReviewRepository())->product_summary($product_id);
        $review_count = (int) ($summary['review_count'] ?? 0);
        $sold_count = (int) ProductMeta::get($product_id, 'sold_count', 0);
        $rating_average = (float) ($summary['rating_average'] ?? 0);

        if ($review_count <= 0 && $sold_count <= 0) {
            return '';
        }

        $html = '<div class="wps-flex wps-flex-wrap wps-gap-2 wps-items-center wps-mb-4">';
        if ($review_count > 0) {
            $html .= '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;background:#f8fafc;border:1px solid #e5e7eb;color:#475569;font-size:12px;line-height:1;">';
            $html .= RatingRenderer::summary_html($rating_average, $review_count, ['show_count' => false, 'class' => 'wps-gap-1']);
            $html .= '<span>' . esc_html(sprintf(_n('%d ulasan', '%d ulasan', $review_count, 'wp-store'), $review_count)) . '</span></span>';
        }
        if ($sold_count > 0) {
            $html .= '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;background:#f8fafc;border:1px solid #e5e7eb;color:#475569;font-size:12px;line-height:1;">';
            $html .= wps_icon(['name' => 'cart', 'size' => 14, 'stroke_color' => '#2563eb']);
            $html .= '<strong style="color:#111827;font-weight:600;">' . esc_html((string) $sold_count) . '</strong><span>' . esc_html(_n('terjual', 'terjual', $sold_count, 'wp-store')) . '</span></span>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function product_info(int $product_id, array $args = []): string
    {
        $stock = ProductMeta::get($product_id, 'stock', null);
        $rows = [
            'Kode Produk' => ProductMeta::get($product_id, 'sku', ''),
            'Berat' => ProductMeta::get($product_id, 'weight', '') !== '' ? number_format_i18n((float) ProductMeta::get($product_id, 'weight', ''), 2) . ' kg' : '',
            'Minimal Order' => ProductMeta::get($product_id, 'min_order', '') !== '' ? (string) (int) ProductMeta::get($product_id, 'min_order', '') : '',
            'Stok' => $stock !== null && $stock !== '' ? (string) (int) $stock : '',
            'Tipe' => ProductMeta::get($product_id, 'product_type', '') === 'digital' ? 'Produk Digital' : (ProductMeta::get($product_id, 'product_type', '') !== '' ? 'Produk Fisik' : ''),
        ];

        $terms = get_the_terms($product_id, 'store_product_cat');
        if (is_array($terms)) {
            $rows['Kategori'] = implode(', ', wp_list_pluck($terms, 'name'));
        }

        $rows = array_filter($rows, static function ($value) {
            return $value !== '' && $value !== null;
        });

        if (empty($rows)) {
            return '';
        }

        $html = '<div class="wps-mb-4"><table style="width: 100%; border-collapse: collapse;"><tbody>';
        foreach ($rows as $label => $value) {
            $html .= '<tr style="border-top: 1px solid #e5e7eb;"><td style="padding: 8px; color:#6b7280; font-size:12px;">' . esc_html($label) . '</td>';
            $html .= '<td style="padding: 8px; text-align: right;" class="wps-text-sm wps-text-gray-900">' . esc_html((string) $value) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    private static function after_summary(int $product_id): string
    {
        $product = ProductData::map_post($product_id);
        if ($product === null) {
            return '';
        }

        ob_start();
        do_action('wp_store_single_after_summary', $product_id, [
            'id' => $product_id,
            'title' => (string) $product['title'],
            'price' => $product['price'] ?? null,
            'stock' => $product['stock'] ?? null,
            'image' => (!empty($product['image']) ? $product['image'] : (WP_STORE_URL . 'assets/frontend/img/noimg.webp')),
            'currency' => get_option('wp_store_settings', [])['currency_symbol'] ?? 'Rp',
        ]);
        $html = trim((string) ob_get_clean());

        return $html !== '' ? '<div class="wps-mb-4">' . $html . '</div>' : '';
    }

    private static function actions(int $product_id): string
    {
        return '<div class="wps-flex wps-gap-2 wps-items-center wps-mb-2"><div>'
            . do_shortcode('[wp_store_add_to_cart id="' . esc_attr((string) $product_id) . '"]')
            . '</div><div>'
            . do_shortcode('[wp_store_add_to_wishlist id="' . esc_attr((string) $product_id) . '"]')
            . '</div></div>';
    }

    private static function share_links(int $product_id): string
    {
        $share_link = get_permalink($product_id);
        $share_title = get_the_title($product_id);
        $enc_url = rawurlencode($share_link);
        $enc_title = rawurlencode($share_title);
        $links = [
            'whatsapp' => 'https://api.whatsapp.com/send?text=' . rawurlencode($share_title . ' ' . $share_link),
            'x-logo' => 'https://twitter.com/intent/tweet?text=' . $enc_title . '&url=' . $enc_url,
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $enc_url,
            'email' => 'mailto:?subject=' . rawurlencode($share_title) . '&body=' . rawurlencode($share_link),
        ];

        $html = '<div class="wps-mb-4"><div class="wps-flex wps-gap-2 wps-items-center">';
        foreach ($links as $icon => $url) {
            $target = $icon === 'email' ? '' : ' target="_blank" rel="noopener"';
            $html .= '<a href="' . esc_url($url) . '"' . $target . ' class="wps-btn wps-btn-secondary wps-btn-sm">' . wps_icon(['name' => $icon, 'size' => 18]) . '</a>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private static function key_list($value): array
    {
        $raw = is_array($value) ? $value : explode(',', (string) $value);
        $keys = [];
        foreach ($raw as $item) {
            $key = sanitize_key(trim((string) $item));
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
