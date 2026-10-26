<?php

namespace App\Services;

use App\Enums\OutletStatus;
use App\Enums\VisitOutcome;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LocationPing;
use App\Models\Outlet;
use App\Models\Territory;
use App\Models\Trip;
use App\Models\Visit;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Absensi, kunjungan, jejak GPS, dan pendaftaran outlet baru dari HP. */
class FieldService
{
    public function checkIn(Employee $employee, array $data): Attendance
    {
        $today = Carbon::today();

        $existing = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();

        return $existing ?? Attendance::create(
            [
                'employee_id' => $employee->id,
                'date' => $today->toDateString(),
                'check_in_at' => now(),
                'check_in_latitude' => $data['latitude'] ?? null,
                'check_in_longitude' => $data['longitude'] ?? null,
                'check_in_photo' => $data['photo_path'] ?? null,
                'note' => $data['note'] ?? null,
            ],
        );
    }

    public function checkOut(Employee $employee, array $data): Attendance
    {
        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', Carbon::today())->first();

        if (! $attendance) {
            throw new BusinessRuleException('Belum absen masuk hari ini.', 'not_checked_in');
        }

        if (! $attendance->check_out_at) {
            $attendance->update([
                'check_out_at' => now(),
                'check_out_latitude' => $data['latitude'] ?? null,
                'check_out_longitude' => $data['longitude'] ?? null,
            ]);
        }

        return $attendance;
    }

    public function startVisit(Employee $employee, ?Trip $trip, array $data): Visit
    {
        if ($existing = Visit::where('client_uuid', $data['client_uuid'])->first()) {
            return $existing;
        }

        $outlet = Outlet::where('id', $data['outlet_id'])->where('depot_id', $employee->depot_id)->first();

        if (! $outlet) {
            throw new BusinessRuleException('Outlet tidak ditemukan di depo Anda.', 'outlet_not_found', 'outlet_id');
        }

        $distance = isset($data['latitude'], $data['longitude']) && $outlet->latitude !== null && $outlet->longitude !== null
            ? Geo::distance((float) $data['latitude'], (float) $data['longitude'], $outlet->latitude, $outlet->longitude)
            : null;

        return Visit::create([
            'client_uuid' => $data['client_uuid'],
            'trip_id' => $trip?->id,
            'outlet_id' => $outlet->id,
            'employee_id' => $employee->id,
            'started_at' => isset($data['started_at']) ? Carbon::parse($data['started_at']) : now(),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'distance_m' => $distance,
            'photo_path' => $data['photo_path'] ?? null,
        ]);
    }

    public function endVisit(Visit $visit, array $data): Visit
    {
        $outcome = isset($data['outcome']) ? VisitOutcome::from($data['outcome']) : ($visit->outcome ?? VisitOutcome::NoSale);

        if ($outcome !== VisitOutcome::Sale && $visit->sales()->exists()) {
            $outcome = VisitOutcome::Sale;
        }

        $visit->update([
            'ended_at' => $visit->ended_at ?? (isset($data['ended_at']) ? Carbon::parse($data['ended_at']) : now()),
            'outcome' => $outcome,
            'no_sale_reason' => $outcome === VisitOutcome::Sale ? null : ($data['no_sale_reason'] ?? $visit->no_sale_reason),
            'note' => $data['note'] ?? $visit->note,
        ]);

        return $visit;
    }

    /** @param array<int, array{recorded_at: string, latitude: float, longitude: float, accuracy_m?: int, battery?: int}> $points */
    public function recordPings(Employee $employee, ?Trip $trip, array $points): int
    {
        $rows = collect($points)->map(fn ($p) => [
            'employee_id' => $employee->id,
            'trip_id' => $trip?->id,
            'recorded_at' => Carbon::parse($p['recorded_at'])->toDateTimeString(),
            'latitude' => $p['latitude'],
            'longitude' => $p['longitude'],
            'accuracy_m' => $p['accuracy_m'] ?? null,
            'battery' => $p['battery'] ?? null,
            'created_at' => now(),
        ])->unique('recorded_at')->values()->all();

        return DB::table((new LocationPing)->getTable())->insertOrIgnore($rows);
    }

    /** Outlet baru dari HP: status pending sampai diverifikasi; area diisi otomatis dari polygon. */
    public function registerOutlet(Employee $employee, array $data): Outlet
    {
        if ($existing = Outlet::where('client_uuid', $data['client_uuid'])->first()) {
            return $existing;
        }

        $territory = isset($data['latitude'], $data['longitude'])
            ? $this->territoryAt($employee->depot_id, (float) $data['latitude'], (float) $data['longitude'])
            : null;

        return Outlet::create([
            'code' => $this->newOutletCode($employee),
            'name' => $data['name'],
            'owner_name' => $data['owner_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'district' => $data['district'] ?? null,
            'city' => $data['city'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'depot_id' => $employee->depot_id,
            'territory_id' => $territory?->id,
            'channel_id' => $data['channel_id'] ?? null,
            'status' => OutletStatus::Pending,
            'photo_path' => $data['photo_path'] ?? null,
            'client_uuid' => $data['client_uuid'],
            'registered_by' => $employee->id,
        ]);
    }

    public function territoryAt(?int $depotId, float $lat, float $lng): ?Territory
    {
        return Territory::where('depot_id', $depotId)->where('is_active', true)->whereNotNull('boundary')->get()
            ->first(fn (Territory $t) => Geo::contains($t->boundary, $lat, $lng));
    }

    private function newOutletCode(Employee $employee): string
    {
        do {
            $code = 'NOO-'.($employee->depot?->code ?? 'X').'-'.strtoupper(Str::random(6));
        } while (Outlet::where('code', $code)->exists());

        return $code;
    }
}
