<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUnitAccess;
use App\Services\PegawaiPortalAccess;
use Database\Seeders\Concerns\YayasanDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun demo, dua jenis:
 *
 * admins (panel pengelola), dibatasi pada unit (dan turunannya) miliknya:
 *   pb                  -> seluruh organisasi
 *   komwil.<prov>       -> wilayahnya
 *   komda.<prov>.<kab>  -> daerahnya
 *   kepsek.<sekolah>    -> sekolahnya saja (panel pengelola: data siswa, absensi siswa, pegawai & absensi pegawai sekolahnya)
 *
 * users (portal pegawai), terhubung ke data pegawai:
 *   kepsek.<sekolah>    -> kepala sekolah, bisa absen di portal pegawai (akun terpisah dari panel di atas)
 *
 * Kata sandi: env SEED_DEMO_PASSWORD (bawaan di bawah). HANYA untuk lingkungan non-produksi.
 */
class YayasanAkunSeeder extends Seeder
{
    use YayasanDataset;

    private const DEFAULT_PASSWORD = 'Yayasan#2026Demo';

    private const COMMON_VIEW = [
        'dashboard.view', 'organization.view', 'programs.view', 'reports.view', 'reports.export',
    ];

    // Pengurus Besar berfungsi sebagai pengarah/pengawas lintas organisasi,
    // bukan operator harian untuk unit, jabatan, pegawai, sekolah, atau absensi.
    private const PB = [
        ...self::COMMON_VIEW, 'organization.access.all', 'programs.approve',
    ];

    // Komisaris Wilayah mengelola tata organisasi dan koordinasi program pada
    // wilayahnya. Cakupan data tetap dibatasi oleh UserUnitAccess.
    private const KOMWIL = [
        ...self::COMMON_VIEW,
        'organization.units.manage', 'organization.positions.manage', 'organization.assignments.manage',
        'programs.manage', 'programs.submit', 'programs.approve',
        // Pendidikan: memantau saja (lihat sekolah, siswa, rekap absensi) di wilayahnya.
        'education.schools.view', 'education.students.view', 'education.attendance.view',
    ];

    // Komisaris Daerah mengurus operasi wilayah lokal, termasuk pegawai,
    // absensi, dan data pendidikan yang berada dalam cakupan unitnya.
    private const KOMDA = [
        ...self::COMMON_VIEW,
        'organization.units.manage', 'organization.positions.manage', 'organization.assignments.manage',
        'employees.view', 'employees.create', 'employees.update', 'employees.status',
        'employees.assignments.manage', 'employees.documents.view', 'employees.documents.manage',
        'attendance.view', 'attendance.records.manage', 'attendance.corrections.review',
        'programs.manage', 'programs.submit',
        'education.schools.view', 'education.schools.manage',
        'education.students.view', 'education.students.manage', 'education.academic.manage',
        'education.attendance.view', 'education.attendance.manage',
    ];

    // Kepala sekolah mengelola siswa, kelas, absensi siswa, serta pegawai dan absensi pegawai
    // HANYA di sekolahnya (cakupan dibatasi UserUnitAccess ke unit sekolah).
    private const KEPSEK = [
        'dashboard.view',
        'employees.view', 'employees.create', 'employees.update', 'employees.status',
        'employees.assignments.manage', 'employees.documents.view', 'employees.documents.manage',
        'attendance.view', 'attendance.records.manage', 'attendance.corrections.review',
        'education.schools.view',
        'education.students.view', 'education.students.manage', 'education.academic.manage',
        'education.attendance.view', 'education.attendance.manage',
    ];

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->call(YayasanKepegawaianSeeder::class);
        Artisan::call('permissions:sync');

        $password = env('SEED_DEMO_PASSWORD', self::DEFAULT_PASSWORD);
        $hash = Hash::make($password);   // sekali saja; bcrypt lambat

        DB::transaction(function () use ($hash) {
            $roles = [
                'super-admin' => ['Super Admin', Permission::where('guard_name', 'admin')->pluck('name')->all()],
                'pengurus-besar' => ['Pengurus Besar', self::PB],
                'komisaris-wilayah' => ['Komisaris Wilayah', self::KOMWIL],
                'komisaris-daerah' => ['Komisaris Daerah', self::KOMDA],
                'kepala-sekolah' => ['Kepala Sekolah', self::KEPSEK],
            ];
            $roleIds = [];
            foreach ($roles as $name => [$label, $permissions]) {
                $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'admin'], ['label' => $label, 'is_system' => false]);
                $role->permissions()->sync(Permission::where('guard_name', 'admin')->whereIn('name', $permissions)->pluck('id'));
                $roleIds[$name] = $role->id;
            }

            $this->account('superadmin', 'Super Admin Demo', $roleIds['super-admin'], null, $hash);
            $this->linkToHead($this->account('pb', 'Ketua Umum (Demo)', $roleIds['pengurus-besar'], self::PB_CODE, $hash), self::PB_CODE, 'Ketua Umum');

            foreach ($this->provinces() as [$provCode, $provName, $komdas]) {
                $this->linkToHead($this->account('komwil.'.strtolower($provCode), "Komisaris Wilayah {$provName} (Demo)", $roleIds['komisaris-wilayah'], "KOMWIL-{$provCode}", $hash), "KOMWIL-{$provCode}", 'Komisaris Wilayah');
                foreach ($komdas as [$komdaCode, $komdaName]) {
                    $this->linkToHead($this->account('komda.'.strtolower("{$provCode}.{$komdaCode}"), "Komisaris Daerah {$komdaName} (Demo)", $roleIds['komisaris-daerah'], "KOMDA-{$provCode}-{$komdaCode}", $hash), "KOMDA-{$provCode}-{$komdaCode}", 'Komisaris Daerah');
                }
            }

            foreach ($this->schoolSpecs() as $spec) {
                $this->account('kepsek.'.strtolower($spec['code']), "Kepala {$spec['name']} (Demo, panel)", $roleIds['kepala-sekolah'], $spec['code'], $hash);
            }

            $pegawaiRoleId = app(PegawaiPortalAccess::class)->defaultRole()->id;
            foreach ($this->schoolSpecs() as $spec) {
                $head = Employee::where('employee_number', sprintf('PEG-%02d-01', $spec['index']))->first();
                if (! $head) {
                    continue;
                }
                $user = $this->pegawaiAccount('kepsek.'.strtolower($spec['code']), "Kepala {$spec['name']} (Demo)", $pegawaiRoleId, $hash);
                if (! $head->user_id) {
                    $head->update(['user_id' => $user->id]);
                }
            }
        });

        $this->command?->info('Akun demo: '.Admin::count().' admin, '.User::count()." pegawai (kata sandi: {$password}). Hanya untuk pengembangan.");
    }

    /** Kaitkan akun panel ke penugasan pimpinan unitnya (mis. Komisaris Daerah) agar tampil sebagai akun terkait. */
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

    private function pegawaiAccount(string $username, string $name, int $roleId, string $hash): User
    {
        $username = str_replace('-', '.', $username);
        $user = User::firstOrCreate(['username' => $username], [
            'name' => $name, 'email' => "{$username}@demo.example.test", 'password' => $hash, 'is_active' => true,
        ]);
        $user->syncRoles([$roleId]);

        return $user;
    }

    private function account(string $username, string $name, int $roleId, ?string $unitCode, string $hash): Admin
    {
        $username = str_replace('-', '.', $username);
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
