#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="${1:-}"
LE_EMAIL="${2:-}"

if [[ "${EUID}" -ne 0 ]]; then echo "Jalankan sebagai root: sudo ./deploy/install.sh domain.tld admin@domain.tld" >&2; exit 1; fi
if [[ -z "$DOMAIN" || -z "$LE_EMAIL" ]]; then echo "Pemakaian: $0 domain.tld admin@domain.tld" >&2; exit 1; fi
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "Domain tidak valid" >&2; exit 1; }
[[ "$LE_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || { echo "Email tidak valid" >&2; exit 1; }

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl openssl nginx certbot ufw git
if ! command -v docker >/dev/null 2>&1; then curl -fsSL https://get.docker.com | sh; fi
systemctl enable --now docker

cd "$APP_DIR"
install -m 600 /dev/null .env
cp .env.example .env
rand() { openssl rand -hex 24; }
APP_KEY="base64:$(openssl rand -base64 32)"
DB_PASSWORD="$(rand)"
DB_ROOT_PASSWORD="$(rand)"
OWNER_PASSWORD="$(rand)"
sed -i \
  -e "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" \
  -e "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" \
  -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD|" \
  -e "s|^SEED_OWNER_EMAIL=.*|SEED_OWNER_EMAIL=owner@$DOMAIN|" \
  -e "s|^SEED_OWNER_PASSWORD=.*|SEED_OWNER_PASSWORD=$OWNER_PASSWORD|" .env
chmod 600 .env

docker compose up -d --build
sleep 10
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan storage:link || true

cat >/etc/nginx/sites-available/franchise-management <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    location /.well-known/acme-challenge/ { root $APP_DIR/docker/webroot; }
    location / { proxy_pass http://127.0.0.1:8080; proxy_set_header Host \$host; proxy_set_header X-Forwarded-Proto \$scheme; proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for; }
}
EOF
mkdir -p "$APP_DIR/docker/webroot"
ln -sfn /etc/nginx/sites-available/franchise-management /etc/nginx/sites-enabled/franchise-management
nginx -t && systemctl reload nginx

if [[ "${SKIP_TLS:-0}" != "1" ]]; then
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

echo "Instalasi selesai: https://$DOMAIN"
echo "Email owner: owner@$DOMAIN"
echo "Password owner: $OWNER_PASSWORD"
echo "Simpan password tersebut. Password hanya ditampilkan sekali."
