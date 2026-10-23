<?php

namespace App\Listeners;

use App\Events\AttendanceCorrectionReviewed;
use App\Listeners\Concerns\HandlesQueueFailure;
use App\Notifications\WorkflowNotification;
use App\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Keputusan atas koreksi -> beri tahu pegawai yang mengajukan (bila punya akun). */
class NotifyCorrectionResult implements ShouldQueue
{
    use HandlesQueueFailure;

    // Dependensi lewat konstruktor: listener antrean tidak mendukung injeksi pada method handle().
    public function __construct(private NotificationRecipients $recipients) {}

    public function handle(AttendanceCorrectionReviewed $event): void
    {
        $correction = $event->correction;

        $this->recipients->users([$correction->employee->user_id])
            ->each(fn ($user) => $user->notify(WorkflowNotification::correctionReviewed($correction)));
    }
}
