<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Penerima skema insentif. */
enum IncentiveTarget: string
{
    use HasOptions;

    case Salesman = 'salesman';
    case Coordinator = 'coordinator';

    public function label(): string
    {
        return match ($this) {
            self::Salesman => 'Sales motoris',
            self::Coordinator => 'Koordinator',
        };
    }
}
