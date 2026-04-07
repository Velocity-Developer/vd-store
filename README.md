# WP Store

WP Store adalah plugin WordPress untuk membuat toko sederhana dengan fitur Produk, Keranjang, Wishlist, Checkout, dan integrasi ongkir RajaOngkir (via Komerce API).

## Untuk Pengguna

- Instalasi
  - Unggah folder plugin ke `wp-content/plugins/wp-store` atau install melalui ZIP.
  - Aktifkan plugin di halaman Plugins.
  - Menu “WP Store” akan muncul di admin untuk mengelola Produk dan Pesanan.

- Pengaturan Toko
  - Buka “WP Store → Pengaturan”.
  - Isi informasi toko, metode pembayaran, dan halaman sistem (Cart, Checkout, Thanks, Tracking).
  - Pengiriman:
    - Masukkan API Key RajaOngkir (via Komerce).
    - Pilih asal pengiriman (Provinsi → Kota → Kecamatan).
    - Pilih kurir yang diaktifkan.
  - Cache data wilayah dikendalikan otomatis, tersedia tombol “Clear Cache” di tab Tools.

- Produk
  - Tambah produk baru di “Produk”.
  - Lengkapi harga, stok, berat (Kg). Berat akan dikonversi otomatis ke gram untuk kalkulasi ongkir.

- Keranjang & Checkout
  - Pengguna dapat menambah ke keranjang dari halaman produk/katalog.
  - Di Checkout, pilih tujuan pengiriman dan kurir; biaya dihitung otomatis dari berat total keranjang.
  - Setelah pesanan dibuat, status pesanan dapat dilihat di admin.

- Wishlist
  - Tombol “Tambah ke Wishlist” tersedia di kartu produk/halaman produk.
  - Jika belum login, akan muncul modal login secara instan tanpa transisi.
  - Wishlist untuk guest disimpan dengan cookie, untuk user tersimpan di database.

- Tracking & Bukti Pembayaran
  - Halaman Tracking dapat diatur di Pengaturan Halaman.
  - Bukti pembayaran (transfer) dapat diunggah via halaman publik yang disediakan, akan muncul di detail pesanan admin.

## Untuk Developer

- REST API Utama
  - Base: `/wp-json/wp-store/v1/`
  - Produk: `/products` (lihat controller terkait)
  - Keranjang: `/cart` (GET/POST; operasi write membutuhkan nonce `X-WP-Nonce`)
  - Wishlist: `/wishlist` (GET/POST/DELETE; write membutuhkan nonce)
  - Checkout: `/checkout` (POST; membutuhkan nonce)
  - RajaOngkir:
    - `/rajaongkir/provinces` GET
    - `/rajaongkir/cities?province={id}` GET
    - `/rajaongkir/subdistricts?city={id}` GET
    - `/rajaongkir/calculate` POST
  - Captcha: `/captcha/new` GET
  - Tools: `/tools/seed-products` POST (admin), `/tools/clear-cache` POST (admin), `/tools/cache-stats` GET (admin)

- Security
  - Semua endpoint write publik menggunakan nonce REST: header `X-WP-Nonce` dengan `wp_create_nonce('wp_rest')`.
  - Admin-only (Tools) dibatasi dengan `current_user_can('manage_options')`.

- Database & Identitas
  - Tabel `store_carts`: menyimpan cart, snapshot shipping_data, dan total_price.
  - Tabel `store_wishlists`: menyimpan wishlist per user/guest.
  - Guest diidentifikasi via cookie `wp_store_cart_key`.

- Komerce API (Wrapper RajaOngkir)
  - Base: `https://rajaongkir.komerce.id/api/v1`
  - Endpoint yang digunakan: destination/province, destination/city/{province}, destination/district/{city}, calculate/domestic-cost.
  - Header: `key: <API_KEY>`, body `application/x-www-form-urlencoded`.

