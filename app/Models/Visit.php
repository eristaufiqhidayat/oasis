<?php

namespace App\Models;

use App\Enums\VisitOutcome;
use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    use ScopedThroughEmployee;

    protected $fillable = [
        'client_uuid', 'trip_id', 'outlet_id', 'employee_id', 'started_at', 'ended_at', 'latitude', 'longitude',
        'distance_m', 'outcome', 'no_sale_reason', 'photo_path', 'note',
    ];

    protected function casts(): array
    {
        return ['outcome' => VisitOutcome::class, 'started_at' => 'datetime', 'ended_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float'];
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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
