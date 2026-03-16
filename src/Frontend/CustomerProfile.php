<?php

namespace WpStore\Frontend;

class CustomerProfile
{
    public function register()
    {
        add_shortcode('store_customer_profile', [$this, 'render_profile']);
        add_shortcode('wp_store_profile', [$this, 'render_profile']);
    }

    public function render_profile($atts = [])
    {
        if (!is_user_logged_in()) {
            $redirect = esc_url(site_url('/profil-saya/'));
            $login_url = esc_url(wp_login_url($redirect));
            $can_register = (bool) get_option('users_can_register');
            $register_url = $can_register ? esc_url(function_exists('wp_registration_url') ? wp_registration_url() : site_url('/wp-login.php?action=register')) : '';
            ob_start();
?>
            <div class="wps-container" style="max-width: 720px; margin: 40px auto;">
                <div class="wps-card">
                    <div class="wps-p-6 wps-text-center">
                        <h2 class="wps-text-xl wps-font-medium wps-text-gray-900">Masuk untuk Mengelola Profil</h2>
                        <p class="wps-mt-2 wps-text-sm wps-text-gray-600">Silakan masuk untuk melihat dan memperbarui data profil, buku alamat, serta wishlist Anda.</p>
                        <div class="wps-flex wps-justify-center wps-items-center wps-gap-3 wps-mt-4">
                            <a href="<?php echo $login_url; ?>" class="wps-btn wps-btn-primary">Masuk</a>
                            <?php if ($can_register && $register_url) : ?>
                                <a href="<?php echo $register_url; ?>" class="wps-btn wps-btn-secondary">Daftar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            return ob_get_clean();
        }

        wp_enqueue_script('alpinejs');
        // Ensure our localize script is available if we use the same handle, 
        // but Shortcode.php registers 'wp-store-frontend'. 
        // We might need to make sure the localized vars are available here too.
        // For safety, I'll enqueue 'wp-store-frontend' which has the vars, 
        // even if I write the specific JS inline for this component.
        wp_enqueue_script('wp-store-frontend');
        wp_enqueue_style('wp-store-frontend-css');

        $settings = get_option('wp_store_settings', []);
        $currency = ($settings['currency_symbol'] ?? 'Rp');
        $nonce = wp_create_nonce('wp_rest');
        $initial_items = [];
        $orders = [];
        $tracking_id = isset($settings['page_tracking']) ? absint($settings['page_tracking']) : 0;
        $tracking_base = $tracking_id ? get_permalink($tracking_id) : site_url('/tracking-order/');
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $user = get_userdata($uid);
            $email = $user ? $user->user_email : '';
            $meta_query = [
                'relation' => 'OR',
                [
                    'key' => '_store_order_user_id',
                    'value' => $uid,
                    'compare' => '='
                ],
                [
                    'relation' => 'AND',
                    [
                        'key' => '_store_order_user_id',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key' => '_store_order_email',
                        'value' => $email,
                        'compare' => '='
                    ]
                ]
            ];
            $q = new \WP_Query([
                'post_type' => 'store_order',
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => $meta_query
            ]);
            foreach ($q->posts as $p) {
                $oid = $p->ID;
                $order_number = get_post_meta($oid, '_store_order_number', true);
                if (!$order_number) {
                    $order_number = $oid;
                }
                $orders[] = [
                    'id' => $oid,
                    'order_number' => $order_number,
                    'date' => get_the_date('d M Y', $oid),
                    'total' => (float) get_post_meta($oid, '_store_order_total', true),
                    'status' => (string) (get_post_meta($oid, '_store_order_status', true) ?: 'pending'),
                    'tracking_url' => add_query_arg(['order' => $order_number], $tracking_base),
                    'items' => (array) (get_post_meta($oid, '_store_order_items', true) ?: [])
                ];
            }
            wp_reset_postdata();
        }
        ob_start();
        ?>

        <script>
            // Ensure wpStoreSettings is available
            if (typeof wpStoreSettings === 'undefined') {
                var wpStoreSettings = <?php echo json_encode([
                                            'restUrl' => esc_url_raw(rest_url('wp-store/v1/')),
                                            'nonce' => wp_create_nonce('wp_rest'),
                                        ]); ?>;
            }
        </script>

