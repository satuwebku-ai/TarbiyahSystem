<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Resigned = 'resigned';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::OnLeave => 'Cuti panjang',
            self::Resigned => 'Mengundurkan diri',
            self::Retired => 'Pensiun',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'badge-soft-success',
            self::OnLeave => 'badge-soft-warning',
            self::Resigned, self::Retired => 'badge-soft-secondary',
        };
    }

    /** Hubungan kerja sudah berakhir (tidak boleh memakai portal / dicatat absensinya). */
    public function isEnded(): bool
    {
        return in_array($this, [self::Resigned, self::Retired], true);
    }
}
