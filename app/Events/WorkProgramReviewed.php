<?php

namespace App\Events;

use App\Models\Admin;
use App\Models\WorkProgram;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Program kerja disetujui atau dikembalikan untuk revisi. */
class WorkProgramReviewed
{
    use Dispatchable, SerializesModels;

    /** @param 'approved'|'rejected' $decision */
    public function __construct(
        public WorkProgram $program,
        public Admin $reviewer,
        public string $decision,
        public ?string $note = null,
    ) {}
}
