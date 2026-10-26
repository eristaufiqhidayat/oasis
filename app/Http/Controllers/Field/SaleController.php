<?php

namespace App\Http\Controllers\Field;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $this->date($request);
        $depot = $this->selectedDepot($request);
        $q = trim((string) $request->query('q'));

        $query = Sale::visibleTo($request->user())->with(['outlet', 'employee'])
            ->whereDate('sold_at', $date)
            ->when($depot, fn ($w) => $w->where('depot_id', $depot))
            ->when($request->integer('motoris'), fn ($w, $id) => $w->where('employee_id', $id))
            ->when($request->query('status'), fn ($w, $s) => $w->where('status', $s))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")
                ->orWhereHas('outlet', fn ($o) => $o->where('name', 'like', "%{$q}%"))));

        $totals = (clone $query)->where('status', SaleStatus::Completed->value)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')->first();

        return Inertia::render('Field/Sales', [
            'filters' => ['tanggal' => $date->toDateString(), 'depot' => $depot, 'motoris' => $request->integer('motoris') ?: null, 'status' => $request->query('status'), 'q' => $q],
            'depots' => $this->depotOptions($request),
            'salesmen' => $this->salesmanOptions($request, $depot),
            'statuses' => self::options(SaleStatus::options()),
            'totals' => ['count' => (int) $totals->count, 'total' => (int) $totals->total],
            'sales' => $query->orderByDesc('sold_at')->paginate(50)->withQueryString()->through(fn (Sale $s) => [
                'id' => $s->id, 'number' => $s->number, 'time' => $s->sold_at->format('H:i'), 'outlet' => $s->outlet->name,
                'employee' => $s->employee->name, 'total' => $s->total, 'payment' => $s->payment_method->label(),
                'status' => ['label' => $s->status->label(), 'variant' => $s->status === SaleStatus::Void ? 'danger' : 'success'],
            ]),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $sale = Sale::visibleTo($request->user())->with(['outlet', 'employee', 'depot', 'trip', 'lines.product'])->findOrFail($id);

        return Inertia::render('Field/SaleShow', [
            'sale' => [
                'id' => $sale->id, 'number' => $sale->number, 'sold_at' => $sale->sold_at->format('d/m/Y H:i'),
                'outlet' => $sale->outlet->name, 'outlet_code' => $sale->outlet->code, 'employee' => $sale->employee->name,
                'depot' => $sale->depot->name, 'trip' => ['id' => $sale->trip_id, 'number' => $sale->trip->number],
                'payment' => $sale->payment_method->label(), 'subtotal' => $sale->subtotal, 'discount' => $sale->discount, 'total' => $sale->total,
                'status' => $sale->status->value, 'status_label' => $sale->status->label(),
                'void_reason' => $sale->void_reason, 'voided_at' => $sale->voided_at?->format('d/m/Y H:i'),
                'lines' => $sale->lines->map(fn ($l) => [
                    'sku' => $l->product->sku, 'name' => $l->product->name, 'quantity' => $l->quantity, 'unit_price' => $l->unit_price, 'line_total' => $l->line_total,
                ]),
            ],
            'canVoid' => $request->user()->can('sale.void') && $sale->status === SaleStatus::Completed,
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function void(Request $request, int $id, SalesService $service): RedirectResponse
    {
        abort_unless($request->user()->can('sale.void'), 403);
        $sale = Sale::visibleTo($request->user())->findOrFail($id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'alasan']);
        $service->void($sale, $data['reason']);

        return back()->with('sukses', "Penjualan {$sale->number} dibatalkan; barang kembali ke stok kendaraan.");
    }
}
