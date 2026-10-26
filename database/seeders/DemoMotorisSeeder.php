<?php

namespace Database\Seeders;

use App\Enums\Position;
use App\Enums\TripStatus;
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
use App\Services\Inventory;
use App\Services\SalesService;
use App\Services\SettlementService;
use App\Services\StockDocumentService;
use App\Services\TripService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Data demo motoris Depo Bekasi di 5 area (Pondok Kopi, Klender, Pulo Gebang, Kranji, Galaxy), setiap hari kerja
 * (Senin–Sabtu) dari 13 Okt 2026 s.d. besok: 10 motoris (2 per area), masing-masing 24 kunjungan dan tepat
 * 20 penjualan per hari → 200 transaksi per hari, plus absensi, tarik galon kosong, dan jejak GPS.
 * Hari-hari sebelumnya sudah dibongkar dan direkonsiliasi; hari terakhir berstatus "kembali"
 * (siap dibongkar gudang dan direkonsiliasi kasir saat demo). Bukan untuk server produksi.
 *
 *   php artisan db:seed --class=DemoMotorisSeeder
 *   DEMO_FROM=2026-10-13 DEMO_TO=2026-10-27 php artisan db:seed --class=DemoMotorisSeeder
 *
 * Akun motoris: bkspk01 … bkspk10, password "password".
 * Aman dijalankan ulang: tanggal yang sudah punya muatan dilewati, muatan terbuka hari sebelumnya ditutup dulu.
 */
class DemoMotorisSeeder extends Seeder
{
    /** Rentang tanggal (Y-m-d); null = DEMO_FROM / DEMO_TO, bawaan 13 Okt 2026 s.d. besok. */
    public static ?string $from = null;

    public static ?string $to = null;

    public const VISITS_PER_SALESMAN = 24;

    /** Kunjungan tanpa penjualan per motoris per hari → 20 penjualan per motoris per hari. */
    private const NO_SALE_PER_DAY = 4;

    /** Area: kode, nama, pusat [lat, lng], warna, jalan, 2 motoris. */
    private const AREAS = [
        ['PK', 'Pondok Kopi', [-6.2260, 106.9410], '#293694', ['Jl. Pondok Kopi Raya', 'Jl. Swadaya Raya', 'Jl. H. Dogol', 'Jl. Malaka Raya', 'Jl. Delima Raya', 'Jl. Kelapa Hijau'], ['Andi Pratama', 'Budi Santoso']],
        ['KL', 'Klender', [-6.2175, 106.9000], '#18a6b1', ['Jl. I Gusti Ngurah Rai', 'Jl. Bekasi Timur Raya', 'Jl. Teratai Putih', 'Jl. Klender Raya', 'Jl. Pahlawan Revolusi', 'Jl. Kayu Manis'], ['Rizky Hidayat', 'Dimas Saputra']],
        ['PG', 'Pulo Gebang', [-6.2050, 106.9560], '#6b7fd7', ['Jl. Pulo Gebang Raya', 'Jl. Sentra Primer Timur', 'Jl. Pulo Gebang Permai', 'Jl. Taman Harapan', 'Jl. Raya Penggilingan', 'Jl. Tipar Cakung'], ['Eko Wahyudi', 'Fajar Nugroho']],
        ['KR', 'Kranji', [-6.2260, 106.9760], '#d9822b', ['Jl. Sultan Agung', 'Jl. Kranji Raya', 'Jl. Bintara Raya', 'Jl. Patriot', 'Jl. Rawa Tembaga', 'Jl. Bintara Jaya'], ['Gilang Ramadhan', 'Hadi Kurniawan']],
        ['GX', 'Galaxy', [-6.2620, 106.9650], '#8fd3d8', ['Jl. Pulo Sirih', 'Jl. Galaxy Raya', 'Jl. Boulevard Raya Galaxy', 'Jl. Jatibening Raya', 'Jl. Pekayon Raya', 'Jl. Kemang Pratama'], ['Irfan Maulana', 'Joko Susilo']],
    ];

    private const NO_SALE_VARIANTS = [
        ['no_sale', 'stock_full', 'Stok masih banyak, kunjungi lagi minggu depan'],
        ['no_sale', 'owner_absent', 'Pemilik tidak ada di tempat'],
        ['closed', null, 'Toko tutup'],
        ['no_sale', 'no_money', 'Masih ada tagihan belum lunas'],
        ['no_sale', 'competitor', 'Sedang pakai merek lain'],
    ];

