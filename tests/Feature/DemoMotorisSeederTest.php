<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Region;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\Visit;
use Database\Seeders\DemoMotorisSeeder;
use Database\Seeders\DemoSeeder;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class DemoMotorisSeederTest extends TestCase
{
    use BuildsOasis;

    protected function tearDown(): void
    {
        DemoMotorisSeeder::$from = DemoMotorisSeeder::$to = DemoMotorisSeeder::$depots = DemoMotorisSeeder::$daily = null;
        parent::tearDown();
    }

    private function bekasiIds()
    {
        return Employee::whereIn('code', collect(range(1, 10))->map(fn ($i) => sprintf('BKS-PK%02d', $i)))->pluck('id');
    }

    public function test_satu_hari_seluruh_indonesia_sekitar_300_juta(): void
    {
        DemoMotorisSeeder::$from = DemoMotorisSeeder::$to = '2026-10-27';
        $this->seed(DemoMotorisSeeder::class);

        $this->assertSame(40, DemoMotorisSeeder::rosterSize());
        $this->assertSame(8, Region::has('depots')->count());
        $this->assertSame(16, Trip::distinct()->count('depot_id'));
        $this->assertSame(40, Trip::whereDate('date', '2026-10-27')->where('status', TripStatus::Returned->value)->count());
        $this->assertSame(40, Attendance::whereDate('date', '2026-10-27')->whereNotNull('check_out_at')->count());
        $this->assertSame(960, Visit::whereDate('started_at', '2026-10-27')->count());
        $this->assertSame(800, Sale::whereDate('sold_at', '2026-10-27')->count());
        $this->assertSame(0, Visit::where('distance_m', '>', config('mobile.visit_radius_m'))->count());
        $this->assertTrue(Visit::all()->every(fn ($v) => $v->ended_at?->gt($v->started_at)));

        // Target 300 jt ±10%, ditambah toleransi pembulatan.
        $total = Sale::sum('total');
        $this->assertGreaterThan(265_000_000, $total);
        $this->assertLessThan(335_000_000, $total);
    }

    public function test_rentang_tanggal_lewati_minggu_tutup_hari_lalu_dan_aman_diulang(): void
    {
        $this->seed(DemoSeeder::class);
        DemoMotorisSeeder::$depots = ['BKS'];

        // Sabtu 24 Okt → Senin 26 Okt (Minggu 25 Okt dilewati).
        DemoMotorisSeeder::$from = '2026-10-24';
        DemoMotorisSeeder::$to = '2026-10-26';
        $this->seed(DemoMotorisSeeder::class);

        // Lanjut ke Selasa: muatan Senin ditutup otomatis; jalankan ulang tidak menambah data.
        DemoMotorisSeeder::$to = '2026-10-27';
        $this->seed(DemoMotorisSeeder::class);
        $this->seed(DemoMotorisSeeder::class);

        $ids = $this->bekasiIds();
        $this->assertSame(0, Trip::whereIn('employee_id', $ids)->whereDate('date', '2026-10-25')->count());
        $this->assertSame(20, Trip::whereIn('employee_id', $ids)->whereDate('date', '<', '2026-10-27')->where('status', TripStatus::Closed->value)->count());
        $this->assertSame(10, Trip::whereIn('employee_id', $ids)->whereDate('date', '2026-10-27')->where('status', TripStatus::Returned->value)->count());
        $this->assertSame(600, Sale::whereIn('employee_id', $ids)->count());

        // Bekasi = 10 dari 40 motoris → porsi target harian ±75 jt.
        $day = Sale::whereIn('employee_id', $ids)->whereDate('sold_at', '2026-10-27')->sum('total');
        $this->assertGreaterThan(65_000_000, $day);
        $this->assertLessThan(85_000_000, $day);
    }

    public function test_muatan_terbuka_di_tanggal_lebih_baru_tidak_menghalangi_riwayat(): void
    {
        DemoMotorisSeeder::$depots = ['BKS'];
        DemoMotorisSeeder::$from = DemoMotorisSeeder::$to = '2026-10-26';
        $this->seed(DemoMotorisSeeder::class);

        DemoMotorisSeeder::$from = '2026-10-23';
        DemoMotorisSeeder::$to = '2026-10-27';
        $this->seed(DemoMotorisSeeder::class);

        $ids = $this->bekasiIds();
        $this->assertSame(30, Trip::whereIn('employee_id', $ids)->where('status', TripStatus::Closed->value)->count()); // 23, 24, 26 Okt
        $this->assertSame(10, Trip::whereIn('employee_id', $ids)->where('status', TripStatus::Returned->value)->whereDate('date', '2026-10-27')->count());
    }
}
