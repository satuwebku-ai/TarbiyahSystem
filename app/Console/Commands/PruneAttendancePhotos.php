<?php

namespace App\Console\Commands;

use App\Helpers\AttendanceClock;
use App\Models\AttendanceRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus foto selfie absen yang sudah melewati masa simpan. Hanya berkas foto yang dihapus dan kolom
 * path-nya dikosongkan; data absen (jam, status, koordinat, jarak, IP) tetap utuh untuk laporan.
 */
class PruneAttendancePhotos extends Command
{
    protected $signature = 'attendance:prune-photos
        {--days= : Masa simpan foto dalam hari (bawaan: ATTENDANCE_PHOTO_RETENTION_DAYS)}
        {--dry-run : Hanya hitung, tanpa menghapus}';

    protected $description = 'Hapus foto selfie absen yang lebih lama dari masa simpan';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('attendance.photo_retention_days');

        if ($days <= 0) {
            $this->warn('Masa simpan foto 0 atau kosong: pembersihan dilewati (foto disimpan tanpa batas).');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $cutoff = AttendanceClock::now()->subDays($days)->toDateString();
        $disk = Storage::disk('local');
        $files = 0;
        $records = 0;

        AttendanceRecord::query()
            ->where('attendance_date', '<', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('check_in_photo')->orWhereNotNull('check_out_photo'))
            ->chunkById(200, function ($chunk) use ($disk, $dry, &$files, &$records) {
                foreach ($chunk as $record) {
                    $changes = [];
                    foreach (['check_in_photo', 'check_out_photo'] as $col) {
                        $path = $record->{$col};
                        if (! $path) {
                            continue;
                        }
                        if (! $dry) {
                            $disk->delete($path);
                            $changes[$col] = null;
                        }
                        $files++;
                    }
                    if ($changes) {
                        $record->forceFill($changes)->saveQuietly();
                    }
                    $records++;
                }
            });

        // Rapikan folder tanggal/pegawai yang sudah kosong (folder tanggal = attendance-proofs/YYYY-MM-DD).
        if (! $dry) {
            foreach ($disk->directories('attendance-proofs') as $dateDir) {
                $date = basename($dateDir);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date < $cutoff) {
                    foreach ($disk->directories($dateDir) as $empDir) {
                        if (! $disk->allFiles($empDir)) {
                            $disk->deleteDirectory($empDir);
                        }
                    }
                    if (! $disk->allFiles($dateDir) && ! $disk->directories($dateDir)) {
                        $disk->deleteDirectory($dateDir);
                    }
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."Foto dihapus: {$files} berkas dari {$records} catatan absen (lebih lama dari {$days} hari, sebelum {$cutoff}).");

        return self::SUCCESS;
    }
}
