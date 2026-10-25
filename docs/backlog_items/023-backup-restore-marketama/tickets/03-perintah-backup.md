## Parent

Bagian dari backlog 023 — lihat spec lengkap di `docs/backlog_items/023-backup-restore-marketama/spec.md`.

## What to build

Perintah `oasis:backup-marketama` dengan opsi `--penuh`, `--sejak`, `--sampai`, `--hanya`, `--lanjut`, dan `--status`.

- Master memakai `Penarik` dengan closure simpan yang diarahkan ke `PenulisSnapshot`.
- Transaksi memakai `Penarik::daftar()` dengan `$extra` filter tanggal dari katalog.
- Inkremental default: tanggal akhir backup sukses terakhir dikurangi 2 hari.
- Progres disimpan di `mk_tarikan` dengan kunci `backup:<snapshot>:<tabel>`.
- Cek jumlah baris (tertulis vs sumber) sebelum snapshot ditandai selesai.

## Acceptance criteria

- [ ] Tes dengan `Http::fake`: backup berhalaman menulis jumlah baris yang benar ke file dan manifest.
- [ ] Gagal di tengah lalu `--lanjut` melanjutkan dari halaman terakhir tanpa baris dobel di file.
- [ ] Tidak ada permintaan POST selain login dan DataTables (diuji lewat `Http::assertSent`).

## Blocked by

- 02
