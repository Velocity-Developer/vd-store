<?php

namespace WpStore\Api;

use WpStore\Domain\Product\ProductData;

use WP_REST_Request;
use WP_REST_Response;

class SettingsController
{
    public function register_routes()
    {
        register_rest_route('wp-store/v1', '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_auth'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_settings'],
                'permission_callback' => [$this, 'check_admin_auth'],
            ],
        ]);

        register_rest_route('wp-store/v1', '/settings/generate-pages', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'generate_pages'],
                'permission_callback' => [$this, 'check_admin_auth'],
            ],
        ]);
        register_rest_route('wp-store/v1', '/settings/custom-shipping-rates', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_custom_shipping_rates'],
                'permission_callback' => [$this, 'check_admin_auth'],
            ],
        ]);
        register_rest_route('wp-store/v1', '/settings/payment-methods', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_payment_methods'],
                'permission_callback' => [$this, '__return_true'],
            ],
        ]);
        register_rest_route('wp-store/v1', '/settings/page-urls', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_page_urls'],
                'permission_callback' => [$this, '__return_true'],
            ],
        ]);
    }

    public function get_settings(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        return new WP_REST_Response([
            'success' => true,
            'settings' => $settings
        ], 200);
    }

    public function check_admin_auth()
    {
        return current_user_can('manage_options');
    }

    public function __return_true()
    {
        return true;
    }

    public function save_settings(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $settings = get_option('wp_store_settings', []);

        // Use active_tab to determine what to update, or just update everything provided
        // The frontend will send the relevant data.
        // To keep it simple and robust, we can merge existing settings with new ones.

        if (isset($params['store_name'])) $settings['store_name'] = sanitize_text_field($params['store_name']);
        if (isset($params['store_address'])) $settings['store_address'] = sanitize_textarea_field($params['store_address']);
        if (isset($params['store_email'])) $settings['store_email'] = sanitize_email($params['store_email']);
        if (isset($params['store_email_from'])) $settings['store_email_from'] = sanitize_email($params['store_email_from']);
        if (isset($params['store_email_admin'])) $settings['store_email_admin'] = sanitize_email($params['store_email_admin']);
        if (isset($params['store_phone'])) $settings['store_phone'] = sanitize_text_field($params['store_phone']);

        // Handle payment methods
        if (isset($params['payment_methods']) && is_array($params['payment_methods'])) {
            $settings['payment_methods'] = array_map('sanitize_text_field', $params['payment_methods']);
        }

        // Email templates
        if (isset($params['email_template_user_new_order'])) {
            $settings['email_template_user_new_order'] = wp_kses_post($params['email_template_user_new_order']);
        }
        if (isset($params['email_template_user_status'])) {
            $settings['email_template_user_status'] = wp_kses_post($params['email_template_user_status']);
        }
        if (isset($params['email_template_admin_new_order'])) {
            $settings['email_template_admin_new_order'] = wp_kses_post($params['email_template_admin_new_order']);
        }
        if (isset($params['email_template_admin_status'])) {
            $settings['email_template_admin_status'] = wp_kses_post($params['email_template_admin_status']);
        }

        // Handle multiple bank accounts
        if (isset($params['store_bank_accounts']) && is_array($params['store_bank_accounts'])) {
            $bank_accounts = [];
            foreach ($params['store_bank_accounts'] as $account) {
                if (isset($account['bank_name']) && isset($account['bank_account']) && isset($account['bank_holder'])) {
                    $bank_accounts[] = [
                        'bank_name'    => sanitize_text_field($account['bank_name']),
                        'bank_account' => sanitize_text_field($account['bank_account']),
                        'bank_holder'  => sanitize_text_field($account['bank_holder']),
                    ];
                }
            }
            $settings['store_bank_accounts'] = $bank_accounts;
        }

        // Legacy support (optional, but good to keep clean if we are fully migrating)
        if (isset($params['bank_name'])) $settings['bank_name'] = sanitize_text_field($params['bank_name']);
        if (isset($params['bank_account'])) $settings['bank_account'] = sanitize_text_field($params['bank_account']);
        if (isset($params['bank_holder'])) $settings['bank_holder'] = sanitize_text_field($params['bank_holder']);

        if (isset($params['rajaongkir_api_key'])) $settings['rajaongkir_api_key'] = sanitize_text_field($params['rajaongkir_api_key']);

        if (isset($params['shipping_origin_province'])) $settings['shipping_origin_province'] = sanitize_text_field($params['shipping_origin_province']);
        if (isset($params['shipping_origin_city'])) $settings['shipping_origin_city'] = sanitize_text_field($params['shipping_origin_city']);
        if (isset($params['shipping_origin_subdistrict'])) $settings['shipping_origin_subdistrict'] = sanitize_text_field($params['shipping_origin_subdistrict']);

        if (isset($params['shipping_couriers']) && is_array($params['shipping_couriers'])) {
            $settings['shipping_couriers'] = array_map('sanitize_text_field', $params['shipping_couriers']);
        }

        if (isset($params['disable_shipping_for_digital'])) {
            $settings['disable_shipping_for_digital'] = ((string) $params['disable_shipping_for_digital'] === '1') ? 1 : 0;
        }

        if (isset($params['custom_shipping_rates']) && is_array($params['custom_shipping_rates'])) {
            $rates = [];
            foreach ($params['custom_shipping_rates'] as $rate) {
                // Ensure type and id are present. Price defaults to 0 if missing/null.
                if (!empty($rate['type']) && !empty($rate['id'])) {
                    $rates[] = [
                        'type' => sanitize_text_field($rate['type']),
                        'id' => sanitize_text_field($rate['id']),
                        'name' => isset($rate['name']) ? sanitize_text_field($rate['name']) : '',
                        'price' => isset($rate['price']) ? floatval($rate['price']) : 0,
                        'label' => isset($rate['label']) ? sanitize_text_field($rate['label']) : '',
                    ];
                }
            }
            $settings['custom_shipping_rates'] = $rates;
        }

        if (isset($params['page_catalog'])) $settings['page_catalog'] = absint($params['page_catalog']);
        if (isset($params['page_shipping_check'])) $settings['page_shipping_check'] = absint($params['page_shipping_check']);
        if (isset($params['page_profile'])) $settings['page_profile'] = absint($params['page_profile']);
        if (isset($params['page_cart'])) $settings['page_cart'] = absint($params['page_cart']);
        if (isset($params['page_checkout'])) $settings['page_checkout'] = absint($params['page_checkout']);
        if (isset($params['page_thanks'])) $settings['page_thanks'] = absint($params['page_thanks']);
        if (isset($params['page_tracking'])) $settings['page_tracking'] = absint($params['page_tracking']);

        if (isset($params['currency_symbol'])) $settings['currency_symbol'] = sanitize_text_field($params['currency_symbol']);
        if (isset($params['product_editor_mode'])) {
            $mode = sanitize_text_field($params['product_editor_mode']);
            $allowed = ['classic', 'gutenberg', 'fse'];
            if (!in_array($mode, $allowed, true)) {
                $mode = 'classic';
            }
            $settings['product_editor_mode'] = $mode;
        }
        if (isset($params['qris_image_id'])) $settings['qris_image_id'] = absint($params['qris_image_id']);
        if (isset($params['qris_label'])) $settings['qris_label'] = sanitize_text_field($params['qris_label']);
        if (isset($params['recaptcha_site_key'])) $settings['recaptcha_site_key'] = sanitize_text_field($params['recaptcha_site_key']);
        if (isset($params['recaptcha_secret_key'])) $settings['recaptcha_secret_key'] = sanitize_text_field($params['recaptcha_secret_key']);

        // Discounts visibility
        if (isset($params['members_only_discount'])) {
            $val = (string) $params['members_only_discount'];
            $settings['members_only_discount'] = ($val === '1') ? 1 : 0;
        }

        // Theme colors
        if (isset($params['theme_primary'])) {
            $hex = sanitize_hex_color($params['theme_primary']);
            if ($hex) $settings['theme_primary'] = $hex;
        }
        if (isset($params['theme_primary_hover'])) {
            $hex = sanitize_hex_color($params['theme_primary_hover']);
            if ($hex) $settings['theme_primary_hover'] = $hex;
        }
        if (isset($params['theme_secondary_text'])) {
            $hex = sanitize_hex_color($params['theme_secondary_text']);
            if ($hex) $settings['theme_secondary_text'] = $hex;
        }
        if (isset($params['theme_secondary_border'])) {
            $hex = sanitize_hex_color($params['theme_secondary_border']);
            if ($hex) $settings['theme_secondary_border'] = $hex;
        }
        if (isset($params['theme_callout_bg'])) {
            $hex = sanitize_hex_color($params['theme_callout_bg']);
            if ($hex) $settings['theme_callout_bg'] = $hex;
        }
        if (isset($params['theme_callout_border'])) {
            $hex = sanitize_hex_color($params['theme_callout_border']);
            if ($hex) $settings['theme_callout_border'] = $hex;
        }
        if (isset($params['theme_callout_title'])) {
            $hex = sanitize_hex_color($params['theme_callout_title']);
            if ($hex) $settings['theme_callout_title'] = $hex;
        }
        if (isset($params['theme_danger_text'])) {
            $hex = sanitize_hex_color($params['theme_danger_text']);
            if ($hex) $settings['theme_danger_text'] = $hex;
        }
        if (isset($params['theme_danger_border'])) {
            $hex = sanitize_hex_color($params['theme_danger_border']);
            if ($hex) $settings['theme_danger_border'] = $hex;
        }

        // thumbnail size
        if (isset($params['product_thumbnail_width'])) {
            $mw = absint($params['product_thumbnail_width']);
            if ($mw > 0) {
                $settings['product_thumbnail_width'] = $mw;
            }
        }

        if (isset($params['product_thumbnail_height'])) {
            $mh = absint($params['product_thumbnail_height']);
            if ($mh > 0) {
                $settings['product_thumbnail_height'] = $mh;
            }
        }


        // Layout
        if (isset($params['container_max_width'])) {
            $mw = absint($params['container_max_width']);
            if ($mw > 0) {
                $settings['container_max_width'] = $mw;
            }
        }

        $settings = apply_filters('wp_store_save_settings', $settings, $params);

        update_option('wp_store_settings', $settings);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Pengaturan berhasil disimpan',
            'settings' => $settings
        ], 200);
    }

    public function save_custom_shipping_rates(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $settings = get_option('wp_store_settings', []);
        $rates = [];
        if (isset($params['custom_shipping_rates']) && is_array($params['custom_shipping_rates'])) {
            foreach ($params['custom_shipping_rates'] as $rate) {
                if (!empty($rate['type']) && !empty($rate['id'])) {
                    $rates[] = [
                        'type' => sanitize_text_field($rate['type']),
                        'id' => sanitize_text_field($rate['id']),
                        'name' => isset($rate['name']) ? sanitize_text_field($rate['name']) : '',
                        'price' => isset($rate['price']) ? floatval($rate['price']) : 0,
                        'label' => isset($rate['label']) ? sanitize_text_field($rate['label']) : '',
                    ];
                }
            }
        }
        $settings['custom_shipping_rates'] = $rates;
        update_option('wp_store_settings', $settings);
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Tarif custom disimpan',
            'settings' => ['custom_shipping_rates' => $rates]
        ], 200);
    }

    public function generate_pages()
    {
        $settings = get_option('wp_store_settings', []);
        $pages_to_create = [
            'page_catalog' => [
                'title' => 'Katalog',
                'content' => '[wp_store_catalog]'
            ],
            'page_shipping_check' => [
                'title' => 'Cek Ongkir',
                'content' => '[wp_store_shipping_checker]'
            ],
            'page_profile' => [
                'title' => 'Profil Saya',
                'content' => '[wp_store_profile]'
            ],
            'page_cart' => [
                'title' => 'Keranjang',
                'content' => '[store_cart]'
            ],
            'page_checkout' => [
                'title' => 'Checkout',
                'content' => '[store_checkout]'
            ],
            'page_thanks' => [
                'title' => 'Terima Kasih',
                'content' => '[store_thanks]'
            ],
            'page_tracking' => [
                'title' => 'Tracking Order',
                'content' => '[store_tracking]'
            ],
        ];

        $created_pages = [];

        foreach ($pages_to_create as $key => $page_data) {
            // Check if page already exists in settings
            if (!empty($settings[$key])) {
                $existing_page = get_post($settings[$key]);
                if ($existing_page && $existing_page->post_status !== 'trash') {
                    continue;
                }
            }

            // Create page
            $page_id = wp_insert_post([
                'post_title'   => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);

            if ($page_id && !is_wp_error($page_id)) {
                $settings[$key] = $page_id;
                $created_pages[] = $page_data['title'];
            }
        }

        update_option('wp_store_settings', $settings);

        return new WP_REST_Response([
            'success' => true,
            'message' => count($created_pages) > 0
                ? 'Halaman berhasil dibuat: ' . implode(', ', $created_pages)
                : 'Semua halaman sudah ada.',
            'settings' => $settings
        ], 200);
    }

    private function get_rajaongkir_base_url()
    {
        return \WpStore\Api\RajaOngkirController::get_rajaongkir_base_url();
    }

    private function get_cart_total_weight_grams()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'store_carts';
        $cookie_key = 'wp_store_cart_key';
        $cart = [];
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $row = $wpdb->get_row($wpdb->prepare("SELECT cart FROM {$table} WHERE user_id = %d LIMIT 1", $user_id));
            if ($row && isset($row->cart)) {
                $data = json_decode($row->cart, true);
                $cart = is_array($data) ? $data : [];
            }
        } else {
            $key = isset($_COOKIE[$cookie_key]) && is_string($_COOKIE[$cookie_key]) && $_COOKIE[$cookie_key] !== '' ? sanitize_key($_COOKIE[$cookie_key]) : '';
            if ($key) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT cart FROM {$table} WHERE guest_key = %s LIMIT 1", $key));
                if ($row && isset($row->cart)) {
                    $data = json_decode($row->cart, true);
                    $cart = is_array($data) ? $data : [];
                }
            }
        }
        $total = 0;
        foreach ($cart as $row) {
            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            if ($product_id <= 0 || $qty <= 0 || get_post_type($product_id) !== 'store_product') {
                continue;
            }
            $grams = ProductData::weight_grams($product_id, 1);
            $total += $grams * $qty;
        }
        if ($total < 1) {
            $total = 1;
        }
        return $total;
    }

    public function calculate_rajaongkir_cost(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $api_key = $settings['rajaongkir_api_key'] ?? '';
        $origin_subdistrict = isset($settings['shipping_origin_subdistrict']) ? (string) $settings['shipping_origin_subdistrict'] : '';
        $params = $request->get_json_params();
        $destination_subdistrict = isset($params['destination_subdistrict']) ? sanitize_text_field($params['destination_subdistrict']) : '';
        $courier = isset($params['courier']) ? sanitize_text_field($params['courier']) : '';
        if (empty($api_key) || empty($origin_subdistrict) || empty($destination_subdistrict) || empty($courier)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Pengaturan atau parameter tidak lengkap.'
            ], 400);
        }
        $weight = $this->get_cart_total_weight_grams();
        $cache_key = 'wp_store_rajaongkir_cost_' . md5(implode('|', [$origin_subdistrict, $destination_subdistrict, $weight, $courier]));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }
        $url = $this->get_rajaongkir_base_url() . '/calculate/domestic-cost';
        $body = [
            'origin' => $origin_subdistrict,
            'destination' => $destination_subdistrict,
            'weight' => $weight,
            'courier' => $courier,
            'price' => 'lowest'
        ];
        $response = wp_remote_post($url, [
            'headers' => [
                'key' => $api_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => $body
        ]);
        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $response->get_error_message()
            ], 500);
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $services = [];
        if (isset($data['data'])) {
            $d = $data['data'];
            if (isset($d['couriers']) && is_array($d['couriers'])) {
                foreach ($d['couriers'] as $cg) {
                    $code = isset($cg['code']) ? (string) $cg['code'] : (isset($cg['courier']['code']) ? (string) $cg['courier']['code'] : '');
                    $list = isset($cg['services']) && is_array($cg['services']) ? $cg['services'] : [];
                    foreach ($list as $row) {
                        $services[] = [
                            'courier' => $code,
                            'service' => isset($row['service']) ? (string) $row['service'] : (isset($row['service_code']) ? (string) $row['service_code'] : ''),
                            'description' => isset($row['description']) ? (string) $row['description'] : (isset($row['service_name']) ? (string) $row['service_name'] : ''),
                            'cost' => isset($row['cost']) ? (float) $row['cost'] : (isset($row['value']) ? (float) $row['value'] : 0),
                            'etd' => isset($row['etd']) ? (string) $row['etd'] : (isset($row['etd_days']) ? (string) $row['etd_days'] : '')
                        ];
                    }
                }
            } elseif (is_array($d)) {
                foreach ($d as $row) {
                    if (isset($row['services']) && is_array($row['services'])) {
                        $code = isset($row['courier']) ? (string) $row['courier'] : (isset($row['code']) ? (string) $row['code'] : '');
                        foreach ($row['services'] as $s) {
                            $services[] = [
                                'courier' => $code,
                                'service' => isset($s['service']) ? (string) $s['service'] : (isset($s['service_code']) ? (string) $s['service_code'] : ''),
                                'description' => isset($s['description']) ? (string) $s['description'] : (isset($s['service_name']) ? (string) $s['service_name'] : ''),
                                'cost' => isset($s['cost']) ? (float) $s['cost'] : (isset($s['value']) ? (float) $s['value'] : 0),
                                'etd' => isset($s['etd']) ? (string) $s['etd'] : (isset($s['etd_days']) ? (string) $s['etd_days'] : '')
                            ];
                        }
                    } else {
                        $services[] = [
                            'courier' => isset($row['courier']) ? (string) $row['courier'] : '',
                            'service' => isset($row['service']) ? (string) $row['service'] : (isset($row['service_code']) ? (string) $row['service_code'] : ''),
                            'description' => isset($row['description']) ? (string) $row['description'] : (isset($row['service_name']) ? (string) $row['service_name'] : ''),
                            'cost' => isset($row['cost']) ? (float) $row['cost'] : (isset($row['value']) ? (float) $row['value'] : 0),
                            'etd' => isset($row['etd']) ? (string) $row['etd'] : (isset($row['etd_days']) ? (string) $row['etd_days'] : '')
                        ];
                    }
                }
            }
        }
        $chosen = null;
        foreach ($services as $s) {
            if ($s['cost'] > 0) {
                $chosen = $s;
                break;
            }
        }
        if (!$chosen && !empty($services)) {
            $chosen = $services[0];
        }
        $payload = [
            'success' => true,
            'weight' => $weight,
            'services' => $services
        ];
        set_transient($cache_key, $payload, DAY_IN_SECONDS);
        return new WP_REST_Response($payload, 200);
    }

    public function get_rajaongkir_provinces(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $api_key = $settings['rajaongkir_api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Key VD Ongkir belum diatur.'
            ], 400);
        }

        $cache_key = 'wp_store_rajaongkir_provinces';
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $cached_data
            ], 200);
        }

        $url = $this->get_rajaongkir_base_url() . '/destination/province';

        $response = wp_remote_get($url, [
            'headers' => ['key' => $api_key]
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $response->get_error_message()
            ], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Komerce response structure: { meta: {...}, data: [ {id, name}, ... ] }
        if (isset($data['data'])) {
            // Map data to match RajaOngkir standard format expected by frontend
            $provinces = array_map(function ($item) {
                return [
                    'province_id' => $item['id'],
                    'province'    => $item['name']
                ];
            }, $data['data']);

            set_transient($cache_key, $provinces, DAY_IN_SECONDS);

            return new WP_REST_Response([
                'success' => true,
                'data' => $provinces
            ], 200);
        }

        // Fallback or legacy structure check
        if (isset($data['rajaongkir']['results'])) {
            set_transient($cache_key, $data['rajaongkir']['results'], DAY_IN_SECONDS);
            return new WP_REST_Response([
                'success' => true,
                'data' => $data['rajaongkir']['results']
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Gagal mengambil data provinsi.',
            'raw' => $data
        ], 500);
    }

    public function get_rajaongkir_cities(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $api_key = $settings['rajaongkir_api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Key VD Ongkir belum diatur.'
            ], 400);
        }

        $province = $request->get_param('province');
        // If province is not provided, we might want to return all cities or error.
        // The Komerce endpoint seems to require province_id in the path.
        if (!$province) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Parameter province ID diperlukan.'
            ], 400);
        }

        $cache_key = 'wp_store_rajaongkir_cities_' . $province;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $cached_data
            ], 200);
        }

        $url = $this->get_rajaongkir_base_url() . "/destination/city/{$province}";

        $response = wp_remote_get($url, [
            'headers' => ['key' => $api_key]
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $response->get_error_message()
            ], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Komerce structure: { meta: {...}, data: [...] }
        if (isset($data['data'])) {
            // Map data to match RajaOngkir standard format expected by frontend
            $cities = array_map(function ($item) {
                return [
                    'city_id'   => $item['id'],
                    'city_name' => $item['name'],
                    'type'      => '', // Komerce doesn't seem to provide type (Kota/Kabupaten) in this response
                    'province'  => '', // Komerce doesn't provide province name in this response
                    'postal_code' => $item['zip_code'] ?? ''
                ];
            }, $data['data']);

            set_transient($cache_key, $cities, DAY_IN_SECONDS);

            return new WP_REST_Response([
                'success' => true,
                'data' => $cities
            ], 200);
        }

        // Fallback to standard RajaOngkir structure check just in case
        if (isset($data['rajaongkir']['results'])) {
            set_transient($cache_key, $data['rajaongkir']['results'], DAY_IN_SECONDS);
            return new WP_REST_Response([
                'success' => true,
                'data' => $data['rajaongkir']['results']
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Gagal mengambil data kota.',
            'raw' => $data
        ], 500);
    }

    public function get_rajaongkir_subdistricts(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $api_key = $settings['rajaongkir_api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Key VD Ongkir belum diatur.'
            ], 400);
        }

        $city = $request->get_param('city');

        if (!$city) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Parameter city ID diperlukan.'
            ], 400);
        }

        $cache_key = 'wp_store_rajaongkir_subdistricts_' . $city;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $cached_data
            ], 200);
        }

        $url = $this->get_rajaongkir_base_url() . "/destination/district/{$city}";

        $response = wp_remote_get($url, [
            'headers' => ['key' => $api_key]
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $response->get_error_message()
            ], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Komerce structure: { meta: {...}, data: [...] }
        if (isset($data['data'])) {
            // Map data to match RajaOngkir standard format expected by frontend
            $subdistricts = array_map(function ($item) {
                return [
                    'subdistrict_id'   => $item['id'],
                    'subdistrict_name' => $item['name']
                ];
            }, $data['data']);

            set_transient($cache_key, $subdistricts, DAY_IN_SECONDS);

            return new WP_REST_Response([
                'success' => true,
                'data' => $subdistricts
            ], 200);
        }

        // Fallback to standard RajaOngkir structure check
        if (isset($data['rajaongkir']['results'])) {
            set_transient($cache_key, $data['rajaongkir']['results'], DAY_IN_SECONDS);
            return new WP_REST_Response([
                'success' => true,
                'data' => $data['rajaongkir']['results']
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Gagal mengambil data kecamatan.',
            'raw' => $data
        ], 500);
    }
    public function get_payment_methods(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $payment_methods = $settings['payment_methods'] ?? [];

        $payment_methods = array_map(function ($method) {
            $nama = str_replace('_', ' ', $method);
            $nama = ucwords($nama);
            return ['id' => $method, 'name' => $nama];
        }, $payment_methods);
        return new WP_REST_Response([
            'success' => true,
            'data' => $payment_methods
        ], 200);
    }
    public function get_page_urls(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $page = [];
        $page['page_cart'] = $settings['page_cart'] ? get_permalink($settings['page_cart']) : '';
        $page['page_checkout'] = $settings['page_checkout'] ? get_permalink($settings['page_checkout']) : '';
        return new WP_REST_Response([
            'success' => true,
            'data' => $page
        ], 200);
    }
}
