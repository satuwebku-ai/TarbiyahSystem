<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use App\Rules\AdminPasswordPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/**
 * Super Admin awal. Atur lewat .env: ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
 * Bila akun sudah ada, password TIDAK ditimpa (hanya dipastikan aktif + role super-admin).
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('domains.seed_admin');

        $findSuper = fn () => Role::where('guard_name', 'admin')->where('name', 'super-admin')->first();

        $super = $findSuper();
        if (! $super) {
            $this->call(RbacSeeder::class);   // role belum ada -> buat dulu
            $super = $findSuper();
        }

        $admin = Admin::where('email', $cfg['email'])->first();

        if ($admin) {
            $admin->forceFill(['is_active' => true]);
            if (! $admin->username) {
                $admin->username = $cfg['username'];
            }
            $admin->save();
            $admin->syncRoles(array_unique([...$admin->roles->pluck('id')->all(), $super->id]));
            $this->command?->info("Admin sudah ada: {$cfg['email']} (password tidak diubah).");

            return;
        }

        $generated = false;
        $password  = $cfg['password'];
        if (! $password) {
            $generated = ! app()->environment('local');
            $password  = $generated ? null : 'GantiPasswordIni!123';
        }

        $passwordRules = ['password' => [AdminPasswordPolicy::rule()]];
        if ($generated) {
            do {
                $password = Str::password(32);
                $validPassword = Validator::make(['password' => $password], $passwordRules)->passes();
            } while (! $validPassword);
        } else {
            Validator::make(['password' => $password], $passwordRules)->validate();
        }

        $admin = Admin::create([
            'name'      => $cfg['name'],
            'username'  => $cfg['username'],
            'email'     => $cfg['email'],
            'password'  => $password,
            'is_active' => true,
        ]);
        $admin->syncRoles([$super->id]);

        $this->command?->info("Super Admin dibuat: {$cfg['email']}");
        if ($generated || ! $cfg['password']) {
            $this->command?->warn("Password: {$password}   <- catat & segera ganti");
        }
    }
}
