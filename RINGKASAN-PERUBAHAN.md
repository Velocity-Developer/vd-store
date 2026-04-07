# Rangkuman Perubahan VD Store

Dokumen ini merangkum perubahan penting yang sudah dilakukan di `VD Store` setelah pemisahan peran antara:

- `VD Store` = core / fondasi toko
- `VD Marketplace` = addon marketplace

Tujuan dokumen ini adalah supaya tim bisa cepat memahami:

- apa yang sekarang menjadi tanggung jawab `VD Store`
- apa yang sudah dipindah dari addon ke core
- kenapa struktur baru ini dipakai
- bagaimana arah pengembangan berikutnya

---

## 1. Peran VD Store Sekarang

Dulu beberapa fitur inti toko masih bercampur dengan logic marketplace.  
Sekarang `VD Store` diposisikan sebagai **engine utama toko online**.

Artinya `VD Store` sekarang menjadi pemilik utama untuk:

- produk dasar
- cart dasar
- wishlist dasar
- checkout dasar
- order dasar
- payment dasar
- halaman inti toko
- profil customer inti
- tracking order inti

Sedangkan `VD Marketplace` hanya menambah fitur yang memang khusus marketplace, seperti:

- toko seller
- shipping per seller
- status fulfillment per seller
- pesan
- notifikasi
- fitur seller dashboard

---

## 2. Domain Produk Sudah Dipusatkan ke VD Store

Logika produk sekarang tidak lagi tersebar di banyak file acak.

Yang sudah dipusatkan ke `VD Store`:

- schema field produk
- baca/tulis meta produk canonical
- mapping data produk
- query/filter/sort produk dasar
- galeri produk inti
- related products dasar
- recently viewed dasar

File penting:

- `src/Domain/Product/ProductSchema.php`
- `src/Domain/Product/ProductMeta.php`
- `src/Domain/Product/ProductData.php`
- `src/Domain/Product/ProductQuery.php`
- `src/Domain/Product/RelatedProducts.php`
- `src/Domain/Product/RecentlyViewed.php`
- `templates/frontend/components/product-gallery.php`
- `templates/frontend/pages/recently-viewed.php`

### Kenapa ini penting

Supaya:

- aturan produk ada di satu tempat
- admin form dan frontend seller memakai kontrak field yang sama
- addon tidak perlu bikin ulang logic produk
- lebih mudah menambah field baru ke depan

### Tambahan penting

Metrik produk umum sekarang juga sudah menjadi milik `VD Store`.

Yang sekarang dianggap canonical di core:

- `_store_sold_count`
- `_store_review_count`
- `_store_rating_average`

Artinya:

- toko online biasa bisa memakai data ini tanpa addon marketplace
- `VD Marketplace` tinggal membaca metrik produk dari core
- metrik produk tidak lagi dianggap data khusus marketplace

---

## 3. Metabox Produk Sekarang Milik VD Store

Admin `store_product` sekarang resmi milik `VD Store`.

Perubahan penting:

- metabox produk tidak lagi bergantung pada `VD Marketplace`
- metabox produk tidak lagi bergantung pada CMB2 untuk bisa tampil
- `VD Store` sekarang punya fallback native metabox sendiri

File penting:

- `src/Admin/ProductMetaBoxes.php`
- `src/Domain/Product/ProductFields.php`
- `assets/admin/js/store-admin.js`

### Dampaknya

- kalau `VD Marketplace` nonaktif, metabox produk tetap muncul
- field galeri, file digital, checkbox, dan field lain tetap jalan dari core
- field marketplace-only bisa disembunyikan saat addon tidak aktif

### Tambah field ke depan

Target strukturnya sekarang:

- cukup tambah definisi field di `ProductSchema`
- kalau tipe field-nya sudah didukung, tidak perlu bikin ulang form dari nol
- kalau tipe field baru belum ada, baru `ProductFields` ikut ditambah

### Catatan tambahan

Fitur **badge / label produk umum** sekarang sudah tidak dipakai lagi sebagai fitur aktif.

Jadi:

- field `Label Produk` sudah dicabut dari schema utama
- kartu produk dan single product tidak lagi menampilkan badge label umum
- yang masih relevan tetap dipakai, misalnya badge digital dan badge diskon

---

## 4. Cart dan Wishlist Dasar Sudah Pindah ke VD Store

