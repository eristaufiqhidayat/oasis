<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReturnReason;
use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\SalesRoute;
use App\Services\Inventory;
use App\Services\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data awal hari kerja untuk HP: profil, muatan aktif & stok kendaraan, produk & harga depo,
 * rute hari ini, ringkasan penjualan, dan daftar pilihan (alasan tidak beli/retur).
 */
class BootstrapController extends ApiController
{
    public function __invoke(Request $request, Pricing $pricing, Inventory $inventory): JsonResponse
    {
        $employee = $this->employee($request)->load('depot', 'supervisor');
        $trip = $this->activeTrip($request, false);
        $today = Carbon::today();
        $prices = $pricing->priceList($employee->depot_id, $today);

        $routeOutlets = SalesRoute::with(['outlets' => fn ($q) => $q->whereIn('status', ['active', 'pending'])])
            ->where('employee_id', $employee->id)->where('is_active', true)
            ->where(fn ($q) => $q->where('weekday', $today->dayOfWeekIso)->orWhereNull('weekday'))
            ->get()->flatMap(fn ($r) => $r->outlets)->unique('id')->values();

        $containerBalances = DB::table('container_movements')->whereIn('outlet_id', $routeOutlets->pluck('id'))
            ->groupBy('outlet_id')->selectRaw('outlet_id, SUM(quantity) as balance')->pluck('balance', 'outlet_id');
        $visited = $trip ? $trip->visits()->pluck('outlet_id')->flip() : collect();

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();

        return $this->ok([
            'server_time' => now()->toIso8601String(),
            'employee' => [
                'id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name,
                'depot' => ['id' => $employee->depot->id, 'code' => $employee->depot->code, 'name' => $employee->depot->name,
                    'latitude' => $employee->depot->latitude, 'longitude' => $employee->depot->longitude],
                'supervisor' => $employee->supervisor?->name,
            ],
            'attendance' => [
                'checked_in_at' => $attendance?->check_in_at?->toIso8601String(),
                'checked_out_at' => $attendance?->check_out_at?->toIso8601String(),
            ],
            'trip' => $trip ? [
                'id' => $trip->id, 'number' => $trip->number, 'date' => $trip->date->toDateString(), 'status' => $trip->status->value,
                'stock' => collect($inventory->balances($trip->vehicle_location_id))->map(fn ($q, $pid) => ['product_id' => $pid, 'quantity' => $q])->values(),
                'sales_total' => (int) $trip->sales()->where('status', 'completed')->sum('total'),
                'cash_total' => (int) $trip->sales()->where('status', 'completed')->where('payment_method', 'cash')->sum('total'),
                'transactions' => $trip->sales()->where('status', 'completed')->count(),
            ] : null,
            'products' => Product::active()->orderBy('name')->get()->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'unit' => $p->unit, 'units_per_case' => $p->units_per_case,
                'tracks_container' => $p->tracks_container, 'price' => $prices[$p->id] ?? null,
            ]),
            'route' => $routeOutlets->map(fn (Outlet $o) => $this->outlet($o, (int) ($containerBalances[$o->id] ?? 0)) + [
                'sequence' => $o->pivot->sequence, 'visited' => $visited->has($o->id),
            ]),
            'options' => [
                'no_sale_reasons' => self::options(config('mobile.no_sale_reasons')),
                'return_reasons' => self::options(ReturnReason::options()),
                'payment_methods' => [['value' => 'cash', 'label' => 'Tunai'], ['value' => 'transfer', 'label' => 'Transfer']],
                'visit_radius_m' => (int) config('mobile.visit_radius_m'),
            ],
        ]);
    }

    public static function outletPayload(Outlet $o, int $containerBalance = 0): array
    {
        return [
            'id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'owner_name' => $o->owner_name, 'phone' => $o->phone,
            'address' => $o->address, 'latitude' => $o->latitude, 'longitude' => $o->longitude, 'status' => $o->status->value,
            'territory_id' => $o->territory_id, 'container_balance' => $containerBalance,
        ];
    }

    private function outlet(Outlet $o, int $balance): array
    {
        return self::outletPayload($o, $balance);
    }
}
