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
  - `wp_store_add_to_cart_button()`

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
- dipakai oleh shortcode `[wp_store_add_to_cart]` dan helper PHP `wp_store_add_to_cart_button()`
- menangani opsi produk, minimal order, dan data modal pilihan sebelum produk masuk keranjang

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
| `[wp_store_add_to_cart]` | `render_add_to_cart()` | `add-to-cart.php` | Tombol add to cart. Untuk file PHP theme/template bisa memakai `wp_store_add_to_cart_button()`. |
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

## 11. Kontrak override tampilan dan query

### Template override
Addon klien bisa override template tanpa mengubah file `vd-store`:

```php
add_filter('wp_store_template_candidates', function ($paths, $template) {
    $paths[] = plugin_dir_path(__FILE__) . 'templates/vd-store/' . $template . '.php';
    return $paths;
}, 10, 2);
```

Template default tetap menjadi fallback terakhir dari `templates/frontend`.

### Renderer produk
Gunakan renderer resmi supaya archive, related, carousel, shortcode, dan Beaver Builder memakai output yang sama:

```php
echo wp_store_render_product_card($product_id, [
    'context' => 'archive',
    'variant' => 'default',
]);

echo wp_store_render_product_component('price', $product_id);
echo wp_store_render_product_component('rating', $product_id);
echo wp_store_product_info($product_id);
echo wp_store_render_single_product($product_id, [
    'hide' => 'rating,related',
]);
```

Shortcode yang tersedia:

```text
[wp_store_product_card id="123"]
[wp_store_product_card id="123" width="400" height="400"]
[wp_store_component id="123" name="rating"]
[wp_store_product_info id="123"]
[wp_store_info id="123"]
[wp_store_single id="123" hide="rating,related"]
```

Section single produk bisa dikurangi atau diurutkan dari addon:

```php
add_filter('wp_store_single_product_sections', function ($sections, $product_id) {
    unset($sections['rating']);
    return $sections;
}, 10, 2);
```

### Filter produk berbasis WP_Query args
Filter archive sekarang dibaca lewat request terstruktur lalu diterapkan ke `WP_Query` args:

```php
$args = wp_store_product_filter_args([
    'post_type' => 'store_product',
    'posts_per_page' => 12,
]);

$query = new WP_Query($args);
```

Hook yang dipakai:

```text
pre_get_posts
fl_builder_loop_query_args
wp_store_product_query_args
wp_store_product_filter_request
```

Mode filter:

```text
off  = full GET/non-JS
auto = GET + enhancement ringan
ajax = disiapkan sebagai mode lanjutan
```

## 12. Contoh pemakaian untuk plugin pendamping dan Beaver Builder

Bagian ini menjelaskan contoh paling praktis. Anggap `vd-store` adalah mesin toko, sedangkan plugin pendamping hanya mengubah tampilan dan kebutuhan khusus klien.

### Contoh struktur plugin pendamping

Misalnya buat plugin baru:

```text
wp-content/plugins/vd-store-client-a/
- vd-store-client-a.php
- templates/
  - vd-store/
    - components/
      - product-card.php
    - pages/
      - single-flex.php
- assets/
  - client-a.css
```

File utama plugin pendamping:

```php
<?php
/**
 * Plugin Name: VD Store Client A
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_store_template_candidates', function ($paths, $template) {
    $paths[] = plugin_dir_path(__FILE__) . 'templates/vd-store/' . $template . '.php';
    return $paths;
}, 10, 2);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'vd-store-client-a',
        plugin_dir_url(__FILE__) . 'assets/client-a.css',
        [],
        '1.0.0'
    );
});
```

Penjelasan sederhana:
- `vd-store` tetap jalan seperti biasa.
- Kalau `vd-store` mau render template, plugin pendamping diberi kesempatan menyediakan file pengganti.
- Kalau file pengganti tidak ada, template bawaan `vd-store` tetap dipakai.

### Contoh override product card

Kalau ingin semua card produk berubah, buat file:

```text
vd-store-client-a/templates/vd-store/components/product-card.php
```

