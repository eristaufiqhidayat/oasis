<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Siklus muatan harian motoris. */
enum TripStatus: string
{
    use HasOptions;

    case Loaded = 'loaded';
    case OnRoute = 'on_route';
    case Returned = 'returned';
    case Unloaded = 'unloaded';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Loaded => 'Siap berangkat',
            self::OnRoute => 'Di lapangan',
            self::Returned => 'Kembali, belum dibongkar',
            self::Unloaded => 'Dibongkar, menunggu setoran',
            self::Closed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
