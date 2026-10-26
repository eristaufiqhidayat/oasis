<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;
use App\Models\CustomerReturn;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $this->date($request);
        $depot = $this->selectedDepot($request);

        return Inertia::render('Field/Returns', [
            'filters' => ['tanggal' => $date->toDateString(), 'depot' => $depot],
            'depots' => $this->depotOptions($request),
            'returns' => CustomerReturn::visibleTo($request->user())->with(['outlet', 'employee', 'trip', 'lines.product'])
                ->whereDate('returned_at', $date)->when($depot, fn ($q) => $q->where('depot_id', $depot))
                ->orderByDesc('returned_at')->paginate(50)->withQueryString()
                ->through(fn (CustomerReturn $r) => [
                    'id' => $r->id, 'number' => $r->number, 'time' => $r->returned_at->format('H:i'), 'outlet' => $r->outlet->name,
                    'employee' => $r->employee->name, 'trip' => ['id' => $r->trip_id, 'number' => $r->trip->number], 'note' => $r->note,
                    'lines' => $r->lines->map(fn ($l) => ['name' => $l->product->name, 'quantity' => $l->quantity, 'reason' => $l->reason->label()]),
                ]),
        ]);
    }
}
