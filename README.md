# Dashboard Penjualan Teras Kota

Sistem Dashboard Penjualan dan POS Kasir Teras Kota dibangun menggunakan framework Laravel, PHP, MySQL, Blade, Bootstrap 5, dan Chart.js.

## Fitur Utama

- **POS Kasir Sederhana**: Pencatatan transaksi multi-menu yang interaktif dengan perhitungan otomatis (omzet, keuntungan, modal) secara real-time via JavaScript.
- **Perhitungan Server-Side Aman**: Verifikasi ulang harga dan persentase keuntungan dilakukan pada server menggunakan transaksi basis data atomik.
- **Snapshot Transaksi**: Menyalin nama menu, nama kategori, harga, dan profit margin saat checkout agar data transaksi lama tidak berubah meskipun menu diperbarui.
- **Dashboard Ringkas & Interaktif**: Kartu informasi omzet, laba bersih, modal, jumlah transaksi, rata-rata, grafik tren penjualan mingguan/bulanan (Chart.js), dan ranking menu terpopuler vs sepi pembeli.
- **Pengaturan Margin Keuntungan**: Mendukung persentase keuntungan global (default 30% disimpan di tabel settings) dan persentase keuntungan khusus per produk.
- **Laporan Penjualan**: Filter rentang waktu terperinci, cetak PDF (DomPDF), dan unduh file laporan CSV/Excel yang kompatibel dengan Microsoft Excel.
- **Profil Admin**: Pengelolaan profil administrator, perubahan username, email, dan password.

---

## Petunjuk Instalasi Mandiri

### 1. Kloning / Unduh Repositori
Pastikan direktori berada dalam path server lokal Anda (misalnya di folder workspace `e:\teraskota`).

### 2. Instal Dependensi PHP
Jalankan composer untuk mengunduh modul:
```bash
composer install
```

### 3. Konfigurasi Environment File
Salin file `.env.example` menjadi `.env`:
```bash
copy .env.example .env
```
Lalu buat application key baru:
```bash
php artisan key:generate
```

### 4. Konfigurasi Database
Sesuaikan parameter database di file `.env` (konfigurasi di bawah ini telah disiapkan untuk XAMPP default):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_teras_kota
DB_USERNAME=root
DB_PASSWORD=
```
*Catatan: Jika database belum dibuat, Anda dapat membuatnya secara manual di phpMyAdmin atau terminal dengan nama `db_teras_kota`.*

### 5. Migrasi dan Seed Data Awal
Jalankan migrasi tabel beserta seeder untuk memuat data admin awal, kategori default, dan daftar harga menu minuman:
```bash
php artisan migrate:fresh --seed
```

### 6. Jalankan Server Lokal
Nyalakan server pengembangan Laravel:
```bash
php artisan serve
```

Buka peramban (browser) dan akses alamat berikut:
```text
http://127.0.0.1:8000
```

---

## Akun Admin Awal

Untuk masuk ke dalam dashboard penjualan, gunakan akun berikut:
- **Username**: `admin` *(atau Email: `admin@teraskota.local`)*
- **Password**: `admin123`

---

## Menjalankan Pengujian (Testing)

Aplikasi ini dilengkapi dengan pengujian fitur (Feature Tests) untuk memastikan akurasi perhitungan margin laba 30%, snapshot transaksi, login, filter laporan, dan lainnya.
Untuk menjalankan unit test:
```bash
php artisan test
```
