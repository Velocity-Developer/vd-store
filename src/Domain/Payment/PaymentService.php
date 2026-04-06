<?php

namespace WpStore\Domain\Payment;

class PaymentService
{
    public function registry()
    {
        return new PaymentMethodRegistry();
    }

    public function initialize_order_payment($order_id, $payment_method, array $data, $total)
    {
        $order_id = (int) $order_id;
        $payment_method = sanitize_key((string) $payment_method);
        $total = (float) $total;

        $payment_info = [
            'payment_url' => '',
            'payment_token' => '',
            'expires_at' => 0,
            'extra' => new \stdClass(),
        ];

        if ($order_id <= 0) {
            return $payment_info;
        }

        if ($payment_method === 'duitku' && $this->registry()->is_available('duitku')) {
            $gateway_response = $this->create_duitku_payment($order_id, $data, $total);
            if (is_wp_error($gateway_response)) {
                return $gateway_response;
            }

            $payment_info = [
                'payment_url' => (string) ($gateway_response['paymentUrl'] ?? ''),
                'payment_token' => sanitize_text_field((string) ($gateway_response['reference'] ?? '')),
                'expires_at' => 0,
                'extra' => [
                    'gateway' => 'duitku',
                    'gateway_status' => sanitize_text_field((string) ($gateway_response['statusCode'] ?? 'pending')),
                    'reference' => sanitize_text_field((string) ($gateway_response['reference'] ?? '')),
                    'response' => $gateway_response,
                ],
            ];
        }

        $payment_info = apply_filters('wp_store_payment_init', $payment_info, $order_id, $payment_method, $data, $total);

        return is_array($payment_info) ? $payment_info : [
            'payment_url' => '',
            'payment_token' => '',
            'expires_at' => 0,
            'extra' => new \stdClass(),
        ];
    }

    public function handle_gateway_callback($gateway, array $payload)
    {
        $gateway = sanitize_key((string) $gateway);
        if ($gateway !== 'duitku') {
            return;
        }

        $invoice = sanitize_text_field((string) ($payload['merchantOrderId'] ?? ''));
        if ($invoice === '') {
            return;
        }

        $order_id = $this->find_order_by_invoice($invoice);
        if ($order_id <= 0) {
            return;
        }

        $normalized = [
            'gateway' => 'duitku',
            'invoice' => $invoice,
            'reference' => sanitize_text_field((string) ($payload['reference'] ?? '')),
            'payment_code' => sanitize_text_field((string) ($payload['paymentCode'] ?? '')),
            'amount' => (float) ($payload['amount'] ?? 0),
            'result_code' => sanitize_text_field((string) ($payload['resultCode'] ?? '')),
            'gateway_status' => sanitize_text_field((string) ($payload['resultCode'] ?? '')) === '00' ? 'paid' : 'failed',
            'callback_at' => current_time('mysql'),
        ];

        $this->persist_callback_payment_data($order_id, $normalized, $payload);
        do_action('wp_store_payment_callback_received', $order_id, $gateway, $normalized, $payload);

        if ($normalized['gateway_status'] === 'paid') {
            $success_status = (string) apply_filters('wp_store_payment_success_status', 'processing', $order_id, $gateway, $normalized, $payload);
            $success_status = $success_status !== '' ? $success_status : 'processing';

            $order_service = new \WpStore\Domain\Order\OrderService();
            $order_service->update_status($order_id, $success_status);

            do_action('wp_store_payment_completed', $order_id, $gateway, $normalized, $payload);
            return;
        }

        do_action('wp_store_payment_failed', $order_id, $gateway, $normalized, $payload);
    }

