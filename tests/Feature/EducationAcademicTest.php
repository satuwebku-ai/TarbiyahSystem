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
use App\Models\StudentClassPlacement;
use App\Models\UserUnitAccess;
use Database\Seeders\FoundationOrganizationSeeder;
use Database\Seeders\PelajarSeeder;
use Database\Seeders\PhaseSevenDemoSeeder;
use Database\Seeders\SchoolLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducationAcademicTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_and_phase_seven_demo_seeders_are_repeatable(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'local');

        try {
            $this->seed(FoundationOrganizationSeeder::class);
            $this->seed(SchoolLevelSeeder::class);
            $this->seed(PelajarSeeder::class);
            $this->seed(PhaseSevenDemoSeeder::class);
            $this->seed(PhaseSevenDemoSeeder::class);
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }

        $pb = OrganizationalUnit::query()->where('code', 'PB-YAYASAN')->firstOrFail();
        $komwil = OrganizationalUnit::query()->where('code', 'KOMWIL-DEMO-PROV')->firstOrFail();
        $this->assertSame($pb->id, $komwil->parent_id);
        $this->assertDatabaseCount('schools', 1);
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseCount('classrooms', 1);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('student_guardians', 1);
        $this->assertDatabaseCount('student_class_placements', 1);
        $this->assertDatabaseHas('organizational_units', [
            'code' => 'KOMDA-DEMO-KAB',
            'parent_id' => $komwil->id,
        ]);
        $this->assertDatabaseHas('organizational_units', [
            'code' => 'SEKOLAH-DEMO-001',
            'parent_id' => OrganizationalUnit::query()->where('code', 'KOMDA-DEMO-KAB')->value('id'),
        ]);
    }

    public function test_super_admin_can_create_school_year_class_student_guardian_and_placement(): void
    {
        $this->seed(FoundationOrganizationSeeder::class);
        $this->seed(SchoolLevelSeeder::class);
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $this->get(route('admin.academic.schools.index'))->assertOk();
        $this->get(route('admin.academic.schools.create'))->assertOk();
        $this->get(route('admin.academic.students.index'))->assertOk();
        $this->get(route('admin.academic.students.create'))->assertOk();

        $komda = $this->createKomda();
        $level = SchoolLevel::query()->where('slug', 'sd')->firstOrFail();

        $this->post(route('admin.academic.schools.store'), [
            'parent_unit_id' => $komda->id,
            'code' => 'SD-UJI-01',
            'name' => 'Sekolah Uji',
            'school_level_id' => $level->id,
            'npsn' => 'TEST-70001',
            'address' => 'Alamat uji',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $school = School::query()->firstOrFail();
        $this->get(route('admin.academic.schools.edit', $school))->assertOk();
        $this->post(route('admin.academic.schools.academic-years.store', $school), [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $year = AcademicYear::query()->firstOrFail();

        $this->post(route('admin.academic.schools.classrooms.store', $school), [
            'academic_year_id' => $year->id,
            'name' => 'Kelas 1A',
            'grade_label' => '1',
            'capacity' => 30,
        ])->assertSessionHasNoErrors();
        $classroom = Classroom::query()->firstOrFail();

        $this->post(route('admin.academic.students.store'), [
            'school_id' => $school->id,
            'nis' => 'SISWA-001',
            'nisn' => 'TEST000001',
            'name' => 'Siswa Uji',
            'gender' => 'female',
            'birth_date' => '2018-01-01',
            'address' => 'Alamat siswa uji',
            'status' => 'active',
        ])->assertSessionHasNoErrors();
        $student = Student::query()->firstOrFail();
        $this->get(route('admin.academic.students.edit', $student))->assertOk();

        $this->post(route('admin.academic.students.guardians.store', $student), [
            'name' => 'Wali Uji',
            'relationship' => 'Orang tua',
            'phone' => '081234567890',
            'is_primary' => '1',
        ])->assertSessionHasNoErrors();

        $this->post(route('admin.academic.students.enrollments.store', $student), [
            'academic_year_id' => $year->id,
            'enrollment_date' => '2026-07-01',
            'status' => 'active',
        ])->assertSessionHasNoErrors();
        $enrollment = $student->enrollments()->firstOrFail();

        $this->post(route('admin.academic.students.placements.store', [$student, $enrollment]), [
            'classroom_id' => $classroom->id,
            'start_date' => '2026-07-01',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_guardians', ['student_id' => $student->id, 'is_primary' => true]);
        $this->assertDatabaseHas('student_class_placements', [
            'student_enrollment_id' => $enrollment->id,
            'classroom_id' => $classroom->id,
            'status' => 'active',
        ]);
        $this->post(route('admin.academic.schools.classrooms.store', $school), [
            'academic_year_id' => $year->id,
            'name' => 'Kelas 1B',
            'grade_label' => '1',
        ])->assertSessionHasNoErrors();
        $newClassroom = Classroom::query()->where('name', 'Kelas 1B')->firstOrFail();
        $this->post(route('admin.academic.students.placements.store', [$student, $enrollment]), [
            'classroom_id' => $newClassroom->id,
            'start_date' => '2026-09-01',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_class_placements', [
            'student_enrollment_id' => $enrollment->id,
            'classroom_id' => $classroom->id,
            'status' => 'ended',
        ]);
        $previousPlacement = StudentClassPlacement::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('classroom_id', $classroom->id)
            ->firstOrFail();
        $this->assertSame('2026-08-31', $previousPlacement->end_date->toDateString());
        $this->patch(route('admin.academic.students.enrollments.status', [$student, $enrollment]), [
            'status' => 'completed',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_class_placements', [
            'student_enrollment_id' => $enrollment->id,
            'classroom_id' => $newClassroom->id,
            'status' => 'ended',
        ]);
        $this->get(route('admin.academic.schools.show', $school))->assertOk()->assertSee('Sekolah Uji');
        $this->get(route('admin.academic.students.show', $student))->assertOk()->assertSee('Siswa Uji')->assertSee('Kelas 1A');
    }

    public function test_unit_restricted_admin_cannot_view_students_outside_assigned_komda(): void
    {
        $this->seed(FoundationOrganizationSeeder::class);
        $this->seed(SchoolLevelSeeder::class);
        $assignedKomda = $this->createKomda('KOMDA-UJI-1');
        $otherKomda = $this->createKomda('KOMDA-UJI-2');
        $assignedStudent = $this->createStudent($assignedKomda, 'Siswa Dalam Cakupan');
        $otherStudent = $this->createStudent($otherKomda, 'Siswa Di Luar Cakupan');

        $permission = Permission::query()->create([
            'name' => 'education.students.view',
            'label' => 'Lihat data siswa',
            'group' => 'Data Sekolah & Akademik',
            'guard_name' => 'admin',
        ]);
        $role = Role::query()->create([
            'name' => 'pengelola-sekolah-uji',
            'label' => 'Pengelola Sekolah Uji',
            'guard_name' => 'admin',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        $admin = Admin::query()->create([
            'name' => 'Admin Sekolah Uji',
            'username' => 'admin-sekolah-uji',
            'email' => 'admin-sekolah-uji@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);
        UserUnitAccess::query()->create([
            'organizational_unit_id' => $assignedKomda->id,
            'model_type' => Admin::class,
            'model_id' => $admin->id,
            'created_at' => now(),
        ]);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.academic.students.index'))
            ->assertOk()
            ->assertSee('Siswa Dalam Cakupan')
            ->assertDontSee('Siswa Di Luar Cakupan');
        $this->get(route('admin.academic.students.show', $otherStudent))->assertForbidden();
        $this->get(route('admin.academic.students.show', $assignedStudent))->assertOk();

        $schoolPermission = Permission::query()->create([
            'name' => 'education.schools.view',
            'label' => 'Lihat data sekolah',
            'group' => 'Data Sekolah & Akademik',
            'guard_name' => 'admin',
        ]);
        $schoolViewerRole = Role::query()->create([
            'name' => 'viewer-sekolah-uji',
            'label' => 'Viewer Sekolah Uji',
            'guard_name' => 'admin',
            'is_system' => false,
        ]);
        $schoolViewerRole->permissions()->attach($schoolPermission->id);
        $schoolViewer = Admin::query()->create([
            'name' => 'Viewer Sekolah',
            'username' => 'viewer-sekolah-uji',
            'email' => 'viewer-sekolah-uji@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $schoolViewer->syncRoles([$schoolViewerRole->id]);
        UserUnitAccess::query()->create([
            'organizational_unit_id' => $assignedKomda->id,
            'model_type' => Admin::class,
            'model_id' => $schoolViewer->id,
            'created_at' => now(),
        ]);
        $this->actingAs($schoolViewer, 'admin');
        $this->get(route('admin.academic.schools.show', $assignedStudent->school))
            ->assertOk()
            ->assertDontSee('Siswa Dalam Cakupan')
            ->assertDontSee('Siswa terdaftar');
    }

    private function createKomda(string $code = 'KOMDA-UJI'): OrganizationalUnit
    {
        $type = OrganizationalUnitType::query()->where('slug', 'komda')->firstOrFail();

        return OrganizationalUnit::query()->create([
            'type_id' => $type->id,
            'code' => $code,
            'name' => 'KOMDA Uji',
            'status' => 'active',
        ]);
    }

    private function createStudent(OrganizationalUnit $komda, string $name): Student
    {
        $unitType = OrganizationalUnitType::query()->where('slug', 'sekolah')->firstOrFail();
        $unit = OrganizationalUnit::query()->create([
            'type_id' => $unitType->id,
            'parent_id' => $komda->id,
            'code' => 'SCHOOL-' . $komda->code,
            'name' => 'Sekolah ' . $komda->code,
            'status' => 'active',
        ]);
        $school = School::query()->create([
            'organizational_unit_id' => $unit->id,
            'school_level_id' => SchoolLevel::query()->where('slug', 'sd')->value('id'),
            'status' => 'active',
        ]);

        return Student::query()->create([
            'school_id' => $school->id,
            'nis' => 'NIS-' . $komda->code,
            'name' => $name,
            'status' => 'active',
        ]);
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
            'name' => 'Admin Fase Tujuh',
            'username' => 'admin-fase-tujuh',
            'email' => 'admin-fase-tujuh@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }
}
