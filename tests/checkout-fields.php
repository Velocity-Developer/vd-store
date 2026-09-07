<?php
// Standalone contract checks: php tests/checkout-fields.php
class WP_Error {
    public $data;
    public function __construct($code, $message, $data) { $this->data = $data; }
}
function apply_filters($name, $default) { return $GLOBALS['fields']; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return esc_attr($value); }
require dirname(__DIR__) . '/src/Domain/Order/CheckoutFields.php';
use WpStore\Domain\Order\CheckoutFields;
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$GLOBALS['fields'] = [];
check(CheckoutFields::validate([]) === [], 'Empty registry must preserve existing checkout');
$GLOBALS['fields'] = [
    'company' => ['label' => '<Company>', 'required' => true, 'priority' => 30],
    'email' => ['type' => 'email'],
    'kind' => ['type' => 'select', 'options' => ['personal' => 'Personal']],
    'consent' => ['type' => 'checkbox', 'required' => true],
    'bad-key' => ['type' => 'text'],
];
$valid = ['company' => '<b>Acme</b>', 'email' => 'a@example.com', 'kind' => 'personal', 'consent' => '1', 'injected' => 'ignored'];
$values = CheckoutFields::validate($valid);
check($values['company'] === 'Acme' && !isset($values['injected']), 'Sanitize and whitelist values');
check(array_key_last(CheckoutFields::definitions()) === 'company', 'Sort by priority');
foreach ([['company', ''], ['company', []], ['email', 'invalid'], ['kind', 'forged'], ['consent', '0'], ['consent', 'yes']] as $case) {
    $input = $valid;
    $input[$case[0]] = $case[1];
    $error = CheckoutFields::validate($input);
    check($error instanceof WP_Error && $error->data['status'] === 400, 'Reject invalid ' . $case[0]);
}
check(CheckoutFields::validate('invalid') instanceof WP_Error, 'Reject malformed payload');
foreach ([false, true] as $marketplace) {
    ob_start();
    CheckoutFields::render($marketplace);
    $html = ob_get_clean();
    check(strpos($html, 'data-wp-store-checkout-field="company"') !== false, 'Both templates expose collection attributes');
    check(strpos($html, '&lt;Company&gt;') !== false, 'Escape labels');
    check(strpos($html, 'bad-key') === false, 'Ignore unsupported keys');
}
$GLOBALS['fields'] = [
    'legacy' => ['label' => 'Legacy'],
    'unknown' => ['section' => 'unknown'],
    'company' => ['section' => 'customer', 'priority' => 20],
    'contact' => ['section' => 'customer', 'priority' => 10],
    'building' => ['section' => 'address', 'required' => true],
    'consent' => ['section' => 'before_submit', 'type' => 'checkbox', 'required' => true],
];
check(CheckoutFields::definitions()['legacy']['section'] === 'notes', 'Legacy fields default to notes');
check(CheckoutFields::definitions()['unknown']['section'] === 'notes', 'Unknown sections fall back to notes');
$digital = CheckoutFields::validate(['consent' => '1', 'building' => ['forged']], false);
check(is_array($digital) && !isset($digital['building']), 'Hidden address values are ignored');
check(CheckoutFields::validate(['consent' => '1'], true) instanceof WP_Error, 'Physical address field remains required');
check(CheckoutFields::validate([], false) instanceof WP_Error, 'Non-address required fields still apply');
check(is_array(CheckoutFields::validate(['consent' => '1', 'building' => 'A'], true)), 'Physical checkout accepts filled address');
foreach ([false, true] as $marketplace) {
    $combined = '';
    foreach (['customer', 'address', 'notes', 'before_submit'] as $section) {
        ob_start();
        CheckoutFields::render($marketplace, $section);
        $html = ob_get_clean();
        $combined .= $html;
        foreach (CheckoutFields::definitions() as $key => $field) {
            check((strpos($html, 'data-wp-store-checkout-field="' . $key . '"') !== false) === ($field['section'] === $section), 'Render only requested section');
        }
        if ($section === 'address') {
            check(strpos($html, ':disabled="!shouldCollectAddress()"') !== false, 'Hidden address controls disabled');
            check(strpos($html, ':required="shouldCollectAddress()"') !== false, 'Address required state follows visibility');
        }
        if ($section === 'customer') {
            check(strpos($html, 'checkout-contact') < strpos($html, 'checkout-company'), 'Priority orders within section');
        }
    }
    check(substr_count($combined, 'data-wp-store-checkout-field=') === 6, 'Each field rendered exactly once');
}
echo "Checkout field contract checks passed.\n";
