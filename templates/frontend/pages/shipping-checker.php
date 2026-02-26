<?php
$settings = get_option('wp_store_settings', []);
$currency = isset($currency) ? (string) $currency : ($settings['currency_symbol'] ?? 'Rp');
$origin_subdistrict = isset($origin_subdistrict) ? (string) $origin_subdistrict : (isset($settings['shipping_origin_subdistrict']) ? (string) $settings['shipping_origin_subdistrict'] : '');
$active_couriers = isset($active_couriers) && is_array($active_couriers) ? $active_couriers : ($settings['shipping_couriers'] ?? ['jne', 'sicepat', 'ide']);
$nonce = isset($nonce) ? (string) $nonce : wp_create_nonce('wp_rest');
?>
<script>
  window.wpStoreSettings = window.wpStoreSettings || {
    restUrl: '<?php echo esc_url_raw(rest_url('wp-store/v1/')); ?>',
    nonce: '<?php echo esc_js($nonce); ?>'
  };
  window.wpStoreShippingChecker = function() {
    return {
      provinces: [],
      cities: [],
      subdistricts: [],
      selectedProvince: '',
      selectedCity: '',
      selectedSubdistrict: '',
      couriers: <?php echo json_encode(array_values($active_couriers)); ?>,
      selectedCouriers: <?php echo json_encode(array_values($active_couriers)); ?>,
      originSubdistrict: '<?php echo esc_js($origin_subdistrict); ?>',
      weightKg: 1,
      services: [],
      loading: false,
      errorMessage: '',
      isLoadingProvinces: false,
      isLoadingCities: false,
      isLoadingSubdistricts: false,
      captchaVerified: false,
      currency: '<?php echo esc_js($currency); ?>',
      formatPrice(n) {
        const v = typeof n === 'number' ? n : parseFloat(n || 0);
        return new Intl.NumberFormat('id-ID', {
          style: 'currency',
          currency: 'IDR',
          minimumFractionDigits: 0
        }).format(v);
      },
      captchaRequired: <?php echo is_user_logged_in() ? 'false' : 'true'; ?>,
      recomputeAllow() {
        this.captchaVerified = this.isCaptchaReady();
      },
      isCaptchaReady() {
        if (!this.captchaRequired) return true;
        const root = document.getElementById('shipping-captcha');
        if (!root) return false;
        const val = root.querySelector('input[name="captcha_value"]');
        const idf = root.querySelector('input[name="captcha_id"]');
        const v = String(val && val.value ? val.value : '').trim();
        const i = String(idf && idf.value ? idf.value : '').trim();
        const vf = root.querySelector('input[name="captcha_verified"]');
        const vv = String(vf && vf.value ? vf.value : '').trim();
        return v !== '' && i !== '' && vv === '1';
      },
      async loadProvinces() {
        this.isLoadingProvinces = true;
        try {
          const res = await fetch(wpStoreSettings.restUrl + 'rajaongkir/provinces', {
            credentials: 'same-origin',
            headers: {
              'X-WP-Nonce': wpStoreSettings.nonce
            }
          });
          const data = await res.json();
          this.provinces = data.data || [];
        } catch (e) {
          this.provinces = [];
        } finally {
          this.isLoadingProvinces = false;
        }
      },
      async loadCities() {
        if (!this.selectedProvince) {
          this.cities = [];
          return;
        }
        this.isLoadingCities = true;
        try {
          const res = await fetch(wpStoreSettings.restUrl + 'rajaongkir/cities?province=' + encodeURIComponent(this.selectedProvince), {
            credentials: 'same-origin',
            headers: {
              'X-WP-Nonce': wpStoreSettings.nonce
            }
          });
          const data = await res.json();
          this.cities = data.data || [];
        } catch (e) {
          this.cities = [];
        } finally {
          this.isLoadingCities = false;
        }
      },
      async loadSubdistricts() {
        if (!this.selectedCity) {
          this.subdistricts = [];
          return;
        }
        this.isLoadingSubdistricts = true;
        try {
          const res = await fetch(wpStoreSettings.restUrl + 'rajaongkir/subdistricts?city=' + encodeURIComponent(this.selectedCity), {
            credentials: 'same-origin',
            headers: {
              'X-WP-Nonce': wpStoreSettings.nonce
            }
          });
          const data = await res.json();
          this.subdistricts = data.data || [];
        } catch (e) {
          this.subdistricts = [];
        } finally {
          this.isLoadingSubdistricts = false;
        }
      },
      async checkShipping() {
        if (this.captchaRequired && !this.isCaptchaReady()) {
          return;
        }
        if (!this.originSubdistrict || !this.selectedSubdistrict || !Array.isArray(this.selectedCouriers) || this.selectedCouriers.length === 0) {
          return;
        }
        this.loading = true;
        this.services = [];
        this.errorMessage = '';
        const grams = Math.max(1, Math.round((parseFloat(this.weightKg || 0) || 0) * 1000));
        try {
          const res = await fetch(wpStoreSettings.restUrl + 'rajaongkir/calculate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': wpStoreSettings.nonce
            },
            body: JSON.stringify({
              destination_subdistrict: this.selectedSubdistrict,
              courier: this.selectedCouriers.join(':'),
              manual_weight_grams: grams
            })
          });
          const data = await res.json();
          if (res.ok && data && data.success && data.data && Array.isArray(data.data.data)) {
            this.services = data.data.data.map(s => ({
              courier: s.code || '',
              service: s.service || '',
              description: s.description || '',
              cost: s.cost || 0,
              etd: s.etd || ''
            }));
            if (this.services.length === 0) {
              this.errorMessage = 'Tidak ada layanan kurir tersedia untuk rute ini.';
            }
          } else {
            this.errorMessage = data.message || 'Gagal menghitung ongkos kirim.';
          }
        } catch (e) {
          this.errorMessage = 'Terjadi kesalahan saat menghubungkan ke server.';
        }
        this.loading = false;
      },
      init() {
        this.loadProvinces();
        this.recomputeAllow();
      }
    }
  }
