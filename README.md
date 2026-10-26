# Oasis Canvassing

Aplikasi canvassing PT Oasis Waters International: motoris membawa barang dari depo, berjualan ke outlet lewat aplikasi HP, lalu kembali untuk bongkar dan setoran. Web dipakai gudang, kasir, kepala depo, koordinator, dan admin pusat.

Laravel 13 (PHP 8.3) + Inertia.js + Vue 3 + Tailwind 4. Database MySQL/MariaDB atau PostgreSQL (SQLite untuk lokal/tes).

## Proses bisnis

```
Gudang terima barang ──► Stok gudang depo
        │
        ▼
Muat ke kendaraan motoris (Muatan Harian)  ── stok pindah: gudang → kendaraan
        │
        ▼  HP: absen, berangkat, kunjungi outlet (rute hari ini)
Penjualan tunai/transfer · Retur outlet · Tarik galon kosong · Outlet baru · GPS
        │
        ▼  HP: selesai
Gudang bongkar: hitung sisa layak / rusak + galon kosong  ── selisih = barang kurang
        │
        ▼
Kasir catat uang setoran  ──►  Kepala depo setujui rekonsiliasi (putuskan selisih)  ──►  Muatan selesai
        │
        ▼
Target bulanan ──► Pencapaian ──► Insentif (perkiraan → disetujui → dibayar, potongan dari selisih)
```

| Menu | Untuk siapa | Isi |
| --- | --- | --- |
| Beranda | semua | Penjualan & kunjungan hari ini, tren 14 hari, produk terlaris, hal yang perlu ditindaklanjuti |
| Lapangan › Muatan Harian | gudang, kepala depo | Buat muatan, batal muat, bongkar |
| Lapangan › Penjualan / Retur / Kunjungan / Absensi / Pelacakan GPS | koordinator, kepala depo | Pantau kegiatan motoris; batalkan penjualan yang salah |
| Lapangan › Verifikasi Outlet Baru | koordinator, kepala depo | Setujui/tolak outlet yang didaftarkan dari HP |
| Gudang › Stok / Penerimaan & Opname / Mutasi | gudang | Saldo per lokasi, terima barang, koreksi opname, buku besar |
| Keuangan › Setoran & Rekonsiliasi | kasir, kepala depo | Uang wajib setor vs diterima, selisih barang & galon, persetujuan |
| Kinerja › Target / Pencapaian / Insentif | admin pusat, kepala depo | Target per depo/motoris/SKU, proyeksi, perhitungan dan pembayaran insentif |
| Laporan | manajemen | Penjualan (per hari/produk/motoris/outlet/depo, CSV), saldo galon outlet, kinerja kunjungan |
| Master Data | admin | Wilayah, depo, area (polygon), karyawan, outlet, rute, saluran, produk, kategori, harga |
| Pengaturan | admin | Pengguna & cakupan depo, peran & izin, skema insentif, log aktivitas |

Penjelasan tabel ada di [docs/DATA_MODEL.md](docs/DATA_MODEL.md), API HP di [docs/API.md](docs/API.md).

## Menjalankan di lokal

```bash
composer setup                      # install, .env, key, migrate, npm install + build
php artisan db:seed                 # peran, akun admin, saluran, kategori, skema insentif
php artisan db:seed --class=DemoSeeder   # opsional: data contoh 2 depo (semua akun demo: password "password")
composer dev                        # server + vite
```

Masuk dengan `admin` / `password` (wajib diganti saat pertama masuk). Akun demo: `bdg.kd` (kepala depo), `bdg.gd` (gudang), `bdg.ks` (kasir), `bdg.ko` (koordinator); motoris HP `bdgm1`…`bdgm3`.

```bash
composer test          # PHPUnit (SQLite in-memory)
vendor/bin/pint        # gaya kode
npm test               # Vitest untuk komponen Vue
```

## Memasang di server

Server tanpa Node.js memakai branch `deploy` (berisi `public/build` hasil GitHub Actions setiap push ke `main`).

Aplikasi ini **dibangun ulang dengan struktur database baru**, jadi pakai **database baru** (jangan database Oasis lama):

```bash
git fetch origin deploy && git reset --hard origin/deploy
composer install --no-dev --optimize-autoloader
# .env: DB_DATABASE=<database baru>, LEGACY_DB_DATABASE=<database lama>, APP_LOCALE=id
php artisan migrate --force
php artisan db:seed --force
php artisan oasis:buat-admin admin            # set password admin
php artisan oasis:impor-master                # depo, area+polygon, karyawan, outlet, produk, akun dari database lama
php artisan optimize:clear && php artisan optimize
```

Produk yang namanya tidak memuat "Oasis" (material/merek lain) tidak ikut diimpor. Untuk membersihkan produk non-Oasis yang sudah terlanjur masuk:

```bash
php artisan oasis:hapus-produk-non-oasis              # lihat daftar dulu (tidak mengubah apa pun)
php artisan oasis:hapus-produk-non-oasis --jalankan   # hapus; --kecuali=SKU untuk yang tetap disimpan
```

Produk tanpa riwayat transaksi dihapus permanen. Produk yang sudah punya riwayat (mutasi, muatan, penjualan) dinonaktifkan dan stok gudangnya dinolkan lewat dokumen penyesuaian, supaya laporan lama tetap utuh.

`oasis:impor-master` bisa diulang (data dicocokkan lewat `legacy_id`), atau membaca CSV: `--csv=/path/folder` (file `mk_depo.csv`, `mk_area.csv`, `area_polygon.csv`, `mk_karyawan.csv`, `mk_outlet.csv`, `mk_produk.csv`, `users.csv`). Password akun lama ikut terbawa. Transaksi lama tidak diimpor.

Setelah impor: isi **harga per depo** (Master › Harga Jual), **rute** motoris, **stok awal** (Gudang › Penerimaan), **target bulan berjalan**, dan cek **skema insentif**.

Jadwal harian (`php artisan schedule:run` di cron setiap menit) menjalankan `oasis:bersihkan`: hapus token HP kedaluwarsa dan jejak GPS > 180 hari.
