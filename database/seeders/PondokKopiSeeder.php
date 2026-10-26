<?php

namespace Database\Seeders;

use App\Enums\Position;
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
use App\Models\Trip;
use App\Models\User;
use App\Services\FieldService;
use App\Services\SalesService;
use App\Services\StockDocumentService;
use App\Services\TripService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Data contoh check-in hari ini untuk area Pondok Kopi (Duren Sawit, Jakarta Timur) di Depo Bekasi:
 * 3 motoris, 24 outlet (8 per motoris) dengan rute hari ini, absensi, kunjungan, penjualan,
 * galon kosong ditarik, dan jejak GPS. Muatan berakhir berstatus "kembali" (siap dibongkar gudang).
 * Akun motoris: bkspk1 / bkspk2 / bkspk3, password "password". Bukan untuk server produksi.
 *
 *   php artisan db:seed --class=PondokKopiSeeder
 *
 * Aman dijalankan ulang: bila motoris Pondok Kopi sudah punya muatan hari ini, simulasi dilewati.
 */
class PondokKopiSeeder extends Seeder
{
    /** Muatan pagi per motoris (satuan dasar), dikunci per SKU. */
    private const LOAD = ['OAS-G19' => 40, 'OAS-B600' => 288, 'OAS-B1500' => 120, 'OAS-B330' => 120, 'OAS-C240' => 15];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        Auth::login(User::where('username', 'admin')->first());

        $region = Region::firstOrCreate(['code' => 'JBR'], ['name' => 'Jawa Barat']);
        $depot = Depot::firstOrCreate(['code' => 'BKS'], ['region_id' => $region->id, 'name' => 'Depo Bekasi', 'city' => 'Bekasi', 'latitude' => -6.2383, 'longitude' => 106.9756]);

        $territory = Territory::firstOrCreate(['depot_id' => $depot->id, 'code' => 'BKS-PK'], [
            'name' => 'Area Pondok Kopi', 'color' => '#293694', 'boundary' => [
                'type' => 'Polygon',
                'coordinates' => [[[106.930, -6.236], [106.952, -6.236], [106.952, -6.216], [106.930, -6.216], [106.930, -6.236]]],
            ],
        ]);

        $products = $this->products($depot);
        $channels = OutletChannel::pluck('id', 'code');
        $roles = Role::pluck('id', 'slug');

        $coord = Employee::firstOrCreate(['code' => 'BKS-KO'], ['name' => 'Koordinator Bekasi', 'position' => Position::Coordinator, 'depot_id' => $depot->id]);

        $today = Carbon::today();
        $trips = app(TripService::class);
        $field = app(FieldService::class);
        $sales = app(SalesService::class);
        $stockAdded = false;

        foreach ($this->plan() as $i => $plan) {
            $no = $i + 1;
            $salesman = Employee::firstOrCreate(['code' => "BKS-PK{$no}"], [
                'name' => $plan['name'], 'position' => Position::Salesman, 'depot_id' => $depot->id,
                'supervisor_id' => $coord->id, 'phone' => $plan['phone'], 'joined_on' => '2026-01-05',
            ]);
            User::firstOrCreate(['username' => "bkspk{$no}"], ['name' => $salesman->name, 'password' => 'password', 'role_id' => $roles['sales'], 'employee_id' => $salesman->id]);

            $outlets = collect($plan['visits'])->map(fn ($v, $k) => Outlet::firstOrCreate(
                ['code' => sprintf('BKS-PK-%04d', $i * 8 + $k + 1)],
                [
                    'name' => $v['outlet'], 'owner_name' => $v['owner'], 'phone' => '0812'.sprintf('%08d', 55100000 + $i * 100 + $k),
                    'address' => $v['address'], 'district' => 'Pondok Kopi, Duren Sawit', 'city' => 'Jakarta Timur',
                    'latitude' => $v['lat'], 'longitude' => $v['lng'], 'depot_id' => $depot->id, 'territory_id' => $territory->id,
                    'channel_id' => $channels[$v['channel']] ?? null, 'status' => 'active',
                ],
            ));

            $route = SalesRoute::firstOrCreate(
                ['employee_id' => $salesman->id, 'weekday' => $today->dayOfWeekIso],
                ['depot_id' => $depot->id, 'name' => "Rute {$salesman->name} – Pondok Kopi"],
            );
            $route->outlets()->sync($outlets->mapWithKeys(fn ($o, $k) => [$o->id => ['sequence' => $k + 1]])->all());

            if (Trip::where('employee_id', $salesman->id)->whereDate('date', $today)->exists()) {
                $this->command?->warn("{$salesman->name} sudah punya muatan hari ini — simulasi dilewati.");

                continue;
            }

            if (! $stockAdded) {
                // Stok gudang cukup untuk tiga muatan pagi ini.
                app(StockDocumentService::class)->post($depot, 'receipt', $today->copy()->subDay(), $products->mapWithKeys(fn ($p, $sku) => [$p->id => self::LOAD[$sku] * 3])->all(), 'DO-PABRIK-PK-'.$today->format('Ymd'));
                $stockAdded = true;
            }

            $this->simulate($salesman, $outlets, $plan['visits'], $products, $today, $trips, $field, $sales);
        }

