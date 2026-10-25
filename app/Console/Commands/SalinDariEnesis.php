<?php

namespace App\Console\Commands;

use App\Support\Backup\SalinDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

// Salin data terbaru dari database Enesis yang sedang berjalan ke Oasis (users, role, trx_*, mk_*,
// kuota, tracking, dst.). Hanya tabel yang ada di kedua database; kolom yang tidak ada di Oasis diabaikan.
class SalinDariEnesis extends Command
{
    protected $signature = 'oasis:salin-dari-enesis
                            {--tabel=* : Hanya tabel ini (boleh berulang)}
                            {--kecuali=* : Lewati tabel ini (boleh berulang)}
                            {--koneksi=enesis : Nama koneksi database sumber (config/database.php)}
                            {--coba : Hanya tampilkan jumlah baris sumber vs Oasis, tanpa menyalin}
                            {--paksa : Jangan minta konfirmasi}';

    protected $description = 'Salin data terbaru dari database Enesis ke Oasis (isi tabel Oasis yang disalin diganti)';

    public function handle(): int
    {
        $koneksi = (string) $this->option('koneksi');

        try {
            DB::connection($koneksi)->getPdo();
        } catch (Throwable $e) {
            $this->error("Tidak bisa terhubung ke database sumber ({$koneksi}): {$e->getMessage()}");
            $this->line('Atur ENESIS_DB_DATABASE (dan bila perlu ENESIS_DB_HOST/USERNAME/PASSWORD) di .env.');

            return self::FAILURE;
        }

        $salin = new SalinDatabase($koneksi, fn (string $pesan) => $this->line($pesan));
        $tabel = $salin->daftarTabel($this->option('tabel'), $this->option('kecuali'));

        if (! $tabel) {
            $this->warn('Tidak ada tabel yang sama di kedua database.');

            return self::FAILURE;
        }

        $this->table(['Tabel', 'Baris di Enesis', 'Baris di Oasis sekarang'], array_map(
            fn ($t) => [$t, number_format($salin->jumlah($koneksi, $t), 0, ',', '.'), number_format($salin->jumlah(null, $t), 0, ',', '.')],
            $tabel,
        ));

        if ($lewat = $salin->tabelHanyaDiSumber()) {
            $this->line('<fg=gray>Ada di Enesis tapi tidak dipakai Oasis (dilewati): '.implode(', ', $lewat).'</>');
        }

        if ($this->option('coba')) {
            return self::SUCCESS;
        }

        $db = DB::connection()->getDatabaseName();
        if (! $this->option('paksa') && ! $this->confirm('Isi '.count($tabel)." tabel di database Oasis ({$db}) akan DIGANTI dengan data Enesis. Lanjutkan?")) {
            return self::FAILURE;
        }

        try {
            $hasil = $salin->jalankan($tabel);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Selesai: %d tabel, %s baris disalin.', count($hasil), number_format(array_sum($hasil), 0, ',', '.')));
        $this->line('Jalankan php artisan optimize:clear bila perlu. Pengguna perlu login ulang (sesi web tidak disalin).');

        return self::SUCCESS;
    }
}
