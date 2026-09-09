<?php
// Local integration test: php tests/direct-checkout.php /absolute/path/to/wp-load.php
// Run only on a development database. Fixtures are removed; outbound mail/HTTP are blocked.
if (PHP_SAPI !== 'cli' || empty($argv[1])) {
    exit("Usage: php tests/direct-checkout.php /path/to/wp-load.php\n");
}
ob_start();
require $argv[1];
require_once ABSPATH . 'wp-admin/includes/user.php';
use WpStore\Domain\Order\DirectCheckout;
use WpStore\Domain\Cart\CartService;
use WpStore\Domain\Product\ProductMeta;

function verify_direct($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function direct_request($data) {
    $request = new WP_REST_Request('POST');
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode($data));
    return $request;
}
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static function () { return new WP_Error('test_http_disabled', 'HTTP blocked by test'); });
$settings = get_option('wp_store_settings', []);
$settings['shipping_mode'] = 'off';
$settings['collect_address'] = '0';
$settings['payment_methods'] = ['bank_transfer'];
$settings['store_bank_accounts'] = [['bank_name' => 'Test', 'account_number' => '123', 'account_holder' => 'Test']];
add_filter('pre_option_wp_store_settings', static function () use (&$settings) { return $settings; });
$wpdb->query('START TRANSACTION');
$tokens = [];
$created = [];
try {
    $suffix = strtolower(wp_generate_password(10, false));
    $user = wp_insert_user(['user_login' => 'direct_test_' . $suffix, 'user_pass' => wp_generate_password(), 'user_email' => 'direct-test-' . $suffix . '@example.invalid']);
    verify_direct(!is_wp_error($user), 'Create isolated test buyer');
    wp_set_current_user($user);
    foreach (['cart', 'direct'] as $name) {
        $id = wp_insert_post(['post_type' => 'store_product', 'post_status' => 'publish', 'post_title' => 'Direct checkout test ' . $name, 'post_author' => $user]);
        $created[$name] = $id;
        foreach (['price' => 10000, 'stock' => 20, 'min_order' => 1, 'weight' => 1] as $key => $value) {
            update_post_meta($id, ProductMeta::meta_key($key), $value);
        }
    }
    $product = $created['direct'];
    update_post_meta($product, ProductMeta::meta_key('variant_name'), 'Warna');
    update_post_meta($product, ProductMeta::meta_key('variant_options'), ['Merah', 'Biru']);
    update_post_meta($product, ProductMeta::meta_key('price_adjustment_name'), 'Ukuran');
    update_post_meta($product, ProductMeta::meta_key('price_adjustment_options'), [['label' => 'XL', 'price' => 2000]]);
    $cart = new CartService();
    $cart->write_raw_items([['id' => $created['cart'], 'qty' => 3, 'opts' => []], ['id' => $product, 'qty' => 1, 'opts' => ['Warna' => 'Biru', 'Ukuran' => 'XL']]]);
    $table = $wpdb->prefix . 'store_carts';
    $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user), ARRAY_A);
    $input = ['id' => $product, 'qty' => 2, 'options' => ['Warna' => 'Merah', 'Ukuran' => 'XL']];
    verify_direct(is_wp_error(DirectCheckout::create(array_merge($input, ['qty' => 99]))), 'Reject insufficient stock');
    verify_direct(is_wp_error(DirectCheckout::create(array_merge($input, ['options' => ['Warna' => 'Forged']]))), 'Reject forged variant');
    $new_session = static function () use ($input, &$tokens) {
        $session = DirectCheckout::create($input);
        if (is_wp_error($session)) { throw new RuntimeException($session->get_error_message()); }
        $tokens[] = $session['token'];
        return $session['token'];
    };
    $token = $new_session();
    $rows = DirectCheckout::read($token);
    verify_direct(count($rows) === 1 && $rows[0]['qty'] === 2 && $rows[0]['opts']['Warna'] === 'Merah', 'Direct session preserves chosen product, quantity, variant');
    $direct_cart = (new VelocityMarketplace\Modules\Cart\CartRepository())->get_checkout_data($token);
    verify_direct(count($direct_cart['items']) === 1 && $direct_cart['count'] === 2, 'Marketplace hydrates only direct item');
    verify_direct(count($direct_cart['seller_groups']) === 1 && $direct_cart['seller_groups'][0]['weight_grams'] === 2000, 'Shipping group uses only direct quantity and weight');
    wp_set_current_user(0);
    verify_direct(is_wp_error(DirectCheckout::read($token)), 'Other actor cannot access token');
    wp_set_current_user($user);
    update_post_meta($product, ProductMeta::meta_key('stock'), 1);
    verify_direct(is_wp_error(DirectCheckout::read($token)), 'Stock revalidated after session creation');
    update_post_meta($product, ProductMeta::meta_key('stock'), 20);
    add_option('wps_direct_lock_' . $token, time(), '', false);
    verify_direct(is_wp_error(DirectCheckout::submit(direct_request(['direct_checkout' => $token]), static function () { throw new RuntimeException('Locked callback ran'); })), 'Concurrent submit rejected');
    delete_option('wps_direct_lock_' . $token);
    $payload = ['direct_checkout' => $token, 'name' => 'Test Buyer', 'email' => 'direct-test@example.invalid', 'phone' => '0800000000', 'payment_method' => 'bank_transfer', 'items' => [['id' => $created['cart'], 'qty' => 19]], 'shipping_cost' => 9999];
    $response = (new WpStore\Api\CheckoutController())->create_order(direct_request($payload));
    $result = $response instanceof WP_REST_Response ? $response->get_data() : [];
    verify_direct(!empty($result['id']), 'Core creates direct order: ' . (is_wp_error($response) ? $response->get_error_message() : wp_json_encode($result)));
    $order = (int) $result['id'];
    $order_items = get_post_meta($order, '_store_order_items', true);
    verify_direct(count($order_items) === 1 && (int) $order_items[0]['product_id'] === $product && (int) $order_items[0]['qty'] === 2, 'Core ignores injected cart items');
    verify_direct((float) $result['total'] === 24000.0, 'Core uses server price plus chosen option adjustment');
    verify_direct(is_wp_error(DirectCheckout::read($token)), 'Successful token cannot be reused');
    verify_direct($snapshot === $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user), ARRAY_A), 'Core checkout preserves full cart row including snapshots');
    $payload['direct_checkout'] = $new_session();
    $payload['payment_method'] = 'bank';
    $response = (new VelocityMarketplace\Modules\Checkout\CheckoutController())->create_order(direct_request($payload));
    $result = $response instanceof WP_REST_Response ? $response->get_data() : [];
    verify_direct(!empty($result['order_id']), 'Marketplace creates direct order: ' . (is_wp_error($response) ? $response->get_error_message() : wp_json_encode($result)));
    $order_items = get_post_meta($result['order_id'], '_store_order_items', true);
    verify_direct(count($order_items) === 1 && (int) $order_items[0]['product_id'] === $product, 'Marketplace orders direct product only');
    verify_direct((float) $result['total'] === 24000.0, 'Marketplace uses server price plus chosen option adjustment');
    verify_direct($snapshot === $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user), ARRAY_A), 'Marketplace checkout preserves full cart row');
    $expired = $new_session();
    delete_transient('wps_direct_' . $expired);
    verify_direct(is_wp_error(DirectCheckout::read($expired)), 'Expired session does not fall back to cart');
    wp_set_current_user(0);
    $_COOKIE['wp_store_cart_key'] = 'direct-test-guest';
    $guest_token = $new_session();
    verify_direct(count(DirectCheckout::read($guest_token)) === 1, 'Guest can resume own direct session');
    $_COOKIE['wp_store_cart_key'] = 'direct-test-other-guest';
    verify_direct(is_wp_error(DirectCheckout::read($guest_token)), 'Guest token bound to guest cookie');
    wp_set_current_user($user);
    $regular_cart = (new VelocityMarketplace\Modules\Cart\CartRepository())->get_checkout_data();
    verify_direct(count($regular_cart['items']) === 2 && $regular_cart['count'] === 4, 'Normal checkout still reads complete shopping cart');
    $settings['shipping_mode'] = 'normal';
    $settings['rajaongkir_api_key'] = 'integration-test';
    $settings['shipping_origin_subdistrict'] = (string) (900000 + $user);
    foreach (['vmp_store_province_id' => '1', 'vmp_store_city_id' => '2', 'vmp_store_subdistrict_id' => (string) (900000 + $user), 'vmp_couriers' => ['jne']] as $key => $value) {
        update_user_meta($user, $key, $value);
    }
    $weights = [];
    add_filter('pre_http_request', static function ($pre, $args, $url) use (&$weights) {
        if (strpos($url, '/calculate/domestic-cost') === false) { return $pre; }
        $body = is_array($args['body']) ? $args['body'] : json_decode($args['body'], true);
        $weights[] = (int) ($body['weight'] ?? 0);
        return ['headers' => [], 'body' => wp_json_encode(['data' => [['code' => 'jne', 'name' => 'JNE', 'service' => 'REG', 'description' => 'Regular', 'cost' => 7000, 'etd' => '2']]]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
    }, 100, 3);
    $payload = array_merge($payload, [
        'address' => 'Test address', 'province_id' => '1', 'city_id' => '2', 'subdistrict_id' => '3',
        'destination_province_id' => '1', 'destination_city_id' => '2', 'destination_subdistrict_id' => '3',
        'shipping_courier' => 'jne', 'shipping_service' => 'REG', 'shipping_cost' => 1,
        'shipping_groups' => [['seller_id' => $user, 'courier' => 'jne', 'service' => 'REG', 'cost' => 1]],
    ]);
    foreach (['core', 'marketplace'] as $engine) {
        $payload['direct_checkout'] = $new_session();
        $payload['payment_method'] = $engine === 'core' ? 'bank_transfer' : 'bank';
        $controller = $engine === 'core' ? new WpStore\Api\CheckoutController() : new VelocityMarketplace\Modules\Checkout\CheckoutController();
        $response = $controller->create_order(direct_request($payload));
        $result = $response instanceof WP_REST_Response ? $response->get_data() : [];
        verify_direct(isset($result['total']) && (float) $result['total'] === 31000.0, $engine . ' uses quoted shipping price instead of submitted cost: ' . wp_json_encode($result));
    }
    verify_direct(!empty($weights) && array_unique($weights) === [2000], 'Shipping quote sends weight of direct item only');
} finally {
    $wpdb->query('ROLLBACK');
    foreach ($tokens as $token) { delete_transient('wps_direct_' . $token); delete_option('wps_direct_lock_' . $token); }
    wp_set_current_user(0);
    foreach ($created as $id) { clean_post_cache($id); wp_delete_post($id, true); }
    if (isset($user) && !is_wp_error($user)) {
        $orders = get_posts(['post_type' => 'store_order', 'post_status' => 'any', 'meta_key' => '_store_order_user_id', 'meta_value' => $user, 'fields' => 'ids', 'posts_per_page' => -1]);
        foreach ($orders as $id) { wp_delete_post($id, true); }
        $wpdb->delete($wpdb->prefix . 'store_carts', ['user_id' => $user], ['%d']);
        clean_user_cache($user);
        wp_delete_user($user);
    }
}
