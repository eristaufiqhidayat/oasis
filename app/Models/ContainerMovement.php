<?php

namespace App\Models;

use App\Enums\ContainerMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerMovement extends Model
{
    protected $fillable = ['client_uuid', 'outlet_id', 'trip_id', 'visit_id', 'sale_id', 'type', 'quantity', 'occurred_at'];

    protected function casts(): array
    {
        return ['type' => ContainerMovementType::class, 'occurred_at' => 'datetime', 'quantity' => 'integer'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
