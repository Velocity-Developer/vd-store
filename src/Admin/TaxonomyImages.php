<?php

namespace WpStore\Admin;

class TaxonomyImages
{
    private const META_KEY = 'wp_store_term_image_id';

    private const TAXONOMIES = [
        'store_product_cat',
        'brand',
    ];

    public function register()
    {
        foreach (self::TAXONOMIES as $taxonomy) {
            add_action($taxonomy . '_add_form_fields', [$this, 'render_add_field']);
            add_action($taxonomy . '_edit_form_fields', [$this, 'render_edit_field']);
            add_action('created_' . $taxonomy, [$this, 'save']);
            add_action('edited_' . $taxonomy, [$this, 'save']);
            add_action('delete_' . $taxonomy, [$this, 'delete']);
            add_filter('manage_edit-' . $taxonomy . '_columns', [$this, 'add_image_column']);
            add_filter('manage_' . $taxonomy . '_custom_column', [$this, 'render_image_column'], 10, 3);
        }

        add_action('quick_edit_custom_box', [$this, 'render_quick_edit_field'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->taxonomy, self::TAXONOMIES, true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'wp-store-taxonomy-images',
            WP_STORE_URL . 'assets/admin/js/taxonomy-images.js',
            ['jquery', 'inline-edit-tax'],
            WP_STORE_VERSION,
            true
        );
        wp_enqueue_style(
            'wp-store-taxonomy-images',
            WP_STORE_URL . 'assets/admin/css/taxonomy-images.css',
            [],
            WP_STORE_VERSION
        );
    }

    public function render_add_field($taxonomy)
    {
        $this->render_field(0, false);
    }

    public function render_edit_field($term, $taxonomy = '')
    {
        $this->render_field((int) $term->term_id, true);
    }

    private function render_field($term_id, $is_edit)
    {
        $image_id = $term_id > 0 ? absint(get_term_meta($term_id, self::META_KEY, true)) : 0;
        $image_html = $image_id > 0
            ? wp_get_attachment_image($image_id, 'thumbnail', false, ['class' => 'wps-term-image-preview__image'])
            : '';

        if ($is_edit) {
            ?>
            <tr class="form-field wps-term-image-field">
                <th scope="row"><label for="wp-store-term-image-id">Gambar</label></th>
                <td><?php $this->render_field_controls($image_id, $image_html); ?></td>
            </tr>
            <?php
            return;
        }
        ?>
        <div class="form-field wps-term-image-field">
            <label for="wp-store-term-image-id">Gambar</label>
            <?php $this->render_field_controls($image_id, $image_html); ?>
        </div>
        <?php
    }

    private function render_field_controls($image_id, $image_html)
    {
        wp_nonce_field('wp_store_term_image', 'wp_store_term_image_nonce');
        ?>
        <input type="hidden" id="wp-store-term-image-id" name="wp_store_term_image_id" value="<?php echo esc_attr((string) $image_id); ?>">
        <div class="wps-term-image-preview <?php echo $image_html !== '' ? 'has-image' : ''; ?>">
            <span class="wps-term-image-preview__empty">Belum ada gambar</span>
            <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <p class="wps-term-image-controls">
            <button type="button" class="button wps-term-image-upload">Pilih gambar</button>
            <button type="button" class="button-link-delete wps-term-image-remove" <?php echo $image_html === '' ? 'hidden' : ''; ?>>Hapus gambar</button>
        </p>
        <p class="description">Gunakan gambar persegi agar tampil rapi pada carousel kategori atau brand.</p>
        <?php
    }

    public function save($term_id)
    {
        if (
            empty($_POST['wp_store_term_image_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_store_term_image_nonce'])), 'wp_store_term_image')
            || !current_user_can('manage_categories')
        ) {
            return;
        }

        $image_id = isset($_POST['wp_store_term_image_id']) ? absint($_POST['wp_store_term_image_id']) : 0;
        if ($image_id > 0 && wp_attachment_is_image($image_id)) {
            update_term_meta($term_id, self::META_KEY, $image_id);
            return;
        }

        delete_term_meta($term_id, self::META_KEY);
    }

    public function delete($term_id)
    {
        delete_term_meta($term_id, self::META_KEY);
    }

    public function render_quick_edit_field($column_name, $post_type, $taxonomy)
    {
        if ($column_name !== 'wp_store_term_image' || !in_array($taxonomy, self::TAXONOMIES, true)) {
            return;
        }
        ?>
        <fieldset class="inline-edit-col wps-term-image-field wps-term-image-field--quick">
            <div class="inline-edit-group">
                <label class="alignleft">
                    <span class="title">Gambar</span>
                    <span class="input-text-wrap">
                        <input type="hidden" name="wp_store_term_image_id" value="">
                        <?php wp_nonce_field('wp_store_term_image', 'wp_store_term_image_nonce', false); ?>
                        <span class="wps-term-image-preview">
                            <span class="wps-term-image-preview__empty">Belum ada gambar</span>
                        </span>
                        <span class="wps-term-image-actions">
                            <button type="button" class="button wps-term-image-upload">Pilih gambar</button>
                            <button type="button" class="button-link-delete wps-term-image-remove" hidden>Hapus gambar</button>
                        </span>
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    public function add_image_column($columns)
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'cb') {
                $result['wp_store_term_image'] = 'Gambar';
            }
        }

        return isset($result['wp_store_term_image']) ? $result : ['wp_store_term_image' => 'Gambar'] + $result;
    }

    public function render_image_column($content, $column_name, $term_id)
    {
        if ($column_name !== 'wp_store_term_image') {
            return $content;
        }

        $image_id = absint(get_term_meta($term_id, self::META_KEY, true));
        $attributes = [
            'class' => 'wps-term-image-column',
            'loading' => 'lazy',
            'alt' => '',
        ];
        $image_html = $image_id > 0
            ? wp_get_attachment_image($image_id, [48, 48], false, $attributes)
            : '<img class="wps-term-image-column wps-term-image-column--default" src="' . esc_url(WP_STORE_URL . 'assets/frontend/img/empty.png') . '" alt="">';

        return '<span class="wps-term-image-column-wrap" data-image-id="' . esc_attr((string) $image_id) . '">' . $image_html . '</span>';
    }
}
