<?php

namespace App\Listeners;

use App\Events\ActivityReportReviewed;
use App\Listeners\Concerns\HandlesQueueFailure;
use App\Notifications\WorkflowNotification;
use App\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Hasil evaluasi laporan -> beri tahu pengaju laporan dan pembuat kegiatan (selain pengevaluasi). */
class NotifyReportReviewed implements ShouldQueue
{
    use HandlesQueueFailure;

    // Dependensi lewat konstruktor: listener antrean tidak mendukung injeksi pada method handle().
    public function __construct(private NotificationRecipients $recipients) {}

    public function handle(ActivityReportReviewed $event): void
    {
        $report = $event->report;

        $to = $this->recipients->admins(
            [$report->submitted_by_admin_id, $report->activity->created_by_admin_id],
            [$event->reviewer->id],
        );

        Notification::send($to, WorkflowNotification::reportReviewed($report, $event->reviewer, $event->decision, $event->note));
    }
}
