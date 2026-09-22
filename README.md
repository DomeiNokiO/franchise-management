# Sistem Manajemen Franchise

Aplikasi web multi-cabang berbasis Laravel 11 untuk pengelolaan franchise. Teknologi utama:

- Laravel 11 dan PHP 8.3
- MySQL 8.4
- Docker Compose
- AdminLTE 4 dan Bootstrap 5 melalui CDN
- Spatie Laravel Permission untuk RBAC
- Nginx, Certbot, dan Let's Encrypt untuk HTTPS

> **Status saat ini:** repository ini berisi fondasi aplikasi yang aman dan siap dideploy. Modul POS lengkap, resep dan pengurangan stok otomatis, alur PO lengkap, biaya operasional, serta laporan laba/rugi masih perlu dikembangkan sebagai fitur lanjutan yang diuji satu per satu.

## Fitur fondasi

- Login dengan validasi, CSRF, regenerasi session, dan cookie aman.
- Role `full-owner`, `owner-mitra`, dan `karyawan-mitra`.
- Relasi user-cabang untuk pembatasan data antar mitra.
- POS dasar: transaksi multi-item, validasi server-side, pengurangan bahan berdasarkan resep, kas masuk, dan detail struk.
- Purchase Order: pembuatan mitra, persetujuan pusat, pengiriman dengan pengurangan stok pusat, dan penerimaan dengan penambahan stok cabang.
- Keuangan privat cabang: shift kasir, kas masuk/keluar, rekonsiliasi saldo, dan ringkasan laporan untuk Owner Mitra.
- Test service POS untuk alur sukses dan penolakan stok tidak cukup.
- Test service PO untuk alur pending sampai received dan pembatasan role.

Fitur lanjutan yang masih perlu dikembangkan:

- Biaya operasional, shift kas, laporan laba/rugi, export laporan, audit log UI, pengaturan brand/logo, manajemen user, dan permission granular adalah fitur lanjutan.

## Instalasi VPS sekali jalan

### Prasyarat VPS kosong

Installer memasang seluruh dependensi host. Sebelum menjalankan installer, siapkan hanya:

1. VPS Debian 12+ atau Ubuntu 22.04+.
2. Akses `root` atau user dengan `sudo`.
3. Domain dengan DNS A record ke IP VPS jika ingin HTTPS.
4. Port TCP `80` dan `443` tersedia.
5. Email aktif untuk Let's Encrypt jika TLS diaktifkan.

Installer otomatis memasang dan memeriksa:

- PHP CLI 8.2+ beserta ekstensi Laravel;
- Composer;
- Node.js 20 dan npm;
- Docker Engine dan Docker Compose;
- Nginx, Certbot, Git, OpenSSL, UFW, curl, dan utilitas sistem.

PHP, Composer, Node.js, dan MySQL tidak perlu dipasang manual. MySQL berjalan sebagai container Docker.

### Menjalankan installer

Mode interaktif, direkomendasikan untuk VPS baru:

```bash
git clone https://github.com/DomeiNokiO/franchise-management.git
cd franchise-management
sudo chmod +x deploy/install.sh
sudo ./deploy/install.sh
```

Installer akan menanyakan domain, email TLS, pilihan HTTPS, nama owner, dan email login owner. Password owner dibuat acak dan dicetak satu kali dalam ringkasan akhir.

Mode dengan parameter domain dan email:

```bash
sudo ./deploy/install.sh app.contoh.com admin@contoh.com
```

Mode otomatis untuk DNS yang belum siap:

```bash
sudo SKIP_TLS=1 OWNER_NAME='Owner Mitra' OWNER_EMAIL='owner@contoh.com' ./deploy/install.sh app.contoh.com admin@contoh.com
```

`SKIP_TLS=1` hanya untuk pengujian. Untuk mode otomatis production, pastikan DNS sudah aktif dan jangan memakai `SKIP_TLS`.

Installer akan:

