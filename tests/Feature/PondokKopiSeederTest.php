<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Attendance;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\Visit;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PondokKopiSeeder;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class PondokKopiSeederTest extends TestCase
{
    use BuildsOasis;

    private function salesmen()
    {
        return Employee::whereIn('code', collect(range(1, 10))->map(fn ($i) => sprintf('BKS-PK%02d', $i)))->get();
    }

    public function test_seeder_membuat_check_in_hari_ini_10_motoris_100_transaksi(): void
    {
        $this->seed(PondokKopiSeeder::class);

        $salesmen = $this->salesmen();
        $this->assertCount(10, $salesmen);

        foreach ($salesmen as $salesman) {
            $this->assertTrue(Attendance::where('employee_id', $salesman->id)->whereDate('date', Carbon::today())->whereNotNull('check_out_at')->exists());
            $visits = Visit::where('employee_id', $salesman->id)->whereDate('started_at', Carbon::today())->orderBy('started_at')->get();
            $this->assertCount(12, $visits);
            $this->assertTrue($visits->every(fn ($v) => $v->ended_at !== null && $v->ended_at->gt($v->started_at)));
            $this->assertSame(10, Sale::where('employee_id', $salesman->id)->count());

            $trip = Trip::where('employee_id', $salesman->id)->whereDate('date', Carbon::today())->first();
            $this->assertSame(TripStatus::Returned, $trip->status);
            $this->assertTrue($trip->returned_at->gt($visits->last()->ended_at));
        }

        $ids = $salesmen->pluck('id');
        $this->assertSame(100, Sale::whereIn('employee_id', $ids)->count());
        $this->assertSame(0, Visit::whereIn('employee_id', $ids)->where('distance_m', '>', config('mobile.visit_radius_m'))->count());
    }

    public function test_seeder_aman_dijalankan_ulang_dan_menggantikan_versi_lama(): void
    {
        $this->seed(DemoSeeder::class);
        $old = $this->employee(Depot::where('code', 'BKS')->first(), attributes: ['code' => 'BKS-PK1']);

        $this->seed(PondokKopiSeeder::class);
        $this->seed(PondokKopiSeeder::class);

        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame(10, Trip::whereIn('employee_id', $this->salesmen()->pluck('id'))->count());
        $this->assertSame(100, Sale::whereIn('employee_id', $this->salesmen()->pluck('id'))->count());
    }
}
