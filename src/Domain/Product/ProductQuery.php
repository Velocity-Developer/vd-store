<?php

namespace WpStore\Domain\Product;

use WP_Query;

class ProductQuery
{
    public static function label_options()
    {
        $options = function_exists('wps_product_label_options') ? \wps_product_label_options() : [];
        return is_array($options) ? $options : [];
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

        $sort = sanitize_key((string) ($source['sort'] ?? 'latest'));
        $sort_aliases = [
            '' => 'latest',
            'az' => 'name_asc',
            'za' => 'name_desc',
            'cheap' => 'price_asc',
            'expensive' => 'price_desc',
        ];
        $sort = $sort_aliases[$sort] ?? $sort;
        if (!array_key_exists($sort, self::sort_options())) {
            $sort = 'latest';
        }

        $cats = [];
        $raw_cats = $source['cats'] ?? $source['product_cats'] ?? [];
        $raw_cats = is_array($raw_cats) ? $raw_cats : [$raw_cats];
        foreach ($raw_cats as $candidate) {
            $id = absint($candidate);
            if ($id > 0) {
                $cats[] = $id;
            }
        }

        $labels = [];
        $raw_labels = $source['labels'] ?? $source['product_labels'] ?? [];
        $raw_labels = is_array($raw_labels) ? $raw_labels : [$raw_labels];
        foreach ($raw_labels as $candidate) {
            $label = ProductMeta::canonical_label((string) $candidate);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        $brands = [];
        $raw_brands = $source['brands'] ?? $source['product_brands'] ?? [];
        $raw_brands = is_array($raw_brands) ? $raw_brands : [$raw_brands];
        foreach ($raw_brands as $candidate) {
            $id = absint($candidate);
            if ($id > 0) {
                $brands[] = $id;
            }
        }

        return [
            'search' => sanitize_text_field((string) ($source['search'] ?? $source['s'] ?? $source['serach'] ?? '')),
            'sort' => $sort,
            'cat' => (int) ($source['product_cat'] ?? $source['cat'] ?? 0),
            'cats' => array_values(array_unique($cats)),
            'author' => (int) ($source['author'] ?? 0),
            'label' => ProductMeta::canonical_label((string) ($source['product_label'] ?? $source['label'] ?? '')),
            'labels' => array_values(array_unique($labels)),
            'brand' => (int) ($source['product_brand'] ?? $source['brand'] ?? 0),
            'brands' => array_values(array_unique($brands)),
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
        $raw_filters = is_array($filters) ? $filters : [];
        $requested_page = isset($raw_filters['page']) ? (int) $raw_filters['page'] : 0;
        if ($requested_page <= 0 && isset($raw_filters['paged'])) {
            $requested_page = (int) $raw_filters['paged'];
        }
        if ($requested_page <= 0) {
            $requested_page = 1;
        }

        $filters = self::normalize_filters($raw_filters);
        $overrides = is_array($overrides) ? $overrides : [];

        $args = wp_parse_args($overrides, [
            'post_type' => 'store_product',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'paged' => max(1, $requested_page),
        ]);

        if (!empty($args['posts_per_page'])) {
            $args['posts_per_page'] = (int) $args['posts_per_page'];
        }
        if (!empty($args['paged'])) {
            $args['paged'] = max(1, (int) $args['paged']);
        }

        if ($filters['search'] !== '') {
            $args['s'] = $filters['search'];
        }

        $cats = [];
        if (!empty($filters['cats']) && is_array($filters['cats'])) {
            $cats = array_values(array_filter(array_map('absint', $filters['cats'])));
        }
        if (empty($cats) && $filters['cat'] > 0) {
            $cats = [(int) $filters['cat']];
        }

        if (!empty($cats)) {
            $args = self::append_tax_query($args, [
                [
                    'taxonomy' => 'store_product_cat',
                    'field' => 'term_id',
                    'terms' => $cats,
                ],
            ]);
        }

        $brands = [];
        if (!empty($filters['brands']) && is_array($filters['brands'])) {
            $brands = array_values(array_filter(array_map('absint', $filters['brands'])));
        }
        if (empty($brands) && !empty($filters['brand'])) {
            $brands = [(int) $filters['brand']];
        }

        if (!empty($brands)) {
            $args = self::append_tax_query($args, [
                [
                    'taxonomy' => 'brand',
                    'field' => 'term_id',
                    'terms' => $brands,
                ],
            ]);
        }

        if ($filters['author'] > 0) {
            $args['author'] = $filters['author'];
        }

        $meta_query = [];

        if ($filters['label'] !== '') {
            $meta_query[] = [
                'key' => ProductMeta::canonical_key('label'),
                'value' => $filters['label'],
                'compare' => '=',
            ];
        }

        if (!empty($filters['labels'])) {
            $meta_query[] = [
                'key' => ProductMeta::canonical_key('label'),
                'value' => array_values(array_filter(array_map('sanitize_key', (array) $filters['labels']))),
                'compare' => 'IN',
            ];
        }

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
            $args = self::append_meta_query($args, $meta_query);
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

        return apply_filters('wp_store_product_query_args', $args, $filters, $overrides);
    }

    public static function apply_to_query(\WP_Query $query, $filters = [], $overrides = [])
    {
        $args = self::build_query_args($filters, $overrides);

        foreach ($args as $key => $value) {
            $query->set($key, $value);
        }
    }

    public static function apply_to_args(array $args, $filters = [])
    {
        return self::build_query_args($filters, $args);
    }

    public static function from_request($request_args = [])
    {
        $filters = ProductFilterRequest::from_source($request_args);
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

    private static function append_tax_query(array $args, array $queries): array
    {
        $existing = isset($args['tax_query']) && is_array($args['tax_query']) ? $args['tax_query'] : [];
        $relation = $existing['relation'] ?? 'AND';
        unset($existing['relation']);
        $args['tax_query'] = array_merge(['relation' => $relation], array_values($existing), $queries);

        return $args;
    }

    private static function append_meta_query(array $args, array $queries): array
    {
        $existing = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
        $relation = $existing['relation'] ?? 'AND';
        unset($existing['relation']);
        $args['meta_query'] = array_merge(['relation' => $relation], array_values($existing), $queries);

        return $args;
    }
}
