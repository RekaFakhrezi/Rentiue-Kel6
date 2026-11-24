# Rentique — PHP Native + JSON

Duplikat mandiri Rentique untuk demonstrasi PBO. Tampilan, foto, katalog, akun pelanggan/admin, tas sewa, checkout, pembayaran simulasi, invoice, inventaris, dan pengembalian menggunakan PHP native. Tidak membutuhkan React, Vite, npm, Composer, MySQL, PostgreSQL, Supabase, internet, atau deploy.

## Menjalankan di Windows

Buka PowerShell di folder proyek, lalu:

```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1
```

Buka **http://127.0.0.1:8082**. Tekan **Ctrl+C** untuk menghentikan server. PHP portabel sudah tersedia dalam `.local/php/`; peluncur memakai PHP dari PATH jika runtime portabel tidak ada.

Jika port sedang dipakai:

```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1 -Port 8083
```

Dengan PHP 8.2+ yang sudah terpasang, perintah manual dari folder proyek:

```sh
php -S 127.0.0.1:8082 -t public router.php
```

Ini aplikasi web lokal: jangan membuka file PHP langsung dari File Explorer. Jalankan server lalu gunakan browser. Server hanya mendengarkan alamat loopback, bukan layanan untuk internet.

## Akun demo

| Akun | Email | Kata sandi |
| --- | --- | --- |
| Pelanggan | customer@rentique.test | Customer123! |
| Admin | admin@rentique.test | Admin123! |

Tombol akun demo hanya mengisi formulir; klik **Masuk** untuk login. Kata sandi akun dalam penyimpanan menggunakan hash. Semua pembayaran adalah simulasi, bukan transfer uang sungguhan.

## Data berada di satu folder

Saat pertama dibuka, aplikasi membuat `storage/data.json` berisi akun dan 12 pakaian demo. File ini menyimpan pengguna, kategori, pakaian, sewaan, rincian item, invoice, pembayaran, pengembalian, dan log stok. Tidak ada koneksi database atau kredensial layanan eksternal.

- Data tetap ada setelah browser ditutup atau server dihentikan/dijalankan ulang.
- Menyalin **seluruh folder proyek termasuk `storage`** memindahkan data ke komputer lain. Komputer tujuan membutuhkan PHP yang sesuai sistem operasinya.
- Untuk cadangan, hentikan server lalu salin `storage/data.json`.
- Jangan mengedit JSON ketika server berjalan. Penyimpanan aplikasi memakai penguncian file dan penggantian atomik agar pembaruan tidak saling menimpa.
- Untuk kembali ke demo awal, hentikan server dan **cadangkan**, kemudian hapus `storage/data.json`. Data demo dibuat kembali saat berikutnya dibuka. Tindakan ini menghilangkan riwayat aktif, jadi jangan dilakukan jika data masih dibutuhkan.
- JSON rusak tidak diam-diam direset. Pulihkan cadangan dan periksa izin tulis folder `storage`.

Data dan sesi lokal tidak masuk Git. Folder proyek asli `Rentique` tidak digunakan sebagai sumber data atau runtime oleh aplikasi ini.

## Lokasi kode

| Lokasi | Isi |
| --- | --- |
| `app/storefront.php` | Beranda, koleksi/filter, detail pakaian, tambah ke tas |
| `app/account.php` | Login/profil, tas, checkout, pembayaran, riwayat, invoice |
| `app/admin.php` | Dasbor, inventaris, penyewaan/pengembalian, pelanggan, log stok |
| `app/bootstrap.php` | Helper tampilan, sesi, dan pemanggilan controller |
| `app/layout.php` | Header, navigasi, footer |
| `backend/src/Models/` | Kelas PBO pengguna, pakaian, transaksi, dan pembayaran |
| `backend/src/Storage/JsonStore.php` | Baca/tulis data JSON lokal |
| `public/assets/` | CSS, font lokal, dan JavaScript vanilla untuk interaksi tambahan |
| `public/images/` | Seluruh foto lokal |
| `storage/data.json` | Data aplikasi yang dihasilkan saat berjalan |
| `router.php`, `public/index.php` | Pengarah permintaan dan halaman PHP |

Formulir utama tetap bekerja tanpa JavaScript; JavaScript hanya membantu pengisian akun demo, pilihan pembayaran, dan cetak invoice. Nominal, otorisasi, stok, dan penyimpanan diproses di PHP.

## Aturan transaksi

Satu durasi berlaku untuk semua pakaian dalam tas. Quote tidak memesan stok; stok berkurang saat sewaan dikonfirmasi. Biaya cuci/aksesori dihitung sekali per unit dan harga transaksi disimpan sebagai snapshot. Pembayaran dapat dicicil melalui tunai, bank, atau dompet digital. Pengembalian memulihkan stok sekali saja; denda Rp10.000 per hari kalender keterlambatan, zona waktu Asia/Jakarta.

## Pemeriksaan domain

```sh
php backend/tests/domain.php
php backend/tests/json_store.php
```

[Pemetaan OOP](docs/OOP-MAPPING.md) · [Diagram kelas](docs/class-diagram.png)

