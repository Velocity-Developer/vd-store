<?php

namespace WpStore\Frontend;

class Captcha
{
    public static function render($args = [])
    {
        $args = is_array($args) ? $args : [];
        $html = '';

        if (self::uses_velocity_addons()) {
            $shortcode = '[velocity_captcha]';
            if (!empty($args['form_selector'])) {
                $shortcode = '[velocity_captcha form="' . esc_attr((string) $args['form_selector']) . '"]';
            }
            $html = do_shortcode($shortcode);
            if (is_string($html) && trim($html) !== '') {
                $uid = 'wps-velocity-captcha-' . substr(md5(uniqid('', true)), 0, 8);
                $html = '<div id="' . esc_attr($uid) . '" class="wps-velocity-captcha" data-wp-store-captcha-provider="velocity">' . $html . '</div>' . self::bridge_script($uid);
                return apply_filters('wp_store_captcha_render_html', $html, $args, self::provider());
            }
        }

        $html = Template::render('components/captcha');

        return apply_filters('wp_store_captcha_render_html', $html, $args, self::provider());
    }

    public static function validate($source = null)
    {
        if (self::uses_velocity_addons()) {
            $result = self::validate_velocity_addons($source);
        } else {
            $result = self::validate_internal($source);
        }

        return apply_filters('wp_store_validate_captcha_result', $result, $source, self::provider());
    }

    public static function provider()
    {
        if (!self::uses_velocity_addons()) {
            return 'internal';
        }

        $options = self::velocity_options();
        $provider = isset($options['provider']) ? sanitize_key((string) $options['provider']) : 'google';

        return 'velocity_' . ($provider === 'image' ? 'image' : 'google');
    }

    public static function uses_velocity_addons()
    {
        if (!class_exists('\Velocity_Addons_Captcha') || !shortcode_exists('velocity_captcha')) {
            return false;
        }

        $options = self::velocity_options();
        if (empty($options['aktif'])) {
            return false;
        }

        $provider = isset($options['provider']) ? sanitize_key((string) $options['provider']) : 'google';
        if ($provider === 'image') {
            return true;
        }

        return !empty($options['sitekey']) && !empty($options['secretkey']);
    }

