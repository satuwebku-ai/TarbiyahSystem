<?php

namespace App\Services;

use App\Enums\StudentAttendanceStatus;
use App\Helpers\AttendanceClock;
use App\Models\Classroom;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendanceCorrection;
use App\Models\StudentAttendanceRecord;
use App\Models\StudentAttendanceSession;
use App\Models\StudentAttendanceSetting;
use App\Models\StudentClassPlacement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menerjemahkan ketukan jari (log mesin) menjadi absensi siswa.
 *
 * Aturan:
 *  - Ketukan pertama pada hari sekolah, di dalam jendela jam absen, menjadi catatan hadir/terlambat.
 *  - Ketukan berikutnya pada hari yang sama diabaikan (tetap tersimpan sebagai log).
 *  - Catatan manual dari admin tidak pernah ditimpa mesin.
 *  - Catatan alpa otomatis (source = auto) boleh ditimpa bila siswa ternyata mengetuk jari.
 */
class FingerprintAttendanceService
{
    public function __construct(private AttendancePolicyResolver $holidays) {}

    /**
     * Simpan log mentah dari mesin lalu proses. Duplikat (mesin mengirim ulang) diabaikan.
     *
     * @param  array<int, array{pin: string, time: string, status?: ?int, verify?: ?int}>  $punches  waktu = waktu dinding mesin
     * @return int jumlah log baru
     */
    public function ingest(FingerprintDevice $device, array $punches): int
    {
        $created = 0;
        $latest = null;
        $now = AttendanceClock::now();
        $newest = $now->copy()->addMinutes(max(0, (int) config('student_attendance.max_future_minutes', 10)));
        $oldest = $now->copy()->subDays(max(1, (int) config('student_attendance.max_punch_age_days', 60)));

        foreach ($punches as $punch) {
            try {
                $local = Carbon::createFromFormat('Y-m-d H:i:s', $punch['time'], AttendanceClock::tz());
            } catch (\Throwable) {
                continue;
            }
            if ($local === false || $local->year < 2000) {
                continue;
            }

            // Jam mesin salah atau data palsu: simpan sebagai log, tetapi jangan jadikan absensi.
            $badTime = $local->gt($newest) || $local->lt($oldest);

            $log = FingerprintLog::query()->firstOrCreate(
                [
                    'fingerprint_device_id' => $device->id,
                    'pin' => $punch['pin'],
                    'punched_at' => AttendanceClock::toStorage($local),
                ],
                [
                    'school_id' => $device->school_id,
                    'status_code' => $punch['status'] ?? null,
                    'verify_mode' => $punch['verify'] ?? null,
                    'result' => $badTime ? 'bad_time' : 'pending',
                    'note' => $badTime ? 'Waktu ketukan di luar batas wajar (periksa jam mesin).' : null,
                    'processed_at' => $badTime ? now() : null,
                ]
            );

            if ($log->wasRecentlyCreated) {
                $created++;
                if ($badTime) {
                    continue;
                }
                $this->process($log);
                $latest = $latest === null || $log->punched_at->gt($latest) ? $log->punched_at : $latest;
            }
        }

        if ($created > 0) {
            $device->forceFill([
                'last_log_at' => $latest ?? $device->last_log_at,
                'total_logs' => $device->total_logs + $created,
            ])->save();
        }

        return $created;
    }

    /** Proses satu log menjadi absensi. Aman dipanggil ulang. Mengembalikan kode hasil. */
    public function process(FingerprintLog $log): string
    {
        $log->loadMissing('device');
        $schoolId = $log->school_id ?? $log->device?->school_id;
        if (! $schoolId) {
            return $this->finish($log, 'pending', null, 'Mesin belum dikaitkan dengan sekolah.');
        }
        if ($log->school_id !== $schoolId) {
            $log->school_id = $schoolId;
        }

        $settings = StudentAttendanceSetting::forSchool($schoolId);
        if (! $settings->fingerprint_enabled) {
            return $this->finish($log, 'disabled');
        }

        $student = Student::query()
            ->where('school_id', $schoolId)
            ->where('fingerprint_pin', $log->pin)
            ->where('status', 'active')
            ->first();
        if (! $student) {
            return $this->finish($log, 'unmapped', null, "PIN {$log->pin} belum dipetakan ke siswa aktif.");
        }

        $local = AttendanceClock::toLocal($log->punched_at);
        $date = $local->toDateString();

        if (! $settings->isSchoolDay($local->isoWeekday())) {
            return $this->finish($log, 'not_school_day', $student);
        }
        if ($settings->skip_holidays && $this->isHoliday($schoolId, $date)) {
            return $this->finish($log, 'holiday', $student);
        }

        $time = $local->format('H:i');
        if ($time < $settings->checkin_open_time) {
            return $this->finish($log, 'too_early', $student);
        }
        if ($time > $settings->checkin_close_time) {
            return $this->finish($log, 'too_late', $student);
        }

        $classroom = $this->classroomFor($student, $date);
        if (! $classroom) {
            return $this->finish($log, 'no_class', $student, 'Tidak ada penempatan kelas aktif pada tanggal tersebut.');
        }

        $lateAfter = Carbon::createFromFormat('Y-m-d H:i', "$date {$settings->start_time}", AttendanceClock::tz())
            ->addMinutes($settings->late_tolerance_minutes);
        $status = $local->gt($lateAfter) ? StudentAttendanceStatus::Late : StudentAttendanceStatus::Present;

        $result = DB::transaction(function () use ($classroom, $date, $settings, $student, $status, $log) {
            $session = StudentAttendanceSession::query()->firstOrCreate([
                'classroom_id' => $classroom->id,
                'attendance_date' => $date,
                'name' => $settings->session_name,
            ]);

            $record = StudentAttendanceRecord::query()->firstOrNew([
                'student_attendance_session_id' => $session->id,
                'student_id' => $student->id,
            ]);

            if (! $record->exists) {
                $record->fill([
                    'status' => $status,
                    'source' => 'fingerprint',
                    'checked_in_at' => $log->punched_at,
                ])->save();

                return $status->value;
            }

            if ($record->source === 'auto') {
                StudentAttendanceCorrection::query()->create([
                    'student_attendance_record_id' => $record->id,
                    'previous_status' => $record->status->value,
                    'new_status' => $status->value,
                    'previous_notes' => $record->notes,
                    'new_notes' => $record->notes,
                    'reason' => 'Hadir tercatat oleh mesin fingerprint setelah ditandai alpa otomatis.',
                    'changed_by_admin_id' => null,
                ]);
                $record->fill([
                    'status' => $status,
                    'source' => 'fingerprint',
                    'checked_in_at' => $log->punched_at,
                ])->save();

                return $status->value;
            }

            return $record->source === 'fingerprint' ? 'duplicate' : 'kept_manual';
        });

        return $this->finish($log, $result, $student);
    }

