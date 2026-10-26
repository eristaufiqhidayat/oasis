<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Status penjualan. */
enum SaleStatus: string
{
    use HasOptions;

    case Completed = 'completed';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai',
            self::Void => 'Dibatalkan',
        };
    }
}
