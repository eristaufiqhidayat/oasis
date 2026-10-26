<?php

namespace App\Http\Controllers\Field;

use App\Enums\StockLocationType;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Trip;
use App\Services\Inventory;
use App\Services\SettlementService;
use App\Services\TripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public const STATUS_VARIANT = [
        'loaded' => 'primary', 'on_route' => 'primary', 'returned' => 'warning', 'unloaded' => 'warning', 'closed' => 'success', 'cancelled' => 'secondary',
    ];

    public function index(Request $request): Response
    {
        $date = $this->date($request);
        $depot = $this->selectedDepot($request);

        $trips = Trip::visibleTo($request->user())->with(['employee', 'depot', 'settlement'])->withSum('lines', 'loaded_qty')
            ->whereDate('date', $date)
            ->when($depot, fn ($q) => $q->where('depot_id', $depot))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('number')->get();

        $loadedIds = $trips->where('status', '!=', TripStatus::Cancelled)->pluck('employee_id');

        return Inertia::render('Field/Trips', [
            'filters' => ['tanggal' => $date->toDateString(), 'depot' => $depot, 'status' => $request->query('status')],
            'depots' => $this->depotOptions($request),
            'statuses' => self::options(TripStatus::options()),
            'trips' => $trips->map(fn (Trip $t) => $this->summaryRow($t))->values(),
            'withoutTrip' => Employee::visibleTo($request->user())->salesmen()->active()
                ->when($depot, fn ($q) => $q->where('depot_id', $depot))
                ->whereNotIn('id', $loadedIds)->orderBy('name')->pluck('name'),
            'canLoad' => $request->user()->can('trip.load'),
        ]);
    }

    public function create(Request $request, Inventory $inventory): Response
    {
        abort_unless($request->user()->can('trip.load'), 403);
        $depot = $this->selectedDepot($request, true);
        $warehouse = $depot ? Depot::find($depot)->location(StockLocationType::Warehouse) : null;
        $stock = $warehouse ? $inventory->balances($warehouse->id) : [];

        return Inertia::render('Field/TripCreate', [
            'depot' => $depot,
            'depots' => $this->depotOptions($request),
            'date' => Carbon::today()->toDateString(),
            'salesmen' => $this->salesmanOptions($request, $depot),
            'products' => Product::active()->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'units_per_case'])->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'unit' => $p->unit, 'units_per_case' => $p->units_per_case,
                'stock' => $stock[$p->id] ?? 0,
            ]),
        ]);
    }

    public function store(Request $request, TripService $trips): RedirectResponse
    {
        abort_unless($request->user()->can('trip.load'), 403);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'lines' => ['required', 'array'],
            'lines.*' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['employee_id' => 'motoris', 'date' => 'tanggal', 'lines' => 'produk']);

        $salesman = Employee::visibleTo($request->user())->findOrFail($data['employee_id']);
        $trip = $trips->load($salesman, Carbon::parse($data['date']), $data['lines'], $data['note'] ?? null);

        return redirect("/muatan/{$trip->id}")->with('sukses', "Muatan {$trip->number} dibuat. Motoris bisa mulai berjualan dari HP.");
    }

    public function show(Request $request, int $id, SettlementService $settlements): Response
    {
        $trip = Trip::visibleTo($request->user())->with(['employee', 'depot', 'settlement.receiver', 'settlement.approver'])->findOrFail($id);

        return Inertia::render('Field/TripShow', [
            'trip' => $this->summaryRow($trip) + [
                'note' => $trip->note,
                'loaded_at' => $trip->loaded_at?->format('d/m/Y H:i'),
                'departed_at' => $trip->departed_at?->format('H:i'),
                'returned_at' => $trip->returned_at?->format('H:i'),
                'unloaded_at' => $trip->unloaded_at?->format('d/m/Y H:i'),
            ],
            'summary' => $settlements->summary($trip),
            'sales' => $trip->sales()->with('outlet')->orderBy('sold_at')->get()->map(fn ($s) => [
                'id' => $s->id, 'number' => $s->number, 'time' => $s->sold_at->format('H:i'), 'outlet' => $s->outlet->name,
                'total' => $s->total, 'payment' => $s->payment_method->label(), 'status' => $s->status->value,
            ]),
            'returns' => $trip->returns()->with(['outlet', 'lines.product'])->get()->map(fn ($r) => [
                'id' => $r->id, 'number' => $r->number, 'outlet' => $r->outlet->name,
                'lines' => $r->lines->map(fn ($l) => "{$l->product->name} × {$l->quantity} ({$l->reason->label()})")->join(', '),
            ]),
            'can' => [
                'cancel' => $request->user()->can('trip.load') && $trip->status === TripStatus::Loaded,
                'unload' => $request->user()->can('trip.unload') && in_array($trip->status, [TripStatus::OnRoute, TripStatus::Returned], true),
                'settle' => $request->user()->can('settlement.view') && $trip->settlement !== null,
            ],
        ]);
    }

    public function cancel(Request $request, int $id, TripService $trips): RedirectResponse
    {
        abort_unless($request->user()->can('trip.load'), 403);
        $trip = Trip::visibleTo($request->user())->findOrFail($id);
        $trips->cancel($trip);

        return back()->with('sukses', "Muatan {$trip->number} dibatalkan; barang kembali ke gudang.");
    }

    public function unload(Request $request, int $id, TripService $trips): RedirectResponse
    {
        abort_unless($request->user()->can('trip.unload'), 403);
        $trip = Trip::visibleTo($request->user())->findOrFail($id);

        $data = $request->validate([
            'counts' => ['present', 'array'],
            'counts.*.good' => ['nullable', 'integer', 'min:0'],
            'counts.*.damaged' => ['nullable', 'integer', 'min:0'],
            'empty_containers' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['empty_containers' => 'galon kosong diterima']);

        $trips->unload($trip, $data['counts'], (int) $data['empty_containers'], $data['note'] ?? null);

        return redirect("/setoran/{$trip->id}")->with('sukses', "Muatan {$trip->number} dibongkar. Lanjutkan setoran uang.");
    }

    private function summaryRow(Trip $t): array
    {
        return [
            'id' => $t->id, 'number' => $t->number, 'date' => $t->date->toDateString(), 'employee' => $t->employee->name,
            'depot' => $t->depot->name, 'loaded_qty' => (int) ($t->lines_sum_loaded_qty ?? $t->lines()->sum('loaded_qty')),
            'status' => ['value' => $t->status->value, 'label' => $t->status->label(), 'variant' => self::STATUS_VARIANT[$t->status->value]],
            'settled' => $t->settlement?->approved_at !== null,
        ];
    }
}
