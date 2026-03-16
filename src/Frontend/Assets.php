<?php

namespace WpStore\Frontend;

class Assets
{
    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer', [$this, 'print_global_assets']);
    }

    public function enqueue()
    {
        wp_register_script(
            'alpinejs',
            'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
            [],
            null,
            true
        );
        wp_add_inline_script('alpinejs', 'window.deferLoadingAlpineJs = true;', 'before');

        wp_register_script(
            'wp-store-vendor',
            WP_STORE_URL . 'assets/frontend/js/vendor.bundle.js',
            [],
            WP_STORE_VERSION,
            true
        );

        wp_register_script(
            'wp-store-frontend',
            WP_STORE_URL . 'assets/frontend/js/store.js',
            ['alpinejs', 'wp-store-vendor'],
            WP_STORE_VERSION,
            true
        );

        wp_register_style(
            'wp-store-frontend-css',
            WP_STORE_URL . 'assets/frontend/css/style.css',
            [],
            WP_STORE_VERSION
        );
        wp_register_style(
            'wp-store-flickity',
            WP_STORE_URL . 'assets/frontend/css/flickity.css',
            [],
            WP_STORE_VERSION
        );

        wp_enqueue_style('wp-store-frontend-css');
        $settings = get_option('wp_store_settings', []);
        $css = '';
        $vars = '';
        $primary = isset($settings['theme_primary']) ? sanitize_hex_color($settings['theme_primary']) : '';
        $primary_hover = isset($settings['theme_primary_hover']) ? sanitize_hex_color($settings['theme_primary_hover']) : '';
        $secondary_text = isset($settings['theme_secondary_text']) ? sanitize_hex_color($settings['theme_secondary_text']) : '';
        $secondary_border = isset($settings['theme_secondary_border']) ? sanitize_hex_color($settings['theme_secondary_border']) : '';
        $callout_bg = isset($settings['theme_callout_bg']) ? sanitize_hex_color($settings['theme_callout_bg']) : '';
        $callout_border = isset($settings['theme_callout_border']) ? sanitize_hex_color($settings['theme_callout_border']) : '';
        $callout_title = isset($settings['theme_callout_title']) ? sanitize_hex_color($settings['theme_callout_title']) : '';
        $danger_text = isset($settings['theme_danger_text']) ? sanitize_hex_color($settings['theme_danger_text']) : '';
        $danger_border = isset($settings['theme_danger_border']) ? sanitize_hex_color($settings['theme_danger_border']) : '';

        $root = [];
        if ($primary) $root[] = "--primary-color:{$primary}";
        if ($primary_hover) $root[] = "--primary-color-hover:{$primary_hover}";
        if ($secondary_text) $root[] = "--secondary-text-color:{$secondary_text}";
        if ($secondary_border) $root[] = "--secondary-border-color:{$secondary_border}";
        if ($callout_bg) $root[] = "--callout-bg-color:{$callout_bg}";
        if ($callout_border) $root[] = "--callout-border-color:{$callout_border}";
        if ($callout_title) $root[] = "--callout-title-color:{$callout_title}";
        if ($danger_text) $root[] = "--danger-text-color:{$danger_text}";
        if ($danger_border) $root[] = "--danger-border-color:{$danger_border}";
        if (!empty($root)) {
            $vars .= ":root{" . implode(';', $root) . ";}\n";
        }

        if ($primary) {
            $css .= ".wps-btn-primary,button.wps-btn-primary{background-color:{$primary};}\n";
            $css .= ".wps-tab.active{color:{$primary};border-bottom-color:{$primary};}\n";
            $css .= ".wps-callout-title{color:{$primary};}\n";
        }
        if ($primary_hover) {
            $css .= ".wps-btn-primary:hover{background-color:{$primary_hover};}\n";
        }
        if ($secondary_text || $secondary_border) {
            $css .= ".wps-btn-secondary{";
            if ($secondary_border) $css .= "border-color:{$secondary_border};";
            if ($secondary_text) $css .= "color:{$secondary_text};";
            $css .= "}\n";
            if ($secondary_border) {
                $css .= ".wps-btn-secondary:hover{border-color:{$secondary_border};}\n";
            }
        }
        if ($callout_bg || $callout_border) {
            $css .= ".wps-callout{";
            if ($callout_bg) $css .= "background-color:{$callout_bg};";
            if ($callout_border) $css .= "border-color:{$callout_border};";
            $css .= "}\n";
        }
        if ($callout_title) {
            $css .= ".wps-callout-title{color:{$callout_title};}\n";
        }
        if ($danger_text || $danger_border) {
            $css .= ".wps-btn-danger{";
            if ($danger_border) $css .= "border-color:{$danger_border};";
            if ($danger_text) $css .= "color:{$danger_text};";
            $css .= "}\n";
        }
        $container_max = isset($settings['container_max_width']) ? (int) $settings['container_max_width'] : 1100;
        if ($container_max > 0) {
            $css .= ".wps-container{max-width:{$container_max}px;margin-left:auto;margin-right:auto;}\n";
        }
        if (!empty($vars) || !empty($css)) {
            wp_add_inline_style('wp-store-frontend-css', $vars . $css);
        }

        wp_localize_script(
            'wp-store-frontend',
            'wpStoreSettings',
            [
                'restUrl' => esc_url_raw(rest_url('wp-store/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'thanksUrl' => (function () {
                    $settings = get_option('wp_store_settings', []);
                    $pid = isset($settings['page_thanks']) ? absint($settings['page_thanks']) : 0;
                    if ($pid) {
                        $url = get_permalink($pid);
                        if ($url) return esc_url_raw($url);
                    }
                    return esc_url_raw(site_url('/thanks/'));
                })(),
                'trackingUrl' => (function () {
                    $settings = get_option('wp_store_settings', []);
                    $pid = isset($settings['page_tracking']) ? absint($settings['page_tracking']) : 0;
                    if ($pid) {
                        $url = get_permalink($pid);
                        if ($url) return esc_url_raw($url);
                    }
                    return esc_url_raw(site_url('/tracking-order/'));
                })(),
                'cartUrl' => (function () {
                    $settings = get_option('wp_store_settings', []);
                    $pid = isset($settings['page_cart']) ? absint($settings['page_cart']) : 0;
                    if ($pid) {
                        $url = get_permalink($pid);
                        if ($url) return esc_url_raw($url);
                    }
                    return esc_url_raw(site_url('/cart/'));
                })(),
                'checkoutUrl' => (function () {
                    $settings = get_option('wp_store_settings', []);
                    $pid = isset($settings['page_checkout']) ? absint($settings['page_checkout']) : 0;
                    if ($pid) {
                        $url = get_permalink($pid);
                        if ($url) return esc_url_raw($url);
                    }
                    return esc_url_raw(site_url('/checkout/'));
                })(),
            ]
        );
        wp_enqueue_style('wp-store-flickity');
        wp_enqueue_script('wp-store-vendor');
        wp_enqueue_script('wp-store-frontend');
    }

    public function print_global_assets()
    {
?>
        <div x-data="{
            show: false,
            basicName: '',
            basicOptions: [],
            advName: '',
            advOptions: [],
            selectedBasic: '',
            selectedAdv: '',
            open(payload) {
                const p = payload || {};
                this.basicName = p.basic_name || '';
                this.basicOptions = Array.isArray(p.basic_values) ? p.basic_values : [];
                this.advName = p.adv_name || '';
                this.advOptions = Array.isArray(p.adv_values) ? p.adv_values : [];
                this.selectedBasic = '';
                this.selectedAdv = '';
                this.show = true;
            },
            submit() {
                const detail = {
                    basic: this.selectedBasic,
                    adv: this.selectedAdv
                };
                window.dispatchEvent(new CustomEvent('wp-store:options-selected', { detail }));
                this.show = false;
            }
        }"
            x-on:wp-store:open-options-modal.window="open($event.detail)"
            x-cloak>
            <div class="wps-modal-backdrop" x-show="show" @click.self="show=false"></div>
            <div class="wps-modal" x-show="show">
                <div class="wps-p-4">
                    <div class="wps-mb-4 wps-text-lg wps-font-medium wps-text-gray-900">Pilih Opsi</div>
                    <div class="wps-mb-4" x-show="basicName && basicOptions.length" x-cloak>
                        <label class="wps-label" x-text="basicName"></label>
                        <select class="wps-select" x-model="selectedBasic">
                            <option value="">-- Pilih --</option>
                            <template x-for="opt in basicOptions" :key="opt">
                                <option :value="opt" x-text="opt"></option>
                            </template>
                        </select>
                    </div>
                    <div class="wps-mb-4" x-show="advName && advOptions.length" x-cloak>
                        <label class="wps-label" x-text="advName"></label>
                        <select class="wps-select" x-model="selectedAdv">
                            <option value="">-- Pilih --</option>
                            <template x-for="opt in advOptions" :key="opt.label">
                                <option
                                    :value="opt.label"
                                    x-text="opt.price ? opt.label + ' - ' + (new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(parseFloat(opt.price || 0))) : opt.label">
                                </option>
                            </template>
                        </select>
                    </div>
                    <div class="wps-flex wps-justify-between wps-items-center">
                        <button type="button" class="wps-btn wps-btn-secondary wps-btn-sm" @click="show=false">Batal</button>
                        <button type="button" class="wps-btn wps-btn-primary wps-btn-sm" @click="submit()">
                            <span>Tambah</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div x-data="{
            show: false,
            type: 'success',
            message: '',
            _timer: null,
            open(payload) {
                const d = payload || {};
                this.type = d.type === 'error' ? 'error' : 'success';
                this.message = d.message || '';
                this.show = true;
                clearTimeout(this._timer);
                this._timer = setTimeout(() => { this.show = false; }, 2000);
            }
        }"
            x-on:wp-store:toast.window="open($event.detail)"
            x-cloak>
            <div x-show="show"
                :style="'position:fixed;bottom:30px;right:30px;padding:12px 16px;background:#fff;box-shadow:0 3px 10px rgba(0,0,0,.1);border-left:4px solid ' + (type === 'success' ? '#46b450' : '#d63638') + ';border-radius:4px;z-index:9999;'">
                <span x-text="message" class="wps-text-sm wps-text-gray-900"></span>
            </div>
        </div>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('wpStoreCartOffcanvas', () => ({
                    open: false,
                    loading: false,
                    updatingKey: '',
                    cart: [],
                    total: 0,
                    urlKeranjang: '',
                    urlCheckout: '',
                    currency: '<?php echo esc_js(($settings['currency_symbol'] ?? 'Rp')); ?>',
                    formatPrice(value) {
                        const v = typeof value === 'number' ? value : parseFloat(value || 0);
                        if (this.currency === 'USD') {
                            return new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                minimumFractionDigits: 0
                            }).format(v);
                        }
                        return new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR',
                            minimumFractionDigits: 0
                        }).format(v);
                    },
                    getItemKey(i) {
                        const opts = i && i.options ? i.options : {};
                        let s = '';
                        try {
                            s = JSON.stringify(opts);
                        } catch (e) {
                            s = '';
                        }
                        return String(i.id) + ':' + s;
                    },
                    async fetchPage() {
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'settings/page-urls', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            if (!res.ok) {
                                return;
                            }
                            this.urlKeranjang = data.data.page_cart || '';
                            this.urlCheckout = data.data.page_checkout || '';
                        } catch (e) {
                            this.urlKeranjang = '';
                            this.urlCheckout = '';
                        }
                    },
                    async fetchCart() {
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'cart', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            this.cart = data.items || [];
                            this.total = data.total || 0;
                        } catch (e) {
                            this.cart = [];
                            this.total = 0;
                        }
                    },
                    async updateItem(item, qty) {
                        this.loading = true;
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'cart', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                },
                                body: JSON.stringify({
                                    id: item.id,
                                    qty,
                                    options: (item.options || {})
                                })
                            });
                            const data = await res.json();
                            if (!res.ok) {
                                return;
                            }
                            this.cart = data.items || [];
                            this.total = data.total || 0;
                            document.dispatchEvent(new CustomEvent('wp-store:cart-updated', {
                                detail: data
                            }));
                        } catch (e) {} finally {
                            this.loading = false;
                            this.updatingKey = '';
                        }
                    },
                    increment(item) {
                        this.updateItem(item, item.qty + 1);
                    },
                    decrement(item) {
                        const q = item.qty > 1 ? item.qty - 1 : 0;
                        this.updateItem(item, q);
                    },
                    remove(item) {
                        this.updatingKey = this.getItemKey(item);
                        this.updateItem(item, 0);
                    },
                    init() {
                        this.fetchCart();
                        this.fetchPage();
                        document.addEventListener('wp-store:cart-updated', (e) => {
                            const data = e.detail || {};
                            this.cart = data.items || [];
                            this.total = data.total || 0;
                        });
                        window.addEventListener('wp-store:open-cart', () => {
                            this.open = true;
                        });
                    }
                }));
            });
        </script>
        <div x-data="wpStoreCartOffcanvas()" x-init="init()" x-cloak>
            <div class="wps-offcanvas-backdrop" x-show="open" @click="open = false" x-transition.opacity></div>
            <div class="wps-offcanvas" x-show="open" x-transition>
                <div class="wps-offcanvas-header">
                    <strong class="wps-text-gray-900">Keranjang</strong>
                    <button type="button" @click="open = false" class="wps-btn-icon">✕</button>
                </div>
                <div class="wps-offcanvas-body">
                    <?php echo \WpStore\Frontend\Template::render('components/cart-list'); ?>
                </div>
                <div class="wps-offcanvas-footer">
                    <!-- <div class="wps-flex wps-justify-center wps-gap-2 wps-bg-dark wps-text-white wps-p-2 wps-rounded-md"> -->
                    <a :href="urlKeranjang" class="wps-btn wps-btn-secondary wps-btn-sm wps-w-full">
                        <?php echo wps_icon(['name' => 'cart', 'size' => 16]); ?>
                        <span class="wps-pl-2">Keranjang</span>
                    </a>
                    <!-- </div> -->
                    <div class="wps-mt-2 wps-flex wps-justify-between wps-checkout-box">
                        <div class="wps-total-box">
                            <div class="wps-total-label">Total</div>
                            <div class="wps-total-amount" x-text="formatPrice(total)"></div>
                        </div>
                        <a :href="urlCheckout" class="wps-btn wps-btn-primary wps-btn-sm wps-checkout-btn" x-show="cart.length > 0"><?php echo wps_icon(['name' => 'credit-card', 'size' => 16, 'class' => 'wps-mr-2']); ?>Checkout</a>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}
