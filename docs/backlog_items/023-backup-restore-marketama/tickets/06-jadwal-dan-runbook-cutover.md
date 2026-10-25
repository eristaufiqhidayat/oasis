## Parent

Bagian dari backlog 023 — lihat spec lengkap di `docs/backlog_items/023-backup-restore-marketama/spec.md`.

## What to build

Tambahkan jadwal di `routes/console.php`:
- harian 01:00 WIB: backup inkremental lalu restore gabung, dengan `withoutOverlapping` dan notifikasi gagal/berhasil
- mingguan Minggu 02:00: backup `--penuh` master dan referensi

Jadwal dikendalikan `OASIS_BACKUP_MARKETAMA=true` di `.env`. Tulis juga runbook cutover (freeze, backup penuh
terakhir, restore, cek jumlah, alihkan HP, matikan jadwal) di `docs/TECH.md`.

## Acceptance criteria

- [ ] `php artisan schedule:list` menampilkan dua jadwal saat flag aktif, dan tidak ada saat flag mati.
- [ ] Runbook berisi query cek jumlah per tabel dan per tanggal (Marketama vs Oasis).

## Blocked by

- 03, 05
