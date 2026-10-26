<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentivePayout extends Model
{
    protected $fillable = [
        'period', 'employee_id', 'incentive_scheme_id', 'amount', 'deduction', 'breakdown', 'status',
        'approved_by', 'approved_at', 'paid_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date', 'status' => PayoutStatus::class, 'breakdown' => 'array', 'amount' => 'integer', 'deduction' => 'integer',
            'approved_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(IncentiveScheme::class, 'incentive_scheme_id');
    }
}
