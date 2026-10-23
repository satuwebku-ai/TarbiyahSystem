<?php

namespace Database\Seeders\Demo;

use App\Enums\CorrectionStatus;
use App\Helpers\AttendanceClock;
use App\Models\Admin;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\FingerprintDevice;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendanceSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat absensi 20 hari kerja terakhir (sebelum hari ini), supaya dashboard, rekap, dan laporan tidak kosong:
 *   - absensi pegawai (hadir, terlambat, sakit, izin, cuti, alpa) + 3 pengajuan koreksi
 *   - absensi siswa per kelas; tiga sekolah pertama memakai mesin fingerprint (mesin, PIN, log ketukan)
 *
 * Tanggal mengikuti hari menjalankan seeder; hari yang sudah terisi tidak diubah, jadi aman diulang.
 */
class DemoAbsensiSeeder extends Seeder
{
    use DemoDataset;

    private const CHUNK = 300;
    private const WORK_START_MIN = 7 * 60;          // 07:00
    private const WORK_TOLERANCE = 15;              // menit, sama dengan "Jam Kerja Yayasan"
    private const FINGERPRINT_SCHOOLS = 3;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->ensure(Admin::where('username', 'superadmin')->exists(), DemoAkunSeeder::class);
        $this->ensure(Student::exists(), DemoAkademikSeeder::class);

        $days = $this->recentWorkdays(20);

        DB::transaction(function () use ($days) {
            foreach ($days as $date) {
                if (str_ends_with($date, '-14')) {
                    AttendanceHoliday::firstOrCreate(['holiday_date' => $date, 'name' => 'Libur Contoh (Demo)', 'organizational_unit_id' => null]);
                }
            }

            $this->employeeAttendance($days);
            $this->studentAttendance($days);
        });

