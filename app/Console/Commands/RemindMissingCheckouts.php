<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class RemindMissingCheckouts extends Command
{
    protected $signature = 'reminders:missing-checkout';

    protected $description = 'Ingatkan pegawai yang kemarin belum absen pulang';

    public function handle(ReminderService $reminders): int
    {
        $n = $reminders->missingCheckouts();
        $this->info("Pengingat absen pulang terkirim: {$n}.");

        return self::SUCCESS;
    }
}
