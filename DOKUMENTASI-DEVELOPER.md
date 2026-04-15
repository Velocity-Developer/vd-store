# Dokumentasi Developer VD Store

Versi plugin: `1.1.0`

Dokumen ini ditujukan untuk developer yang ingin:
- memahami struktur plugin
- mengetahui fungsi file-file penting
- mengetahui shortcode yang tersedia
- mengetahui kalau mau ubah fitur harus mulai dari file mana

## 1. Peran plugin

`VD Store` adalah core commerce.

Plugin ini menjadi pemilik utama untuk:
- produk
- cart
- wishlist
- checkout
- order
- kupon
- tracking order
- profil customer
- review produk

Kalau `VD Marketplace` aktif, addon itu seharusnya membaca data inti dari `VD Store`, bukan membuat sistem produk, order, atau kupon versi kedua.

## 2. File utama

### `vd-store.php`
Isi dan fungsi:
- file bootstrap utama plugin
- mendefinisikan versi plugin
- membuat tabel database plugin
- memanggil class utama plugin
- menyediakan helper global seperti:
  - `wps_icon()`
  - `wps_discount_badge_html()`
  - `wps_product_price_html()`

### `Plugin.php`
Isi dan fungsi:
- class utama yang menjalankan semua module
- mendaftarkan admin, frontend, assets, dan API

### `PostTypes.php`
Isi dan fungsi:
- register post type `store_product`
- register post type `store_order`
- register post type `store_coupon`
- register taxonomy `store_product_cat`

## 3. Struktur folder dan fungsi masing-masing

### Folder `src/Admin`
Dipakai untuk area admin WordPress.

#### `AdminMenu.php`
Fungsi:
- menambah menu admin plugin

#### `Settings.php`
Fungsi:
- pengaturan admin plugin

#### `ProductMetaBoxes.php`
Fungsi:
- field produk di wp-admin
- simpan data produk dari editor admin

#### `ProductColumns.php`
Fungsi:
- kolom custom di daftar produk admin

#### `OrderMetaBoxes.php`
Fungsi:
- field status pesanan
- nomor resi
- kurir
- layanan
- biaya ongkir
- catatan admin

#### `OrderColumns.php`
Fungsi:
- kolom custom di daftar order admin

#### `OrderActions.php`
Fungsi:
- aksi tambahan terkait order di admin

#### `OrderEmails.php`
Fungsi:
- kirim email order dari admin/backend

#### `OrderPrint.php`
Fungsi:
- cetak invoice dan data pengiriman
- memakai halaman HTML print-friendly browser
- auto memanggil `window.print()` saat halaman dibuka

#### `CouponMetaBoxes.php`
Fungsi:
- field kupon di wp-admin

#### `CouponColumns.php`
Fungsi:
- kolom custom daftar kupon

### Folder `src/Api`
Dipakai untuk REST API.

#### `ProductController.php`
Fungsi:
- endpoint produk
- data produk untuk frontend
- print katalog browser lewat route `catalog/print`

#### `CartController.php`
Fungsi:
- endpoint keranjang
- tambah item, ubah qty, hapus item

#### `WishlistController.php`
Fungsi:
- endpoint wishlist

#### `CheckoutController.php`
Fungsi:
- proses checkout
- validasi checkout
- simpan order

#### `CouponController.php`
Fungsi:
- validasi kupon
- hitung diskon kupon

#### `CustomerController.php`
Fungsi:
- data customer profile
- data order customer
- submit review produk dari pesanan

#### `RajaOngkirController.php`
Fungsi:
- endpoint ongkir dan wilayah

#### `SettingsController.php`
Fungsi:
- endpoint pengaturan toko

#### `CaptchaController.php`
Fungsi:
- endpoint captcha

#### `ToolsController.php`
Fungsi:
- tools tambahan untuk maintenance atau debug

### Folder `src/Domain`
Dipakai untuk business logic utama plugin.

#### Bagian produk

##### `ProductSchema.php`
Fungsi:
- sumber aturan field produk
- menentukan field apa saja yang ada
- menentukan field wajib atau tidak
- menentukan field hanya tampil untuk jenis produk tertentu

Kalau mau tambah field produk baru, biasanya mulai dari file ini.

##### `ProductFields.php`
Fungsi:
- render field produk berdasarkan schema
- menyiapkan atribut field untuk admin dan frontend

##### `ProductMeta.php`
Fungsi:
- helper baca dan tulis meta produk

##### `ProductData.php`
Fungsi:
- memetakan data produk menjadi payload siap pakai
- menghitung harga efektif
- menghitung harga reguler, promo, dan harga dengan opsi tambahan

Kalau ada bug harga, biasanya file ini yang harus dicek dulu.

