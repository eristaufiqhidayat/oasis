<?php

namespace Tests\Feature\Web;

use App\Models\IncentiveScheme;
use App\Models\SalesRoute;
use App\Models\StockDocument;
use App\Models\Territory;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class WarehouseAndSettingsTest extends TestCase
{
    use BuildsOasis;

    public function test_penerimaan_dan_opname_memperbarui_stok_gudang(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $p = $this->product('G19', 20000, true);
        $this->actingAs($this->userFor('gudang', null, [$depot]));

        $this->post('/dokumen-stok', ['depot_id' => $depot->id, 'type' => 'receipt', 'date' => now()->toDateString(), 'reference' => 'DO-1',
            'lines' => [['product_id' => $p->id, 'quantity' => 100], ['product_id' => $p->id, 'quantity' => 20]]])->assertSessionHas('sukses');
        $this->assertSame(120, $this->warehouseQty($depot, $p));

        $this->post('/dokumen-stok', ['depot_id' => $depot->id, 'type' => 'adjustment', 'date' => now()->toDateString(),
            'lines' => [['product_id' => $p->id, 'quantity' => -5]]])->assertSessionHasErrors('note');
        $this->post('/dokumen-stok', ['depot_id' => $depot->id, 'type' => 'adjustment', 'date' => now()->toDateString(), 'note' => 'Opname: 5 bocor',
            'lines' => [['product_id' => $p->id, 'quantity' => -5]]])->assertSessionHas('sukses');
        $this->assertSame(115, $this->warehouseQty($depot, $p));
        $this->post('/dokumen-stok', ['depot_id' => $depot->id, 'type' => 'adjustment', 'date' => now()->toDateString(), 'note' => 'x',
            'lines' => [['product_id' => $p->id, 'quantity' => -500]]])->assertSessionHas('galat');

        $this->assertSame(2, StockDocument::count());
        $this->get('/mutasi-stok')->assertOk()->assertInertia(fn ($page) => $page->has('movements.data', 2));
        $this->get('/stok')->assertOk()->assertInertia(fn ($page) => $page->has('products', 1)->has('locations', 1));
    }

    public function test_batas_area_rute_dan_skema_insentif(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $territory = Territory::create(['depot_id' => $depot->id, 'name' => 'Area A']);
        $inside = $this->outlet($depot, ['latitude' => -6.5, 'longitude' => 107.5]);
        $outside = $this->outlet($depot, ['latitude' => -5.0, 'longitude' => 107.5]);
        $this->actingAs($this->userFor('super_admin'));

        $this->put("/master/area/{$territory->id}/batas", [
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[107, -7], [108, -7], [108, -6], [107, -6], [107, -7]]]],
            'assign_outlets' => true,
        ])->assertSessionHas('sukses');
        $this->assertSame($territory->id, $inside->fresh()->territory_id);
        $this->assertNull($outside->fresh()->territory_id);
        $this->get("/master/area/peta?depot={$depot->id}")->assertOk()->assertInertia(fn ($p) => $p->has('territories', 1)->has('outlets', 2));

        $salesman = $this->salesman($depot);
        $this->post('/master/rute', ['name' => 'Senin', 'depot_id' => $depot->id, 'employee_id' => $salesman->id, 'weekday' => 1, 'is_active' => true])->assertSessionHas('sukses');
        $route = SalesRoute::firstOrFail();
        $this->put("/master/rute/{$route->id}/outlet", ['outlets' => [$outside->id, $inside->id]])->assertSessionHas('sukses');
        $this->assertSame([$outside->id, $inside->id], $route->outlets()->pluck('outlets.id')->all());
        $this->get("/master/rute/{$route->id}")->assertOk();

        $this->post('/pengaturan/skema-insentif', ['name' => 'Baru', 'applies_to' => 'salesman', 'is_active' => true,
            'rules' => ['per_effective_call' => 500, 'revenue_percent' => 0.5, 'tiers' => [['min_achievement' => 120, 'amount' => 1000], ['min_achievement' => 90, 'amount' => 500]]]])
            ->assertSessionHas('sukses');
        $scheme = IncentiveScheme::where('name', 'Baru')->firstOrFail();
        $this->assertEquals(90, $scheme->rules['tiers'][0]['min_achievement']);
        $this->put("/pengaturan/skema-insentif/{$scheme->id}", ['name' => 'Baru 2', 'applies_to' => 'coordinator', 'rules' => ['tiers' => []]])->assertSessionHas('sukses');
        $this->assertSame('coordinator', $scheme->fresh()->applies_to->value);

        $this->delete("/master/rute/{$route->id}")->assertSessionHas('sukses');
        $this->get('/pengaturan/log')->assertOk();
    }
}
