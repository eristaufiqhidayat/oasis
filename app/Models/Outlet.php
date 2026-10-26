<?php

namespace App\Models;

use App\Enums\OutletStatus;
use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use ScopedToDepot;

    protected $fillable = [
        'code', 'name', 'owner_name', 'phone', 'address', 'district', 'city', 'latitude', 'longitude',
        'depot_id', 'territory_id', 'channel_id', 'status', 'photo_path', 'client_uuid', 'registered_by',
        'verified_by', 'verified_at', 'verification_note', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['status' => OutletStatus::class, 'latitude' => 'float', 'longitude' => 'float', 'verified_at' => 'datetime'];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(OutletChannel::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'registered_by');
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(SalesRoute::class, 'sales_route_outlet')->withPivot('sequence');
    }

    public function containerMovements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class);
    }

    public function containerBalance(): int
    {
        return (int) $this->containerMovements()->sum('quantity');
    }
}
