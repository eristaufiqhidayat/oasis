<?php

namespace App\Services;

use App\Enums\ContainerMovementType;
use App\Enums\MovementType;
use App\Enums\OutletStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\TripStatus;
use App\Enums\VisitOutcome;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\ContainerMovement;
use App\Models\CustomerReturn;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi motoris di outlet: penjualan, retur, dan penarikan galon kosong.
 * Semua idempoten lewat client_uuid (HP boleh mengirim ulang data yang sama).
 */
class SalesService
{
    public function __construct(private Inventory $inventory, private Pricing $pricing, private TripService $trips) {}

    /**
     * @param  array{client_uuid: string, outlet_id: int, visit_uuid?: ?string, sold_at?: ?string, payment_method: string, lines: array<int, array{product_id: int, quantity: int}>}  $data
     */
    public function recordSale(Employee $salesman, Trip $trip, array $data): Sale
    {
        if ($existing = Sale::where('client_uuid', $data['client_uuid'])->first()) {
            return $existing;
        }

        $outlet = $this->outletFor($salesman, (int) $data['outlet_id']);
        $this->trips->depart($trip);

        $lines = collect($data['lines'])->groupBy('product_id')->map(fn ($rows) => (int) $rows->sum('quantity'))->filter(fn ($q) => $q > 0);

        if ($lines->isEmpty()) {
            throw new BusinessRuleException('Penjualan minimal berisi satu produk.', 'validation_failed', 'lines');
        }

        $products = Product::active()->whereIn('id', $lines->keys())->get()->keyBy('id');

        if ($products->count() !== $lines->count()) {
            throw new BusinessRuleException('Ada produk yang tidak aktif atau tidak dikenal.', 'product_inactive', 'lines');
        }

        $soldAt = $this->time($data['sold_at'] ?? null);
        $prices = $this->pricing->priceList($trip->depot_id, $soldAt, $lines->keys()->all());

        foreach ($lines as $productId => $qty) {
            if (! isset($prices[$productId])) {
                throw new BusinessRuleException("Harga {$products[$productId]->name} belum diatur untuk depo ini.", 'price_missing', 'lines');
            }
        }

        return DB::transaction(function () use ($salesman, $trip, $outlet, $data, $lines, $products, $prices, $soldAt) {
            $subtotal = $lines->map(fn ($qty, $productId) => $qty * $prices[$productId])->sum();
            $visit = $this->visit($data['visit_uuid'] ?? null, $salesman);

            $sale = Sale::create([
                'client_uuid' => $data['client_uuid'],
                'number' => Numbering::next('sales', 'JUAL', $soldAt),
                'depot_id' => $trip->depot_id,
                'trip_id' => $trip->id,
                'visit_id' => $visit?->id,
                'outlet_id' => $outlet->id,
                'employee_id' => $salesman->id,
                'sold_at' => $soldAt,
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'payment_method' => PaymentMethod::from($data['payment_method'] ?? 'cash'),
                'status' => SaleStatus::Completed,
            ]);

            $containers = 0;

            foreach ($lines as $productId => $qty) {
                $sale->lines()->create([
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price' => $prices[$productId],
                    'discount' => 0,
                    'line_total' => $qty * $prices[$productId],
                ]);

                $this->inventory->move($productId, $qty, $trip->vehicle, null, MovementType::Sale, $sale, at: $soldAt);

                if ($products[$productId]->tracks_container) {
                    $containers += $qty;
                }
            }

            if ($containers > 0) {
                ContainerMovement::create([
                    'outlet_id' => $outlet->id, 'trip_id' => $trip->id, 'visit_id' => $visit?->id, 'sale_id' => $sale->id,
                    'type' => ContainerMovementType::Delivered, 'quantity' => $containers, 'occurred_at' => $soldAt,
                ]);
            }

            if ($visit && $visit->outcome === null) {
                $visit->update(['outcome' => VisitOutcome::Sale]);
            }

            return $sale->load('lines');
        });
    }

