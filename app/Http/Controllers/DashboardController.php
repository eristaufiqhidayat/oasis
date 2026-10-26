<?php

namespace App\Http\Controllers;

use App\Enums\OutletStatus;
use App\Enums\TripStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\Visit;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $user = $request->user();
        $depot = $this->selectedDepot($request);
        $today = Carbon::today();
        $period = array_key_exists((string) $request->query('periode'), DashboardService::PERIODS) ? (string) $request->query('periode') : DashboardService::DEFAULT_PERIOD;
        [$from, $to] = DashboardService::range($period, $today);
        $scope = fn ($q) => $depot ? $q->where('depot_id', $depot) : $q;

        $sales = Sale::visibleTo($user)->where($scope)->completed()->whereDate('sold_at', $today);
        $visits = Visit::visibleTo($user)->whereDate('started_at', $today)
            ->when($depot, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('depot_id', $depot)));
        $salesmen = Employee::visibleTo($user)->salesmen()->active()->where($scope)->count();

        return Inertia::render('Dashboard', [
            'depot' => $depot,
            'depots' => $this->depotOptions($request),
            'today' => $today->toDateString(),
            'period' => $period,
            'periods' => self::options(DashboardService::PERIODS),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'charts' => $dashboard->charts($user, $depot, $from, $to),
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
            'links' => [
                'trips' => $user->can('trip.view'),
                'settlements' => $user->can('settlement.view'),
                'outlets' => $user->can('outlet.verify'),
            ],
        ]);
    }
}
