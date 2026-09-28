#!/usr/bin/env bash
# ==============================================================================
# Teras Kota POS - Automated 1-Click VPS Installer & Setup Script
# Architecture : Ubuntu 24.04/26.04 (x86_64 & ARM64/aarch64)
# Specifications: Optimized for 1GB RAM / 1 vCPU / 30GB Storage
# ==============================================================================

set -e

# ANSI Color Codes
C_RESET="\033[0m"
C_BOLD="\033[1m"
C_GREEN="\033[1;32m"
C_RED="\033[1;31m"
C_YELLOW="\033[1;33m"
C_BLUE="\033[1;34m"
C_CYAN="\033[1;36m"
C_WHITE="\033[1;37m"

# Ensure script is executed as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${C_RED}[ERROR] Skrip ini harus dijalankan sebagai root (gunakan sudo bash deploy/install.sh)${C_RESET}"
    exit 1
fi

clear
echo -e "${C_GREEN}${C_BOLD}"
echo "================================================================================"
echo "          TERAS KOTA POS - AUTOMATED VPS INSTALLER (1GB RAM OPTIMIZED)          "
echo "================================================================================"
echo -e "${C_RESET}"

# 1. System & Architecture Detection
ARCH=$(uname -m)
OS_ID=$(grep -E '^ID=' /etc/os-release | cut -d= -f2 | tr -d '"')
OS_VER=$(grep -E '^VERSION_ID=' /etc/os-release | cut -d= -f2 | tr -d '"')

echo -e "Deteksi Sistem Operasi : ${C_CYAN}$OS_ID $OS_VER${C_RESET}"
echo -e "Deteksi Arsitektur CPU : ${C_CYAN}$ARCH${C_RESET}"

if [ "$ARCH" != "x86_64" ] && [ "$ARCH" != "aarch64" ] && [ "$ARCH" != "arm64" ]; then
    echo -e "${C_YELLOW}[WARNING] Arsitektur $ARCH belum teruji penuh. Melanjutkan instalasi...${C_RESET}"
fi