`VD Store` sekarang menjadi pemilik storage dan service dasar untuk:

- cart
- wishlist

File penting:

- `src/Domain/Cart/CartService.php`
- `src/Domain/Wishlist/WishlistService.php`
- `src/Api/CartController.php`
- `src/Api/WishlistController.php`

### Cara kerjanya sekarang

- raw cart tetap disimpan sederhana
- addon marketplace membaca cart dasar dari core
- grouping seller dilakukan di atas data cart dasar, bukan membuat cart kedua

Ini membuat core tetap ringan, tapi masih bisa dipakai untuk marketplace.

---

## 5. Checkout dan Order Dasar Sudah Jadi Milik VD Store

Order utama sekarang dibentuk dari core.

File penting:

- `src/Domain/Order/OrderService.php`
- `src/Api/CheckoutController.php`

Yang sekarang ditulis di core:

- item order dasar
- total order dasar
- status order dasar
- payment method dasar
- payment metadata dasar

### Dampaknya

`VD Marketplace` tidak lagi perlu membuat “order kedua”.  
Addon sekarang hanya menambah layer marketplace di atas order core, seperti:

- shipping group per seller
- status per seller
- fulfillment seller

### Tambahan penting untuk kupon

Kupon bersama sekarang juga diarahkan ke kontrak meta milik `VD Store`.

Meta kupon canonical yang dipakai:

- `_store_coupon_code`
- `_store_coupon_scope`
- `_store_coupon_type`
- `_store_coupon_value`
- `_store_coupon_min_purchase`
- `_store_coupon_usage_limit`
- `_store_coupon_usage_count`
- `_store_coupon_starts_at`
- `_store_coupon_expires_at`

Meta order canonical saat kupon dipakai:

- `_store_order_coupon_code`
- `_store_order_discount_type`
- `_store_order_discount_value`
- `_store_order_discount_amount`
- `_store_order_coupon_id`
- `_store_order_coupon_scope`
- `_store_order_coupon_product_discount`
- `_store_order_coupon_shipping_discount`

Artinya:

- toko online biasa dan marketplace sekarang punya dasar meta kupon yang sama
- data inti kupon tidak perlu dibuat ulang dengan nama berbeda
- kalau nanti mau tambah field kupon baru, titik utamanya ada di core

---

## 6. Payment Dasar Sudah Dipindah ke VD Store

Sebelumnya payment masih banyak hidup di addon.  
Sekarang payment dasar diposisikan sebagai milik core.

File penting:

- `src/Domain/Payment/PaymentMethodRegistry.php`
- `src/Domain/Payment/PaymentService.php`
- `src/Domain/Payment/DuitkuGateway.php`
- `src/Domain/Payment/DuitkuCallbackListener.php`

### Artinya sekarang

`VD Store` mengurus:

- daftar metode pembayaran
- inisialisasi payment
- callback payment dasar
- update status payment dasar

Sedangkan addon hanya mengurus efek marketplace setelah payment berubah, misalnya:

- update status seller
- notifikasi seller
- fulfillment marketplace

---

## 7. Halaman Inti Toko Tetap Milik VD Store

Halaman inti toko sekarang dianggap milik core, bukan addon.

Contohnya:

- katalog
- cart
- checkout
- profile
- tracking

Addon marketplace sekarang diarahkan untuk **memakai halaman inti milik `VD Store`**, bukan membuat ulang halaman yang sama.

Ini mengurangi duplikasi dan membuat arsitektur lebih stabil.

---

## 8. Profil Customer Sekarang Bisa Di-extend dengan Hook

Salah satu perubahan penting adalah profil customer di `VD Store` sekarang tidak perlu dioverride total oleh addon.

Sebaliknya, core menyediakan titik hook/filter supaya addon bisa menambah tab dan panel.

File penting:

- `src/Frontend/CustomerProfile.php`

Hook/filter penting:

- `wp_store_profile_tabs`
- `wp_store_profile_panels`

### Hasilnya

`VD Store` tetap punya tampilan profil dasar:

- Profil Saya
- Buku Alamat
- Wishlist
- Pesanan

Kalau addon aktif, addon tinggal menambah menu seperti:

- Tracking
- Pesan
- Notifikasi
- Beranda Toko
- Produk
- Profil Toko

Jadi core tetap pemilik halaman, addon hanya menambah.

---

## 9. Tracking Inti Sekarang Tetap Milik VD Store

