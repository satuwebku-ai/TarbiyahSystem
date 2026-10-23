<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use App\Rules\AdminPasswordPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * 1. Akun Super Admin. Atur lewat .env: ADMIN_NAME, ADMIN_USERNAME, ADMIN_EMAIL, ADMIN_PASSWORD.
 *
 * Password:
 *   - ADMIN_PASSWORD terisi            -> dipakai apa adanya (wajib lolos kebijakan password).
 *   - kosong, selain production        -> DEV_PASSWORD di bawah (hanya untuk lokal/staging, segera ganti).
 *   - kosong, production               -> dibuat acak dan ditampilkan sekali di layar.
 * Bila akun sudah ada, password TIDAK ditimpa (hanya dipastikan aktif dan berperan super-admin).
 */
class SuperAdminSeeder extends Seeder
{
    /** Password awal untuk lingkungan non-production. Memenuhi kebijakan password bawaan. */
    public const DEV_PASSWORD = 'Admin#2026Tarbiyah';

    public function run(): void
    {
        $cfg = config('domains.seed_admin');

        $super = fn () => Role::where('guard_name', 'admin')->where('name', 'super-admin')->first();
        $role = $super();
        if (! $role) {
            $this->call(RbacSeeder::class);
            $role = $super();
        }

        $admin = Admin::where('email', $cfg['email'])->orWhere('username', $cfg['username'])->first();
        if ($admin) {
            $admin->forceFill(['is_active' => true, 'username' => $admin->username ?: $cfg['username']])->save();
            $admin->syncRoles(array_unique([...$admin->roles->pluck('id')->all(), $role->id]));
            $this->command?->info("Super Admin sudah ada: {$cfg['email']} (password tidak diubah).");

            return;
        }

        $password = $cfg['password'];
        $random = false;
        if (! $password) {
            if (app()->isProduction()) {
                $random = true;
                $rules = ['password' => [AdminPasswordPolicy::rule()]];
                do {
                    $password = Str::password(32);
                } while (Validator::make(['password' => $password], $rules)->fails());
            } else {
                $password = self::DEV_PASSWORD;
            }
        }
        Validator::make(['password' => $password], ['password' => [AdminPasswordPolicy::rule()]])->validate();

        $admin = Admin::create([
            'name' => $cfg['name'], 'username' => $cfg['username'], 'email' => $cfg['email'],
            'password' => $password, 'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);

        $this->command?->info("Super Admin dibuat: {$cfg['username']} / {$cfg['email']}");
        if ($random || ! $cfg['password']) {
            $this->command?->warn("Password: {$password}   <- catat dan segera ganti");
        }
    }
}
