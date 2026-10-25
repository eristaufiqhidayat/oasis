## Parent

Bagian dari backlog 023 — lihat spec lengkap di `docs/backlog_items/023-backup-restore-marketama/spec.md`.

## What to build

Cek langsung ke Marketama (akun admin, `--batas=5`) untuk setiap menu transaksi di katalog:
- bentuk respons DataTables (objek bernama vs array posisi)
- nama parameter filter tanggal
- ketersediaan ID baris

Hasilnya dituangkan ke `config/oasis_backup.php`, yang berisi per tabel: menu sumber, `tableId`, jenis
(master/referensi/transaksi/lokasi), kunci upsert, parameter tanggal, dan pemetaan kolom bila perlu. Tambahkan juga
kelas `App\Support\Backup\KatalogBackup`.

## Acceptance criteria

- [ ] Catatan hasil cek per menu ditambahkan ke bagian "Further Notes" di spec.
- [ ] `config/oasis_backup.php` berisi semua tabel di tabel katalog spec, dan setiap tabel tujuan ada di skema Oasis (diuji).
- [ ] `KatalogBackup` menolak entri yang endpoint-nya tidak lolos `MarketamaClient::postDiizinkan`.

## Blocked by

None (can start immediately)
