<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAttendanceSession extends Model
{
    protected $fillable = [
        'classroom_id',
        'attendance_date',
        'name',
        'recorded_by_admin_id',
    ];

    protected function casts(): array
    {
        return ['attendance_date' => 'date:Y-m-d'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(StudentAttendanceRecord::class);
    }
}
