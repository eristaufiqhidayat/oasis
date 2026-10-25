## Parent

Bagian dari backlog 023 — lihat spec lengkap di `docs/backlog_items/023-backup-restore-marketama/spec.md`.

## What to build

Buat `PenulisSnapshot` dan `PembacaSnapshot`:
- folder `storage/app/backup/marketama/<YYYYMMDD-HHmm>/`, satu `<tabel>.csv.gz` per tabel
- `manifest.json` (format 1, sumber, mode, rentang tanggal, per tabel: jumlah sumber/tertulis, kolom, sha256)
- pembungkusan .zip dengan opsi AES `--sandi`
- permission `0600` dan retensi `--simpan=N`

## Acceptance criteria

- [ ] Snapshot penuh bisa dimuat oleh `marketama:import <folder>` yang lama (nama file = nama tabel).
- [ ] Pembaca menolak format tidak dikenal, sha256 tidak cocok, dan snapshot belum selesai.
- [ ] Unit test tulis → baca bolak-balik untuk CSV.gz dan zip (dengan dan tanpa sandi).

## Blocked by

- 01
