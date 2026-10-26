<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\StockLocationType;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Depot;
use App\Models\Product;
use App\Models\StockDocument;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class StockDocumentService
{
    public function __construct(private Inventory $inventory) {}

    /**
     * Penerimaan (qty > 0) atau penyesuaian opname (qty boleh negatif) ke gudang depo.
     *
     * @param  array<int, int>  $lines  product_id => qty
     */
    public function post(Depot $depot, string $type, CarbonInterface $date, array $lines, ?string $reference = null, ?string $note = null): StockDocument
    {
        $lines = array_filter(array_map('intval', $lines));

        if ($lines === []) {
            throw new BusinessRuleException('Isi minimal satu produk dengan jumlah selain 0.', field: 'lines');
        }

        if ($type === 'receipt' && min($lines) < 0) {
            throw new BusinessRuleException('Jumlah penerimaan tidak boleh negatif.', field: 'lines');
        }

        if (Product::whereIn('id', array_keys($lines))->count() !== count($lines)) {
            throw new BusinessRuleException('Ada produk yang tidak dikenal.', field: 'lines');
        }

        return DB::transaction(function () use ($depot, $type, $date, $lines, $reference, $note) {
            $document = StockDocument::create([
                'number' => Numbering::next('stock_documents', $type === 'receipt' ? 'TRM' : 'ADJ', $date),
                'type' => $type,
                'depot_id' => $depot->id,
                'date' => $date,
                'reference' => $reference,
                'note' => $note,
                'created_by' => auth()->id(),
            ]);

            $warehouse = $depot->location(StockLocationType::Warehouse);
            $movement = $type === 'receipt' ? MovementType::Receipt : MovementType::Adjustment;

            foreach ($lines as $productId => $qty) {
                $document->lines()->create(['product_id' => $productId, 'quantity' => $qty]);
                $this->inventory->move($productId, $qty, null, $warehouse, $movement, $document, $note);
            }

            ActivityLog::record('stock.'.$type, "Dokumen stok {$document->number} ({$depot->name})", $document);

            return $document;
        });
    }
}
