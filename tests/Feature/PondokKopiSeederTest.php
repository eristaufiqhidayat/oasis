<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Outlet;
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

    public function test_seeder_membuat_check_in_hari_ini_untuk_3_motoris_pondok_kopi(): void
    {
        $this->seed(PondokKopiSeeder::class);

        $salesmen = Employee::where('code', 'like', 'BKS-PK%')->get();
        $this->assertCount(3, $salesmen);
        $this->assertSame(24, Outlet::where('code', 'like', 'BKS-PK-%')->count());

        foreach ($salesmen as $salesman) {
            $this->assertTrue(Attendance::where('employee_id', $salesman->id)->whereDate('date', Carbon::today())->whereNotNull('check_out_at')->exists());
            $this->assertSame(8, Visit::where('employee_id', $salesman->id)->whereDate('started_at', Carbon::today())->whereNotNull('ended_at')->count());
            $this->assertSame(TripStatus::Returned, Trip::where('employee_id', $salesman->id)->whereDate('date', Carbon::today())->first()->status);
        }

        $this->assertSame(18, Sale::whereIn('employee_id', $salesmen->pluck('id'))->count());
        $this->assertSame(0, Visit::whereIn('employee_id', $salesmen->pluck('id'))->where('distance_m', '>', config('mobile.visit_radius_m'))->count());
    }

    public function test_seeder_aman_dijalankan_ulang_dan_bersama_demo(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(PondokKopiSeeder::class);
        $this->seed(PondokKopiSeeder::class);

        $this->assertSame(3, Trip::whereHas('employee', fn ($q) => $q->where('code', 'like', 'BKS-PK%'))->count());
        $this->assertSame(24, Visit::whereHas('employee', fn ($q) => $q->where('code', 'like', 'BKS-PK%'))->count());
    }
}
