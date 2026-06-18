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
            $filters['labels'] = [ProductMeta::canonical_label((string) $filters['label'])];
        }

        $filters['page'] = self::current_page($source);
        $filters['per_page'] = max(1, min(100, (int) ($source['per_page'] ?? 12)));

        return apply_filters('wp_store_product_filter_request', $filters, $source);
    }

    public static function current_page($source = []): int
    {
        if (!is_array($source)) {
            $source = [];
        }

        foreach (['shop_page', 'paged', 'page'] as $key) {
            if (isset($source[$key]) && (int) $source[$key] > 0) {
                return max(1, (int) $source[$key]);
            }
        }

        $paged = (int) get_query_var('paged');
        if ($paged > 0) {
            return max(1, $paged);
        }

        $page = (int) get_query_var('page');
        if ($page > 0) {
            return max(1, $page);
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = $request_uri !== '' ? (string) parse_url($request_uri, PHP_URL_PATH) : '';
        if ($path !== '' && preg_match('#(?:^|/)page/([0-9]+)(?:/|$)#', $path, $matches)) {
            return max(1, (int) ($matches[1] ?? 1));
        }

        return 1;
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
            $key = ProductMeta::canonical_label((string) $candidate);
            if ($key !== '') {
                $items[] = $key;
            }
        }

        return array_values(array_unique($items));
    }
}