    private function create_duitku_payment($order_id, array $data, $total)
    {
        if (!DuitkuGateway::is_available()) {
            return new \WP_Error('duitku_not_available', __('Gateway Duitku tidak tersedia.', 'vd-store'));
        }

        $invoice = sanitize_text_field((string) ($data['order_number'] ?? get_post_meta($order_id, '_store_order_number', true)));
        if ($invoice === '') {
            $invoice = 'WPS-' . gmdate('Ymd') . '-' . (int) $order_id;
        }

        $customer_name = trim((string) ($data['name'] ?? 'Customer'));
        $email = sanitize_email((string) ($data['email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            $email = 'customer+' . strtolower($invoice) . '@example.com';
        }

        $phone = preg_replace('/[^0-9]/', '', (string) ($data['phone'] ?? ''));
        $first_name = $customer_name !== '' ? explode(' ', $customer_name)[0] : 'Customer';
        $last_name = '';
        if ($customer_name !== '') {
            $name_parts = preg_split('/\s+/', $customer_name);
            $first_name = !empty($name_parts[0]) ? (string) $name_parts[0] : 'Customer';
            $last_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';
        }

        $address = [
            'firstName' => $first_name,
            'lastName' => $last_name,
            'address' => (string) ($data['address'] ?? ''),
            'city' => (string) ($data['city_name'] ?? ''),
            'postalCode' => (string) ($data['postal_code'] ?? ''),
            'phone' => $phone,
            'countryCode' => 'ID',
        ];

        $item_details = [];
        foreach ((array) ($data['items'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }
            $item_details[] = [
                'name' => (string) ($line['title'] ?? 'Produk'),
                'price' => (int) round((float) ($line['price'] ?? 0)),
                'quantity' => max(1, (int) ($line['qty'] ?? 1)),
            ];
        }

        $return_url = trim((string) ($data['return_url'] ?? ''));
        $callback_url = trim((string) ($data['callback_url'] ?? ''));
        $expiry_period = max(1, (int) ($data['expiry_period'] ?? 60));

        return DuitkuGateway::create_invoice([
            'paymentAmount' => (int) round((float) $total),
            'merchantOrderId' => $invoice,
            'productDetails' => 'Pembayaran pesanan ' . $invoice,
            'additionalParam' => (string) $order_id,
            'merchantUserInfo' => (string) ($data['user_id'] ?? get_post_meta($order_id, '_store_order_user_id', true)),
            'customerVaName' => $customer_name !== '' ? $customer_name : 'Customer',
            'email' => $email,
            'phoneNumber' => $phone,
            'itemDetails' => $item_details,
            'customerDetail' => [
                'firstName' => $first_name,
                'lastName' => $last_name,
                'email' => $email,
                'phoneNumber' => $phone,
                'billingAddress' => $address,
                'shippingAddress' => $address,
            ],
            'callbackUrl' => $callback_url,
            'returnUrl' => $return_url,
            'expiryPeriod' => $expiry_period,
        ]);
    }

    private function persist_callback_payment_data($order_id, array $normalized, array $payload)
    {
        $order_service = new \WpStore\Domain\Order\OrderService();
        $extra = get_post_meta($order_id, '_store_order_payment_extra', true);
        if (!is_array($extra)) {
            $extra = [];
        }

        $extra['gateway'] = (string) $normalized['gateway'];
        $extra['gateway_status'] = (string) $normalized['gateway_status'];
        $extra['reference'] = (string) $normalized['reference'];
        $extra['payment_code'] = (string) $normalized['payment_code'];
        $extra['amount'] = (float) $normalized['amount'];
        $extra['result_code'] = (string) $normalized['result_code'];
        $extra['callback_at'] = (string) $normalized['callback_at'];
        $extra['callback_payload'] = $payload;

        $order_service->update_payment_data($order_id, [
            'payment_url' => (string) get_post_meta($order_id, '_store_order_payment_url', true),
            'payment_token' => (string) get_post_meta($order_id, '_store_order_payment_token', true),
            'expires_at' => (int) get_post_meta($order_id, '_store_order_payment_expires_at', true),
            'extra' => $extra,
        ]);
    }

    private function find_order_by_invoice($invoice)
    {
        $invoice = sanitize_text_field((string) $invoice);
        if ($invoice === '') {
            return 0;
        }

        $orders = get_posts([
            'post_type' => 'store_order',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_store_order_number',
                    'value' => $invoice,
                ],
                [
                    'key' => 'vmp_invoice',
                    'value' => $invoice,
                ],
            ],
        ]);

        return !empty($orders[0]) ? (int) $orders[0] : 0;
    }
}
