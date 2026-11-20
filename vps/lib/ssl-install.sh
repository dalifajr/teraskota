#!/usr/bin/env bash
# language: bash, file: vps/lib/ssl-install.sh, target: Ubuntu 24/26 x86_64/arm64
# SSL management: Let's Encrypt (Certbot) AND Cloudflare Origin Server Certificate

_SSL_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${_SSL_LIB_DIR}/common.sh"

install_certbot() {
    log_step "[7/7] SSL Certificate via Certbot"

    if check_command certbot; then
        log_ok "Certbot sudah terinstall: $(certbot --version 2>&1)"
    else
        log_substep "Menginstall Certbot + plugin Nginx..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y certbot python3-certbot-nginx 2>&1 | tail -1
        if ! check_command certbot; then
            log_warn "apt install gagal, coba via snap..."
            snap install --classic certbot 2>/dev/null || true
            ln -sf /snap/bin/certbot /usr/bin/certbot 2>/dev/null || true
        fi
    fi

    if ! check_command certbot; then
        log_error "Certbot gagal terinstall"
        exit 1
    fi

    log_ok "Certbot ready"
}

obtain_ssl() {
    local domain="${1:?Domain required}"
    local email="${2:-}"

    log_substep "Mendapatkan SSL certificate Let's Encrypt untuk ${domain}..."

    local certbot_cmd=(certbot --nginx -d "$domain" --non-interactive --agree-tos)

    if [ -n "$email" ]; then
        certbot_cmd+=(--email "$email")
    else
        certbot_cmd+=(--register-unsafely-without-email)
    fi

    if "${certbot_cmd[@]}" 2>&1; then
        log_ok "SSL certificate Let's Encrypt berhasil didapatkan untuk ${domain}"
        save_config "SSL_MODE" "certbot"

        if systemctl is-enabled certbot.timer &>/dev/null; then
            log_ok "Auto-renewal sudah aktif (certbot.timer)"
        else
            systemctl enable --now certbot.timer 2>/dev/null || {
                log_warn "certbot.timer tidak tersedia, setup cron..."
                local cron_line="0 3 * * * /usr/bin/certbot renew --quiet --deploy-hook 'systemctl reload nginx'"
                (crontab -l 2>/dev/null | grep -v certbot || true; echo "$cron_line") | crontab -
                log_ok "Cron auto-renewal ditambahkan (03:00 daily)"
            }
        fi
        return 0
    else
        log_warn "SSL Let's Encrypt belum berhasil didapatkan untuk ${domain}"
        log_warn "Penyebab umum: Domain diproxy Cloudflare (Orange Cloud) atau DNS belum propagasi."
        log_warn "Website tetap aktif via HTTP (port 80)."
        log_warn "Opsi solusi:"
        log_warn "  A) Gunakan Cloudflare Origin Certificate (Full Strict): sudo teraskota-ctl ssl-cloudflare"
        log_warn "  B) Atau nonaktifkan Orange Cloud sementara lalu jalankan: sudo teraskota-ctl ssl"
        return 1
    fi
}

# ── Cloudflare Origin Server Certificate Helpers ─────────────────────────────

# Read multi-line PEM input interactively from terminal (paste or file path)
read_pem_input() {
    local label="$1"
    local end_marker="$2"
    local out_file="$3"

    echo
    echo -e "${C_BOLD}${C_CYAN}Input ${label}${C_RESET}"
    echo -e "${C_DIM}Pilih cara input:${C_RESET}"
    echo -e "  ${C_WHITE}1${C_RESET}) Paste teks PEM langsung di terminal"
    echo -e "  ${C_WHITE}2${C_RESET}) Masukkan path file yang sudah ada di VPS (contoh: /tmp/origin.pem)"
    echo

    local mode=""
    read -rp "$(echo -e "${C_CYAN}Pilih [1/2]${C_RESET} [1]: ")" mode
    mode="${mode:-1}"

    if [ "$mode" = "2" ]; then
        local src_path=""
        read -rp "$(echo -e "${C_CYAN}Path file ${label}${C_RESET}: ")" src_path
        if [ -z "$src_path" ] || [ ! -f "$src_path" ]; then
            log_error "File tidak ditemukan: ${src_path}"
            return 1
        fi
        cp "$src_path" "$out_file"
    else
        echo -e "${C_YELLOW}Paste seluruh isi ${label} (termasuk baris BEGIN & END), lalu tekan Enter.${C_RESET}"
        echo -e "${C_DIM}Input otomatis berhenti saat baris '${end_marker}' terbaca (atau tekan Ctrl+D).${C_RESET}"
        : > "$out_file"
        local line=""
        while IFS= read -r line; do
            # Strip trailing CR if pasted from Windows clipboard
            line="${line%$'\r'}"
            printf '%s\n' "$line" >> "$out_file"
            if [[ "$line" == *"${end_marker}"* ]]; then
                break
            fi
        done
    fi

    # Strip any remaining CR characters
    sed -i 's/\r$//' "$out_file"

    if [ ! -s "$out_file" ]; then
        log_error "Isi ${label} kosong!"
        return 1
    fi

    return 0
}

