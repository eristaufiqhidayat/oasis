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
use App\Services\Pricing;
use App\Services\SalesService;
use App\Services\SettlementService;
use App\Services\StockDocumentService;
use App\Services\TripService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Data demo motoris seluruh Indonesia: 8 wilayah, 16 depo, 40 motoris. Depo Bekasi punya 5 area
 * (Pondok Kopi, Klender, Pulo Gebang, Kranji, Galaxy) dengan 10 motoris; 15 depo lain masing-masing 1 area
 * dengan 2 motoris. Setiap hari kerja (Senin–Sabtu) dari 13 Okt 2026 s.d. besok tiap motoris melakukan
 * 24 kunjungan dengan 20 penjualan, plus absensi, tarik galon kosong, dan jejak GPS.
 *
 * Omzet harian seluruh depo diarahkan ke ±Rp300 jt dengan variasi acak ±10% per hari (jumlah barang per
 * transaksi diskalakan). Hari-hari sebelumnya sudah dibongkar dan direkonsiliasi; hari terakhir berstatus
 * "kembali" (siap dibongkar gudang dan direkonsiliasi kasir saat demo). Bukan untuk server produksi.
 *
 *   php artisan db:seed --class=DemoMotorisSeeder
 *   DEMO_FROM=2026-10-13 DEMO_TO=2026-10-27 DEMO_DAILY=300000000 php artisan db:seed --class=DemoMotorisSeeder
 *
 * Akun motoris: Bekasi bkspk01 … bkspk10, depo lain <kode depo>dm1 / dm2 (mis. sbydm1), password "password".
 * Aman dijalankan ulang: tanggal yang sudah punya muatan dilewati, muatan terbuka di tanggal lain ditutup dulu.
 */
class DemoMotorisSeeder extends Seeder
{
    /** Rentang tanggal (Y-m-d); null = DEMO_FROM / DEMO_TO, bawaan 13 Okt 2026 s.d. besok. */
    public static ?string $from = null;

    public static ?string $to = null;

    /** Batasi ke kode depo tertentu (untuk test); null = semua depo. */
    public static ?array $depots = null;

    /** Omzet harian seluruh depo yang dituju (rupiah); null = DEMO_DAILY atau 300 jt. */
    public static ?int $daily = null;

    public const VISITS_PER_SALESMAN = 24;

    /** Kunjungan tanpa penjualan per motoris per hari → 20 penjualan per motoris per hari. */
    private const NO_SALE_PER_DAY = 4;

    /** Variasi omzet harian: ±10%. */
    private const DAILY_SWING = 0.10;

