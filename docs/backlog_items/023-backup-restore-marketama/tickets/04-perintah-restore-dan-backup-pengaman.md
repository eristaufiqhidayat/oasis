## Parent

Bagian dari backlog 023 — lihat spec lengkap di `docs/backlog_items/023-backup-restore-marketama/spec.md`.

## What to build

Perintah `oasis:restore-marketama <snapshot|zip>` dengan opsi `--coba`, `--mode=gabung|ganti`, `--hanya`,
`--kembalikan`, dan `--paksa`.

- `BackupPengaman` men-dump tabel yang akan disentuh ke `storage/app/backup/oasis/pra-<waktu>/`.
- `PemulihData` melakukan upsert per potongan 1.000 baris.
- Penjaga `TABEL_MILIK_OASIS` menolak snapshot yang berisi tabel milik Oasis.
- Migration indeks unik `source_id` di tabel `lap_*` / `tracking_*` yang dipakai, dengan cek duplikat dulu.
- Migration tabel `oasis_restore_log`.

## Acceptance criteria

- [ ] Restore dua kali → jumlah baris sama; baris yang berubah ter-update.
- [ ] `users`, `roles`, `role_hak`, `master_kuota_motoris`, `pengaturan_admin`, `mobile_*`, dan `trx_*` non-`lama` tidak berubah.
- [ ] `--coba` tidak menulis apa pun; `--kembalikan` memulihkan isi tabel persis.
- [ ] Berjalan di SQLite (tes), MySQL, dan PostgreSQL.

## Blocked by

- 02
