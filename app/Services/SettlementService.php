<?php

namespace App\Services;

use App\Enums\ContainerMovementType;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\Resolution;
use App\Enums\SaleStatus;
use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

/**
 * Setoran & rekonsiliasi per muatan. Angka dihitung langsung dari data transaksi sampai disetujui,
 * lalu dibekukan di tabel settlements.
 */
class SettlementService
{
    public function __construct(private Inventory $inventory, private Pricing $pricing) {}

    public function cashExpected(Trip $trip): int
    {
        return (int) $trip->sales()->where('status', SaleStatus::Completed->value)
            ->where('payment_method', PaymentMethod::Cash->value)->sum('total');
    }

    /** Ringkasan lengkap untuk layar rekonsiliasi. */
    public function summary(Trip $trip): array
    {
        $trip->loadMissing(['lines.product', 'settlement']);

        $sold = DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.trip_id', $trip->id)->where('sales.status', SaleStatus::Completed->value)
            ->groupBy('sale_lines.product_id')->selectRaw('sale_lines.product_id as pid, SUM(sale_lines.quantity) as qty')->pluck('qty', 'pid');

        $returned = DB::table('customer_return_lines')->join('customer_returns', 'customer_returns.id', '=', 'customer_return_lines.customer_return_id')
            ->where('customer_returns.trip_id', $trip->id)
            ->groupBy('customer_return_lines.product_id')->selectRaw('customer_return_lines.product_id as pid, SUM(customer_return_lines.quantity) as qty')->pluck('qty', 'pid');

        $prices = $this->pricing->priceList($trip->depot_id, $trip->date);
        $inVehicle = $this->inventory->balances($trip->vehicle_location_id);
        $unloaded = $trip->unloaded_at !== null;

        $productIds = collect($trip->lines->pluck('product_id'))->merge($sold->keys())->merge($returned->keys())->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = $productIds->map(function ($productId) use ($trip, $sold, $returned, $prices, $inVehicle, $unloaded, $products) {
            $line = $trip->lines->firstWhere('product_id', $productId);
            $loaded = (int) ($line?->loaded_qty ?? 0);
            $s = (int) ($sold[$productId] ?? 0);
            $r = (int) ($returned[$productId] ?? 0);
            $good = $line?->returned_good_qty;
            $damaged = $line?->returned_damaged_qty;
            $expected = $loaded - $s + $r;
            $shortage = $unloaded ? $expected - (int) $good - (int) $damaged : null;

            return [
                'product_id' => (int) $productId,
                'sku' => $products[$productId]->sku ?? '',
                'name' => $products[$productId]->name ?? "#{$productId}",
                'loaded' => $loaded,
                'sold' => $s,
                'customer_returns' => $r,
                'expected_remaining' => $expected,
                'in_vehicle' => (int) ($inVehicle[$productId] ?? 0),
                'returned_good' => $good,
                'returned_damaged' => $damaged,
                'shortage' => $shortage,
                'unit_price' => $prices[$productId] ?? 0,
                'shortage_value' => $shortage ? max(0, $shortage) * ($prices[$productId] ?? 0) : 0,
            ];
        })->values();

        $containers = $trip->containerMovements()->get();
        $collected = (int) -$containers->where('type', ContainerMovementType::Collected)->sum('quantity');

        $settlement = $trip->settlement;
        $cashExpected = $settlement?->approved_at ? $settlement->cash_expected : $this->cashExpected($trip);
        $cashReceived = $settlement?->cash_received;

        return [
            'cash_expected' => $cashExpected,
            'transfer_total' => (int) $trip->sales()->where('status', SaleStatus::Completed->value)->where('payment_method', PaymentMethod::Transfer->value)->sum('total'),
            'cash_received' => $cashReceived,
            'cash_variance' => $cashReceived === null ? null : $cashReceived - $cashExpected,
            'lines' => $lines->all(),
            'stock_shortage_value' => (int) $lines->sum('shortage_value'),
            'containers_delivered' => (int) $containers->where('type', ContainerMovementType::Delivered)->sum('quantity'),
            'containers_collected' => $collected,
            'containers_received' => $trip->empty_containers_received,
            'container_shortage' => $trip->empty_containers_received === null ? null : $collected - $trip->empty_containers_received,
        ];
    }

    public function hasVariance(array $summary): bool
    {
        return ($summary['cash_variance'] ?? 0) !== 0
            || $summary['stock_shortage_value'] > 0
            || collect($summary['lines'])->contains(fn ($l) => ($l['shortage'] ?? 0) !== 0)
            || ($summary['container_shortage'] ?? 0) > 0;
    }

    public function receiveCash(Trip $trip, int $amount, ?string $note = null): Settlement
    {
        if (in_array($trip->status, [TripStatus::Loaded, TripStatus::Cancelled, TripStatus::Closed], true)) {
            throw new BusinessRuleException('Setoran hanya untuk muatan yang sudah berjalan dan belum ditutup.');
        }

        if ($amount < 0) {
            throw new BusinessRuleException('Jumlah setoran tidak boleh negatif.', field: 'cash_received');
        }

        $settlement = Settlement::updateOrCreate(['trip_id' => $trip->id], [
            'cash_expected' => $this->cashExpected($trip),
            'cash_received' => $amount,
            'note' => $note,
            'received_by' => auth()->id(),
            'received_at' => now(),
        ]);

        ActivityLog::record('settlement.receive', "Setoran {$trip->number}: Rp ".number_format($amount, 0, ',', '.'), $trip);

        return $settlement;
    }

    public function approve(Trip $trip, ?Resolution $resolution, ?string $note): Settlement
    {
        if ($trip->status !== TripStatus::Unloaded) {
            throw new BusinessRuleException('Rekonsiliasi hanya bisa disetujui setelah muatan dibongkar dan belum ditutup.');
        }

        $summary = $this->summary($trip);

        if ($summary['cash_received'] === null) {
            throw new BusinessRuleException('Uang setoran belum dicatat kasir.');
        }

        $variance = $this->hasVariance($summary);

        if ($variance && (! $resolution || $resolution === Resolution::None || blank($note))) {
            throw new BusinessRuleException('Ada selisih: pilih keputusan dan isi catatan.', field: 'resolution');
        }

        return DB::transaction(function () use ($trip, $summary, $variance, $resolution, $note) {
            // Barang yang tidak kembali dihapus dari stok kendaraan, supaya muatan berikutnya mulai dari nol.
            foreach ($this->inventory->balances($trip->vehicle_location_id) as $productId => $qty) {
                if ($qty > 0) {
                    $this->inventory->move($productId, $qty, $trip->vehicle, null, MovementType::WriteOff, $trip, 'Selisih rekonsiliasi '.$trip->number);
                }
            }

            $settlement = $trip->settlement()->firstOrFail();
            $settlement->update([
                'cash_expected' => $summary['cash_expected'],
                'cash_variance' => $summary['cash_variance'],
                'stock_shortage_value' => $summary['stock_shortage_value'],
                'container_shortage' => $summary['container_shortage'] ?? 0,
                'resolution' => $variance ? $resolution : Resolution::None,
                'note' => $note ?? $settlement->note,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $trip->update(['status' => TripStatus::Closed]);
            ActivityLog::record('settlement.approve', "Rekonsiliasi {$trip->number} disetujui", $trip, ['resolution' => $settlement->resolution?->value]);

            return $settlement;
        });
    }
}
