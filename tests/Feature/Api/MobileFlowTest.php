<?php

namespace Tests\Feature\Api;

use App\Enums\Position;
use App\Enums\TripStatus;
use App\Models\Employee;
use App\Models\SalesRoute;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsOasis;
use Tests\TestCase;

class MobileFlowTest extends TestCase
{
    use BuildsOasis;

    private function login(Employee $salesman): string
    {
        $user = User::where('employee_id', $salesman->id)->first();

        return $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'rahasia123', 'device_name' => 'HP Uji'])
            ->assertOk()->json('data.token');
    }

    public function test_hari_kerja_motoris_lengkap_dari_hp(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $salesman = $this->salesman($depot);
        $galon = $this->product('G19', 20000, true);
        $botol = $this->product('B600', 3000);
        $outlet = $this->outlet($depot);
        $route = SalesRoute::create(['depot_id' => $depot->id, 'employee_id' => $salesman->id, 'name' => 'Rute', 'weekday' => null]);
        $route->outlets()->attach($outlet->id, ['sequence' => 1]);
        $this->stock($depot, $galon, 50);
        $this->stock($depot, $botol, 100);

        $this->actingAs($this->admin());
        $trip = app(TripService::class)->load($salesman, Carbon::today(), [$galon->id => 20, $botol->id => 48]);
        auth()->logout();

        $h = ['Authorization' => 'Bearer '.$this->login($salesman)];

        $boot = $this->getJson('/api/v1/bootstrap', $h)->assertOk();
        $boot->assertJsonPath('data.trip.number', $trip->number)
            ->assertJsonPath('data.route.0.id', $outlet->id)
            ->assertJsonPath('data.products.0.price', 3000);
        $this->assertEqualsCanonicalizing([20, 48], collect($boot->json('data.trip.stock'))->pluck('quantity')->all());

        $this->postJson('/api/v1/attendance/check-in', ['latitude' => -6.9, 'longitude' => 107.6], $h)->assertCreated();
        $this->postJson('/api/v1/attendance/check-in', [], $h)->assertOk(); // kirim ulang tidak membuat absensi baru

        $visitUuid = $this->uuid();
        $this->postJson('/api/v1/visits', ['client_uuid' => $visitUuid, 'outlet_id' => $outlet->id, 'latitude' => -6.9009, 'longitude' => 107.6], $h)
            ->assertCreated()->assertJsonPath('data.distance_m', 100);

        $sale = ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id, 'visit_uuid' => $visitUuid, 'payment_method' => 'cash',
            'lines' => [['product_id' => $galon->id, 'quantity' => 3], ['product_id' => $botol->id, 'quantity' => 24]]];
        $first = $this->postJson('/api/v1/sales', $sale, $h)->assertCreated()->assertJsonPath('data.total', 3 * 20000 + 24 * 3000);
        // Kirim ulang (sinyal putus) → data yang sama, tidak dobel.
        $this->postJson('/api/v1/sales', $sale, $h)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        $this->postJson('/api/v1/returns', ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id,
            'lines' => [['product_id' => $botol->id, 'quantity' => 2, 'reason' => 'leaking']]], $h)->assertCreated();
        $this->postJson('/api/v1/containers', ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id, 'quantity' => 2], $h)
            ->assertCreated()->assertJsonPath('data.outlet_balance', 1);
        $this->postJson("/api/v1/visits/{$visitUuid}/end", [], $h)->assertOk()->assertJsonPath('data.outcome', 'sale');

        $this->postJson('/api/v1/locations', ['points' => [
            ['recorded_at' => now()->toIso8601String(), 'latitude' => -6.9, 'longitude' => 107.6, 'battery' => 80],
            ['recorded_at' => now()->toIso8601String(), 'latitude' => -6.9, 'longitude' => 107.6],
        ]], $h)->assertOk()->assertJsonPath('data.stored', 1);

        // Stok kendaraan tidak cukup → 422 dengan kode jelas.
        $this->postJson('/api/v1/sales', ['client_uuid' => $this->uuid(), 'outlet_id' => $outlet->id, 'payment_method' => 'cash',
            'lines' => [['product_id' => $galon->id, 'quantity' => 999]]], $h)
            ->assertStatus(422)->assertJsonPath('error.code', 'insufficient_stock');

        $this->getJson('/api/v1/sales', $h)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/trip/finish', [], $h)->assertOk()->assertJsonPath('data.status', 'returned');
        $this->assertSame(TripStatus::Returned, $trip->fresh()->status);

        // Setelah selesai, tidak ada muatan aktif lagi.
        $this->postJson('/api/v1/sales', array_merge($sale, ['client_uuid' => $this->uuid()]), $h)
            ->assertStatus(422)->assertJsonPath('error.code', 'no_active_trip');

        $this->postJson('/api/v1/auth/logout', [], $h)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/bootstrap', $h)->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_outlet_baru_dari_hp_masuk_menunggu_verifikasi_dengan_area_otomatis(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $territory = $depot->territories()->create(['name' => 'Area A', 'boundary' => ['type' => 'Polygon', 'coordinates' => [[[107, -7], [108, -7], [108, -6], [107, -6], [107, -7]]]]]);
        $salesman = $this->salesman($depot);
        $h = ['Authorization' => 'Bearer '.$this->login($salesman)];
        $uuid = $this->uuid();

        $this->postJson('/api/v1/outlets', ['client_uuid' => $uuid, 'name' => 'Warung Baru', 'latitude' => -6.5, 'longitude' => 107.5], $h)
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.territory_id', $territory->id);
        $this->postJson('/api/v1/outlets', ['client_uuid' => $uuid, 'name' => 'Warung Baru', 'latitude' => -6.5, 'longitude' => 107.5], $h)->assertOk();
        $this->assertDatabaseCount('outlets', 1);

        $this->getJson('/api/v1/outlets?q=Warung', $h)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_hanya_motoris_aktif_yang_bisa_login_hp(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $coordinator = $this->employee($depot, Position::Coordinator);
        $user = $this->userFor('koordinator', $coordinator);

        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'rahasia123', 'device_name' => 'x'])
            ->assertForbidden()->assertJsonPath('error.code', 'not_salesman');
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'salah', 'device_name' => 'x'])
            ->assertUnauthorized();
        $this->getJson('/api/v1/bootstrap', ['Authorization' => 'Bearer palsu'])->assertUnauthorized();
    }

    public function test_penjualan_ke_outlet_depo_lain_ditolak(): void
    {
        $this->seedReference();
        $depot = $this->depot();
        $other = $this->depot('BKS');
        $salesman = $this->salesman($depot);
        $p = $this->product('B600', 3000);
        $this->stock($depot, $p, 10);
        $this->actingAs($this->admin());
        app(TripService::class)->load($salesman, Carbon::today(), [$p->id => 10]);
        auth()->logout();
        $h = ['Authorization' => 'Bearer '.$this->login($salesman)];

        $this->postJson('/api/v1/sales', ['client_uuid' => $this->uuid(), 'outlet_id' => $this->outlet($other)->id, 'payment_method' => 'cash',
            'lines' => [['product_id' => $p->id, 'quantity' => 1]]], $h)->assertStatus(422)->assertJsonPath('error.code', 'outlet_not_found');
        $this->assertSame(0, Trip::first()->sales()->count());
    }
}
