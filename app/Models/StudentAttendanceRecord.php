<?php

namespace App\Models;

use App\Enums\StudentAttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAttendanceRecord extends Model
{
    protected $fillable = [
        'student_attendance_session_id',
        'student_id',
        'status',
        'notes',
        'source',
        'checked_in_at',
        'recorded_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentAttendanceStatus::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StudentAttendanceSession::class, 'student_attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(StudentAttendanceCorrection::class)->latest();
    }
}