    private const OWNERS = [
        'H. Mahmud', 'Suryati', 'Rudi Hartono', 'Sudarto', 'Liem Hok Sin', 'Ani Rohani', 'Agus Salim', 'Hendra W.',
        'Ustad Hasan', 'Rizali', 'Wawan S.', 'Edi Susanto', 'Imas Masitoh', 'Bambang P.', 'Rizki Amalia', 'Yusuf Efendi',
        'Khodijah', 'Dedi Kurnia', 'Rohman', 'Ahong', 'Siti Aminah', 'Ilham M.', 'Ferry G.', 'Nur Aini', 'Sugeng',
        'Tini Rahayu', 'Asep Sunandar', 'Kokom', 'Daeng Rahman', 'Lusi Marlina', 'Pak Oman', 'Mbak Yuni',
    ];

    /** Jenis outlet: awalan nama, kode saluran, bobot kemunculan. */
    private const KINDS = [
        ['Warung', 'WRG', 5], ['Toko', 'TKL', 4], ['Kios', 'WRG', 2], ['Minimarket', 'MMT', 1], ['Kantin', 'HRK', 1],
    ];

    public function run(): void
    {
        mt_srand(271026);
        $this->call(DatabaseSeeder::class);
        Auth::login(User::where('username', 'admin')->first());

        $to = Carbon::parse(self::$to ?? (env('DEMO_TO') ?: Carbon::tomorrow()->toDateString()))->startOfDay();
        $from = Carbon::parse(self::$from ?? (env('DEMO_FROM') ?: '2026-10-13'))->startOfDay()->min($to);
        $this->command?->info("Transaksi demo {$from->translatedFormat('d M Y')} s.d. {$to->translatedFormat('d M Y')} (Senin–Sabtu)");

        $region = Region::firstOrCreate(['code' => 'JBR'], ['name' => 'Jawa Barat']);
        $depot = Depot::firstOrCreate(['code' => 'BKS'], ['region_id' => $region->id, 'name' => 'Depo Bekasi', 'city' => 'Bekasi', 'latitude' => -6.2383, 'longitude' => 106.9756]);

        $this->retireOldVersion();

        $products = $this->products($depot);
        $channels = OutletChannel::pluck('id', 'code');
        $roles = Role::pluck('id', 'slug');
        $coord = Employee::firstOrCreate(['code' => 'BKS-KO'], ['name' => 'Koordinator Bekasi', 'position' => Position::Coordinator, 'depot_id' => $depot->id]);
        $team = [];
        $no = 0;

        foreach (self::AREAS as [$code, $areaName, $center, $color, $streets, $names]) {
            $territory = $this->territory($depot, $code, $areaName, $center, $color);

            foreach ($names as $half => $name) {
                $no++;
                $nn = sprintf('%02d', $no);
                $salesman = Employee::firstOrCreate(['code' => "BKS-PK{$nn}"], [
                    'name' => $name, 'position' => Position::Salesman, 'depot_id' => $depot->id,
                    'supervisor_id' => $coord->id, 'phone' => '0812340000'.$nn, 'joined_on' => '2026-01-05',
                ]);
                User::firstOrCreate(['username' => "bkspk{$nn}"], ['name' => $salesman->name, 'password' => 'password', 'role_id' => $roles['sales'], 'employee_id' => $salesman->id]);

                $outlets = $this->outlets($code, $half, $center, $streets, $depot, $territory, $channels);

                // Rute Senin–Sabtu dengan outlet yang sama.
                foreach (range(1, 6) as $weekday) {
                    $route = SalesRoute::firstOrCreate(['employee_id' => $salesman->id, 'weekday' => $weekday], ['depot_id' => $depot->id, 'name' => '']);
                    $route->update(['name' => "Rute {$salesman->name} – {$areaName}", 'is_active' => true]);
                    $route->outlets()->sync($outlets->mapWithKeys(fn ($o, $k) => [$o->id => ['sequence' => $k + 1]])->all());
                }

                $team[] = [$no, $salesman, $outlets];
            }
        }

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if ($day->isSunday()) {
                continue;
            }

            $work = [];

            foreach ($team as [$no, $salesman, $outlets]) {
                $plan = $this->planVisits($no, $outlets, $channels);

                if (Trip::where('employee_id', $salesman->id)->whereDate('date', $day)->exists()) {
                    continue;
                }

                $this->closeOpenTrips($salesman, $day);
                $work[] = [$salesman, $plan, $this->loadFor($plan)];
            }

            Carbon::setTestNow();

            if ($work === []) {
                $this->command?->warn("{$day->toDateString()}: sudah ada, dilewati.");

                continue;
            }

            $total = [];
            foreach ($work as [, , $load]) {
                foreach ($load as $sku => $qty) {
                    $total[$products[$sku]->id] = ($total[$products[$sku]->id] ?? 0) + $qty;
                }
            }
            Carbon::setTestNow($day->copy()->subDay()->setTime(15, 0));
            app(StockDocumentService::class)->post($depot, 'receipt', $day->copy()->subDay(), $total, 'DO-PABRIK-BKS-'.$day->format('Ymd').'-'.Str::upper(Str::random(4)));

            foreach ($work as [$salesman, $plan, $load]) {
                $this->simulate($salesman, $plan, $load, $products, $day->copy());
            }

            $this->command?->line("  {$day->toDateString()}: ".count($work).' motoris');
        }

