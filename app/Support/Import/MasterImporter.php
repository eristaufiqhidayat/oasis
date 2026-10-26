<?php

namespace App\Support\Import;

use App\Enums\OutletStatus;
use App\Enums\Position;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\Territory;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Memetakan data master aplikasi lama ke struktur Oasis. Idempoten: baris dicocokkan lewat legacy_id
 * (atau username untuk akun), jadi impor bisa diulang untuk menarik data terbaru tanpa duplikat.
 *
 * Tabel sumber: mk_depo, master_depo (opsional: alamat & koordinat), mk_area, area_polygon (opsional: batas area),
 * mk_karyawan, mk_outlet, mk_produk, users.
 */
class MasterImporter
{
    /** @var array<string, array<string, int>> hitungan per tahap */
    public array $stats = [];

    private array $depots = [];

    private array $territoryDepot = [];

    private array $territories = [];

    private array $employees = [];

    public function __construct(private LegacySource $source, private ?Closure $progress = null)
    {
        $this->source->logger = fn (string $m) => $this->say($m);
    }

    public function run(array $only = []): array
    {
        $steps = [
            'depo' => fn () => $this->depots(),
            'area' => fn () => $this->territories(),
            'karyawan' => fn () => $this->employees(),
            'outlet' => fn () => $this->outlets(),
            'produk' => fn () => $this->products(),
            'akun' => fn () => $this->users(),
        ];

        foreach ($steps as $name => $step) {
            // Peta legacy_id → id selalu dimuat ulang supaya tahap lanjutan bisa jalan sendiri (--only).
            $this->loadMaps();

            if ($only === [] || in_array($name, $only, true)) {
                $this->stats[$name] = ['baru' => 0, 'diperbarui' => 0, 'dilewati' => 0];
                $this->say("Mengimpor {$name}…");
                DB::transaction($step);
            }
        }

        return $this->stats;
    }

    private function depots(): void
    {
        $extra = [];
        foreach ($this->source->rows('master_depo', ['kode', 'nama', 'alamat', 'kota_kab', 'latitude', 'longitude']) as $row) {
            $extra[Str::lower(trim((string) ($row['kode'] ?? '')))] = $row;
            $extra[Str::lower(trim((string) ($row['nama'] ?? '')))] = $row;
        }

        foreach ($this->source->rows('mk_depo', ['id', 'kode', 'nama', 'aktif']) as $row) {
            $info = $extra[Str::lower(trim((string) $row['kode']))] ?? $extra[Str::lower(trim((string) $row['nama']))] ?? [];
            $code = $this->uniqueCode('depots', 'code', $row['kode'] ?: 'D'.$row['id'], 'legacy_id', $row['id']);

            $this->upsert(Depot::class, 'depo', (int) $row['id'], [
                'code' => $code,
                'name' => trim($row['nama']),
                'address' => $info['alamat'] ?? null,
                'city' => $info['kota_kab'] ?? null,
                'latitude' => $this->coordinate($info['latitude'] ?? null, 90),
                'longitude' => $this->coordinate($info['longitude'] ?? null, 180),
                'is_active' => $row['aktif'] === null ? true : (bool) $row['aktif'],
            ]);
        }
    }

    private function territories(): void
    {
        $boundaries = [];
        foreach ($this->source->rows('area_polygon', ['id', 'source_id', 'code', 'polygon']) as $row) {
            $geo = $this->geometry($row['polygon'] ?? null);
            if ($geo) {
                $boundaries['id:'.($row['source_id'] ?? '')] = $geo;
                $boundaries['code:'.Str::lower((string) $row['code'])] = $geo;
            }
        }

        foreach ($this->source->rows('mk_area', ['id', 'kode', 'nama', 'depo_id']) as $row) {
            $depot = $this->depots[(int) $row['depo_id']] ?? null;

            if (! $depot) {
                $this->stats['area']['dilewati']++;

                continue;
            }

            $boundary = $boundaries['id:'.$row['id']] ?? $boundaries['code:'.Str::lower((string) $row['kode'])] ?? null;

            $this->upsert(Territory::class, 'area', (int) $row['id'], array_filter([
                'depot_id' => $depot,
                'code' => $row['kode'] ? Str::limit((string) $row['kode'], 30, '') : null,
                'name' => trim($row['nama']),
                'boundary' => $boundary,
            ], fn ($v) => $v !== null) + ['name' => trim($row['nama'])]);
        }
    }

