<?php

namespace Tests\Feature;

use App\Support\Akses\Akses;
use Tests\TestCase;

// Menu yang belum terhubung ke data aplikasi motoris diberi awalan "* " di sidebar (config marketama.menu_motoris).
class MenuMotorisTest extends TestCase
{
    private function judul(): array
    {
        $hasil = [];

        foreach (Akses::menu(null) as $grup) {
            foreach ($grup['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $m) {
                    $hasil[$m['slug']] = $m['title'];
                }
            }
        }

        return $hasil;
    }

    public function test_oasis_hanya_berisi_menu_terhubung_motoris_tanpa_bintang(): void
    {
        $judul = $this->judul();

        // Menu bertanda "*" di Enesis tidak dibawa ke Oasis, jadi tidak ada judul berawalan "* ".
        $this->assertEmpty(array_filter($judul, fn ($t) => str_starts_with($t, '* ')));
        $this->assertArrayNotHasKey('loading_barang', $judul);
        $this->assertArrayNotHasKey('outlet_duplikat', $judul);
        $this->assertSame('Beranda', $judul['home']);
        $this->assertSame('Stok Kompetitor', $judul['stok_kompetitor']);
        $this->assertSame('Lap. Join Visit', $judul['lap_join_visit']);
        $this->assertSame('Summary All Depo', $judul['summary_all_depo']);
        $this->assertSame('Join Visit', $judul['join_visit']);
        $this->assertSame('Master Area', $judul['master_area']);
    }

    public function test_daftar_menu_motoris_hanya_berisi_slug_yang_ada(): void
    {
        $this->assertEmpty(array_diff(config('marketama.menu_motoris'), array_keys($this->judul())));
    }

    public function test_grup_flyout_tidak_diberi_bintang(): void
    {
        foreach (Akses::menu(null) as $grup) {
            foreach ($grup['items'] as $item) {
                if (isset($item['children'])) {
                    $this->assertStringStartsNotWith('* ', $item['title']);
                }
            }
        }
    }
}
