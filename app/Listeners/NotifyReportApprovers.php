<?php

namespace App\Listeners;

use App\Events\ActivityReportSubmitted;
use App\Listeners\Concerns\HandlesQueueFailure;
use App\Notifications\WorkflowNotification;
use App\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Laporan kegiatan diajukan -> beri tahu pengevaluasi (izin programs.approve) pada unit kegiatan. */
class NotifyReportApprovers implements ShouldQueue
{
    use HandlesQueueFailure;

    // Dependensi lewat konstruktor: listener antrean tidak mendukung injeksi pada method handle().
    public function __construct(private NotificationRecipients $recipients) {}

    public function handle(ActivityReportSubmitted $event): void
    {
        $activity = $event->report->activity;

        $to = $this->recipients->approvers('programs.approve', (int) $activity->organizational_unit_id, [$event->actor->id]);

        Notification::send($to, WorkflowNotification::reportSubmitted($event->report, $event->actor));
    }
}
