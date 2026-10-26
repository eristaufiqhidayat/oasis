<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\IncentiveScheme;
use App\Services\IncentiveService;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncentiveController extends Controller
{
    public function index(Request $request, IncentiveService $service): Response
    {
        $period = Period::parse($request->query('bulan'));
        $depot = $this->selectedDepot($request);
        $ids = $depot ? [$depot] : $this->depotIds($request);
        $rows = $service->rows($period, $ids)
            ->when($request->query('skema'), fn ($c, $s) => $c->where('scheme_id', (int) $s))
            ->values();

        return Inertia::render('Performance/Incentives', [
            'filters' => ['bulan' => $period->format('Y-m'), 'depot' => $depot, 'skema' => $request->integer('skema') ?: null],
            'depots' => $this->depotOptions($request),
            'schemes' => self::options(IncentiveScheme::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            'rows' => $rows,
            'totals' => [
                'estimate' => $rows->where('status', 'estimate')->sum('net'),
                'approved' => $rows->where('status', 'approved')->sum('net'),
                'paid' => $rows->where('status', 'paid')->sum('net'),
            ],
            'can' => ['approve' => $request->user()->can('incentive.approve'), 'pay' => $request->user()->can('incentive.pay')],
        ]);
    }

    public function approve(Request $request, IncentiveService $service): RedirectResponse
    {
        abort_unless($request->user()->can('incentive.approve'), 403);
        $data = $this->validated($request);
        $count = $service->approve(Period::parse($data['bulan']), $data['keys'], $this->depotIds($request));

        return back()->with('sukses', "{$count} insentif disetujui dan nilainya dibekukan.");
    }

    public function pay(Request $request, IncentiveService $service): RedirectResponse
    {
        abort_unless($request->user()->can('incentive.pay'), 403);
        $data = $this->validated($request);
        $count = $service->markPaid(Period::parse($data['bulan']), $data['keys'], $this->depotIds($request));

        return back()->with('sukses', "{$count} insentif ditandai dibayar.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'keys' => ['required', 'array', 'min:1'],
            'keys.*' => ['string', 'regex:/^\d+:\d+$/'],
        ], ['keys.required' => 'Pilih minimal satu baris.']);
    }
}
