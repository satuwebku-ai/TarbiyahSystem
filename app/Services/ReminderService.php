<?php

namespace App\Services;

use App\Enums\CorrectionStatus;
use App\Enums\EmployeeStatus;
use App\Helpers\AttendanceClock;
use App\Models\ActivityReport;
use App\Models\Admin;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\MobileToken;
use App\Models\User;
use App\Models\WorkActivity;
use App\Models\WorkProgram;
use App\Notifications\WorkflowNotification;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Logika pengingat terjadwal. Perintah artisan hanya memanggil kelas ini agar mudah diuji.
 * Setiap pengingat diberi kunci anti-ganda (Cache::add), jadi menjalankan perintah dua kali
 * pada hari yang sama tidak mengirim notifikasi dua kali. Mengembalikan jumlah notifikasi yang dikirim.
 */
class ReminderService
{
    public function __construct(private NotificationRecipients $recipients) {}

    /** Ringkasan harian untuk penyetuju: pengajuan yang menunggu lebih lama dari ambang hari. */
    public function pendingApprovals(?int $days = null): int
    {
        $days ??= (int) config('notifications.pending_reminder_after_days');
        $cutoff = now()->subDays($days);
        $digest = [];
        $count = function (Admin $admin, string $key) use (&$digest): void {
            $digest[$admin->id] ??= ['admin' => $admin, 'programs' => 0, 'reports' => 0, 'corrections' => 0];
            $digest[$admin->id][$key]++;
        };

        WorkProgram::query()
            ->where('status', 'submitted')->where('submitted_at', '<=', $cutoff)
            ->each(fn (WorkProgram $p) => $this->recipients
                ->approvers('programs.approve', (int) $p->organizational_unit_id)
                ->each(fn (Admin $a) => $count($a, 'programs')));

        ActivityReport::query()->with('activity:id,organizational_unit_id')
            ->where('status', 'submitted')->where('submitted_at', '<=', $cutoff)
            ->each(fn (ActivityReport $r) => $this->recipients
                ->approvers('programs.approve', (int) $r->activity->organizational_unit_id)
                ->each(fn (Admin $a) => $count($a, 'reports')));

        AttendanceCorrection::query()->with('employee')
            ->where('status', CorrectionStatus::Pending)->where('created_at', '<=', $cutoff)
            ->each(fn (AttendanceCorrection $c) => $this->recipients
                ->correctionReviewers($c->employee)
                ->each(fn (Admin $a) => $count($a, 'corrections')));

        $today = AttendanceClock::today()->toDateString();
        $sent = 0;
        foreach ($digest as $row) {
            if (! Cache::add("reminder:pending:{$row['admin']->id}:{$today}", 1, now()->addDay())) {
                continue;
            }
            $row['admin']->notify(WorkflowNotification::pendingApprovals($row['programs'], $row['reports'], $row['corrections'], $days));
            $sent++;
        }

        return $sent;
    }

