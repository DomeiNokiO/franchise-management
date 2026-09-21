# Katalog pusat

## Bahan baku

Full Owner dapat membuat, mengubah, mengaktifkan, dan menonaktifkan bahan baku. Field utama:

- Nama dan SKU unik.
- Satuan: kg, gram, liter, pcs, atau satuan operasional lain.
- Harga pokok pusat.
- Reorder point untuk peringatan stok.

Bahan yang sudah dipakai resep, PO, atau masih memiliki stok tidak dihapus secara fisik. Nonaktifkan bahan agar histori transaksi tetap utuh.

## Produk dan resep

Full Owner mengelola produk dan komposisinya. Satu produk dapat memiliki beberapa bahan dengan jumlah desimal. Saat produk diedit, resep diganti dalam transaksi database yang sama agar tidak tersimpan setengah.

Harga POS selalu dibaca dari `products.selling_price` di server. Harga yang tampil di browser hanya preview.

## Dampak ke POS

Jika produk aktif tidak memiliki resep, POS tetap dapat mencatat penjualan tetapi tidak mengurangi bahan. Untuk produk yang harus mengurangi stok, Owner wajib mengisi resep terlebih dahulu.

Jika bahan dalam resep tidak memiliki stok cabang atau stoknya kurang, transaksi POS ditolak seluruhnya.

## Hak akses

- Full Owner: CRUD bahan, produk, dan resep.
- Owner Mitra: membaca katalog dan memakai produk pada POS/PO.
- Karyawan Mitra: membaca produk untuk POS; tidak dapat mengubah katalog.
