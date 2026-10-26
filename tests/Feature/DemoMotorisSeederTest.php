<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Territory;
use App\Models\Trip;
use App\Models\Visit;
use Database\Seeders\DemoMotorisSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class DemoMotorisSeederTest extends TestCase
{
    use BuildsOasis;

    protected function tearDown(): void
    {
        DemoMotorisSeeder::$from = DemoMotorisSeeder::$to = null;
        parent::tearDown();
    }

    private function salesmanIds()
    {
        return Employee::whereIn('code', collect(range(1, 10))->map(fn ($i) => sprintf('BKS-PK%02d', $i)))->pluck('id');
    }

    public function test_satu_hari_berisi_200_transaksi_di_5_area(): void
    {
        DemoMotorisSeeder::$from = DemoMotorisSeeder::$to = '2026-10-27';
        $this->seed(DemoMotorisSeeder::class);

        $tomorrow = Carbon::parse('2026-10-27');
        $ids = $this->salesmanIds();
        $this->assertCount(10, $ids);
        $this->assertSame(5, Territory::whereIn('code', ['BKS-PK', 'BKS-KL', 'BKS-PG', 'BKS-KR', 'BKS-GX'])->count());

        foreach ($ids as $id) {
            $this->assertTrue(Attendance::where('employee_id', $id)->whereDate('date', $tomorrow)->whereNotNull('check_out_at')->exists());
            $visits = Visit::where('employee_id', $id)->whereDate('started_at', $tomorrow)->orderBy('started_at')->get();
            $this->assertCount(24, $visits);
            $this->assertTrue($visits->every(fn ($v) => $v->ended_at?->gt($v->started_at)));
            $this->assertSame(20, Sale::where('employee_id', $id)->whereDate('sold_at', $tomorrow)->count());
            $this->assertSame(TripStatus::Returned, Trip::where('employee_id', $id)->whereDate('date', $tomorrow)->first()->status);
        }

        $this->assertSame(200, Sale::whereIn('employee_id', $ids)->count());
        $this->assertSame(0, Visit::whereIn('employee_id', $ids)->where('distance_m', '>', config('mobile.visit_radius_m'))->count());
        $this->assertSame(5, Visit::whereIn('employee_id', $ids)->get()->pluck('outlet.territory_id')->unique()->count());
    }

    public function test_rentang_tanggal_lewati_minggu_tutup_hari_lalu_dan_aman_diulang(): void
    {
        $this->seed(DemoSeeder::class);

        // Sabtu 24 Okt → Senin 26 Okt (Minggu 25 Okt dilewati).
        DemoMotorisSeeder::$from = '2026-10-24';
        DemoMotorisSeeder::$to = '2026-10-26';
        $this->seed(DemoMotorisSeeder::class);

        // Lanjut ke Selasa: muatan Senin ditutup otomatis; jalankan ulang tidak menambah data.
        DemoMotorisSeeder::$to = '2026-10-27';
        $this->seed(DemoMotorisSeeder::class);
        $this->seed(DemoMotorisSeeder::class);

        $ids = $this->salesmanIds();
        $this->assertSame(0, Trip::whereIn('employee_id', $ids)->whereDate('date', '2026-10-25')->count());
        $this->assertSame(20, Trip::whereIn('employee_id', $ids)->whereDate('date', '<', '2026-10-27')->where('status', TripStatus::Closed->value)->count());
        $this->assertSame(10, Trip::whereIn('employee_id', $ids)->whereDate('date', '2026-10-27')->where('status', TripStatus::Returned->value)->count());
        $this->assertSame(600, Sale::whereIn('employee_id', $ids)->count());
    }

    public function test_muatan_terbuka_di_tanggal_lebih_baru_tidak_menghalangi_riwayat(): void
    {
        DemoMotorisSeeder::$from = DemoMotorisSeeder::$to = '2026-10-26';
        $this->seed(DemoMotorisSeeder::class);

        DemoMotorisSeeder::$from = '2026-10-23';
        DemoMotorisSeeder::$to = '2026-10-27';
        $this->seed(DemoMotorisSeeder::class);

        $ids = $this->salesmanIds();
        $this->assertSame(30, Trip::whereIn('employee_id', $ids)->where('status', TripStatus::Closed->value)->count()); // 23, 24, 26 Okt
        $this->assertSame(10, Trip::whereIn('employee_id', $ids)->where('status', TripStatus::Returned->value)->whereDate('date', '2026-10-27')->count());
    }
}
