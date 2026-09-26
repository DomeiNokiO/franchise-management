# Arsitektur UI

## Prinsip

1. **Tanpa JS framework** — hanya Bootstrap 5, Font Awesome, dan DataTables 1.13 (serverside) via CDN.
2. **Semua form memakai modal** — tambah/edit bahan, produk, buka/tutup shift, dan catat kas adalah modal, bukan halaman baru.
3. **Tabel memakai serverside processing** — server menangani filter, sort, pagination; klien tidak pernah memuat seluruh dataset.
4. **Konfirmasi sebelum aksi destruktif** — `confirm()` untuk hapus data, setujui/tolak/kirim/terima PO, dan tutup shift.
5. **Harga dan total selalu dihitung ulang server-side** (lihat `docs/keuangan-cabang.md`); JS di kasir hanya untuk UX.

## Endpoint serverside DataTables

Semua endpoint mengembalikan JSON standar DataTables:

```json
{ "draw": 1, "recordsTotal": 10, "recordsFiltered": 3, "data": [ { ...baris... } ] }
```

| Route | Nama | Keterangan |
|---|---|---|
| `GET /sales/data` | `sales.data` | Daftar penjualan; tenant-scoped per role |
| `GET /purchase-orders/data` | `purchase-orders.data` | Daftar PO; tenant-scoped per role |
| `GET /ingredients/data` | `ingredients.data` | Daftar bahan baku |
| `GET /products/data` | `products.data` | Daftar produk + ringkasan resep |

Parameter yang dibaca: `draw`, `start`, `length`, `search[value]`, `order[0][column]` (index kolom), `order[0][dir]`.

Implementasi terpusat di `app/Support/DataTables.php`:
- `recordsTotal` dihitung dengan filter tetap **tanpa** search;
- `recordsFiltered` dengan filter tetap **+** search;
- sort hanya untuk kolom yang terdaftar di daftar `$sortable` (whitelist, bukan dari input);
- isolasi tenant (subquery `branch_user`) selalu diterapkan di dalam scope sebelum count.

## Endpoint fragment form

Untuk modal, form dipanggil sebagai HTML parsial:

| Route | Nama |
|---|---|
| `GET /ingredients/form` / `GET /ingredients/form/{id}` | `ingredients.form` — fragment form bahan |
| `GET /products/form` / `GET /products/form/{id}` | `products.form` — fragment form produk + resep |

Hanya `full-owner` yang boleh mengakses (guard di controller). Klien memakai `fetch()` lalu mengisi body modal dan mengatur action/method form (PUT via hidden `_method`).

## Halaman per role

| Halaman | Full Owner | Owner Mitra | Karyawan |
|---|---|---|---|
| Dashboard (statistik + peringatan stok) | ✓ | ✓ | ✓ |
| Kasir (POS 2-pane) | ✓ | ✓ | ✓ |
| Penjualan (tabel serverside) | ✓ | ✓ (cabangnya) | ✓ (cabangnya) |
| PO (tabel + detail timeline) | ✓ | ✓ (cabangnya, hanya buat/terima) | ✗ |
| Keuangan (shift + kas + laporan) | ✗ (ditolak middleware) | ✓ | ✗ |
| Bahan Baku (tabel + modal) | ✓ CRUD | ✓ baca | ✓ baca |
| Produk & Resep (tabel + modal) | ✓ CRUD | ✓ baca | ✓ baca |

## Komponen kasir (POS)

- Kiri: grid produk aktif (klik = tambah ke keranjang).
- Kan: keranjang (qty +/−, hapus), pilih cabang, diskon, nominal terima.
- Total/subtotal/kembali dihitung real-time oleh JS **hanya untuk tampilan**;
  server (`SaleService`) menghitung ulang dari `products.selling_price` dan menolak
  bila pembayaran kurang atau stok/resep tidak cukup.
- Tanpa shift aktif, `SaleService` menolak transaksi (omzet harus bisa direkonsiliasi).

## Keamanan di UI

- `@auth` membatasi sidebar & menu per role; route tetap dilindungi middleware.
- `POST /active-branch` (ganti cabang aktif di topbar) melewati middleware `branch.access`.
- Semua modal form memvalidasi ulang di server; input `branch_id` diuji membership.
- CSRF token dari meta tag dipakai untuk `fetch()` (header `X-CSRF-TOKEN`).
