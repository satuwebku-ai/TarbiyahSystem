<?php

namespace App\Events;

use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Pegawai mengajukan koreksi absensi. */
class AttendanceCorrectionSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public AttendanceCorrection $correction) {}
}
