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
 * 10 motoris, masing-masing 12 outlet di rute hari ini → 120 kunjungan dan tepat 100 transaksi penjualan
 * (10 per motoris), plus absensi, tarik galon kosong, dan jejak GPS. Muatan berakhir berstatus "kembali"
 * (siap dibongkar gudang dan direkonsiliasi kasir).
 * Akun motoris: bkspk01 … bkspk10, password "password". Bukan untuk server produksi.
 *
 *   php artisan db:seed --class=PondokKopiSeeder
 *
 * Aman dijalankan ulang: motoris yang sudah punya muatan hari ini dilewati.
 * Motoris versi lama seeder ini (BKS-PK1…BKS-PK3) dinonaktifkan supaya area berisi tepat 10 motoris.
 */
class PondokKopiSeeder extends Seeder
{
    public const SALESMEN = [
        'Andi Pratama', 'Budi Santoso', 'Rizky Hidayat', 'Dimas Saputra', 'Eko Wahyudi',
        'Fajar Nugroho', 'Gilang Ramadhan', 'Hadi Kurniawan', 'Irfan Maulana', 'Joko Susilo',
    ];

    public const OUTLETS_PER_SALESMAN = 12;

    /** Urutan kunjungan yang tidak menghasilkan penjualan (indeks 0-based) → 10 penjualan per motoris. */
    private const NO_SALE_AT = [3 => ['no_sale', 'stock_full'], 8 => ['closed', null]];

    private const NO_SALE_VARIANTS = [
        ['no_sale', 'stock_full', 'Stok masih banyak, kunjungi lagi minggu depan'],
        ['no_sale', 'owner_absent', 'Pemilik tidak ada di tempat'],
        ['no_sale', 'no_money', 'Masih ada tagihan belum lunas'],
        ['no_sale', 'competitor', 'Sedang pakai merek lain'],
        ['closed', null, 'Toko tutup'],
    ];

    private const STREETS = [
        'Jl. Pondok Kopi Raya', 'Jl. Swadaya Raya', 'Jl. H. Dogol', 'Jl. Malaka Raya', 'Jl. Delima Raya',
        'Jl. Taman Malaka Selatan', 'Jl. Taman Malaka Utara', 'Jl. Kelapa Hijau', 'Jl. Pondok Kopi Blok B', 'Jl. Kayu Tinggi',
    ];

    private const OWNERS = [
        'H. Mahmud', 'Suryati', 'Rudi Hartono', 'Sudarto', 'Liem Hok Sin', 'Ani Rohani', 'Agus Salim', 'Hendra W.',
        'Ustad Hasan', 'Rizali', 'Wawan S.', 'Edi Susanto', 'Imas Masitoh', 'Bambang P.', 'Rizki Amalia', 'Yusuf Efendi',
        'Khodijah', 'Dedi Kurnia', 'Rohman', 'Ahong', 'Siti Aminah', 'Ilham M.', 'Ferry G.', 'Nur Aini',
    ];

    /** Jenis outlet: awalan nama, kode saluran, bobot kemunculan. */
    private const KINDS = [
        ['Warung', 'WRG', 5], ['Toko', 'TKL', 4], ['Kios', 'WRG', 2], ['Minimarket', 'MMT', 1], ['Kantin', 'HRK', 1],
    ];

