<?php

namespace App\Http\Controllers\Reports;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public const GROUPS = ['day' => 'Per hari', 'product' => 'Per produk', 'salesman' => 'Per motoris', 'outlet' => 'Per outlet', 'depot' => 'Per depo'];

    public function index(Request $request): Response
    {
        [$filters, $rows] = $this->build($request);

        return Inertia::render('Reports/Sales', [
            'filters' => $filters,
            'groups' => self::options(self::GROUPS),
            'depots' => $this->depotOptions($request),
            'rows' => $rows,
            'total' => ['quantity' => $rows->sum('quantity'), 'revenue' => $rows->sum('revenue'), 'transactions' => $rows->sum('transactions')],
            'canExport' => $request->user()->can('report.export'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('report.export'), 403);
        [$filters, $rows] = $this->build($request);

        return response()->streamDownload(function () use ($rows, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [self::GROUPS[$filters['grup']], 'Kode', 'Transaksi', 'Qty', 'Nilai (Rp)']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['label'], $r['code'], $r['transactions'], $r['quantity'], $r['revenue']]);
            }
            fclose($out);
        }, "penjualan-{$filters['grup']}-{$filters['dari']}-{$filters['sampai']}.csv", ['Content-Type' => 'text/csv']);
    }

    private function build(Request $request): array
    {
        $group = array_key_exists($request->query('grup'), self::GROUPS) ? $request->query('grup') : 'day';
        $from = $this->date($request, 'dari');
        $to = $request->query('sampai') ? $this->date($request, 'sampai') : $from->copy();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $depot = $this->selectedDepot($request);
        $ids = $this->depotIds($request);

        $query = DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($ids !== null, fn ($q) => $q->whereIn('sales.depot_id', $ids))
            ->when($depot, fn ($q) => $q->where('sales.depot_id', $depot));

        [$key, $label, $code] = match ($group) {
            'day' => [DB::raw('DATE(sales.sold_at)'), DB::raw('DATE(sales.sold_at)'), DB::raw("''")],
            'product' => ['products.id', 'products.name', 'products.sku'],
            'salesman' => ['employees.id', 'employees.name', 'employees.code'],
            'outlet' => ['outlets.id', 'outlets.name', 'outlets.code'],
            'depot' => ['depots.id', 'depots.name', 'depots.code'],
        };

        match ($group) {
            'product' => $query->join('products', 'products.id', '=', 'sale_lines.product_id'),
            'salesman' => $query->join('employees', 'employees.id', '=', 'sales.employee_id'),
            'outlet' => $query->join('outlets', 'outlets.id', '=', 'sales.outlet_id'),
            'depot' => $query->join('depots', 'depots.id', '=', 'sales.depot_id'),
            default => null,
        };

        $groupBy = $group === 'day' ? [DB::raw('DATE(sales.sold_at)')] : [$key, $label, $code];

        $rows = $query->groupBy(...$groupBy)
            ->orderBy($group === 'day' ? DB::raw('DATE(sales.sold_at)') : DB::raw('SUM(sale_lines.line_total)'), $group === 'day' ? 'asc' : 'desc')
            ->limit(1000)
            ->get([
                DB::raw(($group === 'day' ? 'DATE(sales.sold_at)' : $label).' as label'),
                DB::raw(($group === 'day' ? "''" : $code).' as code'),
                DB::raw('COUNT(DISTINCT sales.id) as transactions'),
                DB::raw('SUM(sale_lines.quantity) as quantity'),
                DB::raw('SUM(sale_lines.line_total) as revenue'),
            ])
            ->map(fn ($r) => ['label' => (string) $r->label, 'code' => (string) $r->code, 'transactions' => (int) $r->transactions, 'quantity' => (int) $r->quantity, 'revenue' => (int) $r->revenue]);

        return [['grup' => $group, 'dari' => $from->toDateString(), 'sampai' => $to->toDateString(), 'depot' => $depot], $rows];
    }
}