# Detect Public IP
PUBLIC_IP=$(curl -s -m 4 https://ifconfig.me 2>/dev/null || curl -s -m 4 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
echo -e "Deteksi IP Publik VPS  : ${C_CYAN}$PUBLIC_IP${C_RESET}"
echo "--------------------------------------------------------------------------------"
echo ""

# 2. Interactive Dialog for Domain and Configuration
echo -e "${C_BOLD}[LANGKAH 1/6] PENGATURAN DOMAIN DAN WEBSITE${C_RESET}"
echo -e "Silakan tentukan domain yang akan digunakan untuk mengakses terminal kasir POS."
echo -e "Contoh: ${C_WHITE}pos.teraskotaberlian.com${C_RESET} atau gunakan IP publik jika belum memiliki domain."
echo ""
read -rp ">> Masukkan Nama Domain/Subdomain [Default: $PUBLIC_IP]: " DOMAIN_INPUT
DOMAIN="${DOMAIN_INPUT:-$PUBLIC_IP}"
DOMAIN=$(echo "$DOMAIN" | tr -d ' ' | tr '[:upper:]' '[:lower:]')

echo ""
read -rp ">> Masukkan Alamat Email Administrator (untuk SSL Let's Encrypt): " EMAIL_INPUT
ADMIN_EMAIL="${EMAIL_INPUT:-admin@$DOMAIN}"

echo ""
read -rp ">> Apakah domain $DOMAIN sudah diarahkan (DNS A Record) ke $PUBLIC_IP dan ingin langsung pasang SSL HTTPS? (y/n) [n]: " WANT_SSL_INPUT
WANT_SSL="${WANT_SSL_INPUT:-n}"

echo ""
echo -e "${C_BOLD}[LANGKAH 2/6] PENGATURAN DATABASE (MARIADB LOW-RAM)${C_RESET}"
read -rp ">> Nama Database [db_teras_kota]: " DB_NAME_INPUT
DB_NAME="${DB_NAME_INPUT:-db_teras_kota}"

read -rp ">> Username Database [teraskota_user]: " DB_USER_INPUT
DB_USER="${DB_USER_INPUT:-teraskota_user}"

DEFAULT_PASS=$(openssl rand -hex 8)
read -rp ">> Password Database (Enter untuk generate otomatis: $DEFAULT_PASS): " DB_PASS_INPUT
DB_PASS="${DB_PASS_INPUT:-$DEFAULT_PASS}"

echo ""
echo -e "${C_CYAN}Ringkasan Konfigurasi:${C_RESET}"
echo -e "  Domain   : ${C_WHITE}$DOMAIN${C_RESET}"
echo -e "  Email    : ${C_WHITE}$ADMIN_EMAIL${C_RESET}"
echo -e "  SSL Auto : ${C_WHITE}$WANT_SSL${C_RESET}"
echo -e "  Database : ${C_WHITE}$DB_NAME${C_RESET} (User: $DB_USER)"
echo ""
read -rp "Lanjutkan proses instalasi? (Y/n): " CONFIRM_INSTALL
if [[ "$CONFIRM_INSTALL" =~ ^[Nn]$ ]]; then
    echo "Instalasi dibatalkan."
    exit 0
fi

# 3. Swap 2GB Setup & Kernel Memory Tuning (Vital for 1GB RAM)
echo ""
echo -e "${C_BOLD}[LANGKAH 3/6] MENGONFIGURASI SWAP 2GB & MEMORY TUNING...${C_RESET}"
CURRENT_SWAP=$(free -m | awk '/^Swap:/{print $2}')
if [ "$CURRENT_SWAP" -lt 1000 ]; then
    echo -e "Membuat swapfile 2GB di /swapfile..."
    if [ -f /swapfile ]; then
        swapoff /swapfile 2>/dev/null || true
        rm -f /swapfile
    fi
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    if ! grep -q '/swapfile' /etc/fstab; then
        echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi
    echo -e "${C_GREEN}Swap 2GB berhasil diaktifkan.${C_RESET}"
else
    echo -e "${C_GREEN}Swap memory sudah tersedia ($CURRENT_SWAP MB).${C_RESET}"
fi

# Kernel memory tuning
sysctl vm.swappiness=10 >/dev/null 2>&1 || true
sysctl vm.vfs_cache_pressure=50 >/dev/null 2>&1 || true
grep -q "vm.swappiness" /etc/sysctl.conf || echo "vm.swappiness=10" >> /etc/sysctl.conf
grep -q "vm.vfs_cache_pressure" /etc/sysctl.conf || echo "vm.vfs_cache_pressure=50" >> /etc/sysctl.conf

# 4. Install Dependencies
echo ""
echo -e "${C_BOLD}[LANGKAH 4/6] MENGINSTAL PAKET DEPENDENSI SISTEM...${C_RESET}"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y

apt-get install -y --no-install-recommends \
    nginx \
    mariadb-server \
    git \
    curl \
    unzip \
    tar \
    ufw \
    certbot \
    python3-certbot-nginx \
    software-properties-common \
    ca-certificates \
    lsb-release \
    htop \
    jq

# PHP Version Determination (Ubuntu 24.04/26.04 comes with PHP 8.3 native)
PHP_VER="8.3"
if ! apt-cache show "php${PHP_VER}-fpm" &>/dev/null; then
    PHP_VER="8.2"
fi

echo -e "Memasang PHP ${PHP_VER} dan ekstensi Laravel..."
apt-get install -y --no-install-recommends \
    "php${PHP_VER}-fpm" \
    "php${PHP_VER}-cli" \
    "php${PHP_VER}-mysql" \
    "php${PHP_VER}-mbstring" \
    "php${PHP_VER}-xml" \
    "php${PHP_VER}-bcmath" \
    "php${PHP_VER}-curl" \
    "php${PHP_VER}-gd" \
    "php${PHP_VER}-zip" \
    "php${PHP_VER}-intl" \
    "php${PHP_VER}-sqlite3"

# Install Composer v2
if ! command -v composer &>/dev/null; then
    echo "Memasang Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Install Node.js LTS (Multi-arch amd64 & arm64)
if ! command -v node &>/dev/null; then
    echo "Memasang Node.js LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

# Configure MariaDB Low-Memory Tuning
echo "Menerapkan konfigurasi low-memory pada MariaDB..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/mysql/low-ram.cnf" ]; then
    cp "$SCRIPT_DIR/mysql/low-ram.cnf" /etc/mysql/mariadb.conf.d/99-teraskota-low-ram.cnf
fi
systemctl restart mariadb
systemctl enable mariadb

# Create Database and User
echo "Menyiapkan database dan hak akses..."
mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';"
mariadb -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mariadb -e "FLUSH PRIVILEGES;"

# 5. Project Setup & Configuration
echo ""
echo -e "${C_BOLD}[LANGKAH 5/6] MENYIAPKAN KODE APLIKASI TERAS KOTA...${C_RESET}"
TARGET_DIR="/var/www/teraskota"

if [ -f "$SCRIPT_DIR/../artisan" ]; then
    CURRENT_SOURCE="$(cd "$SCRIPT_DIR/.." && pwd)"
    if [ "$CURRENT_SOURCE" != "$TARGET_DIR" ]; then
        echo "Menyalin file proyek dari $CURRENT_SOURCE ke $TARGET_DIR..."
        mkdir -p "$TARGET_DIR"
        rsync -a --exclude='.git' --exclude='node_modules' --exclude='vendor' "$CURRENT_SOURCE/" "$TARGET_DIR/"
    fi
fi

mkdir -p "$TARGET_DIR"
cd "$TARGET_DIR"

# Generate .env configuration
echo "Mengonfigurasi file .env..."
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    fi
fi

