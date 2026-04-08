<?php

/**
 * Plugin Name: VD Store
 * Description: Plugin ecommerce VD Store berbasis REST API dan Alpine.js
 * Version:     1.0.0
 * Author:      Dev Team Velocitydeveloper.com
 * Author URI:  https://velocitydeveloper.com
 * Text Domain: vd-store
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_STORE_VERSION', '1.0.0');
define('WP_STORE_PATH', plugin_dir_path(__FILE__));
define('WP_STORE_URL', plugin_dir_url(__FILE__));

if (file_exists(WP_STORE_PATH . 'vendor/autoload.php')) {
    require_once WP_STORE_PATH . 'vendor/autoload.php';
}

if (file_exists(WP_STORE_PATH . 'vendor/cmb2/cmb2/init.php')) {
    require_once WP_STORE_PATH . 'vendor/cmb2/cmb2/init.php';
}

spl_autoload_register(function ($class) {
    $prefix = 'WpStore\\';
    $base_dir = WP_STORE_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

function wp_store_init()
{
    $should_migrate = get_option('wp_store_db_version') !== '1.3.0';
    global $wpdb;
    $table_name = $wpdb->prefix . 'store_carts';
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if ($should_migrate || !$exists) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            guest_key VARCHAR(64) NULL DEFAULT NULL,
            cart LONGTEXT NOT NULL,
            shipping_data LONGTEXT NULL DEFAULT NULL,
            marketplace_snapshot LONGTEXT NULL DEFAULT NULL,
            total_price DECIMAL(10,2) NULL DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user (user_id),
            UNIQUE KEY uniq_guest (guest_key)
        ) {$charset_collate};";
        dbDelta($sql);
        update_option('wp_store_db_version', '1.3.0');
    }

    // Create wishlist table
    $wishlist_table = $wpdb->prefix . 'store_wishlists';
    $wishlist_exists = $wpdb->get_var("SHOW TABLES LIKE '$wishlist_table'") === $wishlist_table;
    if ($should_migrate || !$wishlist_exists) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql2 = "CREATE TABLE {$wishlist_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            guest_key VARCHAR(64) NULL DEFAULT NULL,
            wishlist LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user (user_id),
            UNIQUE KEY uniq_guest (guest_key)
        ) {$charset_collate};";
        dbDelta($sql2);
    }

    $plugin = new \WpStore\Core\Plugin();
    $plugin->run();
}

add_action('plugins_loaded', 'wp_store_init');

register_activation_hook(__FILE__, function () {
    $post_types = new \WpStore\Core\PostTypes();
    $post_types->register_product_type();
    $post_types->register_order_type();

    // Trigger update logic
    delete_option('wp_store_db_version'); // Force update
    wp_store_init();

    flush_rewrite_rules();
});

function wps_icon($args = [])
{
    if (is_string($args)) {
        $args = ['name' => $args];
    }
    if (!is_array($args)) {
        return '';
    }
    $data = [];
    if (isset($args['name'])) {
        $data['name'] = sanitize_key($args['name']);
    }
    if (isset($args['size'])) {
        $data['size'] = (int) $args['size'];
    }
    if (isset($args['class']) && is_string($args['class'])) {
        $data['class'] = $args['class'];
    }
    if (isset($args['stroke_color']) && is_string($args['stroke_color'])) {
        $data['stroke_color'] = $args['stroke_color'];
    } elseif (isset($args['stroke-color']) && is_string($args['stroke-color'])) {
        $data['stroke_color'] = $args['stroke-color'];
    } elseif (isset($args['stroke']) && is_string($args['stroke'])) {
        $data['stroke_color'] = $args['stroke'];
    } elseif (isset($args['color']) && is_string($args['color'])) {
        $data['stroke_color'] = $args['color'];
    } elseif (isset($args['border-color']) && is_string($args['border-color'])) {
        $data['stroke_color'] = $args['border-color'];
    } elseif (isset($args['border_color']) && is_string($args['border_color'])) {
        $data['stroke_color'] = $args['border_color'];
    }
    if (isset($args['stroke_width'])) {
        $data['stroke_width'] = (int) $args['stroke_width'];
    } elseif (isset($args['stroke-width'])) {
        $data['stroke_width'] = (int) $args['stroke-width'];
    }
    if (isset($args['fill_color']) && is_string($args['fill_color'])) {
        $data['fill_color'] = $args['fill_color'];
    } elseif (isset($args['fill-color']) && is_string($args['fill-color'])) {
        $data['fill_color'] = $args['fill-color'];
    } elseif (isset($args['fill']) && is_string($args['fill'])) {
        $data['fill_color'] = $args['fill'];
    }
    return \WpStore\Frontend\Template::render('components/icons', $data);
}
function wps_label_badge_html($product_id)
{
    return '';
}
function wps_discount_badge_html($product_id)
{
    $settings = get_option('wp_store_settings', []);
    if (!empty($settings['members_only_discount']) && !is_user_logged_in()) {
        return '';
    }
    $price = get_post_meta((int) $product_id, '_store_price', true);
    $sale = get_post_meta((int) $product_id, '_store_sale_price', true);
    $price = $price !== '' ? (float) $price : null;
    $sale = $sale !== '' ? (float) $sale : null;
    if ($price !== null && $sale !== null && $price > 0 && $sale > 0 && $sale < $price) {
        $untilRaw = (string) get_post_meta((int) $product_id, '_store_flashsale_until', true);
        $untilTs = $untilRaw ? strtotime($untilRaw) : 0;
        $nowTs = current_time('timestamp');
        $active = ($untilTs === 0 || $untilTs > $nowTs);
        if (!$active) return '';
        $percent = round((($price - $sale) / $price) * 100);
        if ($percent <= 0) return '';
        return '<span style="position:absolute;bottom:8px;right:8px;display:inline-flex;align-items:center;background:#ef4444;color:#fff;border-radius:9999px;padding:2px 6px;font-size:10px;line-height:12px;z-index:2;">' . esc_html($percent) . '%</span>';
    }
    return '';
}

function wps_product_price_html($product_id, $args = [])
{
    $product_id = (int) $product_id;
    if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
        return '';
    }

    $args = wp_parse_args(is_array($args) ? $args : [], [
        'countdown' => false,
        'currency' => '',
        'wrapper_class' => 'wps-price',
        'sale_group_class' => 'wps-flex wps-items-baseline wps-gap-2 wps-price-group',
        'sale_class' => 'wps-text-lg wps-text-gray-900 wps-font-medium wps-price-text',
        'regular_class' => 'wps-text-sm wps-text-gray-500',
        'price_class' => 'wps-text-lg wps-text-gray-900 wps-font-medium wps-price-text',
        'empty_class' => 'wps-text-sm wps-text-gray-500',
        'show_empty' => true,
    ]);
    $countdown_attr = $args['countdown'];
    $currency = is_string($args['currency']) && $args['currency'] !== ''
        ? $args['currency']
        : (get_option('wp_store_settings', [])['currency_symbol'] ?? 'Rp');

    $product = \WpStore\Domain\Product\ProductData::map_post($product_id);
    if ($product === null) {
        return '';
    }

    $price = array_key_exists('regular_price', $product) ? $product['regular_price'] : \WpStore\Domain\Product\ProductData::resolve_regular_price($product_id);
    $sale = array_key_exists('sale_price', $product) ? $product['sale_price'] : \WpStore\Domain\Product\ProductData::resolve_sale_price($product_id);
    $want_countdown = is_bool($countdown_attr)
        ? $countdown_attr
        : in_array(strtolower((string) $countdown_attr), ['1', 'true', 'yes'], true);

    $until_raw = (string) \WpStore\Domain\Product\ProductMeta::get($product_id, 'sale_until', '');
    $until_ts = $until_raw ? strtotime($until_raw) : 0;
    $now_ts = current_time('timestamp');
    $sale_active = $sale !== null
        && $sale > 0
        && (($price !== null && $sale < $price) || $price === null)
        && ($until_ts === 0 || $until_ts > $now_ts);

    $settings = get_option('wp_store_settings', []);
    if (!empty($settings['members_only_discount']) && !is_user_logged_in()) {
        $sale_active = false;
    }

    $wrapper_class = trim((string) $args['wrapper_class']);
    $sale_group_class = trim((string) $args['sale_group_class']);
    $sale_class = trim((string) $args['sale_class']);
    $regular_class = trim((string) $args['regular_class']);
    $price_class = trim((string) $args['price_class']);
    $empty_class = trim((string) $args['empty_class']);
    $show_empty = (bool) $args['show_empty'];

    $html = '<div' . ($wrapper_class !== '' ? ' class="' . esc_attr($wrapper_class) . '"' : '') . '>';
    if ($sale_active) {
        $html .= '<div' . ($sale_group_class !== '' ? ' class="' . esc_attr($sale_group_class) . '"' : '') . '>';
        $html .= '<span' . ($sale_class !== '' ? ' class="' . esc_attr($sale_class) . '"' : '') . '>' . esc_html(($currency ?: 'Rp') . ' ' . number_format((float) $sale, 0, ',', '.')) . '</span>';
        if ($price !== null && (float) $price > 0) {
            $html .= '<span' . ($regular_class !== '' ? ' class="' . esc_attr($regular_class) . '"' : '') . ' style="text-decoration: line-through;">' . esc_html(($currency ?: 'Rp') . ' ' . number_format((float) $price, 0, ',', '.')) . '</span>';
        }
        $html .= '</div>';
    } else {
        if ($price !== null) {
            $html .= '<div' . ($price_class !== '' ? ' class="' . esc_attr($price_class) . '"' : '') . '>' . esc_html(($currency ?: 'Rp') . ' ' . number_format((float) $price, 0, ',', '.')) . '</div>';
        } elseif ($show_empty) {
            $html .= '<div' . ($empty_class !== '' ? ' class="' . esc_attr($empty_class) . '"' : '') . '>Harga belum diatur.</div>';
        }
    }

    if ($want_countdown && $until_ts > $now_ts) {
        wp_enqueue_script('alpinejs');
        $end_js = esc_js($until_raw);
        $html .= '<div class="wps-text-xs wps-text-gray-700 wps-mt-1" x-data="{ end: new Date(\'' . $end_js . '\'), d:0,h:0,m:0,s:0, tick(){ const diff = Math.max(0, this.end - new Date()); this.d = Math.floor(diff/86400000); this.h = Math.floor((diff%86400000)/3600000); this.m = Math.floor((diff%3600000)/60000); this.s = Math.floor((diff%60000)/1000); }, init(){ this.tick(); setInterval(()=>this.tick(), 1000); } } ?? {}" x-init="init">';
        $html .= '<span>Berakhir dalam </span><span x-text="d"></span><span> hari </span><span x-text="h"></span><span> jam </span><span x-text="m"></span><span> menit </span><span x-text="s"></span><span> detik</span>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}
