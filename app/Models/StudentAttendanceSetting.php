<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendanceSetting extends Model
{
    protected $fillable = [
        'school_id', 'fingerprint_enabled', 'session_name', 'school_days', 'start_time',
        'late_tolerance_minutes', 'checkin_open_time', 'checkin_close_time', 'skip_holidays',
        'auto_absent_enabled', 'auto_absent_time',
    ];

    protected function casts(): array
    {
        return [
            'fingerprint_enabled' => 'boolean',
            'school_days' => 'array',
            'late_tolerance_minutes' => 'integer',
            'skip_holidays' => 'boolean',
            'auto_absent_enabled' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Pengaturan umum (default semua sekolah); bila belum disimpan, memakai config. */
    public static function general(): self
    {
        return static::query()->whereNull('school_id')->first()
            ?? new static(config('student_attendance.defaults'));
    }

    /** Pengaturan efektif sebuah sekolah: khusus sekolah, lalu umum, lalu bawaan config. */
    public static function forSchool(int $schoolId): self
    {
        return static::query()->where('school_id', $schoolId)->first() ?? static::general();
    }

    public function isSchoolDay(int $isoWeekday): bool
    {
        return in_array($isoWeekday, array_map('intval', $this->school_days ?? []), true);
    }

    public function dayLabels(): string
    {
        return collect($this->school_days ?? [])
            ->map(fn ($day) => (int) $day)
            ->sort()
            ->map(fn (int $day) => AttendancePolicy::DAY_NAMES[$day] ?? $day)
            ->implode(', ');
    }
}
