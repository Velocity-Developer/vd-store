<script>
    if (typeof window.wpStoreSettings === 'undefined') {
        window.wpStoreSettings = {
            restUrl: '<?php echo esc_url_raw(rest_url('wp-store/v1/')); ?>',
            nonce: '<?php echo esc_js($nonce); ?>'
        };
    }
</script>
<script>
    if (typeof window.wpStoreWishlistWidget !== 'function') {
        window.wpStoreWishlistWidget = function() {
            return {
                loading: false,
                updatingAddKey: '',
                updatingRemoveKey: '',
                items: [],
                message: '',
                toastShow: false,
                toastType: 'success',
                toastMessage: '',
                showToast(msg, type) {
                    this.toastMessage = msg || '';
                    this.toastType = type === 'error' ? 'error' : 'success';
                    this.toastShow = true;
                    clearTimeout(this._toastTimer);
                    this._toastTimer = setTimeout(() => {
                        this.toastShow = false;
                    }, 2000);
                },
                normalizeOptions(obj) {
                    const o = obj || {};
                    const sorted = {};
                    Object.keys(o).sort().forEach((k) => {
                        sorted[k] = o[k];
                    });
                    return sorted;
                },
                stringifyOptions(obj) {
                    const n = this.normalizeOptions(obj || {});
                    try {
                        return JSON.stringify(n);
                    } catch (e) {
                        return '{}';
                    }
                },
                async fetchWishlist() {
                    this.loading = true;
                    try {
                        const res = await fetch(wpStoreSettings.restUrl + 'wishlist', {
                            credentials: 'same-origin',
                            headers: {
                                'X-WP-Nonce': wpStoreSettings.nonce
                            }
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.message = data.message || 'Gagal mengambil wishlist';
                            this.items = [];
                            return;
                        }
                        this.items = data.items || [];
                    } catch (e) {
                        this.items = [];
                    } finally {
                        this.loading = false;
                    }
                },
                formatPrice(val) {
                    try {
                        const p = Number(val || 0);
                        return '<?php echo esc_js($currency); ?> ' + p.toLocaleString('id-ID');
                    } catch (e) {
                        return '<?php echo esc_js($currency); ?> ' + (val || 0);
                    }
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
                async remove(item) {
                    this.updatingRemoveKey = this.getItemKey(item);
                    try {
                        const res = await fetch(wpStoreSettings.restUrl + 'wishlist', {
                            method: 'DELETE',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpStoreSettings.nonce
                            },
                            body: JSON.stringify({
                                id: item.id
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || 'Gagal menghapus', 'error');
                            return;
                        }
                        this.items = data.items || [];
                        document.dispatchEvent(new CustomEvent('wp-store:wishlist-updated', {
                            detail: data
                        }));
                        this.showToast('Dihapus dari wishlist', 'success');
                    } catch (e) {
                        this.showToast('Kesalahan jaringan', 'error');
                    } finally {
                        this.updatingRemoveKey = '';
                    }
                },
                async addToCart(item) {
                    this.updatingAddKey = this.getItemKey(item);
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
                                add_qty: 1,
                                options: (item.options || {})
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || 'Gagal menambah ke keranjang', 'error');
                            return;
                        }
                        document.dispatchEvent(new CustomEvent('wp-store:cart-updated', {
                            detail: data
                        }));
                        this.showToast('Ditambahkan ke keranjang', 'success');
                    } catch (e) {
                        this.showToast('Kesalahan jaringan', 'error');
                    } finally {
                        this.updatingAddKey = '';
                    }
                },
                async clear() {
                    try {
                        const res = await fetch(wpStoreSettings.restUrl + 'wishlist', {
                            method: 'DELETE',
                            credentials: 'include',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpStoreSettings.nonce
                            },
                            body: JSON.stringify({})
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            return;
                        }
                        this.items = data.items || [];
                        document.dispatchEvent(new CustomEvent('wp-store:wishlist-updated', {
                            detail: data
                        }));
                    } catch (e) {}
                },
                init() {
                    this.fetchWishlist();
                    document.addEventListener('wp-store:wishlist-updated', (e) => {
                        const data = e.detail || {};
                        this.items = data.items || [];
                    });
                }
            };
        };
    }
    document.addEventListener('alpine:init', () => {
        Alpine.data('wpStoreWishlistWidget', () => window.wpStoreWishlistWidget());
    });
