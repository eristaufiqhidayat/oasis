<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Alasan retur outlet. */
enum ReturnReason: string
{
    use HasOptions;

    case Damaged = 'damaged';
    case Expired = 'expired';
    case Leaking = 'leaking';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Rusak/penyok',
            self::Expired => 'Kedaluwarsa',
            self::Leaking => 'Bocor',
            self::Other => 'Lainnya',
        };
    }
}
