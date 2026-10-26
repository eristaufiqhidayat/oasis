<?php

namespace Tests\Feature\Web;

use App\Enums\Position;
use App\Enums\StockLocationType;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Inventory;
use App\Services\SalesService;
use App\Services\TripService;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class TripSettlementTest extends TestCase
{
    use BuildsOasis;

    public function test_muat_bongkar_setor_dan_rekonsiliasi_dengan_selisih(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $salesman = $this->salesman($depot);
        $galon = $this->product('G19', 20000, true);
        $botol = $this->product('B600', 3000);
        $outlet = $this->outlet($depot);
        $this->stock($depot, $galon, 30);
        $this->stock($depot, $botol, 100);

        $gudang = $this->userFor('gudang', $this->employee($depot, Position::Warehouse), [$depot]);
        $kasir = $this->userFor('kasir', $this->employee($depot, Position::Cashier), [$depot]);
        $kepala = $this->userFor('kepala_depo', $this->employee($depot, Position::DepotHead), [$depot]);

        // Gudang memuat.
        $this->actingAs($gudang)->post('/muatan', [
            'employee_id' => $salesman->id, 'date' => Carbon::today()->toDateString(),
            'lines' => [$galon->id => 10, $botol->id => 48],
        ])->assertRedirect()->assertSessionHas('sukses');
        $trip = Trip::firstOrFail();
        $this->assertSame(20, $this->warehouseQty($depot, $galon));
        $this->actingAs($gudang)->get("/muatan/{$trip->id}")->assertOk();

        // Muatan kedua untuk motoris yang sama ditolak.
        $this->actingAs($gudang)->post('/muatan', ['employee_id' => $salesman->id, 'date' => Carbon::today()->toDateString(), 'lines' => [$botol->id => 1]])
            ->assertSessionHas('galat');

        // Motoris berjualan: 4 galon tunai, 10 botol transfer.
        $sales = app(SalesService::class);
        $sales->recordSale($salesman, $trip, ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id, 'payment_method' => 'cash', 'lines' => [['product_id' => $galon->id, 'quantity' => 4]]]);
        $sales->recordSale($salesman, $trip->fresh(), ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id, 'payment_method' => 'transfer', 'lines' => [['product_id' => $botol->id, 'quantity' => 10]]]);
        $sales->collectContainers($salesman, $trip, ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id, 'quantity' => 4]);

        // Bongkar: galon kurang 1 (hanya 5 dari 6), botol 36 layak + 2 rusak; galon kosong diterima 3 dari 4.
        $this->actingAs($gudang)->post("/muatan/{$trip->id}/bongkar", [
            'counts' => [$galon->id => ['good' => 5, 'damaged' => 0], $botol->id => ['good' => 36, 'damaged' => 2]],
            'empty_containers' => 3,
        ])->assertRedirect("/setoran/{$trip->id}");
        $this->assertSame(TripStatus::Unloaded, $trip->fresh()->status);
        $this->assertSame(25, $this->warehouseQty($depot, $galon));
        $this->assertSame(2, app(Inventory::class)->balance($depot->location(StockLocationType::Damaged)->id, $botol->id));

        // Hitungan melebihi sisa sistem ditolak (sebelum bongkar) — dicek di layanan.
        $this->actingAs($kasir)->get("/setoran/{$trip->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finance/SettlementShow')
                ->where('summary.cash_expected', 80000)
                ->where('summary.transfer_total', 30000)
                ->where('summary.stock_shortage_value', 20000)
                ->where('summary.container_shortage', 1));

        // Kasir menerima uang kurang Rp 5.000.
        $this->actingAs($kasir)->post("/setoran/{$trip->id}/terima", ['cash_received' => 75000])->assertSessionHas('sukses');
        // Kasir tidak boleh menyetujui.
        $this->actingAs($kasir)->post("/setoran/{$trip->id}/setujui", [])->assertForbidden();

        // Selisih tanpa keputusan ditolak, dengan keputusan diterima.
        $this->actingAs($kepala)->post("/setoran/{$trip->id}/setujui", ['resolution' => 'none'])->assertSessionHas('galat');
        $this->actingAs($kepala)->post("/setoran/{$trip->id}/setujui", ['resolution' => 'deduct_incentive', 'note' => 'Galon hilang, uang kurang'])
            ->assertSessionHas('sukses');

        $trip->refresh();
        $this->assertSame(TripStatus::Closed, $trip->status);
        $this->assertSame(-5000, $trip->settlement->cash_variance);
        $this->assertSame(20000, $trip->settlement->stock_shortage_value);
        // Kendaraan kosong lagi setelah selisih dihapuskan.
        $this->assertSame([], app(Inventory::class)->balances($trip->vehicle_location_id));

        // Muatan berikutnya sekarang boleh.
        $this->actingAs($gudang);
        $next = app(TripService::class)->load($salesman, Carbon::tomorrow(), [$botol->id => 5]);
        $this->assertSame(TripStatus::Loaded, $next->status);
    }

    public function test_batal_muat_mengembalikan_stok_dan_batal_jual_mengembalikan_ke_kendaraan(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $salesman = $this->salesman($depot);
        $galon = $this->product('G19', 20000, true);
        $outlet = $this->outlet($depot);
        $this->stock($depot, $galon, 10);
        $kepala = $this->userFor('kepala_depo', null, [$depot]);
        $this->actingAs($kepala);

        $trip = app(TripService::class)->load($salesman, Carbon::today(), [$galon->id => 10]);
        $this->post("/muatan/{$trip->id}/batal")->assertSessionHas('sukses');
        $this->assertSame(10, $this->warehouseQty($depot, $galon));
        $this->assertSame(TripStatus::Cancelled, $trip->fresh()->status);

        $trip = app(TripService::class)->load($salesman, Carbon::today(), [$galon->id => 10]);
        $sale = app(SalesService::class)->recordSale($salesman, $trip, ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id, 'payment_method' => 'cash', 'lines' => [['product_id' => $galon->id, 'quantity' => 3]]]);
        $this->assertSame(3, $outlet->containerBalance());

        $this->post("/penjualan/{$sale->id}/batal", ['reason' => 'Salah input'])->assertSessionHas('sukses');
        $this->assertSame(10, app(Inventory::class)->balance($trip->vehicle_location_id, $galon->id));
        $this->assertSame(0, $outlet->containerBalance());
        $this->post("/penjualan/{$sale->id}/batal", ['reason' => 'lagi'])->assertSessionHas('galat');
    }

    public function test_bongkar_melebihi_sisa_sistem_ditolak(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $salesman = $this->salesman($depot);
        $botol = $this->product('B600', 3000);
        $this->stock($depot, $botol, 10);
        $this->actingAs($this->userFor('gudang', null, [$depot]));
        $trip = app(TripService::class)->load($salesman, Carbon::today(), [$botol->id => 10]);
        app(TripService::class)->depart($trip);

        $this->post("/muatan/{$trip->id}/bongkar", ['counts' => [$botol->id => ['good' => 11]], 'empty_containers' => 0])
            ->assertSessionHasErrors("counts.{$botol->id}");
        $this->assertSame(TripStatus::OnRoute, $trip->fresh()->status);
    }
}