    public function run(): void
    {
        mt_srand(261026);
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

        $this->retireOldVersion();

        $products = $this->products($depot);
        $channels = OutletChannel::pluck('id', 'code');
        $roles = Role::pluck('id', 'slug');
        $coord = Employee::firstOrCreate(['code' => 'BKS-KO'], ['name' => 'Koordinator Bekasi', 'position' => Position::Coordinator, 'depot_id' => $depot->id]);
        $today = Carbon::today();

        // Siapkan master + rencana semua motoris dulu, supaya stok gudang bisa diterima sekali sesuai kebutuhan.
        $work = [];

        foreach (self::SALESMEN as $i => $name) {
            $no = sprintf('%02d', $i + 1);
            $salesman = Employee::firstOrCreate(['code' => "BKS-PK{$no}"], [
                'name' => $name, 'position' => Position::Salesman, 'depot_id' => $depot->id,
                'supervisor_id' => $coord->id, 'phone' => '0812340000'.$no, 'joined_on' => '2026-01-05',
            ]);
            User::firstOrCreate(['username' => "bkspk{$no}"], ['name' => $salesman->name, 'password' => 'password', 'role_id' => $roles['sales'], 'employee_id' => $salesman->id]);

            $outlets = $this->outlets($i, $depot, $territory, $channels);
            $route = SalesRoute::firstOrCreate(
                ['employee_id' => $salesman->id, 'weekday' => $today->dayOfWeekIso],
                ['depot_id' => $depot->id, 'name' => "Rute {$salesman->name} – Pondok Kopi"],
            );
            $route->outlets()->sync($outlets->mapWithKeys(fn ($o, $k) => [$o->id => ['sequence' => $k + 1]])->all());

            $plan = $this->planVisits($i, $outlets, $channels);

            if (Trip::where('employee_id', $salesman->id)->whereDate('date', $today)->exists()) {
                $this->command?->warn("{$salesman->name} sudah punya muatan hari ini — simulasi dilewati.");

                continue;
            }

            $work[] = [$salesman, $plan, $this->loadFor($plan)];
        }

        if ($work !== []) {
            $total = [];
            foreach ($work as [, , $load]) {
                foreach ($load as $sku => $qty) {
                    $total[$products[$sku]->id] = ($total[$products[$sku]->id] ?? 0) + $qty;
                }
            }
            app(StockDocumentService::class)->post($depot, 'receipt', $today->copy()->subDay(), $total, 'DO-PABRIK-PK-'.$today->format('Ymd').'-'.Str::upper(Str::random(4)));
        }

        foreach ($work as [$salesman, $plan, $load]) {
            $this->simulate($salesman, $plan, $load, $products, $today);
        }

        Carbon::setTestNow();
        Auth::logout();
    }

    /** Versi awal seeder ini memakai 3 motoris BKS-PK1…3; nonaktifkan agar tidak dobel dengan BKS-PK01…03. */
    private function retireOldVersion(): void
    {
        $old = Employee::whereIn('code', ['BKS-PK1', 'BKS-PK2', 'BKS-PK3'])->where('is_active', true)->get();

        foreach ($old as $employee) {
            $employee->update(['is_active' => false]);
            SalesRoute::where('employee_id', $employee->id)->update(['is_active' => false]);
        }

        if ($old->isNotEmpty()) {
            $this->command?->info('Motoris versi lama (BKS-PK1…3) dinonaktifkan; diganti BKS-PK01…10.');
        }
    }

    /** 12 outlet per motoris, mengelompok di satu petak area Pondok Kopi (5 × 2 petak). */
    private function outlets(int $i, Depot $depot, Territory $territory, $channels)
    {
        $cellLat = -6.2340 + intdiv($i, 5) * 0.0090;
        $cellLng = 106.9320 + ($i % 5) * 0.0040;
        $pool = collect(self::KINDS)->flatMap(fn ($k) => array_fill(0, $k[2], $k))->values();

        return collect(range(1, self::OUTLETS_PER_SALESMAN))->map(function ($k) use ($i, $depot, $territory, $channels, $cellLat, $cellLng, $pool) {
            $n = $i * self::OUTLETS_PER_SALESMAN + $k;
            $kind = $pool[mt_rand(0, $pool->count() - 1)];
            $owner = self::OWNERS[($n * 7) % count(self::OWNERS)];
            $street = self::STREETS[($i + intdiv($k - 1, 4)) % count(self::STREETS)];
            $label = $kind[0] === 'Minimarket' ? 'Minimarket Kopi '.$n : $kind[0].' '.explode(' ', str_replace(['H. ', 'Ustad '], '', $owner))[0];

            return Outlet::firstOrCreate(['code' => sprintf('BKS-PK-%04d', $n)], [
                'name' => $label, 'owner_name' => $owner, 'phone' => '0813'.sprintf('%08d', 61000000 + $n),
                'address' => "{$street} No. ".mt_rand(1, 150), 'district' => 'Pondok Kopi, Duren Sawit', 'city' => 'Jakarta Timur',
                'latitude' => round($cellLat + mt_rand(0, 80) / 10000, 7), 'longitude' => round($cellLng + mt_rand(0, 36) / 10000, 7),
                'depot_id' => $depot->id, 'territory_id' => $territory->id, 'channel_id' => $channels[$kind[1]] ?? null, 'status' => 'active',
            ]);
        })->values();
    }

