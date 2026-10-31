<?php

namespace WpStore\Domain\Payment;

class PaymentMethodRegistry
{
    public function all()
    {
        $methods = [
            'bank_transfer' => [
                'label' => 'Bank Transfer',
                'available' => true,
            ],
            'qris' => [
                'label' => 'QRIS',
                'available' => true,
            ],
            'duitku' => [
                'label' => 'Duitku',
                'available' => DuitkuGateway::is_available(),
            ],
            'doku' => [
                'label' => 'DOKU',
                'available' => VdPaymentGateway::is_available('doku'),
            ],
            'cod' => [
                'label' => 'COD',
                'available' => true,
            ],
            'paypal' => [
                'label' => 'PayPal',
                'available' => true,
            ],
        ];

        $methods = apply_filters('wp_store_payment_methods_registry', $methods);

        return is_array($methods) ? $methods : [];
    }

    public function available_methods()
    {
        $methods = [];
        foreach ($this->all() as $key => $config) {
            $available = !empty($config['available']);
            if ($available) {
                $methods[] = sanitize_key((string) $key);
            }
        }

        return array_values(array_unique(array_filter($methods)));
    }

    public function is_available($method)
    {
        $method = sanitize_key((string) $method);
        $all = $this->all();

        return !empty($all[$method]['available']);
    }

    public function label($method)
    {
        $all = $this->all();
        $method = sanitize_key((string) $method);

        return isset($all[$method]['label']) ? (string) $all[$method]['label'] : ucwords(str_replace('_', ' ', $method));
    }
}
