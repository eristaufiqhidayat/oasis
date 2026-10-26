<?php

namespace App\Models;

use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Territory extends Model
{
    use ScopedToDepot;

    protected $fillable = ['depot_id', 'code', 'name', 'boundary', 'color', 'is_active', 'legacy_id'];

    protected $attributes = ['is_active' => true, 'color' => '#18a6b1'];

    protected function casts(): array
    {
        return ['boundary' => 'array', 'is_active' => 'boolean'];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }
}
