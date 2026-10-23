<?php

namespace App\Listeners;

use App\Events\WorkProgramSubmitted;
use App\Listeners\Concerns\HandlesQueueFailure;
use App\Notifications\WorkflowNotification;
use App\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Program diajukan -> beri tahu pemegang izin programs.approve yang cakupannya meliputi unit program. */
class NotifyProgramApprovers implements ShouldQueue
{
    use HandlesQueueFailure;

    // Dependensi lewat konstruktor: listener antrean tidak mendukung injeksi pada method handle().
    public function __construct(private NotificationRecipients $recipients) {}

    public function handle(WorkProgramSubmitted $event): void
    {
        $to = $this->recipients->approvers('programs.approve', (int) $event->program->organizational_unit_id, [$event->actor->id]);

        Notification::send($to, WorkflowNotification::programSubmitted($event->program, $event->actor));
    }
}
