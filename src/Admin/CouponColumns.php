<?php

namespace WpStore\Admin;

class CouponColumns
{
    public function register()
    {
        add_filter('manage_store_coupon_posts_columns', [$this, 'add_columns']);
        add_action('manage_store_coupon_posts_custom_column', [$this, 'render_columns'], 10, 2);
    }

    public function add_columns($columns)
    {
        $new = [];
        foreach ($columns as $key => $label) {
            if ($key === 'date') {
                continue;
            }
            $new[$key] = $label;
            if ($key === 'title') {
                $new['coupon_code'] = 'Kode';
                $new['coupon_scope'] = 'Cakupan';
                $new['coupon_type'] = 'Jenis';
                $new['coupon_value'] = 'Nilai';
                $new['coupon_min_purchase'] = 'Min. Belanja';
                $new['coupon_usage'] = 'Penggunaan';
                $new['coupon_starts'] = 'Mulai';
                $new['coupon_expires'] = 'Kadaluarsa';
            }
        }
        // Append date at the end for consistency
        $new['date'] = $columns['date'] ?? 'Date';
        return $new;
    }

    public function render_columns($column, $post_id)
    {
        switch ($column) {
            case 'coupon_code':
                $code = get_post_meta($post_id, '_store_coupon_code', true);
                echo esc_html($code ?: '-');
                break;
            case 'coupon_type':
                $type = get_post_meta($post_id, '_store_coupon_type', true);
                $label = ($type === 'nominal') ? 'Nominal' : 'Persentase';
                echo esc_html($label);
                break;
            case 'coupon_scope':
                $scope = get_post_meta($post_id, '_store_coupon_scope', true);
                echo esc_html($scope === 'shipping' ? 'Ongkir' : 'Produk');
                break;
            case 'coupon_value':
                $type = get_post_meta($post_id, '_store_coupon_type', true);
                $val = get_post_meta($post_id, '_store_coupon_value', true);
                $num = is_numeric($val) ? (float) $val : 0;
                if ($type === 'percent') {
                    echo esc_html(number_format_i18n($num, 0)) . '%';
                } else {
                    echo 'Rp ' . esc_html(number_format_i18n($num, 0));
                }
                break;
            case 'coupon_min_purchase':
                $min_purchase = (float) get_post_meta($post_id, '_store_coupon_min_purchase', true);
                echo esc_html($min_purchase > 0 ? ('Rp ' . number_format_i18n($min_purchase, 0)) : '-');
                break;
            case 'coupon_usage':
                $usage_count = (int) get_post_meta($post_id, '_store_coupon_usage_count', true);
                $usage_limit = (int) get_post_meta($post_id, '_store_coupon_usage_limit', true);
                echo esc_html($usage_count . ($usage_limit > 0 ? (' / ' . $usage_limit) : ' / ∞'));
                break;
            case 'coupon_starts':
                $starts_raw = (string) get_post_meta($post_id, '_store_coupon_starts_at', true);
                if ($starts_raw === '') {
                    echo '-';
                    break;
                }
                $starts_ts = strtotime($starts_raw);
                echo $starts_ts ? esc_html(date_i18n('Y-m-d H:i', $starts_ts)) : esc_html($starts_raw);
                break;
            case 'coupon_expires':
                $raw = (string) get_post_meta($post_id, '_store_coupon_expires_at', true);
                if ($raw === '') {
                    echo '-';
                    break;
                }
                $ts = strtotime($raw);
                if ($ts) {
                    $now = current_time('timestamp');
                    $expired = ($ts <= $now);
                    $text = date_i18n('Y-m-d H:i', $ts);
                    if ($expired) {
                        echo '<span style="color:#d63638;">' . esc_html($text) . ' (kadaluarsa)</span>';
                    } else {
                        echo esc_html($text);
                    }
                } else {
                    echo esc_html($raw);
                }
                break;
        }
    }
}

