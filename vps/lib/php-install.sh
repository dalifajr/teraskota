#!/usr/bin/env bash
# language: bash, file: vps/lib/php-install.sh, target: Ubuntu 24/26 x86_64/arm64
# PHP installation with full error recovery (no subshell variable loss)

_PHP_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${_PHP_LIB_DIR}/common.sh"

# Global state set directly by select_php_source() (avoid subshell loss)
TARGET_PHP=""
PHP_SOURCE=""

# ── Required Extensions ──────────────────────────────────────────────────────
# php-dom is bundled inside php-xml on Ubuntu
PHP_EXTENSIONS=(
    fpm mysql mbstring xml curl zip gd bcmath tokenizer fileinfo intl readline
)

# ── Detect available PHP version ─────────────────────────────────────────────
# Scans apt-cache for stable php*-fpm packages supported by Laravel 12 & composer.lock
# Priority: 8.4 (most stable LTS) -> 8.3 -> 8.5 -> 8.2 (excludes 8.6 which breaks composer.lock ^8.2|^8.3|^8.4|^8.5)
detect_available_php() {
    local ver
    for ver in 8.4 8.3 8.5 8.2; do
        if apt-cache show "php${ver}-fpm" &>/dev/null 2>&1; then
            echo "$ver"
            return 0
        fi
    done
    echo ""
}

# ── Strategy Selection ────────────────────────────────────────────────────────
# Sets globals: TARGET_PHP and PHP_SOURCE directly (do NOT call inside $(...))
select_php_source() {
    local arch codename
    arch=$(detect_arch)
    codename=$(detect_os_codename)

    log_info "Arsitektur: ${arch}, Codename: ${codename}"

    TARGET_PHP=$(detect_available_php)

    if [ -n "$TARGET_PHP" ]; then
        log_ok "PHP ${TARGET_PHP} tersedia di repo default Ubuntu"
        PHP_SOURCE="default"
        return 0
    fi

    log_warn "PHP 8.2+ tidak ada di repo default, mencoba Sury APT..."
    PHP_SOURCE="sury"
    return 0
}

# ── Sury APT Setup (Direct, not PPA) ─────────────────────────────────────────
setup_sury_repo() {
    log_substep "Menginstall prerequisite untuk Sury repo..."

    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        software-properties-common gnupg2 ca-certificates \
        lsb-release apt-transport-https curl 2>&1 | tail -1 || true

    log_substep "Import Sury GPG key..."
    local gpg_key="/usr/share/keyrings/sury-php.gpg"
    local max_retries=3
    local attempt=0

    while [ $attempt -lt $max_retries ]; do
        attempt=$((attempt + 1))
        if curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o "$gpg_key" 2>/dev/null; then
            log_ok "GPG key imported (attempt $attempt)"
            break
        fi
        log_warn "GPG key import gagal (attempt $attempt/$max_retries), retry in 5s..."
        sleep 5
    done

    if [ ! -f "$gpg_key" ]; then
        log_error "Gagal import GPG key setelah $max_retries percobaan"
        return 1
    fi

    local codename
    codename=$(detect_os_codename)

    local sury_codename="$codename"
    local known_codenames=("focal" "jammy" "noble" "oracular" "plucky" "resolute")
    local found=false
    for kc in "${known_codenames[@]}"; do
        if [ "$kc" = "$codename" ]; then
            found=true
            break
        fi
    done

    if [ "$found" = false ]; then
        sury_codename="noble"
        log_warn "Codename '${codename}' mungkin belum didukung Sury, fallback ke '${sury_codename}'"
    fi

    echo "deb [signed-by=${gpg_key}] https://packages.sury.org/php/ ${sury_codename} main" \
        > /etc/apt/sources.list.d/sury-php.list

    log_substep "Updating apt cache..."
    apt-get update -y 2>&1 | tail -1 || true

    TARGET_PHP=$(detect_available_php)

    if [ -n "$TARGET_PHP" ]; then
        log_ok "Sury repo berhasil — PHP ${TARGET_PHP} tersedia"
        return 0
    else
        log_error "PHP 8.2+ tetap tidak tersedia setelah menambah Sury repo"
        return 1
    fi
}

