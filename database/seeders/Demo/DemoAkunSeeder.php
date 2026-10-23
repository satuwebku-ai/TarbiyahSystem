<?php

namespace Database\Seeders\Demo;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUnitAccess;
use App\Services\PegawaiPortalAccess;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun demo untuk tombol "Akun Demo" di halaman login. Dua jenis:
 *
 * admins (panel pengelola), dibatasi pada unit (dan turunannya) miliknya:
 *   superadmin                -> semua
 *   pb                        -> seluruh organisasi
 *   komwil.<prov>             -> wilayahnya
 *   komda.<prov>.<kab>        -> daerahnya
 *   kepsek.<jenjang>.<prov>.<kab> -> sekolahnya saja
 *
 * users (portal pegawai), terhubung ke data pegawai:
 *   kepsek.<jenjang>.<prov>.<kab> -> kepala sekolah (akun terpisah dari panel di atas)
 *   guru.<jenjang>.<prov>.<kab>   -> satu guru per sekolah
 *
 * Kata sandi: env SEED_DEMO_PASSWORD (bawaan di bawah). HANYA untuk lingkungan non-production.
 */
class DemoAkunSeeder extends Seeder
{
    use DemoDataset;

    public const DEFAULT_PASSWORD = 'Yayasan#2026Demo';

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->ensure(Employee::where('employee_number', DemoKepegawaianSeeder::number($this->schoolSpecs()[0]['code'], 1))->exists(), DemoKepegawaianSeeder::class);
        $this->ensure(Role::where('name', 'kepala-sekolah')->where('guard_name', 'admin')->exists(), RbacSeeder::class);

        $password = env('SEED_DEMO_PASSWORD', self::DEFAULT_PASSWORD);
        $hash = Hash::make($password);   // sekali saja; bcrypt lambat

        DB::transaction(function () use ($hash) {
            $roles = Role::where('guard_name', 'admin')
                ->whereIn('name', ['super-admin', 'pengurus-besar', 'komisaris-wilayah', 'komisaris-daerah', 'kepala-sekolah'])
                ->pluck('id', 'name');

            $this->admin('superadmin', 'Super Admin Demo', $roles['super-admin'], null, $hash);
            $this->linkToHead($this->admin('pb', 'Ketua Umum (Demo)', $roles['pengurus-besar'], self::PB_CODE, $hash), self::PB_CODE, 'Ketua Umum');

            foreach ($this->provinces() as [$provCode, $provName, $komdas]) {
                $komwilCode = "KOMWIL-{$provCode}";
                $this->linkToHead($this->admin($this->usernameFor('komwil', $provCode), "Komisaris Wilayah {$provName} (Demo)", $roles['komisaris-wilayah'], $komwilCode, $hash), $komwilCode, 'Komisaris Wilayah');

                foreach ($komdas as [$komdaCode, $komdaName]) {
                    $unitCode = "KOMDA-{$provCode}-{$komdaCode}";
                    $this->linkToHead($this->admin($this->usernameFor('komda', "{$provCode}-{$komdaCode}"), "Komisaris Daerah {$komdaName} (Demo)", $roles['komisaris-daerah'], $unitCode, $hash), $unitCode, 'Komisaris Daerah');
                }
            }

            $portalRole = app(PegawaiPortalAccess::class)->defaultRole()->id;
            foreach ($this->schoolSpecs() as $spec) {
                $this->admin($this->usernameFor('kepsek', $spec['code']), "Kepala {$spec['name']} (Demo, panel)", $roles['kepala-sekolah'], $spec['code'], $hash);

                $this->portalAccount($this->usernameFor('kepsek', $spec['code']), "Kepala {$spec['name']} (Demo)", DemoKepegawaianSeeder::number($spec['code'], 1), $portalRole, $hash);
                $this->portalAccount($this->usernameFor('guru', $spec['code']), "Guru {$spec['name']} (Demo)", DemoKepegawaianSeeder::number($spec['code'], 3), $portalRole, $hash);
            }
        });

        $this->command?->info('Akun demo: '.Admin::count().' admin, '.User::count()." pegawai (kata sandi: {$password}). Hanya untuk pengembangan.");
    }

    /** Kaitkan akun panel ke penugasan pimpinan unitnya agar tampil sebagai akun terkait. */
    private function linkToHead(Admin $admin, string $unitCode, string $positionName): void
    {
        $unit = OrganizationalUnit::where('code', $unitCode)->first();
        $position = $unit?->positions()->where('name', $positionName)->first();
        if (! $position) {
            return;
        }

        OrganizationalAssignment::where('organizational_position_id', $position->id)
            ->where('status', 'active')
            ->whereNull('admin_id')
            ->update(['admin_id' => $admin->id]);
    }

    private function portalAccount(string $username, string $name, string $employeeNumber, int $roleId, string $hash): void
    {
        $employee = Employee::where('employee_number', $employeeNumber)->first();
        if (! $employee) {
            return;
        }

        $user = User::firstOrCreate(['username' => $username], [
            'name' => $name, 'email' => "{$username}@demo.example.test", 'password' => $hash, 'is_active' => true,
        ]);
        $user->syncRoles([$roleId]);

        if (! $employee->user_id && ! Employee::where('user_id', $user->id)->exists()) {
            $employee->update(['user_id' => $user->id]);
        }
    }

    private function admin(string $username, string $name, int $roleId, ?string $unitCode, string $hash): Admin
    {
        $admin = Admin::firstOrCreate(['username' => $username], [
            'name' => $name, 'email' => "{$username}@demo.example.test", 'password' => $hash, 'is_active' => true,
        ]);
        $admin->syncRoles([$roleId]);

        if ($unitCode) {
            $unit = OrganizationalUnit::where('code', $unitCode)->firstOrFail();
            UserUnitAccess::firstOrCreate(
                ['organizational_unit_id' => $unit->id, 'model_type' => Admin::class, 'model_id' => $admin->id],
                ['created_at' => now()],
            );
        }

        return $admin;
    }
}
