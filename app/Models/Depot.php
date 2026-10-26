<?php

namespace App\Models;

use App\Enums\StockLocationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Depot extends Model
{
    protected $fillable = ['region_id', 'code', 'name', 'address', 'city', 'phone', 'latitude', 'longitude', 'is_active', 'legacy_id'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function territories(): HasMany
    {
        return $this->hasMany(Territory::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(StockLocation::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $ids = $user?->depotIds();

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }

    /** Lokasi gudang utama / barang rusak depo ini (dibuat bila belum ada). */
    public function location(StockLocationType $type): StockLocation
    {
        return StockLocation::firstOrCreate(
            ['depot_id' => $this->id, 'type' => $type->value, 'employee_id' => null],
            ['name' => $type->label().' '.$this->name],
        );
    }
}
