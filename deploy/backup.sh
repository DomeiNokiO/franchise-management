#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
docker compose exec -T mysql mysqldump -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" | gzip > "backup-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