</script>
<div class="wps-container wps-mx-auto wps-my-8" x-data="wpStoreShippingChecker()">
  <div class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-4 wps-pt-4">Cek Ongkir</div>
  <div class="wps-card wps-p-4 wps-mb-4">
    <div class="wps-grid wps-grid-cols-2 wps-gap-4">
      <div>
        <label class="wps-label">Provinsi</label>
        <select class="wps-input" x-model="selectedProvince" @change="loadCities()">
          <option value="">-- Pilih Provinsi --</option>
          <template x-for="p in provinces" :key="p.province_id">
            <option :value="p.province_id" x-text="p.province"></option>
          </template>
        </select>
        <div class="wps-text-xs wps-text-gray-500" x-show="isLoadingProvinces">Memuat provinsi...</div>
      </div>
      <div>
        <label class="wps-label">Kota/Kabupaten</label>
        <select class="wps-input" x-model="selectedCity" @change="loadSubdistricts()">
          <option value="">-- Pilih Kota/Kabupaten --</option>
          <template x-for="c in cities" :key="c.city_id">
            <option :value="c.city_id" x-text="c.city_name"></option>
          </template>
        </select>
        <div class="wps-text-xs wps-text-gray-500" x-show="isLoadingCities">Memuat kota...</div>
      </div>
      <div>
        <label class="wps-label">Kecamatan</label>
        <select class="wps-input" x-model="selectedSubdistrict">
          <option value="">-- Pilih Kecamatan --</option>
          <template x-for="s in subdistricts" :key="s.subdistrict_id">
            <option :value="s.subdistrict_id" x-text="s.subdistrict_name"></option>
          </template>
        </select>
        <div class="wps-text-xs wps-text-gray-500" x-show="isLoadingSubdistricts">Memuat kecamatan...</div>
      </div>
      <div>
        <label class="wps-label">Berat (Kg)</label>
        <input type="number" min="0.1" step="0.1" class="wps-input" x-model="weightKg" placeholder="1.0">
      </div>
    </div>
    <div class="wps-divider wps-my-4"></div>
    <div>
      <div class="wps-mt-2" x-show="<?php echo is_user_logged_in() ? 'false' : 'true'; ?>" id="shipping-captcha" @input="recomputeAllow()" @change="recomputeAllow()">
        <?php echo \WpStore\Frontend\Template::render('components/captcha'); ?>
      </div>
      <div class="wps-mt-4">
        <button type="button" class="wps-btn wps-btn-primary" @click="checkShipping()" :disabled="loading || !selectedSubdistrict || couriers.length === 0 || (captchaRequired && !captchaVerified)">
          <span x-show="!loading">Cek Ongkir</span>
          <span x-show="loading">Menghitung...</span>
        </button>
      </div>
      <div class="wps-text-xs wps-text-gray-500 wps-mt-2" x-show="!originSubdistrict">Asal pengiriman belum diatur di pengaturan.</div>
      <div class="wps-text-xs wps-text-gray-500 wps-mt-1" x-show="couriers.length === 0">Tidak ada kurir aktif di pengaturan.</div>
    </div>
  </div>
  <div class="wps-card wps-p-4">
    <div class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-2">Hasil</div>
    <template x-if="errorMessage">
      <div class="wps-text-sm wps-text-red-600 wps-mb-2" x-text="errorMessage"></div>
    </template>
    <template x-if="!errorMessage && services.length === 0">
      <div class="wps-text-sm wps-text-gray-500">Belum ada hasil. Isi alamat dan tekan Cek Ongkir.</div>
    </template>
    <div class="wps-grid wps-grid-cols-2 wps-gap-3" x-show="!errorMessage && services.length > 0">
      <template x-for="s in services" :key="s.courier + ':' + s.service">
        <div class="wps-box-gray wps-p-3 wps-rounded">
          <div class="wps-text-sm wps-text-gray-900 wps-font-medium" x-text="(s.courier || '').toUpperCase() + ' ' + (s.service || '')"></div>
          <div class="wps-text-xs wps-text-gray-600" x-text="s.description"></div>
          <div class="wps-text-sm wps-text-primary-700 wps-font-semibold wps-mt-1" x-text="formatPrice(s.cost)"></div>
          <div class="wps-text-xs wps-text-gray-500" x-text="s.etd ? ('ETD: ' + s.etd) : ''"></div>
        </div>
      </template>
    </div>
  </div>
</div>