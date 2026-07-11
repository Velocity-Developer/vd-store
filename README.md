# VD Store

Versi: `1.4.5`

`VD Store` adalah plugin inti untuk toko online.

Plugin ini menjadi dasar untuk:
- produk
- katalog
- keranjang
- wishlist
- checkout
- pesanan
- kupon
- tracking pesanan
- profil customer
- ulasan produk

Kalau butuh fitur seller, toko per penjual, atau checkout multi-seller, tambahkan `VD Marketplace` di atas plugin ini.

## Cocok untuk apa

Pakai `VD Store` jika ingin membuat:
- toko online biasa
- katalog produk dengan checkout langsung
- produk fisik dan digital
- sistem order tanpa marketplace multi-seller

## Fitur utama

- CPT produk: `store_product`
- CPT pesanan: `store_order`
- CPT kupon: `store_coupon`
- Taxonomy kategori produk: `store_product_cat`
- Produk fisik dan digital
- Harga reguler dan harga promo. Harga boleh kosong untuk produk katalog/inquiry.
- Opsi varian dan opsi harga tambahan
- Keranjang dan wishlist
- Checkout dan tracking order
- Kupon produk dan kupon ongkir
- Profil customer
- Ulasan produk dari halaman pesanan
- Label manual produk
- Dukungan dropship di checkout dan profil customer
- Integrasi ongkir dengan mode normal, gratis ongkir, dan nonaktif
- Pengaturan checkout untuk tetap mengumpulkan alamat saat ongkir gratis/nonaktif
- Integrasi pembayaran manual dan gateway

## Ringkasan update 1.4.5

- Filter produk sekarang diterapkan hanya saat tombol `Terapkan` diklik, sehingga aman dipakai pada halaman Beaver Builder.
- Pada archive kategori, kategori dari URL menjadi scope tetap dan filter kategori lain disembunyikan.
- Menambahkan gambar taxonomy kategori dan brand, termasuk Quick Edit.
- Menambahkan shortcode carousel taxonomy untuk kategori atau brand.

## Instalasi singkat

1. Aktifkan plugin `VD Store`.
2. Buka menu pengaturan `VD Store`.
3. Tentukan halaman sistem:
   - katalog
   - keranjang
   - checkout
   - terima kasih
   - tracking order
   - profil saya
4. Isi pengaturan pembayaran dan ongkir.
5. Tambahkan kategori produk.
6. Tambahkan produk.

## Jenis produk

### Produk fisik
- wajib isi harga
- wajib isi berat
- ikut perhitungan ongkir

### Produk digital
- wajib isi harga
- tidak wajib isi berat
- wajib isi file digital atau URL file digital
- jika cart hanya berisi produk digital, checkout tidak memaksa ongkir

## Label produk

`VD Store` menyediakan label manual bawaan untuk merchandising produk:
- Best Seller
- Limited
- Pre Order
- Ready Stock
- New
- Recommended
- Sale

Label ini disimpan di meta `_store_label` dan dipakai oleh:
- thumbnail produk
- galeri produk
- katalog
- katalog print/PDF

Addon plugin bisa menambah atau mengubah daftar label lewat filter:
- `wp_store_product_labels`
- `wp_store_product_labels_registry`
- `wp_store_product_label_options`
- `wp_store_product_label_badge_html`

Kalau ingin menyesuaikan tampilan badge, gunakan filter `wp_store_product_label_badge_html` agar core tetap bersih.

## Icon

`VD Store` memakai helper `wps_icon()` untuk semua icon UI.

Addon bisa menambah atau override icon lewat filter:
- `wp_store_icons`
- `wp_store_icon_registry`
- `wp_store_icon_html`

Format yang didukung:
- `html` untuk SVG final
- `render` untuk callback yang menerima data icon

Kalau icon baru tidak ada di registry addon, core tetap jatuh ke icon bawaan di template `components/icons.php`.

## Alur customer

### Katalog
Customer bisa:
- melihat daftar produk
- mencari produk
- filter kategori
- filter brand
- filter harga
- mengurutkan produk
- membuka landing archive kategori dan brand dengan URL bersih; parameter filter tambahan tetap didukung
- print katalog lewat halaman print browser

### Single produk
Customer bisa:
- melihat galeri produk
- melihat harga promo
- memilih varian
- memilih opsi harga tambahan
- tambah ke keranjang
- tambah ke wishlist
- melihat produk terkait
- melihat rating dan ulasan

### Keranjang
Customer bisa:
- ubah jumlah item
- hapus item
- lihat opsi item yang dipilih
- lanjut ke checkout

### Checkout
Customer bisa:
- isi data penerima
- isi alamat
- pilih ongkir
- pilih metode pembayaran
- pakai kupon
- upload bukti transfer jika perlu
- melihat ringkasan dropship jika fitur diaktifkan di profil customer

Setelah checkout selesai, customer diarahkan ke halaman tracking order.

### Tracking order
Halaman tracking dipakai untuk:
- melihat status order
- melihat detail item
- melihat resi
- melihat ringkasan pembayaran

## Kupon

Jenis kupon yang didukung:
- diskon produk
- diskon ongkir

Aturan yang didukung:
- nominal
- persen
- minimal belanja
- batas penggunaan
- tanggal mulai
- tanggal kadaluarsa

Catatan:
- kupon produk menghitung minimal belanja dari subtotal produk
- kupon ongkir butuh ongkir yang sudah dipilih

## Ulasan produk

Customer bisa memberi ulasan dari:
- `Profil Saya -> Pesanan`

Syaratnya:
- order sudah selesai
- produk berasal dari order customer tersebut
- satu produk pada satu order hanya bisa direview sekali

