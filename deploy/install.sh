#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="${1:-}"
LE_EMAIL="${2:-}"
NO_DOMAIN="${NO_DOMAIN:-}"
PUBLIC_URL="${PUBLIC_URL:-}"
LOCAL_IP="${LOCAL_IP:-}"
LAN_SUBNET="${LAN_SUBNET:-}"
HOST_BIND_IP="${HOST_BIND_IP:-127.0.0.1}"
INSTALL_DEV_TOOLS="${INSTALL_DEV_TOOLS:-1}"
USE_NGINX=1
NGINX_SERVER_NAME=""

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

VIRT_TYPE="$(systemd-detect-virt --container 2>/dev/null || true)"
if [[ "$VIRT_TYPE" == "lxc" || -f /run/systemd/container && "$(cat /run/systemd/container)" == "lxc" ]]; then
    if [[ "${ALLOW_LXC_DOCKER:-0}" != "1" ]]; then
        fail "Proxmox LXC/CT terdeteksi. Docker membutuhkan CT privileged dengan nesting=1 dan keyctl=1; solusi paling stabil adalah Ubuntu VM. Jika tetap ingin mencoba CT, set ALLOW_LXC_DOCKER=1 setelah mengaktifkan fitur tersebut di Proxmox."
    fi
    log "Proxmox LXC terdeteksi; melanjutkan dengan risiko Docker overlayfs/cgroup terbatas"
fi

if [[ -z "$DOMAIN" && "$NO_DOMAIN" != "1" ]]; then
    mode="$(ask 'Mode instalasi: domain atau tanpa domain? (domain/tanpa)' 'domain')"
    [[ "$mode" =~ ^(tanpa|tanpa-domain|local)$ ]] && NO_DOMAIN=1 || NO_DOMAIN=0
