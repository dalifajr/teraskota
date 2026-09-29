#!/usr/bin/env bash
# language: bash, file: vps/lib/node-install.sh, target: Ubuntu 24/26 x86_64/arm64
# Node.js installation via NodeSource (supports both architectures)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

NODE_MAJOR=20  # LTS

install_node() {
    log_step "[6/7] Install Node.js ${NODE_MAJOR} LTS"

    # If assets are already pre-built in repository, skip Node.js & npm completely
    if [ -f "${TERASKOTA_ROOT}/public/build/manifest.json" ]; then
        log_ok "Assets pre-built terdeteksi (public/build/manifest.json)"
        log_ok "Skip instalasi Node.js & npm (menghemat ~15 menit & RAM)"
        return 0
    fi

    # Skip if already installed with correct version AND npm is available
    if check_command node; then
        local current_major
        current_major=$(node -v 2>/dev/null | sed 's/v//' | cut -d. -f1)
        if [ "$current_major" -ge "$NODE_MAJOR" ] 2>/dev/null; then
            log_ok "Node.js $(node -v) sudah terinstall"

            # Check if npm is also installed
            if ! check_command npm; then
                log_substep "Menginstall npm..."
                DEBIAN_FRONTEND=noninteractive apt-get install -y npm 2>&1 | tail -2
            fi

            if check_command npm; then
                log_ok "npm $(npm -v) terinstall"
                return 0
            fi
            log_warn "npm tidak ditemukan, melanjutkan setup repository Node.js..."
        else
            log_warn "Node.js $(node -v) terlalu lama, upgrade ke v${NODE_MAJOR}..."
        fi
    fi

    # Install prerequisites
    DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg 2>&1 | tail -1

    # NodeSource setup — works on both x86 and arm64
    log_substep "Menambah NodeSource repository..."
    local keyring="/usr/share/keyrings/nodesource.gpg"
    local max_retries=3
    local attempt=0

    while [ $attempt -lt $max_retries ]; do
        attempt=$((attempt + 1))
        if curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | \
            gpg --dearmor -o "$keyring" 2>/dev/null; then
            break
        fi
        log_warn "NodeSource GPG key gagal (attempt $attempt/$max_retries)..."
        sleep 3
    done

    if [ ! -f "$keyring" ]; then
        log_error "Gagal import NodeSource GPG key"
        # Fallback: try apt directly (Ubuntu may have nodejs in universe)
        log_warn "Fallback: install nodejs dari repo Ubuntu..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs npm 2>&1 | tail -1
        if check_command node && check_command npm; then
            log_ok "Node.js $(node -v) dan npm $(npm -v) terinstall dari repo Ubuntu"
            return 0
        fi
        log_error "Semua metode instalasi Node.js gagal"
        exit 1
    fi

    echo "deb [signed-by=${keyring}] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list

    apt-get update -y 2>&1 | tail -1
    DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs npm 2>&1 | tail -1

    if ! check_command npm; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y npm 2>&1 | tail -1
    fi

    if check_command node && check_command npm; then
        log_ok "Node.js $(node -v) terinstall"
        log_ok "npm $(npm -v) terinstall"
    else
        log_error "Node.js atau npm gagal terinstall"
        exit 1
    fi
}

# Build Vite assets — runs in the app directory
build_assets() {
    local app_dir="${1:-$TERASKOTA_ROOT}"

    # If assets are already pre-built, skip compile
    if [ -f "${app_dir}/public/build/manifest.json" ]; then
        log_ok "Assets pre-built sudah tersedia (public/build) — skip compile"
        return 0
    fi

    # Ensure npm exists
    if ! check_command npm; then
        log_substep "Menginstall npm..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y npm 2>&1 | tail -2
    fi

    log_substep "Install npm dependencies (production build)..."
    cd "$app_dir"

    # Install all deps (need devDeps for build)
    npm ci 2>&1 || npm install 2>&1 | tail -5

    log_substep "Building Vite + Tailwind CSS v4..."
    npm run build 2>&1 | tail -5

    if [ -d "${app_dir}/public/build" ]; then
        log_ok "Assets built successfully"

        # Clean up node_modules to save ~150MB
        log_substep "Membersihkan node_modules (hemat ~150MB)..."
        rm -rf "${app_dir}/node_modules"
        log_ok "node_modules dihapus"
    else
        log_error "Build gagal — public/build tidak ada"
        exit 1
    fi
}

# Run if called directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    require_root
    install_node
fi
