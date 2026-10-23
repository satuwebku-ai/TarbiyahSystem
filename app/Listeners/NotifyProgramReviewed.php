<?php

namespace App\Listeners;

use App\Events\WorkProgramReviewed;
use App\Listeners\Concerns\HandlesQueueFailure;
use App\Notifications\WorkflowNotification;
use App\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Keputusan atas program -> beri tahu pengaju dan pembuat program (selain pemutus). */
class NotifyProgramReviewed implements ShouldQueue
{
    use HandlesQueueFailure;

    // Dependensi lewat konstruktor: listener antrean tidak mendukung injeksi pada method handle().
    public function __construct(private NotificationRecipients $recipients) {}

    public function handle(WorkProgramReviewed $event): void
    {
        $program = $event->program;
        $requesterId = $program->approvals()->value('requested_by_admin_id');

        $to = $this->recipients->admins([$requesterId, $program->created_by_admin_id], [$event->reviewer->id]);

        Notification::send($to, WorkflowNotification::programReviewed($program, $event->reviewer, $event->decision, $event->note));
    }
}
