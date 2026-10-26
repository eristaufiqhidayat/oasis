<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use ScopedToDepot;

    protected $fillable = [
        'client_uuid', 'number', 'depot_id', 'trip_id', 'visit_id', 'outlet_id', 'employee_id', 'sold_at',
        'subtotal', 'discount', 'total', 'payment_method', 'status', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class, 'status' => SaleStatus::class, 'sold_at' => 'datetime', 'voided_at' => 'datetime',
            'subtotal' => 'integer', 'discount' => 'integer', 'total' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Completed->value);
    }
}
