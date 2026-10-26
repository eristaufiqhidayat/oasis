<?php

namespace App\Models;

use App\Enums\ReturnReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturnLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_return_id', 'product_id', 'quantity', 'reason'];

    protected function casts(): array
    {
        return ['reason' => ReturnReason::class, 'quantity' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
