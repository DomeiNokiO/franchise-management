# Panduan Pengembangan

## Prinsip

- Controller hanya mengatur HTTP; logika bisnis ada di `app/Services`.
- Request validation ada di `app/Http/Requests`.
- Model berisi relasi dan cast, bukan proses transaksi panjang.
- Perubahan stok dan kas wajib atomik.
- Semua data milik cabang wajib memiliki `branch_id` dan pemeriksaan tenant.
- Nilai harga, total, dan saldo selalu dihitung ulang di server.

## Perubahan database

1. Buat migration baru; jangan mengedit migration yang sudah dipakai production.
2. Tambahkan foreign key dan index yang sesuai.
3. Tambahkan model relation dan cast.
4. Tambahkan test untuk alur sukses dan penolakan.
5. Jalankan `php artisan migrate:fresh --seed` hanya di database development.

## Pemeriksaan sebelum commit

```bash
php artisan test
php artisan pint --test
php artisan route:list
php artisan config:clear
```

Untuk CI, gunakan SQLite in-memory untuk test service dan MySQL untuk test integrasi.

## Deploy update

```bash
git pull --ff-only origin main
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
```

Selalu backup database sebelum migration yang mengubah data.
