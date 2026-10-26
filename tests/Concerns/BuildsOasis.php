<?php

namespace Tests\Concerns;

use App\Enums\MovementType;
use App\Enums\Position;
use App\Enums\StockLocationType;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/** Pembuat data uji: depo, karyawan + akun, outlet, produk berharga, stok gudang. */
trait BuildsOasis
{
    use RefreshDatabase;

    protected function seedReference(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    protected function depot(string $code = 'BDG', array $attributes = []): Depot
    {
        return Depot::create($attributes + ['code' => $code, 'name' => "Depo {$code}", 'latitude' => -6.9, 'longitude' => 107.6]);
    }

    protected function employee(Depot $depot, Position $position = Position::Salesman, array $attributes = []): Employee
    {
        return Employee::create($attributes + [
            'code' => strtoupper(Str::random(6)), 'name' => $position->label().' '.Str::random(4), 'position' => $position, 'depot_id' => $depot->id,
        ]);
    }

    protected function userFor(string $role, ?Employee $employee = null, array $depots = [], array $attributes = []): User
    {
        $user = User::create($attributes + [
            'name' => $employee?->name ?? ucfirst($role),
            'username' => Str::lower($role.'.'.Str::random(5)),
            'password' => 'rahasia123',
            'role_id' => Role::where('slug', $role)->value('id'),
            'employee_id' => $employee?->id,
            'all_depots' => $role === 'super_admin',
        ]);
        $user->depots()->sync(collect($depots)->pluck('id'));

        return $user;
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->first() ?? $this->userFor('super_admin');
    }

    protected function salesman(Depot $depot, ?Employee $supervisor = null): Employee
    {
        $e = $this->employee($depot, Position::Salesman, ['supervisor_id' => $supervisor?->id]);
        $this->userFor('sales', $e);

        return $e;
    }

    protected function product(string $sku, int $price, bool $container = false): Product
    {
        $p = Product::create(['sku' => $sku, 'name' => "Produk {$sku}", 'unit' => $container ? 'galon' : 'botol', 'tracks_container' => $container]);
        ProductPrice::put($p->id, null, '2020-01-01', $price);

        return $p;
    }

    protected function outlet(Depot $depot, array $attributes = []): Outlet
    {
        return Outlet::create($attributes + [
            'code' => 'O-'.strtoupper(Str::random(6)), 'name' => 'Toko '.Str::random(4), 'depot_id' => $depot->id,
            'latitude' => -6.9, 'longitude' => 107.6, 'status' => 'active',
        ]);
    }

    protected function stock(Depot $depot, Product $product, int $qty): void
    {
        app(Inventory::class)->move($product->id, $qty, null, $depot->location(StockLocationType::Warehouse), MovementType::Receipt);
    }

    protected function warehouseQty(Depot $depot, Product $product): int
    {
        return app(Inventory::class)->balance($depot->location(StockLocationType::Warehouse)->id, $product->id);
    }

    protected function uuid(): string
    {
        return (string) Str::uuid();
    }
}
