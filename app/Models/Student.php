<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    public const STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
        'graduated' => 'Lulus',
        'transferred' => 'Pindah',
    ];

    protected $fillable = [
        'school_id',
        'student_account_id',
        'nis',
        'nisn',
        'fingerprint_pin',
        'name',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Akun login pelajar. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(StudentAccount::class, 'student_account_id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)->orderByDesc('created_at');
    }
}
