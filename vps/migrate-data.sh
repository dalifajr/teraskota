#!/usr/bin/env bash
# language: bash, file: vps/migrate-data.sh, target: Ubuntu 24/26 x86_64/arm64
# Script otomatis untuk migrasi data operasional Teras Kota ke VPS
# Usage: sudo bash vps/migrate-data.sh [/path/to/dump.sql]

set -euo pipefail

TERASKOTA_ROOT="/var/www/teraskota"
DEFAULT_DUMP="${TERASKOTA_ROOT}/if0_42684462_db_teras_kota.sql"
SQL_FILE="${1:-$DEFAULT_DUMP}"

# Colors
C_RED="\033[31m"
C_GREEN="\033[32m"
C_YELLOW="\033[33m"
C_CYAN="\033[36m"
C_BOLD="\033[1m"
C_RESET="\033[0m"

log_info()  { echo -e "${C_CYAN}[INFO]${C_RESET} $*"; }
log_ok()    { echo -e "${C_GREEN}[OK]${C_RESET} $*"; }
log_warn()  { echo -e "${C_YELLOW}[WARN]${C_RESET} $*"; }
log_error() { echo -e "${C_RED}[ERROR]${C_RESET} $*"; }

if [ "$(id -u)" -ne 0 ]; then
    log_error "Script ini harus dijalankan sebagai root atau dengan sudo."
    exit 1
fi

if [ ! -d "${TERASKOTA_ROOT}" ]; then
    log_error "Direktori ${TERASKOTA_ROOT} tidak ditemukan. Pastikan aplikasi sudah terinstal di VPS."
    exit 1
fi

cd "${TERASKOTA_ROOT}"

# Jika file SQL tidak ditemukan di argumen pertama, cek lokasi umum
if [ ! -f "${SQL_FILE}" ]; then
    if [ -f "/tmp/dump_teraskota.sql" ]; then
        SQL_FILE="/tmp/dump_teraskota.sql"
    elif [ -f "${TERASKOTA_ROOT}/database/sql/data_only_teraskota.sql" ]; then
        SQL_FILE="${TERASKOTA_ROOT}/database/sql/data_only_teraskota.sql"
    else
        log_error "File SQL dump tidak ditemukan di: ${SQL_FILE}"
        echo "Silakan upload file dump ke VPS, contoh: scp dump.sql root@vps:/tmp/dump_teraskota.sql"
        exit 1
    fi
fi

log_info "Menggunakan file data: ${C_BOLD}${SQL_FILE}${C_RESET}"

# 1. Pastikan migrasi skema tabel terbaru sudah berjalan
log_info "Memastikan seluruh skema tabel Laravel sudah up-to-date..."
sudo -u www-data php artisan migrate --force

# 2. Jalankan command migrasi data teraskota
log_info "Menjalankan migrasi data bisnis ke database..."
sudo -u www-data php artisan teraskota:migrate-data "${SQL_FILE}" --generate-sql

# 3. Refresh cache aplikasi
log_info "Membersihkan dan memperbarui cache aplikasi..."
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize

# Restart PHP-FPM jika service aktif
PHP_SVC=$(systemctl list-units --type=service --state=running | grep -o 'php[0-9.]*-fpm' | head -1 || echo "")
if [ -n "$PHP_SVC" ]; then
    systemctl restart "$PHP_SVC"
    log_ok "Service ${PHP_SVC} berhasil di-restart."
fi

log_ok "Seluruh proses migrasi data berhasil diselesaikan!"
