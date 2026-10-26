<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Metrik target bulanan. */
enum TargetMetric: string
{
    use HasOptions;

    case Revenue = 'revenue';
    case EffectiveCalls = 'effective_calls';
    case Quantity = 'quantity';

    public function label(): string
    {
        return match ($this) {
            self::Revenue => 'Nilai penjualan',
            self::EffectiveCalls => 'Effective call',
            self::Quantity => 'Qty per SKU',
        };
    }
}