    private static function validate_velocity_addons($source)
    {
        $options = self::velocity_options();
        $provider = isset($options['provider']) ? sanitize_key((string) $options['provider']) : 'google';

        if ($provider === 'image') {
            $token = sanitize_text_field(self::request_value('vd_captcha_token', $source));
            $input = sanitize_text_field(self::request_value('vd_captcha_input', $source));
            if ($token === '' || $input === '') {
                return ['success' => false, 'message' => 'Captcha required'];
            }

            $stored = get_transient('vd_captcha_' . $token);
            delete_transient('vd_captcha_' . $token);

            if (is_string($stored) && strtoupper($stored) === strtoupper($input)) {
                return ['success' => true, 'message' => 'Validasi captcha berhasil'];
            }

            return ['success' => false, 'message' => 'Captcha invalid'];
        }

        $response = sanitize_textarea_field(self::request_value('g-recaptcha-response', $source));
        $secret = isset($options['secretkey']) ? (string) $options['secretkey'] : '';
        if ($response === '' || $secret === '') {
            return ['success' => false, 'message' => 'Captcha required'];
        }

        $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '';
        $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 15,
            'body' => [
                'secret' => $secret,
                'response' => $response,
                'remoteip' => $remote_ip,
            ],
        ]);

        if (is_wp_error($verify)) {
            return ['success' => true, 'message' => 'Lewati verifikasi captcha (koneksi gagal)'];
        }

        $code = (int) wp_remote_retrieve_response_code($verify);
        $body = json_decode((string) wp_remote_retrieve_body($verify), true);
        if ($code === 200 && is_array($body) && !empty($body['success'])) {
            return ['success' => true, 'message' => 'Validasi captcha berhasil'];
        }

        return ['success' => false, 'message' => 'Captcha invalid'];
    }

    private static function validate_internal($source)
    {
        $cid = sanitize_text_field(self::request_value('captcha_id', $source));
        $cval = sanitize_text_field(self::request_value('captcha_value', $source));
        if ($cid === '' || $cval === '') {
            return ['success' => false, 'message' => 'Captcha required'];
        }

        $stored = get_transient('wp_store_captcha_' . $cid);
        delete_transient('wp_store_captcha_' . $cid);
        if (is_string($stored) && strtoupper($stored) === strtoupper($cval)) {
            return ['success' => true, 'message' => 'Validasi captcha berhasil'];
        }

        return ['success' => false, 'message' => 'Captcha invalid'];
    }

    private static function request_value($key, $source)
    {
        if (is_array($source)) {
            if (isset($source[$key])) {
                return is_scalar($source[$key]) ? (string) wp_unslash($source[$key]) : '';
            }
            if (isset($source['captcha']) && is_array($source['captcha']) && isset($source['captcha'][$key])) {
                return is_scalar($source['captcha'][$key]) ? (string) wp_unslash($source['captcha'][$key]) : '';
            }
        }

        if (isset($_POST[$key])) {
            return is_scalar($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';
        }

        return '';
    }

    private static function velocity_options()
    {
        $options = get_option('captcha_velocity', []);

        return is_array($options) ? $options : [];
    }

    /**
     * reCAPTCHA Google mengisi g-recaptcha-response tanpa event, jadi nilainya dipantau tiap 500 ms
     * selama halaman terbuka (tanpa batas waktu: pembeli sering mencentang captcha lebih dari 2 menit
     * setelah halaman dibuka, dan token yang kedaluwarsa dikosongkan Google tanpa event juga).
     * Kolom dibaca hanya di dalam wrapper ini, supaya tidak tertukar dengan widget reCAPTCHA lain di halaman.
     *
     * Event `wp-store:captcha-reset` (dikirim ke wrapper atau elemen induknya) mereset captcha. Kirim setelah
     * form dikirim ke server, karena token Google dan kode gambar hanya berlaku sekali.
     */
    private static function bridge_script($uid)
    {
        return '<script>(function(){try{var wrap=document.getElementById("' . esc_js($uid) . '");if(!wrap){return;}var last="";'
            . 'function q(sel){return wrap.querySelector(sel);}'
            . 'function gField(){return q("textarea[name=\'g-recaptcha-response\'],input[name=\'g-recaptcha-response\']");}'
            . 'function read(){var g=gField();var t=q("input[name=\'vd_captcha_token\']");var i=q("input[name=\'vd_captcha_input\']");return [g&&g.value?g.value:"",t&&t.value?t.value:"",i&&i.value?i.value:""].join("|");}'
            . 'function notify(){var now=read();if(now===last){return;}last=now;wrap.dispatchEvent(new Event("input",{bubbles:true}));wrap.dispatchEvent(new Event("change",{bubbles:true}));}'
            . 'function reset(){var g=gField();if(g&&window.grecaptcha&&typeof grecaptcha.reset==="function"){var m=/^g-recaptcha-response(?:-(\\d+))?$/.exec(g.id||"");if(m){try{grecaptcha.reset(m[1]?parseInt(m[1],10):0);}catch(e){g.value="";}}else{g.value="";}}'
            . 'var i=q("input[name=\'vd_captcha_input\']");if(i){i.value="";var img=q("img");if(img&&img.src){img.src=img.src.split("&r=")[0]+"&r="+Date.now();}}notify();}'
            . 'document.addEventListener("wp-store:captcha-reset",function(e){var t=e.target;if(t===document||(t&&t.contains&&t.contains(wrap))){reset();}});'
            . 'wrap.addEventListener("input",function(){setTimeout(notify,0);},true);wrap.addEventListener("change",function(){setTimeout(notify,0);},true);'
            . 'var timer=setInterval(function(){if(!wrap.isConnected){clearInterval(timer);return;}notify();},500);notify();}catch(e){}})();</script>';
    }
}
