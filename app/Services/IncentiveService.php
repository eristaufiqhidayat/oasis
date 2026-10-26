<?php

namespace App\Services;

use App\Enums\IncentiveTarget;
use App\Enums\PayoutStatus;
use App\Enums\Position;
use App\Enums\Resolution;
use App\Enums\TargetMetric;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\IncentivePayout;
use App\Models\IncentiveScheme;
use App\Models\Settlement;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Insentif bulanan. Perkiraan dihitung langsung dari realisasi; saat disetujui nilainya dibekukan
 * di incentive_payouts (approved → paid).
 *
 * Aturan skema (rules): per_effective_call (Rp per EC), revenue_percent (% komisi penjualan),
 * tiers [{min_achievement, amount}] (bonus tingkat tertinggi yang dicapai terhadap target penjualan).
 */
class IncentiveService
{
    public function __construct(private TargetService $targets) {}

    /** @return Collection<int, array> */
    public function rows(CarbonImmutable $period, ?array $depotIds = null): Collection
    {
        $actuals = $this->targets->actuals($period, null)['employee'];
        $revenueTargets = $this->targets->values($period, TargetMetric::Revenue, 'employee');
        $deductions = $this->deductions($period);
        $payouts = IncentivePayout::whereDate('period', $period->toDateString())->get()->keyBy(fn ($p) => "{$p->incentive_scheme_id}:{$p->employee_id}");

        $employees = Employee::with('depot')->where('is_active', true)
            ->whereIn('position', [Position::Salesman->value, Position::Coordinator->value])
            ->when($depotIds !== null, fn ($q) => $q->whereIn('depot_id', $depotIds))
            ->orderBy('name')->get();

        $team = Employee::salesmen()->whereNotNull('supervisor_id')->get(['id', 'supervisor_id'])->groupBy('supervisor_id');

        $rows = collect();
        $deducted = [];

        foreach (IncentiveScheme::where('is_active', true)->orderBy('id')->get() as $scheme) {
            $position = $scheme->applies_to === IncentiveTarget::Salesman ? Position::Salesman : Position::Coordinator;

            foreach ($employees->where('position', $position) as $employee) {
                $key = "{$scheme->id}:{$employee->id}";

                if ($payout = $payouts[$key] ?? null) {
                    $rows->push(['status' => $payout->status->value, 'payout_id' => $payout->id] + $payout->breakdown);

                    continue;
                }

                if ($position === Position::Salesman) {
                    $revenue = $actuals[$employee->id]['revenue'] ?? 0;
                    $ec = $actuals[$employee->id]['ec'] ?? 0;
                    $target = $revenueTargets[$employee->id] ?? null;
                    $members = 1;
                } else {
                    $ids = ($team[$employee->id] ?? collect())->pluck('id');
                    $revenue = $ids->sum(fn ($id) => $actuals[$id]['revenue'] ?? 0);
                    $ec = $ids->sum(fn ($id) => $actuals[$id]['ec'] ?? 0);
                    $target = $ids->sum(fn ($id) => $revenueTargets[$id] ?? 0) ?: null;
                    $members = $ids->count();
                }

                $calc = $this->apply($scheme->rules ?? [], $revenue, $ec, $target);
                // Potongan selisih setoran hanya dikenakan sekali per motoris (skema pertama).
                $deduction = 0;
                if ($position === Position::Salesman && ! isset($deducted[$employee->id])) {
                    $deduction = min($calc['gross'], $deductions[$employee->id] ?? 0);
                    $deducted[$employee->id] = true;
                }

                $rows->push([
                    'status' => 'estimate',
                    'payout_id' => null,
                    'key' => $key,
                    'scheme_id' => $scheme->id,
                    'scheme' => $scheme->name,
                    'applies_to' => $scheme->applies_to->value,
                    'employee_id' => $employee->id,
                    'employee' => $employee->name,
                    'depot' => $employee->depot?->name,
                    'team_size' => $members,
                    'revenue' => $revenue,
                    'ec' => $ec,
                    'target' => $target,
                    'achievement' => TargetService::percent($revenue, $target),
                ] + $calc + ['deduction' => $deduction, 'net' => $calc['gross'] - $deduction]);
            }
        }

        return $rows->values();
    }

    /** @return array{ec_amount: int, commission: int, tier_bonus: int, gross: int} */
    public function apply(array $rules, int $revenue, int $ec, ?int $target): array
    {
        $ecAmount = (int) ($rules['per_effective_call'] ?? 0) * $ec;
        $commission = (int) round($revenue * (float) ($rules['revenue_percent'] ?? 0) / 100);
        $achievement = TargetService::percent($revenue, $target);
        $tier = 0;

        if ($achievement !== null) {
            foreach ($rules['tiers'] ?? [] as $t) {
                if ($achievement >= (float) $t['min_achievement']) {
                    $tier = max($tier, (int) $t['amount']);
                }
            }
        }

        return ['ec_amount' => $ecAmount, 'commission' => $commission, 'tier_bonus' => $tier, 'gross' => $ecAmount + $commission + $tier];
    }

    /** @return array<int, int> employee_id => total potongan dari rekonsiliasi "potong insentif" di bulan itu */
    public function deductions(CarbonImmutable $period): array
    {
        [$from, $to] = Period::range($period);

        return Settlement::query()->join('trips', 'trips.id', '=', 'settlements.trip_id')
            ->where('settlements.resolution', Resolution::DeductIncentive->value)
            ->whereBetween('trips.date', [$from->toDateString(), $to->toDateString()])
            ->get(['trips.employee_id', 'settlements.cash_variance', 'settlements.stock_shortage_value'])
            ->groupBy('employee_id')
            ->map(fn ($rows) => (int) $rows->sum(fn ($r) => max(0, -(int) $r->cash_variance) + (int) $r->stock_shortage_value))
            ->all();
    }

    /** @param array<string> $keys "scheme_id:employee_id" */
    public function approve(CarbonImmutable $period, array $keys, ?array $depotIds = null): int
    {
        $rows = $this->rows($period, $depotIds)->where('status', 'estimate')->whereIn('key', $keys);

        if ($rows->isEmpty()) {
            throw new BusinessRuleException('Pilih minimal satu insentif berstatus perkiraan.');
        }

        DB::transaction(function () use ($rows, $period) {
            foreach ($rows as $row) {
                IncentivePayout::create([
                    'period' => $period->toDateString(),
                    'employee_id' => $row['employee_id'],
                    'incentive_scheme_id' => $row['scheme_id'],
                    'amount' => $row['gross'],
                    'deduction' => $row['deduction'],
                    'breakdown' => collect($row)->except(['status', 'payout_id'])->all(),
                    'status' => PayoutStatus::Approved,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }

            ActivityLog::record('incentive.approve', "Insentif {$period->format('Y-m')} disetujui: {$rows->count()} orang");
        });

        return $rows->count();
    }

    /** @param array<string> $keys */
    public function markPaid(CarbonImmutable $period, array $keys, ?array $depotIds = null): int
    {
        $ids = $this->rows($period, $depotIds)->where('status', PayoutStatus::Approved->value)->whereIn('key', $keys)->pluck('payout_id');

        if ($ids->isEmpty()) {
            throw new BusinessRuleException('Pilih minimal satu insentif yang sudah disetujui.');
        }

        IncentivePayout::whereIn('id', $ids)->update(['status' => PayoutStatus::Paid->value, 'paid_by' => auth()->id(), 'paid_at' => now()]);
        ActivityLog::record('incentive.pay', "Insentif {$period->format('Y-m')} dibayar: {$ids->count()} orang");

        return $ids->count();
    }
}
