<?php

namespace WpStore\Domain\Payment;

class DuitkuCallbackListener
{
    public function register()
    {
        add_action('velocity_duitku_callback', [$this, 'handle_callback']);
    }

    public function handle_callback($payload)
    {
        if (!is_array($payload)) {
            return;
        }

        $service = new PaymentService();
        $service->handle_gateway_callback('duitku', $payload);
    }
}
