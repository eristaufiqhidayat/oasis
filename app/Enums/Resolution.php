<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Keputusan atas selisih setoran. */
enum Resolution: string
{
    use HasOptions;

    case None = 'none';
    case DeductIncentive = 'deduct_incentive';
    case ChargeEmployee = 'charge_employee';
    case WriteOff = 'write_off';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak ada selisih',
            self::DeductIncentive => 'Potong insentif',
            self::ChargeEmployee => 'Ditagih ke motoris',
            self::WriteOff => 'Dihapuskan perusahaan',
        };
    }
}
