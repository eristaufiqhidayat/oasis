<?php

namespace App\Http\Controllers\Api;

use App\Enums\VisitOutcome;
use App\Models\Outlet;
use App\Models\Visit;
use App\Services\FieldService;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Absensi, muatan (berangkat/selesai), kunjungan, GPS, dan outlet. */
class FieldController extends ApiController
{
    public function __construct(private FieldService $field, private TripService $trips) {}

    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate($this->location() + ['photo_path' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:255']]);
        $a = $this->field->checkIn($this->employee($request), $data);

        return $this->created($a, ['checked_in_at' => $a->check_in_at->toIso8601String()]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $a = $this->field->checkOut($this->employee($request), $request->validate($this->location()));

        return $this->ok(['checked_out_at' => $a->check_out_at->toIso8601String()]);
    }

    public function depart(Request $request): JsonResponse
    {
        $trip = $this->trips->depart($this->activeTrip($request));

        return $this->ok(['id' => $trip->id, 'status' => $trip->status->value]);
    }

    public function finish(Request $request): JsonResponse
    {
        $trip = $this->trips->finish($this->activeTrip($request));

        return $this->ok(['id' => $trip->id, 'status' => $trip->status->value]);
    }

    public function startVisit(Request $request): JsonResponse
    {
        $data = $request->validate($this->location() + [
            'client_uuid' => ['required', 'uuid'],
            'outlet_id' => ['required', 'integer'],
            'started_at' => ['nullable', 'date'],
            'photo_path' => ['nullable', 'string', 'max:255'],
        ]);

        $visit = $this->field->startVisit($this->employee($request), $this->activeTrip($request, false), $data);

        return $this->created($visit, $this->visit($visit));
    }

    public function endVisit(Request $request, string $uuid): JsonResponse
    {
        $visit = Visit::where('client_uuid', $uuid)->where('employee_id', $this->employee($request)->id)->firstOrFail();
        $data = $request->validate([
            'outcome' => ['nullable', Rule::enum(VisitOutcome::class)],
            'no_sale_reason' => ['nullable', Rule::in(array_keys(config('mobile.no_sale_reasons')))],
            'ended_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->ok($this->visit($this->field->endVisit($visit, $data)));
    }

    public function pings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:500'],
            'points.*.recorded_at' => ['required', 'date'],
            'points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy_m' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'points.*.battery' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $stored = $this->field->recordPings($this->employee($request), $this->activeTrip($request, false), $data['points']);

        return $this->ok(['received' => count($data['points']), 'stored' => $stored]);
    }

    /** Cari outlet di depo motoris (untuk kunjungan di luar rute). */
    public function outlets(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $employee = $this->employee($request);

        $query = Outlet::where('depot_id', $employee->depot_id)->whereIn('status', ['active', 'pending'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")));

        if ($request->filled(['latitude', 'longitude'])) {
            // Urut terdekat (perkiraan cepat dengan selisih derajat; cukup untuk jarak beberapa km).
            $lat = (float) $request->query('latitude');
            $lng = (float) $request->query('longitude');
            $query->whereNotNull('latitude')->orderByRaw('ABS(latitude - ?) + ABS(longitude - ?)', [$lat, $lng]);
        } else {
            $query->orderBy('name');
        }

        $outlets = $query->limit(50)->get();
        $balances = DB::table('container_movements')->whereIn('outlet_id', $outlets->pluck('id'))->groupBy('outlet_id')->selectRaw('outlet_id, SUM(quantity) as balance')->pluck('balance', 'outlet_id');

        return $this->ok($outlets->map(fn ($o) => BootstrapController::outletPayload($o, (int) ($balances[$o->id] ?? 0))));
    }

    public function registerOutlet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'channel_id' => ['nullable', 'exists:outlet_channels,id'],
            'photo_path' => ['nullable', 'string', 'max:255'],
        ]);

        $outlet = $this->field->registerOutlet($this->employee($request), $data);

        return $this->created($outlet, BootstrapController::outletPayload($outlet));
    }

    private function location(): array
    {
        return ['latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180']];
    }

    private function visit(Visit $v): array
    {
        return [
            'id' => $v->id, 'client_uuid' => $v->client_uuid, 'outlet_id' => $v->outlet_id, 'started_at' => $v->started_at->toIso8601String(),
            'ended_at' => $v->ended_at?->toIso8601String(), 'outcome' => $v->outcome?->value, 'distance_m' => $v->distance_m,
        ];
    }
}
