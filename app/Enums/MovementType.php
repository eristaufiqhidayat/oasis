<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Jenis perpindahan stok. */
enum MovementType: string
{
    use HasOptions;

    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case Load = 'load';
    case Unload = 'unload';
    case Sale = 'sale';
    case SaleVoid = 'sale_void';
    case CustomerReturn = 'customer_return';
    case WriteOff = 'write_off';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Penerimaan',
            self::Adjustment => 'Penyesuaian',
            self::Load => 'Muat ke kendaraan',
            self::Unload => 'Bongkar ke gudang',
            self::Sale => 'Penjualan',
            self::SaleVoid => 'Batal jual',
            self::CustomerReturn => 'Retur outlet',
            self::WriteOff => 'Dihapuskan (selisih)',
        };
    }
}
