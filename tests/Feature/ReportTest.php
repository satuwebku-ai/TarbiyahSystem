<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Admin;
use App\Models\Classroom;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentAttendanceRecord;
use App\Models\StudentAttendanceSession;
use App\Models\UserUnitAccess;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnitType $schoolType;

    private SchoolLevel $level;

    private OrganizationalUnit $root;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolType = OrganizationalUnitType::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah', 'is_active' => true]);
        $this->level = SchoolLevel::query()->create(['name' => 'SD', 'slug' => 'sd', 'sort_order' => 1, 'is_active' => true]);
        $rootType = OrganizationalUnitType::query()->create(['name' => 'Yayasan', 'slug' => 'yayasan', 'is_active' => true]);
        $this->root = OrganizationalUnit::query()->create([
            'type_id' => $rootType->id, 'code' => 'YAYASAN-UJI', 'name' => 'Yayasan Uji', 'status' => 'active',
        ]);
    }

    public function test_admin_without_report_permission_is_forbidden(): void
    {
        $admin = $this->makeAdmin(['dashboard.view']);

        $this->actingAs($admin, 'admin')->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_report_requires_the_permission_of_its_source_module(): void
    {
        $this->makeSchool('A');
        $viewer = $this->makeAdmin(['reports.view']);

        $this->actingAs($viewer, 'admin')
            ->get(route('admin.reports.show', 'student-attendance'))
            ->assertForbidden();

        $viewer = $this->makeAdmin(['reports.view', 'education.attendance.view'], suffix: 'dua');

        $this->actingAs($viewer, 'admin')
            ->get(route('admin.reports.show', 'student-attendance'))
            ->assertOk();
    }

    public function test_dashboard_only_lists_reports_the_user_may_see(): void
    {
        $viewer = $this->makeAdmin(['reports.view', 'education.schools.view']);

        $this->actingAs($viewer, 'admin')
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Rekap Sekolah &amp; Siswa', false)
            ->assertDontSee('Anggaran &amp; Realisasi', false)
            ->assertDontSee('Rekap Absensi Pegawai');
    }

    public function test_scoped_admin_only_sees_data_inside_their_units(): void
    {
        [$schoolA] = $this->makeSchool('A');
        [$schoolB] = $this->makeSchool('B');
        $this->seedStudentAttendance($schoolA, ['present', 'late']);
        $this->seedStudentAttendance($schoolB, ['absent']);

        $admin = $this->makeAdmin(['reports.view', 'education.attendance.view'], $schoolA->organizational_unit_id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.show', ['report' => 'student-attendance', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Sekolah A')
            ->assertDontSee('Sekolah B');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.show', [
                'report' => 'student-attendance',
                'unit_id' => $schoolB->organizational_unit_id,
            ]))
            ->assertForbidden();
    }

    public function test_student_attendance_totals_and_rate_are_computed_from_the_database(): void
    {
        [$school] = $this->makeSchool('A');
        $this->seedStudentAttendance($school, ['present', 'late', 'sick', 'absent']);

        $service = app(ReportService::class);
        $data = $service->build('student-attendance', null, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame(4, $data['totals']['total']);
        $this->assertSame(1, $data['totals']['present']);
        $this->assertSame(1, $data['totals']['absent']);
        $this->assertEquals(50.0, $data['totals']['rate']); // (hadir + terlambat) / semua
        $this->assertCount(1, $data['rows']);
    }

    public function test_admin_without_unit_access_sees_empty_reports(): void
    {
        [$school] = $this->makeSchool('A');
        $this->seedStudentAttendance($school, ['present']);
        $admin = $this->makeAdmin(['reports.view', 'education.attendance.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.show', ['report' => 'student-attendance', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertDontSee('Sekolah A')
            ->assertSee('Belum ada data');
    }

    public function test_period_outside_the_data_returns_no_rows(): void
    {
        [$school] = $this->makeSchool('A');
        $this->seedStudentAttendance($school, ['present']);

        $data = app(ReportService::class)->build('student-attendance', null, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));

        $this->assertSame([], $data['rows']);
        $this->assertNull($data['totals']);
    }

    public function test_empty_scope_returns_nothing(): void
    {
        [$school] = $this->makeSchool('A');
        $this->seedStudentAttendance($school, ['present']);

        $data = app(ReportService::class)->build('student-attendance', [], Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame([], $data['rows']);
    }

    public function test_budget_report_sums_planned_and_realized_per_program_within_scope(): void
    {
        $unitA = $this->makeUnit('Unit A');
        $unitB = $this->makeUnit('Unit B');
        $this->seedBudget($unitA, 'PRG-A', 1_000_000, 400_000);
        $this->seedBudget($unitB, 'PRG-B', 5_000_000, 5_000_000);

        $service = app(ReportService::class);
        $from = Carbon::parse('2026-10-01');
        $to = Carbon::parse('2026-10-31');

        $all = $service->build('budget', null, $from, $to);
        $this->assertEquals(6_000_000, $all['totals']['planned']);

        $scoped = $service->build('budget', [$unitA->id], $from, $to);
        $this->assertCount(1, $scoped['rows']);
        $this->assertEquals(1_000_000, $scoped['totals']['planned']);
        $this->assertEquals(400_000, $scoped['totals']['realized']);
        $this->assertEquals(600_000, $scoped['totals']['difference']);
        $this->assertEquals(40.0, $scoped['totals']['usage']);
    }

    public function test_employee_attendance_report_groups_by_unit(): void
    {
        $unit = $this->makeUnit('Unit Pegawai');
        $employeeId = DB::table('employees')->insertGetId([
            'employee_number' => 'P-001', 'name' => 'Pegawai Uji', 'employee_type' => 'teacher', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['2026-10-05', 'present'], ['2026-10-06', 'late'], ['2026-10-07', 'absent']] as [$date, $status]) {
            DB::table('attendance_records')->insert([
                'employee_id' => $employeeId, 'organizational_unit_id' => $unit->id, 'attendance_date' => $date,
                'status' => $status, 'source' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $data = app(ReportService::class)->build('employee-attendance', [$unit->id], Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame(3, $data['totals']['total']);
        $this->assertEquals(66.7, $data['totals']['rate']);
        $this->assertSame('Unit Pegawai', $data['rows'][0]['unit']);
    }

    public function test_csv_export_returns_rows_and_is_audited(): void
    {
        [$school] = $this->makeSchool('A');
        $this->seedStudentAttendance($school, ['present', 'absent']);
        $admin = $this->makeAdmin(['reports.view', 'reports.export', 'education.attendance.view', 'organization.access.all']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.export', ['report' => 'student-attendance', 'from' => '2026-10-01', 'to' => '2026-10-31']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Rekap Absensi Siswa', $content);
        $this->assertStringContainsString('"Sekolah A";"Kelas 1A"', $content);
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'report.exported']);
    }

    public function test_export_requires_export_permission(): void
    {
        $admin = $this->makeAdmin(['reports.view', 'education.attendance.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.export', 'student-attendance'))
            ->assertForbidden();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.print', 'student-attendance'))
            ->assertForbidden();
    }

    public function test_csv_neutralises_spreadsheet_formulas(): void
    {
        $unit = OrganizationalUnit::query()->create([
            'type_id' => $this->schoolType->id, 'parent_id' => $this->root->id,
            'code' => 'RUMUS', 'name' => '=HYPERLINK("http://x")', 'status' => 'active',
        ]);
        $school = School::query()->create([
            'organizational_unit_id' => $unit->id, 'school_level_id' => $this->level->id, 'status' => 'active',
        ]);
        $admin = $this->makeAdmin(['reports.view', 'reports.export', 'education.schools.view', 'organization.access.all']);

        $content = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.export', 'schools'))
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString("\n=HYPERLINK", $content);
        $this->assertNotNull($school->id);
    }

    public function test_invalid_period_is_rejected(): void
    {
        $admin = $this->makeAdmin(['reports.view', 'education.schools.view']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.index', ['from' => '2026-10-31', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.index', ['from' => '2020-01-01', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_unknown_report_returns_404(): void
    {
        $admin = $this->makeAdmin(['reports.view']);

        $this->actingAs($admin, 'admin')->get('/admin/reports/tidak-ada')->assertNotFound();
    }

    // ------------------------------------------------------------------

    private function makeUnit(string $name): OrganizationalUnit
    {
        $this->sequence++;

        return OrganizationalUnit::query()->create([
            'type_id' => $this->schoolType->id, 'parent_id' => $this->root->id,
            'code' => 'U-'.$this->sequence, 'name' => $name, 'status' => 'active',
        ]);
    }

    /** @return array{0: School, 1: Classroom, 2: Student} */
    private function makeSchool(string $suffix): array
    {
        $unit = $this->makeUnit('Sekolah '.$suffix);
        $school = School::query()->create([
            'organizational_unit_id' => $unit->id, 'school_level_id' => $this->level->id, 'status' => 'active',
        ]);
        $year = AcademicYear::query()->create([
            'school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $classroom = Classroom::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Kelas 1A', 'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id, 'nis' => 'NIS-'.$suffix, 'name' => 'Siswa '.$suffix, 'status' => 'active',
        ]);

        return [$school, $classroom, $student];
    }

    /** Satu siswa dicatat satu kali per status, tiap status di sesi berbeda. */
    private function seedStudentAttendance(School $school, array $statuses): void
    {
        $classroom = Classroom::query()->where('school_id', $school->id)->firstOrFail();
        $student = Student::query()->where('school_id', $school->id)->firstOrFail();

        foreach ($statuses as $index => $status) {
            $session = StudentAttendanceSession::query()->create([
                'classroom_id' => $classroom->id,
                'attendance_date' => Carbon::parse('2026-10-05')->addDays($index)->toDateString(),
                'name' => 'Harian',
            ]);
            StudentAttendanceRecord::query()->create([
                'student_attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $status,
            ]);
        }
    }

    private function seedBudget(OrganizationalUnit $unit, string $code, float $planned, float $realized): void
    {
        $programId = DB::table('work_programs')->insertGetId([
            'organizational_unit_id' => $unit->id, 'code' => $code, 'title' => 'Program '.$code,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = DB::table('work_activities')->insertGetId([
            'work_program_id' => $programId, 'organizational_unit_id' => $unit->id, 'code' => $code.'-1',
            'title' => 'Kegiatan '.$code, 'status' => 'planned', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('activity_budget_items')->insert([
            'work_activity_id' => $activityId, 'name' => 'Belanja', 'planned_amount' => $planned,
            'realized_amount' => $realized, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeAdmin(array $permissions, ?int $unitId = null, string $suffix = 'satu'): Admin
    {
        $role = Role::query()->create([
            'name' => 'peran-'.$suffix.'-'.($this->sequence++), 'label' => 'Peran Uji', 'guard_name' => 'admin', 'is_system' => false,
        ]);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'admin'],
                ['label' => $name, 'group' => 'Uji']
            );
            $role->permissions()->attach($permission->id);
        }

        $admin = Admin::query()->create([
            'name' => 'Admin '.$suffix,
            'username' => 'admin-'.$suffix.'-'.($this->sequence++),
            'email' => 'admin-'.$suffix.'-'.($this->sequence++).'@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);

        if ($unitId) {
            UserUnitAccess::query()->create([
                'organizational_unit_id' => $unitId, 'model_type' => Admin::class,
                'model_id' => $admin->id, 'created_at' => now(),
            ]);
        }

        return $admin;
    }
}
