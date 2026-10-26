<?php

namespace Tests\Feature\Web;

use App\Models\Depot;
use App\Models\Outlet;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\User;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use BuildsOasis;

    public function test_tambah_ubah_hapus_depo_dan_validasi(): void
    {
        $this->seedReference();
        $this->actingAs($this->userFor('super_admin'));

        $this->post('/master/depo', ['code' => 'SBY', 'name' => 'Depo Surabaya', 'is_active' => true])->assertSessionHas('sukses');
        $depot = Depot::where('code', 'SBY')->firstOrFail();
        $this->post('/master/depo', ['code' => 'SBY', 'name' => 'Dobel'])->assertSessionHasErrors('code');

        $this->put("/master/depo/{$depot->id}", ['code' => 'SBY', 'name' => 'Depo Surabaya Timur', 'is_active' => false])->assertSessionHas('sukses');
        $this->assertFalse($depot->fresh()->is_active);
        $this->get('/master/depo?q=Timur')->assertOk()->assertInertia(fn ($p) => $p->component('Resource/Index')->has('rows.data', 1));

        // Depo yang sudah punya outlet tidak bisa dihapus.
        $this->outlet($depot);
        $this->delete("/master/depo/{$depot->id}")->assertSessionHas('galat');
        $this->assertModelExists($depot);
    }

    public function test_outlet_mendapat_area_dari_koordinat_dan_verifikasi_outlet_baru(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $territory = $depot->territories()->create(['name' => 'Area A', 'boundary' => ['type' => 'Polygon', 'coordinates' => [[[107, -7], [108, -7], [108, -6], [107, -6], [107, -7]]]]]);
        $this->actingAs($this->userFor('admin_pusat', null, [], ['all_depots' => true]));

        $this->post('/master/outlet', ['code' => 'T1', 'name' => 'Toko Satu', 'depot_id' => $depot->id, 'latitude' => -6.5, 'longitude' => 107.5, 'status' => 'active'])
            ->assertSessionHas('sukses');
        $this->assertSame($territory->id, Outlet::where('code', 'T1')->value('territory_id'));

        $pending = $this->outlet($depot, ['status' => 'pending', 'registered_by' => $this->salesman($depot)->id]);
        $this->get('/outlet-baru')->assertOk()->assertInertia(fn ($p) => $p->has('outlets.data', 1));
        $this->post("/outlet-baru/{$pending->id}", ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->post("/outlet-baru/{$pending->id}", ['decision' => 'approve'])->assertSessionHas('sukses');
        $this->assertSame('active', $pending->fresh()->status->value);
    }

    public function test_harga_pengguna_dan_peran(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $p = $this->product('B600', 3000);
        $this->actingAs($this->userFor('super_admin'));

        $this->post('/master/harga', ['depot_id' => $depot->id, 'effective_from' => now()->toDateString(), 'prices' => [$p->id => 3300]])->assertSessionHas('sukses');
        $this->get("/master/harga?depot={$depot->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('products.0.price', 3300)->where('products.0.national', 3000)->where('products.0.is_override', true));
        $this->delete("/master/harga/{$depot->id}/{$p->id}")->assertSessionHas('sukses');
        $this->assertSame(1, ProductPrice::count());

        $this->post('/pengaturan/peran', ['name' => 'Auditor', 'permissions' => ['report.view', 'sale.view']])->assertSessionHas('sukses');
        $role = Role::where('name', 'Auditor')->firstOrFail();

        $this->post('/pengaturan/pengguna', [
            'username' => 'auditor1', 'name' => 'Auditor Satu', 'role_id' => $role->id, 'password' => 'rahasia123',
            'all_depots' => false, 'depot_ids' => [$depot->id], 'is_active' => true, 'must_change_password' => true,
        ])->assertSessionHas('sukses');
        $user = User::where('username', 'auditor1')->firstOrFail();
        $this->assertSame([$depot->id], $user->depotIds());
        $this->assertTrue($user->can('report.view'));
        $this->assertFalse($user->can('trip.load'));

        $this->delete("/pengaturan/peran/{$role->id}")->assertSessionHas('galat');
    }
}
