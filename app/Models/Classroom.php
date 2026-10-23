<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'grade_label',
        'capacity',
        'status',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(StudentClassPlacement::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(StudentAttendanceSession::class);
    }
}
