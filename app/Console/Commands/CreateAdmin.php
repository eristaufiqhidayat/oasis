<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oasis:buat-admin {username=admin} {--password= : Kosong = ditanyakan}')]
#[Description('Buat atau reset akun Super Admin')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $role = Role::where('slug', 'super_admin')->first();

        if (! $role) {
            $this->error('Peran super_admin belum ada. Jalankan: php artisan db:seed');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Password baru (min. 8 karakter)');

        if (strlen((string) $password) < 8) {
            $this->error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['username' => $this->argument('username')], [
            'name' => 'Administrator', 'password' => $password, 'role_id' => $role->id, 'all_depots' => true,
            'is_active' => true, 'must_change_password' => false,
        ]);

        $this->info("Akun {$user->username} siap dipakai.");

        return self::SUCCESS;
    }
}
