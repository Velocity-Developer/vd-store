<script>
    if (typeof window.wpStoreSettings === 'undefined') {
        window.wpStoreSettings = {
            restUrl: '<?php echo esc_url_raw(rest_url('wp-store/v1/')); ?>',
            nonce: '<?php echo esc_js($nonce); ?>'
        };
    }
</script>
<script>
    if (typeof window.wpStoreAddToCart !== 'function') {
        window.wpStoreAddToCart = function(params) {
            return {
                loading: false,
                qtyEnabled: !!params.qtyEnabled,
                minQty: (params.minQty > 0 ? params.minQty : 1),
                qty: (params.qty > 0 ? params.qty : 1),
                message: '',
                toastShow: false,
                toastType: 'success',
                toastMessage: '',
                basicName: (params.basicName || ''),
                basicOptions: Array.isArray(params.basicValues) ? params.basicValues : [],
                advName: (params.advName || ''),
                advOptions: Array.isArray(params.advValues) ? params.advValues : [],
                selectedBasic: '',
                selectedAdv: '',
                showToast(msg, type) {
                    window.dispatchEvent(new CustomEvent('wp-store:toast', {
                        detail: {
                            message: msg || '',
                            type: (type === 'error' ? 'error' : 'success')
                        }
                    }));
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
                hasOptions() {
                    return ((this.basicName && this.basicOptions.length > 0) || (this.advName && this.advOptions.length > 0));
                },
                canSubmit() {
                    const needBasic = !!(this.basicName && this.basicOptions.length);
                    const needAdv = !!(this.advName && this.advOptions.length);
                    return (!needBasic || !!this.selectedBasic) && (!needAdv || !!this.selectedAdv);
                },
                incrementQty() {
                    this.qty = this.qty + 1;
                },
                decrementQty() {
                    const m = this.minQty > 0 ? this.minQty : 1;
                    this.qty = this.qty > m ? (this.qty - 1) : m;
                },
                getOptionsPayload() {
                    const opts = {};
                    const bName = (this.basicName || '').trim();
                    const bVal = (this.selectedBasic || '').trim();
                    const aName = (this.advName || '').trim();
                    const aVal = (this.selectedAdv || '').trim();
                    if (bName && bVal) {
                        opts[bName] = bVal;
                    }
                    if (aName && aVal) {
                        opts[aName] = aVal;
                    }
                    return this.normalizeOptions(opts);
                },
                async add() {
                    if (this.loading) {
                        return;
                    }
                    if (this.hasOptions()) {
                        const payload = {
                            basic_name: this.basicName,
                            basic_values: this.basicOptions,
                            adv_name: this.advName,
                            adv_values: this.advOptions
                        };
                        const handler = (e) => {
                            const d = e.detail || {};
                            this.selectedBasic = typeof d.basic === 'string' ? d.basic : '';
                            this.selectedAdv = typeof d.adv === 'string' ? d.adv : '';
                            window.removeEventListener('wp-store:options-selected', handler);
                            this.confirmAdd();
                        };
                        window.addEventListener('wp-store:options-selected', handler, {
                            once: true
                        });
                        window.dispatchEvent(new CustomEvent('wp-store:open-options-modal', {
                            detail: payload
                        }));
                        return;
                    }
                    this.loading = true;
                    await this.confirmAdd();
                },
                async confirmAdd() {
                    try {
                        const addQty = this.qtyEnabled ? (this.qty > 0 ? this.qty : 1) : 1;
                        const res = await fetch(wpStoreSettings.restUrl + 'cart', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpStoreSettings.nonce
                            },
                            body: JSON.stringify({
                                id: params.id,
                                add_qty: addQty,
                                options: this.getOptionsPayload()
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || 'Gagal menambah', 'error');
                            return;
                        }
                        document.dispatchEvent(new CustomEvent('wp-store:cart-updated', {
                            detail: data
                        }));
                        this.showToast('Ditambahkan ke keranjang', 'success');
                    } catch (e) {
                        this.showToast('Kesalahan jaringan', 'error');
                    } finally {
                        this.loading = false;
                    }
                }
            };
        };
    }
    document.addEventListener('alpine:init', () => {
        Alpine.data('wpStoreAddToCart', (params) => window.wpStoreAddToCart(params));
    });
</script>
<div x-data="wpStoreAddToCart({
        id: <?php echo (int) $id; ?>,
        qtyEnabled: <?php echo isset($show_qty) && $show_qty ? 'true' : 'false'; ?>,
        minQty: <?php echo isset($default_qty) ? (int) $default_qty : 1; ?>,
        qty: <?php echo isset($default_qty) ? (int) $default_qty : 1; ?>,
        basicName: '<?php echo esc_js($basic_name); ?>',
        basicValues: JSON.parse('<?php echo esc_js(wp_json_encode($basic_values)); ?>'),
        advName: '<?php echo esc_js($adv_name); ?>',
        advValues: JSON.parse('<?php echo esc_js(wp_json_encode($adv_values)); ?>')
    })">
    <div x-show="qtyEnabled" x-cloak class="wps-flex wps-items-center wps-gap-2 wps-mb-2">
        <button type="button" class=" wps-btn wps-btn-secondary wps-btn-sm wps-decrement-qty" @click="decrementQty()">-</button>
        <input type="text" class=" wps-input wps-input-sm wps-input-qty" :value="qty" readonly style="width:60px; text-align:center;">
        <button type="button" class=" wps-btn wps-btn-secondary wps-btn-sm wps-increment-qty" @click="incrementQty()">+</button>
    </div>
    <button type="button" @click="add()" :disabled="loading" class="<?php echo esc_attr($btn_class); ?> wps-add-to-cart" :style="loading ? 'opacity:.7; pointer-events:none;' : ''">
        <template x-if="loading">
            <span><?php echo wps_icon(['name' => 'spinner', 'size' => 18, 'class' => 'wps-mr-2']); ?></span>
        </template>
        <template x-if="!loading">
            <span><?php echo wps_icon(['name' => 'cart', 'size' => 20, 'class' => 'wps-icon-20 wps-mr-2']); ?></span>
        </template>
        <?php echo esc_html($label); ?>
    </button>
</div>