    /** Rencana 12 kunjungan: jam, hasil, isi penjualan, galon kosong, cara bayar. */
    private function planVisits(int $i, $outlets, $channels): array
    {
        $channelCode = $channels->flip();
        $clock = Carbon::createFromTime(8, mt_rand(0, 10), mt_rand(0, 59));

        return $outlets->map(function (Outlet $outlet, $k) use ($i, $channelCode, &$clock) {
            if ($k === 6) {
                // Istirahat siang: minimal 30 menit, paling cepat lanjut 12:30.
                $clock = $clock->copy()->addMinutes(30)->max(Carbon::createFromTime(12, 30 + mt_rand(0, 15), mt_rand(0, 59)));
            }

            $channel = $channelCode[$outlet->channel_id] ?? 'WRG';
            $miss = self::NO_SALE_AT[$k] ?? null;
            $variant = $miss ? self::NO_SALE_VARIANTS[($i + $k) % count(self::NO_SALE_VARIANTS)] : null;
            $minutes = $miss ? mt_rand(4, 10) : ($channel === 'MMT' || $channel === 'TKL' ? mt_rand(18, 32) : mt_rand(9, 16));

            $visit = [
                'outlet' => $outlet,
                'in' => $clock->format('H:i:s'),
                'out' => $clock->copy()->addMinutes($minutes)->addSeconds(mt_rand(0, 59))->format('H:i:s'),
                'drift' => [mt_rand(-12, 12) / 100000, mt_rand(-12, 12) / 100000],
                'lines' => $miss ? [] : $this->lines($channel),
                'outcome' => $variant[0] ?? 'sale',
                'reason' => $variant[1] ?? null,
                'note' => $variant[2] ?? null,
                'pay' => mt_rand(1, 10) <= 7 ? 'cash' : 'transfer',
            ];
            $visit['empty'] = max(0, ($visit['lines']['OAS-G19'] ?? 0) - mt_rand(0, 2));

            $clock = Carbon::createFromTimeString($visit['out'])->addMinutes(mt_rand(6, 14));

            return $visit;
        })->all();
    }

    private function lines(string $channel): array
    {
        $lines = match ($channel) {
            'MMT' => ['OAS-B600' => 24 * mt_rand(1, 3), 'OAS-B1500' => 12 * mt_rand(1, 2), 'OAS-B330' => 24 * mt_rand(0, 1), 'OAS-C240' => mt_rand(1, 3)],
            'TKL' => ['OAS-G19' => mt_rand(3, 8), 'OAS-B600' => 24 * mt_rand(1, 2), 'OAS-B1500' => 12 * mt_rand(0, 1), 'OAS-C240' => mt_rand(0, 2)],
            'HRK' => ['OAS-G19' => mt_rand(1, 3), 'OAS-B330' => 24, 'OAS-C240' => mt_rand(1, 2)],
            default => ['OAS-G19' => mt_rand(1, 3), 'OAS-B600' => 24 * mt_rand(0, 1), 'OAS-B330' => 24 * mt_rand(0, 1)],
        };

        return array_filter($lines);
    }