        $this->command?->info(sprintf(
            'Absensi demo: %d absensi pegawai, %d absensi siswa, %d log fingerprint (%s s.d. %s).',
            DB::table('attendance_records')->count(), DB::table('student_attendance_records')->count(),
            DB::table('fingerprint_logs')->count(), $days[0], end($days),
        ));
    }

    // ---- pegawai ---------------------------------------------------------

    private function employeeAttendance(array $days): void
    {
        $units = DB::table('employee_assignments')->where('is_primary', true)->pluck('organizational_unit_id', 'employee_id');
        $employees = Employee::where('status', 'active')->orderBy('id')->get(['id', 'employee_number', 'user_id']);
        $byNumber = $employees->keyBy('employee_number');

        // Tiga guru (satu per sekolah pertama) tercatat alpa pada satu hari lalu mengajukan koreksi.
        $correctionDate = $days[count($days) - 3];
        $forced = [];
        foreach (array_slice($this->schoolSpecs(), 0, 3) as $spec) {
            if ($teacher = $byNumber[DemoKepegawaianSeeder::number($spec['code'], 3)] ?? null) {
                $forced[$teacher->id] = $spec;
            }
        }

        $now = now()->format('Y-m-d H:i:s');
        $rows = [];
        foreach ($days as $date) {
            if ($this->isDemoHoliday($date)) {
                continue;
            }
            foreach ($employees as $e) {
                $rows[] = $this->employeeRow($e->id, $units[$e->id] ?? null, $date, isset($forced[$e->id]) && $date === $correctionDate, $now);
                if (count($rows) >= self::CHUNK) {
                    DB::table('attendance_records')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        }
        if ($rows) {
            DB::table('attendance_records')->insertOrIgnore($rows);
        }

        $this->corrections($forced, $employees, $correctionDate);
    }

    private function employeeRow(int $employeeId, ?int $unitId, string $date, bool $forceAbsent, string $now): array
    {
        $r = $forceAbsent ? 0 : $this->roll("att|{$employeeId}|{$date}");
        $in = $out = null;
        $late = 0;
        $note = null;

        if ($r < 3) {
            $status = 'absent';
        } elseif ($r < 6) {
            [$status, $note] = ['sick', 'Sakit (surat dokter menyusul)'];
        } elseif ($r < 8) {
            [$status, $note] = ['permit', 'Izin keperluan keluarga'];
        } elseif ($r < 10) {
            [$status, $note] = ['leave', 'Cuti tahunan'];
        } else {
            $isLate = $r < 22;
            $status = $isLate ? 'late' : 'present';
            $in = $isLate
                ? self::WORK_START_MIN + self::WORK_TOLERANCE + 1 + $this->roll("in|{$employeeId}|{$date}") % 40   // 07:16-07:55
                : 6 * 60 + 35 + $this->roll("in|{$employeeId}|{$date}") % 40;                                      // 06:35-07:14
            $out = 15 * 60 + $this->roll("out|{$employeeId}|{$date}") % 45;                                         // 15:00-15:44
            $late = $isLate ? $in - self::WORK_START_MIN : 0;
        }

        $attended = $in !== null;

        return [
            'employee_id' => $employeeId,
            'organizational_unit_id' => $unitId,
            'attendance_date' => $date,
            'check_in_at' => $attended ? $this->utc($date, $in) : null,
            'check_out_at' => $attended ? $this->utc($date, $out) : null,
            'status' => $status,
            'late_minutes' => $late,
            'work_minutes' => $attended ? $out - $in : null,
            'scheduled_start' => '07:00:00',
            'scheduled_end' => '15:00:00',
            'source' => $attended ? 'web' : 'manual',
            'check_in_ip' => $attended ? '10.0.0.'.(10 + $employeeId % 200) : null,
            'check_out_ip' => $attended ? '10.0.0.'.(10 + $employeeId % 200) : null,
            'notes' => $note,
            'created_by_admin_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function corrections(array $forced, $employees, string $date): void
    {
        $outcomes = [
            ['pending', 'Lupa absen masuk, hadir sejak pukul 06.50.', '06:50', '15:05', null],
            ['pending', 'Aplikasi absen tidak bisa dibuka pagi itu.', '07:00', '15:00', null],
            ['rejected', 'Mohon dikoreksi, saya bertugas luar sekolah.', '07:00', '15:00', 'Surat tugas tidak dilampirkan.'],
        ];

        $i = 0;
        foreach ($forced as $employeeId => $spec) {
            [$status, $reason, $in, $out, $note] = $outcomes[$i++];
            $record = AttendanceRecord::where('employee_id', $employeeId)->whereDate('attendance_date', $date)->first();
            $reviewer = $status === 'pending' ? null : Admin::where('username', $this->usernameFor('kepsek', $spec['code']))->value('id');

            AttendanceCorrection::firstOrCreate(
                ['employee_id' => $employeeId, 'attendance_date' => $date],
                [
                    'attendance_record_id' => $record?->id, 'requested_check_in' => $in, 'requested_check_out' => $out,
                    'reason' => $reason, 'status' => $status === 'pending' ? CorrectionStatus::Pending->value : CorrectionStatus::Rejected->value,
                    'requested_by_user_id' => $employees->firstWhere('id', $employeeId)?->user_id,
                    'reviewed_by_admin_id' => $reviewer, 'reviewed_at' => $reviewer ? now() : null, 'review_note' => $note,
                ],
            );
        }
    }

    // ---- siswa -----------------------------------------------------------

    private function studentAttendance(array $days): void
    {
        $specs = $this->schoolSpecs();
        $schoolIds = School::with('unit:id,code')->get()->mapWithKeys(fn ($s) => [$s->unit->code => $s->id]);
        $fingerprintIds = [];
        foreach (array_slice($specs, 0, self::FINGERPRINT_SCHOOLS) as $spec) {
            $fingerprintIds[] = $schoolIds[$spec['code']];
        }

        $devices = $this->fingerprintSetup($specs, $schoolIds, $fingerprintIds);
        $pins = Student::whereIn('school_id', $fingerprintIds)->pluck('fingerprint_pin', 'id');
        $adminBySchool = [];
        foreach ($specs as $spec) {
            $adminBySchool[$schoolIds[$spec['code']]] = Admin::where('username', $this->usernameFor('kepsek', $spec['code']))->value('id');
        }

        $classrooms = Classroom::where('status', 'active')->get(['id', 'school_id']);
        $roster = DB::table('student_class_placements as p')
            ->join('student_enrollments as e', 'e.id', '=', 'p.student_enrollment_id')
            ->where('p.status', 'active')->get(['p.classroom_id', 'e.student_id'])
            ->groupBy('classroom_id');

        $now = now()->format('Y-m-d H:i:s');
        $schoolDays = array_values(array_filter($days, fn ($d) => ! $this->isDemoHoliday($d) && $d >= self::YEAR_START && $d <= self::YEAR_END));

        // Satu sesi "Harian" per kelas per hari.
        $rows = [];
        foreach ($schoolDays as $date) {
            foreach ($classrooms as $c) {
                $rows[] = [
                    'classroom_id' => $c->id, 'attendance_date' => $date, 'name' => 'Harian',
                    'recorded_by_admin_id' => in_array($c->school_id, $fingerprintIds, true) ? null : $adminBySchool[$c->school_id],
                    'created_at' => $now, 'updated_at' => $now,
                ];
                if (count($rows) >= self::CHUNK) {
                    DB::table('student_attendance_sessions')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        }
        if ($rows) {
            DB::table('student_attendance_sessions')->insertOrIgnore($rows);
        }

        $schoolOf = $classrooms->pluck('school_id', 'id');
        $sessions = DB::table('student_attendance_sessions')->where('name', 'Harian')->whereIn('attendance_date', $schoolDays)
            ->get(['id', 'classroom_id', 'attendance_date']);

        $records = $logs = [];
        $flushRecords = function () use (&$records) {
            if ($records) {
                DB::table('student_attendance_records')->insertOrIgnore($records);
                $records = [];
            }
        };
        $flushLogs = function () use (&$logs) {
            if ($logs) {
                DB::table('fingerprint_logs')->insertOrIgnore($logs);
                $logs = [];
            }
        };

        foreach ($sessions as $session) {
            $date = substr((string) $session->attendance_date, 0, 10);
            $schoolId = $schoolOf[$session->classroom_id];
            $viaFingerprint = in_array($schoolId, $fingerprintIds, true);

            foreach ($roster[$session->classroom_id] ?? [] as $row) {
                $studentId = $row->student_id;
                $r = $this->roll("sat|{$studentId}|{$date}");
                $note = null;
                $minute = null;

                if ($r < 2) {
                    $status = 'absent';
                } elseif ($r < 4) {
                    [$status, $note] = ['sick', 'Sakit'];
                } elseif ($r < 5) {
                    [$status, $note] = ['permit', 'Izin orang tua'];
                } elseif ($r < 10) {
                    $status = 'late';
                    $minute = 7 * 60 + 11 + $this->roll("sin|{$studentId}|{$date}") % 35;    // 07:11-07:45
                } else {
                    $status = 'present';
                    $minute = 6 * 60 + 30 + $this->roll("sin|{$studentId}|{$date}") % 41;    // 06:30-07:10
                }

                $fingerprinted = $viaFingerprint && $minute !== null;
                $punch = $minute !== null ? $this->utc($date, $minute) : null;

                $records[] = [
                    'student_attendance_session_id' => $session->id, 'student_id' => $studentId, 'status' => $status,
                    'notes' => $note, 'source' => $fingerprinted ? 'fingerprint' : 'manual',
                    'checked_in_at' => $fingerprinted ? $punch : null,
                    'recorded_by_admin_id' => $viaFingerprint ? null : $adminBySchool[$schoolId],
                    'created_at' => $now, 'updated_at' => $now,
                ];

                if ($fingerprinted && ($pin = $pins[$studentId] ?? null)) {
                    $logs[] = [
                        'fingerprint_device_id' => $devices[$schoolId], 'school_id' => $schoolId, 'pin' => $pin,
                        'punched_at' => $punch, 'status_code' => 0, 'verify_mode' => 1, 'student_id' => $studentId,
                        'result' => $status, 'note' => null, 'processed_at' => $punch, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }

                if (count($records) >= self::CHUNK) {
                    $flushRecords();
                }
                if (count($logs) >= self::CHUNK) {
                    $flushLogs();
                }
            }
        }
        $flushRecords();
        $flushLogs();

        foreach ($devices as $schoolId => $deviceId) {
            $stats = DB::table('fingerprint_logs')->where('fingerprint_device_id', $deviceId)
                ->selectRaw('count(*) as total, max(punched_at) as last_at')->first();
            FingerprintDevice::whereKey($deviceId)->update([
                'total_logs' => $stats->total, 'last_log_at' => $stats->last_at, 'last_seen_at' => now(), 'last_ip' => '127.0.0.1',
            ]);
        }
    }

    /**
     * Pengaturan absensi siswa (Senin-Jumat), mesin, dan PIN fingerprint untuk tiga sekolah pertama.
     *
     * @return array<int, int> school_id => fingerprint_device_id
     */
    private function fingerprintSetup(array $specs, $schoolIds, array $fingerprintIds): array
    {
        $settings = [
            'fingerprint_enabled' => true, 'session_name' => 'Harian', 'school_days' => [1, 2, 3, 4, 5], 'start_time' => '07:00',
            'late_tolerance_minutes' => 10, 'checkin_open_time' => '05:00', 'checkin_close_time' => '12:00',
            'skip_holidays' => true, 'auto_absent_enabled' => false, 'auto_absent_time' => '10:00',
        ];
        StudentAttendanceSetting::firstOrCreate(['school_id' => null], $settings);

        $devices = [];
        foreach ($fingerprintIds as $i => $schoolId) {
            StudentAttendanceSetting::firstOrCreate(['school_id' => $schoolId], $settings);

            $device = FingerprintDevice::updateOrCreate(
                ['serial_number' => sprintf('DEMO-FP-%03d', $i + 1)],
                ['school_id' => $schoolId, 'name' => 'Mesin Gerbang Utama', 'location' => 'Pintu masuk utama sekolah',
                    'is_active' => true, 'allowed_ips' => '127.0.0.1'],
            );
            $devices[$schoolId] = $device->id;

            $seq = 100;
            foreach (Student::where('school_id', $schoolId)->orderBy('id')->get(['id', 'fingerprint_pin']) as $student) {
                $seq++;
                if ($student->fingerprint_pin === null) {
                    Student::whereKey($student->id)->update(['fingerprint_pin' => (string) $seq]);
                }
            }
        }

        return $devices;
    }

    /** Menit sejak tengah malam (waktu lokal absensi) -> timestamp UTC siap simpan. */
    private function utc(string $date, int $minuteOfDay): string
    {
        return AttendanceClock::fromLocal($date, sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60))->format('Y-m-d H:i:s');
    }
}
