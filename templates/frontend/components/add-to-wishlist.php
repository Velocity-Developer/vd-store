<script>
    if (typeof window.wpStoreSettings === 'undefined') {
        window.wpStoreSettings = {
            restUrl: '<?php echo esc_url_raw(rest_url('wp-store/v1/')); ?>',
            nonce: '<?php echo esc_js($nonce); ?>'
        };
    }
</script>
<script>
    if (typeof window.wpStoreAddToWishlist === 'undefined') {
        window.wpStoreAddToWishlist = function(params) {
            return {
                loading: false,
                inWishlist: false,
                iconOnly: !!params.iconOnly,
                loggedIn: !!params.loggedIn,
                showLoginModal: false,
                loginUrl: params.loginUrl || '',
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
                async ensureWishlistLoaded() {
                    if (window.__wpStoreWishlistItems && Array.isArray(window.__wpStoreWishlistItems)) {
                        return;
                    }
                    if (window.__wpStoreWishlistLoadingPromise) {
                        await window.__wpStoreWishlistLoadingPromise;
                        return;
                    }
                    window.__wpStoreWishlistLoadingPromise = (async () => {
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'wishlist', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            window.__wpStoreWishlistItems = Array.isArray(data.items) ? data.items : [];
                        } catch (e) {
                            window.__wpStoreWishlistItems = [];
                        }
                    })();
                    await window.__wpStoreWishlistLoadingPromise;
                },
                setStateFromCache() {
                    const items = Array.isArray(window.__wpStoreWishlistItems) ? window.__wpStoreWishlistItems : [];
                    this.inWishlist = !!items.find((i) => i.id === params.id);
                },
                async add() {
                    if (!this.loggedIn) {
                        this.showLoginModal = true;
                        return;
                    }
                    this.loading = true;
                    try {
                        const res = await fetch(wpStoreSettings.restUrl + 'wishlist', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpStoreSettings.nonce
                            },
                            body: JSON.stringify({
                                id: params.id
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || 'Gagal menambah', 'error');
                            return;
                        }
                        document.dispatchEvent(new CustomEvent('wp-store:wishlist-updated', {
                            detail: data
                        }));
                        this.inWishlist = true;
                        this.showToast('Ditambahkan ke wishlist', 'success');
                    } catch (e) {
                        this.showToast('Kesalahan jaringan', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                async remove() {
                    this.loading = true;
                    try {
                        const res = await fetch(wpStoreSettings.restUrl + 'wishlist', {
                            method: 'DELETE',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': wpStoreSettings.nonce
                            },
                            body: JSON.stringify({
                                id: params.id
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || 'Gagal menghapus', 'error');
                            return;
                        }
                        document.dispatchEvent(new CustomEvent('wp-store:wishlist-updated', {
                            detail: data
                        }));
                        this.inWishlist = false;
                        this.showToast('Dihapus dari wishlist', 'success');
                    } catch (e) {
                        this.showToast('Kesalahan jaringan', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                init() {
                    const el = this.$root;
                    const load = async () => {
                        await this.ensureWishlistLoaded();
                        this.setStateFromCache();
                    };
                    try {
                        if ('IntersectionObserver' in window) {
                            const io = new IntersectionObserver(async (entries) => {
                                entries.forEach(async (entry) => {
                                    if (entry.isIntersecting) {
                                        await load();
                                        io.disconnect();
                                    }
                                });
                            }, {
                                rootMargin: '120px'
                            });
                            io.observe(el);
                        } else {
                            setTimeout(load, 0);
                        }
                    } catch (e) {
                        setTimeout(load, 0);
                    }
                    el.addEventListener('mouseenter', load, {
                        once: true
                    });
                    el.addEventListener('focus', load, {
                        once: true
                    });
                    document.addEventListener('wp-store:wishlist-updated', (e) => {
                        const d = e.detail || {};
                        const items = Array.isArray(d.items) ? d.items : [];
                        window.__wpStoreWishlistItems = items;
                        this.setStateFromCache();
                    });
                }
            };
        };
    }
</script>
<div x-data="wpStoreAddToWishlist({
        id: <?php echo (int) $id; ?>,
        iconOnly: <?php echo isset($icon_only) && $icon_only ? 'true' : 'false'; ?>,
        loggedIn: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
        loginUrl: '<?php echo esc_js(wp_login_url((function () use ($id) {
                        if (is_singular('store_product') && $id) {
                            return get_permalink($id);
                        }
                        $req = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
                        return home_url($req);
                    })())); ?>'
    })" x-init="init()">
    <button type="button"
        :disabled="loading"
        @click="inWishlist ? remove() : add()"
        class="<?php echo esc_attr($btn_class); ?> wps-wishlist-btn"
        :style="loading ? 'opacity:.7; pointer-events:none;' : ''">
        <template x-if="loading">
            <span>
                <?php echo wps_icon(['name' => 'spinner', 'size' => 18, 'class' => 'wps-mr-0']); ?>
            </span>
        </template>
        <template x-if="!loading && inWishlist">
            <span>
                <?php echo wps_icon(['name' => 'heart', 'size' => 18, 'stroke_color' => '#f472b6', 'class' => 'wps-mr-0']); ?>
            </span>
        </template>
        <template x-if="!loading && !inWishlist">
            <span>
                <?php echo wps_icon(['name' => 'heart', 'size' => 18, 'class' => 'wps-mr-0']); ?>
            </span>
        </template>
        <span x-show="!iconOnly" class="wps-pl-1"><?php echo esc_html($label_add); ?></span>
    </button>
    <div x-show="toastShow" x-transition x-cloak
        :style="'position:fixed;bottom:30px;right:30px;padding:12px 16px;background:#fff;box-shadow:0 3px 10px rgba(0,0,0,.1);border-left:4px solid ' + (toastType === 'success' ? '#46b450' : '#d63638') + ';border-radius:4px;z-index:9999;'">
        <span x-text="toastMessage" class="wps-text-sm wps-text-gray-900"></span>
    </div>
    <div x-show="showLoginModal" x-cloak class="wps-modal-backdrop" @click.self="showLoginModal = false" @keydown.escape.window="showLoginModal = false" style="position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:10000;"></div>
    <div x-show="showLoginModal" x-cloak class="wps-modal" style="position:fixed;top:50%;left:50%;transform:translate(-50%, -50%);background:#ffffff;border-radius:0.2rem;box-shadow:0 10px 25px rgba(0,0,0,0.2);width:480px;max-width:95vw;z-index:10001;padding:16px;">
        <div class="wps-text-lg wps-font-medium wps-text-gray-900" style="margin-bottom:8px;">Butuh Login</div>
        <div class="wps-text-sm wps-text-gray-700" style="margin-bottom:12px;">Silakan login untuk menambahkan wishlist.</div>
        <div class="wps-flex wps-justify-between wps-items-center">
            <button type="button" class="wps-btn wps-btn-secondary wps-btn-sm" @click="showLoginModal = false">Batal</button>
            <a :href="loginUrl" class="wps-btn wps-btn-primary wps-btn-sm">Login</a>
        </div>
    </div>
</div>