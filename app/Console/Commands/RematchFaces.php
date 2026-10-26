<?php

namespace App\Console\Commands;

use App\Helpers\AttendanceClock;
use App\Jobs\MatchAttendanceFace;
use App\Models\AttendanceRecord;
use App\Services\Face\FaceMatcherFactory;
use App\Services\Face\FaceMatchService;
use Illuminate\Console\Command;

/**
 * Ulangi pencocokan wajah untuk absen yang belum punya hasil berguna (belum ada foto acuan, penyedia gagal, wajah tidak
 * terdeteksi, atau dibuat saat pencocokan masih mati), atau hitung ulang status dari skor tersimpan setelah batas diubah.
 */
class RematchFaces extends Command
{
    protected $signature = 'face:rematch
        {--days=30 : Jendela hari ke belakang}
        {--status=* : Status yang diulang: no_reference, error, no_face, unchecked (bawaan: keempatnya)}
        {--reclassify : Hitung ulang status dari skor tersimpan dengan batas saat ini, tanpa memanggil penyedia}
        {--sync : Jalankan langsung, bukan lewat antrean}
        {--dry-run : Hanya hitung, tanpa mengubah atau mengirim apa pun}';

    protected $description = 'Ulangi pencocokan wajah absen pegawai yang belum punya hasil, atau klasifikasi ulang skor';

    private const PARTS = ['check_in', 'check_out'];

    public function handle(FaceMatchService $service): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = AttendanceClock::now()->subDays($days)->toDateString();
        $dry = (bool) $this->option('dry-run');

        if ($this->option('reclassify')) {
            return $this->reclassify($service, $cutoff, $dry);
        }

        if (! FaceMatcherFactory::enabled()) {
            $this->error('Pencocokan wajah sedang dimatikan (Pengaturan Aplikasi > Pencocokan Wajah). Tidak ada yang dikerjakan.');

            return self::FAILURE;
        }

        $wanted = $this->option('status') ?: ['no_reference', 'error', 'no_face', 'unchecked'];
        $unknown = array_diff($wanted, ['no_reference', 'error', 'no_face', 'unchecked']);
        if ($unknown) {
            $this->error('Status tidak dikenal: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $count = 0;
        AttendanceRecord::query()->where('attendance_date', '>=', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('check_in_photo')->orWhereNotNull('check_out_photo'))
            ->chunkById(200, function ($chunk) use ($wanted, $dry, &$count) {
                foreach ($chunk as $record) {
                    foreach (self::PARTS as $part) {
                        if (! $record->{"{$part}_photo"}) {
                            continue;
                        }
                        $status = $record->{"{$part}_face_status"} ?? 'unchecked';
                        if (! in_array($status, $wanted, true)) {
                            continue;
                        }
                        $count++;
                        if ($dry) {
                            continue;
                        }
                        $this->option('sync')
                            ? MatchAttendanceFace::dispatchSync($record->id, $part)
                            : MatchAttendanceFace::dispatch($record->id, $part);
                    }
                }
            });

        $this->info(($dry ? '[dry-run] ' : '').($this->option('sync') ? 'Dicocokkan' : 'Diantrekan').": {$count} foto (sejak {$cutoff}).");

        return self::SUCCESS;
    }

    private function reclassify(FaceMatchService $service, string $cutoff, bool $dry): int
    {
        $changed = 0;
        AttendanceRecord::query()->where('attendance_date', '>=', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('check_in_face_score')->orWhereNotNull('check_out_face_score'))
            ->chunkById(200, function ($chunk) use ($service, $dry, &$changed) {
                foreach ($chunk as $record) {
                    $updates = [];
                    foreach (self::PARTS as $part) {
                        $score = $record->{"{$part}_face_score"};
                        if ($score === null) {
                            continue;
                        }
                        $new = $service->classify((float) $score);
                        if ($new !== $record->{"{$part}_face_status"}) {
                            $updates["{$part}_face_status"] = $new;
                            $changed++;
                        }
                    }
                    if ($updates && ! $dry) {
                        $record->forceFill($updates)->saveQuietly();
                    }
                }
            });

        $this->info(($dry ? '[dry-run] ' : '')."Status diperbarui dari skor tersimpan: {$changed} foto (sejak {$cutoff}).");

        return self::SUCCESS;
    }
}
