<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['trip_id', 'product_id', 'loaded_qty', 'returned_good_qty', 'returned_damaged_qty'];

    protected function casts(): array
    {
        return ['loaded_qty' => 'integer', 'returned_good_qty' => 'integer', 'returned_damaged_qty' => 'integer'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
