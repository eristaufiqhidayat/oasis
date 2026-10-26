<?php

namespace App\Models;

use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerReturn extends Model
{
    use ScopedToDepot;

    protected $fillable = ['client_uuid', 'number', 'depot_id', 'trip_id', 'visit_id', 'outlet_id', 'employee_id', 'returned_at', 'note'];

    protected function casts(): array
    {
        return ['returned_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerReturnLine::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
