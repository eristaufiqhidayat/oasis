<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Periode bulanan "YYYY-MM" → rentang tanggal. */
class Period
{
    public static function parse(?string $value): CarbonImmutable
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            return CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} awal dan akhir bulan (inklusif). */
    public static function range(CarbonImmutable $period): array
    {
        return [$period->startOfMonth()->startOfDay(), $period->endOfMonth()->endOfDay()];
    }
}