##### `ProductQuery.php`
Fungsi:
- query produk
- normalisasi filter query
- apply filter ke `WP_Query`

##### `RelatedProducts.php`
Fungsi:
- logika produk terkait

##### `RecentlyViewed.php`
Fungsi:
- logika produk yang baru dilihat customer

#### Bagian cart dan wishlist

##### `CartService.php`
Fungsi:
- logic utama cart
- simpan cart user atau guest
- hitung subtotal item

##### `WishlistService.php`
Fungsi:
- logic utama wishlist

#### Bagian order dan pembayaran

##### `OrderService.php`
Fungsi:
- membuat order
- menyimpan item order
- menyimpan total dan ringkasan order

##### `PaymentService.php`
Fungsi:
- orkestrasi pembayaran

##### `PaymentMethodRegistry.php`
Fungsi:
- daftar metode pembayaran yang aktif

##### `DuitkuGateway.php`
Fungsi:
- integrasi payment gateway Duitku

##### `DuitkuCallbackListener.php`
Fungsi:
- menangani callback pembayaran dari Duitku

#### Bagian review

##### `ProductReviewRepository.php`
Fungsi:
- simpan dan ambil review produk
- memastikan review terikat ke `product_id`, `order_id`, dan `user_id`

##### `RatingRenderer.php`
Fungsi:
- render bintang rating
- helper tampilan rating produk

### Folder `src/Frontend`
Dipakai untuk frontend rendering dan template.

#### `Shortcode.php`
Fungsi:
- register semua shortcode plugin
- render halaman dan komponen frontend
- override template archive dan single produk

Ini salah satu file paling penting di plugin.

#### `Assets.php`
Fungsi:
- enqueue CSS dan JS frontend

#### `CustomerProfile.php`
Fungsi:
- halaman profil customer
- tab profil, wishlist, pesanan, alamat, dan review

#### `OrderPublicActions.php`
Fungsi:
- aksi order yang bisa dipanggil dari halaman publik

#### `Template.php`
Fungsi:
- helper render template frontend

#### `LoginBranding.php`
Fungsi:
- branding halaman login jika dipakai

### Folder `templates/frontend`
Dipakai untuk file tampilan frontend.

#### Template archive dan single

##### `archive-store_product.php`
Fungsi:
- template archive default produk

##### `single-store_product.php`
Fungsi:
- template single default produk

##### `taxonomy-store_product_cat.php`
Fungsi:
- template kategori produk

#### Template halaman

##### `shop.php`
Fungsi:
- layout daftar produk

##### `single.php`
Fungsi:
- layout single produk

##### `checkout.php`
Fungsi:
- layout checkout

##### `cart.php`
Fungsi:
- layout keranjang

##### `tracking.php`
Fungsi:
- layout tracking order

##### `thanks.php`
Fungsi:
- layout halaman terima kasih

##### `related.php`
Fungsi:
- tampilan produk terkait

##### `recently-viewed.php`
Fungsi:
- tampilan produk yang baru dilihat

##### `catalog.php`
Fungsi:
- tampilan katalog default

##### `shipping-checker.php`
Fungsi:
- tampilan cek ongkir

#### Template komponen

##### `product-card.php`
Fungsi:
- card produk default plugin

##### `product-gallery.php`
Fungsi:
- galeri gambar produk

##### `product-reviews.php`
Fungsi:
- daftar ulasan produk

##### `filters.php`
Fungsi:
- sidebar filter produk

##### `add-to-cart.php`
Fungsi:
- tombol dan flow add to cart

##### `add-to-wishlist.php`
Fungsi:
- tombol wishlist

##### `cart-widget.php`
Fungsi:
- shortcut atau offcanvas keranjang

##### `wishlist-widget.php`
Fungsi:
- tampilan wishlist

##### `products-carousel.php`
Fungsi:
- carousel produk

##### `categories-list.php`
Fungsi:
- daftar kategori

##### `captcha.php`
Fungsi:
- komponen captcha

## 4. Kontrak data penting

### Meta produk

Meta canonical produk:
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

### Meta kupon

Meta canonical kupon:
- `_store_coupon_code`
- `_store_coupon_scope`
- `_store_coupon_type`
- `_store_coupon_value`
- `_store_coupon_min_purchase`
- `_store_coupon_usage_limit`
- `_store_coupon_usage_count`
- `_store_coupon_starts_at`
- `_store_coupon_expires_at`

### Meta order

Meta order yang sering dipakai:
- `_store_order_status`
- `_store_order_shipping_courier`
- `_store_order_shipping_service`
- `_store_order_shipping_cost`
- `_store_order_receipt_no`
- `_store_order_admin_note`
- `_store_order_coupon_code`
- `_store_order_coupon_scope`
- `_store_order_discount_total`

