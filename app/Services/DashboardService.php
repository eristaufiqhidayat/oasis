<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\VisitOutcome;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data grafik Beranda untuk satu periode: tren harian, per wilayah/area, komposisi produk,
 * hasil kunjungan, cara bayar, motoris teratas, dan jam transaksi. Semua angka ikut cakupan depo akun.
 */
class DashboardService
{
    /** Pilihan periode di filter Beranda. */
    public const PERIODS = ['7' => '7 hari terakhir', '14' => '14 hari terakhir', '30' => '30 hari terakhir', 'bulan' => 'Bulan ini'];

    public const DEFAULT_PERIOD = '14';

    /** Produk di luar 4 teratas digabung jadi "Lainnya" (donut maksimal 5 potong). */
    private const PRODUCT_SLICES = 4;

    /** @return array{0: Carbon, 1: Carbon} awal & akhir periode (inklusif) */
    public static function range(string $period, Carbon $today): array
    {
        return match ($period) {
            'bulan' => [$today->copy()->startOfMonth(), $today->copy()],
            default => [$today->copy()->subDays(max(1, (int) $period) - 1), $today->copy()],
        };
    }

    public function charts(User $user, ?int $depot, Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        $sales = fn (Carbon $a, Carbon $b) => $this->sales($user, $depot)->whereDate('sales.sold_at', '>=', $a)->whereDate('sales.sold_at', '<=', $b);
        $current = $sales($from, $to);
        $visits = $this->visits($user, $depot)->whereDate('visits.started_at', '>=', $from)->whereDate('visits.started_at', '<=', $to);

        $revenue = (int) (clone $current)->sum('sales.total');
        $transactions = (clone $current)->count();
        $outcomes = (clone $visits)->groupBy('visits.outcome')->selectRaw('visits.outcome, COUNT(*) as n')->pluck('n', 'outcome');
        $visitCount = (int) $outcomes->sum();

        return [
            'summary' => [
                'revenue' => $revenue,
                'previous_revenue' => (int) $sales($prevFrom, $prevTo)->sum('sales.total'),
                'transactions' => $transactions,
                'average_ticket' => $transactions ? intdiv($revenue, $transactions) : 0,
                'visits' => $visitCount,
                'effective_calls' => (int) ($outcomes[VisitOutcome::Sale->value] ?? 0),
                'active_salesmen' => (clone $current)->distinct()->count('sales.employee_id'),
                'days' => $days,
            ],
            'daily' => $this->daily($current, $from, $to),
            'byArea' => $this->byArea($current, $depot),
            'products' => $this->products($current),
            'outcomes' => collect(VisitOutcome::cases())->map(fn ($o) => ['key' => $o->value, 'label' => $o->label(), 'value' => (int) ($outcomes[$o->value] ?? 0)])->values(),
            'payments' => $this->payments($current),
            'topSalesmen' => $this->topSalesmen($current),
            'hours' => $this->hours($current),
        ];
    }

    /** Penjualan selesai yang boleh dilihat akun, opsional satu depo. */
    private function sales(User $user, ?int $depot): Builder
    {
        return DB::table('sales')->where('sales.status', SaleStatus::Completed->value)
            ->when($user->depotIds() !== null, fn ($q) => $q->whereIn('sales.depot_id', $user->depotIds()))
            ->when($depot, fn ($q) => $q->where('sales.depot_id', $depot));
    }

    private function visits(User $user, ?int $depot): Builder
    {
        $ids = $depot ? [$depot] : $user->depotIds();

        return DB::table('visits')->join('employees', 'employees.id', '=', 'visits.employee_id')
            ->when($ids !== null, fn ($q) => $q->whereIn('employees.depot_id', $ids));
    }

    /** Omzet & transaksi per hari, hari tanpa penjualan tetap ada (0). */
    private function daily(Builder $sales, Carbon $from, Carbon $to): Collection
    {
        $rows = (clone $sales)->groupBy(DB::raw('DATE(sales.sold_at)'))
            ->selectRaw('DATE(sales.sold_at) as day, SUM(sales.total) as total, COUNT(*) as n')->get()->keyBy('day');

        $out = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $row = $rows[$d->toDateString()] ?? null;
            $out->push(['date' => $d->toDateString(), 'total' => (int) ($row->total ?? 0), 'transactions' => (int) ($row->n ?? 0)]);
        }

