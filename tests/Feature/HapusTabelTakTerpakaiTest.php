<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Tabel yang tidak dibuat migration Oasis (sisa Enesis/Marketama) dihapus, dengan cadangan CSV.
class HapusTabelTakTerpakaiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('loading_barang', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->nullable();
        });
        DB::table('loading_barang')->insert([['kode' => 'L1'], ['kode' => 'L2']]);
        Schema::create('kelurahan', fn (Blueprint $t) => $t->string('nama'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/backup/tabel-dihapus'));

        parent::tearDown();
    }

    public function test_tanpa_hapus_hanya_menampilkan_daftar(): void
    {
        $this->artisan('oasis:hapus-tabel-tak-terpakai')
            ->expectsOutputToContain('loading_barang')
            ->expectsOutputToContain('Belum ada yang dihapus')
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('loading_barang'));
    }

    public function test_hapus_tabel_tak_terpakai_dengan_cadangan_dan_tabel_oasis_tetap(): void
    {
        $this->artisan('oasis:hapus-tabel-tak-terpakai', ['--hapus' => true, '--paksa' => true, '--kecuali' => ['kelurahan']])
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('loading_barang'));
        $this->assertTrue(Schema::hasTable('kelurahan'), '--kecuali tidak dihapus');
        foreach (['users', 'roles', 'role_hak', 'mk_outlet', 'trx_absensi', 'mk_baris_mentah', 'tipe_outlet', 'lap_join_visit'] as $t) {
            $this->assertTrue(Schema::hasTable($t), "{$t} dipakai Oasis, tidak boleh dihapus");
        }

        $file = File::glob(storage_path('app/backup/tabel-dihapus/*/loading_barang.csv.gz'))[0] ?? null;
        $this->assertNotNull($file);
        $isi = array_map('str_getcsv', array_filter(explode("\n", gzdecode(file_get_contents($file)))));
        $this->assertSame([['id', 'kode'], ['1', 'L1'], ['2', 'L2']], $isi);
    }
}
