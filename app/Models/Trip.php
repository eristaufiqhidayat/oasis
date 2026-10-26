<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trip extends Model
{
    use ScopedToDepot;

    protected $fillable = [
        'number', 'depot_id', 'employee_id', 'vehicle_location_id', 'date', 'status', 'loaded_by', 'loaded_at',
        'departed_at', 'returned_at', 'unloaded_by', 'unloaded_at', 'empty_containers_received', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class, 'date' => 'date', 'loaded_at' => 'datetime', 'departed_at' => 'datetime',
            'returned_at' => 'datetime', 'unloaded_at' => 'datetime',
        ];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'vehicle_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TripLine::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function containerMovements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class);
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class);
    }
}
