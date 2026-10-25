<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Salin data dari database Enesis (di tes: file SQLite terpisah dengan skema yang sama) ke Oasis.
class SalinDariEnesisTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'enesis').'.sqlite';
        touch($this->file);
        config(['database.connections.enesis' => [
            'driver' => 'sqlite', 'database' => $this->file, 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('enesis');
        Artisan::call('migrate', ['--database' => 'enesis', '--force' => true]);

        $sumber = DB::connection('enesis');
        $sumber->table('users')->insert([
            ['id' => 7, 'name' => 'Andi', 'username' => 'andi', 'email' => 'andi@contoh.test', 'password' => bcrypt('rahasia'), 'kategori' => 'sales'],
            ['id' => 9, 'name' => 'Budi', 'username' => 'budi', 'email' => 'budi@contoh.test', 'password' => bcrypt('rahasia'), 'kategori' => 'sales'],
        ]);
        // Kunci utama gabungan (menu, id) dan tabel tanpa kolom "id".
        $sumber->table('mk_baris_mentah')->insert([
            ['menu' => 'penjualan_barang', 'id' => 1, 'data' => '{}'],
            ['menu' => 'stok_outlet', 'id' => 1, 'data' => '{}'],
        ]);
        $sumber->table('pengaturan_admin')->insert(['kunci' => 'target_nasional', 'nilai' => '100']);
        $sumber->table('sessions')->insert(['id' => 'sesi-enesis', 'payload' => 'x', 'last_activity' => 1]);
        // Tabel menu yang tidak dibawa ke Oasis.
        Schema::connection('enesis')->create('loading_barang', fn (Blueprint $t) => $t->id());
    }

    protected function tearDown(): void
    {
        DB::purge('enesis');
        @unlink($this->file);

        parent::tearDown();
    }

    public function test_menyalin_tabel_yang_sama_dan_mengganti_isi_oasis(): void
    {
        User::factory()->create(['id' => 1, 'username' => 'lama']);

        $this->artisan('oasis:salin-dari-enesis', ['--paksa' => true])
            ->expectsOutputToContain('loading_barang')
            ->assertSuccessful();

        $this->assertSame(['andi', 'budi'], DB::table('users')->orderBy('id')->pluck('username')->all());
        $this->assertSame([7, 9], DB::table('users')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(2, DB::table('mk_baris_mentah')->count());
        $this->assertSame('100', DB::table('pengaturan_admin')->where('kunci', 'target_nasional')->value('nilai'));
        $this->assertSame(0, DB::table('sessions')->count(), 'sesi tidak disalin');
        $this->assertFalse(Schema::hasTable('loading_barang'));

        // Diulang: hasilnya sama, tidak dobel.
        $this->artisan('oasis:salin-dari-enesis', ['--paksa' => true])->assertSuccessful();
        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_coba_tidak_menulis_dan_tabel_bisa_dibatasi(): void
    {
        User::factory()->create(['username' => 'lama']);

        $this->artisan('oasis:salin-dari-enesis', ['--coba' => true])->assertSuccessful();
        $this->assertSame(['lama'], DB::table('users')->pluck('username')->all());

        $this->artisan('oasis:salin-dari-enesis', ['--tabel' => ['pengaturan_admin'], '--paksa' => true])->assertSuccessful();
        $this->assertSame(['lama'], DB::table('users')->pluck('username')->all());
        $this->assertSame(1, DB::table('pengaturan_admin')->count());
    }

    public function test_gagal_jelas_bila_database_sumber_tidak_terhubung(): void
    {
        config(['database.connections.enesis.database' => '/tidak/ada/enesis.sqlite']);
        DB::purge('enesis');

        $this->artisan('oasis:salin-dari-enesis', ['--paksa' => true])
            ->expectsOutputToContain('Tidak bisa terhubung')
            ->assertFailed();
    }
}
