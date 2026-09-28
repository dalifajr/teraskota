#!/bin/bash

# ==========================================================
# TERASKOTA - VPS INSTALLATION SCRIPT (Ubuntu 26 x86/ARM)
# ==========================================================
# Requirement: Ubuntu 26, 1GB RAM, 1 vCPU, 30GB Storage
# Run as Root
# Usage: sudo bash install.sh

if [ "$EUID" -ne 0 ]; then
  echo "Silakan jalankan script ini sebagai root (sudo bash install.sh)"
  exit
fi

APP_DIR="/var/www/teraskota"
REPO_URL="https://github.com/dalifajr/teraskota.git"
DB_NAME="teraskota_db"
DB_USER="teraskota_user"
DB_PASS=$(openssl rand -base64 12)

echo "=========================================================="
echo "1. Membuat Swap 2GB (Mencegah OOM saat npm build)"
echo "=========================================================="
if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' | tee -a /etc/fstab
    echo "Swap 2GB berhasil dibuat."
else
    echo "Swap sudah ada."
fi

echo "=========================================================="
echo "2. Update System & Install Dependencies"
echo "=========================================================="
apt update && apt upgrade -y
apt install -y software-properties-common curl git unzip zip certbot python3-certbot-nginx

# Install PHP 8.3 & Nginx & MariaDB
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y nginx mariadb-server \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-bcmath php8.3-intl php8.3-sqlite3

# Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js (LTS v20)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

echo "=========================================================="
echo "3. Tuning Nginx, PHP-FPM, dan MariaDB untuk RAM 1GB"
echo "=========================================================="
# Tuning MariaDB (Low RAM)
cat > /etc/mysql/mariadb.conf.d/99-tuning.cnf <<EOF
[mysqld]
innodb_buffer_pool_size = 64M
max_connections = 50
key_buffer_size = 16M
EOF
systemctl restart mariadb

# Tuning PHP-FPM 8.3
sed -i 's/pm.max_children = 50/pm.max_children = 5/' /etc/php/8.3/fpm/pool.d/www.conf
sed -i 's/pm.start_servers = 2/pm.start_servers = 2/' /etc/php/8.3/fpm/pool.d/www.conf
sed -i 's/pm.min_spare_servers = 1/pm.min_spare_servers = 1/' /etc/php/8.3/fpm/pool.d/www.conf
sed -i 's/pm.max_spare_servers = 3/pm.max_spare_servers = 3/' /etc/php/8.3/fpm/pool.d/www.conf
systemctl restart php8.3-fpm

echo "=========================================================="
echo "4. Setup Database"
echo "=========================================================="
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "=========================================================="
echo "5. Clone Repository & Setup Laravel"
echo "=========================================================="
if [ -d "$APP_DIR" ]; then
    rm -rf "$APP_DIR"
fi
git clone $REPO_URL $APP_DIR
cd $APP_DIR

# Setup .env
cp .env.example .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Generate Key & Migrate
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan optimize

# Install Node & Build
npm install
npm run build

# Fix Permissions
chown -R www-data:www-data $APP_DIR
find $APP_DIR -type f -exec chmod 644 {} \;
find $APP_DIR -type d -exec chmod 755 {} \;
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

echo "=========================================================="
echo "6. Setup Nginx Default Vhost"
echo "=========================================================="
cat > /etc/nginx/sites-available/teraskota <<EOF
server {
    listen 80;
    server_name _;
    root $APP_DIR/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/teraskota /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
systemctl restart nginx

echo "=========================================================="
echo "7. Menginstal Control Panel (tk-panel)"
echo "=========================================================="
cp $APP_DIR/vps/tk-panel.sh /usr/local/bin/tk-panel
chmod +x /usr/local/bin/tk-panel

echo "=========================================================="
echo "INSTALASI SELESAI!"
echo "Database User: $DB_USER"
echo "Database Pass: $DB_PASS"
echo "Akses control panel dengan mengetikkan: tk-panel"
echo "=========================================================="