        <div class="wps-profile-wrapper" x-data="storeCustomerProfile()" x-init="init()">
            <div class="wps-card wps-p-4" style="margin-bottom: 1rem;">
                <div id="wps-profile-tabs" class="wps-tabs">
                    <button @click="tab = 'profile'" :class="{ 'active': tab === 'profile' }" class="wps-tab wps-flex wps-align-items-center">
                        <?php echo wps_icon(['name' => 'user', 'size' => 16, 'class' => 'wps-mr-2']); ?>Profil Saya
                    </button>
                    <button @click="tab = 'addresses'" :class="{ 'active': tab === 'addresses' }" class="wps-tab wps-flex wps-align-items-center">
                        <?php echo wps_icon(['name' => 'settings', 'size' => 16, 'class' => 'wps-mr-2']); ?>Buku Alamat
                    </button>
                    <button @click="tab = 'wishlist'" :class="{ 'active': tab === 'wishlist' }" class="wps-tab wps-flex wps-align-items-center">
                        <?php echo wps_icon(['name' => 'heart', 'size' => 16, 'class' => 'wps-mr-2']); ?>Wishlist <span class="wps-badge" x-text="wishlistCount" x-show="wishlistCount > 0" style="margin-left:6px;"></span>
                    </button>
                    <button @click="tab = 'orders'" :class="{ 'active': tab === 'orders' }" class="wps-tab wps-flex wps-align-items-center">
                        <?php echo wps_icon(['name' => 'cart', 'size' => 16, 'class' => 'wps-mr-2']); ?>Pesanan
                    </button>
                    <?php do_action('wp_store_profile_additional_tabs'); ?>
                    <a href="<?php echo esc_url(wp_logout_url(site_url('/'))); ?>" class="wps-tab wps-ml-auto wps-flex wps-align-items-center"><?php echo wps_icon(['name' => 'logout', 'size' => 16, 'class' => 'wps-mr-2']); ?>Logout</a>
                </div>
                <script>
                    (function() {
                        var el = document.getElementById('wps-profile-tabs');
                        if (!el) return;
                        var down = false;
                        var startX = 0;
                        var startScroll = 0;
                        el.addEventListener('mousedown', function(e) {
                            if (e.button !== 0) return;
                            down = true;
                            startX = e.pageX;
                            startScroll = el.scrollLeft;
                            el.style.cursor = 'grabbing';
                        });
                        document.addEventListener('mousemove', function(e) {
                            if (!down) return;
                            var dx = e.pageX - startX;
                            el.scrollLeft = startScroll - dx;
                            e.preventDefault();
                        });
                        document.addEventListener('mouseup', function() {
                            if (!down) return;
                            down = false;
                            el.style.cursor = 'grab';
                        });
                        el.addEventListener('mouseleave', function() {
                            if (!down) return;
                            down = false;
                            el.style.cursor = 'grab';
                        });
                        el.style.cursor = 'grab';
                    })();
                </script>
            </div>

            <!-- Notification -->
            <div x-show="message" x-transition class="wps-alert wps-alert-success" x-text="message"></div>
            <div x-show="toastShow" x-transition x-cloak
                :style="'position:fixed;bottom:30px;right:30px;padding:12px 16px;background:#fff;box-shadow:0 3px 10px rgba(0,0,0,.1);border-left:4px solid ' + (toastType === 'success' ? '#46b450' : '#d63638') + ';border-radius:4px;z-index:9999;'">
                <span class="wps-text-sm wps-text-gray-900" x-text="toastMessage"></span>
            </div>

            <!-- Profile Tab -->
            <div x-show="tab === 'profile'">
                <div class="wps-card">
                    <div class="wps-p-6 wps-pb-0 border-b border-gray-200">
                        <h2 class="wps-text-lg wps-font-medium wps-text-gray-900">Informasi Profil</h2>
                        <p class="wps-mt-1 wps-text-sm wps-text-gray-500">Perbarui informasi profil dan detail kontak Anda.</p>
                    </div>
                    <div class="wps-p-6">
                        <form @submit.prevent="saveProfile">
                            <div class="wps-flex wps-items-center wps-gap-4" style="margin-bottom:12px;">
                                <img :src="profile.avatar_url || '<?php echo esc_url(WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>'" alt="Foto Profil" style="width:72px;height:72px;border-radius:9999px;object-fit:cover;border:1px solid #e5e7eb;">
                                <div>
                                    <button type="button" class="wps-btn wps-btn-secondary" @click="$refs.avatarInput.click()">Ubah Foto</button>
                                    <input type="file" x-ref="avatarInput" @change="onAvatarSelected" accept="image/*" style="display:none;">
                                    <p class="wps-text-xs wps-text-gray-500" style="margin-top:6px;">PNG/JPG/WebP, maks ~2MB</p>
                                </div>
                            </div>
                            <div class="wps-grid wps-grid-cols-2 wps-gap-4" style="grid-template-columns: 1fr 1fr; display: grid; gap: 1rem;">
                                <div class="wps-form-group">
                                    <label class="wps-label">Nama Depan</label>
                                    <input type="text" x-model="profile.first_name" required class="wps-input">
                                </div>
                                <div class="wps-form-group">
                                    <label class="wps-label">Nama Belakang</label>
                                    <input type="text" x-model="profile.last_name" class="wps-input">
                                </div>
                            </div>
                            <div class="wps-form-group">
                                <label class="wps-label">Email</label>
                                <input type="text" x-model="profile.email" disabled class="wps-input" style="background-color: #f9fafb;">
                            </div>
                            <div class="wps-form-group">
                                <label class="wps-label">No. Telepon</label>
                                <input type="text" x-model="profile.phone" class="wps-input">
                            </div>
                            <div class="wps-flex" style="justify-content: flex-end; margin-top: 1rem;">
                                <button type="submit" class="wps-btn wps-btn-primary" :disabled="loading">
                                    <template x-if="loading">
                                        <span class="wps-mr-2"><?php echo wps_icon(['name' => 'spinner', 'size' => 16, 'class' => 'wps-mr-2']); ?></span>
                                    </template>
                                    <template x-if="!loading">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="wps-mr-2" viewBox="0 0 16 16">
                                            <path d="M12 2h-2v3h2z" />
                                            <path d="M1.5 0A1.5 1.5 0 0 0 0 1.5v13A1.5 1.5 0 0 0 1.5 16h13a1.5 1.5 0 0 0 1.5-1.5V2.914a1.5 1.5 0 0 0-.44-1.06L14.147.439A1.5 1.5 0 0 0 13.086 0zM4 6a1 1 0 0 1-1-1V1h10v4a1 1 0 0 1-1 1zM3 9h10a1 1 0 0 1 1 1v5H2v-5a1 1 0 0 1 1-1" />
                                        </svg>
                                    </template>
                                    <span x-show="!loading">Simpan Perubahan</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php do_action('wp_store_profile_after_profile_tab'); ?>
            </div>

