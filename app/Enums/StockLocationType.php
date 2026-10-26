<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Jenis lokasi persediaan. */
enum StockLocationType: string
{
    use HasOptions;

    case Warehouse = 'warehouse';
    case Vehicle = 'vehicle';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Gudang',
            self::Vehicle => 'Kendaraan',
            self::Damaged => 'Barang rusak',
        };
    }
}
