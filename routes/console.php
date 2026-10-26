<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Fase 10 — pengingat & pemeliharaan terjadwal. Waktu mengikuti zona waktu absensi.
| Jalankan penjadwal: produksi memakai cron "* * * * * php artisan schedule:run";
| lokal (Windows) cukup "php artisan schedule:work". Antrean: "php artisan queue:work".
*/
$tz = config('attendance.timezone');

Schedule::command('reminders:upcoming-activities')->dailyAt('07:00')->timezone($tz)->withoutOverlapping();
Schedule::command('reminders:pending-approvals')->dailyAt('07:30')->timezone($tz)->withoutOverlapping();
Schedule::command('reminders:missing-checkout')->dailyAt('08:00')->timezone($tz)->withoutOverlapping();
Schedule::command('student-attendance:close-day')->everyFiveMinutes()->timezone($tz)->withoutOverlapping();
// Cadangan database harian (14 berkas terbaru disimpan di storage/app/private/backups).
// Berkas memuat data pribadi & hash kata sandi: salin juga ke luar server (akses terbatas).
Schedule::command('db:backup')->dailyAt('01:00')->timezone($tz)->withoutOverlapping();
Schedule::command('fingerprint:prune-pending')->dailyAt('03:00')->timezone($tz)->withoutOverlapping();
// Foto selfie absen dihapus setelah masa simpan (ATTENDANCE_PHOTO_RETENTION_DAYS, bawaan 90 hari).
Schedule::command('attendance:prune-photos')->dailyAt('03:30')->timezone($tz)->withoutOverlapping();
// Ulangi pencocokan wajah yang gagal (penyedia sempat mati) atau belum punya foto acuan; hanya berjalan bila pencocokan aktif.
Schedule::command('face:rematch', ['--days' => 3, '--status' => ['error', 'no_reference']])->dailyAt('04:00')->timezone($tz)->withoutOverlapping()
    ->when(fn () => \App\Services\Face\FaceMatcherFactory::enabled());
Schedule::command('notifications:prune')->weeklyOn(0, '02:00')->timezone($tz)->withoutOverlapping();
Schedule::command('queue:prune-failed', ['--hours' => 720])->weeklyOn(0, '02:30')->timezone($tz);
