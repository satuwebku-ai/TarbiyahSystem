<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendanceCorrection extends Model
{
    protected $fillable = [
        'student_attendance_record_id',
        'previous_status',
        'new_status',
        'previous_notes',
        'new_notes',
        'reason',
        'changed_by_admin_id',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(StudentAttendanceRecord::class, 'student_attendance_record_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by_admin_id');
    }
}
