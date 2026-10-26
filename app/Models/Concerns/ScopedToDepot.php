<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model yang punya kolom depot_id: scope visibleTo() membatasi data ke depo dalam cakupan akun.
 * Akun all_depots melihat semuanya.
 */
trait ScopedToDepot
{
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $ids = $user?->depotIds();

        return $ids === null ? $query : $query->whereIn($this->qualifyColumn('depot_id'), $ids);
    }
}
