<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Untuk model tanpa depot_id (absensi, kunjungan, GPS): cakupan lewat depo karyawan. */
trait ScopedThroughEmployee
{
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $ids = $user?->depotIds();

        return $ids === null ? $query : $query->whereHas('employee', fn (Builder $q) => $q->whereIn('depot_id', $ids));
    }
}