            <!-- Addresses Tab -->
            <div x-show="tab === 'addresses'">
                <div x-show="!isEditingAddress">
                    <div class="wps-card wps-p-6">
                        <div class="wps-flex wps-justify-between wps-items-center wps-mb-6">
                            <h2 class="wps-text-lg wps-font-medium wps-text-gray-900">Daftar Alamat</h2>
                            <button @click="resetAddressForm(); isEditingAddress = true" class="wps-btn wps-btn-primary">
                                <svg class="wps-w-5 wps-h-5" style="width: 20px; height: 20px; margin-right: 5px;" fill="none" stroke="#fff" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Tambah Alamat
                            </button>
                        </div>
                        <div x-show="addressesLoading" class="wps-grid wps-gap-4" style="display: none; grid-template-columns: none; gap: 1rem;">
                            <div class="wps-card wps-p-4">
                                <div class="wps-flex wps-justify-between wps-items-start">
                                    <div style="flex: 1;">
                                        <div class="wps-skeleton wps-skeleton-text" style="width: 80px; height: 16px;"></div>
                                        <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 100%;"></div>
                                        <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 70%;"></div>
                                    </div>
                                    <div class="wps-flex wps-space-x-2">
                                        <div class="wps-skeleton" style="width: 64px; height: 28px; border-radius: 4px;"></div>
                                        <div class="wps-skeleton" style="width: 64px; height: 28px; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="wps-card wps-p-4">
                                <div class="wps-flex wps-justify-between wps-items-start">
                                    <div style="flex: 1;">
                                        <div class="wps-skeleton wps-skeleton-text" style="width: 60px; height: 16px;"></div>
                                        <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 90%;"></div>
                                        <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 65%;"></div>
                                    </div>
                                    <div class="wps-flex wps-space-x-2">
                                        <div class="wps-skeleton" style="width: 64px; height: 28px; border-radius: 4px;"></div>
                                        <div class="wps-skeleton" style="width: 64px; height: 28px; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="wps-card wps-p-4">
                                <div class="wps-flex wps-justify-between wps-items-start">
                                    <div style="flex: 1;">
                                        <div class="wps-skeleton wps-skeleton-text" style="width: 50px; height: 16px;"></div>
                                        <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 85%;"></div>
                                        <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 60%;"></div>
                                    </div>
                                    <div class="wps-flex wps-space-x-2">
                                        <div class="wps-skeleton" style="width: 64px; height: 28px; border-radius: 4px;"></div>
                                        <div class="wps-skeleton" style="width: 64px; height: 28px; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wps-grid wps-gap-4" x-show="!addressesLoading" style="display: none; grid-template-columns: none; gap: 1rem;">
                            <template x-if="!addressesLoading && addresses.length === 0">
                                <div class="wps-card wps-p-6 wps-text-center wps-text-gray-500">
                                    Belum ada alamat tersimpan.
                                </div>
                            </template>
                            <template x-for="addr in addresses" :key="addr.id">
                                <div class="wps-card wps-p-4">
                                    <div class="wps-flex wps-justify-between wps-items-start">
                                        <div>
                                            <div class="wps-flex wps-items-center wps-mb-2">
                                                <span class="wps-badge wps-bg-blue-500 wps-text-white wps-text-xs wps-font-medium wps-px-2.5 wps-py-0.5 rounded-full" x-text="addr.label"></span>
                                            </div>
                                            <p class="wps-text-sm wps-text-gray-900 wps-mb-1" x-text="addr.address"></p>
                                            <p class="wps-text-sm wps-text-gray-500">
                                                <span x-text="addr.subdistrict_name"></span>,
                                                <span x-text="addr.city_name"></span>,
                                                <span x-text="addr.province_name"></span>
                                                <span x-text="addr.postal_code"></span>
                                            </p>
                                        </div>
                                        <div class="wps-flex wps-space-x-2">
                                            <button @click="editAddress(addr)" class="wps-btn wps-btn-secondary wps-text-sm" style="padding: 0.25rem 0.5rem;">
                                                <span><?php echo wps_icon(['name' => 'sliders2', 'size' => 16, 'class' => 'wps-mr-2']); ?></span>
                                                <span>Edit</span>
                                            </button>
                                            <button @click="deleteAddress(addr.id)" class="wps-btn wps-btn-danger wps-text-sm" style="padding: 0.25rem 0.5rem;">
                                                <span><?php echo wps_icon(['name' => 'trash', 'size' => 16, 'class' => 'wps-mr-2']); ?></span>
                                                <span>Hapus</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Address Form -->
                <div x-show="isEditingAddress">
                    <div class="wps-card">
                        <div class="wps-p-6 wps-mb-0 border-b border-gray-200 wps-flex wps-justify-between wps-items-center">
                            <h2 class="wps-text-lg wps-font-medium wps-text-gray-900" x-text="addressForm.id ? 'Edit Alamat' : 'Tambah Alamat Baru'"></h2>
                            <button @click="cancelEdit()" class="wps-btn-icon wps-text-amber-600 wps-hover-text-amber-700" title="Tutup">
                                <svg class="wps-icon-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="wps-p-6">
                            <form @submit.prevent="saveAddress">
                                <div class="wps-form-group">
                                    <label class="wps-label">Label Alamat</label>
                                    <input type="text" x-model="addressForm.label" placeholder="Contoh: Rumah, Kantor" required class="wps-input">
                                </div>

