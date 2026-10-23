<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class DatabaseRestore extends Command
{
    protected $signature = 'db:restore {file : Berkas cadangan (.jsonl.gz)} {--force : Lewati konfirmasi}';

    protected $description = 'Pulihkan database dari berkas cadangan (MENGGANTI seluruh data saat ini)';

    public function handle(DatabaseBackupService $service): int
    {
        $file = (string) $this->argument('file');

        try {
            $meta = $service->readMeta($file);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->warn('Cadangan dibuat pada '.($meta['created_at'] ?? '?').'. Seluruh data saat ini akan DIGANTI.');
        if (! $this->option('force') && ! $this->confirm('Lanjutkan pemulihan?', false)) {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        try {
            $counts = $service->restore($file);
        } catch (Throwable $e) {
            $this->error('Pemulihan gagal, data lama tidak diubah: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Pemulihan selesai ('.array_sum($counts).' baris, '.count($counts).' tabel).');

        return self::SUCCESS;
    }
}
