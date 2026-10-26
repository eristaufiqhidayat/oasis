<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Nomor dokumen berurutan per hari: PREFIX-YYMMDD-0001. */
class Numbering
{
    public static function next(string $table, string $prefix, CarbonInterface $date): string
    {
        $base = $prefix.'-'.$date->format('ymd').'-';
        $last = DB::table($table)->where('number', 'like', $base.'%')->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
