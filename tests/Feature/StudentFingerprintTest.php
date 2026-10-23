<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Admin;
use App\Models\Classroom;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentAttendanceRecord;
use App\Models\StudentAttendanceSetting;
use App\Models\StudentClassPlacement;
use App\Models\StudentEnrollment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFingerprintTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classroom $classroom;

    private Student $andi;

    private Student $budi;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 02:00:00'); // Rabu 09:00 WIB

        $root = OrganizationalUnit::query()->create([
            'type_id' => OrganizationalUnitType::query()->create(['name' => 'Yayasan', 'slug' => 'yayasan', 'is_active' => true])->id,
            'code' => 'Y', 'name' => 'Yayasan', 'status' => 'active',
        ]);
        $unit = OrganizationalUnit::query()->create([
            'type_id' => OrganizationalUnitType::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah', 'is_active' => true])->id,
            'parent_id' => $root->id, 'code' => 'S1', 'name' => 'SD Satu', 'status' => 'active',
        ]);
        $level = SchoolLevel::query()->create(['name' => 'SD', 'slug' => 'sd', 'sort_order' => 1, 'is_active' => true]);
        $this->school = School::query()->create(['organizational_unit_id' => $unit->id, 'school_level_id' => $level->id, 'status' => 'active']);
        $year = AcademicYear::query()->create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $this->classroom = Classroom::query()->create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => '1A', 'grade_label' => '1', 'status' => 'active']);

        foreach (['andi' => '101', 'budi' => '102'] as $key => $pin) {
            $student = Student::query()->create(['school_id' => $this->school->id, 'nis' => "NIS-$pin", 'name' => ucfirst($key), 'status' => 'active', 'fingerprint_pin' => $pin]);
            $enrollment = StudentEnrollment::query()->create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'enrollment_date' => '2026-07-01', 'status' => 'active']);
            StudentClassPlacement::query()->create(['student_enrollment_id' => $enrollment->id, 'classroom_id' => $this->classroom->id, 'start_date' => '2026-07-01', 'status' => 'active']);
            $this->$key = $student;
        }
    }

    private function device(bool $active = true): FingerprintDevice
    {
        return FingerprintDevice::query()->create([
            'serial_number' => 'SN123', 'name' => 'Gerbang', 'school_id' => $this->school->id, 'is_active' => $active,
        ]);
    }

    private function push(string $body, string $sn = 'SN123')
    {
        return $this->call('POST', "/iclock/cdata?SN=$sn&table=ATTLOG&Stamp=9999", [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }

    private function superAdmin(): Admin
    {
        $role = Role::query()->create(['name' => 'super-admin', 'label' => 'Super Admin', 'guard_name' => 'admin', 'is_system' => true]);
        $admin = Admin::query()->create(['name' => 'SA', 'username' => 'sa-fp', 'email' => 'sa-fp@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    public function test_unknown_device_is_registered_as_pending_and_logs_are_rejected(): void
    {
        $this->get('/iclock/cdata?SN=NEW001&options=all')->assertOk()->assertSee('GET OPTION FROM: NEW001');
        $device = FingerprintDevice::query()->where('serial_number', 'NEW001')->firstOrFail();
        $this->assertTrue($device->isPending());
        $this->assertFalse($device->is_active);

        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n", 'NEW001')->assertStatus(403);
        $this->assertSame(0, FingerprintLog::query()->count());
    }

    public function test_first_punch_creates_present_and_later_punch_is_duplicate(): void
    {
        Carbon::setTestNow('2026-10-21 08:00:00'); // sudah lewat jam 12:00 waktu absensi
        $this->device();
        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n102\t2026-10-21 07:30:10\t0\t1\t0\n")->assertOk()->assertSee('OK: 2');

        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->andi->id, 'status' => 'present', 'source' => 'fingerprint']);
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->budi->id, 'status' => 'late', 'source' => 'fingerprint']);

        $this->push("101\t2026-10-21 12:00:00\t0\t1\t0\n")->assertOk();
        $this->assertSame(2, StudentAttendanceRecord::query()->count());
        $this->assertSame('present', StudentAttendanceRecord::query()->where('student_id', $this->andi->id)->value('status')->value);
        $this->assertDatabaseHas('fingerprint_logs', ['pin' => '101', 'result' => 'duplicate']);

        // Kirim ulang log yang sama tidak membuat duplikat.
        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertOk();
        $this->assertSame(3, FingerprintLog::query()->count());
    }

    public function test_space_separated_firmware_format_is_parsed(): void
    {
        $this->device();
        $this->push("101 2026-10-21 06:50:00 0 1\n")->assertOk()->assertSee('OK: 1');
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->andi->id, 'status' => 'present']);
    }

    public function test_unmapped_pin_is_kept_and_reprocessed_after_mapping(): void
    {
        $this->device();
        $this->push("555\t2026-10-21 06:55:00\t0\t1\t0\n")->assertOk();
        $this->assertDatabaseHas('fingerprint_logs', ['pin' => '555', 'result' => 'unmapped']);
        $this->assertSame(0, StudentAttendanceRecord::query()->count());

        $this->actingAs($this->superAdmin(), 'admin');
        $this->put(route('admin.academic.fingerprint.pins.update'), [
            'school_id' => $this->school->id,
            'pins' => [$this->andi->id => '101', $this->budi->id => '555'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fingerprint_logs', ['pin' => '555', 'result' => 'present']);
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->budi->id, 'status' => 'present']);
    }

    public function test_manual_record_is_never_overwritten_by_fingerprint(): void
    {
        $this->device();
        $this->actingAs($this->superAdmin(), 'admin');
        $this->post(route('admin.academic.student-attendance.store'), [
            'school_id' => $this->school->id, 'classroom_id' => $this->classroom->id,
            'date' => '2026-10-21', 'session_name' => 'Harian',
            'attendance' => [$this->andi->id => ['status' => 'sick', 'notes' => 'Demam'], $this->budi->id => ['status' => 'present']],
        ])->assertSessionHasNoErrors();

        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertOk();
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->andi->id, 'status' => 'sick', 'source' => 'manual']);
        $this->assertDatabaseHas('fingerprint_logs', ['pin' => '101', 'result' => 'kept_manual']);
    }

    public function test_punches_outside_window_or_school_days_are_ignored(): void
    {
        Carbon::setTestNow('2026-10-21 08:00:00'); // sudah lewat jam 13:00 waktu absensi
        $this->device();
        $this->push("101\t2026-10-21 03:00:00\t0\t1\t0\n101\t2026-10-21 13:00:00\t0\t1\t0\n101\t2026-10-18 07:00:00\t0\t1\t0\n")->assertOk();

        $this->assertSame(0, StudentAttendanceRecord::query()->count());
        $this->assertDatabaseHas('fingerprint_logs', ['result' => 'too_early']);
        $this->assertDatabaseHas('fingerprint_logs', ['result' => 'too_late']);
        $this->assertDatabaseHas('fingerprint_logs', ['result' => 'not_school_day']);
    }

    public function test_school_specific_settings_override_general_and_drive_late_status(): void
    {
        $this->device();
        StudentAttendanceSetting::query()->create([
            'school_id' => $this->school->id, 'school_days' => [1, 2, 3, 4, 5], 'start_time' => '08:00',
            'late_tolerance_minutes' => 0, 'checkin_open_time' => '06:00', 'checkin_close_time' => '12:00',
            'session_name' => 'Pagi',
        ]);
        $this->push("101\t2026-10-21 07:50:00\t0\t1\t0\n102\t2026-10-21 08:05:00\t0\t1\t0\n")->assertOk();

        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->andi->id, 'status' => 'present']);
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->budi->id, 'status' => 'late']);
        $this->assertDatabaseHas('student_attendance_sessions', ['classroom_id' => $this->classroom->id, 'name' => 'Pagi']);
    }

    public function test_auto_absent_marks_missing_students_and_fingerprint_can_upgrade_it(): void
    {
        $this->device();
        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertOk();

        $this->artisan('student-attendance:close-day', ['--date' => '2026-10-21', '--force' => true])->assertSuccessful();
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->budi->id, 'status' => 'absent', 'source' => 'auto']);
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->andi->id, 'status' => 'present', 'source' => 'fingerprint']);

        $this->push("102\t2026-10-21 08:00:00\t0\t1\t0\n")->assertOk();
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->budi->id, 'status' => 'late', 'source' => 'fingerprint']);
        $this->assertDatabaseHas('student_attendance_corrections', ['previous_status' => 'absent', 'new_status' => 'late']);
    }

    public function test_admin_pages_render_and_settings_validate(): void
    {
        $this->device();
        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertOk();
        $this->actingAs($this->superAdmin(), 'admin');

        $this->get(route('admin.academic.fingerprint.devices.index'))->assertOk()->assertSee('Gerbang');
        $this->get(route('admin.academic.fingerprint.logs.index'))->assertOk()->assertSee('Andi');
        $this->get(route('admin.academic.fingerprint.pins.index', ['school_id' => $this->school->id]))->assertOk()->assertSee('Budi');
        $this->get(route('admin.academic.student-attendance.settings.index'))->assertOk()->assertSee('Pengaturan umum');
        $this->get(route('admin.academic.student-attendance.settings.general.edit'))->assertOk();
        $this->get(route('admin.academic.student-attendance.settings.school.edit', $this->school))->assertOk();
        $this->get(route('admin.academic.student-attendance.index', ['school_id' => $this->school->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-10-21']))
            ->assertOk()->assertSee('Fingerprint 06:55');

        $this->put(route('admin.academic.student-attendance.settings.school.update', $this->school), [
            'session_name' => 'Harian', 'school_days' => [1, 2], 'start_time' => '07:00', 'late_tolerance_minutes' => 5,
            'checkin_open_time' => '08:00', 'checkin_close_time' => '12:00', 'auto_absent_time' => '10:00',
        ])->assertSessionHasErrors('checkin_open_time');

        $this->put(route('admin.academic.student-attendance.settings.school.update', $this->school), [
            'session_name' => 'Harian', 'school_days' => [1, 2], 'start_time' => '07:00', 'late_tolerance_minutes' => 5,
            'checkin_open_time' => '05:00', 'checkin_close_time' => '12:00', 'auto_absent_time' => '10:00',
            'fingerprint_enabled' => 1, 'auto_absent_enabled' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_attendance_settings', ['school_id' => $this->school->id, 'late_tolerance_minutes' => 5]);

        $this->delete(route('admin.academic.student-attendance.settings.school.reset', $this->school))->assertRedirect();
        $this->assertDatabaseMissing('student_attendance_settings', ['school_id' => $this->school->id]);
    }

    public function test_admin_can_add_device_and_duplicate_pins_are_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');
        $this->post(route('admin.academic.fingerprint.devices.store'), [
            'serial_number' => 'SN999', 'name' => 'Aula', 'school_id' => $this->school->id, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fingerprint_devices', ['serial_number' => 'SN999', 'is_active' => 1]);

        $this->put(route('admin.academic.fingerprint.pins.update'), [
            'school_id' => $this->school->id,
            'pins' => [$this->andi->id => '777', $this->budi->id => '777'],
        ])->assertSessionHasErrors('pins');
        $this->assertSame('101', $this->andi->fresh()->fingerprint_pin);
    }

    public function test_implausible_punch_times_are_stored_but_never_become_attendance(): void
    {
        $device = $this->device();
        // Besok (masa depan) dan terlalu lama (> 60 hari) ditolak; ketukan wajar tetap diproses.
        $this->push("101\t2026-10-22 07:00:00\t0\t1\t0\n102\t2026-06-01 07:00:00\t0\t1\t0\n101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertOk();

        $this->assertSame(2, FingerprintLog::query()->where('result', 'bad_time')->count());
        $this->assertSame(1, StudentAttendanceRecord::query()->count());
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->andi->id, 'status' => 'present']);
        $this->assertDatabaseMissing('student_attendance_records', ['student_id' => $this->budi->id]);
        // last_log_at hanya mengikuti ketukan wajar, bukan yang jamnya salah.
        $good = FingerprintLog::query()->where('result', 'present')->firstOrFail();
        $this->assertTrue($device->fresh()->last_log_at->equalTo($good->punched_at));
    }

    public function test_oversized_push_is_rejected_without_saving_anything(): void
    {
        config(['student_attendance.max_punches_per_request' => 2]);
        $this->device();

        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n102\t2026-10-21 06:56:00\t0\t1\t0\n101\t2026-10-21 06:57:00\t0\t1\t0\n")->assertStatus(413);
        $this->assertSame(0, FingerprintLog::query()->count());

        config(['student_attendance.max_body_bytes' => 10]);
        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertStatus(413);
        $this->assertSame(0, FingerprintLog::query()->count());
    }

    public function test_device_can_be_locked_to_allowed_ips(): void
    {
        $device = $this->device();
        $device->update(['allowed_ips' => '10.9.9.9, 192.168.1.0/24']);

        // Tests berjalan dari 127.0.0.1: ditolak, tidak ada log, dan mesin tidak tampak daring.
        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertStatus(403);
        $this->get('/iclock/cdata?SN=SN123&options=all')->assertStatus(403);
        $this->assertSame(0, FingerprintLog::query()->count());
        $this->assertNull($device->fresh()->last_seen_at);

        $device->update(['allowed_ips' => '10.9.9.9, 127.0.0.0/8']);
        $this->push("101\t2026-10-21 06:55:00\t0\t1\t0\n")->assertOk()->assertSee('OK: 1');
        $this->assertNotNull($device->fresh()->last_seen_at);

        // Tanpa pembatasan, semua IP diterima.
        $device->update(['allowed_ips' => null]);
        $this->get('/iclock/cdata?SN=SN123&options=all')->assertOk();
    }

    public function test_admin_can_set_allowed_ips_and_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');
        $base = ['serial_number' => 'SN555', 'name' => 'Lab', 'school_id' => $this->school->id, 'is_active' => 1];

        $this->post(route('admin.academic.fingerprint.devices.store'), $base + ['allowed_ips' => '999.1.1.1'])
            ->assertSessionHasErrors('allowed_ips');
        $this->post(route('admin.academic.fingerprint.devices.store'), $base + ['allowed_ips' => '10.0.0.0/33'])
            ->assertSessionHasErrors('allowed_ips');
        $this->assertDatabaseMissing('fingerprint_devices', ['serial_number' => 'SN555']);

        $this->post(route('admin.academic.fingerprint.devices.store'), $base + ['allowed_ips' => ' 203.0.113.10 ,10.0.0.0/24 '])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fingerprint_devices', ['serial_number' => 'SN555', 'allowed_ips' => '203.0.113.10, 10.0.0.0/24']);
    }
}
