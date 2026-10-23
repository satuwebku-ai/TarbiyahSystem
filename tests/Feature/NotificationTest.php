<?php

namespace Tests\Feature;

use App\Enums\CorrectionStatus;
use App\Events\ActivityReportSubmitted;
use App\Events\WorkProgramSubmitted;
use App\Helpers\AttendanceClock;
use App\Listeners\NotifyProgramApprovers;
use App\Models\Admin;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserUnitAccess;
use App\Models\User;
use App\Models\WorkActivity;
use App\Models\WorkProgram;
use App\Notifications\WorkflowNotification;
use App\Services\AttendanceService;
use App\Services\ReminderService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

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

    // ---- alur program kerja ----------------------------------------------------

    public function test_submitting_a_program_notifies_only_approvers_in_scope(): void
    {
        $creator = $this->admin('pembuat', ['programs.view', 'programs.manage', 'programs.submit'], [$this->unitA]);
        $approverA = $this->admin('penyetuju-a', ['programs.view', 'programs.approve'], [$this->unitA]);
        $approverB = $this->admin('penyetuju-b', ['programs.view', 'programs.approve'], [$this->unitB]);
        $inactive = $this->admin('nonaktif', ['programs.approve'], [$this->unitA]);
        $inactive->update(['is_active' => false]);
        $noPermission = $this->admin('biasa', ['programs.view'], [$this->unitA]);
        $root = $this->admin('induk', ['programs.approve'], [$this->root]);

        $program = $this->program($this->unitA, $creator);
        $this->actingAs($creator, 'admin')
            ->post(route('admin.programs.submit', $program))->assertSessionHasNoErrors();

        $this->assertSame(1, $approverA->notifications()->count());
        $this->assertSame(1, $root->notifications()->count(), 'Penyetuju di unit induk mencakup unit turunan.');
        $this->assertSame(0, $approverB->notifications()->count(), 'Penyetuju unit lain tidak boleh diberi tahu.');
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $noPermission->notifications()->count());
        $this->assertSame(0, $creator->notifications()->count(), 'Pelaku tidak diberi tahu tentang tindakannya sendiri.');

        $data = $approverA->notifications()->first()->data;
        $this->assertSame('program.submitted', $data['kind']);
        $this->assertSame('admin.programs.show', $data['route']);
        $this->assertStringContainsString($program->title, $data['body']);
    }

    public function test_review_notifies_the_requester_with_the_rejection_note(): void
    {
        $creator = $this->admin('pembuat', ['programs.view', 'programs.manage', 'programs.submit'], [$this->unitA]);
        $approver = $this->admin('penyetuju', ['programs.view', 'programs.approve'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator);

        $this->actingAs($creator, 'admin')->post(route('admin.programs.submit', $program));
        $this->actingAs($approver, 'admin')
            ->patch(route('admin.programs.review', $program), ['decision' => 'rejected', 'note' => 'Anggaran belum rinci.'])
            ->assertSessionHasNoErrors();

        $data = $creator->notifications()->latest()->firstOrFail()->data;
        $this->assertSame('program.reviewed', $data['kind']);
        $this->assertSame('Program kerja perlu revisi', $data['title']);
        $this->assertStringContainsString('Anggaran belum rinci.', $data['body']);
        $this->assertSame(1, $approver->notifications()->count(), 'Pemutus hanya menerima notifikasi pengajuan, bukan hasil keputusannya sendiri.');
    }

    public function test_failed_state_change_does_not_dispatch_events(): void
    {
        Event::fake([WorkProgramSubmitted::class]);
        $creator = $this->admin('pembuat', ['programs.view', 'programs.manage', 'programs.submit'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator);
        $program->update(['status' => 'approved']);

        $this->actingAs($creator, 'admin')->post(route('admin.programs.submit', $program))->assertStatus(422);

        Event::assertNotDispatched(WorkProgramSubmitted::class);
    }

    // ---- laporan kegiatan ------------------------------------------------------

    public function test_activity_report_submit_and_review_notify_the_right_people(): void
    {
        $creator = $this->admin('pembuat', ['programs.view', 'programs.manage', 'programs.submit'], [$this->unitA]);
        $approver = $this->admin('penyetuju', ['programs.view', 'programs.approve'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator, 'approved');
        $activity = $this->activity($program, $creator, 'in_progress');
        $activity->report()->create(['summary' => 'Ringkasan laporan yang cukup panjang.', 'status' => 'draft']);

        $this->actingAs($creator, 'admin')
            ->post(route('admin.programs.activities.report.submit', [$program, $activity]))->assertSessionHasNoErrors();
        $this->assertSame('report.submitted', $approver->notifications()->firstOrFail()->data['kind']);

        $this->actingAs($approver, 'admin')
            ->patch(route('admin.programs.activities.report.review', [$program, $activity]), [
                'decision' => 'revision_requested', 'evaluation_note' => 'Lengkapi dokumentasi.',
            ])->assertSessionHasNoErrors();

        $data = $creator->notifications()->firstOrFail()->data;
        $this->assertSame('report.reviewed', $data['kind']);
        $this->assertStringContainsString('Lengkapi dokumentasi.', $data['body']);
    }

    public function test_report_event_is_dispatched_on_submit(): void
    {
        Event::fake([ActivityReportSubmitted::class]);
        $creator = $this->admin('pembuat', ['programs.view', 'programs.manage', 'programs.submit'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator, 'approved');
        $activity = $this->activity($program, $creator, 'in_progress');
        $activity->report()->create(['summary' => 'Ringkasan laporan yang cukup panjang.', 'status' => 'draft']);

        $this->actingAs($creator, 'admin')->post(route('admin.programs.activities.report.submit', [$program, $activity]));

        Event::assertDispatched(ActivityReportSubmitted::class);
    }

    // ---- koreksi absensi -------------------------------------------------------

    public function test_attendance_correction_notifies_reviewers_then_the_employee(): void
    {
        $this->policy();
        $employeeAdmin = $this->pegawai('pegawai-a');
        $employee = $this->employee('P-001', $this->unitA, $employeeAdmin);
        $reviewer = $this->admin('peninjau-a', ['attendance.corrections.review', 'dashboard.view'], [$this->unitA]);
        $otherUnitReviewer = $this->admin('peninjau-b', ['attendance.corrections.review'], [$this->unitB]);

        $service = app(AttendanceService::class);
        $correction = $service->submitCorrection($employee, $employeeAdmin, [
            'attendance_date' => AttendanceClock::today()->subDay()->toDateString(),
            'requested_check_in' => '07:00', 'requested_check_out' => '15:00', 'reason' => 'Lupa absen.',
        ]);

        $this->assertSame('correction.submitted', $reviewer->notifications()->firstOrFail()->data['kind']);
        $this->assertSame(0, $otherUnitReviewer->notifications()->count());
        $this->assertSame(0, $employeeAdmin->notifications()->count());

        $service->reviewCorrection($correction, false, 'Bukti tidak cukup.', $reviewer);

        $data = $employeeAdmin->notifications()->firstOrFail()->data;
        $this->assertSame('Koreksi absensi ditolak', $data['title']);
        $this->assertStringContainsString('Bukti tidak cukup.', $data['body']);
        $this->assertSame('pegawai.dashboard', $data['route'], 'Akun pegawai tanpa akses dashboard diarahkan ke portal pegawai.');
    }

    public function test_approved_correction_notifies_the_employee(): void
    {
        $this->policy();
        $employeeAdmin = $this->pegawai('pegawai-a');
        $employee = $this->employee('P-001', $this->unitA, $employeeAdmin);
        $reviewer = $this->admin('peninjau-a', ['attendance.corrections.review'], [$this->unitA]);

        $service = app(AttendanceService::class);
        $correction = $service->submitCorrection($employee, $employeeAdmin, [
            'attendance_date' => AttendanceClock::today()->subDay()->toDateString(),
            'requested_check_in' => '07:00', 'requested_check_out' => '15:00', 'reason' => 'Lupa absen.',
        ]);
        $service->reviewCorrection($correction, true, null, $reviewer);

        $this->assertSame('Koreksi absensi disetujui', $employeeAdmin->notifications()->firstOrFail()->data['title']);
        $this->assertSame(CorrectionStatus::Approved, $correction->refresh()->status);
    }

    // ---- kotak masuk -----------------------------------------------------------

    public function test_inbox_lists_own_notifications_and_marks_them_read_when_opened(): void
    {
        $creator = $this->admin('pembuat', ['programs.view', 'programs.manage', 'programs.submit', 'dashboard.view'], [$this->unitA]);
        $approver = $this->admin('penyetuju', ['programs.view', 'programs.approve', 'dashboard.view'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator);
        $this->actingAs($creator, 'admin')->post(route('admin.programs.submit', $program));

        $this->actingAs($approver, 'admin')
            ->get(route('admin.dashboard'))->assertOk()->assertSee('Notifikasi');
        $this->get(route('admin.notifications.index'))
            ->assertOk()->assertSee('Program kerja menunggu persetujuan')->assertSee('Baru');

        $notification = $approver->notifications()->firstOrFail();
        $this->get(route('admin.notifications.open', $notification->id))
            ->assertRedirect(route('admin.programs.show', $program->id));
        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertSame(0, $approver->unreadNotifications()->count());
    }

    public function test_user_cannot_open_or_read_someone_elses_notification(): void
    {
        $owner = $this->admin('pemilik', ['dashboard.view'], []);
        $intruder = $this->admin('penyusup', ['dashboard.view'], []);
        $owner->notify(new WorkflowNotification('program.submitted', 'Rahasia', 'Isi pribadi', 'admin.notifications.index'));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($intruder, 'admin')->get(route('admin.notifications.open', $notification->id))->assertNotFound();
        $this->get(route('admin.notifications.index'))->assertOk()->assertDontSee('Rahasia');
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_mark_all_read_only_touches_own_notifications(): void
    {
        $me = $this->admin('saya', ['dashboard.view'], []);
        $other = $this->admin('lain', ['dashboard.view'], []);
        foreach ([$me, $other] as $admin) {
            $admin->notify(new WorkflowNotification('program.submitted', 'Judul', 'Isi'));
        }

        $this->actingAs($me, 'admin')->post(route('admin.notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $me->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_notification_with_a_removed_route_falls_back_to_the_inbox(): void
    {
        $me = $this->admin('saya', ['dashboard.view'], []);
        $me->notify(new WorkflowNotification('program.submitted', 'Judul', 'Isi', 'route.yang.sudah.dihapus'));

        $this->actingAs($me, 'admin')
            ->get(route('admin.notifications.open', $me->notifications()->firstOrFail()->id))
            ->assertRedirect(route('admin.notifications.index'));
    }

    public function test_pegawai_portal_has_its_own_inbox(): void
    {
        $this->policy();
        $employeeAdmin = $this->pegawai('pegawai-a');
        $this->employee('P-001', $this->unitA, $employeeAdmin);
        $employeeAdmin->notify(new WorkflowNotification('correction.reviewed', 'Koreksi absensi disetujui', 'Isi', 'pegawai.dashboard'));

        $this->actingAs($employeeAdmin, 'pegawai')
            ->get(route('pegawai.notifications.index'))
            ->assertOk()->assertSee('Koreksi absensi disetujui');
    }

    // ---- saluran ---------------------------------------------------------------

    public function test_mail_channel_follows_config_and_email_presence(): void
    {
        $admin = $this->admin('saya', [], []);
        $notification = new WorkflowNotification('program.submitted', 'Judul', 'Isi');

        config(['notifications.mail' => true]);
        $this->assertSame(['database', 'mail'], $notification->via($admin));

        config(['notifications.mail' => false]);
        $this->assertSame(['database'], $notification->via($admin));

        config(['notifications.mail' => true]);
        $admin->email = '';
        $this->assertSame(['database'], $notification->via($admin));
    }

    public function test_mail_message_contains_the_link(): void
    {
        $admin = $this->admin('saya', [], []);
        $mail = (new WorkflowNotification('program.submitted', 'Judul', 'Isi', 'admin.notifications.index'))->toMail($admin);

        $this->assertSame('Judul', $mail->subject);
        $this->assertSame(route('admin.notifications.index'), $mail->actionUrl);
    }

    public function test_listener_failure_is_logged(): void
    {
        Log::shouldReceive('error')->once()->withArgs(fn ($message, $context) => $context['listener'] === NotifyProgramApprovers::class);
        $creator = $this->admin('pembuat', [], []);
        $event = new WorkProgramSubmitted($this->program($this->unitA, $creator), $creator);

        (new NotifyProgramApprovers(app(\App\Services\NotificationRecipients::class)))->failed($event, new Exception('gagal'));
    }

    // ---- pengingat terjadwal ---------------------------------------------------

    public function test_pending_approval_reminder_is_a_digest_with_a_threshold_and_no_duplicates(): void
    {
        $creator = $this->admin('pembuat', [], [$this->unitA]);
        $approver = $this->admin('penyetuju', ['programs.approve'], [$this->unitA]);
        $old = $this->program($this->unitA, $creator, 'submitted', 'OLD');
        $old->update(['submitted_at' => now()->subDays(5)]);
        $fresh = $this->program($this->unitA, $creator, 'submitted', 'NEW');
        $fresh->update(['submitted_at' => now()->subDay()]);

        $service = app(ReminderService::class);
        $this->assertSame(1, $service->pendingApprovals(3));
        $this->assertSame(0, $service->pendingApprovals(3), 'Pengingat yang sama tidak dikirim dua kali pada hari yang sama.');

        $data = $approver->notifications()->firstOrFail()->data;
        $this->assertSame('reminder.pending', $data['kind']);
        $this->assertStringContainsString('1 program kerja', $data['body']);
    }

    public function test_pending_reminder_does_not_reach_approvers_outside_the_scope(): void
    {
        $creator = $this->admin('pembuat', [], [$this->unitA]);
        $outsider = $this->admin('luar', ['programs.approve'], [$this->unitB]);
        $program = $this->program($this->unitA, $creator, 'submitted');
        $program->update(['submitted_at' => now()->subDays(10)]);

        $this->assertSame(0, app(ReminderService::class)->pendingApprovals(3));
        $this->assertSame(0, $outsider->notifications()->count());
    }

    public function test_upcoming_activity_reminder_reaches_creator_and_participant_accounts(): void
    {
        $this->policy();
        $creator = $this->admin('pembuat', ['programs.view', 'programs.manage'], [$this->unitA]);
        $participantAdmin = $this->pegawai('peserta');
        $participant = $this->employee('P-002', $this->unitA, $participantAdmin);
        $program = $this->program($this->unitA, $creator, 'approved');

        $tomorrow = AttendanceClock::today()->addDay()->toDateString();
        $activity = $this->activity($program, $creator, 'planned', AttendanceClock::fromLocal($tomorrow, '09:00'));
        $activity->participants()->create(['employee_id' => $participant->id, 'participant_name' => 'Peserta', 'attendance_status' => 'planned']);
        // kegiatan lusa & kegiatan yang sudah berjalan tidak ikut
        $this->activity($program, $creator, 'planned', AttendanceClock::fromLocal(AttendanceClock::today()->addDays(2)->toDateString(), '09:00'), 'ACT-LUSA');
        $this->activity($program, $creator, 'in_progress', AttendanceClock::fromLocal($tomorrow, '10:00'), 'ACT-JALAN');

        $this->assertSame(2, app(ReminderService::class)->upcomingActivities(1));
        $this->assertSame(0, app(ReminderService::class)->upcomingActivities(1));

        $creatorData = $creator->notifications()->firstOrFail()->data;
        $this->assertSame('admin.programs.show', $creatorData['route']);
        $this->assertStringContainsString('besok', $creatorData['body']);
        $this->assertNull($participantAdmin->notifications()->firstOrFail()->data['route'], 'Peserta tanpa izin program tidak diberi tautan yang akan ditolak.');
    }

    public function test_missing_checkout_reminder_skips_covered_cases(): void
    {
        $this->policy();
        $yesterday = AttendanceClock::today()->subDay();
        $make = function (string $number, array $record = []) use ($yesterday) {
            $admin = $this->pegawai('akun-'.$number);
            $employee = $this->employee($number, $this->unitA, $admin);
            AttendanceRecord::create([
                'employee_id' => $employee->id, 'organizational_unit_id' => $this->unitA->id,
                'attendance_date' => $yesterday->toDateString(), 'status' => 'present',
                'check_in_at' => AttendanceClock::fromLocal($yesterday->toDateString(), '07:00'),
                ...$record,
            ]);

            return [$admin, $employee];
        };

        [$forgot] = $make('P-1');
        [$checkedOut] = $make('P-2', ['check_out_at' => AttendanceClock::fromLocal($yesterday->toDateString(), '15:00')]);
        [$hasCorrection, $emp3] = $make('P-3');
        AttendanceCorrection::create([
            'employee_id' => $emp3->id, 'attendance_date' => $yesterday->toDateString(), 'requested_check_out' => '15:00',
            'reason' => 'Lupa', 'status' => CorrectionStatus::Pending, 'requested_by_user_id' => $hasCorrection->id,
        ]);
        [$resigned, $emp4] = $make('P-4');
        $emp4->update(['status' => 'resigned']);

        $this->assertSame(1, app(ReminderService::class)->missingCheckouts());
        $this->assertSame(1, $forgot->notifications()->count());
        $this->assertSame('reminder.checkout', $forgot->notifications()->first()->data['kind']);
        foreach ([$checkedOut, $hasCorrection, $resigned] as $other) {
            $this->assertSame(0, $other->notifications()->count());
        }
        $this->assertSame(0, app(ReminderService::class)->missingCheckouts(), 'Tidak ada pengingat ganda.');
    }

    public function test_prune_removes_only_old_read_notifications(): void
    {
        $admin = $this->admin('saya', [], []);
        foreach (['lama-dibaca', 'lama-belum', 'baru-dibaca'] as $title) {
            $admin->notify(new WorkflowNotification('program.submitted', $title, 'Isi'));
        }
        $rows = $admin->notifications()->get()->keyBy(fn ($n) => $n->data['title']);
        $rows['lama-dibaca']->forceFill(['read_at' => now()->subDays(100)])->save();
        $rows['baru-dibaca']->forceFill(['read_at' => now()->subDays(2)])->save();

        $this->assertSame(1, app(ReminderService::class)->pruneRead(90));
        $this->assertEqualsCanonicalizing(['lama-belum', 'baru-dibaca'], $admin->notifications()->get()->map(fn ($n) => $n->data['title'])->all());
    }

    public function test_artisan_commands_run_and_are_scheduled(): void
    {
        $this->artisan('reminders:pending-approvals', ['--days' => 3])->assertSuccessful();
        $this->artisan('reminders:upcoming-activities')->assertSuccessful();
        $this->artisan('reminders:missing-checkout')->assertSuccessful();
        $this->artisan('notifications:prune')->assertSuccessful();

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');
        foreach (['reminders:pending-approvals', 'reminders:upcoming-activities', 'reminders:missing-checkout', 'notifications:prune'] as $command) {
            $this->assertStringContainsString($command, $scheduled);
        }
    }

    public function test_listeners_are_queued_and_do_not_run_inside_the_request(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $approver = $this->admin('penyetuju', ['programs.approve'], [$this->unitA]);
        $creator = $this->admin('pembuat', ['programs.manage', 'programs.submit'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator);

        $this->actingAs($creator, 'admin')->post(route('admin.programs.submit', $program));

        \Illuminate\Support\Facades\Queue::assertPushed(
            \Illuminate\Events\CallQueuedListener::class,
            fn ($job) => $job->class === NotifyProgramApprovers::class,
        );
        $this->assertSame(0, $approver->notifications()->count());
    }

    public function test_flow_works_end_to_end_through_the_database_queue(): void
    {
        config(['queue.default' => 'database']);
        $approver = $this->admin('penyetuju', ['programs.approve'], [$this->unitA]);
        $creator = $this->admin('pembuat', ['programs.manage', 'programs.submit'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator);

        $this->actingAs($creator, 'admin')->post(route('admin.programs.submit', $program))->assertSessionHasNoErrors();
        $this->assertSame(0, $approver->notifications()->count(), 'Belum ada yang diproses sebelum worker berjalan.');
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('jobs')->count());

        $this->artisan('queue:work', ['--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();

        $this->assertSame(1, $approver->notifications()->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('failed_jobs')->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count());
    }

    public function test_notification_sent_to_approver_is_a_queueable_workflow_notification(): void
    {
        Notification::fake();
        $approver = $this->admin('penyetuju', ['programs.approve'], [$this->unitA]);
        $creator = $this->admin('pembuat', ['programs.manage', 'programs.submit'], [$this->unitA]);
        $program = $this->program($this->unitA, $creator);

        $this->actingAs($creator, 'admin')->post(route('admin.programs.submit', $program));

        Notification::assertSentTo($approver, WorkflowNotification::class, fn ($n) => $n->kind === 'program.submitted');
        Notification::assertNotSentTo($creator, WorkflowNotification::class);
        $this->assertContains(\Illuminate\Contracts\Queue\ShouldQueue::class, class_implements(WorkflowNotification::class));
    }

    // ---- fixture ---------------------------------------------------------------

    private function admin(string $username, array $permissions, array $units): Admin
    {
        $role = Role::create(['name' => 'role-'.$username, 'label' => $username, 'guard_name' => 'admin', 'is_system' => false]);
        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin'], ['label' => $name, 'group' => 'Uji']);
            $role->permissions()->attach($permission->id);
        }
        $admin = Admin::create([
            'name' => ucfirst($username), 'username' => $username, 'email' => $username.'@example.test',
            'password' => 'Secure-Test-Password-2026!', 'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);
        foreach ($units as $unit) {
            UserUnitAccess::create(['organizational_unit_id' => $unit->id, 'model_type' => Admin::class, 'model_id' => $admin->id, 'created_at' => now()]);
        }

        return $admin;
    }

    /** Akun pegawai/guru (tabel users, guard pegawai) dengan izin absen sendiri. */
    private function pegawai(string $username): User
    {
        $role = Role::create(['name' => 'role-'.$username, 'label' => $username, 'guard_name' => 'pegawai', 'is_system' => false]);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'attendance.self', 'guard_name' => 'pegawai'], ['label' => 'attendance.self', 'group' => 'Uji'])->id);
        $user = User::create([
            'name' => ucfirst($username), 'username' => $username, 'email' => $username.'@example.test',
            'password' => 'Secure-Test-Password-2026!', 'is_active' => true,
        ]);
        $user->syncRoles([$role->id]);

        return $user;
    }

    private function program(OrganizationalUnit $unit, Admin $creator, string $status = 'draft', ?string $code = null): WorkProgram
    {
        $this->seq++;

        return WorkProgram::create([
            'organizational_unit_id' => $unit->id, 'code' => $code ?? 'PRG-'.$this->seq, 'title' => 'Program '.$this->seq,
            'start_date' => today()->toDateString(), 'end_date' => today()->addMonth()->toDateString(),
            'estimated_budget' => 100000, 'status' => $status, 'created_by_admin_id' => $creator->id,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);
    }

    private function activity(WorkProgram $program, Admin $creator, string $status, ?\Carbon\CarbonInterface $start = null, ?string $code = null): WorkActivity
    {
        $this->seq++;
        $start ??= now()->addDays(10);

        return WorkActivity::create([
            'work_program_id' => $program->id, 'organizational_unit_id' => $program->organizational_unit_id,
            'code' => $code ?? 'ACT-'.$this->seq, 'title' => 'Kegiatan '.$this->seq, 'location' => 'Aula',
            'planned_start_at' => $start, 'planned_end_at' => $start->copy()->addHours(3),
            'status' => $status, 'created_by_admin_id' => $creator->id,
        ]);
    }

    private function employee(string $number, OrganizationalUnit $unit, User $account): Employee
    {
        $employee = Employee::create([
            'user_id' => $account->id, 'employee_number' => $number, 'name' => 'Pegawai '.$number,
            'employee_type' => 'staff', 'status' => 'active', 'hire_date' => today()->subYear()->toDateString(),
        ]);
        EmployeeAssignment::create([
            'employee_id' => $employee->id, 'organizational_unit_id' => $unit->id,
            'is_primary' => true, 'start_date' => today()->subYear()->toDateString(),
        ]);

        return $employee;
    }

    private function policy(): void
    {
        AttendancePolicy::create([
            'name' => 'Umum', 'work_days' => [1, 2, 3, 4, 5, 6, 7], 'work_start' => '07:00', 'work_end' => '15:00',
            'late_tolerance_minutes' => 15, 'is_active' => true,
        ]);
    }
}
