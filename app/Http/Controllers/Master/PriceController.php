<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Depot;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Harga jual: harga nasional + harga khusus depo, berlaku mulai tanggal tertentu. */
class PriceController extends Controller
{
    public function index(Request $request, Pricing $pricing): Response
    {
        abort_unless($request->user()->can('product.view'), 403);

        $depot = $this->selectedDepot($request);
        $date = $this->date($request);
        $national = $pricing->priceList(null, $date);
        $current = $depot ? $pricing->priceList($depot, $date) : $national;
        $overrides = $depot ? ProductPrice::where('depot_id', $depot)->whereDate('effective_from', '<=', $date->toDateString())->pluck('product_id')->flip() : collect();
        $upcoming = ProductPrice::whereDate('effective_from', '>', $date->toDateString())
            ->where(fn ($q) => $depot ? $q->where('depot_id', $depot) : $q->whereNull('depot_id'))
            ->orderBy('effective_from')->get()->groupBy('product_id');

        return Inertia::render('Master/Prices', [
            'depot' => $depot,
            'date' => $date->toDateString(),
            'depots' => $this->depotOptions($request),
            'products' => Product::active()->orderBy('name')->get(['id', 'sku', 'name', 'unit'])->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'unit' => $p->unit,
                'national' => $national[$p->id] ?? null,
                'price' => $current[$p->id] ?? null,
                'is_override' => $overrides->has($p->id),
                'upcoming' => ($upcoming[$p->id] ?? collect())->map(fn ($u) => ['price' => $u->price, 'from' => $u->effective_from->toDateString()])->values(),
            ]),
            'canManage' => $request->user()->can('product.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('product.manage'), 403);

        $data = $request->validate([
            'depot_id' => ['nullable', Rule::in(array_column($this->depotOptions($request), 'value'))],
            'effective_from' => ['required', 'date'],
            'prices' => ['required', 'array'],
            'prices.*' => ['nullable', 'integer', 'min:0'],
        ], [], ['effective_from' => 'tanggal berlaku']);

        if ($data['depot_id'] === null && $request->user()->depotIds() !== null) {
            return back()->with('galat', 'Harga nasional hanya bisa diubah akun pusat (semua depo).');
        }

        $from = Carbon::parse($data['effective_from'])->toDateString();
        $changed = 0;

        foreach ($data['prices'] as $productId => $price) {
            if ($price === null || ! Product::whereKey($productId)->exists()) {
                continue;
            }

            ProductPrice::put((int) $productId, $data['depot_id'] ? (int) $data['depot_id'] : null, $from, (int) $price, $request->user()->id);
            $changed++;
        }

        $scope = $data['depot_id'] ? Depot::find($data['depot_id'])->name : 'nasional';
        ActivityLog::record('price.update', "Harga {$scope} mulai {$from}: {$changed} produk");

        return back()->with('sukses', "{$changed} harga {$scope} disimpan, berlaku mulai {$from}.");
    }

    /** Hapus harga khusus depo sehingga kembali memakai harga nasional. */
    public function resetDepot(Request $request, int $depot, int $product): RedirectResponse
    {
        abort_unless($request->user()->can('product.manage'), 403);
        $this->abortUnlessDepot($request, $depot);

        ProductPrice::where('depot_id', $depot)->where('product_id', $product)->delete();

        return back()->with('sukses', 'Harga khusus depo dihapus; produk memakai harga nasional.');
    }
}