</script>
<div x-data="wpStoreWishlistWidget()" x-init="init()">
    <div class="">
        <div class="wps-flex wps-justify-between wps-items-center">
            <div class="wps-text-lg wps-font-medium wps-text-gray-900">Wishlist</div>
            <button class="wps-btn wps-btn-secondary wps-btn-sm" @click="clear()" x-show="items.length">Bersihkan</button>
        </div>
        <div x-show="loading" class="wps-mt-4">
            <div class="wps-table-responsive" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
                <table style="width: 100%; border-collapse: collapse; min-width:560px;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding: 8px; color:#6b7280; font-size:12px;">Produk</th>
                            <th style="text-align:left; padding: 8px; color:#6b7280; font-size:12px;">Nama</th>
                            <th style="text-align:right; padding: 8px; color:#6b7280; font-size:12px;">Harga</th>
                            <th style="text-align:right; padding: 8px; color:#6b7280; font-size:12px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-top: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">
                                <div class="wps-skeleton wps-skeleton-img"></div>
                            </td>
                            <td style="padding: 8px;">
                                <div class="wps-skeleton wps-skeleton-text" style="width:60%;"></div>
                            </td>
                            <td style="padding: 8px; text-align: right;">
                                <div class="wps-skeleton wps-skeleton-text" style="width:40%; margin-left:auto;"></div>
                            </td>
                            <td style="padding: 8px; text-align: right;">
                                <div class="wps-flex wps-gap-2" style="justify-content: flex-end;">
                                    <div class="wps-skeleton" style="width:28px; height:28px; border-radius:4px;"></div>
                                    <div class="wps-skeleton" style="width:28px; height:28px; border-radius:4px;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr style="border-top: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">
                                <div class="wps-skeleton wps-skeleton-img"></div>
                            </td>
                            <td style="padding: 8px;">
                                <div class="wps-skeleton wps-skeleton-text" style="width:50%;"></div>
                            </td>
                            <td style="padding: 8px; text-align: right;">
                                <div class="wps-skeleton wps-skeleton-text" style="width:40%; margin-left:auto;"></div>
                            </td>
                            <td style="padding: 8px; text-align: right;">
                                <div class="wps-flex wps-gap-2" style="justify-content: flex-end;">
                                    <div class="wps-skeleton" style="width:28px; height:28px; border-radius:4px;"></div>
                                    <div class="wps-skeleton" style="width:28px; height:28px; border-radius:4px;"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <template x-if="!loading && !items.length">
            <div class="wps-text-sm wps-text-gray-600 wps-mt-4">Belum ada item di wishlist.</div>
        </template>
        <div class="wps-mt-4 wps-table-responsive" x-show="!loading && items.length" style="overflow-x:auto;-webkit-overflow-scrolling:touch; display: none;">
            <table style="width: 100%; border-collapse: collapse; min-width:560px;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding: 8px; color:#6b7280; font-size:12px;">Produk</th>
                        <th style="text-align:left; padding: 8px; color:#6b7280; font-size:12px;">Nama</th>
                        <th style="text-align:right; padding: 8px; color:#6b7280; font-size:12px;">Harga</th>
                        <th style="text-align:right; padding: 8px; color:#6b7280; font-size:12px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="item in items" :key="item.id">
                        <tr style="border-top: 1px solid #e5e7eb;">
                            <td style="padding: 8px;">
                                <img :src="item.image || '<?php echo esc_url(WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>'" alt="" class="wps-img-40 wps-rounded">
                            </td>
                            <td style="padding: 8px;">
                                <a :href="item.link" class="wps-text-sm wps-text-gray-900" x-text="item.title"></a>
                            </td>
                            <td style="padding: 8px; text-align: right;">
                                <span class="wps-text-sm wps-text-gray-700" x-text="formatPrice(item.price)"></span>
                            </td>
                            <td style="padding: 8px; text-align: right;">
                                <div class="wps-flex wps-gap-2" style="justify-content: flex-end;">
                                    <button class="wps-btn wps-btn-primary wps-btn-sm" @click="addToCart(item)" :disabled="updatingAddKey === getItemKey(item)" :style="(updatingAddKey === getItemKey(item)) ? 'opacity:.7; pointer-events:none;' : ''">
                                        <template x-if="updatingAddKey === getItemKey(item)">
                                            <span><?php echo wps_icon(['name' => 'spinner', 'size' => 16, 'class' => 'wps-mr-2']); ?></span>
                                        </template>
                                        <template x-if="updatingAddKey !== getItemKey(item)">
                                            <span><?php echo wps_icon(['name' => 'cart', 'size' => 16, 'class' => 'wps-mr-2']); ?></span>
                                        </template>
                                        <span>+</span>
                                    </button>
                                    <button class="wps-btn wps-btn-secondary wps-btn-sm" @click="remove(item)" :disabled="updatingRemoveKey === getItemKey(item)" :style="(updatingRemoveKey === getItemKey(item)) ? 'opacity:.7; pointer-events:none;' : ''">
                                        <template x-if="updatingRemoveKey === getItemKey(item)">
                                            <span><?php echo wps_icon(['name' => 'spinner', 'size' => 16, 'class' => 'wps-mr-2']); ?></span>
                                        </template>
                                        <template x-if="updatingRemoveKey !== getItemKey(item)">
                                            <span><?php echo wps_icon(['name' => 'trash', 'size' => 16, 'class' => '']); ?></span>
                                        </template>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div x-show="toastShow" x-transition x-cloak
            :style="'position:fixed;bottom:30px;right:30px;padding:12px 16px;background:#fff;box-shadow:0 3px 10px rgba(0,0,0,.1);border-left:4px solid ' + (toastType === 'success' ? '#46b450' : '#d63638') + ';border-radius:4px;z-index:9999;'">
            <span x-text="toastMessage" class="wps-text-sm wps-text-gray-900"></span>
        </div>
    </div>
</div>