- Shortcodes
  - `wp_store_shop`
    - Menampilkan katalog produk.
    - Opsi: `per_page` (default 12, max 50).
    - Contoh: `[wp_store_shop per_page="12"]`
  - `wp_store_single`
    - Menampilkan komponen single produk pada halaman produk CPT.
    - Dipakai pada template single store_product.
  - `wp_store_related`
    - Menampilkan produk terkait berdasarkan kategori produk.
    - Opsi: `id` (ID produk sumber, opsional; fallback ke post saat ini), `per_page` (default 4, max 12).
    - Contoh: `[wp_store_related id="123" per_page="4"]`
  - `wp_store_gallery`
    - Menampilkan galeri produk inti dengan carousel thumbnail.
    - Opsi: `id` (ID produk, opsional; fallback ke post saat ini).
    - Contoh: `[wp_store_gallery id="123"]`
  - `wp_store_recently_viewed`
    - Menampilkan daftar produk yang baru dilihat dari cookie.
    - Opsi:
      - `limit` (default 4, max 24)
      - `exclude_current` = `true|false`
      - `title`
    - Contoh: `[wp_store_recently_viewed limit="4" exclude_current="true"]`
  - `wp_store_products_carousel`
    - Carousel produk berbasis Flickity.
    - Opsi:
      - `label`: teks label di atas carousel (default kosong)
      - `per_page`: jumlah produk diambil (default 10, max 20)
      - `per_row`: jumlah item per slide (default 1)
      - `img_width`: lebar dasar rasio gambar (default 200)
      - `img_height`: tinggi dasar rasio gambar (default 300)
      - `crop`: `true|false` object-fit cover/contain (default true)
      - `autoplay`: interval ms otomatis, `0` untuk non-aktif (default 0)
      - `pause_on_hover`: `true|false` (default true)
      - `wrap_around`: `true|false` (default true)
      - `page_dots`: `true|false` (default false)
      - `prev_next_buttons`: `true|false` (default true)
      - `lazy_load`: jumlah slide sekitar yang dilazyload (default 0)
      - `cell_align`: `left|center|right` (default center)
      - `draggable`: `true|false` (default true)
      - `contain`: `true|false` (default true)
    - Contoh:  
      `[wp_store_products_carousel per_page="8" per_row="2" autoplay="3000" page_dots="true"]`
    - Badge otomatis di dalam item:
      - Digital badge jika tipe produk digital
      - Badge diskon persen jika harga promo aktif
  - `wp_store_add_to_cart`
    - Tombol/komponen tambah ke keranjang untuk sebuah produk.
    - Opsi:
      - `id`: ID produk
      - `label`: teks default tombol (default `+`)
      - `text`: teks alternatif tombol, jika diisi override `label`
      - `size`: `sm` untuk ukuran kecil, kosong untuk normal
      - `class`: kelas tambahan; akan di-append ke kelas default (bukan replace)
    - Contoh: `[wp_store_add_to_cart id="123"]`
  - `wp_store_detail`
    - Tombol/tautan untuk membuka halaman detail produk.
    - Opsi:
      - `id`: ID produk
      - `text`: label tombol (default `Detail`)
      - `size`: `sm` untuk ukuran kecil, kosong untuk normal
      - `class`: kelas tambahan; akan di-append ke kelas default (bukan replace)
    - Contoh: `[wp_store_detail id="123" size="sm" class="my-btn"]`
  - `wp_store_cart`
    - Widget keranjang ringkas untuk ditaruh di halaman/area bebas.
  - `wp_store_checkout` / `store_checkout`
    - Halaman Checkout. Pastikan halaman ini dipetakan di Pengaturan → Halaman.
  - `wp_store_thanks` / `store_thanks`
    - Halaman “Terima Kasih” setelah checkout.
    - Mendukung parameter URL `order` untuk menampilkan ringkasan pesanan.
  - `wp_store_tracking` / `store_tracking`
    - Halaman Tracking Pesanan.
    - Parameter URL: `order=<ID>` untuk menampilkan status pesanan dan tracking AWB jika tersedia.
  - `wp_store_wishlist`
    - Widget wishlist untuk menampilkan daftar favorit user/guest.
  - `wp_store_add_to_wishlist`
    - Tombol tambah ke wishlist pada kartu/halaman produk.
    - Opsi:
      - `id`: ID produk (opsional, fallback ke post saat ini)
      - `size`: `sm` untuk ukuran kecil
      - `label_add`: teks saat belum ditambahkan (default `Wishlist`)
      - `label_remove`: teks saat sudah ada di wishlist (default `Hapus`)
      - `icon_only`: `1|0` hanya ikon tanpa teks (default `0`)
  - `wp_store_link_profile`
    - Tautan ke halaman profil pengguna (menampilkan avatar).
    - URL halaman profil dikonfigurasi di Pengaturan → Halaman.
  - `wp_store_thumbnail`
    - Menampilkan thumbnail produk dengan dukungan hover.
    - Opsi:
      - `id`: ID produk (opsional, fallback ke post saat ini)
      - `width`: lebar dasar rasio (default 300)
      - `height`: tinggi dasar rasio (default 300)
      - `crop`: `true|false` object-fit cover/contain (default true)
      - `upscale`: `true|false` (disediakan untuk kompatibilitas)
      - `alt`: teks alt gambar (default judul produk)
      - `hover`: `change|none` (default `change` untuk gambar hover jika tersedia gallery)
      - `label`: `true|false` (legacy, saat ini hanya memengaruhi badge Digital dan Diskon; badge label produk umum sudah tidak dipakai)
    - Contoh:  
      `[wp_store_thumbnail id="123" width="240" height="320" hover="change" label="true"]`

