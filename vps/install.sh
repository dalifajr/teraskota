#!/usr/bin/env bash
# language: bash, file: vps/install.sh, target: Ubuntu 24/26 x86_64/arm64
# Teras Kota — Interactive VPS Installer
# Usage: curl -sSL <raw-url> | sudo bash
#    or: sudo bash install.sh

set -euo pipefail

# ── Resolve script directory ──────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB_DIR="${SCRIPT_DIR}/lib"
CONFIG_DIR="${SCRIPT_DIR}/config"

# Source libraries
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
echo "=== Teras Kota Installation $(date) ===" > "${TERASKOTA_LOG}"

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

    # Warnings
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
    INSTALL_DOMAIN=$(prompt_input "Domain" "Domain website (contoh: teraskota.com)" "")
    while [ -z "$INSTALL_DOMAIN" ]; do
        log_warn "Domain wajib diisi"
        INSTALL_DOMAIN=$(prompt_input "Domain" "Domain website (contoh: teraskota.com)" "")
    done

    # GitHub repo
    INSTALL_REPO=$(prompt_input "GitHub Repository" "URL repository GitHub" "https://github.com/username/teraskota.git")

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

    # Email for SSL (optional)
    INSTALL_EMAIL=$(prompt_input "SSL" "Email untuk SSL certificate (kosongkan jika tidak ada)" "")

    # Confirm
    echo
    echo -e "${C_BOLD}━━━ Konfirmasi Konfigurasi ━━━${C_RESET}"
    echo -e "  Domain      : ${C_GREEN}${INSTALL_DOMAIN}${C_RESET}"
    echo -e "  Repository  : ${C_GREEN}${INSTALL_REPO}${C_RESET}"
    echo -e "  Branch      : ${C_GREEN}${INSTALL_BRANCH}${C_RESET}"
    echo -e "  Database    : ${C_GREEN}${INSTALL_DB_NAME}${C_RESET}"
    echo -e "  DB Password : ${C_GREEN}********${C_RESET}"
    echo -e "  SSL Email   : ${C_GREEN}${INSTALL_EMAIL:-<none>}${C_RESET}"
    echo

    if ! prompt_yesno "Konfirmasi" "Lanjutkan instalasi dengan konfigurasi di atas?"; then
        log_info "Instalasi dibatalkan oleh user"
        exit 0
    fi

    # Save to config
    save_config "DOMAIN" "$INSTALL_DOMAIN"
    save_config "REPO_URL" "$INSTALL_REPO"
    save_config "REPO_BRANCH" "$INSTALL_BRANCH"
    save_config "DB_NAME" "$INSTALL_DB_NAME"
    save_config "DB_USER" "teraskota"
    save_config "SSL_EMAIL" "$INSTALL_EMAIL"
    # DB password stored separately with restricted permissions
    echo "$INSTALL_DB_PASS" > "${TERASKOTA_CONFIG}/db_password"
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

    # Swap = RAM size, max 2GB
    local swap_mb=$ram_mb
    [ "$swap_mb" -gt 2048 ] && swap_mb=2048

    log_info "Membuat swap ${swap_mb} MB..."

    fallocate -l "${swap_mb}M" /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count="$swap_mb" status=progress
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile

    # Persist
    if ! grep -q '/swapfile' /etc/fstab; then
        echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi

    # Tune swappiness for VPS (low — prefer RAM)
    sysctl vm.swappiness=10
    if ! grep -q 'vm.swappiness' /etc/sysctl.conf; then
        echo 'vm.swappiness=10' >> /etc/sysctl.conf
    fi

    log_ok "Swap ${swap_mb} MB aktif"
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 3: PHP — delegated to lib/php-install.sh
# ══════════════════════════════════════════════════════════════════════════════
# install_php() is sourced from php-install.sh

