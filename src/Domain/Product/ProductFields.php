<?php

namespace WpStore\Domain\Product;

class ProductFields
{
    public function register()
    {
        add_action('init', [$this, 'register_post_meta']);
    }

    public static function get_sections($context = 'frontend')
    {
        return ProductSchema::sections($context);
    }

    public function register_post_meta()
    {
        $registered = [];
        $fields = array_merge(self::get_fields('admin'), self::get_fields('frontend'));

        foreach ($fields as $field) {
            $meta_key = (string) $field['id'];
            if ($meta_key === '' || isset($registered[$meta_key])) {
                continue;
            }

            $registered[$meta_key] = true;
            $show_in_rest = true;

            if ((($field['type'] ?? '') === 'image' || ($field['type'] ?? '') === 'file') && !empty($field['multiple'])) {
                $show_in_rest = [
                    'schema' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                ];
            } elseif (($field['type'] ?? '') === 'file_url') {
                $show_in_rest = [
                    'schema' => [
                        'type' => 'string',
                    ],
                ];
            } elseif (in_array($meta_key, ['_store_options', '_store_advanced_options'], true)) {
                $show_in_rest = [
                    'schema' => [
                        'type' => 'array',
                    ],
                ];
            }

            register_post_meta('store_product', $meta_key, [
                'single' => true,
                'show_in_rest' => $show_in_rest,
                'type' => self::register_type($field),
                'sanitize_callback' => function ($value) use ($field) {
                    return self::sanitize_value($field, $value);
                },
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }

    public static function get_fields($context = 'frontend')
    {
        $fields = [];
        foreach (self::get_sections($context) as $section) {
            foreach ((array) $section['fields'] as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public static function get_value($post_id, $field)
    {
        $meta_key = (string) ($field['id'] ?? '');
        $default = $field['default'] ?? '';
        $type = (string) ($field['type'] ?? 'text');
        $value = $meta_key !== '' ? get_post_meta((int) $post_id, $meta_key, true) : '';

        if (($value === '' || $value === null) && $default !== '') {
            return (string) $default;
        }

        if ($meta_key === '_store_options' && is_array($value)) {
            return implode(', ', array_values(array_filter(array_map('trim', array_map('strval', $value)))));
        }

        if ($meta_key === '_store_advanced_options' && is_array($value)) {
            $rows = [];
            foreach ($value as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = isset($row['label']) ? trim((string) $row['label']) : '';
                if ($label === '') {
                    continue;
                }
                $price = isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : 0;
                $rows[] = $label . '=' . $price;
            }
            return implode("\n", $rows);
        }

        if ($type === 'checkbox') {
            return !empty($value) ? '1' : '0';
        }

        if (($type === 'image' || $type === 'file') && !empty($field['multiple'])) {
            return self::normalize_attachment_ids($value);
        }

        if ($type === 'image') {
            return is_numeric($value) ? (int) $value : 0;
        }

        if ($type === 'file_url') {
            if (is_numeric($value)) {
                $url = wp_get_attachment_url((int) $value);
                return $url ? (string) $url : '';
            }

            return is_scalar($value) ? (string) $value : '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    public static function render_sections($post_id = 0, $context = 'frontend')
    {
        $html = '';
        foreach (self::get_sections($context) as $section) {
            $html .= '<div class="vmp-meta-section">';
            $html .= '<h4 class="vmp-meta-section__title">' . esc_html((string) $section['title']) . '</h4>';
            $html .= '<div class="row g-2">';
            foreach ((array) $section['fields'] as $field) {
                $html .= self::render_field($field, $post_id, $context);
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    public static function render_field($field, $post_id = 0, $context = 'frontend')
    {
        $field = is_array($field) ? $field : [];
        $meta_key = (string) ($field['id'] ?? '');
        if ($meta_key === '') {
            return '';
        }

        $type = (string) ($field['type'] ?? 'text');
        $value = self::get_value($post_id, $field);
        $label = (string) ($field['name'] ?? $meta_key);
        $placeholder = (string) ($field['placeholder'] ?? '');
        $desc = (string) ($field['desc'] ?? '');
        $required = !empty($field['required']);
        $min = isset($field['min']) && $field['min'] !== '' ? ' min="' . esc_attr((string) $field['min']) . '"' : '';
        $step = isset($field['step']) && $field['step'] !== '' ? ' step="' . esc_attr((string) $field['step']) . '"' : '';
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr($placeholder) . '"' : '';
        $required_attr = $required ? ' required' : '';
        $col_class = !empty($field['full_width']) ? 'col-12' : 'col-md-6';
        $show_if_product_type = isset($field['show_if_product_type']) ? sanitize_key((string) $field['show_if_product_type']) : '';
        $current_product_type = self::current_product_type($post_id);
        $is_conditionally_visible = self::field_visible_for_product_type($field, $current_product_type);
        $wrapper_attrs = '';
        if ($show_if_product_type !== '') {
            $wrapper_attrs .= ' data-show-if-product-type="' . esc_attr($show_if_product_type) . '"';
            if (!$is_conditionally_visible) {
                $wrapper_attrs .= ' style="display:none;"';
            }
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($col_class); ?>"<?php echo $wrapper_attrs; ?>>
            <?php if ($type === 'checkbox') : ?>
                <input type="hidden" name="<?php echo esc_attr($meta_key); ?>" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" value="1" <?php checked($value, '1'); ?>>
                    <label class="form-check-label" for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label>
                    <?php if ($desc !== '') : ?><div class="form-text"><?php echo esc_html($desc); ?></div><?php endif; ?>
                </div>
            <?php else : ?>
                <label class="form-label" for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label>
                <?php if ($type === 'textarea') : ?>
                    <textarea id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" class="form-control" rows="<?php echo esc_attr((string) ($field['rows'] ?? 4)); ?>"<?php echo $placeholder_attr; ?><?php echo $required_attr; ?>><?php echo esc_textarea($value); ?></textarea>
                <?php elseif ($type === 'select') : ?>
                    <select id="<?php echo esc_attr($meta_key); ?>" name="<?php echo esc_attr($meta_key); ?>" class="form-select"<?php echo $required_attr; ?>>
                        <?php foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) : ?>
                            <option value="<?php echo esc_attr((string) $option_value); ?>" <?php selected($value, (string) $option_value); ?>><?php echo esc_html((string) $option_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'radio') : ?>
                    <div>
                        <?php foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) : ?>
                            <label class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr((string) $option_value); ?>" <?php checked($value, (string) $option_value); ?>>
                                <span class="form-check-label"><?php echo esc_html((string) $option_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($type === 'editor') : ?>
                    <?php
                    wp_editor($value, 'editor_' . $meta_key . '_' . $context, [
                        'textarea_name' => $meta_key,
                        'textarea_rows' => (int) ($field['rows'] ?? 6),
                        'teeny' => true,
                        'media_buttons' => false,
                    ]);
                    ?>
                <?php elseif (($type === 'image' || $type === 'file') && !empty($field['media_library'])) : ?>
                    <?php
                    $open_button_class = $context === 'admin' ? 'button button-secondary vmp-media-field__open' : 'btn btn-outline-dark btn-sm vmp-media-field__open';
                    $clear_button_class = $context === 'admin' ? 'button button-secondary vmp-media-field__clear' : 'btn btn-outline-secondary btn-sm vmp-media-field__clear';
                    $preview_items = self::build_media_preview_items($value, !empty($field['multiple']));
                    $input_value = '';
                    if (!empty($field['multiple'])) {
                        $input_value = implode(',', array_map('intval', (array) $value));
                    } elseif (!empty($value)) {
                        $input_value = (string) ((int) $value);
                    }
                    ?>
                    <div class="vmp-media-field" data-multiple="<?php echo !empty($field['multiple']) ? '1' : '0'; ?>">
                        <input id="<?php echo esc_attr($meta_key); ?>" type="hidden" name="<?php echo esc_attr($meta_key); ?>" class="vmp-media-field__input" value="<?php echo esc_attr($input_value); ?>">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" class="<?php echo esc_attr($open_button_class); ?>" data-title="<?php echo esc_attr($label); ?>" data-button="<?php echo esc_attr(!empty($field['multiple']) ? 'Gunakan gambar terpilih' : 'Gunakan gambar ini'); ?>">
                                <?php echo esc_html(!empty($field['multiple']) ? 'Pilih Galeri' : 'Pilih File'); ?>
                            </button>
                            <button type="button" class="<?php echo esc_attr($clear_button_class); ?>" <?php disabled(empty($preview_items)); ?>>Hapus Pilihan</button>
                        </div>
                        <div class="vmp-media-field__preview" data-placeholder="<?php echo esc_attr(!empty($field['multiple']) ? 'Belum ada gambar galeri.' : 'Belum ada gambar dipilih.'); ?>">
                            <?php echo self::render_media_preview_html($preview_items, !empty($field['multiple'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                <?php elseif ($type === 'file_url' && !empty($field['media_library'])) : ?>
                    <?php
                    $open_button_class = $context === 'admin' ? 'button button-secondary vmp-file-link-field__open' : 'btn btn-outline-dark btn-sm vmp-file-link-field__open';
                    $clear_button_class = $context === 'admin' ? 'button button-secondary vmp-file-link-field__clear' : 'btn btn-outline-secondary btn-sm vmp-file-link-field__clear';
                    $file_url = is_string($value) ? $value : '';
                    ?>
                    <div class="vmp-file-link-field">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" class="<?php echo esc_attr($open_button_class); ?>" data-title="<?php echo esc_attr($label); ?>" data-button="<?php echo esc_attr('Gunakan file ini'); ?>">
                                <?php echo esc_html('Pilih File'); ?>
                            </button>
                            <button type="button" class="<?php echo esc_attr($clear_button_class); ?>" <?php disabled($file_url === ''); ?>>Hapus Pilihan</button>
                        </div>
                        <input id="<?php echo esc_attr($meta_key); ?>" type="url" name="<?php echo esc_attr($meta_key); ?>" class="form-control vmp-file-link-field__input" value="<?php echo esc_attr($file_url); ?>" placeholder="<?php echo esc_attr('https://contoh.com/file.zip'); ?>">
                        <div class="vmp-file-link-field__preview" data-placeholder="<?php echo esc_attr('Belum ada file dipilih.'); ?>">
                            <?php echo self::render_file_link_preview_html($file_url); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                <?php elseif ($type === 'file') : ?>
                    <input id="<?php echo esc_attr($meta_key); ?>" type="file" name="<?php echo esc_attr($meta_key); ?>" class="form-control">
                    <?php if (!empty($value)) : ?><div class="form-text"><?php echo esc_html((string) $value); ?></div><?php endif; ?>
                <?php else : ?>
                    <?php $html_type = in_array($type, ['text', 'number', 'email', 'url', 'date'], true) ? $type : 'text'; ?>
                    <input id="<?php echo esc_attr($meta_key); ?>" type="<?php echo esc_attr($html_type); ?>" name="<?php echo esc_attr($meta_key); ?>" class="form-control" value="<?php echo esc_attr($value); ?>"<?php echo $placeholder_attr; ?><?php echo $min; ?><?php echo $step; ?><?php echo $required_attr; ?>>
                <?php endif; ?>
                <?php if ($desc !== '' && $type !== 'checkbox') : ?><div class="form-text"><?php echo esc_html($desc); ?></div><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function save($post_id, $context = 'frontend')
    {
        foreach (self::get_fields($context) as $field) {
            $meta_key = (string) ($field['id'] ?? '');
            if ($meta_key === '') {
                continue;
            }

            if (in_array(($field['type'] ?? 'text'), ['file', 'image', 'file_url'], true)) {
                self::save_file_field($post_id, $field);
                continue;
            }

            $raw = isset($_POST[$meta_key]) ? wp_unslash($_POST[$meta_key]) : '';
            $value = self::sanitize_value($field, $raw);
            update_post_meta($post_id, $meta_key, $value);
        }
    }

    public static function validate_submission($context = 'frontend')
    {
        $submitted_product_type = isset($_POST['_store_product_type'])
            ? sanitize_key((string) wp_unslash($_POST['_store_product_type']))
            : 'physical';

        foreach (self::get_fields($context) as $field) {
            $meta_key = (string) ($field['id'] ?? '');
            if ($meta_key === '' || empty($field['required'])) {
                continue;
            }

             if (!self::field_visible_for_product_type($field, $submitted_product_type)) {
                continue;
            }

            $raw = isset($_POST[$meta_key]) ? wp_unslash($_POST[$meta_key]) : '';
            $label = (string) ($field['name'] ?? $meta_key);
            $type = (string) ($field['type'] ?? 'text');

            if ($type === 'number') {
                if ($raw === '' || !is_numeric($raw)) {
                    return new \WP_Error('required_field', sprintf(__('%s wajib diisi.', 'vd-store'), $label));
                }

                $value = (float) $raw;
                $min = isset($field['min']) && is_numeric($field['min']) ? (float) $field['min'] : null;
                if ($min !== null && $value < $min) {
                    return new \WP_Error('min_field', sprintf(__('%s harus lebih besar dari 0 untuk menghitung ongkir.', 'vd-store'), $label));
                }

                continue;
            }

            if (is_string($raw)) {
                $raw = trim($raw);
            }

            if ($raw === '' || $raw === null || $raw === []) {
                return new \WP_Error('required_field', sprintf(__('%s wajib diisi.', 'vd-store'), $label));
            }
        }

        return true;
    }

    private static function current_product_type($post_id = 0)
    {
        if (isset($_POST['_store_product_type'])) {
            $posted = sanitize_key((string) wp_unslash($_POST['_store_product_type']));
            if (in_array($posted, ['physical', 'digital'], true)) {
                return $posted;
            }
        }

        $post_id = (int) $post_id;
        if ($post_id > 0) {
            $stored = sanitize_key((string) get_post_meta($post_id, '_store_product_type', true));
            if (in_array($stored, ['physical', 'digital'], true)) {
                return $stored;
            }
        }

        return 'physical';
    }

    private static function field_visible_for_product_type($field, $product_type)
    {
        $required_type = isset($field['show_if_product_type']) ? sanitize_key((string) $field['show_if_product_type']) : '';
        if ($required_type === '') {
            return true;
        }

        $product_type = sanitize_key((string) $product_type);
        if (!in_array($product_type, ['physical', 'digital'], true)) {
            $product_type = 'physical';
        }

        return $required_type === $product_type;
    }

    public static function sanitize_value($field, $raw)
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'number') {
            return ($raw === '' || !is_numeric($raw)) ? '' : $raw + 0;
        }

        if ($type === 'email') {
            return sanitize_email((string) $raw);
        }

        if ($type === 'url') {
            return esc_url_raw((string) $raw);
        }

        if ($type === 'checkbox') {
            return !empty($raw) ? '1' : '0';
        }

        if ($type === 'select' || $type === 'radio') {
            $value = sanitize_text_field((string) $raw);
            $options = isset($field['options']) && is_array($field['options']) ? array_map('strval', array_keys($field['options'])) : [];
            if (!in_array($value, $options, true)) {
                return isset($field['default']) ? (string) $field['default'] : '';
            }
            return $value;
        }

        if ($type === 'textarea' || $type === 'editor') {
            $meta_key = (string) ($field['id'] ?? '');
            if ($meta_key === '_store_options') {
                $parts = array_map('trim', explode(',', (string) $raw));
                return array_values(array_filter($parts, static function ($item) {
                    return $item !== '';
                }));
            }

            if ($meta_key === '_store_advanced_options') {
                $rows = preg_split('/\r\n|\r|\n/', (string) $raw);
                $items = [];
                foreach ((array) $rows as $row) {
                    $line = trim((string) $row);
                    if ($line === '') {
                        continue;
                    }

                    $parts = strpos($line, '=') !== false ? array_map('trim', explode('=', $line, 2)) : [$line, 0];
                    $label = isset($parts[0]) ? (string) $parts[0] : '';
                    if ($label === '') {
                        continue;
                    }

                    $items[] = [
                        'label' => $label,
                        'price' => isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 0.0,
                    ];
                }

                return $items;
            }

            return trim((string) wp_kses_post($raw));
        }

        if ($type === 'image' || $type === 'file') {
            if (!empty($field['multiple'])) {
                return self::filter_attachment_ids_for_current_user(self::normalize_attachment_ids($raw));
            }

            $attachment_id = is_numeric($raw) ? (int) $raw : 0;
            return self::attachment_allowed_for_current_user($attachment_id) ? $attachment_id : 0;
        }

        if ($type === 'file_url') {
            if (is_numeric($raw)) {
                $url = wp_get_attachment_url((int) $raw);
                return $url ? esc_url_raw($url) : '';
            }

            return esc_url_raw((string) $raw);
        }

        if ($type === 'date') {
            return sanitize_text_field((string) $raw);
        }

        return sanitize_text_field((string) $raw);
    }

    private static function save_file_field($post_id, $field)
    {
        $meta_key = (string) ($field['id'] ?? '');
        if ($meta_key === '') {
            return;
        }

        if (!empty($field['media_library']) && array_key_exists($meta_key, $_POST)) {
            $raw = wp_unslash($_POST[$meta_key]);
            $value = self::sanitize_value($field, $raw);

            if ((is_array($value) && empty($value)) || (!is_array($value) && empty($value))) {
                delete_post_meta($post_id, $meta_key);
                return;
            }

            update_post_meta($post_id, $meta_key, $value);
            return;
        }

        if (($field['type'] ?? '') === 'file_url') {
            $raw = array_key_exists($meta_key, $_POST) ? wp_unslash($_POST[$meta_key]) : '';
            $value = self::sanitize_value($field, $raw);
            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
                return;
            }

            update_post_meta($post_id, $meta_key, $value);
            return;
        }

        if (empty($_FILES[$meta_key]) || empty($_FILES[$meta_key]['tmp_name'])) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_id = media_handle_upload($meta_key, $post_id);
        if (!is_wp_error($attach_id) && $attach_id) {
            update_post_meta($post_id, $meta_key, (int) $attach_id);
        }
    }

    private static function register_type($field)
    {
        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'number') {
            return 'number';
        }
        if ($type === 'checkbox') {
            return 'boolean';
        }
        if (in_array((string) ($field['id'] ?? ''), ['_store_options', '_store_advanced_options'], true)) {
            return 'array';
        }
        if (($type === 'image' || $type === 'file') && !empty($field['multiple'])) {
            return 'array';
        }
        if ($type === 'image') {
            return 'integer';
        }
        if ($type === 'file_url') {
            return 'string';
        }

        return 'string';
    }

    private static function normalize_attachment_ids($raw)
    {
        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw)));
    }

    private static function filter_attachment_ids_for_current_user($ids)
    {
        $ids = is_array($ids) ? $ids : [];
        return array_values(array_filter($ids, [self::class, 'attachment_allowed_for_current_user']));
    }

    private static function attachment_allowed_for_current_user($attachment_id)
    {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return (int) get_post_field('post_author', $attachment_id) === get_current_user_id();
    }

    private static function build_media_preview_items($value, $multiple = false)
    {
        $ids = $multiple ? self::normalize_attachment_ids($value) : [is_numeric($value) ? (int) $value : 0];
        $items = [];

        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            $thumb = wp_get_attachment_image_url($id, 'medium');
            $full = wp_get_attachment_url($id);
            if (!$thumb && !$full) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'thumb' => $thumb ? $thumb : $full,
                'full' => $full ? $full : $thumb,
                'title' => get_the_title($id),
            ];
        }

        return $items;
    }

    private static function render_media_preview_html($items, $multiple = false)
    {
        if (empty($items)) {
            return '<div class="vmp-media-field__empty text-muted small">Belum ada gambar dipilih.</div>';
        }

        ob_start();
        ?>
        <div class="vmp-media-field__grid<?php echo $multiple ? '' : ' vmp-media-field__grid--single'; ?>">
            <?php foreach ((array) $items as $item) : ?>
                <div class="vmp-media-field__item" data-id="<?php echo esc_attr((string) $item['id']); ?>">
                    <img src="<?php echo esc_url((string) $item['thumb']); ?>" alt="<?php echo esc_attr((string) $item['title']); ?>" class="vmp-media-field__image">
                    <button type="button" class="btn-close vmp-media-field__remove" aria-label="Hapus gambar"></button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_file_link_preview_html($url)
    {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return '<div class="vmp-file-link-field__empty text-muted small">Belum ada file dipilih.</div>';
        }

        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $label = basename($path);
        if ($label === '' || $label === '/' || $label === '\\') {
            $label = $url;
        }

        ob_start();
        ?>
        <div class="vmp-file-link-field__summary">
            <div class="vmp-file-link-field__name"><?php echo esc_html($label); ?></div>
            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" class="vmp-file-link-field__link"><?php echo esc_html($url); ?></a>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
