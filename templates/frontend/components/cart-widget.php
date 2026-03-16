<script>
    if (typeof window.wpStoreSettings === 'undefined') {
        window.wpStoreSettings = {
            restUrl: '<?php echo esc_url_raw(rest_url('wp-store/v1/')); ?>',
            nonce: '<?php echo esc_js($nonce); ?>'
        };
    }
    document.addEventListener('alpine:init', () => {
        Alpine.data('wpStoreCartWidget', () => ({
            open: false,
            loading: false,
            updatingKey: '',
            cart: [],
            total: 0,
            currency: '<?php echo esc_js($currency); ?>',
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
                document.addEventListener('wp-store:cart-updated', (e) => {
                    const data = e.detail || {};
                    this.cart = data.items || [];
                    this.total = data.total || 0;
                });
            }
        }));
    });
</script>
<div x-data="wpStoreCartWidget()" x-init="init()" class="wps-rel">
    <button type="button" @click="window.dispatchEvent(new CustomEvent('wp-store:open-cart'))" class="wps-btn-icon wps-cart-button wps-rel">
        <span class="wps-text-2xl wps-pr-6">
            <?php echo wps_icon(['name' => 'cart', 'size' => 16]); ?>
        </span>
        <span x-text="cart.reduce((sum, item) => sum + (item.qty || 0), 0)" class="wps-absolute wps-top--6 wps-right-0 wps-bg-blue-500 wps-text-white wps-text-xs rounded-full wps-px-2.5 wps-py-0.5"></span>
    </button>
</div>