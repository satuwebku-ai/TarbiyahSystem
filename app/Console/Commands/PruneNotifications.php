<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days= : Masa simpan notifikasi yang sudah dibaca}';

    protected $description = 'Hapus notifikasi lama yang sudah dibaca';

    public function handle(ReminderService $reminders): int
    {
        $n = $reminders->pruneRead($this->option('days') !== null ? (int) $this->option('days') : null);
        $this->info("Notifikasi dihapus: {$n}.");

        return self::SUCCESS;
    }
}
