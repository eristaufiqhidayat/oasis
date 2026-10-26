<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Depot;
use App\Models\Product;
use App\Models\StockDocument;
use App\Services\StockDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $depot = $this->selectedDepot($request);

        return Inertia::render('Warehouse/Documents', [
            'filters' => ['depot' => $depot, 'jenis' => $request->query('jenis')],
            'depots' => $this->depotOptions($request),
            'types' => self::options(StockDocument::TYPES),
            'documents' => StockDocument::visibleTo($request->user())->with(['depot', 'creator', 'lines.product'])
                ->when($depot, fn ($q) => $q->where('depot_id', $depot))
                ->when($request->query('jenis'), fn ($q, $t) => $q->where('type', $t))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (StockDocument $d) => [
                    'id' => $d->id, 'number' => $d->number, 'type' => StockDocument::TYPES[$d->type], 'type_value' => $d->type,
                    'date' => $d->date->format('d/m/Y'), 'depot' => $d->depot->name, 'reference' => $d->reference, 'note' => $d->note,
                    'user' => $d->creator?->name,
                    'lines' => $d->lines->map(fn ($l) => ['name' => $l->product->name, 'quantity' => $l->quantity]),
                ]),
            'products' => Product::active()->orderBy('name')->get(['id', 'sku', 'name', 'unit'])->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->name} ({$p->sku})", 'unit' => $p->unit]),
            'canManage' => $request->user()->can('stock.manage'),
        ]);
    }

    public function store(Request $request, StockDocumentService $service): RedirectResponse
    {
        abort_unless($request->user()->can('stock.manage'), 403);

        $data = $request->validate([
            'depot_id' => ['required', Rule::in(array_column($this->depotOptions($request), 'value'))],
            'type' => ['required', Rule::in(array_keys(StockDocument::TYPES))],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:type,adjustment'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer'],
        ], ['note.required_if' => 'Alasan penyesuaian wajib diisi.'], ['lines' => 'produk', 'depot_id' => 'depo', 'date' => 'tanggal']);

        $lines = [];
        foreach ($data['lines'] as $line) {
            $lines[$line['product_id']] = ($lines[$line['product_id']] ?? 0) + (int) $line['quantity'];
        }

        $document = $service->post(Depot::findOrFail($data['depot_id']), $data['type'], Carbon::parse($data['date']), $lines, $data['reference'] ?? null, $data['note'] ?? null);

        return back()->with('sukses', "Dokumen {$document->number} disimpan; stok gudang diperbarui.");
    }
}