    /** Wilayah → depo: kode, kota, pusat [lat, lng], area: [kode, nama, pusat, warna, jalan, nama motoris]. */
    private const REGIONS = [
        ['JBR', 'Jawa Barat', [
            ['BKS', 'Bekasi', [-6.2383, 106.9756], [
                ['PK', 'Pondok Kopi', [-6.2260, 106.9410], '#293694', ['Jl. Pondok Kopi Raya', 'Jl. Swadaya Raya', 'Jl. H. Dogol', 'Jl. Malaka Raya', 'Jl. Delima Raya', 'Jl. Kelapa Hijau'], ['Andi Pratama', 'Budi Santoso']],
                ['KL', 'Klender', [-6.2175, 106.9000], '#18a6b1', ['Jl. I Gusti Ngurah Rai', 'Jl. Bekasi Timur Raya', 'Jl. Teratai Putih', 'Jl. Klender Raya', 'Jl. Pahlawan Revolusi', 'Jl. Kayu Manis'], ['Rizky Hidayat', 'Dimas Saputra']],
                ['PG', 'Pulo Gebang', [-6.2050, 106.9560], '#6b7fd7', ['Jl. Pulo Gebang Raya', 'Jl. Sentra Primer Timur', 'Jl. Pulo Gebang Permai', 'Jl. Taman Harapan', 'Jl. Raya Penggilingan', 'Jl. Tipar Cakung'], ['Eko Wahyudi', 'Fajar Nugroho']],
                ['KR', 'Kranji', [-6.2260, 106.9760], '#d9822b', ['Jl. Sultan Agung', 'Jl. Kranji Raya', 'Jl. Bintara Raya', 'Jl. Patriot', 'Jl. Rawa Tembaga', 'Jl. Bintara Jaya'], ['Gilang Ramadhan', 'Hadi Kurniawan']],
                ['GX', 'Galaxy', [-6.2620, 106.9650], '#8fd3d8', ['Jl. Pulo Sirih', 'Jl. Galaxy Raya', 'Jl. Boulevard Raya Galaxy', 'Jl. Jatibening Raya', 'Jl. Pekayon Raya', 'Jl. Kemang Pratama'], ['Irfan Maulana', 'Joko Susilo']],
            ]],
            ['BDG', 'Bandung', [-6.9175, 107.6191], [['K1', 'Bandung Timur', [-6.9300, 107.6600], '#293694', ['Jl. Soekarno-Hatta', 'Jl. Kiaracondong', 'Jl. Antapani', 'Jl. Cicadas'], ['Asep Hermawan', 'Cecep Suryana']]]],
            ['CRB', 'Cirebon', [-6.7063, 108.5570], [['K1', 'Cirebon Kota', [-6.7200, 108.5550], '#18a6b1', ['Jl. Kartini', 'Jl. Tuparev', 'Jl. Pemuda', 'Jl. Kesambi'], ['Ujang Kurnia', 'Dadang Supriatna']]]],
        ]],
        ['JKB', 'DKI Jakarta & Banten', [
            ['TGR', 'Tangerang', [-6.1783, 106.6319], [['K1', 'Tangerang Kota', [-6.1900, 106.6400], '#293694', ['Jl. MH Thamrin', 'Jl. Daan Mogot', 'Jl. Merdeka', 'Jl. Imam Bonjol'], ['Ahmad Fauzi', 'Rahmat Hidayat']]]],
            ['JKU', 'Jakarta Utara', [-6.1384, 106.8660], [['K1', 'Koja & Tanjung Priok', [-6.1250, 106.8900], '#18a6b1', ['Jl. Yos Sudarso', 'Jl. Enggano', 'Jl. Plumpang Semper', 'Jl. Kramat Jaya'], ['Bayu Pamungkas', 'Ferry Setiawan']]]],
        ]],
        ['JTG', 'Jawa Tengah & DIY', [
            ['SMG', 'Semarang', [-6.9667, 110.4167], [['K1', 'Semarang Tengah', [-6.9850, 110.4200], '#293694', ['Jl. Majapahit', 'Jl. Pandanaran', 'Jl. Setiabudi', 'Jl. Kaligawe'], ['Bambang Sutrisno', 'Slamet Riyadi']]]],
            ['YOG', 'Yogyakarta', [-7.7956, 110.3695], [['K1', 'Sleman & Kota', [-7.7800, 110.3800], '#18a6b1', ['Jl. Kaliurang', 'Jl. Magelang', 'Jl. Parangtritis', 'Jl. Godean'], ['Wahyu Nugroho', 'Sigit Prasetyo']]]],
        ]],
        ['JTM', 'Jawa Timur', [
            ['SBY', 'Surabaya', [-7.2575, 112.7521], [['K1', 'Surabaya Timur', [-7.2700, 112.7800], '#293694', ['Jl. Rungkut Industri', 'Jl. Kenjeran', 'Jl. Ahmad Yani', 'Jl. Mulyosari'], ['Arief Wicaksono', 'Teguh Santoso']]]],
            ['MLG', 'Malang', [-7.9666, 112.6326], [['K1', 'Malang Kota', [-7.9800, 112.6400], '#18a6b1', ['Jl. Soekarno Hatta', 'Jl. Sawojajar', 'Jl. Kawi', 'Jl. Sulfat'], ['Yoga Pratama', 'Hendro Susilo']]]],
        ]],
        ['BNT', 'Bali & Nusa Tenggara', [
            ['DPS', 'Denpasar', [-8.6705, 115.2126], [['K1', 'Denpasar & Badung', [-8.6800, 115.2000], '#293694', ['Jl. Teuku Umar', 'Jl. Gatot Subroto', 'Jl. Imam Bonjol', 'Jl. Diponegoro'], ['I Made Suarta', 'I Ketut Widana']]]],
        ]],
        ['SMT', 'Sumatera', [
            ['MDN', 'Medan', [3.5952, 98.6722], [['K1', 'Medan Kota', [3.5800, 98.6800], '#293694', ['Jl. Gatot Subroto', 'Jl. Sisingamangaraja', 'Jl. Setia Budi', 'Jl. Ring Road'], ['Rudi Siregar', 'Hotman Simanjuntak']]]],
            ['PLM', 'Palembang', [-2.9761, 104.7754], [['K1', 'Palembang Ilir', [-2.9650, 104.7500], '#18a6b1', ['Jl. Jenderal Sudirman', 'Jl. Demang Lebar Daun', 'Jl. Kol. H. Barlian', 'Jl. Basuki Rahmat'], ['Firman Syahputra', 'Deni Saputra']]]],
            ['PKU', 'Pekanbaru', [0.5071, 101.4478], [['K1', 'Pekanbaru Kota', [0.4950, 101.4300], '#6b7fd7', ['Jl. HR Soebrantas', 'Jl. Tuanku Tambusai', 'Jl. Arifin Ahmad', 'Jl. Riau'], ['Rizal Effendi', 'Yusuf Harahap']]]],
        ]],
        ['KLM', 'Kalimantan', [
            ['BPN', 'Balikpapan', [-1.2379, 116.8529], [['K1', 'Balikpapan Kota', [-1.2500, 116.8650], '#293694', ['Jl. MT Haryono', 'Jl. Soekarno Hatta', 'Jl. Ruhui Rahayu', 'Jl. Marsma Iswahyudi'], ['Agus Riyanto', 'Darmawan Saleh']]]],
        ]],
        ['SLW', 'Sulawesi', [
            ['MKS', 'Makassar', [-5.1477, 119.4327], [['K1', 'Makassar Kota', [-5.1550, 119.4400], '#293694', ['Jl. Perintis Kemerdekaan', 'Jl. AP Pettarani', 'Jl. Urip Sumoharjo', 'Jl. Veteran'], ['Andi Baso', 'Muh. Ilham']]]],
            ['MDO', 'Manado', [1.4748, 124.8421], [['K1', 'Manado Kota', [1.4650, 124.8400], '#18a6b1', ['Jl. Sam Ratulangi', 'Jl. Piere Tendean', 'Jl. Wolter Monginsidi', 'Jl. Bethesda'], ['Steven Lumentut', 'Jefri Rondonuwu']]]],
        ]],
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

    /** Kelipatan jumlah per SKU (botol per dus). */
    private const STEP = ['OAS-G19' => 1, 'OAS-B600' => 24, 'OAS-B1500' => 12, 'OAS-B330' => 24, 'OAS-C240' => 1];

    public function run(): void
    {
        mt_srand(271026);
        $this->call(DatabaseSeeder::class);
        Auth::login(User::where('username', 'admin')->first());

        $to = Carbon::parse(self::$to ?? (env('DEMO_TO') ?: Carbon::tomorrow()->toDateString()))->startOfDay();
        $from = Carbon::parse(self::$from ?? (env('DEMO_FROM') ?: '2026-10-13'))->startOfDay()->min($to);
        $daily = self::$daily ?? (int) (env('DEMO_DAILY') ?: 300_000_000);
        $this->command?->info("Transaksi demo {$from->translatedFormat('d M Y')} s.d. {$to->translatedFormat('d M Y')} (Senin–Sabtu), ±Rp".number_format($daily / 1e6, 0, ',', '.').' jt/hari');

        $this->retireOldVersion();

        $products = $this->products();
        $channels = OutletChannel::pluck('id', 'code');
        $team = $this->buildTeam($channels);

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if ($day->isSunday()) {
                continue;
            }

            $work = [];
            foreach ($team as $m) {
                $plan = $this->planVisits($m['no'], $m['outlets'], $channels);

                if (Trip::where('employee_id', $m['salesman']->id)->whereDate('date', $day)->exists()) {
                    continue;
                }

                $this->closeOpenTrips($m['salesman'], $day);
                $work[] = $m + ['plan' => $plan];
            }

            Carbon::setTestNow();

            if ($work === []) {
                $this->command?->warn("  {$day->toDateString()}: sudah ada, dilewati.");

                continue;
            }

            // Skalakan jumlah barang supaya omzet hari ini ≈ target ±10% (porsi motoris yang ikut dijalankan).
            $target = $daily * (1 + mt_rand(-1000, 1000) / 1000 * self::DAILY_SWING) * count($work) / self::rosterSize();
            $work = $this->scaleToTarget($work, $products, $day, $target);

            foreach (collect($work)->groupBy(fn ($m) => $m['depot']->id) as $group) {
                $total = [];
                foreach ($group as $m) {
                    foreach ($m['load'] as $sku => $qty) {
                        $total[$products[$sku]->id] = ($total[$products[$sku]->id] ?? 0) + $qty;
                    }
                }
                Carbon::setTestNow($day->copy()->subDay()->setTime(15, 0));
                app(StockDocumentService::class)->post($group[0]['depot'], 'receipt', $day->copy()->subDay(), $total, "DO-PABRIK-{$group[0]['depot']->code}-{$day->format('Ymd')}-".Str::upper(Str::random(4)));
            }

            foreach ($work as $m) {
                $this->simulate($m['salesman'], $m['plan'], $m['load'], $products, $day->copy());
            }

            $this->command?->line("  {$day->toDateString()}: ".count($work).' motoris, target ±Rp'.number_format($target / 1e6, 1, ',', '.').' jt');
        }

        Carbon::setTestNow();
        Auth::logout();
    }

