<?php

namespace App\Support\Backup;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

// Menyalin isi tabel dari database lain (Enesis) ke database Oasis (koneksi default).
// - Hanya tabel yang ada di kedua database, dan hanya kolom yang ada di keduanya.
// - Isi tabel Oasis yang disalin dikosongkan dulu, lalu diisi ulang dengan ID asli (relasi tetap utuh).
// - Dibaca bertahap supaya tabel jutaan baris tidak memenuhi memori.
class SalinDatabase
{
    /** Tabel sistem yang tidak disalin (sesi, cache, antrean, riwayat migration). */
    public const LEWAT = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens',
    ];

    /** Tabel inti (akun, role, pengaturan) disalin paling awal supaya tidak tertunda tabel transaksi yang besar. */
    public const DULUAN = [
        'roles', 'role_hak', 'users', 'hierarki_sales', 'pengaturan_admin', 'master_kuota_motoris', 'mobile_tokens',
    ];

    /** Batas placeholder per INSERT (aman untuk MySQL, PostgreSQL, dan SQLite). */
    private const MAKS_PLACEHOLDER = 30000;

    /** @var Closure(string): void */
    private Closure $log;

    /** @var list<string> */
    private array $hanyaDiSumber = [];

    /** @var array<string, string> tabel gagal => pesan error */
    private array $gagal = [];

    public function __construct(private readonly string $koneksiSumber, ?Closure $log = null)
    {
        $this->log = $log ?? fn (string $pesan) => null;
    }

    /**
     * Tabel yang akan disalin: ada di sumber dan di Oasis, bukan tabel sistem.
     *
     * @param  list<string>  $hanya
     * @param  list<string>  $kecuali
     * @return list<string>
     */
    public function daftarTabel(array $hanya = [], array $kecuali = []): array
    {
        $sumber = $this->namaTabel($this->koneksiSumber);
        $tujuan = $this->namaTabel(null);

        $this->hanyaDiSumber = array_values(array_diff($sumber, $tujuan, self::LEWAT));

        $tabel = array_values(array_diff(array_intersect($sumber, $tujuan), self::LEWAT, $kecuali));

        if ($hanya) {
            $tabel = array_values(array_intersect($tabel, $hanya));
        }

        sort($tabel);

        $duluan = array_values(array_intersect(self::DULUAN, $tabel));

        return [...$duluan, ...array_values(array_diff($tabel, $duluan))];
    }

    /** @return list<string> tabel di Enesis yang tidak ada di Oasis (menu yang tidak dibawa) */
    public function tabelHanyaDiSumber(): array
    {
        return $this->hanyaDiSumber;
    }

    /** @return array<string, string> tabel yang gagal disalin pada jalankan() terakhir => pesan error */
    public function gagal(): array
    {
        return $this->gagal;
    }

    public function jumlah(?string $koneksi, string $tabel): int
    {
        return DB::connection($koneksi)->table($tabel)->count();
    }

    /**
     * @param  list<string>  $tabel
     * @return array<string, int> jumlah baris tersalin per tabel
     */
    public function jalankan(array $tabel): array
    {
        $hasil = [];
        $this->gagal = [];

        Schema::disableForeignKeyConstraints();

        try {
            // Satu tabel gagal tidak menghentikan tabel lain; daftar gagal dilaporkan di akhir.
            foreach ($tabel as $t) {
                try {
                    $hasil[$t] = $this->salinTabel($t);
                } catch (Throwable $e) {
                    $this->gagal[$t] = $e->getMessage();
                    ($this->log)("{$t}: GAGAL - ".mb_strimwidth($e->getMessage(), 0, 500, '...'));
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $hasil;
    }

    private function salinTabel(string $tabel): int
    {
        $sumber = DB::connection($this->koneksiSumber);
        $tujuan = DB::connection();
        $kolom = array_values(array_intersect(
            Schema::connection($this->koneksiSumber)->getColumnListing($tabel),
            Schema::getColumnListing($tabel),
        ));

        if (! $kolom) {
            ($this->log)("{$tabel}: tidak ada kolom yang sama, dilewati");

            return 0;
        }

        $mulai = microtime(true);
        $tujuan->table($tabel)->truncate();

        $perInsert = max(1, min(1000, intdiv(self::MAKS_PLACEHOLDER, count($kolom))));
        $antre = [];
        $jumlah = 0;

        foreach ($this->baca($sumber, $tabel, $kolom) as $baris) {
            $antre[] = (array) $baris;

            if (count($antre) >= $perInsert) {
                $tujuan->table($tabel)->insert($antre);
                $jumlah += count($antre);
                $antre = [];
            }
        }

        if ($antre) {
            $tujuan->table($tabel)->insert($antre);
            $jumlah += count($antre);
        }

        $this->setelUlangUrutan($tujuan, $tabel, $kolom);

        ($this->log)(sprintf('%s: %s baris (%.1f detik)', $tabel, number_format($jumlah, 0, ',', '.'), microtime(true) - $mulai));

        return $jumlah;
    }

    /**
     * Baca bertahap: per ID bila kunci utamanya "id" saja; selain itu kursor tanpa buffer (MySQL)
     * atau halaman berurutan per semua kolom kunci.
     *
     * @param  list<string>  $kolom
     */
    private function baca(Connection $sumber, string $tabel, array $kolom): iterable
    {
        $kunci = $this->kunciUtama($tabel);
        $query = $sumber->table($tabel)->select($kolom);

        if ($kunci === ['id']) {
            return $query->lazyById(2000, 'id');
        }

        if ($sumber->getDriverName() === 'mysql' || $sumber->getDriverName() === 'mariadb') {
            return (function () use ($sumber, $query) {
                $pdo = $sumber->getPdo();
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

                try {
                    yield from $query->cursor();
                } finally {
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                }
            })();
        }

        foreach ($kunci ?: $kolom as $k) {
            $query->orderBy($k);
        }

        return $query->lazy(2000);
    }

    /** @return list<string> kolom kunci utama tabel di sumber (kosong bila tidak ada) */
    private function kunciUtama(string $tabel): array
    {
        foreach (Schema::connection($this->koneksiSumber)->getIndexes($tabel) as $indeks) {
            if ($indeks['primary']) {
                return $indeks['columns'];
            }
        }

        return [];
    }

    /** PostgreSQL: setel ulang sequence "id" supaya baris baru tidak bentrok dengan ID yang disalin. */
    private function setelUlangUrutan(Connection $tujuan, string $tabel, array $kolom): void
    {
        if ($tujuan->getDriverName() !== 'pgsql' || ! in_array('id', $kolom, true)) {
            return;
        }

        $urutan = $tujuan->selectOne('select pg_get_serial_sequence(?, ?) as s', [$tabel, 'id'])?->s;

        if ($urutan) {
            $tujuan->statement("select setval(?, coalesce((select max(id) from \"{$tabel}\"), 1), (select count(*) > 0 from \"{$tabel}\"))", [$urutan]);
        }
    }

    /** @return list<string> */
    private function namaTabel(?string $koneksi): array
    {
        $schema = Schema::connection($koneksi);
        $driver = DB::connection($koneksi)->getDriverName();

        return array_values(array_unique(array_map(
            fn ($t) => $t['name'],
            array_filter($schema->getTables(), fn ($t) => $driver !== 'pgsql' || ($t['schema'] ?? 'public') === 'public'),
        )));
    }
}
