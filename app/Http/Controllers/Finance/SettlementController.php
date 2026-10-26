<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Resolution;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Field\TripController;
use App\Models\Trip;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettlementController extends Controller
{
    public function index(Request $request, SettlementService $service): Response
    {
        $date = $this->date($request);
        $depot = $this->selectedDepot($request);

        $trips = Trip::visibleTo($request->user())->with(['employee', 'settlement'])
            ->whereDate('date', $date)->where('status', '!=', TripStatus::Cancelled->value)
            ->when($depot, fn ($q) => $q->where('depot_id', $depot))
            ->orderBy('number')->get();

        $rows = $trips->map(function (Trip $t) use ($service) {
            $s = $service->summary($t);

            return [
                'id' => $t->id, 'number' => $t->number, 'employee' => $t->employee->name,
                'status' => ['label' => $t->status->label(), 'variant' => TripController::STATUS_VARIANT[$t->status->value]],
                'cash_expected' => $s['cash_expected'], 'cash_received' => $s['cash_received'], 'cash_variance' => $s['cash_variance'],
                'stock_shortage_value' => $t->unloaded_at ? $s['stock_shortage_value'] : null,
                'container_shortage' => $s['container_shortage'],
                'approved' => $t->settlement?->approved_at !== null,
                'resolution' => $t->settlement?->resolution?->label(),
            ];
        });

        return Inertia::render('Finance/Settlements', [
            'filters' => ['tanggal' => $date->toDateString(), 'depot' => $depot],
            'depots' => $this->depotOptions($request),
            'rows' => $rows->values(),
            'totals' => [
                'expected' => $rows->sum('cash_expected'),
                'received' => $rows->sum(fn ($r) => (int) $r['cash_received']),
                'pending' => $rows->where('approved', false)->count(),
            ],
        ]);
    }

    public function show(Request $request, int $id, SettlementService $service): Response
    {
        $trip = Trip::visibleTo($request->user())->with(['employee', 'depot', 'settlement.receiver', 'settlement.approver'])->findOrFail($id);
        $summary = $service->summary($trip);
        $settlement = $trip->settlement;

        return Inertia::render('Finance/SettlementShow', [
            'trip' => [
                'id' => $trip->id, 'number' => $trip->number, 'date' => $trip->date->format('d/m/Y'), 'employee' => $trip->employee->name,
                'depot' => $trip->depot->name, 'status' => ['label' => $trip->status->label(), 'variant' => TripController::STATUS_VARIANT[$trip->status->value]],
                'unloaded' => $trip->unloaded_at !== null,
            ],
            'summary' => $summary,
            'hasVariance' => $service->hasVariance($summary),
            'settlement' => $settlement ? [
                'received_by' => $settlement->receiver?->name, 'received_at' => $settlement->received_at?->format('d/m/Y H:i'),
                'approved_by' => $settlement->approver?->name, 'approved_at' => $settlement->approved_at?->format('d/m/Y H:i'),
                'resolution' => $settlement->resolution?->label(), 'note' => $settlement->note,
            ] : null,
            'resolutions' => self::options(collect(Resolution::options())->except(Resolution::None->value)),
            'can' => [
                'receive' => $request->user()->can('settlement.receive') && in_array($trip->status, [TripStatus::OnRoute, TripStatus::Returned, TripStatus::Unloaded], true),
                'approve' => $request->user()->can('settlement.approve') && $trip->status === TripStatus::Unloaded && $summary['cash_received'] !== null,
            ],
        ]);
    }

    public function receive(Request $request, int $id, SettlementService $service): RedirectResponse
    {
        abort_unless($request->user()->can('settlement.receive'), 403);
        $trip = Trip::visibleTo($request->user())->findOrFail($id);
        $data = $request->validate(['cash_received' => ['required', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:255']], [], ['cash_received' => 'uang diterima']);
        $service->receiveCash($trip, (int) $data['cash_received'], $data['note'] ?? null);

        return back()->with('sukses', 'Uang setoran dicatat.');
    }

    public function approve(Request $request, int $id, SettlementService $service): RedirectResponse
    {
        abort_unless($request->user()->can('settlement.approve'), 403);
        $trip = Trip::visibleTo($request->user())->findOrFail($id);
        $data = $request->validate([
            'resolution' => ['nullable', Rule::enum(Resolution::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $service->approve($trip, isset($data['resolution']) ? Resolution::from($data['resolution']) : null, $data['note'] ?? null);

        return back()->with('sukses', "Rekonsiliasi {$trip->number} disetujui; muatan selesai.");
    }
}
