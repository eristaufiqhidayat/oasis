<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Jabatan karyawan. */
enum Position: string
{
    use HasOptions;

    case Salesman = 'salesman';
    case Coordinator = 'coordinator';
    case DepotHead = 'depot_head';
    case Warehouse = 'warehouse';
    case Cashier = 'cashier';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Salesman => 'Sales Motoris',
            self::Coordinator => 'Koordinator',
            self::DepotHead => 'Kepala Depo',
            self::Warehouse => 'Gudang',
            self::Cashier => 'Kasir',
            self::Admin => 'Admin',
        };
    }
}
