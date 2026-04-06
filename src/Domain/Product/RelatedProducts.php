<?php

namespace WpStore\Domain\Product;

class RelatedProducts
{
    public static function ids($product_id, $limit = 4)
    {
        $product_id = (int) $product_id;
        $limit = max(1, min(12, (int) $limit));

        if (!ProductMeta::is_product($product_id)) {
            return [];
        }

        $term_ids = wp_get_post_terms($product_id, 'store_product_cat', ['fields' => 'ids']);
        if (is_wp_error($term_ids) || empty($term_ids)) {
            return [];
        }

        $query = new \WP_Query([
            'post_type' => 'store_product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => [$product_id],
            'ignore_sticky_posts' => true,
            'tax_query' => [
                [
                    'taxonomy' => 'store_product_cat',
                    'field' => 'term_id',
                    'terms' => array_map('intval', (array) $term_ids),
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (!$query->have_posts()) {
            return [];
        }

        $ids = array_map('intval', wp_list_pluck((array) $query->posts, 'ID'));
        wp_reset_postdata();

        return array_values(array_filter($ids));
    }

    public static function items($product_id, $limit = 4)
    {
        $items = [];
        foreach (self::ids($product_id, $limit) as $related_id) {
            $item = ProductData::map_post($related_id);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
