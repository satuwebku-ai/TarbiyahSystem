<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Helpers\AttendanceClock;
use App\Models\Admin;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceHoliday;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Jobs\MatchAttendanceFace;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeFaceReference;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUnitAccess;
use App\Services\AttendancePolicyResolver;
use App\Services\AttendanceRecapService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnit $root;
    private OrganizationalUnit $unitA;
    private OrganizationalUnit $unitB;
    private AttendancePolicy $global;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $this->root = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'R', 'name' => 'Yayasan', 'status' => 'active']);
        $this->unitA = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $this->root->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        $this->unitB = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $this->root->id, 'code' => 'B', 'name' => 'Sekolah B', 'status' => 'active']);

        // Senin–Sabtu 07:00–15:00, toleransi 15 menit (jam lokal Asia/Jakarta)
        $this->global = AttendancePolicy::create([
            'name' => 'Umum', 'work_days' => [1, 2, 3, 4, 5, 6], 'work_start' => '07:00', 'work_end' => '15:00',
            'late_tolerance_minutes' => 15, 'is_active' => true,
        ]);

        Carbon::setTestNow('2026-10-21 05:00:00'); // Rabu, 12:00 WIB
    }

    // ---- check-in / check-out & zona waktu ------------------------------

    public function test_check_in_uses_local_timezone_and_stores_utc(): void
    {
        [$admin] = $this->linkedEmployee($this->unitA);
        $this->actingAs($admin, 'pegawai');

        Carbon::setTestNow('2026-10-21 00:10:00'); // 07:10 WIB
        $this->post(route('pegawai.check-in'))->assertSessionHasNoErrors();

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame('2026-10-21', $record->attendance_date->toDateString());
        $this->assertSame('2026-10-21 00:10:00', $record->check_in_at->format('Y-m-d H:i:s')); // UTC
        $this->assertSame('07:10', AttendanceClock::toLocal($record->check_in_at)->format('H:i'));
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame($this->unitA->id, $record->organizational_unit_id);
    }

    public function test_local_date_is_used_when_utc_date_is_still_yesterday(): void
    {
        [$admin] = $this->linkedEmployee($this->unitA);
        $this->actingAs($admin, 'pegawai');

        Carbon::setTestNow('2026-10-20 23:50:00'); // UTC Selasa, tetapi 06:50 WIB Rabu
        $this->post(route('pegawai.check-in'))->assertSessionHasNoErrors();

        $this->assertSame('2026-10-21', AttendanceRecord::firstOrFail()->attendance_date->toDateString());
    }

    public function test_late_boundaries_follow_tolerance(): void
    {
        [$a1] = $this->linkedEmployee($this->unitA);
        [$a2] = $this->linkedEmployee($this->unitA);

        Carbon::setTestNow('2026-10-21 00:15:00'); // 07:15 = tepat batas toleransi
        $this->actingAs($a1, 'pegawai')->post(route('pegawai.check-in'));
        Carbon::setTestNow('2026-10-21 00:16:00'); // 07:16
        $this->actingAs($a2, 'pegawai')->post(route('pegawai.check-in'));

        $this->assertSame(AttendanceStatus::Present, AttendanceRecord::orderBy('id')->first()->status);
        $late = AttendanceRecord::orderBy('id')->get()[1];
        $this->assertSame(AttendanceStatus::Late, $late->status);
        $this->assertSame(16, $late->late_minutes);
    }

    public function test_duplicate_check_in_and_check_out_rules(): void
    {
        [$admin] = $this->linkedEmployee($this->unitA);
        $this->actingAs($admin, 'pegawai');

        Carbon::setTestNow('2026-10-21 00:30:00');
        $this->post(route('pegawai.check-out'))->assertSessionHasErrors('attendance'); // belum check-in

        $this->post(route('pegawai.check-in'))->assertSessionHasNoErrors();
        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('attendance');
        $this->assertSame(1, AttendanceRecord::count());

        Carbon::setTestNow('2026-10-21 08:00:00'); // 15:00 WIB (lompatan 7,5 jam melewati batas idle sesi)
        $this->withSession(['pegawai_last_activity' => now()->timestamp])->post(route('pegawai.check-out'))->assertSessionHasNoErrors();
        $this->assertSame(450, AttendanceRecord::firstOrFail()->work_minutes);
        $this->post(route('pegawai.check-out'))->assertSessionHasErrors('attendance');
    }

    public function test_check_in_is_blocked_on_non_work_day_holiday_without_policy_or_inactive_employee(): void
    {
        [$admin, $employee] = $this->linkedEmployee($this->unitA);
        $this->actingAs($admin, 'pegawai');

        Carbon::setTestNow('2026-10-25 01:00:00'); // Minggu
        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('attendance');

        Carbon::setTestNow('2026-10-21 01:00:00');
        AttendanceHoliday::create(['holiday_date' => '2026-10-21', 'name' => 'Libur Uji']);
        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('attendance');
        AttendanceHoliday::query()->delete();

        $employee->update(['status' => EmployeeStatus::OnLeave]);
        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('attendance');
        $employee->update(['status' => EmployeeStatus::Active]);

        $this->global->update(['is_active' => false]);
        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('attendance');
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_policy_is_inherited_from_parent_and_overridden_by_unit(): void
    {
        $own = AttendancePolicy::create([
            'organizational_unit_id' => $this->unitA->id, 'name' => 'Sekolah A', 'work_days' => [1, 2, 3, 4, 5],
            'work_start' => '08:00', 'work_end' => '14:00', 'late_tolerance_minutes' => 0, 'is_active' => true,
        ]);
        $child = OrganizationalUnit::create(['type_id' => $this->unitA->type_id, 'parent_id' => $this->unitA->id, 'code' => 'A1', 'name' => 'Kelas A1', 'status' => 'active']);

        $resolver = new AttendancePolicyResolver();
        $this->assertSame($own->id, $resolver->policyForUnit($this->unitA->id)->id);
        $this->assertSame($own->id, $resolver->policyForUnit($child->id)->id);
        $this->assertSame($this->global->id, $resolver->policyForUnit($this->unitB->id)->id);
    }

    // ---- koreksi ---------------------------------------------------------

    public function test_correction_is_approved_by_scoped_reviewer_and_applied(): void
    {
        [$admin, $employee] = $this->linkedEmployee($this->unitA);
        $reviewer = $this->makeAdmin(['attendance.corrections.review', 'employees.view'], $this->unitA);

        $this->actingAs($admin, 'pegawai')->post(route('pegawai.corrections.store'), [
            'attendance_date' => '2026-10-20', 'requested_check_in' => '07:00', 'requested_check_out' => '15:00', 'reason' => 'Lupa check-in',
        ])->assertSessionHasNoErrors();
        $correction = AttendanceCorrection::firstOrFail();

        $this->actingAs($reviewer, 'admin')->patch(route('admin.attendance.corrections.approve', $correction))->assertSessionHasNoErrors();

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame('correction', $record->source);
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame('2026-10-20 00:00:00', $record->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(480, $record->work_minutes);
        $this->assertSame($record->id, $correction->fresh()->attendance_record_id);
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'attendance.correction_approved']);

        // sudah diproses -> tidak bisa diproses lagi
        $this->patch(route('admin.attendance.corrections.approve', $correction))->assertSessionHasErrors('correction');
    }

    public function test_correction_rejection_requires_note_and_creates_no_record(): void
    {
        [$admin, $employee] = $this->linkedEmployee($this->unitA);
        $reviewer = $this->makeAdmin(['attendance.corrections.review', 'employees.view'], $this->unitA);
        app(AttendanceService::class)->submitCorrection($employee, $admin, [
            'attendance_date' => '2026-10-20', 'requested_check_in' => '07:00', 'reason' => 'Lupa',
        ]);
        $correction = AttendanceCorrection::firstOrFail();

        $this->actingAs($reviewer, 'admin');
        $this->patch(route('admin.attendance.corrections.reject', $correction))->assertSessionHasErrors('review_note');
        $this->patch(route('admin.attendance.corrections.reject', $correction), ['review_note' => 'Tidak ada bukti'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $correction->fresh()->status->value);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_correction_review_scope_is_enforced(): void
    {
        [$user, $employee] = $this->linkedEmployee($this->unitA);
        app(AttendanceService::class)->submitCorrection($employee, $user, [
            'attendance_date' => '2026-10-20', 'requested_check_in' => '07:00', 'reason' => 'Lupa check-in',
        ]);
        $correction = AttendanceCorrection::firstOrFail();
        $this->assertSame($user->id, $correction->requested_by_user_id);

        // pegawai (guard pegawai) tidak punya akses ke panel peninjau
        $this->actingAs($user, 'pegawai')->patch(route('admin.attendance.corrections.approve', $correction))->assertRedirect();
        $this->assertSame('pending', $correction->fresh()->status->value);

        // reviewer unit lain -> 403
        $other = $this->makeAdmin(['attendance.corrections.review', 'employees.view'], $this->unitB);
        $this->actingAs($other, 'admin')->patch(route('admin.attendance.corrections.approve', $correction))->assertForbidden();
        $this->assertSame('pending', $correction->fresh()->status->value);
    }

    public function test_correction_window_future_duplicate_and_empty_times_are_rejected(): void
    {
        [$admin, $employee] = $this->linkedEmployee($this->unitA);
        $this->actingAs($admin, 'pegawai');
        $ok = ['requested_check_in' => '07:00', 'reason' => 'Lupa check-in'];

        $this->post(route('pegawai.corrections.store'), $ok + ['attendance_date' => '2026-09-01'])->assertSessionHasErrors('attendance_date');
        $this->post(route('pegawai.corrections.store'), $ok + ['attendance_date' => '2026-10-30'])->assertSessionHasErrors('attendance_date');
        $this->post(route('pegawai.corrections.store'), ['attendance_date' => '2026-10-20', 'reason' => 'Lupa check-in'])->assertSessionHasErrors('requested_check_in');

        $this->post(route('pegawai.corrections.store'), $ok + ['attendance_date' => '2026-10-20'])->assertSessionHasNoErrors();
        $this->post(route('pegawai.corrections.store'), $ok + ['attendance_date' => '2026-10-20'])->assertSessionHasErrors('attendance_date');
        $this->assertSame(1, AttendanceCorrection::count());
    }

    // ---- izin / sakit / cuti ---------------------------------------------

    public function test_manual_entries_skip_non_work_days_and_holidays_and_reject_conflicts(): void
    {
        [, $employee] = $this->linkedEmployee($this->unitA);
        $manager = $this->makeAdmin(['attendance.records.manage', 'employees.view'], $this->unitA);
        AttendanceHoliday::create(['holiday_date' => '2026-10-26', 'name' => 'Libur']);
        $this->actingAs($manager, 'admin');

        // Jum 23, Sab 24, (Min 25 libur mingguan), (Sen 26 libur nasional), Sel 27 = 3 hari kerja
        $this->post(route('admin.attendance.entries.store'), [
            'employee_id' => $employee->id, 'status' => 'sick', 'date_from' => '2026-10-23', 'date_to' => '2026-10-27',
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, AttendanceRecord::where('status', 'sick')->count());

        $this->post(route('admin.attendance.entries.store'), [
            'employee_id' => $employee->id, 'status' => 'leave', 'date_from' => '2026-10-23', 'date_to' => '2026-10-23',
        ])->assertSessionHasErrors('date_from');

        $record = AttendanceRecord::firstOrFail();
        $this->delete(route('admin.attendance.entries.destroy', $record))->assertSessionHasNoErrors();
        $this->assertSame(2, AttendanceRecord::count());
        $this->assertDatabaseHas('admin_audit_logs', ['event' => 'attendance.entry_deleted']);
    }

    public function test_manual_entry_cannot_delete_checkin_record_and_absent_cannot_be_future(): void
    {
        [, $employee] = $this->linkedEmployee($this->unitA);
        $manager = $this->makeAdmin(['attendance.records.manage', 'employees.view'], $this->unitA);
        $present = AttendanceRecord::create([
            'employee_id' => $employee->id, 'organizational_unit_id' => $this->unitA->id, 'attendance_date' => '2026-10-20',
            'check_in_at' => '2026-10-20 00:00:00', 'status' => 'present', 'source' => 'web',
        ]);
        $this->actingAs($manager, 'admin');

        $this->delete(route('admin.attendance.entries.destroy', $present))->assertSessionHasErrors('record');
        $this->assertSame(1, AttendanceRecord::count());

        $this->post(route('admin.attendance.entries.store'), [
            'employee_id' => $employee->id, 'status' => 'absent', 'date_from' => '2026-10-28', 'date_to' => '2026-10-28',
        ])->assertSessionHasErrors('date_to');
    }

    // ---- rekap -----------------------------------------------------------

    public function test_monthly_recap_counts_records_and_derives_absences(): void
    {
        [, $employee] = $this->linkedEmployee($this->unitA, '2026-10-01');
        $rec = fn ($date, $status, $extra = []) => AttendanceRecord::create([
            'employee_id' => $employee->id, 'organizational_unit_id' => $this->unitA->id, 'attendance_date' => $date,
            'status' => $status, 'source' => 'web', ...$extra,
        ]);
        $rec('2026-10-01', 'present', ['work_minutes' => 480]);
        $rec('2026-10-02', 'late', ['late_minutes' => 20, 'work_minutes' => 460]);
        $rec('2026-10-03', 'sick', ['source' => 'manual']);
        AttendanceHoliday::create(['holiday_date' => '2026-10-08', 'name' => 'Libur']);

        $result = app(AttendanceRecapService::class)->build(
            Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), Employee::all(), null,
        );
        $row = $result['rows'][0];

        // Hari kerja Sen–Sab s.d. 20 Okt = 17; 3 tercatat; 1 libur -> 13 tidak hadir otomatis.
        // 21 Okt (hari ini, belum ada catatan) belum dihitung.
        $this->assertSame(['present' => 1, 'late' => 1, 'permit' => 0, 'sick' => 1, 'leave' => 0, 'absent' => 13], $row['counts']);
        $this->assertSame(940, $row['work_minutes']);
        $this->assertSame(20, $row['late_minutes']);
        $this->assertSame('none', $row['days']['2026-10-21']['status']);
        $this->assertSame('holiday', $row['days']['2026-10-08']['status']);
        $this->assertSame('off', $row['days']['2026-10-04']['status']);
        $this->assertSame(13, $result['units'][0]['counts']['absent']);
    }

    public function test_recap_pages_are_scoped_by_unit(): void
    {
        $this->linkedEmployee($this->unitA, '2026-01-01', 'Guru Alfa');
        $this->linkedEmployee($this->unitB, '2026-01-01', 'Guru Beta');
        $scoped = $this->makeAdmin(['attendance.view'], $this->unitA);

        $this->actingAs($scoped, 'admin');
        $this->get(route('admin.attendance.daily', ['date' => '2026-10-20']))->assertOk()->assertSee('Guru Alfa')->assertDontSee('Guru Beta');
        $this->get(route('admin.attendance.monthly', ['month' => '2026-10']))->assertOk()->assertSee('Guru Alfa')->assertDontSee('Guru Beta');
        // meminta unit lain tidak membuka data di luar cakupan
        $this->get(route('admin.attendance.daily', ['date' => '2026-10-20', 'unit_id' => $this->unitB->id]))->assertOk()->assertDontSee('Guru Beta');
    }

    // ---- kebijakan & hari libur ------------------------------------------

    public function test_policy_management_respects_unit_scope_and_single_active_rule(): void
    {
        $scoped = $this->makeAdmin(['attendance.policies.manage'], $this->unitA);
        $this->actingAs($scoped, 'admin');
        $payload = fn ($unit) => [
            'organizational_unit_id' => $unit, 'name' => 'Kebijakan', 'work_days' => [1, 2, 3], 'work_start' => '07:30',
            'work_end' => '14:00', 'late_tolerance_minutes' => 10, 'timezone' => 'Asia/Jakarta', 'is_active' => 1,
        ];

        $this->post(route('admin.attendance.policies.store'), $payload(null))->assertForbidden();           // umum
        $this->post(route('admin.attendance.policies.store'), $payload($this->unitB->id))->assertForbidden(); // unit lain
        $this->post(route('admin.attendance.policies.store'), $payload($this->unitA->id))->assertSessionHasNoErrors();
        $this->post(route('admin.attendance.policies.store'), $payload($this->unitA->id))->assertSessionHasErrors('organizational_unit_id');
        $this->post(route('admin.attendance.policies.store'), [...$payload($this->unitA->id), 'work_end' => '07:00', 'is_active' => 0])->assertSessionHasErrors('work_end');
    }

    public function test_policy_list_shows_only_units_in_scope_like_the_org_structure(): void
    {
        AttendancePolicy::query()->delete();
        $this->makePolicy($this->unitA, 'Asia/Jakarta')->update(['name' => 'Kebijakan A']);
        $this->makePolicy($this->unitB, 'Asia/Jakarta')->update(['name' => 'Kebijakan B']);
        AttendancePolicy::create([
            'organizational_unit_id' => null, 'name' => 'Kebijakan Umum', 'work_days' => [1, 2, 3], 'work_start' => '07:00',
            'work_end' => '15:00', 'late_tolerance_minutes' => 15, 'is_active' => true,
        ]);

        $this->actingAs($this->makeAdmin(['attendance.policies.manage'], $this->unitA), 'admin');
        $this->getJson(route('admin.attendance.policies.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Kebijakan A')->assertDontSee('Kebijakan B')->assertDontSee('Kebijakan Umum');
    }

    public function test_policy_timezone_is_required_and_limited_to_indonesian_zones(): void
    {
        $scoped = $this->makeAdmin(['attendance.policies.manage'], $this->unitA);
        $this->actingAs($scoped, 'admin');
        $payload = [
            'organizational_unit_id' => $this->unitA->id, 'name' => 'Kebijakan', 'work_days' => [1, 2, 3], 'work_start' => '07:30',
            'work_end' => '14:00', 'late_tolerance_minutes' => 10, 'is_active' => 1,
        ];

        $this->post(route('admin.attendance.policies.store'), $payload)->assertSessionHasErrors('timezone');
        $this->post(route('admin.attendance.policies.store'), [...$payload, 'timezone' => 'Asia/Tokyo'])->assertSessionHasErrors('timezone');
        $this->post(route('admin.attendance.policies.store'), [...$payload, 'timezone' => 'Asia/Jayapura'])->assertSessionHasNoErrors();

        $this->assertSame('Asia/Jayapura', AttendancePolicy::where('organizational_unit_id', $this->unitA->id)->firstOrFail()->timezone);
    }

    public function test_timezone_follows_the_policy_that_applies_to_the_unit_including_inherited_ones(): void
    {
        config(['attendance.timezone' => 'Asia/Jakarta']);
        $resolver = new AttendancePolicyResolver();

        $this->assertSame('Asia/Jakarta', $resolver->timezoneForUnit($this->unitA->id)); // kebijakan umum tanpa zona -> bawaan

        $this->makePolicy($this->root, 'Asia/Jayapura');
        $resolver = new AttendancePolicyResolver();
        $this->assertSame('Asia/Jayapura', $resolver->timezoneForUnit($this->unitA->id)); // mewarisi dari unit induk
        $this->assertSame('Asia/Jayapura', $resolver->timezoneForUnit($this->unitB->id));

        $this->makePolicy($this->unitB, 'Asia/Makassar');
        $resolver = new AttendancePolicyResolver();
        $this->assertSame('Asia/Makassar', $resolver->timezoneForUnit($this->unitB->id)); // kebijakan sendiri menimpa induk
        $this->assertSame('Asia/Jayapura', $resolver->timezoneForUnit($this->unitA->id));
    }

    public function test_late_minutes_and_attendance_date_are_calculated_in_each_units_own_timezone(): void
    {
        config(['attendance.timezone' => 'Asia/Jakarta']);
        $this->makePolicy($this->unitA, 'Asia/Jayapura'); // WIT, UTC+9
        $this->makePolicy($this->unitB, 'Asia/Jakarta');  // WIB, UTC+7
        [$wit] = $this->linkedEmployee($this->unitA);
        [$wib] = $this->linkedEmployee($this->unitB);

        // Saat yang sama (00:30 UTC): 09:30 WIT dan 07:30 WIB; jam masuk sama-sama 07:00, toleransi 15 menit.
        Carbon::setTestNow('2026-10-21 00:30:00');
        $this->actingAs($wit, 'pegawai')->post(route('pegawai.check-in'))->assertSessionHasNoErrors();
        $this->actingAs($wib, 'pegawai')->post(route('pegawai.check-in'))->assertSessionHasNoErrors();

        $rec = AttendanceRecord::orderBy('id')->get();
        $this->assertSame(AttendanceStatus::Late, $rec[0]->status);
        $this->assertSame(150, $rec[0]->late_minutes);
        $this->assertSame(AttendanceStatus::Late, $rec[1]->status);
        $this->assertSame(30, $rec[1]->late_minutes);
        $this->assertSame('2026-10-21 00:30:00', $rec[0]->check_in_at->format('Y-m-d H:i:s')); // tetap tersimpan UTC
        $this->assertSame('09:30', AttendanceClock::toLocal($rec[0]->check_in_at, 'Asia/Jayapura')->format('H:i'));
    }

    public function test_attendance_date_uses_the_units_local_day_across_zones(): void
    {
        config(['attendance.timezone' => 'Asia/Jakarta']);
        $this->makePolicy($this->unitA, 'Asia/Jayapura');
        [$wit] = $this->linkedEmployee($this->unitA);

        // 15:30 UTC Selasa = 00:30 WIT Rabu: tanggal absen harus Rabu (hari kerja menurut zona unit).
        Carbon::setTestNow('2026-10-20 15:30:00');
        $this->actingAs($wit, 'pegawai')->post(route('pegawai.check-in'))->assertSessionHasNoErrors();

        $this->assertSame('2026-10-21', AttendanceRecord::firstOrFail()->attendance_date->toDateString());
    }

    public function test_employee_without_zone_on_policy_keeps_the_system_default_zone(): void
    {
        config(['attendance.timezone' => 'Asia/Makassar']);
        [$user] = $this->linkedEmployee($this->unitA); // kebijakan umum tanpa zona

        Carbon::setTestNow('2026-10-21 00:10:00'); // 08:10 WITA -> terlambat 70 menit dari 07:00
        $this->actingAs($user, 'pegawai')->post(route('pegawai.check-in'))->assertSessionHasNoErrors();

        $this->assertSame(70, AttendanceRecord::firstOrFail()->late_minutes);
    }

    public function test_holidays_respect_scope_and_reject_duplicates(): void
    {
        $scoped = $this->makeAdmin(['attendance.holidays.manage'], $this->unitA);
        $this->actingAs($scoped, 'admin');

        $this->post(route('admin.attendance.holidays.store'), ['holiday_date' => '2026-12-25', 'name' => 'Natal'])->assertForbidden();
        $this->post(route('admin.attendance.holidays.store'), ['holiday_date' => '2026-12-25', 'name' => 'Natal', 'organizational_unit_id' => $this->unitA->id])->assertSessionHasNoErrors();
        $this->post(route('admin.attendance.holidays.store'), ['holiday_date' => '2026-12-25', 'name' => 'Natal', 'organizational_unit_id' => $this->unitA->id])->assertSessionHasErrors('holiday_date');
    }

    // ---- helpers ---------------------------------------------------------

    /** @return array{0: User, 1: Employee} */
    // ---- bukti lokasi & foto ----------------------------------------------

    private function requireSchoolLocation(bool $photo = false): void
    {
        $this->global->update([
            'require_location' => true, 'geo_latitude' => -0.8917, 'geo_longitude' => 119.8707,
            'geo_radius_m' => 100, 'require_photo' => $photo,
        ]);
    }

    public function test_location_policy_rejects_missing_far_and_inaccurate_positions_then_accepts_near(): void
    {
        $this->requireSchoolLocation();
        [$user] = $this->linkedEmployee($this->unitA);
        $this->actingAs($user, 'pegawai');
        Carbon::setTestNow('2026-10-21 00:10:00');

        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('location');
        $this->post(route('pegawai.check-in'), ['latitude' => -0.8817, 'longitude' => 119.8707, 'accuracy' => 20])
            ->assertSessionHasErrors('location'); // sekitar 1,1 km dari sekolah
        $this->post(route('pegawai.check-in'), ['latitude' => -0.8917, 'longitude' => 119.8707, 'accuracy' => 900])
            ->assertSessionHasErrors('location'); // akurasi GPS terlalu rendah
        $this->assertSame(0, AttendanceRecord::count());

        $this->post(route('pegawai.check-in'), ['latitude' => -0.8914, 'longitude' => 119.8707, 'accuracy' => 25])
            ->assertSessionHasNoErrors();
        $record = AttendanceRecord::firstOrFail();
        $this->assertEqualsWithDelta(33, $record->check_in_distance_m, 3);
        $this->assertSame(25, $record->check_in_accuracy_m);

        // check-out juga diperiksa
        Carbon::setTestNow('2026-10-21 08:00:00'); // lompatan melewati batas idle sesi, lihat test check-out lain
        $this->withSession(['pegawai_last_activity' => now()->timestamp])->post(route('pegawai.check-out'))->assertSessionHasErrors('location');
        $this->post(route('pegawai.check-out'), ['latitude' => -0.8914, 'longitude' => 119.8707, 'accuracy' => 25])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($record->fresh()->check_out_at);
    }

    public function test_without_location_or_photo_policy_attendance_works_as_before(): void
    {
        [$user] = $this->linkedEmployee($this->unitA);
        $this->actingAs($user, 'pegawai');
        Carbon::setTestNow('2026-10-21 00:10:00');

        $this->post(route('pegawai.check-in'))->assertSessionHasNoErrors();
        $this->assertNull(AttendanceRecord::firstOrFail()->check_in_lat);
    }

    public function test_photo_policy_requires_selfie_and_stores_it_privately(): void
    {
        Storage::fake('local');
        $this->global->update(['require_photo' => true]);
        [$user] = $this->linkedEmployee($this->unitA);
        $this->actingAs($user, 'pegawai');
        Carbon::setTestNow('2026-10-21 00:10:00');

        $this->post(route('pegawai.check-in'))->assertSessionHasErrors('photo');
        $this->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->assertSame(0, AttendanceRecord::count());

        $this->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('selfie.jpg', 320, 240)])
            ->assertSessionHasNoErrors();
        $record = AttendanceRecord::firstOrFail();
        $this->assertNotNull($record->check_in_photo);
        Storage::disk('local')->assertExists($record->check_in_photo);
    }

    public function test_attendance_photo_is_only_served_to_admins_in_scope(): void
    {
        Storage::fake('local');
        $this->global->update(['require_photo' => true]);
        [$user] = $this->linkedEmployee($this->unitA);
        $this->actingAs($user, 'pegawai');
        Carbon::setTestNow('2026-10-21 00:10:00');
        $this->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('selfie.jpg')]);
        $record = AttendanceRecord::firstOrFail();

        $this->actingAs($this->makeAdmin(['attendance.view'], $this->unitA), 'admin')
            ->get(route('admin.attendance.photo', [$record, 'in']))->assertOk();
        $this->actingAs($this->makeAdmin(['attendance.view'], $this->unitB), 'admin')
            ->get(route('admin.attendance.photo', [$record, 'in']))->assertForbidden();
        $this->actingAs($this->makeAdmin([], $this->unitA), 'admin')
            ->get(route('admin.attendance.photo', [$record, 'in']))->assertForbidden();
    }

    public function test_reused_photo_is_rejected_and_gallery_like_photo_is_flagged_for_admin(): void
    {
        Storage::fake('local');
        $this->global->update(['require_photo' => true]);
        [$user] = $this->linkedEmployee($this->unitA);
        $this->actingAs($user, 'pegawai');
        Carbon::setTestNow('2026-10-21 00:10:00');

        $img = imagecreatetruecolor(1200, 900); // lebih lebar dari hasil canvas portal (640 px)
        ob_start();
        imagejpeg($img);
        $bytes = ob_get_clean();
        $upload = function () use ($bytes) {
            $tmp = tempnam(sys_get_temp_dir(), 'ph');
            file_put_contents($tmp, $bytes);

            return new UploadedFile($tmp, 'selfie.jpg', 'image/jpeg', null, true);
        };

        $this->post(route('pegawai.check-in'), ['photo' => $upload()])->assertSessionHasNoErrors();
        $record = AttendanceRecord::firstOrFail();
        $this->assertSame(64, strlen($record->check_in_photo_hash));
        $this->assertStringContainsString('ukuran', (string) $record->check_in_photo_flag);
        Storage::disk('local')->assertExists($record->check_in_photo);

        $this->actingAs($this->makeAdmin(['attendance.view'], $this->unitA), 'admin')
            ->get(route('admin.attendance.daily', ['date' => '2026-10-21']))->assertOk()->assertSee('Foto perlu dicek');

        // berkas identik tidak boleh dipakai lagi untuk check-out; foto baru diterima
        $this->actingAs($user, 'pegawai');
        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->withSession(['pegawai_last_activity' => now()->timestamp])->post(route('pegawai.check-out'), ['photo' => $upload()])
            ->assertSessionHasErrors('photo');
        $this->assertNull($record->fresh()->check_out_at);
        $this->post(route('pegawai.check-out'), ['photo' => UploadedFile::fake()->image('baru.jpg', 320, 240)])->assertSessionHasNoErrors();
        $this->assertNotNull($record->fresh()->check_out_at);
    }

    public function test_photo_checks_can_be_switched_off_from_settings(): void
    {
        Storage::fake('local');
        config(['attendance.photo_reject_duplicate' => false, 'attendance.photo_flag_suspicious' => false, 'attendance.photo_watermark' => false]);
        $this->global->update(['require_photo' => true]);
        [$user] = $this->linkedEmployee($this->unitA);
        $this->actingAs($user, 'pegawai');
        Carbon::setTestNow('2026-10-21 00:10:00');

        $img = imagecreatetruecolor(1200, 900);
        ob_start();
        imagejpeg($img);
        $bytes = ob_get_clean();
        $upload = function () use ($bytes) {
            $tmp = tempnam(sys_get_temp_dir(), 'ph');
            file_put_contents($tmp, $bytes);

            return new UploadedFile($tmp, 'selfie.jpg', 'image/jpeg', null, true);
        };

        $this->post(route('pegawai.check-in'), ['photo' => $upload()])->assertSessionHasNoErrors();
        $record = AttendanceRecord::firstOrFail();
        $this->assertNull($record->check_in_photo_flag);          // penandaan dimatikan
        $this->assertSame(strlen($bytes), strlen(Storage::disk('local')->get($record->check_in_photo))); // tanpa watermark: berkas asli

        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->withSession(['pegawai_last_activity' => now()->timestamp])->post(route('pegawai.check-out'), ['photo' => $upload()])
            ->assertSessionHasNoErrors();                          // penolakan duplikat dimatikan
        $this->assertNotNull($record->fresh()->check_out_at);
    }

    public function test_prune_command_deletes_only_photos_older_than_retention_and_keeps_attendance_data(): void
    {
        Storage::fake('local');
        [, $employee] = $this->linkedEmployee($this->unitA);
        Carbon::setTestNow('2026-10-25 02:00:00');
        $disk = Storage::disk('local');
        $disk->put('attendance-proofs/2026-06-01/1/a.jpg', 'x');
        $disk->put('attendance-proofs/2026-10-20/1/b.jpg', 'y');
        $mk = fn (string $date, string $photo) => AttendanceRecord::create([
            'employee_id' => $employee->id, 'attendance_date' => $date, 'status' => 'present', 'source' => 'web',
            'check_in_photo' => $photo, 'check_in_photo_hash' => hash('sha256', $photo), 'check_in_lat' => -0.8917,
        ]);
        $old = $mk('2026-06-01', 'attendance-proofs/2026-06-01/1/a.jpg');
        $recent = $mk('2026-10-20', 'attendance-proofs/2026-10-20/1/b.jpg');

        $this->artisan('attendance:prune-photos', ['--days' => 90, '--dry-run' => true])->assertExitCode(0);
        $disk->assertExists('attendance-proofs/2026-06-01/1/a.jpg');

        $this->artisan('attendance:prune-photos', ['--days' => 90])->assertExitCode(0);
        $disk->assertMissing('attendance-proofs/2026-06-01/1/a.jpg');
        $disk->assertMissing('attendance-proofs/2026-06-01'); // folder kosong ikut dihapus
        $disk->assertExists('attendance-proofs/2026-10-20/1/b.jpg');
        $this->assertNull($old->fresh()->check_in_photo);
        $this->assertNotNull($old->fresh()->check_in_photo_hash); // sidik tetap ada: foto lama tetap tak bisa dipakai ulang
        $this->assertEqualsWithDelta(-0.8917, (float) $old->fresh()->check_in_lat, 0.0001);
        $this->assertNotNull($recent->fresh()->check_in_photo);
    }

    // ---- pencocokan wajah ------------------------------------------------

    private function faceOn(): void
    {
        Storage::fake('local');
        config(['face.driver' => 'http', 'face.http.url' => 'http://127.0.0.1:8099/compare', 'face.http.token' => 'rahasia']);
        $this->global->update(['require_photo' => true]);
        Carbon::setTestNow('2026-10-21 00:10:00');
    }

    private function withReference(Employee $employee): void
    {
        $path = "face-references/{$employee->id}/a.jpg";
        Storage::disk('local')->put($path, 'foto-acuan');
        EmployeeFaceReference::create(['employee_id' => $employee->id, 'path' => $path, 'consent_confirmed_at' => now()]);
    }

    public function test_face_matching_is_off_by_default_and_sends_nothing(): void
    {
        Storage::fake('local');
        Http::fake();
        $this->global->update(['require_photo' => true]);
        [$user, $employee] = $this->linkedEmployee($this->unitA);
        $this->withReference($employee);
        Carbon::setTestNow('2026-10-21 00:10:00');

        $this->actingAs($user, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('a.jpg', 320, 240)])
            ->assertSessionHasNoErrors();

        $record = AttendanceRecord::firstOrFail();
        $this->assertNull($record->check_in_face_status);
        Http::assertNothingSent();
    }

    public function test_face_score_is_classified_by_thresholds_and_attendance_is_never_rejected(): void
    {
        $this->faceOn();
        Http::fake(['127.0.0.1:8099/*' => Http::sequence()->push(['similarity' => 91.5])->push(['similarity' => 70])->push(['similarity' => 30])]);

        $expected = [['match', '91.50'], ['review', '70.00'], ['mismatch', '30.00']];
        foreach ($expected as $i => [$status, $score]) {
            [$user, $employee] = $this->linkedEmployee($this->unitA);
            $this->withReference($employee);
            $this->actingAs($user, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('a.jpg', 300 + $i * 10, 240)])
                ->assertSessionHasNoErrors();

            $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();
            $this->assertSame($status, $record->check_in_face_status);
            $this->assertEqualsWithDelta((float) $score, (float) $record->check_in_face_score, 0.001);
            $this->assertNotNull($record->check_in_at); // absen tetap tercatat
        }
    }

    public function test_face_matching_marks_no_reference_no_face_and_provider_error_without_breaking_attendance(): void
    {
        $this->faceOn();

        Http::fake(['127.0.0.1:8099/*' => Http::sequence()->push(['error' => 'no_face'], 422)->push('rusak', 500)]);

        // tanpa foto acuan
        [$u1] = $this->linkedEmployee($this->unitA);
        $this->actingAs($u1, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('a.jpg', 310, 240)])->assertSessionHasNoErrors();
        $this->assertSame('no_reference', AttendanceRecord::orderBy('id')->first()->check_in_face_status);
        Http::assertSentCount(0);

        // wajah tidak terdeteksi
        [$u2, $e2] = $this->linkedEmployee($this->unitA);
        $this->withReference($e2);
        $this->actingAs($u2, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('b.jpg', 320, 240)])->assertSessionHasNoErrors();
        $this->assertSame('no_face', AttendanceRecord::where('employee_id', $e2->id)->first()->check_in_face_status);

        // penyedia mati: absen tetap berhasil, status "error"
        [$u3, $e3] = $this->linkedEmployee($this->unitA);
        $this->withReference($e3);
        $this->actingAs($u3, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('c.jpg', 330, 240)])->assertSessionHasNoErrors();
        $record = AttendanceRecord::where('employee_id', $e3->id)->firstOrFail();
        $this->assertNotNull($record->check_in_at);
        $this->assertSame('error', $record->check_in_face_status);
        $this->assertNull($record->check_in_face_score);
    }

    public function test_face_match_runs_for_check_out_and_badges_show_in_admin_daily_report(): void
    {
        $this->faceOn();
        Http::fake(['127.0.0.1:8099/*' => Http::sequence()->push(['similarity' => 92])->push(['similarity' => 41])]);
        [$user, $employee] = $this->linkedEmployee($this->unitA);
        $this->withReference($employee);

        $this->actingAs($user, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('a.jpg', 300, 240)])->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->withSession(['pegawai_last_activity' => now()->timestamp])->post(route('pegawai.check-out'), ['photo' => UploadedFile::fake()->image('b.jpg', 310, 240)])
            ->assertSessionHasNoErrors();

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame('match', $record->check_in_face_status);
        $this->assertSame('mismatch', $record->check_out_face_status);

        $this->actingAs($this->makeAdmin(['attendance.view'], $this->unitA), 'admin')
            ->get(route('admin.attendance.daily', ['date' => '2026-10-21']))->assertOk()
            ->assertSee('Masuk: Wajah cocok 92%')->assertSee('Pulang: Wajah tidak cocok 41%');
    }

    public function test_face_reference_upload_requires_consent_is_scoped_and_limited(): void
    {
        Storage::fake('local');
        $employee = $this->makeEmployee($this->unitA);
        $other = $this->makeEmployee($this->unitB);
        $admin = $this->makeAdmin(['employees.faces.manage'], $this->unitA);
        $this->actingAs($admin, 'admin');
        $img = fn () => UploadedFile::fake()->image('wajah.jpg', 400, 400);

        // tanpa persetujuan ditolak
        $this->post(route('admin.employees.faces.store', $employee), ['photo' => $img()])->assertSessionHasErrors('consent');
        // terlalu kecil ditolak
        $this->post(route('admin.employees.faces.store', $employee), ['photo' => UploadedFile::fake()->image('k.jpg', 100, 100), 'consent' => '1'])->assertSessionHasErrors('photo');
        // pegawai di luar cakupan unit admin
        $this->post(route('admin.employees.faces.store', $other), ['photo' => $img(), 'consent' => '1'])->assertForbidden();

        // sukses sampai batas maksimum
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('admin.employees.faces.store', $employee), ['photo' => $img(), 'consent' => '1'])->assertSessionHasNoErrors();
        }
        $this->post(route('admin.employees.faces.store', $employee), ['photo' => $img(), 'consent' => '1'])->assertSessionHasErrors('photo');
        $this->assertSame(3, $employee->faceReferences()->count());
        $this->assertNotNull($employee->faceReferences()->first()->consent_confirmed_at);

        // gambar hanya untuk admin berizin dan dalam cakupan; hapus menghapus berkas
        $face = $employee->faceReferences()->first();
        $this->get(route('admin.employees.faces.image', [$employee, $face]))->assertOk();
        $this->actingAs($this->makeAdmin(['employees.view'], $this->unitA), 'admin')
            ->get(route('admin.employees.faces.image', [$employee, $face]))->assertForbidden();
        $this->actingAs($admin, 'admin')->delete(route('admin.employees.faces.destroy', [$employee, $face]))->assertRedirect();
        Storage::disk('local')->assertMissing($face->path);
        $this->assertSame(2, $employee->faceReferences()->count());
    }

    // ---- periksa foto & pencocokan ulang ---------------------------------

    /** @return array{0: Employee, 1: Employee, 2: Employee} pegawai berfoto ber-penanda & bersih di unit A, dan ber-penanda di unit B */
    private function photoReviewFixture(): array
    {
        Storage::fake('local');
        $this->global->update(['require_photo' => true]);
        Carbon::setTestNow('2026-10-21 00:10:00');
        $checkIn = function (Employee $e, $user, int $w) {
            $this->actingAs($user, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('a.jpg', $w, 480)])->assertSessionHasNoErrors();
        };
        [$uA, $flagged] = $this->linkedEmployee($this->unitA);
        $checkIn($flagged, $uA, 1200);   // lebar > 640 px: bertanda
        [$uB, $clean] = $this->linkedEmployee($this->unitA);
        $checkIn($clean, $uB, 320);      // seperti hasil kamera: tanpa tanda
        [$uC, $other] = $this->linkedEmployee($this->unitB);
        $checkIn($other, $uC, 1300);     // bertanda tetapi di unit lain

        return [$flagged, $clean, $other];
    }

    public function test_photo_review_lists_only_flagged_photos_in_scope_and_admin_decisions_are_recorded(): void
    {
        [$flagged, $clean, $other] = $this->photoReviewFixture();
        $admin = $this->makeAdmin(['attendance.view', 'attendance.photos.review'], $this->unitA);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.attendance.photos.index'))->assertOk()
            ->assertViewHas('counts', fn ($c) => $c['perlu'] === 1 && $c['semua'] === 2 && $c['ok'] === 0)
            ->assertViewHas('records', fn ($p) => $p->pluck('employee_id')->all() === [$flagged->id]);

        $record = AttendanceRecord::where('employee_id', $flagged->id)->firstOrFail();

        // mencurigakan wajib bercatatan
        $this->post(route('admin.attendance.photos.review', $record), ['decision' => 'suspicious'])->assertSessionHasErrors('note');
        $this->assertNull($record->fresh()->photo_review_status);

        $this->post(route('admin.attendance.photos.review', $record), ['decision' => 'suspicious', 'note' => 'Wajah berbeda dari pegawai'])->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('suspicious', $record->photo_review_status);
        $this->assertSame($admin->id, $record->photo_reviewed_by_admin_id);
        $this->assertNotNull($record->photo_reviewed_at);
        $log = AdminAuditLog::where('event', 'attendance.photo_reviewed')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('Wajah berbeda', json_encode($log->details)); // catatan bebas tidak masuk riwayat keamanan

        // keputusan memindahkan absen dari "perlu" ke tab hasilnya
        $this->get(route('admin.attendance.photos.index'))->assertViewHas('counts', fn ($c) => $c['perlu'] === 0 && $c['suspicious'] === 1);
        $this->get(route('admin.attendance.photos.index', ['status' => 'suspicious']))->assertSee('Wajah berbeda dari pegawai');

        // lencana di rekap harian
        $this->get(route('admin.attendance.daily', ['date' => '2026-10-21']))->assertSee('Foto mencurigakan');

        $this->post(route('admin.attendance.photos.review', $record), ['decision' => 'ok'])->assertSessionHasNoErrors();
        $this->get(route('admin.attendance.daily', ['date' => '2026-10-21']))->assertSee('Foto diverifikasi')->assertDontSee('Foto perlu dicek');

        $this->post(route('admin.attendance.photos.review', $record), ['decision' => 'reset'])->assertSessionHasNoErrors();
        $this->assertNull($record->fresh()->photo_review_status);
        $this->assertNull($record->fresh()->photo_reviewed_by_admin_id);
        $this->get(route('admin.attendance.daily', ['date' => '2026-10-21']))->assertSee('Foto perlu dicek');
    }

    public function test_photo_review_decisions_need_permission_and_scope(): void
    {
        [$flagged, , $other] = $this->photoReviewFixture();
        $recordA = AttendanceRecord::where('employee_id', $flagged->id)->firstOrFail();
        $recordB = AttendanceRecord::where('employee_id', $other->id)->firstOrFail();

        // hanya melihat: boleh membuka daftar, tidak boleh memutuskan
        $viewer = $this->makeAdmin(['attendance.view'], $this->unitA);
        $this->actingAs($viewer, 'admin')->get(route('admin.attendance.photos.index'))->assertOk()->assertDontSee('Mencurigakan</button>', false);
        $this->post(route('admin.attendance.photos.review', $recordA), ['decision' => 'ok'])->assertForbidden();
        $this->post(route('admin.attendance.photos.rematch', $recordA))->assertForbidden();

        // pemutus unit A tidak boleh menyentuh absen unit B
        $reviewer = $this->makeAdmin(['attendance.view', 'attendance.photos.review'], $this->unitA);
        $this->actingAs($reviewer, 'admin')->post(route('admin.attendance.photos.review', $recordB), ['decision' => 'ok'])->assertForbidden();
        $this->assertNull($recordB->fresh()->photo_review_status);
    }

    public function test_new_check_out_photo_invalidates_an_earlier_review(): void
    {
        [$flagged] = $this->photoReviewFixture();
        $record = AttendanceRecord::where('employee_id', $flagged->id)->firstOrFail();
        $admin = $this->makeAdmin(['attendance.view', 'attendance.photos.review'], $this->unitA);
        $this->actingAs($admin, 'admin')->post(route('admin.attendance.photos.review', $record), ['decision' => 'ok'])->assertSessionHasNoErrors();
        $this->assertSame('ok', $record->fresh()->photo_review_status);

        $user = \App\Models\User::findOrFail($flagged->user_id);
        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->actingAs($user, 'pegawai')->withSession(['pegawai_last_activity' => now()->timestamp])
            ->post(route('pegawai.check-out'), ['photo' => UploadedFile::fake()->image('o.jpg', 1100, 480)])->assertSessionHasNoErrors();

        $this->assertNull($record->fresh()->photo_review_status); // foto baru: perlu diputuskan lagi
    }

    public function test_rematch_button_queues_jobs_only_when_face_matching_is_on(): void
    {
        [$flagged] = $this->photoReviewFixture();
        $record = AttendanceRecord::where('employee_id', $flagged->id)->firstOrFail();
        $admin = $this->makeAdmin(['attendance.view', 'attendance.photos.review'], $this->unitA);
        $this->actingAs($admin, 'admin');

        Queue::fake();
        $this->post(route('admin.attendance.photos.rematch', $record))->assertSessionHasErrors('face');
        Queue::assertNothingPushed();

        config(['face.driver' => 'http']);
        $this->post(route('admin.attendance.photos.rematch', $record))->assertSessionHasNoErrors();
        Queue::assertPushed(MatchAttendanceFace::class, fn ($job) => $job->recordId === $record->id && $job->prefix === 'check_in');
        Queue::assertPushed(MatchAttendanceFace::class, 1); // hanya foto masuk yang ada
    }

    public function test_face_rematch_command_retries_unresolved_statuses_and_reclassifies_stored_scores(): void
    {
        $this->faceOn();
        config(['face.driver' => 'off']);
        Http::fake(['127.0.0.1:8099/*' => Http::response(['similarity' => 90])]);

        [$u1, $e1] = $this->linkedEmployee($this->unitA);
        $this->withReference($e1);
        $this->actingAs($u1, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('a.jpg', 300, 240)])->assertSessionHasNoErrors();
        [$u2, $e2] = $this->linkedEmployee($this->unitA);
        $this->actingAs($u2, 'pegawai')->post(route('pegawai.check-in'), ['photo' => UploadedFile::fake()->image('b.jpg', 310, 240)])->assertSessionHasNoErrors();
        $r1 = AttendanceRecord::where('employee_id', $e1->id)->firstOrFail();
        $r2 = AttendanceRecord::where('employee_id', $e2->id)->firstOrFail();
        $this->assertNull($r1->check_in_face_status); // dibuat saat pencocokan mati

        // penyedia mati: perintah menolak
        $this->artisan('face:rematch')->assertExitCode(1);

        config(['face.driver' => 'http']);
        $this->artisan('face:rematch', ['--dry-run' => true, '--sync' => true])->assertExitCode(0);
        Http::assertSentCount(0);
        $this->assertNull($r1->fresh()->check_in_face_status);

        $this->artisan('face:rematch', ['--sync' => true])->assertExitCode(0);
        $this->assertSame('match', $r1->fresh()->check_in_face_status);
        $this->assertSame('no_reference', $r2->fresh()->check_in_face_status);
        Http::assertSentCount(1);

        // yang sudah punya hasil tidak diulang; yang "no_reference" baru diproses setelah foto acuan ada
        $this->artisan('face:rematch', ['--sync' => true])->assertExitCode(0);
        Http::assertSentCount(1);
        $this->withReference($e2);
        $this->artisan('face:rematch', ['--sync' => true])->assertExitCode(0);
        $this->assertSame('match', $r2->fresh()->check_in_face_status);
        Http::assertSentCount(2);

        // batas diubah: hitung ulang dari skor tersimpan tanpa memanggil penyedia
        config(['face.match_threshold' => 95, 'face.review_threshold' => 85]);
        $this->artisan('face:rematch', ['--reclassify' => true])->assertExitCode(0);
        $this->assertSame('review', $r1->fresh()->check_in_face_status);
        $this->assertEqualsWithDelta(90.0, (float) $r1->fresh()->check_in_face_score, 0.001);
        Http::assertSentCount(2);

        $this->artisan('face:rematch', ['--status' => ['salah']])->assertExitCode(1);
    }

    private function makePolicy(OrganizationalUnit $unit, string $timezone): AttendancePolicy
    {
        return AttendancePolicy::create([
            'organizational_unit_id' => $unit->id, 'name' => "Kebijakan {$unit->code}", 'work_days' => [1, 2, 3, 4, 5, 6],
            'work_start' => '07:00', 'work_end' => '15:00', 'late_tolerance_minutes' => 15, 'timezone' => $timezone, 'is_active' => true,
        ]);
    }

    private function linkedEmployee(OrganizationalUnit $unit, string $start = '2026-01-01', ?string $name = null): array
    {
        $user = $this->makePegawaiAccount();
        $employee = $this->makeEmployee($unit, $user, $start, $name);

        return [$user, $employee];
    }

    private function makePegawaiAccount(): User
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => "pegawai-$n", 'label' => "Pegawai $n", 'guard_name' => 'pegawai']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'attendance.self', 'guard_name' => 'pegawai'], ['label' => 'attendance.self'])->id);
        $user = User::create(['name' => "Pegawai $n", 'username' => "pegawai$n", 'email' => "pegawai$n@example.test", 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $user->syncRoles([$role->id]);

        return $user;
    }

    private function makeEmployee(OrganizationalUnit $unit, ?User $user = null, string $start = '2026-01-01', ?string $name = null): Employee
    {
        $n = ++$this->seq;
        $employee = Employee::create([
            'employee_number' => "E-$n", 'name' => $name ?? "Pegawai $n", 'employee_type' => 'teacher',
            'status' => EmployeeStatus::Active, 'user_id' => $user?->id,
        ]);
        $position = OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $unit->id, 'name' => 'Guru'], ['is_active' => true]);
        $employee->assignments()->create([
            'organizational_unit_id' => $unit->id, 'organizational_position_id' => $position->id, 'is_primary' => true, 'start_date' => $start,
        ]);

        return $employee;
    }

    private function makeAdmin(array $permissions, ?OrganizationalUnit $scope = null, bool $super = false): Admin
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => $super ? "super-admin" : "role-$n", 'label' => "Role $n", 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }
        $admin = Admin::create(['name' => "Admin $n", 'username' => "admin$n", 'email' => "admin$n@example.test", 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);
        if ($scope) {
            UserUnitAccess::create(['organizational_unit_id' => $scope->id, 'model_type' => Admin::class, 'model_id' => $admin->id, 'created_at' => now()]);
        }

        return $admin;
    }
}
