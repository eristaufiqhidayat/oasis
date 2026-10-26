<?php

namespace App\Services;

use App\Models\ProductPrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Harga jual berlaku: harga depo (bila ada) menimpa harga nasional; dari tiap jenis dipakai
 * baris dengan effective_from terbaru yang ≤ tanggal transaksi.
 */
class Pricing
{
    /** @return array<int, int> product_id => harga */
    public function priceList(?int $depotId, ?CarbonInterface $date = null, ?array $productIds = null): array
    {
        $date ??= Carbon::today();

        $rows = ProductPrice::query()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('depot_id')->when($depotId, fn ($q) => $q->orWhere('depot_id', $depotId)))
            ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get(['product_id', 'depot_id', 'price']);

        $national = [];
        $depot = [];

        foreach ($rows as $row) {
            if ($row->depot_id === null) {
                $national[$row->product_id] = (int) $row->price;
            } else {
                $depot[$row->product_id] = (int) $row->price;
            }
        }

        return $depot + $national;
    }

    public function priceFor(int $productId, ?int $depotId, ?CarbonInterface $date = null): ?int
    {
        return $this->priceList($depotId, $date, [$productId])[$productId] ?? null;
    }
}