    /** Muatan = kebutuhan penjualan + cadangan 15%, dibulatkan ke atas per 12 (minimal satu dus untuk semua SKU). */
    private function loadFor(array $plan): array
    {
        $need = array_fill_keys(['OAS-G19', 'OAS-B600', 'OAS-B1500', 'OAS-B330', 'OAS-C240'], 0);
        foreach ($plan as $v) {
            foreach ($v['lines'] as $sku => $qty) {
                $need[$sku] += $qty;
            }
        }

        return array_map(fn ($q) => max(12, (int) (ceil($q * 1.15 / 12) * 12)), $need);
    }

    private function simulate(Employee $salesman, array $plan, array $load, $products, Carbon $today): void
    {
        $trips = app(TripService::class);
        $field = app(FieldService::class);
        $sales = app(SalesService::class);
        $at = fn (string $time) => $today->copy()->setTimeFromTimeString($time);
        $depot = $salesman->depot;

        Carbon::setTestNow($at('07:15:00')->addMinutes(mt_rand(0, 15)));
        $trip = $trips->load($salesman, $today->copy(), collect($load)->mapWithKeys(fn ($q, $sku) => [$products[$sku]->id => $q])->all(), 'Muatan rute Pondok Kopi');

        Carbon::setTestNow($at('07:35:00')->addMinutes(mt_rand(0, 10)));
        $field->checkIn($salesman, ['latitude' => $depot->latitude, 'longitude' => $depot->longitude, 'note' => 'Absen di depo']);
        $trips->depart($trip);

        foreach ($plan as $k => $v) {
            $outlet = $v['outlet'];
            $lat = (float) $outlet->latitude + $v['drift'][0];
            $lng = (float) $outlet->longitude + $v['drift'][1];
            $start = $at($v['in']);
            $end = $at($v['out']);

            Carbon::setTestNow($start);
            $visit = $field->startVisit($salesman, $trip, ['client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'latitude' => $lat, 'longitude' => $lng]);
            $field->recordPings($salesman, $trip, [['recorded_at' => $start->toIso8601String(), 'latitude' => $lat, 'longitude' => $lng, 'accuracy_m' => mt_rand(5, 20), 'battery' => max(20, 96 - $k * 5)]]);

            if ($v['lines']) {
                Carbon::setTestNow($end->copy()->subMinutes(2));
                $sales->recordSale($salesman, $trip, [
                    'client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'visit_uuid' => $visit->client_uuid, 'payment_method' => $v['pay'],
                    'lines' => collect($v['lines'])->map(fn ($qty, $sku) => ['product_id' => $products[$sku]->id, 'quantity' => $qty])->values()->all(),
                ]);

                if ($v['empty'] > 0) {
                    $sales->collectContainers($salesman, $trip, ['client_uuid' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'visit_uuid' => $visit->client_uuid, 'quantity' => $v['empty']]);
                }
            }

            Carbon::setTestNow($end);
            $field->endVisit($visit, array_filter(['outcome' => $v['outcome'], 'no_sale_reason' => $v['reason'], 'note' => $v['note']]));
        }

        // Selesai rute, kembali ke depo: muatan menunggu dibongkar gudang dan direkonsiliasi kasir.
        Carbon::setTestNow($at(end($plan)['out'])->addMinutes(mt_rand(25, 45)));
        $field->recordPings($salesman, $trip, [['recorded_at' => now()->toIso8601String(), 'latitude' => $depot->latitude, 'longitude' => $depot->longitude, 'battery' => 35]]);
        $trips->finish($trip);

        Carbon::setTestNow(now()->addMinutes(15));
        $field->checkOut($salesman, ['latitude' => $depot->latitude, 'longitude' => $depot->longitude]);
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
}
