<?php

namespace Tests\Feature\Web;

use App\Enums\Position;
use App\Enums\TargetMetric;
use App\Models\IncentivePayout;
use App\Models\IncentiveScheme;
use App\Models\Settlement;
use App\Models\Trip;
use App\Services\IncentiveService;
use App\Services\SalesService;
use App\Services\TargetService;
use App\Services\TripService;
use App\Support\Period;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class TargetIncentiveTest extends TestCase
{
    use BuildsOasis;

    public function test_target_dibagi_rata_realisasi_dan_insentif_disetujui_lalu_dibayar(): void
    {
        $this->seedReference();
        IncentiveScheme::query()->delete();
        $sales = IncentiveScheme::create(['name' => 'Motoris', 'applies_to' => 'salesman', 'rules' => ['per_effective_call' => 1000, 'revenue_percent' => 1, 'tiers' => [['min_achievement' => 50, 'amount' => 100000], ['min_achievement' => 100, 'amount' => 300000]]]]);
        $coord = IncentiveScheme::create(['name' => 'Koordinator', 'applies_to' => 'coordinator', 'rules' => ['tiers' => [['min_achievement' => 40, 'amount' => 200000]]]]);

        $depot = $this->depot();
        $coordinator = $this->employee($depot, Position::Coordinator);
        $a = $this->salesman($depot, $coordinator);
        $b = $this->salesman($depot, $coordinator);
        $p = $this->product('G19', 20000, true);
        $this->stock($depot, $p, 100);
        $admin = $this->userFor('admin_pusat', null, [], ['all_depots' => true]);
        $this->actingAs($admin);
        $period = Period::parse(now()->format('Y-m'));

        $this->post('/target', ['bulan' => $period->format('Y-m'), 'tab' => 'depot', 'rows' => [['id' => $depot->id, 'revenue' => 1_000_001, 'ec' => 11]]])->assertSessionHas('sukses');
        $this->post('/target/bagi-rata', ['bulan' => $period->format('Y-m'), 'depot' => $depot->id])->assertSessionHas('sukses');
        $values = app(TargetService::class)->values($period, TargetMetric::Revenue, 'employee');
        $this->assertSame(1_000_001, $values[$a->id] + $values[$b->id]);

        // Motoris A menjual Rp 600.000 di 3 outlet (3 EC); B tidak berjualan.
        $trip = app(TripService::class)->load($a, Carbon::today(), [$p->id => 31]);
        foreach (range(1, 3) as $i) {
            app(SalesService::class)->recordSale($a, $trip->fresh(), ['client_uuid' => $this->uuid(), 'outlet_id' => $this->outlet($depot)->id, 'payment_method' => 'cash', 'lines' => [['product_id' => $p->id, 'quantity' => 10]]]);
        }

        $actual = app(TargetService::class)->actuals($period)['employee'][$a->id];
        $this->assertSame(['revenue' => 600000, 'ec' => 3], $actual);
        $this->get('/pencapaian?tab=salesman')->assertOk();

        $rows = app(IncentiveService::class)->rows($period)->keyBy('key');
        $rowA = $rows["{$sales->id}:{$a->id}"];
        // 3 EC × 1.000 + 1% × 600.000 + tier 100% (600.000 / 500.001 = 120%) = 3.000 + 6.000 + 300.000
        $this->assertSame(309000, $rowA['gross']);
        $this->assertSame(0, $rows["{$sales->id}:{$b->id}"]['gross']);
        // Koordinator: tim 600.000 / 1.000.001 = 60% ≥ 40% → 200.000
        $this->assertSame(200000, $rows["{$coord->id}:{$coordinator->id}"]['gross']);

        // Potongan dari rekonsiliasi "potong insentif".
        Settlement::create(['trip_id' => $trip->id, 'cash_expected' => 600000, 'cash_received' => 590000, 'cash_variance' => -10000, 'stock_shortage_value' => 20000, 'resolution' => 'deduct_incentive', 'approved_at' => now()]);
        $rowA = app(IncentiveService::class)->rows($period)->firstWhere('key', "{$sales->id}:{$a->id}");
        $this->assertSame(30000, $rowA['deduction']);
        $this->assertSame(279000, $rowA['net']);

        $this->post('/insentif/setujui', ['bulan' => $period->format('Y-m'), 'keys' => [$rowA['key']]])->assertSessionHas('sukses');
        $this->assertSame('approved', IncentivePayout::first()->status->value);

        // Setelah disetujui, nilai beku walau ada penjualan baru.
        app(SalesService::class)->recordSale($a, $trip->fresh(), ['client_uuid' => $this->uuid(), 'outlet_id' => $this->outlet($depot)->id, 'payment_method' => 'cash', 'lines' => [['product_id' => $p->id, 'quantity' => 1]]]);
        $this->assertSame(279000, app(IncentiveService::class)->rows($period)->firstWhere('key', $rowA['key'])['net']);

        $this->post('/insentif/bayar', ['bulan' => $period->format('Y-m'), 'keys' => [$rowA['key']]])->assertSessionHas('sukses');
        $this->assertSame('paid', IncentivePayout::first()->status->value);
        $this->get('/insentif')->assertOk()->assertInertia(fn ($page) => $page->component('Performance/Incentives')->where('totals.paid', 279000));
        $this->assertNotNull(Trip::first());
    }
}
