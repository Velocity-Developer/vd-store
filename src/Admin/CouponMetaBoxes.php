<?php

namespace WpStore\Admin;

class CouponMetaBoxes
{
    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('add_meta_boxes', [$this, 'add_native_meta_box']);
        add_action('save_post_store_coupon', [$this, 'save_native_meta_box']);
    }

    public function enqueue_scripts()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'store_coupon' || $screen->base !== 'post') {
            return;
        }

        wp_enqueue_script('jquery');
        $js = <<<'JS'
(function(){
    function formatDefaultTitle(){
        var d = new Date();
        var pad = function(n){ return (n < 10 ? '0' : '') + n; };
        return '#' +
            String(d.getFullYear()).slice(-2) +
            pad(d.getMonth() + 1) +
            pad(d.getDate()) +
            pad(d.getHours()) +
            pad(d.getMinutes()) +
            pad(d.getSeconds());
    }

    function applyIfEmpty(){
        var title = document.getElementById('title');
        if (!title) return;
        var val = (title.value || '').trim();
        if (val === '') {
            var codeInput = document.getElementById('_store_coupon_code');
            var code = codeInput ? (codeInput.value || '').trim() : '';
            title.value = code !== '' ? code : formatDefaultTitle();
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        applyIfEmpty();
        var codeInput = document.getElementById('_store_coupon_code');
        var title = document.getElementById('title');
        if (codeInput && title) {
            codeInput.addEventListener('input', function(){
                var tval = (title.value || '').trim();
                if (tval === '' || /^#\d+$/.test(tval)) {
                    title.value = (codeInput.value || '').trim();
                }
            });
        }
    });
})();
JS;
        wp_add_inline_script('jquery', $js, 'after');
    }

    public function add_native_meta_box()
    {
        add_meta_box(
            'wp_store_coupon_native_meta',
            'Detail Kupon',
            [$this, 'render_native_meta_box'],
            'store_coupon',
            'normal',
            'high'
        );
    }

    public function render_native_meta_box($post)
    {
        wp_nonce_field('wp_store_coupon_native_meta_save', 'wp_store_coupon_native_meta_nonce');

        $code = (string) get_post_meta($post->ID, '_store_coupon_code', true);
        $scope = (string) get_post_meta($post->ID, '_store_coupon_scope', true) === 'shipping' ? 'shipping' : 'product';
        $type = (string) get_post_meta($post->ID, '_store_coupon_type', true) === 'percent' ? 'percent' : 'nominal';
        $value = (float) get_post_meta($post->ID, '_store_coupon_value', true);
        $min_purchase = (float) get_post_meta($post->ID, '_store_coupon_min_purchase', true);
        $usage_limit = (int) get_post_meta($post->ID, '_store_coupon_usage_limit', true);
        $usage_count = (int) get_post_meta($post->ID, '_store_coupon_usage_count', true);
        $starts_at = $this->datetime_local_value((string) get_post_meta($post->ID, '_store_coupon_starts_at', true));
        $expires_at = $this->datetime_local_value((string) get_post_meta($post->ID, '_store_coupon_expires_at', true));
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="_store_coupon_code">Kode Kupon</label></th>
                    <td>
                        <input type="text" class="regular-text" id="_store_coupon_code" name="_store_coupon_code" value="<?php echo esc_attr($code); ?>" placeholder="Contoh: HEMAT10">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="_store_coupon_scope">Cakupan Kupon</label></th>
                    <td>
                        <select id="_store_coupon_scope" name="_store_coupon_scope">
                            <option value="product" <?php selected($scope, 'product'); ?>>Diskon Produk</option>
                            <option value="shipping" <?php selected($scope, 'shipping'); ?>>Diskon Ongkir</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="_store_coupon_type">Jenis Potongan</label></th>
                    <td>
                        <select id="_store_coupon_type" name="_store_coupon_type">
                            <option value="percent" <?php selected($type, 'percent'); ?>>Persentase (%)</option>
                            <option value="nominal" <?php selected($type, 'nominal'); ?>>Nominal (Rp)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="_store_coupon_value">Nilai Potongan</label></th>
                    <td><input type="number" min="0" step="0.01" class="regular-text" id="_store_coupon_value" name="_store_coupon_value" value="<?php echo esc_attr((string) $value); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="_store_coupon_min_purchase">Minimal Belanja</label></th>
                    <td><input type="number" min="0" step="0.01" class="regular-text" id="_store_coupon_min_purchase" name="_store_coupon_min_purchase" value="<?php echo esc_attr((string) $min_purchase); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="_store_coupon_usage_limit">Batas Penggunaan</label></th>
                    <td><input type="number" min="0" step="1" class="regular-text" id="_store_coupon_usage_limit" name="_store_coupon_usage_limit" value="<?php echo esc_attr((string) $usage_limit); ?>"><p class="description">Kosongkan atau isi 0 jika tidak dibatasi.</p></td>
                </tr>
                <tr>
                    <th scope="row">Penggunaan Saat Ini</th>
                    <td><?php echo esc_html((string) $usage_count); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="_store_coupon_starts_at">Mulai Berlaku</label></th>
                    <td><input type="datetime-local" class="regular-text" id="_store_coupon_starts_at" name="_store_coupon_starts_at" value="<?php echo esc_attr($starts_at); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="_store_coupon_expires_at">Kadaluarsa</label></th>
                    <td><input type="datetime-local" class="regular-text" id="_store_coupon_expires_at" name="_store_coupon_expires_at" value="<?php echo esc_attr($expires_at); ?>"></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_native_meta_box($post_id)
    {
        $nonce = isset($_POST['wp_store_coupon_native_meta_nonce']) ? (string) wp_unslash($_POST['wp_store_coupon_native_meta_nonce']) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_store_coupon_native_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $code = isset($_POST['_store_coupon_code']) ? sanitize_text_field((string) wp_unslash($_POST['_store_coupon_code'])) : '';
        $scope = isset($_POST['_store_coupon_scope']) && wp_unslash($_POST['_store_coupon_scope']) === 'shipping' ? 'shipping' : 'product';
        $type = isset($_POST['_store_coupon_type']) && wp_unslash($_POST['_store_coupon_type']) === 'percent' ? 'percent' : 'nominal';
        $value = max(0, (float) ($_POST['_store_coupon_value'] ?? 0));
        $min_purchase = max(0, (float) ($_POST['_store_coupon_min_purchase'] ?? 0));
        $usage_limit = max(0, (int) ($_POST['_store_coupon_usage_limit'] ?? 0));
        $starts_at = $this->normalize_datetime(isset($_POST['_store_coupon_starts_at']) ? (string) wp_unslash($_POST['_store_coupon_starts_at']) : '');
        $expires_at = $this->normalize_datetime(isset($_POST['_store_coupon_expires_at']) ? (string) wp_unslash($_POST['_store_coupon_expires_at']) : '');

        update_post_meta($post_id, '_store_coupon_code', $code);
        update_post_meta($post_id, '_store_coupon_scope', $scope);
        update_post_meta($post_id, '_store_coupon_type', $type);
        update_post_meta($post_id, '_store_coupon_value', $value);
        update_post_meta($post_id, '_store_coupon_min_purchase', $min_purchase);
        update_post_meta($post_id, '_store_coupon_usage_limit', $usage_limit);

        if ($starts_at !== '') {
            update_post_meta($post_id, '_store_coupon_starts_at', $starts_at);
        } else {
            delete_post_meta($post_id, '_store_coupon_starts_at');
        }

        if ($expires_at !== '') {
            update_post_meta($post_id, '_store_coupon_expires_at', $expires_at);
        } else {
            delete_post_meta($post_id, '_store_coupon_expires_at');
        }
    }

    private function normalize_datetime($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }
        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function datetime_local_value($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }
        return wp_date('Y-m-d\TH:i', $timestamp);
    }
}
