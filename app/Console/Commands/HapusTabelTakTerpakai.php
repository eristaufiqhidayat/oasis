<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

// Hapus tabel di database Oasis yang tidak dibuat oleh migration Oasis (mis. tabel menu Enesis/Marketama
// yang tidak dibawa, sisa impor dump Enesis). Skema yang seharusnya dihitung dengan menjalankan semua
// migration Oasis ke database SQLite sementara di memori. Tanpa --hapus hanya menampilkan daftar.
class HapusTabelTakTerpakai extends Command
{
    protected $signature = 'oasis:hapus-tabel-tak-terpakai
                            {--hapus : Benar-benar hapus (tanpa ini hanya menampilkan daftar)}
                            {--kecuali=* : Jangan hapus tabel ini (boleh berulang)}
                            {--tanpa-cadangan : Jangan simpan isi tabel ke CSV sebelum dihapus}
                            {--paksa : Jangan minta konfirmasi}';

    protected $description = 'Hapus tabel di database Oasis yang tidak dipakai (tidak dibuat migration Oasis), dengan cadangan CSV';

    public function handle(): int
    {
        $dipakai = $this->tabelSkemaOasis();
        $ada = $this->tabelDatabase();
        $hapus = array_values(array_diff($ada, $dipakai, ['migrations'], $this->option('kecuali')));
        sort($hapus);

        $db = DB::connection()->getDatabaseName();

        if (! $hapus) {
            $this->info("Tidak ada tabel tak terpakai di {$db}. ".count($ada).' tabel, semuanya dipakai Oasis.');

            return self::SUCCESS;
        }

        $this->table(['Tabel tak terpakai', 'Jumlah baris'], array_map(
            fn ($t) => [$t, number_format(DB::table($t)->count(), 0, ',', '.')],
            $hapus,
        ));
        $this->line(sprintf('%d tabel tak terpakai dari %d tabel di %s (%d tabel dipakai Oasis tetap).', count($hapus), count($ada), $db, count(array_intersect($ada, $dipakai))));

        if (! $this->option('hapus')) {
            $this->line('Belum ada yang dihapus. Jalankan dengan --hapus untuk menghapus (isi tabel dicadangkan ke CSV dulu).');

            return self::SUCCESS;
        }

        if (! $this->option('paksa') && ! $this->confirm(count($hapus)." tabel di atas akan DIHAPUS dari {$db}. Lanjutkan?")) {
            $this->warn('Dibatalkan, tidak ada tabel yang dihapus. Ketik "yes" saat konfirmasi, atau pakai --paksa.');

            return self::FAILURE;
        }

        $folder = null;
        if (! $this->option('tanpa-cadangan')) {
            $folder = storage_path('app/backup/tabel-dihapus/'.now()->format('Ymd-His'));
            @mkdir($folder, 0700, true);
        }

        $gagal = [];
        foreach ($hapus as $t) {
            try {
                if ($folder) {
                    $baris = $this->cadangkan($t, "{$folder}/{$t}.csv.gz");
                    $this->line("{$t}: {$baris} baris dicadangkan");
                }

                Schema::drop($t);
                $this->line("<fg=green>{$t}: dihapus</>");
            } catch (Throwable $e) {
                $gagal[$t] = $e->getMessage();
                $this->error("{$t}: GAGAL - ".mb_strimwidth($e->getMessage(), 0, 300, '...'));
            }
        }

        $this->info(sprintf('Selesai: %d tabel dihapus.', count($hapus) - count($gagal)));
        if ($folder) {
            $this->line("Cadangan CSV: {$folder} (bisa dimuat lagi dengan: php artisan marketama:import {$folder} setelah tabelnya dibuat ulang).");
        }

        return $gagal ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> tabel yang dibuat oleh migration Oasis */
    private function tabelSkemaOasis(): array
    {
        $koneksi = 'skema_oasis_sementara';
        config(["database.connections.{$koneksi}" => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge($koneksi);

        try {
            if (Artisan::call('migrate', ['--database' => $koneksi, '--force' => true]) !== 0) {
                throw new RuntimeException('Gagal menghitung skema Oasis: '.Artisan::output());
            }

            return array_map(fn ($t) => $t['name'], Schema::connection($koneksi)->getTables());
        } finally {
            DB::purge($koneksi);
        }
    }

    /** @return list<string> */
    private function tabelDatabase(): array
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';

        return array_values(array_unique(array_map(
            fn ($t) => $t['name'],
            array_filter(Schema::getTables(), fn ($t) => ! $pgsql || ($t['schema'] ?? 'public') === 'public'),
        )));
    }

    /** Simpan isi tabel ke CSV.gz (baris pertama = nama kolom). */
    private function cadangkan(string $tabel, string $file): int
    {
        $kolom = Schema::getColumnListing($tabel);
        $gz = gzopen($file, 'wb6');
        $tulis = function (array $baris) use ($gz) {
            $f = fopen('php://temp', 'r+');
            fputcsv($f, $baris, ',', '"', '');
            rewind($f);
            gzwrite($gz, stream_get_contents($f));
            fclose($f);
        };

        $tulis($kolom);
        $jumlah = 0;
        $query = DB::table($tabel)->select($kolom);
        $kunci = collect(Schema::getIndexes($tabel))->firstWhere('primary', true)['columns'] ?? [];

        if ($kunci === ['id']) {
            $baris = $query->lazyById(2000, 'id');
        } else {
            foreach ($kunci ?: $kolom as $k) {
                $query->orderBy($k);
            }
            $baris = $query->lazy(2000);
        }

        foreach ($baris as $b) {
            $tulis(array_map(fn ($v) => is_bool($v) ? (int) $v : $v, array_values((array) $b)));
            $jumlah++;
        }

        gzclose($gz);
        @chmod($file, 0600);

        return $jumlah;
    }
}
