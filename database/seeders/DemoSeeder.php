<?php

namespace Database\Seeders;

use App\Enums\Position;
use App\Enums\TargetMetric;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OutletChannel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Region;
use App\Models\Role;
use App\Models\SalesRoute;
use App\Models\Territory;
use App\Models\User;
use App\Services\FieldService;
use App\Services\Inventory;
use App\Services\SalesService;
use App\Services\SettlementService;
use App\Services\StockDocumentService;
use App\Services\TargetService;
use App\Services\TripService;
use App\Support\Geo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Data contoh untuk lokal/demo (bukan untuk server produksi): 2 depo, motoris, outlet, rute,
 * produk & harga, stok, satu hari transaksi yang sudah direkonsiliasi dan satu muatan yang sedang jalan.
 * Semua akun demo berpassword "password".
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);
        $this->call(DatabaseSeeder::class);
        Auth::login(User::where('username', 'admin')->first());

        $region = Region::firstOrCreate(['code' => 'JBR'], ['name' => 'Jawa Barat']);
        $depots = [
            Depot::firstOrCreate(['code' => 'BDG'], ['region_id' => $region->id, 'name' => 'Depo Bandung', 'city' => 'Bandung', 'latitude' => -6.9175, 'longitude' => 107.6191]),
            Depot::firstOrCreate(['code' => 'BKS'], ['region_id' => $region->id, 'name' => 'Depo Bekasi', 'city' => 'Bekasi', 'latitude' => -6.2383, 'longitude' => 106.9756]),
        ];

        $galon = ProductCategory::where('name', 'Galon')->value('id');
        $botol = ProductCategory::where('name', 'Botol')->value('id');
        $cup = ProductCategory::where('name', 'Gelas (cup)')->value('id');
        $products = collect([
            ['OAS-G19', 'Oasis Galon 19 L', $galon, 'galon', 1, true, 20000],
            ['OAS-B600', 'Oasis Botol 600 ml', $botol, 'botol', 24, false, 3000],
            ['OAS-B1500', 'Oasis Botol 1500 ml', $botol, 'botol', 12, false, 5500],
            ['OAS-B330', 'Oasis Botol 330 ml', $botol, 'botol', 24, false, 2200],
            ['OAS-C240', 'Oasis Cup 240 ml', $cup, 'dus', 1, false, 22000],
        ])->map(function ($p) {
            $product = Product::firstOrCreate(['sku' => $p[0]], ['name' => $p[1], 'category_id' => $p[2], 'unit' => $p[3], 'units_per_case' => $p[4], 'tracks_container' => $p[5]]);
            ProductPrice::put($product->id, null, '2026-01-01', $p[6]);

            return $product;
        });
        // Harga galon di Bekasi lebih mahal (ongkos kirim).
        ProductPrice::put($products[0]->id, $depots[1]->id, '2026-01-01', 21000);

        $roles = Role::pluck('id', 'slug');
        $channels = OutletChannel::pluck('id')->all();
        $period = Carbon::today()->startOfMonth()->toImmutable();
        $names = ['Andi', 'Budi', 'Cahya', 'Dedi', 'Eka', 'Fajar', 'Gilang', 'Hendra'];
        $shops = ['Warung', 'Toko', 'Kios', 'Depot Air', 'Kantin', 'Toko Kelontong'];
        $owners = ['Bu Siti', 'Pak Ujang', 'Bu Ani', 'Pak Asep', 'Bu Lilis', 'Pak Dadang', 'Bu Euis', 'Pak Rahmat'];
        $n = 0;

        foreach ($depots as $d => $depot) {
            // Area: 2 kotak di sekitar depo.
            $territories = collect([[-0.02, 0.0], [0.0, 0.0]])->map(fn ($o, $i) => Territory::firstOrCreate(
                ['depot_id' => $depot->id, 'code' => "{$depot->code}-A".($i + 1)],
                ['name' => "Area {$depot->city} ".($i ? 'Timur' : 'Barat'), 'color' => $i ? '#293694' : '#18a6b1', 'boundary' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [$depot->longitude + $o[0] - 0.02 + $i * 0.02, $depot->latitude - 0.02],
                        [$depot->longitude + $o[0] + $i * 0.02, $depot->latitude - 0.02],
                        [$depot->longitude + $o[0] + $i * 0.02, $depot->latitude + 0.02],
                        [$depot->longitude + $o[0] - 0.02 + $i * 0.02, $depot->latitude + 0.02],
                        [$depot->longitude + $o[0] - 0.02 + $i * 0.02, $depot->latitude - 0.02],
                    ]],
                ]],
            ));

            $staff = fn (string $code, string $name, Position $pos, ?int $sup = null) => Employee::firstOrCreate(
                ['code' => $code], ['name' => $name, 'position' => $pos, 'depot_id' => $depot->id, 'supervisor_id' => $sup, 'phone' => '08'.mt_rand(1000000000, 1999999999)],
            );
            $head = $staff("{$depot->code}-KD", "Kepala {$depot->name}", Position::DepotHead);
            $coord = $staff("{$depot->code}-KO", 'Koordinator '.$depot->city, Position::Coordinator, $head->id);
            $wh = $staff("{$depot->code}-GD", 'Gudang '.$depot->city, Position::Warehouse);
            $cs = $staff("{$depot->code}-KS", 'Kasir '.$depot->city, Position::Cashier);

            foreach ([[$head, 'kepala_depo'], [$coord, 'koordinator'], [$wh, 'gudang'], [$cs, 'kasir']] as [$e, $role]) {
                $u = User::firstOrCreate(['username' => Str::slug($e->code, '.')], ['name' => $e->name, 'password' => 'password', 'role_id' => $roles[$role], 'employee_id' => $e->id]);
                $u->depots()->syncWithoutDetaching([$depot->id]);
            }

            $salesmen = collect(range(1, 3))->map(function ($i) use ($depot, $staff, $coord, $names, $d, $roles) {
                $e = $staff("{$depot->code}-M{$i}", $names[$d * 3 + $i - 1].' (motoris)', Position::Salesman, $coord->id);
                User::firstOrCreate(['username' => Str::lower("{$depot->code}m{$i}")], ['name' => $e->name, 'password' => 'password', 'role_id' => $roles['sales'], 'employee_id' => $e->id]);

                return $e;
            });

            // Outlet tersebar di area, dibagi ke rute motoris (Senin–Sabtu, 2 rute per motoris).
            $outlets = collect(range(1, 36))->map(function ($i) use ($depot, $territories, $channels, $shops, $owners, &$n) {
                $n++;
                $lat = $depot->latitude + (mt_rand(-180, 180) / 10000);
                $lng = $depot->longitude + (mt_rand(-380, 380) / 10000);
                $territory = $territories->first(fn ($t) => Geo::contains($t->boundary, $lat, $lng));

                return Outlet::firstOrCreate(['code' => sprintf('%s-%04d', $depot->code, $i)], [
                    'name' => $shops[$n % count($shops)].' '.$owners[$n % count($owners)].' '.$i, 'owner_name' => $owners[$n % count($owners)],
                    'phone' => '08'.mt_rand(1100000000, 1999999999), 'address' => "Jl. Contoh No. {$i}, {$depot->city}",
                    'city' => $depot->city, 'latitude' => $lat, 'longitude' => $lng, 'depot_id' => $depot->id,
                    'territory_id' => $territory?->id, 'channel_id' => $channels[$n % count($channels)], 'status' => 'active',
                ]);
            });

            foreach ($salesmen as $s => $salesman) {
                foreach (range(1, 6) as $day) {
                    $route = SalesRoute::firstOrCreate(['employee_id' => $salesman->id, 'weekday' => $day], ['depot_id' => $depot->id, 'name' => "Rute {$salesman->name} – ".SalesRoute::WEEKDAYS[$day]]);
                    $chunk = $outlets->slice(($s * 12) + (($day % 2) * 6), 6)->values();
                    $route->outlets()->sync($chunk->mapWithKeys(fn ($o, $i) => [$o->id => ['sequence' => $i + 1]])->all());
                }
            }

            // Stok awal dan target bulan ini.
            app(StockDocumentService::class)->post($depot, 'receipt', Carbon::today()->subDays(2), $products->mapWithKeys(fn ($p) => [$p->id => $p->tracks_container ? 600 : 2400])->all(), 'DO-PABRIK-'.$depot->code);
            app(TargetService::class)->set($period, TargetMetric::Revenue, ['depot_id' => $depot->id], 150_000_000);
            app(TargetService::class)->set($period, TargetMetric::EffectiveCalls, ['depot_id' => $depot->id], 1800);
            app(TargetService::class)->distributeEvenly($period, $depot);

            $this->simulateDays($depot, $salesmen, $outlets, $products);
        }

        Auth::logout();
    }

    /** Kemarin: muatan lengkap sampai disetujui. Hari ini: muatan sedang di lapangan. */
    private function simulateDays(Depot $depot, $salesmen, $outlets, $products): void
    {
        $trips = app(TripService::class);
        $sales = app(SalesService::class);
        $field = app(FieldService::class);
        $settle = app(SettlementService::class);
        Carbon::setTestNow();
        $realToday = Carbon::today();

        foreach ($salesmen as $s => $salesman) {
            foreach ([1, 0] as $daysAgo) {
                $day = $realToday->copy()->subDays($daysAgo);
                Carbon::setTestNow($day->copy()->setTime(7, 30));
                $trip = $trips->load($salesman, Carbon::today(), $products->mapWithKeys(fn ($p) => [$p->id => $p->tracks_container ? 40 : 96])->all());
                $field->checkIn($salesman, ['latitude' => $depot->latitude, 'longitude' => $depot->longitude]);
                $trips->depart($trip);

                $route = $outlets->slice($s * 12, $daysAgo ? 10 : 5);
                foreach ($route->values() as $i => $outlet) {
                    Carbon::setTestNow($day->copy()->setTime(8 + intdiv($i * 40, 60), ($i * 40) % 60));
                    $visit = $field->startVisit($salesman, $trip, ['client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'latitude' => $outlet->latitude + 0.0002, 'longitude' => $outlet->longitude]);
                    $field->recordPings($salesman, $trip, [['recorded_at' => now()->toIso8601String(), 'latitude' => $outlet->latitude, 'longitude' => $outlet->longitude, 'battery' => 90 - $i * 3]]);

                    if ($i % 4 === 3) {
                        $field->endVisit($visit, ['outcome' => 'no_sale', 'no_sale_reason' => 'stock_full']);

                        continue;
                    }

                    $lines = [['product_id' => $products[0]->id, 'quantity' => mt_rand(1, 4)], ['product_id' => $products[mt_rand(1, 4)]->id, 'quantity' => mt_rand(6, 24)]];
                    $sales->recordSale($salesman, $trip, ['client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'visit_uuid' => $visit->client_uuid, 'payment_method' => $i % 5 ? 'cash' : 'transfer', 'lines' => $lines]);
                    $sales->collectContainers($salesman, $trip, ['client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'quantity' => mt_rand(1, 3)]);
                    $field->endVisit($visit, []);
                }

                if ($daysAgo) {
                    Carbon::setTestNow($day->copy()->setTime(16, 30));
                    $trips->finish($trip);
                    $balances = app(Inventory::class)->balances($trip->vehicle_location_id);
                    $trips->unload($trip, collect($balances)->map(fn ($q) => ['good' => $q, 'damaged' => 0])->all(), (int) -$trip->containerMovements()->where('type', 'collected')->sum('quantity'));
                    $settle->receiveCash($trip, $settle->cashExpected($trip));
                    $settle->approve($trip->fresh(), null, null);
                }
            }
        }

        Carbon::setTestNow();
    }
}
