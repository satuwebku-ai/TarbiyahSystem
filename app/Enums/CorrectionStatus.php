<?php

namespace App\Enums;

enum CorrectionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-soft-warning',
            self::Approved => 'badge-soft-success',
            self::Rejected => 'badge-soft-danger',
        };
    }
}