# ── Install PHP + Extensions ──────────────────────────────────────────────────
install_php() {
    log_step "[3/7] Detect & Install PHP"

    select_php_source

    case "$PHP_SOURCE" in
        default)
            log_info "Menggunakan repo default Ubuntu (PHP ${TARGET_PHP})"
            ;;
        sury)
            setup_sury_repo || {
                log_error "Semua sumber PHP gagal. Abort."
                exit 1
            }
            ;;
        *)
            log_error "Sumber PHP tidak dikenali: '${PHP_SOURCE}'"
            exit 1
            ;;
    esac

    if [ -z "$TARGET_PHP" ]; then
        log_error "TARGET_PHP kosong — tidak ada versi PHP >= 8.2 yang ditemukan."
        exit 1
    fi

    log_info "PHP version target: ${TARGET_PHP}"

    local packages=("php${TARGET_PHP}-cli" "php${TARGET_PHP}-common")
    for ext in "${PHP_EXTENSIONS[@]}"; do
        packages+=("php${TARGET_PHP}-${ext}")
    done

    log_info "Menginstall ${#packages[@]} packages..."
    log_substep "Packages: ${packages[*]}"

    DEBIAN_FRONTEND=noninteractive apt-get install -y "${packages[@]}" 2>&1 | \
        grep -E "^(Setting up|E:|W:)" || true

    # ── Force TARGET_PHP as system default BEFORE version verification ────
    # Prevents previously installed incompatible versions (e.g. php8.6) from hijacking `php`
    if [ -x "/usr/bin/php${TARGET_PHP}" ] && command -v update-alternatives &>/dev/null; then
        update-alternatives --set php "/usr/bin/php${TARGET_PHP}" 2>/dev/null || true
    fi

    # Stop incompatible php8.6-fpm if previously installed to save RAM
    if [ "$TARGET_PHP" != "8.6" ] && systemctl is-active --quiet php8.6-fpm 2>/dev/null; then
        systemctl disable --now php8.6-fpm 2>/dev/null || true
    fi

    # ── Post-install verification ─────────────────────────────────────────
    local installed_ver
    installed_ver=$(detect_php_version)

    if [ -z "$installed_ver" ]; then
        log_error "PHP tidak terdeteksi setelah instalasi"
        exit 1
    fi

    log_ok "PHP ${installed_ver} terinstall"

    if [ "$installed_ver" != "$TARGET_PHP" ]; then
        log_warn "PHP aktif versi ${installed_ver}, bukan ${TARGET_PHP}"
        if [ -x "/usr/bin/php${TARGET_PHP}" ]; then
            ln -sf "/usr/bin/php${TARGET_PHP}" /usr/bin/php 2>/dev/null || true
            installed_ver=$(detect_php_version)
        fi
        TARGET_PHP="$installed_ver"
    fi

    # ── Verify critical extensions ────────────────────────────────────────
    log_substep "Verifikasi extensions..."
    local missing=()
    local required_modules=("pdo_mysql" "mbstring" "xml" "curl" "gd" "bcmath" "fileinfo" "tokenizer" "intl")

    for mod in "${required_modules[@]}"; do
        if ! php -m 2>/dev/null | grep -qi "^${mod}$"; then
            missing+=("$mod")
        fi
    done

    if [ ${#missing[@]} -gt 0 ]; then
        log_warn "Extension belum terload: ${missing[*]}"
        log_substep "Mencoba install ulang yang kurang..."
        for mod in "${missing[@]}"; do
            local pkg_name="php${TARGET_PHP}-${mod}"
            if [ "$mod" = "pdo_mysql" ]; then pkg_name="php${TARGET_PHP}-mysql"; fi
            if [ "$mod" = "tokenizer" ] || [ "$mod" = "fileinfo" ]; then
                pkg_name="php${TARGET_PHP}-common"
            fi
            DEBIAN_FRONTEND=noninteractive apt-get install -y "$pkg_name" 2>/dev/null || true
        done
    fi

    configure_php_fpm

    log_ok "PHP ${TARGET_PHP} + extensions: ready"
    php -v | head -1
    echo

    save_config "PHP_VERSION" "${TARGET_PHP}"
}

# ── PHP-FPM Pool Configuration (1GB RAM tuning) ──────────────────────────────
configure_php_fpm() {
    log_substep "Konfigurasi PHP-FPM pool untuk VPS..."

    local pool_conf="/etc/php/${TARGET_PHP}/fpm/pool.d/www.conf"

    if [ ! -f "$pool_conf" ]; then
        log_warn "Pool config tidak ditemukan: ${pool_conf}"
        return
    fi

    cp "$pool_conf" "${pool_conf}.bak"

    sed -i 's/^pm = .*/pm = dynamic/' "$pool_conf"
    sed -i 's/^pm\.max_children = .*/pm.max_children = 5/' "$pool_conf"
    sed -i 's/^pm\.start_servers = .*/pm.start_servers = 2/' "$pool_conf"
    sed -i 's/^pm\.min_spare_servers = .*/pm.min_spare_servers = 1/' "$pool_conf"
    sed -i 's/^pm\.max_spare_servers = .*/pm.max_spare_servers = 3/' "$pool_conf"

    if ! grep -q "^pm.max_requests" "$pool_conf"; then
        echo "pm.max_requests = 500" >> "$pool_conf"
    else
        sed -i 's/^pm\.max_requests = .*/pm.max_requests = 500/' "$pool_conf"
    fi

    local php_ini="/etc/php/${TARGET_PHP}/fpm/php.ini"
    if [ -f "$php_ini" ]; then
        sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 10M/' "$php_ini"
        sed -i 's/^post_max_size = .*/post_max_size = 12M/' "$php_ini"
        sed -i 's/^memory_limit = .*/memory_limit = 128M/' "$php_ini"
        sed -i 's/^max_execution_time = .*/max_execution_time = 60/' "$php_ini"
        sed -i 's/^;*cgi.fix_pathinfo=.*/cgi.fix_pathinfo=0/' "$php_ini"
    fi

    log_ok "PHP-FPM pool dikonfigurasi (max_children=5, memory_limit=128M)"
}

if [[ "${BASH_SOURCE[0]:-}" == "${0}" ]]; then
    require_root
    install_php
fi