    /** Kegiatan terencana pada program yang disetujui, dimulai $daysAhead hari lagi (hari kalender zona absensi). */
    public function upcomingActivities(?int $daysAhead = null): int
    {
        $daysAhead ??= (int) config('notifications.activity_reminder_days_ahead');
        $day = AttendanceClock::today()->addDays($daysAhead)->toDateString();
        $from = AttendanceClock::fromLocal($day, '00:00:00');
        $to = AttendanceClock::fromLocal($day, '23:59:59');
        $sent = 0;

        WorkActivity::query()
            ->with(['program:id,created_by_admin_id', 'participants.employee:id,user_id'])
            ->where('status', 'planned')
            ->whereBetween('planned_start_at', [$from, $to])
            ->whereHas('program', fn ($q) => $q->where('status', 'approved'))
            ->each(function (WorkActivity $activity) use ($day, $daysAhead, &$sent) {
                // Pengurus pembuat program/kegiatan (admins) dan pegawai peserta (users).
                $recipients = [
                    'admin' => $this->recipients->admins([$activity->created_by_admin_id, $activity->program->created_by_admin_id]),
                    'user' => $this->recipients->users($activity->participants->pluck('employee.user_id')->all()),
                ];

                foreach ($recipients as $type => $accounts) {
                    foreach ($accounts as $account) {
                        if (! Cache::add("reminder:activity:{$activity->id}:{$type}:{$account->id}:{$day}", 1, now()->addDays(2))) {
                            continue;
                        }
                        $canOpen = $account instanceof Admin && $this->recipients->canViewPrograms($account);
                        $account->notify(WorkflowNotification::activityReminder($activity, $canOpen, $daysAhead));
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /** Pegawai yang absen masuk kemarin tetapi belum absen pulang dan belum mengajukan koreksi. */
    public function missingCheckouts(): int
    {
        $date = AttendanceClock::today()->subDay()->toDateString();
        $sent = 0;

        AttendanceRecord::query()->with('employee.user')
            ->where('attendance_date', $date)->whereNotNull('check_in_at')->whereNull('check_out_at')
            ->each(function (AttendanceRecord $record) use ($date, &$sent) {
                $employee = $record->employee;
                $user = $employee->user;

                if (! $user?->is_active || $employee->status !== EmployeeStatus::Active) {
                    return;
                }
                if (AttendanceCorrection::query()->where('employee_id', $employee->id)
                    ->where('attendance_date', $date)->where('status', CorrectionStatus::Pending)->exists()) {
                    return;
                }
                if (! Cache::add("reminder:checkout:{$record->id}", 1, now()->addDays(2))) {
                    return;
                }

                $user->notify(WorkflowNotification::missingCheckout($record));
                $sent++;
            });

        return $sent;
    }

    /**
     * Pengingat jam absen lewat push: "waktunya absen masuk" sekitar jam masuk dan "waktunya absen pulang"
     * saat jam kerja berakhir. Hanya untuk pegawai yang punya perangkat mobile terdaftar (agar lonceng web
     * pegawai lain tidak penuh). Jam, hari kerja, dan hari libur mengikuti kebijakan absensi unit pegawai.
     */
    public function attendanceTimes(): int
    {
        $lead = max(0, (int) config('notifications.checkin_reminder_lead_minutes'));
        $window = max(1, (int) config('notifications.reminder_window_minutes'));

        $userIds = MobileToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereNotNull('push_token')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->distinct()->pluck('tokenable_id');
        if ($userIds->isEmpty()) {
            return 0;
        }

        $resolver = app(AttendancePolicyResolver::class);
        $sent = 0;

        Employee::query()->with('user')
            ->whereIn('user_id', $userIds)->where('status', EmployeeStatus::Active->value)
            ->each(function (Employee $employee) use ($resolver, $lead, $window, &$sent) {
                $user = $employee->user;
                if (! $user?->is_active) {
                    return;
                }

                $unitId = $resolver->unitFor($employee, AttendanceClock::now());
                $policy = $unitId ? $resolver->policyForUnit($unitId) : null;
                if (! $policy || ! $policy->work_start || ! $policy->work_end) {
                    return;
                }

                $tz = $resolver->timezoneFor($policy);
                $now = AttendanceClock::now($tz);
                $date = $now->toDateString();
                if (! $policy->isWorkDay($now->isoWeekday()) || $resolver->holidayFor($unitId, $date)) {
                    return;
                }

                $start = Carbon::parse("{$date} {$policy->work_start}", $tz);
                $end = Carbon::parse("{$date} {$policy->work_end}", $tz);
                $record = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)->where('attendance_date', $date)->first();

                if (! $record) {
                    // Belum ada catatan apa pun hari ini (izin/sakit/cuti juga berupa catatan, jadi tidak diingatkan).
                    if ($now->between($start->copy()->subMinutes($lead), $start->copy()->addMinutes($window))
                        && Cache::add("reminder:checkin:{$employee->id}:{$date}", 1, now()->addDay())) {
                        $user->notify(WorkflowNotification::checkInReminder($start->format('H:i'), $now->gte($start)));
                        $sent++;
                    }

                    return;
                }

                if ($record->check_in_at && ! $record->check_out_at
                    && $now->between($end, $end->copy()->addMinutes($window))
                    && Cache::add("reminder:checkout-time:{$employee->id}:{$date}", 1, now()->addDay())) {
                    $user->notify(WorkflowNotification::checkOutReminder($end->format('H:i')));
                    $sent++;
                }
            });

        return $sent;
    }

    /** Hapus notifikasi yang sudah dibaca dan lebih tua dari masa simpan. Yang belum dibaca tidak disentuh. */
    public function pruneRead(?int $days = null): int
    {
        $days ??= (int) config('notifications.retention_days');

        return DatabaseNotification::query()
            ->whereNotNull('read_at')->where('read_at', '<', now()->subDays($days))
            ->delete();
    }
}
