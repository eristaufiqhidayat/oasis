<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\Position;
use App\Enums\StockLocationType;
use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\Trip;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Siklus muatan harian: muat (gudang → kendaraan), berangkat & selesai (dari HP),
 * bongkar (kendaraan → gudang / barang rusak) lalu setoran.
 */
class TripService
{
    public function __construct(private Inventory $inventory, private SettlementService $settlements) {}

    /** @param array<int, int> $lines product_id => qty */
    public function load(Employee $salesman, CarbonInterface $date, array $lines, ?string $note = null): Trip
    {
        $lines = array_filter(array_map('intval', $lines), fn ($q) => $q > 0);

        if ($salesman->position !== Position::Salesman || ! $salesman->is_active || ! $salesman->depot_id) {
            throw new BusinessRuleException('Muatan hanya untuk sales motoris aktif yang punya depo.', field: 'employee_id');
        }

        if ($lines === []) {
            throw new BusinessRuleException('Isi minimal satu produk yang dimuat.', field: 'lines');
        }

        $open = Trip::where('employee_id', $salesman->id)
            ->whereNotIn('status', [TripStatus::Closed->value, TripStatus::Cancelled->value])
            ->first();

        // Kendaraan harus kosong: muatan sebelumnya wajib dibongkar dan direkonsiliasi dulu.
        if ($open) {
            throw new BusinessRuleException("{$salesman->name} masih punya muatan {$open->number} ({$open->status->label()}). Selesaikan bongkar dan rekonsiliasinya dulu.", field: 'employee_id');
        }

        if (Product::active()->whereIn('id', array_keys($lines))->count() !== count($lines)) {
            throw new BusinessRuleException('Ada produk yang tidak aktif atau tidak dikenal.', field: 'lines');
        }

        return DB::transaction(function () use ($salesman, $date, $lines, $note) {
            $vehicle = $salesman->vehicleLocation();
            $warehouse = $salesman->depot->location(StockLocationType::Warehouse);

            $trip = Trip::create([
                'number' => Numbering::next('trips', 'MUAT', $date),
                'depot_id' => $salesman->depot_id,
                'employee_id' => $salesman->id,
                'vehicle_location_id' => $vehicle->id,
                'date' => $date,
                'status' => TripStatus::Loaded,
                'loaded_by' => auth()->id(),
                'loaded_at' => now(),
                'note' => $note,
            ]);

            foreach ($lines as $productId => $qty) {
                $this->inventory->move($productId, $qty, $warehouse, $vehicle, MovementType::Load, $trip);
                $trip->lines()->create(['product_id' => $productId, 'loaded_qty' => $qty]);
            }

            ActivityLog::record('trip.load', "Muatan {$trip->number} untuk {$salesman->name}", $trip);

            return $trip;
        });
    }

    public function cancel(Trip $trip): void
    {
        if ($trip->status !== TripStatus::Loaded) {
            throw new BusinessRuleException('Muatan yang sudah berangkat tidak bisa dibatalkan; bongkar muatan sebagai gantinya.');
        }

        DB::transaction(function () use ($trip) {
            $warehouse = $trip->depot->location(StockLocationType::Warehouse);

            foreach ($this->inventory->balances($trip->vehicle_location_id) as $productId => $qty) {
                $this->inventory->move($productId, $qty, $trip->vehicle, $warehouse, MovementType::Unload, $trip, 'Batal muat');
            }

            $trip->update(['status' => TripStatus::Cancelled]);
            ActivityLog::record('trip.cancel', "Muatan {$trip->number} dibatalkan", $trip);
        });
    }

    /** HP: motoris mulai jalan. */
    public function depart(Trip $trip): Trip
    {
        if ($trip->status === TripStatus::Loaded) {
            $trip->update(['status' => TripStatus::OnRoute, 'departed_at' => now()]);
        } elseif ($trip->status !== TripStatus::OnRoute) {
            throw new BusinessRuleException('Muatan ini tidak bisa dipakai berjualan (status: '.$trip->status->label().').', 'trip_not_active');
        }

        return $trip;
    }

    /** HP: motoris selesai berjualan dan kembali ke depo. */
    public function finish(Trip $trip): Trip
    {
        if (in_array($trip->status, [TripStatus::Loaded, TripStatus::OnRoute], true)) {
            $trip->update(['status' => TripStatus::Returned, 'returned_at' => now(), 'departed_at' => $trip->departed_at ?? now()]);
        } elseif ($trip->status !== TripStatus::Returned) {
            throw new BusinessRuleException('Muatan ini sudah ditutup.', 'trip_not_active');
        }

        return $trip;
    }

    /**
     * Gudang menghitung sisa barang. Barang layak kembali ke gudang, rusak ke lokasi barang rusak.
     * Yang tidak terhitung tetap tercatat di kendaraan sebagai selisih sampai rekonsiliasi disetujui.
     *
     * @param  array<int, array{good?: int, damaged?: int}>  $counts
     */
    public function unload(Trip $trip, array $counts, int $emptyContainers, ?string $note = null): Trip
    {
        if (! in_array($trip->status, [TripStatus::OnRoute, TripStatus::Returned], true)) {
            throw new BusinessRuleException('Hanya muatan yang sudah berangkat dan belum dibongkar yang bisa dibongkar.');
        }

        $balances = $this->inventory->balances($trip->vehicle_location_id);

        foreach ($counts as $productId => $count) {
            $total = (int) ($count['good'] ?? 0) + (int) ($count['damaged'] ?? 0);

            if (($count['good'] ?? 0) < 0 || ($count['damaged'] ?? 0) < 0) {
                throw new BusinessRuleException('Jumlah hitung tidak boleh negatif.', field: "counts.{$productId}");
            }

            if ($total > ($balances[$productId] ?? 0)) {
                $name = Product::find($productId)?->name ?? "#{$productId}";

                throw new BusinessRuleException("Hitungan {$name} ({$total}) melebihi sisa menurut sistem (".($balances[$productId] ?? 0).'). Periksa penjualan yang belum terkirim dari HP.', field: "counts.{$productId}");
            }
        }

        return DB::transaction(function () use ($trip, $counts, $emptyContainers, $note, $balances) {
            $warehouse = $trip->depot->location(StockLocationType::Warehouse);
            $damaged = $trip->depot->location(StockLocationType::Damaged);

            foreach (array_keys($balances + $counts) as $productId) {
                $good = (int) ($counts[$productId]['good'] ?? 0);
                $bad = (int) ($counts[$productId]['damaged'] ?? 0);

                $this->inventory->move($productId, $good, $trip->vehicle, $warehouse, MovementType::Unload, $trip);
                $this->inventory->move($productId, $bad, $trip->vehicle, $damaged, MovementType::Unload, $trip, 'Barang rusak');

                $trip->lines()->updateOrCreate(['product_id' => $productId], [
                    'returned_good_qty' => $good,
                    'returned_damaged_qty' => $bad,
                ] + ($trip->lines()->where('product_id', $productId)->exists() ? [] : ['loaded_qty' => 0]));
            }

            $trip->update([
                'status' => TripStatus::Unloaded,
                'unloaded_by' => auth()->id(),
                'unloaded_at' => now(),
                'returned_at' => $trip->returned_at ?? now(),
                'empty_containers_received' => max(0, $emptyContainers),
                'note' => $note ?? $trip->note,
            ]);

            Settlement::firstOrCreate(['trip_id' => $trip->id], ['cash_expected' => $this->settlements->cashExpected($trip)]);
            ActivityLog::record('trip.unload', "Muatan {$trip->number} dibongkar", $trip);

            return $trip;
        });
    }
}
