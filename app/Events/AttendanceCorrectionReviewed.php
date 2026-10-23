<?php

namespace App\Events;

use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Koreksi absensi disetujui atau ditolak. Hasilnya dibaca dari $correction->status. */
class AttendanceCorrectionReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public AttendanceCorrection $correction) {}
}
