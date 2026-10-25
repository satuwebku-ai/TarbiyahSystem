<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\Employee;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserUnitAccess;
use App\Services\OrganizationAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 11 — keamanan & audit: login/logout, akses berbasis role dan unit (diperiksa di backend),
 * unggahan dokumen, perpindahan unit, dan jejak audit untuk perubahan penting.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Test-Password-2026!';

    private OrganizationalUnit $root;
    private OrganizationalUnit $unitA;
    private OrganizationalUnit $unitB;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $this->root = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'R', 'name' => 'Yayasan', 'status' => 'active']);
        $this->unitA = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $this->root->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        $this->unitB = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $this->root->id, 'code' => 'B', 'name' => 'Sekolah B', 'status' => 'active']);
    }

    // ---- 1. login & logout ----------------------------------------------

    public function test_admin_login_logout_and_audit(): void
    {
        $admin = $this->makeAdmin(['dashboard.view']);

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->assertGuest('admin');

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertTrue(AdminAuditLog::where('event', 'admin.login_success')->where('actor_admin_id', $admin->id)->exists());

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertTrue(AdminAuditLog::where('event', 'admin.logout')->where('actor_admin_id', $admin->id)->exists());

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_failed_login_message_does_not_reveal_whether_the_account_exists(): void
    {
        $admin = $this->makeAdmin([]);

        $known = $this->from(route('admin.login'))->post(route('admin.login.store'), ['username' => $admin->username, 'password' => 'salah']);
        $unknown = $this->from(route('admin.login'))->post(route('admin.login.store'), ['username' => 'tidak-ada', 'password' => 'salah']);

        $known->assertSessionHasErrors('username');
        $unknown->assertSessionHasErrors('username');
        $this->assertSame(
            $known->baseResponse->getSession()->get('errors')->get('username'),
            $unknown->baseResponse->getSession()->get('errors')->get('username'),
        );
    }

    public function test_inactive_account_cannot_log_in_even_with_the_right_password(): void
    {
        $admin = $this->makeAdmin([]);
        $admin->update(['is_active' => false]);

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest('admin');
    }

    public function test_login_is_locked_after_too_many_failures_even_with_the_right_password(): void
    {
        Cache::flush();
        $admin = $this->makeAdmin([]);
        AdminSecuritySetting::query()->delete();
        AdminSecuritySetting::create(['max_login_attempts' => 3, 'lockout_minutes' => 5, 'session_idle_minutes' => 30]);
        AdminSecuritySetting::clearCache();

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => 'salah']);
        }
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest('admin');
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $admin = $this->makeAdmin([]);
        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_idle_admin_session_is_ended_and_audited(): void
    {
        $admin = $this->makeAdmin(['dashboard.view']);
        $idle = AdminSecuritySetting::current()->session_idle_minutes * 60;

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_last_activity' => now()->timestamp - $idle - 5])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        $this->assertTrue(AdminAuditLog::where('event', 'admin.session_expired')->exists());
    }

    // ---- 2. akses berdasarkan role (backend, bukan hanya menu) ----------

    public function test_every_panel_route_is_behind_login_and_every_authenticated_one_checks_a_permission(): void
    {
        // Halaman yang sengaja hanya perlu login (milik sendiri). Selain ini wajib memakai middleware "can:".
        $loginOnly = ['admin.logout', 'admin.profile.edit', 'admin.profile.update', 'admin.profile.password',
            'admin.notifications.index', 'admin.notifications.read-all', 'admin.notifications.open',
            'admin.profile.two-factor.enable', 'admin.profile.two-factor.confirm', 'admin.profile.two-factor.recovery-codes', 'admin.profile.two-factor.disable'];
        $public = ['admin.login', 'admin.login.store', 'admin.setup', 'admin.setup.store', 'admin.password.request',
            'admin.password.email', 'admin.password.reset', 'admin.password.store',
            'admin.two-factor.challenge', 'admin.two-factor.challenge.store'];

        $unprotected = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'admin.') || in_array($name, $public, true)) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $hasAuth = (bool) array_filter($middleware, fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:')));
            $hasCan = (bool) array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:'));

            if (! $hasAuth || (! $hasCan && ! in_array($name, $loginOnly, true))) {
                $unprotected[] = $name;
            }
        }

        $this->assertSame([], $unprotected, 'Route panel tanpa login/izin: '.implode(', ', $unprotected));
    }

    public function test_pegawai_and_pelajar_routes_other_than_login_flows_require_login(): void
    {
        $unprotected = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! preg_match('/^(pegawai|pelajar)\./', $name) || preg_match('/\.(login|login\.store|register|register\.store|password\..+|two-factor\.challenge(\.store)?|pwa\.(manifest|sw|offline))$/', $name)) {
                continue;
            }
            $hasAuth = (bool) array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:')));
            if (! $hasAuth) {
                $unprotected[] = $name;
            }
        }

        $this->assertSame([], $unprotected, 'Route portal tanpa login: '.implode(', ', $unprotected));
    }

    public function test_admin_without_permission_is_forbidden_on_destructive_actions_even_when_posting_directly(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-1');
        $viewer = $this->makeAdmin(['employees.view', 'dashboard.view']);
        $target = $this->makeAdmin([]);
        $role = Role::create(['name' => 'tmp', 'label' => 'Tmp', 'guard_name' => 'admin']);
        $this->actingAs($viewer, 'admin');

        $this->post(route('admin.employees.status.store', $employee), ['status' => 'resigned', 'effective_date' => '2026-10-01'])->assertForbidden();
        $this->delete(route('admin.admins.destroy', $target))->assertForbidden();
        $this->delete(route('admin.roles.destroy', $role))->assertForbidden();
        $this->get(route('admin.security.activity'))->assertForbidden();

        $this->assertSame(EmployeeStatus::Active, $employee->fresh()->status);
        $this->assertTrue(Admin::whereKey($target->id)->exists());
        $this->assertTrue(Role::whereKey($role->id)->exists());
    }

    // ---- 3. akses berdasarkan unit ---------------------------------------

    public function test_scoped_admin_is_blocked_from_every_action_on_an_employee_of_another_unit(): void
    {
        Storage::fake('local');
        $foreign = $this->makeEmployee($this->unitB, 'P-B');
        $document = $foreign->documents()->create(['title' => 'SK', 'path' => 'employee-documents/x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);
        Storage::disk('local')->put($document->path, 'data');
        $assignment = $foreign->assignments()->firstOrFail();

        $this->actingAs($this->makeAdmin($this->employeePermissions(), $this->unitA), 'admin');

        $this->get(route('admin.employees.show', $foreign))->assertForbidden();
        $this->get(route('admin.employees.edit', $foreign))->assertForbidden();
        $this->put(route('admin.employees.update', $foreign), ['employee_number' => 'X', 'name' => 'Diubah', 'employee_type' => 'teacher'])->assertForbidden();
        $this->post(route('admin.employees.status.store', $foreign), ['status' => 'resigned', 'effective_date' => '2026-10-01'])->assertForbidden();
        $this->put(route('admin.employees.account.update', $foreign), ['user_id' => null])->assertForbidden();
        $this->get(route('admin.employees.assignments.create', $foreign))->assertForbidden();
        $this->patch(route('admin.employees.assignments.end', [$foreign, $assignment]), ['end_date' => '2026-10-01'])->assertForbidden();
        $this->get(route('admin.employees.documents.download', [$foreign, $document]))->assertForbidden();
        $this->delete(route('admin.employees.documents.destroy', [$foreign, $document]))->assertForbidden();

        $this->assertSame('Pegawai P-B', $foreign->fresh()->name);
        $this->assertNull($assignment->fresh()->end_date);
        $this->assertTrue($foreign->documents()->whereKey($document->id)->exists());
        $this->assertSame(0, AdminAuditLog::where('event', 'like', 'employee.%')->count(), 'Aksi yang ditolak tidak boleh meninggalkan jejak perubahan.');
    }

    public function test_scoped_admin_cannot_assign_an_employee_to_a_unit_outside_their_scope(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-A');
        $this->actingAs($this->makeAdmin($this->employeePermissions(), $this->unitA), 'admin');

        $this->post(route('admin.employees.assignments.store', $employee), [
            'organizational_unit_id' => $this->unitB->id, 'start_date' => '2026-10-01',
        ])->assertForbidden();

        $this->assertSame(1, $employee->assignments()->count());
    }

    public function test_moving_a_unit_moves_the_visibility_of_the_whole_subtree(): void
    {
        $type = $this->unitA->type_id;
        $child = OrganizationalUnit::create(['type_id' => $type, 'parent_id' => $this->unitA->id, 'code' => 'A1', 'name' => 'Kelas A1', 'status' => 'active']);
        $grandchild = OrganizationalUnit::create(['type_id' => $type, 'parent_id' => $child->id, 'code' => 'A11', 'name' => 'Ruang A11', 'status' => 'active']);
        $adminA = $this->makeAdmin(['organization.view'], $this->unitA);
        $adminB = $this->makeAdmin(['organization.view'], $this->unitB);
        $access = app(OrganizationAccess::class);

        $this->assertTrue($access->canAccess($adminA, $grandchild->id));
        $this->assertFalse($access->canAccess($adminB, $grandchild->id));

        $super = $this->makeAdmin(['organization.view', 'organization.units.manage', 'organization.access.all']);
        $this->actingAs($super, 'admin')->put(route('admin.organization.units.update', $child), [
            'type_id' => $type, 'parent_id' => $this->unitB->id, 'code' => 'A1', 'name' => 'Kelas A1', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($access->canAccess($adminA, $grandchild->id));
        $this->assertTrue($access->canAccess($adminB, $grandchild->id));
        $this->assertTrue(AdminAuditLog::where('event', 'organization.unit_updated')->where('subject_id', (string) $child->id)->exists());
    }

    public function test_a_unit_cannot_be_moved_under_its_own_descendant(): void
    {
        $child = OrganizationalUnit::create(['type_id' => $this->unitA->type_id, 'parent_id' => $this->unitA->id, 'code' => 'A1', 'name' => 'Kelas A1', 'status' => 'active']);
        $this->actingAs($this->makeAdmin(['organization.view', 'organization.units.manage', 'organization.access.all']), 'admin');

        $this->put(route('admin.organization.units.update', $this->unitA), [
            'type_id' => $this->unitA->type_id, 'parent_id' => $child->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active',
        ])->assertSessionHasErrors('parent_id');

        $this->assertSame($this->root->id, $this->unitA->fresh()->parent_id);
    }

    public function test_unit_search_works_on_the_configured_database(): void
    {
        $this->actingAs($this->makeAdmin(['organization.view', 'organization.access.all']), 'admin');

        $this->get(route('admin.organization.units.index', ['search' => 'sekolah a']))
            ->assertOk()->assertSee('Sekolah A')->assertDontSee('Sekolah B');
    }

    // ---- 4. validasi input & unggahan dokumen ----------------------------

    public function test_document_upload_rejects_dangerous_oversized_and_disguised_files(): void
    {
        Storage::fake('local');
        $employee = $this->makeEmployee($this->unitA, 'P-A');
        $this->actingAs($this->makeAdmin($this->employeePermissions(), $this->unitA), 'admin');
        $url = route('admin.employees.documents.store', $employee);

        $this->post($url, ['title' => 'Skrip', 'file' => UploadedFile::fake()->create('shell.php', 5, 'text/x-php')])->assertSessionHasErrors('file');
        $this->post($url, ['title' => 'Exe', 'file' => UploadedFile::fake()->create('a.exe', 5, 'application/octet-stream')])->assertSessionHasErrors('file');
        $this->post($url, ['title' => 'Besar', 'file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post($url, ['title' => '', 'file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')])->assertSessionHasErrors('title');
        $this->post($url, ['title' => 'Tanpa file'])->assertSessionHasErrors('file');

        // Isi PHP berkedok .pdf harus ditolak karena jenis berkas dibaca dari isinya, bukan dari nama.
        $tmp = tempnam(sys_get_temp_dir(), 'sec');
        file_put_contents($tmp, "<?php echo 'x'; ?>");
        $disguised = new UploadedFile($tmp, 'palsu.pdf', 'application/pdf', null, true);
        $this->post($url, ['title' => 'Palsu', 'file' => $disguised])->assertSessionHasErrors('file');

        $this->assertSame(0, $employee->documents()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_valid_document_is_stored_privately_and_audited(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $employee = $this->makeEmployee($this->unitA, 'P-A');
        $admin = $this->makeAdmin($this->employeePermissions(), $this->unitA);
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.employees.documents.store', $employee), [
            'title' => 'SK', 'file' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $document = $employee->documents()->firstOrFail();

        Storage::disk('local')->assertExists($document->path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Dokumen pegawai tidak boleh ada di disk publik.');
        $this->assertTrue(AdminAuditLog::where('event', 'employee.document_uploaded')->where('actor_admin_id', $admin->id)->exists());

        $this->delete(route('admin.employees.documents.destroy', [$employee, $document]));
        $log = AdminAuditLog::where('event', 'employee.document_deleted')->firstOrFail();
        $this->assertSame((string) $employee->id, $log->subject_id);
        $this->assertSame('SK', $log->details['title']);
    }

    public function test_employee_input_is_validated_and_numbers_are_unique(): void
    {
        $this->makeEmployee($this->unitA, 'P-1');
        $position = OrganizationalPosition::create(['organizational_unit_id' => $this->unitB->id, 'name' => 'Guru', 'is_active' => true]);
        $this->actingAs($this->makeAdmin([...$this->employeePermissions(), 'organization.access.all']), 'admin');

        $this->post(route('admin.employees.store'), ['employee_number' => 'P-1', 'name' => 'A', 'employee_type' => 'bukan-tipe', 'email' => 'bukan-email',
            'organizational_unit_id' => $this->unitA->id, 'start_date' => '2026-01-01'])
            ->assertSessionHasErrors(['employee_number', 'name', 'employee_type', 'email']);
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-8', 'name' => 'Budi', 'employee_type' => 'teacher',
            'organizational_unit_id' => $this->unitA->id, 'start_date' => 'bukan-tanggal'])
            ->assertSessionHasErrors('start_date');

        // Jabatan milik unit lain tidak boleh dipasang ke unit ini.
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-9', 'name' => 'Budi', 'employee_type' => 'teacher',
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $position->id, 'start_date' => '2026-01-01'])
            ->assertSessionHasErrors('organizational_position_id');

        $this->assertSame(1, Employee::count());
    }

    // ---- 5. audit log untuk perubahan penting ----------------------------

    public function test_important_employee_and_position_changes_leave_an_audit_trail(): void
    {
        $position = OrganizationalPosition::create(['organizational_unit_id' => $this->unitA->id, 'name' => 'Kepala', 'is_active' => true]);
        $admin = $this->makeAdmin([...$this->employeePermissions(), 'organization.access.all']);
        $account = \App\Models\User::create(['name' => 'Akun Pegawai', 'username' => 'akun.pegawai', 'email' => 'akun.pegawai@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.employees.store'), ['employee_number' => 'P-1', 'name' => 'Budi', 'employee_type' => 'teacher',
            'organizational_unit_id' => $this->unitA->id, 'start_date' => '2026-01-01'])->assertSessionHasNoErrors();
        $employee = Employee::firstOrFail();

        $this->put(route('admin.employees.update', $employee), ['employee_number' => 'P-1', 'name' => 'Budi S', 'employee_type' => 'teacher'])->assertSessionHasNoErrors();

        // Perubahan jabatan: penempatan baru, diubah, lalu diakhiri.
        $this->post(route('admin.employees.assignments.store', $employee), [
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $position->id, 'start_date' => '2026-06-01',
        ])->assertSessionHasNoErrors();
        $assignment = $employee->assignments()->where('organizational_position_id', $position->id)->firstOrFail();
        $this->put(route('admin.employees.assignments.update', [$employee, $assignment]), [
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $position->id, 'start_date' => '2026-06-15',
        ])->assertSessionHasNoErrors();
        $this->patch(route('admin.employees.assignments.end', [$employee, $assignment]), ['end_date' => '2026-09-30'])->assertSessionHasNoErrors();

        $this->put(route('admin.employees.account.update', $employee), ['user_id' => $account->id])->assertSessionHasNoErrors();
        $this->post(route('admin.employees.status.store', $employee), ['status' => 'resigned', 'effective_date' => '2026-10-01', 'reason' => 'Pindah'])->assertSessionHasNoErrors();

        foreach (['employee.created', 'employee.updated', 'employee.assignment_created', 'employee.assignment_updated',
            'employee.assignment_ended', 'employee.account_linked', 'employee.status_changed'] as $event) {
            $this->assertTrue(
                AdminAuditLog::where('event', $event)->where('actor_admin_id', $admin->id)->where('subject_id', (string) $employee->id)->exists(),
                "Audit log {$event} tidak tercatat.",
            );
        }

        $status = AdminAuditLog::where('event', 'employee.status_changed')->firstOrFail();
        $this->assertSame(['from' => 'active', 'to' => 'resigned', 'effective_date' => '2026-10-01'], $status->details);
        $update = AdminAuditLog::where('event', 'employee.assignment_updated')->firstOrFail();
        $this->assertSame('2026-06-01', $update->details['before']['start_date']);
        $this->assertSame('2026-06-15', $update->details['after']['start_date']);
    }

    public function test_invalid_status_change_leaves_no_audit_entry(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-1');
        $this->actingAs($this->makeAdmin([...$this->employeePermissions(), 'organization.access.all']), 'admin');

        $this->post(route('admin.employees.status.store', $employee), ['status' => 'active', 'effective_date' => '2026-10-01'])->assertSessionHasErrors('status');
        $this->post(route('admin.employees.status.store', $employee), ['status' => 'ngawur', 'effective_date' => '2026-10-01'])->assertSessionHasErrors('status');

        $this->assertSame(0, AdminAuditLog::where('event', 'employee.status_changed')->count());
    }

    public function test_organization_assignment_changes_are_audited(): void
    {
        $position = OrganizationalPosition::create(['organizational_unit_id' => $this->unitA->id, 'name' => 'Kepala', 'is_active' => true]);
        $admin = $this->makeAdmin(['organization.view', 'organization.assignments.manage', 'organization.access.all']);
        $this->actingAs($admin, 'admin');

        $employee = \App\Models\Employee::create(['employee_number' => 'PEG-T1', 'name' => 'Pak Kepala', 'employee_type' => 'staff', 'status' => 'active']);
        $employee->assignments()->create(['organizational_unit_id' => $this->unitA->id, 'start_date' => '2026-01-01']);

        $this->post(route('admin.organization.assignments.store'), [
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $position->id,
            'employee_id' => $employee->id, 'start_date' => '2026-01-01',
        ])->assertSessionHasNoErrors();
        $assignment = \App\Models\OrganizationalAssignment::firstOrFail();
        $this->patch(route('admin.organization.assignments.end', $assignment), ['end_date' => '2026-09-30'])->assertSessionHasNoErrors();

        $this->assertTrue(AdminAuditLog::where('event', 'organization.assignment_created')->where('actor_admin_id', $admin->id)->exists());
        $this->assertTrue(AdminAuditLog::where('event', 'organization.assignment_ended')->where('subject_id', (string) $assignment->id)->exists());
    }

    // ---- helper ----------------------------------------------------------

    private function employeePermissions(): array
    {
        return ['employees.view', 'employees.create', 'employees.update', 'employees.status',
            'employees.assignments.manage', 'employees.link_account', 'employees.documents.view', 'employees.documents.manage'];
    }

    private function makeEmployee(OrganizationalUnit $unit, string $number): Employee
    {
        $employee = Employee::create(['employee_number' => $number, 'name' => "Pegawai $number", 'employee_type' => 'teacher', 'status' => EmployeeStatus::Active]);
        $employee->assignments()->create(['organizational_unit_id' => $unit->id, 'is_primary' => true, 'start_date' => '2026-01-01']);

        return $employee;
    }

    private function makeAdmin(array $permissions, ?OrganizationalUnit $scope = null): Admin
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => "role-$n", 'label' => "Role $n", 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }
        $admin = Admin::create(['name' => "Admin $n", 'username' => "sec$n", 'email' => "sec$n@example.test", 'password' => self::PASSWORD, 'is_active' => true]);
        $admin->syncRoles([$role->id]);
        if ($scope) {
            UserUnitAccess::create(['organizational_unit_id' => $scope->id, 'model_type' => Admin::class, 'model_id' => $admin->id, 'created_at' => now()]);
        }

        return $admin;
    }

    public function test_failed_logins_are_written_to_the_audit_log(): void
    {
        Cache::flush();
        $admin = $this->makeAdmin([]);

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => 'salah'])->assertSessionHasErrors('username');

        $entry = AdminAuditLog::where('event', 'admin.login_failed')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertSame($admin->username, $entry->details['identifier']);
        $this->assertSame('invalid_credentials', $entry->details['reason']);
        $this->assertNull($entry->actor_admin_id);
        // Kata sandi yang diketik tidak pernah ikut tercatat.
        $this->assertStringNotContainsString('salah', json_encode($entry->details));
    }

    public function test_many_usernames_from_one_ip_are_blocked_and_logged_once(): void
    {
        Cache::flush();
        config(['admin_security.login_ip_max_failures' => 4]);
        $admin = $this->makeAdmin([]);

        foreach (['a1', 'a2', 'a3', 'a4'] as $name) {
            $this->post(route('admin.login.store'), ['username' => $name, 'password' => 'salah']);
        }

        // Username baru dan password benar milik akun sah pun ditolak dari IP yang sama.
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->post(route('admin.login.store'), ['username' => 'a5', 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->assertGuest('admin');
        $this->assertSame(1, AdminAuditLog::where('event', 'admin.login_throttled')->count());
        $this->assertSame(4, AdminAuditLog::where('event', 'admin.login_failed')->count());
    }

    public function test_a_successful_login_does_not_reset_the_ip_counter(): void
    {
        Cache::flush();
        config(['admin_security.login_ip_max_failures' => 3]);
        $admin = $this->makeAdmin([]);

        foreach (['b1', 'b2'] as $name) {
            $this->post(route('admin.login.store'), ['username' => $name, 'password' => 'salah']);
        }
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertRedirect();
        $this->post(route('admin.logout'));
        $this->post(route('admin.login.store'), ['username' => 'b3', 'password' => 'salah']);

        // 2 gagal + 1 gagal = 3 -> terblokir walau sempat ada login sukses di antaranya.
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest('admin');
    }

    public function test_student_login_also_has_the_ip_limit(): void
    {
        Cache::flush();
        config(['admin_security.login_ip_max_failures' => 3]);

        foreach (['1001', '1002', '1003'] as $nis) {
            $this->post(route('pelajar.login.store'), ['nis' => $nis, 'password' => 'salah']);
        }
        $response = $this->post(route('pelajar.login.store'), ['nis' => '1004', 'password' => 'salah']);

        $response->assertSessionHasErrors('nis');
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('nis'));
        $this->assertSame(0, AdminAuditLog::where('event', 'like', 'pelajar.%')->count());
    }

    public function test_daily_database_backup_is_scheduled(): void
    {
        \Illuminate\Support\Facades\Artisan::call('schedule:list');

        $this->assertStringContainsString('db:backup', \Illuminate\Support\Facades\Artisan::output());
    }
}
