#!/bin/bash

# ==========================================================
# TERASKOTA - VPS CONTROL PANEL
# ==========================================================

APP_DIR="/var/www/teraskota"

function show_header() {
    echo "======================================================"
    echo "              TERASKOTA CONTROL PANEL                 "
    echo "======================================================"
}

function show_help() {
    show_header
    echo "Perintah yang tersedia:"
    echo "  tk-panel info        : Melihat status server (RAM, Disk, Service)"
    echo "  tk-panel start       : Menyalakan Nginx, PHP-FPM, MariaDB"
    echo "  tk-panel stop        : Mematikan Nginx, PHP-FPM, MariaDB"
    echo "  tk-panel restart     : Merestart Nginx, PHP-FPM, MariaDB"
    echo "  tk-panel update      : Pull repo GitHub terbaru & build PWA"
    echo "  tk-panel domain      : Mengubah nama domain website"
    echo "  tk-panel cert        : Setup / Renew HTTPS SSL (Certbot)"
    echo "  tk-panel optimize    : Optimize cache Laravel"
    echo "======================================================"
}

case "$1" in
    info)
        show_header
        echo "[1] Memory Usage (RAM & Swap):"
        free -h
        echo ""
        echo "[2] Disk Usage:"
        df -h /
        echo ""
        echo "[3] Service Status:"
        systemctl is-active nginx php8.3-fpm mariadb | awk '{print "Nginx/PHP/MariaDB: "$0}'
        ;;
        
    start)
        echo "Menyalakan service..."
        systemctl start nginx php8.3-fpm mariadb
        echo "Service berhasil dinyalakan."
        ;;
        
    stop)
        echo "Mematikan service..."
        systemctl stop nginx php8.3-fpm mariadb
        echo "Service berhasil dimatikan."
        ;;
        
    restart)
        echo "Merestart service..."
        systemctl restart nginx php8.3-fpm mariadb
        echo "Service berhasil direstart."
        ;;
        
    update)
        show_header
        echo "Memulai update aplikasi dari GitHub..."
        cd $APP_DIR
        
        echo "1. Menarik kode terbaru..."
        git reset --hard HEAD
        git pull origin main
        
        echo "2. Install dependensi PHP (Composer)..."
        composer install --no-dev --optimize-autoloader
        
        echo "3. Migrasi Database..."
        php artisan migrate --force
        
        echo "4. Build Frontend (PWA)..."
        npm install
        npm run build
        
        echo "5. Optimize Laravel..."
        php artisan optimize:clear
        php artisan optimize
        
        echo "6. Fix Permissions..."
        chown -R www-data:www-data $APP_DIR
        chmod -R 775 $APP_DIR/storage
        chmod -R 775 $APP_DIR/bootstrap/cache
        
        echo "Update Selesai!"
        ;;
        
    domain)
        show_header
        if [ -z "$2" ]; then
            read -p "Masukkan nama domain baru (contoh: pos.namatoko.com): " DOMAIN
        else
            DOMAIN="$2"
        fi
        
        if [ -n "$DOMAIN" ]; then
            sed -i "s/server_name .*/server_name $DOMAIN;/" /etc/nginx/sites-available/teraskota
            systemctl restart nginx
            echo "Domain berhasil diubah menjadi $DOMAIN"
            echo "Untuk mengaktifkan HTTPS, jalankan: tk-panel cert"
        else
            echo "Nama domain tidak boleh kosong."
        fi
        ;;
        
    cert)
        show_header
        DOMAIN=$(grep server_name /etc/nginx/sites-available/teraskota | awk '{print $2}' | tr -d ';')
        
        if [ "$DOMAIN" = "_" ] || [ -z "$DOMAIN" ]; then
            echo "Domain masih default (_). Silakan ubah domain terlebih dahulu."
            echo "Gunakan perintah: tk-panel domain"
        else
            echo "Meminta sertifikat SSL untuk $DOMAIN..."
            certbot --nginx -d $DOMAIN
        fi
        ;;
        
    optimize)
        show_header
        echo "Melakukan optimasi Laravel Cache..."
        cd $APP_DIR
        php artisan optimize:clear
        php artisan optimize
        echo "Optimasi selesai!"
        ;;
        
    *)
        show_help
        ;;
esac
