<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class RemindPendingApprovals extends Command
{
    protected $signature = 'reminders:pending-approvals {--days= : Ambang hari (bawaan config/notifications.php)}';

    protected $description = 'Ingatkan penyetuju tentang pengajuan yang belum diproses';

    public function handle(ReminderService $reminders): int
    {
        $n = $reminders->pendingApprovals($this->option('days') !== null ? (int) $this->option('days') : null);
        $this->info("Pengingat persetujuan terkirim: {$n}.");

        return self::SUCCESS;
    }
}
