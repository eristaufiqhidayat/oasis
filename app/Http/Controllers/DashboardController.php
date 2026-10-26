<?php

namespace App\Http\Controllers;

use App\Enums\OutletStatus;
use App\Enums\SaleStatus;
use App\Enums\TripStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $depot = $this->selectedDepot($request);
        $today = Carbon::today();
        $scope = fn ($q) => $depot ? $q->where('depot_id', $depot) : $q;

        $sales = Sale::visibleTo($user)->where($scope)->completed()->whereDate('sold_at', $today);
        $visits = Visit::visibleTo($user)->whereDate('started_at', $today)
            ->when($depot, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('depot_id', $depot)));
        $salesmen = Employee::visibleTo($user)->salesmen()->active()->where($scope)->count();

        $trend = Sale::visibleTo($user)->where($scope)->completed()
            ->whereDate('sold_at', '>=', $today->copy()->subDays(13))
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->selectRaw('DATE(sold_at) as day, SUM(total) as total')->pluck('total', 'day');

        $top = DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sales.status', SaleStatus::Completed->value)->whereDate('sales.sold_at', '>=', $today->copy()->startOfMonth())
            ->when($user->depotIds() !== null, fn ($q) => $q->whereIn('sales.depot_id', $user->depotIds()))
            ->when($depot, fn ($q) => $q->where('sales.depot_id', $depot))
            ->groupBy('products.id', 'products.name')->orderByDesc(DB::raw('SUM(sale_lines.line_total)'))->limit(5)
            ->get(['products.name', DB::raw('SUM(sale_lines.quantity) as qty'), DB::raw('SUM(sale_lines.line_total) as total')]);

        return Inertia::render('Dashboard', [
            'depot' => $depot,
            'depots' => $this->depotOptions($request),
            'today' => $today->toDateString(),
            'kpi' => [
                'revenue' => (int) (clone $sales)->sum('total'),
                'transactions' => (clone $sales)->count(),
                'visits' => (clone $visits)->count(),
                'effective_calls' => (clone $visits)->where('outcome', 'sale')->count(),
                'attendance' => Attendance::visibleTo($user)->whereDate('date', $today)
                    ->whereHas('employee', fn ($e) => $e->salesmen()->when($depot, fn ($q) => $q->where('depot_id', $depot)))->count(),
                'salesmen' => $salesmen,
                'trips_on_route' => Trip::visibleTo($user)->where($scope)->whereIn('status', [TripStatus::Loaded->value, TripStatus::OnRoute->value])->count(),
                'trips_to_settle' => Trip::visibleTo($user)->where($scope)->whereIn('status', [TripStatus::Returned->value, TripStatus::Unloaded->value])->count(),
                'outlets_pending' => Outlet::visibleTo($user)->where($scope)->where('status', OutletStatus::Pending->value)->count(),
            ],
            'trend' => collect(range(13, 0))->map(fn ($d) => [
                'date' => $day = $today->copy()->subDays($d)->toDateString(),
                'total' => (int) ($trend[$day] ?? 0),
            ])->values(),
            'topProducts' => $top->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'total' => (int) $r->total]),
            'links' => [
                'trips' => $user->can('trip.view'),
                'settlements' => $user->can('settlement.view'),
                'outlets' => $user->can('outlet.verify'),
            ],
        ]);
    }
}
