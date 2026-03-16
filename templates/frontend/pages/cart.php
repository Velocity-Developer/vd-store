<div x-data="wpStoreCartPage()" x-init="init()">
    <div class="wps-container">
        <div class="wps-card-body">
            <template x-if="!loading && cart.length === 0">
                <div class="wps-text-sm wps-text-gray-500 wps-flex wps-items-center wps-gap-2">
                    <span><?php echo wps_icon(['name' => 'cart', 'size' => 16]); ?></span>
                    <span>Keranjang kosong.</span>
                </div>
            </template>
            <template x-if="cart.length > 0">
                <table class="wps-table">
                    <thead class="wps-table-head">
                        <tr>
                            <th class="wps-th wps-text-left wps-text-xs wps-text-gray-500">Foto</th>
                            <th class="wps-th wps-text-left wps-text-xs wps-text-gray-500">Nama Produk</th>
                            <th class="wps-th wps-text-left wps-text-xs wps-text-gray-500">Qty</th>
                            <th class="wps-th wps-text-right wps-text-xs wps-text-gray-500">Total</th>
                        </tr>
                    </thead>
                    <tbody x-show="loading">
                        <template x-for="i in 3">
                            <tr class="wps-divider">
                                <td class="wps-td">
                                    <div class="wps-skeleton wps-skeleton-img"></div>
                                </td>
                                <td class="wps-td">
                                    <div class="wps-skeleton wps-skeleton-text" style="width:60%;"></div>
                                </td>
                                <td class="wps-td">
                                    <div class="wps-skeleton wps-skeleton-text" style="width:40%;"></div>
                                </td>
                                <td class="wps-td wps-text-right">
                                    <div class="wps-skeleton wps-skeleton-text" style="width:40%; margin-left:auto;"></div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tbody x-show="!loading">
                        <template x-for="item in cart" :key="item.id + ':' + (item.options ? JSON.stringify(item.options) : '')">
                            <tr class="wps-divider">
                                <td class="wps-td">
                                    <img :src="item.image ? item.image : '<?php echo esc_url(WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>'" alt="" class="wps-img-40">
                                </td>
                                <td class="wps-td">
                                    <div x-text="item.title" class="wps-text-sm wps-text-gray-900"></div>
                                </td>
                                <td class="wps-td">
                                    <div class="wps-flex wps-items-center wps-gap-1">
                                        <button type="button" @click="decrement(item)" class="wps-btn wps-btn-secondary wps-btn-sm" style="padding: 2px 8px; font-size: 12px; line-height: 1; min-width: 24px; height: 22px;">-</button>
                                        <span x-text="item.qty" class="wps-badge wps-badge-sm" style="font-size: 12px; padding: 2px 6px; line-height: 1;"></span>
                                        <button type="button" @click="increment(item)" class="wps-btn wps-btn-secondary wps-btn-sm" style="padding: 2px 8px; font-size: 12px; line-height: 1; min-width: 24px; height: 22px;">+</button>
                                    </div>
                                </td>
                                <td class="wps-td wps-text-right">
                                    <div class="wps-text-sm wps-text-gray-900" x-text="formatPrice(item.subtotal)"></div>
                                    <button type="button" @click="remove(item)" :disabled="loading && updatingKey === getItemKey(item)" class="wps-btn wps-btn-danger wps-btn-sm" :style="(loading && updatingKey === getItemKey(item)) ? 'opacity:.7; pointer-events:none; margin-top:6px;' : 'margin-top:6px;'">
                                        <template x-if="loading && updatingKey === getItemKey(item)">
                                            <span><?php echo wps_icon(['name' => 'spinner', 'size' => 14]); ?></span>
                                        </template>
                                        <template x-if="!loading || updatingKey !== getItemKey(item)">
                                            <span><?php echo wps_icon(['name' => 'close', 'size' => 14]); ?></span>
                                        </template>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </div>
        <div class="wps-card-footer wps-flex wps-justify-between wps-items-center wps-mt-4">
            <div class="wps-total-box">
                <div class="wps-total-label wps-text-bold">Total</div>
                <template x-if="loading">
                    <div class="wps-skeleton wps-skeleton-text" style="width:120px; height:20px;"></div>
                </template>
                <template x-if="!loading">
                    <div class="wps-total-amount" x-text="formatPrice(total)"></div>
                </template>
            </div>
            <div class="wps-flex wps-gap-2">
                <template x-if="loading">
                    <div class="wps-skeleton" style="width:220px; height:36px; border-radius:4px;"></div>
                </template>
                <a :href="urlCheckout" class="wps-btn wps-btn-primary" x-show="!loading && cart.length > 0"><?php echo wps_icon(['name' => 'credit-card', 'size' => 16, 'class' => 'wps-mr-2']); ?>Lanjut ke Pembayaran</a>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('wpStoreCartPage', () => ({
            loading: false,
            updatingKey: '',
            urlCheckout: '',
            cart: [],
            total: 0,
            currency: '<?php echo esc_js(($currency ?? 'Rp')); ?>',
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
                    this.urlCheckout = data.data.page_checkout || '';
                } catch (e) {
                    this.urlCheckout = '';
                }
            },
            async fetchCart() {
                this.loading = true;
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
                } finally {
                    this.loading = false;
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
                    this.loading = false;
                });
            }
        }));
    });
</script>