Isi contoh sederhana:

```php
<?php
$id = (int) ($item['id'] ?? 0);
$title = (string) ($item['title'] ?? '');
$link = (string) ($item['link'] ?? '#');
$thumbnail_width = isset($thumbnail_width) ? (int) $thumbnail_width : 200;
$thumbnail_height = isset($thumbnail_height) ? (int) $thumbnail_height : 300;
?>

<article class="client-product-card">
    <a href="<?php echo esc_url($link); ?>" class="client-product-card__image">
        <?php echo do_shortcode('[wp_store_thumbnail id="' . esc_attr((string) $id) . '" width="' . esc_attr((string) $thumbnail_width) . '" height="' . esc_attr((string) $thumbnail_height) . '"]'); ?>
    </a>

    <h3 class="client-product-card__title">
        <a href="<?php echo esc_url($link); ?>">
            <?php echo esc_html($title); ?>
        </a>
    </h3>

    <?php echo wp_store_render_product_component('price', $id); ?>

    <div class="client-product-card__action">
        <?php echo do_shortcode('[wp_store_add_to_cart id="' . esc_attr((string) $id) . '" text="Beli"]'); ?>
    </div>
</article>
```

Dampaknya:
- Archive produk ikut berubah.
- Related product ikut berubah.
- Recently viewed ikut berubah.
- Carousel ikut berubah.
- Shortcode `[wp_store_product_card]` ikut berubah.

Jadi developer cukup ubah satu file product card.

### Contoh memanggil product card dari PHP

Kalau plugin pendamping punya template custom sendiri:

```php
echo wp_store_render_product_card(get_the_ID(), [
    'context' => 'custom-section',
    'variant' => 'compact',
]);
```

Kalau butuh ukuran thumbnail khusus:

```php
echo wp_store_render_product_card($product_id, [
    'thumbnail_width' => 400,
    'thumbnail_height' => 400,
]);
```

Kalau tidak mengirim ukuran, ukuran default mengikuti pengaturan:

```text
wp-admin/admin.php?page=wp-store-settings&tab=style
```

### Contoh single produk fleksibel

Single produk default sekarang dirender lewat:

```php
wp_store_render_single_product($product_id);
```

Kalau klien tidak mau rating dan related product:

```php
echo wp_store_render_single_product($product_id, [
    'hide' => 'rating,related',
]);
```

Kalau ingin aturan global dari plugin pendamping:

```php
add_filter('wp_store_single_product_sections', function ($sections, $product_id) {
    unset($sections['rating']);
    unset($sections['share']);
    return $sections;
}, 10, 2);
```

Penjelasan sederhana:
- Section adalah bagian-bagian single produk.
- Developer bisa mematikan bagian tertentu tanpa copy seluruh template single produk.
- Kalau butuh layout total berbeda, override file `pages/single-flex.php`.

### Contoh override layout single produk

Buat file:

```text
vd-store-client-a/templates/vd-store/pages/single-flex.php
```

Isi contoh minimal:

```php
<?php
$id = isset($id) ? (int) $id : 0;
if ($id <= 0) {
    return;
}
?>

<div class="client-single-product">
    <div class="client-single-product__media">
        <?php echo wp_store_render_product_component('gallery', $id); ?>
    </div>

    <div class="client-single-product__summary">
        <?php echo wp_store_render_product_component('title', $id); ?>
        <?php echo wp_store_render_product_component('price', $id); ?>
        <?php echo wp_store_product_info($id); ?>
        <?php echo wp_store_render_product_component('actions', $id); ?>
    </div>

    <div class="client-single-product__description">
        <?php echo wp_store_render_product_component('description', $id); ?>
    </div>
</div>
```

### Contoh komponen untuk Beaver Builder

Di Beaver Builder, developer bisa membuat layout single produk secara manual, lalu isi tiap module HTML/Shortcode dengan shortcode berikut.

Judul produk:

```text
[wp_store_component name="title"]
```

Gallery:

```text
[wp_store_component name="gallery"]
```

Harga:

