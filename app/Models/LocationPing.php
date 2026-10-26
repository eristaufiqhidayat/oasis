<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPing extends Model
{
    use ScopedThroughEmployee;

    public const UPDATED_AT = null;

    protected $fillable = ['employee_id', 'trip_id', 'recorded_at', 'latitude', 'longitude', 'accuracy_m', 'battery'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