1. Memasang dan memeriksa PHP, Composer, Node.js, Docker Compose, Nginx, Certbot, Git, OpenSSL, dan UFW.
2. Membuat `.env` dengan permission `600`.
3. Membuat `APP_KEY`, password database, password root database, dan password owner secara acak.
4. Membuat serta menjalankan container aplikasi dan MySQL.
5. Menjalankan migrasi dan seeder Laravel.
6. Mengatur Nginx sebagai reverse proxy ke container.
7. Mengaktifkan HTTPS Let's Encrypt bila dipilih.
8. Mengaktifkan firewall untuk SSH dan Nginx.

Akun owner awal:

- Email: `owner@domain-anda`
- Password: ditampilkan satu kali di terminal installer

Simpan password tersebut di password manager, lalu ganti setelah login pertama.

### Instalasi tanpa domain lokal

Untuk VM Proxmox yang diakses melalui Cloudflare Tunnel, installer memiliki mode tanpa domain. Domain publik tetap dibuat di Cloudflare, tetapi VM aplikasi tidak memerlukan DNS lokal atau Let's Encrypt.

#### Akses langsung melalui IP lokal VM

Jika hanya ingin membuka aplikasi dari jaringan lokal tanpa domain dan tanpa nomor port, gunakan mode IP LAN:

```bash
sudo NO_DOMAIN=1 \
  LOCAL_IP=192.168.10.25 \
  LAN_SUBNET=192.168.10.0/24 \
  ./deploy/install.sh
```

Buka dari komputer dalam jaringan yang sama:

```text
http://192.168.10.25
```

Installer akan:

- menjalankan Nginx host pada port `80`;
- meneruskan Nginx ke container aplikasi di `127.0.0.1:8080`;
- mengisi `APP_URL=http://192.168.10.25`;
- membuka UFW port `80` hanya dari `192.168.10.0/24`;
- tidak meminta Let's Encrypt;
- tidak membutuhkan Cloudflare Tunnel.

Ganti `LOCAL_IP` dan `LAN_SUBNET` sesuai jaringan Proxmox Anda. IP harus merupakan IP yang benar-benar terpasang pada VM aplikasi.

#### Cloudflare Tunnel di VM yang sama

```bash
sudo NO_DOMAIN=1 PUBLIC_URL=https://app.example.com HOST_BIND_IP=127.0.0.1 ./deploy/install.sh
```

Arahkan tunnel ke:

```yaml
ingress:
  - hostname: app.example.com
    service: http://127.0.0.1:8080
  - service: http_status:404
```

#### Cloudflared di VM berbeda

Jalankan installer di VM aplikasi dan gunakan IP private VM tersebut:

```bash
sudo NO_DOMAIN=1 PUBLIC_URL=https://app.example.com HOST_BIND_IP=192.168.10.25 ./deploy/install.sh
```

Saat mode interaktif, pilih `tanpa`, lalu pilih `lain` ketika ditanya lokasi Cloudflare Tunnel dan masukkan IP private VM aplikasi.

Arahkan tunnel ke:

```yaml
ingress:
  - hostname: app.example.com
    service: http://192.168.10.25:8080
  - service: http_status:404
```

Pada mode ini:

- tidak meminta sertifikat Let's Encrypt;
- tidak membuat konfigurasi Nginx host;
- aplikasi berjalan di container pada port host `8080`;
- `APP_URL` diisi URL Cloudflare publik agar link/login Laravel benar;
- binding default hanya `127.0.0.1`;
- binding berubah ke IP private yang Anda masukkan hanya jika tunnel berada di VM lain;
- UFW tidak membuka port HTTP/HTTPS, sehingga akses publik tetap melalui tunnel;
- port `8080` harus diizinkan hanya dari IP VM `cloudflared` pada firewall jaringan Proxmox/VM.

Jangan gunakan `HOST_BIND_IP=0.0.0.0`. Itu akan membuka service ke seluruh interface VM.

### Catatan Proxmox LXC/CT

Installer mendeteksi jika dijalankan di Proxmox LXC/CT. Docker di CT dapat gagal dengan error `failed to mount ... overlayfs ... permission denied`, seperti yang terjadi pada container tanpa fitur nesting.

Rekomendasi production: buat **VM Ubuntu 22.04/24.04**, bukan LXC/CT, lalu jalankan installer di dalam VM. Docker akan memakai kernel VM secara normal.