fi
if [[ "$NO_DOMAIN" == "1" ]]; then
    DOMAIN="localhost"
    LE_EMAIL="none@localhost"
    SKIP_TLS=1
    if [[ -z "$LOCAL_IP" ]]; then
        local_mode="$(ask 'Akses tanpa domain: lokal saja atau IP LAN? (lokal/lan)' 'lokal')"
        if [[ "$local_mode" =~ ^(lan|ip|jaringan)$ ]]; then
            default_local_ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
            LOCAL_IP="$(ask 'IP lokal VM aplikasi' "${default_local_ip:-192.168.1.25}")"
        fi
    fi
    if [[ -n "$LOCAL_IP" ]]; then
        [[ "$LOCAL_IP" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]] || fail "LOCAL_IP harus berupa alamat IPv4."
        PUBLIC_URL="http://$LOCAL_IP"
        NGINX_SERVER_NAME="$LOCAL_IP"
        HOST_BIND_IP=127.0.0.1
        if [[ -z "$LAN_SUBNET" ]]; then
            LAN_SUBNET="$(ask 'Subnet LAN yang boleh mengakses port 80 (CIDR)' '192.168.1.0/24')"
        fi
        [[ "$LAN_SUBNET" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}/[0-9]{1,2}$ ]] || fail "LAN_SUBNET harus dalam format CIDR, contoh 192.168.1.0/24."
    else
        USE_NGINX=0
    fi
    if [[ -z "$PUBLIC_URL" ]]; then PUBLIC_URL='http://localhost:8080'; fi
    [[ "$PUBLIC_URL" =~ ^https?:// ]] || fail "PUBLIC_URL harus diawali http:// atau https://."
    if [[ "$USE_NGINX" == "0" && "$HOST_BIND_IP" == "127.0.0.1" ]]; then
        tunnel_location="$(ask 'Cloudflare Tunnel berada di VM yang sama atau VM lain? (sama/lain)' 'sama')"
        if [[ "$tunnel_location" =~ ^(lain|berbeda|other)$ ]]; then
            default_bind="$(hostname -I 2>/dev/null | awk '{print $1}')"
            HOST_BIND_IP="$(ask 'IP private VM aplikasi yang boleh diakses cloudflared' "${default_bind:-192.168.1.25}")"
        fi
    fi
    [[ "$HOST_BIND_IP" =~ ^(127\.0\.0\.1|localhost|[0-9]{1,3}(\.[0-9]{1,3}){3})$ ]] || fail "HOST_BIND_IP harus localhost atau alamat IPv4 private yang valid."
else
    if [[ -z "$DOMAIN" ]]; then DOMAIN="$(ask 'Domain aplikasi' 'app.contoh.com')"; fi
    if [[ -z "$LE_EMAIL" ]]; then LE_EMAIL="$(ask "Email untuk TLS Let's Encrypt" 'admin@contoh.com')"; fi
    [[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || fail "Domain tidak valid."
    [[ "$LE_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || fail "Email tidak valid."
    if [[ -z "$SKIP_TLS" ]]; then
        tls_answer="$(ask "Aktifkan HTTPS Let's Encrypt? (DNS harus sudah mengarah)" 'y')"
        [[ "$tls_answer" =~ ^[Yy]$ ]] && SKIP_TLS=0 || SKIP_TLS=1
    fi
fi
if [[ -z "${OWNER_NAME:-}" ]]; then OWNER_NAME="$(ask 'Nama Full Owner awal' 'Full Owner')"; fi
    if [[ -z "${OWNER_EMAIL:-}" ]]; then OWNER_EMAIL="$(ask 'Email login Full Owner awal' 'owner@local.test')"; fi
[[ "$OWNER_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || fail "Email owner tidak valid."
OWNER_PASSWORD="${OWNER_PASSWORD:-$(rand)}"

log "Memasang paket sistem"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl openssl nginx certbot ufw git gnupg lsb-release software-properties-common unzip locales
if ! locale -a 2>/dev/null | grep -qi '^en_US\.utf-8$'; then
    sed -i 's/^# *en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen || true
    locale-gen en_US.UTF-8 || true
fi
export LANG="${LANG:-en_US.UTF-8}" LC_ALL="${LC_ALL:-en_US.UTF-8}"

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
APP_URL="${PUBLIC_URL:-http://$DOMAIN}"
[[ "$SKIP_TLS" != "1" && "$NO_DOMAIN" != "1" ]] && APP_URL="https://$DOMAIN"
sed -i \
    -e "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" \
    -e "s|^APP_URL=.*|APP_URL=$APP_URL|" \
    -e "s|^HOST_BIND_IP=.*|HOST_BIND_IP=$HOST_BIND_IP|" \
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

if [[ "$USE_NGINX" == "1" ]]; then
    log "Mengatur Nginx reverse proxy"
    mkdir -p "$APP_DIR/docker/webroot"
    cat >/etc/nginx/sites-available/franchise-management <<EOF
server {
    listen 80;
    server_name ${NGINX_SERVER_NAME:-$DOMAIN};
    location /.well-known/acme-challenge/ { root $APP_DIR/docker/webroot; }
    location / { proxy_pass http://127.0.0.1:8080; proxy_set_header Host \$host; proxy_set_header X-Forwarded-Proto \$scheme; proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for; }
}
EOF
    ln -sfn /etc/nginx/sites-available/franchise-management /etc/nginx/sites-enabled/franchise-management
    nginx -t && systemctl reload nginx
fi

if [[ "$SKIP_TLS" != "1" && "$NO_DOMAIN" != "1" ]]; then
    log "Meminta sertifikat Let's Encrypt"
    certbot certonly --webroot -w "$APP_DIR/docker/webroot" -d "$DOMAIN" --email "$LE_EMAIL" --agree-tos --no-eff-email --non-interactive
    cat >/etc/nginx/sites-available/franchise-management <<EOF
server {
    listen 80;
    server_name ${NGINX_SERVER_NAME:-$DOMAIN};
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl http2;
    server_name ${NGINX_SERVER_NAME:-$DOMAIN};
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
if [[ "$USE_NGINX" == "1" && "$NO_DOMAIN" != "1" ]]; then
    ufw allow 'Nginx Full' || true
fi
if [[ "$NO_DOMAIN" == "1" && -n "$LOCAL_IP" ]]; then
    ufw allow from "$LAN_SUBNET" to any port 80 proto tcp || true
fi
ufw allow OpenSSH || true
ufw --force enable || true

SCHEME="http"
if [[ "$NO_DOMAIN" == "1" ]]; then
    DISPLAY_URL="$PUBLIC_URL"
elif [[ "$SKIP_TLS" != "1" ]]; then
    SCHEME="https"
    DISPLAY_URL="$SCHEME://$DOMAIN"
else
    DISPLAY_URL="http://$DOMAIN"
fi
echo
echo "============================================================"
echo "INSTALASI SELESAI"
echo "============================================================"
echo "URL aplikasi       : $DISPLAY_URL"
if [[ "$NO_DOMAIN" == "1" ]]; then
    echo "Akses lokal        : http://127.0.0.1:8080"
    echo "Cloudflare Tunnel  : arahkan service ke http://127.0.0.1:8080"
fi
echo "Nama owner         : $OWNER_NAME"
echo "Email login        : $OWNER_EMAIL"
echo "Password login     : $OWNER_PASSWORD"
echo "File konfigurasi   : $APP_DIR/.env (permission 600)"
echo "Backup database    : sudo $APP_DIR/deploy/backup.sh"
echo
echo "Simpan password di password manager. Password hanya ditampilkan sekali."
echo "Ganti password setelah login pertama."
echo "============================================================"