    private function employees(): void
    {
        $positions = ['sales' => Position::Salesman, 'koordinator' => Position::Coordinator, 'supervisor' => Position::Coordinator, 'lainnya' => Position::Admin];
        $supervisors = [];

        foreach ($this->source->rows('mk_karyawan', ['id', 'kategori', 'nama', 'depo_id', 'koordinator_id', 'no_hp', 'tanggal_mulai', 'aktif']) as $row) {
            $position = $positions[$row['kategori']] ?? Position::Admin;
            $joined = $this->date($row['tanggal_mulai'] ?? null);

            $id = $this->upsert(Employee::class, 'karyawan', (int) $row['id'], [
                'code' => $this->uniqueCode('employees', 'code', 'MK'.$row['id'], 'legacy_id', $row['id']),
                'name' => trim($row['nama']),
                'position' => $position,
                'depot_id' => $this->depots[(int) $row['depo_id']] ?? null,
                'phone' => $row['no_hp'] ? Str::limit((string) $row['no_hp'], 30, '') : null,
                'joined_on' => $joined,
                'is_active' => $row['aktif'] === null ? true : (bool) $row['aktif'],
            ]);

            if ($row['koordinator_id']) {
                $supervisors[$id] = (int) $row['koordinator_id'];
            }
        }

        // Atasan diisi setelah semua karyawan ada.
        $map = Employee::whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        foreach ($supervisors as $employeeId => $legacySupervisor) {
            if (isset($map[$legacySupervisor]) && $map[$legacySupervisor] !== $employeeId) {
                Employee::whereKey($employeeId)->update(['supervisor_id' => $map[$legacySupervisor]]);
            }
        }
    }

    /** Outlet bisa ratusan ribu baris: disimpan per blok 1.000 baris dengan upsert, bukan per baris. */
    private function outlets(): void
    {
        $existing = DB::table('outlets')->whereNotNull('legacy_id')->pluck('legacy_id', 'id')->flip()->all();
        $codes = DB::table('outlets')->pluck('legacy_id', 'code')->all();
        $batch = [];
        $now = now()->toDateTimeString();

        foreach ($this->source->rows('mk_outlet', ['id', 'kode', 'nama', 'alamat', 'area_id', 'kecamatan', 'kota_kabupaten', 'latitude', 'longitude', 'status_fiktif']) as $row) {
            $territory = $this->territories[(int) $row['area_id']] ?? null;
            $depot = $territory ? $this->territoryDepot[$territory] : null;

            if (! $depot) {
                $this->stats['outlet']['dilewati']++;

                continue;
            }

            $legacyId = (int) $row['id'];
            $fictitious = in_array(Str::lower((string) ($row['status_fiktif'] ?? '')), ['ya', 'yes', '1', 'true', 'fiktif'], true);

            // Kode unik: bila sudah dipakai outlet lain, tambah akhiran -2, -3, …
            $base = Str::limit(trim((string) ($row['kode'] ?: 'MK'.$legacyId)), 36, '');
            $code = $base;
            for ($i = 2; array_key_exists($code, $codes) && (int) $codes[$code] !== $legacyId; $i++) {
                $code = "{$base}-{$i}";
            }
            $codes[$code] = $legacyId;

            $this->stats['outlet'][isset($existing[$legacyId]) ? 'diperbarui' : 'baru']++;
            $batch[] = [
                'legacy_id' => $legacyId,
                'code' => $code,
                'name' => Str::limit(trim((string) ($row['nama'] ?: 'Outlet '.$legacyId)), 255, ''),
                'address' => $row['alamat'] ? Str::limit((string) $row['alamat'], 255, '') : null,
                'district' => $row['kecamatan'] ? Str::limit((string) $row['kecamatan'], 255, '') : null,
                'city' => $row['kota_kabupaten'] ? Str::limit((string) $row['kota_kabupaten'], 255, '') : null,
                'latitude' => $this->coordinate($row['latitude'] ?? null, 90),
                'longitude' => $this->coordinate($row['longitude'] ?? null, 180),
                'depot_id' => $depot,
                'territory_id' => $territory,
                'status' => ($fictitious ? OutletStatus::Inactive : OutletStatus::Active)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 1000) {
                $this->flushOutlets($batch);
            }
        }

        $this->flushOutlets($batch);
    }

    private function flushOutlets(array &$batch): void
    {
        if ($batch === []) {
            return;
        }

        DB::table('outlets')->upsert($batch, ['legacy_id'], [
            'code', 'name', 'address', 'district', 'city', 'latitude', 'longitude', 'depot_id', 'territory_id', 'status', 'updated_at',
        ]);
        $this->say('  … '.number_format($this->stats['outlet']['baru'] + $this->stats['outlet']['diperbarui'], 0, ',', '.').' outlet');
        $batch = [];
    }

    private function products(): void
    {
        $today = Carbon::today()->toDateString();

        foreach ($this->source->rows('mk_produk', ['id', 'kode', 'nama', 'harga_jual']) as $row) {
            $name = trim($row['nama']);
            $id = $this->upsert(Product::class, 'produk', (int) $row['id'], [
                'sku' => $this->uniqueCode('products', 'sku', $row['kode'] ?: 'MK'.$row['id'], 'legacy_id', $row['id']),
                'name' => $name,
            ], ['tracks_container' => Str::contains(Str::lower($name), 'galon'), 'is_active' => true]);

            $price = (int) round((float) ($row['harga_jual'] ?? 0));
            if ($price > 0 && ! ProductPrice::where('product_id', $id)->whereNull('depot_id')->exists()) {
                ProductPrice::put($id, null, $today, $price);
            }
        }
    }

