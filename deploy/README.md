# Panduan Deployment & Kontrol Panel VPS - Teras Kota POS

Panduan resmi untuk menyebarkan (deploy) aplikasi **Teras Kota POS** pada server VPS dengan spesifikasi rendah (**RAM 1 GB, 1 vCPU, Storage 30 GB**) pada sistem operasi **Ubuntu 24.04 / 26.04 LTS**, mendukung arsitektur **Intel/AMD (x86_64)** dan **ARM (aarch64 / ARM64)**.

---

## 1. Spesifikasi Server & Dukungan Arsitektur

| Spesifikasi | Nilai Minimum / Rekomendasi | Keterangan |
|---|---|---|
| **CPU** | 1 vCPU | Mendukung x86_64 (Intel/AMD) & aarch64 (ARM64) |
| **RAM** | 1 GB | Dilengkapi auto-swap 2 GB + kernel tuning |
| **Penyimpanan** | 30 GB SSD/NVMe | Cukup untuk OS, database, dan backup berkala |
| **Sistem Operasi** | Ubuntu 24.04 LTS / 26.04 LTS | Standar industri cloud VPS |
| **Web Server** | Nginx | Ringan, non-blocking, hemat RAM |
| **Database** | MariaDB 10.11+ / MySQL | Dikonfigurasi *low-memory tuning* (~120 MB RAM) |
| **PHP** | PHP 8.3 / 8.2 FPM | Mode `ondemand` (bebas memory leak) |

---

## 2. Cara Instalasi di VPS Baru

### Langkah 1: Hubungkan ke VPS via SSH
Buka terminal pada komputer Anda lalu login sebagai root:
```bash
ssh root@IP_VPS_ANDA
```

### Langkah 2: Unduh Repositori Proyek
Clone kode proyek Teras Kota ke server:
```bash
git clone https://github.com/USERNAME_ANDA/teraskota.git /var/www/teraskota
cd /var/www/teraskota
```
*(Atau upload folder proyek melalui SFTP/Rsync ke `/var/www/teraskota`)*

### Langkah 3: Jalankan Skrip Instalasi Otomatis
Jalankan skrip instalasi dengan hak akses root:
```bash
sudo bash deploy/install.sh
```

### Langkah 4: Ikuti Dialog Interaktif
Skrip akan memandu Anda melalui dialog terminal interaktif:
1. **Nama Domain**: Masukkan domain atau subdomain Anda (contoh: `pos.teraskotaberlian.com`), atau tekan Enter untuk menggunakan IP publik sementara.
2. **Email Admin**: Masukkan alamat email untuk sertifikat SSL Let's Encrypt.
3. **Pemasangan SSL Otomatis**: Jika domain Anda sudah diarahkan (DNS A record) ke IP VPS, pilih `y` untuk langsung mengamankan koneksi dengan HTTPS.
4. **Nama & Kredensial Database**: Disediakan nilai default dan kata sandi acak yang aman secara otomatis.

Skrip akan secara otomatis:
- Menyiapkan Swap 2 GB dan tuning kernel Linux.
- Menginstal Nginx, PHP 8.3 + ekstensi, MariaDB, Composer, Node.js, dan Certbot.
- Mengonfigurasi permission, database, migrasi tabel, dan optimasi cache.
- Mendaftarkan perintah global `teraskota` pada terminal.

---

## 3. Kontrol Panel Terminal (`teraskota`)

Setelah instalasi selesai, Anda dapat mengelola website kapan saja hanya dengan mengetik perintah berikut di terminal SSH:

```bash
teraskota
```

