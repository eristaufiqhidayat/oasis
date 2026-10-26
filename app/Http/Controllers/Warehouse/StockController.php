<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Saldo stok per lokasi (gudang, barang rusak, kendaraan motoris) satu depo. */
class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $depot = $this->selectedDepot($request, true);
        $locations = StockLocation::where('depot_id', $depot)->with('employee')->orderByRaw("CASE type WHEN 'warehouse' THEN 0 WHEN 'damaged' THEN 1 ELSE 2 END")->orderBy('name')->get();
        $balances = DB::table('stock_balances')->whereIn('location_id', $locations->pluck('id'))->where('quantity', '!=', 0)->get()
            ->groupBy('product_id')->map(fn ($rows) => $rows->pluck('quantity', 'location_id')->map(fn ($q) => (int) $q));

        // Kendaraan kosong tidak perlu jadi kolom.
        $locations = $locations->filter(fn ($l) => $l->type->value !== 'vehicle' || $balances->contains(fn ($b) => isset($b[$l->id])))->values();

        return Inertia::render('Warehouse/Stock', [
            'depot' => $depot,
            'depots' => $this->depotOptions($request),
            'locations' => $locations->map(fn ($l) => ['id' => $l->id, 'name' => $l->type->value === 'vehicle' ? ($l->employee?->name ?? $l->name) : $l->type->label(), 'type' => $l->type->value]),
            'products' => Product::orderBy('name')->get(['id', 'sku', 'name', 'unit', 'units_per_case', 'is_active'])
                ->filter(fn ($p) => $p->is_active || isset($balances[$p->id]))
                ->map(fn ($p) => [
                    'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'unit' => $p->unit, 'units_per_case' => $p->units_per_case,
                    'balances' => $balances[$p->id] ?? (object) [],
                ])->values(),
            'canManage' => $request->user()->can('stock.manage'),
        ]);
    }
}
