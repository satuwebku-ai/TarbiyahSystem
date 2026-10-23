<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Enums\EmployeeStatus;
use App\Models\AdminAuditLog;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PegawaiPortalAccess;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Portal Pegawai/Guru (guard "pegawai"). Dijalankan pada mode routing "path" (bawaan). */
class PegawaiPortalTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Test-Password-2026!';

    private OrganizationalUnit $unit;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $this->unit = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        AttendancePolicy::create([
            'name' => 'Umum', 'work_days' => [1, 2, 3, 4, 5, 6], 'work_start' => '07:00', 'work_end' => '15:00',
            'late_tolerance_minutes' => 15, 'is_active' => true,
        ]);

        Carbon::setTestNow('2026-10-21 05:00:00'); // Rabu 12:00 WIB
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- akses & login --------------------------------------------------

    public function test_guest_is_sent_to_the_pegawai_login_not_the_admin_or_pelajar_one(): void
    {
        $this->get(route('pegawai.dashboard'))->assertRedirect(route('pegawai.login'));
        $this->get(route('pegawai.history'))->assertRedirect(route('pegawai.login'));
        $this->get(route('pegawai.login'))->assertOk()->assertSee('Portal Pegawai');
    }

    public function test_linked_employee_logs_in_and_only_the_pegawai_guard_is_authenticated(): void
    {
        [$admin] = $this->linkedEmployee();

        $this->post(route('pegawai.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])
            ->assertRedirect(route('pegawai.dashboard'));

        $this->assertAuthenticatedAs($admin, 'pegawai');
        $this->assertGuest('admin');
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertTrue(AdminAuditLog::where('event', 'pegawai.login_success')->where('actor_name', $admin->name)->exists());

        // Login portal TIDAK membuka panel pengelola.
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_a_panel_session_does_not_open_the_portal(): void
    {
        $admin = \App\Models\Admin::create(['name' => 'Panel', 'username' => 'panel', 'email' => 'panel@example.test', 'password' => self::PASSWORD, 'is_active' => true]);

        $this->actingAs($admin, 'admin');
        $this->get(route('pegawai.dashboard'))->assertRedirect(route('pegawai.login'));
    }

    public function test_login_is_refused_when_account_is_not_entitled_to_the_portal(): void
    {
        $cases = [
            'tanpa data pegawai' => fn () => $this->makeAdmin(['attendance.self']),
            'tanpa izin' => fn () => $this->linkedAdmin([], EmployeeStatus::Active),
            'pegawai pensiun' => fn () => $this->linkedAdmin(['attendance.self'], EmployeeStatus::Retired),
            'pegawai mengundurkan diri' => fn () => $this->linkedAdmin(['attendance.self'], EmployeeStatus::Resigned),
        ];

        foreach ($cases as $label => $make) {
            $admin = $make();
            $this->post(route('pegawai.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])
                ->assertSessionHasErrors('username');
            $this->assertGuest('pegawai');
        }

        $inactive = $this->linkedEmployee()[0];
        $inactive->update(['is_active' => false]);
        $this->post(route('pegawai.login.store'), ['username' => $inactive->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest('pegawai');

        [$ok] = $this->linkedEmployee();
        $this->post(route('pegawai.login.store'), ['username' => $ok->username, 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->assertGuest('pegawai');
    }

    public function test_on_leave_employee_can_log_in_but_cannot_check_in(): void
    {
        [$admin, $employee] = $this->linkedEmployee();
        $employee->update(['status' => EmployeeStatus::OnLeave]);

        $this->post(route('pegawai.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertRedirect(route('pegawai.dashboard'));
        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('attendance');
        $this->assertSame(0, AttendanceRecord::count());
    }

    // ---- sesi terus dievaluasi -----------------------------------------

    public function test_session_ends_when_employee_retires_or_permission_is_revoked_or_account_deactivated(): void
    {
        foreach ([
            fn (User $a, Employee $e) => $e->update(['status' => EmployeeStatus::Retired]),
            fn (User $a, Employee $e) => $a->roles()->first()->permissions()->detach(),
            fn (User $a, Employee $e) => $e->update(['user_id' => null]),
            fn (User $a, Employee $e) => $a->update(['is_active' => false]),
        ] as $i => $revoke) {
            [$admin, $employee] = $this->linkedEmployee();
            $this->actingAs($admin, 'pegawai');
            $this->get(route('pegawai.dashboard'))->assertOk();

            $revoke($admin, $employee);
            // cache izin per-instance model: muat ulang seperti request baru
            $this->actingAs(User::find($admin->id), 'pegawai');

            $this->get(route('pegawai.dashboard'))->assertRedirect(route('pegawai.login'));
            $this->assertGuest('pegawai');
            $this->flushSession();
        }
    }

    public function test_idle_session_expires(): void
    {
        [$admin] = $this->linkedEmployee();
        $this->actingAs($admin, 'pegawai')->get(route('pegawai.dashboard'))->assertOk();

        Carbon::setTestNow(now()->addHours(5));
        $this->get(route('pegawai.dashboard'))->assertRedirect(route('pegawai.login'));
        $this->assertGuest('pegawai');
    }

    // ---- absen & koreksi sendiri ---------------------------------------

    public function test_employee_checks_in_and_out_for_themselves_only(): void
    {
        [$admin, $employee] = $this->linkedEmployee();
        [, $other] = $this->linkedEmployee();
        $this->actingAs($admin, 'pegawai');

        Carbon::setTestNow('2026-10-21 00:10:00'); // 07:10 WIB
        $this->get(route('pegawai.dashboard'))->assertOk()->assertSee($employee->name)->assertSee('Sekolah A');
        $this->post(route('pegawai.check-in'), ['employee_id' => $other->id])->assertSessionHasNoErrors();

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame($employee->id, $record->employee_id);
        $this->assertSame(0, AttendanceRecord::where('employee_id', $other->id)->count());
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame('2026-10-21 00:10:00', $record->check_in_at->format('Y-m-d H:i:s'));

        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('attendance');

        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->withSession(['pegawai_last_activity' => now()->timestamp])->post(route('pegawai.check-out'))->assertSessionHasNoErrors();
        $this->assertSame(470, $record->fresh()->work_minutes); // 07:10 -> 15:00 WIB
    }

    public function test_correction_is_filed_for_the_logged_in_employee_and_waits_for_review(): void
    {
        [$admin, $employee] = $this->linkedEmployee();
        [, $other] = $this->linkedEmployee();
        $this->actingAs($admin, 'pegawai');

        $this->post(route('pegawai.corrections.store'), [
            'attendance_date' => '2026-10-20', 'requested_check_in' => '07:00', 'reason' => 'Lupa check-in', 'employee_id' => $other->id,
        ])->assertSessionHasNoErrors();

        $c = AttendanceCorrection::firstOrFail();
        $this->assertSame($employee->id, $c->employee_id);
        $this->assertSame(CorrectionStatus::Pending, $c->status);
        $this->assertSame($admin->id, $c->requested_by_user_id);
        $this->assertSame(0, AttendanceRecord::count()); // belum jadi absensi sebelum disetujui

        $this->post(route('pegawai.corrections.store'), ['attendance_date' => '2026-09-01', 'requested_check_in' => '07:00', 'reason' => 'Terlalu lama'])
            ->assertSessionHasErrors('attendance_date');
        $this->post(route('pegawai.corrections.store'), ['attendance_date' => '2026-10-19', 'reason' => 'x'])->assertSessionHasErrors('reason');
    }

    public function test_history_shows_only_own_records_and_validates_month(): void
    {
        [$admin, $employee] = $this->linkedEmployee();
        [, $other] = $this->linkedEmployee(name: 'Pegawai Lain');
        $this->actingAs($admin, 'pegawai');

        AttendanceRecord::create([
            'employee_id' => $employee->id, 'organizational_unit_id' => $this->unit->id, 'attendance_date' => '2026-10-19',
            'check_in_at' => '2026-10-19 00:05:00', 'status' => AttendanceStatus::Present, 'source' => 'web',
        ]);
        AttendanceRecord::create([
            'employee_id' => $other->id, 'organizational_unit_id' => $this->unit->id, 'attendance_date' => '2026-10-20',
            'check_in_at' => '2026-10-20 00:40:00', 'status' => AttendanceStatus::Late, 'late_minutes' => 40, 'source' => 'web',
        ]);

        $r = $this->get(route('pegawai.history', ['month' => '2026-10']))->assertOk();
        $r->assertSee('07:05');                 // milik sendiri
        $r->assertDontSee('07:40');             // milik orang lain
        $r->assertDontSee('Pegawai Lain');

        $this->get(route('pegawai.history', ['month' => 'bukan-bulan']))->assertSessionHasErrors('month');
        $this->get(route('pegawai.history', ['month' => '2030-01']))->assertOk()->assertSee('Oct 2026'); // bulan depan dipangkas ke bulan ini
    }

    // ---- profil & reset -------------------------------------------------

    public function test_all_portal_pages_render(): void
    {
        $this->get(route('pegawai.password.request'))->assertOk()->assertSee('Portal Pegawai');
        $this->get(route('pegawai.password.reset', ['token' => 'abc', 'email' => 'a@example.test']))->assertOk();

        [$admin] = $this->linkedEmployee();
        $this->actingAs($admin, 'pegawai');
        $this->get(route('pegawai.profile.edit'))->assertOk()->assertSee('Ubah kata sandi')->assertSee($admin->username);
        $this->put(route('pegawai.profile.update'), ['name' => 'Nama Baru', 'email' => $admin->email])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', $admin->fresh()->name);
        $this->post(route('pegawai.logout'))->assertRedirect(route('pegawai.login'));
        $this->assertGuest('pegawai');
        $this->assertTrue(AdminAuditLog::where('event', 'pegawai.logout')->exists());
    }

    public function test_password_change_requires_current_password(): void
    {
        [$admin] = $this->linkedEmployee();
        $this->actingAs($admin, 'pegawai');

        $this->put(route('pegawai.profile.password'), ['current_password' => 'salah', 'password' => 'Baru-Sekali-2026!x', 'password_confirmation' => 'Baru-Sekali-2026!x'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('pegawai.profile.password'), ['current_password' => self::PASSWORD, 'password' => 'Baru-Sekali-2026!x', 'password_confirmation' => 'Baru-Sekali-2026!x'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(\Hash::check('Baru-Sekali-2026!x', $admin->fresh()->password));
    }

    public function test_reset_link_requested_from_the_portal_points_to_the_portal(): void
    {
        Notification::fake();
        [$admin] = $this->linkedEmployee();

        $this->post(route('pegawai.password.email'), ['email' => $admin->email])->assertSessionHas('status');

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $n) use ($admin) {
            return str_contains($n->toMail($admin)->actionUrl, '/pegawai/reset-password/');
        });
    }

    public function test_default_pegawai_role_has_only_the_self_service_permission(): void
    {
        $role = app(PegawaiPortalAccess::class)->defaultRole();
        $this->assertSame(['attendance.self'], $role->permissions()->pluck('name')->all());
    }

    // ---- helper ---------------------------------------------------------

    private function linkedAdmin(array $permissions, EmployeeStatus $status): User
    {
        $admin = $this->makeAdmin($permissions);
        $this->makeEmployee($admin, $status);

        return $admin;
    }

    private function linkedEmployee(?string $name = null): array
    {
        $admin = $this->makeAdmin(['attendance.self']);

        return [$admin, $this->makeEmployee($admin, EmployeeStatus::Active, $name)];
    }

    private function makeEmployee(User $admin, EmployeeStatus $status = EmployeeStatus::Active, ?string $name = null): Employee
    {
        $n = ++$this->seq;
        $employee = Employee::create([
            'employee_number' => "E-$n", 'name' => $name ?? "Pegawai $n", 'employee_type' => 'teacher', 'status' => $status, 'user_id' => $admin->id,
        ]);
        $position = OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $this->unit->id, 'name' => 'Guru'], ['is_active' => true]);
        $employee->assignments()->create([
            'organizational_unit_id' => $this->unit->id, 'organizational_position_id' => $position->id, 'is_primary' => true, 'start_date' => '2026-01-01',
        ]);

        return $employee;
    }

    private function makeAdmin(array $permissions): User
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => "role-$n", 'label' => "Role $n", 'guard_name' => 'pegawai']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'pegawai'], ['label' => $p])->id);
        }
        $admin = User::create(['name' => "Admin $n", 'username' => "pegawai$n", 'email' => "pegawai$n@example.test", 'password' => self::PASSWORD, 'is_active' => true]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }
}
