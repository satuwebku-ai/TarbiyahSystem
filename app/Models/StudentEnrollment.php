<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentEnrollment extends Model
{
    public const STATUSES = [
        'active' => 'Aktif',
        'completed' => 'Selesai',
        'transferred' => 'Pindah',
        'withdrawn' => 'Mengundurkan diri',
    ];

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'enrollment_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return ['enrollment_date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(StudentClassPlacement::class)->orderByDesc('start_date');
    }
}
