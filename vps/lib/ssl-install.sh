#!/usr/bin/env bash
# language: bash, file: vps/lib/ssl-install.sh, target: Ubuntu 24/26 x86_64/arm64
# Certbot SSL installation and certificate management

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

install_certbot() {
    log_step "[7/7] SSL Certificate via Certbot"

    if check_command certbot; then
        log_ok "Certbot sudah terinstall: $(certbot --version 2>&1)"
    else
        log_substep "Menginstall Certbot + plugin Nginx..."
        DEBIAN_FRONTEND=noninteractive apt-get install -y certbot python3-certbot-nginx 2>&1 | tail -1
        if ! check_command certbot; then
            # Fallback: snap
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

    log_substep "Mendapatkan SSL certificate untuk ${domain}..."

    # Build certbot command
    local certbot_cmd=(certbot --nginx -d "$domain" --non-interactive --agree-tos)

    if [ -n "$email" ]; then
        certbot_cmd+=(--email "$email")
    else
        certbot_cmd+=(--register-unsafely-without-email)
    fi

    # Attempt to get cert
    if "${certbot_cmd[@]}" 2>&1; then
        log_ok "SSL certificate berhasil didapatkan untuk ${domain}"

        # Verify auto-renewal timer
        if systemctl is-enabled certbot.timer &>/dev/null; then
            log_ok "Auto-renewal sudah aktif (certbot.timer)"
        else
            systemctl enable --now certbot.timer 2>/dev/null || {
                # Fallback: cron
                log_warn "certbot.timer tidak tersedia, setup cron..."
                local cron_line="0 3 * * * /usr/bin/certbot renew --quiet --deploy-hook 'systemctl reload nginx'"
                (crontab -l 2>/dev/null | grep -v certbot; echo "$cron_line") | crontab -
                log_ok "Cron auto-renewal ditambahkan (03:00 daily)"
            }
        fi
    else
        log_warn "SSL belum berhasil didapatkan untuk ${domain}"
        log_warn "Penyebab umum: Domain diproxy Cloudflare (Orange Cloud) atau DNS belum propagasi."
        log_warn "Website tetap aktif via HTTP (port 80)."
        log_warn "Cara setup SSL nanti:"
        log_warn "  1. Di dashboard Cloudflare: ubah awan ke 'DNS Only' (Grey) sementara"
        log_warn "  2. Jalankan: sudo teraskota-ctl -> pilih menu 'Fix SSL Certificate'"
        log_warn "  3. Setelah sukses, ubah kembali Cloudflare ke 'Proxied' (Orange)"
        return 0
    fi
}

renew_ssl() {
    log_info "Memperbarui SSL certificates..."
    if certbot renew --force-renewal --deploy-hook 'systemctl reload nginx' 2>&1; then
        log_ok "SSL certificates diperbarui"
    else
        log_error "SSL renewal gagal"
        return 1
    fi
}

# Run if called directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    require_root
    install_certbot
fi