## Admin pesanan

Di halaman edit pesanan admin tersedia field:
- status pesanan
- nomor resi
- kurir
- layanan
- biaya ongkir
- catatan admin

Admin juga bisa:
- print invoice
- print data pengiriman

Catatan:
- print memakai halaman HTML print-friendly browser
- tidak memakai generator PDF server-side

## Halaman dan shortcode

### Shortcode halaman utama

| Shortcode | Fungsi |
| --- | --- |
| `[wp_store_shop]` | Render daftar produk berdasarkan query shortcode. |
| `[wp_store_catalog]` | Render katalog produk sederhana. |
| `[wp_store_shop_with_filters]` | Render shop dengan sidebar filter. |
| `[wp_store_single]` | Render halaman single produk. |
| `[wp_store_cart_page]` | Render halaman keranjang. |
| `[store_cart]` | Alias dari `[wp_store_cart_page]`. |
| `[wp_store_checkout]` | Render halaman checkout. |
| `[store_checkout]` | Alias dari `[wp_store_checkout]`. |
| `[wp_store_thanks]` | Render halaman terima kasih. |
| `[store_thanks]` | Alias dari `[wp_store_thanks]`. |
| `[wp_store_tracking]` | Render halaman tracking pesanan. |
| `[store_tracking]` | Alias dari `[wp_store_tracking]`. |
| `[wp_store_wishlist]` | Render halaman wishlist. |

### Shortcode komponen produk

| Shortcode | Fungsi |
| --- | --- |
| `[wp_store_product_card]` | Render reusable product card. |
| `[wp_store_component]` | Render komponen produk reusable, misalnya `title`, `price`, `rating`, `actions`, `description`, `related`. |
| `[wp_store_product_info]` | Render tabel info/meta produk. |
| `[wp_store_info]` | Alias dari `[wp_store_product_info]`. |
| `[wp_store_product_meta]` | Alias dari `[wp_store_product_info]`. |
| `[wp_store_related]` | Render produk terkait. |
| `[wp_store_gallery]` | Render galeri produk. |
| `[wp_store_thumbnail]` | Render thumbnail produk. |
| `[wp_store_price]` | Render harga produk. |
| `[wp_store_add_to_cart]` | Tombol tambah ke keranjang. Untuk PHP/theme bisa memakai `wp_store_add_to_cart_button()`. |
| `[wp_store_buy_button]` | Alias dari `[wp_store_add_to_cart]`. |
| `[wp_store_detail]` | Link ke detail produk. |
| `[wp_store_add_to_wishlist]` | Tombol tambah ke wishlist. |
| `[wp_store_rating]` | Ringkasan bintang rating produk. |
| `[wp_store_review_count]` | Jumlah ulasan produk. |
| `[wp_store_product_reviews]` | Daftar ulasan produk. |
| `[wp_store_recently_viewed]` | Produk yang baru dilihat customer. |
| `[wp_store_products_carousel]` | Carousel produk. |

### Shortcode komponen toko dan utilitas

| Shortcode | Fungsi |
| --- | --- |
| `[wp_store_cart]` | Shortcut atau offcanvas keranjang. Atribut: `size` untuk ukuran icon. |
| `[wp_store_link_profile]` | Link atau icon ke halaman profil customer. Atribut: `size` untuk ukuran foto profil. |
| `[wp_store_filters]` | Sidebar filter shop. |
| `[wp_store_shipping_checker]` | Cek ongkir di halaman publik. |
| `[wp_store_couriers]` | Render daftar / logo kurir aktif dari pengaturan toko. |
| `[wp_store_captcha]` | Komponen captcha. Memakai Velocity Addons jika aktif, fallback ke captcha bawaan VD Store jika tidak. |
| `[wp-store-captcha]` | Alias dari `[wp_store_captcha]`. |
| `[wp_store_categories]` | Daftar kategori produk. |
| `[wp_store_taxonomies_carousel]` | Carousel kategori atau brand dengan gambar taxonomy. |
| `[wp_store_sosmed]` | Daftar sosial media. |
| `[wp_store_contact]` | Informasi kontak toko. |
| `[wp_store_bank_accounts]` | Daftar rekening toko. |

## Shortcode yang sering dipakai

### Shop dengan filter

```text
[wp_store_shop_with_filters per_page="12"]
```

### Tombol tambah ke keranjang

```text
[wp_store_add_to_cart id="123"]
```

Dengan teks tombol khusus:

```text
[wp_store_add_to_cart id="123" text="Beli Sekarang"]
```

Dengan tombol icon saja:

```text
[wp_store_add_to_cart id="123" text="" class="btn btn-primary btn-sm"]
```

Kalau dipakai di file PHP theme atau template:

```php
echo wp_store_add_to_cart_button(123, [
    'text' => 'Beli Sekarang',
    'class' => 'btn btn-primary btn-sm',
]);
```

Fungsi ini memakai jalur yang sama dengan shortcode, jadi opsi produk, minimal order, dan modal pilihan tetap seragam.

### Harga produk

```text
[wp_store_price id="123"]
```

Tanpa wrapper luar dan harga sebagai `span`:

```text
[wp_store_price id="123" wrapper="" tag="span"]
```

Alternatif yang lebih eksplisit:

```text
[wp_store_price id="123" wrapper="none" tag="span"]
```

### Galeri produk

```text
[wp_store_gallery id="123"]
```

### Produk terkait

```text
[wp_store_related id="123" limit="4"]
```

## Dokumentasi developer

Kalau ingin mengubah fungsi plugin, lihat file:
- `DOKUMENTASI-DEVELOPER.md`
