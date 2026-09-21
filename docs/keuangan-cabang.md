# Keuangan privat cabang

## Batas akses

- **Owner Mitra** dapat membuka laporan, membuka/menutup shift, dan mencatat kas.
- **Karyawan Mitra** dapat memakai POS dan, bila diberi permission, mencatat transaksi kas/shift; tidak dapat membuka laporan laba-rugi.
- **Full Owner** tidak memiliki route maupun service access ke omzet, saldo kas, biaya, shift, atau laporan keuangan cabang.

Semua data memiliki `branch_id`. Service memeriksa membership user sebelum mutasi atau query.

## Shift kasir

1. Owner Mitra atau kasir membuka shift dengan saldo awal.
2. Transaksi POS otomatis dicatat sebagai kas masuk dan terhubung ke shift aktif.
3. Kas masuk/keluar manual harus memakai shift aktif.
4. Saat tutup shift, sistem menghitung:

```text
Saldo sistem = saldo awal + kas masuk - kas keluar
Selisih = saldo fisik akhir - saldo sistem
```

5. Shift yang sudah tertutup tidak dapat menerima transaksi baru.
6. Satu cabang hanya boleh memiliki satu shift aktif.

## Keuangan dan laporan

Route `/finance` menampilkan filter cabang dan tanggal untuk Owner Mitra. Ringkasan yang tersedia:

- omzet dan jumlah transaksi POS;
- kas masuk;
- biaya operasional/kas keluar;
- daftar pengeluaran;
- saldo shift terbuka dan penutupan.

Laporan laba/rugi lengkap perlu menambahkan perhitungan HPP dari resep dan biaya operasional terklasifikasi. Jangan menyebut saldo kas sebagai laba.

## Keamanan transaksi

- Nominal divalidasi di server.
- Mutasi kas dibungkus `DB::transaction`.
- Shift dikunci dengan `lockForUpdate` saat dibuka/ditutup dan saat transaksi dicatat.
- POS menolak transaksi bila tidak ada shift aktif agar omzet dapat direkonsiliasi.
