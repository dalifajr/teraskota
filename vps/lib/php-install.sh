#!/usr/bin/env bash
# language: bash, file: vps/lib/php-install.sh, target: Ubuntu 24/26 x86_64/arm64
# PHP installation with full error recovery for all three failure modes

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Target PHP version — auto-detected below. Laravel 12 requires ^8.2
TARGET_PHP=""

# ── Required Extensions ──────────────────────────────────────────────────────
# php-dom is bundled inside php-xml on Ubuntu
PHP_EXTENSIONS=(
    fpm mysql mbstring xml curl zip gd bcmath tokenizer fileinfo intl readline
)

# ── Detect available PHP version ─────────────────────────────────────────────
# Scans apt-cache for php*-fpm packages, picks the highest version >= 8.2
detect_available_php() {
    local ver
    # Search for available php-fpm packages in repo
    for ver in 8.6 8.5 8.4 8.3 8.2; do
        if apt-cache show "php${ver}-fpm" &>/dev/null 2>&1; then
            echo "$ver"
            return 0
        fi
    done
    echo ""
}

# ── Strategy Selection ────────────────────────────────────────────────────────
# Priority:
#   1. Ubuntu default repo — works on x86 AND arm64
#   2. Sury APT direct (not PPA) — works on x86 AND arm64
select_php_source() {
    local arch codename
    arch=$(detect_arch)
    codename=$(detect_os_codename)

    log_info "Arsitektur: ${arch}, Codename: ${codename}"

    # Auto-detect available PHP version from default repos
    TARGET_PHP=$(detect_available_php)

    if [ -n "$TARGET_PHP" ]; then
        log_ok "PHP ${TARGET_PHP} tersedia di repo default Ubuntu"
        echo "default"
        return 0
    fi

    # Nothing in default repo — try Sury
    log_warn "PHP 8.2+ tidak ada di repo default, mencoba Sury APT..."
    echo "sury"
}

# ── Sury APT Setup (Direct, not PPA) ─────────────────────────────────────────
setup_sury_repo() {
    log_substep "Menginstall prerequisite untuk Sury repo..."

    # Pre-install dependencies FIRST — this is where most failures happen
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        software-properties-common gnupg2 ca-certificates \
        lsb-release apt-transport-https curl 2>&1 | tail -1

    # Method 1: Import GPG key manually (avoids network-dependent add-apt-repository)
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

    # Add repo directly (not via add-apt-repository which often fails)
    local codename
    codename=$(detect_os_codename)

    # Sury may not support newest Ubuntu codenames yet — fallback to latest known
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
        # Fallback to latest known codename
        sury_codename="noble"
        log_warn "Codename '${codename}' mungkin belum didukung Sury, fallback ke '${sury_codename}'"
    fi

    echo "deb [signed-by=${gpg_key}] https://packages.sury.org/php/ ${sury_codename} main" \
        > /etc/apt/sources.list.d/sury-php.list

    log_substep "Updating apt cache..."
    apt-get update -y 2>&1 | tail -1

    # Re-detect PHP version after adding Sury repo
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

    local source
    source=$(select_php_source)

    log_info "PHP version target: ${TARGET_PHP}"

    case "$source" in
        default)
            log_info "Menggunakan repo default Ubuntu"
            ;;
        sury)
            setup_sury_repo || {
                log_error "Semua sumber PHP gagal. Abort."
                exit 1
            }
            ;;
    esac

    # Build package list with detected version
    local packages=("php${TARGET_PHP}-cli" "php${TARGET_PHP}-common")
    for ext in "${PHP_EXTENSIONS[@]}"; do
        packages+=("php${TARGET_PHP}-${ext}")
    done

    log_info "Menginstall ${#packages[@]} packages..."
    log_substep "Packages: ${packages[*]}"

    # Install with automatic yes, noninteractive to prevent dpkg prompts
    DEBIAN_FRONTEND=noninteractive apt-get install -y "${packages[@]}" 2>&1 | \
        grep -E "^(Setting up|E:|W:)" || true

    # ── Post-install verification ─────────────────────────────────────────
    local installed_ver
    installed_ver=$(detect_php_version)

    if [ -z "$installed_ver" ]; then
        log_error "PHP tidak terdeteksi setelah instalasi"
        exit 1
    fi

    log_ok "PHP ${installed_ver} terinstall"

    # ── Fix multiple version conflicts ────────────────────────────────────
    # Ensure correct version is default
    if [ "$installed_ver" != "$TARGET_PHP" ]; then
        log_warn "PHP terinstall versi ${installed_ver}, bukan ${TARGET_PHP}"
        log_warn "Menyesuaikan — Laravel 12 butuh ≥8.2, versi ${installed_ver} tetap kompatibel"
        TARGET_PHP="$installed_ver"
    fi

    # Set as default via update-alternatives
    if command -v update-alternatives &>/dev/null; then
        update-alternatives --set php "/usr/bin/php${TARGET_PHP}" 2>/dev/null || true
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
            # pdo_mysql is in php-mysql
            if [ "$mod" = "pdo_mysql" ]; then pkg_name="php${TARGET_PHP}-mysql"; fi
            # tokenizer/fileinfo are in php-common
            if [ "$mod" = "tokenizer" ] || [ "$mod" = "fileinfo" ]; then
                pkg_name="php${TARGET_PHP}-common"
            fi
            DEBIAN_FRONTEND=noninteractive apt-get install -y "$pkg_name" 2>/dev/null || true
        done
    fi

    # ── Configure PHP-FPM ─────────────────────────────────────────────────
    configure_php_fpm

    # Final check
    log_ok "PHP ${TARGET_PHP} + extensions: ready"
    php -v | head -1
    echo

    # Export for other scripts
    save_config "PHP_VERSION" "${TARGET_PHP}"
}

# ── PHP-FPM Pool Configuration (1GB RAM tuning) ──────────────────────────────
configure_php_fpm() {
    log_substep "Konfigurasi PHP-FPM pool untuk 1GB RAM..."

    local pool_conf="/etc/php/${TARGET_PHP}/fpm/pool.d/www.conf"

    if [ ! -f "$pool_conf" ]; then
        log_warn "Pool config tidak ditemukan: ${pool_conf}"
        return
    fi

    # Backup original
    cp "$pool_conf" "${pool_conf}.bak"

    # Apply memory-optimized settings
    sed -i 's/^pm = .*/pm = dynamic/' "$pool_conf"
    sed -i 's/^pm\.max_children = .*/pm.max_children = 5/' "$pool_conf"
    sed -i 's/^pm\.start_servers = .*/pm.start_servers = 2/' "$pool_conf"
    sed -i 's/^pm\.min_spare_servers = .*/pm.min_spare_servers = 1/' "$pool_conf"
    sed -i 's/^pm\.max_spare_servers = .*/pm.max_spare_servers = 3/' "$pool_conf"

    # Add max_requests if not present
    if ! grep -q "^pm.max_requests" "$pool_conf"; then
        echo "pm.max_requests = 500" >> "$pool_conf"
    else
        sed -i 's/^pm\.max_requests = .*/pm.max_requests = 500/' "$pool_conf"
    fi

    # PHP ini tuning
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

# Run if called directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    require_root
    install_php
fi
