<?php

namespace WpStore\Domain\Product;

class ProductFilterRequest
{
    public static function from_globals(): array
    {
        return self::from_source($_GET);
    }

    public static function from_source($source = []): array
    {
        if (!is_array($source)) {
            $source = [];
        }

        $filters = ProductQuery::normalize_filters($source);
        $filters['cats'] = self::int_list($source['cats'] ?? $source['product_cats'] ?? []);
        if (empty($filters['cats']) && (int) ($filters['cat'] ?? 0) > 0) {
            $filters['cats'] = [(int) $filters['cat']];
        }

        $filters['labels'] = self::key_list($source['labels'] ?? $source['product_labels'] ?? []);
        if (empty($filters['labels']) && !empty($filters['label'])) {
            $filters['labels'] = [sanitize_key((string) $filters['label'])];
        }

        $page = $source['shop_page'] ?? $source['page'] ?? get_query_var('paged');
        if (!$page) {
            $page = 1;
        }
        $filters['page'] = max(1, (int) $page);
        $filters['per_page'] = max(1, min(100, (int) ($source['per_page'] ?? 12)));

        return apply_filters('wp_store_product_filter_request', $filters, $source);
    }

    private static function int_list($value): array
    {
        $raw = is_array($value) ? $value : [$value];
        $items = [];
        foreach ($raw as $candidate) {
            $id = absint($candidate);
            if ($id > 0) {
                $items[] = $id;
            }
        }

        return array_values(array_unique($items));
    }

    private static function key_list($value): array
    {
        $raw = is_array($value) ? $value : [$value];
        $items = [];
        foreach ($raw as $candidate) {
            $key = sanitize_key((string) $candidate);
            if ($key !== '') {
                $items[] = $key;
            }
        }

        return array_values(array_unique($items));
    }
}
