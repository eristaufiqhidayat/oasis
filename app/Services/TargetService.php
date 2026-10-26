<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Enums\TargetMetric;
use App\Exceptions\BusinessRuleException;
use App\Models\ActivityLog;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Target;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Target bulanan dan realisasinya. Effective call (EC) = jumlah pasangan (outlet, tanggal)
 * dengan minimal satu penjualan selesai.
 */
class TargetService
{
    /**
     * Simpan satu nilai target. value null/0 = hapus target.
     *
     * @param  array{depot_id?: ?int, employee_id?: ?int, product_id?: ?int}  $scope
     */
    public function set(CarbonImmutable $period, TargetMetric $metric, array $scope, ?int $value): void
    {
        $keys = [
            'period' => $period->toDateString(),
            'metric' => $metric->value,
            'depot_id' => $scope['depot_id'] ?? null,
            'employee_id' => $scope['employee_id'] ?? null,
            'product_id' => $scope['product_id'] ?? null,
        ];

        $query = Target::query()->whereDate('period', $keys['period']);
        foreach (array_slice($keys, 1, null, true) as $column => $v) {
            $v === null ? $query->whereNull($column) : $query->where($column, $v);
        }

        if (! $value) {
            $query->delete();

            return;
        }

        $existing = $query->first();
        $existing
            ? $existing->update(['value' => $value, 'updated_by' => auth()->id()])
            : Target::create($keys + ['value' => $value, 'updated_by' => auth()->id()]);
    }

    /** @return array<int, int> id (depot/karyawan) => nilai */
    public function values(CarbonImmutable $period, TargetMetric $metric, string $scope): array
    {
        $column = $scope === 'depot' ? 'depot_id' : 'employee_id';

        return Target::whereDate('period', $period->toDateString())->where('metric', $metric->value)
            ->whereNotNull($column)->whereNull('product_id')
            ->when($scope === 'depot', fn ($q) => $q->whereNull('employee_id'))
            ->pluck('value', $column)->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, int> "depot_id:product_id" => qty target */
    public function productTargets(CarbonImmutable $period): array
    {
        return Target::whereDate('period', $period->toDateString())->where('metric', TargetMetric::Quantity->value)
            ->get()->mapWithKeys(fn ($t) => ["{$t->depot_id}:{$t->product_id}" => (int) $t->value])->all();
    }

    /**
     * Realisasi per karyawan dan per depo.
     *
     * @return array{employee: array<int, array{revenue: int, ec: int}>, depot: array<int, array{revenue: int, ec: int}>}
     */
    public function actuals(CarbonImmutable $period, ?array $depotIds = null): array
    {
        [$from, $to] = Period::range($period);

        $base = DB::table('sales')->where('status', SaleStatus::Completed->value)
            ->whereBetween('sold_at', [$from, $to])
            ->when($depotIds !== null, fn ($q) => $q->whereIn('depot_id', $depotIds));

        $employee = [];
        $depot = [];

        foreach ((clone $base)->groupBy('employee_id', 'depot_id')->get(['employee_id', 'depot_id', DB::raw('SUM(total) as revenue')]) as $row) {
            $employee[$row->employee_id]['revenue'] = ($employee[$row->employee_id]['revenue'] ?? 0) + (int) $row->revenue;
            $depot[$row->depot_id]['revenue'] = ($depot[$row->depot_id]['revenue'] ?? 0) + (int) $row->revenue;
        }

        $calls = (clone $base)->select('employee_id', 'depot_id', 'outlet_id', DB::raw('DATE(sold_at) as day'))
            ->groupBy('employee_id', 'depot_id', 'outlet_id', DB::raw('DATE(sold_at)'))->get();

        foreach ($calls as $row) {
            $employee[$row->employee_id]['ec'] = ($employee[$row->employee_id]['ec'] ?? 0) + 1;
            $depot[$row->depot_id]['ec'] = ($depot[$row->depot_id]['ec'] ?? 0) + 1;
        }

        $fill = fn (array $rows) => array_map(fn ($r) => ['revenue' => $r['revenue'] ?? 0, 'ec' => $r['ec'] ?? 0], $rows);

        return ['employee' => $fill($employee), 'depot' => $fill($depot)];
    }

    /** @return array<string, int> "depot_id:product_id" => qty terjual */
    public function productActuals(CarbonImmutable $period, ?array $depotIds = null): array
    {
        [$from, $to] = Period::range($period);

        return DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)->whereBetween('sales.sold_at', [$from, $to])
            ->when($depotIds !== null, fn ($q) => $q->whereIn('sales.depot_id', $depotIds))
            ->groupBy('sales.depot_id', 'sale_lines.product_id')
            ->get(['sales.depot_id', 'sale_lines.product_id', DB::raw('SUM(sale_lines.quantity) as qty')])
            ->mapWithKeys(fn ($r) => ["{$r->depot_id}:{$r->product_id}" => (int) $r->qty])->all();
    }

    public static function percent(int|float $actual, int|float|null $target): ?float
    {
        return $target ? round($actual / $target * 100, 1) : null;
    }

    /** Bagi target revenue & EC depo rata ke motoris aktif depo itu; sisa pembagian ke motoris pertama. */
    public function distributeEvenly(CarbonImmutable $period, Depot $depot): int
    {
        $salesmen = Employee::salesmen()->active()->where('depot_id', $depot->id)->orderBy('name')->get();

        if ($salesmen->isEmpty()) {
            throw new BusinessRuleException('Depo ini belum punya sales motoris aktif.');
        }

        foreach ([TargetMetric::Revenue, TargetMetric::EffectiveCalls] as $metric) {
            $total = $this->values($period, $metric, 'depot')[$depot->id] ?? 0;

            if (! $total) {
                if ($metric === TargetMetric::Revenue) {
                    throw new BusinessRuleException('Target penjualan depo bulan ini belum diisi.');
                }

                continue;
            }

            $share = intdiv($total, $salesmen->count());
            $rest = $total - $share * $salesmen->count();

            foreach ($salesmen as $i => $salesman) {
                $this->set($period, $metric, ['employee_id' => $salesman->id], $share + ($i === 0 ? $rest : 0));
            }
        }

        ActivityLog::record('target.distribute', "Target {$period->format('Y-m')} depo {$depot->name} dibagi ke {$salesmen->count()} motoris", $depot);

        return $salesmen->count();
    }
}
