<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Status outlet: outlet baru dari HP menunggu verifikasi. */
enum OutletStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Active => 'Aktif',
            self::Rejected => 'Ditolak',
            self::Inactive => 'Nonaktif',
        };
    }
}