## 5. Aturan inti yang harus dipahami

### Produk fisik
- berat wajib
- ikut hitung ongkir

### Produk digital
- berat tidak wajib
- file digital wajib
- cart digital-only tidak boleh memaksa shipping

### Opsi harga tambahan
- harga akhir item = harga dasar + tambahan opsi
- jangan ganti harga dasar dengan nilai opsi saja

### Harga promo
- filtering
- sorting
- cart
- checkout
- order

semuanya harus membaca harga efektif, bukan selalu harga reguler.

### Review produk
- review harus terikat ke:
  - `product_id`
  - `order_id`
  - `user_id`
- tidak memakai comment WordPress biasa

## 6. Shortcode lengkap

Semua shortcode di bawah didaftarkan di `Shortcode.php`.

### Halaman

| Shortcode | Method | Template utama | Fungsi |
| --- | --- | --- | --- |
| `[wp_store_shop]` | `render_shop()` | `shop.php` | Daftar produk berdasarkan query shortcode. |
| `[wp_store_catalog]` | `render_catalog()` | `catalog.php` | Katalog produk sederhana. |
| `[wp_store_shop_with_filters]` | `render_shop_with_filters()` | `filters.php` + `shop.php` | Shop dengan sidebar filter. |
| `[wp_store_single]` | `render_single()` | `single.php` | Single produk. |
| `[wp_store_cart_page]` | `render_cart_page()` | `cart.php` | Halaman keranjang. |
| `[store_cart]` | `render_cart_page()` | `cart.php` | Alias cart page. |
| `[wp_store_checkout]` | `render_checkout()` | `checkout.php` | Halaman checkout. |
| `[store_checkout]` | `render_checkout()` | `checkout.php` | Alias checkout. |
| `[wp_store_thanks]` | `render_thanks()` | `thanks.php` | Halaman terima kasih. |
| `[store_thanks]` | `render_thanks()` | `thanks.php` | Alias thanks. |
| `[wp_store_tracking]` | `render_tracking()` | `tracking.php` | Tracking pesanan. |
| `[store_tracking]` | `render_tracking()` | `tracking.php` | Alias tracking. |
| `[wp_store_wishlist]` | `render_wishlist()` | `wishlist-widget.php` | Halaman wishlist. |

### Komponen produk

| Shortcode | Method | Template utama | Fungsi |
| --- | --- | --- | --- |
| `[wp_store_related]` | `render_related()` | `related.php` | Produk terkait. |
| `[wp_store_gallery]` | `render_gallery()` | `product-gallery.php` | Galeri produk. |
| `[wp_store_thumbnail]` | `render_thumbnail()` | method langsung | Thumbnail produk. |
| `[wp_store_price]` | `render_price()` | helper `wps_product_price_html()` | Harga produk. |
| `[wp_store_add_to_cart]` | `render_add_to_cart()` | `add-to-cart.php` | Tombol add to cart. |
| `[wp_store_detail]` | `render_detail()` | method langsung | Link detail produk. |
| `[wp_store_add_to_wishlist]` | `render_add_to_wishlist()` | `add-to-wishlist.php` | Tombol wishlist. |
| `[wp_store_rating]` | `render_rating()` | `RatingRenderer.php` | Bintang rating produk. |
| `[wp_store_review_count]` | `render_review_count()` | method langsung | Jumlah ulasan produk. |
| `[wp_store_product_reviews]` | `render_product_reviews()` | `product-reviews.php` | Daftar ulasan produk. |
| `[wp_store_recently_viewed]` | `render_recently_viewed()` | `recently-viewed.php` | Produk yang baru dilihat. |
| `[wp_store_products_carousel]` | `render_products_carousel()` | `products-carousel.php` | Carousel produk. |

### Komponen toko dan utilitas

| Shortcode | Method | Template utama | Fungsi |
| --- | --- | --- | --- |
| `[wp_store_cart]` | `render_cart_widget()` | `cart-widget.php` | Shortcut atau offcanvas keranjang. |
| `[wp_store_link_profile]` | `render_link_profile()` | method langsung | Link atau icon profil customer. |
| `[wp_store_filters]` | `render_filters()` | `filters.php` | Sidebar filter shop. |
| `[wp_store_shipping_checker]` | `render_shipping_checker()` | `shipping-checker.php` | Cek ongkir. |
| `[wp_store_categories]` | `render_categories()` | `categories-list.php` | Daftar kategori. |
| `[wp_store_sosmed]` | `render_sosmed()` | method langsung | Sosial media toko. |
| `[wp_store_contact]` | `render_contact()` | method langsung | Kontak toko. |
| `[wp_store_bank_accounts]` | `render_bank_accounts()` | method langsung | Daftar rekening toko. |
| `[wp_store_couriers]` | `render_couriers()` | method langsung | Logo kurir aktif. |
| `[wp_store_captcha]` | `render_captcha()` | `captcha.php` | Captcha. |
| `[wp-store-captcha]` | `render_captcha()` | `captcha.php` | Alias captcha. |