    /**
     * Tandai alpa otomatis siswa yang belum punya catatan pada tanggal itu (hanya hari sekolah).
     * Idempoten: hanya membuat catatan yang belum ada. Mengembalikan jumlah catatan alpa baru.
     */
    public function closeDay(School $school, Carbon $date): int
    {
        $settings = StudentAttendanceSetting::forSchool($school->id);
        $day = $date->toDateString();

        if (! $settings->isSchoolDay($date->isoWeekday())) {
            return 0;
        }
        if ($settings->skip_holidays && $this->isHoliday($school->id, $day)) {
            return 0;
        }

        $access = app(StudentAttendanceAccess::class);
        $created = 0;

        Classroom::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->whereHas('academicYear', fn (Builder $q) => $q
                ->whereDate('start_date', '<=', $day)
                ->whereDate('end_date', '>=', $day))
            ->with('academicYear')
            ->get()
            ->each(function (Classroom $classroom) use ($access, $settings, $day, &$created) {
                $roster = $access->roster($classroom, $day);
                if ($roster->isEmpty()) {
                    return;
                }

                DB::transaction(function () use ($classroom, $roster, $settings, $day, &$created) {
                    $session = StudentAttendanceSession::query()->firstOrCreate([
                        'classroom_id' => $classroom->id,
                        'attendance_date' => $day,
                        'name' => $settings->session_name,
                    ]);
                    $existing = $session->records()->pluck('student_id')->all();

                    foreach ($roster->whereNotIn('id', $existing) as $student) {
                        StudentAttendanceRecord::query()->create([
                            'student_attendance_session_id' => $session->id,
                            'student_id' => $student->id,
                            'status' => StudentAttendanceStatus::Absent,
                            'source' => 'auto',
                            'notes' => 'Tidak ada ketukan fingerprint.',
                        ]);
                        $created++;
                    }
                });
            });

        return $created;
    }

    /**
     * Proses ulang log yang belum menghasilkan absensi (mis. setelah PIN dipetakan).
     * Diurutkan menurut waktu ketuk agar "ketukan pertama menang" tetap benar.
     */
    public function reprocess(Builder $logs): int
    {
        $ids = $logs->whereIn('result', FingerprintLog::RETRYABLE)
            ->orderBy('punched_at')
            ->orderBy('id')
            ->pluck('fingerprint_logs.id');

        $changed = 0;
        foreach ($ids->chunk(200) as $chunk) {
            $byId = FingerprintLog::query()->whereIn('id', $chunk)->get()->keyBy('id');
            foreach ($chunk as $id) {
                $log = $byId->get($id);
                if (! $log) {
                    continue;
                }
                $before = $log->result;
                if ($this->process($log) !== $before) {
                    $changed++;
                }
            }
        }

        return $changed;
    }

    private function classroomFor(Student $student, string $date): ?Classroom
    {
        $placement = StudentClassPlacement::query()
            ->with('classroom.academicYear')
            ->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
            ->whereHas('classroom', fn (Builder $q) => $q
                ->where('school_id', $student->school_id)
                ->where('status', 'active')
                ->whereHas('academicYear', fn (Builder $year) => $year
                    ->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date)))
            ->whereHas('enrollment', fn (Builder $q) => $q
                ->where('student_id', $student->id)
                ->whereDate('enrollment_date', '<=', $date)
                ->whereIn('status', ['active', 'completed', 'transferred']))
            ->orderByDesc('start_date')
            ->first();

        return $placement?->classroom;
    }

    private function isHoliday(int $schoolId, string $date): bool
    {
        $unitId = School::query()->whereKey($schoolId)->value('organizational_unit_id');

        return $this->holidays->holidayFor($unitId ? (int) $unitId : null, $date) !== null;
    }

    private function finish(FingerprintLog $log, string $result, ?Student $student = null, ?string $note = null): string
    {
        $log->forceFill([
            'result' => $result,
            'student_id' => $student?->id,
            'note' => $note,
            'processed_at' => now(),
        ])->save();

        return $result;
    }
}
