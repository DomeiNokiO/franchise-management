#!/usr/bin/env bash
# ============================================================================
# Update aplikasi Franchise Management (jalankan DI DALAM CT/VM):
#     /opt/franchise-management/deploy/update.sh
#
# Aman: git pull --ff-only, migrate, refresh cache. .env dan data tak tersentuh.
# ============================================================================
set -Eeuo pipefail
export LC_ALL=C.UTF-8 LANG=C.UTF-8

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"
trap 'echo "" >&2; echo "[ERROR] Update gagal di baris $LINENO: ${BASH_COMMAND}" >&2' ERR

[[ "${EUID}" -eq 0 ]] || { echo "Jalankan sebagai root: sudo $0"; exit 1; }
[[ -f composer.json ]] || { echo "Bukan direktori aplikasi: $APP_DIR"; exit 1; }
command -v git >/dev/null || { echo "git tidak terpasang."; exit 1; }
command -v php  >/dev/null || { echo "php tidak terpasang."; exit 1; }

# service account aplikasi (dibuat installer)
RUN_USER="appsvc"
id "$RUN_USER" >/dev/null 2>&1 || RUN_USER="root"

# Cegah "dubious ownership" saat root mem-pull repo milik service-account
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
[[ "$RUN_USER" != "root" ]] && sudo -u "$RUN_USER" git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

OWNER="$(stat -c '%U' "$APP_DIR")"

echo "==> Pull kode terbaru (pemilik: ${OWNER})"
BEFORE="$(git rev-parse HEAD)"
if [[ "$OWNER" == "root" ]]; then
    git pull --ff-only
else
    sudo -u "$OWNER" git -C "$APP_DIR" pull --ff-only
fi
AFTER="$(git rev-parse HEAD)"

if [[ "$BEFORE" == "$AFTER" ]]; then
    echo "    Sudah versi terbaru ($AFTER). Selesai."
    exit 0
fi
echo "    ${BEFORE:0:7} -> ${AFTER:0:7}"

# File baru/diubah mungkin jadi milik root → kembalikan ke service account
if [[ "$OWNER" == "root" && "$RUN_USER" != "root" ]]; then
    chown -R "$RUN_USER:$RUN_USER" "$APP_DIR"
fi

# Reinstall dependensi HANYA bila composer berubah
CHANGED="$(git diff --name-only "$BEFORE" "$AFTER")"
if echo "$CHANGED" | grep -qE '^composer\.(lock|json)$'; then
    echo "==> composer install (composer.lock berubah)"
    if [[ -f composer.lock ]]; then
        composer install --no-dev --no-interaction --no-progress --optimize-autoloader --quiet
    else
        composer update --no-dev --no-interaction --no-progress --optimize-autoloader --quiet
    fi
else
    echo "    composer.lock tidak berubah — skip dependensi."
fi

# Migrasi (idempotent) + refresh cache
echo "==> php artisan migrate --force"
sudo -u "$RUN_USER" php artisan migrate --force

echo "==> Refresh cache (config/route/view)"
sudo -u "$RUN_USER" php artisan config:cache >/dev/null
sudo -u "$RUN_USER" php artisan route:cache >/dev/null || true
sudo -u "$RUN_USER" php artisan view:cache  >/dev/null || true

echo "==> Reload PHP-FPM + Nginx"
PHP_VER="$(dpkg -l 2>/dev/null | awk '/^ii  php[0-9]+\.[0-9]+-fpm/{print $2; exit}' | sed 's/^php//')"
[[ -n "${PHP_VER:-}" ]] && systemctl reload "php${PHP_VER}-fpm" 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo
echo "Update selesai. Versi: ${AFTER:0:7}"
echo "Jika tampilan aneh di browser: hard refresh (Ctrl+Shift+R)."
