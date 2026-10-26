<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use ScopedThroughEmployee;

    protected $fillable = [
        'employee_id', 'date', 'check_in_at', 'check_in_latitude', 'check_in_longitude', 'check_in_photo',
        'check_out_at', 'check_out_latitude', 'check_out_longitude', 'note',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
