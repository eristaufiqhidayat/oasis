<?php

namespace App\Http\Controllers\Field;

use App\Enums\VisitOutcome;
use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $this->date($request);
        $depot = $this->selectedDepot($request);
        $radius = (int) config('mobile.visit_radius_m');
        $reasons = config('mobile.no_sale_reasons');

        $query = Visit::visibleTo($request->user())->with(['outlet', 'employee'])->withSum(['sales' => fn ($q) => $q->where('status', 'completed')], 'total')
            ->whereDate('started_at', $date)
            ->when($depot, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('depot_id', $depot)))
            ->when($request->integer('motoris'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('hasil'), fn ($q, $v) => $q->where('outcome', $v));

        return Inertia::render('Field/Visits', [
            'filters' => ['tanggal' => $date->toDateString(), 'depot' => $depot, 'motoris' => $request->integer('motoris') ?: null, 'hasil' => $request->query('hasil')],
            'depots' => $this->depotOptions($request),
            'salesmen' => $this->salesmanOptions($request, $depot),
            'outcomes' => self::options(VisitOutcome::options()),
            'radius' => $radius,
            'visits' => $query->orderByDesc('started_at')->paginate(50)->withQueryString()->through(fn (Visit $v) => [
                'id' => $v->id, 'employee' => $v->employee->name, 'outlet' => $v->outlet->name, 'outlet_code' => $v->outlet->code,
                'start' => $v->started_at->format('H:i'), 'end' => $v->ended_at?->format('H:i'),
                'duration' => $v->ended_at ? (int) $v->started_at->diffInMinutes($v->ended_at) : null,
                'distance' => $v->distance_m, 'far' => $v->distance_m !== null && $v->distance_m > $radius,
                'outcome' => $v->outcome ? ['label' => $v->outcome->label(), 'variant' => $v->outcome === VisitOutcome::Sale ? 'success' : 'secondary'] : ['label' => 'Berlangsung', 'variant' => 'primary'],
                'reason' => $v->no_sale_reason ? ($reasons[$v->no_sale_reason] ?? $v->no_sale_reason) : null,
                'total' => (int) ($v->sales_sum_total ?? 0),
            ]),
        ]);
    }
}
