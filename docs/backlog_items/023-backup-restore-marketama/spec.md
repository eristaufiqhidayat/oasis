# Backup & Restore Data dari Aplikasi Marketama yang Sedang Berjalan

> Backlog item: [023](../../BACKLOG.md)

## Problem Statement

Aplikasi lama **Marketama** (https://app.cnvmarketama.com) masih dipakai setiap hari, sementara Oasis disiapkan
sebagai penggantinya. Data Oasis saat ini hanya punya dua jalur masuk, dan keduanya tidak cukup untuk masa transisi:

1. **Zip data awal** (`marketama_csv_import_1-22okt2026.zip`) adalah potret sekali jadi per 22 Okt 2026 dengan
   format tabel lama. Transaksi sesudah tanggal itu tidak ada, dan tabel/kolom baru (`mk_*`, `trx_*`, kolom `*_id`,
   `sumber`) tidak punya CSV di zip.
2. **`marketama:tarik`** menarik master (depo, area, karyawan, user, outlet) langsung ke `mk_*`, tetapi menu
   transaksi hanya bisa ditarik lewat `mentah:<menu>` ke `mk_baris_mentah` (JSON berkunci judul kolom HTML). Tidak ada
   langkah yang mengubah `mk_baris_mentah` menjadi `lap_*`, padahal `enesis:migrasi-transaksi` membaca dari `lap_*`.
   Hasil tarikan juga langsung masuk database, jadi tidak ada **file backup** yang bisa disimpan, dipindah ke
   server lain, atau dipakai untuk mengulang restore.

Selain itu `marketama:import` / `MarketamaDataSeeder` **mengosongkan tabel** sebelum mengisi (`CsvTableLoader`).
Cara itu cocok untuk impor pertama, tapi berbahaya kalau diulang tiap hari: riwayat yang sudah dimigrasi dan data
yang masuk dari aplikasi HP Oasis bisa ikut hilang.

Yang dibutuhkan: cara rutin dan aman untuk (a) **mem-backup** data Marketama yang sedang berjalan ke file snapshot,
dan (b) **me-restore** snapshot itu ke Oasis secara bertahap (inkremental, bisa diulang, bisa dibatalkan) sampai hari
pindah (cutover).

## Solution

Pipeline dua langkah dengan **snapshot portabel** di tengahnya, memakai ulang klien baca-saja yang sudah ada
(`MarketamaClient`, penjaga `postDiizinkan` tidak berubah):

```
Marketama (baca saja)                    Snapshot (file)                          Oasis
┌───────────────────────┐   backup   ┌──────────────────────────┐   restore   ┌──────────────────────────────┐
│ login admin           │ ─────────▶ │ manifest.json            │ ──────────▶ │ 0. backup pengaman Oasis     │
│ DataTables per menu   │            │ <tabel>.csv.gz per tabel │             │ 1. master  → mk_* (upsert)   │
│ (100 baris/halaman,   │            │ sha256 + jumlah baris    │             │ 2. transaksi → lap_* (upsert)│
│  jeda 300 ms)         │            │ → dibungkus .zip         │             │ 3. sambung ID → migrasi trx_*│
└───────────────────────┘            └──────────────────────────┘             │ 4. sinkron user, laporan     │
                                                                               └──────────────────────────────┘
```

### 1. Katalog backup (`config/oasis_backup.php`)

Satu daftar yang menentukan menu Marketama apa yang di-backup dan ke tabel Oasis mana. Isinya hanya menu yang
dipakai Oasis (menu bertanda `*` di Enesis tidak ikut).

| Jenis | Menu Marketama → tabel Oasis | Kunci upsert | Mode tarik |
| --- | --- | --- | --- |
| Master | depo, wilayah depo, area, distributor, produk, karyawan (sales/koordinator/supervisor/lainnya), user, outlet → `mk_*` (lewat `Penarik` yang sudah ada) | `id` asli Marketama | selalu penuh |
| Referensi | `holiday_dates`, `tipe_outlet`, `produk_kompetitor`, `waktu_aktif_app`, `sales_point_conversion_per_warehouse`, `rumus_insentif_sales`, `master_depo` | `source_id` | selalu penuh (kecil) |
| Transaksi | `lap_penjualan_barang`, `lap_penjualan_barang_detail`, `lap_checkin_checkout`, `lap_absensi_sales`, `lap_absensi_koordinator`, `lap_checkin_outlet`, `lap_join_visit`, `lap_barang_canvassing`, `lap_barang_habis_terjual`, `lap_pengembalian_barang`, `lap_stok_outlet`, `lap_stok_kompetitor` | `source_id` | penuh atau per rentang tanggal |
| Lokasi | tracking sales & koordinator (`tracking_*`, `tracking_*_lokasi`) | `source_id` / (orang, waktu) | per rentang tanggal |

Laporan turunan (Effective Call, NOO, AC, EC, RO, OSS, barang terbaik, insentif) **tidak** di-backup. Oasis
menghitungnya sendiri dari `trx_*` lewat kelas `Sumber*`.

Tiap entri transaksi mencatat: slug menu Marketama, `tableId` bila halaman punya lebih dari satu tabel, nama
parameter filter tanggal DataTables (mis. `start_date` / `end_date`, **perlu dicek per menu**), dan pemetaan kolom
sumber → kolom `lap_*`.

### 2. Format snapshot

```
storage/app/backup/marketama/20261026-0100/
├── manifest.json
├── mk_depo.csv.gz
├── mk_karyawan.csv.gz
├── ...
├── lap_penjualan_barang.csv.gz
└── tracking_sales_lokasi.csv.gz
→ marketama_backup_20261026-0100.zip
```

`manifest.json` memuat:
- `format`: versi format (mulai `1`), supaya restore menolak snapshot yang tidak dikenalnya.
- `sumber`: URL Marketama, waktu mulai/selesai, akun yang dipakai (username saja, tanpa password).
- `mode`: `penuh` atau `inkremental`, plus `sejak` / `sampai` untuk transaksi.
- Per tabel: menu sumber, jumlah baris di sumber (`recordsFiltered`), jumlah baris tertulis, daftar kolom, dan
  `sha256` file.

Satu CSV per tabel dengan **nama file = nama tabel**. Dengan begitu snapshot penuh tetap bisa dimuat oleh
`marketama:import` / `MarketamaDataSeeder` lama, seperti zip data awal.

### 3. Perintah backup: `php artisan oasis:backup-marketama`

```bash
php artisan oasis:backup-marketama                      # inkremental: transaksi sejak backup terakhir yang sukses
php artisan oasis:backup-marketama --penuh              # semua data (pertama kali / mingguan)
php artisan oasis:backup-marketama --sejak=2026-10-23 --sampai=2026-10-25
php artisan oasis:backup-marketama --hanya=lap_penjualan_barang --hanya=mk_outlet
php artisan oasis:backup-marketama --lanjut=20261026-0100   # lanjutkan snapshot yang terputus
php artisan oasis:backup-marketama --status
```

- Login sekali dengan `MARKETAMA_USERNAME` / `MARKETAMA_PASSWORD`. Tetap hanya GET, POST login, dan POST DataTables.
- Master tetap memakai `Penarik` (form edit untuk relasi ID), tapi hasilnya **juga ditulis ke file snapshot**,
  bukan hanya ke database.
- Transaksi ditarik per rentang tanggal lewat parameter filter DataTables. Inkremental default: dari tanggal akhir
  backup sukses terakhir dikurangi 2 hari, supaya data yang telat masuk atau diubah di Marketama ikut terambil.
  Upsert saat restore membuat tumpang-tindih ini aman.
- Posisi per tabel dicatat (memakai pola `mk_tarikan` dengan kunci `backup:<snapshot>:<tabel>`), jadi bisa
  dilanjutkan bila putus. Timeout tetap ditangani dengan memperkecil halaman (`halamanTahanTimeout`).
- Snapshot baru ditandai `selesai` dan di-zip setelah semua tabel lolos cek jumlah baris (tertulis = sumber).
  Selisih dicatat di manifest sebagai peringatan, bukan diam-diam diabaikan.

### 4. Perintah restore: `php artisan oasis:restore-marketama <snapshot|file.zip>`

```bash
php artisan oasis:restore-marketama 20261026-0100 --coba      # dry-run: validasi + hitung insert/update, tanpa menulis
php artisan oasis:restore-marketama marketama_backup_20261026-0100.zip
php artisan oasis:restore-marketama 20261026-0100 --mode=ganti --hanya=lap_stok_outlet
php artisan oasis:restore-marketama --kembalikan=pra-20261026-0105   # rollback ke backup pengaman
```

Urutan:
1. **Validasi.** Versi format dikenal, `sha256` cocok, setiap tabel ada di skema Oasis, dan kolom wajib (kunci upsert)
   ada. Snapshot yang belum `selesai` ditolak, kecuali dengan `--paksa`.
2. **Backup pengaman.** Dump tabel yang akan disentuh ke `storage/app/backup/oasis/pra-<waktu>/` dengan format CSV
   yang sama. Inilah yang dipakai `--kembalikan`.
3. **Muat master → `mk_*`, referensi, lalu transaksi → `lap_*`.** Mode `gabung` (default) melakukan **upsert per
   kunci** (`source_id` / `id` asli) dalam potongan 1.000 baris per transaksi DB, tanpa mengosongkan tabel. Mode
   `ganti` mengosongkan tabel itu dulu (perilaku `marketama:import` lama) dan hanya untuk tabel yang disebut di
   `--hanya`.
4. **Pipeline pasca-restore** dengan memanggil perintah yang sudah ada:
   `marketama:tarik sambung` (area_id outlet, karyawan_id user) → `enesis:sambung-transaksi` (kolom `*_id` di
   `lap_*`) → `enesis:migrasi-transaksi` (`lap_*` → `trx_*` dengan `sumber = lama`; idempotent, tidak menimpa data
   HP) → `enesis:sinkron-user`.
5. **Laporan.** Per tabel: baris baru, diperbarui, dilewati, gagal. Ringkasan disimpan ke tabel `oasis_restore_log`.

**Tabel milik Oasis tidak pernah disentuh restore.** Daftar penjaga `TABEL_MILIK_OASIS`: `users` (password, status,
role), `roles`, `role_hak`, `master_kuota_motoris`, `pengaturan_admin`, `hierarki_sales`, `mobile_*`, dan baris `trx_*`
dengan `sumber` selain `lama`. Restore menolak snapshot yang berisi tabel-tabel ini.

### 5. Jadwal masa transisi & cutover

- **Harian 01:00 WIB** (`routes/console.php`, `withoutOverlapping`): backup inkremental, lalu restore `gabung`.
  Hasilnya dikirim ke log/email admin.
- **Mingguan Minggu 02:00**: backup `--penuh` untuk master dan referensi, supaya karyawan/outlet/user yang diubah atau
  dihapus di Marketama ikut sinkron.
- **Cutover:** umumkan freeze input di Marketama → backup `--penuh` terakhir → restore → cek laporan (jumlah per
  tabel dan per hari cocok) → aplikasi HP diarahkan ke API Oasis → matikan jadwal backup Marketama.
  Snapshot terakhir disimpan permanen sebagai arsip.

### 6. Keamanan & beban server

- Penjaga baca-saja `MarketamaClient::postDiizinkan` tetap satu-satunya jalur POST. Tidak ada endpoint simpan,
  ubah, atau hapus yang bisa dipanggil.
- Kredensial hanya di `.env` server. Manifest hanya mencatat username.
- Snapshot berisi data pribadi: disimpan di `storage/app/backup` (sudah di-`.gitignore`), permission `0600`. Opsi
  `--sandi` mengenkripsi zip (AES-256), dan retensi default 14 snapshot (`--simpan=14`) lalu yang lama dihapus.
- Jeda antar permintaan (`MARKETAMA_JEDA_MS`, default 300 ms) dan jadwal di luar jam kerja, supaya Marketama tidak
  terbebani.

### 7. (Opsional, fase 2) Halaman admin "Backup & Restore"

Menu di grup Master Data & Pengaturan (hak `backup_marketama`, hanya super admin):
- daftar snapshot dengan status, jumlah baris per tabel, dan ukuran
- tombol "Backup sekarang" dan "Restore" yang berjalan lewat queue job
- unduh zip dan riwayat `oasis_restore_log`

Halaman ini disusun dari komponen UI yang ada (`DataTable`, `Card`, `Button`, `Badge`, `Modal`).

## User Stories

1. Sebagai admin Oasis, saya ingin data Marketama terbaru masuk ke Oasis setiap malam tanpa langkah manual,
   supaya laporan Oasis selalu sama dengan Marketama selama masa transisi.
2. Sebagai admin, saya ingin setiap backup tersimpan sebagai file zip lengkap dengan manifest, supaya bisa dipindah
   ke server lain atau dipakai ulang untuk restore.
3. Sebagai admin, saya ingin restore bisa dijalankan berulang tanpa membuat data dobel, supaya aman bila jadwal gagal
   lalu diulang.
4. Sebagai admin, saya ingin restore tidak pernah menimpa akun, password, role, kuota, dan transaksi HP yang dibuat di
   Oasis.
5. Sebagai admin, saya ingin melihat dulu dampak restore (`--coba`) dan bisa membatalkannya (`--kembalikan`), supaya
   kesalahan bisa dipulihkan.
6. Sebagai pemilik server Marketama, saya ingin backup hanya membaca dan diberi jeda, supaya aplikasi lama tidak
   terganggu.
7. Sebagai developer, saya ingin backup yang putus di tengah bisa dilanjutkan, karena tarikan penuh bisa makan waktu
   berjam-jam.

## Implementation Decisions

- **Kelas baru** di `app/Support/Backup/`: `KatalogBackup` (membaca `config/oasis_backup.php`), `PenulisSnapshot`
  (CSV.gz + manifest + checksum), `PembacaSnapshot` (validasi + iterasi baris), `PemulihData` (upsert per potongan),
  `BackupPengaman` (dump dan kembalikan). Perintah: `OasisBackupMarketama`, `OasisRestoreMarketama`.
- `Penarik::daftar()` dipakai ulang. Closure `$simpan` diarahkan ke `PenulisSnapshot` alih-alih langsung ke DB.
  `Penarik` hanya perlu satu tambahan: parameter `$extra` (filter tanggal) yang sudah ada diteruskan dari katalog.
- **Kunci kolom transaksi:** `HalamanParser::baris()` sekarang menyusun kunci dari judul kolom HTML, sedangkan
  `lap_*` memakai nama field asli (`salesperson_name`, `warehouse_name`, …). Pilihan utama: bila respons DataTables
  berupa objek bernama (field asli), **pakai nama field langsung** dan jangan diratakan dengan `array_values`.
  Cadangan: pemetaan judul → kolom per menu di katalog.
- Upsert memakai `DB::table()->upsert()` (MySQL, PostgreSQL, dan SQLite didukung Laravel) dengan kunci per tabel.
  Migration baru menambah **indeks unik** `source_id` di tabel `lap_*` / `tracking_*` yang dipakai, dengan
  pengecekan duplikat dulu.
- `CsvTableLoader` tidak diubah perilakunya (tetap truncate untuk `marketama:import`). Restore memakai jalur upsert
  sendiri.
- Tabel log baru `oasis_restore_log` (snapshot, mode, mulai/selesai, ringkasan JSON per tabel, status).

## Testing Decisions

- `Http::fake()` untuk halaman login, halaman menu (token + URL DataTables + thead), dan respons DataTables
  berhalaman. Uji bahwa backup menulis CSV.gz dan manifest dengan jumlah baris serta sha256 yang benar, dan bahwa
  backup bisa dilanjutkan setelah gagal di halaman ke-2.
- Uji penjaga baca-saja: katalog yang menunjuk endpoint non-DataTables ditolak (`postDiizinkan`).
- Restore dari snapshot fixture kecil di `tests/Fixtures/backup/`:
  - restore dua kali menghasilkan jumlah baris yang sama (idempotent)
  - baris yang berubah di snapshot kedua ter-update
  - `trx_*` ber-`sumber` HP dan tabel `TABEL_MILIK_OASIS` tidak berubah
  - checksum salah atau format tidak dikenal ditolak
  - `--coba` tidak menulis apa pun
  - `--kembalikan` memulihkan isi tabel persis
- Uji pipeline: setelah restore, `trx_penjualan` berisi baris `sumber = lama` dari `lap_penjualan_barang` fixture.

## Out of Scope

- Menulis balik data Oasis ke Marketama (sinkron dua arah). Klien tetap baca saja.
- Backup database Oasis secara umum (mysqldump/pg_dump penuh untuk disaster recovery). Itu tugas server/hosting.
  Backup pengaman di sini hanya untuk tabel yang disentuh restore.
- Menu yang tidak dibawa ke Oasis (Warehouse, penggajian, dll.).
- File foto/lampiran Marketama (selfie absensi, foto outlet). Hanya URL-nya yang ikut. Mengunduh file bisa jadi item
  terpisah bila dibutuhkan.

## Further Notes / Perlu dicek ke Marketama sebelum implementasi

1. **Bentuk respons DataTables menu transaksi:** objek bernama atau array posisi? Ini menentukan apakah pemetaan
   kolom diperlukan. Cek dengan satu permintaan `--batas=5`.
2. **Nama parameter filter tanggal** per menu laporan (dari JS halaman). Bila ada menu tanpa filter tanggal, menu
   itu hanya bisa ditarik penuh.
3. **ID baris transaksi** (`data-id` / kolom pertama) harus ada untuk kunci upsert `source_id`. Bila tidak ada, pakai
   kunci gabungan (mis. `trans_code`, atau orang + waktu).
4. **Perkiraan waktu:** ±1,7 juta baris detail penjualan ÷ 100 baris/halaman ≈ 17 ribu permintaan. Dengan jeda
   300 ms dan respons ±0,3–0,7 dtk, tarikan penuh ±3–5 jam. Inkremental harian hanya beberapa menit.
5. Perlu izin/persetujuan pemilik Marketama untuk penarikan terjadwal memakai akun admin.
