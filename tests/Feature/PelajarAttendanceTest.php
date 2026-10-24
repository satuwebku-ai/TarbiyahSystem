<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\School;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\StudentAttendanceRecord;
use App\Models\StudentAttendanceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rekap absensi di portal pelajar: hanya data milik sendiri, difilter per bulan. */
class PelajarAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 08:00:00');

        $type = OrganizationalUnitType::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah', 'is_active' => true]);
        $level = SchoolLevel::query()->create(['name' => 'Sekolah Dasar', 'slug' => 'sd', 'sort_order' => 1, 'is_active' => true]);
        $unit = OrganizationalUnit::query()->create([
            'type_id' => $type->id, 'code' => 'SEKOLAH-UJI', 'name' => 'Sekolah Uji', 'status' => 'active',
        ]);
        $this->school = School::query()->create([
            'organizational_unit_id' => $unit->id, 'school_level_id' => $level->id, 'status' => 'active',
        ]);
        $year = AcademicYear::query()->create([
            'school_id' => $this->school->id, 'name' => '2026/2027',
            'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $this->classroom = Classroom::query()->create([
            'school_id' => $this->school->id, 'academic_year_id' => $year->id,
            'name' => 'Kelas 1A', 'grade_label' => '1', 'status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('pelajar.attendance'))->assertRedirect(route('pelajar.login'));
    }

    public function test_student_sees_only_own_records_for_the_selected_month(): void
    {
        [$account, $student] = $this->makeStudentWithAccount('1001');
        $other = Student::query()->create([
            'school_id' => $this->school->id, 'nis' => '2002', 'name' => 'Siswa Lain', 'status' => 'active',
        ]);

        $this->record($student, '2026-11-03', 'present');
        $this->record($student, '2026-11-04', 'late');
        $this->record($student, '2026-11-05', 'sick');
        $this->record($student, '2026-11-06', 'absent', 'Tanpa kabar');
        $this->record($student, '2026-10-30', 'permit');   // bulan lain
        $this->record($other, '2026-11-03', 'absent');     // siswa lain

        $response = $this->actingAs($account, 'pelajar')
            ->get(route('pelajar.attendance', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('November 2026')
            ->assertSee('Tanpa kabar');

        $counts = $response->viewData('counts');
        $this->assertSame(1, $counts['present']);
        $this->assertSame(1, $counts['late']);
        $this->assertSame(1, $counts['sick']);
        $this->assertSame(1, $counts['absent']);
        $this->assertSame(0, $counts['permit']);
        $this->assertSame(4, $response->viewData('total'));
        $this->assertSame(50.0, $response->viewData('percentage'));
        $this->assertCount(4, $response->viewData('records'));
        $this->assertTrue($response->viewData('records')->every(fn ($r) => $r->student_id === $student->id));

        // Bulan sebelumnya hanya berisi catatan izin miliknya sendiri.
        $october = $this->actingAs($account, 'pelajar')
            ->get(route('pelajar.attendance', ['month' => '2026-10']))
            ->assertOk();
        $this->assertSame(1, $october->viewData('counts')['permit']);
        $this->assertSame(1, $october->viewData('total'));
    }

    public function test_empty_month_shows_placeholder_and_no_percentage(): void
    {
        [$account] = $this->makeStudentWithAccount('1001');

        $response = $this->actingAs($account, 'pelajar')
            ->get(route('pelajar.attendance'))
            ->assertOk()
            ->assertSee('Belum ada data absensi pada bulan ini.');

        $this->assertNull($response->viewData('percentage'));
        $this->assertSame('2026-11', $response->viewData('currentMonth'));
    }

    public function test_future_month_is_clamped_to_current_month(): void
    {
        [$account] = $this->makeStudentWithAccount('1001');

        $response = $this->actingAs($account, 'pelajar')
            ->get(route('pelajar.attendance', ['month' => '2027-03']))
            ->assertOk();

        $this->assertSame('2026-11', $response->viewData('month')->format('Y-m'));
        $this->assertNull($response->viewData('nextMonth'));
    }

    public function test_invalid_month_is_rejected(): void
    {
        [$account] = $this->makeStudentWithAccount('1001');

        $this->actingAs($account, 'pelajar')
            ->get(route('pelajar.attendance', ['month' => 'bukan-bulan']))
            ->assertSessionHasErrors('month');
    }

    public function test_account_without_linked_student_sees_a_notice(): void
    {
        $account = StudentAccount::query()->create([
            'name' => 'Tanpa Siswa', 'nis' => '9999', 'password' => 'rahasia-123', 'is_active' => true,
        ]);

        $this->actingAs($account, 'pelajar')
            ->get(route('pelajar.attendance'))
            ->assertOk()
            ->assertSee('belum tertaut dengan data siswa');
    }

    private function makeStudentWithAccount(string $nis): array
    {
        $account = StudentAccount::query()->create([
            'name' => 'Siswa '.$nis, 'nis' => $nis, 'password' => 'rahasia-123', 'is_active' => true,
        ]);
        $student = Student::query()->create([
            'school_id' => $this->school->id, 'student_account_id' => $account->id,
            'nis' => $nis, 'name' => 'Siswa '.$nis, 'status' => 'active',
        ]);

        return [$account, $student];
    }

    private function record(Student $student, string $date, string $status, ?string $notes = null): void
    {
        $session = StudentAttendanceSession::query()->firstOrCreate(
            ['classroom_id' => $this->classroom->id, 'attendance_date' => $date, 'name' => 'Harian'],
        );
        StudentAttendanceRecord::query()->create([
            'student_attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => $status,
            'notes' => $notes,
        ]);
    }
}
