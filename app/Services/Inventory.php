<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Buku besar persediaan. Semua perubahan stok wajib lewat move(): mencatat stock_movements
 * dan memperbarui stock_balances dalam satu transaksi. null = di luar sistem (pemasok/pelanggan).
 */
class Inventory
{
    public function move(
        int $productId,
        int $quantity,
        ?StockLocation $from,
        ?StockLocation $to,
        MovementType $type,
        ?Model $reference = null,
        ?string $note = null,
        ?CarbonInterface $at = null,
    ): ?StockMovement {
        if ($quantity < 0) {
            [$from, $to, $quantity] = [$to, $from, -$quantity];
        }

        if ($quantity === 0) {
            return null;
        }

        return DB::transaction(function () use ($productId, $quantity, $from, $to, $type, $reference, $note, $at) {
            if ($from) {
                $available = $this->lockedBalance($from->id, $productId);

                if ($available < $quantity) {
                    $product = Product::find($productId);

                    throw new BusinessRuleException(sprintf(
                        'Stok %s di %s tidak cukup: tersedia %d, dibutuhkan %d.',
                        $product?->name ?? "#{$productId}", $from->name, $available, $quantity,
                    ), 'insufficient_stock');
                }

                $this->adjust($from->id, $productId, -$quantity);
            }

            if ($to) {
                $this->adjust($to->id, $productId, $quantity);
            }

            return StockMovement::create([
                'occurred_at' => $at ?? now(),
                'type' => $type,
                'product_id' => $productId,
                'quantity' => $quantity,
                'from_location_id' => $from?->id,
                'to_location_id' => $to?->id,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => auth()->id(),
            ]);
        });
    }

    public function balance(int $locationId, int $productId): int
    {
        return (int) DB::table('stock_balances')->where('location_id', $locationId)->where('product_id', $productId)->value('quantity');
    }

    /** @return array<int, int> product_id => qty (hanya yang ≠ 0) */
    public function balances(int $locationId): array
    {
        return DB::table('stock_balances')->where('location_id', $locationId)->where('quantity', '!=', 0)
            ->pluck('quantity', 'product_id')->map(fn ($q) => (int) $q)->all();
    }

    private function lockedBalance(int $locationId, int $productId): int
    {
        return (int) DB::table('stock_balances')->where('location_id', $locationId)->where('product_id', $productId)
            ->lockForUpdate()->value('quantity');
    }

    private function adjust(int $locationId, int $productId, int $delta): void
    {
        $updated = DB::table('stock_balances')->where('location_id', $locationId)->where('product_id', $productId)
            ->update(['quantity' => DB::raw('quantity + '.(int) $delta), 'updated_at' => now()]);

        if (! $updated) {
            DB::table('stock_balances')->insert(['location_id' => $locationId, 'product_id' => $productId, 'quantity' => $delta, 'updated_at' => now()]);
        }
    }
}
