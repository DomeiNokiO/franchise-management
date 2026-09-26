#!/usr/bin/env bash
# ============================================================================
# Installer Franchise Management — untuk Proxmox LXC (CT) & VPS ringan.
#
# TANPA Docker: PHP-FPM + Nginx + SQLite (default) atau MariaDB (opsional).
# Ringan: muat di CT unprivileged 1 vCPU / 1 GB RAM.
#
# Cara pakai:
#   1. Interaktif di dalam CT:
#        curl -fsSL https://raw.githubusercontent.com/DomeiNokiO/franchise-management/refs/heads/main/deploy/install.sh | bash
#
#   2. Non-interaktif (env vars) — untuk otomasi / create-ct.sh:
#        REPO_URL=... FRANCHISE_OWNER_EMAIL=owner@x.com \
#        FRANCHISE_OWNER_PASSWORD=*** bash install.sh
#
# Env opsional:
#   REPO_URL                 (default: repo GitHub publik)
#   APP_DIR                  (default: /opt/franchise-management)
#   FRANCHISE_DOMAIN         (opsional: domain + Let's Encrypt, DNS harus sudah siap)
#   FRANCHISE_LE_EMAIL       (default: admin@FRANCHISE_DOMAIN)
#   FRANCHISE_OWNER_NAME     (default: Full Owner)
#   FRANCHISE_OWNER_EMAIL    (wajib, prompt bila kosong)
#   FRANCHISE_OWNER_PASSWORD (wajib, prompt hidden bila kosong)
#   USE_MYSQL                (0=SQLite default, 1=MariaDB)
#   SKIP_TLS                 (1 = lewati Let's Encrypt meski ada domain)
# ============================================================================
set -Eeuo pipefail
export LC_ALL=C.UTF-8 LANG=C.UTF-8 DEBIAN_FRONTEND=noninteractive

REPO_URL="${REPO_URL:-https://github.com/DomeiNokiO/franchise-management.git}"
APP_DIR="${APP_DIR:-/opt/franchise-management}"
APP_USER="appsvc"
USE_MYSQL="${USE_MYSQL:-0}"
SKIP_TLS="${SKIP_TLS:-0}"
trap 'echo "" >&2; echo "[ERROR] Installer gagal di baris $LINENO: ${BASH_COMMAND}" >&2' ERR

log()  { echo; echo "==> $*"; }
fail() { echo "" >&2; echo "[FATAL] $*" >&2; exit 1; }

# --- prompt interaktif via /dev/tty (aman untuk `curl | bash`) -------------
tty_guard() { [[ -e /dev/tty ]] && { : > /dev/tty; } 2>/dev/null || true; }
ask() { # $1=prompt  $2=default
    local answer
    tty_guard
    printf "%s [%s]: " "$1" "$2" > /dev/tty
    read -r answer < /dev/tty || true
    printf '%s' "${answer:-$2}"
}
ask_secret() { # $1=prompt
    local answer
    tty_guard
    printf "%s: " "$1" > /dev/tty
    read -rs answer < /dev/tty || true
    echo > /dev/tty
    printf '%s' "$answer"
}

# --- cek awal --------------------------------------------------------------
[[ "${EUID}" -eq 0 ]] || fail "Jalankan sebagai root: sudo bash install.sh"
[[ -f /etc/os-release ]] || fail "/etc/os-release tidak ada."
. /etc/os-release
case "${ID:-}" in
    ubuntu)  [[ "${VERSION_ID%%.*}" -ge 20 ]] || fail "Ubuntu minimal 20.04 (terdeteksi $VERSION_ID).";;
    debian)  [[ "${VERSION_ID%%.*}" -ge 12 ]] || fail "Debian minimal 12 (terdeteksi $VERSION_ID).";;
    *) fail "OS didukung: Debian 12+ atau Ubuntu 20.04+ (terdeteksi: ${ID:-?}).";;
esac

# --- prompt kredensial -----------------------------------------------------
FRANCHISE_OWNER_NAME="${FRANCHISE_OWNER_NAME:-$(ask 'Nama Full Owner awal' 'Full Owner')}"
FRANCHISE_OWNER_EMAIL="${FRANCHISE_OWNER_EMAIL:-$(ask 'Email login Full Owner awal' 'owner@example.com')}"
if [[ -z "${FRANCHISE_OWNER_PASSWORD:-}" ]]; then
    if [[ -e /dev/tty ]]; then FRANCHISE_OWNER_PASSWORD="$(ask_secret 'Password login Full Owner (min 8 karakter)')"; fi
