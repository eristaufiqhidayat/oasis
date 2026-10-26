<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Saldo galon per outlet: galon isi terkirim − galon kosong ditarik. */
class ContainerReportController extends Controller
{
    public function index(Request $request): Response
    {
        $depot = $this->selectedDepot($request, true);
        $q = trim((string) $request->query('q'));

        $totals = DB::table('container_movements')
            ->select('outlet_id',
                DB::raw("SUM(CASE WHEN type = 'delivered' THEN quantity ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN type = 'collected' THEN -quantity ELSE 0 END) as collected"),
                DB::raw('SUM(quantity) as balance'),
                DB::raw('MAX(occurred_at) as last_at'))
            ->groupBy('outlet_id');

        $rows = Outlet::query()->joinSub($totals, 't', 't.outlet_id', '=', 'outlets.id')
            ->where('outlets.depot_id', $depot)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('outlets.name', 'like', "%{$q}%")->orWhere('outlets.code', 'like', "%{$q}%")))
            ->orderByDesc('t.balance')
            ->select('outlets.id', 'outlets.code', 'outlets.name', 't.delivered', 't.collected', 't.balance', 't.last_at')
            ->paginate(50)->withQueryString()
            ->through(fn ($o) => [
                'id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'delivered' => (int) $o->delivered,
                'collected' => (int) $o->collected, 'balance' => (int) $o->balance, 'last_at' => substr((string) $o->last_at, 0, 10),
            ]);

        $sum = DB::table('container_movements')->join('outlets', 'outlets.id', '=', 'container_movements.outlet_id')
            ->where('outlets.depot_id', $depot)->sum('container_movements.quantity');

        return Inertia::render('Reports/Containers', [
            'filters' => ['depot' => $depot, 'q' => $q],
            'depots' => $this->depotOptions($request),
            'rows' => $rows,
            'totalBalance' => (int) $sum,
        ]);
    }
}
