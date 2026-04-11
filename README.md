# VD Store

Versi: `1.1.0`

`VD Store` adalah plugin inti untuk toko online.

Fungsi utamanya:
- produk
- keranjang
- wishlist
- checkout
- pesanan
- kupon
- tracking pesanan
- profil customer
- ulasan produk

## Cocok untuk apa

Pakai plugin ini jika kamu ingin membuat:
- toko online biasa
- katalog produk dengan checkout langsung
- sistem order tanpa fitur marketplace multi-seller

Kalau butuh fitur seller dan toko per penjual, tambahkan `VD Marketplace` di atas plugin ini.

## Fitur utama

- Produk fisik dan digital
- Harga promo
- Opsi varian dan opsi harga tambahan
- Keranjang dan wishlist
- Checkout dan tracking pesanan
- Kupon produk dan kupon ongkir
- Ulasan produk dari halaman pesanan customer
- Halaman profil customer

## Cara pakai singkat

1. Aktifkan plugin `VD Store`
2. Buka menu pengaturan toko di admin
3. Tentukan halaman sistem:
   - katalog
   - keranjang
   - checkout
   - terima kasih
   - tracking order
   - profil saya
4. Isi pengaturan pembayaran dan ongkir
5. Tambahkan produk

## Produk

### Produk fisik
- wajib isi harga
- berat wajib diisi
- dipakai untuk hitung ongkir

### Produk digital
- wajib isi harga
- berat tidak wajib
- wajib isi file digital atau URL file digital

## Keranjang dan checkout

- Produk fisik memakai ongkir
- Produk digital murni tidak butuh ongkir
- Cart campuran fisik + digital tetap didukung
- Setelah checkout, customer diarahkan ke halaman tracking order

## Ulasan produk

Customer bisa memberi ulasan dari:
- `Profil Saya -> Pesanan`

Syaratnya:
- order sudah selesai
- item produk berasal dari order customer tersebut

## Admin pesanan

Di edit pesanan admin tersedia field:
- status pesanan
- nomor resi
- kurir
- layanan
- biaya ongkir
- catatan admin

## Shortcode utama

- `[wp_store_catalog]`
- `[wp_store_cart]`
- `[wp_store_checkout]`
- `[wp_store_tracking]`
- `[wp_store_profile]`
- `[wp_store_wishlist]`
- `[wp_store_gallery]`
- `[wp_store_price]`
- `[wp_store_related]`
- `[wp_store_recently_viewed]`

## Catatan

- `VD Store` adalah core commerce
- `VD Marketplace` adalah addon, bukan pengganti core
- dokumentasi teknis developer ada di file:
  - `DOKUMENTASI-DEVELOPER.md`
