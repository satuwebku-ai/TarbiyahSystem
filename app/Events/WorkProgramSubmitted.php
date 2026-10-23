<?php

namespace App\Events;

use App\Models\Admin;
use App\Models\WorkProgram;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Program kerja diajukan untuk persetujuan. */
class WorkProgramSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public WorkProgram $program, public Admin $actor) {}
}