fi
[[ -n "${FRANCHISE_OWNER_PASSWORD:-}" ]] || fail "FRANCHISE_OWNER_PASSWORD wajib diisi (env atau prompt)."
[[ ${#FRANCHISE_OWNER_PASSWORD} -ge 8 ]] || fail "Password minimal 8 karakter."
[[ "$FRANCHISE_OWNER_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || fail "Email owner tidak valid: $FRANCHISE_OWNER_EMAIL"

FRANCHISE_DOMAIN="${FRANCHISE_DOMAIN:-}"
if [[ -z "$FRANCHISE_DOMAIN" ]]; then
    FRANCHISE_DOMAIN="$(ask 'Domain (kosong = akses via IP, tanpa TLS)' '')"
fi
if [[ -n "$FRANCHISE_DOMAIN" && "$SKIP_TLS" != "1" ]]; then
    FRANCHISE_LE_EMAIL="${FRANCHISE_LE_EMAIL:-admin@${FRANCHISE_DOMAIN%%.*}com}"
    [[ "$FRANCHISE_LE_EMAIL" == *@* ]] || FRANCHISE_LE_EMAIL="admin@example.com"
fi
[[ "$FRANCHISE_DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || fail "Domain tidak valid: $FRANCHISE_DOMAIN"

# --- paket sistem ----------------------------------------------------------
log "Memasang paket dasar (curl, git, openssl, locale)"
apt-get update -qq
apt-get install -y -qq curl ca-certificates gnupg git openssl >/dev/null
command -v git >/dev/null || fail "git tidak terpasang."

log "Memasang Nginx + PHP-FPM + ekstensi"
apt-get install -y -qq nginx composer unzip >/dev/null
# ekstensi PHP: deteksi versi major dari distro (php8.3 di Ubuntu 24.04/Debian 13, php8.2 di Debian 12)
PHP_EXT_BASE=$(dpkg -l 2>/dev/null | awk '/^ii  php[0-9]+\.[0-9]+-common/{print $2; exit}')
if [[ -z "$PHP_EXT_BASE" ]]; then
    PHP_VER="8.3"; [[ "${ID}" == "debian" && "${VERSION_ID%%.*}" -eq 12 ]] && PHP_VER="8.2"
    apt-get install -y -qq "php${PHP_VER}-fpm" >/dev/null
    PHP_EXT_BASE="php${PHP_VER}"
fi
PHP_VER="${PHP_EXT_BASE#php}"
apt-get install -y -qq \
    "${PHP_EXT_BASE}-fpm" "${PHP_EXT_BASE}-cli" "${PHP_EXT_BASE}-mbstring" "${PHP_EXT_BASE}-xml" \
    "${PHP_EXT_BASE}-curl" "${PHP_EXT_BASE}-zip" "${PHP_EXT_BASE}-bcmath" "${PHP_EXT_BASE}-intl" \
    "${PHP_EXT_BASE}-sqlite3" >/dev/null
command -v "php-fpm${PHP_VER}" >/dev/null || fail "PHP-FPM ${PHP_VER} tidak terpasang."
php -r 'exit((PHP_MAJOR_VERSION>8||(PHP_MAJOR_VERSION==8&&PHP_MINOR_VERSION>=2))?0:1);' || fail "PHP >= 8.2 diperlukan."

if [[ "$USE_MYSQL" == "1" ]]; then
    log "Memasang MariaDB server"
    apt-get install -y -qq mariadb-server mariadb-client >/dev/null
fi
if [[ -n "$FRANCHISE_DOMAIN" && "$SKIP_TLS" != "1" ]]; then
    log "Memasang Certbot (Let's Encrypt)"
    apt-get install -y -qq certbot >/dev/null
fi

# --- clone repo ------------------------------------------------------------
if [[ -f "$APP_DIR/composer.json" ]]; then
    log "Repo sudah ada di $APP_DIR — dipakai (mode reinstall/update)"
else
    log "Clone $REPO_URL ke $APP_DIR"
    rm -rf "$APP_DIR"
    git clone --depth 1 "$REPO_URL" "$APP_DIR"
fi
cd "$APP_DIR"

# --- composer ---------------------------------------------------------------
log "Menjalankan composer install (production)"
if [[ -f composer.lock ]]; then
    composer install --no-dev --no-interaction --no-progress --optimize-autoloader --quiet
else
    composer update --no-dev --no-interaction --no-progress --optimize-autoloader --quiet
fi

# --- database ---------------------------------------------------------------
if [[ "$USE_MYSQL" == "1" ]]; then
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=franchise
    DB_USERNAME=franchise
    DB_PASSWORD="$(openssl rand -hex 24)"
    systemctl enable --now mariadb
    for i in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && break; sleep 2; done
    mysqladmin ping >/dev/null || fail "MariaDB belum siap."
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
              CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
              CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
              GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
              GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1';
              FLUSH PRIVILEGES;"
else
    # SQLite — tanpa daemon, tanpa password, nyaris tak makan RAM
    DB_CONNECTION=sqlite
    DB_HOST=""
    DB_PORT=""
    DB_DATABASE="$APP_DIR/database/database.sqlite"
    DB_USERNAME=""
    DB_PASSWORD=""
fi

# --- .env --------------------------------------------------------------------
log "Menyiapkan .env"
if [[ -f .env ]]; then cp .env ".env.backup.$(date -u +%Y%m%dT%H%M%SZ)"; fi
cp .env.example .env

LOCAL_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
if [[ -n "$FRANCHISE_DOMAIN" && "$SKIP_TLS" != "1" ]]; then
    APP_URL="https://$FRANCHISE_DOMAIN"
elif [[ -n "$FRANCHISE_DOMAIN" ]]; then
    APP_URL="http://$FRANCHISE_DOMAIN"
else
    APP_URL="http://${LOCAL_IP:-localhost}"
fi
SESSION_SECURE_COOKIE=false
[[ "$APP_URL" == https://* ]] && SESSION_SECURE_COOKIE=true

sed -i \
    -e "s|^APP_URL=.*|APP_URL=$APP_URL|" \
    -e "s|^DB_CONNECTION=.*|DB_CONNECTION=$DB_CONNECTION|" \
    -e "s|^DB_HOST=.*|DB_HOST=$DB_HOST|" \
    -e "s|^DB_PORT=.*|DB_PORT=$DB_PORT|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" \
    -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=|" \
    -e "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=$SESSION_SECURE_COOKIE|" \
    -e "s|^SEED_OWNER_NAME=.*|SEED_OWNER_NAME=\"$FRANCHISE_OWNER_NAME\"|" \
    -e "s|^SEED_OWNER_EMAIL=.*|SEED_OWNER_EMAIL=$FRANCHISE_OWNER_EMAIL|" \
    -e "s|^SEED_OWNER_PASSWORD=.*|SEED_OWNER_PASSWORD=$FRANCHISE_OWNER_PASSWORD|" \
    .env

# --- user service + hak akses ------------------------------------------------
log "Membuat service account $APP_USER"
id "$APP_USER" >/dev/null 2>&1 || useradd --system --create-home --shell /usr/sbin/nologin "$APP_USER"

# SQLite: buat file DB + berikan hak tulis untuk app
if [[ "$DB_CONNECTION" == "sqlite" ]]; then
    mkdir -p database
    touch "$DB_DATABASE"
fi

chown -R "$APP_USER:$APP_USER" "$APP_DIR"
chmod 600 "$APP_DIR/.env"
chmod -R a+rX "$APP_DIR/storage" || true
# php-fpm pool: jalan sebagai appsvc (bukan www-data) agar bisa baca .env
FPM_POOL="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"
if [[ -f "$FPM_POOL" ]]; then
    sed -ri "s|^user *=.*|user = ${APP_USER}|" "$FPM_POOL"
    sed -ri "s|^group *=.*|group = ${APP_USER}|" "$FPM_POOL"
fi

# --- migrasi + seed -----------------------------------------------------------
log "Menjalankan migrasi + seed database"
sudo -u "$APP_USER" php artisan key:generate --force
sudo -u "$APP_USER" php artisan migrate --force
sudo -u "$APP_USER" php artisan db:seed --force
sudo -u "$APP_USER" php artisan storage:link || true
sudo -u "$APP_USER" php artisan config:cache >/dev/null
sudo -u "$APP_USER" php artisan route:cache >/dev/null || true
sudo -u "$APP_USER" php artisan view:cache >/dev/null || true

# --- nginx --------------------------------------------------------------------
log "Mengkonfigurasi Nginx"
SERVER_NAME="${FRANCHISE_DOMAIN:-_}"
cat > "/etc/nginx/sites-available/franchise-management" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};

    root ${APP_DIR}/public;
    index index.php index.html;
    client_max_body_size 20M;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location /.well-known/acme-challenge/ { root ${APP_DIR}/public; }

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param APP_BASE ${APP_DIR};
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_read_timeout 120s;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF
ln -sfn "/etc/nginx/sites-available/franchise-management" "/etc/nginx/sites-enabled/franchise-management"
rm -f /etc/nginx/sites-enabled/default
nginx -t >/dev/null
systemctl enable nginx >/dev/null 2>&1
systemctl enable --now "php${PHP_VER}-fpm"
systemctl reload nginx

# --- TLS (opsional) -------------------------------------------------------------
if [[ -n "$FRANCHISE_DOMAIN" && "$SKIP_TLS" != "1" ]]; then
    log "Meminta sertifikat Let's Encrypt untuk $FRANCHISE_DOMAIN"
    certbot certonly --webroot -w "$APP_DIR/public" -d "$FRANCHISE_DOMAIN" \
        --email "$FRANCHISE_LE_EMAIL" --agree-tos --no-eff-email --non-interactive
    cat > "/etc/nginx/sites-available/franchise-management" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${FRANCHISE_DOMAIN};

    location /.well-known/acme-challenge/ { root ${APP_DIR}/public; }
    location / { return 301 https://\$host\$request_uri; }
}
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name ${FRANCHISE_DOMAIN};

    ssl_certificate /etc/letsencrypt/live/${FRANCHISE_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${FRANCHISE_DOMAIN}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    root ${APP_DIR}/public;
    index index.php index.html;
    client_max_body_size 20M;

    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_read_timeout 120s;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF
    nginx -t >/dev/null
    systemctl reload nginx
fi

# --- firewall (best-effort; di CT sering dikelola PVE firewall) -------------------
if command -v ufw >/dev/null 2>&1; then
    ufw allow OpenSSH >/dev/null 2>&1 || true
    ufw allow 80/tcp >/dev/null 2>&1 || true
    ufw allow 443/tcp >/dev/null 2>&1 || true
    ufw --force enable >/dev/null 2>&1 || true
fi

# --- smoke check ------------------------------------------------------------------
log "Verifikasi akhir"
ROOT_CODE="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/ || true)"
LOGIN_CODE="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/login || true)"
[[ "$ROOT_CODE" == "302" && "$LOGIN_CODE" == "200" ]] || \
    fail "Smoke check gagal: /=${ROOT_CODE} /login=${LOGIN_CODE}. Cek: journalctl -u nginx -u php${PHP_VER}-fpm"

# --- ringkasan ----------------------------------------------------------------------
if [[ -n "$FRANCHISE_DOMAIN" && "$SKIP_TLS" != "1" ]]; then
    DISPLAY_URL="https://$FRANCHISE_DOMAIN"
else
    DISPLAY_URL="http://${LOCAL_IP:-localhost}"
fi
echo
echo "============================================================"
echo "  INSTALASI SELESAI — Franchise Management (tanpa Docker)"
echo "============================================================"
echo "  URL aplikasi      : $DISPLAY_URL"
echo "  Direktori aplikasi: $APP_DIR"
echo "  Database          : $DB_CONNECTION${DB_CONNECTION:+ ($( [ "$DB_CONNECTION" = sqlite ] && echo "file $DB_DATABASE" || echo "$DB_DATABASE"))}"
echo "  Nama owner        : $FRANCHISE_OWNER_NAME"
echo "  Email login       : $FRANCHISE_OWNER_EMAIL"
echo "  Password login    : $FRANCHISE_OWNER_PASSWORD"
echo
echo "  Update aplikasi   : $APP_DIR/deploy/update.sh"
echo "  Backup            : $APP_DIR/deploy/backup.sh"
echo
echo "  Simpan password di password manager (hanya tampil sekali)."
echo "  Ganti password setelah login pertama."
echo "============================================================"
