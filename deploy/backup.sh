#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

if [[ ! -f .env ]]; then
  echo "File .env tidak ditemukan. Jalankan installer terlebih dahulu." >&2
  exit 1
fi
set -a
. ./.env
set +a

BACKUP_DIR="${BACKUP_DIR:-$APP_DIR/backups}"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
FILE="$BACKUP_DIR/backup-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
docker compose exec -T mysql mysqldump --single-transaction --quick --lock-tables=false \
  -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" | gzip > "$FILE"
chmod 600 "$FILE"
echo "Backup berhasil: $FILE"
