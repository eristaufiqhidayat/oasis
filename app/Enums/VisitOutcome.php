<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Hasil kunjungan. */
enum VisitOutcome: string
{
    use HasOptions;

    case Sale = 'sale';
    case NoSale = 'no_sale';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Terjadi penjualan',
            self::NoSale => 'Tidak beli',
            self::Closed => 'Toko tutup',
        };
    }
}
