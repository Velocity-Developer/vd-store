<?php

namespace WpStore\Api;

use WpStore\Domain\Product\ProductData;
use WpStore\Domain\Product\ProductQuery;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class ProductController
{
    public function register_routes()
    {
        register_rest_route('wp-store/v1', '/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_products'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('wp-store/v1', '/products/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_product'],
                'permission_callback' => '__return_true',
                'args' => [
                    'id' => [
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
        ]);

        register_rest_route('wp-store/v1', '/catalog/pdf', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'download_catalog_pdf'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function get_products(WP_REST_Request $request)
    {
        $filters = ProductQuery::normalize_filters($request->get_params());
        $per_page = isset($request['per_page']) ? (int) $request['per_page'] : 12;
        $paged = isset($request['page']) ? (int) $request['page'] : 1;
        if ($per_page <= 0 || $per_page > 50) {
            $per_page = 12;
        }
        if ($paged <= 0) {
            $paged = 1;
        }
        if (($filters['cat'] ?? 0) <= 0 && !empty($request['category'])) {
            $term = get_term_by('slug', sanitize_title((string) $request['category']), 'store_product_cat');
            if ($term && !is_wp_error($term)) {
                $filters['cat'] = (int) $term->term_id;
            }
        }

        $args = ProductQuery::build_query_args($filters, [
            'post_type' => 'store_product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
        ]);
        $query = new WP_Query($args);

        $items = [];
        foreach ($query->posts as $post) {
            $items[] = $this->format_product($post->ID);
        }

        $response = [
            'items' => $items,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'page' => $paged,
        ];

        return new WP_REST_Response($response, 200);
    }

    public function download_catalog_pdf(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $args = [
            'post_type' => 'store_product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        $query = new \WP_Query($args);
        $items = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $product = ProductData::map_post($id);
                if ($product === null) {
                    continue;
                }

                $priceNum = $product['price'];
                $saleNum = ProductData::resolve_sale_price($id);
                $saleActive = $saleNum !== null;
                $settings = get_option('wp_store_settings', []);
                if (!empty($settings['members_only_discount']) && !is_user_logged_in()) {
                    $saleActive = false;
                }
                $percent = ($saleActive && $priceNum !== null && $priceNum > 0) ? round((($priceNum - $saleNum) / $priceNum) * 100) : 0;
                $items[] = [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'link' => $product['link'],
                    'image' => get_the_post_thumbnail_url($id, 'medium') ?: $product['image'],
                    'price' => $priceNum,
                    'sale_price' => $saleNum,
                    'sale_active' => $saleActive,
                    'discount_percent' => $percent,
                ];
            }
            wp_reset_postdata();
        }
        $html = \WpStore\Frontend\Template::render('pages/catalog-pdf', [
            'items' => $items,
            'currency' => $currency
        ]);
        if (!class_exists('\Dompdf\Dompdf')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Dompdf belum tersedia.'
            ], 500);
        }
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (ob_get_length()) {
            ob_end_clean();
        }
        $date_part = function_exists('wp_date') ? wp_date('ymd') : date('ymd');
        $rand_part = str_pad((string) wp_rand(0, 999), 3, '0', STR_PAD_LEFT);
        $filename = 'katalog-' . $date_part . '-' . $rand_part . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
    public function get_product(WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        if ($id <= 0 || get_post_type($id) !== 'store_product') {
            return new WP_REST_Response(['message' => 'Produk tidak ditemukan'], 404);
        }

        return new WP_REST_Response($this->format_product($id), 200);
    }

    private function format_product($id)
    {
        $data = ProductData::map_post($id);
        if ($data === null) {
            return [];
        }

        return [
            'id' => $data['id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'image' => $data['image'],
            'link' => $data['link'],
            'sale_price' => $data['sale_price'],
            'sku' => $data['sku'],
            'min_order' => $data['min_order'],
            'weight_kg' => $data['weight_kg'],
            'sold_count' => $data['sold_count'],
            'review_count' => $data['review_count'],
            'rating_average' => $data['rating_average'],
            'gallery_ids' => $data['gallery_ids'],
            'variant_name' => $data['variant_name'],
            'variant_options' => $data['variant_options'],
            'price_adjustment_name' => $data['price_adjustment_name'],
            'price_adjustment_options' => $data['price_adjustment_options'],
        ];
    }
}
