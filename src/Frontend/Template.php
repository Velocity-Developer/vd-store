<?php

namespace WpStore\Frontend;

class Template
{
    public static function locate(string $template): string
    {
        $template = self::normalize_template($template);
        if ($template === '') {
            return '';
        }

        $default_path = WP_STORE_PATH . 'templates/frontend/' . $template . '.php';
        $override = apply_filters('wp_store_locate_template', '', $template, $default_path);
        if (is_string($override) && $override !== '' && file_exists($override)) {
            return $override;
        }

        $candidates = apply_filters('wp_store_template_candidates', [], $template);
        if (!is_array($candidates)) {
            $candidates = [];
        }

        $theme_candidates = [
            trailingslashit(get_stylesheet_directory()) . 'vd-store/' . $template . '.php',
            trailingslashit(get_template_directory()) . 'vd-store/' . $template . '.php',
        ];

        foreach (array_merge($candidates, $theme_candidates, [$default_path]) as $path) {
            if (is_string($path) && $path !== '' && file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    public static function render(string $template, array $data = []): string
    {
        $path = self::locate($template);
        if ($path === '') {
            return '';
        }
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }
        ob_start();
        require $path;
        return ob_get_clean();
    }

    private static function normalize_template(string $template): string
    {
        $template = str_replace(['\\', "\0"], ['/', ''], $template);
        $template = preg_replace('#\.php$#', '', $template);
        $parts = [];
        foreach (explode('/', $template) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
