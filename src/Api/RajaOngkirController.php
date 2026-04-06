<?php

namespace WpStore\Api;

use WpStore\Domain\Product\ProductData;

use WP_REST_Request;
use WP_REST_Response;

class RajaOngkirController
{
    public function register_routes()
    {
        register_rest_route('wp-store/v1', '/rajaongkir/provinces', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_rajaongkir_provinces'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('wp-store/v1', '/rajaongkir/cities', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_rajaongkir_cities'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('wp-store/v1', '/rajaongkir/subdistricts', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_rajaongkir_subdistricts'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('wp-store/v1', '/rajaongkir/calculate', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'calculate_rajaongkir_cost'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'calculate_rajaongkir_cost'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('wp-store/v1', '/rajaongkir/waybill', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_rajaongkir_waybill'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function get_rajaongkir_base_url()
    {
        $base_url = 'https://ongkir.velocitydeveloper.id/api/v3';
        return $base_url;
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
        $disable_shipping_for_digital = !empty($settings['disable_shipping_for_digital']);
        $api_key = $settings['rajaongkir_api_key'] ?? '';
        $origin_subdistrict = isset($settings['shipping_origin_subdistrict']) ? (string) $settings['shipping_origin_subdistrict'] : '';
        $params = $request->get_json_params();
        if (!is_array($params) || empty($params)) {
            $params = [
                'destination_subdistrict' => $request->get_param('destination_subdistrict'),
                'destination_city' => $request->get_param('destination_city'),
                'destination_province' => $request->get_param('destination_province'),
                'courier' => $request->get_param('courier'),
                'manual_weight_grams' => $request->get_param('manual_weight_grams'),
            ];
        }
        $params = apply_filters('wp_store_before_calculate_shipping', $params, $request);
        if ($disable_shipping_for_digital) {
            $items = isset($params['items']) && is_array($params['items']) ? $params['items'] : null;
            $all_digital = null;
            if (is_array($items)) {
                $all_digital = true;
                foreach ($items as $it) {
                    $pid = isset($it['id']) ? (int) $it['id'] : 0;
                    if ($pid > 0 && get_post_type($pid) === 'store_product') {
                        $is_digital = ProductData::is_digital($pid);
                        if (!$is_digital) {
                            $all_digital = false;
                            break;
                        }
                    }
                }
            }
            if ($all_digital === true) {
                return new WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'meta' => [
                            'message' => 'No shipping required for digital items',
                            'code' => 200,
                            'status' => 'success'
                        ],
                        'data' => []
                    ]
                ], 200);
            }
        }
        $destination_subdistrict = isset($params['destination_subdistrict']) ? sanitize_text_field($params['destination_subdistrict']) : '';
        $destination_city = isset($params['destination_city']) ? sanitize_text_field($params['destination_city']) : '';
        $destination_province = isset($params['destination_province']) ? sanitize_text_field($params['destination_province']) : '';
        $courier = isset($params['courier']) ? sanitize_text_field($params['courier']) : '';

        // Custom Rates
        $custom_services = [];
        $custom_rates = isset($settings['custom_shipping_rates']) && is_array($settings['custom_shipping_rates']) ? $settings['custom_shipping_rates'] : [];
        foreach ($custom_rates as $rate) {
            $type = $rate['type'] ?? '';
            $id = $rate['id'] ?? '';
            $match = false;
            if ($type === 'subdistrict' && (string)$id === (string)$destination_subdistrict) $match = true;
            elseif ($type === 'city' && (string)$id === (string)$destination_city) $match = true;
            elseif ($type === 'province' && (string)$id === (string)$destination_province) $match = true;

            if ($match) {
                $custom_services[] = [
                    'courier' => 'custom',
                    'service' => isset($rate['label']) && $rate['label'] ? $rate['label'] : 'Custom',
                    'description' => isset($rate['name']) && $rate['name'] ? $rate['name'] : 'Shipping',
                    'cost' => (float)($rate['price'] ?? 0),
                    'etd' => ''
                ];
            }
        }

        $items = isset($params['items']) && is_array($params['items']) ? $params['items'] : null;
        $manual_weight = isset($params['manual_weight_grams']) ? (int) $params['manual_weight_grams'] : 0;
        if ($manual_weight < 0) $manual_weight = 0;
        $weight_base = $manual_weight > 0 ? $manual_weight : ($items ? $this->get_items_total_weight_grams($items) : $this->get_cart_total_weight_grams());
        $weight = apply_filters('wp_store_shipping_weight', $weight_base, $params);

        if (empty($api_key) || empty($origin_subdistrict) || empty($destination_subdistrict) || empty($courier)) {
            if (!empty($custom_services)) {
                $mapped = array_map(function ($s) {
                    return [
                        'name' => 'Custom Rate',
                        'code' => isset($s['courier']) ? (string) $s['courier'] : 'custom',
                        'service' => isset($s['service']) ? (string) $s['service'] : '',
                        'description' => isset($s['description']) ? (string) $s['description'] : '',
                        'cost' => isset($s['cost']) ? (float) $s['cost'] : 0,
                        'etd' => isset($s['etd']) ? (string) $s['etd'] : ''
                    ];
                }, $custom_services);
                return new WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'meta' => [
                            'message' => 'Success Calculate Cost',
                            'code' => 200,
                            'status' => 'success'
                        ],
                        'data' => $mapped
                    ]
                ], 200);
            }
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Pengaturan atau parameter tidak lengkap.'
            ], 400);
        }

        $cache_key = apply_filters('wp_store_shipping_cache_key', 'wp_store_rajaongkir_cost_v2_' . md5(implode('|', [$origin_subdistrict, $destination_subdistrict, $weight, $courier, serialize($custom_services)])), $params);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }
        $url = self::get_rajaongkir_base_url() . '/calculate/domestic-cost';
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
                    $list = isset($cg['services']) && is_array($cg['services']) ? $cg['services'] : [];
                    foreach ($list as $row) {
                        $services[] = [
                            'courier' => isset($row['code']) ? (string) $row['code'] : (isset($cg['code']) ? (string) $cg['code'] : ''),
                            'name' => isset($cg['name']) ? (string) $cg['name'] : $this->map_courier_name(isset($row['code']) ? (string) $row['code'] : ''),
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
                        foreach ($row['services'] as $s) {
                            $services[] = [
                                'courier' => isset($s['code']) ? (string) $s['code'] : (isset($row['courier']) ? (string) $row['courier'] : (isset($row['code']) ? (string) $row['code'] : '')),
                                'name' => isset($row['name']) ? (string) $row['name'] : $this->map_courier_name(isset($row['courier']) ? (string) $row['courier'] : (isset($row['code']) ? (string) $row['code'] : '')),
                                'service' => isset($s['service']) ? (string) $s['service'] : (isset($s['service_code']) ? (string) $s['service_code'] : ''),
                                'description' => isset($s['description']) ? (string) $s['description'] : (isset($s['service_name']) ? (string) $s['service_name'] : ''),
                                'cost' => isset($s['cost']) ? (float) $s['cost'] : (isset($s['value']) ? (float) $s['value'] : 0),
                                'etd' => isset($s['etd']) ? (string) $s['etd'] : (isset($s['etd_days']) ? (string) $s['etd_days'] : '')
                            ];
                        }
                    } else {
                        $services[] = [
                            'courier' => isset($row['courier']) ? (string) $row['courier'] : (isset($row['code']) ? (string) $row['code'] : ''),
                            'name' => isset($row['name']) ? (string) $row['name'] : $this->map_courier_name(isset($row['courier']) ? (string) $row['courier'] : (isset($row['code']) ? (string) $row['code'] : '')),
                            'service' => isset($row['service']) ? (string) $row['service'] : (isset($row['service_code']) ? (string) $row['service_code'] : ''),
                            'description' => isset($row['description']) ? (string) $row['description'] : (isset($row['service_name']) ? (string) $row['service_name'] : ''),
                            'cost' => isset($row['cost']) ? (float) $row['cost'] : (isset($row['value']) ? (float) $row['value'] : 0),
                            'etd' => isset($row['etd']) ? (string) $row['etd'] : (isset($row['etd_days']) ? (string) $row['etd_days'] : '')
                        ];
                    }
                }
            }
        }
        if (empty($services)) {
            $codes = preg_split('/[:,]+/', (string) $courier);
            $codes = array_values(array_filter(array_map('trim', $codes)));
            foreach ($codes as $c) {
                $single = [
                    'origin' => $origin_subdistrict,
                    'destination' => $destination_subdistrict,
                    'weight' => $weight,
                    'courier' => $c,
                    'price' => 'lowest'
                ];
                $resp = wp_remote_post($url, [
                    'headers' => [
                        'key' => $api_key,
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'body' => $single
                ]);
                if (is_wp_error($resp)) {
                    continue;
                }
                $b2 = wp_remote_retrieve_body($resp);
                $d2 = json_decode($b2, true);
                if (isset($d2['data'])) {
                    $dd = $d2['data'];
                    if (isset($dd['couriers']) && is_array($dd['couriers'])) {
                        foreach ($dd['couriers'] as $cg) {
                            $list = isset($cg['services']) && is_array($cg['services']) ? $cg['services'] : [];
                            foreach ($list as $row) {
                                $services[] = [
                                    'courier' => isset($row['code']) ? (string) $row['code'] : '',
                                    'service' => isset($row['service']) ? (string) $row['service'] : (isset($row['service_code']) ? (string) $row['service_code'] : ''),
                                    'description' => isset($row['description']) ? (string) $row['description'] : (isset($row['service_name']) ? (string) $row['service_name'] : ''),
                                    'cost' => isset($row['cost']) ? (float) $row['cost'] : (isset($row['value']) ? (float) $row['value'] : 0),
                                    'etd' => isset($row['etd']) ? (string) $row['etd'] : (isset($row['etd_days']) ? (string) $row['etd_days'] : '')
                                ];
                            }
                        }
                    } elseif (is_array($dd)) {
                        foreach ($dd as $row) {
                            if (isset($row['services']) && is_array($row['services'])) {
                                foreach ($row['services'] as $s) {
                                    $services[] = [
                                        'courier' => isset($row['code']) ? (string) $row['code'] : '',
                                        'service' => isset($s['service']) ? (string) $s['service'] : (isset($s['service_code']) ? (string) $s['service_code'] : ''),
                                        'description' => isset($s['description']) ? (string) $s['description'] : (isset($s['service_name']) ? (string) $s['service_name'] : ''),
                                        'cost' => isset($s['cost']) ? (float) $s['cost'] : (isset($s['value']) ? (float) $s['value'] : 0),
                                        'etd' => isset($s['etd']) ? (string) $s['etd'] : (isset($s['etd_days']) ? (string) $s['etd_days'] : '')
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
        $services = array_merge($custom_services, $services);
        $services = apply_filters('wp_store_shipping_services', $services, $params);
        $mapped = array_map(function ($s) {
            return [
                'name' => isset($s['name']) ? (string) $s['name'] : $this->map_courier_name(isset($s['courier']) ? (string) $s['courier'] : ''),
                'code' => isset($s['courier']) ? (string) $s['courier'] : '',
                'service' => isset($s['service']) ? (string) $s['service'] : '',
                'description' => isset($s['description']) ? (string) $s['description'] : '',
                'cost' => isset($s['cost']) ? (float) $s['cost'] : 0,
                'etd' => isset($s['etd']) ? (string) $s['etd'] : ''
            ];
        }, $services);
        $payload = [
            'success' => true,
            'data' => [
                'meta' => [
                    'message' => 'Success Calculate Cost',
                    'code' => 200,
                    'status' => 'success'
                ],
                'data' => $mapped
            ]
        ];
        set_transient($cache_key, $payload, DAY_IN_SECONDS);
        do_action('wp_store_shipping_calculated', $payload, $params);
        return new WP_REST_Response($payload, 200);
    }

    private function get_items_total_weight_grams($items)
    {
        $total = 0;
        if (!is_array($items)) {
            return $this->get_cart_total_weight_grams();
        }
        foreach ($items as $row) {
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

    private function map_courier_name($code)
    {
        $code = strtolower((string) $code);
        $names = [
            'jne' => 'Jalur Nugraha Ekakurir (JNE)',
            'jnt' => 'J&T Express',
            'sicepat' => 'SiCepat Ekspres',
            'pos' => 'POS Indonesia',
            'lion' => 'Lion Parcel',
            'tiki' => 'TIKI',
            'sap' => 'SAP Express',
            'ninja' => 'Ninja Express',
            'ide' => 'IDExpress',
            'wahana' => 'Wahana Prestasi Logistik',
            'sc' => 'Sentral Cargo',
            'rex' => 'Royal Express Asia',
            'custom' => 'Custom Rate'
        ];
        return $names[$code] ?? strtoupper($code);
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

        $url = self::get_rajaongkir_base_url() . '/destination/province';

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

        if (isset($data['data'])) {
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

        $url = self::get_rajaongkir_base_url() . "/destination/city/{$province}";

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

        if (isset($data['data'])) {
            $cities = array_map(function ($item) {
                return [
                    'city_id'   => $item['id'],
                    'city_name' => $item['name'],
                    'type'      => '',
                    'province'  => '',
                    'postal_code' => $item['zip_code'] ?? ''
                ];
            }, $data['data']);

            set_transient($cache_key, $cities, DAY_IN_SECONDS);

            return new WP_REST_Response([
                'success' => true,
                'data' => $cities
            ], 200);
        }

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

        $url = self::get_rajaongkir_base_url() . "/destination/district/{$city}";

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

        if (isset($data['data'])) {
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

    public function get_rajaongkir_waybill(WP_REST_Request $request)
    {
        $settings = get_option('wp_store_settings', []);
        $api_key = $settings['rajaongkir_api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Key VD Ongkir belum diatur.'
            ], 400);
        }

        $awb = sanitize_text_field($request->get_param('awb'));
        $courier = sanitize_text_field($request->get_param('courier'));
        if ($awb === '' || $courier === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Parameter awb dan courier diperlukan.'
            ], 400);
        }

        $cache_key = 'wp_store_waybill_' . md5($awb . '|' . strtolower($courier));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response([
                'success' => true,
                'data' => $cached
            ], 200);
        }

        $base = self::get_rajaongkir_base_url() . '/waybill';
        $url = add_query_arg([
            'awb' => $awb,
            'courier' => strtolower($courier)
        ], $base);

        $response = wp_remote_post($url, [
            'headers' => ['key' => $api_key],
            'timeout' => 12,
        ]);
        // return $response;
        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $response->get_error_message()
            ], 500);
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = $code === 200 ? wp_remote_retrieve_body($response) : '';
        $data = $body !== '' ? json_decode($body, true) : null;
        if (!is_array($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gagal mengambil tracking waybill.'
            ], 500);
        }
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return new WP_REST_Response([
            'success' => true,
            'data' => $data
        ], 200);
    }
}
