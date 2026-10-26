<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Cara bayar outlet. */
enum PaymentMethod: string
{
    use HasOptions;

    case Cash = 'cash';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer',
        };
    }
}
