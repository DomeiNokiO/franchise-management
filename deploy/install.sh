#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="${1:-}"
LE_EMAIL="${2:-}"
SKIP_TLS="${SKIP_TLS:-}"
INSTALL_DEV_TOOLS="${INSTALL_DEV_TOOLS:-1}"

fail() { echo "ERROR: $*" >&2; exit 1; }
log() { echo; echo "==> $*"; }
rand() { openssl rand -hex 24; }
ask() {
    local prompt="$1" default="$2" answer
    read -r -p "$prompt [$default]: " answer
    printf '%s' "${answer:-$default}"
}

[[ "${EUID}" -eq 0 ]] || fail "Jalankan sebagai root: sudo ./deploy/install.sh"
[[ -f /etc/os-release ]] || fail "Sistem operasi tidak dikenali. Gunakan Debian 12+ atau Ubuntu 22.04+."
. /etc/os-release
case "${ID:-}" in debian|ubuntu) ;; *) fail "OS didukung hanya Debian atau Ubuntu." ;; esac

if [[ -z "$DOMAIN" ]]; then DOMAIN="$(ask 'Domain aplikasi' 'app.contoh.com')"; fi
if [[ -z "$LE_EMAIL" ]]; then LE_EMAIL="$(ask "Email untuk TLS Let's Encrypt" 'admin@contoh.com')"; fi
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || fail "Domain tidak valid."
[[ "$LE_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || fail "Email tidak valid."
if [[ -z "$SKIP_TLS" ]]; then
    tls_answer="$(ask "Aktifkan HTTPS Let's Encrypt? (DNS harus sudah mengarah)" 'y')"
    [[ "$tls_answer" =~ ^[Yy]$ ]] && SKIP_TLS=0 || SKIP_TLS=1
fi
if [[ -z "${OWNER_NAME:-}" ]]; then OWNER_NAME="$(ask 'Nama Full Owner awal' 'Full Owner')"; fi
if [[ -z "${OWNER_EMAIL:-}" ]]; then OWNER_EMAIL="$(ask 'Email login Full Owner awal' "owner@$DOMAIN")"; fi
[[ "$OWNER_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || fail "Email owner tidak valid."
OWNER_PASSWORD="${OWNER_PASSWORD:-$(rand)}"

log "Memasang paket sistem"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl openssl nginx certbot ufw git gnupg lsb-release software-properties-common unzip

log "Memastikan PHP CLI 8.2+"
if [[ "$ID" == "ubuntu" ]]; then
    add-apt-repository -y ppa:ondrej/php >/dev/null
    apt-get update
fi
apt-get install -y php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-mysql php8.3-bcmath || apt-get install -y php-cli php-mbstring php-xml php-curl php-zip php-mysql php-bcmath
php_major="$(php -r 'echo PHP_MAJOR_VERSION;')"
php_minor="$(php -r 'echo PHP_MINOR_VERSION;')"
(( php_major > 8 || (php_major == 8 && php_minor >= 2) )) || fail "PHP minimal 8.2 diperlukan; versi terpasang $(php -r 'echo PHP_VERSION;')."

log "Memasang Composer"
if ! command -v composer >/dev/null 2>&1; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
composer --version >/dev/null

log "Memasang Docker Engine dan Docker Compose"
if ! command -v docker >/dev/null 2>&1; then curl -fsSL https://get.docker.com | sh; fi
systemctl enable --now docker
command -v docker >/dev/null || fail "Docker gagal dipasang."
docker compose version >/dev/null 2>&1 || fail "Plugin Docker Compose tidak tersedia."

if [[ "$INSTALL_DEV_TOOLS" == "1" ]]; then
    log "Memasang Node.js 20 dan npm"
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
    node_major="$(node -p 'process.versions.node.split(".")[0]')"
    (( node_major >= 20 )) || fail "Node.js 20+ diperlukan; versi terpasang $(node --version)."
fi

cd "$APP_DIR"
if [[ -f .env ]]; then cp .env ".env.backup.$(date -u +%Y%m%dT%H%M%SZ)"; fi
cp .env.example .env
chmod 600 .env
APP_KEY="base64:$(openssl rand -base64 32)"
DB_PASSWORD="$(rand)"
DB_ROOT_PASSWORD="$(rand)"
APP_URL="http://$DOMAIN"
[[ "$SKIP_TLS" != "1" ]] && APP_URL="https://$DOMAIN"
sed -i \
    -e "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" \
    -e "s|^APP_URL=.*|APP_URL=$APP_URL|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" \
    -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD|" \
    -e "s|^SEED_OWNER_NAME=.*|SEED_OWNER_NAME=\"$OWNER_NAME\"|" \
    -e "s|^SEED_OWNER_EMAIL=.*|SEED_OWNER_EMAIL=$OWNER_EMAIL|" \
    -e "s|^SEED_OWNER_PASSWORD=.*|SEED_OWNER_PASSWORD=$OWNER_PASSWORD|" .env
chmod 600 .env

log "Membangun dan menjalankan container"
docker compose up -d --build
for attempt in {1..30}; do
    if docker compose exec -T app php artisan about >/dev/null 2>&1; then break; fi
    [[ "$attempt" -eq 30 ]] && fail "Container aplikasi belum siap. Periksa: docker compose logs app mysql"
    sleep 2
done
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan storage:link || true
docker compose exec -T app php artisan optimize

log "Mengatur Nginx reverse proxy"
mkdir -p "$APP_DIR/docker/webroot"
cat >/etc/nginx/sites-available/franchise-management <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    location /.well-known/acme-challenge/ { root $APP_DIR/docker/webroot; }
    location / { proxy_pass http://127.0.0.1:8080; proxy_set_header Host \$host; proxy_set_header X-Forwarded-Proto \$scheme; proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for; }
}
EOF
ln -sfn /etc/nginx/sites-available/franchise-management /etc/nginx/sites-enabled/franchise-management
nginx -t && systemctl reload nginx

if [[ "$SKIP_TLS" != "1" ]]; then
    log "Meminta sertifikat Let's Encrypt"
    certbot certonly --webroot -w "$APP_DIR/docker/webroot" -d "$DOMAIN" --email "$LE_EMAIL" --agree-tos --no-eff-email --non-interactive
    cat >/etc/nginx/sites-available/franchise-management <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl http2;
    server_name $DOMAIN;
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    location / { proxy_pass http://127.0.0.1:8080; proxy_set_header Host \$host; proxy_set_header X-Forwarded-Proto https; proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for; }
}
EOF
    nginx -t && systemctl reload nginx
fi
ufw allow OpenSSH || true
ufw allow 'Nginx Full' || true
ufw --force enable || true

SCHEME="http"
[[ "$SKIP_TLS" != "1" ]] && SCHEME="https"
echo
echo "============================================================"
echo "INSTALASI SELESAI"
echo "============================================================"
echo "URL aplikasi       : $SCHEME://$DOMAIN"
echo "Nama owner         : $OWNER_NAME"
echo "Email login        : $OWNER_EMAIL"
echo "Password login     : $OWNER_PASSWORD"
echo "File konfigurasi   : $APP_DIR/.env (permission 600)"
echo "Backup database    : sudo $APP_DIR/deploy/backup.sh"
echo
echo "Simpan password di password manager. Password hanya ditampilkan sekali."
echo "Ganti password setelah login pertama."
echo "============================================================"
