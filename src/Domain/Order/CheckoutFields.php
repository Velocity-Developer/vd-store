<?php

namespace WpStore\Domain\Order;

/** Shared additional fields for the store and marketplace checkout. */
class CheckoutFields
{
    public static function definitions(): array
    {
        $fields = apply_filters('wp_store_checkout_fields', []);
        $result = [];
        foreach (is_array($fields) ? $fields : [] as $key => $field) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/', $key) || !is_array($field)) {
                continue;
            }
            $field = array_merge(['type' => 'text', 'label' => $key, 'required' => false, 'placeholder' => '', 'priority' => 10, 'options' => []], $field);
            if (!in_array($field['type'], ['text', 'textarea', 'email', 'tel', 'select', 'checkbox'], true)) {
                continue;
            }
            $field['options'] = is_array($field['options']) ? $field['options'] : [];
            $field['section'] = in_array($field['section'] ?? '', ['customer', 'address', 'notes', 'before_submit'], true)
                ? $field['section'] : 'notes';
            $result[$key] = $field;
        }
        uasort($result, static function ($a, $b) {
            return (int) $a['priority'] <=> (int) $b['priority'];
        });
        return $result;
    }

    /** Returns sanitized registered values, or a REST-compatible validation error. */
    public static function validate($submitted, bool $collect_address = true)
    {
        if (!is_array($submitted)) {
            return new \WP_Error('invalid_checkout_fields', 'Format field tambahan tidak valid.', ['status' => 400]);
        }
        $values = [];
        foreach (self::definitions() as $key => $field) {
            if ($field['section'] === 'address' && !$collect_address) {
                continue;
            }
            $raw = $submitted[$key] ?? '';
            if (!is_scalar($raw)) {
                return self::error($key, $field['label'] . ' tidak valid.');
            }
            $value = $field['type'] === 'textarea' ? sanitize_textarea_field((string) $raw) : sanitize_text_field((string) $raw);
            if ($field['type'] === 'checkbox') {
                if (!in_array($raw, ['', false, true, 0, 1, '0', '1'], true)) {
                    return self::error($key, $field['label'] . ' tidak valid.');
                }
                $value = in_array($raw, [true, 1, '1'], true) ? '1' : '0';
            }
            if (!empty($field['required']) && ($value === '' || ($field['type'] === 'checkbox' && $value !== '1'))) {
                return self::error($key, $field['label'] . ' wajib diisi.');
            }
            if ($value !== '' && $field['type'] === 'email' && !is_email($value)) {
                return self::error($key, $field['label'] . ' harus berupa email yang valid.');
            }
            if ($value !== '' && $field['type'] === 'select' && !array_key_exists($value, $field['options'])) {
                return self::error($key, $field['label'] . ' memiliki pilihan tidak valid.');
            }
            $values[$key] = $value;
        }
        return $values;
    }

    private static function error($key, $message)
    {
        return new \WP_Error('invalid_checkout_field', $message, ['status' => 400, 'field' => $key]);
    }

    public static function render($marketplace = false, string $section = 'notes'): void
    {
        foreach (self::definitions() as $key => $field) {
            if ($field['section'] !== $section) {
                continue;
            }
            $id = 'wp-store-checkout-' . $key;
            $type = $field['type'];
            $class = $marketplace ? ($type === 'select' ? 'form-select' : 'form-control') : ($type === 'textarea' ? 'wps-textarea' : 'wps-input');
            echo '<div class="' . ($marketplace ? 'col-12' : 'wps-form-group') . '"' . ($section === 'address' ? ' x-show="shouldCollectAddress()"' : '') . '>';
            echo '<label class="' . ($marketplace ? 'form-label' : 'wps-label') . '" for="' . esc_attr($id) . '">' . esc_html($field['label']) . (!empty($field['required']) ? ' *' : '') . '</label>';
            $attrs = ' id="' . esc_attr($id) . '" name="checkout_fields[' . esc_attr($key) . ']" data-wp-store-checkout-field="' . esc_attr($key) . '"';
            if ($section === 'address') {
                $attrs .= ' :disabled="!shouldCollectAddress()"';
                $attrs .= !empty($field['required']) ? ' :required="shouldCollectAddress()"' : '';
            } else {
                $attrs .= !empty($field['required']) ? ' required' : '';
            }
            if ($type === 'select') {
                echo '<select class="' . esc_attr($class) . '"' . $attrs . '><option value="">' . esc_html($field['placeholder'] ?: 'Pilih...') . '</option>';
                foreach ($field['options'] as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
                }
                echo '</select>';
            } elseif ($type === 'textarea') {
                echo '<textarea class="' . esc_attr($class) . '" rows="3" placeholder="' . esc_attr($field['placeholder']) . '"' . $attrs . '></textarea>';
            } else {
                echo '<input type="' . esc_attr($type) . '" class="' . esc_attr($type === 'checkbox' ? ($marketplace ? 'form-check-input' : '') : $class) . '" placeholder="' . esc_attr($field['placeholder']) . '"' . $attrs . ($type === 'checkbox' ? ' value="1"' : '') . '>';
            }
            echo '</div>';
        }
    }
}