        Carbon::setTestNow();
        Auth::logout();
    }

    private function simulate(Employee $salesman, $outlets, array $visits, $products, Carbon $today, TripService $trips, FieldService $field, SalesService $sales): void
    {
        $at = fn (string $time) => $today->copy()->setTimeFromTimeString($time);

        Carbon::setTestNow($at('07:20:00'));
        $trip = $trips->load($salesman, $today->copy(), $products->mapWithKeys(fn ($p, $sku) => [$p->id => self::LOAD[$sku]])->all(), 'Muatan rute Pondok Kopi');

        Carbon::setTestNow($at('07:40:00'));
        $field->checkIn($salesman, ['latitude' => -6.2383, 'longitude' => 106.9756, 'note' => 'Absen di depo']);
        $trips->depart($trip);

        foreach ($visits as $k => $v) {
            $outlet = $outlets[$k];
            $start = $at($v['in']);
            $end = $at($v['out']);

            Carbon::setTestNow($start);
            $visit = $field->startVisit($salesman, $trip, [
                'client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id,
                'latitude' => $v['lat'] + $v['drift'][0], 'longitude' => $v['lng'] + $v['drift'][1],
            ]);
            $field->recordPings($salesman, $trip, [[
                'recorded_at' => $start->toIso8601String(), 'latitude' => $v['lat'] + $v['drift'][0],
                'longitude' => $v['lng'] + $v['drift'][1], 'accuracy_m' => 8 + $k, 'battery' => 95 - $k * 4,
            ]]);

            if ($v['lines']) {
                Carbon::setTestNow($end->copy()->subMinutes(3));
                $lines = collect($v['lines'])->map(fn ($qty, $sku) => ['product_id' => $products[$sku]->id, 'quantity' => $qty])->values()->all();
                $sales->recordSale($salesman, $trip, [
                    'client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'visit_uuid' => $visit->client_uuid,
                    'payment_method' => $v['pay'] ?? 'cash', 'lines' => $lines,
                ]);

                if (($v['empty'] ?? 0) > 0) {
                    $sales->collectContainers($salesman, $trip, ['client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'visit_uuid' => $visit->client_uuid, 'quantity' => $v['empty']]);
                }
            }

            Carbon::setTestNow($end);
            $field->endVisit($visit, array_filter([
                'outcome' => $v['lines'] ? 'sale' : $v['outcome'],
                'no_sale_reason' => $v['reason'] ?? null,
                'note' => $v['note'] ?? null,
            ]));
        }

        // Selesai rute, kembali ke depo: muatan menunggu dibongkar gudang dan direkonsiliasi kasir.
        Carbon::setTestNow($at('14:25:00'));
        $field->recordPings($salesman, $trip, [['recorded_at' => now()->toIso8601String(), 'latitude' => -6.2383, 'longitude' => 106.9756, 'battery' => 60]]);
        $trips->finish($trip);

        Carbon::setTestNow($at('14:40:00'));
        $field->checkOut($salesman, ['latitude' => -6.2383, 'longitude' => 106.9756]);
    }

    /** Produk & harga sama dengan DemoSeeder (dibuat bila belum ada). */
    private function products(Depot $depot)
    {
        $products = collect([
            ['OAS-G19', 'Oasis Galon 19 L', 'Galon', 'galon', 1, true, 20000],
            ['OAS-B600', 'Oasis Botol 600 ml', 'Botol', 'botol', 24, false, 3000],
            ['OAS-B1500', 'Oasis Botol 1500 ml', 'Botol', 'botol', 12, false, 5500],
            ['OAS-B330', 'Oasis Botol 330 ml', 'Botol', 'botol', 24, false, 2200],
            ['OAS-C240', 'Oasis Cup 240 ml', 'Gelas (cup)', 'dus', 1, false, 22000],
        ])->mapWithKeys(function ($p) {
            $product = Product::firstOrCreate(['sku' => $p[0]], ['name' => $p[1], 'category_id' => ProductCategory::where('name', $p[2])->value('id'), 'unit' => $p[3], 'units_per_case' => $p[4], 'tracks_container' => $p[5]]);

            if (! ProductPrice::where('product_id', $product->id)->whereNull('depot_id')->exists()) {
                ProductPrice::put($product->id, null, '2026-01-01', $p[6]);
            }

            return [$p[0] => $product];
        });

        if (! ProductPrice::where('product_id', $products['OAS-G19']->id)->where('depot_id', $depot->id)->exists()) {
            ProductPrice::put($products['OAS-G19']->id, $depot->id, '2026-01-01', 21000);
        }

        return $products;
    }

    /**
     * Rencana kunjungan hari ini. lines = SKU => qty (kosong = tidak ada penjualan),
     * empty = galon kosong ditarik, drift = selisih posisi HP dari titik outlet.
     */
    private function plan(): array
    {
        $v = fn ($outlet, $owner, $address, $channel, $lat, $lng, $in, $out, $lines, $extra = []) => [
            'outlet' => $outlet, 'owner' => $owner, 'address' => $address, 'channel' => $channel,
            'lat' => $lat, 'lng' => $lng, 'in' => $in, 'out' => $out, 'lines' => $lines,
            'drift' => [0.00012, 0.00009],
        ] + $extra;

        return [
            ['name' => 'Andi Pratama', 'phone' => '081234560001', 'visits' => [
                $v('Toko Sumber Rejeki', 'H. Mahmud', 'Jl. Pondok Kopi Raya No. 12', 'TKL', -6.2218450, 106.9402310, '08:05:12', '08:32:40', ['OAS-G19' => 10, 'OAS-B600' => 48, 'OAS-B1500' => 24, 'OAS-C240' => 3], ['empty' => 8, 'note' => 'Order rutin mingguan']),
                $v('Warung Bu Yati', 'Suryati', 'Jl. Pondok Kopi Raya No. 45', 'WRG', -6.2225110, 106.9411870, '08:45:03', '08:58:21', ['OAS-G19' => 2, 'OAS-B600' => 24], ['empty' => 2, 'note' => 'Tambah stok minuman']),
                $v('Toko Berkah Jaya', 'Rudi Hartono', 'Jl. Swadaya Raya No. 8', 'TKL', -6.2231740, 106.9398520, '09:12:47', '09:40:15', ['OAS-G19' => 5, 'OAS-B600' => 48, 'OAS-B330' => 24], ['empty' => 4, 'pay' => 'transfer', 'note' => 'Ambil promo bundling']),
                $v('Kios Pak Darto', 'Sudarto', 'Jl. Swadaya Gg. Melati No. 3', 'WRG', -6.2238060, 106.9389940, '09:55:30', '10:04:02', [], ['outcome' => 'no_sale', 'reason' => 'stock_full']),
                $v('Toko Makmur Abadi', 'Liem Hok Sin', 'Jl. Swadaya Raya No. 27', 'TKL', -6.2244380, 106.9395170, '10:20:18', '10:58:44', ['OAS-G19' => 15, 'OAS-B600' => 72, 'OAS-B1500' => 36, 'OAS-C240' => 5], ['empty' => 12, 'pay' => 'transfer', 'note' => 'Order besar, sisa permintaan kirim besok']),
                $v('Warung Mpok Ani', 'Ani Rohani', 'Jl. H. Dogol No. 15', 'WRG', -6.2251920, 106.9407630, '11:15:09', '11:27:33', ['OAS-G19' => 2, 'OAS-B330' => 24], ['empty' => 2]),
                $v('Toko Sinar Mulya', 'Agus Salim', 'Jl. H. Dogol No. 40', 'TKL', -6.2257330, 106.9416080, '13:05:41', '13:09:10', [], ['outcome' => 'closed', 'note' => 'Pemilik sedang pulang kampung']),
                $v('Minimarket Kopi Mart', 'Hendra W.', 'Jl. Pondok Kopi Raya No. 88', 'MMT', -6.2263570, 106.9424410, '13:25:56', '13:52:19', ['OAS-B600' => 48, 'OAS-B1500' => 24, 'OAS-B330' => 24, 'OAS-C240' => 2], ['pay' => 'transfer', 'note' => 'Pasang display baru']),
            ]],
            ['name' => 'Budi Santoso', 'phone' => '081234560002', 'visits' => [
                $v('Toko Barokah', 'Ustad Hasan', 'Jl. Malaka Raya No. 5', 'TKL', -6.2198210, 106.9358460, '08:10:25', '08:35:50', ['OAS-G19' => 6, 'OAS-B600' => 48, 'OAS-B1500' => 12], ['empty' => 5, 'note' => 'Order rutin']),
                $v('Warung Bang Jali', 'Rizali', 'Jl. Malaka Raya No. 19', 'WRG', -6.2204650, 106.9365790, '08:48:14', '08:59:37', ['OAS-G19' => 2, 'OAS-B600' => 24], ['empty' => 2]),
                $v('Toko Cahaya Baru', 'Wawan S.', 'Jl. Malaka Gg. Mawar No. 7', 'TKL', -6.2210870, 106.9372130, '09:14:02', '09:22:48', [], ['outcome' => 'no_sale', 'reason' => 'no_money', 'note' => 'Masih ada tagihan belum lunas']),
                $v('Toko Maju Lancar', 'Edi Susanto', 'Jl. Delima Raya No. 22', 'TKL', -6.2216990, 106.9379660, '09:38:33', '10:15:06', ['OAS-G19' => 14, 'OAS-B600' => 72, 'OAS-B1500' => 36, 'OAS-C240' => 4], ['empty' => 12, 'pay' => 'transfer', 'note' => 'Order + tagih faktur minggu lalu']),
                $v('Warung Teh Imas', 'Imas Masitoh', 'Jl. Delima III No. 4', 'WRG', -6.2223420, 106.9371280, '10:30:47', '10:41:19', ['OAS-G19' => 1, 'OAS-B330' => 24], ['empty' => 1]),
                $v('Toko Sejahtera', 'Bambang P.', 'Jl. Delima Raya No. 51', 'TKL', -6.2229780, 106.9363840, '10:58:21', '11:30:55', ['OAS-G19' => 8, 'OAS-B600' => 48, 'OAS-B1500' => 24, 'OAS-C240' => 2], ['empty' => 6, 'note' => 'Minta harga khusus, perlu approval']),
                $v('Kios Rizki', 'Rizki Amalia', 'Jl. Pondok Kopi Blok A No. 10', 'WRG', -6.2236140, 106.9356210, '13:10:08', '13:21:42', ['OAS-G19' => 2, 'OAS-B600' => 24], ['empty' => 2]),
                $v('Toko Anugerah', 'Yusuf Efendi', 'Jl. Pondok Kopi Blok B No. 2', 'TKL', -6.2242510, 106.9349870, '13:36:55', '13:45:30', [], ['outcome' => 'no_sale', 'reason' => 'owner_absent']),
            ]],
            ['name' => 'Rizky Hidayat', 'phone' => '081234560003', 'visits' => [
                $v('Toko Harapan Kita', 'Joko S.', 'Jl. Pondok Kopi Raya No. 120', 'TKL', -6.2270120, 106.9433560, '08:02:39', '08:40:11', ['OAS-G19' => 12, 'OAS-B600' => 48, 'OAS-B1500' => 24, 'OAS-B330' => 24, 'OAS-C240' => 3], ['empty' => 10, 'note' => 'Order rutin + produk baru']),
                $v('Warung Mak Ijah', 'Khodijah', 'Jl. Pondok Kopi Gg. Kenanga No. 9', 'WRG', -6.2276540, 106.9441020, '08:55:26', '09:06:48', ['OAS-G19' => 2, 'OAS-B600' => 24], ['empty' => 2]),
                $v('Toko Mitra Usaha', 'Dedi Kurnia', 'Jl. Taman Malaka Selatan No. 14', 'TKL', -6.2282870, 106.9448390, '09:21:13', '09:49:37', ['OAS-G19' => 6, 'OAS-B600' => 48], ['empty' => 5]),
                $v('Kios Pak Oman', 'Rohman', 'Jl. Taman Malaka Selatan No. 31', 'WRG', -6.2289310, 106.9455740, '10:03:50', '10:07:22', [], ['outcome' => 'closed', 'note' => 'Toko tutup, renovasi']),
                $v('Toko Sentosa', 'Ahong', 'Jl. Kelapa Hijau No. 6', 'TKL', -6.2295660, 106.9462180, '10:22:04', '11:01:39', ['OAS-G19' => 15, 'OAS-B600' => 72, 'OAS-B1500' => 36, 'OAS-C240' => 5], ['empty' => 13, 'pay' => 'transfer', 'note' => 'Order besar, bayar transfer']),
                $v('Warung Bu Siti', 'Siti Aminah', 'Jl. Kelapa Hijau No. 18', 'WRG', -6.2290120, 106.9470550, '11:18:27', '11:29:05', ['OAS-G19' => 2, 'OAS-B330' => 24], ['empty' => 2]),
                $v('Toko Rezeki Lancar', 'Ilham M.', 'Jl. Kelapa Hijau No. 33', 'TKL', -6.2283780, 106.9477010, '13:02:16', '13:11:44', [], ['outcome' => 'no_sale', 'reason' => 'stock_full', 'note' => 'Kunjungi lagi minggu depan']),
                $v('Minimarket Malaka 24', 'Ferry G.', 'Jl. Taman Malaka Utara No. 2', 'MMT', -6.2277450, 106.9483460, '13:27:48', '13:58:12', ['OAS-B600' => 48, 'OAS-B1500' => 24, 'OAS-B330' => 24, 'OAS-C240' => 2], ['pay' => 'transfer', 'note' => 'Cek planogram & order']),
            ]],
        ];
    }
}
