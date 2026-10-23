<?php

namespace App\Listeners;

use App\Events\AttendanceCorrectionSubmitted;
use App\Listeners\Concerns\HandlesQueueFailure;
use App\Notifications\WorkflowNotification;
use App\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Koreksi absensi diajukan -> beri tahu peninjau yang cakupannya meliputi pegawai (pengurus; pegawai tidak bisa meninjau koreksinya sendiri). */
class NotifyCorrectionReviewers implements ShouldQueue
{
    use HandlesQueueFailure;

    // Dependensi lewat konstruktor: listener antrean tidak mendukung injeksi pada method handle().
    public function __construct(private NotificationRecipients $recipients) {}

    public function handle(AttendanceCorrectionSubmitted $event): void
    {
        $employee = $event->correction->employee;

        $to = $this->recipients->correctionReviewers($employee);

        Notification::send($to, WorkflowNotification::correctionSubmitted($event->correction));
    }
}
