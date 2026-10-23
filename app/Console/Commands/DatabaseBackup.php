<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--path= : Lokasi berkas (bawaan: storage/app/private/backups)} {--keep=14 : Jumlah cadangan terbaru yang disimpan}';

    protected $description = 'Buat cadangan logis database (berkas .jsonl.gz)';

    public function handle(DatabaseBackupService $service): int
    {
        $path = $this->option('path') ?: storage_path('app/private/backups/backup-'.now()->format('Ymd-His').'.jsonl.gz');

        try {
            $counts = $service->backup($path);
        } catch (Throwable $e) {
            @unlink($path);
            $this->error('Cadangan gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cadangan dibuat: '.$path.' ('.array_sum($counts).' baris, '.count($counts).' tabel).');

        if (! $this->option('path')) {
            $files = glob(dirname($path).'/backup-*.jsonl.gz') ?: [];
            rsort($files);
            foreach (array_slice($files, max(1, (int) $this->option('keep'))) as $old) {
                @unlink($old);
            }
        }

        return self::SUCCESS;
    }
}