Tracking order dasar sekarang kembali ditegaskan sebagai milik `VD Store`.

File penting:

- `src/Frontend/Shortcode.php`
- `templates/frontend/pages/tracking.php`

`VD Store` sekarang menyediakan:

- form tracking utama
- ringkasan order dasar
- alamat pengiriman dasar
- status order dasar
- payment info dasar
- upload bukti transfer dasar

### Extension point baru

Supaya addon tidak bikin tracking kedua, core sekarang menyediakan hook/filter:

- `wp_store_tracking_query_param`
- `wp_store_tracking_input_label`
- `wp_store_tracking_input_placeholder`
- `wp_store_tracking_submit_label`
- `wp_store_tracking_empty_help`
- `wp_store_tracking_resolved_order_id`
- `wp_store_tracking_after_order_content`

### Manfaatnya

Addon marketplace sekarang bisa:

- pakai input `invoice` alih-alih `order`
- resolve order dari invoice marketplace
- menyuntik blok tambahan seperti “Pengiriman per Toko”

Tanpa perlu mengambil alih seluruh halaman tracking.

---

## 10. Single Product Core Sekarang Lebih Lengkap

Halaman single product bawaan `VD Store` sekarang tidak lagi terlalu minimal.

Yang sudah ditambahkan:

- ringkasan metrik produk umum:
  - rating rata-rata
  - jumlah ulasan
  - jumlah terjual
- slot hook setelah ringkasan produk
- section `Produk Terkait` di bawah deskripsi

Manfaatnya:

- halaman single tetap kuat walau tanpa addon
- saat `VD Marketplace` aktif, addon cukup menambah info seller lewat hook, bukan mengambil alih template

---

## 11. User Data Customer Sekarang Satu Sumber

Untuk data customer umum, `VD Store` sekarang diposisikan sebagai source of truth.

Contoh data customer yang dipakai bersama:

- nama user
- email
- telepon
- alamat
- wishlist
- avatar customer

Ini penting supaya walaupun UI bisa berbeda saat addon aktif, data customer dasarnya tetap satu sumber.

---

## 12. Kenapa Folder `Domain` Dipakai

Di `VD Store` sekarang ada banyak file di folder `src/Domain/...`.

Tujuannya sederhana:

- logic bisnis inti tidak dicampur ke controller atau template
- aturan inti punya tempat yang jelas
- addon lebih mudah memakai core
- kalau schema berubah, titik ubahnya lebih sedikit

Contoh:

- `ProductData` = memetakan data produk
- `ProductQuery` = query produk dasar
- `CartService` = cart dasar
- `WishlistService` = wishlist dasar
- `OrderService` = order dasar
- `PaymentService` = payment dasar

Jadi:

- `Domain` = otak / aturan inti
- `Api` = pintu REST
- `Admin` = tampilan admin
- `Frontend` = tampilan user

---

## 13. Arah Teknis Sekarang

Secara praktis, arah sistem sekarang adalah:

### VD Store

Menjadi core untuk:

- toko online biasa
- reusable untuk addon lain

### VD Marketplace

Menjadi addon untuk:

- multi-seller
- seller dashboard
- fulfillment per seller
- fitur marketplace lain

Ini membuat arsitektur lebih aman untuk:

- maintenance
- scale ke banyak website
- penambahan addon lain di masa depan

---

## 14. Ringkasan Singkat

Kalau ingin dijelaskan sangat singkat:

1. `VD Store` sekarang menjadi fondasi utama toko.
2. Produk, cart, wishlist, order, payment, profile, dan tracking dasar sudah dipindah ke core.
3. `VD Marketplace` tidak lagi seharusnya membuat ulang fitur inti toko.
4. Addon sekarang diarahkan untuk menambah fitur lewat hook/filter atau layer tambahan saja.
5. Struktur baru ini membuat sistem lebih rapi, lebih ringan, dan lebih mudah dikembangkan.

---

## 15. Catatan untuk Tim

Saat menambah fitur baru, gunakan aturan ini:

- kalau fitur itu dibutuhkan toko biasa, masuk ke `VD Store`
- kalau fitur itu hanya dibutuhkan marketplace, masuk ke `VD Marketplace`
- kalau addon hanya perlu menambah UI di halaman core, usahakan pakai hook/filter, jangan override total
- kalau menambah field produk, mulai dari `ProductSchema`