# Validate that a certificate PEM and private key PEM are valid and cryptographically match
validate_cloudflare_cert_pair() {
    local cert_file="$1"
    local key_file="$2"

    if ! openssl x509 -in "$cert_file" -noout 2>/dev/null; then
        log_error "Format Origin Certificate (.pem) tidak valid menurut OpenSSL!"
        return 1
    fi

    if ! openssl pkey -in "$key_file" -noout 2>/dev/null; then
        log_error "Format Private Key (.key) tidak valid menurut OpenSSL!"
        return 1
    fi

    local cert_pub key_pub
    cert_pub=$(openssl x509 -in "$cert_file" -pubkey -noout 2>/dev/null | openssl sha256 | awk '{print $NF}')
    key_pub=$(openssl pkey -in "$key_file" -pubout 2>/dev/null | openssl sha256 | awk '{print $NF}')

    if [ -z "$cert_pub" ] || [ "$cert_pub" != "$key_pub" ]; then
        log_error "Origin Certificate dan Private Key TIDAK COCOK! Pastikan keduanya berasal dari pasangan yang sama."
        return 1
    fi

    return 0
}

# Write Nginx vhost configured with Cloudflare Origin SSL
# Serves BOTH port 80 and port 443 in a single server block (no forced 80->443 redirect)
# so it works seamlessly under Cloudflare Flexible, Full, AND Full (Strict) without redirect loops.
write_nginx_cloudflare_vhost() {
    local domain="$1"
    local php_ver="$2"
    local cert_file="$3"
    local key_file="$4"
    local vhost="/etc/nginx/sites-available/teraskota"

    cat > "$vhost" <<EOFNGINX
# KasirLoka / Teras Kota — Nginx Virtual Host (Cloudflare Compatible: Flexible / Full / Full Strict)
server {
    listen 80;
    listen [::]:80;
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name ${domain};
    root /var/www/teraskota/public;
    index index.php;

    # Cloudflare Origin Server Certificate
    ssl_certificate     ${cert_file};
    ssl_certificate_key ${key_file};
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml
        application/rss+xml
        image/svg+xml;

    # Static assets — long cache (Vite hashed filenames)
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    # PWA Service Worker — no cache
    location = /service-worker.js {
        expires -1;
        add_header Cache-Control "no-store, no-cache, must-revalidate";
    }

    # PWA Manifest
    location ~* ^/(manifest\.webmanifest|manifest\.json)$ {
        expires 1d;
        add_header Cache-Control "public";
    }

    # Static files
    location ~* \.(ico|css|js|gif|jpe?g|png|webp|avif|svg|woff2?|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public";
        try_files \$uri =404;
    }

    # Laravel routing
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${php_ver}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;

        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 60;
        fastcgi_read_timeout 60;

        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    # Deny dotfiles (except .well-known)
    location ~ /\.(?!well-known) {
        deny all;
    }

    # Deny sensitive internal directories (allow public/storage symlink & uploads)
    location ~ ^/(vendor|bootstrap|database|tests|vps)/ {
        deny all;
    }

    client_max_body_size 10M;

    access_log /var/log/nginx/teraskota-access.log;
    error_log  /var/log/nginx/teraskota-error.log;
}
EOFNGINX

    ln -sf "$vhost" /etc/nginx/sites-enabled/teraskota
    rm -f /etc/nginx/sites-enabled/default
}

