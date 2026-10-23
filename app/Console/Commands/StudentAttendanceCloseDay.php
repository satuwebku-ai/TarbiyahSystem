<?php

namespace App\Console\Commands;

use App\Helpers\AttendanceClock;
use App\Models\School;
use App\Models\StudentAttendanceSetting;
use App\Services\FingerprintAttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class StudentAttendanceCloseDay extends Command
{
    protected $signature = 'student-attendance:close-day
        {--date= : Tanggal (Y-m-d), bawaan hari ini}
        {--school= : Batasi ke satu ID sekolah}
        {--force : Abaikan waktu alpa otomatis dan pengaturan nonaktif}';

    protected $description = 'Tandai alpa otomatis siswa yang belum punya catatan absensi (sesuai pengaturan sekolah)';

    public function handle(FingerprintAttendanceService $service): int
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('!Y-m-d', (string) $this->option('date'), AttendanceClock::tz())
            : AttendanceClock::today();
        $now = AttendanceClock::now();
        $total = 0;

        School::query()
            ->where('status', 'active')
            ->when($this->option('school'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->each(function (School $school) use ($service, $date, $now, &$total) {
                $settings = StudentAttendanceSetting::forSchool($school->id);
                if (! $this->option('force')) {
                    if (! $settings->auto_absent_enabled) {
                        return;
                    }
                    // Untuk hari ini, tunggu sampai waktu alpa otomatis lewat.
                    if ($date->isSameDay($now) && $now->format('H:i') < $settings->auto_absent_time) {
                        return;
                    }
                }
                $total += $service->closeDay($school, $date);
            });

        $this->info("Alpa otomatis dicatat: {$total}.");

        return self::SUCCESS;
    }
}