                                <div class="wps-form-group">
                                    <label class="wps-label">Provinsi</label>
                                    <select x-model="addressForm.province_id" @change="onProvinceChange()" class="wps-select" :disabled="isLoadingProvinces">
                                        <option value="">Pilih Provinsi</option>
                                        <template x-for="prov in provinces" :key="prov.province_id">
                                            <option :value="prov.province_id" x-text="prov.province"></option>
                                        </template>
                                    </select>
                                    <span x-show="isLoadingProvinces" class="wps-text-sm wps-text-gray-500">Memuat...</span>
                                </div>

                                <div class="wps-grid wps-grid-cols-2 wps-gap-4" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="wps-form-group">
                                        <label class="wps-label">Kota/Kabupaten</label>
                                        <select x-model="addressForm.city_id" @change="onCityChange()" class="wps-select" :disabled="!addressForm.province_id || isLoadingCities">
                                            <option value="">Pilih Kota/Kabupaten</option>
                                            <template x-for="city in cities" :key="city.city_id">
                                                <option :value="String(city.city_id)" x-text="city.type + ' ' + city.city_name" :selected="city.city_id == addressForm.city_id"></option>
                                            </template>
                                        </select>
                                        <span x-show="isLoadingCities" class="wps-text-sm wps-text-gray-500">Memuat...</span>
                                    </div>

                                    <div class="wps-form-group">
                                        <label class="wps-label">Kecamatan</label>
                                        <select x-model="addressForm.subdistrict_id" @change="onSubdistrictChange()" class="wps-select" :disabled="!addressForm.city_id || isLoadingSubdistricts">
                                            <option value="">Pilih Kecamatan</option>
                                            <template x-for="sub in subdistricts" :key="sub.subdistrict_id">
                                                <option :value="String(sub.subdistrict_id)" x-text="sub.subdistrict_name" :selected="sub.subdistrict_id == addressForm.subdistrict_id"></option>
                                            </template>
                                        </select>
                                        <span x-show="isLoadingSubdistricts" class="wps-text-sm wps-text-gray-500">Memuat...</span>
                                    </div>
                                </div>

                                <div class="wps-form-group">
                                    <label class="wps-label">Kode Pos</label>
                                    <input type="text" x-model="addressForm.postal_code" required class="wps-input">
                                </div>

                                <div class="wps-form-group">
                                    <label class="wps-label">Alamat Lengkap</label>
                                    <textarea x-model="addressForm.address" required rows="3" class="wps-textarea"></textarea>
                                </div>

                                <div class="wps-flex wps-justify-between wps-items-center wps-mt-6">
                                    <button type="button" @click="cancelEdit()" class="wps-btn wps-btn-secondary">Batal</button>
                                    <button type="submit" class="wps-btn wps-btn-primary" :disabled="loading">
                                        <template x-if="loading">
                                            <span class="wps-mr-2"><?php echo wps_icon(['name' => 'spinner', 'size' => 16, 'class' => 'wps-mr-2']); ?></span>
                                        </template>
                                        <template x-if="!loading">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="wps-mr-2" viewBox="0 0 16 16">
                                                <path d="M12 2h-2v3h2z" />
                                                <path d="M1.5 0A1.5 1.5 0 0 0 0 1.5v13A1.5 1.5 0 0 0 1.5 16h13a1.5 1.5 0 0 0 1.5-1.5V2.914a1.5 1.5 0 0 0-.44-1.06L14.147.439A1.5 1.5 0 0 0 13.086 0zM4 6a1 1 0 0 1-1-1V1h10v4a1 1 0 0 1-1 1zM3 9h10a1 1 0 0 1 1 1v5H2v-5a1 1 0 0 1 1-1" />
                                            </svg>
                                        </template>
                                        <span x-show="!loading">Simpan Alamat</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wishlist Tab -->
            <div x-show="tab === 'wishlist'">
                <div class="wps-card">
                    <div class="wps-p-6">
                        <?php echo \WpStore\Frontend\Template::render('components/wishlist-widget', [
                            'currency' => $currency,
                            'nonce' => $nonce,
                            'initial_items' => $initial_items
                        ]); ?>
                    </div>
                </div>
            </div>

