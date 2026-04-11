# Dokumentasi Developer VD Store

Dokumen ini untuk developer. Isinya menjelaskan struktur inti plugin `VD Store`.

## Peran plugin

`VD Store` adalah core commerce.

Artinya plugin ini menjadi pemilik utama untuk:
- produk
- cart
- wishlist
- checkout
- order
- kupon
- profil customer
- tracking order
- review produk

`VD Marketplace` hanya menambah fitur marketplace di atas data inti ini.

## File penting

### Bootstrap plugin
- `vd-store.php`

### Produk
- `src/Domain/Product/ProductSchema.php`
- `src/Domain/Product/ProductFields.php`
- `src/Domain/Product/ProductMeta.php`
- `src/Domain/Product/ProductData.php`
- `src/Domain/Product/ProductQuery.php`

### Cart dan wishlist
- `src/Domain/Cart/CartService.php`
- `src/Domain/Wishlist/WishlistService.php`
- `src/Api/CartController.php`
- `src/Api/WishlistController.php`

### Checkout, order, kupon
- `src/Api/CheckoutController.php`
- `src/Api/CouponController.php`
- `src/Domain/Order/OrderService.php`
- `src/Admin/OrderMetaBoxes.php`

### Review produk
- `src/Domain/Review/ProductReviewRepository.php`
- `src/Api/CustomerController.php`
- `src/Frontend/CustomerProfile.php`

### Frontend
- `src/Frontend/Shortcode.php`
- `src/Frontend/Assets.php`
- `templates/frontend/`

## Kontrak produk inti

Meta canonical produk yang dipakai core:
- `_store_product_type`
- `_store_price`
- `_store_sale_price`
- `_store_flashsale_until`
- `_store_digital_file`
- `_store_sku`
- `_store_stock`
- `_store_min_order`
- `_store_weight_kg`
- `_store_gallery_ids`
- `_store_option_name`
- `_store_options`
- `_store_option2_name`
- `_store_advanced_options`
- `_store_sold_count`
- `_store_review_count`
- `_store_rating_average`

## Aturan penting produk

### Produk fisik
- wajib isi berat
- ikut hitung ongkir

### Produk digital
- berat tidak wajib
- file digital wajib
- tidak memaksa ongkir jika cart hanya berisi item digital

### Opsi harga tambahan
- harga akhir item = harga dasar + tambahan opsi
- jangan menimpa harga dasar dengan nilai opsi saja

## Validasi field produk

Sumber kebenaran field produk ada di:
- `ProductSchema`
- `ProductFields`

Pola yang dipakai sekarang:
- schema menentukan field
- render form admin dan frontend membaca schema yang sama
- validasi server membaca schema yang sama
- popup validasi JS hanya untuk feedback cepat, bukan sumber kebenaran utama

Kalau mau tambah field baru, urutannya:
1. tambah definisi di `ProductSchema`
2. pakai tipe field yang sudah ada di `ProductFields`
3. kalau perlu tipe field baru, baru extend `ProductFields`

## Review produk

Review produk sekarang milik core `VD Store`.

Alur saat ini:
- customer buka `Profil Saya -> Pesanan`
- hanya item pada order selesai yang bisa direview
- single product menampilkan rating dan daftar review

Review ini sengaja tidak memakai comment WordPress bawaan, karena review harus terikat ke:
- product
- order
- buyer

## Admin order

Admin order sekarang punya metabox native untuk:
- status pesanan
- nomor resi
- kurir
- layanan
- biaya ongkir
- catatan admin

Tujuannya supaya field inti order tidak bergantung ke CMB2.

## Arah integrasi dengan VD Marketplace

`VD Marketplace` membaca data inti dari `VD Store`.

Yang dipakai bersama antara core dan addon antara lain:
- CPT produk: `store_product`
- CPT order: `store_order`
- CPT kupon: `store_coupon`
- taxonomy kategori: `store_product_cat`
- meta inti produk dan kupon

Jangan bikin storage produk/order/coupon kedua di addon.

## Area yang paling sensitif saat diubah

Kalau mengubah area ini, tes ulang end-to-end:
- form produk admin
- cart dan checkout
- kupon produk dan ongkir
- review produk
- order status dan resi
- produk digital vs fisik
- harga promo dan opsi harga tambahan

## Versi saat ini

- plugin version: `1.1.0`
- constant: `WP_STORE_VERSION`
