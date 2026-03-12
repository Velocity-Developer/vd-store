<?php
$settings = get_option('wp_store_settings', []);
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>
<div class="wrap wp-store-wrapper" x-data="storeSettingsManager()">
    <div class="wp-store-header">
        <div>
            <h1 class="wp-store-title">Pengaturan Toko</h1>
            <p class="wp-store-helper">Kelola informasi toko, pembayaran, dan pengaturan sistem.</p>
        </div>
    </div>

    <div class="wp-store-card wp-store-card-settings wp-store-p-0">
        <!-- Tabs Navigation -->
        <div class="wp-store-tabs">
            <div @click="switchTab('general')" class="wp-store-tab" :class="{ 'active': activeTab === 'general' }">
                Umum
            </div>
            <div @click="switchTab('payment')" class="wp-store-tab" :class="{ 'active': activeTab === 'payment' }">
                Pembayaran
            </div>
            <div @click="switchTab('shipping')" class="wp-store-tab" :class="{ 'active': activeTab === 'shipping' }">
                Pengiriman
            </div>
            <div @click="switchTab('pages')" class="wp-store-tab" :class="{ 'active': activeTab === 'pages' }">
                Halaman
            </div>
            <div @click="switchTab('system')" class="wp-store-tab" :class="{ 'active': activeTab === 'system' }">
                Sistem
            </div>
            <div @click="switchTab('style')" class="wp-store-tab" :class="{ 'active': activeTab === 'style' }">
                Style
            </div>
            <div @click="switchTab('tools')" class="wp-store-tab" :class="{ 'active': activeTab === 'tools' }">
                Tool
            </div>
        </div>

        <form @submit.prevent="saveSettings" x-ref="form">
            <input type="hidden" name="active_tab" :value="activeTab">

            <!-- Tab: Umum -->
            <div x-show="activeTab === 'general'" class="wp-store-tab-content" x-cloak>
                <div class="wp-store-form-grid">
                    <div>
                        <label class="wp-store-label" for="store_name">Nama Toko</label>
                        <input name="store_name" type="text" id="store_name" value="<?php echo esc_attr($settings['store_name'] ?? get_bloginfo('name')); ?>" class="wp-store-input" placeholder="Contoh: Toko Serba Ada">
                    </div>

                    <div>
                        <label class="wp-store-label" for="store_address">Alamat Toko</label>
                        <textarea name="store_address" id="store_address" class="wp-store-textarea" rows="3"><?php echo esc_textarea($settings['store_address'] ?? ''); ?></textarea>
                        <p class="wp-store-helper">Alamat lengkap toko untuk invoice/nota.</p>
                    </div>

                    <div class="wp-store-grid-2">
                        <div>
                            <label class="wp-store-label" for="store_email">Email Toko</label>
                            <input name="store_email" type="email" id="store_email" value="<?php echo esc_attr($settings['store_email'] ?? get_bloginfo('admin_email')); ?>" class="wp-store-input">
                        </div>
                        <div>
                            <label class="wp-store-label" for="store_phone">Telepon/WA</label>
                            <input name="store_phone" type="text" id="store_phone" value="<?php echo esc_attr($settings['store_phone'] ?? ''); ?>" class="wp-store-input">
                        </div>
                    </div>

                    <div class="wp-store-box-gray wp-store-mt-4">
                        <div class="wp-store-flex wp-store-justify-between wp-store-items-center">
                            <div>
                                <h4 class="wp-store-subtitle-small">Tarif Ongkir Custom</h4>
                                <p class="wp-store-helper wp-store-mb-2">Atur tarif ongkir manual berdasarkan lokasi.</p>
                            </div>
                            <button type="button" class="wp-store-btn wp-store-btn-small wp-store-btn-secondary" @click="openRateModal()">
                                <span class="dashicons dashicons-plus-alt2"></span> Tambah Tarif
                            </button>
                        </div>

                        <div class="wp-store-table-wrapper wp-store-mt-2" x-show="customShippingRates.length > 0">
                            <table class="wp-store-table">
                                <thead>
                                    <tr>
                                        <th>Tipe</th>
                                        <th>Lokasi</th>
                                        <th>Tarif (Rp)</th>
                                        <th>Label</th>
                                        <th style="width: 80px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(rate, index) in customShippingRates" :key="index">
                                        <tr>
                                            <td x-text="rate.type.toUpperCase()"></td>
                                            <td x-text="rate.name"></td>
                                            <td x-text="parseInt(rate.price).toLocaleString('id-ID')"></td>
                                            <td x-text="rate.label || '-'"></td>
                                            <td>
                                                <div class="wp-store-flex-gap">
                                                    <button type="button" class="wp-store-icon-btn" @click="openRateModal(index)" title="Edit">
                                                        <span class="dashicons dashicons-edit"></span>
                                                    </button>
                                                    <button type="button" class="wp-store-icon-btn wp-store-text-red" @click="removeRate(index)" title="Hapus">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div x-show="customShippingRates.length === 0" class="wp-store-helper wp-store-mt-2">Belum ada tarif custom.</div>
                    </div>
                </div>
            </div>

            <!-- Tab: Style -->
            <div x-show="activeTab === 'style'" class="wp-store-tab-content" x-cloak>
                <div class="wp-store-form-grid">
                    <p class="wp-store-helper">Atur warna tema frontend. Warna yang dipilih akan mengganti warna default pada tombol, tab, dan komponen callout.</p>
                    <div class="wp-store-grid-3">
                        <div>
                            <label class="wp-store-label" for="theme_primary">Primary</label>
                            <input name="theme_primary" id="theme_primary" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_primary'] ?? '#2563eb'); ?>">
                            <p class="wp-store-helper">Warna utama untuk tombol dan tab aktif.</p>
                        </div>
                        <div>
                            <label class="wp-store-label" for="theme_primary_hover">Primary Hover</label>
                            <input name="theme_primary_hover" id="theme_primary_hover" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_primary_hover'] ?? '#1d4ed8'); ?>">
                            <p class="wp-store-helper">Warna saat hover pada tombol utama.</p>
                        </div>
                        <div>
                            <label class="wp-store-label" for="theme_secondary_border">Secondary Border</label>
                            <input name="theme_secondary_border" id="theme_secondary_border" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_secondary_border'] ?? '#d1d5db'); ?>">
                            <p class="wp-store-helper">Warna border untuk tombol sekunder.</p>
                        </div>
                    </div>
                    <div class="wp-store-grid-3">
                        <div>
                            <label class="wp-store-label" for="theme_secondary_text">Secondary Text</label>
                            <input name="theme_secondary_text" id="theme_secondary_text" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_secondary_text'] ?? '#374151'); ?>">
                            <p class="wp-store-helper">Warna teks untuk tombol sekunder.</p>
                        </div>
                        <div>
                            <label class="wp-store-label" for="theme_callout_bg">Callout Background</label>
                            <input name="theme_callout_bg" id="theme_callout_bg" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_callout_bg'] ?? '#eff6ff'); ?>">
                            <p class="wp-store-helper">Warna latar belakang untuk callout.</p>
                        </div>
                        <div>
                            <label class="wp-store-label" for="theme_callout_border">Callout Border</label>
                            <input name="theme_callout_border" id="theme_callout_border" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_callout_border'] ?? '#bfdbfe'); ?>">
                            <p class="wp-store-helper">Warna border untuk callout.</p>
                        </div>
                    </div>
                    <div class="wp-store-grid-3">
                        <div>
                            <label class="wp-store-label" for="theme_callout_title">Callout Title</label>
                            <input name="theme_callout_title" id="theme_callout_title" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_callout_title'] ?? '#1d4ed8'); ?>">
                            <p class="wp-store-helper">Warna judul pada callout.</p>
                        </div>
                        <div>
                            <label class="wp-store-label" for="theme_danger_text">Danger Text</label>
                            <input name="theme_danger_text" id="theme_danger_text" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_danger_text'] ?? '#ef4444'); ?>">
                            <p class="wp-store-helper">Warna teks untuk tombol berbahaya (hapus, dll.).</p>
                        </div>
                        <div>
                            <label class="wp-store-label" for="theme_danger_border">Danger Border</label>
                            <input name="theme_danger_border" id="theme_danger_border" type="color" class="wp-store-input" value="<?php echo esc_attr($settings['theme_danger_border'] ?? '#fca5a5'); ?>">
                            <p class="wp-store-helper">Warna border untuk tombol berbahaya.</p>
                        </div>
                    </div>
                    <div class="wp-store-box-gray wp-store-mt-4">
                        <h3 class="wp-store-subtitle">Thumbnail Produk</h3>
                        <p class="wp-store-helper">Atur ukuran default untuk thumbnail produk di archive dan shortcode.</p>
                        <div class="wp-store-grid-2">
                            <div>
                                <label class="wp-store-label" for="product_thumbnail_width">Lebar (px)</label>
                                <input name="product_thumbnail_width" id="product_thumbnail_width" type="number" min="10" class="wp-store-input" value="<?php echo esc_attr($settings['product_thumbnail_width'] ?? 200); ?>">
                            </div>
                            <div>
                                <label class="wp-store-label" for="product_thumbnail_height">Tinggi (px)</label>
                                <input name="product_thumbnail_height" id="product_thumbnail_height" type="number" min="10" class="wp-store-input" value="<?php echo esc_attr($settings['product_thumbnail_height'] ?? 300); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="wp-store-box-gray wp-store-mt-4">
                        <h3 class="wp-store-subtitle">Layout</h3>
                        <div class="wp-store-grid-3">
                            <div>
                                <label class="wp-store-label" for="container_max_width">Container Max Width (px)</label>
                                <input name="container_max_width" id="container_max_width" type="number" min="600" step="10" class="wp-store-input" value="<?php echo esc_attr($settings['container_max_width'] ?? 1100); ?>">
                                <p class="wp-store-helper">Lebar maksimum kontainer utama di frontend.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Pembayaran -->
            <div x-show="activeTab === 'payment'" class="wp-store-tab-content" x-cloak>
                <div class="wp-store-form-grid">
                    <h3 class="wp-store-subtitle">Transfer Bank</h3>
                    <p class="wp-store-helper">Kelola daftar rekening bank untuk pembayaran manual.</p>

                    <!-- Pilih metode pembayaran (multi-select) -->
                    <div class="wp-store-grid-2">
                        <div class="wp-store-flex wp-store-gap-2">
                            <label class="wp-store-btn" :class="{'wp-store-btn-primary': paymentMethods.includes('bank_transfer')}">
                                <input type="checkbox" name="payment_methods[]" value="bank_transfer" x-model="paymentMethods" style="position:absolute;opacity:0;width:0;height:0;">
                                Transfer Bank
                            </label>
                            <label class="wp-store-btn" :class="{'wp-store-btn-primary': paymentMethods.includes('qris')}">
                                <input type="checkbox" name="payment_methods[]" value="qris" x-model="paymentMethods" style="position:absolute;opacity:0;width:0;height:0;">
                                QRIS
                            </label>
                            <!-- duitku -->
                            <?php do_action('wp_store_settings_payment_methods_end'); ?>
                        </div>
                    </div>
                    <div x-show="paymentMethods.includes('bank_transfer')">
                        <template x-for="(account, index) in bankAccounts" :key="index">
                            <div class="wp-store-box-gray wp-store-mt-4" style="position: relative; padding-top: 30px;">
                                <!-- Remove Button -->
                                <button type="button" @click="removeBankAccount(index)" class="button-link-delete" style="position: absolute; top: 10px; right: 10px; text-decoration: none;" title="Hapus Rekening" x-show="bankAccounts.length > 0">
                                    <span class="dashicons dashicons-trash"></span> Hapus
                                </button>

                                <div class="wp-store-grid-3">
                                    <div>
                                        <label class="wp-store-label">Nama Bank</label>
                                        <select x-model="account.bank_name" class="wp-store-input">
                                            <template x-for="bank in indonesianBanks" :key="bank">
                                                <option :value="bank" x-text="bank" :selected="account.bank_name === bank"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="wp-store-label">Nomor Rekening</label>
                                        <input type="text" x-model="account.bank_account" class="wp-store-input" placeholder="Contoh: 1234567890">
                                    </div>
                                    <div>
                                        <label class="wp-store-label">Atas Nama</label>
                                        <input type="text" x-model="account.bank_holder" class="wp-store-input" placeholder="Contoh: Nama Pemilik">
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div class="wp-store-mt-4">
                            <button type="button" @click="addBankAccount" class="wp-store-btn wp-store-btn-secondary">
                                <span class="dashicons dashicons-plus-alt2"></span> Tambah Rekening
                            </button>
                        </div>
                    </div>

                    <div x-show="paymentMethods.includes('qris')" class="wp-store-box-gray wp-store-mt-4">
                        <h3 class="wp-store-subtitle">QRIS</h3>
                        <p class="wp-store-helper">Unggah gambar QRIS untuk pembayaran cepat.</p>
                        <div class="wp-store-grid-2">
                            <div>
                                <label class="wp-store-label">Label</label>
                                <input type="text" name="qris_label" class="wp-store-input" value="<?php echo esc_attr($settings['qris_label'] ?? 'QRIS'); ?>">
                            </div>
                            <div>
                                <label class="wp-store-label">Gambar QRIS</label>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:140px; height:140px; border:1px solid #e5e7eb; border-radius:6px; display:flex; align-items:center; justify-content:center; background:#f9fafb; overflow:hidden;">
                                        <?php
                                        $qris_id = isset($settings['qris_image_id']) ? absint($settings['qris_image_id']) : 0;
                                        $qris_src = $qris_id ? wp_get_attachment_image_url($qris_id, 'medium') : '';
                                        ?>
                                        <img src="<?php echo esc_url($qris_src ?: WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>" alt="" style="max-width:100%; max-height:100%;">
                                    </div>
                                    <div style="display:flex; gap:8px;">
                                        <button type="button" class="wp-store-btn wp-store-btn-secondary" @click="selectQrisImage">Pilih Gambar</button>
                                        <button type="button" class="wp-store-btn wp-store-btn-secondary" @click="clearQrisImage">Hapus</button>
                                    </div>
                                </div>
                                <input type="hidden" name="qris_image_id" :value="settings.qris_image_id">
                            </div>
                        </div>
                    </div>
                    <?php do_action('wp_store_settings_payment_tab_end'); ?>
                </div>
            </div>

            <!-- Tab: Pengiriman -->
            <div x-show="activeTab === 'shipping'" class="wp-store-tab-content" x-cloak>
                <div class="wp-store-form-grid">
                    <h3 class="wp-store-subtitle">Pengaturan Pengiriman</h3>
                    <p class="wp-store-helper">Konfigurasi API VD Ongkir dan metode pengiriman.</p>

                    <div class="wp-store-box-gray wp-store-mt-4">
                        <h4 class="wp-store-subtitle-small">Opsi Pengiriman</h4>
                        <label class="wp-store-checkbox-label">
                            <input type="hidden" name="disable_shipping_for_digital" value="0">
                            <input type="checkbox" name="disable_shipping_for_digital" value="1" <?php echo !empty($settings['disable_shipping_for_digital']) ? 'checked' : ''; ?>>
                            Matikan ongkir untuk produk digital (sembunyikan alamat & ongkir jika semua item digital)
                        </label>
                    </div>

                    <div class="wp-store-box-gray wp-store-mt-4">
                        <h4 class="wp-store-subtitle-small">API VD Ongkir</h4>
                        <div class="wp-store-mt-2 wp-store-flex wp-store-items-center">
                            <input name="rajaongkir_api_key" type="text" id="rajaongkir_api_key" x-ref="apiKeyInput" value="<?php echo esc_attr($settings['rajaongkir_api_key'] ?? ''); ?>" class="wp-store-input" placeholder="Masukkan API Key Starter/Basic/Pro Anda">
                            <div class="wp-store-mt-2" style="margin-left:8px; width:120px;">
                                <button type="button" class="wp-store-btn wp-store-btn-primary" @click="saveApiKey" :disabled="isSavingApi">Simpan API</button>
                            </div>
                        </div>
                    </div>

                    <div class="wp-store-box-gray wp-store-mt-4" x-show="isApiValid">
                        <h4 class="wp-store-subtitle-small">Asal Pengiriman</h4>
                        <p class="wp-store-helper">Lokasi toko Anda untuk perhitungan ongkos kirim.</p>

                        <div class="wp-store-mt-2">
                            <!-- Province -->
                            <div class="wp-store-mb-2">
                                <label class="wp-store-label" for="shipping_origin_province">Provinsi</label>
                                <select name="shipping_origin_province" id="shipping_origin_province" x-model="settings.shipping_origin_province" @change="onProvinceChange()" class="wp-store-input" style="width: 100%; max-width: 400px;">
                                    <option value="">-- Pilih Provinsi --</option>
                                    <template x-for="prov in provinces" :key="prov.province_id">
                                        <option :value="prov.province_id" x-text="prov.province" :selected="prov.province_id == settings.shipping_origin_province"></option>
                                    </template>
                                </select>
                                <div x-show="isLoadingProvinces" class="wp-store-helper">Memuat provinsi...</div>
                            </div>

                            <!-- City -->
                            <div class="wp-store-mb-2">
                                <label class="wp-store-label" for="shipping_origin_city">Kota/Kabupaten</label>
                                <select name="shipping_origin_city" id="shipping_origin_city" x-model="settings.shipping_origin_city" @change="onCityChange()" class="wp-store-input" style="width: 100%; max-width: 400px;" :disabled="!settings.shipping_origin_province">
                                    <option value="">-- Pilih Kota/Kabupaten --</option>
                                    <template x-for="city in cities" :key="city.city_id">
                                        <option :value="city.city_id" x-text="`${city.type} ${city.city_name}`" :selected="city.city_id == settings.shipping_origin_city"></option>
                                    </template>
                                </select>
                                <div x-show="isLoadingCities" class="wp-store-helper">Memuat kota...</div>
                            </div>

                            <!-- Subdistrict -->
                            <div class="wp-store-mb-2">
                                <label class="wp-store-label" for="shipping_origin_subdistrict">Kecamatan</label>
                                <select name="shipping_origin_subdistrict" id="shipping_origin_subdistrict" x-model="settings.shipping_origin_subdistrict" class="wp-store-input" style="width: 100%; max-width: 400px;" :disabled="!settings.shipping_origin_city">
                                    <option value="">-- Pilih Kecamatan --</option>
                                    <template x-for="sub in subdistricts" :key="sub.subdistrict_id">
                                        <option :value="sub.subdistrict_id" x-text="sub.subdistrict_name" :selected="sub.subdistrict_id == settings.shipping_origin_subdistrict"></option>
                                    </template>
                                </select>
                                <div x-show="isLoadingSubdistricts" class="wp-store-helper">Memuat kecamatan...</div>
                            </div>

                            <p class="wp-store-helper">Lokasi toko Anda untuk perhitungan ongkos kirim.</p>
                        </div>
                    </div>

                    <div class="wp-store-box-gray wp-store-mt-4" x-show="isApiValid">
                        <h4 class="wp-store-subtitle-small">Kurir Aktif</h4>
                        <p class="wp-store-helper">Pilih kurir yang ingin Anda gunakan.</p>

                        <div class="wp-store-grid-3 wp-store-mt-2">
                            <?php
                            $couriers = [
                                'jne' => 'JNE',
                                'sicepat' => 'SiCepat',
                                'ide' => 'IDExpress',
                                'sap' => 'SAP Express',
                                'ninja' => 'Ninja',
                                'jnt' => 'J&T Express',
                                'tiki' => 'TIKI',
                                'wahana' => 'Wahana Express',
                                'pos' => 'POS Indonesia',
                                'sentral' => 'Sentral Cargo',
                                'lion' => 'Lion Parcel',
                                'rex' => 'Royal Express Asia'
                            ];
                            $active_couriers = $settings['shipping_couriers'] ?? ['jne', 'sicepat', 'ide'];
                            foreach ($couriers as $code => $label) :
                            ?>
                                <label class="wp-store-checkbox-label">
                                    <input type="checkbox" name="shipping_couriers[]" value="<?php echo $code; ?>" <?php echo in_array($code, $active_couriers) ? 'checked' : ''; ?>>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Tab: Halaman -->
            <div x-show="activeTab === 'pages'" class="wp-store-tab-content" x-cloak>
                <div class="wp-store-form-grid">
                    <p class="wp-store-helper">Tentukan halaman untuk fitur-fitur toko.</p>

                    <div>
                        <label class="wp-store-label">Halaman Toko (Shop)</label>
                        <div class="wp-store-helper">Menggunakan archive produk: <?php echo esc_html(site_url('/produk/')); ?></div>
                    </div>

                    <div>
                        <label class="wp-store-label" for="page_catalog">Halaman Katalog</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'page_catalog',
                            'selected' => $settings['page_catalog'] ?? 0,
                            'show_option_none' => '-- Pilih Halaman --',
                            'class' => 'wp-store-input'
                        ]);
                        ?>
                    </div>

                    <div>
                        <label class="wp-store-label" for="page_shipping_check">Halaman Cek Ongkir</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'page_shipping_check',
                            'selected' => $settings['page_shipping_check'] ?? 0,
                            'show_option_none' => '-- Pilih Halaman --',
                            'class' => 'wp-store-input'
                        ]);
                        ?>
                    </div>

                    <div>
                        <label class="wp-store-label" for="page_profile">Halaman Profil</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'page_profile',
                            'selected' => $settings['page_profile'] ?? 0,
                            'show_option_none' => '-- Pilih Halaman --',
                            'class' => 'wp-store-input'
                        ]);
                        ?>
                    </div>

                    <div>
                        <label class="wp-store-label" for="page_cart">Halaman Keranjang (Cart)</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'page_cart',
                            'selected' => $settings['page_cart'] ?? 0,
                            'show_option_none' => '-- Pilih Halaman --',
                            'class' => 'wp-store-input'
                        ]);
                        ?>
                    </div>

                    <div>
                        <label class="wp-store-label" for="page_checkout">Halaman Checkout</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'page_checkout',
                            'selected' => $settings['page_checkout'] ?? 0,
                            'show_option_none' => '-- Pilih Halaman --',
                            'class' => 'wp-store-input'
                        ]);
                        ?>
                    </div>

                    <div>
                        <label class="wp-store-label" for="page_thanks">Halaman Terima Kasih (Thanks)</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'page_thanks',
                            'selected' => $settings['page_thanks'] ?? 0,
                            'show_option_none' => '-- Pilih Halaman --',
                            'class' => 'wp-store-input'
                        ]);
                        ?>
                    </div>

                    <div>
                        <label class="wp-store-label" for="page_tracking">Halaman Tracking Order</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'page_tracking',
                            'selected' => $settings['page_tracking'] ?? 0,
                            'show_option_none' => '-- Pilih Halaman --',
                            'class' => 'wp-store-input'
                        ]);
                        ?>
                    </div>

                    <div class="wp-store-box-gray">
                        <h3 class="wp-store-subtitle">Generate Halaman Otomatis</h3>
                        <p class="wp-store-helper">Belum punya halaman? Klik tombol di bawah ini untuk membuat halaman Profil, Keranjang, Checkout, Terima Kasih, dan Tracking Order secara otomatis dengan shortcode yang sesuai.</p>

                        <div class="wp-store-mt-4">
                            <button type="button" @click="openGeneratePagesModal" class="wp-store-btn wp-store-btn-secondary" :disabled="isGenerating">
                                <span class="dashicons dashicons-plus-alt" x-show="!isGenerating"></span>
                                <span class="dashicons dashicons-update" x-show="isGenerating" style="animation: spin 2s linear infinite;"></span>
                                <span x-text="isGenerating ? 'Sedang Membuat...' : 'Buat Halaman Otomatis'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Sistem -->
            <div x-show="activeTab === 'system'" class="wp-store-tab-content" x-cloak>
                <div class="wp-store-form-grid">
                    <div>
                        <label class="wp-store-label" for="currency_symbol">Simbol Mata Uang</label>
                        <select name="currency_symbol" id="currency_symbol" class="wp-store-input" style="width: 150px;">
                            <option value="Rp" <?php selected($settings['currency_symbol'] ?? 'Rp', 'Rp'); ?>>Rp (Rupiah)</option>
                            <option value="USD" <?php selected($settings['currency_symbol'] ?? 'Rp', 'USD'); ?>>USD (Dollar)</option>
                        </select>
                    </div>
                    <div>
                        <label class="wp-store-label" for="product_editor_mode">Mode Editor Produk</label>
                        <select name="product_editor_mode" id="product_editor_mode" class="wp-store-input" style="width: 260px;">
                            <option value="classic" <?php selected($settings['product_editor_mode'] ?? 'classic', 'classic'); ?>>Classic Editor</option>
                            <option value="gutenberg" <?php selected($settings['product_editor_mode'] ?? 'classic', 'gutenberg'); ?>>Gutenberg</option>
                            <!-- <option value="fse"  -->
                            <?php
                            //selected($settings['product_editor_mode'] ?? 'classic', 'fse'); 
                            // 
                            ?>
                            <!-- >
                            Full Site Editor</option> -->
                        </select>
                        <p class="wp-store-helper">Mengatur apakah halaman edit produk memakai Classic Editor atau Gutenberg/FSE.</p>
                    </div>
                    <div>
                        <label class="wp-store-label">Diskon Hanya untuk Member</label>
                        <div>
                            <label class="wp-store-checkbox-label">
                                <input type="hidden" name="members_only_discount" value="0">
                                <input type="checkbox" name="members_only_discount" value="1" <?php echo !empty($settings['members_only_discount']) ? 'checked' : ''; ?>>
                                Hanya tampilkan harga promo untuk pengguna yang login
                            </label>
                        </div>
                        <p class="wp-store-helper">Jika aktif, pengunjung yang belum login tidak akan melihat harga promo maupun badge diskon.</p>
                    </div>
                    <div class="wp-store-grid-2">
                        <div>
                            <label class="wp-store-label" for="recaptcha_site_key">reCAPTCHA Site Key</label>
                            <input name="recaptcha_site_key" type="text" id="recaptcha_site_key" value="<?php echo esc_attr($settings['recaptcha_site_key'] ?? ''); ?>" class="wp-store-input" placeholder="Site Key">
                        </div>
                        <div>
                            <label class="wp-store-label" for="recaptcha_secret_key">reCAPTCHA Secret Key</label>
                            <input name="recaptcha_secret_key" type="text" id="recaptcha_secret_key" value="<?php echo esc_attr($settings['recaptcha_secret_key'] ?? ''); ?>" class="wp-store-input" placeholder="Secret Key">
                        </div>
                    </div>
                    <div class="wp-store-box-gray wp-store-mt-4 wp-store-email-template">
                        <div class="wp-store-grid-2 wp-store-mb-4">
                            <div>
                                <label class="wp-store-label" for="store_email_from">Email Pengirim (From)</label>
                                <?php $default_from = (function () {
                                    $u = wp_get_current_user();
                                    $site_url = get_bloginfo('url');
                                    $domain = parse_url($site_url, PHP_URL_HOST);
                                    $username = ($u && isset($u->user_login) && $u->user_login) ? $u->user_login : 'noreply';
                                    return $username . '@' . $domain;
                                })(); ?>
                                <input name="store_email_from" type="email" id="store_email_from" value="<?php echo esc_attr($settings['store_email_from'] ?? $default_from); ?>" class="wp-store-input" placeholder="username@namadomain.com">
                                <p class="wp-store-helper">Email ini digunakan sebagai pengirim pada notifikasi. Default: admin email.</p>
                            </div>
                            <div>
                                <label class="wp-store-label" for="store_email_admin">Email Admin (Tujuan Notifikasi)</label>
                                <input name="store_email_admin" type="email" id="store_email_admin" value="<?php echo esc_attr($settings['store_email_admin'] ?? get_bloginfo('admin_email')); ?>" class="wp-store-input" placeholder="admin@namadomain.com">
                                <p class="wp-store-helper">Email admin penerima notifikasi. Default: admin email.</p>
                            </div>
                        </div>
                        <h3 class="wp-store-subtitle">Template Email</h3>
                        <p class="wp-store-helper wp-store-mb-4">Gunakan placeholder: {{store_name}}, {{order_number}}, {{status_label}}, {{tracking_url}}, {{total}}.</p>
                        <div class="wp-store-grid-2">
                            <div class="wp-store-mb-4">
                                <label class="wp-store-label" for="email_template_user_new_order">User: Pesanan Baru</label>
                                <?php
                                $content_user_new = $settings['email_template_user_new_order'] ?? '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                                    . '<p>Halo,</p>'
                                    . '<p>Terima kasih. Pesanan #{{order_number}} telah kami terima.</p>'
                                    . '<p>Anda dapat memantau status pesanan melalui tautan berikut:</p>'
                                    . '<p><a href="{{tracking_url}}" target="_blank" rel="noopener">Lihat Status Pesanan</a></p>'
                                    . '<p>Salam,<br>{{store_name}}</p>'
                                    . '</div>';
                                wp_editor($content_user_new, 'email_template_user_new_order', [
                                    'textarea_name' => 'email_template_user_new_order',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'quicktags' => false,
                                    'tinymce' => ['menubar' => false, 'toolbar1' => '', 'toolbar2' => '']
                                ]);
                                ?>
                            </div>
                            <div class="wp-store-mb-4">
                                <label class="wp-store-label" for="email_template_admin_new_order">Admin: Pesanan Baru</label>
                                <?php
                                $content_admin_new = $settings['email_template_admin_new_order'] ?? '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                                    . '<p>Order baru #{{order_number}}.</p>'
                                    . '<p>Total: {{total}}</p>'
                                    . '<p>Tracking: <a href="{{tracking_url}}" target="_blank" rel="noopener">{{tracking_url}}</a></p>'
                                    . '</div>';
                                wp_editor($content_admin_new, 'email_template_admin_new_order', [
                                    'textarea_name' => 'email_template_admin_new_order',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'quicktags' => false,
                                    'tinymce' => ['menubar' => false, 'toolbar1' => '', 'toolbar2' => '']
                                ]);
                                ?>
                            </div>
                        </div>
                        <div class="wp-store-grid-2">
                            <div class="wp-store-mb-4">
                                <label class="wp-store-label" for="email_template_user_status">User: Status Order</label>
                                <?php
                                $content_user_status = $settings['email_template_user_status'] ?? '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                                    . '<p>Halo,</p>'
                                    . '<p>Status pesanan #{{order_number}} Anda kini: <strong>{{status_label}}</strong>.</p>'
                                    . '<p>Anda dapat melihat detail dan riwayat pengiriman melalui tautan berikut:</p>'
                                    . '<p><a href="{{tracking_url}}" target="_blank" rel="noopener">Lihat Status Pesanan</a></p>'
                                    . '<p>Salam,<br>{{store_name}}</p>'
                                    . '</div>';
                                wp_editor($content_user_status, 'email_template_user_status', [
                                    'textarea_name' => 'email_template_user_status',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'quicktags' => false,
                                    'tinymce' => ['menubar' => false, 'toolbar1' => '', 'toolbar2' => '']
                                ]);
                                ?>
                            </div>
                            <div class="wp-store-mb-4">
                                <label class="wp-store-label" for="email_template_admin_status">Admin: Status Order</label>
                                <?php
                                $content_admin_status = $settings['email_template_admin_status'] ?? '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111;">'
                                    . '<p>Status order #{{order_number}}: <strong>{{status_label}}</strong>.</p>'
                                    . '<p>Tracking: <a href="{{tracking_url}}" target="_blank" rel="noopener">{{tracking_url}}</a></p>'
                                    . '</div>';
                                wp_editor($content_admin_status, 'email_template_admin_status', [
                                    'textarea_name' => 'email_template_admin_status',
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'quicktags' => false,
                                    'tinymce' => ['menubar' => false, 'toolbar1' => '', 'toolbar2' => '']
                                ]);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Tool -->
            <div x-show="activeTab === 'tools'" class="wp-store-tab-content" x-cloak>
                <div class="wp-store-grid-3">
                    <div class="wp-store-box-gray">
                        <h3 class="wp-store-subtitle">Cache RajaOngkir</h3>
                        <p class="wp-store-helper">Lihat ukuran cache dan bersihkan untuk menghemat API.</p>
                        <div class="wp-store-mt-4" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <div class="wp-store-helper">
                                <span x-show="isLoadingCacheStats">Menghitung ukuran cache...</span>
                                <span x-show="!isLoadingCacheStats">
                                    <strong x-text="cacheStats.entries"></strong> entri,
                                    <strong x-text="cacheStats.approx_mb"></strong> MB
                                </span>
                            </div>
                            <button type="button" @click="clearCache" class="wp-store-btn wp-store-btn-secondary" :disabled="isClearing">
                                <span class="dashicons dashicons-trash" x-show="!isClearing"></span>
                                <span class="dashicons dashicons-update" x-show="isClearing" style="animation: spin 2s linear infinite;"></span>
                                <span x-text="isClearing ? 'Membersihkan Cache...' : 'Clear Cache'"></span>
                            </button>
                        </div>
                    </div>
                    <div class="wp-store-box-gray">
                        <h3 class="wp-store-subtitle">Seeder Produk</h3>
                        <p class="wp-store-helper">Buat produk contoh untuk pengujian katalog.</p>
                        <div class="wp-store-mt-4" style="display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="button" @click="openSeederModal" class="wp-store-btn wp-store-btn-secondary" :disabled="isSeeding">
                                <span class="dashicons dashicons-admin-tools" x-show="!isSeeding"></span>
                                <span class="dashicons dashicons-update" x-show="isSeeding" style="animation: spin 2s linear infinite;"></span>
                                <span x-text="isSeeding ? 'Menjalankan Seeder...' : 'Run Seeder'"></span>
                            </button>
                        </div>
                    </div>
                    <div class="wp-store-box-gray">
                        <h3 class="wp-store-subtitle">Produk</h3>
                        <p class="wp-store-helper">Kelola daftar produk toko Anda.</p>
                        <div class="wp-store-mt-4" style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="<?php echo admin_url('edit.php?post_type=store_product'); ?>" class="wp-store-btn wp-store-btn-secondary">
                                <span class="dashicons dashicons-cart"></span>
                                Produk
                            </a>
                        </div>
                    </div>
                    <?php do_action('wp_store_settings_tools_tab'); ?>
                </div>
            </div>

            <div class="wp-store-form-actions">
                <button type="submit" class="wp-store-btn wp-store-btn-primary" :disabled="isSaving">
                    <span class="dashicons dashicons-saved" x-show="!isSaving"></span>
                    <span class="dashicons dashicons-update" x-show="isSaving" style="animation: spin 2s linear infinite;"></span>
                    <span x-text="isSaving ? 'Menyimpan...' : 'Simpan Pengaturan'"></span>
                </button>
            </div>
        </form>
    </div>

    <!-- Notification Toast -->
    <div x-show="notification.show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="wp-store-toast"
        :class="notification.type"
        x-cloak>
        <span class="dashicons" :class="notification.type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'" class="wp-store-icon-20"></span>
        <span x-text="notification.message"></span>
    </div>
    <div x-show="isSeederModalOpen" x-cloak class="wp-store-modal-overlay">
        <div class="wp-store-modal" @keydown.escape.window="closeSeederModal">
            <div class="wp-store-modal-header">Konfirmasi Seeder</div>
            <div class="wp-store-modal-body">
                Jalankan seeder untuk membuat produk contoh?
            </div>
            <div class="wp-store-modal-actions">
                <button type="button" class="wp-store-btn wp-store-btn-secondary" @click="closeSeederModal" :disabled="isSeeding">Batal</button>
                <button type="button" class="wp-store-btn wp-store-btn-primary" @click="runSeeder" :disabled="isSeeding">
                    <span class="dashicons dashicons-update" x-show="isSeeding" style="animation: spin 2s linear infinite;"></span>
                    <span x-text="isSeeding ? 'Memproses...' : 'Jalankan'"></span>
                </button>
            </div>
        </div>
    </div>
    <div x-show="isGeneratePagesModalOpen" x-cloak class="wp-store-modal-overlay">
        <div class="wp-store-modal" @keydown.escape.window="closeGeneratePagesModal">
            <div class="wp-store-modal-header">Konfirmasi Pembuatan Halaman</div>
            <div class="wp-store-modal-body">
                Buat halaman Toko, Profil, Keranjang, Checkout, Terima Kasih, dan Tracking Order secara otomatis?
            </div>
            <div class="wp-store-modal-actions">
                <button type="button" class="wp-store-btn wp-store-btn-secondary" @click="closeGeneratePagesModal" :disabled="isGenerating">Batal</button>
                <button type="button" class="wp-store-btn wp-store-btn-primary" @click="generatePages" :disabled="isGenerating">
                    <span class="dashicons dashicons-update" x-show="isGenerating" style="animation: spin 2s linear infinite;"></span>
                    <span x-text="isGenerating ? 'Memproses...' : 'Buat'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Rate Modal -->
    <div x-show="isRateModalOpen" x-cloak class="wp-store-modal-overlay">
        <div class="wp-store-modal" @keydown.escape.window="closeRateModal" style="max-width: 500px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="wp-store-modal-header">
                <span x-text="rateForm.index >= 0 ? 'Edit Tarif' : 'Tambah Tarif'"></span>
            </div>
            <div class="wp-store-modal-body" style="overflow-y: auto;">
                <div class="wp-store-form-grid">
                    <div>
                        <label class="wp-store-label">Provinsi</label>
                        <select class="wp-store-input" x-model="rateForm.provId" @change="loadCitiesForRate(rateForm.provId); rateForm.cityId=''; rateForm.subId='';">
                            <option value="">-- Pilih Provinsi --</option>
                            <template x-for="p in provinces" :key="p.province_id">
                                <option :value="p.province_id" x-text="p.province"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="rateForm.provId">
                        <label class="wp-store-label">Kota/Kabupaten</label>
                        <select class="wp-store-input" x-model="rateForm.cityId" @change="loadSubdistrictsForRate(rateForm.cityId); rateForm.subId='';">
                            <option value="">-- Seluruh Kota di Provinsi ini --</option>
                            <template x-for="c in rateCities" :key="c.city_id">
                                <option :value="c.city_id" x-text="c.type + ' ' + c.city_name"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="rateForm.cityId">
                        <label class="wp-store-label">Kecamatan</label>
                        <select class="wp-store-input" x-model="rateForm.subId">
                            <option value="">-- Seluruh Kecamatan di Kota ini --</option>
                            <template x-for="s in rateSubdistricts" :key="s.subdistrict_id">
                                <option :value="s.subdistrict_id" x-text="s.subdistrict_name"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="wp-store-label">Tarif (Rp)</label>
                        <input type="number" class="wp-store-input" x-model="rateForm.price" min="0">
                    </div>

                    <div>
                        <label class="wp-store-label">Label (Opsional)</label>
                        <input type="text" class="wp-store-input" x-model="rateForm.label" placeholder="Contoh: Ongkir Khusus">
                    </div>
                </div>
            </div>
            <div class="wp-store-modal-actions">
                <button type="button" class="wp-store-btn wp-store-btn-secondary" @click="closeRateModal">Batal</button>
                <button type="button" class="wp-store-btn wp-store-btn-primary" @click="submitRate">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
    const wpStoreConfig = {
        nonce: '<?php echo wp_create_nonce("wp_rest"); ?>',
        apiUrl: '<?php echo esc_url_raw(rest_url("wp-store/v1")); ?>',
        debug: true
    };

    if (wpStoreConfig.debug) {
        console.log('WP Store Config loaded:', wpStoreConfig);
    }

    document.addEventListener('alpine:init', () => {
        Alpine.data('storeSettingsManager', () => ({
            activeTab: '<?php echo esc_js($active_tab); ?>',
            isSaving: false,
            isSavingApi: false,
            isGenerating: false,
            isSeeding: false,
            isClearing: false,
            isLoadingCacheStats: false,
            isSeederModalOpen: false,
            isGeneratePagesModalOpen: false,
            notification: {
                show: false,
                message: '',
                type: 'success'
            },
            cacheStats: {
                bytes: 0,
                entries: 0,
                approx_mb: 0
            },
            paymentMethods: ['bank_transfer', 'qris'],
            bankAccounts: [],
            customShippingRates: [],
            isRateModalOpen: false,
            rateForm: {
                index: -1, // -1 for new
                type: 'province',
                id: '',
                name: '',
                price: 0,
                label: '',
                provId: '',
                cityId: '',
                subId: ''
            },
            rateLocations: [], // Dropdown options for the modal
            rateCities: [],
            rateSubdistricts: [],
            isLoadingRateLocations: false,

            indonesianBanks: [
                'Bank Mandiri', 'BRI', 'BCA', 'BNI', 'BTN', 'BSI', 'CIMB Niaga',
                'OCBC NISP', 'Bank Permata', 'Bank Danamon', 'Panin Bank',
                'Maybank Indonesia', 'Bank Mega', 'Bank Sinarmas', 'Bank BTN Syariah',
                'Bank Mega Syariah', 'Bank Commonwealth', 'Bank UOB Indonesia',
                'Bank DBS Indonesia', 'Bank Woori Saudara', 'Bank Hana Indonesia',
                'Bank Resona Perdania', 'Bank J Trust Indonesia', 'Bank Ina Perdana',
                'Bank Artha Graha', 'Bank Index Selindo', 'Bank Ganesha',
                'Bank Maspion', 'Bank Bumi Arta', 'Bank Victoria', 'Lainnya'
            ],

            provinces: [],
            cities: [],
            subdistricts: [],
            isLoadingProvinces: false,
            isLoadingCities: false,
            isLoadingSubdistricts: false,
            isApiValid: false,
            settings: {
                shipping_origin_province: '<?php echo esc_js($settings['shipping_origin_province'] ?? ''); ?>',
                shipping_origin_city: '<?php echo esc_js($settings['shipping_origin_city'] ?? ''); ?>',
                shipping_origin_subdistrict: '<?php echo esc_js($settings['shipping_origin_subdistrict'] ?? ''); ?>',
                qris_image_id: '<?php echo esc_js($settings['qris_image_id'] ?? ''); ?>'
            },
            updatePaymentMethods(method) {
                if (this.paymentMethods.includes(method)) {
                    this.paymentMethods = this.paymentMethods.filter(m => m !== method);
                } else {
                    this.paymentMethods.push(method);
                }
                console.log('Updated payment methods:', this.paymentMethods);
            },
            init() {
                // Initialize history state if needed
                this.updateUrl(this.activeTab);

                this.loadSettings().then(() => {
                    if (this.isApiValid) {
                        this.loadProvinces().then(() => {
                            if (this.settings.shipping_origin_province) {
                                this.loadCities(this.settings.shipping_origin_province).then(() => {
                                    if (this.settings.shipping_origin_city) {
                                        this.loadSubdistricts(this.settings.shipping_origin_city);
                                    }
                                });
                            }
                        });
                    }
                });

                this.loadCacheStats();

            },
            async loadSettings() {
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/settings`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (response.ok && result && result.success) {
                        const s = result.settings || {};
                        // Payment methods
                        if (Array.isArray(s.payment_methods) && s.payment_methods.length) {
                            this.paymentMethods = s.payment_methods;
                        }
                        // Shipping origin & QRIS
                        this.settings.shipping_origin_province = s.shipping_origin_province || '';
                        this.settings.shipping_origin_city = s.shipping_origin_city || '';
                        this.settings.shipping_origin_subdistrict = s.shipping_origin_subdistrict || '';
                        this.settings.qris_image_id = s.qris_image_id || '';
                        // Custom shipping rates
                        this.customShippingRates = Array.isArray(s.custom_shipping_rates) ? s.custom_shipping_rates : [];
                        // Bank accounts (with legacy fallback if present)
                        if (Array.isArray(s.store_bank_accounts) && s.store_bank_accounts.length > 0) {
                            this.bankAccounts = s.store_bank_accounts;
                        } else if (s.bank_name || s.bank_account || s.bank_holder) {
                            this.bankAccounts = [{
                                bank_name: s.bank_name || '',
                                bank_account: s.bank_account || '',
                                bank_holder: s.bank_holder || ''
                            }];
                        } else if (this.bankAccounts.length === 0) {
                            this.addBankAccount();
                        }
                        this.isApiValid = !!(s.rajaongkir_api_key);
                    }
                } catch (e) {
                    console.error('Gagal memuat settings:', e);
                }
            },

            addBankAccount() {
                this.bankAccounts.push({
                    bank_name: 'BCA',
                    bank_account: '',
                    bank_holder: ''
                });
            },

            removeBankAccount(index) {
                this.bankAccounts.splice(index, 1);
            },

            openRateModal(index = -1) {
                this.rateForm.index = index;
                this.rateForm.provId = '';
                this.rateForm.cityId = '';
                this.rateForm.subId = '';
                this.rateCities = [];
                this.rateSubdistricts = [];

                if (index >= 0 && this.customShippingRates[index]) {
                    const r = this.customShippingRates[index];
                    this.rateForm.type = r.type;
                    this.rateForm.id = r.id;
                    this.rateForm.name = r.name;
                    this.rateForm.price = r.price;
                    this.rateForm.label = r.label;

                    // Try to pre-fill if possible
                    if (r.type === 'province') {
                        this.rateForm.provId = r.id;
                    }
                } else {
                    this.rateForm.type = 'province';
                    this.rateForm.id = '';
                    this.rateForm.name = '';
                    this.rateForm.price = 0;
                    this.rateForm.label = '';
                }
                this.isRateModalOpen = true;
            },

            async loadCitiesForRate(provId) {
                if (!provId) {
                    this.rateCities = [];
                    return;
                }
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/rajaongkir/cities?province=${provId}`, {
                        headers: {
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (result.success) this.rateCities = result.data;
                } catch (e) {
                    console.error(e);
                }
            },

            async loadSubdistrictsForRate(cityId) {
                if (!cityId) {
                    this.rateSubdistricts = [];
                    return;
                }
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/rajaongkir/subdistricts?city=${cityId}`, {
                        headers: {
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (result.success) this.rateSubdistricts = result.data;
                } catch (e) {
                    console.error(e);
                }
            },

            closeRateModal() {
                this.isRateModalOpen = false;
            },

            async loadRateLocations(type) {
                this.isLoadingRateLocations = true;
                this.rateLocations = [];
                let endpoint = '';

                if (type === 'province') {
                    endpoint = 'provinces';
                } else if (type === 'city') {
                    endpoint = 'cities'; // Ideally filtered by province, but for custom rate we might want all cities? 
                    // Wait, listing ALL cities (500+) in a dropdown is bad. 
                    // Usually user selects Province first then City.
                    // But here "Type" defines the scope.
                    // If Type is City, we need to select a City. 
                    // If Type is Subdistrict, we need to select a Subdistrict.
                    // To make it usable, we should probably still require Province selection for City, and City for Subdistrict.
                    // But the requirement says "Based on Province, City OR Subdistrict".
                    // So if I select "City", I'm setting a rate for that specific City regardless of province (though City implies Province).
                    // To keep UI simple for now:
                    // If Type = Province: List Provinces.
                    // If Type = City: List All Cities (might be slow but ok for admin).
                    // If Type = Subdistrict: This is too many (7000+). 
                    // We MUST have a hierarchy selector even for "Subdistrict" rule.
                }

                // Actually, let's just use the existing loadProvinces/loadCities methods but adapted.
                // However, the rule is simple: Type + ID.
                // If I select "Subdistrict", I need to find that subdistrict.
                // Let's rely on the existing API.

                try {
                    let url = `${wpStoreConfig.apiUrl}/rajaongkir/${endpoint}`;
                    // For now let's just support Province and City for direct listing.
                    // Subdistricts are too many. 
                    // Maybe we need a cascading select in the modal: Province -> City -> Subdistrict.
                    // And the "Rule Type" is determined by what level you stop at?
                    // No, the user wants "Based on Province OR City OR Subdistrict".
                    // So I can select a Province and say "Ongkir to West Java = 20k".
                    // Or select a City "Bandung = 10k".

                    // Revised Plan for Modal:
                    // Always show Province dropdown.
                    // Show City dropdown if Province selected.
                    // Show Subdistrict dropdown if City selected.
                    // And a Radio button to select "Apply Rule Level": Province / City / Subdistrict.

                    // But to simplify implementation given existing API:
                    // Let's fetch all Provinces first.
                    // When Province selected, fetch Cities.
                    // When City selected, fetch Subdistricts.

                    // The "Type" in `rateForm` will be the selected level.
                    // But to make it work, I'll just use the `onRateProvinceChange` etc.
                } catch (e) {
                    console.error(e);
                }
                this.isLoadingRateLocations = false;
            },

            // We will use a more interactive approach in the modal HTML, so this loadRateLocations might be redundant if we reuse the logic.
            // Let's implement specific loaders for the modal.

            submitRate() {
                let type = 'province';
                let id = this.rateForm.provId;
                let name = '';

                if (this.rateForm.subId) {
                    type = 'subdistrict';
                    id = this.rateForm.subId;
                    let s = this.rateSubdistricts.find(x => x.subdistrict_id == id);
                    let c = this.rateCities.find(x => x.city_id == this.rateForm.cityId);
                    let p = this.provinces.find(x => x.province_id == this.rateForm.provId);
                    name = (s ? s.subdistrict_name : id) + ', ' + (c ? c.city_name : '') + ', ' + (p ? p.province : '');
                } else if (this.rateForm.cityId) {
                    type = 'city';
                    id = this.rateForm.cityId;
                    let c = this.rateCities.find(x => x.city_id == id);
                    let p = this.provinces.find(x => x.province_id == this.rateForm.provId);
                    name = (c ? c.type + ' ' + c.city_name : id) + ', ' + (p ? p.province : '');
                } else if (this.rateForm.provId) {
                    type = 'province';
                    id = this.rateForm.provId;
                    let p = this.provinces.find(x => x.province_id == id);
                    name = p ? p.province : id;
                }

                if (!id) {
                    alert('Pilih minimal provinsi');
                    return;
                }

                this.rateForm.type = type;
                this.rateForm.id = id;
                this.rateForm.name = name;
                this.saveRate();
            },

            async saveRate() {
                if (!this.rateForm.id) {
                    alert('Pilih lokasi terlebih dahulu');
                    return;
                }
                const rate = {
                    type: this.rateForm.type,
                    id: this.rateForm.id,
                    name: this.rateForm.name,
                    price: parseFloat(this.rateForm.price),
                    label: this.rateForm.label
                };

                if (this.rateForm.index >= 0) {
                    this.customShippingRates[this.rateForm.index] = rate;
                } else {
                    this.customShippingRates.push(rate);
                }
                this.closeRateModal();
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/settings/custom-shipping-rates`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpStoreConfig.nonce
                        },
                        body: JSON.stringify({
                            custom_shipping_rates: this.customShippingRates
                        })
                    });
                    const result = await response.json();
                    if (response.ok && result && result.success) {
                        this.showNotification('Tarif custom disimpan!', 'success');
                    } else {
                        this.showNotification((result && result.message) ? result.message : 'Gagal menyimpan tarif custom.', 'error');
                    }
                } catch (e) {
                    this.showNotification('Terjadi kesalahan jaringan.', 'error');
                }
            },

            removeRate(index) {
                if (confirm('Hapus tarif ini?')) {
                    this.customShippingRates.splice(index, 1);
                }
            },

            async loadProvinces() {
                this.isLoadingProvinces = true;
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/rajaongkir/provinces`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (response.ok && result.success) {
                        this.provinces = result.data;
                        this.isApiValid = true;
                    } else {
                        this.isApiValid = false;
                    }
                } catch (error) {
                    console.error('Error loading provinces:', error);
                    this.isApiValid = false;
                } finally {
                    this.isLoadingProvinces = false;
                }
            },

            async loadCities(provinceId) {
                if (!provinceId) return;
                this.isLoadingCities = true;
                this.cities = [];
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/rajaongkir/cities?province=${provinceId}`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (response.ok && result.success) {
                        this.cities = result.data;
                    } else {
                        console.error('Failed to load cities:', result.message);
                    }
                } catch (error) {
                    console.error('Error loading cities:', error);
                } finally {
                    this.isLoadingCities = false;
                }
            },

            async loadSubdistricts(cityId) {
                if (!cityId) return;
                // Don't try to load subdistricts for starter account if we know it will fail
                // But let's try anyway as user might have upgraded key but not setting
                this.isLoadingSubdistricts = true;
                this.subdistricts = [];
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/rajaongkir/subdistricts?city=${cityId}`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (response.ok && result.success) {
                        this.subdistricts = result.data;
                    }
                } catch (error) {
                    console.error('Error loading subdistricts:', error);
                } finally {
                    this.isLoadingSubdistricts = false;
                }
            },
            async saveApiKey() {
                const key = this.$refs.apiKeyInput ? this.$refs.apiKeyInput.value.trim() : '';
                this.isSavingApi = true;
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/settings`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpStoreConfig.nonce
                        },
                        body: JSON.stringify({
                            rajaongkir_api_key: key
                        })
                    });
                    const result = await response.json();
                    if (response.ok && result && result.success) {
                        this.showNotification('API Key disimpan!', 'success');
                        await this.loadProvinces();
                    } else {
                        this.showNotification(result.message || 'Gagal menyimpan API Key.', 'error');
                        this.isApiValid = false;
                    }
                } catch (e) {
                    this.showNotification('Terjadi kesalahan jaringan.', 'error');
                    this.isApiValid = false;
                } finally {
                    this.isSavingApi = false;
                }
            },

            onProvinceChange() {
                this.settings.shipping_origin_city = '';
                this.settings.shipping_origin_subdistrict = '';
                this.cities = [];
                this.subdistricts = [];
                if (this.settings.shipping_origin_province) {
                    this.loadCities(this.settings.shipping_origin_province);
                }
            },

            onCityChange() {
                this.settings.shipping_origin_subdistrict = '';
                this.subdistricts = [];
                if (this.settings.shipping_origin_city) {
                    this.loadSubdistricts(this.settings.shipping_origin_city);
                }
            },

            switchTab(tab) {
                this.activeTab = tab;
                this.updateUrl(tab);
            },

            updateUrl(tab) {
                const newUrl = window.location.pathname + '?page=wp-store-settings&tab=' + tab;
                window.history.pushState({
                    tab: tab
                }, '', newUrl);
            },
            selectQrisImage() {
                const frame = wp.media({
                    title: 'Pilih Gambar QRIS',
                    button: {
                        text: 'Gunakan Gambar Ini'
                    },
                    multiple: false
                });
                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    this.settings.qris_image_id = attachment.id;
                    const img = document.querySelector('.wp-store-tab-content [alt=""]');
                    if (img) img.src = attachment.url;
                });
                frame.open();
            },
            clearQrisImage() {
                this.settings.qris_image_id = '';
                const img = document.querySelector('.wp-store-tab-content [alt=""]');
                if (img) img.src = '<?php echo esc_js(WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>';
            },

            async saveSettings() {
                this.isSaving = true;
                const formData = new FormData(this.$refs.form);
                const data = {};
                formData.forEach((value, key) => {
                    // Skip nonce and referer fields if they exist
                    if (key === '_wpnonce' || key === '_wp_http_referer') return;

                    // Handle array inputs like shipping_couriers[]
                    if (key.endsWith('[]')) {
                        const realKey = key.slice(0, -2);
                        if (!data[realKey]) {
                            data[realKey] = [];
                        }
                        data[realKey].push(value);
                    } else {
                        data[key] = value;
                    }
                });
                // Handle payment methods from Alpine state
                data.payment_methods = Array.isArray(this.paymentMethods) ? JSON.parse(JSON.stringify(this.paymentMethods)) : [];

                // Add bank accounts manually to data
                data.store_bank_accounts = JSON.parse(JSON.stringify(this.bankAccounts));
                // Add custom shipping rates manually to data
                data.custom_shipping_rates = JSON.parse(JSON.stringify(this.customShippingRates));

                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/settings`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpStoreConfig.nonce
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (response.ok) {
                        this.showNotification('Pengaturan berhasil disimpan!', 'success');
                    } else {
                        this.showNotification(result.message || 'Gagal menyimpan pengaturan.', 'error');
                    }
                } catch (error) {
                    this.showNotification('Terjadi kesalahan jaringan.', 'error');
                    console.error(error);
                } finally {
                    this.isSaving = false;
                }
            },

            async generatePages() {
                this.isGenerating = true;

                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/settings/generate-pages`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });

                    const result = await response.json();

                    if (response.ok) {
                        this.showNotification(result.message, 'success');
                        // Optionally reload to show new page selections, but for now just notify
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        this.showNotification(result.message || 'Gagal membuat halaman.', 'error');
                    }
                } catch (error) {
                    this.showNotification('Terjadi kesalahan jaringan.', 'error');
                    console.error(error);
                } finally {
                    this.isGenerating = false;
                    this.closeGeneratePagesModal();
                }
            },

            openSeederModal() {
                this.isSeederModalOpen = true;
            },
            closeSeederModal() {
                this.isSeederModalOpen = false;
            },
            openGeneratePagesModal() {
                this.isGeneratePagesModalOpen = true;
            },
            closeGeneratePagesModal() {
                this.isGeneratePagesModalOpen = false;
            },
            async runSeeder() {
                this.isSeeding = true;
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/tools/seed-products`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpStoreConfig.nonce
                        },
                        body: JSON.stringify({
                            count: 12
                        })
                    });
                    const result = await response.json();
                    if (response.ok) {
                        this.showNotification(result.message || 'Seeder berhasil dijalankan.', 'success');
                    } else {
                        this.showNotification(result.message || 'Gagal menjalankan seeder.', 'error');
                    }
                } catch (error) {
                    this.showNotification('Terjadi kesalahan jaringan.', 'error');
                    console.error(error);
                } finally {
                    this.isSeeding = false;
                    this.closeSeederModal();
                }
            },
            async clearCache() {
                this.isClearing = true;
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/tools/clear-cache`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (response.ok) {
                        this.showNotification(result.message || 'Cache berhasil dibersihkan.', 'success');
                        this.loadCacheStats();
                    } else {
                        this.showNotification(result.message || 'Gagal membersihkan cache.', 'error');
                    }
                } catch (error) {
                    this.showNotification('Terjadi kesalahan jaringan.', 'error');
                    console.error(error);
                } finally {
                    this.isClearing = false;
                }
            },
            async loadCacheStats() {
                this.isLoadingCacheStats = true;
                try {
                    const response = await fetch(`${wpStoreConfig.apiUrl}/tools/cache-stats`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-WP-Nonce': wpStoreConfig.nonce
                        }
                    });
                    const result = await response.json();
                    if (response.ok && result.success) {
                        this.cacheStats = result;
                    }
                } catch (error) {
                    console.error(error);
                } finally {
                    this.isLoadingCacheStats = false;
                }
            },

            showNotification(message, type = 'success') {
                this.notification.message = message;
                this.notification.type = type;
                this.notification.show = true;
                setTimeout(() => {
                    this.notification.show = false;
                }, 3000);
            }
        }));
    });
</script>