### Tampilan Antarmuka Terminal (TUI)
```text
================================================================================
  ████████╗███████╗██████╗  █████╗ ███████╗██╗  ██╗ ██████╗ ████████╗ █████╗ 
  ╚══██╔══╝██╔════╝██╔══██╗██╔══██╗██╔════╝██║ ██╔╝██╔═══██╗╚══██╔══╝██╔══██╗
     ██║   █████╗  ██████╔╝███████║███████╗█████╔╝ ██║   ██║   ██║   ███████║
     ██║   ██╔══╝  ██╔══██╗██╔══██║╚════██║██╔═██╗ ██║   ██║   ██║   ██╔══██║
     ██║   ███████╗██║  ██║██║  ██║███████║██║  ██╗╚██████╔╝   ██║   ██║  ██║
     ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝ ╚═════╝    ╚═╝   ╚═╝  ╚═╝

       Terminal Control Panel & Management CLI (1GB RAM Optimized)
       Path: /var/www/teraskota | Arch: x86_64
================================================================================
SISTEM & SPESIFIKASI:
  Host / OS       : vps-teraskota (Ubuntu 24.04 - x86_64)
  Uptime / Load   : up 3 days | Load: 0.12, 0.08, 0.02
  RAM Usage       : 380 MB / 980 MB (38%)
  SWAP Usage      : 84 MB / 2048 MB (4%)
  Penyimpanan     : 6.4G / 29.5G (22%)

STATUS LAYANAN SERVER:
  Web Server      : Nginx [● ACTIVE]
  PHP FastCGI     : php8.3-fpm [● ACTIVE]
  Database        : mariadb [● ACTIVE]

APLIKASI & DOMAIN:
  Alamat URL/Domain: https://pos.teraskotaberlian.com
  Sertifikat SSL  : Aktif (Let's Encrypt)
  Status Website  : Online (Normal)
  Git Version     : branch 'main' (c84f29a)
================================================================================
MENU MANAJEMEN TERAS KOTA:
  1. Informasi & Status Lengkap Sistem (System Health)
  2. Kelola Layanan (Start / Stop / Restart Nginx, PHP, DB)
  3. Ubah Domain Website (Change Domain Nginx + .env)
  4. Pasang / Perbaiki Sertifikat SSL (Let's Encrypt)
  5. Update Aplikasi via GitHub (Pull + Migrate + Optimize)
  6. Mode Pemeliharaan (Maintenance Mode ON / OFF)
  7. Bersihkan Cache & Optimasi Laravel (Clear / Cache)
  8. Cadangkan Database (Backup Dump SQL)
  9. Pulihkan Database (Restore from Backup)
 10. Tuning Memori Rendah (1GB RAM Optimization)
  0. Keluar
================================================================================
```

### Perintah Cepat (Headless Mode)
Selain melalui menu interaktif, Anda juga dapat menjalankan tugas administratif secara langsung:

- Cek status sistem:
  ```bash
  teraskota status
  ```
- Restart semua layanan:
  ```bash
  teraskota restart
  ```
- Restart layanan spesifik:
  ```bash
  teraskota restart nginx
  teraskota restart php
  teraskota restart db
  ```
- Update aplikasi via GitHub:
  ```bash
  teraskota update
  ```
- Cadangkan database secara instan:
  ```bash
  teraskota backup
  ```
- Bersihkan & segarkan cache:
  ```bash
  teraskota cache
  ```

---

## 4. Konfigurasi Khusus VPS 1 GB RAM

Paket deployment ini telah disesuaikan agar server 1 GB RAM berjalan stabil 24/7 tanpa risiko crash:

1. **Swap Memory 2 GB**: Dibuat secara otomatis di `/swapfile` untuk menampung lonjakan memori sementara saat migrasi atau instalasi package.
2. **PHP-FPM Ondemand**: Proses PHP hanya aktif saat ada request masuk dan langsung dilepaskan setelah selesai (`pm = ondemand`, `max_children = 5`).
3. **Database Low-Memory**: Menonaktifkan `performance_schema` (menghemat ~300 MB RAM) dan membatasi `innodb_buffer_pool_size = 128M`.
4. **PWA Static Caching**: Nginx meng-cache aset frontend secara agresif di sisi browser kasir sehingga server menerima beban request minimal.