    public function void(Sale $sale, string $reason): Sale
    {
        if ($sale->status === SaleStatus::Void) {
            throw new BusinessRuleException('Penjualan ini sudah dibatalkan.');
        }

        if (! in_array($sale->trip->status, [TripStatus::Loaded, TripStatus::OnRoute, TripStatus::Returned], true)) {
            throw new BusinessRuleException('Penjualan hanya bisa dibatalkan sebelum muatannya dibongkar gudang.');
        }

        return DB::transaction(function () use ($sale, $reason) {
            foreach ($sale->lines as $line) {
                $this->inventory->move($line->product_id, $line->quantity, null, $sale->trip->vehicle, MovementType::SaleVoid, $sale, $reason);
            }

            ContainerMovement::where('sale_id', $sale->id)->delete();

            $sale->update(['status' => SaleStatus::Void, 'voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => $reason]);
            ActivityLog::record('sale.void', "Penjualan {$sale->number} dibatalkan: {$reason}", $sale);

            return $sale;
        });
    }

    /** @param array{client_uuid: string, outlet_id: int, visit_uuid?: ?string, returned_at?: ?string, note?: ?string, lines: array<int, array{product_id: int, quantity: int, reason: string}>} $data */
    public function recordReturn(Employee $salesman, Trip $trip, array $data): CustomerReturn
    {
        if ($existing = CustomerReturn::where('client_uuid', $data['client_uuid'])->first()) {
            return $existing;
        }

        $outlet = $this->outletFor($salesman, (int) $data['outlet_id']);
        $this->trips->depart($trip);
        $at = $this->time($data['returned_at'] ?? null);

        return DB::transaction(function () use ($salesman, $trip, $outlet, $data, $at) {
            $return = CustomerReturn::create([
                'client_uuid' => $data['client_uuid'],
                'number' => Numbering::next('customer_returns', 'RTR', $at),
                'depot_id' => $trip->depot_id,
                'trip_id' => $trip->id,
                'visit_id' => $this->visit($data['visit_uuid'] ?? null, $salesman)?->id,
                'outlet_id' => $outlet->id,
                'employee_id' => $salesman->id,
                'returned_at' => $at,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $return->lines()->create(['product_id' => $line['product_id'], 'quantity' => $line['quantity'], 'reason' => $line['reason']]);
                // Barang retur ikut di kendaraan sampai dibongkar gudang.
                $this->inventory->move((int) $line['product_id'], (int) $line['quantity'], null, $trip->vehicle, MovementType::CustomerReturn, $return, at: $at);
            }

            return $return->load('lines');
        });
    }

    /** @param array{client_uuid: string, outlet_id: int, visit_uuid?: ?string, quantity: int, collected_at?: ?string} $data */
    public function collectContainers(Employee $salesman, Trip $trip, array $data): ContainerMovement
    {
        if ($existing = ContainerMovement::where('client_uuid', $data['client_uuid'])->first()) {
            return $existing;
        }

        $outlet = $this->outletFor($salesman, (int) $data['outlet_id']);
        $this->trips->depart($trip);

        return ContainerMovement::create([
            'client_uuid' => $data['client_uuid'],
            'outlet_id' => $outlet->id,
            'trip_id' => $trip->id,
            'visit_id' => $this->visit($data['visit_uuid'] ?? null, $salesman)?->id,
            'type' => ContainerMovementType::Collected,
            'quantity' => -abs((int) $data['quantity']),
            'occurred_at' => $this->time($data['collected_at'] ?? null),
        ]);
    }

    private function outletFor(Employee $salesman, int $outletId): Outlet
    {
        $outlet = Outlet::find($outletId);

        if (! $outlet || $outlet->depot_id !== $salesman->depot_id) {
            throw new BusinessRuleException('Outlet tidak ditemukan di depo Anda.', 'outlet_not_found', 'outlet_id');
        }

        if (! in_array($outlet->status, [OutletStatus::Active, OutletStatus::Pending], true)) {
            throw new BusinessRuleException('Outlet ini nonaktif atau ditolak.', 'outlet_inactive', 'outlet_id');
        }

        return $outlet;
    }

    private function visit(?string $uuid, Employee $salesman): ?Visit
    {
        return $uuid ? Visit::where('client_uuid', $uuid)->where('employee_id', $salesman->id)->first() : null;
    }

    /** Waktu dari HP (offline) dipakai bila wajar: tidak di masa depan dan tidak lebih dari 3 hari lalu. */
    private function time(?string $value): CarbonInterface
    {
        $now = Carbon::now();

        if (! $value) {
            return $now;
        }

        $at = Carbon::parse($value);

        return $at->greaterThan($now->copy()->addMinutes(5)) || $at->lessThan($now->copy()->subDays(3)) ? $now : $at;
    }
}
