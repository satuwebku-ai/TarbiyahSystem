<?php

namespace Tests\Feature;

use App\Helpers\AttendanceClock;
use App\Models\Admin;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\FingerprintLog;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\StudentAttendanceRecord;
use App\Models\User;
use App\Models\WorkProgram;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\DemoKepegawaianSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Yayasan#2026Demo';

    private function seedDemo(): void
    {
        $this->seed(DemoSeeder::class);
    }

    public function test_hierarchy_is_pb_then_komwil_then_komda_then_three_schools_each(): void
    {
        $this->seedDemo();

        $pb = OrganizationalUnit::where('code', 'PB-YAYASAN')->firstOrFail();
        $this->assertNull($pb->parent_id);

        $komwils = OrganizationalUnit::whereHas('type', fn ($q) => $q->where('slug', 'komwil'))->get();
        $this->assertCount(2, $komwils);
        $komwils->each(fn ($u) => $this->assertSame($pb->id, $u->parent_id));

        $komdas = OrganizationalUnit::whereHas('type', fn ($q) => $q->where('slug', 'komda'))->get();
        $this->assertCount(4, $komdas);
        foreach ($komdas as $komda) {
            $this->assertContains($komda->parent_id, $komwils->pluck('id'));
            $levels = School::whereHas('unit', fn ($q) => $q->where('parent_id', $komda->id))->with('level')->get()->pluck('level.slug')->sort()->values()->all();
            $this->assertSame(['sd', 'sma', 'smp'], $levels, $komda->name);
        }

        $this->assertDatabaseHas('organizational_units', ['code' => 'PB-MAJELIS']);
    }

    public function test_every_student_has_a_guardian_one_active_enrollment_and_one_class(): void
    {
        $this->seedDemo();

        $this->assertSame(360, Student::count());
        $this->assertSame(0, Student::doesntHave('guardians')->count());
        $this->assertSame(0, Student::whereDoesntHave('enrollments.placements', fn ($q) => $q->where('status', 'active'))->count());
        $this->assertSame(360, DB::table('student_enrollments')->count());
        $this->assertGreaterThan(0, Employee::where('employee_type', 'teacher')->count());
        $this->assertGreaterThan(0, Employee::where('employee_type', 'staff')->count());
    }

    public function test_running_the_seeder_again_does_not_duplicate_anything(): void
    {
        $this->seedDemo();

        $tables = ['organizational_units', 'organizational_positions', 'organizational_assignments', 'schools', 'employees', 'employee_assignments',
            'students', 'student_guardians', 'student_enrollments', 'student_class_placements', 'classrooms', 'admins', 'users', 'user_unit_access',
            'roles', 'permission_role', 'student_accounts', 'attendance_records', 'attendance_holidays', 'attendance_corrections',
            'student_attendance_sessions', 'student_attendance_records', 'fingerprint_devices', 'fingerprint_logs', 'student_attendance_settings',
            'work_programs', 'work_activities', 'activity_budget_items', 'activity_participants', 'activity_reports', 'activity_approvals'];
        $before = array_map(fn ($t) => DB::table($t)->count(), $tables);

        $this->seedDemo();

        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->count(), $tables));
    }

    public function test_school_head_and_teacher_are_pegawai_accounts_linked_to_employee_data(): void
    {
        $this->seedDemo();

        $head = User::where('username', 'kepsek.sd.sulteng.pal')->firstOrFail();
        $this->assertNotNull($head->employee, 'Kepala sekolah harus terhubung ke data pegawai.');
        $this->assertNotNull(User::where('username', 'guru.sd.sulteng.pal')->firstOrFail()->employee);

        $this->post(route('pegawai.login.store'), ['username' => 'kepsek.sd.sulteng.pal', 'password' => self::PASSWORD])
            ->assertRedirect(route('pegawai.dashboard'));
        $this->assertAuthenticatedAs($head, 'pegawai');
        $this->assertGuest('admin');
        $this->get(route('pegawai.dashboard'))->assertOk();
    }

    public function test_komda_admin_only_sees_employees_inside_their_own_komda(): void
    {
        $this->seedDemo();
        $komda = Admin::where('username', 'komda.sulteng.pal')->firstOrFail();

        $this->post(route('admin.login.store'), ['username' => 'komda.sulteng.pal', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($komda, 'admin');

        $own = Employee::where('employee_number', DemoKepegawaianSeeder::number('SD-SULTENG-PAL', 2))->firstOrFail();
        $other = Employee::where('employee_number', DemoKepegawaianSeeder::number('SD-SULSEL-MKS', 2))->firstOrFail();
        $this->get(route('admin.employees.show', $own))->assertOk();
        $this->get(route('admin.employees.show', $other))->assertForbidden();
    }

    public function test_demo_student_account_is_linked_to_a_real_student(): void
    {
        $this->seedDemo();

        $account = StudentAccount::where('nis', '1234567890')->firstOrFail();
        $student = $account->student;
        $this->assertNotNull($student, 'Akun pelajar demo harus terhubung ke data pelajar.');
        $this->assertSame('1234567890', $student->nis);
        $this->assertSame(1, StudentAccount::count());
    }

    public function test_employee_attendance_covers_recent_workdays_and_follows_the_policy(): void
    {
        $this->seedDemo();

        $today = AttendanceClock::today()->toDateString();
        $this->assertSame(0, AttendanceRecord::whereDate('attendance_date', $today)->count(), 'Hari ini dibiarkan kosong untuk dicoba langsung.');
        $this->assertGreaterThanOrEqual(19, AttendanceRecord::distinct()->count('attendance_date'));

        foreach (AttendanceRecord::whereIn('status', ['present', 'late'])->limit(400)->get() as $record) {
            $in = AttendanceClock::toLocal($record->check_in_at)->format('H:i');
            $out = AttendanceClock::toLocal($record->check_out_at)->format('H:i');
            if ($record->status->value === 'late') {
                $this->assertGreaterThan('07:15', $in);
                $this->assertGreaterThan(15, $record->late_minutes);
            } else {
                $this->assertLessThanOrEqual('07:15', $in);
                $this->assertSame(0, $record->late_minutes);
            }
            $this->assertGreaterThanOrEqual('15:00', $out);
            $this->assertSame((int) $record->check_in_at->diffInMinutes($record->check_out_at), $record->work_minutes);
        }

        $this->assertSame(0, AttendanceRecord::whereIn('status', ['sick', 'permit', 'leave', 'absent'])->whereNotNull('check_in_at')->count());
    }

    public function test_three_correction_requests_are_waiting_or_reviewed(): void
    {
        $this->seedDemo();

        $this->assertSame(3, AttendanceCorrection::count());
        $this->assertSame(2, AttendanceCorrection::where('status', 'pending')->count());
        $rejected = AttendanceCorrection::where('status', 'rejected')->firstOrFail();
        $this->assertNotNull($rejected->review_note);
        $this->assertNotNull($rejected->reviewed_by_admin_id);
        $this->assertSame(0, AttendanceCorrection::whereNull('attendance_record_id')->count());
        $this->assertSame('absent', $rejected->record->status->value ?? 'absent');
    }

    public function test_fingerprint_schools_have_devices_pins_and_logs_matching_attendance(): void
    {
        $this->seedDemo();

        $this->assertSame(3, DB::table('fingerprint_devices')->where('is_active', true)->whereNotNull('allowed_ips')->count());
        $this->assertGreaterThan(0, FingerprintLog::count());
        $this->assertSame(FingerprintLog::count(), StudentAttendanceRecord::where('source', 'fingerprint')->count());
        $this->assertSame(0, FingerprintLog::whereNull('student_id')->count());

        // PIN unik per sekolah dan setiap ketukan menunjuk ke siswa pemilik PIN itu.
        foreach (FingerprintLog::with('student')->limit(200)->get() as $log) {
            $this->assertSame($log->pin, $log->student->fingerprint_pin);
            $this->assertSame($log->school_id, $log->student->school_id);
        }
        $this->assertSame(
            (int) DB::table('fingerprint_logs')->count(),
            (int) DB::table('fingerprint_devices')->sum('total_logs'),
        );
    }

    public function test_work_programs_cover_every_approval_state_and_have_reports(): void
    {
        $this->seedDemo();

        $komdaStatuses = WorkProgram::whereHas('unit', fn ($q) => $q->whereHas('type', fn ($t) => $t->where('slug', 'komda')))->pluck('status')->sort()->values()->all();
        $this->assertSame(['approved', 'draft', 'rejected', 'submitted'], $komdaStatuses);
        $this->assertSame(1, DB::table('activity_reports')->where('status', 'reviewed')->count());
        $this->assertGreaterThan(0, DB::table('work_activities')->where('status', 'in_progress')->count());
        $this->assertGreaterThan(0, DB::table('work_activities')->where('status', 'planned')->where('planned_start_at', '>', now())->count());
    }

    public function test_demo_seeder_is_skipped_in_production(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            // Dipanggil langsung: `db:seed` meminta konfirmasi interaktif di production.
            (new DemoSeeder)->setContainer($this->app)->run();
            (new DatabaseSeeder)->setContainer($this->app)->run();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }

        $this->assertSame(0, Student::count());
        $this->assertSame(0, StudentAccount::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, School::count());
        $this->assertSame(1, Admin::count(), 'Hanya Super Admin dari AdminSeeder.');
        $this->assertSame(0, Admin::where('username', 'like', '%.%')->where('email', 'like', '%@demo.example.test')->count());
    }

    public function test_old_demo_data_is_detected_and_left_untouched(): void
    {
        // Menyerupai database yang pernah diisi YayasanSeeder lama (nomor pegawai PEG-01-01).
        Employee::create(['employee_number' => 'PEG-01-01', 'name' => 'Data Lama', 'employee_type' => 'teacher', 'status' => 'active']);

        $this->seedDemo();

        $this->assertSame(1, Employee::count());
        $this->assertSame(0, OrganizationalUnit::count());
        $this->assertSame(0, Student::count());
    }

    public function test_rbac_seeder_gives_organization_roles_valid_permissions_and_keeps_later_edits(): void
    {
        $this->seed(RbacSeeder::class);

        foreach (['pengurus-besar' => RbacSeeder::PB, 'komisaris-wilayah' => RbacSeeder::KOMWIL, 'komisaris-daerah' => RbacSeeder::KOMDA, 'kepala-sekolah' => RbacSeeder::KEPSEK] as $name => $expected) {
            $role = Role::where('guard_name', 'admin')->where('name', $name)->firstOrFail();
            $this->assertEqualsCanonicalizing(array_unique($expected), $role->permissions()->pluck('name')->all(), "Izin role {$name} harus persis daftar di seeder (nama izin salah ketik tidak akan lolos).");
        }

        // Perubahan izin oleh pengelola tidak boleh tertimpa saat seeder dijalankan lagi (mis. saat deploy).
        $role = Role::where('name', 'kepala-sekolah')->where('guard_name', 'admin')->firstOrFail();
        $role->permissions()->sync(Permission::where('guard_name', 'admin')->where('name', 'dashboard.view')->pluck('id'));
        $this->seed(RbacSeeder::class);
        $this->assertSame(['dashboard.view'], $role->permissions()->pluck('name')->all());
    }
}
