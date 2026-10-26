<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $depot = $this->selectedDepot($request, true);
        $from = $this->date($request, 'dari')->startOfDay();
        $to = $request->query('sampai') ? $this->date($request, 'sampai')->endOfDay() : $from->copy()->endOfDay();
        $locationIds = StockLocation::where('depot_id', $depot)->pluck('id');

        $movements = StockMovement::with(['product', 'from.employee', 'to.employee', 'user'])
            ->whereBetween('occurred_at', [$from, $to])
            ->where(fn ($q) => $q->whereIn('from_location_id', $locationIds)->orWhereIn('to_location_id', $locationIds))
            ->when($request->integer('produk'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->query('jenis'), fn ($q, $t) => $q->where('type', $t))
            ->latest('occurred_at')->latest('id')->paginate(100)->withQueryString();

        $name = fn (?StockLocation $l) => $l ? ($l->type->value === 'vehicle' ? 'Kendaraan '.($l->employee?->name ?? '') : $l->type->label()) : '—';

        return Inertia::render('Warehouse/Movements', [
            'filters' => ['depot' => $depot, 'dari' => $from->toDateString(), 'sampai' => $to->toDateString(), 'produk' => $request->integer('produk') ?: null, 'jenis' => $request->query('jenis')],
            'depots' => $this->depotOptions($request),
            'types' => self::options(MovementType::options()),
            'products' => self::options(Product::orderBy('name')->pluck('name', 'id')),
            'movements' => $movements->through(fn (StockMovement $m) => [
                'id' => $m->id, 'at' => $m->occurred_at->format('d/m H:i'), 'type' => $m->type->label(), 'product' => $m->product->name,
                'quantity' => $m->quantity, 'from' => $name($m->from), 'to' => $name($m->to), 'note' => $m->note, 'user' => $m->user?->name,
            ]),
        ]);
    }
}
