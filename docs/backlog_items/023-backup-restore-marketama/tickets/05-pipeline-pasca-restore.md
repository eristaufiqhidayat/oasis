## Parent

Bagian dari backlog 023 — lihat spec lengkap di `docs/backlog_items/023-backup-restore-marketama/spec.md`.

## What to build

Setelah data dimuat, restore menjalankan berurutan:
1. `marketama:tarik sambung`
2. `enesis:sambung-transaksi` (hanya tabel yang berubah)
3. `enesis:migrasi-transaksi` (jenis terkait)
4. `enesis:sinkron-user`

Ringkasan per tabel (baru/diperbarui/dilewati/gagal) dicetak dan disimpan ke `oasis_restore_log`.

## Acceptance criteria

- [ ] Fixture `lap_penjualan_barang` → setelah restore ada di `trx_penjualan` dengan `sumber = lama`.
- [ ] Transaksi HP di tanggal yang sama tidak tertimpa (aturan `MigrasiTransaksi` tetap berlaku).
- [ ] Opsi `--tanpa-pipeline` untuk melewati langkah ini.

## Blocked by

- 04
