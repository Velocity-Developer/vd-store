<?php
$uid = substr(md5(uniqid('', true)), 0, 8);
?>
<div id="wps-captcha-<?php echo esc_attr($uid); ?>" class="wps-mt-2">
    <div class="wps-flex wps-items-center wps-gap-2 wps-mt-2" id="wps-captcha-ui-top-<?php echo esc_attr($uid); ?>">
        <div id="wps-captcha-image-<?php echo esc_attr($uid); ?>"></div>
        <button type="button" class="wps-btn wps-btn-secondary wps-btn-sm wps-captcha-refresh" id="wps-captcha-refresh-<?php echo esc_attr($uid); ?>">
            <?php echo wps_icon(['name' => 'arrow-repeat', 'size' => 16, 'class' => 'bi bi-arrow-repeat']); ?>
        </button>
    </div>
    <div class="wps-flex wps-items-center wps-gap-2 wps-mt-2" id="wps-captcha-ui-bottom-<?php echo esc_attr($uid); ?>" style="max-width: 300px;">
        <input type="text" name="captcha_value" class="wps-input" placeholder="Masukkan teks">
        <input type="hidden" name="captcha_id" id="wps-captcha-id-<?php echo esc_attr($uid); ?>" value="">
        <button type="button" class="wps-btn wps-btn-secondary" id="wps-captcha-check-<?php echo esc_attr($uid); ?>">
            <span>Periksa</span>
        </button>
        <input type="hidden" name="captcha_verified" id="wps-captcha-verified-<?php echo esc_attr($uid); ?>" value="">
    </div>
    <button id="wps-captcha-check-icon-<?php echo esc_attr($uid); ?>" class="wps-ml-1 wps-btn wps-btn-sm wps-btn-outline-success" style="display:none;">
        <?php echo wps_icon(['name' => 'check', 'size' => 16, 'class' => 'bi bi-check']); ?>
        <span>Captcha Terverifikasi</span>
    </button>
</div>
<script>
    (function() {
        var img = document.getElementById('wps-captcha-image-<?php echo esc_js($uid); ?>');
        var idf = document.getElementById('wps-captcha-id-<?php echo esc_js($uid); ?>');
        var btn = document.getElementById('wps-captcha-refresh-<?php echo esc_js($uid); ?>');
        var root = document.getElementById('wps-captcha-<?php echo esc_js($uid); ?>');
        var checkBtn = document.getElementById('wps-captcha-check-<?php echo esc_js($uid); ?>');
        var checkIcon = document.getElementById('wps-captcha-check-icon-<?php echo esc_js($uid); ?>');
        var topWrap = document.getElementById('wps-captcha-ui-top-<?php echo esc_js($uid); ?>');
        var bottomWrap = document.getElementById('wps-captcha-ui-bottom-<?php echo esc_js($uid); ?>');
        var valInput = root ? root.querySelector('input[name="captcha_value"]') : null;
        var verifiedInput = document.getElementById('wps-captcha-verified-<?php echo esc_js($uid); ?>');
        var requiredLen = 5;

        function showToast(message, type) {
            var el = document.getElementById('wp-store-frontend-toast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'wp-store-frontend-toast';
                el.style.position = 'fixed';
                el.style.bottom = '30px';
                el.style.right = '30px';
                el.style.padding = '12px 16px';
                el.style.background = '#fff';
                el.style.boxShadow = '0 3px 10px rgba(0,0,0,.1)';
                el.style.borderLeft = '4px solid #46b450';
                el.style.borderRadius = '4px';
                el.style.zIndex = '9999';
                el.style.fontSize = '14px';
                el.style.color = '#111827';
                document.body.appendChild(el);
            }
            el.style.borderLeftColor = (type === 'success' ? '#46b450' : '#d63638');
            el.textContent = message || '';
            el.style.display = 'block';
            el.style.opacity = '1';
            clearTimeout(el._timer);
            el._timer = setTimeout(function() {
                el.style.opacity = '0';
                setTimeout(function() {
                    el.style.display = 'none';
                }, 200);
            }, 2000);
        }

        function load() {
            fetch(wpStoreSettings.restUrl + 'captcha/new', {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': wpStoreSettings.nonce
                }
            }).then(function(r) {
                return r.json()
            }).then(function(j) {
                if (j && j.success) {
                    idf.value = j.id;
                    img.innerHTML = j.svg;
                    if (verifiedInput) verifiedInput.value = '';
                    if (checkIcon) checkIcon.style.display = 'none';
                    if (topWrap) topWrap.style.display = '';
                    if (bottomWrap) bottomWrap.style.display = '';
                    if (root) {
                        root.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                        root.dispatchEvent(new Event('input', {
                            bubbles: true
                        }));
                    }
                } else {
                    loadFailed();
                }
            }).catch(loadFailed);
        }

        function loadFailed() {
            if (idf) idf.value = '';
            if (verifiedInput) verifiedInput.value = '';
            if (checkIcon) checkIcon.style.display = 'none';
            if (topWrap) topWrap.style.display = '';
            if (bottomWrap) bottomWrap.style.display = '';
            showToast('Captcha gagal dimuat. Klik tombol muat ulang di samping captcha.', 'error');
            if (root) {
                root.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
            }
        }

        // Kode captcha hanya berlaku sekali: form mengirim event ini setelah dikirim ke server.
        document.addEventListener('wp-store:captcha-reset', function(e) {
            var t = e.target;
            if (!root || !(t === document || (t && t.contains && t.contains(root)))) return;
            if (valInput) valInput.value = '';
            load();
        });
        if (document.readyState !== 'loading') {
            load();
        } else {
            document.addEventListener('DOMContentLoaded', load);
        }
        if (btn) btn.addEventListener('click', load);
        if (checkBtn) checkBtn.addEventListener('click', function() {
            var val = (valInput && valInput.value ? String(valInput.value).trim() : '');
            var idv = (idf && idf.value ? String(idf.value).trim() : '');
            if (val.length >= requiredLen && idv !== '') {
                if (verifiedInput) verifiedInput.value = '1';
                if (checkIcon) checkIcon.style.display = 'inline';
                if (topWrap) topWrap.style.display = 'none';
                if (bottomWrap) bottomWrap.style.display = 'none';
                showToast('Captcha terverifikasi.', 'success');
            } else {
                if (verifiedInput) verifiedInput.value = '';
                if (checkIcon) checkIcon.style.display = 'none';
                if (topWrap) topWrap.style.display = '';
                if (bottomWrap) bottomWrap.style.display = '';
                showToast('Captcha tidak valid. Periksa kembali.', 'error');
            }
            if (root) {
                root.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
                root.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
            }
        });
    })();
</script>