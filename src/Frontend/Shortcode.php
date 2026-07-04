<?php

namespace WpStore\Frontend;

use WpStore\Domain\Product\ProductData;
use WpStore\Domain\Product\ProductFilterRequest;
use WpStore\Domain\Product\ProductMeta;
use WpStore\Domain\Product\ProductQuery;
use WpStore\Domain\Product\ProductRenderer;
use WpStore\Domain\Product\RelatedProducts;
use WpStore\Domain\Product\RecentlyViewed;

class Shortcode
{
    public function register()
    {
        add_shortcode('wp_store_shop', [$this, 'render_shop']);
        add_shortcode('wp_store_single', [$this, 'render_single']);
        add_shortcode('wp_store_product_card', [$this, 'render_product_card']);
        add_shortcode('wp_store_component', [$this, 'render_component']);
        add_shortcode('wp_store_product_info', [$this, 'render_product_info']);
        add_shortcode('wp_store_info', [$this, 'render_product_info']);
        add_shortcode('wp_store_product_meta', [$this, 'render_product_info']);
        add_shortcode('wp_store_buy_button', [$this, 'render_add_to_cart']);
        add_shortcode('wp_store_related', [$this, 'render_related']);
        add_shortcode('wp_store_gallery', [$this, 'render_gallery']);
        add_shortcode('wp_store_rating', [$this, 'render_rating']);
        add_shortcode('wp_store_review_count', [$this, 'render_review_count']);
        add_shortcode('wp_store_product_reviews', [$this, 'render_product_reviews']);
        add_shortcode('wp_store_recently_viewed', [$this, 'render_recently_viewed']);
        add_shortcode('wp_store_thumbnail', [$this, 'render_thumbnail']);
        add_shortcode('wp_store_price', [$this, 'render_price']);
        add_shortcode('wp_store_add_to_cart', [$this, 'render_add_to_cart']);
        add_shortcode('wp_store_detail', [$this, 'render_detail']);
        add_shortcode('wp_store_cart', [$this, 'render_cart_widget']);
        add_shortcode('wp_store_cart_page', [$this, 'render_cart_page']);
        add_shortcode('store_cart', [$this, 'render_cart_page']);
        add_shortcode('wp_store_checkout', [$this, 'render_checkout']);
        add_shortcode('store_checkout', [$this, 'render_checkout']);
        add_shortcode('wp_store_thanks', [$this, 'render_thanks']);
        add_shortcode('store_thanks', [$this, 'render_thanks']);
        add_shortcode('wp_store_tracking', [$this, 'render_tracking']);
        add_shortcode('store_tracking', [$this, 'render_tracking']);
        add_shortcode('wp_store_wishlist', [$this, 'render_wishlist']);
        add_shortcode('wp_store_add_to_wishlist', [$this, 'render_add_to_wishlist']);
        add_shortcode('wp_store_link_profile', [$this, 'render_link_profile']);
        add_shortcode('wp_store_products_carousel', [$this, 'render_products_carousel']);
        add_shortcode('wp_store_shipping_checker', [$this, 'render_shipping_checker']);
        add_shortcode('wp_store_catalog', [$this, 'render_catalog']);
        add_shortcode('wp_store_categories', [$this, 'render_categories']);
        add_shortcode('wp_store_sosmed', [$this, 'render_sosmed']);
        add_shortcode('wp_store_contact', [$this, 'render_contact']);
        add_shortcode('wp_store_bank_accounts', [$this, 'render_bank_accounts']);
        add_shortcode('wp_store_filters', [$this, 'render_filters']);
        add_shortcode('wp_store_shop_with_filters', [$this, 'render_shop_with_filters']);
        add_shortcode('wp_store_couriers', [$this, 'render_couriers']);
        add_shortcode('wp_store_captcha', [$this, 'render_captcha']);
        add_shortcode('wp-store-captcha', [$this, 'render_captcha']);
        add_filter('the_content', [$this, 'filter_single_content']);
        add_filter('the_content', [$this, 'filter_cart_page_content']);
        add_filter('template_include', [$this, 'override_archive_template']);
        add_action('pre_get_posts', [$this, 'adjust_archive_query']);
        add_action('wp_head', [$this, 'output_product_archive_canonical'], 1);
        add_filter('fl_builder_loop_query_args', [$this, 'filter_beaver_builder_query_args']);
        add_action('template_redirect', [$this, 'redirect_page_conflict']);
    }

    private function resolve_product_id($given_id = 0)
    {
        $id = (int) $given_id;
        if ($id <= 0) {
            $loop_id = get_the_ID();
            if ($loop_id && is_numeric($loop_id)) {
                $id = (int) $loop_id;
            }
        }
        if ($id > 0 && get_post_type($id) !== 'store_product') {
            $meta_pid = (int) get_post_meta($id, 'product_id', true);
            if ($meta_pid > 0) {
                $id = $meta_pid;
            }
        }
        if ($id > 0 && get_post_type($id) !== 'store_product') {
            return 0;
        }
        return $id > 0 ? $id : 0;
    }

    private function get_currency()
    {
        $settings = get_option('wp_store_settings', []);
        return ($settings['currency_symbol'] ?? 'Rp');
    }

    private function get_thumbnail_size()
    {
        $settings = get_option('wp_store_settings', []);
        $w = isset($settings['product_thumbnail_width']) ? (int) $settings['product_thumbnail_width'] : 200;
        $h = isset($settings['product_thumbnail_height']) ? (int) $settings['product_thumbnail_height'] : 300;
        if ($w <= 0) $w = 200;
        if ($h <= 0) $h = 300;
        return [$w, $h];
    }

    private function product_payload($product_id)
    {
        return ProductData::map_post((int) $product_id);
    }

    private function card_item_from_product($product_id, $image_size = 'medium')
    {
        return ProductRenderer::card_item((int) $product_id, $image_size);
    }

    private function price_stats_for_products(array $product_ids)
    {
        $prices = [];
        foreach ($product_ids as $product_id) {
            $price = ProductData::resolve_effective_price((int) $product_id);
            if ($price === null || !is_numeric($price)) {
                continue;
            }
            $prices[] = (float) $price;
        }

        if (empty($prices)) {
            return [
                'min' => 0.0,
                'max' => 0.0,
                'avg' => 0.0,
            ];
        }

        return [
            'min' => (float) min($prices),
            'max' => (float) max($prices),
            'avg' => (float) (array_sum($prices) / count($prices)),
        ];
    }

