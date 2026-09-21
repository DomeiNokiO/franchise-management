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

### Prasyarat

1. VPS Debian 12/13 atau Ubuntu 22.04/24.04.
2. Akses `root` atau user dengan `sudo`.
3. Domain sudah memiliki DNS A record yang mengarah ke IP VPS.
4. Port TCP `80` dan `443` tersedia.
5. Email aktif untuk registrasi Let's Encrypt.

### Menjalankan installer

```bash
git clone https://github.com/DomeiNokiO/franchise-management.git
cd franchise-management
sudo chmod +x deploy/install.sh
sudo ./deploy/install.sh app.contoh.com admin@contoh.com
```

Installer akan:

1. Memasang Docker, Nginx, Certbot, Git, OpenSSL, dan UFW.
2. Membuat `.env` dengan permission `600`.
3. Membuat `APP_KEY`, password database, password root database, dan password owner secara acak.
4. Membuat serta menjalankan container aplikasi dan MySQL.
5. Menjalankan migrasi dan seeder Laravel.
6. Mengatur Nginx sebagai reverse proxy ke container.
7. Mengaktifkan HTTPS Let's Encrypt.
8. Mengaktifkan firewall untuk SSH dan Nginx.

Akun owner awal:

- Email: `owner@domain-anda`
- Password: ditampilkan satu kali di terminal installer

Simpan password tersebut di password manager, lalu ganti setelah login pertama.

### Jika DNS belum siap

Untuk menjalankan instalasi tanpa meminta sertifikat TLS:

```bash
sudo SKIP_TLS=1 ./deploy/install.sh app.contoh.com admin@contoh.com
```

Jangan gunakan mode ini untuk aplikasi publik. Setelah DNS siap, jalankan Certbot secara manual dan ubah konfigurasi Nginx ke HTTPS.

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
