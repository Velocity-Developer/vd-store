<?php

namespace WpStore\Admin;

class OrderEmails
{
    public function register()
    {
        add_action('updated_post_meta', [$this, 'maybe_send_status_email'], 10, 4);
        add_action('wp_store_order_created', [$this, 'send_new_order_emails'], 10, 4);
    }

    public function maybe_send_status_email($meta_id, $post_id, $meta_key, $meta_value)
    {
        if ($meta_key !== '_store_order_status') {
            return;
        }
        if (get_post_type($post_id) !== 'store_order') {
            return;
        }
        $email = get_post_meta($post_id, '_store_order_email', true);
        if (!is_string($email) || $email === '' || !is_email($email)) {
            return;
        }
        $settings = get_option('wp_store_settings', []);
        $store_name = isset($settings['store_name']) && is_string($settings['store_name']) && $settings['store_name'] !== '' ? $settings['store_name'] : get_bloginfo('name');
        $order_number = get_post_meta($post_id, '_store_order_number', true);
        $order_number = $order_number ? $order_number : $post_id;
        $labels = [
            'pending' => 'Pending',
            'awaiting_payment' => 'Menunggu Pembayaran',
            'paid' => 'Sudah Dibayar',
            'processing' => 'Sedang Diproses',
            'shipped' => 'Dikirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];
        $status_label = isset($labels[$meta_value]) ? $labels[$meta_value] : $meta_value;
        $tracking_id = isset($settings['page_tracking']) ? (int) $settings['page_tracking'] : 0;
        $tracking_url = $tracking_id ? get_permalink($tracking_id) : site_url('/tracking-order/');
        $subject = '[' . $store_name . '] Status Pesanan #' . $order_number . ': ' . $status_label;
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $vars = [
            'store_name' => $store_name,
            'order_number' => (string) $order_number,
            'status_label' => $status_label,
            'tracking_url' => esc_url(add_query_arg(['order' => $order_number], $tracking_url)),
        ];
        $user_tmpl = isset($settings['email_template_user_status']) && is_string($settings['email_template_user_status']) ? $settings['email_template_user_status'] : '';
        if ($user_tmpl === '') {
            $user_tmpl = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                . '<p>Halo,</p>'
                . '<p>Status pesanan #{{order_number}} Anda kini: <strong>{{status_label}}</strong>.</p>'
                . '<p>Anda dapat melihat detail dan riwayat pengiriman melalui tautan berikut:</p>'
                . '<p><a href="{{tracking_url}}" target="_blank" rel="noopener">Lihat Status Pesanan</a></p>'
                . '<p>Salam,<br>{{store_name}}</p>'
                . '</div>';
        }
        $body = $this->render_template($user_tmpl, $vars);
        $from_email = isset($settings['store_email_from']) && is_email($settings['store_email_from']) ? $settings['store_email_from'] : (isset($settings['store_email']) && is_email($settings['store_email']) ? $settings['store_email'] : get_bloginfo('admin_email'));
        $from_name = $store_name;
        $cb_from = function ($e) use ($from_email) {
            return $from_email;
        };
        $cb_name = function ($n) use ($from_name) {
            return $from_name;
        };
        add_filter('wp_mail_from', $cb_from);
        add_filter('wp_mail_from_name', $cb_name);
        wp_mail($email, $subject, $body, $headers);
        remove_filter('wp_mail_from', $cb_from);
        remove_filter('wp_mail_from_name', $cb_name);

        $admin_email = isset($settings['store_email_admin']) && is_email($settings['store_email_admin']) ? $settings['store_email_admin'] : (isset($settings['store_email']) && is_email($settings['store_email']) ? $settings['store_email'] : get_bloginfo('admin_email'));
        $admin_tmpl = isset($settings['email_template_admin_status']) && is_string($settings['email_template_admin_status']) ? $settings['email_template_admin_status'] : '';
        if ($admin_tmpl === '') {
            $admin_tmpl = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                . '<p>Status order #{{order_number}}: <strong>{{status_label}}</strong>.</p>'
                . '<p>Tracking: <a href="{{tracking_url}}" target="_blank" rel="noopener">{{tracking_url}}</a></p>'
                . '</div>';
        }
        if (is_email($admin_email)) {
            $admin_subject = '[' . $store_name . '] Status Order #' . $order_number . ': ' . $status_label;
            $admin_body = $this->render_template($admin_tmpl, $vars);
            $from_email = isset($settings['store_email_from']) && is_email($settings['store_email_from']) ? $settings['store_email_from'] : (isset($settings['store_email']) && is_email($settings['store_email']) ? $settings['store_email'] : get_bloginfo('admin_email'));
            $from_name = $store_name;
            $cb_from = function ($e) use ($from_email) {
                return $from_email;
            };
            $cb_name = function ($n) use ($from_name) {
                return $from_name;
            };
            add_filter('wp_mail_from', $cb_from);
            add_filter('wp_mail_from_name', $cb_name);
            wp_mail($admin_email, $admin_subject, $admin_body, $headers);
            remove_filter('wp_mail_from', $cb_from);
            remove_filter('wp_mail_from_name', $cb_name);
        }
    }

    public function send_new_order_emails($order_id, $data, $lines, $order_total)
    {
        if (get_post_type($order_id) !== 'store_order') {
            return;
        }
        $settings = get_option('wp_store_settings', []);
        $store_name = isset($settings['store_name']) && is_string($settings['store_name']) && $settings['store_name'] !== '' ? $settings['store_name'] : get_bloginfo('name');
        $order_number = get_post_meta($order_id, '_store_order_number', true);
        $order_number = $order_number ? $order_number : $order_id;
        $tracking_id = isset($settings['page_tracking']) ? (int) $settings['page_tracking'] : 0;
        $tracking_url = $tracking_id ? get_permalink($tracking_id) : site_url('/tracking-order/');
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $vars = [
            'store_name' => $store_name,
            'order_number' => (string) $order_number,
            'status_label' => get_post_meta($order_id, '_store_order_status', true) ?: '',
            'tracking_url' => esc_url(add_query_arg(['order' => $order_number], $tracking_url)),
            'total' => (string) $order_total,
        ];
        $email = get_post_meta($order_id, '_store_order_email', true);
        if (is_string($email) && is_email($email)) {
            $user_subject = '[' . $store_name . '] Pesanan Baru #' . $order_number;
            $user_tmpl = isset($settings['email_template_user_new_order']) && is_string($settings['email_template_user_new_order']) ? $settings['email_template_user_new_order'] : '';
            if ($user_tmpl === '') {
                $user_tmpl = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                    . '<p>Halo,</p>'
                    . '<p>Terima kasih. Pesanan #{{order_number}} telah kami terima.</p>'
                    . '<p>Anda dapat memantau status pesanan melalui tautan berikut:</p>'
                    . '<p><a href="{{tracking_url}}" target="_blank" rel="noopener">Lihat Status Pesanan</a></p>'
                    . '<p>Salam,<br>{{store_name}}</p>'
                    . '</div>';
            }
            $user_body = $this->render_template($user_tmpl, $vars);
            $from_email = isset($settings['store_email_from']) && is_email($settings['store_email_from']) ? $settings['store_email_from'] : (isset($settings['store_email']) && is_email($settings['store_email']) ? $settings['store_email'] : get_bloginfo('admin_email'));
            $from_name = $store_name;
            $cb_from = function ($e) use ($from_email) {
                return $from_email;
            };
            $cb_name = function ($n) use ($from_name) {
                return $from_name;
            };
            add_filter('wp_mail_from', $cb_from);
            add_filter('wp_mail_from_name', $cb_name);
            wp_mail($email, $user_subject, $user_body, $headers);
            remove_filter('wp_mail_from', $cb_from);
            remove_filter('wp_mail_from_name', $cb_name);
        }
        $admin_email = isset($settings['store_email_admin']) && is_email($settings['store_email_admin']) ? $settings['store_email_admin'] : (isset($settings['store_email']) && is_email($settings['store_email']) ? $settings['store_email'] : get_bloginfo('admin_email'));
        if (is_email($admin_email)) {
            $admin_subject = '[' . $store_name . '] Order Baru #' . $order_number;
            $admin_tmpl = isset($settings['email_template_admin_new_order']) && is_string($settings['email_template_admin_new_order']) ? $settings['email_template_admin_new_order'] : '';
            if ($admin_tmpl === '') {
                $admin_tmpl = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                    . '<p>Order baru #{{order_number}}.</p>'
                    . '<p>Total: {{total}}</p>'
                    . '<p>Tracking: <a href="{{tracking_url}}" target="_blank" rel="noopener">{{tracking_url}}</a></p>'
                    . '</div>';
            }
            $admin_body = $this->render_template($admin_tmpl, $vars);
            $from_email = isset($settings['store_email_from']) && is_email($settings['store_email_from']) ? $settings['store_email_from'] : (isset($settings['store_email']) && is_email($settings['store_email']) ? $settings['store_email'] : get_bloginfo('admin_email'));
            $from_name = $store_name;
            $cb_from = function ($e) use ($from_email) {
                return $from_email;
            };
            $cb_name = function ($n) use ($from_name) {
                return $from_name;
            };
            add_filter('wp_mail_from', $cb_from);
            add_filter('wp_mail_from_name', $cb_name);
            wp_mail($admin_email, $admin_subject, $admin_body, $headers);
            remove_filter('wp_mail_from', $cb_from);
            remove_filter('wp_mail_from_name', $cb_name);
        }
    }

    private function render_template($template, array $vars)
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }
        return strtr($template, $replacements);
    }
}