- Metrik Produk Canonical
  - `VD Store` sekarang menjadi pemilik meta agregat produk umum:
    - `_store_sold_count`
    - `_store_review_count`
    - `_store_rating_average`
  - galeri produk inti dan recently viewed inti sekarang juga punya entry point shortcode di core:
    - `wp_store_gallery`
    - `wp_store_recently_viewed`
  - Metrik ini dipakai untuk kebutuhan toko online biasa maupun saat `VD Marketplace` aktif.
  - Fitur label/badge produk umum `_store_label` sudah tidak lagi dipakai sebagai fitur aktif.
  - `wp_store_price`
    - Menampilkan harga produk (harga promo vs normal).
    - Opsi:
      - `id`: ID produk
      - `countdown`: `true|false` menampilkan waktu berakhir promo jika tersedia
    - Contoh:  
      `[wp_store_price id="123" countdown="true"]`

- Berat & Cache
  - Berat total dihitung dari meta `_store_weight_kg` per produk dan dikonversi ke gram.
  - Cache wilayah dan ongkir menggunakan `transient` dengan masa simpan ± 24 jam.

- Hook & Filter
  - Checkout:
    - `wp_store_before_create_order($data, WP_REST_Request $request)`
    - `wp_store_after_create_order($order_id, $data, $lines, $order_total)`
  - Shipping:
    - `wp_store_before_calculate_shipping($params, WP_REST_Request $request)`
    - `wp_store_shipping_weight($grams, $params)`
    - `wp_store_shipping_cache_key($key, $params)`
    - `wp_store_shipping_services($services, $params)`
    - `wp_store_shipping_payload($payload, $params)`
    - `wp_store_after_calculate_shipping($payload, $params)`
    - `wp_store_shipping_calculated($payload, $params)`
  - Bukti Pembayaran:
    - `wp_store_upload_allowed_statuses($allowed_statuses, $order_id)`
    - `wp_store_upload_proof_allowed_mimes($mimes, $order_id)`
    - `wp_store_before_upload_proof($file, $order_id)`
    - `wp_store_payment_proof_uploaded($order_id, $attachment_id, $url)`
    - `wp_store_after_upload_proof($order_id, $attachment_id, $url)`
  - Tools:
    - `wp_store_tools_products_seeded($created_ids)`
    - `wp_store_after_seed_products($created_ids)`
  - Captcha:
    - `wp_store_captcha_code_length($length)`
    - `wp_store_captcha_code($code)`
    - `wp_store_captcha_svg($svg, $code)`
    - `wp_store_captcha_created($id, $code)`

- Contoh Penggunaan Filter

```php
// Tambah catatan khusus saat checkout
add_filter('wp_store_before_create_order', function ($data, $request) {
    $data['notes'] = trim(($data['notes'] ?? '') . "\nSumber: Landing Promo A");
    return $data;
}, 10, 2);

// Tambah layanan kurir kustom setelah kalkulasi
add_filter('wp_store_after_calculate_shipping', function ($payload, $params) {
    $payload['services'][] = [
        'courier' => 'CUSTOM',
        'service' => 'SAME_DAY',
        'description' => 'Kurir internal same-day',
        'cost' => 25000,
        'etd' => '0-1'
    ];
    return $payload;
}, 10, 2);

// Validasi file bukti transfer sebelum upload
add_filter('wp_store_before_upload_proof', function ($file, $order_id) {
    if (empty($file['type']) && preg_match('/\.(jpe?g|png|webp|pdf)$/i', $file['name'] ?? '')) {
        $file['type'] = 'image/jpeg';
    }
    return $file;
}, 10, 2);
```
