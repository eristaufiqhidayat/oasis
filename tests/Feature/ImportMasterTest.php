<?php

namespace Tests\Feature;

use App\Models\Depot;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class ImportMasterTest extends TestCase
{
    use BuildsOasis;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/oasis-import-'.uniqid();
        mkdir($this->dir);

        $csv = function (string $table, array $rows) {
            $h = fopen("{$this->dir}/{$table}.csv", 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, array_keys($rows[0]), escape: '\\');
            foreach ($rows as $r) {
                fputcsv($h, $r, escape: '\\');
            }
            fclose($h);
        };

        $csv('mk_depo', [['id' => 11, 'kode' => 'BDG', 'nama' => 'Depo Bandung', 'aktif' => 1]]);
        $csv('master_depo', [['kode' => 'BDG', 'nama' => 'Depo Bandung', 'alamat' => 'Jl. Soekarno Hatta', 'kota_kab' => 'Bandung', 'latitude' => '-6.9175', 'longitude' => '107.6191']]);
        $csv('mk_area', [['id' => 21, 'kode' => 'A1', 'nama' => 'Area Kota', 'depo_id' => 11], ['id' => 22, 'kode' => 'X', 'nama' => 'Tanpa depo', 'depo_id' => 99]]);
        $csv('area_polygon', [['source_id' => 21, 'code' => 'A1', 'polygon' => json_encode([[-7, 107], [-7, 108], [-6, 108], [-6, 107]])]]);
        $csv('mk_karyawan', [
            ['id' => 31, 'kategori' => 'koordinator', 'nama' => 'Kordi', 'depo_id' => 11, 'koordinator_id' => '', 'no_hp' => '0811', 'tanggal_mulai' => '2024-01-02', 'aktif' => 1],
            ['id' => 32, 'kategori' => 'sales', 'nama' => 'Sales Satu', 'depo_id' => 11, 'koordinator_id' => 31, 'no_hp' => '0812', 'tanggal_mulai' => '', 'aktif' => 1],
        ]);
        $csv('mk_outlet', [
            ['id' => 41, 'kode' => 'OUT1', 'nama' => 'Warung A', 'alamat' => 'Jl. A', 'area_id' => 21, 'kecamatan' => 'Coblong', 'kota_kabupaten' => 'Bandung', 'latitude' => '-6.5', 'longitude' => '107.5', 'status_fiktif' => ''],
            ['id' => 42, 'kode' => 'OUT2', 'nama' => 'Tanpa area', 'alamat' => '', 'area_id' => 99, 'kecamatan' => '', 'kota_kabupaten' => '', 'latitude' => '', 'longitude' => '', 'status_fiktif' => ''],
        ]);
        $csv('mk_produk', [['id' => 51, 'kode' => 'G19', 'nama' => 'Oasis Galon 19L', 'harga_jual' => '20000.00']]);
        $csv('users', [['id' => 1, 'name' => 'Sales Satu', 'username' => 'Sales1', 'password' => Hash::make('lama123'), 'kategori' => 'sales', 'karyawan_id' => 32, 'aktif' => 1, 'wajib_ganti_password' => 0]]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*.csv"));
        rmdir($this->dir);
        parent::tearDown();
    }

    public function test_impor_csv_memetakan_master_dan_bisa_diulang(): void
    {
        $this->seedReference();

        $this->artisan('oasis:impor-master', ['--csv' => $this->dir])->assertSuccessful();

        $depot = Depot::where('legacy_id', 11)->firstOrFail();
        $this->assertSame('Jl. Soekarno Hatta', $depot->address);
        $this->assertEqualsWithDelta(-6.9175, $depot->latitude, 0.0001);

        $territory = Territory::where('legacy_id', 21)->firstOrFail();
        $this->assertSame('Polygon', $territory->boundary['type']);
        // [lat, lng] lama diubah ke GeoJSON [lng, lat] dan ring ditutup.
        $this->assertSame([107, -7], array_map('intval', $territory->boundary['coordinates'][0][0]));
        $this->assertCount(5, $territory->boundary['coordinates'][0]);
        $this->assertSame(1, Territory::count());

        $sales = Employee::where('legacy_id', 32)->firstOrFail();
        $this->assertSame('salesman', $sales->position->value);
        $this->assertSame(Employee::where('legacy_id', 31)->value('id'), $sales->supervisor_id);

        $this->assertSame(1, Outlet::count());
        $this->assertSame($territory->id, Outlet::first()->territory_id);
        $this->assertTrue(Product::where('sku', 'G19')->value('tracks_container'));

        $user = User::where('username', 'sales1')->firstOrFail();
        $this->assertSame($sales->id, $user->employee_id);
        $this->assertTrue(Hash::check('lama123', $user->password));
        $this->assertTrue($user->isSalesman());

        // Diulang: tidak ada duplikat.
        $this->artisan('oasis:impor-master', ['--csv' => $this->dir])->assertSuccessful();
        $this->assertSame(1, Depot::count());
        $this->assertSame(2, Employee::count());
        $this->assertSame(1, Outlet::count());
        $this->assertSame(2, User::count()); // admin + sales1
    }
}