        return $out;
    }

    /** Semua depo → per wilayah; satu depo → per area penjualan. */
    private function byArea(Builder $sales, ?int $depot): array
    {
        $query = (clone $sales)->join('outlets', 'outlets.id', '=', 'sales.outlet_id');

        $rows = $depot
            ? $query->leftJoin('territories', 'territories.id', '=', 'outlets.territory_id')
                ->groupBy('territories.id', 'territories.name')
                ->selectRaw("COALESCE(territories.name, 'Tanpa area') as name, SUM(sales.total) as total, COUNT(*) as n")
            : $query->join('depots', 'depots.id', '=', 'sales.depot_id')->leftJoin('regions', 'regions.id', '=', 'depots.region_id')
                ->groupBy('regions.id', 'regions.name')
                ->selectRaw("COALESCE(regions.name, 'Tanpa wilayah') as name, SUM(sales.total) as total, COUNT(*) as n, COUNT(DISTINCT sales.depot_id) as depots");

        return [
            'dimension' => $depot ? 'area' : 'wilayah',
            'items' => $rows->orderByDesc('total')->get()->map(fn ($r) => [
                'name' => preg_replace('/^Area /', '', $r->name), 'total' => (int) $r->total, 'transactions' => (int) $r->n,
                'depots' => isset($r->depots) ? (int) $r->depots : null,
            ])->values(),
        ];
    }

    private function products(Builder $sales): Collection
    {
        $rows = (clone $sales)->join('sale_lines', 'sale_lines.sale_id', '=', 'sales.id')->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->groupBy('products.id', 'products.name')->orderByDesc(DB::raw('SUM(sale_lines.line_total)'))
            ->get(['products.name', DB::raw('SUM(sale_lines.quantity) as qty'), DB::raw('SUM(sale_lines.line_total) as total')])
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'total' => (int) $r->total]);

        if ($rows->count() <= self::PRODUCT_SLICES + 1) {
            return $rows->values();
        }

        $rest = $rows->slice(self::PRODUCT_SLICES);

        return $rows->take(self::PRODUCT_SLICES)->push(['name' => 'Lainnya ('.$rest->count().' produk)', 'qty' => $rest->sum('qty'), 'total' => $rest->sum('total')])->values();
    }

    private function payments(Builder $sales): Collection
    {
        $rows = (clone $sales)->groupBy('sales.payment_method')->selectRaw('sales.payment_method as method, SUM(sales.total) as total, COUNT(*) as n')->get()->keyBy('method');

        return collect(PaymentMethod::cases())->map(fn ($m) => [
            'key' => $m->value, 'label' => $m->label(), 'value' => (int) ($rows[$m->value]->total ?? 0), 'transactions' => (int) ($rows[$m->value]->n ?? 0),
        ])->values();
    }

    private function topSalesmen(Builder $sales, int $limit = 10): Collection
    {
        return (clone $sales)->join('employees', 'employees.id', '=', 'sales.employee_id')->join('depots', 'depots.id', '=', 'sales.depot_id')
            ->groupBy('employees.id', 'employees.name', 'depots.name')->orderByDesc(DB::raw('SUM(sales.total)'))->limit($limit)
            ->get(['employees.name', 'depots.name as depot', DB::raw('SUM(sales.total) as total'), DB::raw('COUNT(*) as n')])
            ->map(fn ($r) => ['name' => $r->name, 'depot' => preg_replace('/^Depo /', '', $r->depot), 'total' => (int) $r->total, 'transactions' => (int) $r->n]);
    }

    /** Jumlah transaksi per jam (06–18); jam di luar rentang ikut ke ujung terdekat. */
    private function hours(Builder $sales): Collection
    {
        $hour = match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', sales.sold_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM sales.sold_at)',
            default => 'HOUR(sales.sold_at)',
        };

        $rows = (clone $sales)->groupBy(DB::raw($hour))->selectRaw("{$hour} as h, COUNT(*) as n, SUM(sales.total) as total")->get();
        $buckets = collect(range(6, 18))->mapWithKeys(fn ($h) => [$h => ['hour' => $h, 'transactions' => 0, 'total' => 0]])->all();

        foreach ($rows as $r) {
            $h = min(18, max(6, (int) $r->h));
            $buckets[$h]['transactions'] += (int) $r->n;
            $buckets[$h]['total'] += (int) $r->total;
        }

        return collect(array_values($buckets));
    }
}
