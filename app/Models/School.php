<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    public const STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
    ];

    protected $fillable = [
        'organizational_unit_id',
        'school_level_id',
        'npsn',
        'phone',
        'email',
        'status',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class)->orderByDesc('start_date');
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
