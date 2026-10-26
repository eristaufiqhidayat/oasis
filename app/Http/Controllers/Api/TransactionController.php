<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\ReturnReason;
use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Penjualan, retur, dan galon kosong dari HP. Semua idempoten lewat client_uuid. */
class TransactionController extends ApiController
{
    public function __construct(private SalesService $sales) {}

    public function storeSale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'outlet_id' => ['required', 'integer'],
            'visit_uuid' => ['nullable', 'uuid'],
            'sold_at' => ['nullable', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        if ($existing = Sale::where('client_uuid', $data['client_uuid'])->first()) {
            return $this->ok($this->sale($existing->load('lines')));
        }

        $sale = $this->sales->recordSale($this->employee($request), $this->activeTrip($request), $data);

        return $this->created($sale, $this->sale($sale));
    }

    public function sales(Request $request): JsonResponse
    {
        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();

        $sales = Sale::with(['lines', 'outlet'])->where('employee_id', $this->employee($request)->id)
            ->whereDate('sold_at', $date)->orderBy('sold_at')->get();

        return $this->ok($sales->map(fn ($s) => $this->sale($s) + ['outlet_name' => $s->outlet->name]));
    }

    public function storeReturn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'outlet_id' => ['required', 'integer'],
            'visit_uuid' => ['nullable', 'uuid'],
            'returned_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.reason' => ['required', Rule::enum(ReturnReason::class)],
        ]);

        $return = $this->sales->recordReturn($this->employee($request), $this->activeTrip($request), $data);

        return $this->created($return, ['id' => $return->id, 'number' => $return->number, 'client_uuid' => $return->client_uuid]);
    }

    public function storeContainers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'outlet_id' => ['required', 'integer'],
            'visit_uuid' => ['nullable', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'collected_at' => ['nullable', 'date'],
        ]);

        $movement = $this->sales->collectContainers($this->employee($request), $this->activeTrip($request), $data);

        return $this->created($movement, [
            'id' => $movement->id, 'client_uuid' => $movement->client_uuid, 'quantity' => abs($movement->quantity),
            'outlet_balance' => $movement->outlet->containerBalance(),
        ]);
    }

    private function sale(Sale $s): array
    {
        return [
            'id' => $s->id, 'client_uuid' => $s->client_uuid, 'number' => $s->number, 'outlet_id' => $s->outlet_id,
            'sold_at' => $s->sold_at->toIso8601String(), 'payment_method' => $s->payment_method->value, 'status' => $s->status->value,
            'total' => $s->total,
            'lines' => $s->lines->map(fn ($l) => ['product_id' => $l->product_id, 'quantity' => $l->quantity, 'unit_price' => $l->unit_price, 'line_total' => $l->line_total]),
        ];
    }
}
