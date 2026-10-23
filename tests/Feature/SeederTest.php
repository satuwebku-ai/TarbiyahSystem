<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JenisPegawaiSeeder;
use Database\Seeders\JenisUnitSeeder;
use Database\Seeders\PenugasanSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_fills_every_data_set(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 6. jenis unit, 5. jenis pegawai
        $this->assertSame(['komda', 'komwil', 'majelis', 'pengurus-besar', 'sekolah'], OrganizationalUnitType::orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['pb', 'staff', 'teacher'], EmployeeType::orderBy('code')->pluck('code')->all());

        // 7. unit: 1 PB + 13 majelis + 2 KOMWIL + 4 KOMDA + 12 sekolah
        $this->assertSame(32, OrganizationalUnit::count());
        $pb = OrganizationalUnit::where('code', 'PB-YAYASAN')->firstOrFail();
        $this->assertNull($pb->parent_id);
        $this->assertSame(2, OrganizationalUnit::whereHas('type', fn ($q) => $q->where('slug', 'komwil'))->where('parent_id', $pb->id)->count());
        foreach (OrganizationalUnit::whereHas('type', fn ($q) => $q->where('slug', 'komda'))->get() as $komda) {
            $levels = School::whereHas('unit', fn ($q) => $q->where('parent_id', $komda->id))->with('level')->get()->pluck('level.slug')->sort()->values()->all();
            $this->assertSame(['sd', 'sma', 'smp'], $levels, $komda->name);
        }

        // 8. jabatan: guru dan tenaga administrasi tanpa batas, pejabat satu orang
        $this->assertNull(OrganizationalPosition::where('name', 'Guru')->first()->capacity);
        $this->assertSame(1, OrganizationalPosition::where('name', 'Kepala Sekolah')->first()->capacity);

        // 4. sekolah, 3. siswa
        $this->assertSame(12, School::count());
        $this->assertSame(36, Classroom::count());
        $this->assertSame(360, Student::count());
        $this->assertSame(0, Student::whereDoesntHave('guardians')->count());

        // 2. pegawai per jenis: 12 sekolah x (7 guru + 1 tenaga administrasi); PB: 4+13+2*3+4*3 pengurus + 7 staf sekretariat
        $this->assertSame(84, Employee::where('employee_type', 'teacher')->count());
        $this->assertSame(12, Employee::where('employee_type', 'staff')->count());
        $this->assertSame(42, Employee::where('employee_type', 'pb')->count());

        // 9. penugasan: setiap pegawai berjabatan (selain staf sekretariat) tercatat memegang jabatannya
        $this->assertSame(Employee::count() - 7, OrganizationalAssignment::where('status', 'active')->count());
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = fn () => [OrganizationalUnit::count(), OrganizationalPosition::count(), School::count(), Employee::count(), OrganizationalAssignment::count(), Student::count(), Admin::count()];
        $first = $counts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $counts());
    }

    public function test_super_admin_gets_the_configured_or_default_password_and_keeps_it_afterwards(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $admin = Admin::where('username', SuperAdminSeeder::USERNAME)->firstOrFail();
        $this->assertTrue(Hash::check(SuperAdminSeeder::DEV_PASSWORD, $admin->password));
        $this->assertSame(SuperAdminSeeder::EMAIL, $admin->email);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->hasRole('super-admin'));

        $admin->forceFill(['password' => 'Diganti#Pengelola9'])->save();
        $this->seed(SuperAdminSeeder::class);
        $this->assertSame(1, Admin::count());
        $this->assertTrue(Hash::check('Diganti#Pengelola9', $admin->fresh()->password));
    }

    public function test_super_admin_password_comes_from_env_config_when_set(): void
    {
        config(['domains.seed_admin.password' => 'DariEnv#Rahasia2026']);
        $this->seed(SuperAdminSeeder::class);

        $this->assertTrue(Hash::check('DariEnv#Rahasia2026', Admin::firstOrFail()->password));
    }

    public function test_super_admin_password_is_random_in_production_without_env(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        config(['domains.seed_admin.password' => null]);

        try {
            (new SuperAdminSeeder)->setContainer($this->app)->run();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }

        $this->assertFalse(Hash::check(SuperAdminSeeder::DEV_PASSWORD, Admin::firstOrFail()->password));
    }

    public function test_reference_seeders_keep_edits_made_by_admins(): void
    {
        $this->seed(JenisPegawaiSeeder::class);
        EmployeeType::where('code', 'pb')->update(['name' => 'Staf Pengurus Besar', 'is_active' => false]);
        $this->seed(JenisPegawaiSeeder::class);
        $this->assertSame('Staf Pengurus Besar', EmployeeType::where('code', 'pb')->value('name'));
        $this->assertFalse((bool) EmployeeType::where('code', 'pb')->value('is_active'));

        $this->seed(JenisUnitSeeder::class);
        OrganizationalUnitType::where('slug', 'sekolah')->update(['name' => 'Satuan Pendidikan']);
        $this->seed(JenisUnitSeeder::class);
        $this->assertSame('Satuan Pendidikan', OrganizationalUnitType::where('slug', 'sekolah')->value('name'));
    }

    public function test_sample_data_is_skipped_in_production_but_reference_data_and_admin_are_kept(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        config(['domains.seed_admin.password' => 'DariEnv#Rahasia2026']);

        try {
            // Dipanggil langsung: `db:seed` meminta konfirmasi interaktif di production.
            (new DatabaseSeeder)->setContainer($this->app)->run();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }

        $this->assertSame(0, OrganizationalUnit::count());
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Student::count());
        $this->assertSame(0, School::count());
        $this->assertSame(1, Admin::count());
        $this->assertSame(3, EmployeeType::count());
        $this->assertSame(5, OrganizationalUnitType::count());
    }

    public function test_assignment_seeder_runs_the_prerequisite_seeders_and_is_repeatable(): void
    {
        $this->seed(PenugasanSeeder::class);
        $n = OrganizationalAssignment::count();
        $this->assertGreaterThan(0, $n);

        $this->seed(PenugasanSeeder::class);
        $this->assertSame($n, OrganizationalAssignment::count());
    }

    public function test_rbac_seeder_gives_organization_roles_valid_permissions_and_keeps_later_edits(): void
    {
        $this->seed(RbacSeeder::class);

        foreach (['pengurus-besar' => RbacSeeder::PB, 'komisaris-wilayah' => RbacSeeder::KOMWIL, 'komisaris-daerah' => RbacSeeder::KOMDA, 'kepala-sekolah' => RbacSeeder::KEPSEK] as $name => $expected) {
            $role = Role::where('guard_name', 'admin')->where('name', $name)->firstOrFail();
            $this->assertEqualsCanonicalizing(array_unique($expected), $role->permissions()->pluck('name')->all(), "Izin role {$name} harus persis daftar di seeder.");
        }

        $role = Role::where('name', 'kepala-sekolah')->where('guard_name', 'admin')->firstOrFail();
        $role->permissions()->sync(Permission::where('guard_name', 'admin')->where('name', 'dashboard.view')->pluck('id'));
        $this->seed(RbacSeeder::class);
        $this->assertSame(['dashboard.view'], $role->permissions()->pluck('name')->all());
    }
}