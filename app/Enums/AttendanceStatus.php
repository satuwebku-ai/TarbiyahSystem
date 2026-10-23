<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Permit = 'permit';
    case Sick = 'sick';
    case Leave = 'leave';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Permit => 'Izin',
            self::Sick => 'Sakit',
            self::Leave => 'Cuti',
            self::Absent => 'Tidak hadir',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Present => 'badge-soft-success',
            self::Late => 'badge-soft-warning',
            self::Permit, self::Leave => 'badge-soft-primary',
            self::Sick => 'badge-soft-purple',
            self::Absent => 'badge-soft-danger',
        };
    }

    /** Dicatat oleh pengelola (bukan hasil check-in). */
    public function isManual(): bool
    {
        return ! in_array($this, [self::Present, self::Late], true);
    }
}
