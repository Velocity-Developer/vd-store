<?php

namespace WpStore\Api;

use WP_REST_Request;
use WP_REST_Response;

class CustomerController
{
    public function register_routes()
    {
        // Profile Endpoints
        register_rest_route('wp-store/v1', '/customer/profile', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_profile'],
                'permission_callback' => [$this, 'check_auth'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_profile'],
                'permission_callback' => [$this, 'check_auth'],
            ],
        ]);

        register_rest_route('wp-store/v1', '/customer/avatar', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'upload_avatar'],
                'permission_callback' => [$this, 'check_auth'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_avatar'],
                'permission_callback' => [$this, 'check_auth'],
            ],
        ]);

        // Address Endpoints (CRUD)
        register_rest_route('wp-store/v1', '/customer/addresses', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_addresses'],
                'permission_callback' => [$this, 'check_auth'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_address'],
                'permission_callback' => [$this, 'check_auth'],
            ],
        ]);

        register_rest_route('wp-store/v1', '/customer/addresses/(?P<id>[\w-]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_address'],
                'permission_callback' => [$this, 'check_auth'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_address'],
                'permission_callback' => [$this, 'check_auth'],
            ],
        ]);

        // Orders list
        register_rest_route('wp-store/v1', '/customer/orders', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_orders'],
                'permission_callback' => [$this, 'check_auth'],
            ],
        ]);
    }

    public function check_auth()
    {
        return is_user_logged_in();
    }

    public function get_profile()
    {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        $avatar_id = (int) get_user_meta($user_id, '_store_avatar_id', true);
        $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
        if (!$avatar_url) {
            $avatar_url = function_exists('get_avatar_url') ? get_avatar_url($user_id) : '';
        }

        return new WP_REST_Response([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, '_store_phone', true),
            'avatar_url' => $avatar_url,
        ], 200);
    }

    public function update_profile(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();

        $first_name = sanitize_text_field($request->get_param('first_name'));
        $last_name = sanitize_text_field($request->get_param('last_name'));
        $phone = sanitize_text_field($request->get_param('phone'));

        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ]);

        update_user_meta($user_id, '_store_phone', $phone);

        return new WP_REST_Response(['message' => 'Profil berhasil diperbarui'], 200);
    }

    public function upload_avatar(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!isset($_FILES['avatar'])) {
            return new WP_REST_Response(['message' => 'File tidak ditemukan'], 400);
        }
        $file = $_FILES['avatar'];
        if (!isset($file['name']) || !isset($file['tmp_name'])) {
            return new WP_REST_Response(['message' => 'File tidak valid'], 400);
        }
        $allowed = apply_filters('wp_store_customer_avatar_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
        ], $user_id);
        $type = isset($file['type']) ? $file['type'] : '';
        if ($type && !in_array($type, $allowed, true)) {
            return new WP_REST_Response(['message' => 'Tipe file tidak diizinkan'], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded = wp_handle_upload($file, ['test_form' => false]);
        if (!isset($uploaded['file']) || isset($uploaded['error'])) {
            return new WP_REST_Response(['message' => isset($uploaded['error']) ? $uploaded['error'] : 'Upload gagal'], 500);
        }

        $filetype = wp_check_filetype(basename($uploaded['file']), null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded['file'])),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $uploaded['file']);
        if (is_wp_error($attach_id) || !$attach_id) {
            return new WP_REST_Response(['message' => 'Gagal menyimpan lampiran'], 500);
        }

        $meta = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
        if ($meta) {
            wp_update_attachment_metadata($attach_id, $meta);
        }

        update_user_meta($user_id, '_store_avatar_id', (int) $attach_id);
        $url = wp_get_attachment_image_url($attach_id, 'thumbnail');

        do_action('wp_store_customer_avatar_uploaded', $user_id, (int) $attach_id, (string) $url);
        return new WP_REST_Response([
            'message' => 'Foto profil diperbarui',
            'avatar_id' => (int) $attach_id,
            'avatar_url' => (string) ($url ?: ''),
        ], 200);
    }

    public function delete_avatar(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $avatar_id = (int) get_user_meta($user_id, '_store_avatar_id', true);
        if ($avatar_id) {
            delete_user_meta($user_id, '_store_avatar_id');
        }
        do_action('wp_store_customer_avatar_deleted', $user_id, $avatar_id);
        return new WP_REST_Response(['message' => 'Foto profil dihapus'], 200);
    }

    // --- Address CRUD Logic ---
    // Storing addresses as an array in user meta '_store_addresses'
    // Structure: [{id: 'uniqid', label: 'Home', address: '...', city: '...'}]

    public function get_addresses()
    {
        $user_id = get_current_user_id();
        $addresses = get_user_meta($user_id, '_store_addresses', true);

        if (!is_array($addresses)) {
            $addresses = [];
        }

        return new WP_REST_Response($addresses, 200);
    }

    public function create_address(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $addresses = get_user_meta($user_id, '_store_addresses', true);
        if (!is_array($addresses)) {
            $addresses = [];
        }

        $new_address = [
            'id' => uniqid('addr_'),
            'label' => sanitize_text_field($request->get_param('label')),
            'address' => sanitize_textarea_field($request->get_param('address')),
            'province_id' => sanitize_text_field($request->get_param('province_id')),
            'province_name' => sanitize_text_field($request->get_param('province_name')),
            'city_id' => sanitize_text_field($request->get_param('city_id')),
            'city_name' => sanitize_text_field($request->get_param('city_name')),
            'subdistrict_id' => sanitize_text_field($request->get_param('subdistrict_id')),
            'subdistrict_name' => sanitize_text_field($request->get_param('subdistrict_name')),
            'postal_code' => sanitize_text_field($request->get_param('postal_code')),
        ];

        // Fallback for legacy 'city' field usage in display if needed, 
        // though frontend should now use city_name.
        // We'll keep 'city' as an alias to 'city_name' or just rely on 'city_name'.
        // Let's explicitly add 'city' for backward compatibility if any.
        $new_address['city'] = $new_address['city_name'];

        array_push($addresses, $new_address);
        update_user_meta($user_id, '_store_addresses', $addresses);

        return new WP_REST_Response($new_address, 201);
    }

    public function update_address(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $address_id = $request->get_param('id');
        $addresses = get_user_meta($user_id, '_store_addresses', true);

        if (!is_array($addresses)) {
            return new WP_REST_Response(['message' => 'Address list not found'], 404);
        }

        $found = false;
        foreach ($addresses as &$addr) {
            if ($addr['id'] === $address_id) {
                $addr['label'] = sanitize_text_field($request->get_param('label'));
                $addr['address'] = sanitize_textarea_field($request->get_param('address'));

                $addr['province_id'] = sanitize_text_field($request->get_param('province_id'));
                $addr['province_name'] = sanitize_text_field($request->get_param('province_name'));
                $addr['city_id'] = sanitize_text_field($request->get_param('city_id'));
                $addr['city_name'] = sanitize_text_field($request->get_param('city_name'));
                $addr['subdistrict_id'] = sanitize_text_field($request->get_param('subdistrict_id'));
                $addr['subdistrict_name'] = sanitize_text_field($request->get_param('subdistrict_name'));

                $addr['postal_code'] = sanitize_text_field($request->get_param('postal_code'));

                // Backwards compat
                $addr['city'] = $addr['city_name'];

                $found = true;
                break;
            }
        }

        if (!$found) {
            return new WP_REST_Response(['message' => 'Address not found'], 404);
        }

        update_user_meta($user_id, '_store_addresses', $addresses);
        return new WP_REST_Response(['message' => 'Alamat berhasil diperbarui'], 200);
    }

    public function delete_address(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $address_id = $request->get_param('id');
        $addresses = get_user_meta($user_id, '_store_addresses', true);

        if (!is_array($addresses)) {
            return new WP_REST_Response(['message' => 'Address list not found'], 404);
        }

        $new_addresses = array_filter($addresses, function ($addr) use ($address_id) {
            return $addr['id'] !== $address_id;
        });

        // Re-index array
        $new_addresses = array_values($new_addresses);

        update_user_meta($user_id, '_store_addresses', $new_addresses);
        return new WP_REST_Response(['message' => 'Alamat berhasil dihapus'], 200);
    }

    public function get_orders(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_REST_Response([], 200);
        }
        $email = (string) $user->user_email;
        $settings = get_option('wp_store_settings', []);
        $tracking_id = isset($settings['page_tracking']) ? absint($settings['page_tracking']) : 0;
        $tracking_base = $tracking_id ? get_permalink($tracking_id) : site_url('/tracking-order/');
        $meta_query = [
            'relation' => 'OR',
            [
                'key' => '_store_order_user_id',
                'value' => $user_id,
                'compare' => '='
            ],
            [
                'relation' => 'AND',
                [
                    'key' => '_store_order_user_id',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_store_order_email',
                    'value' => $email,
                    'compare' => '='
                ]
            ]
        ];
        $q = new \WP_Query([
            'post_type' => 'store_order',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => $meta_query
        ]);
        $orders = [];
        foreach ($q->posts as $p) {
            $oid = $p->ID;
            $order_number = get_post_meta($oid, '_store_order_number', true);
            if (!$order_number) {
                $order_number = $oid;
            }
            $raw_items = (array) (get_post_meta($oid, '_store_order_items', true) ?: []);
            $orders[] = [
                'id' => $oid,
                'order_number' => $order_number,
                'date' => get_the_date('d M Y', $oid),
                'total' => (float) get_post_meta($oid, '_store_order_total', true),
                'status' => (string) (get_post_meta($oid, '_store_order_status', true) ?: 'pending'),
                'tracking_url' => add_query_arg(['order' => $order_number], $tracking_base),
                'items' => $this->normalize_order_items($raw_items)
            ];
        }
        wp_reset_postdata();
        return new WP_REST_Response($orders, 200);
    }

    private function normalize_order_items($items)
    {
        $out = [];
        $map = [];
        foreach (is_array($items) ? $items : [] as $l) {
            $pid = isset($l['product_id']) ? (int) $l['product_id'] : 0;
            $qty = isset($l['qty']) ? (int) $l['qty'] : 0;
            $price = isset($l['price']) ? (float) $l['price'] : 0;
            $title = isset($l['title']) ? (string) $l['title'] : '';
            $opts = isset($l['options']) && is_array($l['options']) ? $l['options'] : [];
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $key = (string) $pid . '|' . wp_json_encode($opts);
            if (!isset($map[$key])) {
                $map[$key] = count($out);
                $out[] = [
                    'product_id' => $pid,
                    'title' => $title !== '' ? $title : get_the_title($pid),
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $price * $qty,
                    'options' => $opts,
                ];
            } else {
                $idx = (int) $map[$key];
                $out[$idx]['qty'] += $qty;
                $out[$idx]['subtotal'] = $out[$idx]['price'] * $out[$idx]['qty'];
            }
        }
        return $out;
    }
}
