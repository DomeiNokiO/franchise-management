# Deploy ke Proxmox LXC (CT)

Aplikasi ini **ringan**: Laravel + PHP-FPM + Nginx + **SQLite** (default).
Tidak butuh Docker, tidak butuh nesting — muat di CT **unprivileged 1 vCPU / 1 GB RAM / 8 GB disk**.

| Komponen | Pilihan | Kenapa |
|---|---|---|
| Web server | Nginx | serve static + proxy PHP |
| Runtime | PHP-FPM 8.3 (systemd) | native, tanpa container |
| Database | SQLite (default) | tanpa daemon, tanpa password, RAM nyaris nol |
| Database alternatif | MariaDB | opsional (`USE_MYSQL=1`) untuk >10 transaksi/detik atau sync multi-host |

## Skenario 1 — Satu perintah dari host PVE (paling mudah)

Di **host Proxmox**, buat CT + install aplikasi sekaligus:

```bash
# unduh script pembantu
curl -fsSL https://raw.githubusercontent.com/DomeiNokiO/franchise-management/refs/heads/main/deploy/create-ct.sh -o /root/create-ct.sh
chmod +x /root/create-ct.sh

# non-interaktif: langsung jadi (kredensial via env)
FRANCHISE_OWNER_NAME="Suro" \
FRANCHISE_OWNER_EMAIL="suro@kontan.id" \
FRANCHISE_OWNER_PASSWORD="***" \
FRANCHISE_DOMAIN="franchise.kontan.id" \
/root/create-ct.sh 210 192.168.1.50 192.168.1.1 local-lvm
```

Skrip ini: cari/unduh template Ubuntu terbaru (otomatis), buat CT unprivileged
`1 vCPU / 1 GB / 8 GB`, start, tunggu jaringan siap, lalu jalankan installer di
dalam CT, dan verifikasi HTTP dari host.

Bila env kredensial tidak diisi, installer berjalan **interaktif** — buka
console CT (PVE WebUI → Terminal) dan lanjutkan prompt-nya.

Argumen: `create-ct.sh [CTID] [IP] [GW] [STORAGE]` — IP boleh dikosongkan (DHCP).

## Skenario 2 — CT sudah ada, install manual

Di **dalam CT** (root):

```bash
curl -fsSL https://raw.githubusercontent.com/DomeiNokiO/franchise-management/refs/heads/main/deploy/install.sh | bash
```

Installer memandu: nama/email/password owner → domain (kosong = akses via IP) → selesai.

## Skenario 3 — dengan domain + HTTPS

Setelah DNS `franchise.kontan.id` mengarah ke IP CT:

```bash
FRANCHISE_DOMAIN="franchise.kontan.id" \
FRANCHISE_LE_EMAIL="suro@kontan.id" \
curl -fsSL https://raw.githubusercontent.com/DomeiNokiO/franchise-management/refs/heads/main/deploy/install.sh | bash
```

Installer memasang Certbot, meminta sertifikat Let's Encrypt (webroot),
dan menuliskan server block Nginx HTTPS (redirect HTTP→HTTPS, HSTS).

## Update aplikasi

Di dalam CT — satu perintah:

```bash
/opt/franchise-management/deploy/update.sh
```

Skrip melakukan: `git pull --ff-only` (sebagai pemilik repo, tanpa masalah
*dubious ownership*), `composer install` **hanya bila composer.lock berubah**,
`php artisan migrate --force`, refresh cache config/route/view, reload
PHP-FPM + Nginx. `.env` dan data tidak tersentuh.

## Backup

Di dalam CT:

```bash
/opt/franchise-management/deploy/backup.sh            # → /opt/franchise-management/backups/
/opt/franchise-management/deploy/backup.sh /mnt/bkp   # → direktori tujuan
```

SQLite: hot backup via `VACUUM INTO` (aplikasi tetap jalan) + file `.env`.
MySQL: `mysqldump --single-transaction`. Hasil `backup-<waktu>.tar.gz` (mode 600).

Saran: backup harian ke storage PVE:

```bash
# crontab -e (di dalam CT)
0 3 * * * /opt/franchise-management/deploy/backup.sh /backup >> /var/log/franchise-backup.log 2>&1
```

## Restore (dari backup .tar.gz)

```bash
mkdir /tmp/restore && tar -xzf backup-*.tar.gz -C /tmp/restore && cd /tmp/restore/franchise-backup-*/
# SQLite:
cp database.sqlite /opt/franchise-management/database/database.sqlite
cp env.backup /opt/franchise-management/.env
chown -R appsvc:appsvc /opt/franchise-management
systemctl reload php8.3-fpm nginx
```

## Konfigurasi

Semua konfigurasi ada di `/opt/franchise-management/.env` (mode 600).
Ubah lalu jalankan `sudo php artisan config:cache` sebagai `appsvc`
(termasuk otomatis oleh `update.sh`).

Variabel penting:

| Variabel | Default | Keterangan |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | `sqlite` atau `mysql` |
| `DB_DATABASE` | `/opt/franchise-management/database/database.sqlite` | path file (sqlite) / nama DB (mysql) |
| `APP_URL` | `http://<IP-CT>` | URL publik aplikasi |
| `SEED_OWNER_*` | — | kredensial full-owner awal (hanya dipakai saat seed) |

## Keamanan bawaan

- App berjalan sebagai user `appsvc` (bukan root); PHP-FPM pool di-set ke `appsvc`.
- `.env` mode 600, di luar document root; semua file dotfile di-Nginx `deny all`.
- Header: `X-Content-Type-Options`, `X-Frame-Options SAMEORIGIN`, `Referrer-Policy`,
  + `Strict-Transport-Security` (mode HTTPS).
- UFW: hanya 22/80/443 dibuka (best-effort — di CT, firewall PVE umumnya
  mengelola port; jangan buka port PVE host ke internet).
- CSRF + session database + rate-limit login sudah aktif di aplikasi.

## Migrasi SQLite → MySQL (bila suatu hari dibutuhkan)

```bash
# di dalam CT, setelah MariaDB terpasang:
# 1. export data SQLite → SQL
sqlite3 /opt/franchise-management/database/database.sqlite .dump > /tmp/swap.sql
# 2. import ke MySQL
mysql -u franchise -p franchise < /tmp/swap.sql
# 3. set .env: DB_CONNECTION=mysql, DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD
# 4. sudo -u appsvc php artisan config:cache
```

## Peringatan operasional

- **Jangan hapus `/opt/franchise-management`** untuk upgrade — gunakan `update.sh`.
- CT unprivileged **tidak** perlu `nesting`/`keyctl` khusus; jangan berikan
  `unprivileged=0` (privileged) kecuali ada alasan lain — aplikasi ini tidak memerlukannya.
- RAM minimum 1 GB; SQLite + PHP-FPM (1-2 process) + Nginx memakai < 200 MB saat idle.
- Backup tetap wajib: SQLite = satu file, mudah hilang bila disk CT penuh/hapus.
