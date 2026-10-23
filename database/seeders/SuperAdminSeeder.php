<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use App\Rules\AdminPasswordPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * 1. Akun Super Admin, siap pakai tanpa mengisi .env:
 *
 *     Username : superadmin
 *     Email    : superadmin@apps-ku.my.id
 *     Password : Admin#2026Tarbiyah
 *
 * Ubah konstanta di bawah bila perlu. Aturan password:
 *   - ADMIN_PASSWORD di .env terisi -> dipakai menggantikan password bawaan (wajib lolos kebijakan password).
 *   - selain production             -> PASSWORD bawaan di bawah (hanya untuk lokal/staging, segera ganti).
 *   - production tanpa ADMIN_PASSWORD -> dibuat acak dan ditampilkan sekali di layar.
 * Bila akun sudah ada, password TIDAK ditimpa (hanya dipastikan aktif dan berperan super-admin).
 */
class SuperAdminSeeder extends Seeder
{
    public const NAME = 'Super Admin';
    public const USERNAME = 'superadmin';
    public const EMAIL = 'superadmin@apps-ku.my.id';

    /** Password awal untuk lingkungan non-production. Memenuhi kebijakan password bawaan. */
    public const DEV_PASSWORD = 'Admin#2026Tarbiyah';

    public function run(): void
    {
        $role = $this->superAdminRole();

        $admin = Admin::where('email', self::EMAIL)->orWhere('username', self::USERNAME)->first();
        if ($admin) {
            $admin->forceFill(['is_active' => true, 'username' => $admin->username ?: self::USERNAME])->save();
            $admin->syncRoles(array_unique([...$admin->roles->pluck('id')->all(), $role->id]));
            $this->command?->info("Super Admin sudah ada: {$admin->username} (password tidak diubah).");

            return;
        }

        $password = config('domains.seed_admin.password');
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
            'name' => self::NAME, 'username' => self::USERNAME, 'email' => self::EMAIL,
            'password' => $password, 'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);

        $this->command?->info('Super Admin dibuat. Username: '.self::USERNAME.' | Email: '.self::EMAIL);
        if ($random || $password === self::DEV_PASSWORD) {
            $this->command?->warn("Password: {$password}   <- segera ganti setelah login");
        }
    }

    private function superAdminRole(): Role
    {
        $find = fn () => Role::where('guard_name', 'admin')->where('name', 'super-admin')->first();

        if (! ($role = $find())) {
            $this->call(RbacSeeder::class);
            $role = $find();
        }

        return $role;
    }
}