            <!-- Orders Tab -->
            <div x-show="tab === 'orders'">
                <div class="wps-card wps-p-6">
                    <div class="wps-flex wps-justify-between wps-items-center wps-mb-4">
                        <h2 class="wps-text-lg wps-font-medium wps-text-gray-900">Riwayat Pesanan</h2>
                    </div>
                    <div x-show="ordersLoading" class="wps-grid wps-gap-4" style="display: none; grid-template-columns: none; gap: 1rem;">
                        <div class="wps-card wps-p-4">
                            <div class="wps-flex wps-justify-between wps-items-center">
                                <div>
                                    <div class="wps-skeleton wps-skeleton-text" style="width: 120px; height: 16px;"></div>
                                    <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 80px; height: 12px;"></div>
                                </div>
                                <div class="wps-skeleton wps-skeleton-text" style="width: 80px; height: 16px;"></div>
                            </div>
                            <div class="wps-flex wps-justify-between wps-items-center wps-mt-3">
                                <div class="wps-skeleton" style="width: 90px; height: 20px; border-radius: 9999px;"></div>
                                <div class="wps-skeleton" style="width: 90px; height: 28px; border-radius: 4px;"></div>
                            </div>
                            <div class="wps-mt-3">
                                <div class="wps-skeleton wps-skeleton-text" style="width: 60px; height: 10px;"></div>
                                <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 100%; height: 12px;"></div>
                                <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 90%; height: 12px;"></div>
                            </div>
                        </div>
                        <div class="wps-card wps-p-4">
                            <div class="wps-flex wps-justify-between wps-items-center">
                                <div>
                                    <div class="wps-skeleton wps-skeleton-text" style="width: 130px; height: 16px;"></div>
                                    <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 70px; height: 12px;"></div>
                                </div>
                                <div class="wps-skeleton wps-skeleton-text" style="width: 90px; height: 16px;"></div>
                            </div>
                            <div class="wps-flex wps-justify-between wps-items-center wps-mt-3">
                                <div class="wps-skeleton" style="width: 90px; height: 20px; border-radius: 9999px;"></div>
                                <div class="wps-skeleton" style="width: 90px; height: 28px; border-radius: 4px;"></div>
                            </div>
                            <div class="wps-mt-3">
                                <div class="wps-skeleton wps-skeleton-text" style="width: 70px; height: 10px;"></div>
                                <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 95%; height: 12px;"></div>
                                <div class="wps-skeleton wps-skeleton-text wps-mt-2" style="width: 85%; height: 12px;"></div>
                            </div>
                        </div>
                    </div>
                    <template x-if="!ordersLoading && orders.length === 0">
                        <div class="wps-text-center wps-text-gray-500">Belum ada pesanan.</div>
                    </template>
                    <div class="wps-grid wps-gap-4" x-show="!ordersLoading" style="display: none; grid-template-columns: none; gap: 1rem;">
                        <template x-for="order in orders" :key="order.id">
                            <div class="wps-card wps-p-4">
                                <div class="wps-flex wps-justify-between wps-items-center">
                                    <div>
                                        <div class="wps-text-sm wps-text-gray-900 wps-font-medium">#<span x-text="order.order_number || order.id"></span></div>
                                        <div class="wps-text-xs wps-text-gray-500" x-text="order.date"></div>
                                    </div>
                                    <div class="wps-text-sm wps-text-gray-900 wps-font-medium"><?php echo esc_html(($currency ?: 'Rp')); ?> <span x-text="formatCurrency(order.total)"></span></div>
                                </div>
                                <div class="wps-flex wps-justify-between wps-items-center wps-mt-3">
                                    <span class="wps-badge wps-bg-blue-500 wps-text-white wps-text-xs wps-font-medium wps-px-2.5 wps-py-0.5 rounded-full" x-text="statusLabel(order.status)"></span>
                                    <a :href="order.tracking_url" class="wps-btn wps-btn-secondary">Tracking</a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <?php do_action('wp_store_profile_additional_panels'); ?>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('storeCustomerProfile', () => ({
                    tab: 'profile',
                    loading: false,
                    message: '',
                    toastShow: false,
                    toastType: 'success',
                    toastMessage: '',
                    wishlistCount: 0,
                    orders: [],
                    ordersLoading: false,
                    profile: {
                        first_name: '',
                        last_name: '',
                        email: '',
                        phone: '',
                        avatar_url: ''
                    },
                    addresses: [],
                    addressesLoading: false,
                    isEditingAddress: false,

                    // Address Form Data
                    addressForm: {
                        id: null,
                        label: '',
                        address: '',
                        province_id: '',
                        province_name: '',
                        city_id: '',
                        city_name: '',
                        subdistrict_id: '',
                        subdistrict_name: '',
                        postal_code: ''
                    },

                    // VD Ongkir Data
                    provinces: [],
                    cities: [],
                    subdistricts: [],
                    isLoadingProvinces: false,
                    isLoadingCities: false,
                    isLoadingSubdistricts: false,

                    init() {
                        const urlParams = new URLSearchParams(window.location.search);
                        const tabParam = urlParams.get('tab');
                        if (tabParam && ['profile', 'addresses', 'wishlist', 'orders', 'vendor'].includes(tabParam)) {
                            this.tab = tabParam;
                        }

                        this.$watch('tab', (value) => {
                            const url = new URL(window.location);
                            url.searchParams.set('tab', value);
                            window.history.pushState({}, '', url);
                            if (value === 'orders' && this.orders.length === 0) {
                                this.fetchOrders();
                            }
                            if (value === 'addresses' && this.addresses.length === 0) {
                                this.fetchAddresses();
                            }
                        });

                        this.$watch('addressForm.city_id', async (val) => {
                            if (val) {
                                await this.loadSubdistricts(val);
                            } else {
                                this.subdistricts = [];
                            }
                        });

                        this.fetchProfile();
                        this.fetchAddresses().then(() => {
                            const editParam = urlParams.get('edit');
                            if (editParam && this.addresses.length > 0) {
                                const addr = this.addresses.find(a => String(a.id) === String(editParam));
                                if (addr) {
                                    this.editAddress(addr);
                                    this.tab = 'addresses';
                                }
                            }
                        });
                        this.loadProvinces(); // Load provinces early
                        this.fetchWishlistCount();
                        if (this.tab === 'orders') {
                            this.fetchOrders();
                        }
                        document.addEventListener('wp-store:wishlist-updated', (e) => {
                            const data = e.detail || {};
                            const items = Array.isArray(data.items) ? data.items : [];
                            this.wishlistCount = items.length;
                        });
                    },
                    formatCurrency(n) {
                        try {
                            return Number(n || 0).toLocaleString('id-ID');
                        } catch (e) {
                            return String(n || 0);
                        }
                    },
                    statusLabel(s) {
                        const m = {
                            pending: 'Pending',
                            awaiting_payment: 'Menunggu Pembayaran',
                            paid: 'Sudah Dibayar',
                            processing: 'Sedang Diproses',
                            shipped: 'Dikirim',
                            completed: 'Selesai',
                            cancelled: 'Dibatalkan'
                        };
                        return m[s] || s;
                    },

                    async fetchOrders() {
                        try {
                            this.ordersLoading = true;
                            const res = await fetch(wpStoreSettings.restUrl + 'customer/orders', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            this.orders = Array.isArray(data) ? data : [];
                        } catch (err) {
                            this.orders = [];
                        } finally {
                            this.ordersLoading = false;
                        }
                    },

                    async fetchProfile() {
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'customer/profile', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            this.profile = data;
                        } catch (err) {
                            console.error(err);
                        }
                    },

                    onAvatarSelected(e) {
                        const file = e.target && e.target.files && e.target.files[0] ? e.target.files[0] : null;
                        if (!file) return;
                        this.uploadAvatar(file).finally(() => {
                            try {
                                e.target.value = '';
                            } catch (_) {}
                        });
                    },

                    async uploadAvatar(file) {
                        this.loading = true;
                        try {
                            const fd = new FormData();
                            fd.append('avatar', file);
                            const res = await fetch(wpStoreSettings.restUrl + 'customer/avatar', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                },
                                body: fd
                            });
                            const data = await res.json();
                            if (res.ok) {
                                this.profile.avatar_url = data.avatar_url || this.profile.avatar_url;
                                this.showToast(data.message || 'Foto profil diperbarui', 'success');
                            } else {
                                const msg = data && data.message ? data.message : 'Gagal memperbarui foto profil';
                                this.showToast(msg, 'error');
                            }
                        } catch (err) {
                            this.showToast('Kesalahan jaringan saat unggah foto', 'error');
                        } finally {
                            this.loading = false;
                        }
                    },

                    async fetchWishlistCount() {
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'wishlist', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            this.wishlistCount = Array.isArray(data.items) ? data.items.length : 0;
                        } catch (err) {
                            this.wishlistCount = 0;
                        }
                    },

