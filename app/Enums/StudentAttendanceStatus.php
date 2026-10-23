<?php

namespace App\Enums;

enum StudentAttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Permit = 'permit';
    case Sick = 'sick';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Permit => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpa',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Present => 'text-bg-success',
            self::Late => 'text-bg-warning',
            self::Permit => 'text-bg-primary',
            self::Sick => 'text-bg-info',
            self::Absent => 'text-bg-danger',
        };
    }
}
