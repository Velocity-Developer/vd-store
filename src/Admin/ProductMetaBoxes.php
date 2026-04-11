<?php

namespace WpStore\Admin;

use WpStore\Domain\Product\ProductFields;

class ProductMetaBoxes
{
    private $drafting_for_validation = false;

    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('add_meta_boxes', [$this, 'add_native_meta_box']);
        add_action('save_post_store_product', [$this, 'save_native_meta_box']);
        add_action('admin_notices', [$this, 'render_validation_notice']);
    }

    public function enqueue_styles()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'store_product') {
            wp_enqueue_style(
                'wp-store-admin-cmb2',
                WP_STORE_URL . 'assets/admin/css/xmb2.css',
                [],
                WP_STORE_VERSION
            );

            wp_enqueue_script(
                'wp-store-admin-js',
                WP_STORE_URL . 'assets/admin/js/store-admin.js',
                ['jquery'],
                WP_STORE_VERSION,
                true
            );

            wp_localize_script('wp-store-admin-js', 'vmpSettings', [
                'currentUserId' => get_current_user_id(),
                'canManageOptions' => current_user_can('manage_options'),
            ]);

            wp_enqueue_media();
        }
    }

    public function add_native_meta_box()
    {
        add_meta_box(
            'wp_store_product_native_meta',
            'Data Produk',
            [$this, 'render_native_meta_box'],
            'store_product',
            'normal',
            'high'
        );
    }

    public function render_native_meta_box($post)
    {
        wp_nonce_field('wp_store_product_native_meta_save', 'wp_store_product_native_meta_nonce');
        ?>
        <style>
            .vmp-meta-section{margin-bottom:16px}
            .vmp-meta-section__title{margin:0 0 10px;font-size:13px}
            .vmp-meta-section .row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
            .vmp-meta-section .col-12{grid-column:1 / -1}
            .vmp-meta-section input:not([type="checkbox"]):not([type="hidden"]),
            .vmp-meta-section select,
            .vmp-meta-section textarea{width:100%}
            .vmp-meta-section .form-check{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding-top:8px}
            .vmp-meta-section .form-check-input{width:auto !important;min-width:16px;min-height:16px;margin:0}
            .vmp-meta-section .form-check-label{margin:0;font-weight:600}
            .vmp-meta-section .form-check .form-text{flex:0 0 100%;margin:0 0 0 24px}
            .vmp-meta-section .form-text{margin-top:4px;color:#646970}
            .vmp-meta-section .d-flex{display:flex}
            .vmp-meta-section .flex-wrap{flex-wrap:wrap}
            .vmp-meta-section .gap-2{gap:8px}
            .vmp-meta-section .mb-2{margin-bottom:8px}
            .vmp-media-field{display:block}
            .vmp-media-field__preview{margin-top:10px}
            .vmp-media-field__empty{padding:14px;border:1px dashed #c3c4c7;border-radius:8px;background:#f6f7f7;color:#646970}
            .vmp-media-field__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;max-width:100%}
            .vmp-media-field__grid--single{grid-template-columns:minmax(0,220px)}
            .vmp-media-field__item{position:relative;overflow:hidden;border:1px solid #dcdcde;border-radius:10px;background:#fff;aspect-ratio:1/1;box-shadow:0 1px 2px rgba(0,0,0,.04)}
            .vmp-media-field__grid--single .vmp-media-field__item{aspect-ratio:auto;min-height:180px}
            .vmp-media-field__image{display:block;width:100%;height:100%;object-fit:cover}
            .vmp-media-field__grid--single .vmp-media-field__image{height:220px}
            .vmp-media-field__remove{position:absolute;top:8px;right:8px;display:flex;align-items:center;justify-content:center;width:26px;height:26px;padding:0;border:0;border-radius:999px;background:rgba(29,35,39,.82);color:#fff;cursor:pointer;line-height:1}
            .vmp-media-field__remove::before{content:"×";font-size:18px;font-weight:700}
            .vmp-media-field__remove:hover{background:#b32d2e}
            .vmp-media-field__remove:focus{outline:none;box-shadow:0 0 0 2px #72aee6}
            .vmp-file-link-field__preview{margin-top:10px}
            .vmp-file-link-field__empty{padding:14px;border:1px dashed #c3c4c7;border-radius:8px;background:#f6f7f7;color:#646970}
            .vmp-file-link-field__summary{padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
            .vmp-file-link-field__name{font-weight:600;margin-bottom:4px;word-break:break-word}
            .vmp-file-link-field__link{word-break:break-all}
        </style>
        <?php
        echo ProductFields::render_sections((int) $post->ID, 'admin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function save_native_meta_box($post_id)
    {
        if (!isset($_POST['wp_store_product_native_meta_nonce']) || !wp_verify_nonce($_POST['wp_store_product_native_meta_nonce'], 'wp_store_product_native_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $validation = ProductFields::validate_submission('admin');
        if (is_wp_error($validation)) {
            $error_data = $validation->get_error_data();
            $error_field = is_array($error_data) && !empty($error_data['field']) ? (string) $error_data['field'] : '';
            $this->queue_validation_notice($validation->get_error_message(), $error_field);

            if (!$this->drafting_for_validation && in_array(get_post_status($post_id), ['publish', 'pending'], true)) {
                $this->drafting_for_validation = true;
                remove_action('save_post_store_product', [$this, 'save_native_meta_box']);
                wp_update_post([
                    'ID' => (int) $post_id,
                    'post_status' => 'draft',
                ]);
                add_action('save_post_store_product', [$this, 'save_native_meta_box']);
                $this->drafting_for_validation = false;
            }

            return;
        }

        ProductFields::save((int) $post_id, 'admin');
    }

    public function render_validation_notice()
    {
        if (!is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'store_product') {
            return;
        }

        $message = isset($_GET['wp_store_product_error']) ? sanitize_text_field((string) wp_unslash($_GET['wp_store_product_error'])) : '';
        if ($message === '') {
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function queue_validation_notice($message, $field = '')
    {
        add_filter('redirect_post_location', static function ($location) use ($message, $field) {
            return add_query_arg([
                'wp_store_product_error' => rawurlencode((string) $message),
                'wp_store_product_error_field' => rawurlencode((string) $field),
            ], $location);
        });
    }

}
