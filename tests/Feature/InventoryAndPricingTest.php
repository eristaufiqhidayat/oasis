<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\StockLocationType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductPrice;
use App\Services\Inventory;
use App\Services\Pricing;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class InventoryAndPricingTest extends TestCase
{
    use BuildsOasis;

    public function test_perpindahan_stok_memperbarui_saldo_dan_menolak_stok_minus(): void
    {
        $depot = $this->depot();
        $product = $this->product('G19', 20000, true);
        $inventory = app(Inventory::class);
        $warehouse = $depot->location(StockLocationType::Warehouse);
        $damaged = $depot->location(StockLocationType::Damaged);

        $inventory->move($product->id, 10, null, $warehouse, MovementType::Receipt);
        $inventory->move($product->id, 3, $warehouse, $damaged, MovementType::Adjustment);
        // qty negatif = arah dibalik (penyesuaian berkurang).
        $inventory->move($product->id, -2, null, $warehouse, MovementType::Adjustment);

        $this->assertSame(5, $inventory->balance($warehouse->id, $product->id));
        $this->assertSame(3, $inventory->balance($damaged->id, $product->id));
        $this->assertDatabaseCount('stock_movements', 3);

        $this->expectException(BusinessRuleException::class);
        $inventory->move($product->id, 6, $warehouse, $damaged, MovementType::Adjustment);
    }

    public function test_harga_depo_menimpa_harga_nasional_sesuai_tanggal_berlaku(): void
    {
        $a = $this->depot('A');
        $b = $this->depot('B');
        $product = $this->product('B600', 3000);
        ProductPrice::put($product->id, null, '2026-06-01', 3200);
        ProductPrice::put($product->id, $b->id, '2026-07-01', 3500);
        $pricing = app(Pricing::class);

        $this->assertSame(3000, $pricing->priceFor($product->id, $a->id, Carbon::parse('2026-05-31')));
        $this->assertSame(3200, $pricing->priceFor($product->id, $a->id, Carbon::parse('2026-06-15')));
        $this->assertSame(3200, $pricing->priceFor($product->id, $b->id, Carbon::parse('2026-06-15')));
        $this->assertSame(3500, $pricing->priceFor($product->id, $b->id, Carbon::parse('2026-07-02')));
        $this->assertNull($pricing->priceFor(999, $b->id));
    }
}