    private function users(): void
    {
        $roles = Role::pluck('id', 'slug');
        $map = ['super_admin' => 'super_admin', 'koordinator' => 'koordinator', 'sales' => 'sales', 'lainnya' => 'koordinator'];

        foreach ($this->source->rows('users', ['id', 'name', 'username', 'password', 'kategori', 'karyawan_id', 'aktif', 'wajib_ganti_password']) as $row) {
            $username = Str::lower(trim((string) ($row['username'] ?? '')));

            if ($username === '' || ! isset($row['password'])) {
                $this->stats['akun']['dilewati']++;

                continue;
            }

            $kategori = $row['kategori'] ?? 'lainnya';
            $employee = $this->employees[(int) ($row['karyawan_id'] ?? 0)] ?? null;
            $user = User::where('username', $username)->first();

            if ($employee && User::where('employee_id', $employee)->where('username', '!=', $username)->exists()) {
                $employee = null;
            }

            $attributes = [
                'name' => trim((string) ($row['name'] ?? $username)) ?: $username,
                'role_id' => $roles[$map[$kategori] ?? 'koordinator'] ?? $roles['koordinator'],
                'employee_id' => $employee,
                'all_depots' => $kategori === 'super_admin',
                'is_active' => ($row['aktif'] ?? 1) ? true : false,
                'must_change_password' => (bool) ($row['wajib_ganti_password'] ?? false),
            ];

            if ($user) {
                $user->forceFill($attributes)->save();
                $this->stats['akun']['diperbarui']++;
            } else {
                // Hash bcrypt lama dipakai apa adanya, jadi password motoris tidak berubah.
                $user = new User;
                $user->forceFill($attributes + ['username' => $username, 'password' => Str::random(40)])->save();
                DB::table('users')->where('id', $user->id)->update(['password' => $row['password']]);
                $this->stats['akun']['baru']++;
            }
        }
    }

    // ---- pembantu ----

    /** @return int id baris tujuan */
    private function upsert(string $model, string $step, int $legacyId, array $attributes, array $onCreate = []): int
    {
        $existing = $model::where('legacy_id', $legacyId)->first();

        if ($existing) {
            $existing->fill($attributes)->save();
            $this->stats[$step]['diperbarui']++;

            return $existing->id;
        }

        $created = $model::create($attributes + $onCreate + ['legacy_id' => $legacyId]);
        $this->stats[$step]['baru']++;

        return $created->id;
    }

    private function loadMaps(): void
    {
        $this->depots = Depot::whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();
        $this->territories = Territory::whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();
        $this->territoryDepot = Territory::pluck('depot_id', 'id')->all();
        $this->employees = Employee::whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();
    }

    private function uniqueCode(string $table, string $column, string $code, string $keyColumn, mixed $key): string
    {
        $code = Str::limit(trim($code), 36, '');
        $candidate = $code;
        $i = 2;

        while (DB::table($table)->where($column, $candidate)->where(fn ($q) => $q->whereNull($keyColumn)->orWhere($keyColumn, '!=', $key))->exists()) {
            $candidate = $code.'-'.$i++;
        }

        return $candidate;
    }

    private function coordinate(mixed $value, int $limit): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $v = (float) $value;

        return $v === 0.0 || abs($v) > $limit ? null : $v;
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Polygon lama → GeoJSON. Menerima GeoJSON, [[lat,lng],…], [{lat,lng},…], atau daftar beberapa polygon.
     * Urutan lat/lng ditebak dari rentang Indonesia (|lng| > 90).
     */
    public function geometry(mixed $raw): ?array
    {
        $data = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($data) || $data === []) {
            return null;
        }

        if (isset($data['type'], $data['coordinates'])) {
            return in_array($data['type'], ['Polygon', 'MultiPolygon'], true) ? $data : null;
        }

        if (isset($data['geometry'])) {
            return $this->geometry($data['geometry']);
        }

        $ring = function (array $points): ?array {
            $out = [];
            foreach ($points as $p) {
                [$a, $b] = isset($p['lat']) ? [(float) $p['lat'], (float) ($p['lng'] ?? $p['lon'] ?? 0)] : [(float) ($p[0] ?? 0), (float) ($p[1] ?? 0)];
                $out[] = abs($a) > 90 ? [$a, $b] : [$b, $a];
            }

            if (count($out) < 3) {
                return null;
            }

            if ($out[0] !== end($out)) {
                $out[] = $out[0];
            }

            return $out;
        };

        $first = reset($data);
        $isPoint = fn ($p) => is_array($p) && (isset($p['lat']) || (isset($p[0]) && is_numeric($p[0])));

        if ($isPoint($first)) {
            $r = $ring($data);

            return $r ? ['type' => 'Polygon', 'coordinates' => [$r]] : null;
        }

        $polygons = array_values(array_filter(array_map(fn ($points) => is_array($points) ? $ring($points) : null, $data)));

        return match (count($polygons)) {
            0 => null,
            1 => ['type' => 'Polygon', 'coordinates' => [$polygons[0]]],
            default => ['type' => 'MultiPolygon', 'coordinates' => array_map(fn ($r) => [$r], $polygons)],
        };
    }

    private function say(string $message): void
    {
        if ($this->progress) {
            ($this->progress)($message.sprintf(' (memori %d MB)', memory_get_usage(true) / 1048576));
        }
    }
}
