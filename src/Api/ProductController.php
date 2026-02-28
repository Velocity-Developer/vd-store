<?php

namespace WpStore\Api;

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
        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0 || $per_page > 50) {
            $per_page = 12;
        }

        $paged = (int) $request->get_param('page');
        if ($paged <= 0) {
            $paged = 1;
        }

        $args = [
            'post_type' => 'store_product',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => 'publish',
        ];

        $search = $request->get_param('search');
        if (is_string($search) && $search !== '') {
            $args['s'] = $search;
        }

        $category = $request->get_param('category');
        if (is_string($category) && $category !== '') {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'store_product_cat',
                    'field' => 'slug',
                    'terms' => $category,
                ],
            ];
        }

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
                $price = get_post_meta($id, '_store_price', true);
                $sale = get_post_meta($id, '_store_sale_price', true);
                $untilRaw = (string) get_post_meta($id, '_store_flashsale_until', true);
                $untilTs = $untilRaw ? strtotime($untilRaw) : 0;
                $nowTs = current_time('timestamp');
                $priceNum = $price !== '' ? (float) $price : null;
                $saleNum = $sale !== '' ? (float) $sale : null;
                $saleActive = $saleNum !== null && $saleNum > 0 && (($priceNum !== null && $saleNum < $priceNum) || $priceNum === null) && ($untilTs === 0 || $untilTs > $nowTs);
                $settings = get_option('wp_store_settings', []);
                if (!empty($settings['members_only_discount']) && !is_user_logged_in()) {
                    $saleActive = false;
                }
                $percent = ($saleActive && $priceNum !== null && $priceNum > 0) ? round((($priceNum - $saleNum) / $priceNum) * 100) : 0;
                $label = get_post_meta($id, '_store_label', true);
                $image = get_the_post_thumbnail_url($id, 'medium');
                $items[] = [
                    'id' => $id,
                    'title' => get_the_title(),
                    'link' => get_permalink(),
                    'image' => $image ? $image : null,
                    'price' => $priceNum,
                    'sale_price' => $saleNum,
                    'sale_active' => $saleActive,
                    'discount_percent' => $percent,
                    'label' => is_string($label) ? $label : '',
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
        $price = get_post_meta($id, '_store_price', true);
        $stock = get_post_meta($id, '_store_stock', true);
        $image = get_the_post_thumbnail_url($id, 'medium');

        return [
            'id' => $id,
            'title' => get_the_title($id),
            'slug' => get_post_field('post_name', $id),
            'excerpt' => wp_trim_words(get_post_field('post_content', $id), 20),
            'price' => $price !== '' ? (float) $price : null,
            'stock' => $stock !== '' ? (int) $stock : null,
            'image' => $image ? $image : null,
            'link' => get_permalink($id),
        ];
    }
}
