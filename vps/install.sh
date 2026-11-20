#!/usr/bin/env bash
# language: bash, file: vps/install.sh, target: Ubuntu 24/26 x86_64/arm64
# KasirLoka / Teras Kota — Interactive VPS Installer (Multi-Tenant SaaS Ready)
# Usage: sudo bash vps/install.sh
#    or: curl -sSL <raw-url> | sudo bash

set -euo pipefail

# ── Bootstrap when executed via `curl | sudo bash` (no local lib/ dir) ────────
_SELF_SOURCE="${BASH_SOURCE[0]:-}"
if [ -n "$_SELF_SOURCE" ] && [ -f "$_SELF_SOURCE" ]; then
    INSTALLER_DIR="$(cd "$(dirname "$_SELF_SOURCE")" && pwd)"
else
    INSTALLER_DIR=""
fi

if [ -z "$INSTALLER_DIR" ] || [ ! -f "${INSTALLER_DIR}/lib/common.sh" ]; then
    if [ "$(id -u)" -ne 0 ]; then
        echo "[ERR] Script harus dijalankan sebagai root (sudo)." >&2
        exit 1
    fi
    BOOTSTRAP_REPO="${TERASKOTA_BOOTSTRAP_REPO:-https://github.com/dalifajr/teraskota.git}"
    BOOTSTRAP_BRANCH="${TERASKOTA_BOOTSTRAP_BRANCH:-main}"
    BOOTSTRAP_DIR="/tmp/teraskota-installer"

    echo "[INFO] Mode remote pipe terdeteksi. Mengunduh installer ke ${BOOTSTRAP_DIR}..."
    if ! command -v git &>/dev/null; then
        apt-get update -y >/dev/null 2>&1 || true
        DEBIAN_FRONTEND=noninteractive apt-get install -y git >/dev/null 2>&1
    fi
    rm -rf "$BOOTSTRAP_DIR"
    git clone --depth 1 -b "$BOOTSTRAP_BRANCH" "$BOOTSTRAP_REPO" "$BOOTSTRAP_DIR"
    exec bash "${BOOTSTRAP_DIR}/vps/install.sh" "$@" < /dev/tty
fi

LIB_DIR="${INSTALLER_DIR}/lib"
CONFIG_DIR="${INSTALLER_DIR}/config"

# Source libraries (libraries use private _*_LIB_DIR so INSTALLER_DIR is never overwritten)
source "${LIB_DIR}/common.sh"
source "${LIB_DIR}/php-install.sh"
source "${LIB_DIR}/node-install.sh"
source "${LIB_DIR}/ssl-install.sh"

# ── Trap ──────────────────────────────────────────────────────────────────────
trap cleanup_on_error EXIT

# ── Pre-flight ────────────────────────────────────────────────────────────────
require_root
require_ubuntu

# Init log
mkdir -p "$(dirname "${TERASKOTA_LOG}")"
echo "=== KasirLoka / Teras Kota Installation $(date) ===" > "${TERASKOTA_LOG}"

# Staged Cloudflare Origin SSL temp files (if chosen during interactive dialog)
STAGED_CF_CERT=""
STAGED_CF_KEY=""

