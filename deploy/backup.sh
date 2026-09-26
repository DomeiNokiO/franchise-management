#!/usr/bin/env bash
# ============================================================================
# Backup data Franchise Management (jalankan DI DALAM CT/VM):
#     /opt/franchise-management/deploy/backup.sh [tujuan/]
#
# - SQLite : copy file DB (hot backup via VACUUM INTO) + .env
# - MySQL  : mysqldump
# Hasil: backup-<waktu>.tar.gz  (atau di direktori tujuan bila diberikan)
# ============================================================================
set -Eeuo pipefail
export LC_ALL=C.UTF-8

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"
[[ -f .env ]] || { echo "File .env tidak ditemukan — installer belum dijalankan." >&2; exit 1; }

# baca .env (hanya variabel yang dibutuhkan)
DB_CONNECTION="$(grep -E '^DB_CONNECTION=' .env | cut -d= -f2-)"
DB_DATABASE="$(grep -E '^DB_DATABASE=' .env | cut -d= -f2-)"
DB_USERNAME="$(grep -E '^DB_USERNAME=' .env | cut -d= -f2-)"
DB_PASSWORD="$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2-)"
DB_HOST="$(grep -E '^DB_HOST=' .env | cut -d= -f2-)"

DEST="${1:-$APP_DIR/backups}"
mkdir -p "$DEST"; chmod 700 "$DEST" 2>/dev/null || true
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
NAME="franchise-backup-${STAMP}"
mkdir "$TMP/$NAME"

if [[ "$DB_CONNECTION" == "sqlite" ]]; then
    echo "==> Backup SQLite: $DB_DATABASE"
    [[ -f "$DB_DATABASE" ]] || { echo "File SQLite tidak ada: $DB_DATABASE" >&2; exit 1; }
    # VACUUM INTO = snapshot konsisten tanpa menghentikan app
    php -r '$db=new PDO("sqlite:'.$DB_DATABASE.'"); $db->exec("VACUUM INTO \"'.$TMP.'/'.$NAME.'/database.sqlite\""); echo "OK\n";'
    cp -f .env "$TMP/$NAME/env.backup"
    chmod 600 "$TMP/$NAME"/database.sqlite "$TMP/$NAME/env.backup"
elif [[ -n "$DB_CONNECTION" && "$DB_CONNECTION" != "sqlite" ]]; then
    echo "==> Backup MySQL/MariaDB: $DB_DATABASE"
    mysqldump --host="${DB_HOST:-127.0.0.1}" --user="$DB_USERNAME" --password="***" \
        --single-transaction --quick --lock-tables=false "$DB_DATABASE" | gzip > "$TMP/$NAME/database.sql.gz"
    cp -f .env "$TMP/$NAME/env.backup"
    chmod 600 "$TMP/$NAME"/database.sql.gz "$TMP/$NAME/env.backup"
else
    echo "DB_CONNECTION tidak dikenal: '$DB_CONNECTION'" >&2; exit 1
fi

tar -czf "$DEST/$NAME.tar.gz" -C "$TMP" "$NAME"
chmod 600 "$DEST/$NAME.tar.gz"
echo "Backup berhasil: $DEST/$NAME.tar.gz"
echo "Isi: database + file env (tanpa kode aplikasi)."
