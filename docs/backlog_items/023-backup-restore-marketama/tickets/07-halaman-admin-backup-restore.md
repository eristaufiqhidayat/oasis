## Parent

Bagian dari backlog 023 — lihat spec lengkap di `docs/backlog_items/023-backup-restore-marketama/spec.md`.

## What to build

(Fase 2, opsional) Menu "Backup & Restore" di grup Master Data & Pengaturan (hak `backup_marketama`, super admin):
- daftar snapshot (status, jumlah per tabel, ukuran)
- tombol Backup sekarang dan Restore lewat queue job
- unduh zip dan riwayat `oasis_restore_log`

Disusun dari komponen `DataTable`, `Card`, `Button`, `Badge`, dan `Modal`.

## Acceptance criteria

- [ ] Hanya role dengan hak `backup_marketama` yang bisa membuka dan menjalankan.
- [ ] Restore dari UI selalu menampilkan hasil `--coba` dulu dan butuh konfirmasi.

## Blocked by

- 06