```text
[wp_store_component name="price"]
```

Rating:

```text
[wp_store_component name="rating"]
```

Info produk:

```text
[wp_store_product_info]
```

Tombol beli:

```text
[wp_store_component name="actions"]
```

Deskripsi:

```text
[wp_store_component name="description"]
```

Related product:

```text
[wp_store_component name="related"]
```

Catatan:
- Kalau shortcode dipakai di halaman single produk, `id` boleh dikosongkan.
- Kalau dipakai di halaman biasa, isi `id` produk.

Contoh di halaman biasa:

```text
[wp_store_component id="123" name="price"]
[wp_store_product_info id="123"]
[wp_store_product_card id="123"]
```

### Contoh layout Beaver Builder single produk

Contoh susunan module:

```text
Row 1, dua kolom
- Kolom kiri: [wp_store_component name="gallery"]
- Kolom kanan:
  - [wp_store_component name="title"]
  - [wp_store_component name="price"]
  - [wp_store_product_info]
  - [wp_store_component name="actions"]

Row 2
- [wp_store_component name="description"]

Row 3
- [wp_store_component name="related"]
```

Dengan cara ini Beaver Builder hanya mengatur layout. Data produk, harga, tombol beli, dan logic cart tetap berasal dari `vd-store`.

### Contoh filter produk di halaman custom

Untuk halaman yang dibuat manual, pakai:

```text
[wp_store_shop_with_filters filter_mode="off" per_page="12"]
```

Mode `off` berarti:
- Form filter submit biasa.
- URL berisi query seperti `?sort=price_asc&min_price=10000`.
- Tetap jalan tanpa JavaScript.
- Cocok untuk halaman sederhana dan stabil.

Mode `auto`:

```text
[wp_store_shop_with_filters filter_mode="auto" per_page="12"]
```

Mode `auto` berarti:
- Tetap memakai query string.
- Ada enhancement Alpine untuk pengalaman lebih halus.
- Kalau JavaScript gagal, konsep dasarnya tetap GET.

### Contoh query produk custom dari PHP

Kalau plugin pendamping punya template halaman sendiri:

```php
$args = wp_store_product_filter_args([
    'post_type' => 'store_product',
    'posts_per_page' => 12,
    'paged' => max(1, (int) get_query_var('paged')),
]);

$query = new WP_Query($args);

while ($query->have_posts()) {
    $query->the_post();
    echo wp_store_render_product_card(get_the_ID());
}

wp_reset_postdata();
```

Penjelasan sederhana:
- `wp_store_product_filter_args()` membaca filter dari URL.
- Hasilnya menjadi args untuk `WP_Query`.
- Card tetap dirender lewat renderer resmi.

### Contoh Beaver Builder archive/query

Kalau Beaver Builder memakai module Posts atau Loop dengan post type `store_product`, filter dari URL akan masuk lewat hook:

```text
fl_builder_loop_query_args
```

Artinya:
- User pilih kategori/harga/sort dari form filter.
- URL berubah.
- Query Beaver Builder ikut membaca filter.
- Developer tidak perlu menulis ulang logic filter di Beaver.

Untuk filter-nya, tetap taruh shortcode ini di sidebar atau row kiri:

```text
[wp_store_filters mode="off"]
```

Atau pakai paket lengkap:

```text
[wp_store_shop_with_filters filter_mode="off"]
```

### Kapan pakai cara yang mana

Pakai template override kalau:
- desain card atau single produk berbeda jauh;
- semua loop harus ikut berubah;
- klien punya desain khusus.

Pakai shortcode Beaver Builder kalau:
- hanya butuh susun ulang layout;
- ingin cepat membuat single produk custom;
- tidak ingin membuat file template.

Pakai PHP renderer kalau:
- plugin pendamping punya template sendiri;
- butuh loop custom;
- butuh kontrol lebih detail dari shortcode.

Pakai filter query kalau:
- halaman produk dibuat manual;
- query dibuat oleh Beaver Builder;
- query dibuat oleh shortcode;
- semua harus membaca filter URL yang sama.