    private function build_shop_query_args(array $cats, $sort, $search = '')
    {
        $args = [
            'post_type' => 'store_product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'no_found_rows' => true,
        ];

        $search = sanitize_text_field((string) $search);
        if ($search !== '') {
            $args['s'] = $search;
        }

        if (!empty($cats)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'store_product_cat',
                    'field' => 'term_id',
                    'terms' => $cats,
                ],
            ];
        }

        if ($sort === 'az') {
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
        } elseif ($sort === 'za') {
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
        } elseif ($sort === 'sold_desc') {
            $args['meta_key'] = '_store_sold_count';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } elseif ($sort === 'rating_desc') {
            $args['meta_key'] = '_store_rating_average';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
        } else {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }

        return $args;
    }

    public function filter_single_content($content)
    {
        if (is_singular('store_product') && in_the_loop() && is_main_query()) {
            $id = get_the_ID();
            if (!$id || get_post_type($id) !== 'store_product') {
                return $content;
            }
            $product = $this->product_payload($id);
            if ($product === null) {
                return $content;
            }
            return ProductRenderer::render_single((int) $id, [
                'content' => $content,
            ]);
        }
        return $content;
    }

    public function render_shop($atts = [])
    {
        $atts = shortcode_atts([
            'per_page' => 12,
        ], $atts);

        $per_page = (int) $atts['per_page'];
        if ($per_page <= 0 || $per_page > 50) {
            $per_page = 12;
        }

        $paged = isset($_GET['shop_page']) ? (int) $_GET['shop_page'] : 0;
        if ($paged <= 0) {
            $qp = (int) get_query_var('paged');
            if ($qp <= 0) {
                $qp = (int) get_query_var('page');
            }
            $paged = $qp > 0 ? $qp : 1;
        }
        if ($paged <= 1) {
            $candidate_paths = [];
            $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
            $redirect_url = isset($_SERVER['REDIRECT_URL']) ? (string) $_SERVER['REDIRECT_URL'] : '';
            $path_info = isset($_SERVER['PATH_INFO']) ? (string) $_SERVER['PATH_INFO'] : '';
            if ($request_uri !== '') {
                $request_path = (string) parse_url($request_uri, PHP_URL_PATH);
                if ($request_path !== '') {
                    $candidate_paths[] = $request_path;
                }
            }
            if ($redirect_url !== '') {
                $candidate_paths[] = $redirect_url;
            }
            if ($path_info !== '') {
                $candidate_paths[] = $path_info;
            }
            global $wp;
            if (isset($wp) && is_object($wp) && !empty($wp->request)) {
                $candidate_paths[] = '/' . ltrim((string) $wp->request, '/');
            }

            foreach ($candidate_paths as $candidate_path) {
                if (!is_string($candidate_path) || $candidate_path === '') {
                    continue;
                }
                if (preg_match('#(?:^|/)page/(\d+)(?:/|$)#', $candidate_path, $matches)) {
                    $uri_page = (int) ($matches[1] ?? 0);
                    if ($uri_page > 1) {
                        $paged = $uri_page;
                        break;
                    }
                }
            }
        }

        $request_filters = ProductFilterRequest::from_globals();
        if (is_tax('store_product_cat')) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $request_filters['cats'] = array_values(array_unique(array_merge(
                    [(int) $term->term_id],
                    (array) ($request_filters['cats'] ?? [])
                )));
            }
        }
        if (is_tax('brand')) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $request_filters['brands'] = array_values(array_unique(array_merge(
                    [(int) $term->term_id],
                    (array) ($request_filters['brands'] ?? [])
                )));
            }
        }

        $currency = (get_option('wp_store_settings', [])['currency_symbol'] ?? 'Rp');
        $query = new \WP_Query(ProductQuery::build_query_args($request_filters, [
            'post_type' => 'store_product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
        ]));
        $items = [];
        foreach ($query->posts as $post) {
            $product_id = is_object($post) ? (int) $post->ID : (int) $post;
            $item = $this->card_item_from_product((int) $product_id, $this->get_thumbnail_size());
            if ($item === null) {
                continue;
            }
            $items[] = $item;
        }

        $total_items = (int) $query->found_posts;
        $total_pages = max(1, (int) $query->max_num_pages);
        if ($paged > $total_pages) {
            $paged = $total_pages;
        }

        return Template::render('pages/shop', [
            'items' => $items,
            'currency' => $currency,
            'page' => (int) $paged,
            'pages' => (int) $total_pages,
            'total' => (int) $total_items
        ]);
    }

    public function render_captcha($atts = [])
    {
        wp_enqueue_script('alpinejs');
        wp_enqueue_script('wp-store-frontend');
        $atts = shortcode_atts([
            'target-button' => '',
            'target_button' => ''
        ], $atts);
        $selector = '';
        if (isset($atts['target-button']) && is_string($atts['target-button'])) {
            $selector = $atts['target-button'];
        }
        if ($selector === '' && isset($atts['target_button']) && is_string($atts['target_button'])) {
            $selector = $atts['target_button'];
        }
        $html = '<div class="wps-captcha-shortcode-wrap" data-target-button="' . esc_attr($selector) . '">';
        $html .= Captcha::render();
        $html .= '</div>';
        $html .= '<script>(function(){try{var wrap=document.currentScript&&document.currentScript.previousElementSibling; if(!wrap||!wrap.classList||!wrap.classList.contains("wps-captcha-shortcode-wrap")){wrap=document.querySelector(".wps-captcha-shortcode-wrap");} var sel=(wrap&&wrap.getAttribute("data-target-button"))||""; if(!wrap||!sel){return;} function value(name){var el=wrap.querySelector("[name=\""+name+"\"]")||document.querySelector("[name=\""+name+"\"]");return String(el&&el.value?el.value:"").trim();} function ready(){ var ok=false; if(value("g-recaptcha-response")!==""){ok=true;} if(value("vd_captcha_token")!==""&&value("vd_captcha_input")!==""){ok=true;} if(value("captcha_verified")==="1"&&value("captcha_id")!==""&&value("captcha_value")!==""){ok=true;} document.querySelectorAll(sel).forEach(function(btn){ try{ if(ok){ btn.removeAttribute("disabled"); }else{ btn.setAttribute("disabled","disabled"); } }catch(e){} }); } wrap.addEventListener("change", ready, true); wrap.addEventListener("input", ready, true); if(document.readyState!=="loading"){ ready(); } else { document.addEventListener("DOMContentLoaded", ready); } }catch(e){console&&console.warn&&console.warn(e);} })();</script>';
        return $html;
    }

    public function render_checkout($atts = [])
    {
        wp_enqueue_script('alpinejs');
        wp_enqueue_script('wp-store-frontend');
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $origin_subdistrict = isset($settings['shipping_origin_subdistrict']) ? (string) $settings['shipping_origin_subdistrict'] : '';
        $active_couriers = $settings['shipping_couriers'] ?? ['jne', 'sicepat', 'ide'];
        $nonce = wp_create_nonce('wp_rest');
        return Template::render('pages/checkout', [
            'currency' => $currency,
            'origin_subdistrict' => $origin_subdistrict,
            'active_couriers' => $active_couriers,
            'nonce' => $nonce
        ]);
    }

    public function render_shipping_checker($atts = [])
    {
        wp_enqueue_script('alpinejs');
        wp_enqueue_script('wp-store-frontend');
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $origin_subdistrict = isset($settings['shipping_origin_subdistrict']) ? (string) $settings['shipping_origin_subdistrict'] : '';
        $active_couriers = $settings['shipping_couriers'] ?? ['jne', 'sicepat', 'ide'];
        $nonce = wp_create_nonce('wp_rest');
        return Template::render('pages/shipping-checker', [
            'currency' => $currency,
            'origin_subdistrict' => $origin_subdistrict,
            'active_couriers' => $active_couriers,
            'nonce' => $nonce
        ]);
    }

    public function render_catalog($atts = [])
    {
        wp_enqueue_script('alpinejs');
        wp_enqueue_script('wp-store-frontend');
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $query = ProductQuery::query([
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $items = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $item = $this->card_item_from_product($id, $this->get_thumbnail_size());
                if ($item !== null) {
                    $items[] = $item;
                }
            }
            wp_reset_postdata();
        }
        return Template::render('pages/catalog', [
            'items' => $items,
            'currency' => $currency
        ]);
    }

    public function render_categories($atts = [])
    {
        $atts = shortcode_atts([
            'hide_empty' => '0',
            'orderby' => 'name',
            'order' => 'ASC',
        ], $atts);

        $categories = get_terms([
            'taxonomy' => 'store_product_cat',
            'hide_empty' => (string) $atts['hide_empty'] === '1',
            'orderby' => sanitize_key($atts['orderby']),
            'order' => strtoupper(sanitize_key($atts['order'])),
        ]);

        if (is_wp_error($categories) || empty($categories)) {
            return '';
        }

        return Template::render('components/categories-list', [
            'categories' => $categories
        ]);
    }

    public function render_sosmed($atts = [])
    {
        ob_start();

        $sosmedava = ['facebook', 'instagram', 'twitter', 'youtube'];
        $sosmed = [
            'facebook' => [
                'color'     => '#475A95',
                'caption'   => 'Find us on',
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16"> <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/> </svg>',
            ],
            'instagram' => [
                'color'     => '#C43FBD',
                'caption'   => 'Follow us on',
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" fill="currentColor" class="bi bi-instagram" viewBox="0 0 16 16"> <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/> </svg>',
            ],
            'twitter' => [
                'color'     => '#000000',
                'caption'   => 'Follow us on',
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" fill="currentColor" class="bi bi-twitter-x" viewBox="0 0 16 16"> <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z"/> </svg>',
            ],
            'youtube' => [
                'color'     => '#E93E3C',
                'caption'   => 'Subscribe us on',
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" fill="currentColor" class="bi bi-youtube" viewBox="0 0 16 16"> <path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.007 2.007 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.007 2.007 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31.4 31.4 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.007 2.007 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A99.788 99.788 0 0 1 7.858 2h.193zM6.4 5.209v4.818l4.157-2.408L6.4 5.209z"/> </svg>',
            ],
        ];

        if (!empty($atts)) {
            echo '<div class="wps-sosmed">';
            foreach ($atts as $key => $value) {
                if (in_array($key, $sosmedava)) {
                    $captionkey = 'caption-' . $key;
                    $caption    = isset($atts[$captionkey]) ? $atts[$captionkey] : $sosmed[$key]['caption'];

                    echo '<a href="' . esc_url($value) . '" target="_blank" class="wps-sosmed-item wps-text-white wps-display-block wps-mb-2 wps-px-3 wps-py-2 ' . esc_attr($key) . '" style="background-color: ' . esc_attr($sosmed[$key]['color']) . ' !important; text-decoration: none; border-radius: 8px;">';
                    echo '<div class="wps-flex wps-items-center wps-p-2">';
                    echo '<div class="wps-text-center" style="width: 50px; flex-shrink: 0;">';
                    echo $sosmed[$key]['icon'];
                    echo '</div>';
                    echo '<div class="wps-ml-3">';
                    echo '<div class="wps-text-sm">' . esc_html($caption) . '</div>';
                    echo '<div class="wps-font-semibold wps-text-lg">' . esc_html(ucfirst($key)) . '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</a>';
                }
            }
            echo '</div>';
        }

        return ob_get_clean();
    }

    public function render_bank_accounts($atts = [])
    {
        $settings = get_option('wp_store_settings', []);
        $accounts = $settings['store_bank_accounts'] ?? [];

        if (empty($accounts) || !is_array($accounts)) {
            return '';
        }

        ob_start();
        echo '<div class="wps-bank-accounts wps-text-center wps-mt-6">';
        foreach ($accounts as $index => $acc) {
            if (empty($acc['bank_name']) || empty($acc['bank_account'])) continue;

            $bank_name = (string) $acc['bank_name'];
            if ($bank_name === 'Bank BTN Syariah') {
                $bank_name = 'BSN';
            }
            $logo_url = function_exists('wp_store_bank_logo') ? wp_store_bank_logo($bank_name) : '';

            echo '<div class="wps-bank-item wps-mb-2">';
            if ($logo_url) {
                echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($bank_name) . '" class="wps-mx-auto wps-mb-1" style="max-height: 40px; width: auto;">';
            } else {
                echo '<div class="wps-font-normal wps-text-lg wps-mb-1">' . esc_html($bank_name) . '</div>';
            }
            echo '<div class="wps-text-sm wps-font-normal wps-mb-1">' . esc_html($acc['bank_account']);
            if (!empty($acc['bank_holder']) && !empty($acc['bank_account'])) {
                echo '<span class="wps-text-gray-700"> a/n </span>' . esc_html($acc['bank_holder']);
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    public function render_contact($atts = [])
    {
        ob_start();
        $atts = shortcode_atts([
            'style' => 'true',
        ], $atts);

        $settings = get_option('wp_store_settings', []);
        $nosms = $settings['store_sms'] ?? '';
        $notlp = $settings['store_phone'] ?? '';
        $nowa = $settings['store_wa'] ?? '';
        $notelegram = $settings['store_telegram'] ?? '';
        $emailktoko = $settings['store_email'] ?? '';
        $isipesan = 'Hallo ' . get_bloginfo('name');

        // Normalize numbers
        if ($nosms && substr($nosms, 0, 1) === '0') {
            $nosms = '+62' . substr($nosms, 1);
        }
        if ($notlp && substr($notlp, 0, 1) === '0') {
            $notlp = '+62' . substr($notlp, 1);
        }
        if ($nowa && substr($nowa, 0, 1) === '0') {
            $nowa = '62' . substr($nowa, 1);
        }

        $buttons = [
            'sms' => [
                'data'      => $nosms,
                'caption'   => $nosms,
                'href'      => 'sms:' . $nosms . '?body=' . rawurlencode($isipesan),
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat-right-text" viewBox="0 0 16 16"> <path d="M2 1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h9.586a2 2 0 0 1 1.414.586l2 2V2a1 1 0 0 0-1-1H2zm12-1a2 2 0 0 1 2 2v12.793a.5.5 0 0 1-.854.353l-2.853-2.853a1 1 0 0 0-.707-.293H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h12z"/> <path d="M3 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM3 6a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9A.5.5 0 0 1 3 6zm0 2.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z"/> </svg>',
            ],
            'tlp' => [
                'data'      => $notlp,
                'caption'   => $notlp,
                'href'      => 'tel:' . $notlp,
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16"> <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/> </svg>',
            ],
            'wa' => [
                'data'      => $nowa,
                'caption'   => $nowa,
                'href'      => 'https://wa.me/' . $nowa . '?text=' . rawurlencode($isipesan),
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16"> <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/> </svg>',
            ],
            'telegram' => [
                'data'      => $notelegram,
                'caption'   => $notelegram,
                'href'      => 'https://telegram.me/' . $notelegram,
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telegram" viewBox="0 0 16 16"> <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/></svg>',
            ],
            'email' => [
                'data'      => $emailktoko,
                'caption'   => $emailktoko,
                'href'      => 'mailto:' . $emailktoko,
                'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16"> <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/> </svg>',
            ],
        ];

        $class = ($atts['style'] === 'true') ? 'wps-btn wps-btn-sm wps-btn-secondary wps-display-block wps-mb-1 wps-text-left' : 'wps-btn wps-btn-sm wps-btn-link wps-px-0';

        echo '<div class="wps-contact-buttons">';
        foreach ($buttons as $key => $data) {
            if ($data['data']) {
                echo '<a href="' . esc_url($data['href']) . '" target="_blank" class="' . esc_attr($class) . '" style="text-decoration:none;">';
                echo '<span class="wps-mr-2">' . $data['icon'] . '</span>';
                echo '<span class="wps-contact-caption">' . esc_html($data['caption']) . '</span>';
                echo '</a>';
            }
        }
        echo '</div>';

        return ob_get_clean();
    }

    private function resolve_order_id($input)
    {
        if (empty($input)) return 0;

        $args = [
            'post_type' => 'store_order',
            'meta_key' => '_store_order_number',
            'meta_value' => $input,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'post_status' => 'any'
        ];
        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            return $query->posts[0];
        }

        if (is_numeric($input)) {
            $id = absint($input);
            if ($id > 0 && get_post_type($id) === 'store_order') {
                $has_token = get_post_meta($id, '_store_order_number', true);
                if (!$has_token) {
                    return $id;
                }
            }
        }

        return 0;
    }

    public function render_thanks($atts = [])
    {
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $input = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';
        $order_id = $this->resolve_order_id($input);
        return Template::render('pages/thanks', [
            'currency' => $currency,
            'order_id' => $order_id,
        ]);
    }

    public function render_tracking($atts = [])
    {
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $query_param = (string) apply_filters('wp_store_tracking_query_param', 'order', $atts);
        if ($query_param === '') {
            $query_param = 'order';
        }
        $input = isset($_GET[$query_param]) ? sanitize_text_field(wp_unslash((string) $_GET[$query_param])) : '';
        if ($input === '' && $query_param !== 'order' && isset($_GET['order'])) {
            $input = sanitize_text_field(wp_unslash((string) $_GET['order']));
        }
        $order_id = $this->resolve_order_id($input);
        $order_id = (int) apply_filters('wp_store_tracking_resolved_order_id', $order_id, $input, [
            'atts' => $atts,
            'query_param' => $query_param,
        ]);
        return Template::render('pages/tracking', [
            'currency' => $currency,
            'order_id' => $order_id,
            'tracking_query_param' => $query_param,
            'tracking_query_value' => $input,
            'tracking_input_label' => (string) apply_filters('wp_store_tracking_input_label', 'Nomor Order', $query_param, $atts),
            'tracking_input_placeholder' => (string) apply_filters('wp_store_tracking_input_placeholder', 'Masukkan Nomor Order', $query_param, $atts),
            'tracking_submit_label' => (string) apply_filters('wp_store_tracking_submit_label', 'Lacak', $query_param, $atts),
            'tracking_empty_help' => (string) apply_filters('wp_store_tracking_empty_help', 'Masukkan nomor order di form berikut untuk melihat status.', $query_param, $atts),
        ]);
    }

    public function render_related($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'per_page' => 4,
        ], $atts);
        $id = $this->resolve_product_id((int) $atts['id']);
        if ($id <= 0 || get_post_type($id) !== 'store_product') {
            return '';
        }
        $per_page = (int) $atts['per_page'];
        if ($per_page <= 0 || $per_page > 12) {
            $per_page = 4;
        }
        $currency = (get_option('wp_store_settings', [])['currency_symbol'] ?? 'Rp');

        $items = [];
        foreach (RelatedProducts::ids($id, $per_page) as $related_id) {
            $item = $this->card_item_from_product($related_id, 'medium');
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return Template::render('pages/related', [
            'items' => $items,
            'currency' => $currency
        ]);
    }

    public function render_product_card($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'context' => 'shortcode',
            'variant' => 'default',
            'image_size' => '',
            'width' => '',
            'height' => '',
            'crop' => 'true',
            'class' => '',
        ], $atts);

        $card_args = [
            'context' => $atts['context'],
            'variant' => $atts['variant'],
            'card_class' => $atts['class'],
            'thumbnail_crop' => $atts['crop'],
        ];
        if ((string) $atts['image_size'] !== '') {
            $card_args['image_size'] = $atts['image_size'];
        }
        if ((int) $atts['width'] > 0) {
            $card_args['thumbnail_width'] = (int) $atts['width'];
        }
        if ((int) $atts['height'] > 0) {
            $card_args['thumbnail_height'] = (int) $atts['height'];
        }

        $id = $this->resolve_product_id((int) $atts['id']);
        return $id > 0 ? ProductRenderer::render_card($id, $card_args) : '';
    }

    public function render_component($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'name' => '',
            'component' => '',
        ], $atts);

        $id = $this->resolve_product_id((int) $atts['id']);
        $component = (string) ($atts['component'] ?: $atts['name']);
        return $id > 0 && $component !== '' ? ProductRenderer::render_component($component, $id, $atts) : '';
    }

    public function render_product_info($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $id = $this->resolve_product_id((int) $atts['id']);
        return $id > 0 ? ProductRenderer::product_info($id, $atts) : '';
    }

    public function render_gallery($atts = [])
    {
        wp_enqueue_script('wp-store-frontend');
        wp_enqueue_style('wp-store-flickity');

        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);
        $id = $this->resolve_product_id((int) $atts['id']);
        if ($id <= 0 || get_post_type($id) !== 'store_product') {
            return '';
        }

        $product = $this->product_payload($id);
        if ($product === null) {
            return '';
        }

        $featured = get_the_post_thumbnail_url($id, 'large');
        $fallback = WP_STORE_URL . 'assets/frontend/img/noimg.webp';
        $image_src = $featured ?: ($product['image'] ?: $fallback);
        $gallery_ids = ProductMeta::gallery_ids((int) $id);
        $items = [];
        $featured_thumb = get_the_post_thumbnail_url((int) $id, 'thumbnail');
        $featured_thumb = $featured_thumb ? $featured_thumb : $image_src;
        $items[] = [
            'full' => $image_src,
            'thumb' => $featured_thumb,
        ];

        if (!empty($gallery_ids)) {
            foreach ($gallery_ids as $attachment_id) {
                $attachment_id = (int) $attachment_id;
                if ($attachment_id <= 0) {
                    continue;
                }
                $full = wp_get_attachment_image_url($attachment_id, 'large');
                $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                if ($full) {
                    $items[] = [
                        'full' => $full,
                        'thumb' => $thumb ?: $full,
                    ];
                }
            }
        }

        return Template::render('components/product-gallery', [
            'id' => (int) $id,
            'title' => (string) $product['title'],
            'image_src' => (string) $image_src,
            'items' => $items,
        ]);
    }

    public function render_product_reviews($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'limit' => 20,
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        return Template::render('components/product-reviews', [
            'product_id' => $product_id,
            'limit' => max(1, min(100, (int) $atts['limit'])),
            'review_repo' => new \WpStore\Domain\Review\ProductReviewRepository(),
        ]);
    }

    public function render_rating($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'size' => 16,
            'show_value' => 'true',
            'show_count' => 'true',
            'class' => '',
            'count_text' => __('ulasan', 'vd-store'),
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $summary = (new \WpStore\Domain\Review\ProductReviewRepository())->product_summary($product_id);

        return \WpStore\Domain\Review\RatingRenderer::summary_html(
            (float) ($summary['rating_average'] ?? 0),
            (int) ($summary['review_count'] ?? 0),
            [
                'size' => max(10, (int) $atts['size']),
                'show_value' => filter_var($atts['show_value'], FILTER_VALIDATE_BOOLEAN),
                'show_count' => filter_var($atts['show_count'], FILTER_VALIDATE_BOOLEAN),
                'class' => sanitize_text_field((string) $atts['class']),
                'count_text' => sanitize_text_field((string) $atts['count_text']),
            ]
        );
    }

    public function render_review_count($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'class' => '',
            'suffix' => __('ulasan', 'vd-store'),
        ], $atts);

        $product_id = $this->resolve_product_id((int) $atts['id']);
        if ($product_id <= 0) {
            return '';
        }

        $summary = (new \WpStore\Domain\Review\ProductReviewRepository())->product_summary($product_id);
        $count = (int) ($summary['review_count'] ?? 0);

        return sprintf(
            '<span class="%1$s">%2$s</span>',
            esc_attr(trim((string) $atts['class'])),
            esc_html(sprintf(__('%1$d %2$s', 'vd-store'), $count, (string) $atts['suffix']))
        );
    }

    public function render_recently_viewed($atts = [])
    {
        $atts = shortcode_atts([
            'limit' => 4,
            'exclude_current' => 'true',
            'title' => __('Produk yang Baru Dilihat', 'wp-store'),
        ], $atts);

        $exclude_id = filter_var($atts['exclude_current'], FILTER_VALIDATE_BOOLEAN) ? $this->resolve_product_id(0) : 0;
        $items = [];
        foreach (RecentlyViewed::items($exclude_id, (int) $atts['limit']) as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $items[] = [
                'id' => (int) $item['id'],
                'title' => (string) ($item['title'] ?? ''),
                'link' => (string) ($item['link'] ?? '#'),
                'image' => (string) ($item['image'] ?? ''),
                'price' => $item['price'] ?? null,
                'stock' => $item['stock'] ?? null,
            ];
        }

        if (empty($items)) {
            return '';
        }

        return Template::render('pages/recently-viewed', [
            'title' => (string) $atts['title'],
            'items' => $items,
            'currency' => $this->get_currency(),
        ]);
    }

    public function render_filters($atts = [])
    {
        $atts = shortcode_atts([
            'mode' => 'auto',
        ], $atts);

        $terms = get_terms([
            'taxonomy' => 'store_product_cat',
            'hide_empty' => false,
        ]);
        $categories = [];
        if (!is_wp_error($terms) && is_array($terms)) {
            foreach ($terms as $t) {
                $categories[] = [
                    'id' => (int) $t->term_id,
                    'name' => (string) $t->name,
                    'parent' => (int) ($t->parent ?? 0),
                ];
            }
        }

        $brand_terms = get_terms([
            'taxonomy' => 'brand',
            'hide_empty' => false,
        ]);
        $brands = [];
        if (!is_wp_error($brand_terms) && is_array($brand_terms)) {
            foreach ($brand_terms as $t) {
                $brands[] = [
                    'id' => (int) $t->term_id,
                    'name' => (string) $t->name,
                ];
            }
        }

        $request = ProductFilterRequest::from_globals();
        $current = [
            'sort' => (string) ($request['sort'] ?? ''),
            'min_price' => $request['min_price'] ?? '',
            'max_price' => $request['max_price'] ?? '',
            'cats' => isset($request['cats']) && is_array($request['cats']) ? $request['cats'] : [],
            'brands' => isset($request['brands']) && is_array($request['brands']) ? $request['brands'] : [],
        ];
        if (is_tax('store_product_cat')) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $tid = (int) $term->term_id;
                if ($tid > 0 && !in_array($tid, $current['cats'], true)) {
                    $current['cats'][] = $tid;
                }
            }
        }
        if (is_tax('brand')) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $tid = (int) $term->term_id;
                if ($tid > 0 && !in_array($tid, $current['brands'], true)) {
                    $current['brands'][] = $tid;
                }
            }
        }
        $locked_cats = [];
        $locked_brands = [];
        if (is_tax('store_product_cat')) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $tid = (int) $term->term_id;
                if ($tid > 0) {
                    $locked_cats[] = $tid;
                }
            }
        }
        if (is_tax('brand')) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $tid = (int) $term->term_id;
                if ($tid > 0) {
                    $locked_brands[] = $tid;
                }
            }
        }
        if (is_tax(['store_product_cat', 'brand'])) {
            $term = get_queried_object();
            $reset_url = ($term && !is_wp_error($term)) ? get_term_link($term) : get_post_type_archive_link('store_product');
        } elseif (is_post_type_archive('store_product') || (get_query_var('post_type') === 'store_product' && !is_singular())) {
            $reset_url = get_post_type_archive_link('store_product');
        } else {
            $req = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
            $path = parse_url($req, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = '/';
            }
            $path = preg_replace('#/page/\d+/?#', '/', $path);
            $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
            if ($home_path !== '' && $home_path !== '/' && strpos($path, $home_path) === 0) {
                $path = substr($path, strlen($home_path));
                $path = '/' . ltrim((string) $path, '/');
            }
            $reset_url = home_url($path ?: '/');
        }
        if (is_wp_error($reset_url) || !is_string($reset_url) || $reset_url === '') {
            $reset_url = home_url('/');
        }
        $stats_filters = $request;
        $stats_filters['sort'] = 'latest';
        if (!empty($current['cats'])) {
            $stats_filters['cats'] = $current['cats'];
        } elseif (!empty($locked_cats)) {
            $stats_filters['cats'] = $locked_cats;
        }
        if (!empty($current['brands'])) {
            $stats_filters['brands'] = $current['brands'];
        } elseif (!empty($locked_brands)) {
            $stats_filters['brands'] = $locked_brands;
        }
        $price_stats_query = new \WP_Query(ProductQuery::build_query_args($stats_filters, [
            'post_type' => 'store_product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]));
        $price_stats = $this->price_stats_for_products($price_stats_query->posts);
        $min_price_global = floor(max(0.0, (float) ($price_stats['min'] ?? 0)));
        $max_price_global = ceil(max($min_price_global, (float) ($price_stats['max'] ?? 0)));
        $avg_price_global = round((float) ($price_stats['avg'] ?? 0));
        return Template::render('components/filters', [
            'categories' => $categories,
            'brands' => $brands,
            'current' => $current,
            'reset_url' => $reset_url,
            'price_min_global' => $min_price_global,
            'price_max_global' => $max_price_global,
            'price_avg_global' => $avg_price_global,
            'locked_cats' => $locked_cats,
            'locked_brands' => $locked_brands,
            'mode' => sanitize_key((string) $atts['mode']),
        ]);
    }

    public function render_shop_with_filters($atts = [])
    {
        $atts = shortcode_atts([
            'per_page' => 12,
            'filter_mode' => 'auto',
        ], $atts);
        if (sanitize_key((string) $atts['filter_mode']) !== 'off') {
            wp_enqueue_script('alpinejs');
        }
        $filters = $this->render_filters(['mode' => $atts['filter_mode']]);
        $shop = $this->render_shop(['per_page' => $atts['per_page']]);
        if (sanitize_key((string) $atts['filter_mode']) === 'off') {
            return '<div class="wps-flex wps-gap-4"><div style="width:260px;flex:0 0 260px;">' . $filters . '</div><div style="flex:1 1 auto;">' . $shop . '</div></div>';
        }
        ob_start();
?>
<div x-data="{ openFilters:false, isMobile: window.matchMedia('(max-width: 768px)').matches } ?? {}" x-init="(() => {
              const mq = window.matchMedia('(max-width: 768px)');
              const update = () => { isMobile = mq.matches };
              if (mq.addEventListener) { mq.addEventListener('change', update); } else if (mq.addListener) { mq.addListener(update); }
              update();
            })()">
    <div class="wps-flex wps-justify-end wps-mb-2" x-show="isMobile" x-cloak>
        <button class="wps-btn wps-btn-secondary"
            @click="openFilters = true"><?php echo esc_html__('Filter', 'wp-store'); ?></button>
    </div>
    <div class="wps-flex wps-gap-4">
        <div x-show="!isMobile" x-cloak style="width:260px;flex:0 0 260px;"><?php echo $filters; ?></div>
        <div style="flex:1 1 auto;"><?php echo $shop; ?></div>
    </div>
    <template x-if="openFilters">
        <div>
            <div class="wps-offcanvas-backdrop" @click="openFilters=false"></div>
            <div class="wps-offcanvas">
                <div class="wps-offcanvas-header">
                    <div><?php echo esc_html__('Filter', 'wp-store'); ?></div>
                    <button class="wps-btn wps-btn-secondary"
                        @click="openFilters=false"><?php echo esc_html__('Tutup', 'wp-store'); ?></button>
                </div>
                <div class="wps-offcanvas-body">
                    <?php echo $filters; ?>
                </div>
            </div>
        </div>
    </template>
</div>
<?php
        return ob_get_clean();
    }

    public function render_products_carousel($atts = [])
    {
        wp_enqueue_script('alpinejs');
        $defaults = $this->get_thumbnail_size();
        $atts = shortcode_atts([
            'label' => '',
            'per_page' => 10,
            'per_row' => 1,
            'img_width' => $defaults[0],
            'img_height' => $defaults[1],
            'crop' => 'true',
            'autoplay' => 0,
            'pause_on_hover' => 'true',
            'wrap_around' => 'true',
            'page_dots' => 'false',
            'prev_next_buttons' => 'true',
            'lazy_load' => 0,
            'cell_align' => 'center',
            'draggable' => 'true',
            'contain' => 'true'
        ], $atts);
        wp_enqueue_style('wp-store-flickity');
        $per_page = (int) $atts['per_page'];
        if ($per_page <= 0 || $per_page > 20) {
            $per_page = 10;
        }
        $args = [
            'post_type' => 'store_product',
            'posts_per_page' => $per_page,
            'post_status' => 'publish',
        ];
        $query = new \WP_Query($args);
        $currency = $this->get_currency();
        $items = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $item = $this->card_item_from_product($id, 'medium');
                if ($item !== null) {
                    $items[] = $item;
                }
            }
            wp_reset_postdata();
        }
        $html = Template::render('components/products-carousel', [
            'items' => $items,
            'per_row' => (int) $atts['per_row'],
            'currency' => $currency,
            'label' => (string) $atts['label'],
            'img_width' => max(1, (int) $atts['img_width']),
            'img_height' => max(1, (int) $atts['img_height']),
            'crop' => in_array(strtolower((string) $atts['crop']), ['1', 'true', 'yes'], true),
            'opts' => [
                'autoplay' => max(0, (int) $atts['autoplay']),
                'pause_on_hover' => in_array(strtolower((string) $atts['pause_on_hover']), ['1', 'true', 'yes'], true),
                'wrap_around' => in_array(strtolower((string) $atts['wrap_around']), ['1', 'true', 'yes'], true),
                'page_dots' => in_array(strtolower((string) $atts['page_dots']), ['1', 'true', 'yes'], true),
                'prev_next_buttons' => in_array(strtolower((string) $atts['prev_next_buttons']), ['1', 'true', 'yes'], true),
                'lazy_load' => max(0, (int) $atts['lazy_load']),
                'cell_align' => sanitize_key($atts['cell_align']),
                'draggable' => in_array(strtolower((string) $atts['draggable']), ['1', 'true', 'yes'], true),
                'contain' => in_array(strtolower((string) $atts['contain']), ['1', 'true', 'yes'], true),
            ]
        ]);
        wp_enqueue_script('wp-store-frontend');
        return $html;
    }

    public function render_thumbnail($atts = [])
    {
        $defaults = $this->get_thumbnail_size();
        $atts = shortcode_atts([
            'id' => 0,
            'width' => $defaults[0],
            'height' => $defaults[1],
            'crop' => 'true',
            'upscale' => 'true',
            'alt' => '',
            'hover' => 'change',
            'label' => 'true'
        ], $atts);
        $id = $this->resolve_product_id((int) $atts['id']);
        if ($id <= 0 || get_post_type($id) !== 'store_product') {
            return '';
        }
        $product = $this->product_payload($id);
        if ($product === null) {
            return '';
        }
        $w = max(1, (int) $atts['width']);
        $h = max(1, (int) $atts['height']);
        $size = [$w, $h];
        $src = get_the_post_thumbnail_url($id, $size);
        if (!$src) {
            $src = WP_STORE_URL . 'assets/frontend/img/noimg.webp';
        }
        $alt = is_string($atts['alt']) && $atts['alt'] !== '' ? $atts['alt'] : get_the_title($id);
        $crop = in_array(strtolower((string) $atts['crop']), ['1', 'true', 'yes'], true);
        $style = 'width:100%; height:100%; object-fit:' . ($crop ? 'cover' : 'contain') . ';';
        $wrap_style = 'width:100%; aspect-ratio:' . (int) $w . ' / ' . (int) $h . '; overflow:hidden;';
        $hoverMode = sanitize_key($atts['hover']);
        $showLabel = in_array(strtolower((string) $atts['label']), ['1', 'true', 'yes'], true);
        $badgeHtml = '';
        $digitalHtml = '';
        if ($showLabel) {
            $ptype = (string) ProductMeta::get((int) $id, 'product_type', 'physical');
            $is_digital = $ptype === 'digital';
            if ($is_digital) {
                $digitalHtml = '<span class="wps-digital-badge wps-text-xs wps-text-white">'
                    . \wps_icon(['name' => 'cloud-download', 'size' => 12, 'stroke_color' => '#ffffff'])
                    . '<span class="txt wps-text-white wps-text-xs">Digital</span>'
                    . '</span>';
            }
            $badgeHtml = \wps_label_badge_html((int) $id);
        }
        if ($hoverMode === 'change') {
            $hover_src = '';
            $gal = ProductMeta::gallery_ids((int) $id);
            if (is_array($gal) && !empty($gal)) {
                $first = array_values($gal)[0];
                if (is_numeric($first)) {
                    $url = wp_get_attachment_image_url((int) $first, $size);
                    if (is_string($url)) $hover_src = $url;
                } elseif (is_string($first)) {
                    $hover_src = $first;
                }
            }
            $wrap_class = 'wps-card-hover';
            $image_wrap_class = 'wps-image-wrap' . ($hover_src ? ' wps-has-hover' : '');
            $html = '<div class="' . esc_attr($wrap_class) . '"><div class="' . esc_attr($image_wrap_class) . '" style="' . esc_attr($wrap_style) . '">';
            $html .= '<img class="wps-rounded img-main" src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '" style="' . esc_attr($style) . '">';
            if ($hover_src) {
                $html .= '<img class="wps-rounded img-hover" src="' . esc_url($hover_src) . '" alt="' . esc_attr($alt) . '">';
            }
            if ($digitalHtml) {
                $html .= $digitalHtml;
            }
            if ($badgeHtml) {
                $html .= $badgeHtml;
            }
            $html .= \wps_discount_badge_html((int) $id);
            $html .= '</div></div>';
            return $html;
        }
        return '<div class="wps-image-wrap" style="' . esc_attr($wrap_style) . '"><img src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '" style="' . esc_attr($style) . '" class="wps-rounded">' . $digitalHtml . $badgeHtml . \wps_discount_badge_html((int) $id) . '</div>';
    }

    public function render_price($atts)
    {
        $raw_atts = is_array($atts) ? $atts : [];
        $atts = shortcode_atts([
            'id' => 0,
            'countdown' => false,
            'wrapper' => '',
            'tag' => '',
            'class' => '',
        ], $atts);

        $id = $this->resolve_product_id((int) $atts['id']);
        $args = ['countdown' => $atts['countdown']];
        if (array_key_exists('wrapper', $raw_atts)) {
            $args['wrapper_tag'] = (string) $atts['wrapper'];
        }
        if ((string) $atts['tag'] !== '') {
            $args['price_tag'] = (string) $atts['tag'];
            $args['empty_tag'] = (string) $atts['tag'];
        }
        if ((string) $atts['class'] !== '') {
            $args['price_class'] = (string) $atts['class'];
            $args['empty_class'] = (string) $atts['class'];
        }

        return $id > 0 ? wps_product_price_html($id, $args) : '';
    }

    public function render_single($atts = [])
    {
        wp_enqueue_script('wp-store-frontend');
        wp_enqueue_style('wp-store-flickity');
        $atts = shortcode_atts([
            'id' => get_the_ID(),
            'hide' => '',
        ], $atts);
        $id = (int) $atts['id'];
        if ($id <= 0) {
            $loop_id = get_the_ID();
            if ($loop_id && is_numeric($loop_id)) {
                $id = (int) $loop_id;
            }
        }
        if ($id <= 0 || get_post_type($id) !== 'store_product') {
            return '';
        }
        $product = $this->product_payload($id);
        if ($product === null) {
            return '';
        }
        $content = get_post_field('post_content', $id);
        $content = apply_filters('the_content', $content);
        return ProductRenderer::render_single((int) $id, [
            'content' => $content,
            'hide' => (string) $atts['hide'],
        ]);
    }


    public function render_add_to_cart($atts = [])
    {
        wp_enqueue_script('alpinejs');
        $raw_atts = is_array($atts) ? $atts : [];
        $atts = shortcode_atts([
            'id' => 0,
            'label' => '+',
            'text' => '',
            'class' => 'wps-btn wps-btn-primary',
            'qty' => 0
        ], $atts);
        $btn_class = $atts['class'] ?? 'wps-btn wps-btn-primary';
        $id = $this->resolve_product_id((int) $atts['id']);
        if ($id > 0 && get_post_type($id) !== 'store_product') {
            return '';
        }
        if ($id <= 0) {
            return '';
        }
        $product = $this->product_payload($id);
        if ($product === null) {
            return '';
        }
        $is_purchasable = ProductData::is_purchasable((int) $id);
        $basic_name = $product['variant_name'];
        $basic_values = $product['variant_options'];
        $adv_name = $product['price_adjustment_name'];
        $adv_values = $product['price_adjustment_options'];
        $nonce = wp_create_nonce('wp_rest');
        if (array_key_exists('text', $raw_atts)) {
            $label = (string) $atts['text'];
        } else {
            $label = (is_string($atts['text']) && $atts['text'] !== '') ? $atts['text'] : $atts['label'];
        }
        $wantQty = false;
        if (is_bool($atts['qty'])) {
            $wantQty = $atts['qty'];
        } else {
            $wantQty = in_array(strtolower((string) $atts['qty']), ['1', 'true', 'yes'], true);
        }
        $default_qty = max(1, (int) $product['min_order']);
        if (!$is_purchasable) {
            $label = (string) apply_filters('wp_store_not_purchasable_button_text', 'Hubungi Admin', $id, $atts);
            $wantQty = false;
        }
        return Template::render('components/add-to-cart', [
            'btn_class' => $btn_class,
            'id' => $id,
            'label' => $label,
            'basic_name' => $basic_name ?: '',
            'basic_values' => (is_array($basic_values) ? array_values($basic_values) : []),
            'adv_name' => $adv_name ?: '',
            'adv_values' => (is_array($adv_values) ? array_values($adv_values) : []),
            'base_price' => isset($product['price']) && is_numeric($product['price']) ? (float) $product['price'] : 0.0,
            'nonce' => $nonce,
            'show_qty' => $wantQty,
            'default_qty' => $default_qty,
            'is_purchasable' => $is_purchasable,
        ]);
    }

    public function render_detail($atts = [])
    {
        $atts = shortcode_atts([
            'id' => 0,
            'text' => 'Detail',
            'size' => '',
            'class' => 'wps-btn wps-btn-secondary wps-w-full'
        ], $atts);
        $size = sanitize_key($atts['size']);
        $base_class = 'wps-btn wps-btn-secondary';
        $extra_class = is_string($atts['class']) ? trim($atts['class']) : '';
        $btn_class = trim($base_class . ($size === 'sm' ? ' wps-btn-sm' : '') . ($extra_class ? ' ' . $extra_class : ''));
        $id = $this->resolve_product_id((int) $atts['id']);
        if ($id <= 0 || get_post_type($id) !== 'store_product') {
            return '';
        }
        $link = get_permalink($id);
        $text = (string) $atts['text'];
        return '<a href="' . esc_url($link) . '" class="' . esc_attr($btn_class) . '">' . \wps_icon(['name' => 'eye', 'size' => 16, 'class' => 'wps-mr-2']) . esc_html($text !== '' ? $text : 'Detail') . '</a>';
    }

    public function render_cart_widget($atts = [])
    {
        wp_enqueue_script('alpinejs');
        $atts = shortcode_atts([
            'size' => 16,
        ], $atts);
        $icon_size = (int) $atts['size'];
        if ($icon_size <= 0) {
            $icon_size = 16;
        }
        $icon_size = max(10, min(96, $icon_size));

        $settings = get_option('wp_store_settings', []);
        $checkout_page_id = isset($settings['page_checkout']) ? absint($settings['page_checkout']) : 0;
        $checkout_url = $checkout_page_id ? get_permalink($checkout_page_id) : '';
        $cart_page_id = isset($settings['page_cart']) ? absint($settings['page_cart']) : 0;
        $cart_url = $cart_page_id ? get_permalink($cart_page_id) : site_url('/keranjang/');
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $nonce = wp_create_nonce('wp_rest');
        return Template::render('components/cart-widget', [
            'checkout_url' => $checkout_url,
            'cart_url' => $cart_url,
            'currency' => $currency,
            'nonce' => $nonce,
            'icon_size' => $icon_size,
        ]);
    }

    public function render_cart_page($atts = [])
    {
        wp_enqueue_script('alpinejs');
        wp_enqueue_script('wp-store-frontend');
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        return Template::render('pages/cart', [
            'currency' => $currency
        ]);
    }

    public function filter_cart_page_content($content)
    {
        if (!is_singular('page') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $page_id = get_queried_object_id();
        if (!$page_id) {
            return $content;
        }
        $settings = get_option('wp_store_settings', []);
        $cart_page_id = isset($settings['page_cart']) ? absint($settings['page_cart']) : 0;
        if ($cart_page_id && $page_id === $cart_page_id) {
            wp_enqueue_script('alpinejs');
            wp_enqueue_script('wp-store-frontend');
            $currency = ($settings['currency_symbol'] ?? 'Rp');
            return Template::render('pages/cart', [
                'currency' => $currency
            ]);
        }
        return $content;
    }

    public function render_wishlist($atts = [])
    {
        wp_enqueue_script('alpinejs');
        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $nonce = wp_create_nonce('wp_rest');
        return Template::render('components/wishlist-widget', [
            'currency' => $currency,
            'nonce' => $nonce
        ]);
    }

    public function render_add_to_wishlist($atts = [])
    {
        wp_enqueue_script('alpinejs');
        $atts = shortcode_atts([
            'id' => 0,
            'size' => '',
            'label_add' => 'Wishlist',
            'label_remove' => 'Hapus',
            'icon_only' => '0',
        ], $atts);
        $size = sanitize_key($atts['size']);
        $btn_class = 'wps-btn wps-btn-secondary' . ($size === 'sm' ? ' wps-btn-sm' : '');
        $icon_only = (string) $atts['icon_only'] === '1';
        $id = (int) $atts['id'];
        if ($id <= 0) {
            $loop_id = get_the_ID();
            if ($loop_id && is_numeric($loop_id)) {
                $id = (int) $loop_id;
            }
        }
        if ($id > 0 && get_post_type($id) !== 'store_product') {
            return '';
        }
        if ($id <= 0) {
            return '';
        }
        $nonce = wp_create_nonce('wp_rest');
        return Template::render('components/add-to-wishlist', [
            'btn_class' => $btn_class,
            'id' => $id,
            'label_add' => $atts['label_add'],
            'label_remove' => $atts['label_remove'],
            'icon_only' => $icon_only,
            'nonce' => $nonce
        ]);
    }

    public function render_link_profile($atts = [])
    {
        $atts = shortcode_atts([
            'size' => 32,
        ], $atts);
        $image_size = (int) $atts['size'];
        if ($image_size <= 0) {
            $image_size = 32;
        }
        $image_size = max(16, min(160, $image_size));

        $settings = get_option('wp_store_settings', []);
        $pid = isset($settings['page_profile']) ? absint($settings['page_profile']) : 0;
        $profile_url = $pid ? get_permalink($pid) : site_url('/profil-saya/');
        $avatar_url = '';
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $aid = (int) get_user_meta($uid, '_store_avatar_id', true);
            $avatar_url = $aid ? wp_get_attachment_image_url($aid, 'thumbnail') : '';
            if (!$avatar_url && function_exists('get_avatar_url')) {
                $avatar_url = get_avatar_url($uid);
            }
        }
        if (!$avatar_url) {
            $avatar_url = WP_STORE_URL . 'assets/frontend/img/user.png';
        }
        $html = '<a href="' . esc_url($profile_url) . '" class="wps-link-profile" style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;">'
            . '<img src="' . esc_url($avatar_url) . '" alt="Profil" style="width:' . esc_attr((string) $image_size) . 'px;height:' . esc_attr((string) $image_size) . 'px;border-radius:9999px;object-fit:cover;border:1px solid #e5e7eb;" />'
            . '</a>';
        return $html;
    }

    public function override_archive_template($template)
    {
        if (is_singular('store_product')) {
            $tpl = Template::locate('single-store_product');
            if ($tpl !== '') {
                return $tpl;
            }
        }
        if (is_tax('store_product_cat')) {
            $tpl = Template::locate('taxonomy-store_product_cat');
            if ($tpl !== '') {
                return $tpl;
            }
        }
        if (is_tax('brand')) {
            $tpl = Template::locate('taxonomy-brand');
            if ($tpl !== '') {
                return $tpl;
            }
        }
        if (is_post_type_archive('store_product') || (get_query_var('post_type') === 'store_product' && !is_singular() && !is_tax())) {
            $tpl = Template::locate('archive-store_product');
            if ($tpl !== '') {
                return $tpl;
            }
        }
        return $template;
    }

    public function adjust_archive_query($query)
    {
        if (is_admin()) return;
        if (!$query->is_main_query()) return;
        if ($query->is_post_type_archive('store_product') || $query->is_tax(['store_product_cat', 'brand']) || ($query->get('post_type') === 'store_product' && !$query->is_singular() && !$query->is_tax())) {
            $filters = ProductFilterRequest::from_globals();
            if ($query->is_tax('store_product_cat')) {
                $term = get_queried_object();
                if ($term && isset($term->term_id)) {
                    $filters['cats'] = array_values(array_unique(array_merge(
                        [(int) $term->term_id],
                        (array) ($filters['cats'] ?? [])
                    )));
                }
            }
            if ($query->is_tax('brand')) {
                $term = get_queried_object();
                if ($term && isset($term->term_id)) {
                    $filters['brands'] = array_values(array_unique(array_merge(
                        [(int) $term->term_id],
                        (array) ($filters['brands'] ?? [])
                    )));
                }
            }

            ProductQuery::apply_to_query($query, $filters, [
                'post_type' => 'store_product',
                'post_status' => 'publish',
                'ignore_sticky_posts' => true,
            ]);
        }
    }

    public function output_product_archive_canonical()
    {
        if (!is_post_type_archive('store_product') && !is_tax(['store_product_cat', 'brand'])) {
            return;
        }

        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION')) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        if ($home_path !== '' && $home_path !== '/' && strpos($path, $home_path) === 0) {
            $path = substr($path, strlen($home_path));
        }
        $canonical = $path !== '' ? home_url('/' . ltrim($path, '/')) : '';
        if ($canonical === '') {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }

    public function filter_beaver_builder_query_args($args)
    {
        if (!is_array($args)) {
            return $args;
        }

        $post_type = $args['post_type'] ?? '';
        $post_types = is_array($post_type) ? $post_type : [$post_type];
        if (!in_array('store_product', $post_types, true)) {
            return $args;
        }

        return ProductQuery::apply_to_args($args, ProductFilterRequest::from_globals());
    }

    public function redirect_page_conflict()
    {
        if (is_admin()) return;
        if (is_page()) {
            $page = get_queried_object();
            if ($page && isset($page->post_name) && $page->post_name === 'produk') {
                $n = (int) get_query_var('paged');
                if ($n <= 0) {
                    $n = (int) get_query_var('page');
                }
                $base = get_post_type_archive_link('store_product');
                $produk_page = function_exists('get_page_by_path') ? get_page_by_path('produk') : null;
                if ($produk_page && is_a($produk_page, '\WP_Post') && $base) {
                    if (rtrim($base, '/') === rtrim(home_url('/produk/'), '/')) {
                        $base = home_url('/produk-list/');
                    }
                }
                $target = $base;
                if ($base && $n > 1) {
                    $target = trailingslashit($base) . 'page/' . $n . '/';
                }
                $current = home_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/');
                if ($target && rtrim($target, '/') !== rtrim($current, '/')) {
                    wp_redirect($target, 301);
                    exit;
                }
            }
        }
        if (is_404()) {
            $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
            if (strpos($uri, '/produk/') === 0) {
                $n = 0;
                if (preg_match('#/page/(\d+)/#', $uri, $m)) {
                    $n = (int) ($m[1] ?? 0);
                }
                $base = get_post_type_archive_link('store_product');
                $produk_page = function_exists('get_page_by_path') ? get_page_by_path('produk') : null;
                if ($produk_page && is_a($produk_page, '\WP_Post') && $base) {
                    if (rtrim($base, '/') === rtrim(home_url('/produk/'), '/')) {
                        $base = home_url('/produk-list/');
                    }
                }
                if ($base) {
                    $target = $n > 1 ? trailingslashit($base) . 'page/' . $n . '/' : $base;
                    $current = home_url($uri);
                    if (rtrim($target, '/') !== rtrim($current, '/')) {
                        wp_redirect($target, 301);
                        exit;
                    }
                }
            }
        }
    }

    public function render_couriers($atts = [])
    {
        $atts = shortcode_atts([
            'height' => '30',
            'gap' => '10',
            'class' => '',
        ], $atts);

        $settings = get_option('wp_store_settings', []);
        $active_couriers = $settings['shipping_couriers'] ?? [];

        if (empty($active_couriers)) {
            return '';
        }

        $gap = esc_attr($atts['gap']) . 'px';
        $height = esc_attr($atts['height']) . 'px';
        $class = esc_attr($atts['class']);

        $html = '<div class="wp-store-courier-images ' . $class . '" style="display: flex; flex-wrap: wrap; gap: ' . $gap . '; align-items: center;">';
        foreach ($active_couriers as $code) {
            $image_url = WP_STORE_URL . 'assets/frontend/img/ekspedisi/' . $code . '.webp';
            $html .= sprintf(
                '<img src="%s" alt="%s" style="height: %s; width: auto; object-fit: contain;">',
                esc_url($image_url),
                esc_attr(strtoupper($code)),
                $height
            );
        }
        $html .= '</div>';

        return $html;
    }
}
