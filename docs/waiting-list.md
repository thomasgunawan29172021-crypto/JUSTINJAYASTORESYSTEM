# Waiting List / PO List

Menu: CRM → Waiting List / PO List. Pencatatan kebutuhan saja; tidak terhubung Accurate, tidak mengelola atau mengalokasikan stok.

- Role baru **CRM** dapat dipilih sebagai role utama atau tambahan lewat manajemen user. Hanya role CRM dan CEO yang dapat membuka serta mengubah Waiting List. Akses data pelanggan lama tetap tersedia bagi Kepala Toko, Frontliner, dan Admin Chat sesuai cabangnya; mereka tidak mendapat akses Waiting List. Gudang juga tidak mendapat akses.
- Pilih pelanggan lama (cari nama/nomor), atau daftarkan nama dan nomor baru dari form PO. Nomor dinormalisasi; nomor yang sudah ada harus dipilih dari pelanggan lama. Cabang hanya untuk kepemilikan data pelanggan, bukan stok.
- Satu PO berisi satu atau banyak produk bebas, jumlah unit, harga per unit dalam rupiah, dan catatan. Pelanggan dapat memiliki banyak PO.
- Status: Menunggu, Sudah dikabari, Jadi beli. Ubah per produk atau seluruh produk **dalam satu PO**. Perubahan dicatat dalam histori pelanggan. Status dapat dikoreksi kembali.
- Rekap menggabungkan nama produk tanpa membedakan kapital/spasi berulang. Varian/kapasitas yang berbeda tetap berbeda bila ditulis berbeda. Rekap aktif mencakup menunggu dan sudah dikabari, diurutkan total unit.
- Angka menu adalah jumlah baris produk aktif, bukan jumlah unit atau pelanggan. Produk jadi beli tetap tersedia melalui filter semua status/Jadi beli.
- Klik produk di rekap untuk melihat pesanan yang memuat kebutuhan tersebut. Setiap kartu tetap menampilkan seluruh isi PO agar cakupan tombol massal jelas.

## Menjalankan

Dependensi yang terpasang membutuhkan PHP **8.4 atau lebih baru**. Jalankan dari root proyek:

```sh
php artisan migrate --force
npm run build
php artisan test
```

Migrasi baru hanya menambah `waiting_orders` dan `waiting_items`. Tidak mengubah katalog atau stok. Test menggunakan SQLite `:memory:` dan tidak mengisi database operasional.

## Smoke test manual

1. Login sebagai CRM/CEO. Buka menu waiting list, tambah pelanggan baru dan dua produk dengan qty/harga berbeda.
2. Pastikan kontak, subtotal, rekap unit, dan angka menu benar. Cari pelanggan yang sama dan buat PO kedua tanpa membuat customer baru.
3. Ubah satu produk menjadi Sudah dikabari; produk lain harus tetap Menunggu. Terapkan Jadi beli ke seluruh produk PO tersebut.
4. Pastikan PO selesai hilang dari filter aktif dan tetap terlihat di filter Semua status. Periksa histori di detail pelanggan.
5. Coba nomor yang sama dalam format `08...` dan `+62...`, qty nol, dan harga negatif; sistem harus menolak.
6. Login Gudang: menu tidak tersedia dan URL langsung ditolak. Frontliner cabang lain tidak dapat mencari/mengubah PO tersebut.
7. Pada layar sempit, coba tambah/hapus baris produk, cari pelanggan, dan geser tabel horizontal.
