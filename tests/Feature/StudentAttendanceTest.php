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
use App\Models\StudentClassPlacement;
use App\Models\StudentEnrollment;
use App\Models\UserUnitAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnitType $schoolType;

    private SchoolLevel $level;

    private OrganizationalUnit $root;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolType = OrganizationalUnitType::query()->create([
            'name' => 'Sekolah',
            'slug' => 'sekolah',
            'is_active' => true,
        ]);
        $this->level = SchoolLevel::query()->create([
            'name' => 'Sekolah Dasar',
            'slug' => 'sd',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $rootType = OrganizationalUnitType::query()->create([
            'name' => 'Yayasan',
            'slug' => 'yayasan',
            'is_active' => true,
        ]);
        $this->root = OrganizationalUnit::query()->create([
            'type_id' => $rootType->id,
            'code' => 'YAYASAN-UJI',
            'name' => 'Yayasan Uji',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_record_update_and_view_student_attendance_recap(): void
    {
        [$school, $year, $classroom, $students] = $this->createSchoolClassAndStudents();
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $query = [
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'date' => '2026-10-20',
            'session_name' => 'Pagi',
        ];

        $this->get(route('admin.academic.student-attendance.index', $query))
            ->assertOk()
            ->assertSee($students[0]->name)
            ->assertSee($students[1]->name);

        $payload = [
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'date' => '2026-10-20',
            'session_name' => 'Pagi',
            'attendance' => [
                $students[0]->id => ['status' => 'present', 'notes' => ''],
                $students[1]->id => ['status' => 'sick', 'notes' => 'Demam'],
            ],
        ];
        $this->post(route('admin.academic.student-attendance.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.academic.student-attendance.index', $query));

        $session = StudentAttendanceSession::query()->firstOrFail();
        $this->assertSame(2, StudentAttendanceRecord::query()->count());
        $this->assertDatabaseHas('student_attendance_records', [
            'student_attendance_session_id' => $session->id,
            'student_id' => $students[1]->id,
            'status' => 'sick',
            'notes' => 'Demam',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'student_attendance.saved']);

        $payload['attendance'][$students[1]->id] = ['status' => 'present', 'notes' => ''];
        $this->post(route('admin.academic.student-attendance.store'), $payload)
            ->assertSessionHasErrors('correction_reason');
        $this->assertDatabaseHas('student_attendance_records', [
            'student_attendance_session_id' => $session->id,
            'student_id' => $students[1]->id,
            'status' => 'sick',
        ]);

        $payload['correction_reason'] = 'Status diperbarui setelah konfirmasi wali kelas.';
        $this->post(route('admin.academic.student-attendance.store'), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame(1, StudentAttendanceSession::query()->count());
        $this->assertSame(2, StudentAttendanceRecord::query()->count());
        $this->assertDatabaseHas('student_attendance_corrections', [
            'student_attendance_record_id' => StudentAttendanceRecord::query()
                ->where('student_attendance_session_id', $session->id)
                ->where('student_id', $students[1]->id)
                ->value('id'),
            'previous_status' => 'sick',
            'new_status' => 'present',
            'reason' => 'Status diperbarui setelah konfirmasi wali kelas.',
        ]);
        $this->get(route('admin.academic.student-attendance.index', $query))
            ->assertOk()
            ->assertSee('Riwayat koreksi')
            ->assertSee('Status diperbarui setelah konfirmasi wali kelas.');
        $this->assertDatabaseHas('student_attendance_records', [
            'student_attendance_session_id' => $session->id,
            'student_id' => $students[1]->id,
            'status' => 'present',
            'notes' => null,
        ]);

        $this->get(route('admin.academic.student-attendance.report', [
            'period' => 'month',
            'month' => '2026-10',
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'classroom_id' => $classroom->id,
        ]))
            ->assertOk()
            ->assertSee('Rekap October 2026')
            ->assertSee($students[0]->name)
            ->assertSee('Hadir');

        $this->get(route('admin.academic.student-attendance.report', [
            'period' => 'year',
            'academic_year_id' => $year->id,
            'school_id' => $school->id,
        ]))
            ->assertOk()
            ->assertSee('Rekap 2026/2027')
            ->assertSee($students[1]->name);
    }

    public function test_attendance_date_must_be_in_the_selected_classes_academic_year(): void
    {
        [$school, , $classroom, $students] = $this->createSchoolClassAndStudents();
        $this->actingAs($this->createSuperAdmin(), 'admin');

        $this->post(route('admin.academic.student-attendance.store'), [
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'date' => '2027-07-01',
            'session_name' => 'Harian',
            'attendance' => [
                $students[0]->id => ['status' => 'present'],
                $students[1]->id => ['status' => 'absent'],
            ],
        ])->assertSessionHasErrors('date');

        $this->assertSame(0, StudentAttendanceSession::query()->count());
        $this->assertSame(0, StudentAttendanceRecord::query()->count());
    }

    public function test_scoped_admin_cannot_open_attendance_for_another_school(): void
    {
        [$allowedSchool, , $allowedClassroom, $allowedStudents] = $this->createSchoolClassAndStudents('A');
        [$otherSchool, , $otherClassroom] = $this->createSchoolClassAndStudents('B');
        $admin = $this->createScopedAdmin($allowedSchool->organizational_unit_id);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.academic.student-attendance.index', [
            'school_id' => $allowedSchool->id,
            'classroom_id' => $allowedClassroom->id,
            'date' => '2026-10-20',
        ]))
            ->assertOk()
            ->assertSee($allowedStudents[0]->name)
            ->assertDontSee($otherSchool->unit->name);

        $this->get(route('admin.academic.student-attendance.index', [
            'classroom_id' => $otherClassroom->id,
            'date' => '2026-10-20',
        ]))->assertNotFound();
    }

    public function test_attendance_submission_rejects_students_outside_the_roster(): void
    {
        [$school, , $classroom, $students] = $this->createSchoolClassAndStudents();
        $outsider = Student::query()->create([
            'school_id' => $school->id,
            'nis' => 'NIS-OUTSIDE',
            'name' => 'Siswa Di Luar Kelas',
            'status' => 'active',
        ]);
        $this->actingAs($this->createSuperAdmin(), 'admin');

        $this->post(route('admin.academic.student-attendance.store'), [
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'date' => '2026-10-20',
            'session_name' => 'Harian',
            'attendance' => [
                $students[0]->id => ['status' => 'present'],
                $students[1]->id => ['status' => 'present'],
                $outsider->id => ['status' => 'present'],
            ],
        ])->assertSessionHasErrors('attendance');

        $this->assertSame(0, StudentAttendanceSession::query()->count());
    }

    private function createSchoolClassAndStudents(string $suffix = ''): array
    {
        $this->sequence++;
        $suffix = $suffix ?: (string) $this->sequence;
        $unit = OrganizationalUnit::query()->create([
            'type_id' => $this->schoolType->id,
            'parent_id' => $this->root->id,
            'code' => 'SEKOLAH-'.$suffix,
            'name' => 'Sekolah '.$suffix,
            'status' => 'active',
        ]);
        $school = School::query()->create([
            'organizational_unit_id' => $unit->id,
            'school_level_id' => $this->level->id,
            'status' => 'active',
        ]);
        $year = AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $classroom = Classroom::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'name' => 'Kelas 1A',
            'grade_label' => '1',
            'status' => 'active',
        ]);

        $students = [];
        foreach (['Siswa Satu', 'Siswa Dua'] as $index => $name) {
            $student = Student::query()->create([
                'school_id' => $school->id,
                'nis' => 'NIS-'.$suffix.'-'.$index,
                'name' => $name.' '.$suffix,
                'status' => 'active',
            ]);
            $enrollment = StudentEnrollment::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'enrollment_date' => '2026-07-01',
                'status' => 'active',
            ]);
            StudentClassPlacement::query()->create([
                'student_enrollment_id' => $enrollment->id,
                'classroom_id' => $classroom->id,
                'start_date' => '2026-07-01',
                'status' => 'active',
            ]);
            $students[] = $student;
        }

        return [$school, $year, $classroom, $students];
    }

    private function createSuperAdmin(): Admin
    {
        $role = Role::query()->create([
            'name' => 'super-admin',
            'label' => 'Super Admin',
            'guard_name' => 'admin',
            'is_system' => true,
        ]);
        $admin = Admin::query()->create([
            'name' => 'Admin Absensi',
            'username' => 'admin-absensi-uji',
            'email' => 'admin-absensi-uji@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    private function createScopedAdmin(int $unitId): Admin
    {
        $permission = Permission::query()->create([
            'name' => 'education.attendance.manage',
            'label' => 'Catat absensi siswa',
            'group' => 'Data Sekolah & Akademik',
            'guard_name' => 'admin',
        ]);
        $role = Role::query()->create([
            'name' => 'operator-absensi-uji',
            'label' => 'Operator Absensi',
            'guard_name' => 'admin',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        $admin = Admin::query()->create([
            'name' => 'Operator Sekolah',
            'username' => 'operator-absensi-uji',
            'email' => 'operator-absensi-uji@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);
        UserUnitAccess::query()->create([
            'organizational_unit_id' => $unitId,
            'model_type' => Admin::class,
            'model_id' => $admin->id,
            'created_at' => now(),
        ]);

        return $admin;
    }
}
