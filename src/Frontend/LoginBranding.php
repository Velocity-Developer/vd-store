<?php

namespace WpStore\Frontend;

class LoginBranding
{
    public function register()
    {
        add_action('login_head', [$this, 'inject_favicon']);
        add_filter('login_title', [$this, 'filter_title'], 10, 2);
        add_filter('login_headertext', [$this, 'filter_headertext']);
        add_filter('login_headerurl', [$this, 'filter_headerurl']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_logo_styles']);
    }

    public function inject_favicon()
    {
        $icon = function_exists('get_site_icon_url') ? get_site_icon_url(192) : '';
        if ($icon) {
            echo '<link rel="icon" href="' . esc_url($icon) . '" sizes="192x192">' . "\n";
            echo '<link rel="apple-touch-icon" href="' . esc_url($icon) . '">' . "\n";
        }
    }

    public function filter_title($login_title, $title)
    {
        $settings = get_option('wp_store_settings', []);
        $store = isset($settings['store_name']) && is_string($settings['store_name']) && $settings['store_name'] !== '' ? $settings['store_name'] : get_bloginfo('name');
        return $store . ' – Masuk';
    }

    public function filter_headertext($text)
    {
        $settings = get_option('wp_store_settings', []);
        $store = isset($settings['store_name']) && is_string($settings['store_name']) && $settings['store_name'] !== '' ? $settings['store_name'] : get_bloginfo('name');
        return $store;
    }

    public function filter_headerurl($url)
    {
        return home_url('/');
    }

    public function enqueue_logo_styles()
    {
        $settings = get_option('wp_store_settings', []);
        $primary = isset($settings['theme_primary']) ? sanitize_hex_color($settings['theme_primary']) : '#2563eb';
        $primary_hover = isset($settings['theme_primary_hover']) ? sanitize_hex_color($settings['theme_primary_hover']) : '#1d4ed8';
        $bg_color = isset($settings['login_bg_color']) ? sanitize_hex_color($settings['login_bg_color']) : '#f5f7fb';
        $logo = $this->login_logo_data();

        $css = '';
        if (!empty($logo['url'])) {
            $css .= '
            .login h1 a {
                background-image: url(' . esc_url($logo['url']) . ');
                background-size: contain;
                background-position: center;
                width: ' . (int) $logo['width'] . 'px;
                height: ' . (int) $logo['height'] . 'px;
            }';
        }

        $css .= '
        body.login {
            background: ' . esc_html($bg_color) . ';
        }
        .login form {
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06);
            border-radius: 8px;
        }
        .login form .input, .login input[type=text], .login input[type=password] {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            box-shadow: none;
        }
        .login .button.button-primary, .login .button-primary {
            background: ' . esc_html($primary) . ';
            border-color: ' . esc_html($primary) . ';
            color: #fff;
            text-shadow: none;
            border-radius: 4px;
            box-shadow: none;
        }
        .login .button.button-primary:hover, .login .button-primary:hover {
            background: ' . esc_html($primary_hover) . ';
            border-color: ' . esc_html($primary_hover) . ';
        }
        ';

        wp_add_inline_style('login', $css);
    }

    private function login_logo_data()
    {
        $custom_logo_id = function_exists('get_theme_mod') ? (int) get_theme_mod('custom_logo') : 0;
        if ($custom_logo_id > 0) {
            $image = wp_get_attachment_image_src($custom_logo_id, 'full');
            if (is_array($image) && !empty($image[0])) {
                $width = isset($image[1]) ? max(1, (int) $image[1]) : 220;
                $height = isset($image[2]) ? max(1, (int) $image[2]) : 90;
                $max_width = 220;
                $max_height = 110;
                $ratio = min($max_width / $width, $max_height / $height, 1);

                return [
                    'url' => (string) $image[0],
                    'width' => max(84, (int) round($width * $ratio)),
                    'height' => max(60, (int) round($height * $ratio)),
                ];
            }
        }

        $icon = function_exists('get_site_icon_url') ? get_site_icon_url(192) : '';
        if ($icon) {
            return [
                'url' => $icon,
                'width' => 84,
                'height' => 84,
            ];
        }

        return [
            'url' => '',
            'width' => 84,
            'height' => 84,
        ];
    }
}
