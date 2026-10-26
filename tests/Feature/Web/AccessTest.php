<?php

namespace Tests\Feature\Web;

use App\Enums\Position;
use App\Models\Trip;
use App\Models\User;
use App\Services\FieldService;
use App\Services\TripService;
use App\Support\Navigation;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use BuildsOasis;

    public function test_semua_menu_terbuka_untuk_super_admin_dengan_data_demo(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('username', 'admin')->first();
        $admin->update(['must_change_password' => false]);
        $this->actingAs($admin);

        $slugs = collect(Navigation::flat($admin))->pluck('slug');
        $this->assertGreaterThan(25, $slugs->count());

        foreach ($slugs as $slug) {
            $response = $this->get("/{$slug}");
            $response->assertOk();
            $component = $response->viewData('page')['component'];
            $this->assertFileExists(resource_path("js/Pages/{$component}.vue"), "Halaman Vue {$component} untuk /{$slug}");
        }

        // Halaman turunan.
        $trip = Trip::first();
        foreach (["/muatan/{$trip->id}", '/muatan/baru', "/setoran/{$trip->id}", '/master/area/peta', '/master/rute/1', '/penjualan/1',
            '/target?tab=salesman', '/target?tab=product', '/pencapaian?tab=product', '/laporan/penjualan?grup=product', '/ganti-password'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertFileExists(resource_path('js/Pages/'.$response->viewData('page')['component'].'.vue'));
        }

        $this->get('/laporan/penjualan/unduh?grup=salesman')->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_absensi_menampilkan_motoris_yang_belum_absen(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $this->salesman($depot);
        $present = $this->salesman($depot);
        app(FieldService::class)->checkIn($present, []);

        $this->actingAs($this->admin()->forceFill(['must_change_password' => false]))->get('/absensi')->assertOk()
            ->assertInertia(fn ($page) => $page->has('rows', 2)->where('summary.present', 1));
    }

    public function test_izin_peran_dan_cakupan_depo(): void
    {
        $this->seedReference();
        $a = $this->depot('A');
        $b = $this->depot('B');
        $p = $this->product('B600', 3000);
        $this->stock($b, $p, 10);
        $this->actingAs($this->admin());
        $tripB = app(TripService::class)->load($this->salesman($b), Carbon::today(), [$p->id => 5]);

        $gudangA = $this->userFor('gudang', $this->employee($a, Position::Warehouse));
        $kepalaA = $this->userFor('kepala_depo', null, [$a]);

        // Gudang tidak punya akses setoran & pengguna.
        $this->actingAs($gudangA)->get('/setoran')->assertForbidden();
        $this->actingAs($gudangA)->get('/pengaturan/pengguna')->assertForbidden();
        $this->actingAs($gudangA)->get('/muatan')->assertOk();

        // Cakupan depo: muatan depo B tidak terlihat dari akun depo A.
        $this->actingAs($kepalaA)->get("/muatan/{$tripB->id}")->assertNotFound();
        $this->actingAs($kepalaA)->get('/muatan?depot='.$b->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('trips', []));

        // Sidebar hanya berisi menu yang diizinkan.
        $menu = Navigation::flat($gudangA);
        $this->assertContains('stok', array_column($menu, 'slug'));
        $this->assertNotContains('setoran', array_column($menu, 'slug'));
    }

    public function test_login_web_dan_akun_motoris_ditolak(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $salesman = $this->salesman($depot);
        $salesUser = User::where('employee_id', $salesman->id)->first();

        $this->post('/login', ['username' => $salesUser->username, 'password' => 'rahasia123'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->get('/home')->assertRedirect('/login');
        $this->post('/login', ['username' => 'admin', 'password' => 'password'])->assertRedirect('/home');
        // Admin awal wajib ganti password dulu.
        $this->get('/home')->assertRedirect('/ganti-password');
        $this->put('/ganti-password', ['current_password' => 'password', 'password' => 'PasswordBaru1', 'password_confirmation' => 'PasswordBaru1'])
            ->assertRedirect('/home');
        $this->get('/home')->assertOk();
    }
}
