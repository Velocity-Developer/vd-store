<?php

namespace WpStore\Domain\Product;

use WP_Query;

class ProductQuery
{
    public static function label_options()
    {
        return [];
    }

    public static function sort_options()
    {
        return [
            'latest' => __('Terbaru', 'wp-store'),
            'sold_desc' => __('Terlaris', 'wp-store'),
            'rating_desc' => __('Rating Tertinggi', 'wp-store'),
            'price_asc' => __('Harga Terendah', 'wp-store'),
            'price_desc' => __('Harga Tertinggi', 'wp-store'),
            'name_asc' => __('Nama A-Z', 'wp-store'),
            'name_desc' => __('Nama Z-A', 'wp-store'),
        ];
    }

    public static function normalize_filters($source = [])
    {
        if (!is_array($source)) {
            $source = [];
        }

        return [
            'search' => sanitize_text_field((string) ($source['search'] ?? $source['s'] ?? '')),
            'sort' => sanitize_key((string) ($source['sort'] ?? 'latest')),
            'cat' => (int) ($source['product_cat'] ?? $source['cat'] ?? 0),
            'author' => (int) ($source['author'] ?? 0),
            'label' => sanitize_key((string) ($source['product_label'] ?? $source['label'] ?? '')),
            'min_price' => self::normalize_numeric_filter($source['min_price'] ?? ''),
            'max_price' => self::normalize_numeric_filter($source['max_price'] ?? ''),
        ];
    }

    public static function query($args = [])
    {
        $defaults = [
            'post_type' => 'store_product',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'paged' => 1,
        ];

        $args = wp_parse_args($args, $defaults);
        return new WP_Query($args);
    }

    public static function build_query_args($filters = [], $overrides = [])
    {
        $filters = self::normalize_filters(is_array($filters) ? $filters : []);
        $overrides = is_array($overrides) ? $overrides : [];

        $args = [
            'post_type' => isset($overrides['post_type']) ? $overrides['post_type'] : 'store_product',
            'post_status' => isset($overrides['post_status']) ? $overrides['post_status'] : 'publish',
            'posts_per_page' => !empty($overrides['posts_per_page']) ? max(1, (int) $overrides['posts_per_page']) : 12,
            'paged' => !empty($overrides['paged']) ? max(1, (int) $overrides['paged']) : 1,
        ];

        if ($filters['search'] !== '') {
            $args['s'] = $filters['search'];
        }

        if ($filters['cat'] > 0) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'store_product_cat',
                    'field' => 'term_id',
                    'terms' => [$filters['cat']],
                ],
            ];
        }

        if ($filters['author'] > 0) {
            $args['author'] = $filters['author'];
        }

        $meta_query = [];

        if ($filters['min_price'] !== '') {
            $meta_query[] = [
                'key' => ProductMeta::canonical_key('price'),
                'value' => (float) $filters['min_price'],
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        if ($filters['max_price'] !== '') {
            $meta_query[] = [
                'key' => ProductMeta::canonical_key('price'),
                'value' => (float) $filters['max_price'],
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
        }

        if ($filters['sort'] === 'sold_desc') {
            $args['meta_key'] = ProductMeta::canonical_key('sold_count');
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($filters['sort'] === 'rating_desc') {
            $args['meta_key'] = ProductMeta::canonical_key('rating_average');
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($filters['sort'] === 'price_asc') {
            $args['meta_key'] = ProductMeta::canonical_key('price');
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'ASC';
        } elseif ($filters['sort'] === 'price_desc') {
            $args['meta_key'] = ProductMeta::canonical_key('price');
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($filters['sort'] === 'name_asc') {
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
        } elseif ($filters['sort'] === 'name_desc') {
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
        } else {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }

        return array_merge($args, $overrides);
    }

    public static function apply_to_query(\WP_Query $query, $filters = [], $overrides = [])
    {
        $args = self::build_query_args($filters, $overrides);

        foreach ($args as $key => $value) {
            $query->set($key, $value);
        }
    }

    public static function from_request($request_args = [])
    {
        $filters = self::normalize_filters($request_args);
        $per_page = isset($request_args['per_page']) ? (int) $request_args['per_page'] : 12;
        $paged = isset($request_args['page']) ? (int) $request_args['page'] : 1;

        if ($per_page <= 0 || $per_page > 50) {
            $per_page = 12;
        }

        if ($paged <= 0) {
            $paged = 1;
        }

        if ($filters['cat'] <= 0 && !empty($request_args['category'])) {
            $term = get_term_by('slug', sanitize_title((string) $request_args['category']), 'store_product_cat');
            if ($term && !is_wp_error($term)) {
                $filters['cat'] = (int) $term->term_id;
            }
        }

        return self::build_query_args($filters, [
            'post_type' => 'store_product',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => 'publish',
        ]);
    }

    private static function normalize_numeric_filter($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (float) $value;
    }
}
