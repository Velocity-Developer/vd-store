<?php

namespace WpStore\Domain\Product;

class ProductData
{
    public static function map_post($post_id)
    {
        $post_id = (int) $post_id;
        if (!ProductMeta::is_product($post_id)) {
            return null;
        }

        $regular_price = self::resolve_regular_price($post_id);
        $sale_price = self::resolve_sale_price($post_id);
        $effective_price = self::resolve_effective_price($post_id);
        $stock = ProductMeta::get($post_id, 'stock', '');
        $image = get_the_post_thumbnail_url($post_id, 'medium');

        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'slug' => get_post_field('post_name', $post_id),
            'excerpt' => wp_trim_words((string) get_post_field('post_content', $post_id), 20),
            'price' => $effective_price,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'stock' => $stock !== '' ? (int) $stock : null,
            'image' => $image ? $image : null,
            'link' => get_permalink($post_id),
            'label' => '',
            'sku' => (string) ProductMeta::get($post_id, 'sku', ''),
            'min_order' => (int) ProductMeta::get_number($post_id, 'min_order', 1),
            'weight_kg' => (float) ProductMeta::get_number($post_id, 'weight', 0),
            'sold_count' => max(0, (int) ProductMeta::get_number($post_id, 'sold_count', 0)),
            'review_count' => max(0, (int) ProductMeta::get_number($post_id, 'review_count', 0)),
            'rating_average' => max(0.0, (float) ProductMeta::get_number($post_id, 'rating_average', 0)),
            'gallery_ids' => ProductMeta::gallery_ids($post_id),
            'variant_name' => (string) ProductMeta::get($post_id, 'variant_name', ''),
            'variant_options' => ProductMeta::get_list($post_id, 'variant_options'),
            'price_adjustment_name' => (string) ProductMeta::get($post_id, 'price_adjustment_name', ''),
            'price_adjustment_options' => ProductMeta::get_list($post_id, 'price_adjustment_options'),
        ];
    }

    public static function resolve_regular_price($product_id)
    {
        $product_id = (int) $product_id;
        if (!ProductMeta::is_product($product_id)) {
            return null;
        }

        $price = ProductMeta::get($product_id, 'price', '');
        return ($price !== '' && is_numeric($price)) ? (float) $price : null;
    }

    public static function resolve_sale_price($product_id)
    {
        $product_id = (int) $product_id;
        if (!ProductMeta::is_product($product_id)) {
            return null;
        }

        $sale = ProductMeta::get($product_id, 'sale_price', '');
        $sale = ($sale !== '' && is_numeric($sale)) ? (float) $sale : null;
        if ($sale === null || $sale <= 0) {
            return null;
        }

        $price = ProductMeta::get($product_id, 'price', '');
        $price = ($price !== '' && is_numeric($price)) ? (float) $price : null;

        $until_raw = (string) ProductMeta::get($product_id, 'sale_until', '');
        $until_ts = $until_raw ? strtotime($until_raw) : 0;
        if ($until_ts > 0 && $until_ts <= current_time('timestamp')) {
            return null;
        }

        if ($price !== null && $price > 0 && $sale >= $price) {
            return null;
        }

        return $sale;
    }

    public static function is_digital($product_id)
    {
        return (string) ProductMeta::get($product_id, 'product_type', 'physical') === 'digital';
    }

    public static function resolve_effective_price($product_id)
    {
        $regular = self::resolve_regular_price((int) $product_id);
        $sale = self::resolve_sale_price((int) $product_id);

        if ($sale !== null && $sale > 0 && ($regular === null || $regular <= 0 || $sale < $regular)) {
            return $sale;
        }

        return $regular;
    }

    public static function weight_grams($product_id, $minimum = 1)
    {
        $weight_kg = ProductMeta::get_number($product_id, 'weight', 0);
        $grams = (int) round($weight_kg * 1000);
        return max((int) $minimum, $grams);
    }

    public static function resolve_price_with_options($product_id, $options = [])
    {
        $product = self::map_post((int) $product_id);
        if ($product === null) {
            return 0.0;
        }

        $base = (float) (self::resolve_effective_price((int) $product_id) ?? 0);
        $adjustment_name = (string) ($product['price_adjustment_name'] ?? '');
        $adjustments = isset($product['price_adjustment_options']) && is_array($product['price_adjustment_options'])
            ? $product['price_adjustment_options']
            : [];

        if ($adjustment_name !== '' && isset($options[$adjustment_name])) {
            $selected = (string) $options[$adjustment_name];
            foreach ($adjustments as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $label = isset($row['label']) ? (string) $row['label'] : '';
                if ($label === '') {
                    continue;
                }

                $amount = 0.0;
                if (isset($row['amount']) && is_numeric($row['amount'])) {
                    $amount = (float) $row['amount'];
                } elseif (isset($row['price']) && is_numeric($row['price'])) {
                    $amount = (float) $row['price'];
                }

                if ($label === $selected && $amount > 0) {
                    return $amount;
                }
            }
        }

        return $base;
    }

    public static function increment_sold_count($product_id, $qty = 1)
    {
        $product_id = (int) $product_id;
        $qty = (int) $qty;

        if (!ProductMeta::is_product($product_id) || $qty <= 0) {
            return;
        }

        $current = (int) ProductMeta::get_number($product_id, 'sold_count', 0);
        update_post_meta($product_id, ProductMeta::meta_key('sold_count'), max(0, $current + $qty));
    }
}