                    async saveProfile() {
                        this.loading = true;
                        this.message = '';
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'customer/profile', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                },
                                body: JSON.stringify(this.profile)
                            });
                            const data = await res.json();
                            if (res.ok) {
                                this.message = data.message || 'Profil berhasil diperbarui';
                                this.showToast(this.message, 'success');
                            } else {
                                const msg = data && data.message ? data.message : 'Gagal memperbarui profil';
                                this.message = msg;
                                this.showToast(msg, 'error');
                            }
                            setTimeout(() => this.message = '', 3000);
                        } catch (err) {
                            const msg = 'Terjadi kesalahan jaringan.';
                            this.message = msg;
                            this.showToast(msg, 'error');
                        } finally {
                            this.loading = false;
                        }
                    },

                    showToast(msg, type) {
                        this.toastMessage = msg || '';
                        this.toastType = type === 'success' ? 'success' : 'error';
                        this.toastShow = true;
                        clearTimeout(this._toastTimer);
                        this._toastTimer = setTimeout(() => {
                            this.toastShow = false;
                        }, 2000);
                    },

                    async fetchAddresses() {
                        try {
                            this.addressesLoading = true;
                            const res = await fetch(wpStoreSettings.restUrl + 'customer/addresses', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            this.addresses = await res.json();
                        } catch (err) {
                            console.error(err);
                        } finally {
                            this.addressesLoading = false;
                        }
                    },

                    // --- VD Ongkir Methods ---

                    async loadProvinces() {
                        if (this.provinces.length > 0) return;
                        this.isLoadingProvinces = true;
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'rajaongkir/provinces', {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            this.provinces = (data.data || []).map(p => ({
                                ...p,
                                province_id: String(p.province_id)
                            }));
                        } catch (err) {
                            console.error(err);
                        } finally {
                            this.isLoadingProvinces = false;
                        }
                    },

                    async loadCities(provinceId) {
                        if (!provinceId) {
                            this.cities = [];
                            return;
                        }
                        this.isLoadingCities = true;
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'rajaongkir/cities?province=' + provinceId, {
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });
                            const data = await res.json();
                            this.cities = (data.data || []).map(c => ({
                                ...c,
                                city_id: String(c.city_id)
                            }));
                            if (this.addressForm.city_id) {
                                const exists = this.cities.some(c => String(c.city_id) === String(this.addressForm.city_id));
                                if (!exists && this.addressForm.city_name) {
                                    const norm = (s) => String(s).toLowerCase().trim();
                                    const foundCity = this.cities.find(c =>
                                        norm(c.city_name) === norm(this.addressForm.city_name) ||
                                        norm((c.type ? c.type + ' ' : '') + c.city_name) === norm(this.addressForm.city_name)
                                    );
                                    if (foundCity) {
                                        this.addressForm.city_id = String(foundCity.city_id);
                                        this.addressForm.city_name = (foundCity.type ? foundCity.type + ' ' : '') + foundCity.city_name;
                                        if (foundCity.postal_code) {
                                            this.addressForm.postal_code = foundCity.postal_code;
                                        }
                                    }
                                }
                            } else if (this.addressForm.city_name) {
                                const norm = (s) => String(s).toLowerCase().trim();
                                const foundCity = this.cities.find(c =>
                                    norm(c.city_name) === norm(this.addressForm.city_name) ||
                                    norm((c.type ? c.type + ' ' : '') + c.city_name) === norm(this.addressForm.city_name)
                                );
                                if (foundCity) {
                                    this.addressForm.city_id = String(foundCity.city_id);
                                    this.addressForm.city_name = (foundCity.type ? foundCity.type + ' ' : '') + foundCity.city_name;
                                    if (foundCity.postal_code) {
                                        this.addressForm.postal_code = foundCity.postal_code;
                                    }
                                }
                            }
                        } catch (err) {
                            console.error(err);
                        } finally {
                            this.isLoadingCities = false;
                        }
                    },

                    async loadSubdistricts(cityId) {
                        if (!cityId) {
                            this.subdistricts = [];
                            return;
                        }
                        this.isLoadingSubdistricts = true;
                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'rajaongkir/subdistricts?city=' + cityId, {
                                credentials: 'same-origin'
                            });
                            const data = await res.json();
                            this.subdistricts = (data.data || []).map(s => ({
                                ...s,
                                subdistrict_id: String(s.subdistrict_id)
                            }));
                            if (this.addressForm.subdistrict_id) {
                                const exists = this.subdistricts.some(s => String(s.subdistrict_id) === String(this.addressForm.subdistrict_id));
                                if (!exists && this.addressForm.subdistrict_name) {
                                    const norm = (str) => String(str).toLowerCase().trim();
                                    const foundSub = this.subdistricts.find(s => norm(s.subdistrict_name) === norm(this.addressForm.subdistrict_name));
                                    if (foundSub) {
                                        this.addressForm.subdistrict_id = String(foundSub.subdistrict_id);
                                        this.addressForm.subdistrict_name = foundSub.subdistrict_name;
                                    }
                                }
                            } else if (this.addressForm.subdistrict_name) {
                                const norm = (str) => String(str).toLowerCase().trim();
                                const foundSub = this.subdistricts.find(s => norm(s.subdistrict_name) === norm(this.addressForm.subdistrict_name));
                                if (foundSub) {
                                    this.addressForm.subdistrict_id = String(foundSub.subdistrict_id);
                                    this.addressForm.subdistrict_name = foundSub.subdistrict_name;
                                }
                            }
                        } catch (err) {
                            console.error(err);
                        } finally {
                            this.isLoadingSubdistricts = false;
                        }
                    },

                    async onProvinceChange() {
                        this.addressForm.city_id = '';
                        this.addressForm.city_name = '';
                        this.addressForm.subdistrict_id = '';
                        this.addressForm.subdistrict_name = '';
                        this.cities = [];
                        this.subdistricts = [];

                        // Update province name
                        const selected = this.provinces.find(p => p.province_id == this.addressForm.province_id);
                        if (selected) {
                            this.addressForm.province_name = selected.province;
                        }

                        if (this.addressForm.province_id) {
                            await this.loadCities(this.addressForm.province_id);
                        }
                    },

                    async onCityChange() {
                        this.addressForm.subdistrict_id = '';
                        this.addressForm.subdistrict_name = '';
                        this.subdistricts = [];

                        // Update city name
                        const selected = this.cities.find(c => c.city_id == this.addressForm.city_id);
                        if (selected) {
                            this.addressForm.city_name = selected.type + ' ' + selected.city_name;
                            // Optional: auto-fill postal code if available from city (RajaOngkir city data has postal_code)
                            if (selected.postal_code) {
                                this.addressForm.postal_code = selected.postal_code;
                            }
                        }

                        if (this.addressForm.city_id) {
                            await this.loadSubdistricts(this.addressForm.city_id);
                        }
                    },

                    onSubdistrictChange() {
                        const selected = this.subdistricts.find(s => s.subdistrict_id == this.addressForm.subdistrict_id);
                        if (selected) {
                            this.addressForm.subdistrict_name = selected.subdistrict_name;
                        }
                    },

                    // --- Form Handling ---

                    resetAddressForm() {
                        this.addressForm = {
                            id: null,
                            label: '',
                            address: '',
                            province_id: '',
                            province_name: '',
                            city_id: '',
                            city_name: '',
                            subdistrict_id: '',
                            subdistrict_name: '',
                            postal_code: ''
                        };
                        this.cities = [];
                        this.subdistricts = [];
                    },

                    async editAddress(addr) {
                        this.resetAddressForm();
                        // Copy values
                        this.addressForm = {
                            ...addr,
                            province_id: addr.province_id != null ? String(addr.province_id) : '',
                            city_id: addr.city_id != null ? String(addr.city_id) : '',
                            subdistrict_id: addr.subdistrict_id != null ? String(addr.subdistrict_id) : '',
                            city_name: addr.city_name ?? addr.city ?? '',
                            subdistrict_name: addr.subdistrict_name ?? ''
                        };

                        // Trigger loads
                        if (this.addressForm.province_id) {
                            await this.loadCities(this.addressForm.province_id);
                            // Fallback by city_name if city_id missing
                            if (!this.addressForm.city_id && this.addressForm.city_name) {
                                const norm = (s) => String(s).toLowerCase().trim();
                                const foundCity = this.cities.find(c =>
                                    norm(c.city_name) === norm(this.addressForm.city_name) ||
                                    norm((c.type ? c.type + ' ' : '') + c.city_name) === norm(this.addressForm.city_name)
                                );
                                if (foundCity) {
                                    this.addressForm.city_id = String(foundCity.city_id);
                                    this.addressForm.city_name = (foundCity.type ? foundCity.type + ' ' : '') + foundCity.city_name;
                                    if (foundCity.postal_code) {
                                        this.addressForm.postal_code = foundCity.postal_code;
                                    }
                                }
                            }
                        }
                        if (this.addressForm.city_id) {
                            await this.loadSubdistricts(this.addressForm.city_id);
                            // Fallback by subdistrict_name if subdistrict_id missing
                            if (!this.addressForm.subdistrict_id && this.addressForm.subdistrict_name) {
                                const norm = (s) => String(s).toLowerCase().trim();
                                const foundSub = this.subdistricts.find(s => norm(s.subdistrict_name) === norm(this.addressForm.subdistrict_name));
                                if (foundSub) {
                                    this.addressForm.subdistrict_id = String(foundSub.subdistrict_id);
                                    this.addressForm.subdistrict_name = foundSub.subdistrict_name;
                                }
                            }
                        }

                        this.isEditingAddress = true;
                        this.setEditParam(this.addressForm.id);
                    },

                    async saveAddress() {
                        this.loading = true;
                        try {
                            const isUpdate = !!this.addressForm.id;
                            const url = isUpdate ?
                                wpStoreSettings.restUrl + 'customer/addresses/' + this.addressForm.id :
                                wpStoreSettings.restUrl + 'customer/addresses';

                            const method = isUpdate ? 'PUT' : 'POST';

                            const res = await fetch(url, {
                                method: method,
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                },
                                body: JSON.stringify(this.addressForm)
                            });

                            if (res.ok) {
                                await this.fetchAddresses();
                                this.isEditingAddress = false;
                                this.message = isUpdate ? 'Alamat diperbarui' : 'Alamat ditambahkan';
                                this.showToast(this.message, 'success');
                                setTimeout(() => this.message = '', 3000);
                                this.clearEditParam();
                            } else {
                                const data = await res.json().catch(() => ({}));
                                const msg = data && data.message ? data.message : 'Gagal menyimpan alamat';
                                this.message = msg;
                                this.showToast(msg, 'error');
                                setTimeout(() => this.message = '', 3000);
                            }
                        } catch (err) {
                            const msg = 'Terjadi kesalahan jaringan.';
                            this.message = msg;
                            this.showToast(msg, 'error');
                            setTimeout(() => this.message = '', 3000);
                        } finally {
                            this.loading = false;
                        }
                    },

                    async deleteAddress(id) {
                        if (!confirm('Apakah Anda yakin ingin menghapus alamat ini?')) return;

                        try {
                            const res = await fetch(wpStoreSettings.restUrl + 'customer/addresses/' + id, {
                                method: 'DELETE',
                                credentials: 'same-origin',
                                headers: {
                                    'X-WP-Nonce': wpStoreSettings.nonce
                                }
                            });

                            if (res.ok) {
                                await this.fetchAddresses();
                                this.clearEditParam();
                            }
                        } catch (err) {
                            console.error(err);
                        }
                    },

                    cancelEdit() {
                        this.isEditingAddress = false;
                        this.clearEditParam();
                    },

                    setEditParam(id) {
                        const url = new URL(window.location);
                        url.searchParams.set('tab', 'addresses');
                        url.searchParams.set('edit', id);
                        window.history.pushState({}, '', url);
                    },

                    clearEditParam() {
                        const url = new URL(window.location);
                        url.searchParams.delete('edit');
                        window.history.pushState({}, '', url);
                    }
                }));
            });
        </script>
<?php
        return ob_get_clean();
    }
}
