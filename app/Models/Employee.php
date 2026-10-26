<?php

namespace App\Models;

use App\Enums\Position;
use App\Enums\StockLocationType;
use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use ScopedToDepot;

    protected $fillable = ['code', 'name', 'position', 'depot_id', 'supervisor_id', 'phone', 'joined_on', 'is_active', 'legacy_id'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['position' => Position::class, 'joined_on' => 'date', 'is_active' => 'boolean'];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function team(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function scopeSalesmen(Builder $query): Builder
    {
        return $query->where('position', Position::Salesman->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Lokasi stok kendaraan milik motoris ini di depo-nya (dibuat bila belum ada). */
    public function vehicleLocation(): StockLocation
    {
        return StockLocation::firstOrCreate(
            ['depot_id' => $this->depot_id, 'type' => StockLocationType::Vehicle->value, 'employee_id' => $this->id],
            ['name' => 'Kendaraan '.$this->name],
        );
    }
}
