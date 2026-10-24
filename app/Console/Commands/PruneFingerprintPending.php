<?php

namespace App\Console\Commands;

use App\Models\FingerprintDevice;
use Illuminate\Console\Command;

/**
 * Endpoint /iclock mendaftarkan nomor seri asing sebagai mesin "pending" (maks. config
 * student_attendance.max_pending_devices). Perintah ini membersihkan mesin pending yang tidak pernah
 * diklaim sekolah, tidak aktif, dan belum pernah mengirim log, supaya slot tidak penuh oleh pendaftaran asing.
 */
class PruneFingerprintPending extends Command
{
    protected $signature = 'fingerprint:prune-pending {--days=7 : Hapus pending yang lebih tua dari sekian hari}';

    protected $description = 'Hapus mesin fingerprint pending yang tidak diklaim dan tanpa log';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $n = FingerprintDevice::query()
            ->whereNull('school_id')
            ->where('is_active', false)
            ->where('created_at', '<', now()->subDays($days))
            ->whereDoesntHave('logs')
            ->delete();

        $this->info("Mesin pending dihapus: {$n}.");

        return self::SUCCESS;
    }
}