    /** Jumlah motoris di seluruh daftar (target harian dibagi rata per motoris). */
    public static function rosterSize(): int
    {
        return collect(self::REGIONS)->flatMap(fn ($r) => $r[2])->flatMap(fn ($d) => $d[3])->sum(fn ($a) => count($a[5]));
    }

    /** Wilayah, depo, area, koordinator, motoris + akun, outlet, dan rute Senin–Sabtu. */
    private function buildTeam($channels): array
    {
        $roles = Role::pluck('id', 'slug');
        $team = [];
        $no = 0;

        foreach (self::REGIONS as [$regionCode, $regionName, $depots]) {
            $region = Region::firstOrCreate(['code' => $regionCode], ['name' => $regionName]);

            foreach ($depots as [$depotCode, $city, [$dLat, $dLng], $areas]) {
                if (self::$depots !== null && ! in_array($depotCode, self::$depots, true)) {
                    continue;
                }

                $depot = Depot::firstOrCreate(['code' => $depotCode], ['region_id' => $region->id, 'name' => "Depo {$city}", 'city' => $city, 'latitude' => $dLat, 'longitude' => $dLng]);
                $coord = Employee::firstOrCreate(['code' => "{$depotCode}-KO"], ['name' => "Koordinator {$city}", 'position' => Position::Coordinator, 'depot_id' => $depot->id]);
                $local = 0;

                foreach ($areas as [$areaCode, $areaName, $center, $color, $streets, $names]) {
                    $territory = $this->territory($depot, "{$depotCode}-{$areaCode}", $areaName, $center, $color, $areaCode === 'PK');

                    foreach ($names as $half => $name) {
                        $no++;
                        $local++;
                        // Bekasi memakai kode lama BKS-PK01…10 supaya data versi sebelumnya tetap tersambung.
                        [$code, $username] = $depotCode === 'BKS'
                            ? [sprintf('BKS-PK%02d', $local), sprintf('bkspk%02d', $local)]
                            : ["{$depotCode}-DM{$local}", Str::lower($depotCode)."dm{$local}"];

                        $salesman = Employee::firstOrCreate(['code' => $code], [
                            'name' => $name, 'position' => Position::Salesman, 'depot_id' => $depot->id,
                            'supervisor_id' => $coord->id, 'phone' => '0812'.sprintf('%08d', 34000000 + $no), 'joined_on' => '2026-01-05',
                        ]);
                        User::firstOrCreate(['username' => $username], ['name' => $salesman->name, 'password' => 'password', 'role_id' => $roles['sales'], 'employee_id' => $salesman->id]);

                        $prefix = $depotCode === 'BKS' ? "BKS-{$areaCode}" : "{$depotCode}-DM";
                        $outlets = $this->outlets($prefix, $depotCode === 'BKS' ? $half : $local - 1, $center, $streets, $depot, $territory, $channels);

                        foreach (range(1, 6) as $weekday) {
                            $route = SalesRoute::firstOrCreate(['employee_id' => $salesman->id, 'weekday' => $weekday], ['depot_id' => $depot->id, 'name' => '']);
                            $route->update(['name' => "Rute {$salesman->name} – {$areaName}", 'is_active' => true]);
                            $route->outlets()->sync($outlets->mapWithKeys(fn ($o, $k) => [$o->id => ['sequence' => $k + 1]])->all());
                        }

                        $team[] = ['no' => $no, 'salesman' => $salesman, 'outlets' => $outlets, 'depot' => $depot];
                    }
                }
            }
        }

        return $team;
    }

