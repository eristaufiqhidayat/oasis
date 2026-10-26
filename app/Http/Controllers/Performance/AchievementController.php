<?php

namespace App\Http\Controllers\Performance;

use App\Enums\TargetMetric;
use App\Http\Controllers\Controller;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Product;
use App\Services\TargetService;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AchievementController extends Controller
{
    public function index(Request $request, TargetService $targets): Response
    {
        $period = Period::parse($request->query('bulan'));
        $tab = array_key_exists($request->query('tab'), TargetController::TABS) ? $request->query('tab') : 'depot';
        $depot = $this->selectedDepot($request);
        $ids = $this->depotIds($request);
        $actuals = $targets->actuals($period, $ids);

        // Hari kerja berjalan: untuk proyeksi akhir bulan (realisasi / hari berjalan × jumlah hari).
        $elapsed = $period->isSameMonth(now()) ? now()->day : $period->daysInMonth;

        $row = function (string $name, array $actual, ?int $target, ?int $ecTarget, array $extra = []) use ($period, $elapsed) {
            $projection = (int) round($actual['revenue'] / max(1, $elapsed) * $period->daysInMonth);

            return $extra + [
                'name' => $name,
                'revenue' => $actual['revenue'], 'target' => $target, 'percent' => TargetService::percent($actual['revenue'], $target),
                'projection' => $projection, 'projection_percent' => TargetService::percent($projection, $target),
                'ec' => $actual['ec'], 'ec_target' => $ecTarget, 'ec_percent' => TargetService::percent($actual['ec'], $ecTarget),
            ];
        };

        $empty = ['revenue' => 0, 'ec' => 0];

        $rows = match ($tab) {
            'depot' => (function () use ($request, $targets, $period, $actuals, $row, $empty) {
                $rev = $targets->values($period, TargetMetric::Revenue, 'depot');
                $ec = $targets->values($period, TargetMetric::EffectiveCalls, 'depot');

                return Depot::visibleTo($request->user())->where('is_active', true)->orderBy('name')->get()
                    ->map(fn ($d) => $row($d->name, $actuals['depot'][$d->id] ?? $empty, $rev[$d->id] ?? null, $ec[$d->id] ?? null, ['id' => $d->id]));
            })(),
            'salesman' => (function () use ($request, $targets, $period, $actuals, $row, $empty, $depot) {
                $rev = $targets->values($period, TargetMetric::Revenue, 'employee');
                $ec = $targets->values($period, TargetMetric::EffectiveCalls, 'employee');

                return Employee::visibleTo($request->user())->salesmen()->active()->with('depot')
                    ->when($depot, fn ($q) => $q->where('depot_id', $depot))->orderBy('name')->get()
                    ->map(fn ($e) => $row($e->name, $actuals['employee'][$e->id] ?? $empty, $rev[$e->id] ?? null, $ec[$e->id] ?? null, ['id' => $e->id, 'depot' => $e->depot?->name]));
            })(),
            'product' => (function () use ($targets, $period, $ids, $depot) {
                $sold = $targets->productActuals($period, $ids);
                $goal = $targets->productTargets($period);
                $sum = fn (array $map, int $productId) => collect($map)->filter(fn ($v, $k) => str_ends_with($k, ":{$productId}") && (! $depot || str_starts_with($k, "{$depot}:")))->sum();

                return Product::active()->orderBy('name')->get()->map(function ($p) use ($sold, $goal, $sum) {
                    $qty = $sum($sold, $p->id);
                    $target = $sum($goal, $p->id) ?: null;

                    return ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'quantity' => $qty, 'target' => $target, 'percent' => TargetService::percent($qty, $target)];
                });
            })(),
        };

        $rows = $rows->values();

        return Inertia::render('Performance/Achievement', [
            'filters' => ['bulan' => $period->format('Y-m'), 'tab' => $tab, 'depot' => $depot],
            'tabs' => self::options(TargetController::TABS),
            'depots' => $this->depotOptions($request),
            'rows' => $rows,
            'elapsed' => $elapsed,
            'daysInMonth' => $period->daysInMonth,
            'total' => $tab === 'product' ? null : [
                'revenue' => $rows->sum('revenue'), 'target' => $rows->sum('target'),
                'percent' => TargetService::percent($rows->sum('revenue'), $rows->sum('target')),
                'ec' => $rows->sum('ec'), 'ec_target' => $rows->sum('ec_target'),
            ],
        ]);
    }
}
