<?php

namespace App\Models;

use App\Enums\Resolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    protected $fillable = [
        'trip_id', 'cash_expected', 'cash_received', 'cash_variance', 'stock_shortage_value', 'container_shortage',
        'resolution', 'note', 'received_by', 'received_at', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolution' => Resolution::class, 'received_at' => 'datetime', 'approved_at' => 'datetime',
            'cash_expected' => 'integer', 'cash_received' => 'integer', 'cash_variance' => 'integer',
            'stock_shortage_value' => 'integer', 'container_shortage' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
