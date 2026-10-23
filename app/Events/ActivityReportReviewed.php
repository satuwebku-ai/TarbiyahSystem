<?php

namespace App\Events;

use App\Models\Admin;
use App\Models\ActivityReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Laporan kegiatan dievaluasi atau dikembalikan untuk perbaikan. */
class ActivityReportReviewed
{
    use Dispatchable, SerializesModels;

    /** @param 'reviewed'|'revision_requested' $decision */
    public function __construct(
        public ActivityReport $report,
        public Admin $reviewer,
        public string $decision,
        public ?string $note = null,
    ) {}
}
