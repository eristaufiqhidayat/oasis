<?php

namespace Database\Seeders;

use App\Models\IncentiveScheme;
use App\Models\OutletChannel;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Data acuan yang wajib ada: peran bawaan, akun admin, saluran outlet, kategori produk, skema insentif contoh. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions.roles') as $slug => $role) {
            Role::updateOrCreate(['slug' => $slug], $role + ['is_system' => true]);
        }

        if (! User::where('username', 'admin')->exists()) {
            $password = config('auth.seed_admin_password');

            User::create([
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => $password ?: 'password',
                'role_id' => Role::where('slug', 'super_admin')->value('id'),
                'all_depots' => true,
                'must_change_password' => ! $password,
            ]);
        }

        foreach ([
            'WRG' => 'Warung', 'TKL' => 'Toko kelontong', 'MMT' => 'Minimarket', 'DIU' => 'Depot isi ulang',
            'HRK' => 'Hotel/Restoran/Kafe', 'KTR' => 'Kantor & instansi', 'RMT' => 'Rumah tangga',
        ] as $code => $name) {
            OutletChannel::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (['Galon', 'Botol', 'Gelas (cup)'] as $name) {
            ProductCategory::firstOrCreate(['name' => $name]);
        }

        if (! IncentiveScheme::exists()) {
            IncentiveScheme::create([
                'name' => 'Insentif motoris standar',
                'applies_to' => 'salesman',
                'rules' => ['per_effective_call' => 1000, 'revenue_percent' => 1, 'tiers' => [
                    ['min_achievement' => 80, 'amount' => 250000],
                    ['min_achievement' => 100, 'amount' => 500000],
                    ['min_achievement' => 120, 'amount' => 750000],
                ]],
            ]);
            IncentiveScheme::create([
                'name' => 'Insentif koordinator standar',
                'applies_to' => 'coordinator',
                'rules' => ['per_effective_call' => 0, 'revenue_percent' => 0.25, 'tiers' => [
                    ['min_achievement' => 90, 'amount' => 500000],
                    ['min_achievement' => 100, 'amount' => 1000000],
                ]],
            ]);
        }
    }
}
