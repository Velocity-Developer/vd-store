# VD Store

Versi: `1.1.0`

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
- Harga reguler dan harga promo
- Opsi varian dan opsi harga tambahan
- Keranjang dan wishlist
- Checkout dan tracking order
- Kupon produk dan kupon ongkir
- Profil customer
- Ulasan produk dari halaman pesanan
- Integrasi ongkir
- Integrasi pembayaran manual dan gateway

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

## Alur customer

### Katalog
Customer bisa:
- melihat daftar produk
- mencari produk
- filter kategori
- filter harga
- mengurutkan produk

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
| `[wp_store_related]` | Render produk terkait. |
| `[wp_store_gallery]` | Render galeri produk. |
| `[wp_store_thumbnail]` | Render thumbnail produk. |
| `[wp_store_price]` | Render harga produk. |
| `[wp_store_add_to_cart]` | Tombol tambah ke keranjang. |
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
| `[wp_store_cart]` | Shortcut atau offcanvas keranjang. |
| `[wp_store_link_profile]` | Link atau icon ke halaman profil customer. |
| `[wp_store_filters]` | Sidebar filter shop. |
| `[wp_store_shipping_checker]` | Cek ongkir di halaman publik. |
| `[wp_store_categories]` | Daftar kategori produk. |
| `[wp_store_sosmed]` | Daftar sosial media. |
| `[wp_store_contact]` | Informasi kontak toko. |
| `[wp_store_bank_accounts]` | Daftar rekening toko. |
| `[wp_store_couriers]` | Logo kurir aktif. |
| `[wp_store_captcha]` | Komponen captcha. |
| `[wp-store-captcha]` | Alias dari `[wp_store_captcha]`. |

## Shortcode yang sering dipakai

### Shop dengan filter

```text
[wp_store_shop_with_filters per_page="12"]
```

### Tombol tambah ke keranjang

```text
[wp_store_add_to_cart id="123"]
```

Dengan tombol icon saja:

```text
[wp_store_add_to_cart id="123" text="" class="btn btn-primary btn-sm"]
```

### Harga produk

```text
[wp_store_price id="123"]
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
