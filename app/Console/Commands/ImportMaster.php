<?php

namespace App\Console\Commands;

use App\Support\Import\LegacySource;
use App\Support\Import\MasterImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oasis:impor-master
    {--csv= : Folder CSV (satu file per tabel: mk_depo.csv, mk_area.csv, …)}
    {--koneksi=legacy : Koneksi database aplikasi lama (LEGACY_DB_* di .env) bila --csv tidak diisi}
    {--only=* : Tahap tertentu saja: depo, area, karyawan, outlet, produk, akun}')]
#[Description('Impor data master (depo, area, karyawan, outlet, produk, akun) dari aplikasi lama ke struktur Oasis')]
class ImportMaster extends Command
{
    public function handle(): int
    {
        try {
            $source = $this->option('csv') ? LegacySource::csv($this->option('csv')) : LegacySource::database($this->option('koneksi'));
        } catch (Throwable $e) {
            $this->error('Sumber data tidak bisa dibuka: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sumber: '.$source->describe());
        $importer = new MasterImporter($source, fn (string $m) => $this->line($m));
        $stats = $importer->run($this->option('only'));

        $this->table(['Tahap', 'Baru', 'Diperbarui', 'Dilewati'], collect($stats)->map(fn ($s, $k) => [$k, $s['baru'], $s['diperbarui'], $s['dilewati']])->values());
        $this->comment('Outlet tanpa area/depo dan akun tanpa password dilewati. Atur harga per depo dan rute di menu Master.');

        return self::SUCCESS;
    }
}
