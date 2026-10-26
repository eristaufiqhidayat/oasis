<?php

namespace App\Support\Import;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Sumber data aplikasi lama (Marketama/Oasis lama): koneksi database "legacy" atau folder CSV
 * (satu file per tabel, nama file = nama tabel, baris pertama = header).
 */
class LegacySource
{
    /** @var (\Closure(string): void)|null */
    public ?\Closure $logger = null;

    private function __construct(private ?string $connection, private ?string $folder) {}

    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }

    public static function database(string $connection = 'legacy'): self
    {
        DB::connection($connection)->getPdo();

        return new self($connection, null);
    }

    public static function csv(string $folder): self
    {
        if (! is_dir($folder)) {
            throw new RuntimeException("Folder {$folder} tidak ditemukan.");
        }

        return new self(null, rtrim($folder, '/'));
    }

    public function has(string $table): bool
    {
        return $this->connection
            ? Schema::connection($this->connection)->hasTable($table)
            : is_file("{$this->folder}/{$table}.csv");
    }

    /**
     * Baris tabel sumber. Hanya kolom $columns yang diambil (kolom besar seperti JSON mentah tidak ikut);
     * kolom yang tidak ada di sumber diisi null.
     *
     * @param  array<string>  $columns
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(string $table, array $columns): Generator
    {
        if (! $this->has($table)) {
            return;
        }

        $blank = array_fill_keys($columns, null);

        if ($this->connection) {
            $schema = Schema::connection($this->connection);
            $listing = array_map('strtolower', $schema->getColumnListing($table));
            $available = array_values(array_filter($columns, fn ($c) => in_array(strtolower($c), $listing, true)));

            // Daftar kolom tidak terbaca (mis. skema berbeda): tetap pilih kolom yang diminta, jangan SELECT *.
            if ($available === []) {
                $available = $columns;
            }

            $this->log("  {$table}: kolom ".implode(', ', $available));
            $query = DB::connection($this->connection)->table($table)->select($available);

            // Tabel besar (mk_outlet bisa ratusan ribu baris): ambil per blok berdasarkan id, bukan OFFSET
            // yang makin lambat di blok belakang.
            $rows = in_array('id', $available, true)
                ? $query->lazyById(500, 'id')
                : $query->orderBy($available[0])->lazy(500);

            foreach ($rows as $row) {
                yield (array) $row + $blank;
            }

            return;
        }

        $handle = fopen("{$this->folder}/{$table}.csv", 'r');
        $header = fgetcsv($handle, escape: '\\');

        if (! $header) {
            fclose($handle);

            return;
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            if ($values === [null]) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
            yield array_intersect_key(array_map(fn ($v) => $v === '' || $v === 'NULL' ? null : $v, $row), $blank) + $blank;
        }

        fclose($handle);
    }

    public function describe(): string
    {
        return $this->connection ? "database ({$this->connection})" : "folder {$this->folder}";
    }
}
