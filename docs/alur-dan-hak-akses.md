# Alur bisnis dan hak akses

## Aktor

| Aktor | Ruang lingkup | Hak utama |
|---|---|---|
| Full Owner | Seluruh cabang dan gudang pusat | Katalog bahan, stok pusat, approval PO, monitoring stok; tidak boleh melihat laporan keuangan cabang |
| Owner Mitra | Cabang yang terhubung pada pivot `branch_user` | POS, stok fisik, PO, kas, biaya operasional, laporan keuangan cabangnya |
| Karyawan Mitra | Cabang yang terhubung pada pivot `branch_user` | POS dan kas harian sesuai permission yang diberikan |

## Alur POS

1. Kasir memilih cabang yang memang terhubung ke user.
2. Kasir memilih produk dan jumlah. Harga dikirim hanya sebagai tampilan; server mengambil harga dari tabel `products`.
3. Server memvalidasi produk aktif, jumlah, diskon, dan pembayaran.
4. Server membuka transaksi database dan mengunci baris stok cabang dengan `lockForUpdate`.
5. Server membaca resep produk dan menghitung kebutuhan bahan baku.
6. Jika stok tidak cukup, seluruh transaksi dibatalkan tanpa membuat penjualan atau kas.
7. Jika cukup, server membuat penjualan, item penjualan, mutasi stok negatif, dan kas masuk dalam satu transaksi.
8. Struk detail dapat dibuka dari daftar penjualan.

## Aturan isolasi tenant

- User mitra hanya boleh memakai cabang yang ada pada `branch_user`.
- Daftar penjualan mitra memakai subquery cabang milik user.
- Detail penjualan memeriksa membership cabang sebelum menampilkan data.
- Jangan menerima `branch_id` dari URL tanpa policy atau pemeriksaan membership.
- Full Owner boleh memonitor stok lintas cabang, tetapi middleware `financial.branch` menolak route finansial cabang.

## Status PO

`pending -> approved -> shipped -> received`.

- Mitra membuat PO berstatus `pending`.
- Full Owner menyetujui PO menjadi `approved`.
- Gudang pusat mengirim menjadi `shipped`.
- Mitra menerima; saat status `received`, stok cabang bertambah dan mutasi stok dibuat.
- PO `rejected` tidak boleh diterima dan tidak boleh mengubah stok.

## Kontrak service

Service domain harus:

- menerima user dan branch ID;
- memeriksa membership sebelum query atau mutasi;
- memakai `DB::transaction` untuk perubahan lintas tabel;
- memakai `lockForUpdate` untuk saldo stok dan kas yang rawan race condition;
- menghitung nilai uang dari data server, bukan input browser;
- melempar `ValidationException` untuk input bisnis yang tidak valid;
- menulis audit/mutasi yang dapat ditelusuri.