## 7. Kalau mau edit fitur tertentu, mulai dari file ini

### Mau ubah field produk
Mulai dari:
- `ProductSchema.php`
- `ProductFields.php`

Kalau field tampil di admin:
- `ProductMetaBoxes.php`

### Mau ubah harga
Mulai dari:
- `ProductData.php`

Kalau mau ubah HTML harga:
- `vd-store.php`
  - helper `wps_product_price_html()`

### Mau ubah card produk
Mulai dari:
- `product-card.php`

Kalau data card kurang:
- `Shortcode.php`
  - method `card_item_from_product()`

### Mau ubah galeri produk
Mulai dari:
- `product-gallery.php`
- `store.js`
- `style.css`

### Mau ubah archive shop dan filter
Mulai dari:
- `Shortcode.php`
  - `render_shop()`
  - `render_filters()`
  - `render_shop_with_filters()`
  - `adjust_archive_query()`
- `filters.php`
- `shop.php`
- `style.css`

### Mau ubah produk terkait
Mulai dari:
- `RelatedProducts.php`
- `Shortcode.php`
  - `render_related()`
- `related.php`

### Mau ubah cart
Mulai dari:
- `CartService.php`
- `CartController.php`
- `cart.php`
- `cart-widget.php`

### Mau ubah checkout
Mulai dari:
- `CheckoutController.php`
- `checkout.php`
- `OrderService.php`

### Mau ubah kupon
Mulai dari:
- `CouponController.php`
- `CouponMetaBoxes.php`

### Mau ubah status order dan resi admin
Mulai dari:
- `OrderMetaBoxes.php`
- `OrderColumns.php`
- `OrderActions.php`

### Mau ubah review produk
Mulai dari:
- `ProductReviewRepository.php`
- `CustomerController.php`
- `CustomerProfile.php`
- `product-reviews.php`

### Mau ubah profil customer
Mulai dari:
- `CustomerProfile.php`
- `CustomerController.php`

### Mau ubah tracking order
Mulai dari:
- `tracking.php`
- `OrderPublicActions.php`

### Mau ubah asset frontend
Mulai dari:
- `Assets.php`
- `style.css`
- `store.js`

## 8. Method penting di `Shortcode.php`

Kalau ingin cepat memahami alur frontend, fokus dulu ke method ini:

### Query dan shop
- `render_shop()`
- `render_filters()`
- `render_shop_with_filters()`
- `adjust_archive_query()`

### Single produk
- `render_single()`
- `render_gallery()`
- `render_related()`
- `render_product_reviews()`

### Card dan komponen produk
- `card_item_from_product()`
- `render_thumbnail()`
- `render_price()`
- `render_add_to_cart()`
- `render_add_to_wishlist()`

### Cart dan checkout
- `render_cart_widget()`
- `render_cart_page()`
- `filter_cart_page_content()`
- `render_checkout()`
- `render_tracking()`
- `render_thanks()`

### Template dan URL
- `override_archive_template()`
- `redirect_page_conflict()`

## 9. Area sensitif yang wajib dites setelah diubah

Kalau mengubah area ini, tes ulang end-to-end:

### Produk
- produk fisik
- produk digital
- harga promo
- opsi harga tambahan
- gallery produk

### Shop
- filter harga
- sort harga
- reset filter
- archive produk
- produk terkait

### Cart dan checkout
- add to cart
- update qty
- hapus item
- cart digital-only
- cart campuran fisik + digital
- pilih ongkir
- checkout selesai

### Kupon
- kupon produk
- kupon ongkir
- minimal belanja
- batas penggunaan
- tanggal aktif dan kadaluarsa

### Order
- status order
- nomor resi
- tracking publik
- upload bukti transfer

### Review
- submit review dari pesanan
- review ganda ditolak
- review tampil di single produk

## 10. Prinsip pengembangan yang sekarang dipakai

### Produk
- field produk diatur dari schema
- admin dan frontend membaca aturan yang sama
- validasi server tetap jadi sumber kebenaran

### Harga
- angka backend harus membaca harga efektif
- helper tampilan harga dibungkus di `wps_product_price_html()`

### Review
- review tetap custom data
- jangan pindah ke comment WordPress biasa

### Integrasi addon
- addon harus membaca kontrak canonical `VD Store`
- jangan buat meta produk, order, atau kupon versi kedua kalau tidak benar-benar perlu