    /**
     * Kalikan jumlah setiap baris dengan faktor yang sama (dibulatkan ke kelipatan dus) supaya total
     * semua penjualan ≈ target, lalu hitung galon kosong dan muatan dari hasilnya.
     */
    private function scaleToTarget(array $work, $products, Carbon $day, float $target): array
    {
        $pricing = app(Pricing::class);
        $ids = $products->map->id;
        $prices = collect($work)->map(fn ($m) => $m['depot']->id)->unique()
            ->mapWithKeys(fn ($depotId) => [$depotId => $pricing->priceList($depotId, $day, $ids->values()->all())]);

        $value = fn (array $work, string $key) => collect($work)->sum(fn ($m) => collect($m['plan'])->sum(
            fn ($v) => collect($v[$key])->sum(fn ($qty, $sku) => $qty * $prices[$m['depot']->id][$ids[$sku]]),
        ));

        foreach ($work as &$m) {
            foreach ($m['plan'] as &$v) {
                $v['base'] = $v['lines'];
            }
            unset($v);
        }
        unset($m);

        // Pembulatan ke kelipatan dus menggeser total; koreksi faktornya beberapa kali.
        $factor = $target / max(1, $value($work, 'base'));
        for ($pass = 0; $pass < 4; $pass++) {
            foreach ($work as &$m) {
                foreach ($m['plan'] as &$v) {
                    foreach ($v['base'] as $sku => $qty) {
                        $step = self::STEP[$sku];
                        $v['lines'][$sku] = max($step, (int) round($qty * $factor / $step) * $step);
                    }
                }
                unset($v);
            }
            unset($m);

            $factor *= $target / max(1, $value($work, 'lines'));
        }

        foreach ($work as &$m) {
            foreach ($m['plan'] as &$v) {
                $v['empty'] = max(0, ($v['lines']['OAS-G19'] ?? 0) - mt_rand(0, 2));
            }
            unset($v);
            $m['load'] = $this->loadFor($m['plan']);
        }
        unset($m);

        return $work;
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

    /** Area kotak di sekitar pusat (Pondok Kopi memakai kotak yang sama dengan versi pertama). */
    private function territory(Depot $depot, string $code, string $name, array $center, string $color, bool $legacyBox): Territory
    {
        $d = $legacyBox ? [0.010, 0.011] : [0.008, 0.008];
        [$lat, $lng] = $center;

        return Territory::firstOrCreate(['depot_id' => $depot->id, 'code' => $code], [
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
    private function outlets(string $prefix, int $half, array $center, array $streets, Depot $depot, Territory $territory, $channels)
    {
        $pool = collect(self::KINDS)->flatMap(fn ($k) => array_fill(0, $k[2], $k))->values();
        [$lat, $lng] = $center;
        $jakartaTimur = $depot->code === 'BKS' && in_array($prefix, ['BKS-PK', 'BKS-KL', 'BKS-PG'], true);

        return collect(range(1, self::VISITS_PER_SALESMAN))->map(function ($k) use ($prefix, $half, $lat, $lng, $streets, $depot, $territory, $channels, $pool, $jakartaTimur) {
            $n = $half * self::VISITS_PER_SALESMAN + $k;
            $kind = $pool[mt_rand(0, $pool->count() - 1)];
            $owner = self::OWNERS[($n * 7 + crc32($prefix)) % count(self::OWNERS)];
            $short = explode(' ', str_replace(['H. ', 'Ustad ', 'Pak ', 'Mbak '], '', $owner))[0];
            $label = $kind[0] === 'Minimarket' ? "Minimarket {$short} Mart" : "{$kind[0]} {$short}";

            return Outlet::firstOrCreate(['code' => sprintf('%s-%04d', $prefix, $n)], [
                'name' => $label, 'owner_name' => $owner, 'phone' => '0813'.sprintf('%08d', 60000000 + crc32($prefix) % 900000 + $n),
                'address' => $streets[intdiv($k - 1, 4) % count($streets)].' No. '.mt_rand(1, 150),
                'district' => str_replace('Area ', '', $territory->name),
                'city' => $jakartaTimur ? 'Jakarta Timur' : $depot->city,
                'latitude' => round($lat - 0.0065 + mt_rand(0, 130) / 10000, 7),
                'longitude' => round($lng + ($half ? 0.0005 : -0.0065) + mt_rand(0, 60) / 10000, 7),
                'depot_id' => $depot->id, 'territory_id' => $territory->id, 'channel_id' => $channels[$kind[1]] ?? null, 'status' => 'active',
            ]);
        })->values();
    }

    /** Rencana 24 kunjungan: jam, hasil, isi penjualan (belum diskalakan), cara bayar. */
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
                'empty' => 0,
            ];

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
        $need = array_fill_keys(array_keys(self::STEP), 0);
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

    /** Produk & harga nasional sama dengan DemoSeeder; galon di Bekasi Rp21.000 (dibuat bila belum ada). */
    private function products()
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

        $region = Region::firstOrCreate(['code' => 'JBR'], ['name' => 'Jawa Barat']);
        $bekasi = Depot::firstOrCreate(['code' => 'BKS'], ['region_id' => $region->id, 'name' => 'Depo Bekasi', 'city' => 'Bekasi', 'latitude' => -6.2383, 'longitude' => 106.9756]);
        if (! ProductPrice::where('product_id', $products['OAS-G19']->id)->where('depot_id', $bekasi->id)->exists()) {
            ProductPrice::put($products['OAS-G19']->id, $bekasi->id, '2026-01-01', 21000);
        }

        return $products;
    }
}
