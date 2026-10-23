<?php

namespace App\Events;

use App\Models\Admin;
use App\Models\ActivityReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Laporan kegiatan diajukan untuk evaluasi. */
class ActivityReportSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ActivityReport $report, public Admin $actor) {}
}
