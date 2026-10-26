<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /** Pilihan untuk dropdown: [value => label]. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