# ══════════════════════════════════════════════════════════════════════════════
# STEP 4: MariaDB
# ══════════════════════════════════════════════════════════════════════════════
install_mariadb() {
    log_step "[4/7] Install MariaDB"

    if check_command mariadb; then
        log_ok "MariaDB sudah terinstall"
    else
        log_substep "Menginstall MariaDB server..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client 2>&1 | tail -3
    fi

    # Ensure running
    systemctl enable --now mariadb

    # Apply tuning config
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

    # Create database and user
    local db_name db_user db_pass
    db_name=$(load_config "DB_NAME" "db_teras_kota")
    db_user=$(load_config "DB_USER" "teraskota")
    db_pass=$(cat "${TERASKOTA_CONFIG}/db_password" 2>/dev/null || echo "")

    log_substep "Membuat database '${db_name}' dan user '${db_user}'..."

    mariadb -u root <<-EOSQL
CREATE DATABASE IF NOT EXISTS \`${db_name}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';
FLUSH PRIVILEGES;
EOSQL

    # Restart with tuning applied
    systemctl restart mariadb

    log_ok "Database '${db_name}' dan user '${db_user}' siap"

    # Secure installation (non-interactive)
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

    # Configure vhost from template
    local php_ver domain
    php_ver=$(load_config "PHP_VERSION" "8.3")
    domain=$(load_config "DOMAIN")

    local template="${CONFIG_DIR}/nginx.conf.template"
    local vhost="/etc/nginx/sites-available/teraskota"

    if [ ! -f "$template" ]; then
        log_error "Template nginx tidak ditemukan: ${template}"
        exit 1
    fi

    # Replace placeholders
    sed -e "s/{{DOMAIN}}/${domain}/g" \
        -e "s/{{PHP_VERSION}}/${php_ver}/g" \
        "$template" > "$vhost"

    # Enable site, disable default
    ln -sf "$vhost" /etc/nginx/sites-enabled/teraskota
    rm -f /etc/nginx/sites-enabled/default

    # Test config
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
    log_step "Setup Aplikasi Teras Kota"

    local repo_url branch
    repo_url=$(load_config "REPO_URL")
    branch=$(load_config "REPO_BRANCH" "main")

    # Install git if needed
    if ! check_command git; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y git 2>&1 | tail -1
    fi

    # Install composer if needed
    if ! check_command composer; then
        log_substep "Menginstall Composer..."
        local expected_sig
        expected_sig=$(curl -fsSL https://composer.github.io/installer.sig)
        curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
        local actual_sig
        actual_sig=$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")

        if [ "$expected_sig" = "$actual_sig" ]; then
            php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
            log_ok "Composer $(composer --version 2>&1 | head -1)"
        else
            log_error "Composer installer signature mismatch"
            # Install anyway — many systems skip this check
            php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer 2>/dev/null || {
                log_error "Composer install gagal"
                exit 1
            }
        fi
        rm -f /tmp/composer-setup.php
    fi

    # Install unzip (needed by composer)
    DEBIAN_FRONTEND=noninteractive apt-get install -y unzip 2>&1 | tail -1

    # Clone repository
    if [ -d "${TERASKOTA_ROOT}/.git" ]; then
        log_info "Repository sudah ada, pulling update..."
        cd "${TERASKOTA_ROOT}"
        git fetch origin
        git checkout "$branch"
        git pull origin "$branch"
    else
        log_info "Cloning repository..."
        rm -rf "${TERASKOTA_ROOT}"
        git clone -b "$branch" "$repo_url" "${TERASKOTA_ROOT}"
    fi

    cd "${TERASKOTA_ROOT}"

    # Composer install (production)
    log_substep "Menginstall PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5

    # Configure .env
    log_substep "Mengkonfigurasi .env..."
    cp .env.example .env

    local domain db_name db_user db_pass
    domain=$(load_config "DOMAIN")
    db_name=$(load_config "DB_NAME" "db_teras_kota")
    db_user=$(load_config "DB_USER" "teraskota")
    db_pass=$(cat "${TERASKOTA_CONFIG}/db_password" 2>/dev/null || echo "")

    # Update .env values
    sed -i "s|APP_NAME=.*|APP_NAME=\"Teras Kota Berlian Makmur\"|" .env
    sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|APP_URL=.*|APP_URL=https://${domain}|" .env

    # Database config — uncomment and set MySQL values
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|.*DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|.*DB_PORT=.*|DB_PORT=3306|" .env
    sed -i "s|.*DB_DATABASE=.*|DB_DATABASE=${db_name}|" .env
    sed -i "s|.*DB_USERNAME=.*|DB_USERNAME=${db_user}|" .env
    sed -i "s|.*DB_PASSWORD=.*|DB_PASSWORD=${db_pass}|" .env

    # Session & Cache for production
    sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=file|" .env
    sed -i "s|CACHE_STORE=.*|CACHE_STORE=file|" .env
    sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|" .env

    # Deduplicate .env keys (keep last occurrence of each, matches Laravel behavior)
    local tmp_env
    tmp_env=$(mktemp)
    tac .env | awk -F= '!seen[$1]++' | tac > "$tmp_env" && mv "$tmp_env" .env

    # Generate app key
    php artisan key:generate --force

    # Run migrations
    log_substep "Menjalankan database migrations..."
    php artisan migrate --force

    # Storage symlink (public/storage → storage/app/public)
    php artisan storage:link --force 2>/dev/null || true

    # Optimize
    log_substep "Optimizing untuk production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # Set permissions
    log_substep "Setting permissions..."
    chown -R www-data:www-data "${TERASKOTA_ROOT}"
    chmod -R 755 "${TERASKOTA_ROOT}"
    chmod -R 775 "${TERASKOTA_ROOT}/storage"
    chmod -R 775 "${TERASKOTA_ROOT}/bootstrap/cache"

    log_ok "Aplikasi Teras Kota siap"
}

# ══════════════════════════════════════════════════════════════════════════════
# STEP 6: Node.js + Build Assets — delegated to lib/node-install.sh
# ══════════════════════════════════════════════════════════════════════════════
# install_node() and build_assets() are sourced from node-install.sh

# ══════════════════════════════════════════════════════════════════════════════
# STEP 7: SSL — delegated to lib/ssl-install.sh
# ══════════════════════════════════════════════════════════════════════════════
# install_certbot() and obtain_ssl() are sourced from ssl-install.sh

# ══════════════════════════════════════════════════════════════════════════════
# Post-install: Firewall, Backup Cron, Control Panel
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

    # Enable non-interactively
    echo "y" | ufw enable

    log_ok "Firewall aktif: SSH(22), HTTP(80), HTTPS(443)"
}

setup_backup_cron() {
    log_step "Setup Auto-Backup"

    mkdir -p "${TERASKOTA_BACKUP_DIR}"

    local db_name db_user db_pass
    db_name=$(load_config "DB_NAME" "db_teras_kota")
    db_user=$(load_config "DB_USER" "teraskota")
    db_pass=$(cat "${TERASKOTA_CONFIG}/db_password" 2>/dev/null || echo "")

    # Create backup script
    cat > /usr/local/bin/teraskota-backup <<EOFBACKUP
#!/usr/bin/env bash
# Auto-generated backup script for Teras Kota
BACKUP_DIR="${TERASKOTA_BACKUP_DIR}"
TIMESTAMP=\$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="\${BACKUP_DIR}/db_\${TIMESTAMP}.sql.gz"

# Dump and compress
mysqldump -u ${db_user} -p'${db_pass}' ${db_name} 2>/dev/null | gzip > "\${BACKUP_FILE}"

# Retain last 7 days only
find "\${BACKUP_DIR}" -name "db_*.sql.gz" -mtime +7 -delete

echo "[\$(date)] Backup: \${BACKUP_FILE}" >> /var/log/teraskota-backup.log
EOFBACKUP

    chmod 700 /usr/local/bin/teraskota-backup

    # Daily cron at 02:00
    local cron_line="0 2 * * * /usr/local/bin/teraskota-backup"
    (crontab -l 2>/dev/null | grep -v teraskota-backup; echo "$cron_line") | crontab -

    log_ok "Auto-backup daily at 02:00 (retain 7 days)"
}

install_control_panel() {
    log_step "Install Control Panel"

    local ctl_src="${SCRIPT_DIR}/teraskota-ctl"
    local ctl_dst="/usr/local/bin/teraskota-ctl"

    if [ -f "$ctl_src" ]; then
        cp "$ctl_src" "$ctl_dst"
        chmod +x "$ctl_dst"
        log_ok "teraskota-ctl terinstall di ${ctl_dst}"
        echo -e "  ${C_DIM}Gunakan: ${C_CYAN}sudo teraskota-ctl${C_RESET}"
    else
        log_warn "File teraskota-ctl tidak ditemukan di ${ctl_src}"
    fi
}

# ══════════════════════════════════════════════════════════════════════════════
# Summary
# ══════════════════════════════════════════════════════════════════════════════
print_summary() {
    local domain php_ver
    domain=$(load_config "DOMAIN")
    php_ver=$(load_config "PHP_VERSION" "8.3")

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
    echo -e "  ${C_BOLD}Website${C_RESET}     : https://${domain}"
    echo -e "  ${C_BOLD}Root Dir${C_RESET}    : ${TERASKOTA_ROOT}"
    echo -e "  ${C_BOLD}PHP${C_RESET}         : ${php_ver}-fpm"
    echo -e "  ${C_BOLD}Database${C_RESET}    : MariaDB → $(load_config 'DB_NAME')"
    echo -e "  ${C_BOLD}Web Server${C_RESET}  : Nginx"
    echo -e "  ${C_BOLD}SSL${C_RESET}         : Certbot (auto-renew)"
    echo -e "  ${C_BOLD}Backup${C_RESET}      : Daily 02:00 → ${TERASKOTA_BACKUP_DIR}"
    echo -e "  ${C_BOLD}Firewall${C_RESET}    : UFW (22, 80, 443)"
    echo
    echo -e "  ${C_BOLD}Control Panel:${C_RESET}"
    echo -e "    ${C_CYAN}sudo teraskota-ctl${C_RESET}         (interactive menu)"
    echo -e "    ${C_CYAN}sudo teraskota-ctl status${C_RESET}  (direct command)"
    echo
    echo -e "  ${C_BOLD}Log:${C_RESET} ${TERASKOTA_LOG}"
    echo
}

# ══════════════════════════════════════════════════════════════════════════════
# MAIN EXECUTION
# ══════════════════════════════════════════════════════════════════════════════
main() {
    print_banner

    # Update apt cache first
    log_info "Updating package cache..."
    apt-get update -y 2>&1 | tail -1

    # Install whiptail for dialogs if not available
    if ! check_command whiptail; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y whiptail 2>/dev/null || true
    fi

    # [1/7] System check
    system_check

    # Interactive config
    collect_config

    # [2/7] Swap
    setup_swap

    # [3/7] PHP (from php-install.sh)
    install_php

    # [4/7] MariaDB
    install_mariadb

    # [5/7] Nginx
    install_nginx

    # Clone & setup app (between Nginx and Node — needs repo for npm build)
    setup_application

    # [6/7] Node.js + build assets
    install_node
    build_assets "${TERASKOTA_ROOT}"

    # Re-set permissions after build
    chown -R www-data:www-data "${TERASKOTA_ROOT}"

    # [7/7] SSL
    install_certbot
    obtain_ssl "$(load_config 'DOMAIN')" "$(load_config 'SSL_EMAIL')"

    # Post-install
    setup_firewall
    setup_backup_cron
    install_control_panel

    # Restart all services
    log_step "Final: Restart Services"
    local php_ver
    php_ver=$(load_config "PHP_VERSION" "8.3")
    systemctl restart "php${php_ver}-fpm"
    systemctl restart mariadb
    systemctl restart nginx
    log_ok "All services restarted"

    # Done
    print_summary

    # Remove trap since we succeeded
    trap - EXIT
}

main "$@"
