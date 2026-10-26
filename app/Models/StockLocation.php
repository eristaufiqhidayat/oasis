<?php

namespace App\Models;

use App\Enums\StockLocationType;
use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLocation extends Model
{
    use ScopedToDepot;

    protected $fillable = ['depot_id', 'type', 'employee_id', 'name'];

    protected function casts(): array
    {
        return ['type' => StockLocationType::class];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
