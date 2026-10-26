<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = ['product_id', 'depot_id', 'price', 'effective_from', 'created_by'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'effective_from' => 'date'];
    }

    /** Simpan harga untuk (produk, depo, tanggal berlaku); baris yang sama diperbarui. */
    public static function put(int $productId, ?int $depotId, string $effectiveFrom, int $price, ?int $userId = null): self
    {
        $existing = self::where('product_id', $productId)
            ->where(fn ($q) => $depotId === null ? $q->whereNull('depot_id') : $q->where('depot_id', $depotId))
            ->whereDate('effective_from', $effectiveFrom)->first();

        if ($existing) {
            $existing->update(['price' => $price, 'created_by' => $userId ?? $existing->created_by]);

            return $existing;
        }

        return self::create(['product_id' => $productId, 'depot_id' => $depotId, 'effective_from' => $effectiveFrom, 'price' => $price, 'created_by' => $userId]);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }
}