        Carbon::setTestNow();
        Auth::logout();
    }

    /** Versi awal seeder (BKS-PK1…3) dinonaktifkan agar tidak dobel dengan BKS-PK01…10. */
    private function retireOldVersion(): void
    {
        $old = Employee::whereIn('code', ['BKS-PK1', 'BKS-PK2', 'BKS-PK3'])->where('is_active', true)->get();

        foreach ($old as $employee) {
            $employee->update(['is_active' => false]);
            SalesRoute::where('employee_id', $employee->id)->update(['is_active' => false]);
        }
    }

    /** Muatan motoris yang masih terbuka di tanggal lain (mis. dari versi seeder sebelumnya): bongkar semua sisa, setor sesuai sistem, setujui. */
    private function closeOpenTrips(Employee $salesman, Carbon $day): void
    {
        $trips = app(TripService::class);
        $settle = app(SettlementService::class);

        $open = Trip::where('employee_id', $salesman->id)->whereDate('date', '!=', $day)
            ->whereNotIn('status', [TripStatus::Closed->value, TripStatus::Cancelled->value])->get();

        foreach ($open as $trip) {
            Carbon::setTestNow(Carbon::parse($trip->date)->setTime(17, 0));

            if ($trip->status === TripStatus::Loaded) {
                $trips->cancel($trip);

                continue;
            }

            $trips->finish($trip);

            if ($trip->fresh()->status === TripStatus::Returned) {
                $balances = app(Inventory::class)->balances($trip->vehicle_location_id);
                $trips->unload($trip, collect($balances)->map(fn ($q) => ['good' => $q, 'damaged' => 0])->all(), (int) -$trip->containerMovements()->where('type', 'collected')->sum('quantity'));
            }

            $trip = $trip->fresh();
            $settle->receiveCash($trip, $settle->cashExpected($trip));
            $settle->approve($trip->fresh(), null, 'Ditutup otomatis oleh seeder demo');
        }
    }

    private function territory(Depot $depot, string $code, string $name, array $center, string $color): Territory
    {
        // Pondok Kopi memakai kotak yang sama dengan versi sebelumnya; area lain kotak ±0,008°.
        $d = $code === 'PK' ? [0.010, 0.011] : [0.008, 0.008];
        [$lat, $lng] = $center;

        return Territory::firstOrCreate(['depot_id' => $depot->id, 'code' => "BKS-{$code}"], [
            'name' => "Area {$name}", 'color' => $color, 'boundary' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$lng - $d[1], $lat - $d[0]], [$lng + $d[1], $lat - $d[0]], [$lng + $d[1], $lat + $d[0]],
                    [$lng - $d[1], $lat + $d[0]], [$lng - $d[1], $lat - $d[0]],
                ]],
            ],
        ]);
    }

    /** 24 outlet per motoris; dua motoris satu area berbagi area (kiri/kanan pusat). */
    private function outlets(string $code, int $half, array $center, array $streets, Depot $depot, Territory $territory, $channels)
    {
        $pool = collect(self::KINDS)->flatMap(fn ($k) => array_fill(0, $k[2], $k))->values();
        [$lat, $lng] = $center;

        return collect(range(1, self::VISITS_PER_SALESMAN))->map(function ($k) use ($code, $half, $lat, $lng, $streets, $depot, $territory, $channels, $pool) {
            $n = $half * self::VISITS_PER_SALESMAN + $k;
            $kind = $pool[mt_rand(0, $pool->count() - 1)];
            $owner = self::OWNERS[($n * 7 + strlen($code)) % count(self::OWNERS)];
            $short = explode(' ', str_replace(['H. ', 'Ustad ', 'Pak ', 'Mbak '], '', $owner))[0];
            $label = $kind[0] === 'Minimarket' ? "Minimarket {$short} Mart" : "{$kind[0]} {$short}";

            return Outlet::firstOrCreate(['code' => sprintf('BKS-%s-%04d', $code, $n)], [
                'name' => $label, 'owner_name' => $owner, 'phone' => '0813'.sprintf('%08d', 60000000 + crc32($code) % 900000 + $n),
                'address' => $streets[intdiv($k - 1, 4) % count($streets)].' No. '.mt_rand(1, 150), 'district' => str_replace('Area ', '', $territory->name), 'city' => $code === 'KR' || $code === 'GX' ? 'Bekasi' : 'Jakarta Timur',
                'latitude' => round($lat - 0.0065 + mt_rand(0, 130) / 10000, 7),
                'longitude' => round($lng + ($half ? 0.0005 : -0.0065) + mt_rand(0, 60) / 10000, 7),
                'depot_id' => $depot->id, 'territory_id' => $territory->id, 'channel_id' => $channels[$kind[1]] ?? null, 'status' => 'active',
            ]);
        })->values();
    }

    /** Rencana 24 kunjungan: jam, hasil, isi penjualan, galon kosong, cara bayar. */
    private function planVisits(int $no, $outlets, $channels): array
    {
        $channelCode = $channels->flip();
        $clock = Carbon::createFromTime(7, 50 + mt_rand(0, 9), mt_rand(0, 59));
        $misses = collect(range(1, self::VISITS_PER_SALESMAN - 1))->shuffle(mt_rand())->take(self::NO_SALE_PER_DAY)->all();

        return $outlets->map(function (Outlet $outlet, $k) use ($no, $channelCode, $misses, &$clock) {
            if ($k === 12) {
                // Istirahat siang minimal 30 menit, paling cepat lanjut 12:15.
                $clock = $clock->copy()->addMinutes(30)->max(Carbon::createFromTime(12, 15 + mt_rand(0, 10), mt_rand(0, 59)));
            }

            $channel = $channelCode[$outlet->channel_id] ?? 'WRG';
            $miss = in_array($k, $misses, true);
            $variant = $miss ? self::NO_SALE_VARIANTS[($no + $k) % count(self::NO_SALE_VARIANTS)] : null;
            $minutes = $miss ? mt_rand(3, 7) : (in_array($channel, ['MMT', 'TKL'], true) ? mt_rand(12, 20) : mt_rand(6, 11));

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

            $clock = Carbon::createFromTimeString($visit['out'])->addMinutes(mt_rand(3, 8));

            return $visit;
        })->all();
    }

    private function lines(string $channel): array
    {
        $lines = match ($channel) {
            'MMT' => ['OAS-B600' => 24 * mt_rand(2, 4), 'OAS-B1500' => 12 * mt_rand(1, 2), 'OAS-B330' => 24 * mt_rand(0, 1), 'OAS-C240' => mt_rand(1, 3)],
            'TKL' => ['OAS-G19' => mt_rand(4, 9), 'OAS-B600' => 24 * mt_rand(1, 3), 'OAS-B1500' => 12 * mt_rand(0, 1), 'OAS-C240' => mt_rand(0, 2)],
            'HRK' => ['OAS-G19' => mt_rand(2, 4), 'OAS-B330' => 24, 'OAS-C240' => mt_rand(1, 2)],
            default => ['OAS-G19' => mt_rand(1, 4), 'OAS-B600' => 24 * mt_rand(0, 1), 'OAS-B330' => 24 * mt_rand(0, 1)],
        };

        return array_filter($lines);
    }

    /** Muatan = kebutuhan penjualan + cadangan 15%, dibulatkan ke atas per 12 (minimal 12 per SKU). */
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

    private function simulate(Employee $salesman, array $plan, array $load, $products, Carbon $day): void
    {
        $trips = app(TripService::class);
        $field = app(FieldService::class);
        $sales = app(SalesService::class);
        $at = fn (string $time) => $day->copy()->setTimeFromTimeString($time);
        $depot = $salesman->depot;

        Carbon::setTestNow($at('07:00:00')->addMinutes(mt_rand(0, 15)));
        $trip = $trips->load($salesman, $day->copy(), collect($load)->mapWithKeys(fn ($q, $sku) => [$products[$sku]->id => $q])->all(), 'Muatan rute harian');

        Carbon::setTestNow($at('07:20:00')->addMinutes(mt_rand(0, 10)));
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
            $field->recordPings($salesman, $trip, [['recorded_at' => $start->toIso8601String(), 'latitude' => $lat, 'longitude' => $lng, 'accuracy_m' => mt_rand(5, 20), 'battery' => max(15, 97 - $k * 3)]]);

            if ($v['lines']) {
                Carbon::setTestNow($end->copy()->subMinutes(1));
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
        $field->recordPings($salesman, $trip, [['recorded_at' => now()->toIso8601String(), 'latitude' => $depot->latitude, 'longitude' => $depot->longitude, 'battery' => 30]]);
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
