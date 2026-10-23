<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAccount;
use Database\Seeders\SiswaSeeder;
use App\Models\SchoolLevel;
use App\Models\UserUnitAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Perbaikan temuan audit prioritas tinggi: eskalasi cakupan unit, pengambilalihan akun peran
 * tinggi oleh admin biasa, /iclock hanya berdasar nomor seri, dan TRUSTED_PROXIES via env().
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Test-Password-2026!';

    private OrganizationalUnit $unitA;
    private OrganizationalUnit $unitA1;
    private OrganizationalUnit $unitB;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $root = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'R', 'name' => 'Yayasan', 'status' => 'active']);
        $this->unitA = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $root->id, 'code' => 'A', 'name' => 'Wilayah A', 'status' => 'active']);
        $this->unitA1 = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $this->unitA->id, 'code' => 'A1', 'name' => 'Sekolah A1', 'status' => 'active']);
        $this->unitB = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $root->id, 'code' => 'B', 'name' => 'Wilayah B', 'status' => 'active']);
    }

    private function role(string $name, array $permissions = []): Role
    {
        $role = Role::create(['name' => $name, 'label' => $name, 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }

        return $role;
    }

    private function makeAdmin(array $permissions, ?OrganizationalUnit $scope = null, array $extraRoles = []): Admin
    {
        $n = ++$this->seq;
        $role = $this->role("role-$n", $permissions);
        $admin = Admin::create(['name' => "Admin $n", 'username' => "hard$n", 'email' => "hard$n@example.test", 'password' => self::PASSWORD, 'is_active' => true]);
        $admin->syncRoles([$role->id, ...array_map(fn (Role $r) => $r->id, $extraRoles)]);
        if ($scope) {
            UserUnitAccess::create(['organizational_unit_id' => $scope->id, 'model_type' => Admin::class, 'model_id' => $admin->id, 'created_at' => now()]);
        }

        return $admin;
    }

    private function superAdmin(): Admin
    {
        $role = Role::query()->where('name', 'super-admin')->where('guard_name', 'admin')->first()
            ?? $this->role('super-admin');

        return $this->makeAdmin([], null, [$role]);
    }

    private function scopeOf(Admin $admin): array
    {
        return UserUnitAccess::query()->where('model_type', Admin::class)->where('model_id', $admin->id)
            ->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    // ---- 1. eskalasi cakupan unit ---------------------------------------

    public function test_scoped_admin_cannot_grant_units_outside_their_own_scope(): void
    {
        $actor = $this->makeAdmin(['organization.access.manage'], $this->unitA);
        $target = $this->makeAdmin([]);
        $this->actingAs($actor, 'admin');

        $this->put(route('admin.organization.access.update'), ['admin_id' => $target->id, 'unit_ids' => [$this->unitB->id]])
            ->assertSessionHasErrors('unit_ids');
        $this->assertSame([], $this->scopeOf($target));

        // Mengangkat dirinya sendiri ke unit lain juga ditolak.
        $this->put(route('admin.organization.access.update'), ['admin_id' => $actor->id, 'unit_ids' => [$this->unitA->id, $this->unitB->id]])
            ->assertSessionHasErrors('unit_ids');
        $this->assertSame([$this->unitA->id], $this->scopeOf($actor));
    }

    public function test_scoped_admin_can_grant_units_inside_scope_and_keeps_assignments_outside_it(): void
    {
        $actor = $this->makeAdmin(['organization.access.manage'], $this->unitA);
        $target = $this->makeAdmin([], $this->unitB);
        $this->actingAs($actor, 'admin');

        $this->put(route('admin.organization.access.update'), ['admin_id' => $target->id, 'unit_ids' => [$this->unitA1->id]])
            ->assertSessionHasNoErrors();

        // Unit B milik target di luar cakupan pengelola: tidak ikut terhapus.
        $this->assertSame([$this->unitA1->id, $this->unitB->id], $this->scopeOf($target));

        $this->put(route('admin.organization.access.update'), ['admin_id' => $target->id, 'unit_ids' => []])->assertSessionHasNoErrors();
        $this->assertSame([$this->unitB->id], $this->scopeOf($target));
    }

    public function test_unrestricted_admin_can_still_assign_any_unit(): void
    {
        $actor = $this->makeAdmin(['organization.access.manage', 'organization.access.all']);
        $target = $this->makeAdmin([]);
        $this->actingAs($actor, 'admin');

        $this->put(route('admin.organization.access.update'), ['admin_id' => $target->id, 'unit_ids' => [$this->unitA->id, $this->unitB->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->unitA->id, $this->unitB->id], $this->scopeOf($target));
    }

    public function test_scoped_admin_cannot_change_scope_of_super_admin_or_core_role_accounts(): void
    {
        $actor = $this->makeAdmin(['organization.access.manage'], $this->unitA);
        $super = $this->superAdmin();
        $pb = $this->makeAdmin([], null, [$this->role('pengurus-besar')]);
        $this->actingAs($actor, 'admin');

        $this->put(route('admin.organization.access.update'), ['admin_id' => $super->id, 'unit_ids' => [$this->unitA->id]])->assertForbidden();
        $this->put(route('admin.organization.access.update'), ['admin_id' => $pb->id, 'unit_ids' => [$this->unitA->id]])->assertForbidden();
        $this->assertSame([], $this->scopeOf($super));
        $this->assertSame([], $this->scopeOf($pb));

        // Halaman Akses Unit pun tidak menawarkan akun-akun itu.
        $this->get(route('admin.organization.access.index'))->assertOk()
            ->assertDontSee($super->username)->assertDontSee($pb->username);
    }

    public function test_account_form_cannot_create_an_account_with_units_outside_scope(): void
    {
        $actor = $this->makeAdmin(['admins.create', 'organization.access.manage'], $this->unitA);
        $this->actingAs($actor, 'admin');

        $payload = [
            'name' => 'Baru', 'username' => 'akun-baru', 'email' => 'baru@example.test',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            'unit_scope_present' => 1,
        ];

        $this->post(route('admin.admins.store'), $payload + ['unit_ids' => [$this->unitB->id]])->assertSessionHasErrors('unit_ids');
        $this->assertDatabaseMissing('admins', ['username' => 'akun-baru']);

        $this->post(route('admin.admins.store'), $payload + ['unit_ids' => [$this->unitA1->id]])->assertSessionHasNoErrors();
        $created = Admin::where('username', 'akun-baru')->firstOrFail();
        $this->assertSame([$this->unitA1->id], $this->scopeOf($created));
    }

    public function test_account_form_edit_cannot_widen_scope_beyond_the_editors_own(): void
    {
        $actor = $this->makeAdmin(['admins.update', 'organization.access.manage'], $this->unitA);
        $target = $this->makeAdmin([]);
        $this->actingAs($actor, 'admin');

        $this->put(route('admin.admins.update', $target), [
            'name' => $target->name, 'username' => $target->username, 'email' => $target->email,
            'unit_scope_present' => 1, 'unit_ids' => [$this->unitB->id],
        ])->assertSessionHasErrors('unit_ids');
        $this->assertSame([], $this->scopeOf($target));
    }

    // ---- 2. akun / role peran tinggi ------------------------------------

    public function test_regular_admin_cannot_edit_or_delete_core_role_accounts(): void
    {
        $actor = $this->makeAdmin(['admins.view', 'admins.update', 'admins.delete']);
        $pb = $this->makeAdmin([], null, [$this->role('pengurus-besar')]);
        $oldHash = $pb->password;
        $this->actingAs($actor, 'admin');

        $this->get(route('admin.admins.edit', $pb))->assertForbidden();
        $this->put(route('admin.admins.update', $pb), [
            'name' => $pb->name, 'username' => $pb->username, 'email' => 'ambil-alih@example.test',
            'password' => 'Another-Secure-Pass-2026!', 'password_confirmation' => 'Another-Secure-Pass-2026!',
        ])->assertForbidden();
        $this->delete(route('admin.admins.destroy', $pb))->assertForbidden();

        $fresh = $pb->fresh();
        $this->assertSame($oldHash, $fresh->password);
        $this->assertNotSame('ambil-alih@example.test', $fresh->email);
        $this->assertTrue($fresh->hasProtectedRole());
    }

    public function test_regular_admin_still_manages_ordinary_accounts(): void
    {
        $actor = $this->makeAdmin(['admins.view', 'admins.update']);
        $target = $this->makeAdmin([]);
        $this->actingAs($actor, 'admin');

        $this->put(route('admin.admins.update', $target), [
            'name' => 'Nama Baru', 'username' => $target->username, 'email' => $target->email, 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.admins.index'));
        $this->assertSame('Nama Baru', $target->fresh()->name);
    }

    public function test_super_admin_can_still_edit_core_role_accounts(): void
    {
        $super = $this->superAdmin();
        $pb = $this->makeAdmin([], null, [$this->role('pengurus-besar')]);
        $this->actingAs($super, 'admin');

        $this->get(route('admin.admins.edit', $pb))->assertOk();
    }

    public function test_regular_admin_cannot_assign_a_role_that_grants_access_to_all_units(): void
    {
        $actor = $this->makeAdmin(['admins.update']);
        $target = $this->makeAdmin([]);
        $wide = $this->role('akses-luas', ['organization.access.all']);
        $this->actingAs($actor, 'admin');

        $this->put(route('admin.admins.update', $target), [
            'name' => $target->name, 'username' => $target->username, 'email' => $target->email, 'roles' => [$wide->id],
        ])->assertForbidden();
        $this->assertFalse($target->fresh()->roles->contains($wide));

        // Dan role itu tidak muncul sebagai pilihan di formulir.
        $this->get(route('admin.admins.edit', $target))->assertOk()->assertDontSee('akses-luas');
    }

    // ---- 3. /iclock: nomor seri saja tidak cukup ------------------------

    private function device(array $attrs = []): FingerprintDevice
    {
        $unit = OrganizationalUnit::query()->where('code', 'A1')->first();
        $school = School::query()->first() ?? School::query()->create([
            'organizational_unit_id' => $unit->id,
            'school_level_id' => SchoolLevel::query()->create(['name' => 'SD', 'slug' => 'sd', 'sort_order' => 1, 'is_active' => true])->id,
            'status' => 'active',
        ]);

        return FingerprintDevice::query()->create($attrs + [
            'serial_number' => 'SNHARD1', 'name' => 'Gerbang', 'school_id' => $school->id, 'is_active' => true,
        ]);
    }

    private function push(string $sn = 'SNHARD1')
    {
        return $this->call('POST', "/iclock/cdata?SN=$sn&table=ATTLOG&Stamp=1", [], [], [], ['CONTENT_TYPE' => 'text/plain'], "101\t2026-10-21 06:55:00\t0\t1\t0\n");
    }

    public function test_active_device_without_ip_restriction_is_refused_when_required(): void
    {
        config(['student_attendance.require_allowed_ips' => true]);
        $device = $this->device();

        $this->push()->assertStatus(403);
        $this->get('/iclock/cdata?SN=SNHARD1&options=all')->assertStatus(403);
        $this->assertSame(0, FingerprintLog::query()->count());
        $this->assertNull($device->fresh()->last_seen_at);
    }

    public function test_active_device_with_ip_restriction_is_accepted_when_required(): void
    {
        config(['student_attendance.require_allowed_ips' => true]);
        $device = $this->device(['allowed_ips' => '127.0.0.0/8']);

        $this->push()->assertOk();
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_requirement_can_be_switched_off_and_does_not_block_unclaimed_devices(): void
    {
        config(['student_attendance.require_allowed_ips' => false]);
        $this->device();
        $this->push()->assertOk();

        // Perangkat baru terdeteksi tetap boleh mendaftar (nonaktif, tanpa log) agar admin bisa mengaturnya.
        config(['student_attendance.require_allowed_ips' => true]);
        $this->get('/iclock/cdata?SN=SNNEW1&options=all')->assertOk();
        $this->assertDatabaseHas('fingerprint_devices', ['serial_number' => 'SNNEW1', 'is_active' => 0]);
    }

    public function test_admin_cannot_activate_a_device_without_ip_restriction_when_required(): void
    {
        config(['student_attendance.require_allowed_ips' => true]);
        $school = $this->device(['serial_number' => 'SNTMP', 'is_active' => false])->school;
        $this->actingAs($this->superAdmin(), 'admin');
        $base = ['serial_number' => 'SNHARD2', 'name' => 'Aula', 'school_id' => $school->id, 'is_active' => 1];

        $this->post(route('admin.academic.fingerprint.devices.store'), $base)->assertSessionHasErrors('allowed_ips');
        $this->assertDatabaseMissing('fingerprint_devices', ['serial_number' => 'SNHARD2']);

        $this->post(route('admin.academic.fingerprint.devices.store'), $base + ['allowed_ips' => '203.0.113.10'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fingerprint_devices', ['serial_number' => 'SNHARD2', 'is_active' => 1]);

        // Menyalakan lewat tombol aktif/nonaktif juga memerlukan batas IP.
        $off = $this->device(['serial_number' => 'SNOFF', 'is_active' => false]);
        $this->post(route('admin.academic.fingerprint.devices.toggle', $off))->assertSessionHas('error');
        $this->assertFalse($off->fresh()->is_active);
    }

    // ---- 4. proxy tepercaya lewat config, bukan env() -------------------

    public function test_trusted_proxies_come_from_config_so_they_survive_config_cache(): void
    {
        Route::get('/_client-ip', fn (Request $request) => $request->ip());
        $headers = ['X-Forwarded-For' => '203.0.113.7'];

        // Tanpa proxy tepercaya header X-Forwarded-For dari klien diabaikan.
        config(['trustedproxy.proxies' => null]);
        $this->withHeaders($headers)->get('/_client-ip')->assertOk()->assertSee('127.0.0.1');

        config(['trustedproxy.proxies' => ['127.0.0.1']]);
        $this->withHeaders($headers)->get('/_client-ip')->assertOk()->assertSee('203.0.113.7');
    }

    public function test_bootstrap_does_not_read_trusted_proxies_through_env(): void
    {
        $this->assertStringNotContainsString("env('TRUSTED_PROXIES')", file_get_contents(base_path('bootstrap/app.php')));
        $this->assertArrayHasKey('proxies', require config_path('trustedproxy.php'));
    }

    // ---- opsi 2: seeder demo, sesi lain, registrasi pelajar --------------

    public function test_sample_student_seeder_is_skipped_in_production(): void
    {
        $this->app['env'] = 'production';
        (new SiswaSeeder)->run();
        $this->assertSame(0, \App\Models\Student::count());
        $this->assertSame(0, StudentAccount::count());

        $this->app['env'] = 'testing';
        $this->seed(SiswaSeeder::class);
        $this->assertSame(360, \App\Models\Student::count());
        $this->assertSame(0, StudentAccount::count(), 'Seeder tidak membuat akun dengan password tertulis di kode.');
    }

    public function test_password_change_by_someone_else_ends_other_sessions(): void
    {
        $admin = $this->makeAdmin(['dashboard.view']);
        $this->actingAs($admin, 'admin');
        $this->get(route('admin.dashboard'))->assertOk();

        // Password diganti dari perangkat lain / oleh admin lain: sesi ini harus berakhir.
        $admin->update(['password' => 'Another-Secure-Pass-2026!']);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_own_password_change_keeps_this_session_but_ends_the_rest(): void
    {
        $admin = $this->makeAdmin(['dashboard.view']);
        $this->actingAs($admin, 'admin');
        $this->get(route('admin.dashboard'))->assertOk();

        $new = 'Another-Secure-Pass-2026!';
        $this->put(route('admin.profile.password'), ['current_password' => self::PASSWORD, 'password' => $new, 'password_confirmation' => $new])
            ->assertSessionHasNoErrors();

        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(\Hash::check($new, $admin->fresh()->password));
    }

    private function registrationStudent(array $attrs = []): Student
    {
        $unit = OrganizationalUnit::query()->where('code', 'A1')->first();
        $school = School::query()->first() ?? School::query()->create([
            'organizational_unit_id' => $unit->id,
            'school_level_id' => SchoolLevel::query()->create(['name' => 'SD', 'slug' => 'sd', 'sort_order' => 1, 'is_active' => true])->id,
            'status' => 'active',
        ]);

        return Student::query()->create($attrs + ['school_id' => $school->id, 'nis' => 'NIS-777', 'name' => 'Siti Data', 'status' => 'active']);
    }

    private function register(string $nis, string $birthDate = '2012-05-17')
    {
        config(['domains.pelajar_registration' => true]);

        return $this->post(route('pelajar.register.store'), [
            'nis' => $nis, 'birth_date' => $birthDate, 'password' => 'Pelajar123x', 'password_confirmation' => 'Pelajar123x',
        ]);
    }

    public function test_registration_requires_a_matching_active_student_and_links_the_account(): void
    {
        $student = $this->registrationStudent(['birth_date' => '2012-05-17']);

        $this->register('NIS-000')->assertSessionHasErrors('nis');
        $this->assertSame(0, StudentAccount::count());

        $this->register('NIS-777')->assertSessionHasNoErrors()->assertRedirect(route('pelajar.dashboard'));
        $account = StudentAccount::where('nis', 'NIS-777')->firstOrFail();
        $this->assertSame($account->id, $student->fresh()->student_account_id);
        $this->assertSame('Siti Data', $account->name);   // nama dari data sekolah, bukan isian formulir
    }

    public function test_registration_is_refused_for_inactive_or_already_linked_students(): void
    {
        $this->registrationStudent(['nis' => 'NIS-LULUS', 'status' => 'graduated', 'birth_date' => '2012-05-17']);
        $taken = StudentAccount::create(['name' => 'Pemilik', 'nis' => 'NIS-777', 'password' => 'Pelajar123x', 'is_active' => true]);
        $this->registrationStudent(['student_account_id' => $taken->id, 'birth_date' => '2012-05-17']);

        $this->register('NIS-LULUS')->assertSessionHasErrors('nis');
        $this->post(route('pelajar.logout'));
        $this->register('NIS-777')->assertSessionHasErrors('nis');
        $this->assertSame(1, StudentAccount::count());
    }

    public function test_registration_needs_the_right_birth_date_and_guesses_are_limited_per_nis(): void
    {
        $this->registrationStudent(['birth_date' => '2012-05-17']);
        $this->registrationStudent(['nis' => 'NIS-KOSONG', 'birth_date' => null]);

        $this->register('NIS-777', '2012-05-18')->assertSessionHasErrors('nis');
        $this->register('NIS-KOSONG', '2012-05-17')->assertSessionHasErrors('nis');   // tanpa tanggal lahir di data: tak bisa diverifikasi
        $this->post(route('pelajar.register.store'), ['nis' => 'NIS-777', 'password' => 'Pelajar123x', 'password_confirmation' => 'Pelajar123x'])
            ->assertSessionHasErrors('birth_date');
        $this->assertSame(0, StudentAccount::count());

        // Pesan sama untuk NIS yang tidak ada: keberadaan NIS tidak bocor.
        $unknown = $this->register('NIS-TIDAK-ADA')->baseResponse->getSession()->get('errors')->get('nis');
        $wrong = $this->register('NIS-777', '2012-05-19')->baseResponse->getSession()->get('errors')->get('nis');
        $this->assertSame($unknown, $wrong);

        // Setelah 5 tebakan salah untuk NIS yang sama, NIS itu ditutup walau tanggal lahirnya kini benar.
        $this->register('NIS-777', '2012-05-20');
        $this->register('NIS-777', '2012-05-21');
        $this->register('NIS-777', '2012-05-22');
        $this->register('NIS-777', '2012-05-17')->assertSessionHasErrors('nis');
        $this->assertSame(0, StudentAccount::count());
    }
}
