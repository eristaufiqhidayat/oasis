<?php

namespace App\Models;

use App\Models\Concerns\ScopedToDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDocument extends Model
{
    use ScopedToDepot;

    public const TYPES = ['receipt' => 'Penerimaan barang', 'adjustment' => 'Penyesuaian (opname)'];

    protected $fillable = ['number', 'type', 'depot_id', 'date', 'reference', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
