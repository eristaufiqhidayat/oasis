<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Kinerja kunjungan per motoris: jumlah kunjungan, EC, strike rate, kepatuhan rute, kunjungan jauh dari outlet. */
class VisitReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $this->date($request, 'dari');
        $to = $request->query('sampai') ? $this->date($request, 'sampai') : $from->copy();
        $depot = $this->selectedDepot($request, true);
        $radius = (int) config('mobile.visit_radius_m');

        $salesmen = Employee::visibleTo($request->user())->salesmen()->active()->where('depot_id', $depot)->orderBy('name')->get();

        $stats = DB::table('visits')->whereIn('employee_id', $salesmen->pluck('id'))
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('employee_id')
            ->get([
                'employee_id',
                DB::raw('COUNT(*) as visits'),
                DB::raw("SUM(CASE WHEN outcome = 'sale' THEN 1 ELSE 0 END) as effective"),
                DB::raw("SUM(CASE WHEN distance_m > {$radius} THEN 1 ELSE 0 END) as far"),
                DB::raw('COUNT(DISTINCT outlet_id) as outlets'),
            ])->keyBy('employee_id');

        // Rencana kunjungan = jumlah outlet di rute aktif motoris pada hari-hari dalam rentang.
        $planned = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $routes = SalesRoute::where('is_active', true)->whereIn('employee_id', $salesmen->pluck('id'))
                ->where(fn ($q) => $q->where('weekday', $d->dayOfWeekIso)->orWhereNull('weekday'))->withCount('outlets')->get();
            foreach ($routes as $r) {
                $planned[$r->employee_id] = ($planned[$r->employee_id] ?? 0) + $r->outlets_count;
            }
        }

        return Inertia::render('Reports/Visits', [
            'filters' => ['dari' => $from->toDateString(), 'sampai' => $to->toDateString(), 'depot' => $depot],
            'depots' => $this->depotOptions($request),
            'radius' => $radius,
            'rows' => $salesmen->map(function ($e) use ($stats, $planned) {
                $s = $stats[$e->id] ?? null;
                $visits = (int) ($s->visits ?? 0);
                $effective = (int) ($s->effective ?? 0);

                return [
                    'id' => $e->id, 'name' => $e->name, 'planned' => $planned[$e->id] ?? 0, 'visits' => $visits,
                    'outlets' => (int) ($s->outlets ?? 0), 'effective' => $effective,
                    'strike_rate' => $visits ? round($effective / $visits * 100, 1) : null,
                    'compliance' => ($planned[$e->id] ?? 0) ? round($visits / $planned[$e->id] * 100, 1) : null,
                    'far' => (int) ($s->far ?? 0),
                ];
            })->values(),
        ]);
    }
}
