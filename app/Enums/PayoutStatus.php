<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Status pembayaran insentif. */
enum PayoutStatus: string
{
    use HasOptions;

    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::Paid => 'Dibayar',
        };
    }
}
