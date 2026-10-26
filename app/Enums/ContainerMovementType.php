<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Pergerakan galon di outlet. */
enum ContainerMovementType: string
{
    use HasOptions;

    case Delivered = 'delivered';
    case Collected = 'collected';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Delivered => 'Galon isi dikirim',
            self::Collected => 'Galon kosong ditarik',
            self::Adjustment => 'Koreksi saldo',
        };
    }
}