Jika tetap memakai CT untuk testing, dari host Proxmox aktifkan fitur berikut:

```bash
pct set <CTID> -features nesting=1,keyctl=1
pct restart <CTID>
```

CT privileged lebih kompatibel daripada unprivileged CT, tetapi tetap tidak menjamin semua storage backend mendukung Docker overlayfs. Setelah fitur aktif, jalankan ulang installer dengan:

```bash
sudo ALLOW_LXC_DOCKER=1 NO_DOMAIN=1 \
  LOCAL_IP=192.168.10.25 \
  LAN_SUBNET=192.168.10.0/24 \
  ./deploy/install.sh
```

Gunakan opsi tersebut hanya setelah memahami risiko Docker-in-LXC. Jangan menjalankan installer ulang berulang kali tanpa memperbaiki konfigurasi CT terlebih dahulu.

## Update aplikasi setelah `git pull`

```bash
cd /opt/franchise-management
git pull --ff-only origin main
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
```

Jangan menghapus volume Docker `mysql_data` atau `storage`; keduanya menyimpan data aplikasi.

## Backup dan pemulihan database

Buat backup:

```bash
cd /opt/franchise-management
sudo ./deploy/backup.sh
```

File backup dibuat sebagai `backup-UTC.sql.gz`. Simpan salinan di server atau storage terpisah. Untuk pemulihan, contoh perintahnya:

```bash
gunzip -c backup-UTC.sql.gz | docker compose exec -T mysql \
  mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
```

Pastikan variabel database dimuat dari `.env` sebelum menjalankan perintah pemulihan.

## Pengembangan lokal

Prasyarat: PHP 8.2+, Composer, Node.js 20+, Docker, dan Docker Compose.

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Host Hermes yang dipakai untuk menyiapkan repository ini tidak memiliki PHP, Composer, MySQL, atau Docker daemon aktif. Karena itu, runtime Laravel, migrasi, dan test browser harus dijalankan di VPS atau CI.

## Keuangan privat cabang

Implementasi shift, kas masuk/keluar, dan ringkasan laporan hanya untuk Owner Mitra dijelaskan di `docs/keuangan-cabang.md`. Full Owner pusat dan Karyawan Mitra ditolak dari laporan keuangan.

## Alur Purchase Order

1. Owner Mitra membuat PO dari menu `PO` untuk cabang miliknya.
2. Full Owner membuka detail PO dan memilih `Setujui`.
3. Full Owner memilih `Kirim`; sistem mengunci stok pusat, memastikan stok cukup, lalu mengurangi stok pusat.
4. Mitra melihat PO berstatus `shipped` dan memilih `Terima barang`.
5. Sistem menambah stok cabang dan membuat mutasi stok penerimaan.
6. Transisi yang tidak sesuai status ditolak oleh service.

Lihat `docs/alur-dan-hak-akses.md` untuk role dan workflow, `docs/katalog-dan-resep.md` untuk katalog pusat, serta `docs/panduan-pengembangan.md` untuk standar perubahan kode.

## Keamanan dan pemeliharaan

- Jangan commit `.env`, credential database, dump database, token API, atau password.
- Jangan aktifkan `APP_DEBUG=true` di production.
- Wajib gunakan HTTPS sebelum aplikasi dibuka untuk publik.
- MySQL hanya tersedia di jaringan Docker internal; port database tidak dipublikasikan.
- Setiap controller baru yang memakai `branch_id` wajib memeriksa membership user melalui policy atau middleware.
- Tambahkan test feature sebelum mengaktifkan POS, mutasi stok, approval PO, dan laporan keuangan.
- Lakukan backup database terjadwal dan uji pemulihan backup secara berkala.

## Struktur penting

- `app/Models` - model domain dan relasi database.
- `app/Http/Controllers` - controller HTTP.
- `app/Http/Middleware` - pembatasan role dan cabang.
- `database/migrations` - struktur database.
- `database/seeders` - role dan data awal.
- `deploy/install.sh` - instalasi VPS.
- `deploy/backup.sh` - backup database.
- `compose.yaml` - service aplikasi dan MySQL.
- `docker/` - konfigurasi Nginx dan Supervisor.
