<?php

namespace App\Http\Controllers\Performance;

use App\Enums\TargetMetric;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Product;
use App\Services\TargetService;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Target bulanan: per depo (nilai & EC), per motoris (nilai & EC), per SKU per depo (qty). */
class TargetController extends Controller
{
    public const TABS = ['depot' => 'Per Depo', 'salesman' => 'Per Motoris', 'product' => 'Per SKU'];

    public function index(Request $request, TargetService $targets): Response
    {
        $period = Period::parse($request->query('bulan'));
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'depot';
        $depot = $this->selectedDepot($request, $tab !== 'depot');

        $revenue = $targets->values($period, TargetMetric::Revenue, $tab === 'depot' ? 'depot' : 'employee');
        $ec = $targets->values($period, TargetMetric::EffectiveCalls, $tab === 'depot' ? 'depot' : 'employee');

        $employeeRevenue = $targets->values($period, TargetMetric::Revenue, 'employee');

        $rows = match ($tab) {
            'depot' => Depot::visibleTo($request->user())->where('is_active', true)->orderBy('name')->get()->map(function ($d) use ($revenue, $ec, $employeeRevenue) {
                $team = Employee::salesmen()->active()->where('depot_id', $d->id)->pluck('id');
                $assigned = array_sum(array_intersect_key($employeeRevenue, array_flip($team->all())));

                return ['id' => $d->id, 'name' => $d->name, 'revenue' => $revenue[$d->id] ?? null, 'ec' => $ec[$d->id] ?? null, 'assigned' => $assigned, 'salesmen' => $team->count()];
            }),
            'salesman' => Employee::visibleTo($request->user())->salesmen()->active()->where('depot_id', $depot)->orderBy('name')->get()
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'code' => $e->code, 'revenue' => $revenue[$e->id] ?? null, 'ec' => $ec[$e->id] ?? null]),
            'product' => (function () use ($targets, $period, $depot) {
                $goal = $targets->productTargets($period);

                return Product::active()->orderBy('name')->get()->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit, 'quantity' => $goal["{$depot}:{$p->id}"] ?? null,
                ]);
            })(),
        };

        return Inertia::render('Performance/Targets', [
            'filters' => ['bulan' => $period->format('Y-m'), 'tab' => $tab, 'depot' => $depot],
            'tabs' => self::options(self::TABS),
            'depots' => $this->depotOptions($request),
            'rows' => $rows->values(),
            'depotTarget' => $depot ? ['revenue' => $targets->values($period, TargetMetric::Revenue, 'depot')[$depot] ?? null, 'ec' => $targets->values($period, TargetMetric::EffectiveCalls, 'depot')[$depot] ?? null] : null,
            'canManage' => $request->user()->can('target.manage'),
        ]);
    }

    public function store(Request $request, TargetService $targets): RedirectResponse
    {
        abort_unless($request->user()->can('target.manage'), 403);

        $data = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'tab' => ['required', Rule::in(array_keys(self::TABS))],
            'depot' => ['nullable', 'integer'],
            'rows' => ['present', 'array'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.revenue' => ['nullable', 'integer', 'min:0'],
            'rows.*.ec' => ['nullable', 'integer', 'min:0'],
            'rows.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $period = Period::parse($data['bulan']);
        $allowedDepots = array_column($this->depotOptions($request), 'value');

        DB::transaction(function () use ($data, $period, $targets, $allowedDepots, $request) {
            foreach ($data['rows'] as $row) {
                if ($data['tab'] === 'product') {
                    if (in_array((int) $data['depot'], $allowedDepots, true)) {
                        $targets->set($period, TargetMetric::Quantity, ['depot_id' => (int) $data['depot'], 'product_id' => $row['id']], $row['quantity'] ?? null);
                    }

                    continue;
                }

                $scope = $data['tab'] === 'depot' ? ['depot_id' => $row['id']] : ['employee_id' => $row['id']];
                $allowed = $data['tab'] === 'depot'
                    ? in_array($row['id'], $allowedDepots, true)
                    : Employee::visibleTo($request->user())->whereKey($row['id'])->exists();

                if ($allowed) {
                    $targets->set($period, TargetMetric::Revenue, $scope, $row['revenue'] ?? null);
                    $targets->set($period, TargetMetric::EffectiveCalls, $scope, $row['ec'] ?? null);
                }
            }
        });

        ActivityLog::record('target.update', "Target {$data['bulan']} (".self::TABS[$data['tab']].') disimpan');

        return back()->with('sukses', 'Target disimpan.');
    }

    public function distribute(Request $request, TargetService $targets): RedirectResponse
    {
        abort_unless($request->user()->can('target.manage'), 403);
        $data = $request->validate(['bulan' => ['required', 'date_format:Y-m'], 'depot' => ['required', 'integer']]);
        $this->abortUnlessDepot($request, (int) $data['depot']);

        $count = $targets->distributeEvenly(Period::parse($data['bulan']), Depot::findOrFail($data['depot']));

        return back()->with('sukses', "Target depo dibagi rata ke {$count} motoris.");
    }
}
