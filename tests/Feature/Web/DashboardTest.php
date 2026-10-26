<?php

namespace Tests\Feature\Web;

use App\Models\Depot;
use App\Models\Sale;
use App\Models\Visit;
use Database\Seeders\DemoMotorisSeeder;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsOasis;

    protected function setUp(): void
    {
        parent::setUp();
        DemoMotorisSeeder::$from = '2026-10-26';
        DemoMotorisSeeder::$to = '2026-10-27';
        DemoMotorisSeeder::$depots = ['BKS', 'SBY'];
        $this->seed(DemoMotorisSeeder::class);
        // Seeder mengembalikan jam ke waktu nyata; patok "hari ini" sesudahnya.
        Carbon::setTestNow('2026-10-27 18:00:00');
    }

    protected function tearDown(): void
    {
        DemoMotorisSeeder::$from = DemoMotorisSeeder::$to = DemoMotorisSeeder::$depots = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_grafik_semua_depo_per_wilayah_dan_angka_cocok_dengan_transaksi(): void
    {
        $this->actingAs($this->userFor('super_admin'))->get('/home?periode=7')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('period', '7')
            ->where('range', ['from' => '2026-10-21', 'to' => '2026-10-27'])
            ->where('charts.summary.revenue', (int) Sale::sum('total'))
            ->where('charts.summary.transactions', 2 * 12 * 20)
            ->where('charts.summary.visits', 2 * 12 * 24)
            ->where('charts.summary.effective_calls', Visit::where('outcome', 'sale')->count())
            ->where('charts.summary.active_salesmen', 12)
            ->has('charts.daily', 7)
            ->where('charts.daily.6.date', '2026-10-27')
            ->where('charts.daily.6.transactions', 12 * 20)
            ->where('charts.daily.0.total', 0)
            ->where('charts.byArea.dimension', 'wilayah')
            ->where('charts.byArea.items', fn ($items) => collect($items)->pluck('name')->sort()->values()->all() === ['Jawa Barat', 'Jawa Timur']
                && collect($items)->sum('total') === (int) Sale::sum('total'))
            ->has('charts.products', 5)
            ->where('charts.products', fn ($p) => collect($p)->sum('total') === (int) Sale::sum('total'))
            ->has('charts.outcomes', 3)
            ->where('charts.payments', fn ($p) => collect($p)->sum('transactions') === 480)
            ->has('charts.topSalesmen', 10)
            ->has('charts.hours', 13)
            ->where('charts.hours', fn ($h) => collect($h)->sum('transactions') === 480));
    }

    public function test_satu_depo_dirinci_per_area_dan_akun_depo_tidak_melihat_depo_lain(): void
    {
        $bekasi = Depot::where('code', 'BKS')->first();

        $this->actingAs($this->userFor('super_admin'))->get("/home?depot={$bekasi->id}&periode=bulan")->assertInertia(fn ($page) => $page
            ->where('range.from', '2026-10-01')
            ->where('charts.byArea.dimension', 'area')
            ->where('charts.byArea.items', fn ($items) => collect($items)->count() === 5 && collect($items)->pluck('name')->contains('Pondok Kopi'))
            ->where('charts.summary.transactions', 2 * 10 * 20));

        // Kepala depo Surabaya hanya melihat Surabaya walau meminta depo Bekasi.
        $surabaya = Depot::where('code', 'SBY')->first();
        $head = $this->userFor('kepala_depo', depots: [$surabaya]);
        $this->actingAs($head)->get("/home?depot={$bekasi->id}")->assertInertia(fn ($page) => $page
            ->where('charts.summary.transactions', 2 * 2 * 20)
            ->where('charts.topSalesmen', fn ($m) => collect($m)->every(fn ($r) => $r['depot'] === 'Surabaya')));
    }

    public function test_periode_tidak_dikenal_memakai_bawaan(): void
    {
        $this->actingAs($this->userFor('super_admin'))->get('/home?periode=abc')->assertInertia(fn ($page) => $page
            ->where('period', '14')
            ->has('charts.daily', 14));
    }
}