# ══════════════════════════════════════════════════════════════════════════════
# STEP 1: System Check
# ══════════════════════════════════════════════════════════════════════════════
system_check() {
    log_step "[1/7] System Check"

    local arch codename version ram_mb disk_gb
    arch=$(detect_arch)
    codename=$(detect_os_codename)
    version=$(detect_os_version)
    ram_mb=$(detect_total_ram_mb)
    disk_gb=$(detect_disk_free_gb)

    echo -e "  OS          : Ubuntu ${version} (${codename})"
    echo -e "  Arsitektur  : ${arch}"
    echo -e "  RAM         : ${ram_mb} MB"
    echo -e "  Disk Free   : ${disk_gb} GB"

    save_config "ARCH" "$arch"
    save_config "OS_CODENAME" "$codename"
    save_config "OS_VERSION" "$version"

    if [ "$ram_mb" -lt 512 ]; then
        log_error "RAM terlalu kecil (${ram_mb} MB). Minimal 512MB."
        exit 1
    elif [ "$ram_mb" -lt 1024 ]; then
        log_warn "RAM rendah (${ram_mb} MB). Swap sangat direkomendasikan."
    fi

    if [ "$disk_gb" -lt 5 ]; then
        log_error "Disk terlalu kecil (${disk_gb} GB). Minimal 5GB free."
        exit 1
    fi

    log_ok "System check passed"
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 1.5: Interactive Configuration Dialog
# ══════════════════════════════════════════════════════════════════════════════
collect_config() {
    log_step "Konfigurasi Instalasi"

    echo -e "${C_DIM}Jawab pertanyaan berikut untuk mengkonfigurasi instalasi.${C_RESET}"
    echo -e "${C_DIM}Tekan Enter untuk menggunakan nilai default [dalam kurung].${C_RESET}"
    echo

    # Domain
    INSTALL_DOMAIN=$(prompt_input "Domain" "Domain website (contoh: kasirloka.id atau teh.belajaridn.id)" "")
    while [ -z "$INSTALL_DOMAIN" ]; do
        log_warn "Domain wajib diisi"
        INSTALL_DOMAIN=$(prompt_input "Domain" "Domain website (contoh: kasirloka.id)" "")
    done

    # Platform Name
    INSTALL_PLATFORM_NAME=$(prompt_input "Nama Platform" "Nama brand platform SaaS" "KasirLoka")

    # GitHub repo
    local default_repo="https://github.com/dalifajr/teraskota.git"
    if [ -d "${INSTALLER_DIR}/../.git" ]; then
        local detected_origin
        detected_origin=$(git -C "${INSTALLER_DIR}/.." remote get-url origin 2>/dev/null || echo "")
        if [ -n "$detected_origin" ]; then
            default_repo="$detected_origin"
        fi
    fi
    INSTALL_REPO=$(prompt_input "GitHub Repository" "URL repository GitHub" "$default_repo")

    # GitHub branch
    INSTALL_BRANCH=$(prompt_input "Branch" "Branch yang akan di-deploy" "main")

    # Database password
    echo
    INSTALL_DB_PASS=$(prompt_password "Database" "Password untuk database user 'teraskota'")
    while [ -z "$INSTALL_DB_PASS" ]; do
        log_warn "Password database wajib diisi (keamanan)"
        INSTALL_DB_PASS=$(prompt_password "Database" "Password untuk database user 'teraskota'")
    done

    # Database name
    INSTALL_DB_NAME=$(prompt_input "Database" "Nama database" "db_teras_kota")

    # SSL Mode Selection
    echo
    echo -e "${C_BOLD}━━━ Mode Sertifikat SSL ━━━${C_RESET}"
    echo -e "  ${C_WHITE}1${C_RESET}) Let's Encrypt Otomatis (Certbot — DNS Only / Non-Cloudflare Proxy)"
    echo -e "  ${C_WHITE}2${C_RESET}) Cloudflare Origin Server Certificate (Full Strict — Cocok untuk Orange Cloud)"
    echo -e "  ${C_WHITE}3${C_RESET}) Lewati SSL dulu (HTTP saja, bisa pasang nanti via teraskota-ctl)"
    echo

    local ssl_choice=""
    read -rp "$(echo -e "${C_CYAN}Pilih Mode SSL [1-3]${C_RESET} [1]: ")" ssl_choice
    ssl_choice="${ssl_choice:-1}"

    INSTALL_SSL_MODE="certbot"
    INSTALL_EMAIL=""

    case "$ssl_choice" in
        2)
            INSTALL_SSL_MODE="cloudflare"
            echo
            log_info "Anda memilih Cloudflare Origin Server Certificate."
            echo -e "${C_DIM}Di Dashboard Cloudflare: SSL/TLS → Origin Server → Create Certificate (PEM).${C_RESET}"

            STAGED_CF_CERT=$(mktemp)
            STAGED_CF_KEY=$(mktemp)

            while true; do
                if read_pem_input "Origin Certificate (PEM)" "END CERTIFICATE" "$STAGED_CF_CERT" && \
                   read_pem_input "Private Key (PEM)" "PRIVATE KEY-----" "$STAGED_CF_KEY" && \
                   validate_cloudflare_cert_pair "$STAGED_CF_CERT" "$STAGED_CF_KEY"; then
                    log_ok "Cloudflare Origin Certificate & Private Key valid dan cocok!"
                    break
                else
                    if ! prompt_yesno "SSL Error" "Sertifikat tidak valid / tidak cocok. Coba input ulang?"; then
                        log_warn "Beralih ke mode tanpa SSL (HTTP dulu). Anda bisa pasang nanti lewat: sudo teraskota-ctl ssl-cloudflare"
                        INSTALL_SSL_MODE="none"
                        rm -f "$STAGED_CF_CERT" "$STAGED_CF_KEY"
                        STAGED_CF_CERT=""
                        STAGED_CF_KEY=""
                        break
                    fi
                fi
            done
            ;;
        3)
            INSTALL_SSL_MODE="none"
            ;;
        *)
            INSTALL_SSL_MODE="certbot"
            INSTALL_EMAIL=$(prompt_input "SSL" "Email untuk Let's Encrypt SSL (opsional, kosongkan jika tidak ada)" "")
            ;;
    esac

    # Confirm
    echo
    echo -e "${C_BOLD}━━━ Konfirmasi Konfigurasi ━━━${C_RESET}"
    echo -e "  Domain      : ${C_GREEN}${INSTALL_DOMAIN}${C_RESET}"
    echo -e "  Platform    : ${C_GREEN}${INSTALL_PLATFORM_NAME}${C_RESET}"
    echo -e "  Repository  : ${C_GREEN}${INSTALL_REPO}${C_RESET}"
    echo -e "  Branch      : ${C_GREEN}${INSTALL_BRANCH}${C_RESET}"
    echo -e "  Database    : ${C_GREEN}${INSTALL_DB_NAME}${C_RESET}"
    echo -e "  DB Password : ${C_GREEN}********${C_RESET}"
    echo -e "  Mode SSL    : ${C_GREEN}${INSTALL_SSL_MODE}${C_RESET}"
    if [ "$INSTALL_SSL_MODE" = "certbot" ]; then
        echo -e "  SSL Email   : ${C_GREEN}${INSTALL_EMAIL:-<none>}${C_RESET}"
    fi
    echo

    if ! prompt_yesno "Konfirmasi" "Lanjutkan instalasi dengan konfigurasi di atas?"; then
        log_info "Instalasi dibatalkan oleh user"
        rm -f "${STAGED_CF_CERT:-}" "${STAGED_CF_KEY:-}"
        exit 0
    fi

    # Save to config
    save_config "DOMAIN" "$INSTALL_DOMAIN"
    save_config "PLATFORM_NAME" "$INSTALL_PLATFORM_NAME"
    save_config "REPO_URL" "$INSTALL_REPO"
    save_config "REPO_BRANCH" "$INSTALL_BRANCH"
    save_config "DB_NAME" "$INSTALL_DB_NAME"
    save_config "DB_USER" "teraskota"
    save_config "SSL_MODE" "$INSTALL_SSL_MODE"
    save_config "SSL_EMAIL" "$INSTALL_EMAIL"

    # DB password stored separately with restricted permissions (no trailing newline issues)
    mkdir -p "${TERASKOTA_CONFIG}"
    printf '%s' "$INSTALL_DB_PASS" > "${TERASKOTA_CONFIG}/db_password"
    chmod 600 "${TERASKOTA_CONFIG}/db_password"
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 2: Swap Setup
# ══════════════════════════════════════════════════════════════════════════════
setup_swap() {
    log_step "[2/7] Setup Swap"

    if swapon --show | grep -q '/'; then
        local swap_size
        swap_size=$(free -m | awk '/Swap/ {print $2}')
        log_ok "Swap sudah aktif (${swap_size} MB)"
        return 0
    fi

    local ram_mb
    ram_mb=$(detect_total_ram_mb)

    local swap_mb=$ram_mb
    [ "$swap_mb" -gt 2048 ] && swap_mb=2048

    log_info "Membuat swap ${swap_mb} MB..."

    fallocate -l "${swap_mb}M" /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count="$swap_mb" status=progress
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile

    if ! grep -q '/swapfile' /etc/fstab; then
        echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi

    sysctl vm.swappiness=10
    if ! grep -q 'vm.swappiness' /etc/sysctl.conf; then
        echo 'vm.swappiness=10' >> /etc/sysctl.conf
    fi

    log_ok "Swap ${swap_mb} MB aktif"
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 4: MariaDB
# ══════════════════════════════════════════════════════════════════════════════
ensure_mariadb_user() {
    local db_name="$1"
    local db_user="$2"
    local db_pass="$3"
    local esc_pass
    esc_pass=$(sql_escape "$db_pass")

    mariadb -u root <<-EOSQL
CREATE DATABASE IF NOT EXISTS \`${db_name}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${esc_pass}';
ALTER USER '${db_user}'@'localhost' IDENTIFIED BY '${esc_pass}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';

CREATE USER IF NOT EXISTS '${db_user}'@'127.0.0.1' IDENTIFIED BY '${esc_pass}';
ALTER USER '${db_user}'@'127.0.0.1' IDENTIFIED BY '${esc_pass}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'127.0.0.1';

CREATE USER IF NOT EXISTS '${db_user}'@'::1' IDENTIFIED BY '${esc_pass}';
ALTER USER '${db_user}'@'::1' IDENTIFIED BY '${esc_pass}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'::1';

FLUSH PRIVILEGES;
EOSQL
}

install_mariadb() {
    log_step "[4/7] Install MariaDB"

    if check_command mariadb; then
        log_ok "MariaDB sudah terinstall"
    else
        log_substep "Menginstall MariaDB server..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client 2>&1 | tail -3
    fi

    systemctl enable --now mariadb

    local tuning_src="${CONFIG_DIR}/mysql-tuning.cnf"
    local tuning_dst=""

    if [ -d /etc/mysql/mariadb.conf.d ]; then
        tuning_dst="/etc/mysql/mariadb.conf.d/99-teraskota.cnf"
    elif [ -d /etc/mysql/mysql.conf.d ]; then
        tuning_dst="/etc/mysql/mysql.conf.d/99-teraskota.cnf"
    elif [ -d /etc/mysql/conf.d ]; then
        tuning_dst="/etc/mysql/conf.d/99-teraskota.cnf"
    fi

    if [ -n "$tuning_dst" ] && [ -f "$tuning_src" ]; then
        cp "$tuning_src" "$tuning_dst"
        log_ok "Tuning config applied: ${tuning_dst}"
    fi

    local db_name db_user db_pass
    db_name=$(load_config "DB_NAME" "db_teras_kota")
    db_user=$(load_config "DB_USER" "teraskota")
    db_pass=$(cat "${TERASKOTA_CONFIG}/db_password" 2>/dev/null || echo "")

    log_substep "Membuat database '${db_name}' dan user '${db_user}'..."
    ensure_mariadb_user "$db_name" "$db_user" "$db_pass"

    systemctl restart mariadb
    log_ok "Database '${db_name}' dan user '${db_user}' siap"

    log_substep "Mengamankan MariaDB..."
    mariadb -u root <<-EOSQL2
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOSQL2

    log_ok "MariaDB secured"
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 5: Nginx
# ══════════════════════════════════════════════════════════════════════════════
install_nginx() {
    log_step "[5/7] Install Nginx"

    if check_command nginx; then
        log_ok "Nginx sudah terinstall"
    else
        DEBIAN_FRONTEND=noninteractive apt-get install -y nginx 2>&1 | tail -1
    fi

    systemctl enable --now nginx

    local php_ver domain
    php_ver=$(load_config "PHP_VERSION" "8.4")
    domain=$(load_config "DOMAIN")

    local template="${CONFIG_DIR}/nginx.conf.template"
    local vhost="/etc/nginx/sites-available/teraskota"

    if [ ! -f "$template" ]; then
        log_error "Template nginx tidak ditemukan: ${template}"
        exit 1
    fi

    sed -e "s/{{DOMAIN}}/${domain}/g" \
        -e "s/{{PHP_VERSION}}/${php_ver}/g" \
        "$template" > "$vhost"

    ln -sf "$vhost" /etc/nginx/sites-enabled/teraskota
    rm -f /etc/nginx/sites-enabled/default

    if nginx -t 2>&1; then
        systemctl reload nginx
        log_ok "Nginx dikonfigurasi untuk ${domain}"
    else
        log_error "Nginx config test gagal"
        nginx -t
        exit 1
    fi
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 5.5: Clone & Setup Application
# ══════════════════════════════════════════════════════════════════════════════
setup_application() {
    log_step "Setup Aplikasi KasirLoka / Teras Kota"

    local repo_url branch
    repo_url=$(load_config "REPO_URL")
    branch=$(load_config "REPO_BRANCH" "main")

    if ! check_command git; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y git 2>&1 | tail -1
    fi

    if ! check_command composer; then
        log_substep "Menginstall Composer..."
        local expected_sig actual_sig
        expected_sig=$(curl -fsSL https://composer.github.io/installer.sig)
        curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
        actual_sig=$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")

        if [ "$expected_sig" = "$actual_sig" ]; then
            php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
            log_ok "Composer $(composer --version 2>&1 | head -1)"
        else
            log_warn "Composer signature check warning — mencoba instalasi fallback..."
            php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer 2>/dev/null || {
                log_error "Composer install gagal"
                exit 1
            }
        fi
        rm -f /tmp/composer-setup.php
    fi

    DEBIAN_FRONTEND=noninteractive apt-get install -y unzip 2>&1 | tail -1

    git config --global --add safe.directory "${TERASKOTA_ROOT}" 2>/dev/null || true
    git config --system --add safe.directory "${TERASKOTA_ROOT}" 2>/dev/null || true

    if [ -d "${TERASKOTA_ROOT}/.git" ]; then
        log_info "Repository sudah ada, pulling update..."
        cd "${TERASKOTA_ROOT}"
        git stash 2>/dev/null || true
        git fetch origin
        git checkout "$branch"
        git pull origin "$branch"
    else
        log_info "Cloning repository..."
        rm -rf "${TERASKOTA_ROOT}"
        git clone -b "$branch" "$repo_url" "${TERASKOTA_ROOT}"
    fi

    cd "${TERASKOTA_ROOT}"

    log_substep "Menginstall PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5

    # Configure .env safely via set_env_var (immune to &, |, \, ' in DB passwords)
    log_substep "Mengkonfigurasi .env..."
    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    local domain platform_name db_name db_user db_pass ssl_mode app_scheme
    domain=$(load_config "DOMAIN")
    platform_name=$(load_config "PLATFORM_NAME" "KasirLoka")
    db_name=$(load_config "DB_NAME" "db_teras_kota")
    db_user=$(load_config "DB_USER" "teraskota")
    db_pass=$(cat "${TERASKOTA_CONFIG}/db_password" 2>/dev/null || echo "")
    ssl_mode=$(load_config "SSL_MODE" "certbot")
    app_scheme="https"
    if [ "$ssl_mode" = "none" ]; then
        app_scheme="http"
    fi

    set_env_var .env "APP_NAME" "$platform_name"
    set_env_var .env "PLATFORM_NAME" "$platform_name"
    set_env_var .env "APP_ENV" "production"
    set_env_var .env "APP_DEBUG" "false"
    set_env_var .env "APP_TIMEZONE" "Asia/Jakarta"
    set_env_var .env "APP_LOCALE" "id"
    set_env_var .env "APP_FALLBACK_LOCALE" "en"
    set_env_var .env "APP_FAKER_LOCALE" "id_ID"
    set_env_var .env "APP_URL" "${app_scheme}://${domain}"

    set_env_var .env "DB_CONNECTION" "mysql"
    set_env_var .env "DB_HOST" "127.0.0.1"
    set_env_var .env "DB_PORT" "3306"
    set_env_var .env "DB_DATABASE" "$db_name"
    set_env_var .env "DB_USERNAME" "$db_user"
    set_env_var .env "DB_PASSWORD" "$db_pass"

    set_env_var .env "SESSION_DRIVER" "file"
    set_env_var .env "CACHE_STORE" "file"
    set_env_var .env "QUEUE_CONNECTION" "sync"

    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        php artisan key:generate --force
    fi

    ensure_mariadb_user "$db_name" "$db_user" "$db_pass" 2>/dev/null || true

    local restart_mariadb=0
    for cnf in /etc/mysql/mariadb.conf.d/99-teraskota.cnf /etc/mysql/mysql.conf.d/99-teraskota.cnf /etc/mysql/conf.d/99-teraskota.cnf; do
        if [ -f "$cnf" ] && grep -q "^skip_name_resolve" "$cnf"; then
            sed -i 's/^skip_name_resolve/# skip_name_resolve/' "$cnf"
            restart_mariadb=1
        fi
    done
    if [ "$restart_mariadb" -eq 1 ]; then
        systemctl restart mariadb
    fi

    log_substep "Menjalankan database migrations..."
    php artisan migrate --force

    log_substep "Seeding database default (Super Admin, Tenant #1, Plans)..."
    php artisan db:seed --force

    # Ensure writable public upload & font cache directories exist
    mkdir -p "${TERASKOTA_ROOT}/public/uploads/tenants"
    mkdir -p "${TERASKOTA_ROOT}/storage/fonts"

    php artisan storage:link --force 2>/dev/null || true

    log_substep "Optimizing untuk production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    log_substep "Setting permissions..."
    chown -R www-data:www-data "${TERASKOTA_ROOT}"
    chmod -R 755 "${TERASKOTA_ROOT}"
    chmod -R 775 "${TERASKOTA_ROOT}/storage"
    chmod -R 775 "${TERASKOTA_ROOT}/bootstrap/cache"
    chmod -R 775 "${TERASKOTA_ROOT}/public/uploads"

    log_ok "Aplikasi ${platform_name} siap"
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 7: SSL Configuration (Certbot / Cloudflare Origin / None)
# ══════════════════════════════════════════════════════════════════════════════
setup_ssl() {
    local ssl_mode domain email
    ssl_mode=$(load_config "SSL_MODE" "certbot")
    domain=$(load_config "DOMAIN")
    email=$(load_config "SSL_EMAIL" "")

    case "$ssl_mode" in
        cloudflare)
            if [ -n "$STAGED_CF_CERT" ] && [ -f "$STAGED_CF_CERT" ] && [ -n "$STAGED_CF_KEY" ] && [ -f "$STAGED_CF_KEY" ]; then
                setup_cloudflare_origin_ssl "$domain" "$STAGED_CF_CERT" "$STAGED_CF_KEY" || true
                rm -f "$STAGED_CF_CERT" "$STAGED_CF_KEY"
            else
                setup_cloudflare_origin_ssl "$domain" || true
            fi
            ;;
        certbot)
            install_certbot
            obtain_ssl "$domain" "$email" || true
            ;;
        none|*)
            log_step "[7/7] SSL Dilewati (Mode HTTP)"
            log_info "Anda dapat memasang SSL kapan saja dengan: sudo teraskota-ctl ssl"
            ;;
    esac
}

# ══════════════════════════════════════════════════════════════════════════════
# Post-install: Firewall, Backup Schedule, Control Panel
# ══════════════════════════════════════════════════════════════════════════════
setup_firewall() {
    log_step "Setup Firewall (UFW)"

    if ! check_command ufw; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y ufw 2>&1 | tail -1
    fi

    ufw default deny incoming
    ufw default allow outgoing
    ufw allow 22/tcp   comment 'SSH'
    ufw allow 80/tcp   comment 'HTTP'
    ufw allow 443/tcp  comment 'HTTPS'

    echo "y" | ufw enable

    log_ok "Firewall aktif: SSH(22), HTTP(80), HTTPS(443)"
}

setup_backup_cron() {
    log_step "Setup Auto-Backup"

    mkdir -p "${TERASKOTA_BACKUP_DIR}"

    local db_name db_user
    db_name=$(load_config "DB_NAME" "db_teras_kota")
    db_user=$(load_config "DB_USER" "teraskota")

    # Backup script reads password via MYSQL_PWD (safe for special characters & hidden from `ps`)
    cat > /usr/local/bin/teraskota-backup <<EOFBACKUP
#!/usr/bin/env bash
set -euo pipefail
BACKUP_DIR="${TERASKOTA_BACKUP_DIR}"
TIMESTAMP=\$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="\${BACKUP_DIR}/db_\${TIMESTAMP}.sql.gz"
DB_PASS_FILE="${TERASKOTA_CONFIG}/db_password"

mkdir -p "\${BACKUP_DIR}"
MYSQL_PWD="\$(cat "\${DB_PASS_FILE}")" mysqldump -u "${db_user}" "${db_name}" 2>/dev/null | gzip > "\${BACKUP_FILE}"

# Retain last 7 days only
find "\${BACKUP_DIR}" -name "db_*.sql.gz" -mtime +7 -delete

echo "[\$(date)] Backup: \${BACKUP_FILE}" >> /var/log/teraskota-backup.log
EOFBACKUP

    chmod 700 /usr/local/bin/teraskota-backup

    # Prefer systemd timer; fallback to cron.d ONLY if systemd timer fails (prevents double backup at 02:00!)
    cat > /etc/systemd/system/teraskota-backup.service <<'EOFSVC'
[Unit]
Description=KasirLoka / Teras Kota Daily Database Backup

[Service]
Type=oneshot
ExecStart=/usr/local/bin/teraskota-backup
EOFSVC

    cat > /etc/systemd/system/teraskota-backup.timer <<'EOFTMR'
[Unit]
Description=Daily Database Backup Timer for KasirLoka / Teras Kota

[Timer]
OnCalendar=*-*-* 02:00:00
Persistent=true

[Install]
WantedBy=timers.target
EOFTMR

    systemctl daemon-reload 2>/dev/null || true
    if systemctl enable --now teraskota-backup.timer 2>/dev/null; then
        # Remove legacy cron.d entry so backups never run twice simultaneously
        rm -f /etc/cron.d/teraskota-backup
        log_ok "Auto-backup harian 02:00 aktif via systemd timer (retensi 7 hari)"
    elif [ -d /etc/cron.d ]; then
        cat > /etc/cron.d/teraskota-backup <<'EOFCRON'
# Daily backup at 02:00 AM
0 2 * * * root /usr/local/bin/teraskota-backup >/dev/null 2>&1
EOFCRON
        chmod 644 /etc/cron.d/teraskota-backup
        log_ok "Auto-backup harian 02:00 aktif via /etc/cron.d (retensi 7 hari)"
    fi
}

install_control_panel() {
    log_step "Install Control Panel"

    local ctl_src=""
    for candidate in \
        "${INSTALLER_DIR}/teraskota-ctl" \
        "${TERASKOTA_ROOT}/vps/teraskota-ctl" \
        "/tmp/teraskota-installer/vps/teraskota-ctl"; do
        if [ -f "$candidate" ]; then
            ctl_src="$candidate"
            break
        fi
    done

    if [ -n "$ctl_src" ]; then
        cp "$ctl_src" /usr/local/bin/teraskota-ctl
        chmod +x /usr/local/bin/teraskota-ctl
        cp "$ctl_src" /usr/bin/teraskota-ctl
        chmod +x /usr/bin/teraskota-ctl
        ln -sf /usr/bin/teraskota-ctl /usr/bin/teraskota-cli
        ln -sf /usr/local/bin/teraskota-ctl /usr/local/bin/teraskota-cli
        hash -r 2>/dev/null || true
        log_ok "teraskota-ctl (dan teraskota-cli) terinstall di /usr/bin dan /usr/local/bin"
        echo -e "  ${C_DIM}Gunakan: ${C_CYAN}sudo teraskota-ctl${C_RESET} atau ${C_CYAN}sudo teraskota-cli${C_RESET}"
    else
        log_warn "File teraskota-ctl tidak ditemukan"
    fi
}

# ══════════════════════════════════════════════════════════════════════════════
# Summary
# ══════════════════════════════════════════════════════════════════════════════
print_summary() {
    local domain php_ver ssl_mode site_url ssl_status platform_name
    domain=$(load_config "DOMAIN")
    php_ver=$(load_config "PHP_VERSION" "8.4")
    ssl_mode=$(load_config "SSL_MODE" "certbot")
    platform_name=$(load_config "PLATFORM_NAME" "KasirLoka")

    if [ "$ssl_mode" = "cloudflare" ] && [ -f "${CLOUDFLARE_SSL_DIR}/${domain}.pem" ]; then
        site_url="https://${domain}"
        ssl_status="${C_GREEN}Cloudflare Origin SSL (Full Strict)${C_RESET}"
    elif [ -d "/etc/letsencrypt/live/${domain}" ]; then
        site_url="https://${domain}"
        ssl_status="${C_GREEN}Let's Encrypt Active (Certbot auto-renew)${C_RESET}"
    else
        site_url="http://${domain}"
        ssl_status="${C_YELLOW}Pending (HTTP aktif — pasang via sudo teraskota-ctl ssl)${C_RESET}"
    fi

    echo
    echo -e "${C_BOLD}${C_GREEN}"
    cat << 'EOF'
  ╔════════════════════════════════════════════════════╗
  ║                                                    ║
  ║       INSTALASI BERHASIL ✓                         ║
  ║                                                    ║
  ╚════════════════════════════════════════════════════╝
EOF
    echo -e "${C_RESET}"
    echo -e "  ${C_BOLD}Platform${C_RESET}    : ${C_GREEN}${platform_name}${C_RESET}"
    echo -e "  ${C_BOLD}Website${C_RESET}     : ${C_GREEN}${site_url}${C_RESET}"
    echo -e "  ${C_BOLD}Root Dir${C_RESET}    : ${TERASKOTA_ROOT}"
    echo -e "  ${C_BOLD}PHP${C_RESET}         : ${php_ver}-fpm"
    echo -e "  ${C_BOLD}Database${C_RESET}    : MariaDB → $(load_config 'DB_NAME')"
    echo -e "  ${C_BOLD}Web Server${C_RESET}  : Nginx"
    echo -e "  ${C_BOLD}SSL${C_RESET}         : ${ssl_status}"
    echo -e "  ${C_BOLD}Backup${C_RESET}      : Daily 02:00 → ${TERASKOTA_BACKUP_DIR}"
    echo -e "  ${C_BOLD}Firewall${C_RESET}    : UFW (22, 80, 443)"
    echo
    echo -e "  ${C_BOLD}Login Super Admin Default:${C_RESET}"
    echo -e "    URL       : ${C_CYAN}${site_url}/login${C_RESET}"
    echo -e "    Username  : ${C_YELLOW}superadmin${C_RESET}  |  Password: ${C_YELLOW}superadmin123${C_RESET}"
    echo -e "    ${C_DIM}(Segera ganti kata sandi di menu Profil Akun!)${C_RESET}"
    echo
    echo -e "  ${C_BOLD}Control Panel:${C_RESET}"
    echo -e "    ${C_CYAN}sudo teraskota-ctl${C_RESET}                (menu interaktif)"
    echo -e "    ${C_CYAN}sudo teraskota-ctl ssl-cloudflare${C_RESET} (pasang Cloudflare Origin SSL)"
    echo -e "    ${C_CYAN}sudo teraskota-ctl status${C_RESET}         (cek status & resources)"
    echo
    echo -e "  ${C_BOLD}Log:${C_RESET} ${TERASKOTA_LOG}"
    echo
}

# ══════════════════════════════════════════════════════════════════════════════
# MAIN EXECUTION
# ══════════════════════════════════════════════════════════════════════════════
main() {
    print_banner

    dpkg --configure -a 2>/dev/null || true

    log_info "Membersihkan PPA yang tidak didukung..."
    for f in /etc/apt/sources.list.d/*ondrej* /etc/apt/sources.list.d/*sury* \
             /etc/apt/sources.list.d/*ppa_ondrej*; do
        if [ -f "$f" ]; then
            log_warn "Menghapus: $(basename "$f")"
            rm -f "$f"
        fi
    done

    log_info "Updating package cache..."
    apt-get update -y 2>&1 | tail -3 || true

    if ! check_command whiptail; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y whiptail 2>/dev/null || true
    fi

    system_check
    collect_config
    setup_swap
    install_php
    install_mariadb
    install_nginx
    setup_application
    install_node
    build_assets "${TERASKOTA_ROOT}"

    chown -R www-data:www-data "${TERASKOTA_ROOT}"

    setup_ssl
    setup_firewall
    setup_backup_cron
    install_control_panel

    log_step "Final: Restart Services"
    local php_ver
    php_ver=$(load_config "PHP_VERSION" "8.4")
    systemctl restart "php${php_ver}-fpm"
    systemctl restart mariadb
    systemctl restart nginx
    log_ok "All services restarted"

    print_summary
    trap - EXIT
}

main "$@"
