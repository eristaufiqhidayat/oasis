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
    private function __construct(private ?string $connection, private ?string $folder) {}

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

    /** @return Generator<int, array<string, mixed>> */
    public function rows(string $table): Generator
    {
        if (! $this->has($table)) {
            return;
        }

        if ($this->connection) {
            $query = DB::connection($this->connection)->table($table);

            // Tabel besar (mk_outlet bisa ratusan ribu baris): ambil per blok berdasarkan id, bukan OFFSET
            // yang makin lambat di blok belakang.
            $rows = Schema::connection($this->connection)->hasColumn($table, 'id')
                ? $query->lazyById(2000, 'id')
                : $query->orderBy(DB::raw('1'))->lazy(2000);

            foreach ($rows as $row) {
                yield (array) $row;
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
            yield array_map(fn ($v) => $v === '' || $v === 'NULL' ? null : $v, $row);
        }

        fclose($handle);
    }

    public function describe(): string
    {
        return $this->connection ? "database ({$this->connection})" : "folder {$this->folder}";
    }
}