# Interactive or non-interactive Cloudflare Origin SSL installation
# Usage: setup_cloudflare_origin_ssl <domain> [cert_src_file] [key_src_file]
setup_cloudflare_origin_ssl() {
    local domain="${1:?Domain required}"
    local cert_src="${2:-}"
    local key_src="${3:-}"
    local php_ver
    php_ver=$(load_config "PHP_VERSION" "$(detect_php_version)")
    php_ver="${php_ver:-8.4}"

    log_step "Konfigurasi Cloudflare Origin Server SSL (${domain})"

    local tmp_cert tmp_key
    tmp_cert=$(mktemp)
    tmp_key=$(mktemp)

    if [ -n "$cert_src" ] && [ -f "$cert_src" ]; then
        cp "$cert_src" "$tmp_cert"
        sed -i 's/\r$//' "$tmp_cert"
    else
        echo -e "${C_DIM}Di Dashboard Cloudflare: SSL/TLS → Origin Server → Create Certificate (format PEM).${C_RESET}"
        if ! read_pem_input "Origin Certificate (PEM)" "END CERTIFICATE" "$tmp_cert"; then
            rm -f "$tmp_cert" "$tmp_key"
            return 1
        fi
    fi

    if [ -n "$key_src" ] && [ -f "$key_src" ]; then
        cp "$key_src" "$tmp_key"
        sed -i 's/\r$//' "$tmp_key"
    else
        if ! read_pem_input "Private Key (PEM)" "PRIVATE KEY-----" "$tmp_key"; then
            rm -f "$tmp_cert" "$tmp_key"
            return 1
        fi
    fi

    log_substep "Memvalidasi pasangan Certificate & Private Key dengan OpenSSL..."
    if ! validate_cloudflare_cert_pair "$tmp_cert" "$tmp_key"; then
        rm -f "$tmp_cert" "$tmp_key"
        return 1
    fi

    mkdir -p "${CLOUDFLARE_SSL_DIR}"
    chmod 755 "${CLOUDFLARE_SSL_DIR}"

    local final_cert="${CLOUDFLARE_SSL_DIR}/${domain}.pem"
    local final_key="${CLOUDFLARE_SSL_DIR}/${domain}.key"

    mv "$tmp_cert" "$final_cert"
    mv "$tmp_key" "$final_key"
    chmod 644 "$final_cert"
    chmod 600 "$final_key"

    local expiry
    expiry=$(openssl x509 -enddate -noout -in "$final_cert" 2>/dev/null | cut -d= -f2 || echo "unknown")
    log_ok "Sertifikat tervalidasi (Berlaku hingga: ${expiry})"

    # Backup previous nginx vhost before replacing
    local vhost="/etc/nginx/sites-available/teraskota"
    if [ -f "$vhost" ]; then
        cp "$vhost" "${vhost}.bak"
    fi

    write_nginx_cloudflare_vhost "$domain" "$php_ver" "$final_cert" "$final_key"

    if nginx -t 2>&1; then
        systemctl reload nginx
        save_config "SSL_MODE" "cloudflare"
        save_config "CF_CERT_PATH" "$final_cert"
        save_config "CF_KEY_PATH" "$final_key"

        # Update APP_URL to https in .env if installed
        if [ -f "${TERASKOTA_ROOT}/.env" ]; then
            set_env_var "${TERASKOTA_ROOT}/.env" "APP_URL" "https://${domain}"
            (cd "${TERASKOTA_ROOT}" && php artisan config:cache >/dev/null 2>&1 || true)
        fi

        log_ok "Cloudflare Origin SSL aktif untuk https://${domain}!"
        echo -e "  ${C_YELLOW}Penting:${C_RESET} Di Dashboard Cloudflare → SSL/TLS → Overview, atur mode enkripsi ke ${C_BOLD}Full (strict)${C_RESET}."
        return 0
    else
        log_error "Nginx config test gagal! Mengembalikan konfigurasi sebelumnya..."
        if [ -f "${vhost}.bak" ]; then
            mv "${vhost}.bak" "$vhost"
            systemctl reload nginx 2>/dev/null || true
        fi
        return 1
    fi
}

renew_ssl() {
    local ssl_mode
    ssl_mode=$(load_config "SSL_MODE" "certbot")
    if [ "$ssl_mode" = "cloudflare" ]; then
        log_info "Domain menggunakan Cloudflare Origin Certificate (berlaku hingga 15 tahun)."
        log_info "Untuk mengganti sertifikat, jalankan: sudo teraskota-ctl ssl-cloudflare"
        return 0
    fi

    log_info "Memperbarui SSL certificates Let's Encrypt..."
    if certbot renew --force-renewal --deploy-hook 'systemctl reload nginx' 2>&1; then
        log_ok "SSL certificates diperbarui"
    else
        log_error "SSL renewal gagal"
        return 1
    fi
}

if [[ "${BASH_SOURCE[0]:-}" == "${0}" ]]; then
    require_root
    install_certbot
fi
