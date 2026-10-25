<?php

namespace Database\Seeders;

use App\Support\CsvTableLoader;
use Illuminate\Database\Seeder;

// Data referensi tanpa data pribadi yang dipakai menu Oasis / aplikasi motoris (depo, libur, tipe outlet, rumus, dll).
// File CSV ada di database/seeders/data.
class ReferenceDataSeeder extends Seeder
{
    public const TABLES = [
        'produk_kompetitor',
        'master_depo',
        'holiday_dates',
        'tipe_outlet',
        'sales_point_conversion_per_warehouse',
        'waktu_aktif_app',
        'rumus_insentif_sales',
    ];

    public function run(): void
    {
        foreach (self::TABLES as $table) {
            $rows = CsvTableLoader::load($table, database_path("seeders/data/{$table}.csv"));
            $this->command?->info("{$table}: {$rows} baris");
        }
    }
}