sed -i -E "s|^APP_URL=.*|APP_URL=http://$DOMAIN|" .env
sed -i -E "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i -E "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i -E "s|^DB_PORT=.*|DB_PORT=3306|" .env
sed -i -E "s|^DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
sed -i -E "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
sed -i -E "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
sed -i -E "s|^CACHE_STORE=.*|CACHE_STORE=file|" .env
sed -i -E "s|^SESSION_DRIVER=.*|SESSION_DRIVER=file|" .env
sed -i -E "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|" .env

# Set folder permissions
chown -R www-data:www-data "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 755 {} \;
find "$TARGET_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$TARGET_DIR/storage" "$TARGET_DIR/bootstrap/cache"
chmod +x "$TARGET_DIR/artisan"

# Run Composer
echo "Menginstal dependensi Composer..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# Laravel artisan setups
sudo -u www-data php artisan key:generate --force
sudo -u www-data php artisan storage:link --force 2>/dev/null || true
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force 2>/dev/null || true

# Build Vite frontend assets with swap protection
if [ ! -d "public/build" ]; then
    echo "Membangun aset frontend (Vite)..."
    export NODE_OPTIONS="--max-old-space-size=512"
    npm install --omit=dev 2>/dev/null || npm install
    npm run build
fi

sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize

# 6. Configure Nginx and PHP-FPM
echo ""
echo -e "${C_BOLD}[LANGKAH 6/6] MENGONFIGURASI NGINX & TERMINAL CONTROL PANEL...${C_RESET}"

PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"

# Configure Nginx VHost
NGINX_TEMPLATE="$TARGET_DIR/deploy/nginx/teraskota.conf"
NGINX_DEST="/etc/nginx/sites-available/teraskota.conf"

if [ -f "$NGINX_TEMPLATE" ]; then
    cp "$NGINX_TEMPLATE" "$NGINX_DEST"
    sed -i -E "s|\{\{DOMAIN\}\}|$DOMAIN|g" "$NGINX_DEST"
    sed -i -E "s|\{\{ROOT_PATH\}\}|$TARGET_DIR|g" "$NGINX_DEST"
    sed -i -E "s|\{\{PHP_FPM_SOCK\}\}|unix:$PHP_SOCK|g" "$NGINX_DEST"

    ln -sf "$NGINX_DEST" /etc/nginx/sites-enabled/teraskota.conf
    rm -f /etc/nginx/sites-enabled/default

    nginx -t && systemctl reload nginx
fi

# Configure PHP-FPM Pool
FPM_CONF="$TARGET_DIR/deploy/php/teraskota-fpm.conf"
if [ -f "$FPM_CONF" ]; then
    cp "$FPM_CONF" "/etc/php/${PHP_VER}/fpm/pool.d/teraskota.conf"
    systemctl restart "php${PHP_VER}-fpm"
fi

# Configure Firewall
echo "Mengonfigurasi Firewall (UFW)..."
ufw allow 'OpenSSH' >/dev/null 2>&1 || true
ufw allow 80/tcp >/dev/null 2>&1 || true
ufw allow 443/tcp >/dev/null 2>&1 || true
echo "y" | ufw enable >/dev/null 2>&1 || true

# Install SSL if requested
if [[ "$WANT_SSL" =~ ^[Yy]$ ]] && [ "$DOMAIN" != "$PUBLIC_IP" ]; then
    echo "Memasang sertifikat SSL Let's Encrypt untuk $DOMAIN..."
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect || true
    sed -i -E "s|^APP_URL=http://|APP_URL=https://|" "$TARGET_DIR/.env"
    sudo -u www-data php "$TARGET_DIR/artisan" config:clear >/dev/null 2>&1 || true
    systemctl reload nginx
fi

# Register Global Terminal Control Panel Command
chmod +x "$TARGET_DIR/deploy/teraskota"
ln -sf "$TARGET_DIR/deploy/teraskota" /usr/local/bin/teraskota

echo ""
echo -e "${C_GREEN}${C_BOLD}================================================================================"
echo "                   INSTALASI TERAS KOTA POS BERHASIL!                           "
echo "================================================================================${C_RESET}"
echo -e "Website URL      : ${C_CYAN}http://$DOMAIN${C_RESET} (atau https://$DOMAIN jika SSL aktif)"
echo -e "Direktori Web    : ${C_WHITE}$TARGET_DIR${C_RESET}"
echo -e "Database Name    : ${C_WHITE}$DB_NAME${C_RESET}"
echo -e "Database User    : ${C_WHITE}$DB_USER${C_RESET}"
echo -e "Database Pass    : ${C_WHITE}$DB_PASS${C_RESET}"
echo ""
echo -e "${C_YELLOW}${C_BOLD}KONTROL PANEL TERMINAL:${C_RESET}"
echo -e "Untuk mengelola website, restart service, ubah domain, backup, dan update git,"
echo -e "cukup ketik perintah berikut di terminal SSH kapan saja:"
echo ""
echo -e "    ${C_GREEN}${C_BOLD}teraskota${C_RESET}"
echo ""
echo "================================================================================"
