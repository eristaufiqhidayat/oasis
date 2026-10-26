<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Depot;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\StockDocumentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Signature('oasis:hapus-produk-non-oasis
    {--jalankan : Benar-benar menghapus. Tanpa opsi ini hanya menampilkan daftar (aman)}
    {--kecuali=* : SKU yang tetap disimpan walau namanya tidak memuat "Oasis"}')]
#[Description('Hapus produk/material yang bukan produk Oasis (nama tidak memuat "Oasis") dari master dan stok gudang')]
class CleanNonOasisProducts extends Command
{
    /** Tabel transaksi yang menyimpan riwayat produk (FK restrict). */
    private const HISTORY = [
        'stock_movements' => 'mutasi stok',
        'stock_document_lines' => 'dokumen gudang',
        'trip_lines' => 'muatan',
        'sale_lines' => 'penjualan',
        'customer_return_lines' => 'retur',
    ];

    public function handle(): int
    {
        $keep = collect($this->option('kecuali'))->map(fn ($s) => mb_strtolower(trim($s)))->filter();
        $products = Product::orderBy('name')->get()
            ->reject(fn (Product $p) => self::isOasis($p->name) || $keep->contains(mb_strtolower($p->sku)));

        // Yang sudah dinonaktifkan dan stoknya nol sudah beres pada run sebelumnya.
        $rows = $products->map(fn (Product $p) => $this->inspect($p))
            ->reject(fn ($r) => ! $r['deletable'] && ! $r['product']->is_active && $r['stock'] === 0)->values();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada produk non-Oasis yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $this->table(
            ['SKU', 'Nama', 'Stok', 'Riwayat transaksi', 'Tindakan'],
            $rows->map(fn ($r) => [$r['product']->sku, $r['product']->name, $r['stock'], $r['history'] ?: '-', $r['action']]),
        );

        $delete = $rows->where('deletable', true)->count();
        $deactivate = $rows->count() - $delete;
        $this->line("Dihapus permanen: {$delete} · Dinonaktifkan (ada riwayat transaksi, disimpan untuk laporan): {$deactivate}");

        if (! $this->option('jalankan')) {
            $this->warn('Mode lihat saja, belum ada yang diubah. Jalankan ulang dengan --jalankan untuk menghapus.');
            $this->line('Produk yang ingin tetap disimpan: --kecuali=SKU (boleh diulang).');

            return self::SUCCESS;
        }

        $admin = User::where('username', 'admin')->first();
        if ($admin) {
            Auth::login($admin);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                $this->clean($r);
            }
        });

        $this->info("Selesai: {$delete} produk dihapus, {$deactivate} produk dinonaktifkan dan stoknya dinolkan.");

        return self::SUCCESS;
    }

    public static function isOasis(string $name): bool
    {
        return str_contains(mb_strtolower($name), 'oasis');
    }

    /** @return array{product: Product, stock: int, history: string, deletable: bool, action: string} */
    private function inspect(Product $product): array
    {
        $history = collect(self::HISTORY)
            ->map(fn ($label, $table) => [$label, DB::table($table)->where('product_id', $product->id)->count()])
            ->filter(fn ($x) => $x[1] > 0);
        $stock = (int) DB::table('stock_balances')->where('product_id', $product->id)->sum('quantity');
        $deletable = $history->isEmpty();

        return [
            'product' => $product,
            'stock' => $stock,
            'history' => $history->map(fn ($x) => "{$x[1]} {$x[0]}")->join(', '),
            'deletable' => $deletable,
            'action' => $deletable ? 'hapus' : ($stock !== 0 ? 'nonaktifkan + nolkan stok gudang' : 'nonaktifkan'),
        ];
    }

    private function clean(array $r): void
    {
        /** @var Product $product */
        $product = $r['product'];

        if ($r['deletable']) {
            // Harga, saldo stok (tanpa mutasi), dan target ikut terhapus lewat cascade.
            $product->delete();
            ActivityLog::record('product.delete', "Hapus produk non-Oasis {$product->name} ({$product->sku})");

            return;
        }

        $this->zeroWarehouseStock($product);
        $product->update(['is_active' => false]);
        ActivityLog::record('product.deactivate', "Nonaktifkan produk non-Oasis {$product->name} ({$product->sku})", $product);
    }

    /** Sisa stok di gudang depo dinolkan lewat dokumen penyesuaian, supaya jejak mutasinya tetap ada. */
    private function zeroWarehouseStock(Product $product): void
    {
        /** @var Collection<int, object> $balances */
        $balances = DB::table('stock_balances')
            ->join('stock_locations', 'stock_locations.id', '=', 'stock_balances.location_id')
            ->where('stock_balances.product_id', $product->id)->where('stock_balances.quantity', '!=', 0)
            ->where('stock_locations.type', 'warehouse')
            ->get(['stock_locations.depot_id', 'stock_balances.quantity']);

        foreach ($balances as $b) {
            app(StockDocumentService::class)->post(
                Depot::findOrFail($b->depot_id), 'adjustment', Carbon::today(), [$product->id => -(int) $b->quantity],
                'HAPUS-NON-OASIS', "Hapus produk non-Oasis {$product->name}",
            );
        }

        $elsewhere = DB::table('stock_balances')->where('product_id', $product->id)->where('quantity', '!=', 0)->count();
        if ($elsewhere > 0) {
            $where = StockLocation::whereIn('id', DB::table('stock_balances')->where('product_id', $product->id)->where('quantity', '!=', 0)->pluck('location_id'))->pluck('name')->join(', ');
            $this->warn("{$product->name}: masih ada stok di {$where} (kendaraan/barang rusak) — selesaikan lewat bongkar/opname.");
        }
    }
}
