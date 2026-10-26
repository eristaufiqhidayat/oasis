<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['sku', 'name', 'category_id', 'unit', 'units_per_case', 'tracks_container', 'is_active', 'legacy_id'];

    protected $attributes = ['is_active' => true, 'units_per_case' => 1, 'unit' => 'pcs', 'tracks_container' => false];

    protected function casts(): array
    {
        return ['tracks_container' => 'boolean', 'is_active' => 'boolean', 'units_per_case' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
