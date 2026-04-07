<?php

namespace WpStore\Domain\Product;

class ProductMeta
{
    public static function is_product($product_id)
    {
        return $product_id > 0 && get_post_type($product_id) === 'store_product';
    }

    public static function get($product_id, $key, $default = '')
    {
        if (!self::is_product((int) $product_id)) {
            return $default;
        }

        $meta_key = self::meta_key($key);
        if ($meta_key === '') {
            return $default;
        }

        $value = get_post_meta((int) $product_id, $meta_key, true);
        return $value === '' ? $default : $value;
    }

    public static function get_number($product_id, $key, $default = 0)
    {
        $value = self::get($product_id, $key, null);
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function get_list($product_id, $key)
    {
        $value = self::get($product_id, $key, []);
        return is_array($value) ? array_values($value) : [];
    }

    public static function gallery_ids($product_id)
    {
        $value = self::get($product_id, 'gallery_ids', []);
        if (is_array($value)) {
            return array_values(array_filter(array_map('absint', $value)));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('absint', explode(',', $value))));
        }

        return [];
    }

    public static function label($product_id)
    {
        return '';
    }

    public static function canonical_label($label)
    {
        return '';
    }

    public static function canonical_key($key)
    {
        return self::meta_key($key);
    }

    public static function meta_key($key)
    {
        $map = [
            'product_type' => '_store_product_type',
            'price' => '_store_price',
            'sale_price' => '_store_sale_price',
            'sale_until' => '_store_flashsale_until',
            'digital_file' => '_store_digital_file',
            'sku' => '_store_sku',
            'stock' => '_store_stock',
            'min_order' => '_store_min_order',
            'weight' => '_store_weight_kg',
            'sold_count' => '_store_sold_count',
            'review_count' => '_store_review_count',
            'rating_average' => '_store_rating_average',
            'gallery_ids' => '_store_gallery_ids',
            'variant_name' => '_store_option_name',
            'variant_options' => '_store_options',
            'price_adjustment_name' => '_store_option2_name',
            'price_adjustment_options' => '_store_advanced_options',
        ];

        return $map[$key] ?? '';
    }
}
