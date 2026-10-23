<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class RemindUpcomingActivities extends Command
{
    protected $signature = 'reminders:upcoming-activities {--days= : Berapa hari sebelum kegiatan dimulai}';

    protected $description = 'Ingatkan pelaksana dan peserta tentang kegiatan yang akan dimulai';

    public function handle(ReminderService $reminders): int
    {
        $n = $reminders->upcomingActivities($this->option('days') !== null ? (int) $this->option('days') : null);
        $this->info("Pengingat kegiatan terkirim: {$n}.");

        return self::SUCCESS;
    }
}
