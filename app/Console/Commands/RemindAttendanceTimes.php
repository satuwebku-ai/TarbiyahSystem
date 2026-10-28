<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class RemindAttendanceTimes extends Command
{
    protected $signature = 'reminders:attendance-time';

    protected $description = 'Ingatkan pegawai (lewat push mobile) saat jam absen masuk dan pulang';

    public function handle(ReminderService $reminders): int
    {
        $n = $reminders->attendanceTimes();
        $this->info("Pengingat jam absen terkirim: {$n}.");

        return self::SUCCESS;
    }
}
