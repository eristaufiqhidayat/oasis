<?php

namespace App\Models;

use App\Enums\TargetMetric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Target extends Model
{
    protected $fillable = ['period', 'metric', 'depot_id', 'employee_id', 'product_id', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['period' => 'date', 'metric' => TargetMetric::class, 'value' => 'integer'];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
