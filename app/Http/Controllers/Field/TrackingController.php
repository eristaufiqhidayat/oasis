<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LocationPing;
use App\Models\Visit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Jejak GPS motoris per hari + titik kunjungan, atau posisi terakhir semua motoris. */
class TrackingController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $this->date($request);
        $depot = $this->selectedDepot($request, true);
        $employeeId = $request->integer('motoris') ?: null;

        $salesmen = Employee::visibleTo($request->user())->salesmen()->active()->where('depot_id', $depot)->orderBy('name')->get(['id', 'name']);

        $latest = LocationPing::whereIn('employee_id', $salesmen->pluck('id'))->whereDate('recorded_at', $date)
            ->orderBy('recorded_at')->get(['employee_id', 'recorded_at', 'latitude', 'longitude', 'battery'])
            ->groupBy('employee_id')->map(fn ($rows) => $rows->last());

        $track = [];
        $visits = [];

        if ($employeeId && $salesmen->contains('id', $employeeId)) {
            $track = LocationPing::where('employee_id', $employeeId)->whereDate('recorded_at', $date)->orderBy('recorded_at')
                ->get(['recorded_at', 'latitude', 'longitude'])->map(fn ($p) => [$p->latitude, $p->longitude, $p->recorded_at->format('H:i')])->all();
            $visits = Visit::with('outlet')->where('employee_id', $employeeId)->whereDate('started_at', $date)->orderBy('started_at')->get()
                ->filter(fn ($v) => $v->outlet->latitude !== null)
                ->map(fn ($v) => [
                    'lat' => $v->outlet->latitude, 'lng' => $v->outlet->longitude, 'outlet' => $v->outlet->name,
                    'time' => $v->started_at->format('H:i'), 'outcome' => $v->outcome?->label() ?? 'Berlangsung', 'sale' => $v->outcome?->value === 'sale',
                ])->values()->all();
        }

        return Inertia::render('Field/Tracking', [
            'filters' => ['tanggal' => $date->toDateString(), 'depot' => $depot, 'motoris' => $employeeId],
            'depots' => $this->depotOptions($request),
            'salesmen' => $salesmen->map(fn ($s) => [
                'value' => $s->id, 'label' => $s->name,
                'last' => isset($latest[$s->id]) ? ['lat' => $latest[$s->id]->latitude, 'lng' => $latest[$s->id]->longitude, 'time' => $latest[$s->id]->recorded_at->format('H:i'), 'battery' => $latest[$s->id]->battery] : null,
            ])->values(),
            'track' => $track,
            'visits' => $visits,
        ]);
    }
}
