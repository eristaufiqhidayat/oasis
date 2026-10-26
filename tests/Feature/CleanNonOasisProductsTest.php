<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StockDocument;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class CleanNonOasisProductsTest extends TestCase
{
    use BuildsOasis;

    private function make(string $sku, string $name): Product
    {
        $p = Product::create(['sku' => $sku, 'name' => $name, 'unit' => 'pcs']);
        ProductPrice::put($p->id, null, '2026-01-01', 1000);

        return $p;
    }

    public function test_mode_lihat_saja_tidak_mengubah_apa_pun(): void
    {
        $this->seedReference();
        $this->make('G19', 'Oasis Galon 19 L');
        $this->make('TTP', 'Tutup galon + segel');

        $this->artisan('oasis:hapus-produk-non-oasis')->expectsOutputToContain('Mode lihat saja')->assertSuccessful();

        $this->assertSame(2, Product::count());
    }

    public function test_hapus_produk_non_oasis_dan_simpan_yang_punya_riwayat(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $oasis = $this->make('G19', 'Oasis Galon 19 L');
        $unused = $this->make('AQ', 'Aqua 600 ml');
        $kept = $this->make('CUP', 'Gelas plastik');
        $stocked = $this->make('TTP', 'Tutup galon + segel');
        $this->stock($depot, $oasis, 50);
        $this->stock($depot, $stocked, 300);

        $this->artisan('oasis:hapus-produk-non-oasis', ['--jalankan' => true, '--kecuali' => ['cup']])->assertSuccessful();

        // Tanpa riwayat → terhapus beserta harganya.
        $this->assertNull(Product::find($unused->id));
        $this->assertSame(0, ProductPrice::where('product_id', $unused->id)->count());

        // Punya riwayat stok → dinonaktifkan, stok gudang dinolkan lewat dokumen penyesuaian.
        $this->assertFalse($stocked->fresh()->is_active);
        $this->assertSame(0, $this->warehouseQty($depot, $stocked));
        $this->assertSame(1, StockDocument::where('type', 'adjustment')->where('reference', 'HAPUS-NON-OASIS')->count());

        // Produk Oasis dan pengecualian tidak tersentuh.
        $this->assertTrue($oasis->fresh()->is_active);
        $this->assertSame(50, $this->warehouseQty($depot, $oasis));
        $this->assertTrue($kept->fresh()->is_active);

        // Run berikutnya tidak menemukan apa-apa lagi.
        $this->artisan('oasis:hapus-produk-non-oasis', ['--kecuali' => ['CUP']])->expectsOutputToContain('Tidak ada produk non-Oasis')->assertSuccessful();

        // Halaman stok tidak lagi menampilkan produk non-Oasis.
        $this->actingAs($this->userFor('super_admin'))
            ->get('/stok?depot='.$depot->id)
            ->assertInertia(fn ($page) => $page->where('products', fn ($products) => collect($products)->pluck('sku')->sort()->values()->all() === ['CUP', 'G19']));
    }
}
